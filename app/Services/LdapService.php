<?php

namespace App\Services;

/**
 * LdapService
 *
 * Wraps native PHP ldap_* functions to communicate with LLDAP.
 *
 * LLDAP user DN:  uid=<username>,<LDAP_USERS_OU>
 * Object classes: person, inetOrgPerson
 * Key attributes: uid, cn, sn, givenName, displayName, mail, telephoneNumber, userPassword
 *
 * Requires php ext-ldap to be enabled. Add to php.ini:
 *   extension=ldap
 *
 * Required .env keys (see config/services.php 'ldap' block):
 *   LDAP_HOST, LDAP_PORT, LDAP_BASE_DN
 *   LDAP_BIND_DN  – admin bind DN
 *   LDAP_BIND_PW  – admin bind password
 *   LDAP_USERS_OU – ou=people,dc=example,dc=com
 *   LDAP_SSL      – true for ldaps://
 */
class LdapService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Verify user credentials by attempting a user-level LDAP bind.
     * This is the primary method for replacing Hash::check on login.
     *
     * Accepts either the LLDAP uid  (e.g. "muliana")
     *                  OR an email  (e.g. "muliana@zinus.com").
     *
     * When an email is supplied the method first performs an admin-bind search
     * to resolve the uid, then binds as that user.
     *
     * @param  string  $identifier  uid or email
     * @param  string  $password
     */
    public function authenticate(string $identifier, string $password): bool
    {
        if ($identifier === '' || $password === '') {
            return false;
        }

        try {
            // Resolve to uid when an email address was supplied
            if (str_contains($identifier, '@')) {
                $ldapUser = $this->findUserByEmail($identifier);
                if ($ldapUser === null) {
                    return false;
                }
                $uid = $ldapUser['username'];
            } else {
                $uid = $identifier;
            }

            if ($uid === '') {
                return false;
            }

            $conn   = $this->connect();
            $userDn = $this->buildUserDn($uid);
            $result = @ldap_bind($conn, $userDn, $password);
            ldap_unbind($conn);

            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Find a user AND verify their password in a single LDAP connection.
     *
     * Replaces calling findUserByIdentifier() + authenticate() separately,
     * which would open two TCP connections.
     *
     * Returns the user array on success, or null on failure (not found /
     * wrong password / connection error).
     *
     * @param  string  $identifier  uid or email
     * @param  string  $password
     * @return array<string, mixed>|null
     */
    public function findAndAuthenticate(string $identifier, string $password): ?array
    {
        if ($identifier === '' || $password === '') {
            return null;
        }

        try {
            $conn = $this->connect();

            // -------------------------------------------------------------------
            // Step A: Admin bind to find the user entry (resolves uid OR email)
            // -------------------------------------------------------------------
            $this->adminBind($conn);

            $usersOu    = (string) config('services.ldap.users_ou', config('services.ldap.base_dn', ''));
            $attributes = ['uid', 'cn', 'sn', 'givenName', 'displayName', 'mail', 'telephoneNumber'];

            if (str_contains($identifier, '@')) {
                $filter = '(mail=' . $this->escapeFilter($identifier) . ')';
            } else {
                $filter = '(uid=' . $this->escapeFilter($identifier) . ')';
            }

            $result  = ldap_search($conn, $usersOu, $filter, $attributes, 0, 1);
            $entries = $result !== false ? ldap_get_entries($conn, $result) : false;

            if ($entries === false || empty($entries['count'])) {
                ldap_unbind($conn);

                return null;
            }

            $ldapUser = $this->parseEntry($entries[0]);
            $uid      = $ldapUser['username'];

            if ($uid === '') {
                ldap_unbind($conn);

                return null;
            }

            // -------------------------------------------------------------------
            // Step A2 (optional): Group membership gate — same admin connection,
            // zero extra TCP round-trips.
            // Enable by setting LDAP_LOGIN_GROUP to a LLDAP group cn
            // (e.g. "zinusit_users"). Empty = disabled, all LLDAP users may login.
            // -------------------------------------------------------------------
            $loginGroup = (string) config('services.ldap.login_group', '');

            if ($loginGroup !== '') {
                $groupsOu    = (string) config('services.ldap.groups_ou', config('services.ldap.base_dn', ''));
                $memberDn    = $this->buildUserDn($uid);
                $groupFilter = '(&(objectClass=groupOfUniqueNames)(cn=' . $this->escapeFilter($loginGroup) . ')(uniqueMember=' . $this->escapeFilter($memberDn) . '))';

                $groupResult  = ldap_search($conn, $groupsOu, $groupFilter, ['cn'], 0, 1);
                $groupEntries = $groupResult !== false ? ldap_get_entries($conn, $groupResult) : false;

                if ($groupEntries === false || empty($groupEntries['count'])) {
                    ldap_unbind($conn);

                    return null; // user tidak terdaftar di group yang dibutuhkan
                }
            }

            // -------------------------------------------------------------------
            // Step B: Re-bind on the SAME connection as the user (verify password)
            // -------------------------------------------------------------------
            $userDn    = $this->buildUserDn($uid);
            $bindOk    = @ldap_bind($conn, $userDn, $password);
            ldap_unbind($conn);

            return $bindOk ? $ldapUser : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find a single LDAP user by uid.
     */
    public function findUser(string $username): ?array
    {
        if ($username === '') {
            return null;
        }

        return $this->searchOne('(uid=' . $this->escapeFilter($username) . ')');
    }

    /**
     * Find a single LDAP user by mail attribute.
     */
    public function findUserByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }

        return $this->searchOne('(mail=' . $this->escapeFilter($email) . ')');
    }

    /**
     * Find a user by username or email (whichever yields a result first).
     */
    public function findUserByIdentifier(string $identifier): ?array
    {
        if ($identifier === '') {
            return null;
        }

        $byUid = $this->findUser($identifier);
        if ($byUid !== null) {
            return $byUid;
        }

        if (str_contains($identifier, '@')) {
            return $this->findUserByEmail($identifier);
        }

        return null;
    }

    /**
     * Check if a user belongs to a specific group by its CN.
     */
    public function isUserInGroup(string $username, string $groupCn): bool
    {
        if ($username === '' || $groupCn === '') {
            return false;
        }

        try {
            $conn = $this->connect();
            $this->adminBind($conn);

            $groupsOu = (string) config('services.ldap.groups_ou', config('services.ldap.base_dn', ''));
            $userDn   = $this->buildUserDn($username);
            
            // Filter: find group entry that has the user as a uniqueMember
            $groupFilter = '(&(objectClass=groupOfUniqueNames)(cn=' . $this->escapeFilter($groupCn) . ')(uniqueMember=' . $this->escapeFilter($userDn) . '))';

            $result = ldap_search($conn, $groupsOu, $groupFilter, ['cn'], 0, 1);
            $entries = $result !== false ? ldap_get_entries($conn, $result) : false;
            ldap_unbind($conn);

            return !($entries === false || empty($entries['count']));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Return all users from the LLDAP users OU.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllUsers(): array
    {
        return $this->searchAll('(objectClass=inetOrgPerson)');
    }

    /**
     * Create a new user in LLDAP.
     *
     * Required keys in $data: username, password
     * Optional: first_name, last_name, email, phone
     */
    public function createUser(array $data): bool
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return false;
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? '')) ?: $username;
        $displayName = trim($firstName . ' ' . $lastName) ?: $username;
        $cn = $displayName;
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));

        $entry = [
            'objectClass' => ['person', 'inetOrgPerson'],
            'uid'         => $username,
            'cn'          => $cn,
            'sn'          => $lastName,
            'userPassword' => $password,
        ];

        if ($firstName !== '') {
            $entry['givenName'] = $firstName;
        }

        if ($displayName !== '') {
            $entry['displayName'] = $displayName;
        }

        if ($email !== '') {
            $entry['mail'] = $email;
        }

        if ($phone !== '') {
            $entry['telephoneNumber'] = $phone;
        }

        try {
            $conn = $this->connect();
            $this->adminBind($conn);
            $dn = $this->buildUserDn($username);
            $result = @ldap_add($conn, $dn, $entry);
            ldap_unbind($conn);

            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Update mutable attributes of an existing LDAP user.
     * Skips empty values so partial updates are safe.
     *
     * @param  string  $username  Current uid (used to locate the entry)
     * @param  array   $data      Keys: first_name, last_name, email, phone
     */
    public function updateUser(string $username, array $data): bool
    {
        if ($username === '') {
            return false;
        }

        $modifications = [];

        $firstName = isset($data['first_name']) ? trim((string) $data['first_name']) : null;
        $lastName = isset($data['last_name']) ? trim((string) $data['last_name']) : null;

        if ($firstName !== null && $firstName !== '') {
            $modifications['givenName'] = [$firstName];
        }

        if ($lastName !== null && $lastName !== '') {
            $modifications['sn'] = [$lastName];
        }

        if ($firstName !== null || $lastName !== null) {
            // Rebuild cn / displayName when name parts change
            $existingUser = $this->findUser($username);
            $resolvedFirst = $firstName ?? (string) ($existingUser['first_name'] ?? '');
            $resolvedLast = $lastName ?? (string) ($existingUser['last_name'] ?? $username);
            $displayName = trim($resolvedFirst . ' ' . $resolvedLast) ?: $username;
            $modifications['cn'] = [$displayName];
            $modifications['displayName'] = [$displayName];
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($email !== null && $email !== '') {
            $modifications['mail'] = [$email];
        }

        $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
        if ($phone !== null && $phone !== '') {
            $modifications['telephoneNumber'] = [$phone];
        }

        if (empty($modifications)) {
            return true; // Nothing to update
        }

        try {
            $conn = $this->connect();
            $this->adminBind($conn);
            $dn = $this->buildUserDn($username);
            $result = @ldap_mod_replace($conn, $dn, $modifications);
            ldap_unbind($conn);

            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete a user from LLDAP.
     */
    public function deleteUser(string $username): bool
    {
        if ($username === '') {
            return false;
        }

        try {
            $conn = $this->connect();
            $this->adminBind($conn);
            $dn = $this->buildUserDn($username);
            $result = @ldap_delete($conn, $dn);
            ldap_unbind($conn);

            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Change an existing LDAP user's password.
     * LLDAP accepts plain-text passwords via userPassword; it hashes internally.
     */
    public function changePassword(string $username, string $newPassword): bool
    {
        if ($username === '' || $newPassword === '') {
            return false;
        }

        try {
            $conn = $this->connect();
            $this->adminBind($conn);
            $dn = $this->buildUserDn($username);
            $result = @ldap_mod_replace($conn, $dn, ['userPassword' => [$newPassword]]);
            ldap_unbind($conn);

            return (bool) $result;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Test admin connectivity. Returns true if admin bind succeeds.
     */
    public function testConnection(): bool
    {
        try {
            $conn = $this->connect();
            $this->adminBind($conn);
            ldap_unbind($conn);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Open an LDAP connection handle.
     *
     * @return \LDAP\Connection
     * @throws \RuntimeException
     */
    private function connect(): mixed
    {
        $host = (string) config('services.ldap.host', '127.0.0.1');
        $port = (int) config('services.ldap.port', 389);
        $ssl  = (bool) config('services.ldap.ssl', false);

        $uri = $ssl
            ? "ldaps://{$host}:{$port}"
            : "ldap://{$host}:{$port}";

        // ldap_connect() returns false only on malformed URI (rare); connection
        // errors surface at ldap_bind time.
        $conn = ldap_connect($uri);

        if ($conn === false) {
            throw new \RuntimeException("LDAP: cannot connect to {$uri}");
        }

        $timeout = (int) config('services.ldap.timeout', 5);

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, $timeout);

        return $conn;
    }

    /**
     * Perform admin bind. Throws on failure.
     */
    private function adminBind(mixed $conn): void
    {
        $bindDn = (string) config('services.ldap.bind_dn', '');
        $bindPw = (string) config('services.ldap.bind_pw', '');

        if (!@ldap_bind($conn, $bindDn, $bindPw)) {
            throw new \RuntimeException('LDAP admin bind failed: ' . ldap_error($conn));
        }
    }

    /**
     * Build the full DN for a user entry.
     */
    private function buildUserDn(string $username): string
    {
        $usersOu = (string) config('services.ldap.users_ou', config('services.ldap.base_dn', ''));

        return "uid={$username},{$usersOu}";
    }

    /**
     * Search the users OU for the first entry matching $filter.
     */
    private function searchOne(string $filter): ?array
    {
        $results = $this->searchAll($filter, 1);

        return $results[0] ?? null;
    }

    /**
     * Search the users OU and return all matching entries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchAll(string $filter, int $sizeLimit = 0): array
    {
        $attributes = ['uid', 'cn', 'sn', 'givenName', 'displayName', 'mail', 'telephoneNumber', 'o', 'company', 'l', 'streetAddress'];

        try {
            $conn = $this->connect();
            $this->adminBind($conn);

            $usersOu = (string) config('services.ldap.users_ou', config('services.ldap.base_dn', ''));

            $result = ldap_search($conn, $usersOu, $filter, $attributes, 0, $sizeLimit);

            if ($result === false) {
                ldap_unbind($conn);

                return [];
            }

            $entries = ldap_get_entries($conn, $result);
            ldap_unbind($conn);

            if ($entries === false || empty($entries['count'])) {
                return [];
            }

            $users = [];
            for ($i = 0; $i < $entries['count']; $i++) {
                $users[] = $this->parseEntry($entries[$i]);
            }

            return $users;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Normalize a raw LDAP entry into a flat associative array.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function parseEntry(array $entry): array
    {
        $uid  = $this->firstValue($entry, 'uid');
        $givenName = $this->firstValue($entry, 'givenname');
        $sn  = $this->firstValue($entry, 'sn');
        $cn  = $this->firstValue($entry, 'cn');
        $displayName = $this->firstValue($entry, 'displayname') ?: $cn;

        return [
            'dn'           => $entry['dn'] ?? '',
            'username'     => $uid,
            'uid'          => $uid,
            'first_name'   => $givenName,
            'last_name'    => $sn,
            'name'         => $displayName ?: trim("{$givenName} {$sn}") ?: $uid,
            'email'        => $this->firstValue($entry, 'mail'),
            'phone'        => $this->firstValue($entry, 'telephonenumber'),
            'company_name' => $this->firstValue($entry, 'o') ?: $this->firstValue($entry, 'company'),
            'location_name'=> $this->firstValue($entry, 'l') ?: $this->firstValue($entry, 'streetaddress'),
        ];
    }

    /**
     * Extract the first value of a multi-value LDAP attribute, or empty string.
     */
    private function firstValue(array $entry, string $attribute): string
    {
        $lowerKey = strtolower($attribute);
        $value = $entry[$lowerKey] ?? $entry[$attribute] ?? null;

        if (is_array($value)) {
            return trim((string) ($value[0] ?? ''));
        }

        return trim((string) ($value ?? ''));
    }

    /**
     * Escape a value for use in an LDAP search filter.
     */
    private function escapeFilter(string $value): string
    {
        return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
    }
}
