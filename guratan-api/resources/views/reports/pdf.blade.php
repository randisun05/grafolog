<!DOCTYPE html>
<html lang="{{ $bahasa }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t['judul'] }} - Guratan</title>
    <style>
        @page { margin: 28mm 18mm 20mm 18mm; }
        body { font-family: sans-serif; font-size: 11.5px; color: #222; }
        header { position: fixed; top: -18mm; left: 0; right: 0; font-size: 9px; color: #888; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 9px; color: #888; text-align: center; }
        footer .page:after { content: counter(page); }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .identitas { width: 100%; border-collapse: collapse; margin: 10px 0 14px; font-size: 11px; }
        .identitas td { padding: 3px 6px; border: 1px solid #e3e3e3; }
        .identitas td.k { width: 26%; background: #f7f7f7; color: #555; }
        .disclaimer { border: 1px solid #c9a227; background: #fbf6e6; padding: 8px 10px; font-size: 10px; margin-bottom: 14px; }
        .konsultasi { border: 1px solid #9cc4a8; background: #eef7f0; padding: 8px 10px; font-size: 10.5px; margin-bottom: 14px; }
        .ringkasan { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 10.5px; }
        .ringkasan th, .ringkasan td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        .ringkasan th { background: #f2f2f2; }
        .sindrom h2 { font-size: 14px; background: #f2f2f2; padding: 6px 10px; margin: 16px 0 4px; page-break-after: avoid; }
        .sindrom .meta { color: #555; font-size: 10.5px; margin: 0 0 8px 10px; }
        .aspek { margin: 0 10px 12px 10px; }
        .aspek h3 { font-size: 12.5px; margin: 0 0 2px; page-break-after: avoid; }
        .aspek .skor { color: #444; font-size: 10.5px; margin: 0 0 4px; page-break-after: avoid; }
        .aspek p { text-align: justify; line-height: 1.45; margin: 0; }
        .aspek .fallback { font-size: 9.5px; color: #a15c00; font-style: italic; margin-bottom: 3px; }
        .catatan { font-size: 9.5px; color: #777; margin: -8px 0 14px; }
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
    @php
        $locale = $bahasa === 'en' ? 'en' : 'id';
        $tanggal = fn ($d) => $d ? $d->copy()->timezone('Asia/Jakarta')->locale($locale)->translatedFormat($locale === 'en' ? 'F j, Y' : 'd F Y') : '-';
        $subjek = $report->sample?->user;
        $konsultasi = $report->sample?->consultation;
    @endphp

    <header>{{ str_replace(':nama', $subjek?->name ?? '-', $t['header']) }}</header>
    <footer>{{ str_replace(':id', $report->id, $t['footer']) }} <span class="page"></span></footer>

    <h1>{{ $t['judul'] }}</h1>

    <table class="identitas">
        <tr><td class="k">{{ $t['nama'] }}</td><td>{{ $subjek?->name ?? '-' }}</td><td class="k">{{ $t['tier'] }}</td><td>{{ ucfirst($report->tier) }}</td></tr>
        <tr><td class="k">{{ $t['grafolog'] }}</td><td>{{ $grafolog?->name ?? '-' }}</td><td class="k">{{ $t['tanggal'] }}</td><td>{{ $tanggal($report->generated_at) }}</td></tr>
        <tr><td class="k">{{ $t['nomor'] }}</td><td>#{{ $report->id }}</td><td class="k">{{ $t['ditinjau'] }}</td><td>{{ $report->reviewed_at ? $tanggal($report->reviewed_at) : $t['belum_ditinjau'] }}</td></tr>
    </table>

    <p class="disclaimer">{{ $t['disclaimer'] }}</p>

    @if ($report->tier === 'master' && $konsultasi)
        <p class="konsultasi">
            <strong>{{ $t['konsultasi'] }}:</strong>
            {{ $konsultasi->scheduled_at->copy()->timezone('Asia/Jakarta')->locale($locale)->translatedFormat($locale === 'en' ? 'l, F j, Y H:i' : 'l, d F Y H:i') }} {{ $t['zona_waktu'] }}
            ({{ $t['status_konsultasi'][$konsultasi->status] ?? $konsultasi->status }}) · {{ $konsultasi->meeting_url }}
            @if ($konsultasi->notes)<br>{{ $konsultasi->notes }}@endif
        </p>
    @endif

    <table class="ringkasan">
        <tr><th>{{ $t['sindrom'] }}</th><th>{{ $t['polaritas'] }}</th><th>{{ $t['rata_rata'] }}</th><th>{{ $t['kategori'] }}</th></tr>
        @foreach ($data['sindrom'] ?? [] as $sindrom)
            <tr>
                <td>{{ $sindrom['kode_romawi'] }}. {{ $sindrom['nama'] }}</td>
                <td>{{ $sindrom['polaritas'] }}</td>
                <td>{{ $sindrom['rata_rata_skor'] }}</td>
                <td>{{ $sindrom['band_label_rata_rata'] }}</td>
            </tr>
        @endforeach
    </table>
    @if ($t['catatan_terjemahan'])
        <p class="catatan">{{ $t['catatan_terjemahan'] }}</p>
    @endif

    @foreach ($data['sindrom'] ?? [] as $sindrom)
        <div class="sindrom">
            <h2>{{ $sindrom['kode_romawi'] }}. {{ $sindrom['nama'] }}</h2>
            <p class="meta">
                {{ $t['polaritas'] }}: {{ $sindrom['polaritas'] }} &middot;
                {{ $t['rata_rata'] }}: {{ $sindrom['rata_rata_skor'] }} ({{ $sindrom['band_label_rata_rata'] }})
            </p>
            @foreach ($sindrom['aspek'] as $aspek)
                <div class="aspek">
                    <h3>{{ $aspek['nama'] }}</h3>
                    <p class="skor">{{ $t['skor'] }}: {{ $aspek['skor'] }}/10 - {{ $aspek['band_label'] }}</p>
                    @if ($bahasa === 'id' && ($aspek['narasi_bahasa'] ?? 'id') === 'en')
                        <p class="fallback">{{ $t['belum_diterjemahkan'] }}</p>
                    @endif
                    <p>{!! nl2br(e($aspek['narasi'])) !!}</p>
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
