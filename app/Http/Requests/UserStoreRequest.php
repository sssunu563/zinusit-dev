<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $username = trim((string) $this->input('username', ''));
        $this->merge([
            'first_name'   => trim((string) $this->input('first_name', '')),
            'last_name'    => trim((string) $this->input('last_name', '')),
            'email'        => trim((string) $this->input('email', '')),
            'username'     => $username === '' ? null : $username,
            'phone'        => ($v = trim((string) $this->input('phone', '')))   === '' ? null : $v,
            'mobile'       => ($v = trim((string) $this->input('mobile', '')))  === '' ? null : $v,
            'jobtitle'     => ($v = trim((string) $this->input('jobtitle', '')))     === '' ? null : $v,
            'employee_num' => ($v = trim((string) $this->input('employee_num', ''))) === '' ? null : $v,
            'website'      => ($v = trim((string) $this->input('website', ''))) === '' ? null : $v,
            'notes'        => ($v = trim((string) $this->input('notes', '')))   === '' ? null : $v,
            'vip'                  => (bool) $this->input('vip', false),
            'remote'               => (bool) $this->input('remote', false),
            'auto_assign_licenses' => (bool) $this->input('auto_assign_licenses', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique(User::class, 'username'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'phone'                => ['nullable', 'string', 'max:255'],
            'mobile'               => ['nullable', 'string', 'max:255'],
            'jobtitle'             => ['nullable', 'string', 'max:255'],
            'employee_num'         => ['nullable', 'string', 'max:255'],
            'website'              => ['nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:2000'],
            'vip'                  => ['boolean'],
            'remote'               => ['boolean'],
            'auto_assign_licenses' => ['boolean'],
            'manager_id'    => ['nullable', 'integer'],
            'location_id'   => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'company_id'    => ['nullable', 'integer'],
            'password'      => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, strip, atau underscore.',
        ];
    }
}