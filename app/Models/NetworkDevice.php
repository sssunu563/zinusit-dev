<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkDevice extends Model
{
    protected $table = 'network_devices';

    protected $fillable = [
        'source', 'source_instance', 'source_id',
        'device_name', 'ip_address', 'host_group', 'probe',
        'location', 'site', 'last_status', 'last_sync',
        'monitor_backup', 'is_active', 'is_excluded',
        'maintenance_note', 'maintenance_until',
    ];

    protected $casts = [
        'monitor_backup'    => 'boolean',
        'is_active'         => 'boolean',
        'is_excluded'       => 'boolean',
        'last_sync'         => 'datetime',
        'maintenance_until' => 'datetime',
    ];

    public function uptimeDaily(): HasMany
    {
        return $this->hasMany(NetworkUptimeDaily::class, 'device_id');
    }

    public function backupMonthly(): HasMany
    {
        return $this->hasMany(NetworkBackupMonthly::class, 'device_id');
    }

    public function isInMaintenance(): bool
    {
        if (!$this->maintenance_note) return false;
        if (!$this->maintenance_until) return true;
        return $this->maintenance_until->isFuture();
    }
}
