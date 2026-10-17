<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Konsultasi Grafologi</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <h2>Jadwal Konsultasi Grafologi Anda</h2>
    <p>Halo {{ $consultation->sample?->user?->name }},</p>
    <p>Grafolog {{ $consultation->scheduler?->name }} telah menjadwalkan sesi konsultasi tier Master Anda:</p>
    <p>
        <strong>Waktu:</strong>
        {{ $consultation->scheduled_at->timezone(config('app.display_timezone', 'Asia/Jakarta'))->translatedFormat('l, d F Y H:i') }} WIB<br>
        <strong>Tautan:</strong> <a href="{{ $consultation->meeting_url }}">{{ $consultation->meeting_url }}</a>
    </p>
    @if ($consultation->notes)
        <p><strong>Catatan:</strong><br>{!! nl2br(e($consultation->notes)) !!}</p>
    @endif
    <p>Terima kasih,<br>{{ config('app.name') }}</p>
</body>
</html>
