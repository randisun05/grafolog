<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const form = ref({
  token: route.query.token ?? '',
  email: route.query.email ?? '',
  password: '',
  password_confirmation: '',
})
const errors = ref({})
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    const { data } = await api.post('/auth/reset-password', form.value)
    toast.push(data.message, 'success')
    router.push({ name: 'login' })
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {
      token: [e.response?.data?.message ?? 'Gagal mengubah kata sandi.'],
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form">
    <h1>Buat Kata Sandi Baru</h1>
    <form @submit.prevent="submit">
      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label>
        Kata Sandi Baru
        <input v-model="form.password" type="password" required autocomplete="new-password" />
      </label>
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <label>
        Konfirmasi Kata Sandi Baru
        <input
          v-model="form.password_confirmation"
          type="password"
          required
          autocomplete="new-password"
        />
      </label>
      <p v-if="errors.token" class="error">{{ errors.token[0] }}</p>

      <button type="submit" class="btn btn--primary" :disabled="loading || !form.token">
        {{ loading ? 'Menyimpan...' : 'Simpan Kata Sandi' }}
      </button>
    </form>
    <RouterLink to="/lupa-password" class="auth-form__link">Minta tautan baru</RouterLink>
  </div>
</template>

<style scoped src="./auth-form.css"></style>
