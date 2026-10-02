<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitFieldset extends Model
{
    protected $table = 'snipeit_fieldsets';

    protected $fillable = [
        'id',
        'name',
        'fields',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false; // Use Snipe-IT ID as primary key

    /**
     * Models using this fieldset
     */
    public function models()
    {
        return $this->hasMany(SnipeitModel::class, 'fieldset_id');
    }
}
