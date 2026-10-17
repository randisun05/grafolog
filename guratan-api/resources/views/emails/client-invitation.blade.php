<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Undangan Akun Guratan</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <h2>Akun Guratan Anda sudah dibuat</h2>
    <p>Halo {{ $client->name }},</p>
    <p>Grafolog {{ $grafolog->name }} mendaftarkan Anda di Guratan untuk analisis tulisan tangan.
        Atur kata sandi Anda lewat tautan berikut (berlaku 7 hari):</p>
    <p><a href="{{ $url }}">Aktifkan akun saya</a></p>
    <p>Setelah aktif, Anda bisa melihat laporan, jadwal konsultasi, dan promo produk Guratan langsung di aplikasi.</p>
    <p>Terima kasih,<br>{{ config('app.name') }}</p>
</body>
</html>
