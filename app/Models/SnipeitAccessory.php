<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitAccessory extends Model
{
    protected $table = 'snipeit_accessories';

    protected $fillable = [
        'id',
        'name',
        'category_id',
        'qty',
        'remaining_qty',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false;

    /**
     * Category relationship
     */
    public function category()
    {
        return $this->belongsTo(SnipeitCategory::class, 'category_id');
    }
}
