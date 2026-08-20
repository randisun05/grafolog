# Tugas: PELAJARI dulu, JANGAN ubah kode apapun

Ini sesi pertama di folder `grafolog/` (folder baru, sebelumnya kedua project ini
ada di folder lain yang isinya campur banyak project berbeda). Riwayat sesi lama
mungkin tidak otomatis tersambung ke sini — jadi tugasmu SEKARANG murni membaca
dan memahami kondisi NYATA project, bukan melanjutkan development.

## Yang harus kamu lakukan

1. **Baca `guratan-api/CLAUDE.md`** (kalau ada) — ini klaim tentang apa yang
   sudah dibangun. JANGAN percaya begitu saja, verifikasi ke kode aslinya:
   - Jalankan `php artisan route:list` — bandingkan dengan yang diklaim di CLAUDE.md
   - Cek `database/migrations/` — tabel apa saja yang benar-benar ada
   - Jalankan test yang ada (`php artisan test`) — berapa yang lolos, berapa gagal
   - Cek `app/Services/Llm/` — pastikan `LLM_PROVIDER` di `.env` beneran `none`
     seperti yang diklaim (atau sudah berubah?)

2. **Baca `guratan-web/CLAUDE.md`** (kalau ada — kalau belum ada, berarti belum
   pernah dibuat, catat ini sebagai temuan). Verifikasi ke kode asli:
   - Cek `src/views/` dan `src/components/` — apa saja yang benar-benar ada,
     apakah sesuai daftar yang direncanakan sebelumnya
   - Cek bagaimana token auth disimpan sekarang (harusnya `localStorage`,
     ini bug yang sudah diperbaiki minggu lalu — pastikan benar sudah diperbaiki)
   - Cek apakah CSS/styling sudah diterapkan dengan benar (ini juga bug yang
     dilaporkan sudah diperbaiki — verifikasi, jangan asumsi)
   - Coba `npm run build` dan `npm run dev` — apakah masih jalan tanpa error

3. **Cek koneksi API↔Web**:
   - `guratan-web` bagian mana yang menyimpan base URL API?
   - `guratan-api/config/cors.php` — apakah origin `guratan-web` (mis. `:5173`)
     sudah terdaftar?

## Setelah selesai membaca & verifikasi

**JANGAN mengubah kode apapun dulu.** Laporkan dalam bentuk ringkas:
- Apa yang terkonfirmasi BENAR sesuai klaim di CLAUDE.md
- Apa yang TERNYATA BEDA dari yang diklaim (kalau ada)
- Apa yang masih rusak/belum selesai
- Update isi `guratan-api/CLAUDE.md` dan `guratan-web/CLAUDE.md` supaya
  mencerminkan kondisi NYATA (bukan klaim lama yang mungkin sudah usang),
  tapi tunjukkan dulu rangkumannya ke user sebelum menimpa filenya.

Setelah laporan ini disetujui user, sesi ini bisa dilanjutkan untuk development
berikutnya — beri tahu user bahwa sesi ini bisa di-resume nanti dengan
`claude --resume` (pilih sesi bernama "pelajari-project" dari daftar).
