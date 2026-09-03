<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UptimeDaily extends Model
{
    protected $table    = 'uptime_daily';
    protected $fillable = ['host_id', 'report_date', 'uptime_percent', 'status'];
    protected $casts    = ['report_date' => 'date', 'uptime_percent' => 'float'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(UptimeDevice::class, 'host_id', 'host_id');
    }
}
