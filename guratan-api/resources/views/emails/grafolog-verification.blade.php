<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Verifikasi Grafolog</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <p>Halo {{ $grafolog->name }},</p>
    @if ($grafolog->grafolog_status === 'verified')
        <p>Akun grafolog Anda sudah diverifikasi. Anda sekarang bisa mendaftarkan klien, menerima
            penugasan, dan mengisi measurement worksheet di Guratan.</p>
        <p><a href="{{ rtrim(config('app.frontend_url'), '/') }}/login">Masuk ke Guratan</a></p>
    @else
        <p>Mohon maaf, pendaftaran grafolog Anda belum dapat kami setujui.</p>
        @if ($grafolog->grafolog_review_note)
            <p><strong>Catatan admin:</strong><br>{!! nl2br(e($grafolog->grafolog_review_note)) !!}</p>
        @endif
    @endif
    <p>Terima kasih,<br>{{ config('app.name') }}</p>
</body>
</html>
