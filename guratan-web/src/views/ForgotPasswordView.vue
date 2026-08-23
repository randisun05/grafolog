<script setup>
import { ref } from 'vue'
import api from '@/lib/api'

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await api.post('/auth/forgot-password', { email: email.value })
    // Backend selalu balas pesan yang sama baik email terdaftar atau tidak
    // (mencegah orang mengecek email siapa yang punya akun) - frontend
    // cuma perlu tampilkan "terkirim", tidak perlu tahu hasil sebenarnya.
    sent.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal mengirim tautan reset.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Lupa Kata Sandi</h1>

    <template v-if="sent">
      <p>Kalau email <strong>{{ email }}</strong> terdaftar, tautan reset kata sandi sudah dikirim. Cek kotak masuk Anda.</p>
      <RouterLink to="/login" class="auth-form__link">Kembali ke halaman masuk</RouterLink>
    </template>

    <form v-else @submit.prevent="submit">
      <p class="auth-form__hint">Masukkan email akun Anda, kami kirim tautan untuk reset kata sandi.</p>
      <label>
        Email
        <input v-model="email" type="email" required />
      </label>
      <p v-if="error" class="error">{{ error }}</p>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Mengirim...' : 'Kirim Tautan Reset' }}
      </button>
      <RouterLink to="/login" class="auth-form__link">Kembali ke halaman masuk</RouterLink>
    </form>
  </div>
</template>

<style scoped>
.auth-form {
  max-width: 360px;
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
.auth-form__hint {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 16px;
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
.auth-form__link {
  display: block;
  text-align: center;
  margin-top: 14px;
  font-size: 13px;
}
</style>
