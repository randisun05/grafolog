<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import GameLeaderboard from '@/components/games/GameLeaderboard.vue'

const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const loadError = ref('')
const questions = ref([])
const currentIndex = ref(0)
const answers = ref({})
const reviewing = ref(false)
const result = ref(null)

const nama = ref(auth.user?.name ?? '')
const submitting = ref(false)
const submitted = ref(false)

const currentQuestion = computed(() => questions.value[currentIndex.value])
const isLastQuestion = computed(() => currentIndex.value >= questions.value.length - 1)

async function loadQuestions() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/games/trivia/questions')
    questions.value = data
  } catch {
    loadError.value = 'Gagal memuat soal.'
  } finally {
    loading.value = false
  }
}

function choose(index) {
  answers.value[currentQuestion.value.id] = index
}

async function nextQuestion() {
  if (!isLastQuestion.value) {
    currentIndex.value++
    return
  }

  try {
    const payload = {
      answers: questions.value.map((q) => ({ question_id: q.id, chosen_index: answers.value[q.id] })),
    }
    const { data } = await api.post('/games/trivia/check', payload)
    result.value = data
    reviewing.value = true
  } catch {
    toast.push('Gagal menilai jawaban.')
  }
}

async function submitScore() {
  submitting.value = true
  try {
    await api.post('/games/scores', { game_type: 'trivia', nama: nama.value, skor: result.value.skor })
    submitted.value = true
  } catch {
    toast.push('Gagal mengirim skor.')
  } finally {
    submitting.value = false
  }
}

function playAgain() {
  currentIndex.value = 0
  answers.value = {}
  result.value = null
  reviewing.value = false
  submitted.value = false
  loadQuestions()
}

onMounted(loadQuestions)
</script>

<template>
  <div class="game-trivia">
    <h1>Trivia Grafologi</h1>
    <p class="game-trivia__note">Uji wawasanmu seputar fakta dan istilah grafologi.</p>

    <LoadingSpinner v-if="loading" label="Memuat soal..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else-if="!reviewing && currentQuestion">
      <p class="game-trivia__progress">Soal {{ currentIndex + 1 }} / {{ questions.length }}</p>
      <p class="game-trivia__question">{{ currentQuestion.pertanyaan }}</p>
      <div class="game-trivia__choices">
        <button
          v-for="(pilihan, i) in currentQuestion.pilihan"
          :key="i"
          type="button"
          class="game-trivia__choice"
          :class="{ 'is-chosen': answers[currentQuestion.id] === i }"
          @click="choose(i)"
        >
          {{ pilihan }}
        </button>
      </div>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="answers[currentQuestion.id] === undefined"
        @click="nextQuestion"
      >
        {{ isLastQuestion ? 'Selesai' : 'Lanjut' }}
      </button>
    </template>

    <template v-else-if="reviewing && result">
      <h2>Skor: {{ result.skor }} / {{ result.total }}</h2>

      <div class="game-trivia__review">
        <div v-for="(detail, i) in result.detail" :key="detail.question_id" class="game-trivia__review-item">
          <p :class="detail.correct ? 'game-trivia__correct' : 'game-trivia__incorrect'">
            {{ i + 1 }}. {{ questions[i].pertanyaan }} - {{ detail.correct ? 'Benar' : 'Salah' }}
          </p>
          <p v-if="detail.penjelasan" class="game-trivia__penjelasan">{{ detail.penjelasan }}</p>
        </div>
      </div>

      <div v-if="!submitted" class="game-trivia__submit">
        <label>
          Nama untuk papan skor
          <input v-model="nama" type="text" maxlength="30" placeholder="Nama Anda" />
        </label>
        <button type="button" class="btn btn--primary" :disabled="submitting || !nama" @click="submitScore">
          {{ submitting ? 'Mengirim...' : 'Kirim Skor' }}
        </button>
      </div>

      <GameLeaderboard v-if="submitted" game-type="trivia" />
      <button type="button" class="btn" @click="playAgain">Main Lagi</button>
    </template>
  </div>
</template>

<style scoped>
.game-trivia {
  max-width: 640px;
}
.game-trivia__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 16px;
}
.game-trivia__progress {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 8px;
}
.game-trivia__question {
  font-size: 17px;
  font-family: var(--font-heading);
  margin-bottom: 16px;
}
.game-trivia__choices {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}
.game-trivia__choice {
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
  cursor: pointer;
  text-align: left;
}
.game-trivia__choice.is-chosen {
  border-color: var(--color-seal);
  background: var(--color-paper-alt);
}
.game-trivia__review {
  margin: 16px 0;
}
.game-trivia__review-item {
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border);
}
.game-trivia__correct {
  color: var(--color-sage);
  font-weight: 600;
  font-size: 13.5px;
}
.game-trivia__incorrect {
  color: var(--color-seal);
  font-weight: 600;
  font-size: 13.5px;
}
.game-trivia__penjelasan {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-top: 4px;
}
.game-trivia__submit {
  margin: 16px 0;
}
.game-trivia__submit label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
</style>
