<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitCompany extends Model
{
    protected $table = 'snipeit_companies';

    protected $fillable = [
        'id',
        'name',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false; // Use Snipe-IT ID as primary key
}
