<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitAsset extends Model
{
    protected $table = 'snipeit_assets';

    protected $fillable = [
        'id',
        'name',
        'asset_tag',
        'serial',
        'model_name',
        'status_id',
        'category_id',
        'location_id',
        'assigned_to',
        'assigned_type',
        'notes',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false;

    /**
     * Status label relationship
     */
    public function statusLabel()
    {
        return $this->belongsTo(SnipeitStatusLabel::class, 'status_id');
    }

    /**
     * Category relationship
     */
    public function category()
    {
        return $this->belongsTo(SnipeitCategory::class, 'category_id');
    }

    /**
     * Location relationship
     */
    public function location()
    {
        return $this->belongsTo(SnipeitLocation::class, 'location_id');
    }

    /**
     * Assigned user relationship
     */
    public function assignedUser()
    {
        return $this->belongsTo(SnipeitUser::class, 'assigned_to');
    }

    /**
     * Check if asset is a laptop
     */
    public function isLaptop(): bool
    {
        $categoryName = strtolower($this->category->name ?? '');
        $modelName = strtolower($this->model_name ?? '');
        $name = strtolower($this->name ?? '');

        return str_contains($categoryName, 'laptop')
            || str_contains($modelName, 'laptop')
            || str_contains($name, 'laptop');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeWithStatus($query, int $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope: Filter laptops only
     */
    public function scopeLaptopsOnly($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('category', function ($cat) {
                $cat->where('name', 'like', '%laptop%');
            })
            ->orWhere('model_name', 'like', '%laptop%')
            ->orWhere('name', 'like', '%laptop%');
        });
    }
}
