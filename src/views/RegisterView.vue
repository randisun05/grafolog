<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'user',
})
const errors = ref({})
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    await auth.register(form.value)
    router.push({ name: 'dashboard' })
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Daftar</h1>
    <form @submit.prevent="submit">
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
        Daftar sebagai
        <select v-model="form.role">
          <option value="user">Individu (klien)</option>
          <option value="grafolog">Grafolog</option>
        </select>
      </label>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Memproses...' : 'Daftar' }}
      </button>
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
</style>
