<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifySiteKey;
use App\Http\Requests\Public\ContactRequest;
use App\Mail\NewInquiryMail;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ContactController extends Controller
{
    /**
     * Website contact form (called by the Next.js server, see VerifySiteKey).
     * The inquiry is always saved first; the email to the inbox is best-effort
     * so a mail outage never loses a message (notified=false shows it in the dashboard).
     */
    public function store(ContactRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fingerprint = Inquiry::fingerprint($data['email'], $data['message']);

        // Double-click / resubmit: same email + message within the window -> same answer, no duplicate.
        $duplicate = Inquiry::query()
            ->where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subMinutes(config('contact.duplicate_window_minutes')))
            ->exists();

        if (! $duplicate) {
            $inquiry = Inquiry::create($data + [
                'ip_address' => VerifySiteKey::visitorIp($request),
                'user_agent' => Str::limit((string) $request->header('X-Visitor-Agent'), 250, ''),
                'fingerprint' => $fingerprint,
            ]);

            try {
                Mail::to(config('contact.notify_email'))->send(new NewInquiryMail($inquiry));
                $inquiry->forceFill(['notified_at' => now()])->saveQuietly();
            } catch (Throwable $e) {
                Log::error('Inquiry notification email failed', ['inquiry_id' => $inquiry->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'Thanks! Your message has been sent. We\'ll get back to you soon.'], 201);
    }
}
