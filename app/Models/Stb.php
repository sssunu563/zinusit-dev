<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stb extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'document_type',
        'movement_type',
        'linked_stb_id',
        'returned_at',
        'it_drafter_id',
        'it_checker_id',
        'it_approved_id',
        'po_doc_no',
        'req_doc_no',
        'user_id',
        'user_name',
        'user_company',
        'user_dept',
        'user_title',
        'user_phone',
        'user_email',
        'group_id',
        'location_name',
        'building',
        'batch_no',
        'use_date',
        'photo',
        'remark',
        'expected_return_date',
        'it_drafter_signature_path',
        'it_drafter_signed_at',
        'it_checker_signature_path',
        'it_checker_signed_at',
        'it_approved_signature_path',
        'it_approved_signed_at',
        'requester_received_signature_path',
        'requester_received_signed_at',
        'requester_dept_head_signature_path',
        'requester_dept_head_signed_at',
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
        'it_checker_id' => 'integer',
        'it_approved_id' => 'integer',
        'user_id' => 'integer',
        'group_id' => 'integer',
        'use_date' => 'date',
        'expected_return_date' => 'date',
        'it_drafter_signed_at' => 'datetime',
        'it_checker_signed_at' => 'datetime',
        'it_approved_signed_at' => 'datetime',
        'requester_received_signed_at' => 'datetime',
        'requester_dept_head_signed_at' => 'datetime',
        'completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'is_completed' => 'boolean',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'it_drafter_signature_path' => 'encrypted',
        'it_checker_signature_path' => 'encrypted',
        'it_approved_signature_path' => 'encrypted',
        'requester_received_signature_path' => 'encrypted',
        'requester_dept_head_signature_path' => 'encrypted',
    ];

    /**
     * Get the items for the STB.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StbItem::class);
    }

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
        return $this->hasMany(StbAttachment::class);
    }
}
