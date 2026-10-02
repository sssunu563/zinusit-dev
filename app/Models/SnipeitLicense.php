<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitLicense extends Model
{
    protected $table = 'snipeit_licenses';

    protected $fillable = [
        'id',
        'name',
        'product_key',
        'category_id',
        'seats',
        'free_seats_count',
        'expiration_date',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
        'expiration_date' => 'date',
    ];

    public $incrementing = false;

    /**
     * Category relationship
     */
    public function category()
    {
        return $this->belongsTo(SnipeitCategory::class, 'category_id');
    }

    /**
     * Check if license is expired
     */
    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    /**
     * Scope: Expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', now())
            ->whereDate('expiration_date', '<=', now()->addDays($days));
    }
}
