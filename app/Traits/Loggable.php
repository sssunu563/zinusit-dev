<?php

namespace App\Traits;

use App\Models\ActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    /**
     * Boot the loggable trait for a model.
     */
    public static function bootLoggable(): void
    {
        static::created(function (Model $model) {
            $model->logAction('created');
        });

        static::updated(function (Model $model) {
            // Only log if attributes actually changed
            if ($model->wasChanged()) {
                $model->logAction('updated');
            }
        });

        static::deleted(function (Model $model) {
            $model->logAction('deleted');
        });
    }

    /**
     * Automatically log an action for this model.
     */
    public function logAction(string $actionType, ?string $note = null, ?Model $target = null): void
    {
        $old_values = [];
        $new_values = [];

        if ($actionType === 'updated') {
            $changes = $this->getChanges();
            
            // Ignore updated_at from being logged as a change if it's the only one
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }

            foreach ($changes as $key => $val) {
                $old_values[$key] = $this->getOriginal($key);
                $new_values[$key] = $val;
            }
        } elseif ($actionType === 'created') {
            $new_values = $this->getAttributes();
        } elseif ($actionType === 'deleted') {
            $old_values = $this->getAttributes();
        }

        // Attempt to auto-resolve target to mimic Snipe-IT behavior
        // In SnipeIT, when a User is updated, the Target is also the User itself.
        if (!$target && get_class($this) === 'App\\Models\\User') {
            $target = clone $this;
        }

        ActionLog::create([
            'user_id' => Auth::id(), // Who triggered the action
            'action_type' => $actionType,
            'item_type' => get_class($this),
            'item_id' => $this->getKey(),
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target ? $target->getKey() : null,
            'note' => $note,
            'log_meta' => [
                'old' => $old_values,
                'new' => $new_values,
            ],
        ]);
    }
}
