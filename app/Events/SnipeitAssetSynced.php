<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SnipeitAssetSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $assetId,
        public string $action, // 'created', 'updated', 'deleted'
    ) {
    }
}
