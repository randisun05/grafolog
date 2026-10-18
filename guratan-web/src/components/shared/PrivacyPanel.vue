<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

/** Hak data pribadi (UU PDP): unduh salinan data, hapus akun. */
const auth = useAuthStore()
const router = useRouter()
const toast = useToast()
const exporting = ref(false)
const showDelete = ref(false)
const password = ref('')
const confirmText = ref('')
const forfeit = ref(false)
const deleting = ref(false)
const error = ref('')

async function exportData() {
  exporting.value = true
  try {
    const res = await api.get('/me/export', { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `data-guratan-${auth.user.id}.json`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.push('Gagal mengunduh data. Coba lagi nanti.')
  } finally {
    exporting.value = false
  }
}

async function deleteAccount() {
  error.value = ''
  deleting.value = true
  try {
    await api.post('/me/delete', {
      password: password.value,
      confirm: confirmText.value,
      forfeit_tokens: forfeit.value || undefined,
    })
    await auth.logout().catch(() => {})
    toast.push('Akun Anda telah dihapus.', 'success')
    router.push({ name: 'landing' })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal menghapus akun.'
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <section class="privacy">
    <h2>Data & Privasi</h2>
    <p class="privacy__hint">
      Anda berhak mengunduh salinan data pribadi dan meminta penghapusan akun. Lihat
      <RouterLink to="/privasi">Kebijakan Privasi</RouterLink>.
    </p>
    <button type="button" class="btn" :disabled="exporting" @click="exportData">
      {{ exporting ? 'Menyiapkan...' : 'Unduh data saya (JSON)' }}
    </button>

    <div class="privacy__danger">
      <button v-if="!showDelete" type="button" class="btn" @click="showDelete = true">
        Hapus akun saya
      </button>
      <form v-else @submit.prevent="deleteAccount">
        <p>
          Penghapusan bersifat permanen: identitas, CV, kotak masuk, dan scan tulisan Anda dihapus.
          Data transaksi dan laporan yang wajib disimpan tetap ada tanpa identitas pribadi.
        </p>
        <label for="del-pass">Kata sandi</label>
        <input
          id="del-pass"
          v-model="password"
          type="password"
          required
          autocomplete="current-password"
        />
        <label for="del-confirm">Ketik HAPUS untuk konfirmasi</label>
        <input id="del-confirm" v-model="confirmText" type="text" required />
        <label v-if="auth.isGrafolog" class="privacy__check">
          <input v-model="forfeit" type="checkbox" /> Saya paham saldo token yang tersisa akan
          hangus (tidak dapat diuangkan).
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="btn btn--primary" :disabled="deleting || confirmText !== 'HAPUS'">
          {{ deleting ? 'Menghapus...' : 'Hapus akun permanen' }}
        </button>
        <button type="button" class="btn" @click="showDelete = false">Batal</button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.privacy {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: flex-start;
}
.privacy__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.privacy__danger {
  border-top: 1px solid var(--color-border);
  padding-top: 12px;
  width: 100%;
}
.privacy__danger form {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 420px;
}
.privacy__check {
  display: flex;
  gap: 6px;
  font-size: 13px;
}
.privacy__check input {
  width: auto;
}
</style>
