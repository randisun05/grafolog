# Guratan

SaaS analisis grafologi (ilmu membaca kepribadian dari tulisan tangan)
berbahasa Indonesia. User mengunggah/mengirim foto tulisan tangan, sistem
menghasilkan laporan kepribadian. Dua repo di folder ini: `guratan-api`
(Laravel backend) dan `guratan-web` (Vue 3 frontend) — **detail teknis ada di
`guratan-api/CLAUDE.md` dan `guratan-web/CLAUDE.md`, verifikasi ke situ untuk
apa pun yang code-shaped, jangan asumsi dari file ini.** File ini adalah
konteks produk/bisnis, ditulis 2026-07-26.

**Riwayat kerja & keputusan sesi-sesi sebelumnya** (snapshot memori Claude,
dibawa serta supaya sesi baru di clone manapun langsung punya konteks penuh
tanpa baca ulang semua kode dari nol) ada di `.claude/memory/MEMORY.md` —
baca itu duluan untuk tahu apa yang sudah selesai, keputusan yang sudah
dikunci, dan yang sengaja ditunda. Isinya snapshot per-tanggal (bukan live
state) — tetap verifikasi klaim spesifik ke kode/CLAUDE.md sub-repo sebelum
dipakai sebagai fakta.

## Masalah yang diselesaikan

Psikotes konvensional lambat, mahal, dan butuh psikolog per sesi. Grafologi
digital menawarkan alternatif lebih cepat & murah untuk insight kepribadian —
baik individu yang penasaran soal dirinya sendiri, maupun perusahaan yang
butuh alat bantu screening SDM.

**Catatan kredibilitas (penting, memengaruhi banyak keputusan desain):**
grafologi tidak punya dasar ilmiah sekuat psikometri standar (Big Five, dsb).
Produk ini diposisikan sebagai *insight tool reflektif*, BUKAN alat diagnosis
klinis.

## Target pasar

- **B2C** (individu) — insight personal, pengalaman interaktif & menyenangkan.
- **B2B** (perusahaan) — bantuan rekrutmen/penempatan SDM. Fase pasca-MVP,
  belum jadi fokus pembangunan saat ini.

## Model bisnis — 3 tier

| Tier | Harga | Cara kerja |
|---|---|---|
| Rapid | Gratis | Upload foto → computer vision otomatis → hasil instan. CV belum dibangun (pasca-MVP); saat ini skor placeholder acak (lihat `guratan-api/CLAUDE.md`). |
| Comprehensive | Berbayar (~Rp49rb/laporan) | Grafolog bersertifikat mengukur tulisan tangan manual (kaliper, pakem grafologi ilmiah), input skor 1-10 untuk 40 aspek lewat Portal Grafolog. |
| Master | Berbayar, premium | Sama seperti Comprehensive + sesi konsultasi langsung dengan grafolog. |

**Kenapa Rapid = AI tapi Comprehensive/Master = manual grafolog:** keputusan
sadar — tier berbayar tinggi (dipakai untuk keputusan penting) mengandalkan
penilaian manusia bersertifikat, lebih kredibel & lebih defensif secara
hukum dibanding klaim akurasi AI murni.

## Fondasi ilmiah — bukan karangan AI

Seluruh skor & narasi laporan berasal dari knowledge base nyata milik user
(dikonversi dari file Excel referensi grafologi profesional):

- **8 Sindrom** (kategori besar: Driving Forces, Intellect, Functional
  Ability, Interpersonal Relations, Productivity, Stress Vulnerability,
  Defenses, Problem Areas)
- **40 Aspek** (trait spesifik di bawah tiap sindrom)
- **704 Indikator** (ciri fisik tulisan tangan spesifik, bukti tiap aspek)
- **37 variabel ukur grafometrik** (margin, spasi, kemiringan, tekanan, dst)
  dengan kategori rentang presisi (mm/rasio) — acuan pengukuran manual
  grafolog

LLM di sistem ini hanya merangkai/menerjemahkan narasi yang sudah ada di
knowledge base — TIDAK pernah "mengarang" interpretasi psikologi dari nol
(lihat `NarasiCacheService` di `guratan-api/CLAUDE.md`).

## Status pengembangan (ringkas — detail penuh di CLAUDE.md sub-repo)

MVP inti (alur Comprehensive/Master) sudah dibangun & tervalidasi
end-to-end: register → grafolog login → cari klien → isi form 40 skor →
laporan ter-generate dari cache → lihat laporan → unduh PDF → email
notifikasi (kode terkirim & terqueue benar, tapi `MAIL_MAILER=log` — belum
tersambung SMTP nyata).

### Yang SENGAJA belum dibangun (bukan lupa — keputusan)

- Computer vision untuk Rapid tier.
- Kalimat penghubung antar-aspek dalam satu laporan (butuh LLM per-laporan,
  ditunda sampai ada data pemakaian nyata).
- Dashboard B2B, batch processing, culture-fit matching, marketplace
  psikolog.
- Payment gateway, Privacy Policy/ToS final, deployment production.
- Terjemahan Bahasa Indonesia untuk narasi (masih Bahasa Inggris di
  knowledge base).

## Prinsip yang memandu semua keputusan teknis

1. Jangan klaim "akurasi ilmiah" berlebihan — selalu framing sebagai insight
   reflektif, terutama di konteks B2B/rekrutmen.
2. Data yang diproses = data psikologis sensitif → keamanan & audit log
   bukan opsional.
3. LLM dipakai seminimal & se-defensif mungkin (cache, bukan panggilan live
   per-user) — demi biaya DAN privasi.
4. MVP dulu, jangan bangun fitur yang belum perlu (CV, B2B dashboard, dst).
