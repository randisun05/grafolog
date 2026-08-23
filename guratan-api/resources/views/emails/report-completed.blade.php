<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Anda Sudah Siap</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <h2>Laporan Anda Sudah Siap</h2>
    <p>Halo {{ $report->sample?->user?->name }},</p>
    <p>
        Laporan kepribadian ({{ ucfirst($report->tier) }}) Anda telah selesai dianalisis dan siap untuk dilihat.
    </p>
    <p>
        <a href="{{ config('app.frontend_url') }}/reports/{{ $report->id }}">
            Lihat Laporan
        </a>
    </p>
    <p>Terima kasih,<br>{{ config('app.name') }}</p>
</body>
</html>
