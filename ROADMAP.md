# Roadmap Guratan

Daftar kerja terurut menuju MVP yang siap dipakai publik, disusun 2026-07-26
berdasarkan verifikasi kode langsung (bukan asumsi). Lihat `CLAUDE.md` (root)
untuk konteks produk, `guratan-api/CLAUDE.md` & `guratan-web/CLAUDE.md` untuk
detail teknis tiap repo. Centang `[x]` saat selesai, urut dari atas.

## Fase 0 — Penyelesaian cepat (paling dekat ke selesai)

- [x] **Selesai 2026-07-27.** Sambungkan `MAIL_MAILER` ke provider SMTP nyata.
  Kredensial Gmail pertama di `.env` ternyata dummy (Google menolak auth,
  535 Bad Credentials) — user mengganti dengan kredensial nyata, lalu
  diverifikasi penuh end-to-end: buat report sungguhan → observer
  men-dispatch job → `queue:work` memproses → email terkirim → 0 entri di
  `failed_jobs`. `guratan-api`
- [x] **Selesai 2026-07-27.** Perbaiki bug: request tanpa header
  `Accept: application/json` ke route ber-`auth:sanctum` menghasilkan 500
  (`Route [login] not defined`) alih-alih 401. Penyebab: default
  `ApplicationBuilder::withMiddleware()` Laravel mendaftarkan
  `redirectGuestsTo(fn () => route('login'))` sebelum callback app sendiri
  jalan — aplikasi ini API-only, tidak punya route `login`. Fix: tambah
  `$middleware->redirectGuestsTo(fn () => null)` di `bootstrap/app.php`.
  Diverifikasi: 401 di kedua skenario header, `php artisan test` tetap 6/6.
  `guratan-api`

## Fase 1 — Pengokohan MVP (stabilitas & kepercayaan sebelum go-live)

- [x] **Selesai 2026-07-27.** Test untuk `AuthController`, `SampleController`,
  `ScoringController`, `ReportController` — 35 test baru (total 41, dari 6),
  semua lolos. Mencakup otorisasi/IDOR, validasi, rate limit, audit log, PDF.
  `guratan-api`
- [x] **Selesai 2026-07-27.** `components/layout/AppNavbar`,
  `components/upload/UploadDropzone`, `components/shared/` (`ProgressTracker`,
  `ToastNotification`, `LoadingSpinner`) — semua dibangun dan di-wire ke view
  nyata (bukan komponen nganggur). Build + lint lolos; **belum dicek visual
  di browser sungguhan** — lihat `guratan-web/CLAUDE.md`. `guratan-web`
- [x] **Selesai 2026-07-27.** Review keamanan. Ditemukan & langsung diperbaiki:
  (1) `public/storage` symlink dangling (sisa reorg folder, upload rapid-tier
  jadi tidak bisa diakses), (2) `ScoringController::submit` tidak menolak
  resubmit untuk sample yang sudah `completed` (bisa bikin laporan duplikat).
  Ditemukan & **belum diputuskan** (perlu keputusan produk, bukan cuma
  teknis) — lihat "Open security findings" di `guratan-api/CLAUDE.md`:
  (a) gambar rapid-tier disajikan dari disk publik tanpa cek kepemilikan
  (risiko rendah — nama file hash tidak bisa ditebak — tapi tidak konsisten
  dengan prinsip data sensitif), (b) token Sanctum tidak pernah kedaluwarsa.
  `guratan-api`

## Fase 2 — Kesiapan produksi

- [~] **Backend selesai 2026-07-27, frontend & kredensial belum.** Integrasi
  payment gateway DOKU: migration `payments`, `DokuService` (signature
  HMAC-SHA256 sesuai dokumentasi resmi DOKU), `PaymentController` (buat
  pembayaran + terima webhook notifikasi), 6 test baru (total 47), semua
  lolos. Yang masih perlu:
  - [ ] Isi `DOKU_CLIENT_ID`/`DOKU_SECRET_KEY` sandbox nyata di `.env` (masih
    kosong/placeholder) dan uji satu transaksi sungguhan.
  - [ ] Konfirmasi harga tier Master (`config/pricing.php` — 149000 masih
    tebakan, comprehensive 49000 sudah sesuai `CLAUDE.md` root).
  - [ ] **Keputusan produk terbuka**: belum ada UI checkout self-service sama
    sekali — alur yang ada sekarang cuma grafolog membuatkan sample untuk
    klien (`PortalGrafologView`), klien sendiri tidak pernah membuka
    aplikasi untuk pesan/bayar. Perlu diputuskan dulu alur pemesanannya
    seperti apa sebelum tombol "Bayar" bisa di-wire ke frontend.
  - [ ] Verifikasi payload webhook notifikasi DOKU sungguhan (field yang
    dipakai sekarang, `order.invoice_number` & `transaction.status`,
    berdasarkan dokumentasi resmi tapi belum dicocokkan ke notifikasi nyata
    dari DOKU Sandbox).
  `guratan-api`
- [ ] Privacy Policy & Terms of Service — **draft sudah ditulis** di
  `legal/privacy-policy.md` dan `legal/terms-of-service.md` (2026-07-27),
  masih berstatus DRAFT dengan banyak `[ISI INI]` placeholder (nama badan
  hukum, harga resmi, kebijakan retensi data, kebijakan refund). **Wajib
  ditinjau tim legal/bisnis** sebelum dipublikasikan — belum ada halaman di
  `guratan-web` yang menampilkannya, itu juga belum dibuat.
- [ ] Setup deployment production — **checklist sudah ditulis** di
  `DEPLOYMENT.md` (env vars yang wajib berubah, langkah `storage:link` di
  server baru, contoh config Supervisor untuk queue worker, checklist
  sebelum go-live). Belum ada server production sungguhan yang disiapkan —
  ini panduan, bukan konfirmasi deployment sudah terjadi.

## Fase 3 — Pasca-MVP (sengaja ditunda, jangan dikerjakan sebelum fase 0-2 selesai)

- [ ] Computer vision untuk Rapid tier — **DIBATALKAN 2026-08-01**, lihat
  "Pivot MGA" di bawah: tier Rapid dinonaktifkan, CV tidak lagi relevan.
- [ ] Kalimat penghubung antar-aspek dalam laporan (LLM per-laporan) — tunda
  sampai ada data pemakaian nyata, sesuai prinsip "LLM seminimal & se-defensif
  mungkin."
- [~] **Terjemahan Bahasa Indonesia untuk narasi KB** — pipeline sudah
  dibangun & bug auth Anthropic (`x-api-key` bukan Bearer) sudah diperbaiki
  2026-07-31, 83 baris cache basi (bahasa='id' tapi provider='none') sudah
  dibersihkan. **Terhenti**: menunggu `LLM_API_KEY` nyata dari user di
  `guratan-api/.env` (masih kosong per 2026-08-01). Setelah diisi: jalankan
  `php artisan narasi:pregenerate --bahasa=id`, verifikasi 1 laporan penuh
  tampil Bahasa Indonesia end-to-end.
- [ ] Dashboard B2B, batch processing, culture-fit matching, marketplace
  psikolog — **diserap ke Pivot MGA** di bawah (bukan lagi ditunda, sudah
  jadi fokus aktif per 2026-08-01).

---

## Pivot — Master Graphology Assistant (mulai 2026-08-01)

**Konteks:** user meminta refactor besar dari "Guratan" (B2C-condong, 2 role)
ke "Master Graphology Assistant" — platform B2B-berat, 5 role (Administrator,
Grafolog, Supervisor, HR/Company, Client), IA baru (Dashboard → Project →
Assessment → Report). Ini supersede prinsip lama "B2B pasca-MVP" di
`CLAUDE.md` root — keputusan eksplisit user, bukan drift scope diam-diam.

Rencana lengkap (sitemap, wireframe, menu per role, user flow, audit
komponen keep/remove/new, struktur folder, roadmap 7 fase berisiko-terurut)
ada di artefak yang dipublikasikan 2026-07-31/08-01 (tidak disimpan di repo
— lihat riwayat chat kalau perlu detail visual).

**3 keputusan produk dikunci 2026-08-01:**
1. **Tier Rapid (gratis/CV) dinonaktifkan.** Sample rapid lama tetap bisa
   dilihat (tidak dihapus), tapi tidak bisa buat sample rapid baru.
