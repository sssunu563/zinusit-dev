<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerResourceDaily extends Model
{
    protected $table = 'server_resource_daily';
    protected $fillable = [
        'host_id',
        'report_date',
        'cpu_usage_percent',
        'memory_usage_percent',
        'hdd_free_percent',
    ];

    protected $casts = [
        'report_date' => 'date',
        'cpu_usage_percent' => 'float',
        'memory_usage_percent' => 'float',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ServerDevice::class, 'host_id', 'source_id');
    }
}
