<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitSupplier extends Model
{
    protected $table = 'snipeit_suppliers';

    protected $fillable = [
        'id',
        'name',
        'address',
        'address2',
        'city',
        'state',
        'country',
        'zip',
        'phone',
        'fax',
        'email',
        'contact',
        'notes',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false; // Use Snipe-IT ID as primary key
}
