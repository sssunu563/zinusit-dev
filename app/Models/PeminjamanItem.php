<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanItem extends Model
{
    use HasFactory;
    protected $table = 'peminjaman_items';

    protected $fillable = [
        'peminjaman_id',
        'nama',
        'kategori',
        'type',
        'jumlah',
        'serial_no',
        'inventory_number',
        'computer_id',
        'snipeit_asset_id',
        'asset_reference_snapshot',
        'condition',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class);
    }
}
