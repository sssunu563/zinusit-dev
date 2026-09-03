<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Services\AuthLogService;
use App\Services\LdapService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::authenticateUsing(function (Request $request) {
            $identifier = trim((string) $request->input(Fortify::username()));
            $password   = (string) $request->input('password');
            $logService = app(AuthLogService::class);
            $ldap       = app(LdapService::class);

            // ---------------------------------------------------------------
            // Step 1+2 (+group check): Temukan user, verifikasi password, dan
            // (opsional) cek keanggotaan group — semua dalam 1 LDAP connection.
            // Tidak ada HTTP call ke Snipe-IT di sini sama sekali.
            // ---------------------------------------------------------------
            $ldapUser = null;

            try {
                $ldapUser = $ldap->findAndAuthenticate($identifier, $password);
            } catch (\Throwable $e) {
                $logService->write($request, 'login', 'failed', $identifier, null, [
                    'reason'  => 'ldap_error',
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if ($ldapUser === null) {
                $logService->write($request, 'login', 'failed', $identifier, null, [
                    'reason' => 'ldap_user_not_found_or_invalid_credentials',
                ]);

                return null;
            }

            $ldapUid = $ldapUser['username'];

            // ---------------------------------------------------------------
            // Step 3: Cari atau buat local Laravel user dari data LLDAP saja.
            //         Snipe-IT sync dilakukan terpisah (bukan saat login).
            // ---------------------------------------------------------------
            try {
                $localUser = User::where('username', $ldapUid)->first()
                          ?? ($ldapUser['email'] ? User::where('email', $ldapUser['email'])->first() : null);

                if ($localUser === null) {
                    $localUser = $this->provisionFromLdap($ldapUser);

                    $logService->write($request, 'user_sync', 'created', $identifier, $localUser, [
                        'ldap_uid' => $ldapUid,
                    ]);
                } else {
                    $changed = $this->refreshLocalUser($localUser, $ldapUser);

                    $logService->write($request, 'user_sync', $changed ? 'updated' : 'matched', $identifier, $localUser, [
                        'ldap_uid' => $ldapUid,
                    ]);
                }
            } catch (\Throwable $e) {
                // DB error saat sync user — log tapi tetap lanjutkan login
                // kalau user sudah ada di local DB, coba ambil lagi
                \Illuminate\Support\Facades\Log::error('Login DB sync error: ' . $e->getMessage());
                $localUser = User::where('username', $ldapUid)->first()
                          ?? ($ldapUser['email'] ? User::where('email', $ldapUser['email'])->first() : null);

                if ($localUser === null) {
                    // DB benar-benar tidak bisa diakses, tidak bisa login
                    return null;
                }
            }

            // ---------------------------------------------------------------
            // Step 4: Login berhasil
            // ---------------------------------------------------------------
            $logService->write($request, 'login', 'success', $identifier, $localUser, [
                'ldap_uid' => $ldapUid,
            ]);

            return $localUser;
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(20)->by($throttleKey);
        });
    }

    /**
     * Create a new local user from LLDAP data only.
     * Password is a random placeholder — real auth is always via LDAP bind.
     */
    private function provisionFromLdap(array $ldapUser): User
    {
        $firstName = $ldapUser['first_name'];
        $lastName  = $ldapUser['last_name'];
        $fullName  = trim($firstName . ' ' . $lastName);
        $username  = $ldapUser['username'];
        $email     = $ldapUser['email'];

        $user = new User();
        $user->name              = $fullName ?: $username;
        $user->username          = $username;
        $user->snipeit_username  = $username;
        $user->email             = $email ?: null;
        $user->email_verified_at = now();
        $user->password          = Hash::make(Str::random(32));

        // PERF: Cache admin group membership for 5 minutes to avoid
        // a separate LDAP connection on every login
        $ldap = app(LdapService::class);
        $user->is_admin = \Illuminate\Support\Facades\Cache::remember(
            "ldap_is_admin:{$username}",
            300,
            fn () => $ldap->isUserInGroup($username, 'lldap_admin')
        );

        $user->save();

        return $user;
    }

    /**
     * Refresh a local user's profile fields from fresh LLDAP data.
     * Returns true if any field was actually changed.
     */
    private function refreshLocalUser(User $user, array $ldapUser): bool
    {
        $firstName = $ldapUser['first_name'];
        $lastName  = $ldapUser['last_name'];
        $fullName  = trim($firstName . ' ' . $lastName);
        $email     = $ldapUser['email'];

        $changes = [];

        if ($fullName !== '' && $user->name !== $fullName) {
            $changes['name'] = $fullName;
        }

        if ($ldapUser['username'] !== '' && $user->username !== $ldapUser['username']) {
            $changes['username']         = $ldapUser['username'];
            $changes['snipeit_username'] = $ldapUser['username'];
        }

        if ($email !== '' && $user->email !== $email) {
            $changes['email'] = $email;
        }

        $user->fill($changes);

        // PERF: Cache admin group membership for 5 minutes to avoid
        // a separate LDAP connection on every login refresh
        $ldap    = app(LdapService::class);
        $isAdmin = \Illuminate\Support\Facades\Cache::remember(
            "ldap_is_admin:{$user->username}",
            300,
            fn () => $ldap->isUserInGroup($user->username, 'lldap_admin')
        );
        if ($user->is_admin !== $isAdmin) {
            $user->is_admin = $isAdmin;
        }

        if ($user->isDirty()) {
            $user->save();

            return true;
        }

        return false;
    }
}
