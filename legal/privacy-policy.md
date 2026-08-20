<!--
STATUS: DRAFT — belum ditinjau tim legal/bisnis, JANGAN publikasikan atau
tautkan dari aplikasi sebelum disetujui. Ditulis 2026-07-27 berdasarkan
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
  dalam bentuk asli), peran (individu atau grafolog bersertifikat).
- **Foto tulisan tangan** (tier Rapid): diunggah oleh Anda untuk dianalisis.
- **Skor grafologi** (tier Comprehensive/Master): 40 aspek yang diinput
  grafolog bersertifikat berdasarkan pengukuran manual tulisan tangan fisik
  Anda.
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
- Mengirim notifikasi email saat laporan Anda selesai dibuat.
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
- Sample tulisan tangan (foto, tier Rapid) dan laporan hanya bisa diakses
  oleh pemilik akun terkait dan grafolog yang menangani kasus tersebut.
- [KEPUTUSAN TERTUNDA - lihat guratan-api/CLAUDE.md "Open security findings":
  foto tulisan tangan tier Rapid saat ini disimpan di disk publik dengan nama
  file acak (tidak bisa ditebak, tapi tidak ada pengecekan kepemilikan
  eksplisit). Perbarui bagian ini begitu keputusan diambil.]

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
- Untuk B2B/rekrutmen (fase pasca-MVP, belum aktif): [BELUM ADA KEBIJAKAN -
  wajib ditulis sebelum fitur B2B diluncurkan, termasuk consent eksplisit
  dari kandidat sebelum laporan dibagikan ke perusahaan perekrut].

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