2. **Project : Assessment = 1 : banyak.** Satu Project (mis. "Rekrutmen
   Staff Finance Q3 2026") bisa berisi banyak Assessment (kandidat).
   Menentukan bentuk migration `projects` di Fase 02.
3. **Administrator/Supervisor**: akun pertama dari seeder (bootstrap), lalu
   Administrator yang sudah login bisa membuat akun Admin/Supervisor lain
   lewat panel — bukan pendaftaran publik terbuka.

**Roadmap 7 fase (dari artefak, urut risiko & ketergantungan — tiap fase
bisa dipakai sendiri, tidak menunggu fase berikutnya):**

- [x] **Selesai 2026-08-01. Fase 01 — Navigasi & penamaan (bagian nonaktifkan
  Rapid).** Backend: `StoreSampleRequest` hanya terima
  `comprehensive`/`master`, cabang kode rapid di `SampleController` dihapus
  (sudah tak terjangkau). Frontend: link "Unggah", route `/upload` &
  `/hasil-rapid/:id`, dan view/komponennya (`UploadView`, `HasilRapidView`,
  `UploadDropzone`) dihapus. Sample/laporan rapid lama tetap bisa dilihat
  lewat Riwayat → ReportView (jalur itu tidak pernah bergantung pada
  file yang dihapus). 47/47 test backend lolos, build+lint frontend lolos.
  Relabeling menu per-role penuh (Administrator/Supervisor/HR) ditunda ke
  Fase 05/06 karena role itu belum ada di database.
- [x] **Selesai 2026-08-01. Fase 02 — Entitas Project.** Migration `projects`
  (`name` nullable, `source` enum: grafolog/hr/client, `created_by`) +
  `handwriting_samples.project_id` (nullable FK, `cascadeOnDelete`).
  Backfill dijalankan di DB dev nyata: 8 sample lama → 8 project baru (6
  `grafolog`, 2 `client`), 0 sample dengan `project_id` null. `source` adalah
  sumbu klasifikasi terpisah dari `tier` (comprehensive/master) — `tier`
  tidak dihapus, tetap dipakai untuk pricing. `SampleController::store`
  sekarang selalu bikin 1 Project baru per sample baru (skema sudah siap 1
  Project : banyak Assessment untuk kasus HR nanti, tapi belum ada UI untuk
  menambah assessment kedua ke project yang sama — itu Fase 06). 49/49 test
  lolos (2 test baru: assert Project dibuat, assert `source` benar untuk
  grafolog vs client). `guratan-api`
- [x] **Selesai 2026-08-01. Fase 03 — Dashboard KPI per role.** Backend:
  `GET /api/dashboard` (role-aware — grafolog dapat active
  projects/pending review/completed bulan ini/avg turnaround; client dapat
  total/completed/in-progress assessment + avg turnaround), 4 test baru.
  Frontend: `DashboardView` baru (kartu KPI + activity feed 5 item), jadi
  halaman landing setelah login/register (menggantikan `riwayat` sebagai
  redirect default), `Riwayat` tetap ada sebagai daftar detail terpisah
  (bukan dihapus, cuma bukan lagi halaman utama). Chart & quick-actions dari
  wireframe awal **sengaja ditunda** — bukan bagian scope minimal yang
  disepakati. 53/53 test backend lolos, build+lint frontend lolos. Kontrak
  API diverifikasi lewat HTTP nyata (register → login → panggil endpoint
  dengan token asli); **render visual di browser tidak diverifikasi** — tidak
  ada tool browser di sesi kerja ini, cuma lint/build + endpoint check.
- [x] **Selesai 2026-08-03. Fase 04 — Assessment Workspace 3-panel.**
  Backend: `POST /api/samples/{sample}/scores/preview` — pakai
  `ScoringEngineService::generate()` apa adanya (servicenya sudah toleran
  skor sebagian, tidak perlu diubah), tidak menyimpan apa pun, 4 test baru.
  Frontend: step "Isi Skor" di `PortalGrafologView` jadi 3 kolom (info
  klien/sample | `SindromAccordion` — tidak diubah, cuma dipindah posisi |
  `AutoCalculationPanel` baru, live via watcher ter-debounce 500ms).
  56/56 test backend lolos, build+lint frontend lolos, **dan** diverifikasi
  lewat HTTP nyata end-to-end (register → buat sample → panggil preview
  dengan skor sebagian → hasil benar, 0 baris tersimpan). Render visual di
  browser tetap belum diverifikasi (tidak ada tool browser di sesi kerja).
  Pemecahan `PortalGrafologView` jadi 3 rute terpisah (`/grafolog/clients`,
  `/grafolog/projects/new`, workspace) **ditunda** — di luar scope minimal
  yang disepakati untuk fase ini, lihat catatan di `guratan-web/CLAUDE.md`.
- [x] **Selesai 2026-08-03. Fase 05 — Role Administrator & Supervisor.**
  Backend: enum `users.role` diperluas jadi 4 (`user`/`grafolog`/
  `administrator`/`supervisor`), middleware `role:<role>` baru (pengganti
  `abort_unless(isGrafolog())` yang tidak scalable ke banyak role — kode
  lama tidak disentuh, cuma tidak dipakai lagi untuk hal baru),
  `AdministratorSeeder` (bootstrap 1 admin dari `ADMIN_EMAIL`/
  `ADMIN_PASSWORD` env, skip kalau kosong), `POST /api/admin/users` (admin
  bikin admin/supervisor/grafolog lain — publik `/auth/register` tetap
  cuma terima user/grafolog, dikunci test eksplisit). 64/64 test lolos.
  Frontend: `/admin/users` (`AdminUsersView` — form buat akun + tabel user),
  link "Kelola Staf" di navbar untuk role administrator.
  **Diverifikasi visual di browser sungguhan untuk pertama kalinya** di
  proyek ini — pakai Playwright headless (belum ada skill project untuk
  ini, setup ad-hoc via npx): login sebagai admin bootstrap, buka
  `/admin/users`, buat akun grafolog, konfirmasi toast sukses + baris baru
  muncul di tabel, 0 error console. Screenshot ada di scratchpad sesi kerja.
  Panel admin penuh (Master Data, cross-project Reports) dan antrean review
  Supervisor **ditunda** — di luar scope minimal fase ini; supervisor role
  sudah bisa dibuat tapi belum ada halaman/alur kerja khusus untuknya.
- [x] **Selesai 2026-08-06. Fase 06 — HR: Company, Candidate, Assignment.**
  Backend: table `companies` + `users.company_id`, role `hr` ditambahkan ke
  enum (5 role sekarang lengkap sesuai rencana awal MGA). "Candidate"
  **bukan table baru** — reuse `User` (role `user` + `company_id`) supaya
  seluruh pipeline Project/Sample/Report/ReportView jalan tanpa perubahan.
  `CandidateImportController` parse CSV native (tanpa dependency baru),
  bikin 1 Project (`source: hr`) + N HandwritingSample per baris. Table
  `assignments` baru memisahkan "siapa pemilik relasi kandidat" (HR, lewat
  `created_by`) dari "siapa yang mengerjakan" (grafolog yang di-assign) —
  otorisasi di `ScoringController`/`SampleController`/`ReportController`
  diperluas **aditif** (helper `isScorableBy()`/`isViewableBy()` di
  `HandwritingSample`, cek `created_by` lama tidak disentuh). 92/92 test
  lolos. Frontend: `/hr/candidates` (upload CSV + assign grafolog inline),
  `/grafolog/ditugaskan` (daftar sample menunggu skor, termasuk yang
  ditugaskan HR), `PortalGrafologView` sekarang bisa resume sample lewat
  `?sampleId=`. **Diverifikasi end-to-end 2 kali**: HTTP murni (admin→
  company→HR→import CSV→assign→grafolog submit skor→report jadi) dan
  browser sungguhan via Playwright (screenshot tiap langkah, 0 error
  console).
  **Ditunda dengan sengaja**: Billing/Subscription (belum ada model harga
  untuk paket company — perlu keputusan bisnis dulu, bukan hal teknis),
  Master Data editor & cross-project Reports view untuk admin, dan seluruh
  fungsi Supervisor (role sudah bisa dibuat sejak Fase 05, masih belum
  punya halaman/alur kerja).
- [x] **Selesai 2026-08-06. Fase 07 — Poles akhir.** Murni frontend, tidak
  ada perubahan backend. **Dark mode**: hanya token warna dasar
  (`--color-ink/paper/seal/sage`) yang didefinisikan ulang untuk gelap —
  semua turunan lain otomatis ikut, tidak perlu aturan dark khusus per
  komponen. Ikut `prefers-color-scheme` bawaan OS, bisa di-override manual
  lewat tombol di navbar (`useTheme.js`, tersimpan di localStorage, pola
  reactive-ref modul yang sama seperti `useToast.js`). **Command palette**:
  Ctrl/Cmd+K membuka daftar halaman sesuai role user (cermin dari
  `AppNavbar`) + aksi ganti tema, bisa diketik-filter, navigasi panah,
  Enter/Esc, klik-luar-untuk-tutup. Chip hint "Ctrl/⌘+K" di navbar biar
  ketahuan ada shortcut-nya. Diverifikasi lewat browser sungguhan
  (Playwright): toggle tema → cek atribut + repaint visual → reload → tema
  tetap tersimpan; buka palette → filter "staf" → Enter → mendarat di
  `/admin/users` dengan palette tertutup; buka lagi → Esc → tertutup. 0
  error console.

**Roadmap MGA 7 fase (dari pivot 2026-08-01) selesai semua per
2026-08-06.** Item yang sengaja belum dibangun di sepanjang jalan (bukan
lupa) tetap terbuka untuk pekerjaan lanjutan kapan pun dibutuhkan:
Billing/Subscription untuk paket company (Fase 06 — perlu keputusan harga
dari bisnis dulu), panel Master Data & cross-project Reports untuk admin
(disebut di rencana awal Fase 05, belum pernah dikerjakan), seluruh fungsi
Supervisor (role ada sejak Fase 05, belum ada halaman/alur kerja), dan
pemecahan `PortalGrafologView` jadi rute terpisah (Fase 04, ditunda karena
di luar scope minimal saat itu).

---

## Inisiatif — Commerce & CMS (mulai 2026-08-06)

**Konteks:** setelah roadmap MGA 7 fase tuntas, user menanyakan manajemen
harga/diskon/pembayaran/checkout-mandiri/CMS homepage/promosi — semuanya
diverifikasi **belum ada** (harga hardcode, tidak ada UI checkout meski
backend DOKU sudah ada sejak lama, tidak ada diskon/CMS/promosi sama
sekali). Rencana lengkap (data model, diagram alur checkout, roadmap
Fase A-F) ada di artefak yang dipublikasikan 2026-08-06 — tidak disimpan
di repo, lihat riwayat chat kalau perlu detail visual/diagram.

**Semua keputusan bisnis terjawab 2026-08-06:**
1. **Harga tier Master** — TIDAK dipatok angka tetap. User: "harga
   ditentukan di management produk dan harga jadi bisa diubah-ubah dan
   bisa terhubung dengan diskon/promosi." Ini sudah persis arsitektur
   `pricing_plans` di Fase A (admin ubah kapan saja lewat panel) —
   placeholder 149000 tetap jadi nilai awal sampai admin menggantinya
   sendiri, tidak ada lagi yang perlu "dikonfirmasi" secara terpisah.
2. **Jadwal konsultasi Master**: manual — tim hubungi klien setelah bayar,
   bukan sistem booking kalender.
3. **Tipe diskon**: persentase + potongan tetap (keduanya, admin pilih
   saat bikin kode).
4. **Checkout mandiri**: menambah jalur ketiga, alur grafolog-langsung &
   HR-impor tetap ada apa adanya.
5. **CMS homepage**: field tetap yang bisa diedit, bukan page-builder
   bebas.
6. *(ditemukan & langsung diperbaiki saat review, bukan pertanyaan bisnis)*
   — lihat Fase A di bawah.

**Roadmap 6 fase (A-F), penomoran terpisah dari MGA 01-07 di atas:**

- [x] **Selesai 2026-08-06. Fase A — Harga dinamis + tutup celah
  payment-gate.** Backend: table `pricing_plans` (histori tersimpan, ganti
  harga = nonaktifkan baris lama + buat baris baru, bukan overwrite),
  `PaymentController` & `GET /api/pricing` (publik) baca dari sini,
  `config/pricing.php` lama **dihapus** (sudah tidak dipakai). Perubahan
  harga admin tercatat di `audit_logs`. **Keputusan #6 ditemukan saat
  review**: `StoreSampleRequest` sudah mengizinkan klien bikin sample
  sendiri (`POST /api/samples`, tier comprehensive/master) sejak Fase 02,
  tapi `ScoringController` **tidak pernah cek status bayar** — celah nyata,
  bukan berkaitan langsung dengan CMS. Ditutup dengan
  `HandwritingSample::requiresPayment()`/`isPaid()`, gate 402 di
  `ScoringController::preview`/`submit`, **hanya untuk sample
  `Project.source === 'client'`** — sample dari grafolog-langsung/HR tidak
  kena (asumsi: pembayaran mereka diatur terpisah, di luar sistem). 104
  test lolos (naik dari 92). Frontend: `/admin/pricing` — edit harga per
  tier + riwayat, link "Kelola Harga" + entri Command Palette. Diverifikasi
  browser sungguhan: ubah harga di UI → endpoint publik langsung
  mencerminkan harga baru, 0 error console.
- [x] **Selesai 2026-08-06. Fase B — Diskon/kupon.** Table
  `discount_codes` (persentase & tetap sesuai Keputusan #3, kuota opsional,
  masa berlaku opsional, pembatasan tier opsional). Validasi terpusat di
  `DiscountCode::isValidFor()`/`amountOff()` — dipakai endpoint preview
  sekarang, akan dipakai checkout sungguhan di Fase D nanti, jangan
  duplikasi logika ini di tempat lain. `POST /api/pricing/preview`
  (autentikasi) hitung harga akhir dari tier + kode opsional — kode
  invalid tidak error, cuma `code_valid:false` dengan harga dasar tetap
  tampil (biar UI checkout nanti bisa kasih feedback tanpa request gagal).
  Admin CRUD sengaja **tidak punya "edit"** selain toggle aktif/nonaktif —
  ubah nilai kode yang sudah pernah dipakai diam-diam mengubah makna
  histori pemakaian; polanya nonaktifkan+buat baru. Setiap
  buat/aktifkan/nonaktifkan tercatat di audit log. 125 test lolos (naik
  dari 104) — termasuk bug nyata yang ketemu dari kegagalan test:
  `is_active`/`used_count` tidak otomatis ter-refresh ke object PHP
  setelah `create()` di MySQL, diperbaiki dengan default eksplisit di
  model. Frontend: `/admin/discounts` — bikin kode + tabel dengan toggle
  aktif/nonaktif. Diverifikasi browser sungguhan + query DB langsung: buat
  kode 15%, nonaktifkan, aktifkan lagi, hitung preview harga — semua
  benar secara matematis (Rp52.069 → potongan Rp7.810 → akhir Rp44.259).
- [ ] **Fase C — Aktivasi DOKU sandbox.** Isi kredensial asli, kirim 1
  transaksi test, cocokkan payload webhook sungguhan melawan asumsi yang
  dipakai kode sekarang (`order.invoice_number`, `transaction.status`).
- [x] **Selesai 2026-08-06. Fase D — Halaman checkout mandiri.** Backend:
  `PaymentController::store` terima `discount_code` opsional (pakai
  `DiscountCode::isValidFor()`/`amountOff()` yang sama dari Fase B, tidak
  duplikasi logika), `payments` dapat kolom `base_amount` +
  `discount_code_id` supaya invoice diskon tetap bisa ditelusuri.
  `DiscountCode::incrementUsage()` akhirnya terpakai — dipanggil di
  webhook saat status benar-benar `paid` (bukan saat preview/buat payment),
  dijaga anti-dobel kalau DOKU kirim notifikasi SUCCESS dua kali. DOKU
  `callback_url` disambungkan ke `{FRONTEND_URL}/dashboard` supaya browser
  klien kembali ke app setelah bayar, bukan menggantung. **Bug nyata
  ditemukan & diperbaiki saat verifikasi**: error DOKU belum dikonfigurasi
  bocor jadi 500 mentah dengan stack trace — sekarang jadi 503 bersih
  dengan pesan yang aman ditampilkan ke klien. 129 test lolos (naik dari
  125). Frontend: `/pesan` — pilih tier (harga dari `GET /api/pricing`),
  terapkan kode diskon (preview live), "Bayar Sekarang" **reuse dua
  endpoint yang sudah ada** (`POST /api/samples` lalu
  `POST /api/samples/{id}/payment`) — tidak ada endpoint order baru sama
  sekali. Diverifikasi browser sungguhan end-to-end: pilih tier → kode
  diskon 10% terhitung benar (Rp149.000→Rp134.100) → klik bayar → error
  "DOKU belum dikonfigurasi" (kondisi nyata sekarang) tampil rapi sebagai
  pesan inline + toast, bukan crash. Redirect DOKU sungguhan baru bisa
  diuji setelah Fase C (kredensial sandbox asli) selesai.
- [x] **Selesai 2026-08-06. Fase E — CMS homepage.** Backend: table
  `content_blocks` (key-value datar, dibatasi ke daftar tetap
  `ContentBlock::EDITABLE_KEYS` — 3 field: eyebrow, tagline, label tombol
  CTA — sesuai Keputusan #5, tolak key di luar daftar dengan 404, bukan
  page-builder bebas). Nilai default di seeder **persis sama** dengan teks
  yang sebelumnya hardcode di `LandingView.vue`, jadi migrasi ke CMS tidak
  mengubah tampilan sampai admin benar-benar edit. `GET /api/content`
  publik (bentuk flat `{key: value}`, bukan list berpaginasi — sesuai
  kebutuhan frontend). 137 test lolos (naik dari 129). Frontend:
  `LandingView.vue` fetch dari API dengan **fallback ke teks default**
  kalau API gagal/lambat (homepage tidak boleh kosong cuma karena CMS
  bermasalah); `/admin/content` — form per-field dengan tombol simpan
  sendiri-sendiri. Diverifikasi browser sungguhan lintas 2 konteks: admin
  ubah tagline → sesi tamu baru (belum login) buka homepage dari nol →
  langsung lihat teks baru, 0 error console. Teks uji coba dikembalikan ke
  aslinya setelah verifikasi.
- [x] **Selesai 2026-08-06. Fase F — Banner promosi dashboard.** Table
  `announcements` (judul, isi, `is_active`, `starts_at`/`ends_at` opsional
  keduanya independen, `target_roles` JSON opsional — null berarti semua
  role). `Announcement::isVisibleTo(User)` jadi satu-satunya sumber
  kebenaran untuk visibilitas, dipakai `GET /api/announcements` (autentikasi,
  hanya kembalikan yang terlihat untuk user saat ini) — pola yang sama
  dengan `DiscountCode::isValidFor()` di Fase B. Sengaja diproaktifkan:
  bug `$attributes` default (Fase B, `is_active`/`used_count` tidak
  ter-refresh dari MySQL setelah `create()`) langsung ditiru fix-nya di
  model ini sebelum sempat jadi kegagalan test — 15 test baru semua lolos
  di percobaan pertama. Beda dari `PricingPlan`/`DiscountCode`: admin CRUD
  di sini **mengizinkan edit penuh in-place** (bukan
  nonaktifkan+buat-baru), karena pengumuman tidak punya makna "histori
  pemakaian" yang perlu dilindungi. Aksi admin (buat/ubah) tercatat di
  audit log. 152 test lolos (naik dari 137). Frontend: `/admin/announcements`
  — form buat pengumuman (judul, isi, checkbox target role, tanggal
  mulai/berhenti opsional) + tabel dengan toggle aktif/nonaktif;
  `DashboardView.vue` fetch `GET /api/announcements` setelah data dashboard
  utama (gagal diam-diam, dashboard tetap tampil), render sebagai banner
  yang bisa ditutup (dismiss per sesi browser — bukan permanen, akan
  muncul lagi di kunjungan berikutnya selama masih aktif) di atas kartu
  KPI. Diverifikasi browser sungguhan lintas 3 konteks: admin buat
  pengumuman target role klien → klien lihat banner di dashboard → tutup
  banner → hilang dari sesi itu; akun grafolog (tidak ditarget) tidak
  pernah melihatnya sama sekali. 0 error console.

---

**Status Inisiatif Commerce & CMS:** Fase A, B, D, E, F **semua selesai**
(2026-08-06). Yang tersisa hanya **Fase C — aktivasi DOKU sandbox**, dan itu
bukan lagi soal keputusan/kode — murni menunggu user memasukkan kredensial
sandbox DOKU asli (`DOKU_CLIENT_ID`/`DOKU_SECRET_KEY`) supaya redirect
pembayaran sungguhan (bukan cuma jalur error 503-nya) bisa akhirnya diuji
end-to-end.

**Cara pakai:** Fase 0-1 (bagian atas) sudah selesai untuk MVP lama. Fase 2
lama (payment/legal/deployment) jalan paralel, tidak diblokir pivot MGA.
Fase 3 lama sebagian diserap ke Pivot MGA di atas. Pivot MGA 01→07 **sudah
tuntas semua** (2026-08-01 → 2026-08-06) — baseline rollback:
`guratan-api@9dcca3d`, `guratan-web@c30d5e2`. Inisiatif Commerce & CMS
(A-F) **hampir tuntas** — hanya Fase C (kredensial DOKU sandbox) yang masih
menunggu user.

---

## Inisiatif — Token Grafolog (selesai 2026-08-07)

**Konteks:** user minta sistem token untuk grafolog — biaya token diatur
manual oleh admin, setiap laporan berhasil dibuat memotong token, setiap
pembelian token menambah token. Keputusan bisnis dikonfirmasi lewat
AskUserQuestion sebelum membangun: (1) grafolog beli token sendiri lewat
DOKU (transaksi sungguhan, bukan admin top-up manual), (2) konsumsi token
per laporan **ditentukan admin dan bisa berubah** (bukan angka tetap
hardcode), termasuk diskon untuk pembelian token, (3) saldo habis →
**diblokir (402)**, mirror pola payment-gate klien yang sudah ada.

Dibangun dalam satu batch (bukan fase bertahap seperti Commerce & CMS
di atas, karena semua bagian saling terkait erat — gate tanpa jalur beli
tidak berguna, dan sebaliknya):

- **`TokenCost`** (per tier comprehensive/master) dan **`TokenPrice`**
  (harga per 1 token, Rupiah) — pola histori sama persis dengan
  `PricingPlan::setPriceFor()`: ganti nilai = nonaktifkan baris lama +
  buat baris baru. **Kedua tabel sengaja kosong saat rilis** —
  `TokenCost::activeTokensFor()` yang `null` diperlakukan sebagai 0 (tidak
  ada gate) di `ScoringController`, supaya rilis fitur ini TIDAK langsung
  memblokir grafolog lama yang saldonya 0. Admin harus sengaja mengisi
  biaya token per tier dulu di `/admin/tokens` sebelum gate aktif.
- **`TokenWalletService`** — satu-satunya tempat yang boleh mengubah
  `User::token_balance`. `credit()`/`debit()` mengunci baris user
  (`lockForUpdate`) dan mencatat satu baris `token_ledger_entries`
  (immutable, `balance_after` tersimpan per baris) di transaksi yang sama,
  supaya cache saldo dan ledger audit tidak pernah berbeda meski ada
  request bersamaan. `debit()` melempar 402 kalau saldo kurang — gate yang
  diminta user — dipanggil dari dalam `DB::transaction` milik
  `ScoringController::submit`, jadi laporan yang gagal dipotong tokennya
  tidak pernah setengah jadi.
- **Pembelian token reuse integrasi DOKU yang sama**, bukan bikin jalur
  pembayaran kedua: `DokuService::createCheckout()` di-refactor supaya
  tidak lagi type-hint ke model `Payment` (sekarang terima
  invoice/amount/currency sebagai parameter biasa), dipakai bersama oleh
  `Payment` (checkout sample klien) dan `TokenPurchase` (beli token
  grafolog) baru. DOKU Back Office hanya punya SATU Notification URL, jadi
  `PaymentController::notification` sekarang membedakan lewat prefix
  invoice_number (`INV-` vs `TOKEN-`) dan mengarahkan ke tabel yang benar
  — token baru ditambahkan lewat `TokenWalletService::credit()` saat
  status `SUCCESS` sungguhan, dijaga anti-dobel sama seperti kuota diskon.
- **Kode diskon kini juga berlaku untuk pembelian token** — `applicable_
  tiers` menerima nilai `token` selain `comprehensive`/`master`, reuse
  `DiscountCode::isValidFor()`/`amountOff()` tanpa perubahan sama sekali.
- **Dashboard grafolog** dapat KPI baru "Sisa Token" — tidak perlu ubah
  template `DashboardView.vue` sama sekali karena kartu KPI sudah dirender
  generik dari array `kpi` milik API.
- Frontend baru: `/admin/tokens` (atur harga per token + biaya token per
  tier, riwayat masing-masing) dan `/token-saya` (saldo, beli token dengan
  preview diskon, riwayat transaksi) — pola struktural sama persis dengan
  `AdminPricingView`/`OrderView`.

190 test lolos (naik dari 152, 38 baru). Diverifikasi penuh melawan
database dev sungguhan (bukan cuma test suite): admin atur harga & biaya
lewat browser, dashboard grafolog menampilkan KPI baru, halaman beli token
gagal rapi saat DOKU belum dikonfigurasi (503, sama seperti `OrderView`),
dan seluruh siklus gate — diblokir di saldo 0 → di-*credit* → submit
laporan berhasil & memotong tepat sejumlah yang dikonfigurasi, baris
ledger tertaut ke laporan yang dihasilkan — dikonfirmasi lewat pemanggilan
API langsung terhadap server dev sungguhan. Data uji coba dibersihkan dan
pengaturan token dikembalikan ke kondisi "belum dikonfigurasi" setelah
verifikasi, supaya gate tidak diam-diam aktif untuk akun grafolog lain di
database dev.

**Yang sengaja belum dibangun:** jalur pembelian token belum pernah diuji
sampai redirect DOKU sungguhan (sama blocker-nya dengan Commerce Fase C —
kredensial sandbox DOKU asli); admin belum mengisi harga per token maupun
biaya token per tier yang sesungguhnya (masih kosong/"belum dikonfigurasi"
sampai admin mengisi lewat `/admin/tokens`).

---

## Inisiatif — Redesain Beranda & Palet "Kertas Berani" (selesai 2026-08-07)

**Konteks:** user minta FE dirombak — beranda dulu terlalu sederhana/monoton
(cuma 1 blok hero: eyebrow/H1/tagline/2 tombol), diminta beberapa section
(cara kerja, harga, kedalaman analisis, kejujuran, cara daftar) dibuat
se-interaktif mungkin, "seperti company profile". Palet krem lama dinilai
terasa tua/kaku untuk menyasar Gen Z. Proses: mockup HTML interaktif
dulu (dibahas via chat, bukan langsung kode) — termasuk perbandingan 2
arah warna (dark "Buku Catatan Malam" vs terang "Kertas Berani") — user
pilih **Kertas Berani**, baru diimplementasikan ke Vue sungguhan.

- **Palet baru** (`guratan-web/src/assets/base.css`): ink/paper/seal/sage
  dicerahkan & dinaikkan saturasinya (bukan keluarga warna baru), plus
  `--color-gold` baru untuk aksen tier Master. Dark mode & kedua
  `[data-theme]` override ikut diperbarui.
- **`LandingView.vue` dirombak total**: hero dengan animasi tanda tangan
  (CSS murni, hormati `prefers-reduced-motion`), perbandingan cara lama vs
  Guratan, 4 langkah cara kerja, explorer Sindrom/Aspek interaktif
  (accordion + counter animasi), kartu harga dari `GET /api/pricing`
  sungguhan (bukan angka hardcode), section "Kejujuran Kami", alur daftar,
  CTA penutup. Label Sindrom/Aspek di explorer dihaluskan ke Bahasa
  Indonesia (mis. "Authoritarian" → "Ketegasan") — data statis di
  komponen, sengaja tidak disambungkan ke tabel `sindrom`/`aspek` (istilah
  teknis asli tetap dipakai di form skor grafolog).
- **CMS homepage diperluas dari 3 ke 21 field** (`ContentBlock::
  EDITABLE_KEYS`) supaya semua section baru bisa diedit admin — termasuk
  `ContentBlock::LIST_KEYS` baru untuk 4 field berisi daftar (bullet
  perbandingan, langkah kerja, poin kejujuran), disimpan sebagai JSON
  string di kolom `value` yang tetap `text` biasa. `AdminContentView.vue`
  merender field list ini sebagai editor terstruktur kecil (jumlah item
  tetap), bukan textarea JSON mentah.
- Dashboard klien & admin **tidak perlu CMS baru** — promo/konten di
  sana sudah dikelola sistem yang ada (Announcements, Pricing, Discount
  Codes); hanya beranda yang sebelumnya statis total.

192 test backend lolos (2 baru). Diverifikasi lewat browser sungguhan:
hero tampil dari CMS, kartu harga menampilkan Rp100.000/Rp149.000 asli,
explorer Sindrom accordion berfungsi dengan label Indonesia, dan edit
admin pada field list (judul salah satu langkah) langsung muncul di
beranda publik setelah reload — 0 error console.

---

## Inisiatif — Knowledge Management System (mulai 2026-08-08)

**Konteks:** diskusi panjang membongkar bahwa alur penilaian Master
sesungguhnya bukan input skor 1-10 subjektif seperti yang terbangun di MVP,
melainkan checklist bertingkat: measurement worksheet fisik (37 variabel
ukur) → diklasifikasi ke band baku → dicocokkan ke Indikator (704, masing-
masing Aspek punya 10 posisi bernomor + varian huruf opsional a/b/c) lewat
aturan operator → Aspek = jumlah posisi tercentang (0-10) → Sindrom = rata-
rata Aspek-nya (sudah benar di `ScoringEngineService`, tidak berubah). User
eksplisit minta seluruh knowledge ini (Indikator, measurement, aturan,
referensi silang) **jangan di-hardcode** — harus data yang bisa dikelola
admin, karena metodologi akan terus berkembang. Rencana lengkap (KM-A s/d
KM-H) ada di artifact sesi perencanaan, bukan di repo ini.

**KM-A — infrastruktur dasar (selesai 2026-08-08):**

- **`metodologi_penilaian`** table baru, 1 baris seed `"master"` — label
  tipe metodologi penilaian, supaya metodologi lain di masa depan (bukan
  cuma revisi Master) bisa dibedakan tanpa bongkar data yang ada. Ditempel
  ke `measurement_variable.metodologi_id`; **sengaja tidak** ditempel ke
  Sindrom/Aspek/Indikator (konten psikologis, sama lintas metodologi apa
  pun) atau ke `measurement_category` (transitif lewat `variable_id`,
  duplikasi kolom cuma berisiko drift).
- **`Indikator.posisi`/`.varian`** kolom baru (nullable di DB, pola sama
  seperti `project_id`), backfill penuh 704 baris dari parsing `kode` lewat
  helper `App\Support\IndikatorKode` — dipakai bareng migrasi backfill
  sekali-jalan ini dan `GrafologiKnowledgeSeeder` untuk re-seed berikutnya,
  supaya tidak ada 2 salinan regex yang bisa berbeda.
- **`GrafologiKnowledgeSeeder` dibenahi jadi idempoten** — sebelumnya insert
  buta, gagal/dobel kalau dijalankan ulang di atas data yang ada. Sekarang
  `updateOrInsert` per kolom kode unik (termasuk `sindrom.kode_romawi`, baru
  dibuat unik lewat migrasi terpisah). Diverifikasi 2× jalan berturutan di
  database dev sungguhan — jumlah baris & ID primary key identik di kedua
  run, aman untuk relasi FK yang sudah ada.

212 test backend lolos (15 baru). KM-A murni fondasi skema + data, belum ada
UI atau perubahan ke `ScoringController`.

**KM-B — CRUD panel admin untuk entitas sederhana (selesai 2026-08-08):**

- Backend: `Api\Admin\{Sindrom,MeasurementVariable,MeasurementCategory,
  ScoringRuleBand}Controller`, full CRUD, digerbang `role:administrator`.
  `Sindrom::destroy()` sengaja diberi guard — `aspek.sindrom_id` adalah
  `cascadeOnDelete`, tanpa guard, hapus 1 Sindrom diam-diam menghapus semua
  Aspek (dan Indikator-nya, cascade lagi) di bawahnya. Variabel ukur baru
  otomatis berlabel metodologi "master" kalau tidak disebutkan eksplisit.
  30 test baru (242 total).
- Frontend: `/admin/knowledge` (`AdminKnowledgeView.vue`) — 1 halaman, 3 tab
  (Sindrom, Variabel Ukur + kategori nested per baris, Band Skor), edit
  inline per baris (tidak ada komponen modal di codebase ini, konsisten
  dengan pola yang sudah ada). Diverifikasi lewat browser sungguhan
  (Playwright): siklus penuh create→edit→hapus di ketiga tab, 0 error
  console, jumlah baris kembali ke baseline setelah data uji dibersihkan.

**KM-C — CRUD Aspek + 4 level narasi (selesai 2026-08-08):**

- Backend: `Api\Admin\AspekController`, full CRUD. `destroy()` diberi guard
  yang sama polanya dengan `Sindrom::destroy()` — `indikator.aspek_id` juga
  `cascadeOnDelete`, jadi diblokir (422) kalau Aspek masih punya Indikator
  terkait. 9 test baru (251 total).
- Frontend: tab ke-4 "Aspek" di `/admin/knowledge` — form buat minimal
  (kode/Sindrom/nama), tombol "Ubah" membuka panel edit penuh (keterangan
  umum + 4 textarea narasi very_high/high/medium/low), pola sama seperti
  panel kategori nested di tab Variabel Ukur. Diverifikasi browser
  sungguhan: buat → isi 5 field teks → simpan → reload halaman → buka lagi
  panel edit → teks yang tersimpan masih ada (bukti tersimpan ke backend,
  bukan cuma state lokal) → hapus. 0 error console.

**KM-D — CRUD Indikator, dengan paginasi + cari/filter (selesai 2026-08-08):**

- Backend: `Api\Admin\IndikatorController`. Beda dari 3 controller KM
  sebelumnya — `index()` **wajib** dipaginasi (`paginate(25)`) plus
  `?search=` (kode/nama) dan `?aspek_id=`, karena 704 baris tidak realistis
  dikirim sekaligus. `posisi`/`varian` (kolom dari KM-A) sekarang field
  yang bisa diedit sungguhan. Tidak butuh guard hapus seperti Sindrom/Aspek
  — `indikator_cross_reference.indikator_sumber_id` itu `nullOnDelete`,
  bukan `cascadeOnDelete`. 9 test baru (260 total).
- Frontend: tab ke-5 "Indikator" — satu-satunya tab dengan kotak pencarian
  (debounce 400ms) + dropdown filter Aspek + navigasi halaman
  sebelumnya/berikutnya. Diverifikasi browser sungguhan lawan data KB asli
  (pencarian "extremely small" benar menyaring 704 → 1), siklus penuh
  buat/ubah/hapus dengan persistensi dikonfirmasi setelah reload halaman.
  0 error console.

**KM-E — rule/operator builder (selesai 2026-08-08):** bagian yang jadi
tujuan utama seluruh rencana KM — menghubungkan Indikator ke variabel ukur.

- Backend: tabel `indikator_rules`, 2 tipe aturan sesuai §3.2 rencana KM —
  `category` (variabel = label kategori, mis. "Middle zone height" @
  "large") dan `comparison` (variabel_A [operator] koefisien × variabel_B
  ATAU angka tetap — tepat salah satu, tidak dua-duanya/tidak kosong
  dua-duanya). Validasi lintas-field (field mana wajib/terlarang per tipe)
  ditaruh di `withValidator()` FormRequest, bukan rantai `required_if`/
  `prohibited_if` yang sulit dibaca untuk 2 bentuk yang saling eksklusif.
  `category_label` divalidasi cocok ke kategori sungguhan milik variabel
  yang dipilih — supaya salah ketik tidak diam-diam jadi aturan yang tidak
  akan pernah cocok saat KM-G nanti dijalankan. Kolom baru
  `indikator.rule_group_logic` (AND/OR, bukan diulang di tiap baris
  aturan — 1 Indikator cuma 1 nilai gabungan yang masuk akal).
  `MeasurementVariableController::destroy()` dapat guard baru — diblokir
  kalau variabelnya masih dipakai 1+ aturan. 16 test baru (276 total).
- Frontend: panel edit Indikator (tab ke-5) dapat sub-bagian baru "Aturan
  Operator" — pilih AND/OR, daftar aturan yang sudah ada (ditampilkan
  ringkas, mis. "d stem width > 1.5000× 5.2000"), form tambah aturan yang
  bentuknya berubah sesuai tipe (category/comparison) via v-if/v-else.
  Dropdown kategori terisi otomatis dari kategori sungguhan milik variabel
  yang dipilih. Diverifikasi browser sungguhan: tambah 1 aturan tiap tipe,
  ubah & simpan AND/OR, reload halaman lalu buka lagi Indikator yang sama
  untuk memastikan kedua aturan DAN pilihan gabungannya tetap tersimpan,
  lalu hapus keduanya.

