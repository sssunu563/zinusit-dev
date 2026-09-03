<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UptimeDevice extends Model
{
    protected $table    = 'uptime_devices';
    protected $fillable = ['host_id', 'device_name', 'ip_address', 'host_group', 'site', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function daily(): HasMany
    {
        return $this->hasMany(UptimeDaily::class, 'host_id', 'host_id');
    }
}
