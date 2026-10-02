<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitModel extends Model
{
    protected $table = 'snipeit_models';

    protected $fillable = [
        'id',
        'name',
        'model_number',
        'manufacturer_id',
        'manufacturer_name',
        'category_id',
        'category_name',
        'fieldset_id',
        'fieldset_name',
        'notes',
        'raw_data',
        'synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public $incrementing = false; // Use Snipe-IT ID as primary key

    /**
     * Relationship to manufacturer
     */
    public function manufacturer()
    {
        return $this->belongsTo(SnipeitManufacturer::class, 'manufacturer_id');
    }

    /**
     * Relationship to category
     */
    public function category()
    {
        return $this->belongsTo(SnipeitCategory::class, 'category_id');
    }

    /**
     * Relationship to fieldset
     */
    public function fieldset()
    {
        return $this->belongsTo(SnipeitFieldset::class, 'fieldset_id');
    }
}