**Aturan sudah bisa dibuat administrator, tapi belum ada yang MENGEVALUASI
aturan itu** — `ScoringController` sama sekali belum berubah, itu pekerjaan
KM-G.

**KM-F — aktivasi `indikator_cross_reference` sebagai data terkelola
(selesai 2026-08-08):** mengaktifkan tabel yang dorman sejak konversi awal
JSON→DB (257 matched / 280 total baris).

- Backend: kolom baru `aktif` (boolean, default true) — status yang
  dikelola admin. **Ini BUKAN UI cascade-trigger itu sendiri** (centang
  Indikator A men-suggest B) — itu baru masuk akal begitu form Indikator
  sungguhan (KM-G) ada; fase ini murni lapisan kelola datanya. Method
  seeder terakhir yang masih delete-lalu-insert (`seedCrossReference`)
  akhirnya dibenahi jadi idempoten juga — `updateOrInsert` keyed pasangan
  (indikator_sumber_raw, mereferensikan_ke_kode), dipastikan unik dulu di
  280 baris data sebelum dipakai sebagai kunci. `aktif` sengaja tidak ikut
  ditulis di update payload, supaya reseed tidak menimpanya balik ke true
  — diverifikasi lewat siklus nonaktifkan → reseed → tetap nonaktif, baik
  di test suite maupun database dev sungguhan. `match_status` dihitung
  server (matched hanya kalau sumber DAN target sama-sama menunjuk
  Indikator yang benar-benar ada), tidak pernah diterima sebagai input
  admin. Endpoint baru `IndikatorController::options()` untuk dropdown
  ringan (704 Indikator, tanpa paginasi). 10 test baru (286 total).
