<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

/** Penghalang persetujuan ulang ToS/Privasi - muncul bila versi persetujuan pengguna bukan yang berlaku. */
const auth = useAuthStore()
const router = useRouter()
const toast = useToast()
const checked = ref(false)
const saving = ref(false)
const open = computed(() => auth.isAuthenticated && auth.user?.needs_consent === true)

async function accept() {
  saving.value = true
  try {
    const { data } = await api.post('/me/accept-terms', { accept: true })
    auth.setUser({ ...auth.user, ...data.user })
    toast.push('Terima kasih, persetujuan Anda tercatat.', 'success')
  } catch {
    toast.push('Gagal menyimpan persetujuan.')
  } finally {
    saving.value = false
  }
}
async function decline() {
  await auth.logout()
  router.push({ name: 'landing' })
}
</script>

<template>
  <div v-if="open" class="cg" role="dialog" aria-modal="true" aria-labelledby="cg-title">
    <div class="cg__box">
      <h2 id="cg-title">Ketentuan & Kebijakan Privasi diperbarui</h2>
      <p>
        Kami memperbarui
        <a href="/syarat" target="_blank" rel="noopener">Ketentuan Layanan</a> dan
        <a href="/privasi" target="_blank" rel="noopener">Kebijakan Privasi</a> (pemrosesan data
        pribadi sesuai UU PDP: hak akses, ekspor, dan penghapusan data, pemrosesan analisis, serta
        retensi log). Mohon baca dan setujui untuk melanjutkan.
      </p>
      <label class="cg__check">
        <input v-model="checked" type="checkbox" />
        <span>Saya telah membaca dan menyetujui Ketentuan Layanan dan Kebijakan Privasi.</span>
      </label>
      <div class="cg__actions">
        <button
          type="button"
          class="btn btn--primary"
          :disabled="!checked || saving"
          @click="accept"
        >
          {{ saving ? 'Menyimpan...' : 'Setuju & Lanjutkan' }}
        </button>
        <button type="button" class="btn" @click="decline">Tidak setuju (keluar)</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cg {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgb(0 0 0 / 55%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.cg__box {
  background: var(--color-surface);
  color: var(--color-ink);
  border-radius: var(--radius-md);
  padding: 20px 22px;
  max-width: 520px;
  width: 100%;
  box-shadow: var(--shadow-card);
}
.cg__box h2 {
  margin-top: 0;
  font-size: 18px;
}
.cg__check {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 14px;
  margin: 12px 0;
}
.cg__check input {
  width: auto;
  margin-top: 3px;
}
.cg__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
