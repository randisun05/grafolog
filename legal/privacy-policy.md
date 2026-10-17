<!--
STATUS: DRAFT — belum ditinjau tim legal/bisnis. Sejak 2026-10-03 file ini
DITAMPILKAN di guratan-web (/privasi, ditautkan dari checkbox persetujuan
register) dengan banner "Draf" selama masih ada [kurung siku] - wajib
ditinjau penasihat hukum sebelum go-live. Ditulis 2026-07-27, diperbarui
2026-10-03 (Rapid dihapus, foto privat, HR, supervisor, konsultasi) berdasarkan
CLAUDE.md root (prinsip produk) dan kondisi teknis nyata di guratan-api/web
CLAUDE.md. Semua [DALAM KURUNG SIKU] wajib diisi/dikonfirmasi sebelum rilis.
Rujukan hukum: UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (PDP) -
draft ini mengasumsikan Guratan adalah Pengendali Data Pribadi berdasarkan UU
tsb; konfirmasikan ke penasihat hukum, jangan andalkan draft AI untuk
kepatuhan hukum final.
-->

# Kebijakan Privasi Guratan

*Terakhir diperbarui: [TANGGAL RILIS]*

## 1. Data Apa yang Kami Kumpulkan

- **Data akun**: nama, email, kata sandi (di-hash, tidak pernah disimpan
  dalam bentuk asli), peran, serta waktu Anda memberikan persetujuan
  pemrosesan data.
- **Foto tulisan tangan**: diunggah oleh Anda (atau oleh grafolog/perusahaan
  yang menangani Anda) untuk diukur oleh grafolog. Disimpan di penyimpanan
  privat, tidak bisa diakses lewat tautan publik.
- **Skor grafologi**: 40 aspek yang diinput grafolog bersertifikat
  berdasarkan pengukuran manual tulisan tangan Anda, beserta catatan
  pengukuran.
- **Jadwal konsultasi** (tier Master): waktu dan tautan pertemuan daring.
- **Data pembayaran** (tier Comprehensive/Master): nomor invoice, jumlah,
  status transaksi. Detail kartu/rekening/e-wallet Anda **tidak pernah**
  disimpan atau diproses oleh server kami — diproses langsung oleh mitra
  payment gateway kami, DOKU ([tautan kebijakan privasi DOKU]).
- **Log akses**: setiap kali laporan kepribadian Anda dibaca (termasuk oleh
  Anda sendiri), dicatat untuk audit keamanan (waktu, alamat IP, hasil akses
  diterima/ditolak).

## 2. Kenapa Data Ini Sensitif

Laporan kepribadian yang dihasilkan Guratan berasal dari analisis grafologi
terhadap tulisan tangan Anda. **Kami memperlakukan ini sebagai data pribadi
yang bersifat sensitif** (terkait profil psikologis seseorang), meskipun
grafologi sendiri bukan alat diagnosis klinis berbasis psikometri standar —
lihat Ketentuan Layanan pasal soal batasan keakuratan. Karena sifat
sensitifnya, kami menerapkan kontrol keamanan tambahan (lihat bagian 4).

## 3. Untuk Apa Data Digunakan

- Menghasilkan dan menampilkan laporan kepribadian Anda.
- Memproses pembayaran untuk tier berbayar (Comprehensive/Master).
- Mengirim notifikasi email saat laporan Anda selesai dan disetujui, saat
  jadwal konsultasi ditetapkan, dan untuk pemulihan kata sandi.
- Peninjauan mutu (quality review) laporan oleh supervisor internal
  sebelum laporan dirilis kepada Anda.
- Audit keamanan internal (mendeteksi akses tidak sah ke laporan sensitif).
- **Tidak digunakan** untuk melatih model AI/LLM pihak ketiga — narasi
  laporan berasal dari basis pengetahuan grafologi yang sudah ada, LLM (jika
  dipakai) hanya merangkai teks yang sudah tersedia, bukan mengirim data
  pribadi Anda ke penyedia LLM eksternal untuk pemrosesan bebas. [Konfirmasi:
  status LLM_PROVIDER aktif saat rilis - lihat guratan-api/CLAUDE.md, karena
  ini memengaruhi apakah ada data yang benar-benar dikirim ke pihak ketiga.]

## 4. Bagaimana Data Dilindungi

- Kata sandi di-hash (bcrypt), tidak pernah disimpan/ditampilkan dalam
  bentuk asli.
- Autentikasi berbasis token (Sanctum), dikirim lewat HTTPS di production.
- Setiap pembacaan laporan kepribadian tercatat di log audit internal,
  terlepas dari apakah akses tersebut berhasil atau ditolak.
- Foto tulisan tangan dan laporan hanya bisa diakses oleh pemilik akun,
  grafolog yang menangani, supervisor peninjau mutu, administrator, dan -
  untuk kandidat rekrutmen - HR perusahaan yang mendaftarkan Anda. Setiap
  akses foto dan laporan dicatat di log audit.
- Sesi login otomatis kedaluwarsa setelah [30] hari.

## 5. Berapa Lama Data Disimpan

[BELUM DIPUTUSKAN - perlu kebijakan retensi resmi. Pertimbangan: laporan
kepribadian dan skor mentah bisa disimpan selama akun aktif; pertimbangkan
periode penghapusan otomatis setelah akun tidak aktif N tahun, sesuai
prinsip minimisasi data UU PDP.]

## 6. Berbagi Data ke Pihak Ketiga

- **DOKU** (payment gateway): menerima nama, email, dan detail transaksi
  untuk memproses pembayaran tier berbayar. Tidak menerima foto tulisan
  tangan atau skor kepribadian Anda.
- **Penyedia email** [isi nama provider SMTP production]: menerima alamat
  email untuk mengirim notifikasi laporan selesai.
- Kami **tidak menjual** data pribadi Anda ke pihak mana pun.
- **Perusahaan perekrut (B2B)**: jika Anda didaftarkan sebagai kandidat
  oleh HR sebuah perusahaan, laporan Anda dapat dilihat oleh HR perusahaan
  tersebut. Perusahaan wajib memperoleh persetujuan tertulis Anda sebelum
  mendaftarkan Anda (dikonfirmasi HR di sistem saat impor kandidat).
  [Tinjau penasihat hukum: apakah perlu perjanjian pemrosesan data
  terpisah dengan perusahaan klien.]

## 7. Hak Anda

Sesuai UU PDP, Anda berhak untuk:
- Meminta salinan data pribadi yang kami simpan tentang Anda.
- Meminta koreksi data yang tidak akurat.
- Meminta penghapusan akun dan data terkait (dengan pengecualian data yang
  wajib disimpan untuk kepatuhan hukum/pembukuan transaksi).
- Menarik persetujuan pemrosesan data (dapat berdampak pada kemampuan kami
  memberikan layanan).

Hubungi kami di [EMAIL KONTAK PRIVASI] untuk permintaan terkait hak-hak di
atas.

## 8. Kontak

[NAMA PERUSAHAAN/ENTITAS HUKUM]
[ALAMAT]
[EMAIL KONTAK]