- Frontend: tab ke-6 "Referensi Silang" — pola sama seperti tab Indikator
  (paginasi + cari), tombol Aktif/Nonaktif jadi aksi utama terpisah dari
  edit penuh. Diverifikasi browser sungguhan: cari data asli, toggle aktif,
  ubah kode target, reload + cari ulang untuk memastikan kedua perubahan
  tersimpan, lalu hapus baris uji coba.

**KM-G — measurement worksheet sungguhan + sambungkan ke scoring (selesai
2026-08-08):** fase yang paling sensitif di seluruh rencana KM, karena ini
satu-satunya yang menyentuh alur skor yang sudah dipakai produksi
end-to-end. **`ScoringController`, `SubmitScoresRequest`, dan
`ScoringEngineService` sengaja SAMA SEKALI TIDAK DIUBAH** — form manual
1-10 lama tetap ada dan tetap default, persis seperti yang diminta dalam
diskusi rencana ("jalur lama tetap bisa jadi fallback").

- Backend: 2 tabel baru — `measurement_readings` (hasil ukur mentah per
  variabel per sample) dan `sample_indikator_checks` (status tercentang
  per Indikator per sample, `checked` adalah kolom boolean bukan
  keberadaan baris — supaya uncheck manual grafolog tahan terhadap
  evaluasi ulang). `App\Services\Scoring\ChecklistEngineService` menjalankan
  `indikator_rules` (KM-E) atas hasil ukur, menerapkan cascade referensi
  silang (KM-F) satu-hop, lalu menghitung skor per Aspek dari jumlah
  posisi tercentang. Endpoint baru: measurement worksheet (baca/tulis) dan
  checklist (baca + toggle manual dengan konfirmasi eksplisit untuk
  cascade-uncheck). **Jembatan ke scoring yang sudah ada murni di
  frontend** — hasil tally dibaca lalu dikirim ke
  `POST /api/samples/{id}/scores` yang tidak berubah sama sekali, payload-nya
  identik dengan form manual. Dibuktikan lewat test integrasi end-to-end
  yang menjalankan urutan HTTP sungguhan (ukur → checklist → toggle manual
  → submit) dan memastikan skor yang tersimpan cocok dengan tally. 315 test
  backend lolos (dari 286).
