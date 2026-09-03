<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkUptimeDaily extends Model
{
    protected $table    = 'network_uptime_daily';
    protected $fillable = ['device_id', 'report_date', 'uptime_percent', 'status'];
    protected $casts    = ['report_date' => 'date', 'uptime_percent' => 'float'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'device_id');
    }
}
