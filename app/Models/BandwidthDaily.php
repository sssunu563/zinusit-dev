<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BandwidthDaily extends Model
{
    protected $table = 'bandwidth_daily';

    protected $fillable = [
        'sensor_id',
        'location',
        'provider',
        'description',
        'report_date',
        'value_mbps',
    ];

    protected $casts = [
        'report_date' => 'date',
        'value_mbps'  => 'float',
    ];
}
