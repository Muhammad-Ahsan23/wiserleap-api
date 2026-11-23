<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InquiryReply */
class InquiryReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'by' => $this->whenLoaded('user', fn () => $this->user?->name),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
