<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kepribadian - Guratan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .subtitle { color: #666; margin-top: 4px; margin-bottom: 24px; }
        .sindrom { margin-bottom: 20px; page-break-inside: avoid; }
        .sindrom h2 { font-size: 15px; background: #f2f2f2; padding: 6px 10px; margin-bottom: 4px; }
        .sindrom .meta { color: #555; font-size: 11px; margin: 0 0 8px 10px; }
        .aspek { margin: 0 10px 12px 10px; }
        .aspek h3 { font-size: 13px; margin-bottom: 2px; }
        .aspek .skor { color: #444; font-size: 11px; margin-bottom: 4px; }
        .aspek p { text-align: justify; line-height: 1.4; margin: 0; }
    </style>
</head>
<body>
    <h1>Laporan Kepribadian Grafologi</h1>
    <p class="subtitle">
        Tier: {{ ucfirst($report->tier) }} &middot;
        Dibuat: {{ optional($report->generated_at)->translatedFormat('d F Y H:i') }}
    </p>

    @foreach ($report->data['sindrom'] ?? [] as $sindrom)
        <div class="sindrom">
            <h2>{{ $sindrom['kode_romawi'] }}. {{ $sindrom['nama'] }}</h2>
            <p class="meta">
                Polaritas: {{ $sindrom['polaritas'] }} &middot;
                Rata-rata skor: {{ $sindrom['rata_rata_skor'] }} ({{ $sindrom['band_label_rata_rata'] }})
            </p>
            @foreach ($sindrom['aspek'] as $aspek)
                <div class="aspek">
                    <h3>{{ $aspek['nama'] }}</h3>
                    <p class="skor">Skor: {{ $aspek['skor'] }}/10 - {{ $aspek['band_label'] }}</p>
                    <p>{!! nl2br(e($aspek['narasi'])) !!}</p>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
