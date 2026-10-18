<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatTanggal } from '@/lib/format'
import { useToast } from '@/composables/useToast'

/** Kirim pesan massal ke kotak masuk (opsional + email) per peran. */
const toast = useToast()
const ROLES = [
  { value: 'user', label: 'Klien' },
  { value: 'grafolog', label: 'Grafolog' },
  { value: 'hr', label: 'HR / Perusahaan' },
  { value: 'administrator', label: 'Admin' },
]
const form = ref({ title: '', body: '', target_roles: [], send_email: false, is_critical: false })
const history = ref([])
const sending = ref(false)

async function load() {
  history.value = (await api.get('/admin/broadcasts')).data
}

async function send() {
  if (!window.confirm('Kirim pesan ini sekarang? Tidak bisa ditarik kembali.')) return
  sending.value = true
  try {
    const { data } = await api.post('/admin/broadcasts', form.value)
    toast.push(`Terkirim ke ${data.recipients} pengguna.`)
    form.value = { title: '', body: '', target_roles: [], send_email: false, is_critical: false }
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengirim.')
  } finally {
    sending.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="bc">
    <h1>Broadcast</h1>
    <form class="bc__form" @submit.prevent="send">
      <label
        >Judul
        <input v-model="form.title" required maxlength="150" />
      </label>
      <label
        >Isi pesan
        <textarea v-model="form.body" required rows="4" maxlength="3000" />
      </label>
      <fieldset>
        <legend>Penerima (kosong = semua pengguna)</legend>
        <label v-for="r in ROLES" :key="r.value" class="bc__check">
          <input v-model="form.target_roles" type="checkbox" :value="r.value" /> {{ r.label }}
        </label>
      </fieldset>
      <label class="bc__check">
        <input v-model="form.send_email" type="checkbox" /> Kirim juga lewat email (kandidat B2B
        tetap hanya kotak masuk)
      </label>
      <label class="bc__check">
        <input v-model="form.is_critical" type="checkbox" /> Pemberitahuan penting / insiden data
        (selalu dikirim email, walau pengguna menonaktifkan email notifikasi)
      </label>
      <button class="btn btn--primary" :disabled="sending">
        {{ sending ? 'Mengirim...' : 'Kirim' }}
      </button>
    </form>

    <h2>Riwayat</h2>
    <p v-if="!history.length" class="bc__hint">Belum ada broadcast.</p>
    <ul v-else class="bc__history">
      <li v-for="b in history" :key="b.id">
        <strong>{{ b.title }}</strong>
        <span class="bc__hint">
          {{ formatTanggal(b.created_at, true) }} ·
          {{ b.target_roles?.length ? b.target_roles.join(', ') : 'semua' }}
          {{ b.send_email ? '· +email' : '' }}
        </span>
        <p>{{ b.body }}</p>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.bc {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 720px;
}
.bc__form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.bc__form label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
}
.bc__form fieldset {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  display: flex;
  flex-wrap: wrap;
  gap: 6px 16px;
}
.bc__form .bc__check {
  flex-direction: row;
  align-items: center;
  gap: 6px;
}
.bc__form .btn {
  align-self: flex-start;
}
.bc__hint {
  color: var(--color-text-soft);
  font-size: 13px;
  margin-left: 8px;
}
.bc__history {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.bc__history li {
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.bc__history p {
  margin: 4px 0 0;
  white-space: pre-line;
  font-size: 14px;
}
</style>
