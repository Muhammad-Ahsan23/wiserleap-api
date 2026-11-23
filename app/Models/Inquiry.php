<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'subject', 'budget', 'message', 'status', 'ip_address', 'user_agent', 'fingerprint', 'notified_at', 'read_at', 'replied_at'])]
#[Hidden(['fingerprint'])]
class Inquiry extends Model
{
    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'notified_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public static function fingerprint(string $email, string $message): string
    {
        return hash('sha256', mb_strtolower(trim($email)).'|'.preg_replace('/\s+/u', ' ', trim($message)));
    }

    public function replies(): HasMany
    {
        return $this->hasMany(InquiryReply::class)->oldest('created_at')->oldest('id');
    }

    /** Opening a new inquiry marks it read. */
    public function markRead(): void
    {
        if ($this->status === InquiryStatus::New) {
            $this->update(['status' => InquiryStatus::Read, 'read_at' => now()]);
        }
    }
}
