<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StbItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stb_id',
        'nama',
        'kategori',
        'type',
        'jumlah',
        'condition',
        'serial_no',
        'inventory_number',
        'computer_id',
        'snipeit_asset_id',
        'asset_reference_snapshot',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'computer_id' => 'integer',
        'snipeit_asset_id' => 'integer',
    ];

    /**
     * Get the STB that owns the item.
     */
    public function stb(): BelongsTo
    {
        return $this->belongsTo(Stb::class);
    }
}
