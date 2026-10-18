<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
</head>
<body style="font-family: sans-serif; color: #222;">
    <h3>{{ $judul }}</h3>
    <p>{!! nl2br(e($isi)) !!}</p>
    @if ($tautan)
        <p><a href="{{ rtrim(config('app.frontend_url'), '/') }}{{ $tautan }}">Buka di Guratan</a></p>
    @endif
    <p style="color:#777;font-size:12px">Notifikasi ini juga tersedia di kotak masuk aplikasi Guratan.</p>
</body>
</html>