- Frontend: `PortalGrafologView` step 2 dapat toggle "cara isi skor"
  (Manual/Measurement Worksheet) — mode manual persis seperti sebelumnya,
  mode worksheet menampilkan `MeasurementWorksheet` + `IndikatorChecklist`
  baru (checkbox per Indikator, badge Auto/Terkait + alasan pemicu
  ditampilkan inline — sesuai permintaan rencana supaya grafolog tahu
  KENAPA sesuatu tercentang otomatis). Kedua mode menulis ke `scores` yang
  sama, jadi kalkulasi otomatis & submit tidak perlu kode baru sama sekali.
- Diverifikasi browser dengan data KB sungguhan: 1 aturan kategori nyata
  pada Indikator "02-8a", nilai ukur yang jatuh di band "large" sungguhan
  memicu auto-centang dengan badge + alasan tampil benar, 1 referensi
  silang nyata memicu cascade ke Indikator di Aspek lain, tally diterapkan
  ke form, submit lewat endpoint yang tidak berubah berhasil sampai
  laporan selesai. Semua data uji coba dibersihkan setelahnya.

**KM-H — Peta Konsep knowledge untuk administrator (selesai 2026-08-08):**
fase terakhir, murni baca (tidak ada create/edit/delete di sini sama
sekali — itu tetap tugas 6 tab lain).

- Backend: 3 endpoint bertingkat (`Api\Admin\ConceptMapController`) —
  overview (8 Sindrom + Aspek bersarang + jumlah Indikator), detail 1
  Aspek (daftar Indikator + jumlah aturan/referensi), detail 1 Indikator
  (aturan operator + referensi silang KELUAR **dan** MASUK — arah masuk
  ini belum ada tampilannya di mana pun sebelumnya). Sengaja bertingkat,
  bukan 1 endpoint yang mengirim 704 Indikator + seluruh relasinya
  sekaligus — selain lambat, itu tidak bisa dijelajah manusia sebagai
  peta. 5 test baru, 320 total.
- Frontend: tab ke-7 "Peta Konsep" — 3 kolom Sindrom→Aspek→Indikator
  dengan garis penghubung SVG mengikuti node yang dipilih di tiap kolom
  (dihitung dari posisi DOM node, bukan library graph — cukup sederhana
  untuk tidak perlu dependensi baru), plus panel relasi yang bisa diklik
  untuk lompat langsung ke Indikator terkait (referensi keluar/masuk),
  menjadikan peta ini benar-benar bisa dijelajah, bukan sekadar 3 kotak
  daftar terpisah.
- Diverifikasi browser dengan data KB sungguhan: pilih Sindrom → Aspek →
  Indikator ber-aturan, konfirmasi teks aturan dan 3 referensi silang
  keluar sungguhan tampil benar, klik salah satu chip untuk lompat peta,
  konfirmasi garis penghubung SVG mengikuti path yang dipilih. Data uji
  coba dibersihkan setelahnya.

**Seluruh rencana KM-A sampai KM-H sudah selesai.** Setiap entitas
knowledge (Sindrom, Aspek, Indikator, Variabel Ukur/Kategori/Band Skor)
punya CRUD admin penuh, sistem aturan operator bisa dibuat DAN dievaluasi
sungguhan lewat measurement worksheet, referensi silang dikelola DAN aktif
sebagai cascade DAN bisa dijelajah dua arah lewat peta konsep. Tidak ada
sisa dari rencana awal.

**Review pasca-KM-G/H (2026-08-08):** diminta evaluasi atas kode yang baru
dibangun sebelum dianggap siap pakai. Ditemukan dan diperbaiki 7 bug
sungguhan (masing-masing diverifikasi manual, bukan diterima mentah-mentah
dari hasil review) — 2 yang paling berdampak: (1) `ChecklistController`
lupa diberi payment gate yang sudah ada di endpoint scoring lain (sample
klien belum bayar bisa diakses checklist-nya), (2) tombol "Terapkan Skor
Checklist ke Form" bisa membuat form terbaca lengkap dan bisa disubmit
padahal grafolog baru meninjau sebagian kecil dari 40 Aspek (skor floor=1
otomatis untuk semua Aspek yang belum disentuh). Detail lengkap ke-7 bug
+ perbaikannya ada di `guratan-api/CLAUDE.md` dan `guratan-web/CLAUDE.md`.
327 test backend lolos (dari 320).

