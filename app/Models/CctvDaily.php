<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CctvDaily extends Model
{
    protected $table    = 'cctv_daily';
    protected $fillable = ['sensor_id', 'location', 'provider', 'description', 'report_date', 'value_mbps'];
    protected $casts    = ['report_date' => 'date', 'value_mbps' => 'float'];
}
