<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatTanggal } from '@/lib/format'
import { useInbox } from '@/composables/useInbox'

/** Kotak masuk notifikasi: pesan sistem + broadcast admin. */
const router = useRouter()
const { unread, refresh } = useInbox()
const messages = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const onlyUnread = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/inbox', {
      params: { page: page.value, ...(onlyUnread.value ? { unread: 1 } : {}) },
    })
    messages.value = data.messages.data
    lastPage.value = data.messages.last_page
    unread.value = data.unread
  } finally {
    loading.value = false
  }
}

async function open(m) {
  if (!m.read_at) {
    await api.post(`/inbox/${m.id}/read`)
    m.read_at = new Date().toISOString()
    refresh()
  }
  if (m.url) router.push(m.url)
}

async function readAll() {
  await api.post('/inbox/read-all')
  messages.value.forEach((m) => (m.read_at ??= new Date().toISOString()))
  unread.value = 0
}

function go(delta) {
  page.value += delta
  load()
}
function toggleUnread() {
  page.value = 1
  load()
}
onMounted(load)
</script>

<template>
  <div class="inbox">
    <div class="inbox__head">
      <h1>Kotak Masuk</h1>
      <div class="inbox__tools">
        <label
          ><input v-model="onlyUnread" type="checkbox" @change="toggleUnread" /> Belum dibaca</label
        >
        <button type="button" class="btn" :disabled="!unread" @click="readAll">
          Tandai semua dibaca
        </button>
      </div>
    </div>
    <SkeletonLoader v-if="loading" variant="list" :rows="4" label="Memuat..." />
    <p v-else-if="!messages.length" class="inbox__empty">Belum ada pesan.</p>
    <ul v-else class="inbox__list">
      <li
        v-for="m in messages"
        :key="m.id"
        class="inbox__item"
        :class="{ 'inbox__item--unread': !m.read_at }"
      >
        <button type="button" @click="open(m)">
          <strong>{{ m.title }}</strong>
          <span class="inbox__body">{{ m.body }}</span>
          <small>{{ formatTanggal(m.created_at, true) }}{{ m.url ? ' · buka ›' : '' }}</small>
        </button>
      </li>
    </ul>
    <div v-if="lastPage > 1" class="inbox__pager">
      <button type="button" class="btn" :disabled="page <= 1" @click="go(-1)">‹ Sebelumnya</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button type="button" class="btn" :disabled="page >= lastPage" @click="go(1)">
        Berikutnya ›
      </button>
    </div>
  </div>
</template>

<style scoped>
.inbox {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 760px;
}
.inbox__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.inbox__tools {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}
.inbox__empty {
  color: var(--color-text-soft);
}
.inbox__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.inbox__item button {
  width: 100%;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 14px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: inherit;
  color: var(--color-ink);
}
.inbox__item--unread button {
  border-left: 3px solid var(--color-seal);
  background: var(--color-seal-soft);
}
.inbox__body {
  font-size: 14px;
  color: var(--color-ink-soft);
  white-space: pre-line;
}
.inbox__item small {
  color: var(--color-text-soft);
}
.inbox__pager {
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: center;
}
</style>
