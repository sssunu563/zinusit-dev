<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnipeitUser extends Model
{
    protected $table = 'snipeit_users';

    protected $fillable = [
        'snipeit_id',
        'name',
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'jobtitle',
        'employee_num',
        'manager_id',
        'manager_name',
        'location_id',
        'location_name',
        'department_id',
        'department',
        'company_id',
        'company',
        'activated',
        'avatar',
        'raw_data',
        'snipeit_created_at',
        'snipeit_updated_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'activated' => 'boolean',
        'snipeit_created_at' => 'datetime',
        'snipeit_updated_at' => 'datetime',
    ];

    public $incrementing = true;

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->username ?? '-');
    }

    /**
     * Assets assigned to this user
     */
    public function assets()
    {
        return $this->hasMany(SnipeitAsset::class, 'assigned_to');
    }
}
