<!--
SOP Pembelian, Pembatalan & Refund - draf 2026-10-17 (Fase E hasil uji manual).
Bagian [DALAM KURUNG SIKU] adalah keputusan bisnis/hukum yang belum final dan
membuat halaman ditandai "Draf" otomatis di LegalView. Alur sistem yang
dijelaskan di bawah SUDAH dibangun: OrderService, OrderController::requestRefund,
Admin\RefundController.
-->

# Kebijakan Pembelian, Pembatalan & Pengembalian Dana

Berlaku untuk semua layanan Guratan: Master Graphology, layanan pendukung
(wawancara, konsultasi, konseling, program intervensi), dan pesanan perusahaan.

## 1. Pembelian

1. Pilih layanan di halaman **Pesan Layanan** (perusahaan: menu **Rekrutmen →
   Pesan Asesmen**). Harga yang berlaku adalah harga yang tertera saat pesanan
   dibuat, termasuk potongan kode promo yang valid saat itu.
2. Untuk layanan dengan analisis tulisan tangan, unggah **hasil scan** tulisan
   (kertas A4 utuh, 300 dpi, JPG/PNG, minimal 1920×1080 piksel, maks. 30 MB).
3. Bayar melalui mitra pembayaran DOKU. Pesanan berstatus **Diproses** setelah
   pembayaran terkonfirmasi; grafolog lalu ditugaskan (oleh admin, atau grafolog
   pilihan perusahaan untuk pesanan B2B).
4. Laporan grafologi terbit setelah grafolog menerbitkannya sendiri atau setelah
   divalidasi supervisor (bila grafolog memintanya). Layanan pendukung
   dijadwalkan oleh grafolog dan laporannya tersedia di **Riwayat**.
5. Estimasi waktu pengerjaan: [ISI - mis. laporan grafologi X hari kerja setelah
   scan lengkap diterima].

## 2. Pembatalan sebelum pembayaran

Pesanan yang belum dibayar dapat dibatalkan kapan saja dari halaman
**Riwayat** (perusahaan: **Rekrutmen → Pesan Asesmen → Riwayat pesanan**),
tanpa biaya.

## 3. Pengembalian dana (refund)

1. Refund dapat **diajukan** dari halaman Riwayat selama pesanan masih
   **Diproses** dan **belum ada hasil yang diserahkan** - laporan grafologi belum
   terbit dan belum ada layanan pendukung yang selesai.
2. Sertakan alasan pengajuan. Admin Guratan meninjau dan memberi keputusan
   beserta catatan dalam [ISI - mis. 3 hari kerja].
3. Bila disetujui, pesanan berstatus **Dikembalikan**, layanan yang belum
   selesai dibatalkan, dan dana dikembalikan ke metode pembayaran semula melalui
   DOKU dalam [ISI - estimasi hari kerja sesuai ketentuan DOKU/bank].
   Besaran refund: [ISI - penuh / dipotong biaya administrasi X%].
4. Setelah laporan terbit atau layanan selesai, refund tidak dapat diajukan
   lewat aplikasi. Keluhan kualitas ditangani melalui [ISI - kanal kontak], dan
   admin dapat memutuskan refund atas inisiatif sendiri.
5. Kegagalan teknis di sisi Guratan (mis. pembayaran sukses tetapi pesanan tidak
   terproses) selalu dikembalikan penuh: [KONFIRMASI].

## 4. Pesanan perusahaan (B2B)

Satu pesanan perusahaan mencakup beberapa kandidat dan dibayar sekali. Pesanan
yang belum dibayar dapat dibatalkan oleh HR. Refund untuk kandidat tertentu
diajukan melalui [ISI - kanal kontak akun perusahaan] dan diproses admin per
kandidat.

## 5. Layanan pendukung & jadwal

Perubahan jadwal sesi (wawancara, konsultasi, konseling) disepakati langsung
dengan grafolog yang ditugaskan. Ketidakhadiran tanpa pemberitahuan: [ISI -
kebijakan no-show].
