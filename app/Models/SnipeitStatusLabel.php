<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitStatusLabel extends Model
{
    protected $table = 'snipeit_status_labels';

    protected $fillable = [
        'id',
        'name',
        'status_type',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false;

    /**
     * Assets with this status
     */
    public function assets()
    {
        return $this->hasMany(SnipeitAsset::class, 'status_id');
    }
}
