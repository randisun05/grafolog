# Deployment Checklist — Guratan

Checklist ini disusun 2026-07-27 berdasarkan kondisi teknis nyata (lihat
`guratan-api/CLAUDE.md`, `guratan-web/CLAUDE.md`). Belum ada server production
yang benar-benar disiapkan — ini adalah panduan langkah, bukan konfirmasi
bahwa deployment sudah terjadi.

## guratan-api (Laravel)

### Environment variables yang WAJIB berubah dari .env dev

| Variable | Dev (sekarang) | Production (harus jadi) |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** — kalau lupa, stack trace + path file internal bocor ke siapa pun yang memicu error 500 (lihat catatan di guratan-api/CLAUDE.md, ini nyata terjadi saat debugging sesi 2026-07-27) |
| `APP_URL` | `http://localhost` | domain production API |
| `DB_*` | MySQL lokal `guratan_db` | kredensial DB production - **jangan** pakai kredensial dev |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173` | domain production `guratan-web` |
| `MAIL_MAILER` | `smtp` (akun Gmail pribadi) | provider transaksional yang layak untuk volume production (Gmail SMTP punya rate limit harian dan bukan dirancang untuk ini — lihat catatan di bawah) |
| `DOKU_CLIENT_ID` / `DOKU_SECRET_KEY` | kosong/sandbox | **kredensial PRODUCTION dari DOKU Back Office** — akun sandbox dan production DOKU terpisah total, jangan tertukar |
| `DOKU_IS_PRODUCTION` | `false` | `true` |
| `XENDIT_SECRET_KEY` / `XENDIT_CALLBACK_TOKEN` | kosong | (opsional, gateway alternatif) key `xnd_production_...` + token webhook dari Xendit Dashboard; Callback URL: `https://<api>/api/payments/xendit-notification` |
| `PAYMENT_GATEWAY` | `doku` | `doku` atau `xendit` (default; pembeli bisa memilih bila keduanya terisi) |
| `PRICE_MASTER` (di `config/pricing.php`) | placeholder 149000 | **konfirmasi angka resmi ke bisnis sebelum go-live** |

### Langkah deploy

1. `composer install --no-dev --optimize-autoloader`
2. `php artisan key:generate` (hanya kalau APP_KEY belum ada — jangan generate ulang di server yang sudah live, akan merusak data terenkripsi/token lama)
3. `php artisan migrate --force`
4. **`php artisan storage:link`** — jalankan fresh di server production, jangan copy symlink dari dev. Symlink pernah dangling gara-gara ini (lihat guratan-api/CLAUDE.md), pastikan diverifikasi resolve ke path yang benar setelah deploy: `ls -la public/storage`.
5. Queue worker: jalankan `php artisan queue:work` di bawah process manager (Supervisor/systemd), BUKAN `queue:listen` (itu untuk dev). Contoh Supervisor:
   ```ini
   [program:guratan-queue]
   command=php /path/to/guratan-api/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   numprocs=1
   ```
6. Daftarkan Notification URL production (`https://api.domain-anda.com/api/payments/notification`) di DOKU Back Office (akun production, bukan sandbox) — kalau tidak didaftarkan, DOKU tidak akan pernah memanggil endpoint ini dan status pembayaran tidak akan pernah ter-update dari `pending`.
7. HTTPS wajib — Sanctum Bearer token dan data psikologis sensitif tidak boleh lewat HTTP polos.
8. Jalankan `php artisan test` sekali lagi di environment yang mendekati production (PHP version yang sama) sebelum switch DNS/traffic.

### Catatan email production

Kredensial Gmail personal (`MAIL_MAILER=smtp`) yang dipakai saat development
punya kuota kirim harian yang ketat dan bisa kena flag spam pada volume
tinggi. Sebelum production sungguhan, pertimbangkan provider transaksional
(mis. yang mendukung SPF/DKIM domain sendiri) — bukan blocker MVP, tapi
jangan asumsikan akun Gmail dev ini siap dipakai untuk trafik nyata.

## guratan-web (Vue/Vite)

1. Buat `.env.production` (atau set env di CI/host) dengan
   `VITE_API_URL=https://api.domain-anda.com/api`.
2. `npm run build` → hasil ada di `dist/`.
3. Serve `dist/` sebagai static site (Nginx, Vercel, Netlify, dll.) dengan
   fallback SPA ke `index.html` (karena `vue-router` pakai `createWebHistory`,
   bukan hash mode — route seperti `/reports/5` harus tetap resolve ke
   `index.html` di sisi server, bukan 404).
4. Pastikan domain production terdaftar di `CORS_ALLOWED_ORIGINS` sisi API
   (lihat tabel di atas) — kalau tidak, semua request browser akan gagal
   kena CORS meskipun API-nya sendiri sehat.

## Sebelum benar-benar go-live (di luar konfigurasi teknis)

- [ ] `legal/privacy-policy.md` dan `legal/terms-of-service.md` sudah
  ditinjau & disetujui tim legal/bisnis, lalu benar-benar dipublikasikan
  sebagai halaman di `guratan-web` (belum ada route untuk ini — perlu dibuat
  `PrivacyPolicyView`/`TermsOfServiceView` dan ditautkan, misalnya di footer).
