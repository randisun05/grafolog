<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const refreshing = ref(false)

async function refresh() {
  refreshing.value = true
  try {
    await auth.fetchMe()
    if (auth.isGrafolog) router.push({ name: 'dashboard' })
  } finally {
    refreshing.value = false
  }
}

onMounted(refresh)
</script>

<template>
  <div class="gr-status">
    <h1>Status Pendaftaran Grafolog</h1>

    <template v-if="auth.user?.grafolog_status === 'rejected'">
      <p class="gr-status__badge gr-status__badge--rejected">Belum disetujui</p>
      <p>Pendaftaran Anda belum dapat kami setujui.</p>
      <p v-if="auth.user?.grafolog_review_note" class="gr-status__note">
        <strong>Catatan admin:</strong> {{ auth.user.grafolog_review_note }}
      </p>
      <p>Hubungi admin Guratan bila ingin mengajukan ulang dengan dokumen yang diperbaiki.</p>
    </template>

    <template v-else>
      <p class="gr-status__badge">Menunggu verifikasi</p>
      <p>
        Terima kasih, {{ auth.user?.name }}. Admin sedang memeriksa sertifikat Anda. Kami akan
        mengirim email ke <strong>{{ auth.user?.email }}</strong> begitu akun aktif.
      </p>
      <p>
        Selama menunggu, Anda belum bisa mendaftarkan klien, menerima penugasan, atau mengisi
        worksheet.
      </p>
    </template>

    <button type="button" class="btn" :disabled="refreshing" @click="refresh">
      {{ refreshing ? 'Memeriksa...' : 'Periksa status' }}
    </button>
  </div>
</template>

<style scoped>
.gr-status {
  max-width: 560px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.gr-status__badge {
  align-self: flex-start;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  background: var(--color-gold-soft);
  color: var(--color-text);
}
.gr-status__badge--rejected {
  background: var(--color-seal-soft);
  color: var(--color-seal-dark);
}
.gr-status__note {
  padding: 12px;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
}
.btn {
  align-self: flex-start;
}
</style>
