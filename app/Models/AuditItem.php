<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_session_id',
        'snipeit_asset_id',
        'asset_tag',
        'serial',
        'status',
        'physical_location',
        'physical_user',
        'notes',
        'expected_location',
        'expected_user',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AuditSession::class, 'audit_session_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
