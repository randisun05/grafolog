<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'

// Pendaftaran grafolog lewat verifikasi data (2026-09-02) - beda dari
// RegisterView.vue: TIDAK langsung membuat akun/token, cuma mengirim
// pengajuan (biodata + dokumen bukti profesi) yang ditinjau administrator
// dulu lewat /admin/grafolog-applications. Lihat guratan-api/CLAUDE.md.
const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  catatan: '',
})
const documentFile = ref(null)
const errors = ref({})
const loading = ref(false)
const submitted = ref(false)
const submitError = ref('')

function onFileChange(e) {
  documentFile.value = e.target.files[0] ?? null
}

async function submit() {
  loading.value = true
  errors.value = {}
  submitError.value = ''
  try {
    const payload = new FormData()
    for (const [key, value] of Object.entries(form.value)) {
      payload.append(key, value)
    }
    if (documentFile.value) payload.append('document', documentFile.value)

    await api.post('/grafolog-applications', payload)
    submitted.value = true
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    submitError.value = e.response?.status === 422 ? '' : (e.response?.data?.message ?? 'Gagal mengirim pengajuan.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Daftar sebagai Grafolog</h1>

    <div v-if="submitted" class="grafolog-register__success">
      <p>
        Pengajuan Anda sudah kami terima. Tim kami akan meninjau biodata dan bukti profesi Anda - setelah
        disetujui administrator, Anda bisa masuk memakai email dan kata sandi yang baru saja didaftarkan.
      </p>
      <RouterLink to="/login" class="btn">Ke Halaman Masuk</RouterLink>
    </div>

    <form v-else @submit.prevent="submit">
      <p class="grafolog-register__intro">
        Isi biodata dan unggah bukti profesi Anda (sertifikat, kartu anggota asosiasi grafologi, atau dokumen
        pendukung lain). Akun baru aktif setelah ditinjau dan disetujui administrator.
      </p>

      <label>
        Nama
        <input v-model="form.name" type="text" required />
      </label>
      <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>

      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label>
        Kata Sandi
        <input v-model="form.password" type="password" required />
      </label>
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <label>
        Konfirmasi Kata Sandi
        <input v-model="form.password_confirmation" type="password" required />
      </label>

      <label>
        Nomor HP/WhatsApp (opsional)
        <input v-model="form.phone" type="text" />
      </label>
      <p v-if="errors.phone" class="error">{{ errors.phone[0] }}</p>

      <label>
        Pengalaman/Sertifikasi (opsional)
        <textarea v-model="form.catatan" rows="3" placeholder="Mis. sertifikasi dari lembaga X, N tahun pengalaman..."></textarea>
      </label>
      <p v-if="errors.catatan" class="error">{{ errors.catatan[0] }}</p>

      <label>
        Bukti Profesi (foto/PDF sertifikat, kartu anggota, atau dokumen lain - maks 5MB)
        <input type="file" accept=".jpg,.jpeg,.png,.pdf" required @change="onFileChange" />
      </label>
      <p v-if="errors.document" class="error">{{ errors.document[0] }}</p>

      <p v-if="submitError" class="error">{{ submitError }}</p>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Mengirim...' : 'Kirim Pengajuan' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.auth-form {
  max-width: 420px;
  width: 100%;
  margin: 0 auto;
  padding: 32px 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
}
.auth-form h1 {
  margin-bottom: 20px;
}
.grafolog-register__intro {
  font-size: 13.5px;
  color: var(--color-text-soft);
  margin-bottom: 18px;
  line-height: 1.6;
}
.grafolog-register__success p {
  font-size: 14px;
  color: var(--color-ink-soft);
  line-height: 1.7;
  margin-bottom: 18px;
}
label {
  display: block;
  margin-bottom: 14px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.error {
  font-size: 12px;
  margin: -8px 0 10px;
}
.btn--primary {
  width: 100%;
  padding: 11px;
}
</style>
