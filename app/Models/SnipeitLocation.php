<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitLocation extends Model
{
    protected $table = 'snipeit_locations';

    protected $fillable = [
        'id',
        'name',
        'address',
        'city',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false;

    /**
     * Assets in this location
     */
    public function assets()
    {
        return $this->hasMany(SnipeitAsset::class, 'location_id');
    }
}
