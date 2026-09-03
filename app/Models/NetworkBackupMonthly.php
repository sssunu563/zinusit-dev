<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkBackupMonthly extends Model
{
    protected $table    = 'network_backup_monthly';
    protected $fillable = ['device_id', 'year', 'month', 'has_backup', 'notes', 'updated_by'];
    protected $casts    = ['has_backup' => 'boolean'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'device_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
