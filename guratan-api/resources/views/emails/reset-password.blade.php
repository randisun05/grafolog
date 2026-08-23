<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Reset Kata Sandi</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <h2>Reset Kata Sandi</h2>
    <p>Ada permintaan untuk mereset kata sandi akun Guratan Anda. Kalau ini bukan Anda, abaikan email ini saja.</p>
    <p>
        <a href="{{ $resetUrl }}">Reset Kata Sandi</a>
    </p>
    <p style="color: #666; font-size: 12px;">Tautan ini berlaku 60 menit.</p>
    <p>Terima kasih,<br>{{ config('app.name') }}</p>
</body>
</html>
