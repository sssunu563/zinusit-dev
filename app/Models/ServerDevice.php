<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerDevice extends Model
{
    protected $table = 'server_devices';

    protected $fillable = [
        'source', 'source_instance', 'source_id',
        'device_name', 'ip_address', 'host_group', 'probe',
        'location', 'site', 'last_status', 'last_sync',
        'is_active', 'is_excluded',
        'maintenance_note', 'maintenance_until',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'is_excluded'       => 'boolean',
        'last_sync'         => 'datetime',
        'maintenance_until' => 'datetime',
    ];

    public function resourceDaily(): HasMany
    {
        return $this->hasMany(ServerResourceDaily::class, 'host_id', 'source_id');
    }

    public function temperatureDaily(): HasMany
    {
        return $this->hasMany(ServerTemperatureDaily::class, 'host_id', 'source_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(ServerMaintenanceLog::class, 'device_id');
    }

    public function isInMaintenance(): bool
    {
        if (!$this->maintenance_note) return false;
        if (!$this->maintenance_until) return true;
        return $this->maintenance_until->isFuture();
    }
}
