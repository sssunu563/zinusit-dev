<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    private const TICKET_SCOPE_OPTIONS = [
        'general',
        'asset',
    ];

    private const MAINTENANCE_TYPE_OPTIONS = [
        'Pemeliharaan',
        'Perbaikan',
        'Uji PAT',
        'Pembaruan',
        'Dukungan Perangkat Keras',
        'Dukungan Perangkat Lunak',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
            'ticket_scope' => ['required', 'string', Rule::in(self::TICKET_SCOPE_OPTIONS)],
            'priority' => ['required', 'string', Rule::in(['Low', 'Medium', 'High', 'Urgent'])],
            'requester' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
            'snipeit_asset_id' => [
                Rule::requiredIf(fn () => $this->normalizedTicketScope() === 'asset'),
                'nullable',
                'integer',
                'min:1',
            ],
            'asset_reference_snapshot' => ['nullable', 'string', 'max:120'],
            'maintenance_type' => [
                Rule::requiredIf(fn () => $this->normalizedTicketScope() === 'asset'),
                'nullable',
                'string',
                Rule::in(self::MAINTENANCE_TYPE_OPTIONS),
            ],
            'issue_description' => ['required', 'string', 'max:5000'],
            'action_taken' => ['required', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'technician' => ['required', 'string', 'max:120'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'status' => ['required', 'string', Rule::in(['Open', 'In Progress', 'Closed'])],
            'date_closed' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company' => trim((string) $this->input('company')),
            'location' => trim((string) $this->input('location')),
            'category' => trim((string) $this->input('category')),
            'ticket_scope' => $this->normalizedTicketScope(),
            'requester' => trim((string) $this->input('requester')),
            'department' => trim((string) $this->input('department')),
            'snipeit_asset_id' => $this->filled('snipeit_asset_id') ? (int) $this->input('snipeit_asset_id') : null,
            'asset_reference_snapshot' => trim((string) $this->input('asset_reference_snapshot')) ?: null,
            'maintenance_type' => trim((string) $this->input('maintenance_type')) ?: 'Maintenance',
            'issue_description' => trim((string) $this->input('issue_description')),
            'action_taken' => trim((string) $this->input('action_taken')),
            'note' => trim((string) $this->input('note')),
            'technician' => trim((string) $this->input('technician')),
            'vendor_id' => $this->filled('vendor_id') ? (int) $this->input('vendor_id') : null,
            'date_closed' => $this->filled('date_closed') ? $this->input('date_closed') : null,
        ]);
    }

    private function normalizedTicketScope(): string
    {
        $value = trim((string) $this->input('ticket_scope'));

        return in_array($value, self::TICKET_SCOPE_OPTIONS, true) ? $value : 'general';
    }
}
