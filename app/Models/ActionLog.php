<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActionLog extends Model
{
    protected $fillable = [
        'user_id',
        'action_type',
        'item_type',
        'item_id',
        'target_type',
        'target_id',
        'snipeit_id',
        'snipeit_type',
        'note',
        'log_meta',
    ];

    protected $casts = [
        'log_meta' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
