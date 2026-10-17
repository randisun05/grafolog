# Panduan Uji Manual End-to-End — Guratan

Disusun 2026-10-17 dari kode & CLAUDE.md. Mode pembayaran: **bypass** (tanpa DOKU).
Pembayaran DOKU asli, SMTP asli, dan LLM asli **tidak** bisa diuji di sini (lihat bagian 9).

## 1. Persiapan (sudah dikerjakan di mesin ini)

| Item | Status |
|---|---|
| PHP 8.3, Composer, Node 24 | ada |
| `guratan-api/vendor` | ada |
| `guratan-web/node_modules` | di-install (`npm ci`) |
| MySQL Laragon 8.4.3 (`C:\laragon\...`) | datadir ada; DB `guratan` sudah termigrasi, KB lengkap (8 sindrom/40 aspek/704 indikator, 363 rule), data demo 13 akun |
| `.env` API | `DB_DATABASE=guratan`, `PAYMENT_BYPASS=true`, `MAIL_MAILER=log`, `LLM_PROVIDER=none`, `QUEUE_CONNECTION=database`, `FRONTEND_URL=http://127.0.0.1:5173`, `CORS_ALLOWED_ORIGINS` memuat localhost/127.0.0.1:5173 |
| `.env.development` web | `VITE_API_URL=http://127.0.0.1:8123/api` |
| `start-dev.bat` | path MySQL diperbaiki (`D:\laragon\...8.0.46` → `C:\laragon\...8.4.3`) + ditambah **queue worker** |

### Menyalakan semua
Klik dua kali `start-dev.bat` → 4 jendela: MySQL, API `:8123`, Web `:5173`, Queue.
**Queue worker wajib hidup** — tanpa itu email "laporan siap", jadwal konsultasi, dan reset password tidak terkirim.
Buka **http://localhost:5173** (Vite hanya listen di `localhost`; `127.0.0.1:5173` tidak tersambung). Link di email log mengarah ke `127.0.0.1:5173` (dari `FRONTEND_URL`) — ganti manual jadi `localhost` saat membukanya.

Catatan: saat panduan ini dibuat, MySQL + API + Vite sudah saya nyalakan dan login demo terverifikasi (API 200, Web 200).
Kalau jendela tertutup, jalankan ulang `start-dev.bat`. MySQL di mesin ini kadang mati sendiri → restart.

### Email (mode log)
Tidak ada email sungguhan. Isi email ada di `guratan-api\storage\logs\laravel.log` (cari `Subject:` / `reset-password`).

### Reset data ke awal (HAPUS semua data DB `guratan`)
```
cd guratan-api
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoDataSeeder
```
Opsional: untuk akun admin sendiri isi `ADMIN_EMAIL`/`ADMIN_PASSWORD` di `.env` sebelum `--seed`. Kalau tidak, pakai `admin.demo@guratan.test`.

### Berkas yang perlu disiapkan
- 2–3 foto (jpg/png/webp, < 10 MB) untuk "tulisan tangan".
- `kandidat.csv` untuk HR:
  ```
  name,email
  Rina Uji,rina.uji@majujaya.test
  Joko Uji,joko.uji@majujaya.test
  ```

## 2. Akun (password semua: `DemoGuratan123`)

| Peran | Email |
|---|---|
| Administrator | admin.demo@guratan.test |
| Supervisor | supervisor@guratan.test |
| Grafolog | ani.grafolog@guratan.test, bima.grafolog@guratan.test |
| HR | hr@majujaya.test |
| Klien | budi / citra / dewi / eko / fajar / gita @guratan.test |
| Kandidat | hendra / intan @majujaya.test |

Data demo siap pakai: Budi, Citra (+konsultasi Master), Hendra = laporan **disetujui**; Fajar = **menunggu review**;
Gita = **perlu revisi**; Dewi = **lunas, belum ditugaskan**; Eko = **belum bayar**; Intan = kandidat **belum ditugaskan**.

