<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\SnipeItManagedUserService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        [$firstName, $lastName] = $this->splitName((string) $input['name']);

        return app(SnipeItManagedUserService::class)->createManagedUser([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => null,
            'email' => $input['email'],
            'phone' => null,
            'jobtitle' => null,
            'manager_id' => null,
            'location_id' => null,
            'department_id' => null,
            'company_id' => null,
            'password' => $input['password'],
        ]);
    }

    private function splitName(string $name): array
    {
        $normalized = trim($name);
        $parts = preg_split('/\s+/', $normalized) ?: [];
        $firstName = array_shift($parts) ?: $normalized;

        return [$firstName, trim(implode(' ', $parts))];
    }
}
