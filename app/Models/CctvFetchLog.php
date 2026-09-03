<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CctvFetchLog extends Model
{
    protected $table    = 'cctv_fetch_logs';
    protected $fillable = [
        'fetch_date', 'source', 'source_instance', 'device_type',
        'group_name', 'status', 'devices_ok', 'devices_fail',
        'notes', 'is_manual', 'triggered_by',
    ];
    protected $casts = ['fetch_date' => 'date', 'is_manual' => 'boolean'];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
