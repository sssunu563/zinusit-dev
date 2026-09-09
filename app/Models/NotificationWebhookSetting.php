<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationWebhookSetting extends Model
{
    protected $fillable = [
        'enabled',
        'provider',
        'webhook_url',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'webhook_url' => 'encrypted',
    ];
}
