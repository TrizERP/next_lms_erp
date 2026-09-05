<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; text-align: center; color: #1f2937; }
        .frame { border: 6px double #4f46e5; padding: 60px 50px; margin: 20px; }
        .eyebrow { letter-spacing: 4px; text-transform: uppercase; color: #6b7280; font-size: 12px; }
        .title { font-size: 34px; font-weight: bold; margin: 10px 0 30px; color: #312e81; }
        .presented { font-size: 14px; color: #6b7280; }
        .name { font-size: 28px; font-weight: bold; margin: 10px 0 20px; }
        .course { font-size: 18px; margin: 10px 0 30px; }
        .meta { font-size: 12px; color: #6b7280; margin-top: 40px; }
        .expired { color: #b91c1c; font-weight: bold; margin-top: 10px; }
        .code { font-size: 11px; color: #9ca3af; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="eyebrow">Certificate of Completion</div>
        <div class="title">{{ $certificate->name ?? $certificate->course_title }}</div>

        <div class="presented">This certifies that</div>
        <div class="name">{{ $learnerName }}</div>

        <div class="presented">has successfully completed</div>
        <div class="course">{{ $certificate->course_title }}</div>

        @if(!empty($certificate->description))
            <p>{{ $certificate->description }}</p>
        @endif

        @if(!empty($tags))
            <p style="font-size: 12px; color: #6b7280;">{{ implode(' · ', $tags) }}</p>
        @endif

        <div class="meta">
            Certificate No: {{ $certificate->certificate_number }}<br>
            Issued: {{ \Carbon\Carbon::parse($certificate->issued_at)->format('d M Y') }}
            @if(!empty($certificate->expires_at))
                &nbsp;·&nbsp; Valid until {{ \Carbon\Carbon::parse($certificate->expires_at)->format('d M Y') }}
            @endif
        </div>

        @if($isExpired)
            <div class="expired">This certificate has expired.</div>
        @endif

        @if(!empty($verifyUrl))
            <div class="code">Verify at: {{ $verifyUrl }}</div>
        @endif
    </div>
</body>
</html>
