<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A reply written in the dashboard, sent to the visitor with their message quoted. */
class InquiryReplyMail extends Mailable
{
    use Queueable;

    public function __construct(public Inquiry $inquiry, public string $replySubject, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('contact.reply_from_name')),
            replyTo: [new Address((string) config('contact.notify_email'), (string) config('contact.reply_from_name'))],
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.inquiry-reply', text: 'mail.inquiry-reply-text');
    }
}
