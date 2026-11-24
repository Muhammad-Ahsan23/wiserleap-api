<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MediaItem */
class MediaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'name' => $this->name,
            'alt' => $this->alt ?? '',
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'uploadedBy' => $this->whenLoaded('user', fn () => $this->user?->name),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