Tips: pakai **2 profil browser / jendela incognito** supaya bisa login dua peran sekaligus.

## 3. Alur utama (urut — ini jalur bisnis inti)

### A. Smoke & landing (tanpa login)
1. `/` tampil: hero, harga (49.000 / 149.000 dari API), footer `/privasi` `/syarat` terbuka.
2. Toggle tema terang/gelap di navbar jalan. Ctrl+K membuka command palette setelah login.

### B. Admin menyiapkan platform (admin.demo)
1. `/dashboard` → KPI admin muncul.
2. `/admin/pricing`: harga aktif comprehensive 49.000, master 149.000. Ubah salah satu → riwayat harga bertambah. (Kembalikan.)
3. `/admin/discounts`: buat kode `UJI50` (persen 50, semua tier). Nonaktifkan/aktifkan.
4. `/admin/announcements`: buat pengumuman untuk peran `user` → nanti muncul di dashboard klien.
5. `/admin/content`: ubah satu teks beranda → cek `/` berubah.
6. `/admin/users`: buat **grafolog baru**; buat **perusahaan** lalu **HR** baru (HR wajib `company_id`).
7. `/admin/knowledge` (7 tab): cek tab Aspek menampilkan teks EN & ID berdampingan; tab Matriks terbuka; jangan hapus data KB.
8. (Opsional, token) `/admin/tokens`: kalau dikosongkan, fitur token **tidak aktif** (normal: "Belum diatur"). Lihat bagian H.

### C. Klien B2C memesan (klien baru, bypass bayar)
1. `/register`: daftar klien baru **tanpa** centang persetujuan → ditolak; lalu dengan centang → masuk `/dashboard`. Tidak ada pilihan peran (selalu klien).
2. Dashboard menampilkan pengumuman dari B.4 (bisa ditutup, muncul lagi saat reload).
3. `/pesan`: pilih tier Comprehensive. Coba bayar **tanpa foto** → ditolak (wajib 1–5 foto). Unggah foto.
4. Isi kode `UJI50` → total terpotong 50%. Kode ngawur → ditolak.
5. "Bayar Sekarang" → **tidak redirect DOKU**, toast "Mode uji", kembali ke `/dashboard`. KPI "dalam proses" bertambah.
6. Klik bayar ulang/refresh → tidak membuat pesanan ganda.
7. `/riwayat`: **belum ada laporan** (belum dikerjakan).

### D. Admin menugaskan grafolog (admin.demo)
1. `/admin/orders` → filter *unassigned*: pesanan klien baru muncul (juga Dewi). Filter *unpaid*: Eko.
2. Tugaskan pesanan ke **ani.grafolog**. Coba tugaskan pesanan Eko (belum bayar) → harus ditolak/tidak tersedia.
3. Foto tulisan tangan terlihat di kolom foto.

