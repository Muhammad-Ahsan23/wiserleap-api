<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/** Sent to the business inbox; "Reply" in the mail app answers the visitor. */
class NewInquiryMail extends Mailable
{
    use Queueable;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->inquiry->email, $this->inquiry->name)],
            subject: Str::limit('New website inquiry: '.($this->inquiry->subject ?: $this->inquiry->name), 180),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-Inquiry-Id' => (string) $this->inquiry->id]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.inquiry-new',
            text: 'mail.inquiry-new-text',
            with: ['dashboardUrl' => config('admin.frontend_url')],
        );
    }
}
