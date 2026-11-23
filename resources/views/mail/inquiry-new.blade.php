<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>New website inquiry</title></head>
<body style="margin:0;padding:24px;background:#f6f5fa;font-family:Arial,Helvetica,sans-serif;color:#17162b;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">
    <tr><td style="background:#140d2e;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:bold;">New website inquiry</td></tr>
    <tr><td style="padding:24px;">
      <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.6;">
        <tr><td style="color:#625f78;padding-right:16px;vertical-align:top;">Name</td><td>{{ $inquiry->name }}</td></tr>
        <tr><td style="color:#625f78;padding-right:16px;vertical-align:top;">Email</td><td>{{ $inquiry->email }}</td></tr>
        <tr><td style="color:#625f78;padding-right:16px;vertical-align:top;">Subject</td><td>{{ $inquiry->subject ?: '—' }}</td></tr>
        <tr><td style="color:#625f78;padding-right:16px;vertical-align:top;">Budget</td><td>{{ $inquiry->budget ?: '—' }}</td></tr>
        <tr><td style="color:#625f78;padding-right:16px;vertical-align:top;">Received</td><td>{{ $inquiry->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A T') }}</td></tr>
      </table>
      <div style="margin-top:20px;padding:16px;background:#faf9fd;border-left:3px solid #6d3fd6;font-size:15px;line-height:1.6;white-space:pre-wrap;">{{ $inquiry->message }}</div>
      <p style="margin-top:20px;font-size:13px;color:#625f78;">Reply to this email to answer {{ $inquiry->name }} directly, or reply from the admin dashboard (Inquiries) to keep the conversation on record.</p>
    </td></tr>
  </table>
</body>
</html>
