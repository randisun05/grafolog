<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ email: '', password: '' })
const errors = ref({})
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    await auth.login(form.value)
    router.push(route.query.redirect || { name: 'dashboard' })
  } catch (e) {
    errors.value = e.response?.data?.errors ?? { email: [e.response?.data?.message ?? 'Gagal masuk.'] }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Masuk</h1>
    <form @submit.prevent="submit">
      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label>
        Kata Sandi
        <input v-model="form.password" type="password" required />
      </label>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Memproses...' : 'Masuk' }}
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
