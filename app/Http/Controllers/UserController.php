<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Models\ActionLog;
use App\Services\LdapService;
use App\Services\SnipeItManagedUserService;
use App\Services\SnipeItService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly SnipeItManagedUserService $managedUsers,
        private readonly SnipeItService $snipe,
        private readonly LdapService $ldap,
        private readonly SnipeItController $snipeItController,
    ) {
    }

    // =========================================================================
    // Criterion 2 – User List: Snipe-IT as source of truth
    // =========================================================================

    /**
     * Display user list sourced directly from Snipe-IT, enriched with
     * local DB linkage info (local ID, last sync timestamp, etc).
     */
    public function index(): Response
    {
        // Pull all users from Snipe-IT (up to 500; adjust limit as needed)
        $remoteUsers = $this->snipe->fetchRows('users', ['limit' => 500, 'sort' => 'name', 'order' => 'asc'], 500);

        // Build a lookup map of local users keyed by snipeit_user_id for enrichment
        $localBySnipeId = User::query()
            ->whereNotNull('snipeit_user_id')
            ->get(['id', 'snipeit_user_id', 'snipeit_synced_at', 'email_verified_at'])
            ->keyBy('snipeit_user_id');

        $users = collect($remoteUsers)->map(function (array $remote) use ($localBySnipeId) {
            $remoteId = (int) ($remote['id'] ?? 0);
            $local    = $localBySnipeId->get($remoteId);

            return [
                // --- Snipe-IT identity ---
                'snipeit_user_id'  => $remoteId,
                'snipeit_username' => $remote['username'] ?? null,
                // --- Profile fields ---
                'name'          => $remote['name'] ?? null,
                'first_name'    => $remote['first_name'] ?? null,
                'last_name'     => $remote['last_name'] ?? null,
                'email'         => $remote['email'] ?? null,
                'phone'         => $remote['phone'] ?? $remote['mobile'] ?? null,
                'jobtitle'      => $remote['jobtitle'] ?? null,
                'employee_num'  => $remote['employee_num'] ?? null,
                'manager_name'  => isset($remote['manager']) ? ($remote['manager']['name'] ?? null) : null,
                'location_name' => isset($remote['location']) ? ($remote['location']['name'] ?? null) : null,
                'department_name' => isset($remote['department']) ? ($remote['department']['name'] ?? null) : null,
                'company_name'  => isset($remote['company']) ? ($remote['company']['name'] ?? null) : null,
                // --- Snipe-IT role / activation flags ---
                'activated'         => (bool) ($remote['activated'] ?? false),
                'ldap_import'       => (bool) ($remote['ldap_import'] ?? false),
                'permissions'       => $remote['permissions'] ?? [],
                // --- Local linkage ---
                'id'                => $local?->id,
                'email_verified_at' => $local?->email_verified_at?->toIso8601String(),
                'snipeit_synced_at' => $local?->snipeit_synced_at?->toIso8601String(),
            ];
        })->values();

        return Inertia::render('Users/Index', [
            'users'  => $users,
            'status' => session('status'),
            'options' => $this->managedUsers->getFormOptions(),
        ]);
    }

    /**
     * API-like endpoint to fetch full edit data for a user to be used in a modal.
     */
    public function getEditData(User $user): \Illuminate\Http\JsonResponse
    {
        // Pull full Snipe-IT profile for this user
        $snipeProfile = [];
        if ($user->snipeit_user_id) {
            $resp = $this->snipe->request("users/{$user->snipeit_user_id}");
            if (!empty($resp['id'])) {
                $snipeProfile = $resp;
            }
        }

        $mergedProfile = array_merge(
            $this->managedUsers->getProfileForUser($user),
            [
                'id'                   => $user->id,
                'name'                 => $user->name,
                'username'             => $user->username ?? $snipeProfile['username'] ?? '',
                'email'                => $user->email   ?? $snipeProfile['email']    ?? '',
                'mobile'               => $snipeProfile['mobile']  ?? null,
                'website'              => $snipeProfile['website'] ?? null,
                'notes'                => $snipeProfile['notes']   ?? null,
                'vip'                  => (bool) ($snipeProfile['vip']                  ?? false),
                'remote'               => (bool) ($snipeProfile['remote']               ?? false),
                'auto_assign_licenses' => (bool) ($snipeProfile['auto_assign_licenses'] ?? false),
            ]
        );

        return response()->json($mergedProfile);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'options' => $this->managedUsers->getFormOptions(),
        ]);
    }

    // =========================================================================
    // Criterion 4 – Create: dual-write to Snipe-IT + LLDAP
    // =========================================================================

    /**
     * Create user in Snipe-IT (via SnipeItManagedUserService) then mirror
     * the account to LLDAP so SSO credentials are immediately available.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Step 1: write to Snipe-IT and local DB
        $user = $this->managedUsers->createManagedUser($data);

        // Step 2: mirror credentials to LLDAP
        if (filled($data['username'] ?? null) && filled($data['password'] ?? null)) {
            $this->ldap->createUser([
                'username'   => $data['username'],
                'password'   => $data['password'],
                'first_name' => $data['first_name'] ?? null,
                'last_name'  => $data['last_name'] ?? null,
                'email'      => $data['email'] ?? null,
                'phone'      => $data['phone'] ?? null,
            ]);
        }

        // Log user creation
        try {
            ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => 'created',
                'item_type'   => User::class,
                'item_id'     => $user->id,
                'target_type' => User::class,
                'target_id'   => $user->id,
                'note'        => "User '{$user->name}' ({$user->email}) dibuat",
                'log_meta'    => ['username' => $user->username, 'email' => $user->email],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write user create log', ['error' => $e->getMessage()]);
        }

        return to_route('users.index')->with('status', 'User berhasil ditambahkan.');
    }

    public function edit(User $user): Response
    {
        // Pull full Snipe-IT profile for this user
        $snipeProfile = [];
        if ($user->snipeit_user_id) {
            $resp = $this->snipe->request("users/{$user->snipeit_user_id}");
            if (!empty($resp['id'])) {
                $snipeProfile = $resp;
            }
        }

        $mergedProfile = array_merge(
            $this->managedUsers->getProfileForUser($user),
            [
                'id'                   => $user->id,
                'name'                 => $user->name,
                'username'             => $user->username ?? $snipeProfile['username'] ?? '',
                'email'                => $user->email   ?? $snipeProfile['email']    ?? '',
                'mobile'               => $snipeProfile['mobile']  ?? null,
                'website'              => $snipeProfile['website'] ?? null,
                'notes'                => $snipeProfile['notes']   ?? null,
                'vip'                  => (bool) ($snipeProfile['vip']                  ?? false),
                'remote'               => (bool) ($snipeProfile['remote']               ?? false),
                'auto_assign_licenses' => (bool) ($snipeProfile['auto_assign_licenses'] ?? false),
            ]
        );

        return Inertia::render('Users/Edit', [
            'user'    => $mergedProfile,
            'options' => $this->managedUsers->getFormOptions(),
        ]);
    }

    // =========================================================================
    // Criterion 4 – Update: dual-write to Snipe-IT + LLDAP
    // =========================================================================

    /**
     * Update user in Snipe-IT + local DB, then push changes to LLDAP.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Step 1: update Snipe-IT and local DB
        $this->managedUsers->updateManagedUser(
            $user,
            $data,
            allowPasswordUpdate: true,
            markVerified: true,
        );

        // Step 2: push profile changes to LLDAP
        $ldapUsername = $user->username;
        if ($ldapUsername) {
            $this->ldap->updateUser($ldapUsername, [
                'first_name' => $data['first_name'] ?? null,
                'last_name'  => $data['last_name'] ?? null,
                'email'      => $data['email'] ?? null,
                'phone'      => $data['phone'] ?? null,
            ]);

            // If a new password was provided, push it to LLDAP
            if (filled($data['password'] ?? null)) {
                $this->ldap->changePassword($ldapUsername, $data['password']);
            }
        }

        // Log user update
        try {
            ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => 'updated',
                'item_type'   => User::class,
                'item_id'     => $user->id,
                'target_type' => User::class,
                'target_id'   => $user->id,
                'note'        => "User '{$user->name}' ({$user->email}) diperbarui",
                'log_meta'    => ['username' => $user->username, 'email' => $user->email],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write user update log', ['error' => $e->getMessage()]);
        }

        return to_route('users.index')->with('status', 'User berhasil diperbarui.');
    }

    /**
     * Update only the password for a user.
     */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::default(), 'confirmed'],
        ]);

        $password = $request->input('password');

        // Update local password
        $user->update(['password' => \Hash::make($password)]);

        // Push to LLDAP if username exists
        if ($user->username) {
            try {
                $this->ldap->changePassword($user->username, $password);
            } catch (\Exception $e) {
                \Log::error('Failed to change LLDAP password: ' . $e->getMessage());
                return back()->with('error', 'Password lokal diperbarui, namun gagal diperbarui di LLDAP.');
            }
        }

        // Push to Snipe-IT if ID exists
        if ($user->snipeit_user_id) {
            $this->snipe->updateRecord('users', $user->snipeit_user_id, [
                'password' => $password,
                'password_confirmation' => $password
            ]);
        }

        // Log password change
        try {
            ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => 'password_changed',
                'item_type'   => User::class,
                'item_id'     => $user->id,
                'target_type' => User::class,
                'target_id'   => $user->id,
                'note'        => "Password user '{$user->name}' diperbarui",
                'log_meta'    => ['username' => $user->username],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write password change log', ['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Password user berhasil diperbarui.');
    }
    // =========================================================================

    /**
     * Display the 2-tab user detail page.
     * Tab 1 – Detail: LLDAP profile fused with Snipe-IT profile fields.
     * Tab 2 – Role & Akses: Snipe-IT permissions / groups for the user.
     */
    public function show(User $user): Response
    {
        $snipeId = $user->snipeit_user_id;
        $snipeUser = [];

        // --- LLDAP profile (local, fast) ---
        $ldapUser = [];
        if ($user->username) {
            $ldapUser = $this->ldap->findUser($user->username) ?? [];
        }

        // --- Local DB queries (no network cost) ---
        $localDocs = [];
        if ($snipeId) {
            $localDocs = \App\Models\Stb::where('user_id', $snipeId)
                ->with('items')
                ->latest()
                ->get()
                ->map(fn($stb) => [
                    'id'           => $stb->id,
                    'doc_no'       => sprintf("%s-%s-%s-%04d", 
                                        $stb->document_type === 'loan' ? 'LOAN' : 'STB',
                                        strtoupper(substr($stb->location_name ?: 'UNK', 0, 3)),
                                        $stb->created_at->format('ym'),
                                        $stb->id
                                      ),
                    'type'         => $stb->document_type,
                    'movement'     => $stb->movement_type,
                    'status'       => $stb->is_completed ? 'completed' : ($stb->cancelled_at ? 'cancelled' : 'pending'),
                    'deliver_date' => $stb->deliver_date?->toDateString(),
                    'items'        => $stb->items->map(fn($i) => $i->nama)->join(', '),
                    'url'          => $stb->document_type === 'loan' ? route('peminjaman.show', $stb->id) : route('stb.show', $stb->id),
                ]);
        }

        // Local ActionLogs
        $localLogs = ActionLog::where(function ($q) use ($user, $snipeId) {
            $q->where(function ($sq) use ($user) {
                $sq->where('target_type', User::class)->where('target_id', $user->id);
            });
            if ($snipeId) {
                $q->orWhere(function ($sq) use ($snipeId) {
                    $sq->where('snipeit_type', 'user')->where('snipeit_id', $snipeId);
                });
            }
        })->with('user')->latest()->get();

        // -----------------------------------------------------------------------
        // FIX #6 PERFORMANCE: Fire all Snipe-IT requests concurrently via HTTP Pool
        // -----------------------------------------------------------------------
        $userAssets       = [];
        $userLicenses     = [];
        $userAccessories  = [];
        $userConsumables  = [];
        $userFiles        = [];
        $userHistory      = [];
        $userEulas        = [];
        $managedUsers     = [];
        $managedLocations = [];

        if ($snipeId) {
            // PERF: First pool — critical data needed to render the page
            $poolResults = $this->snipe->requestPool([
                'profile'          => ["users/{$snipeId}", []],
                'assets'           => ["users/{$snipeId}/assets", []],
                'licenses'         => ["users/{$snipeId}/licenses", []],
                'accessories'      => ["users/{$snipeId}/accessories", []],
                'files'            => ["users/{$snipeId}/files", []],
                'eulas'            => ["users/{$snipeId}/eulas", []],
                // PERF: Only first page for history and consumables — most users < 500 entries
                'cons_p1'   => ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'action_type' => 'checkout', 'limit' => 500, 'offset' => 0]],
                'hist_p1'   => ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'limit' => 500, 'offset' => 0]],
                'managed_users'    => ['users',     ['manager_id' => $snipeId, 'limit' => 200]],
                'managed_locations'=> ['locations', ['manager_id' => $snipeId, 'limit' => 200]],
            ]);

            // PERF: Only fetch extra pages if the first page was full (500 rows)
            $hist1Rows = $poolResults['hist_p1']['rows'] ?? [];
            $cons1Rows = $poolResults['cons_p1']['rows'] ?? [];
            $needMoreHistory    = count($hist1Rows) >= 500;
            $needMoreConsumable = count($cons1Rows) >= 500;

            $extraPool = [];
            if ($needMoreHistory) {
                $extraPool['hist_p2'] = ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'limit' => 500, 'offset' => 500]];
                $extraPool['hist_p3'] = ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'limit' => 500, 'offset' => 1000]];
            }
            if ($needMoreConsumable) {
                $extraPool['cons_p2'] = ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'action_type' => 'checkout', 'limit' => 500, 'offset' => 500]];
                $extraPool['cons_p3'] = ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'action_type' => 'checkout', 'limit' => 500, 'offset' => 1000]];
            }
            if (!empty($extraPool)) {
                $extraResults = $this->snipe->requestPool($extraPool);
                $poolResults  = array_merge($poolResults, $extraResults);
            }

            $profileResp = $poolResults['profile'] ?? [];
            $snipeUser   = ($profileResp['id'] ?? null) ? $profileResp : [];

            $userAssets      = $poolResults['assets']['rows']      ?? (is_array($poolResults['assets'] ?? null) ? $poolResults['assets'] : []);
            $userLicenses    = $poolResults['licenses']['rows']    ?? (is_array($poolResults['licenses'] ?? null) ? $poolResults['licenses'] : []);
            $userAccessories = $poolResults['accessories']['rows'] ?? (is_array($poolResults['accessories'] ?? null) ? $poolResults['accessories'] : []);
            $userFiles       = $poolResults['files']['rows']       ?? (is_array($poolResults['files'] ?? null) ? $poolResults['files'] : []);
            $userEulas       = $poolResults['eulas']['rows']       ?? (is_array($poolResults['eulas'] ?? null) ? $poolResults['eulas'] : []);
            
            // Merge history pages (extra pages only fetched when first page was full)
            $userHistory = array_merge(
                $poolResults['hist_p1']['rows'] ?? [],
                $poolResults['hist_p2']['rows'] ?? [],
                $poolResults['hist_p3']['rows'] ?? []
            );

            // Merge consumable pages (extra pages only fetched when first page was full)
            $mergedConsLogs = array_merge(
                $poolResults['cons_p1']['rows'] ?? [],
                $poolResults['cons_p2']['rows'] ?? [],
                $poolResults['cons_p3']['rows'] ?? []
            );

            // Build consumable list from merged checkout log
            if (!empty($mergedConsLogs)) {
                $uniqueCons = [];
                foreach ($mergedConsLogs as $row) {
                    if (($row['item']['type'] ?? '') === 'consumable') {
                        $cid = $row['item']['id'];
                        $qty = (int) ($row['qty'] ?? 1);
                        if (!isset($uniqueCons[$cid])) {
                            $uniqueCons[$cid] = [
                                'id'          => $cid,
                                'name'        => $row['item']['name'] ?? '',
                                'checkout_at' => $row['created_at']['datetime'] ?? null,
                                'note'        => $row['note'] ?? '',
                                'qty'         => $qty,
                            ];
                        } else {
                            $uniqueCons[$cid]['qty'] += $qty;
                        }
                    }
                }
                $userConsumables = array_values($uniqueCons);
            }

            $managedUsers = collect($poolResults['managed_users']['rows'] ?? [])->map(fn($u) => [
                'id' => $u['id'], 'name' => $u['name'], 'username' => $u['username'],
                'email' => $u['email'], 'avatar' => $u['avatar'],
            ])->values()->all();

            $managedLocations = collect($poolResults['managed_locations']['rows'] ?? [])->map(fn($l) => [
                'id' => $l['id'], 'name' => $l['name'], 'currency' => $l['currency'],
            ])->values()->all();
        }

        // --- Merge profile: LLDAP is primary source; Snipe-IT fills gaps ---
        $profile = [
            'id'             => $user->id,
            'name'           => $ldapUser['name']       ?? $snipeUser['name']      ?? $user->name,
            'first_name'     => $ldapUser['first_name'] ?? $snipeUser['first_name'] ?? null,
            'last_name'      => $ldapUser['last_name']  ?? $snipeUser['last_name']  ?? null,
            'username'       => $ldapUser['username']   ?? $user->username,
            'display_name'   => $snipeUser['name']      ?? $user->name,
            'email'          => $ldapUser['email']      ?? $snipeUser['email']     ?? $user->email,
            'phone'          => $ldapUser['phone']      ?? $snipeUser['phone']     ?? $snipeUser['mobile'] ?? null,
            // Snipe-IT profile fields
            'jobtitle'         => $snipeUser['jobtitle']       ?? null,
            'employee_num'     => $snipeUser['employee_num']   ?? $user->employee_num ?? null,
            'manager_name'     => $snipeUser['manager']['name']    ?? null,
            'manager_id'       => $snipeUser['manager']['id']      ?? null,
            'location_name'    => $snipeUser['location']['name']   ?? null,
            'location_id'      => $snipeUser['location']['id']     ?? null,
            'department_name'  => $snipeUser['department']['name'] ?? null,
            'department_id'    => $snipeUser['department']['id']   ?? null,
            'company_name'     => $snipeUser['company']['name']    ?? null,
            'company_id'       => $snipeUser['company']['id']      ?? null,
            // Extra Snipe-IT user flags
            'vip'                  => (bool) ($snipeUser['vip']                  ?? false),
            'remote'               => (bool) ($snipeUser['remote']               ?? false),
            'activated'            => (bool) ($snipeUser['activated']            ?? false),
            'ldap_import'          => (bool) ($snipeUser['ldap_import']          ?? false),
            'auto_assign_licenses' => (bool) ($snipeUser['auto_assign_licenses'] ?? false),
            'two_factor_enrolled'  => (bool) ($snipeUser['two_factor_enrolled']  ?? false),
            'two_factor_optin'     => (bool) ($snipeUser['two_factor_optin']     ?? false),
            // Avatar (with cache buster to ensure immediate update after change)
            'avatar'               => isset($snipeUser['avatar']) ? $snipeUser['avatar'] . (str_contains($snipeUser['avatar'], '?') ? '&' : '?') . 't=' . time() : null,
            // Groups
            'groups'               => $snipeUser['groups']['rows'] ?? [],
            // Timestamps
            'last_login'           => $snipeUser['last_login']['datetime']          ?? null,
            'snipeit_created_at'   => $snipeUser['created_at']['datetime']          ?? null,
            'snipeit_user_id'      => $snipeId,
            'snipeit_synced_at'    => $user->snipeit_synced_at?->toIso8601String(),
            'email_verified_at'    => $user->email_verified_at?->toIso8601String(),
            'created_at'           => $user->created_at?->toIso8601String(),
            // Source indicator
            'ldap_source'          => !empty($ldapUser),
            // Item Counts — consumables_count always uses actual computed value (FIX #3)
            'assets_count'         => $snipeUser['assets_count']      ?? count($userAssets),
            'licenses_count'       => $snipeUser['licenses_count']    ?? count($userLicenses),
            'accessories_count'    => $snipeUser['accessories_count'] ?? count($userAccessories),
            'consumables_count'    => count($userConsumables),
        ];

        // --- Permissions / role flags ---
        $permissions  = $snipeUser['permissions'] ?? [];
        $isSuperAdmin = ($permissions['superuser'] ?? '0') === '1';
        $isAdmin      = ($permissions['admin'] ?? '0') === '1' || $isSuperAdmin;

        $roleAccess = [
            'is_superuser'       => $isSuperAdmin,
            'is_admin'           => $isAdmin,
            'permissions'        => $permissions,
            'assets_count'       => $profile['assets_count'],
            'licenses_count'     => $profile['licenses_count'],
            'accessories_count'  => $profile['accessories_count'],
            'consumables_count'  => $profile['consumables_count'],
        ];

        // --- Transform sub-resource rows ---
        $assetToStb = \App\Models\StbItem::whereIn('snipeit_asset_id', collect($userAssets)->pluck('id'))
            ->get(['snipeit_asset_id', 'stb_id'])
            ->keyBy('snipeit_asset_id');

        $assets = collect($userAssets)->map(fn (array $a) => [
            'id'             => $a['id']             ?? null,
            'asset_tag'      => $a['asset_tag']      ?? '-',
            'name'           => $a['name']           ?? '-',
            'serial'         => $a['serial']         ?? '-',
            'inventory_number'=> $a['otherserial']    ?? '-',
            'model_name'     => $a['model']['name']  ?? '-',
            'category_name'  => $a['category']['name'] ?? '-',
            'status_name'    => $a['status_label']['name'] ?? '-',
            'status_type'    => $a['status_label']['status_type'] ?? null,
            'location_name'  => $a['location']['name'] ?? '-',
            'image'          => $a['image']          ?? null,
            'purchase_cost'  => $a['purchase_cost']  ?? null,
            'book_value'     => $a['book_value']     ?? null,
            'checkout_at'    => $a['last_checkout']['formatted'] ?? null,
            'stb_id'         => $assetToStb->get($a['id'])?->stb_id,
        ])->values()->all();

        $licenses = collect($userLicenses)->map(fn (array $l) => [
            'id'                  => $l['id']                  ?? null,
            'name'                => $l['name']                ?? '-',
            'product_key'         => $l['serial']              ?? '-',
            'purchase_cost'       => $l['purchase_cost']       ?? null,
            'purchase_order'      => $l['purchase_order']      ?? '-',
            'order_number'        => $l['order_number']        ?? '-',
        ])->values()->all();

        $accessories = collect($userAccessories)->map(fn (array $ac) => [
            'id'            => $ac['id']            ?? null,
            'name'          => $ac['name']          ?? '-',
            'serial'        => $ac['serial']        ?? '-',
            'category_name' => $ac['category']['name'] ?? '-',
            'checkout_at'   => $ac['created_at']['formatted'] ?? null,
            'notes'         => $ac['notes']         ?? null,
            'unit_cost'     => $ac['purchase_cost']  ?? null,
        ])->values()->all();

        $consumables = collect($userConsumables)->map(fn (array $c) => [
            'id'        => $c['id']        ?? null,
            'name'      => $c['name']      ?? '-',
            'unit_cost' => null, // Not available in activity log
            'qty'       => (int) ($c['qty'] ?? 1),
            'checkout_at'=> $c['checkout_at'] ?? null,
            'notes'     => $c['note']      ?? null,
        ])->values()->all();

        // --- UNIFIED ASSETS (Combined Hardware, License, Accessory) ---
        // Use SnipeItController::userAssets() for non-hardware to reuse the same
        // deep-fetch + note-parsing logic (SN: / Ref:) used by STB Return form.
        $licenseItems = [];
        $accessoryItems = [];
        if ($snipeId) {
            try {
                $licenseItems  = json_decode($this->snipeItController->userAssets((int) $snipeId, 'license')->getContent(), true) ?? [];
                $accessoryItems = json_decode($this->snipeItController->userAssets((int) $snipeId, 'accessories')->getContent(), true) ?? [];
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch license/accessory items via SnipeItController: ' . $e->getMessage());
            }
        }

        $unifiedAssets = collect($assets)->map(fn($a) => array_merge($a, ['type' => 'hardware']))
            ->concat(collect($licenseItems)->map(fn($l) => [
                'id'            => $l['id'],
                'name'          => $l['name'] ?? '-',
                'model_name'    => $l['name'] ?? '-',
                'asset_tag'     => $l['inventory_number'] ?? $l['otherserial'] ?? '-',
                'serial'        => $l['serial'] ?? '-',
                'category_name' => $l['type_name'] ?? 'License',
                'type'          => 'license',
                'status_name'   => 'Active',
                'status_type'   => 'deployable',
                'checkout_at'   => null,
            ]))
            ->concat(collect($accessoryItems)->map(fn($ac) => [
                'id'            => $ac['id'],
                'name'          => $ac['name'] ?? '-',
                'model_name'    => $ac['name'] ?? '-',
                'asset_tag'     => $ac['inventory_number'] ?? $ac['otherserial'] ?? '-',
                'serial'        => $ac['serial'] ?? '-',
                'category_name' => $ac['type_name'] ?? 'Accessory',
                'type'          => 'accessory',
                'status_name'   => 'Assigned',
                'status_type'   => 'deployable',
                'checkout_at'   => null,
            ]))->values()->all();

        // --- Transform files ---
        $files = collect($userFiles)->map(fn (array $f) => [
            'id'          => $f['id']          ?? null,
            'name'        => $f['name']        ?? null,
            'filename'    => $f['filename']    ?? '-',
            'url'         => $f['url'] ?? $f['download_url'] ?? null,
            'notes'       => $f['note'] ?? $f['notes'] ?? null,
            'created_at'  => $f['created_at']['formatted'] ?? null,
        ])->values()->all();

        // --- Transform history ---
        $history = collect($userHistory)->map(fn (array $h) => [
            'id'          => $h['id']          ?? null,
            'icon'        => $h['item_type']   ?? null,
            'created_at'  => !empty($h['created_at']['datetime']) ? \Carbon\Carbon::parse($h['created_at']['datetime'])->format('Y-m-d H:i:s') : null,
            'admin_name'  => $h['admin']['name'] ?? ($h['created_by']['name'] ?? '-'),
            'action_type' => $h['action_type'] ?? '-',
            'item_name'   => $h['item']['name'] ?? '-',
            'item_type'   => $h['item_type']   ?? null,
            'target_name' => $h['target']['name'] ?? '-',
            'target_type' => $h['target_type'] ?? null,
            'filename'    => $h['filename']    ?? null,
            'download_url'=> $h['download_url'] ?? null,
            'qty'         => $h['qty']         ?? null,
            'note'        => $h['note']        ?? null,
            'changed'     => $h['log_meta']    ?? null,
        ])->concat($localLogs->map(fn($l) => [
            'id'          => 'local_' . $l->id,
            'icon'        => 'local',
            'created_at'  => $l->created_at?->format('Y-m-d H:i:s'),
            'admin_name'  => $l->user?->name ?? 'System',
            'action_type'  => $l->action_type,
            // FIX #4 ORPHANED DATA: use log_meta to preserve item name even if asset deleted from Snipe-IT
            'item_name'    => $l->log_meta['item_name'] ?? $l->log_meta['doc_no'] ?? $l->note ?? 'Local Activity',
            'item_type'    => $l->item_type,
            'target_name'  => $user->name,
            'target_type'  => 'user',
            'note'         => $l->note,
            'changed'      => $l->log_meta,
        ]))->sortByDesc('created_at')->values()->all();

        // (managed_users, managed_locations, and eulas already fetched via pool above)

        return Inertia::render('Users/Show', [
            'user'             => $profile,
            'roleAccess'       => $roleAccess,
            'assets'           => $unifiedAssets,
            'consumables'      => $consumables,
            'eulas'            => $userEulas,
            'files'            => $files,
            'history'          => $history,
            'managed_users'    => $managedUsers,
            'managed_locations' => $managedLocations,
            'local_docs'       => $localDocs,
            // PERF: 'metadata' and 'options' removed — they trigger 10+ heavy API calls
            // but are not used in the Show template (only needed for edit/handover modals).
        ]);
    }

    private function buildAssetMetadata(): array
    {
        $pool = $this->snipe->requestPool([
            'users_p1'      => ['users',        ['limit' => 500, 'offset' => 0]],
            'users_p2'      => ['users',        ['limit' => 500, 'offset' => 500]],
            'models_p1'     => ['models',       ['limit' => 500, 'offset' => 0]],
            'models_p2'     => ['models',       ['limit' => 500, 'offset' => 500]],
            'models_p3'     => ['models',       ['limit' => 500, 'offset' => 1000]],
            'statuslabels'  => ['statuslabels', ['limit' => 500]],
            'categories'    => ['categories',   ['limit' => 500]],
            'locations'     => ['locations',    ['limit' => 500]],
            'companies'     => ['companies',    ['limit' => 500]],
            'suppliers'     => ['suppliers',    ['limit' => 500]],
        ]);

        $users = array_merge($pool['users_p1']['rows'] ?? [], $pool['users_p2']['rows'] ?? []);
        $models = array_merge(
            $pool['models_p1']['rows'] ?? [],
            $pool['models_p2']['rows'] ?? [],
            $pool['models_p3']['rows'] ?? []
        );

        return [
            'users' => collect($users)->map(fn($u) => [
                'id' => $u['id'], 
                'name' => $u['name'],
                'company' => data_get($u, 'company.name'),
                'department' => data_get($u, 'department.name'),
                'location_id' => data_get($u, 'location.id'),
            ])->all(),
            'models' => collect($models)->map(fn($m) => [
                'id' => $m['id'], 
                'name' => $m['name'],
                'category_name' => data_get($m, 'category.name'),
            ])->all(),
            'status_labels' => collect($pool['statuslabels']['rows'] ?? [])->map(fn($s) => ['id' => $s['id'], 'name' => $s['name']])->all(),
            'categories' => collect($pool['categories']['rows'] ?? [])->map(fn($c) => ['id' => $c['id'], 'name' => $c['name']])->all(),
            'locations' => collect($pool['locations']['rows'] ?? [])->map(fn($l) => ['id' => $l['id'], 'name' => $l['name']])->all(),
            'companies' => collect($pool['companies']['rows'] ?? [])->map(fn($c) => ['id' => $c['id'], 'name' => $c['name']])->all(),
            'suppliers' => collect($pool['suppliers']['rows'] ?? [])->map(fn($s) => ['id' => $s['id'], 'name' => $s['name']])->all(),
        ];
    }

    public function uploadFile(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB limit
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $snipeId = $user->snipeit_user_id;

        if (!$snipeId) {
            return back()->with('error', 'User tidak terhubung ke Snipe-IT.');
        }

        $file = $request->file('file');
        
        $response = $this->snipe->uploadFile(
            'users',
            $snipeId,
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            $request->input('notes')
        );

        if (($response['status'] ?? 'error') === 'success') {
            $this->snipe->flushCacheForUser($snipeId);
            return back()->with('status', 'File berhasil diunggah.');
        }

        return back()->with('error', 'Gagal mengunggah file: ' . ($response['messages']['api'][0] ?? 'Terjadi kesalahan.'));
    }

    public function deleteFile(User $user, int $fileId): RedirectResponse
    {
        $snipeId = $user->snipeit_user_id;

        if (!$snipeId) {
            return back()->with('error', 'User tidak terhubung ke Snipe-IT.');
        }

        $response = $this->snipe->deleteFile('users', $snipeId, $fileId);

        if (($response['status'] ?? 'error') === 'success') {
            $this->snipe->flushCacheForUser($snipeId);
            return back()->with('status', 'File berhasil dihapus.');
        }

        return back()->with('error', 'Gagal menghapus file.');
    }

    public function updateAvatar(Request $request, User $user): RedirectResponse
    {
        \Log::info('Entering updateAvatar for user: ' . $user->username);
        
        $request->validate([
            'image' => ['required', 'string'], // Base64 Data URI
        ]);

        $snipeId = $user->snipeit_user_id;

        if (!$snipeId) {
            return back()->with('error', 'User tidak terhubung ke Snipe-IT.');
        }

        $base64Data = $request->input('image');
        
        // Decode base64 and strip the data URI prefix
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $extension = strtolower($type[1]);
            $rawData = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
        } else {
            return back()->with('error', 'Format foto tidak valid.');
        }

        $filename = "avatar_{$user->id}_{$user->username}." . $extension;

        $response = $this->snipe->updateAvatar($snipeId, $rawData, $filename);

        \Log::info('Snipe-IT Avatar Update (Multipart) Response:', [
            'user' => $user->username,
            'response' => $response
        ]);

        if (($response['status'] ?? 'error') === 'success') {
            $this->snipe->flushCacheForUser($snipeId);
            return back()->with('status', 'Foto profil berhasil diperbarui.');
        }

        return back()->with('error', 'Gagal memperbarui foto profil: ' . ($response['messages']['api'][0] ?? 'Terjadi kesalahan.'));
    }

    public function deleteAvatar(User $user): RedirectResponse
    {
        $snipeId = $user->snipeit_user_id;

        if (!$snipeId) {
            return back()->with('error', 'User tidak terhubung ke Snipe-IT.');
        }

        // Snipe-IT: set image to null to delete
        $response = $this->snipe->updateRecord('users', $snipeId, ['image' => null]);

        if (($response['status'] ?? 'error') === 'success') {
            $this->snipe->flushCacheForUser($snipeId);
            return back()->with('status', 'Foto profil berhasil dihapus.');
        }

        return back()->with('error', 'Gagal menghapus foto profil.');
    }

    public function checkinAsset(User $user, int $assetId): RedirectResponse
    {
        $response = $this->snipe->checkinAsset('hardware', $assetId);

        if (($response['status'] ?? 'error') === 'success') {
            $snipeId = $user->snipeit_user_id;
            if ($snipeId) $this->snipe->flushCacheForUser($snipeId);

            try {
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'checkin',
                    'item_type'   => 'snipeit_assets',
                    'item_id'     => $assetId,
                    'snipeit_id'  => $assetId,
                    'snipeit_type'=> 'assets',
                    'target_type' => User::class,
                    'target_id'   => $user->id,
                    'note'        => "Check-in asset dari {$user->name}",
                    'log_meta'    => [
                        'user_id'   => $user->id,
                        'recipient' => $user->name,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to log checkinAsset: ' . $e->getMessage());
            }

            return back()->with('status', 'Asset berhasil di-checkin.');
        }

        return back()->with('error', 'Gagal checkin asset.');
    }

    public function checkinLicense(User $user, int $licenseId): RedirectResponse
    {
        $response = $this->snipe->checkinAsset('licenses', $licenseId);

        if (($response['status'] ?? 'error') === 'success') {
            $snipeId = $user->snipeit_user_id;
            if ($snipeId) $this->snipe->flushCacheForUser($snipeId);

            try {
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'checkin',
                    'item_type'   => 'snipeit_license',
                    'item_id'     => $licenseId,
                    'snipeit_id'  => $licenseId,
                    'snipeit_type'=> 'license',
                    'target_type' => User::class,
                    'target_id'   => $user->id,
                    'note'        => "Check-in license dari {$user->name}",
                    'log_meta'    => [
                        'user_id'   => $user->id,
                        'recipient' => $user->name,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to log checkinLicense: ' . $e->getMessage());
            }

            return back()->with('status', 'License berhasil di-checkin.');
        }

        return back()->with('error', 'Gagal checkin license.');
    }

    public function checkinAccessory(User $user, int $accessoryId): RedirectResponse
    {
        $response = $this->snipe->checkinAsset('accessories', $accessoryId);

        if (($response['status'] ?? 'error') === 'success') {
            $snipeId = $user->snipeit_user_id;
            if ($snipeId) $this->snipe->flushCacheForUser($snipeId);

            try {
                ActionLog::create([
                    'user_id'     => auth()->id(),
                    'action_type' => 'checkin',
                    'item_type'   => 'snipeit_accessories',
                    'item_id'     => $accessoryId,
                    'snipeit_id'  => $accessoryId,
                    'snipeit_type'=> 'accessories',
                    'target_type' => User::class,
                    'target_id'   => $user->id,
                    'note'        => "Check-in accessory dari {$user->name}",
                    'log_meta'    => [
                        'user_id'   => $user->id,
                        'recipient' => $user->name,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to log checkinAccessory: ' . $e->getMessage());
            }

            return back()->with('status', 'Accessory berhasil di-checkin.');
        }

        return back()->with('error', 'Gagal checkin accessory.');
    }

    public function checkinConsumable(User $user, int $consumableId): RedirectResponse
    {
        // Consumables don't usually have "checkin", they are consumed. 
        // But some Snipe-IT versions might have it. Usually just ignore or show error.
        return back()->with('error', 'Consumables tidak dapat di-checkin.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $snipeId = $user->snipeit_user_id;

        // Guard: block delete if user has active assignments in Snipe-IT
        if ($snipeId) {
            $resp   = $this->snipe->request("users/{$snipeId}");
            $total  = (int) ($resp['assets_count'] ?? 0)
                    + (int) ($resp['licenses_count'] ?? 0)
                    + (int) ($resp['accessories_count'] ?? 0);

            if ($total > 0) {
                return to_route('users.show', $user)
                    ->with('error', 'User masih memiliki asset yang di-assign. Harap checkin semua asset terlebih dahulu.');
            }

            $this->snipe->deleteRecord('users', $snipeId);
        }

        // Remove from LLDAP
        if ($user->username) {
            try {
                $this->ldap->deleteUser($user->username);
            } catch (\Throwable) {
                // LDAP removal failure is non-blocking
            }
        }

        $user->delete();

        return to_route('users.index')->with('status', 'User berhasil dihapus.');
    }

    // =========================================================================
    // Criterion 2 – Sync: pull LLDAP → push to Snipe-IT
    // =========================================================================

    /**
     * Sync all LLDAP users into Snipe-IT.
     * Triggered by the "Sync User LDAP" button on the User List page.
     *
     * Logic:
     *  1. Pull all users from LLDAP via LdapService::getAllUsers()
     *  2. For each user, search Snipe-IT by username/email
     *  3. If found → update; if not found → create
     */
    public function syncLdap(): RedirectResponse
    {
        $ldapUsers = $this->ldap->getAllUsers();
        $synced = 0;
        $failed = 0;

        foreach ($ldapUsers as $ldapUser) {
            $username = $ldapUser['username'] ?? '';
            $email    = $ldapUser['email'] ?? '';

            if ($username === '') {
                continue;
            }

            try {
                // Check if already in Snipe-IT
                $existing = $this->snipe->fetchRows('users', ['search' => $username, 'limit' => 5], 5);
                $match = collect($existing)->first(
                    fn ($u) => strtolower((string) ($u['username'] ?? '')) === strtolower($username)
                               || ($email !== '' && strtolower((string) ($u['email'] ?? '')) === strtolower($email)),
                );

                $companyId = $this->managedUsers->resolveCompanyIdByName($ldapUser['company_name'] ?? null);
                $locationId = $this->managedUsers->resolveLocationIdByName($ldapUser['location_name'] ?? null);

                $payload = array_filter([
                    'first_name'  => $ldapUser['first_name'] ?: null,
                    'last_name'   => $ldapUser['last_name']  ?: null,
                    'username'    => $username,
                    'email'       => $email ?: null,
                    'phone'       => $ldapUser['phone'] ?: null,
                    'company_id'  => $companyId,
                    'location_id' => $locationId,
                    'activated'   => 1,
                    'ldap_import' => 1,
                ], fn ($v) => $v !== null && $v !== '');

                if ($match) {
                    $this->snipe->updateRecord('users', (int) $match['id'], $payload);
                } else {
                    // Snipe-IT requires a password for new users even if they use LDAP
                    $payload['password'] = \Illuminate\Support\Str::random(24);
                    $payload['password_confirmation'] = $payload['password'];
                    $this->snipe->createRecord('users', $payload);
                }

                $synced++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $syncedToLocal = $this->managedUsers->syncAllUsers();

        $message = "Sync berantai selesai: {$synced} LDAP user diproses ke Snipe-IT, dan {$syncedToLocal} data akhir berhasil disinkronkan kembali ke database lokal.";
        if ($failed > 0) {
            $message .= " ({$failed} koneksi bermasalah).";
        }

        return to_route('users.index')->with('status', $message);
    }

    /**
     * Sync users from Snipe-IT into local DB (legacy, kept for compatibility).
     */
    public function sync(): RedirectResponse
    {
        $count = $this->managedUsers->syncAllUsers();

        return to_route('users.index')->with('status', "Berhasil mensinkronisasi {$count} user dari Snipe-IT.");
    }
}
