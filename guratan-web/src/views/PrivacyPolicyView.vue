<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'

const content = ref({})

onMounted(async () => {
  try {
    const { data } = await api.get('/content')
    content.value = data
  } catch {
    // Halaman kebijakan tetap tampil lengkap walau CMS gagal dimuat - cuma
    // baris kontak legal yang jadi kosong (sudah aman disembunyikan di bawah).
  }
})

const contactEmail = computed(() => content.value.legal_contact_email || content.value.support_email || '')
</script>

<template>
  <div class="legal-page">
    <h1>Kebijakan Privasi Guratan</h1>
    <p class="legal-page__updated">Terakhir diperbarui: 30 Agustus 2026</p>

    <section>
      <h2>1. Data yang Kami Kumpulkan</h2>
      <ul>
        <li>Data akun: nama, email, kata sandi (di-hash, tidak pernah disimpan dalam bentuk asli).</li>
        <li>Skor grafologi 40 aspek yang diinput grafolog bersertifikat berdasarkan pengukuran manual tulisan tangan fisik Anda.</li>
        <li>
          Data pembayaran (invoice, jumlah, status transaksi) — detail kartu/rekening/e-wallet Anda
          <strong>tidak pernah</strong> disimpan atau diproses oleh server kami, diproses langsung oleh mitra
          payment gateway kami, DOKU.
        </li>
        <li>Log akses: setiap kali laporan kepribadian Anda dibaca, dicatat untuk audit keamanan (waktu, alamat IP, hasil akses).</li>
      </ul>
    </section>

    <section>
      <h2>2. Kenapa Data Ini Sensitif</h2>
      <p>
        Laporan kepribadian yang dihasilkan Guratan berasal dari analisis grafologi terhadap tulisan tangan Anda.
        Kami memperlakukan ini sebagai data pribadi yang bersifat sensitif, meskipun grafologi sendiri bukan alat
        diagnosis klinis berbasis psikometri standar — lihat
        <RouterLink to="/ketentuan-layanan">Ketentuan Layanan</RouterLink> pasal soal batasan keakuratan.
      </p>
    </section>

    <section>
      <h2>3. Untuk Apa Data Digunakan</h2>
      <ul>
        <li>Menghasilkan dan menampilkan laporan kepribadian Anda.</li>
        <li>Memproses pembayaran untuk tier berbayar (Comprehensive/Master).</li>
        <li>Mengirim notifikasi email saat laporan Anda selesai dibuat.</li>
        <li>Audit keamanan internal (mendeteksi akses tidak sah ke laporan sensitif).</li>
        <li>Narasi laporan disusun dari basis pengetahuan grafologi yang sudah ada — bukan dikarang bebas dari data pribadi Anda.</li>
      </ul>
    </section>

    <section>
      <h2>4. Bagaimana Data Dilindungi</h2>
      <ul>
        <li>Kata sandi di-hash, tidak pernah disimpan/ditampilkan dalam bentuk asli.</li>
        <li>Autentikasi berbasis token, dikirim lewat koneksi terenkripsi (HTTPS) di production.</li>
        <li>Setiap pembacaan laporan kepribadian tercatat di log audit internal, terlepas dari apakah akses tersebut berhasil atau ditolak.</li>
        <li>Laporan hanya bisa diakses oleh pemilik akun terkait dan grafolog yang menangani kasus tersebut.</li>
      </ul>
    </section>

    <section>
      <h2>5. Berapa Lama Data Disimpan</h2>
      <p>
        Data disimpan selama akun Anda aktif. Setelah Anda meminta penghapusan akun, data pribadi dihapus dalam
        30 hari kerja, kecuali data yang wajib disimpan untuk kepatuhan hukum (mis. catatan transaksi pembayaran).
      </p>
    </section>

    <section>
      <h2>6. Berbagi Data ke Pihak Ketiga</h2>
      <ul>
        <li>
          <strong>DOKU</strong> (payment gateway): menerima nama, email, dan detail transaksi untuk memproses
          pembayaran tier berbayar. Tidak menerima skor kepribadian Anda.
        </li>
        <li>
          Untuk laporan yang dibuat lewat perusahaan (rekrutmen B2B): hasil laporan dapat diakses oleh tim HR
          perusahaan yang mengundang Anda mengikuti proses tersebut.
        </li>
        <li>
          <strong>Google Analytics</strong>: kami menggunakan layanan ini untuk memahami pola kunjungan ke
          situs (halaman yang dibuka, durasi kunjungan) - data yang dikirim bersifat statistik penggunaan
          situs, <strong>bukan</strong> skor kepribadian atau isi laporan Anda.
        </li>
        <li>Kami <strong>tidak menjual</strong> data pribadi Anda ke pihak mana pun.</li>
      </ul>
    </section>

    <section>
      <h2>7. Hak Anda</h2>
      <p>Sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi, Anda berhak untuk:</p>
      <ul>
        <li>Meminta salinan data pribadi yang kami simpan tentang Anda.</li>
        <li>Meminta koreksi data yang tidak akurat.</li>
        <li>Meminta penghapusan akun dan data terkait (dengan pengecualian data yang wajib disimpan untuk kepatuhan hukum).</li>
        <li>Menarik persetujuan pemrosesan data (dapat berdampak pada kemampuan kami memberikan layanan).</li>
      </ul>
      <p>
        Hubungi kami<span v-if="contactEmail"> di <strong>{{ contactEmail }}</strong></span>
        lewat <RouterLink to="/bantuan">halaman Bantuan</RouterLink> untuk permintaan terkait hak-hak di atas.
      </p>
    </section>

    <section v-if="content.legal_entity_name">
      <h2>8. Kontak</h2>
      <p>{{ content.legal_entity_name }}</p>
      <p v-if="contactEmail">{{ contactEmail }}</p>
    </section>
  </div>
</template>

<style scoped>
.legal-page {
  max-width: 720px;
}
.legal-page__updated {
  color: var(--color-text-soft);
  font-size: 13px;
  margin-bottom: 24px;
}
.legal-page h2 {
  font-size: 17px;
  margin: 28px 0 10px;
}
.legal-page p,
.legal-page li {
  font-size: 14px;
  line-height: 1.7;
  color: var(--color-ink-soft);
}
.legal-page ul {
  padding-left: 20px;
  margin: 8px 0;
}
.legal-page li {
  margin-bottom: 6px;
}
.legal-page a {
  color: var(--color-seal);
}
</style>
