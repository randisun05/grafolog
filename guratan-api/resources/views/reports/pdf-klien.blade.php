<!DOCTYPE html>
<html lang="{{ $report->narasi_bahasa === 'en' ? 'en' : 'id' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->narasi_bahasa === 'en' ? 'Personality Report' : 'Laporan Kepribadian' }} - Guratan</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #222; line-height: 1.7; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .subtitle { color: #666; margin-top: 4px; margin-bottom: 28px; }
        .narasi { text-align: justify; white-space: pre-line; }
    </style>
</head>
<body>
    <h1>{{ $report->narasi_bahasa === 'en' ? 'Personality Report' : 'Laporan Kepribadian Grafologi' }}</h1>
    <p class="subtitle">
        Tier: {{ ucfirst($report->tier) }} &middot;
        {{ $report->narasi_bahasa === 'en' ? 'Generated' : 'Dibuat' }}:
        {{ optional($report->generated_at)->translatedFormat('d F Y H:i') }}
    </p>

    <p class="narasi">{!! nl2br(e($report->narasi_terpadu)) !!}</p>
</body>
</html>
