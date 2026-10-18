<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import PasswordChangeForm from '@/components/shared/PasswordChangeForm.vue'

/** Wajib setelah login dengan password sementara (kandidat yang dibuat perusahaan). */
const auth = useAuthStore()
const router = useRouter()
</script>

<template>
  <div class="cp">
    <h1>Buat kata sandi baru</h1>
    <p class="cp__hint">
      Halo {{ auth.user?.name }}. Anda masuk dengan kata sandi sementara dari perusahaan. Ganti
      dengan kata sandi Anda sendiri sebelum melanjutkan - perusahaan tidak akan mengetahuinya.
    </p>
    <PasswordChangeForm @changed="router.push({ name: 'dashboard' })" />
  </div>
</template>

<style scoped>
.cp {
  max-width: 480px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.cp__hint {
  color: var(--color-text-soft);
  font-size: 14px;
  margin: 0;
}
</style>
