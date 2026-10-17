<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Verifikasi Perusahaan</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <p>Halo {{ $hr->name }},</p>
    @if ($company->isVerified())
        <p>Perusahaan <strong>{{ $company->name }}</strong> sudah diverifikasi. Anda sekarang bisa
            menambahkan posisi & kandidat, memesan layanan, dan memilih grafolog di Guratan.</p>
        <p><a href="{{ rtrim(config('app.frontend_url'), '/') }}/login">Masuk ke Guratan</a></p>
    @else
        <p>Mohon maaf, pendaftaran perusahaan <strong>{{ $company->name }}</strong> belum dapat kami setujui.</p>
        @if ($company->review_note)
            <p><strong>Catatan admin:</strong><br>{!! nl2br(e($company->review_note)) !!}</p>
        @endif
    @endif
    <p>Terima kasih,<br>{{ config('app.name') }}</p>
</body>
</html>
