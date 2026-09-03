<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peminjaman extends Model
{
    use HasFactory;
    protected $table = 'peminjamans';


    protected $fillable = [
        'status',
        'document_type',
        'movement_type',
        'linked_stb_id',
        'returned_at',
        'it_drafter_id',
        'user_id',
        'user_name',
        'user_company',
        'user_dept',
        'user_title',
        'user_phone',
        'user_email',
        'group_id',
        'location_name',
        'use_date',
        'photo',
        'return_photo_path',
        'remark',
        'expected_return_date',
        'it_drafter_signature_path',
        'it_drafter_signed_at',
        'requester_received_signature_path',
        'requester_received_signed_at',
        'completed_pdf_path',
        'completed_at',
        'is_completed',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'linked_stb_id' => 'integer',
        'it_drafter_id' => 'integer',
        'user_id' => 'integer',
        'group_id' => 'integer',
        'use_date' => 'date',
        'expected_return_date' => 'date',
        'it_drafter_signed_at' => 'datetime',
        'requester_received_signed_at' => 'datetime',
        'completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'is_completed' => 'boolean',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'it_drafter_signature_path' => 'encrypted',
        'requester_received_signature_path' => 'encrypted',
    ];

    /**
     * Get the items for the Peminjaman.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PeminjamanItem::class);
    }

    /**
     * For backward compatibility with shared logic that expects linkedStb
     */
    public function linkedStb(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_stb_id');
    }

    public function linkedReturns(): HasMany
    {
        return $this->hasMany(self::class, 'linked_stb_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PeminjamanAttachment::class);
    }
}
