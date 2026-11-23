<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Inquiry */
class InquiryResource extends JsonResource
{
    private bool $detailed = false;

    /** Full message, technical details and the reply thread (detail view). */
    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'budget' => $this->budget,
            'status' => $this->status->value,
            'preview' => $this->when(! $this->detailed, fn () => mb_strimwidth(preg_replace('/\s+/u', ' ', $this->message), 0, 140, '…')),
            'message' => $this->when($this->detailed, fn () => $this->message),
            'repliesCount' => $this->whenCounted('replies'),
            'replies' => $this->when($this->detailed, fn () => InquiryReplyResource::collection($this->whenLoaded('replies'))),
            'ipAddress' => $this->when($this->detailed, fn () => $this->ip_address),
            'userAgent' => $this->when($this->detailed, fn () => $this->user_agent),
            'notified' => $this->notified_at !== null,
            'createdAt' => $this->created_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'repliedAt' => $this->replied_at?->toIso8601String(),
        ];
    }
}
