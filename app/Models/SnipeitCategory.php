<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitCategory extends Model
{
    protected $table = 'snipeit_categories';

    protected $fillable = [
        'id',
        'name',
        'category_type',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false;

    /**
     * Assets in this category
     */
    public function assets()
    {
        return $this->hasMany(SnipeitAsset::class, 'category_id');
    }

    /**
     * Consumables in this category
     */
    public function consumables()
    {
        return $this->hasMany(SnipeitConsumable::class, 'category_id');
    }

    /**
     * Licenses in this category
     */
    public function licenses()
    {
        return $this->hasMany(SnipeitLicense::class, 'category_id');
    }
}
