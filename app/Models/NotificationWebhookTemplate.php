<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationWebhookTemplate extends Model
{
    protected $fillable = [
        'event',
        'enabled',
        'title',
        'message',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
