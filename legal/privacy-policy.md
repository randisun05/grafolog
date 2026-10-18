<!--
Ditulis ulang 2026-10-18 (tahap 5) mengacu UU No. 27 Tahun 2022 (PDP): data
spesifik/sensitif, dasar pemrosesan (persetujuan), hak subjek data (Ps. 5-15),
kewajiban pengendali (Ps. 20-50 termasuk pemberitahuan kegagalan pelindungan
dalam 3x24 jam, Ps. 46), pejabat PDP (Ps. 53), transfer lintas negara (Ps. 56).
Isi mengikuti kondisi sistem nyata (lihat guratan-api/CLAUDE.md). Kurung siku
[HURUF KAPITAL] = data entitas yang harus diisi. Belum ditinjau penasihat hukum.
-->

# Kebijakan Privasi Guratan

*Berlaku sejak: [TANGGAL RILIS] · Versi 1.1 (19 Oktober 2026)*

[NAMA PERUSAHAAN] ("kami") adalah **Pengendali Data Pribadi** atas data yang diproses
lewat Guratan menurut UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP).

## 1. Data yang Kami Proses

| Kategori | Isi |
|---|---|
| Akun | Nama, email, kata sandi (di-hash), peran, waktu persetujuan. |
| Tulisan tangan | **Scan PDF** tulisan tangan, disimpan privat (tanpa tautan publik). |
| Hasil analisis | Skor 40 aspek, catatan pengukuran, laporan kepribadian, analisis gabungan kandidat. |
| Kandidat (B2B) | Nama, identitas akun yang dibuat HR, posisi/jabatan yang dinilai, status penugasan. |
| CV / profil | Isian CV yang diatur administrator (mis. profil, pendidikan, keluarga, kontak), termasuk **foto profil dan dokumen CV** (PDF/DOCX). Variabel bertanda tersembunyi hanya terlihat oleh Anda dan administrator. |
| Grafolog | Profil, sertifikat, CV profesional, ketersediaan bulanan, saldo dan riwayat token, riwayat supervisi. |
| Transaksi | Nomor pesanan/invoice, jumlah, status; detail kartu/rekening **tidak** kami simpan (diproses DOKU). |
| Komunikasi | Pesan kotak masuk, riwayat email notifikasi, jadwal konsultasi. |
| Log aktivitas | Waktu, akun, jenis kejadian (permintaan/login/login gagal/logout), alamat endpoint, kode status, alamat IP, user agent. **Tanpa isi permintaan** dan tanpa isi tulisan/laporan. |
| Log audit | Setiap akses ke laporan/scan dan tindakan penting administratif. |

## 2. Data Sensitif

Hasil analisis grafologi adalah profil kepribadian seseorang. Kami **memperlakukannya
sebagai data pribadi yang bersifat sensitif** dan hanya memprosesnya dengan persetujuan
eksplisit Anda (atau, untuk kandidat, persetujuan yang dikonfirmasi perusahaan). Kami
tidak memakai data ini untuk iklan atau profiling lain di luar layanan.

## 3. Tujuan dan Dasar Pemrosesan

- Membuat dan menampilkan laporan Anda (persetujuan/pelaksanaan perjanjian).
- Memproses pembayaran, refund oleh administrator, dan pembukuan (perjanjian & kewajiban hukum).
- Mengirim notifikasi email dan kotak masuk tentang pesanan/penugasan/laporan.
- Validasi mutu laporan lewat supervisi grafolog lain (laporan yang diminta supervisi dilihat supervisor terkait).
- Keamanan sistem, pencegahan penyalahgunaan, dan audit (kepentingan sah & kewajiban keamanan).
- Analisis gabungan kandidat berbantuan AI untuk perusahaan (lihat pasal 4).

## 4. Pemrosesan oleh Penyedia AI

Analisis gabungan memakai penyedia model bahasa pihak ketiga ([PENYEDIA AI]). Yang
dikirim hanya **data teranonimkan**: ringkasan skor dan teks dari basis pengetahuan
grafologi, **tanpa nama, email, identitas, maupun scan**. Teks pembentuk narasi laporan
berasal dari basis pengetahuan; AI tidak mengarang interpretasi baru dan tidak
memutuskan kelolosan. Hasil disimpan sementara (cache) agar tidak diproses ulang.
Data tidak digunakan kami untuk melatih model. Penyedia dapat berada di luar Indonesia;
transfer lintas negara dilakukan dengan memastikan tingkat pelindungan yang memadai
sesuai UU PDP.

## 5. Siapa yang Dapat Mengakses

- Pemilik akun, grafolog yang menangani, supervisor yang ditugaskan pada laporan itu,
  dan administrator (terbatas kebutuhan operasional).
- **Kandidat B2B**: HR perusahaan yang mendaftarkan Anda melihat laporan dan analisis
  Anda. Perusahaan wajib mendapat persetujuan Anda sebelum mendaftarkan; akun Anda dibuat
  perusahaan tanpa email, dan kata sandi sementara wajib Anda ganti.
