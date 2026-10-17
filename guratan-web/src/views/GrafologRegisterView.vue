<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  certificate_number: '',
  consent: false,
})
const certificate = ref(null)
const errors = ref({})
const loading = ref(false)

function onFile(e) {
  certificate.value = e.target.files[0] ?? null
}

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) =>
      fd.append(k, k === 'consent' ? (v ? '1' : '0') : v),
    )
    if (certificate.value) fd.append('certificate', certificate.value)
    await auth.registerGrafolog(fd)
    router.push({ name: 'grafolog-status' })
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {
      name: [e.response?.data?.message ?? 'Pendaftaran gagal.'],
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form auth-form--wide">
    <h1>Daftar sebagai Grafolog</h1>
    <p class="auth-form__hint">
      Akun baru diperiksa admin sebelum aktif. Siapkan sertifikat grafologi Anda (PDF/JPG/PNG, maks.
      10 MB).
    </p>
    <form @submit.prevent="submit">
      <label for="gr-name">Nama lengkap</label>
      <input id="gr-name" v-model="form.name" type="text" required />
      <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>

      <label for="gr-email">Email</label>
      <input id="gr-email" v-model="form.email" type="email" required />
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label for="gr-phone">Nomor HP / WhatsApp</label>
      <input id="gr-phone" v-model="form.phone" type="tel" required />
      <p v-if="errors.phone" class="error">{{ errors.phone[0] }}</p>

      <label for="gr-cert-no">Nomor sertifikat grafologi</label>
      <input id="gr-cert-no" v-model="form.certificate_number" type="text" required />
      <p v-if="errors.certificate_number" class="error">{{ errors.certificate_number[0] }}</p>

      <label for="gr-cert">File sertifikat</label>
      <input id="gr-cert" type="file" accept=".pdf,.jpg,.jpeg,.png" required @change="onFile" />
      <p v-if="errors.certificate" class="error">{{ errors.certificate[0] }}</p>

      <label for="gr-pass">Kata sandi</label>
      <input id="gr-pass" v-model="form.password" type="password" required />
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <label for="gr-pass2">Konfirmasi kata sandi</label>
      <input id="gr-pass2" v-model="form.password_confirmation" type="password" required />

      <label class="consent">
        <input v-model="form.consent" type="checkbox" required />
        <span>
          Saya menyetujui
          <RouterLink to="/privasi" target="_blank">Kebijakan Privasi</RouterLink> dan
          <RouterLink to="/syarat" target="_blank">Syarat Layanan</RouterLink>, serta menyatakan
          data sertifikat di atas benar.
        </span>
      </label>
      <p v-if="errors.consent" class="error">{{ errors.consent[0] }}</p>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Mengirim...' : 'Kirim Pendaftaran' }}
      </button>
    </form>
    <RouterLink to="/login" class="auth-form__link">Sudah punya akun? Masuk</RouterLink>
  </div>
</template>

<style scoped src="./auth-form.css"></style>
<style scoped>
.auth-form--wide {
  max-width: 440px;
}
input {
  margin: 4px 0 14px;
}
label {
  margin-bottom: 0;
}
.consent {
  display: flex;
  gap: 0.5rem;
  align-items: flex-start;
  font-size: 0.875rem;
  margin: 4px 0 14px;
}
.consent input {
  width: auto;
  margin: 0.2rem 0 0;
}
</style>
