{{-- Plain-text part: not rendered as HTML, so values are printed unescaped. --}}
{!! $body !!}

--
On {!! $inquiry->created_at->timezone(config('app.timezone'))->format('M j, Y') !!}, {!! $inquiry->name !!} wrote:

{!! preg_replace('/^/m', '> ', $inquiry->message) !!}