### E. Grafolog mengerjakan (ani.grafolog)
1. `/grafolog/ditugaskan` → pesanan tadi muncul. Klik → `/portal-grafolog?sampleId=…` langsung ke langkah "Isi Skor".
2. Kartu "Foto Tulisan Tangan": lihat foto, lightbox, coba unggah tambahan.
3. **Mode Manual**: isi 40 aspek (skor 1–10). Panel Auto Calculation kanan ter-update live. Coba kirim dengan aspek belum lengkap → ditolak.
4. **Mode Worksheet** (jalur utama grafolog): isi hasil ukur beberapa variabel (mis. Middle zone height, Letter spacing, Ovals height min/maks) → "Simpan Hasil Ukur" → checklist indikator otomatis tercentang dengan badge **Auto** + alasan. Centang manual satu indikator → cek badge **Terkait**/**Ciri sama**. Uncheck yang memicu cascade → muncul konfirmasi. Centang kotak "Saya sudah meninjau…" → "Terapkan Skor Checklist ke Form".
5. Kirim → langkah 3 "Laporan Selesai". Kirim ulang sample yang sama → ditolak 422.
6. Buka laporan: status review **menunggu_review**.

### F. Gerbang QA supervisor (supervisor@guratan.test)
1. Sebelum review: login **klien** (pemesan) → laporan **tidak terlihat** di `/riwayat` (hanya yang disetujui).
2. Supervisor `/supervisor/reviews` → laporan masuk antrean. **Minta Revisi** + catatan.
3. Ani: `/grafolog/ditugaskan` → bagian "Perlu Revisi dari Supervisor" memuat catatan. Buka laporan → **Koreksi Skor** (ubah skor / mode worksheet) + alasan; atau **Edit narasi** satu aspek (badge "✏️ diedit manual"). Keduanya mengembalikan laporan ke `menunggu_review`.
4. "Riwayat Perubahan": versi lama tersimpan, klik untuk lihat snapshot.
5. Supervisor **Setujui**.
6. Cek `laravel.log`: email `ReportCompletedMail` ke klien ada (queue worker harus hidup).
7. Klien login → laporan sekarang muncul di `/riwayat`.

### G. Klien membaca laporan
1. Buka `/reports/{id}`: disclaimer "insight reflektif, bukan diagnosis" di atas.
2. Ganti **Bahasa Indonesia ⇄ English**; narasi ID adalah **draf terjemahan AI** (wajar ada catatan "terjemahan belum tersedia" untuk beberapa).
3. Unduh **PDF ID** dan **PDF EN** → buka: identitas, tabel ringkasan sindrom, disclaimer, nomor halaman.
4. Klien **tidak** melihat tombol Koreksi/Edit narasi.

### H. Tier Master + konsultasi
1. Klien `/pesan` → tier **Master** → bayar (bypass) → admin tugaskan → grafolog skor → supervisor setujui (ulang C–F, ringkas).
2. Grafolog di laporan: `ConsultationPanel` → isi jadwal (**masa depan**) + link `https://…`. Tanggal lampau / link `http://` → ditolak.
3. `laravel.log`: email `ConsultationScheduledMail`. Klien melihat jadwal di laporan.
4. Bandingkan dengan Citra (data demo sudah punya konsultasi).

### I. Jalur grafolog langsung (walk-in) — ani.grafolog
1. `/portal-grafolog` → cari klien dengan email yang **tidak ada** → 404 → "Daftarkan klien baru" (wajib centang konfirmasi persetujuan). Kosongkan password → muncul **banner password yang digenerate** (catat).
2. Pilih tier → Buat Sample → isi skor → selesai. Sample jalur ini **tidak** minta bayar klien.
3. Login klien baru dengan password tadi → laporan baru muncul **setelah** disetujui supervisor.
4. Cari klien yang sudah ada (mis. budi@guratan.test) → ditemukan.

### J. Jalur HR / B2B — hr@majujaya.test
1. `/hr/candidates`: unggah `kandidat.csv` (centang konfirmasi persetujuan kandidat) → 2 kandidat muncul.
2. CSV dengan 1 baris email tidak valid → **seluruh** impor ditolak dengan nomor baris (tidak ada yang tersimpan separuh).
3. Tugaskan kandidat ke grafolog (dropdown). Kolom Foto: unggah foto.
4. Ani mengerjakan (seperti E) → supervisor setujui (seperti F).
5. HR hanya melihat laporan **disetujui**; kandidat bisa login sendiri (akun dibuat saat impor — password acak, pakai "Lupa password" untuk masuk).
6. Intan (demo, belum ditugaskan) bisa dipakai uji tugaskan lagi.

### K. Lupa password
1. `/lupa-password` isi email klien → pesan generik (sama untuk email tak terdaftar).
2. Ambil link di `laravel.log` → `/reset-password?token=…&email=…` → password baru → login lama gagal, baru berhasil.
3. Spam >6x/menit → kena rate limit.

### L. Token grafolog (opsional — fitur mati sampai admin atur)
1. Admin `/admin/tokens`: set harga per token & kebutuhan token per tier → aktif.
2. Ani `/token-saya`: beli token (bypass → langsung lunas, saldo bertambah, toast "Mode uji"). Coba kode diskon scope `token`.
3. Saldo kurang saat submit laporan → diblokir 402. Setelah koreksi laporan, token **tidak** ditagih ulang.
4. Kembalikan harga/biaya ke kosong bila ingin fitur mati lagi.

## 4. Uji negatif & keamanan (cepat, tiap satu klik/URL)

| Uji | Hasil yang benar |
|---|---|
| Klien buka `/admin/users`, `/portal-grafolog` | dialihkan |
| Grafolog buka laporan sample milik grafolog lain via URL | 403/ditolak |
| Klien Budi buka `/reports/{id}` punya Citra | ditolak |
| Klien Eko (belum bayar) → grafolog coba skor sample-nya | 402 |
| Grafolog kirim skor 11 / 0 | ditolak |
| Registrasi publik dengan role grafolog (via API) | ditolak |
| Login salah 20x/menit | rate limit |
| Token kedaluwarsa/dihapus di localStorage | redirect `/login` |
| Laporan belum disetujui diunduh PDF oleh klien | ditolak |

## 5. Verifikasi cepat DB (opsional)
```
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root guratan
SELECT id,status FROM payments ORDER BY id DESC LIMIT 5;      -- notification_payload {"bypass":true}
SELECT id,review_status FROM personality_reports ORDER BY id DESC LIMIT 5;
SELECT aksi,created_at FROM audit_logs ORDER BY id DESC LIMIT 15;  -- lihat_laporan, pembayaran_mode_uji, koreksi_skor_laporan, ...
SELECT COUNT(*) FROM failed_jobs;                              -- harus 0
```

## 6. Tes otomatis (sanity sebelum manual)
```
cd guratan-api
php artisan test        # sqlite in-memory, tidak menyentuh DB guratan; 438 tes
cd ../guratan-web
npm run lint && npm run build
```

## 7. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| `SQLSTATE[2002] actively refused` | MySQL mati → jalankan ulang `start-dev.bat` |
| Web error jaringan / CORS | API belum di port **8123**, atau buka web lewat host selain `localhost`/`127.0.0.1:5173` |
| Port 8123 balasan aneh | proses lama menempati; `netstat -ano \| findstr 8123`, matikan |
| Email tak muncul di log | queue worker tidak jalan; cek jendela "Guratan - Queue" |
| Foto tidak muncul | `php artisan storage:link` (foto sendiri lewat endpoint ber-token, tapi cek disk `storage/app/private`) |
| Laporan memuat "(narasi tidak tersedia di sumber data)" | celah KB sumber (aspek 09, 08, 35) — bukan bug |
| Perubahan `.env` tak terbaca | `php artisan config:clear`, restart API |

## 8. Hal yang perlu diingat saat menilai hasil
- Skor/narasi dari KB; `LLM_PROVIDER=none` → tidak ada panggilan LLM. Narasi ID berasal dari kolom `*_id` (draf AI).
- Indikator `keterangan` (di `indikator_terkait`) masih bahasa Inggris di kedua versi — memang belum diterjemahkan.
- Centang "ciri bersama" membuat urutan centang tidak memengaruhi skor; laporan lama tidak berubah.
- Supervisor QA **sengaja** menyembunyikan laporan dari klien/HR sampai disetujui, termasuk setelah koreksi.

## 9. Tidak bisa diuji sekarang
- Redirect & webhook DOKU asli (butuh kredensial sandbox; matikan `PAYMENT_BYPASS` saat sudah ada).
- Email SMTP sungguhan (`MAIL_MAILER=log`).
- Narasi LLM (butuh `LLM_PROVIDER=gemini` + key).
- Deployment production (`APP_DEBUG=false`, bypass otomatis mati di production).
- Dashboard B2B lanjutan, billing perusahaan, CV Rapid (sengaja belum dibangun).
