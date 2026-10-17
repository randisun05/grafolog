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
    if (!auth.isPendingHr) router.push({ name: 'dashboard' })
  } finally {
    refreshing.value = false
  }
}

onMounted(refresh)
</script>

<template>
  <div class="co-status">
    <h1>Status Pendaftaran Perusahaan</h1>

    <template v-if="auth.user?.company?.status === 'rejected'">
      <p class="co-status__badge co-status__badge--rejected">Belum disetujui</p>
      <p>Pendaftaran {{ auth.user.company.name }} belum dapat kami setujui.</p>
      <p v-if="auth.user.company.review_note" class="co-status__note">
        <strong>Catatan admin:</strong> {{ auth.user.company.review_note }}
      </p>
      <p>Hubungi admin Guratan bila ingin mengajukan ulang dengan data yang diperbaiki.</p>
    </template>

    <template v-else>
      <p class="co-status__badge">Menunggu verifikasi</p>
      <p>
        Terima kasih, {{ auth.user?.name }}. Admin sedang memeriksa data
        <strong>{{ auth.user?.company?.name }}</strong
        >. Kami akan mengirim email ke <strong>{{ auth.user?.email }}</strong> begitu akun aktif.
      </p>
      <p>
        Setelah aktif Anda bisa membuat posisi, menambahkan kandidat, memilih grafolog, memesan
        asesmen, dan membandingkan hasil kandidat.
      </p>
    </template>

    <button type="button" class="btn" :disabled="refreshing" @click="refresh">
      {{ refreshing ? 'Memeriksa...' : 'Periksa status' }}
    </button>
  </div>
</template>

<style scoped>
.co-status {
  max-width: 560px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.co-status__badge {
  align-self: flex-start;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  background: var(--color-gold-soft);
  color: var(--color-text);
}
.co-status__badge--rejected {
  background: var(--color-seal-soft);
  color: var(--color-seal-dark);
}
.co-status__note {
  padding: 12px;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
}
.btn {
  align-self: flex-start;
}
</style>
