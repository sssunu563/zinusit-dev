<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StbAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'stb_id',
        'file_path',
        'file_type',
        'notes',
    ];

    /**
     * Get the STB that owns the attachment.
     */
    public function stb(): BelongsTo
    {
        return $this->belongsTo(Stb::class);
    }
}
