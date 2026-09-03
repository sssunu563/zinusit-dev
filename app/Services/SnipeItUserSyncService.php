<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class SnipeItUserSyncService
{
    public function __construct(
        private readonly SnipeItService $snipe,
    ) {
    }

    public function findSnipeUserByIdentifier(string $identifier): ?array
    {
        $normalizedIdentifier = $this->normalize($identifier);

        if ($normalizedIdentifier === '') {
            return null;
        }

        // Cache per-identifier untuk 60 detik — menghindari 1 HTTP round-trip
        // ekstra ke Snipe-IT setiap login. TTL pendek agar perubahan data tidak stale.
        $cacheKey = 'snipe_user_' . sha1($normalizedIdentifier);

        return Cache::remember($cacheKey, 60, function () use ($identifier, $normalizedIdentifier) {
            $rows = $this->snipe->fetchRows('users', [
                'search' => $identifier,
            ], 100);

            foreach ($rows as $user) {
                $email    = $this->normalize(Arr::get($user, 'email'));
                $username = $this->normalize(Arr::get($user, 'username'));

                if (in_array($normalizedIdentifier, [$email, $username], true)) {
                    return $user;
                }
            }

            return null;
        });
    }

    public function findLocalUserByIdentifier(string $identifier, ?array $snipeUser = null): ?User
    {
        $normalizedIdentifier = $this->normalize($identifier);

        if ($normalizedIdentifier === '') {
            return null;
        }

        $localUser = User::query()
            ->where('email', $normalizedIdentifier)
            ->orWhere('username', $normalizedIdentifier)
            ->orWhere('snipeit_username', $normalizedIdentifier)
            ->first();

        if ($localUser || !$snipeUser) {
            return $localUser;
        }

        $snipeEmail = $this->normalize(Arr::get($snipeUser, 'email'));
        $snipeUsername = $this->normalize(Arr::get($snipeUser, 'username'));
        $snipeId = (int) Arr::get($snipeUser, 'id', 0);

        $query = User::query();

        if ($snipeId > 0) {
            $query->where('snipeit_user_id', $snipeId);
        }

        if ($snipeEmail !== '') {
            $method = $snipeId > 0 ? 'orWhere' : 'where';
            $query->{$method}('email', $snipeEmail);
        }

        if ($snipeUsername !== '') {
            $method = $snipeId > 0 || $snipeEmail !== '' ? 'orWhere' : 'where';
            $query->{$method}('username', $snipeUsername)
                ->orWhere('snipeit_username', $snipeUsername);
        }

        return $query->first();
    }

    public function syncLocalUser(User $user, array $snipeUser): bool
    {
        $firstName = trim((string) Arr::get($snipeUser, 'first_name', ''));
        $lastName = trim((string) Arr::get($snipeUser, 'last_name', ''));
        $fullName = trim($firstName . ' ' . $lastName);

        $payload = [
            'name' => $fullName !== '' ? $fullName : trim((string) Arr::get($snipeUser, 'name', $user->name)),
            'snipeit_synced_at' => now(),
        ];

        $email = trim((string) Arr::get($snipeUser, 'email', ''));
        if ($email !== '' && $this->isUniqueForUser('email', $email, $user)) {
            $payload['email'] = $email;
        }

        $username = trim((string) Arr::get($snipeUser, 'username', ''));
        if ($username !== '' && $this->isUniqueForUser('username', $username, $user)) {
            $payload['username'] = $username;
            $payload['snipeit_username'] = $username;
        }

        $snipeItUserId = (int) Arr::get($snipeUser, 'id', 0);
        if ($snipeItUserId > 0 && $this->isUniqueForUser('snipeit_user_id', $snipeItUserId, $user)) {
            $payload['snipeit_user_id'] = $snipeItUserId;
        }

        $user->fill($payload);

        if (!$user->isDirty()) {
            return false;
        }

        $user->save();

        return true;
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function isUniqueForUser(string $column, mixed $value, User $user): bool
    {
        return !User::query()
            ->where($column, $value)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * Returns true if the Snipe-IT user has login enabled (activated checkbox).
     */
    public function isLoginEnabled(array $snipeUser): bool
    {
        return (bool) Arr::get($snipeUser, 'activated', false);
    }

    /**
     * Auto-provision a new local user from a Snipe-IT record.
     * Sets password = username (plain) and flags must_change_password = true.
     */
    public function provisionFromSnipe(array $snipeUser): User
    {
        $firstName = trim((string) Arr::get($snipeUser, 'first_name', ''));
        $lastName  = trim((string) Arr::get($snipeUser, 'last_name', ''));
        $fullName  = trim($firstName . ' ' . $lastName);
        $username  = trim((string) Arr::get($snipeUser, 'username', ''));
        $email     = trim((string) Arr::get($snipeUser, 'email', ''));

        $user = new User();
        $user->name                 = $fullName !== '' ? $fullName : ($username ?: 'Unknown');
        $user->username             = $username;
        $user->snipeit_username     = $username;
        $user->email                = $email !== '' ? $email : null;
        $user->snipeit_user_id      = (int) Arr::get($snipeUser, 'id', 0) ?: null;
        $user->snipeit_synced_at    = now();
        $user->email_verified_at    = now();
        $user->must_change_password = true;
        // Default password = username (user must change on first login)
        $user->password             = Hash::make($username);
        $user->save();

        return $user;
    }
}