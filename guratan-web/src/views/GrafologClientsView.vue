<script setup>
// Klien Saya (grafolog): daftarkan klien yang dibawa sendiri + daftar klien bawaan.
// Klien menerima email undangan untuk mengatur kata sandinya; pesanannya otomatis
// ditugaskan ke grafolog ini begitu lunas.
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

const toast = useToast()

const clients = ref([])
const loading = ref(true)
const loadError = ref('')

const form = ref({ name: '', email: '', phone: '', consent: false })
const saving = ref(false)
const formError = ref('')
const invitation = ref(null)
const resendingId = ref(null)

async function load() {
  try {
    clients.value = (await api.get('/clients')).data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat daftar klien.'
  } finally {
    loading.value = false
  }
}

async function register() {
  formError.value = ''
  invitation.value = null
  saving.value = true
  try {
    const { data } = await api.post('/clients', {
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone || undefined,
      consent_confirmed: form.value.consent,
    })
    // invitation_url hanya dikirim backend di env lokal (MAIL_MAILER=log).
    invitation.value = { email: data.email, url: data.invitation_url }
    form.value = { name: '', email: '', phone: '', consent: false }
    toast.push('Klien terdaftar, undangan email dikirim.', 'success')
    await load()
  } catch (e) {
    const errors = e.response?.data?.errors
    formError.value =
      (errors && Object.values(errors).flat()[0]) ??
      e.response?.data?.message ??
      'Gagal mendaftarkan klien.'
  } finally {
    saving.value = false
  }
}

async function resend(client) {
  resendingId.value = client.id
  try {
    const { data } = await api.post(`/clients/${client.id}/invite`)
    invitation.value = { email: client.email, url: data.invitation_url }
    toast.push('Undangan dikirim ulang.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengirim ulang undangan.')
  } finally {
    resendingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="grafolog-clients">
    <h1>Klien Saya</h1>
    <p class="grafolog-clients__note">
      Daftarkan klien yang Anda bawa. Sistem mengirim email undangan agar klien mengatur kata
      sandinya sendiri, lalu memesan laporan. Pesanan mereka otomatis ditugaskan ke Anda setelah
      lunas — muncul di "Ditugaskan ke Saya".
    </p>

    <section class="grafolog-clients__card">
      <h2>Daftarkan Klien Baru</h2>
      <form @submit.prevent="register">
        <div class="grafolog-clients__row">
          <label>
            Nama
            <input v-model="form.name" type="text" required maxlength="255" />
          </label>
          <label>
            Email
            <input v-model="form.email" type="email" required maxlength="255" />
          </label>
          <label>
            Telepon (opsional)
            <input v-model="form.phone" type="tel" maxlength="30" />
          </label>
        </div>
        <label class="grafolog-clients__consent">
          <input v-model="form.consent" type="checkbox" required />
          Klien sudah menyetujui pemrosesan data pribadi dan tulisan tangannya (UU PDP).
        </label>
        <p v-if="formError" class="error">{{ formError }}</p>
        <button type="submit" class="btn btn--primary" :disabled="saving">
          {{ saving ? 'Mendaftarkan...' : 'Daftarkan & Kirim Undangan' }}
        </button>
      </form>
      <p v-if="invitation" class="grafolog-clients__sent">
        Undangan dikirim ke <strong>{{ invitation.email }}</strong
        >.
        <template v-if="invitation.url">
          Mode lokal — tautan:
          <a :href="invitation.url" target="_blank" rel="noopener">buka tautan undangan</a>
        </template>
      </p>
    </section>

    <section>
      <h2>Klien Bawaan Saya</h2>
      <SkeletonLoader v-if="loading" variant="list" :rows="3" label="Memuat daftar klien..." />
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <p v-else-if="!clients.length" class="grafolog-clients__empty">Belum ada klien terdaftar.</p>
      <ul v-else class="grafolog-clients__list">
        <li v-for="c in clients" :key="c.id" class="grafolog-clients__item">
          <div>
            <span class="grafolog-clients__name">{{ c.name }}</span>
            <span class="grafolog-clients__meta">
              {{ c.email }}<template v-if="c.phone"> · {{ c.phone }}</template> ·
              {{ c.orders_count }} pesanan
            </span>
          </div>
          <button class="btn" :disabled="resendingId === c.id" @click="resend(c)">
            {{ resendingId === c.id ? 'Mengirim...' : 'Kirim Ulang Undangan' }}
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.grafolog-clients {
  max-width: 760px;
}
.grafolog-clients__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.grafolog-clients__card {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 28px;
  background: var(--color-surface);
}
.grafolog-clients__card h2 {
  margin-top: 0;
  font-size: 16px;
}
.grafolog-clients__row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}
.grafolog-clients__row label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1 1 200px;
  font-size: 13px;
}
.grafolog-clients__consent {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 13px;
  margin-bottom: 12px;
}
.grafolog-clients__sent {
  margin-top: 12px;
  font-size: 13px;
  color: var(--color-text-soft);
}
.grafolog-clients__empty {
  color: var(--color-text-soft);
}
.grafolog-clients__list {
  list-style: none;
  padding: 0;
}
.grafolog-clients__item {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px 16px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--color-border);
}
.grafolog-clients__name {
  display: block;
  font-weight: 600;
}
.grafolog-clients__meta {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
  overflow-wrap: anywhere;
}
</style>
