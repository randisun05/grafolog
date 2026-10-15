<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kepribadian - Guratan</title>
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
    </style>
</head>
<body>
    @php
        $tanggal = fn ($d) => $d ? $d->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y') : '-';
        $subjek = $report->sample?->user;
        $konsultasi = $report->sample?->consultation;
    @endphp

    <header>Guratan · Laporan Kepribadian Grafologi · Rahasia — hanya untuk {{ $subjek?->name ?? 'pemilik laporan' }}</header>
    <footer>Laporan #{{ $report->id }} · Halaman <span class="page"></span></footer>

    <h1>Laporan Kepribadian Grafologi</h1>

    <table class="identitas">
        <tr><td class="k">Nama</td><td>{{ $subjek?->name ?? '-' }}</td><td class="k">Tier</td><td>{{ ucfirst($report->tier) }}</td></tr>
        <tr><td class="k">Grafolog</td><td>{{ $grafolog?->name ?? '-' }}</td><td class="k">Tanggal laporan</td><td>{{ $tanggal($report->generated_at) }}</td></tr>
        <tr><td class="k">No. laporan</td><td>#{{ $report->id }}</td><td class="k">Ditinjau</td><td>{{ $report->reviewed_at ? $tanggal($report->reviewed_at) : 'Belum ditinjau' }}</td></tr>
    </table>

    <p class="disclaimer">
        Laporan ini adalah alat insight reflektif berbasis grafologi, bukan diagnosis psikologis atau
        klinis. Grafologi tidak memiliki dasar ilmiah sekuat psikometri standar - jangan jadikan laporan
        ini satu-satunya dasar keputusan penting (rekrutmen, medis, hukum, finansial).
    </p>

    @if ($report->tier === 'master' && $konsultasi)
        <p class="konsultasi">
            <strong>Sesi konsultasi:</strong>
            {{ $konsultasi->scheduled_at->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i') }} WIB
            ({{ $konsultasi->status }}) · {{ $konsultasi->meeting_url }}
            @if ($konsultasi->notes)<br>{{ $konsultasi->notes }}@endif
        </p>
    @endif

    <table class="ringkasan">
        <tr><th>Sindrom</th><th>Polaritas</th><th>Rata-rata skor</th><th>Kategori</th></tr>
        @foreach ($report->data['sindrom'] ?? [] as $sindrom)
            <tr>
                <td>{{ $sindrom['kode_romawi'] }}. {{ $sindrom['nama'] }}</td>
                <td>{{ $sindrom['polaritas'] }}</td>
                <td>{{ $sindrom['rata_rata_skor'] }}</td>
                <td>{{ $sindrom['band_label_rata_rata'] }}</td>
            </tr>
        @endforeach
    </table>

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
