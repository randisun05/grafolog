<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

const route = useRoute()
const router = useRouter()

const email = ref(route.query.email ?? '')
const token = ref(route.query.token ?? '')
const password = ref('')
const passwordConfirmation = ref('')
const errors = ref({})
const loading = ref(false)
const done = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    await api.post('/auth/reset-password', {
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    done.value = true
    setTimeout(() => router.push({ name: 'login' }), 2000)
  } catch (e) {
    errors.value = e.response?.data?.errors ?? { password: [e.response?.data?.message ?? 'Gagal mereset kata sandi.'] }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Reset Kata Sandi</h1>

    <p v-if="done">Kata sandi berhasil direset. Mengarahkan ke halaman masuk...</p>

    <form v-else @submit.prevent="submit">
      <label>
        Kata Sandi Baru
        <input v-model="password" type="password" required />
      </label>
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <label>
        Ulangi Kata Sandi Baru
        <input v-model="passwordConfirmation" type="password" required />
      </label>

      <button type="submit" class="btn btn--primary" :disabled="loading || !email || !token">
        {{ loading ? 'Menyimpan...' : 'Reset Kata Sandi' }}
      </button>
      <p v-if="!email || !token" class="error">Tautan tidak lengkap - minta tautan reset baru.</p>
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
