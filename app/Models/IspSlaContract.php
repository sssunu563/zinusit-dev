<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IspSlaContract extends Model
{
    protected $table    = 'isp_sla_contracts';
    protected $fillable = ['location', 'fct', 'provider', 'bandwidth', 'target_pct', 'is_active', 'sort_order'];
    protected $casts    = ['target_pct' => 'float', 'is_active' => 'boolean'];

    public function monthly(): HasMany
    {
        return $this->hasMany(IspSlaMonthly::class, 'contract_id');
    }

    public function downHistory(): HasMany
    {
        return $this->hasMany(IspDownHistory::class, 'contract_id');
    }
}
