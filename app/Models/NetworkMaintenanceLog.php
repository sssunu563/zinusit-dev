<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkMaintenanceLog extends Model
{
    protected $table = 'network_maintenance_logs';

    protected $fillable = [
        'device_id', 'status', 'started_at', 'resolved_at',
        'event_type', 'notes', 'is_auto', 'created_by', 'closed_by',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'resolved_at' => 'datetime',
        'is_auto'     => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'device_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function durationLabel(): string
    {
        $start = \Carbon\Carbon::parse($this->started_at);
        $end   = $this->resolved_at ? \Carbon\Carbon::parse($this->resolved_at) : now();
        $diff  = $start->diff($end);
        
        $parts = [];
        if ($diff->d > 0) $parts[] = "{$diff->d}d";
        if ($diff->h > 0) $parts[] = "{$diff->h}h";
        if ($diff->i > 0) $parts[] = "{$diff->i}m";
        if ($diff->s > 0) $parts[] = "{$diff->s}s";
        
        return empty($parts) ? '0s' : implode('', $parts);
    }
}
