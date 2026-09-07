<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Compliance Certificate</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; margin: 0; padding: 40px; }
        .header { border-bottom: 3px solid #111827; padding-bottom: 16px; margin-bottom: 24px; }
        .eyebrow { letter-spacing: 3px; text-transform: uppercase; color: #6b7280; font-size: 11px; }
        .title { font-size: 24px; font-weight: bold; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td { padding: 8px 0; font-size: 13px; }
        td.label { color: #6b7280; width: 200px; }
        .expired { color: #b91c1c; font-weight: bold; margin-top: 20px; }
        .footer { margin-top: 60px; font-size: 11px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <div class="eyebrow">Compliance Certificate</div>
        <div class="title">{{ $certificate->name ?? $certificate->course_title }}</div>
    </div>

    <table>
        <tr><td class="label">Learner</td><td>{{ $learnerName }}</td></tr>
        <tr><td class="label">Course</td><td>{{ $certificate->course_title }}</td></tr>
        <tr><td class="label">Certificate No.</td><td>{{ $certificate->certificate_number }}</td></tr>
        <tr><td class="label">Issued</td><td>{{ \Carbon\Carbon::parse($certificate->issued_at)->format('d M Y') }}</td></tr>
        @if(!empty($certificate->expires_at))
        <tr><td class="label">Valid Until</td><td>{{ \Carbon\Carbon::parse($certificate->expires_at)->format('d M Y') }}</td></tr>
        @endif
        @if(!empty($tags))
        <tr><td class="label">Tags</td><td>{{ implode(', ', $tags) }}</td></tr>
        @endif
        @if(!empty($certificate->description))
        <tr><td class="label">Description</td><td>{{ $certificate->description }}</td></tr>
        @endif
    </table>

    @if($isExpired)
        <div class="expired">This certificate has expired.</div>
    @endif

    @if(!empty($verifyUrl))
        <div class="footer">Verify this certificate at: {{ $verifyUrl }}</div>
    @endif
</body>
</html>