- [ ] Dua temuan keamanan terbuka dari review Fase 1 (lihat
  "Open security findings" di `guratan-api/CLAUDE.md`) sudah diputuskan:
  akses gambar rapid-tier, dan masa berlaku token Sanctum.
- [ ] Kebijakan retensi data (bagian 5 `privacy-policy.md`) sudah diisi,
  bukan placeholder.
- [ ] Verifikasi role "grafolog" masih self-declared saat registrasi (lihat
  Ketentuan Layanan pasal 4) — putuskan apakah ini cukup untuk MVP atau perlu
  proses verifikasi manual sebelum publik.

## Operasional (ditambahkan 2026-10-19)

### Periksa kesiapan
`php artisan app:preflight` (tambahkan `--strict` agar peringatan ikut menggagalkan; cocok dipakai di CI/deploy script). Memeriksa APP_DEBUG/URL https/CORS, koneksi DB, storage, MAIL_MAILER (bukan `log`), mode uji pembayaran mati, kredensial DOKU produksi, antrean (bukan sync + tidak ada job macet >10 menit), detak scheduler, administrator ada, tidak ada akun demo `@guratan.test`, LLM.

### Proses latar yang wajib hidup
1. **Scheduler** - cron: `* * * * * cd /path/guratan-api && php artisan schedule:run >> /dev/null 2>&1`. Menjalankan sweep supervisi (jam), `activity:prune` (harian, retensi log 90 hari), `sanctum:prune-expired`, `db:backup` (02:30), dan detak yang dibaca preflight.
2. **Worker antrean** (email, notifikasi) - contoh supervisor:
   ```
   [program:guratan-queue]
   command=php /path/guratan-api/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   stopwaitsecs=3600
   ```
3. Set `upload_max_filesize`/`post_max_size` (scan PDF sampai 30 MB; PHP default 2 MB) + `client_max_body_size` nginx.

### Cadangan
- `php artisan db:backup` (otomatis harian, simpan 14 terakhir di `storage/app/private/backups`). Ini **bukan** cadangan lepas-server: salin folder itu **dan** `storage/app/private` (scan PDF, CV, sertifikat) ke penyimpanan terpisah (mis. rsync/rclone terjadwal ke object storage terenkripsi). Uji pemulihan minimal sekali.
- Perlu `mysqldump` terpasang di server untuk MySQL/MariaDB.

### Insiden data pribadi (UU PDP: beri tahu subjek data dan lembaga dalam 3x24 jam)
1. Tangani/hentikan sumber kebocoran, catat apa/kapan/siapa terdampak (lihat `audit_logs`, `activity_logs`).
2. Admin → **Broadcast** → centang *Pemberitahuan penting / insiden data*, pilih peran terdampak: terkirim ke kotak masuk **dan email** walau pengguna mematikan email notifikasi.
3. Pemberitahuan tertulis ke lembaga berwenang di luar aplikasi.

### Sekali jalan setelah deploy pertama
- `php artisan samples:privatize-legacy-images --dry-run` lalu tanpa `--dry-run`: memindahkan gambar Rapid lama dari disk publik ke privat.
- Hapus data demo bila terbawa (`@guratan.test`); `DemoDataSeeder` menolak jalan di production.
- DOKU Back Office: isi Notification URL `https://<api>/api/payments/notification`; cek ulang nama channel di `config/payment_methods.php` terhadap dokumentasi DOKU terbaru.

## Mengaktifkan DOKU sungguhan (sandbox → produksi)

1. **Kredensial**: DOKU Back Office → Sandbox → Client ID + Secret Key. Isi di `guratan-api/.env` (jangan commit, jangan kirim lewat chat):
   ```
   PAYMENT_BYPASS=false
   DOKU_CLIENT_ID=...
   DOKU_SECRET_KEY=...
   DOKU_IS_PRODUCTION=false
   DOKU_CALLBACK_URL=https://<domain-frontend>/dashboard
   ```
   `php artisan config:clear`. Mode uji mati otomatis juga di `APP_ENV=production`.
2. **Notification URL** (Back Office → Settings → Payment → Notification): `https://<domain-api>/api/payments/notification`. DOKU tidak bisa memanggil localhost - untuk uji dari laptop buka tunnel ke port API, mis. `cloudflared tunnel --url http://localhost:8123` atau `ngrok http 8123`, lalu pakai URL https yang dihasilkan sebagai Notification URL dan `APP_URL`.
3. **Uji sandbox** (urutan): pesanan klien (B2C) → pesanan HR (B2B) → pembelian token → promo dengan batas metode bayar. Setelah bayar di halaman DOKU, pastikan status order jadi *diproses* dan `payments.notification_payload` terisi. Bandingkan isi payload dengan field yang dibaca `PaymentController::notification` (`order.invoice_number`, `transaction.status`) dan header `Client-Id`/`Signature`; bila beda, perbaiki di sana.
4. **Cek nama channel** di `config/payment_methods.php` (DOKU `payment_method_types`) terhadap dokumentasi DOKU terbaru.
5. **Produksi**: kredensial produksi + `DOKU_IS_PRODUCTION=true`, Notification URL produksi, lalu `php artisan app:preflight --strict`.
