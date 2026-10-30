<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatTanggal } from '@/lib/format'
import { useToast } from '@/composables/useToast'

/** Chat teks klien ↔ grafolog untuk satu pesanan. Polling ringan 8 detik selagi halaman terbuka. */
const props = defineProps({ id: { type: [String, Number], required: true } })
const toast = useToast()
const messages = ref([])
const meta = ref({ open: false, with: null, order: null })
const draft = ref('')
const sending = ref(false)
const error = ref('')
const box = ref(null)
let timer

async function load(initial = false) {
  try {
    const after = initial ? 0 : (messages.value.at(-1)?.id ?? 0)
    const { data } = await api.get(`/orders/${props.id}/messages`, { params: { after } })
    meta.value = { open: data.open, with: data.with, order: data.order }
    if (initial) messages.value = data.messages
    else if (data.messages.length) messages.value.push(...data.messages)
    if (initial || data.messages.length) {
      await nextTick()
      if (box.value) box.value.scrollTop = box.value.scrollHeight
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Chat tidak dapat dibuka.'
    clearInterval(timer)
  }
}

async function send() {
  const body = draft.value.trim()
  if (!body || sending.value) return
  sending.value = true
  try {
    await api.post(`/orders/${props.id}/messages`, { body })
    draft.value = ''
    await load()
  } catch (e) {
    toast.push(
      e.response?.data?.errors?.body?.[0] ?? e.response?.data?.message ?? 'Pesan gagal dikirim.',
    )
  } finally {
    sending.value = false
  }
}

onMounted(async () => {
  await load(true)
  timer = setInterval(() => document.visibilityState === 'visible' && load(), 8000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="chat">
    <RouterLink to="/riwayat" class="chat__back">‹ Kembali</RouterLink>
    <h1>Chat pesanan #{{ id }}</h1>
    <p v-if="error" class="error">{{ error }}</p>
    <template v-else>
      <p class="chat__sub">
        {{ meta.order?.product
        }}<template v-if="meta.with">
          · dengan <strong>{{ meta.with.name }}</strong></template
        >
      </p>
      <div ref="box" class="chat__box" role="log" aria-live="polite">
        <p v-if="!messages.length" class="chat__empty">Belum ada pesan.</p>
        <div
          v-for="m in messages"
          :key="m.id"
          class="chat__msg"
          :class="{ 'chat__msg--mine': m.mine }"
        >
          <span class="chat__who">{{ m.mine ? 'Anda' : m.sender }}</span>
          <p>{{ m.body }}</p>
          <small
            >{{ formatTanggal(m.at, true)
            }}<template v-if="m.mine"> · {{ m.read ? 'dibaca' : 'terkirim' }}</template></small
          >
        </div>
      </div>
      <form v-if="meta.open" class="chat__form" @submit.prevent="send">
        <textarea
          v-model="draft"
          rows="2"
          maxlength="2000"
          placeholder="Tulis pesan (teks saja)"
          aria-label="Pesan"
          @keydown.enter.exact.prevent="send"
        />
        <button class="btn btn--primary" :disabled="sending || !draft.trim()">Kirim</button>
      </form>
      <p v-else class="chat__closed">
        Chat ini hanya dapat dibaca (belum dibuka atau sudah ditutup).
      </p>
      <p class="chat__note">
        Jangan membagikan data sensitif (kata sandi, nomor kartu). Percakapan dapat ditinjau
        administrator bila ada sengketa.
      </p>
    </template>
  </div>
</template>

<style scoped>
.chat {
  max-width: 680px;
}
.chat__back {
  font-size: 13px;
}
.chat__sub {
  color: var(--color-text-soft);
  margin: 0 0 10px;
}
.chat__box {
  height: 360px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.chat__empty {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: auto;
}
.chat__msg {
  max-width: 82%;
  align-self: flex-start;
  background: var(--color-paper-alt);
  border-radius: var(--radius-md);
  padding: 6px 10px;
}
.chat__msg--mine {
  align-self: flex-end;
  background: var(--color-seal-soft);
}
.chat__msg p {
  margin: 2px 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: 14px;
}
.chat__who {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--color-text-soft);
}
.chat__msg small {
  font-size: 11px;
  color: var(--color-text-soft);
}
.chat__form {
  display: flex;
  gap: 8px;
  margin-top: 8px;
  align-items: flex-end;
}
.chat__form textarea {
  flex: 1;
  resize: vertical;
}
.chat__closed,
.chat__note {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
</style>
