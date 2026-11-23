<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;

class ActivityLogger
{
    public function log(ActivityAction $action, string $title): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_title' => mb_substr($title, 0, 160),
            'created_at' => now(),
        ]);
    }
}
