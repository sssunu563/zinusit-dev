<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CctvNvrRecord extends Model
{
    protected $table    = 'cctv_nvr_records';
    protected $fillable = [
        'device_id', 'year', 'month',
        'check_date', 'last_record_date', 'duration_days',
        'notes', 'updated_by',
    ];
    protected $casts = [
        'check_date'       => 'date',
        'last_record_date' => 'date',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(CctvDevice::class, 'device_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