- **CV**: klien dan perusahaan dapat melihat CV (non-tersembunyi) grafolog yang memeriksa mereka; grafolog yang menangani dapat melihat CV (non-tersembunyi) klien/kandidatnya; dua grafolog yang terhubung lewat supervisi saling melihat CV non-tersembunyi. Setiap akses oleh pihak lain tercatat di log audit.
- Perusahaan lain tidak dapat melihat data Anda.
- Kami **tidak menjual** data pribadi.

## 6. Pihak Ketiga

| Pihak | Data | Tujuan |
|---|---|---|
| DOKU | Nama, email, detail transaksi | Pembayaran |
| Penyedia email [PENYEDIA EMAIL] | Alamat email, isi notifikasi | Pengiriman email |
| Penyedia AI | Data teranonimkan | Analisis gabungan |
| Penyedia hosting [PENYEDIA HOSTING] | Seluruh data tersimpan terenkripsi sesuai konfigurasi | Infrastruktur |

## 7. Retensi

| Data | Disimpan |
|---|---|
| Log aktivitas | **90 hari**, lalu dihapus otomatis. |
| Log audit keamanan | [BATAS RETENSI AUDIT, mis. 2 tahun]. |
| Data transaksi | Sesuai kewajiban pembukuan/pajak. |
| Scan, skor, laporan | Selama akun aktif; dihapus/dianonimkan atas permintaan Anda, kecuali yang wajib disimpan hukum. |
| Akun kandidat | Mengikuti masa penugasan di perusahaan; dihapus saat perusahaan atau Anda memintanya. |
| Pesan kotak masuk | Selama akun aktif. |

## 8. Keamanan

Kata sandi di-hash; token sesi kedaluwarsa otomatis (maks. 30 hari); lalu lintas lewat
HTTPS; berkas scan di penyimpanan privat; pembatasan akses berbasis peran; pencatatan
audit atas akses laporan; log aktivitas hanya berisi metadata. **Bila terjadi kegagalan
pelindungan data pribadi**, kami memberitahu Anda dan lembaga berwenang secara tertulis
paling lambat **3 x 24 jam** sesuai UU PDP, memuat data yang terungkap, kapan dan
bagaimana terjadinya, serta langkah penanganan.

## 9. Hak Anda (dan cara menggunakannya)

**Swalayan di aplikasi** (menu Profil → *Data & Privasi*): (a) **unduh salinan data pribadi** (JSON) dan (b) **hapus akun**. Penghapusan = anonimisasi: nama, email, telepon, profil, CV (termasuk foto/dokumen), kotak masuk, sertifikat, dan scan tulisan Anda dihapus; akun tidak dapat dipakai lagi. Data transaksi dan laporan yang wajib kami simpan (pembukuan, riwayat pekerjaan grafolog) tetap ada **tanpa identitas pribadi**. Penghapusan ditunda bila masih ada pesanan diproses, atau (grafolog) penugasan/supervisi berjalan; saldo token yang tersisa hangus dan tidak dapat diuangkan. Akun kandidat dikelola perusahaan yang mendaftarkan Anda, dan akun perusahaan/administrator dihapus lewat administrator.

**Notifikasi email** dapat dimatikan di Profil (pesan penting seperti pembayaran, refund, verifikasi, dan pemberitahuan insiden data tetap dikirim). Kotak masuk di aplikasi selalu aktif.

**Persetujuan ulang**: setiap perubahan material pada Ketentuan/Kebijakan ini meminta persetujuan Anda kembali saat Anda masuk.

Hak lengkap menurut UU PDP

Sesuai UU PDP Anda berhak: mendapat informasi tentang pemrosesan; mengakses dan
memperoleh salinan data; melengkapi/memperbarui/memperbaiki data; mengakhiri
pemrosesan, menghapus, atau memusnahkan data; menarik persetujuan; mengajukan keberatan
atas keputusan yang semata-mata berdasarkan pemrosesan otomatis; menunda atau membatasi
pemrosesan; menggugat dan menerima ganti rugi atas pelanggaran; serta memperoleh data
dalam format yang lazim. Untuk hak yang tidak tersedia swalayan, kirim permintaan ke [EMAIL PRIVASI]; kami menanggapi paling
lambat 3 x 24 jam (konfirmasi) dan menyelesaikan dalam batas waktu yang wajar menurut
hukum. Penarikan persetujuan dapat membuat layanan tidak dapat dilanjutkan.

## 10. Anak

Layanan ditujukan untuk usia 17 tahun ke atas. Data anak hanya diproses dengan
persetujuan orang tua/wali.

## 11. Perubahan Kebijakan

Perubahan material kami beritahukan lewat email dan kotak masuk sebelum berlaku.

## 12. Kontak

Pejabat pelindungan data: [NAMA / JABATAN] · [EMAIL PRIVASI] · [NAMA PERUSAHAAN], [ALAMAT]
