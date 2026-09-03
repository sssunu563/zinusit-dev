<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SnipeItManagedUserService
{
    private ?array $remoteUsers = null;

    private ?array $locations = null;

    private ?array $departments = null;

    private ?array $companies = null;

    public function __construct(
        private readonly SnipeItService $snipe,
    ) {
    }

    public function getFormOptions(): array
    {
        // PERF: Cache form options for 10 minutes — these change very rarely
        // (managers/locations/departments/companies lists)
        return \Illuminate\Support\Facades\Cache::remember('snipeit_form_options', 600, function () {
            // Fetch all 4 list types in a single pool call (12 parallel requests)
            $pool = $this->snipe->requestPool([
                'users_p1'   => ['users',       ['limit' => 500, 'offset' => 0]],
                'users_p2'   => ['users',       ['limit' => 500, 'offset' => 500]],
                'loc_p1'     => ['locations',   ['limit' => 500, 'offset' => 0]],
                'loc_p2'     => ['locations',   ['limit' => 500, 'offset' => 500]],
                'dept_p1'    => ['departments', ['limit' => 500, 'offset' => 0]],
                'dept_p2'    => ['departments', ['limit' => 500, 'offset' => 500]],
                'comp_p1'    => ['companies',   ['limit' => 500, 'offset' => 0]],
                'comp_p2'    => ['companies',   ['limit' => 500, 'offset' => 500]],
            ]);

            $users   = array_merge($pool['users_p1']['rows'] ?? [], $pool['users_p2']['rows'] ?? []);
            $locs    = array_merge($pool['loc_p1']['rows']   ?? [], $pool['loc_p2']['rows']   ?? []);
            $depts   = array_merge($pool['dept_p1']['rows']  ?? [], $pool['dept_p2']['rows']  ?? []);
            $comps   = array_merge($pool['comp_p1']['rows']  ?? [], $pool['comp_p2']['rows']  ?? []);

            return [
                'managers'    => collect($users)->map(fn (array $u) => ['id' => $u['id'], 'name' => $u['name']])
                                    ->filter(fn (array $u) => $u['id'] > 0)->values()->all(),
                'locations'   => $this->mapSimpleOptions($locs),
                'departments' => $this->mapSimpleOptions($depts),
                'companies'   => $this->mapSimpleOptions($comps),
            ];
        });
    }

    public function getProfileForUser(User $user): array
    {
        $remoteUser = $this->findRemoteUserForLocalUser($user);

        if ($remoteUser) {
            return $this->mergeLocalAndRemote($user, $remoteUser);
        }

        return $this->localFallbackProfile($user);
    }

    public function syncAllUsers(): int
    {
        // Fetch up to 1500 users in parallel to bypass 500 cap
        $pool = $this->snipe->requestPool([
            'p1' => ['users', ['limit' => 500, 'offset' => 0]],
            'p2' => ['users', ['limit' => 500, 'offset' => 500]],
            'p3' => ['users', ['limit' => 500, 'offset' => 1000]],
        ]);

        $remoteUsers = array_merge(
            $pool['p1']['rows'] ?? [],
            $pool['p2']['rows'] ?? [],
            $pool['p3']['rows'] ?? []
        );

        $processedCount = 0;

        foreach ($remoteUsers as $remoteUser) {
            $remoteId = (int) ($remoteUser['id'] ?? 0);
            if ($remoteId <= 0) {
                continue;
            }

            $localUser = $this->findLocalUserForRemoteUser($remoteUser);

            if ($localUser) {
                $remoteEmail = $this->normalizeString($remoteUser['email'] ?? null);
                $remoteUsername = $this->normalizeString($remoteUser['username'] ?? null);

                $localUser->update([
                    'name' => $remoteUser['name'] ?: $localUser->name,
                    'email' => $this->preferUniqueValue($localUser, 'email', $remoteEmail),
                    'username' => $this->preferUniqueValue($localUser, 'username', $remoteUsername),
                    'employee_num' => $this->normalizeString($remoteUser['employee_num'] ?? null),
                    'snipeit_user_id' => $remoteId,
                    'snipeit_username' => $remoteUsername,
                    'avatar' => $remoteUser['avatar'] ?? null,
                    'location' => $this->nestedName($remoteUser, 'location'),
                    'department' => $this->nestedName($remoteUser, 'department'),
                    'company' => $this->nestedName($remoteUser, 'company'),
                    'snipeit_synced_at' => now(),
                ]);
            } else {
                // Import new
                User::create([
                    'name' => $remoteUser['name'] ?: 'Snipe-IT User',
                    'email' => $this->normalizeString($remoteUser['email'] ?? null) ?: "user_{$remoteId}@snipeit.local",
                    'username' => $this->normalizeString($remoteUser['username'] ?? null) ?: "snipe_{$remoteId}",
                    'employee_num' => $this->normalizeString($remoteUser['employee_num'] ?? null),
                    'password' => \Illuminate\Support\Str::random(16),
                    'snipeit_user_id' => $remoteId,
                    'snipeit_username' => $this->normalizeString($remoteUser['username'] ?? null),
                    'avatar' => $remoteUser['avatar'] ?? null,
                    'location' => $this->nestedName($remoteUser, 'location'),
                    'department' => $this->nestedName($remoteUser, 'department'),
                    'company' => $this->nestedName($remoteUser, 'company'),
                    'snipeit_synced_at' => now(),
                    'email_verified_at' => now(),
                ]);
            }

            $processedCount++;
        }

        return $processedCount;
    }

    private function findLocalUserForRemoteUser(array $remoteUser): ?User
    {
        $remoteId = (int) ($remoteUser['id'] ?? 0);
        $remoteEmail = $this->normalizeString($remoteUser['email'] ?? null);
        $remoteUsername = $this->normalizeString($remoteUser['username'] ?? null);

        // 1. Primary match: Snipe-IT ID
        if ($remoteId > 0) {
            $match = User::where('snipeit_user_id', $remoteId)->first();
            if ($match) return $match;
        }

        // 2. Secondary match: Email or Username, but ONLY if not linked to a different Snipe-IT ID
        if ($remoteEmail) {
            $match = User::where('email', 'LIKE', $remoteEmail)->first();
            if ($match) {
                // If this local user is already linked to a DIFFERENT Snipe-IT ID, it's a different person.
                if ($match->snipeit_user_id && (int)$match->snipeit_user_id !== $remoteId) {
                    // Rename/archive the old user to free up the email for the new one.
                    // Make sure the archived values are unique even if the same conflicting user
                    // has already been renamed in a previous sync pass.
                    $match->updateQuietly([
                        'email' => $this->uniqueArchiveValue('email', $match, $match->email),
                        'username' => $this->uniqueArchiveValue('username', $match, $match->username),
                    ]);
                    return null; // Force create new
                }
                return $match;
            }
        }

        if ($remoteUsername) {
            $match = User::where('username', 'LIKE', $remoteUsername)->first();
            if ($match) {
                // If this local user is already linked to a DIFFERENT Snipe-IT ID, it's a different person.
                if ($match->snipeit_user_id && (int)$match->snipeit_user_id !== $remoteId) {
                    // Rename/archive the old user to free up the username for the new one.
                    $match->updateQuietly([
                        'username' => $this->uniqueArchiveValue('username', $match, $match->username),
                    ]);
                    return null; // Force create new
                }
                return $match;
            }
        }

        return null;
    }

    public function getProfilesForUsers(iterable $users): array
    {
        $profiles = [];

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $profiles[$user->getKey()] = $this->getProfileForUser($user);
        }

        return $profiles;
    }

    public function createManagedUser(array $data, bool $markVerified = true): User
    {
        $remoteUser = $this->upsertRemoteUser(null, $data);

        $user = new User();
        $this->fillLocalUser($user, $data, $remoteUser, true, $markVerified);

        return $user;
    }

    public function updateManagedUser(
        User $user,
        array $data,
        bool $allowPasswordUpdate = false,
        bool $resetEmailVerification = false,
        bool $markVerified = false,
    ): User {
        $remoteUser = $this->upsertRemoteUser($user->snipeit_user_id, $data);

        $this->fillLocalUser(
            $user,
            $data,
            $remoteUser,
            $allowPasswordUpdate,
            $markVerified,
            $resetEmailVerification,
        );

        return $user;
    }

    private function fillLocalUser(
        User $user,
        array $data,
        array $remoteUser,
        bool $allowPasswordUpdate,
        bool $markVerified,
        bool $resetEmailVerification = false,
    ): void {
        $originalEmail = $user->email;
        $user->fill([
            'name' => $this->buildFullName($data),
            'username' => $this->normalizeString($data['username'] ?? null),
            'email' => $this->normalizeString($data['email'] ?? null),
            'employee_num' => $this->normalizeString($data['employee_num'] ?? null),
            'snipeit_user_id' => Arr::get($remoteUser, 'id'),
            'snipeit_username' => $this->normalizeString(Arr::get($remoteUser, 'username')),
            'avatar' => Arr::get($remoteUser, 'avatar'),
            'location' => $this->nestedName($remoteUser, 'location'),
            'department' => $this->nestedName($remoteUser, 'department'),
            'company' => $this->nestedName($remoteUser, 'company'),
            'snipeit_synced_at' => now(),
        ]);

        if ($allowPasswordUpdate && filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        if ($markVerified) {
            $user->email_verified_at = now();
        } elseif ($resetEmailVerification && $originalEmail !== null && $originalEmail !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->save();
    }

    public function resolveLocationIdByName(?string $name, bool $createIfMissing = true): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        foreach ($this->getLocations() as $location) {
            if (mb_strtolower($location['name']) === mb_strtolower($name)) {
                return (int) $location['id'];
            }
        }

        if (!$createIfMissing) {
            return null;
        }

        try {
            $resp = $this->snipe->createRecord('locations', ['name' => $name]);
            if (($resp['status'] ?? 'error') === 'success') {
                $newId = (int) ($resp['payload']['id'] ?? $resp['id'] ?? 0);
                if ($newId > 0) {
                    $this->locations = null;
                    return $newId;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    public function resolveDepartmentIdByName(?string $name, bool $createIfMissing = true): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        foreach ($this->getDepartments() as $department) {
            if (mb_strtolower($department['name']) === mb_strtolower($name)) {
                return (int) $department['id'];
            }
        }

        if (!$createIfMissing) {
            return null;
        }

        try {
            $resp = $this->snipe->createRecord('departments', ['name' => $name]);
            if (($resp['status'] ?? 'error') === 'success') {
                $newId = (int) ($resp['payload']['id'] ?? $resp['id'] ?? 0);
                if ($newId > 0) {
                    $this->departments = null;
                    return $newId;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    public function resolveCompanyIdByName(?string $name, bool $createIfMissing = true): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        foreach ($this->getCompanies() as $company) {
            if (mb_strtolower($company['name']) === mb_strtolower($name)) {
                return (int) $company['id'];
            }
        }

        if (!$createIfMissing) {
            return null;
        }

        // Attempt to create
        try {
            $resp = $this->snipe->createRecord('companies', ['name' => $name]);
            if (($resp['status'] ?? 'error') === 'success') {
                $newId = (int) ($resp['payload']['id'] ?? $resp['id'] ?? 0);
                if ($newId > 0) {
                    // Flush companies cache so next lookup finds it
                    $this->companies = null;
                    return $newId;
                }
            }
        } catch (\Throwable) {
            // Silently fail and return null
        }

        return null;
    }

    private function upsertRemoteUser(?int $remoteUserId, array $data): array
    {
        $payload = $this->buildRemotePayload($data);
        $response = $remoteUserId
            ? $this->snipe->updateRecord('users', $remoteUserId, $payload)
            : $this->snipe->createRecord('users', $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            throw ValidationException::withMessages($this->normalizeRemoteErrors($response['messages'] ?? null));
        }

        $resolvedRemoteUserId = $this->extractRemoteUserId($response, $remoteUserId);
        $freshRemoteUser = $resolvedRemoteUserId ? $this->safeGetRemoteUser($resolvedRemoteUserId) : null;

        if ($freshRemoteUser) {
            return $freshRemoteUser;
        }

        return array_merge($payload, [
            'id' => $resolvedRemoteUserId,
            'name' => $this->buildFullName($data),
        ]);
    }

    private function buildRemotePayload(array $data): array
    {
        $payload = [
            'first_name'           => $this->normalizeString($data['first_name'] ?? null),
            'last_name'            => $this->normalizeString($data['last_name'] ?? null),
            'username'             => $this->normalizeString($data['username'] ?? null) ?: $this->normalizeString($data['email'] ?? null),
            'email'                => $this->normalizeString($data['email'] ?? null),
            'phone'                => $this->normalizeString($data['phone'] ?? null),
            'jobtitle'             => $this->normalizeString($data['jobtitle'] ?? null),
            'manager_id'           => $this->normalizeInteger($data['manager_id'] ?? null),
            'location_id'          => $this->normalizeInteger($data['location_id'] ?? null),
            'department_id'        => $this->normalizeInteger($data['department_id'] ?? null),
            'company_id'           => $this->normalizeInteger($data['company_id'] ?? null),
            'employee_num'         => $this->normalizeString($data['employee_num'] ?? null),
            'mobile'               => $this->normalizeString($data['mobile'] ?? null),
            'website'              => $this->normalizeString($data['website'] ?? null),
            'notes'                => $this->normalizeString($data['notes'] ?? null),
            'vip'                  => isset($data['vip']) ? (bool) $data['vip'] : null,
            'remote'               => isset($data['remote']) ? (bool) $data['remote'] : null,
            'auto_assign_licenses' => isset($data['auto_assign_licenses']) ? (bool) $data['auto_assign_licenses'] : null,
            'activated'            => 1,
        ];

        return array_filter($payload, fn ($v) => $v !== null && $v !== '');
    }

    private function extractRemoteUserId(array $response, ?int $fallbackId): ?int
    {
        $candidate = Arr::get($response, 'payload.id')
            ?? Arr::get($response, 'id')
            ?? Arr::get($response, 'payload.payload.id')
            ?? $fallbackId;

        $resolved = (int) $candidate;

        return $resolved > 0 ? $resolved : null;
    }

    private function normalizeRemoteErrors(mixed $messages): array
    {
        if (!is_array($messages)) {
            return ['api' => ['Failed to sync user with Snipe-IT.']];
        }

        $normalized = [];

        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                $normalized[(string) $key] = array_map('strval', $value);
                continue;
            }

            $normalized[(string) $key] = [(string) $value];
        }

        return $normalized ?: ['api' => ['Failed to sync user with Snipe-IT.']];
    }

    private function mergeLocalAndRemote(User $user, array $remoteUser): array
    {
        return [
            'first_name'     => $this->normalizeString($remoteUser['first_name'] ?? null) ?: $this->localFallbackProfile($user)['first_name'],
            'last_name'      => $this->normalizeString($remoteUser['last_name'] ?? null) ?: $this->localFallbackProfile($user)['last_name'],
            'username'       => $this->normalizeString($remoteUser['username'] ?? null) ?: $user->username,
            'email'          => $this->normalizeString($remoteUser['email'] ?? null) ?: $user->email,
            'employee_num'   => $this->normalizeString($remoteUser['employee_num'] ?? null) ?: $user->employee_num,
            'phone'          => $this->normalizeString($remoteUser['phone'] ?? null),
            'mobile'         => $this->normalizeString($remoteUser['mobile'] ?? null),
            'jobtitle'       => $this->normalizeString($remoteUser['jobtitle'] ?? null),
            'website'        => $this->normalizeString($remoteUser['website'] ?? null),
            'notes'          => $this->normalizeString($remoteUser['notes'] ?? null),
            'vip'            => (bool) ($remoteUser['vip'] ?? false),
            'remote'         => (bool) ($remoteUser['remote'] ?? false),
            'auto_assign_licenses' => (bool) ($remoteUser['auto_assign_licenses'] ?? false),
            'manager_id'     => $this->nestedId($remoteUser, 'manager'),
            'location_id'    => $this->nestedId($remoteUser, 'location'),
            'department_id'  => $this->nestedId($remoteUser, 'department'),
            'company_id'     => $this->nestedId($remoteUser, 'company'),
            'manager_name'   => $this->nestedName($remoteUser, 'manager'),
            'location_name'  => $this->nestedName($remoteUser, 'location'),
            'department_name'=> $this->nestedName($remoteUser, 'department'),
            'company_name'   => $this->nestedName($remoteUser, 'company'),
            'avatar'         => $remoteUser['avatar'] ?? null,
        ];
    }

    private function localFallbackProfile(User $user): array
    {
        [$firstName, $lastName] = $this->splitName($user->name);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $user->username,
            'email' => $user->email,
            'employee_num' => $user->employee_num,
            'phone' => null,
            'jobtitle' => null,
            'manager_id' => null,
            'location_id' => null,
            'department_id' => null,
            'company_id' => null,
            'manager_name' => '',
            'location_name' => '',
            'department_name' => '',
            'company_name' => '',
        ];
    }

    private function buildFullName(array $data): string
    {
        $firstName = $this->normalizeString($data['first_name'] ?? null);
        $lastName = $this->normalizeString($data['last_name'] ?? null);

        return trim(implode(' ', array_filter([$firstName, $lastName])));
    }

    private function splitName(?string $name): array
    {
        $normalized = trim((string) $name);

        if ($normalized === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        $firstName = array_shift($parts) ?: '';

        return [$firstName, trim(implode(' ', $parts))];
    }

    private function findRemoteUserForLocalUser(User $user): ?array
    {
        // PERF: If we already have the Snipe-IT user ID, fetch it directly
        // instead of loading all 1500 users just to find one match.
        if ($user->snipeit_user_id) {
            $remoteUser = $this->safeGetRemoteUser((int) $user->snipeit_user_id);
            if ($remoteUser) {
                return $remoteUser;
            }
        }

        // Fallback: search by email/username in the full user list
        foreach ($this->getRemoteUsers() as $remoteUser) {
            $remoteId       = (int) ($remoteUser['id'] ?? 0);
            $remoteEmail    = mb_strtolower(trim((string) ($remoteUser['email']    ?? '')));
            $remoteUsername = mb_strtolower(trim((string) ($remoteUser['username'] ?? '')));

            if ($user->email && $remoteEmail !== '' && $remoteEmail === mb_strtolower(trim($user->email))) {
                return $remoteUser;
            }

            if ($user->username && $remoteUsername !== '' && $remoteUsername === mb_strtolower(trim($user->username))) {
                return $remoteUser;
            }
        }

        return null;
    }

    private function safeGetRemoteUser(int $id): ?array
    {
        try {
            $user = $this->snipe->getUser($id);
        } catch (\Throwable) {
            return null;
        }

        return is_array($user) ? $user : null;
    }

    private function getRemoteUsers(): array
    {
        if ($this->remoteUsers !== null) {
            return $this->remoteUsers;
        }

        $pool = $this->snipe->requestPool([
            'p1' => ['users', ['limit' => 500, 'offset' => 0]],
            'p2' => ['users', ['limit' => 500, 'offset' => 500]],
            'p3' => ['users', ['limit' => 500, 'offset' => 1000]],
        ]);

        $this->remoteUsers = array_merge(
            $pool['p1']['rows'] ?? [],
            $pool['p2']['rows'] ?? [],
            $pool['p3']['rows'] ?? []
        );

        return $this->remoteUsers;
    }

    private function getLocations(): array
    {
        if ($this->locations !== null) {
            return $this->locations;
        }

        $pool = $this->snipe->requestPool([
            'p1' => ['locations', ['limit' => 500, 'offset' => 0]],
            'p2' => ['locations', ['limit' => 500, 'offset' => 500]],
            'p3' => ['locations', ['limit' => 500, 'offset' => 1000]],
        ]);

        $this->locations = array_merge(
            $pool['p1']['rows'] ?? [],
            $pool['p2']['rows'] ?? [],
            $pool['p3']['rows'] ?? []
        );

        return $this->locations;
    }

    private function getDepartments(): array
    {
        if ($this->departments !== null) {
            return $this->departments;
        }

        $pool = $this->snipe->requestPool([
            'p1' => ['departments', ['limit' => 500, 'offset' => 0]],
            'p2' => ['departments', ['limit' => 500, 'offset' => 500]],
            'p3' => ['departments', ['limit' => 500, 'offset' => 1000]],
        ]);

        $this->departments = array_merge(
            $pool['p1']['rows'] ?? [],
            $pool['p2']['rows'] ?? [],
            $pool['p3']['rows'] ?? []
        );

        return $this->departments;
    }

    private function getCompanies(): array
    {
        if ($this->companies !== null) {
            return $this->companies;
        }

        $pool = $this->snipe->requestPool([
            'p1' => ['companies', ['limit' => 500, 'offset' => 0]],
            'p2' => ['companies', ['limit' => 500, 'offset' => 500]],
            'p3' => ['companies', ['limit' => 500, 'offset' => 1000]],
        ]);

        $this->companies = array_merge(
            $pool['p1']['rows'] ?? [],
            $pool['p2']['rows'] ?? [],
            $pool['p3']['rows'] ?? []
        );

        return $this->companies;
    }

    private function mapSimpleOptions(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row) => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => trim((string) ($row['name'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['name'] !== '')
            ->values()
            ->all();
    }

    private function normalizeString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function nestedId(array $row, string $key): ?int
    {
        $value = Arr::get($row, $key . '.id');

        if (is_array($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function preferUniqueValue(User $user, string $column, ?string $candidate): ?string
    {
        if ($candidate === null || $candidate === '') {
            return $user->{$column};
        }

        $alreadyOwnedByThisUser = $user->{$column} === $candidate;

        if ($alreadyOwnedByThisUser || $this->isUniqueForUser($column, $candidate, $user)) {
            return $candidate;
        }

        return $user->{$column};
    }

    private function uniqueArchiveValue(string $column, User $user, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $suffix = '_old_' . time() . '_' . $user->getKey();
        $candidate = $value . $suffix;

        $attempt = 0;
        while (User::query()
            ->where($column, $candidate)
            ->whereKeyNot($user->getKey())
            ->exists()) {
            $attempt++;
            $candidate = $value . $suffix . '_' . $attempt;
        }

        return $candidate;
    }

    private function isUniqueForUser(string $column, mixed $value, User $user): bool
    {
        return !User::query()
            ->where($column, $value)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    private function nestedName(array $row, string $key): string
    {
        return trim((string) (Arr::get($row, $key . '.name') ?? ''));
    }
}