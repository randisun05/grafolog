<!--
STATUS: DRAFT — belum ditinjau tim legal/bisnis, JANGAN publikasikan atau
tautkan dari aplikasi sebelum disetujui. Ditulis 2026-07-27. Semua
[DALAM KURUNG SIKU] wajib diisi/dikonfirmasi. Pasal 3 (batasan keakuratan)
adalah yang PALING PENTING di dokumen ini - ini yang melindungi Guratan
secara hukum dari klaim "produk ini salah mendiagnosis saya", sesuai prinsip
"jangan klaim akurasi ilmiah berlebihan" di CLAUDE.md root. Jangan lemahkan
pasal ini demi bahasa pemasaran yang lebih menarik.
-->

# Ketentuan Layanan Guratan

*Terakhir diperbarui: [TANGGAL RILIS]*

## 1. Tentang Layanan

Guratan adalah platform analisis grafologi (ilmu membaca kepribadian dari
tulisan tangan) berbasis web, tersedia dalam tiga tier:

| Tier | Cara Kerja |
|---|---|
| Rapid (gratis) | Skor otomatis dari foto tulisan tangan. |
| Comprehensive (berbayar) | Grafolog bersertifikat mengukur tulisan tangan fisik Anda secara manual dan mengisi skor 40 aspek. |
| Master (berbayar, premium) | Sama seperti Comprehensive, ditambah sesi konsultasi langsung dengan grafolog. |

## 2. Bukan Alat Diagnosis Klinis

**Guratan adalah alat insight reflektif untuk eksplorasi kepribadian, BUKAN
alat diagnosis psikologis atau klinis.** Grafologi tidak memiliki dasar
ilmiah sekuat psikometri standar (misalnya Big Five). Laporan yang Anda
terima:

- Tidak boleh dijadikan satu-satunya dasar keputusan penting (medis, hukum,
  finansial, atau hubungan kerja/rekrutmen).
- Tidak menggantikan konsultasi dengan psikolog atau profesional kesehatan
  mental berlisensi.
- Disusun dari basis pengetahuan grafologi (referensi Sindrom/Aspek/
  Indikator) yang ditafsirkan oleh grafolog bersertifikat (tier
  Comprehensive/Master) atau sistem otomatis dengan skor sementara/placeholder
  (tier Rapid — lihat pasal 2a).

### 2a. Tier Rapid Menggunakan Skor Sementara

Tier Rapid **belum** menggunakan computer vision sungguhan untuk menganalisis
foto tulisan tangan Anda — analisis gambar otomatis masih dalam
pengembangan. Skor yang ditampilkan pada tier Rapid saat ini bersifat
sementara/placeholder untuk keperluan demonstrasi alur produk, bukan hasil
analisis tulisan tangan yang valid. [WAJIB: pastikan pasal ini dihapus/
diperbarui begitu computer vision sungguhan diimplementasikan - lihat status
di guratan-api/CLAUDE.md sebelum publikasi ulang dokumen ini.]

## 3. Batasan Tanggung Jawab

Sepanjang diizinkan hukum yang berlaku, [NAMA PERUSAHAAN] tidak bertanggung
jawab atas keputusan yang Anda ambil berdasarkan laporan Guratan, termasuk
namun tidak terbatas pada keputusan karier, hubungan, atau kesehatan mental.
Jika Anda mengalami masalah kesehatan mental, segera hubungi profesional
berlisensi atau layanan darurat setempat.

## 4. Akun Pengguna

- Anda bertanggung jawab menjaga kerahasiaan kata sandi akun Anda.
- Peran "grafolog" hanya diberikan kepada individu yang telah diverifikasi
  memiliki sertifikasi grafologi yang sah. [BELUM ADA PROSES VERIFIKASI
  TEKNIS - saat ini role diset manual/self-declared saat registrasi, lihat
  guratan-api/CLAUDE.md. Perlu proses verifikasi sebelum go-live jika role
  grafolog membawa konsekuensi hukum/kepercayaan publik.]

## 5. Pembayaran & Pengembalian Dana (Tier Comprehensive/Master)

- Pembayaran diproses melalui mitra payment gateway pihak ketiga, DOKU.
- Harga berlaku saat transaksi dilakukan: [ISI HARGA RESMI comprehensive
  dan master - lihat config/pricing.php di guratan-api, angka di sana masih
  PLACEHOLDER untuk tier Master, belum dikonfirmasi bisnis].
- Kebijakan pengembalian dana: [BELUM DITULIS - wajib diputuskan sebelum
  go-live: apakah ada refund kalau grafolog belum mulai mengerjakan skor?
  Bagaimana kalau pembayaran DOKU sukses tapi terjadi kegagalan teknis di
  sisi kami?]

## 6. Kekayaan Intelektual

Basis pengetahuan grafologi (Sindrom, Aspek, Indikator, dan narasi terkait)
adalah milik [NAMA PEMEGANG HAK - perusahaan/individu penyusun materi
grafologi asli]. Laporan yang dihasilkan untuk Anda adalah milik Anda untuk
penggunaan pribadi.

## 7. Perubahan Ketentuan

Kami dapat memperbarui Ketentuan Layanan ini dari waktu ke waktu.
Perubahan material akan diberitahukan melalui [email / notifikasi di
aplikasi].

## 8. Hukum yang Berlaku

Ketentuan ini diatur oleh hukum Republik Indonesia. [Konfirmasi mekanisme
penyelesaian sengketa - pengadilan negeri mana / arbitrase, sesuai domisili
badan hukum perusahaan.]

## 9. Kontak

[NAMA PERUSAHAAN/ENTITAS HUKUM]
[ALAMAT]
[EMAIL KONTAK]
