# Contoh laporan (data dummy)

Dihasilkan dari `DemoDataSeeder` lewat alur API sungguhan (login klien → unduh PDF).
Setiap laporan tersedia dalam dua bahasa.

- `laporan-master-citra-id.pdf` / `-en.pdf` — tier Master, persona "sosial", dengan jadwal konsultasi.
- `laporan-comprehensive-budi-id.pdf` / `-en.pdf` — tier Comprehensive, persona "pemimpin".
- `web-laporan-citra-id.png` / `-en.png` — tampilan web dengan pilihan bahasa.
- `web-dashboard-klien.png`, `web-antrean-supervisor.png` — tampilan lain.

Narasi Bahasa Indonesia adalah **draf terjemahan AI** dari referensi grafologi
berbahasa Inggris (`guratan-api/database/seeders/data/terjemahan_id.json`) dan
wajib ditinjau grafolog sebelum dipakai untuk klien. Aspek 09 (Comprehension)
level sedang/rendah masih kosong di sumber aslinya.
