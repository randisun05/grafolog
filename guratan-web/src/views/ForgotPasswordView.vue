<script setup>
import { ref } from 'vue'
import api from '@/lib/api'

const email = ref('')
const message = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  message.value = ''
  try {
    const { data } = await api.post('/auth/forgot-password', { email: email.value })
    message.value = data.message
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal mengirim tautan. Coba lagi nanti.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Lupa Kata Sandi</h1>
    <p class="auth-form__hint">
      Masukkan email akun Anda, kami kirimkan tautan untuk membuat kata sandi baru.
    </p>
    <form @submit.prevent="submit">
      <label>
        Email
        <input v-model="email" type="email" required />
      </label>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="message" class="auth-form__success">{{ message }}</p>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Mengirim...' : 'Kirim Tautan' }}
      </button>
    </form>
    <RouterLink to="/login" class="auth-form__link">Kembali ke halaman masuk</RouterLink>
  </div>
</template>

<style scoped src="./auth-form.css"></style>