**Aturan Irregularity (2026-08-08):** konten pertama sungguhan untuk
`indikator_rules` (sebelumnya selalu kosong, cuma diuji dengan data
buatan). User memberi 20 ambang ukur (semua relatif ke "Middle zone
height" via rasio, atau ambang sudut absolut) untuk konsep "irregular",
lalu dicocokkan lewat diskusi ke 45 Indikator yang namanya mengandung
kata "irregular"/"regular" — 28 aturan jadi lewat
`database/seeders/IrregularityRuleSeeder.php` (idempoten, masuk rantai
`DatabaseSeeder`). Keputusan penting: "regular" = kebalikan matematis
dari "irregular" pada ambang yang sama (bukan aturan terpisah), dan
5 Indikator "Middle zone height irregular/regular" sengaja TIDAK diberi
aturan karena sumber datanya membandingkan variabel dengan dirinya
sendiri (kemungkinan typo sumber) — user memilih skip, bukan menebak.
Live-verified lewat `ChecklistEngineService` sungguhan, bukan cuma
tersimpan di DB. 333 test backend lolos (dari 327).

**2 batch lanjutan analisis nama Indikator (2026-08-08, sama hari):**
`CategoryMatchRuleSeeder` (66 aturan `category`, pola paling sederhana —
nama Indikator persis sama dengan "Variabel + kategori", tanpa tebakan
ambang sama sekali) dan `VariableEqualityRuleSeeder` (7 aturan
`comparison equals`, dari 28 kandidat berbahasa perbandingan yang
diperiksa, cuma 7 yang benar-benar membandingkan 2 Variabel Ukur nyata).
**Temuan penting**: 1 Indikator (38-9) ternyata membandingkan SKOR ASPEK
("Score for Mental Orientation is 2.0+ higher than score for Physical
Energy"), bukan measurement mentah — di luar kemampuan skema
`indikator_rules` saat ini, butuh desain baru kalau mau dikerjakan.
Kata "and"/"or" di beberapa nama Indikator dicek dan dipastikan cuma tata
bahasa, bukan operator logika. `indikator_rules` sekarang 101 baris (28 +
66 + 7). 341 test backend lolos (dari 333).

## Inisiatif — Koreksi Laporan & Riwayat Versi (2026-08-08)

User meminta pengembangan report lanjutan, dipecah jadi 4 sub-ide saat
didiskusikan: (1) koreksi manual skor + regenerasi, (2) edit narasi manual
langsung, (3) kategorisasi Indikator/Sindrom per topik (karier/cinta/dst)
untuk basis chat interaktif klien nanti. User eksplisit minta #2 duluan
("#2 dl") - #1 kategorisasi dan #3/4 chat interaktif BELUM dikerjakan.
Chat interaktif sengaja ditahan - bertentangan langsung dengan prinsip
proyek yang sudah dikunci ("LLM dipakai seminimal & se-defensif mungkin,
cache bukan panggilan live per-user") dan perlu diskusi produk terpisah
soal biaya/privasi sebelum dikerjakan.

**Selesai 2026-08-08**: `report_revisions` (jejak audit terpadu untuk
koreksi skor DAN edit narasi manual), `ScoringController::correct()`
(kebalikan `submit()` - mensyaratkan sample sudah completed, bukan
menolaknya; tanpa re-charge token; grafolog pemilik boleh koreksi tanpa
approval tambahan sesuai keputusan user), `ReportController::updateNarasi()`
(edit narasi manual langsung di JSON `data`, flag `narasi_diedit_manual`
otomatis hilang saat laporan dikoreksi ulang - regenerasi dari KB
dianggap lebih dipercaya). Frontend: tombol "Koreksi Skor" (reuse form
scoring asli), badge "diedit manual" + tombol edit inline per Aspek,
"Riwayat Perubahan" collapsible yang bisa menampilkan versi lama. Bug
ditemukan & diperbaiki saat verifikasi browser: field relasi Laravel
diserialisasi snake_case (`aspek_scores`), bukan camelCase seperti nama
method PHP-nya. 355 test backend lolos (dari 341).

**Belum dikerjakan**: kategorisasi topik Indikator/Sindrom, chat
interaktif klien.

## Inisiatif — Rentang Ukur (Range-Mode) & Narasi Per-Indikator (2026-08-19)

User memberi instruksi (typo berat, dianalisis lewat diskusi): (1) Indikator
yang tidak terakomodir measurement biasa butuh form input nilai
terbesar/terkecil, selisihnya jadi patokan rule irregularity - ternyata ini
mengonfirmasi bahwa 20 ambang "Range is more than..." yang diberikan user
2026-08-08 memang literal berarti selisih (max-min), bukan nilai titik
tunggal seperti yang diasumsikan saat pertama kali diimplementasikan; (2)
cross-reference sebagai "exclusion logic" (if A checked maka B otomatis
TIDAK checked) - **dibahas, TIDAK dieksekusi**, user pilih "bahas dulu"; (3)
koreksi laporan harus bisa buka ulang measurement worksheet, bukan cuma form
skor 1-10; (4) narasi per-Indikator (bukan cuma per-Aspek) harus ikut masuk
laporan.

**Selesai 2026-08-19**:
- `measurement_readings` dapat kolom `nilai_min`/`nilai_max` (nullable,
  independen dari `nilai`); `indikator_rules` dapat `variable_a_value_mode`
  (`nilai`/`range`) - `ChecklistEngineService` menghitung selisih
  (nilai_max - nilai_min) saat mode `range`, dipakai sisi variable_a saja
  (variable_b/compare_value tetap nilai titik, sesuai bunyi asli ambang).
- **Retrofit `IrregularityRuleSeeder`**: semua 28 rule lama diubah ke
  `variable_a_value_mode=range` (bukan konten baru, cuma cara evaluasinya
  benar). Ini juga menyelesaikan 5 Indikator "Middle zone height
  irregular/regular" yang dulu SENGAJA di-skip 2026-08-08 (dikira typo
  self-reference) - sekarang jelas valid: range(MZH) vs 1×nilai-titik(MZH),
  2 mode berbeda untuk variabel yang sama. Total `indikator_rules` 106
  (dari 101).
- `MeasurementController`/`ChecklistController` tidak lagi memblokir sample
  `status === 'completed'` - dibutuhkan supaya `ReportCorrectionPanel`
  (fitur inisiatif sebelumnya) bisa dapat mode toggle baru "Measurement
  Worksheet" yang membuka ulang hasil ukur/checklist tersimpan untuk
  diedit; mengubahnya TIDAK mengubah laporan aktif sampai grafolog sengaja
  submit lewat `scores/correct` yang sudah ada.
- `ScoringController::attachIndikatorNarasi()` (dipanggil dari `submit()`
  dan `correct()`) menambahkan `aspek.indikator_terkait` (kode/nama/
  keterangan tiap Indikator tercentang) ke `data` laporan, dibaca dari
  `Indikator.keterangan` yang sebelumnya ada di DB tapi tidak pernah
  dipakai. No-op untuk laporan mode manual (tidak punya `sample_indikator_
  checks`). Frontend: `ReportDocument.vue` merender daftar ini di bawah
  narasi tiap Aspek.
- **Data KB**: `35-1b`/`35-7b` (2 dari 32 `keterangan` kosong) diisi user
  langsung (sama dengan teks varian `a`-nya) - diperbaiki di
  `grafologi_knowledge_base.json` (sumber), bukan di seeder. 30 sisanya
  (semua di Aspek 35 "Fears", varian derajat `-dupN`) **ditunda, perlu
  teks dari user** - lihat memory `project_kb_content_gaps` kalau perlu
  detail daftar lengkap.
- 362 test backend lolos (dari 355). Diverifikasi lewat browser sungguhan
  (Playwright) + ground-truth DB, bukan cuma test suite: sample nyata diberi
  MZH=2 (nilai) + Ovals height min=1/maks=4 lewat worksheet -> Indikator
  27-5b auto-checked dengan alasan "Range Ovals height: 3 > Middle zone
  height (2)" -> laporan jadi, `indikator_terkait` Aspek 27 berisi
  keterangan Indikator itu -> buka "Koreksi Skor" mode Measurement
  Worksheet, konfirmasi field MZH/Ovals min/maks ter-prefill dari hasil
  ukur tersimpan. 0 error console. Data uji coba dibersihkan.

**Belum dikerjakan / ditunda**: 30 teks `keterangan` Aspek 35 (perlu input
user). Exclusion logic untuk cross-reference dibahas 2026-08-19 (lihat
inisiatif "Unifikasi Cross-Reference" di bawah) - **diputuskan tidak
dibangun**, bukan ditunda.

## Inisiatif — Unifikasi Cross-Reference ke Sistem Aturan (2026-08-19)

User bertanya: kenapa cascade "centang A -> ikut centang B" adalah
mekanisme terpisah (tabel `indikator_cross_reference` + KM-F) dari sistem
aturan measurement/irregularity (`indikator_rules`), padahal secara konsep
sama-sama "kriteria yang membuat Indikator tercentang otomatis"? Diskusi
menghasilkan keputusan: satukan jadi 1 sistem - cross-reference jadi
`rule_type` ketiga (`indikator_checked`) di `indikator_rules`. 2 keputusan
dikonfirmasi lewat AskUserQuestion sebelum eksekusi (perubahan besar ke
engine skoring inti): (1) rantai ketergantungan boleh berlapis (A->B->C,
bukan cuma satu-hop seperti cascade lama), (2) migrasi penuh - tabel lama
dihapus, bukan berdampingan.

**Selesai 2026-08-19**: `ChecklistEngineService::evaluateSample()` ditulis
ulang jadi evaluasi titik-tetap (fixed-point, maksimal N iterasi = jumlah
Indikator, terjamin berhenti karena monoton - dibuktikan lewat test
mutual-dependency yang stabil, bukan infinite loop) menggantikan pola lama
"1 pass rule measurement + cascade satu-hop terpisah". 257 relasi
cross-reference aktif+matched dimigrasi ke `indikator_rules`, tabel
`indikator_cross_reference` DIHAPUS beserta kolom
`sample_indikator_checks.cross_reference_id` yang jadi redundan. Admin tab
"Referensi Silang" (KM-F, 7 tab -> 6 tab) dihapus - kelola relasi sekarang
lewat sub-bagian "Aturan Operator" yang sudah ada di tab Indikator (KM-E).
Peta Konsep (KM-H) tidak perlu perubahan frontend sama sekali - bentuk
response API dipertahankan identik meski sumber datanya pindah tempat. 361
test backend lolos (dari 355 - turun net meski banyak tes baru, karena 9
test `IndikatorCrossReferenceControllerTest` ikut terhapus bersama
fiturnya). Browser-verified lewat Playwright lawan data KB sungguhan: tab
lama terkonfirmasi hilang, rule tipe baru bisa dibuat/dihapus lewat UI,
Peta Konsep untuk Indikator nyata hasil migrasi (bukan data buatan)
menampilkan 3 referensi keluar dengan benar. Detail teknis lengkap di
`guratan-api/CLAUDE.md` "Unifikasi cross-reference ke indikator_rules".

**Exclusion logic diputuskan TIDAK dibangun** (bukan ditunda - ditutup
2026-08-19): "if A checked -> B TIDAK checked" dibahas 2026-08-08, user
eksplisit konfirmasi tidak diperlukan ("tidak perlu ada reverse seperti
itu"). `indikator_checked` tetap inclusion-only sesuai desain.

## Inisiatif — Narasi Terpadu & Kombinasi Temuan (2026-08-22)

**Narasi Terpadu**: laporan klien sekarang narasi deskriptif mengalir
(bukan breakdown Sindrom/Aspek/Indikator, itu jadi bahan internal grafolog
saja) - AI generate draft (1 call live per-laporan, asinkron lewat queue
job), grafolog wajib review/edit/finalize sebelum klien bisa lihat.
Optimalisasi: dedup-guard (hash skor, cegah generate ulang percuma),
prompt caching Anthropic, worker timeout & retry_after dinaikkan (cegah
job terpotong/dobel jalan), koreksi skor menurunkan status final→draft
otomatis (bukan auto-generate ulang). Detail teknis lengkap di
`guratan-api/CLAUDE.md` "Narasi terpadu (laporan klien)" +
"optimalisasi 2026-08-22" + "3 celah keselarasan/race-condition".

**Kombinasi Temuan**: mekanisme baru untuk kombinasi BEBERAPA
Indikator/Aspek/Sindrom sekaligus menghasilkan 1 interpretasi baru (beda
dari cascade `indikator_checked` yang cuma memperluas bukti yang sama).
Manajemennya (skema `kombinasi_temuan`/`kombinasi_syarat` + admin UI tab
"Kombinasi Temuan" + mesin evaluasi `KombinasiTemuanService`, otomatis
masuk ke breakdown internal & narasi terpadu) **sudah dibangun dan
teruji** (399 backend tests total). Detail di `guratan-api/CLAUDE.md`
"Kombinasi Temuan".

### Belum dikerjakan / tertunda

- **Data Kombinasi Temuan dari Excel asli** — user konfirmasi kontennya
  ADA di referensi Excel profesional grafolog (sama seperti 704
  Indikator/40 Aspek lain), belum diserahkan/didigitalisasi. Tabel
  `kombinasi_temuan`/`kombinasi_syarat` masih KOSONG di semua environment.
  Begitu file Excel-nya ada: ekstrak jadi seeder JSON (pola sama dengan
  `GrafologiKnowledgeSeeder`), JANGAN isi data dengan tebakan/karangan AI -
  ini melanggar prinsip inti proyek ("LLM tidak pernah mengarang
  interpretasi psikologi").
- Verifikasi manual narasi terpadu lewat browser + kredensial Anthropic
  asli (`.env` masih `LLM_PROVIDER=none` di semua environment yang
  diketahui).

## Inisiatif — Kesiapan Publikasi (checklist, dimulai 2026-08-23)

User bertanya "apalagi yang perlu dikembangkan/diperkuat supaya siap
publikasi, secara sistem handal sesuai prosedur dan konsumen experience
baik (end user/grafolog/B2B)". Checklist ini konsolidasi jawabannya
supaya persisten lintas sesi (bukan cuma di riwayat chat) — update
langsung di sini begitu satu item selesai atau ada temuan baru.

### Selesai (tidak butuh kredensial/keputusan bisnis) — 2026-08-23

- [x] **Lupa password** — flow reset via email, token 60 menit,
  anti-enumeration. Sekalian ketemu & fix `config('app.frontend_url')`
  yang belum pernah terdaftar (link email report-completed mengarah ke
  URL API, bukan frontend).
- [x] **Guard biaya AI** — `throttle:20,60` khusus endpoint generate
  narasi terpadu, menutup celah `force: true` bypass dedup-guard.
- [x] **CI otomatis** — `.github/workflows/ci.yml`, test+lint+build tiap
  push/PR. Sekalian bersihkan pelanggaran gaya kode Pint yang sudah lama
  ada.
- [x] **UX gap 3 persona** — dashboard HR yang sebelumnya rusak total
  (KPI selalu 0), status laporan klien yang salah sinyal (badge "Selesai"
  padahal narasi belum final), peringatan token grafolog sebelum isi
  form 40 aspek.
- [x] **Monitoring (Sentry)** — `sentry/sentry-laravel` terpasang &
  terhubung, mati total sampai `SENTRY_LARAVEL_DSN` diisi (scaffolding
  selesai, aktivasi butuh akun Sentry — lihat kredensial di bawah).
- [x] **Backup database** — `spatie/laravel-backup` dikonfigurasi &
  dijadwalkan, jalur file terverifikasi end-to-end, jalur `mysqldump`
  belum bisa dites di sandbox dev (scaffolding selesai, verifikasi
  server nyata masih di bawah).
- [x] **Patch keamanan dependensi** — `guzzlehttp/guzzle`/
  `league/commonmark` (transitif `laravel/framework`) dibawa ke versi
  yang menambal 8 advisory (beberapa severity tinggi), ditemukan lewat
  `composer audit` saat mengerjakan item backup di atas.

Detail teknis lengkap tiap item di `guratan-api/CLAUDE.md` (cari
"Forgot Password"/"Guard biaya AI"/"UX gaps per persona"/"Monitoring &
backup") dan `guratan-web/CLAUDE.md`.

### Tertunda — butuh kredensial pihak ketiga

- [ ] **Kredensial Anthropic asli** — narasi terpadu masih
  `LLM_PROVIDER=none`, belum pernah dites generate sungguhan.
- [ ] **SMTP production** — masih `MAIL_MAILER=log`/Gmail pribadi (lihat
  `DEPLOYMENT.md` "Catatan email production").
- [ ] **Verifikasi DOKU sandbox** — payment gateway belum pernah dites
  lawan notifikasi sungguhan dari DOKU.

### Tertunda — butuh keputusan bisnis

- [x] **Privacy Policy/ToS** — **selesai 2026-08-30**, lihat "Tertunda —
  di luar kode aplikasi" di bawah. Sudah dipublikasikan sebagai halaman
  nyata (`/kebijakan-privasi`, `/ketentuan-layanan`), belum ditinjau
  pengacara sungguhan tapi sudah bukan draft tersembunyi lagi.
- [x] **Kebijakan retensi data** — **default ditetapkan 2026-08-30**
  (30 hari kerja pasca penghapusan akun, dinyatakan di Kebijakan
  Privasi) - bukan lagi placeholder kosong, meski belum ditinjau legal
  formal.
- [x] **Verifikasi role "grafolog"** — **selesai 2026-09-02**, lihat
  "Pendaftaran grafolog lewat verifikasi data" di bawah. Self-register
  langsung sudah ditutup, sekarang lewat pengajuan biodata+bukti profesi
  yang direview administrator dulu.
- [ ] **Akses gambar rapid-tier lama** — masih di disk publik tanpa
  ownership check (risiko rendah, tier sudah pensiun 2026-08-01) —
  pindah ke private atau biarkan?
- [ ] **Masa berlaku token Sanctum** — sekarang tidak pernah expired.
- [ ] **Peran Supervisor belum ada fungsinya** — role-nya sudah ada di
  sistem sejak MGA Fase 05 (bisa dibuatkan akun lewat
  `/admin/users`), tapi belum ada halaman/kerjaan/review-queue apa pun
  untuknya — perlu diputuskan dulu supervisor itu sebenarnya mengawasi/
  meninjau apa, baru bisa dibangun (2026-09-03, dari pertanyaan user
  "fitur pelengkap apa lagi").
- [x] **Sistem "produk"/tier data-driven — SELESAI 2026-09-03, 4 fase** —
  user konfirmasi beberapa varian produk akan ditambahkan dalam waktu
  dekat, sepadan dibangun tabel `products` sungguhan. Lihat "Inisiatif —
  Sistem Products Data-Driven" di bawah untuk rencana & detail teknis
  lengkap tiap fase.

### Tertunda — operasional produksi (butuh server nyata untuk dieksekusi)

- [ ] **`APP_DEBUG=false`** — sudah didokumentasikan di `DEPLOYMENT.md`,
  tinggal dijalankan saat deploy.
- [ ] **Queue worker supervision** (Supervisor/systemd) — contoh config
  sudah ada di `DEPLOYMENT.md`.
- [ ] **Cron scheduler untuk backup** — `* * * * * php artisan
  schedule:run`, tanpa ini jadwal backup terdaftar tapi tidak pernah
  jalan sendiri.
- [ ] **Verifikasi jalur `mysqldump`** — `backup:run` penuh (bukan
  `--only-files`) belum pernah dites lawan MySQL sungguhan, cuma lewat
  baca kode paket `spatie/db-dumper`.

### Tertunda — di luar kode aplikasi (legal, infrastruktur, produk) — 2026-08-30

User tanya "selain [daftar sebelumnya] apa lagi untuk bisa go public" —
kategori ini belum pernah disebut sepanjang sesi-sesi sebelumnya karena
di luar scope kode murni. Sengaja dipisah dari 3 kategori "Tertunda" di
atas (kredensial/keputusan bisnis/operasional server) karena butuh
keahlian/keputusan yang beda (legal, bisnis go-to-market), bukan sekadar
menunggu satu input untuk dieksekusi.

**Legal & kepatuhan (spesifik Indonesia):**
- [x] **Privacy Policy & Terms of Service dipublikasikan** — 2026-08-30.
  Draft yang tadinya `legal/privacy-policy.md`/`legal/terms-of-service.md`
  (berlabel "JANGAN publikasikan sebelum ditinjau") disederhanakan lalu
  ditayangkan sebagai halaman nyata: `/kebijakan-privasi`
  (`PrivacyPolicyView.vue`) dan `/ketentuan-layanan` (`TermsOfServiceView.vue`),
  ditautkan dari footer global baru (`AppFooter.vue`, dipasang di
  `App.vue`). Atas instruksi eksplisit user ("simpel saja"): TIDAK ada
  dashboard self-service ekspor/hapus data — permintaan hak data-subjek
  diarahkan lewat kanal dukungan pelanggan (`/bantuan`, lihat di bawah)
  sebagai gantinya. Retensi data didefinisikan tetap 30 hari kerja pasca
  penghapusan akun (bukan lagi `[BELUM DIPUTUSKAN]`). Sisa kepatuhan UU
  PDP yang lebih dalam (consent terpisah, penunjukan penanggung jawab
  perlindungan data formal) tetap belum dikerjakan — lihat item di bawah.
- [x] ~~Disclosure penggunaan AI ke klien~~ — **dicoret 2026-08-30 atas
  instruksi eksplisit user** ("tidak perlu ada disclaimer AI"), bukan
  dikerjakan. Jangan tambahkan lagi tanpa keputusan baru dari user.
- [x] **Status legal jasa grafologi — placeholder teknis disiapkan**
  2026-08-30. User menyatakan legitimasi/pengawasan hukum produk berasal
  dari sebuah "biro psikologi" tapi belum menyebutkan namanya — Claude
  sengaja TIDAK mengarang nama entitas ini. Dibangun 2 field
  admin-editable baru di `ContentBlock` (`legal_entity_name`,
  `legal_contact_email`, default kosong) lewat panel `/admin/content`
  yang sudah ada; `PrivacyPolicyView`/`TermsOfServiceView`/`AppFooter`
  semuanya menyembunyikan baris entitas ini sepenuhnya kalau kosong
  (tidak tampil sebagai placeholder ber-kurung-siku ke publik). Ini
  cuma "tempatnya sudah siap" — user masih perlu isi nama biro psikologi
  yang sebenarnya lewat panel admin, dan pertanyaan hukum yang lebih
  besar (perlu izin usaha/lisensi tertentu untuk B2B rekrutmen) masih
  belum dijawab.

**Infrastruktur & operasional:**
- [ ] **Domain + SSL** — belum ada domain nyata terdaftar/dikonfigurasi.
- [ ] **CDN** untuk aset statis (build Vite).
- [ ] **Uptime monitoring eksternal** (mis. UptimeRobot/Pingdom) - beda
  dari Sentry (error tracking) yang sudah ada, ini soal "situs mati total"
  ketika bahkan Sentry sendiri tidak bisa lapor.
- [ ] **Staging environment** terpisah dari dev untuk uji config
  production sebelum benar-benar live.
- [ ] **Load testing** — belum pernah diuji lawan traffic bersamaan yang
  realistis (semua verifikasi sesi-sesi ini pakai 1 browser headless).

**Produk & UX:**
- [x] **Kanal dukungan pelanggan** — 2026-08-30. Halaman `/bantuan`
  (`HelpView.vue`, ditautkan dari footer global) menampilkan email/
  WhatsApp/jam layanan/catatan pembuka, semuanya dibaca dari 4 field
  `ContentBlock` baru (`support_email`, `support_whatsapp`,
  `support_hours`, `support_note`) via panel `/admin/content` yang sudah
  ada — sengaja TIDAK membangun sistem tiket/manajemen kontak baru,
  "managementnya bisa diatur" dipenuhi dengan reuse CMS fixed-field yang
  sudah ada, sesuai instruksi user "simpel saja". Ini juga jadi kanal
  permintaan hak data-subjek (lihat item UU PDP di atas) — bukan
  dashboard self-service terpisah.
- [~] **Uji coba mobile & lintas-browser** — sebelumnya semua verifikasi
  Playwright cuma di viewport desktop Chromium, belum pernah dicek di HP.
  **2026-09-03**: dicek pakai Playwright viewport iPhone (390px) di
  seluruh halaman admin (tabel-berat) + publik — ketemu 2 masalah nyata
  (halaman admin dengan tabel meluber ke samping bikin SELURUH halaman
  horizontal-scroll, bukan cuma tabelnya; tab bar `AdminKnowledgeView.vue`
  sama). **Sudah diperbaiki**: `overflow-x: auto` + `display: block` di
  11 file yang punya `<table>` (`AdminProductsView`, `AdminUsersView`,
  `AdminAuditLogView`, `AdminDiscountsView`, `AdminAnalyticsView`,
  `AdminKnowledgeView`, `AdminRecapGrafologView`/`PaymentsView`/
  `TokenPurchasesView`/`UsersView`, `HrCandidatesView`) + tab bar
  `AdminKnowledgeView.vue`. Dikonfirmasi ulang: 0 overflow di semua
  halaman itu di 390px, tampilan desktop 1280px tidak berubah sama
  sekali. **Masih tertunda**: navbar admin tidak punya menu hamburger
  (17 link nav cuma di-wrap jadi berbaris di HP, bukan disembunyikan ke
  menu - bukan overflow, tapi makan banyak ruang layar) - keputusan
  desain lebih besar, sengaja belum dikerjakan. Firefox/Safari juga
  belum pernah dicek sama sekali (cuma Chromium via Playwright).
- [x] **Web analytics (Google Analytics)** — 2026-08-30. Terpasang di
  `guratan-web` (`src/lib/analytics.js`, gtag.js diinjeksi manual tanpa
  dependency npm baru, pageview dikirim manual lewat
  `router.afterEach()` karena ini SPA). **Sengaja tidak aktif sampai
  admin isi Measurement ID sungguhan** — `VITE_GA_MEASUREMENT_ID` di
  `guratan-web/.env.development` default kosong, kode no-op total kalau
  kosong (tidak ada script GA diinjeksi, tidak ada `window.gtag`) —
  konsisten dengan pola placeholder `legal_entity_name` di atas: siap
  dipasang, tapi butuh admin mengisi Measurement ID GA4 sungguhan
  sebelum benar-benar melacak. Untuk production, `VITE_GA_MEASUREMENT_ID`
  perlu diisi via env var deployment (tidak ada `.env.production` di
  repo ini). Kebijakan Privasi (`/kebijakan-privasi`) sudah diperbarui
  mengungkap pemakaian Google Analytics di section "Berbagi Data ke
  Pihak Ketiga". **Belum ada cookie-consent banner** — user minta
  "pasang saja" tanpa consent flow tambahan; kalau nanti dianggap perlu
  secara hukum (UU PDP), itu keputusan/pekerjaan terpisah.

**Keamanan tambahan:**
- [ ] **2FA untuk akun staf/admin** — data psikologis sensitif + akses
  ke sistem pembayaran, sekarang cuma dilindungi password.

### Gap "management" — ditemukan DAN ditutup 2026-08-23

User bertanya "apakah secara management sudah lengkap dan baik?" — dicek
langsung ke kode (routes/api.php + controller Admin\*), 2 gap konkret
ditemukan, user konfirmasi "ya" untuk dikerjakan. Investigasi lanjutan
saat mengerjakan gap #2 menemukan gap ketiga yang lebih parah dari dugaan
awal (lihat di bawah). Ketiganya sudah selesai dikerjakan hari yang sama:

- [x] **Viewer log audit** — `Api\Admin\AuditLogController::index()`
  (`GET /admin/audit-logs`, paginated, filter aksi/actor/tanggal) +
  halaman `AdminAuditLogView.vue` (`/admin/audit-logs`). Pertama kalinya
  45 titik `AuditLog::record()` di seluruh aplikasi bisa dibaca kembali
  lewat aplikasi, bukan cuma query DB manual.
- [x] **Staff account bisa diedit & dinonaktifkan** — kolom
  `users.is_active` (bukan hard-delete), `PATCH /admin/users/{user}` (edit
  nama/email/role/company_id, toggle aktif, reset password opsional),
  tombol "Ubah" + panel edit inline di `AdminUsersView.vue`. Menonaktifkan
  langsung mencabut semua token Sanctum aktif (bukan cuma blokir login
  berikutnya). Admin tidak bisa menonaktifkan akun sendiri (guard 422).
- [x] **Ditemukan saat mengerjakan #2, ternyata lebih parah dari dugaan:
  tidak ada UI perusahaan SAMA SEKALI.** `POST /api/admin/companies` sudah
  ada sejak MGA Fase 06 tapi tidak pernah punya frontend caller — akun HR
  (wajib `company_id`) sebenarnya tidak bisa dibuat lewat aplikasi tanpa
  API call manual, dropdown company di form buat-HR selama ini kosong tak
  tersambung apa pun. Ditutup sekalian: `PATCH /admin/companies/{company}`
  (edit nama + toggle aktif, TIDAK mencabut akses hr terkait secara
  cascade — keputusan desain eksplisit) + section "Perusahaan" baru
  (create form + tabel) di `AdminUsersView.vue`.

14 test baru, 438 backend tests total (up from 424). Detail teknis
lengkap di `guratan-api/CLAUDE.md` "Gap management ditutup" dan
`guratan-web/CLAUDE.md`. **Browser-verified 2026-08-23** lewat Playwright
headless (9/9 pemeriksaan lolos, 0 error konsol nyata) — lihat
`guratan-api/CLAUDE.md`'s entri yang sama untuk skenario lengkap. Sekalian
menemukan & memperbaiki 1 bug produksi nyata yang tidak terkait langsung
(config `backup.php`'s notification email crash total aplikasi begitu
`ADMIN_EMAIL` dikosongkan - state yang sengaja didukung, lihat detail di
`guratan-api/CLAUDE.md`).

Sudah diketahui & didokumentasikan sebagai gap terpisah (BUKAN temuan
baru, lihat "HR: Company, Candidate import, Assignment" di
`guratan-api/CLAUDE.md`): tidak ada dashboard admin untuk lihat semua
Project/Report lintas perusahaan, tidak ada editor "Master Data" terpusat
— keduanya disebut eksplisit di rencana MGA Fase 05/06 asli dan sengaja
ditunda, bukan lupa.

### 3 fitur B2B yang belum ada — dipecah 3 fase, 2026-08-23

User tanya "apa saja fitur B2B" lalu minta ketiganya dijalankan setelah
lihat dulu dipecah fase (dikonfirmasi lewat EnterPlanMode). Untuk fase 3,
ditanya model harga lewat AskUserQuestion — user pilih **kontrak custom
per perusahaan** (sales-led, sistem cuma mencatat, tidak menghitung
tagihan otomatis).

- [x] **Fase 1 — dashboard admin lintas-perusahaan** (statistik agregat
  HR/kandidat/laporan selesai per company). Selesai — lihat
  `guratan-api/CLAUDE.md` "B2B Fase 1".
- [x] **Fase 2 — laporan tersegmentasi per-Topik untuk B2B** (wire
  `TopikFilterService`/`segmen()` yang sudah ada ke UI HR). Selesai —
  lihat `guratan-api/CLAUDE.md` "B2B Fase 2".
- [x] **Fase 3 — kontrak B2B** (`company_contracts`, record-only, admin
  CRUD). Selesai — lihat `guratan-api/CLAUDE.md` "B2B Fase 3".

**Seluruh rencana 3-fase B2B selesai 2026-08-23** (459 backend tests
total, up from 447 sebelum fase 1 dimulai). Fitur B2B yang MASIH belum
ada, sengaja bukan bagian rencana ini (lihat "HR: Company, Candidate
import, Assignment" di `guratan-api/CLAUDE.md`): editor "Master Data"
terpusat lintas-entitas, dan kalkulasi tagihan otomatis dari kontrak
(sengaja tidak dibangun — user pilih model record-only sales-led, bukan
kalkulasi otomatis).

### Notifikasi/Pengumuman/Promo untuk grafolog/klien/B2B — 2026-08-23

User minta ini dibangun. Ternyata pengumuman (`Announcement`, sudah bisa
target per-role termasuk `hr`) dan kode diskon (`DiscountCode`) **sudah
ada** sejak Commerce Fase B/F — dikonfirmasi ke user lewat
AskUserQuestion, bukan dibangun ulang. Yang benar-benar baru: mekanisme
bel notifikasi PERSISTEN per-user (`announcement_reads` — read/unread
tersimpan di server, bukan cuma dismiss lokal-session yang hilang tiap
reload seperti sebelumnya), ditempel global di navbar (bukan cuma
halaman Dashboard). Untuk B2B: TIDAK membangun billing/subscription baru
(itu keputusan bisnis besar, masih ditunda) — sebagai gantinya
`Announcement`'s target-role `hr` yang sudah ada dipakai sebagai kanal
promo B2B (pengumuman, bukan kode diskon transaksional, karena memang
belum ada alur pembelian B2B untuk didiskon).

Browser-verified 2026-08-23 (Playwright): 3 pengumuman dibuat (umum/
grafolog/hr) → ketiga persona (grafolog/klien/hr) masing-masing melihat
badge & isi notifikasi yang BENAR sesuai target role-nya, read-state
terbukti persisten lewat reload halaman. 9/9 pemeriksaan lolos. Detail
teknis lengkap di `guratan-api/CLAUDE.md` "Notifikasi/Pengumuman/Promo"
dan `guratan-web/CLAUDE.md`. 444 backend tests total (up from 438).

### Pendaftaran grafolog lewat verifikasi data — 2026-09-02

User minta jalur pendaftaran grafolog yang cukup "biodata dan bukti
profesi atau apapun", ditinjau dulu sebelum akun aktif. Investigasi
sebelum membangun menemukan sesuatu yang penting: jalur publik
`/auth/register` **sudah** mengizinkan siapa pun mendaftar langsung
sebagai `role: grafolog` tanpa review sama sekali sejak MGA Fase 05
(keputusan produk yang eksplisit saat itu, dites lewat
`test_register_can_create_grafolog_role`) — bukan celah yang lupa
ditutup, tapi keputusan lama yang sekarang digantikan permintaan baru
ini. Jalur lama itu **ditutup** (`RegisterRequest` cuma izinkan `role:
user` lagi), diganti alur baru: `POST /api/grafolog-applications`
(publik, tanpa token/akun langsung) → status `pending` → administrator
meninjau biodata + dokumen bukti profesi lewat halaman baru
`/admin/grafolog-applications` → Setujui (baru di titik ini akun `users`
sungguhan dibuat, role `grafolog`, aktif) atau Tolak (dengan catatan
opsional, boleh daftar ulang dengan email sama). Dokumen bukti profesi
disimpan di disk private (bukan URL publik), cuma bisa diunduh admin.

Browser-verified 2026-09-02 (Playwright): submit pengajuan sungguhan
dengan file diunggah nyata → tidak ada token/redirect (beda dari
register biasa) → email sama tidak bisa dipakai daftar 2x selagi masih
`pending` → admin login, buka pengajuan, lihat dokumen di tab baru,
Setujui → akun grafolog baru langsung bisa login pakai password yang
sama persis diajukan. Detail teknis lengkap di `guratan-api/CLAUDE.md`
"Pendaftaran grafolog lewat verifikasi data" dan `guratan-web/CLAUDE.md`.
473 backend tests total (up from 459).

### Laporan/Rekap + Dashboard Analitik Admin — dipecah 4 fase, mulai 2026-09-03

User minta laporan rekap (pengguna, grafolog, pembelian token, pembelian
laporan) plus dashboard analitik (revenue, analisa produk, pertumbuhan
pengguna, kinerja grafolog, ekonomi token, efektivitas promo/diskon) —
eksplisit minta daftar dulu sebelum dikerjakan. Daftar 10 item diajukan,
user pilih: kerjakan semua bertahap (4 fase), chart visual (bukan tabel
angka saja). Rencana detail (lewat plan mode + AskUserQuestion untuk 2
keputusan desain) disimpan sebagai riwayat sesi; ringkasan tiap fase:

- **Fase 1 (selesai 2026-09-03)**: mekanisme export CSV
  (`App\Support\CsvStreamer`, dipakai ulang tiap fase setelahnya) +
  Rekap Pengguna + Rekap Grafolog. Detail teknis di `guratan-api/CLAUDE.md`
  "Laporan/Rekap admin — Fase 1" dan `guratan-web/CLAUDE.md`.
- **Fase 2 (selesai 2026-09-03)**: Rekap Pembelian Token + Rekap
  Pembelian Laporan, pakai ulang `CsvStreamer` dari Fase 1. Detail
  teknis di `guratan-api/CLAUDE.md` "Laporan/Rekap admin — Fase 2" dan
  `guratan-web/CLAUDE.md`.
- **Fase 3 (selesai 2026-09-03)**: Dashboard Analitik bagian Revenue,
  Analisa Produk, Pertumbuhan Pengguna — memperkenalkan Chart.js+
  vue-chartjs (dependency chart pertama di `guratan-web`), warna chart
  ikut dark mode otomatis lewat `chartTheme.js`. Detail teknis di
  `guratan-api/CLAUDE.md` "Laporan/Rekap admin — Fase 3" dan
  `guratan-web/CLAUDE.md`.
- **Fase 4 (selesai 2026-09-03)**: Dashboard Analitik bagian Kinerja
  Grafolog, Ekonomi Token, Efektivitas Promo/Diskon — menutup seluruh
  rencana 4-fase. Detail teknis di `guratan-api/CLAUDE.md` "Laporan/Rekap
  admin — Fase 4" dan `guratan-web/CLAUDE.md`.

**Seluruh inisiatif selesai 2026-09-03**: export CSV (`App\Support\CsvStreamer`),
4 halaman rekap terfilter+export (Pengguna, Grafolog, Pembelian Token,
Pembelian Laporan), dan dashboard analitik 6-section dengan 7 chart
(`Admin\AnalyticsController`, Chart.js+vue-chartjs) — semuanya
browser-verified dengan data seed nyata, bukan cuma test hijau. 502
backend tests total (up from 473 sebelum inisiatif ini dimulai).

### Inisiatif — Sistem Products Data-Driven, dipecah 4 fase, mulai 2026-09-03

User bertanya dari mana asal tier "Comprehensive"/"Master" dan apakah
bisa di-manage karena produk turunan akan banyak ditambahkan. Audit kode
mengonfirmasi: tidak ada tabel `products` terpusat — tier murni string
diulang independen di 5 tabel/kolom, dengan 7 titik validasi hardcoded
di backend dan ~9 titik hardcoded di frontend. Harga & biaya token per
tier SUDAH admin-manageable (`/admin/pricing`/`/admin/tokens`), yang
belum ada adalah kemampuan menambah tier BARU tanpa deploy kode. User
konfirmasi lewat AskUserQuestion: karena beberapa varian produk akan
ditambahkan dalam waktu dekat, sepadan dibangun tabel `products`
sungguhan sekarang (bukan ditunda sampai kebutuhan konkret).

Kabar baik dari audit: nol perbedaan perilaku antara comprehensive/master
di lapisan service (scoring/narasi/PDF) — cuma beda harga & label. Tier
baru otomatis mewarisi semua perilaku itu tanpa sentuh
`ScoringEngineService`/dsb sama sekali.

- **Fase 1 (selesai 2026-09-03)**: fondasi — tabel `products` + model
  `Product` (`activeCodes()` helper, `code` immutable setelah dibuat),
  `ProductSeeder` (idempoten, seed comprehensive/master aktif + rapid
  nonaktif untuk preservasi histori), `Admin\ProductController` (CRUD
  tanpa delete, tidak dipaginasi, menampilkan produk nonaktif juga),
  `Api\ProductController` publik (`GET /api/products`, cuma aktif).
  Belum menyentuh 7 titik hardcoded lama sama sekali (itu Fase 2b) -
  murni fondasi baru berdampingan dengan yang lama. Detail teknis di
  `guratan-api/CLAUDE.md` "Sistem Products data-driven — Fase 1".
- **Fase 2a (selesai 2026-09-03)**: lebarkan 4 kolom `enum`
  (`handwriting_samples.tier`, `personality_reports.tier`,
  `pricing_plans.tier`, `token_costs.tier`) jadi `string` biasa — murni
  skema, nol perubahan perilaku (dibuktikan lewat suite test lama yang
  tetap hijau tanpa diubah sama sekali, 513/514 identik sebelum/sesudah).
  `discount_codes.applicable_tiers` tetap JSON free-form seperti sekarang.
  Detail teknis di `guratan-api/CLAUDE.md` "Sistem Products data-driven
  — Fase 2a".
- **Fase 2b (selesai 2026-09-03)**: ganti 7 titik `in:comprehensive,master`/
  `in_array([...])` hardcoded jadi `Product::activeCodes()` dinamis
  (3 Form Request, 1 aturan diskon yang tetap terima literal `'token'`
  di sampingnya, 2 pengaman controller, 1 pembuat respons wallet token).
  Ini titik di mana tier baru benar-benar bisa dipakai lewat backend
  untuk pertama kalinya. Gotcha ditemukan & diperbaiki: `RefreshDatabase`
  tidak menjalankan seeder otomatis, jadi 7 test class yang lewat
  endpoint terkait butuh trait baru `tests/Concerns/SeedsProducts.php`
  supaya tidak semua test tier gagal karena tabel `products` kosong.
  Detail teknis di `guratan-api/CLAUDE.md` "Sistem Products data-driven
  — Fase 2b".
- **Fase 3 (selesai 2026-09-03)**: `AdminProductsView.vue` baru (CRUD
  produk) + `AdminPricingView.vue`/`AdminTokensView.vue` jadi dinamis
  (fetch `/products`, bukan lagi hardcode 2 tier) — ini titik pembuktian
  nilai utama: produk baru (dites lewat produk seed "Deluxe") langsung
  dapat kartu harga/biaya token tanpa perubahan kode lagi, dikonfirmasi
  lewat browser-test end-to-end. Detail teknis di `guratan-api/CLAUDE.md`
  "Sistem Products data-driven — Fase 3" dan `guratan-web/CLAUDE.md`.
- **Fase 4 (selesai 2026-09-03, PENUTUP INISIATIF)**: sisa 5 file
  frontend publik/staf (`OrderView.vue`, `LandingView.vue` dengan
  fallback ke daftar hardcoded kalau fetch gagal, `HrCandidatesView.vue`,
  `PortalGrafologView.vue`, `AdminDiscountsView.vue`'s tier-picker) jadi
  dinamis lewat `GET /api/products` — titik ini produk baru benar-benar
  bisa dipesan/dipilih end-to-end oleh klien/HR/grafolog. Dibuktikan
  lewat browser-test end-to-end penuh: produk ke-3 "Elite" dibuat lewat
  `/admin/products`, langsung bisa dipesan klien di `/pesan`, tampil di
  landing page publik, jadi opsi tier di form impor HR & Portal Grafolog,
  dan jadi checkbox `applicable_tiers` saat admin buat kode diskon —
  lalu dinonaktifkan dan dikonfirmasi hilang dari kelima permukaan itu
  sekaligus, 0 error konsol. Detail teknis di `guratan-api/CLAUDE.md` dan
  `guratan-web/CLAUDE.md`, masing-masing entri "Sistem Products
  data-driven — Fase 4 (penutup)".

**Status akhir**: tier/produk laporan sekarang murni data — admin bisa
menambah varian produk baru dari `/admin/products` tanpa deploy kode
apa pun sama sekali, langsung mewarisi harga, biaya token, dan seluruh
alur pemesanan/impor/portal/diskon secara otomatis.
