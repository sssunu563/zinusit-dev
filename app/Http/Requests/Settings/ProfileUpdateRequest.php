<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name'   => trim((string) $this->input('first_name', '')),
            'last_name'    => ($v = trim((string) $this->input('last_name', '')))   === '' ? null : $v,
            'username'     => ($v = trim((string) $this->input('username', '')))    === '' ? null : $v,
            'email'        => trim((string) $this->input('email', '')),
            'phone'        => ($v = trim((string) $this->input('phone', '')))       === '' ? null : $v,
            'mobile'       => ($v = trim((string) $this->input('mobile', '')))      === '' ? null : $v,
            'jobtitle'     => ($v = trim((string) $this->input('jobtitle', '')))    === '' ? null : $v,
            'website'      => ($v = trim((string) $this->input('website', '')))     === '' ? null : $v,
            'notes'        => ($v = trim((string) $this->input('notes', '')))       === '' ? null : $v,
            'vip'                  => (bool) $this->input('vip', false),
            'remote'               => (bool) $this->input('remote', false),
            'auto_assign_licenses' => (bool) $this->input('auto_assign_licenses', false),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['nullable', 'string', 'max:255'],
            'username'     => [
                'nullable', 'string', 'max:255',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique(User::class, 'username')->ignore($this->user()->id),
            ],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()->id),
            ],
            'phone'                => ['nullable', 'string', 'max:255'],
            'mobile'               => ['nullable', 'string', 'max:255'],
            'jobtitle'             => ['nullable', 'string', 'max:255'],
            'website'              => ['nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:2000'],
            'vip'                  => ['boolean'],
            'remote'               => ['boolean'],
            'auto_assign_licenses' => ['boolean'],
            'manager_id'           => ['nullable', 'integer'],
            'location_id'          => ['nullable', 'integer'],
            'department_id'        => ['nullable', 'integer'],
            'company_id'           => ['nullable', 'integer'],
        ];
    }
}
