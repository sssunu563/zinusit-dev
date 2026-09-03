<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspSlaMonthly extends Model
{
    protected $table    = 'isp_sla_monthly';
    protected $fillable = ['contract_id', 'year', 'month', 'uptime_pct', 'notes', 'updated_by'];
    protected $casts    = ['uptime_pct' => 'float'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(IspSlaContract::class, 'contract_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
