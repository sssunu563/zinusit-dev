<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspDownHistory extends Model
{
    protected $table    = 'isp_down_history';
    protected $fillable = ['contract_id', 'incident_date', 'case_description', 'action_taken', 'duration_minutes', 'created_by'];
    protected $casts    = ['incident_date' => 'date'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(IspSlaContract::class, 'contract_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
