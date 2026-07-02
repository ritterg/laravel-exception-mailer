<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1f2937;">
    <h2 style="margin-bottom: 0.25rem;">Rate limit exceeded</h2>
    <p style="color: #6b7280; margin-top: 0;">
        An IP address tripped a request rate limit on
        <strong>{{ config('app.name') }}</strong> ({{ config('app.env') }}).
    </p>
    <table cellpadding="6" style="border-collapse: collapse; border: 1px solid #e5e7eb;">
        <tr><td style="border: 1px solid #e5e7eb;"><strong>IP</strong></td><td style="border: 1px solid #e5e7eb;">{{ $ip }}</td></tr>
        <tr><td style="border: 1px solid #e5e7eb;"><strong>Request</strong></td><td style="border: 1px solid #e5e7eb;">{{ $method }} {{ $url }}</td></tr>
        <tr><td style="border: 1px solid #e5e7eb;"><strong>User agent</strong></td><td style="border: 1px solid #e5e7eb;">{{ $userAgent ?? '—' }}</td></tr>
        @if($retryAfter)
        <tr><td style="border: 1px solid #e5e7eb;"><strong>Retry after</strong></td><td style="border: 1px solid #e5e7eb;">{{ $retryAfter }}s</td></tr>
        @endif
    </table>
</body>
</html>
