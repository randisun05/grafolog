<script setup>
import { ref, onMounted, nextTick } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const conversations = ref([])
const conversationsLoading = ref(false)

const activeConversation = ref(null)
const messages = ref([])
const messageLoading = ref(false)

const draft = ref('')
const sending = ref(false)
const sendError = ref('')

const messagePanel = ref(null)

async function loadConversations() {
  conversationsLoading.value = true
  try {
    const { data } = await api.get('/hr/chat')
    conversations.value = data
  } catch {
    toast.push('Gagal memuat daftar percakapan.')
  } finally {
    conversationsLoading.value = false
  }
}

async function openConversation(conversation) {
  activeConversation.value = conversation
  messageLoading.value = true
  sendError.value = ''
  try {
    const { data } = await api.get(`/hr/chat/${conversation.id}`)
    messages.value = data.messages
    await scrollToBottom()
  } catch {
    toast.push('Gagal memuat percakapan.')
  } finally {
    messageLoading.value = false
  }
}

async function startNewConversation() {
  try {
    const { data } = await api.post('/hr/chat', {})
    conversations.value.unshift(data)
    await openConversation(data)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuat percakapan baru.')
  }
}

async function deleteConversation(conversation) {
  if (!confirm('Hapus percakapan ini?')) return
  try {
    await api.delete(`/hr/chat/${conversation.id}`)
    conversations.value = conversations.value.filter((c) => c.id !== conversation.id)
    if (activeConversation.value?.id === conversation.id) {
      activeConversation.value = null
      messages.value = []
    }
  } catch {
    toast.push('Gagal menghapus percakapan.')
  }
}

async function scrollToBottom() {
  await nextTick()
  if (messagePanel.value) {
    messagePanel.value.scrollTop = messagePanel.value.scrollHeight
  }
}

async function sendMessage() {
  const text = draft.value.trim()
  if (!text || sending.value) return
  if (!activeConversation.value) {
    await startNewConversation()
    if (!activeConversation.value) return
  }

  sendError.value = ''
  // Render optimistic pesan user - server juga menyimpan pesan ini
  // sebelum memanggil LLM (lihat HrChatService), jadi tidak ada
  // risiko pertanyaan hilang kalau balasan gagal.
  messages.value.push({ id: `optimistic-${Date.now()}`, role: 'user', content: text })
  draft.value = ''
  await scrollToBottom()

  sending.value = true
  try {
    const { data } = await api.post(`/hr/chat/${activeConversation.value.id}/messages`, {
      message: text,
    })
    messages.value.push(data)
    await scrollToBottom()
  } catch (e) {
    if (e.response?.status === 429) {
      toast.push('Terlalu banyak pesan, coba lagi sebentar lagi.')
    } else if (e.response?.status === 503) {
      sendError.value = e.response.data?.message ?? 'Asisten chat sedang tidak tersedia, coba lagi nanti.'
    } else {
      sendError.value = e.response?.data?.message ?? 'Gagal mengirim pesan.'
    }
  } finally {
    sending.value = false
  }
}

onMounted(loadConversations)
</script>

