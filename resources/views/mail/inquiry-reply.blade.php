<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $replySubject }}</title></head>
<body style="margin:0;padding:24px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#17162b;">
  <div style="max-width:600px;margin:0 auto;font-size:15px;line-height:1.65;">
    <div style="white-space:pre-wrap;">{{ $body }}</div>
    <hr style="margin:28px 0 16px;border:0;border-top:1px solid #e6e4ef;">
    <p style="margin:0 0 8px;font-size:13px;color:#625f78;">On {{ $inquiry->created_at->timezone(config('app.timezone'))->format('M j, Y') }}, {{ $inquiry->name }} wrote:</p>
    <div style="padding-left:12px;border-left:3px solid #e6e4ef;color:#625f78;font-size:14px;white-space:pre-wrap;">{{ $inquiry->message }}</div>
  </div>
</body>
</html>
