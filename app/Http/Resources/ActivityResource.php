<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActivityLog */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->action->value,
            'title' => $this->subject_title,
            'by' => $this->whenLoaded('user', fn () => $this->user?->name),
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