<template>
  <div class="hr-chat">
    <h2>Asisten Chat</h2>

    <div class="hr-chat__disclaimer">
      Asisten ini memberikan ringkasan reflektif berdasarkan data yang tersimpan, bukan penilaian final atau
      diagnosis. Ringkasan dikirim ke penyedia AI tanpa identitas kandidat (hanya berlabel "Kandidat N") dan hanya
      dari laporan yang sudah disetujui.
    </div>

    <div class="hr-chat__layout">
      <aside class="hr-chat__sidebar">
        <button type="button" class="btn btn--primary hr-chat__new" @click="startNewConversation">
          + Percakapan Baru
        </button>
        <SkeletonLoader v-if="conversationsLoading" variant="list" :rows="3" label="Memuat..." />
        <p v-else-if="conversations.length === 0" class="hr-chat__empty">Belum ada percakapan.</p>
        <ul v-else class="hr-chat__list">
          <li
            v-for="c in conversations"
            :key="c.id"
            class="hr-chat__list-item"
            :class="{ 'hr-chat__list-item--active': activeConversation?.id === c.id }"
          >
            <button type="button" class="hr-chat__list-button" @click="openConversation(c)">
              {{ c.title || `Percakapan #${c.id}` }}
            </button>
            <button type="button" class="hr-chat__list-delete" @click="deleteConversation(c)">×</button>
          </li>
        </ul>
      </aside>

      <section class="hr-chat__panel">
        <div v-if="!activeConversation" class="hr-chat__placeholder">
          Pilih percakapan atau mulai yang baru.
        </div>
        <template v-else>
          <div ref="messagePanel" class="hr-chat__messages">
            <SkeletonLoader v-if="messageLoading" variant="list" :rows="3" label="Memuat..." />
            <template v-else>
              <p v-if="messages.length === 0" class="hr-chat__empty">
                Belum ada pesan - mulai dengan bertanya tentang kandidat perusahaan Anda.
              </p>
              <div
                v-for="m in messages"
                :key="m.id"
                class="hr-chat__bubble"
                :class="`hr-chat__bubble--${m.role}`"
              >
                {{ m.content }}
              </div>
              <div v-if="sending" class="hr-chat__bubble hr-chat__bubble--assistant hr-chat__bubble--typing">
                mengetik...
              </div>
            </template>
          </div>

          <p v-if="sendError" class="error hr-chat__send-error">{{ sendError }}</p>

          <form class="hr-chat__composer" @submit.prevent="sendMessage">
            <input
              v-model="draft"
              type="text"
              placeholder="Tanyakan sesuatu tentang data kandidat perusahaan Anda..."
              :disabled="sending"
              maxlength="2000"
            />
            <button type="submit" class="btn btn--primary" :disabled="sending || !draft.trim()">Kirim</button>
          </form>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.hr-chat {
  max-width: 1000px;
}
.hr-chat__disclaimer {
  background: var(--color-paper-alt);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 18px;
}
.hr-chat__layout {
  display: flex;
  gap: 20px;
  height: 560px;
}
.hr-chat__sidebar {
  width: 240px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px;
  background: var(--color-surface);
}
.hr-chat__new {
  width: 100%;
}
.hr-chat__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.hr-chat__list {
  list-style: none;
  padding: 0;
  margin: 0;
  overflow-y: auto;
}
.hr-chat__list-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.hr-chat__list-item--active .hr-chat__list-button {
  background: var(--color-paper-alt);
  font-weight: 600;
}
.hr-chat__list-button {
  flex: 1;
  text-align: left;
  background: none;
  border: none;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 13px;
  color: var(--color-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.hr-chat__list-button:hover {
  background: var(--color-paper-alt);
}
.hr-chat__list-delete {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-soft);
  font-size: 16px;
  padding: 4px 8px;
}
.hr-chat__list-delete:hover {
  color: var(--color-seal);
}
.hr-chat__panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  overflow: hidden;
}
.hr-chat__placeholder {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-soft);
  font-size: 13px;
}
.hr-chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hr-chat__bubble {
  max-width: 75%;
  padding: 10px 14px;
  border-radius: var(--radius-md);
  font-size: 13.5px;
  white-space: pre-wrap;
}
.hr-chat__bubble--user {
  align-self: flex-end;
  background: var(--color-seal);
  color: #fff;
}
.hr-chat__bubble--assistant {
  align-self: flex-start;
  background: var(--color-paper-alt);
  color: var(--color-ink);
}
.hr-chat__bubble--typing {
  color: var(--color-text-soft);
  font-style: italic;
}
.hr-chat__send-error {
  padding: 0 16px;
  font-size: 12.5px;
}
.hr-chat__composer {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border);
}
.hr-chat__composer input {
  flex: 1;
}

@media (max-width: 720px) {
  .hr-chat__layout {
    flex-direction: column;
    height: auto;
  }
  .hr-chat__sidebar {
    width: 100%;
  }
  .hr-chat__messages {
    max-height: 400px;
  }
}
</style>
