<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitManufacturer extends Model
{
    protected $table = 'snipeit_manufacturers';

    protected $fillable = [
        'id',
        'name',
        'url',
        'support_url',
        'support_phone',
        'support_email',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false; // Use Snipe-IT ID as primary key

    /**
     * Models from this manufacturer
     */
    public function models()
    {
        return $this->hasMany(SnipeitModel::class, 'manufacturer_id');
    }
}
