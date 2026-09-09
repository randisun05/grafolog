<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const activeTab = ref('trivia')
const tabs = [
  { key: 'trivia', label: 'Trivia' },
  { key: 'glosarium', label: 'Glosarium' },
  { key: 'skor', label: 'Moderasi Skor' },
]

// --- Trivia ---
const questions = ref([])
const questionsLoading = ref(true)
const createQuestionForm = ref({ pertanyaan: '', pilihan: ['', '', '', ''], jawaban_benar_index: 0, penjelasan: '' })
const createQuestionErrors = ref({})
const creatingQuestion = ref(false)

async function loadQuestions() {
  questionsLoading.value = true
  try {
    const { data } = await api.get('/admin/trivia-questions')
    questions.value = data
  } catch {
    toast.push('Gagal memuat soal trivia.')
  } finally {
    questionsLoading.value = false
  }
}

async function createQuestion() {
  creatingQuestion.value = true
  createQuestionErrors.value = {}
  try {
    await api.post('/admin/trivia-questions', createQuestionForm.value)
    toast.push('Soal trivia berhasil dibuat.', 'success')
    createQuestionForm.value = { pertanyaan: '', pilihan: ['', '', '', ''], jawaban_benar_index: 0, penjelasan: '' }
    await loadQuestions()
  } catch (e) {
    createQuestionErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat soal trivia.')
  } finally {
    creatingQuestion.value = false
  }
}

async function toggleQuestionActive(question) {
  try {
    const { data } = await api.patch(`/admin/trivia-questions/${question.id}`, { is_active: !question.is_active })
    Object.assign(question, data)
  } catch {
    toast.push('Gagal mengubah status soal.')
  }
}

async function deleteQuestion(question) {
  if (!window.confirm(`Hapus soal "${question.pertanyaan}"?`)) return
  try {
    await api.delete(`/admin/trivia-questions/${question.id}`)
    toast.push('Soal dihapus.', 'success')
    await loadQuestions()
  } catch {
    toast.push('Gagal menghapus soal.')
  }
}

// --- Glosarium ---
const terms = ref([])
const termsLoading = ref(true)
const createTermForm = ref({ istilah: '', definisi: '' })
const createTermErrors = ref({})
const creatingTerm = ref(false)

async function loadTerms() {
  termsLoading.value = true
  try {
    const { data } = await api.get('/admin/glossary-terms')
    terms.value = data
  } catch {
    toast.push('Gagal memuat istilah.')
  } finally {
    termsLoading.value = false
  }
}

async function createTerm() {
  creatingTerm.value = true
  createTermErrors.value = {}
  try {
    await api.post('/admin/glossary-terms', createTermForm.value)
    toast.push('Istilah berhasil dibuat.', 'success')
    createTermForm.value = { istilah: '', definisi: '' }
    await loadTerms()
  } catch (e) {
    createTermErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat istilah.')
  } finally {
    creatingTerm.value = false
  }
}

async function toggleTermActive(term) {
  try {
    const { data } = await api.patch(`/admin/glossary-terms/${term.id}`, { is_active: !term.is_active })
    Object.assign(term, data)
  } catch {
    toast.push('Gagal mengubah status istilah.')
  }
}

async function deleteTerm(term) {
  if (!window.confirm(`Hapus istilah "${term.istilah}"?`)) return
  try {
    await api.delete(`/admin/glossary-terms/${term.id}`)
    toast.push('Istilah dihapus.', 'success')
    await loadTerms()
  } catch {
    toast.push('Gagal menghapus istilah.')
  }
}

// --- Moderasi Skor ---
const scores = ref([])
const scoresLoading = ref(true)
const scoreGameTypeFilter = ref('')

async function loadScores() {
  scoresLoading.value = true
  try {
    const { data } = await api.get('/admin/game-scores', {
      params: { game_type: scoreGameTypeFilter.value || undefined },
    })
    scores.value = data.data
  } catch {
    toast.push('Gagal memuat skor.')
  } finally {
    scoresLoading.value = false
  }
}

async function deleteScore(score) {
  if (!window.confirm(`Hapus skor "${score.nama}"?`)) return
  try {
    await api.delete(`/admin/game-scores/${score.id}`)
    toast.push('Skor dihapus.', 'success')
    await loadScores()
  } catch {
    toast.push('Gagal menghapus skor.')
  }
}

onMounted(() => {
  loadQuestions()
  loadTerms()
  loadScores()
})
</script>

