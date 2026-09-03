<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanAttachment extends Model
{
    use HasFactory;
    protected $table = 'peminjaman_attachments';

    protected $fillable = [
        'peminjaman_id',
        'file_path',
        'file_type',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class);
    }
}
