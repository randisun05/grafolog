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
    // Halaman ketentuan tetap tampil lengkap walau CMS gagal dimuat.
  }
})

const contactEmail = computed(() => content.value.legal_contact_email || content.value.support_email || '')
</script>

<template>
  <div class="legal-page">
    <h1>Ketentuan Layanan Guratan</h1>
    <p class="legal-page__updated">Terakhir diperbarui: 30 Agustus 2026</p>

    <section>
      <h2>1. Tentang Layanan</h2>
      <p>
        Guratan adalah platform analisis grafologi (ilmu membaca kepribadian dari tulisan tangan) berbasis web,
        tersedia dalam dua tier:
      </p>
      <ul>
        <li><strong>Comprehensive</strong> — grafolog bersertifikat mengukur tulisan tangan fisik Anda secara manual dan mengisi skor 40 aspek.</li>
        <li><strong>Master</strong> — sama seperti Comprehensive, ditambah sesi konsultasi langsung dengan grafolog.</li>
      </ul>
    </section>

    <section>
      <h2>2. Bukan Alat Diagnosis Klinis</h2>
      <p>
        <strong>Guratan adalah alat insight reflektif untuk eksplorasi kepribadian, BUKAN alat diagnosis
        psikologis atau klinis.</strong> Grafologi tidak memiliki dasar ilmiah sekuat psikometri standar
        (misalnya Big Five). Laporan yang Anda terima:
      </p>
      <ul>
        <li>Tidak boleh dijadikan satu-satunya dasar keputusan penting (medis, hukum, finansial, atau hubungan kerja/rekrutmen).</li>
        <li>Tidak menggantikan konsultasi dengan psikolog atau profesional kesehatan mental berlisensi.</li>
        <li>Disusun dari basis pengetahuan grafologi (referensi Sindrom/Aspek/Indikator) yang ditafsirkan oleh grafolog bersertifikat.</li>
      </ul>
    </section>

    <section>
      <h2>3. Batasan Tanggung Jawab</h2>
      <p>
        Sepanjang diizinkan hukum yang berlaku, penyelenggara Guratan tidak bertanggung jawab atas keputusan yang
        Anda ambil berdasarkan laporan Guratan, termasuk namun tidak terbatas pada keputusan karier, hubungan,
        atau kesehatan mental. Jika Anda mengalami masalah kesehatan mental, segera hubungi profesional
        berlisensi atau layanan darurat setempat.
      </p>
    </section>

    <section>
      <h2>4. Akun Pengguna</h2>
      <p>
        Anda bertanggung jawab menjaga kerahasiaan kata sandi akun Anda. Peran "grafolog" diberikan kepada
        individu yang bekerja sama dengan penyelenggara sebagai grafolog bersertifikat.
      </p>
    </section>

    <section>
      <h2>5. Pembayaran &amp; Pengembalian Dana</h2>
      <p>
        Pembayaran untuk tier Comprehensive/Master diproses melalui mitra payment gateway pihak ketiga, DOKU.
        Harga yang berlaku adalah harga yang ditampilkan di aplikasi saat transaksi dilakukan. Permintaan
        pengembalian dana dievaluasi kasus per kasus — hubungi
        <RouterLink to="/bantuan">dukungan pelanggan</RouterLink> kami.
      </p>
    </section>

    <section>
      <h2>6. Kekayaan Intelektual</h2>
      <p>
        Basis pengetahuan grafologi (Sindrom, Aspek, Indikator, dan narasi terkait) adalah milik penyelenggara
        layanan. Laporan yang dihasilkan untuk Anda adalah milik Anda untuk penggunaan pribadi.
      </p>
    </section>

    <section>
      <h2>7. Perubahan Ketentuan</h2>
      <p>
        Kami dapat memperbarui Ketentuan Layanan ini dari waktu ke waktu. Perubahan material akan diberitahukan
        melalui email atau notifikasi di aplikasi.
      </p>
    </section>

    <section>
      <h2>8. Hukum yang Berlaku</h2>
      <p>Ketentuan ini diatur oleh hukum Republik Indonesia.</p>
    </section>

    <section v-if="content.legal_entity_name || contactEmail">
      <h2>9. Kontak</h2>
      <p v-if="content.legal_entity_name">{{ content.legal_entity_name }}</p>
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