<template>
  <div class="admin-games">
    <h1>Kelola Games</h1>
    <p class="admin-games__note">Konten Trivia &amp; Glosarium untuk mini game publik, plus moderasi papan skor.</p>

    <div class="admin-games__tabs">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        :class="{ 'admin-games__tab--active': activeTab === t.key }"
        @click="activeTab = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- Trivia -->
    <section v-if="activeTab === 'trivia'" class="admin-games__panel">
      <div class="admin-games__create">
        <h2>Tambah Soal Trivia</h2>
        <form @submit.prevent="createQuestion">
          <label>
            Pertanyaan
            <textarea v-model="createQuestionForm.pertanyaan" rows="2" required></textarea>
          </label>
          <p v-if="createQuestionErrors.pertanyaan" class="error">{{ createQuestionErrors.pertanyaan[0] }}</p>

          <label v-for="(pilihan, i) in createQuestionForm.pilihan" :key="i">
            Pilihan {{ i + 1 }}
            <input v-model="createQuestionForm.pilihan[i]" type="text" required />
          </label>

          <label>
            Jawaban Benar
            <select v-model.number="createQuestionForm.jawaban_benar_index">
              <option v-for="(pilihan, i) in createQuestionForm.pilihan" :key="i" :value="i">
                Pilihan {{ i + 1 }}{{ pilihan ? `: ${pilihan}` : '' }}
              </option>
            </select>
          </label>

          <label>
            Penjelasan (opsional)
            <textarea v-model="createQuestionForm.penjelasan" rows="2"></textarea>
          </label>

          <button type="submit" class="btn btn--primary" :disabled="creatingQuestion">
            {{ creatingQuestion ? 'Menyimpan...' : 'Tambah Soal' }}
          </button>
        </form>
      </div>

      <LoadingSpinner v-if="questionsLoading" label="Memuat..." />
      <table v-else class="admin-games__table">
        <thead>
          <tr>
            <th>Pertanyaan</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in questions" :key="q.id">
            <td>{{ q.pertanyaan }}</td>
            <td>
              <span class="badge" :class="q.is_active ? 'badge--active' : 'badge--inactive'">
                {{ q.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="admin-games__actions">
              <button type="button" class="btn" @click="toggleQuestionActive(q)">
                {{ q.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
              <button type="button" class="btn" @click="deleteQuestion(q)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Glosarium -->
    <section v-if="activeTab === 'glosarium'" class="admin-games__panel">
      <div class="admin-games__create">
        <h2>Tambah Istilah</h2>
        <form @submit.prevent="createTerm">
          <label>
            Istilah
            <input v-model="createTermForm.istilah" type="text" required />
          </label>
          <p v-if="createTermErrors.istilah" class="error">{{ createTermErrors.istilah[0] }}</p>

          <label>
            Definisi
            <textarea v-model="createTermForm.definisi" rows="2" required></textarea>
          </label>
          <p v-if="createTermErrors.definisi" class="error">{{ createTermErrors.definisi[0] }}</p>

          <button type="submit" class="btn btn--primary" :disabled="creatingTerm">
            {{ creatingTerm ? 'Menyimpan...' : 'Tambah Istilah' }}
          </button>
        </form>
      </div>

      <LoadingSpinner v-if="termsLoading" label="Memuat..." />
      <table v-else class="admin-games__table">
        <thead>
          <tr>
            <th>Istilah</th>
            <th>Definisi</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in terms" :key="t.id">
            <td>{{ t.istilah }}</td>
            <td>{{ t.definisi }}</td>
            <td>
              <span class="badge" :class="t.is_active ? 'badge--active' : 'badge--inactive'">
                {{ t.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="admin-games__actions">
              <button type="button" class="btn" @click="toggleTermActive(t)">
                {{ t.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
              <button type="button" class="btn" @click="deleteTerm(t)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Moderasi Skor -->
    <section v-if="activeTab === 'skor'" class="admin-games__panel">
      <div class="admin-games__filter">
        <label>
          Game
          <select v-model="scoreGameTypeFilter" @change="loadScores">
            <option value="">Semua</option>
            <option value="tebak_kepribadian">Tebak Kepribadian</option>
            <option value="trivia">Trivia</option>
            <option value="memory_match">Memory Match</option>
          </select>
        </label>
      </div>

      <LoadingSpinner v-if="scoresLoading" label="Memuat..." />
      <table v-else class="admin-games__table">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Game</th>
            <th>Skor</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in scores" :key="s.id">
            <td>{{ s.nama }}</td>
            <td>{{ s.game_type }}</td>
            <td>{{ s.skor }}</td>
            <td>
              <button type="button" class="btn" @click="deleteScore(s)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

<style scoped>
.admin-games {
  max-width: 900px;
}
.admin-games__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-games__tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  overflow-x: auto;
}
.admin-games__tabs button {
  padding: 7px 16px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}
.admin-games__tab--active {
  background: var(--color-seal);
  color: #fff;
  border-color: var(--color-seal);
}
.admin-games__create {
  margin-bottom: 24px;
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-games__create h2 {
  font-size: 15px;
  margin-bottom: 12px;
}
.admin-games__create label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
.admin-games__filter {
  margin-bottom: 16px;
}
.admin-games__filter label {
  font-size: 13px;
  color: var(--color-text-soft);
}
.admin-games__table {
  display: block;
  width: 100%;
  overflow-x: auto;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-games__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-games__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-games__actions {
  display: flex;
  gap: 6px;
}
.badge {
  font-size: 11.5px;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}
.badge--active {
  background: var(--color-sage);
  color: #fff;
}
.badge--inactive {
  background: var(--color-ink-faint);
  color: var(--color-ink-soft);
}
</style>
