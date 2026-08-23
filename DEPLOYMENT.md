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
8. Jalankan `php artisan test` sekali lagi di environment yang mendekati production (PHP version yang sama) sebelum switch DNS/traffic. **CI otomatis ada sejak 2026-08-23** (`.github/workflows/ci.yml` — 2 job paralel: backend menjalankan `vendor/bin/pint --test` + `php artisan test` di PHP 8.3, frontend menjalankan `npm run lint` + `npm run build` di Node 22, keduanya trigger di tiap push/PR ke branch mana pun) — status hijau di GitHub sebelum merge sudah mengonfirmasi ini, langkah manual di atas jadi verifikasi tambahan terakhir, bukan satu-satunya jaring pengaman.

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
