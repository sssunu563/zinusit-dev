<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerTemperatureDaily extends Model
{
    protected $table = 'server_temperature_daily';
    protected $fillable = [
        'sensor_id',
        'location',
        'description',
        'report_date',
        'value_celsius',
    ];

    protected $casts = [
        'report_date' => 'date',
        'value_celsius' => 'float',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ServerDevice::class, 'sensor_id', 'source_id');
    }
}
