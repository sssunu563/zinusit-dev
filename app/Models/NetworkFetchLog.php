<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkFetchLog extends Model
{
    protected $table    = 'network_fetch_logs';
    protected $fillable = ['fetch_date', 'source', 'source_instance', 'group_name', 'status', 'devices_ok', 'devices_fail', 'notes', 'triggered_by', 'is_manual'];
    protected $casts    = ['fetch_date' => 'date', 'is_manual' => 'boolean'];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
