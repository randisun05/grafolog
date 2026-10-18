<script setup>
import { ref } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const emit = defineEmits(['changed'])
const auth = useAuthStore()
const toast = useToast()
const form = ref({ current_password: '', password: '', password_confirmation: '' })
const errors = ref({})
const saving = ref(false)

async function submit() {
  saving.value = true
  errors.value = {}
  try {
    const { data } = await api.put('/auth/change-password', form.value)
    auth.setUser(data.user)
    form.value = { current_password: '', password: '', password_confirmation: '' }
    toast.push('Kata sandi diganti.', 'success')
    emit('changed')
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    if (!Object.keys(errors.value).length)
      toast.push(e.response?.data?.message ?? 'Gagal mengganti kata sandi.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="pcf" @submit.prevent="submit">
    <label for="pcf-cur">Kata sandi saat ini</label>
    <input
      id="pcf-cur"
      v-model="form.current_password"
      type="password"
      required
      autocomplete="current-password"
    />
    <p v-if="errors.current_password" class="error">{{ errors.current_password[0] }}</p>
    <label for="pcf-new">Kata sandi baru (min. 8, huruf & angka)</label>
    <input
      id="pcf-new"
      v-model="form.password"
      type="password"
      required
      autocomplete="new-password"
    />
    <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>
    <label for="pcf-new2">Ulangi kata sandi baru</label>
    <input
      id="pcf-new2"
      v-model="form.password_confirmation"
      type="password"
      required
      autocomplete="new-password"
    />
    <button type="submit" class="btn btn--primary" :disabled="saving">
      {{ saving ? 'Menyimpan...' : 'Ganti kata sandi' }}
    </button>
  </form>
</template>

<style scoped>
.pcf {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 380px;
}
.pcf label {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-top: 6px;
}
.pcf .btn {
  align-self: flex-start;
  margin-top: 10px;
}
</style>
