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
        .aspek .indikator { margin: 6px 0 0 0; padding-left: 16px; font-size: 11px; color: #333; }
        .aspek .indikator li { margin-bottom: 4px; }
        .aspek .indikator .kode { font-weight: bold; margin-right: 4px; }
        .aspek .indikator .keterangan { color: #555; margin: 2px 0 0 0; }
        .kombinasi { margin-top: 24px; border-top: 2px solid #ccc; padding-top: 12px; }
        .kombinasi h2 { font-size: 15px; margin-bottom: 8px; }
        .kombinasi-item { margin: 0 10px 12px 10px; padding-left: 10px; border-left: 2px solid #999; }
        .kombinasi-item h3 { font-size: 13px; margin-bottom: 2px; }
        .kombinasi-item p { text-align: justify; line-height: 1.4; margin: 0; }
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
                    @if (!empty($aspek['indikator_terkait']))
                        <ul class="indikator">
                            @foreach ($aspek['indikator_terkait'] as $indikator)
                                <li>
                                    <span class="kode">{{ $indikator['kode'] }}</span>{{ $indikator['nama'] }}
                                    @if (!empty($indikator['keterangan']))
                                        <p class="keterangan">{{ $indikator['keterangan'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    @if (!empty($report->data['kombinasi_ditemukan']))
        <div class="kombinasi">
            <h2>Pola Kombinasi Ditemukan</h2>
            @foreach ($report->data['kombinasi_ditemukan'] as $temuan)
                <div class="kombinasi-item">
                    <h3>{{ $temuan['nama'] }}</h3>
                    <p>{{ $temuan['teks_interpretasi'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
