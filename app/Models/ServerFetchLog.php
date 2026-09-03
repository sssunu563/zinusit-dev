<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerFetchLog extends Model
{
    protected $table    = 'server_fetch_logs';
    protected $fillable = [
        'fetch_date',
        'group_name',
        'status',
        'devices_ok',
        'devices_fail',
        'notes',
        'triggered_by',
        'is_manual',
    ];

    protected $casts = [
        'fetch_date' => 'date',
        'is_manual' => 'boolean',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
