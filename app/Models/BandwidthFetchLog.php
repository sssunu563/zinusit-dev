<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthFetchLog extends Model
{
    protected $table = 'bandwidth_fetch_logs';

    protected $fillable = [
        'fetch_date',
        'status',
        'sensors_ok',
        'sensors_fail',
        'notes',
        'triggered_by',
        'is_manual',
    ];

    protected $casts = [
        'fetch_date' => 'date',
        'is_manual'  => 'boolean',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
