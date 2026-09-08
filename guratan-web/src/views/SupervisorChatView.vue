<script setup>
import { ref, onMounted, nextTick } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
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
    const { data } = await api.get('/supervisor/chat/conversations')
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
    const { data } = await api.get(`/supervisor/chat/conversations/${conversation.id}`)
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
    const { data } = await api.post('/supervisor/chat/conversations', {})
    conversations.value.unshift(data)
    await openConversation(data)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuat percakapan baru.')
  }
}

async function deleteConversation(conversation) {
  if (!confirm('Hapus percakapan ini?')) return
  try {
    await api.delete(`/supervisor/chat/conversations/${conversation.id}`)
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
  // sebelum memanggil LLM (lihat SupervisorChatService), jadi tidak ada
  // risiko pertanyaan hilang kalau balasan gagal.
  messages.value.push({ id: `optimistic-${Date.now()}`, role: 'user', content: text })
  draft.value = ''
  await scrollToBottom()

  sending.value = true
  try {
    const { data } = await api.post(`/supervisor/chat/conversations/${activeConversation.value.id}/messages`, {
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
  <div class="supervisor-chat">
    <h1>Asisten Chat</h1>

    <div class="supervisor-chat__disclaimer">
      Asisten ini memberikan ringkasan reflektif berdasarkan data yang tersimpan, bukan penilaian final atau
      diagnosis.
    </div>

    <div class="supervisor-chat__layout">
      <aside class="supervisor-chat__sidebar">
        <button type="button" class="btn btn--primary supervisor-chat__new" @click="startNewConversation">
          + Percakapan Baru
        </button>
        <LoadingSpinner v-if="conversationsLoading" label="Memuat..." />
        <p v-else-if="conversations.length === 0" class="supervisor-chat__empty">Belum ada percakapan.</p>
        <ul v-else class="supervisor-chat__list">
          <li
            v-for="c in conversations"
            :key="c.id"
            class="supervisor-chat__list-item"
            :class="{ 'supervisor-chat__list-item--active': activeConversation?.id === c.id }"
          >
            <button type="button" class="supervisor-chat__list-button" @click="openConversation(c)">
              {{ c.title || `Percakapan #${c.id}` }}
            </button>
            <button type="button" class="supervisor-chat__list-delete" @click="deleteConversation(c)">×</button>
          </li>
        </ul>
      </aside>

      <section class="supervisor-chat__panel">
        <div v-if="!activeConversation" class="supervisor-chat__placeholder">
          Pilih percakapan atau mulai yang baru.
        </div>
        <template v-else>
          <div ref="messagePanel" class="supervisor-chat__messages">
            <LoadingSpinner v-if="messageLoading" label="Memuat..." />
            <template v-else>
              <p v-if="messages.length === 0" class="supervisor-chat__empty">
                Belum ada pesan - mulai dengan bertanya tentang kandidat perusahaan Anda.
              </p>
              <div
                v-for="m in messages"
                :key="m.id"
                class="supervisor-chat__bubble"
                :class="`supervisor-chat__bubble--${m.role}`"
              >
                {{ m.content }}
              </div>
              <div v-if="sending" class="supervisor-chat__bubble supervisor-chat__bubble--assistant supervisor-chat__bubble--typing">
                mengetik...
              </div>
            </template>
          </div>

          <p v-if="sendError" class="error supervisor-chat__send-error">{{ sendError }}</p>

          <form class="supervisor-chat__composer" @submit.prevent="sendMessage">
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
.supervisor-chat {
  max-width: 1000px;
}
.supervisor-chat__disclaimer {
  background: var(--color-paper-alt);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 18px;
}
.supervisor-chat__layout {
  display: flex;
  gap: 20px;
  height: 560px;
}
.supervisor-chat__sidebar {
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
.supervisor-chat__new {
  width: 100%;
}
.supervisor-chat__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.supervisor-chat__list {
  list-style: none;
  padding: 0;
  margin: 0;
  overflow-y: auto;
}
.supervisor-chat__list-item {
  display: flex;
  align-items: center;
  gap: 4px;
}
.supervisor-chat__list-item--active .supervisor-chat__list-button {
  background: var(--color-paper-alt);
  font-weight: 600;
}
.supervisor-chat__list-button {
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
.supervisor-chat__list-button:hover {
  background: var(--color-paper-alt);
}
.supervisor-chat__list-delete {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--color-text-soft);
  font-size: 16px;
  padding: 4px 8px;
}
.supervisor-chat__list-delete:hover {
  color: var(--color-seal);
}
.supervisor-chat__panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  overflow: hidden;
}
.supervisor-chat__placeholder {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-soft);
  font-size: 13px;
}
.supervisor-chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.supervisor-chat__bubble {
  max-width: 75%;
  padding: 10px 14px;
  border-radius: var(--radius-md);
  font-size: 13.5px;
  white-space: pre-wrap;
}
.supervisor-chat__bubble--user {
  align-self: flex-end;
  background: var(--color-seal);
  color: #fff;
}
.supervisor-chat__bubble--assistant {
  align-self: flex-start;
  background: var(--color-paper-alt);
  color: var(--color-ink);
}
.supervisor-chat__bubble--typing {
  color: var(--color-text-soft);
  font-style: italic;
}
.supervisor-chat__send-error {
  padding: 0 16px;
  font-size: 12.5px;
}
.supervisor-chat__composer {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  border-top: 1px solid var(--color-border);
}
.supervisor-chat__composer input {
  flex: 1;
}

@media (max-width: 720px) {
  .supervisor-chat__layout {
    flex-direction: column;
    height: auto;
  }
  .supervisor-chat__sidebar {
    width: 100%;
  }
  .supervisor-chat__messages {
    max-height: 400px;
  }
}
</style>
