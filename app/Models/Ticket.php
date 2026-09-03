<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'company',
        'location',
        'category',
        'ticket_scope',
        'priority',
        'requester',
        'department',
        'snipeit_asset_id',
        'asset_reference_snapshot',
        'maintenance_type',
        'issue_description',
        'action_taken',
        'note',
        'technician',
        'vendor_id',
        'status',
        'date_closed',
        'snipeit_maintenance_id',
        'snipeit_sync_status',
        'snipeit_sync_message',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'snipeit_asset_id' => 'integer',
            'date_closed' => 'date',
            'snipeit_maintenance_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}