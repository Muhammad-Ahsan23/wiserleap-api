{{-- Plain-text part: not rendered as HTML, so values are printed unescaped. --}}
New website inquiry

Name:     {!! $inquiry->name !!}
Email:    {!! $inquiry->email !!}
Subject:  {!! $inquiry->subject ?: '-' !!}
Budget:   {!! $inquiry->budget ?: '-' !!}
Received: {!! $inquiry->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A T') !!}

{!! $inquiry->message !!}

--
Reply to this email to answer {!! $inquiry->name !!} directly, or reply from the admin dashboard (Inquiries) to keep the conversation on record.
