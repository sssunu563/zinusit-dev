<?php

namespace App\Http\Controllers;

use App\Services\LdapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    public function __construct(
        private readonly LdapService $ldap
    ) {}

    public function show(): Response
    {
        return Inertia::render('auth/ChangePassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user = $request->user();
        $plainPassword = $request->password;

        // Update local DB
        $user->password = Hash::make($plainPassword);
        $user->must_change_password = false;
        $user->save();

        // Sync ke LLDAP supaya password konsisten
        if ($user->username) {
            try {
                $this->ldap->changePassword($user->username, $plainPassword);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('LDAP password sync failed for ' . $user->username . ': ' . $e->getMessage());
            }
        }

        return redirect()->route('dashboard');
    }
}
