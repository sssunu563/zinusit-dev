<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Services\LdapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function __construct(
        private readonly LdapService $ldap
    ) {
    }

    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Password');
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $password = $request->input('password');

        // 1. Update local database
        $user->update([
            'password' => $password,
        ]);

        // 2. Update LLDAP
        if ($user->username) {
            $this->ldap->changePassword($user->username, $password);
        }

        // 3. Logout
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Password berhasil diperbarui. Silakan login kembali dengan password baru Anda.');
    }
}
