<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import GameLeaderboard from '@/components/games/GameLeaderboard.vue'
import { styleForKeterangan } from '@/lib/handwritingStyle'

const TOTAL_ROUNDS = 8

const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const loadError = ref('')
const question = ref(null)
const chosenAspekId = ref(null)
const result = ref(null)
const round = ref(0)
const score = ref(0)
const finished = ref(false)

const nama = ref(auth.user?.name ?? '')
const submitting = ref(false)
const submitted = ref(false)

const sampleStyle = computed(() => (question.value ? styleForKeterangan(question.value.keterangan) : {}))

async function loadQuestion() {
  loading.value = true
  loadError.value = ''
  result.value = null
  chosenAspekId.value = null
  try {
    const { data } = await api.get('/games/tebak-kepribadian/question')
    question.value = data
  } catch {
    loadError.value = 'Gagal memuat soal.'
  } finally {
    loading.value = false
  }
}

async function choose(aspekId) {
  if (result.value) return
  chosenAspekId.value = aspekId
  try {
    const { data } = await api.post('/games/tebak-kepribadian/answer', {
      indikator_id: question.value.indikator_id,
      aspek_id: aspekId,
    })
    result.value = data
    if (data.correct) score.value++
  } catch {
    toast.push('Gagal memeriksa jawaban.')
  }
}

function next() {
  round.value++
  if (round.value >= TOTAL_ROUNDS) {
    finished.value = true
    return
  }
  loadQuestion()
}

async function submitScore() {
  submitting.value = true
  try {
    await api.post('/games/scores', { game_type: 'tebak_kepribadian', nama: nama.value, skor: score.value })
    submitted.value = true
  } catch {
    toast.push('Gagal mengirim skor.')
  } finally {
    submitting.value = false
  }
}

function playAgain() {
  round.value = 0
  score.value = 0
  finished.value = false
  submitted.value = false
  loadQuestion()
}

onMounted(loadQuestion)
</script>

<template>
  <div class="game-tebak">
    <h1>Tebak Kepribadian dari Tulisan</h1>
    <p class="game-tebak__note">Tebak sifat kepribadian dari ciri tulisan tangan berikut - lalu lihat penjelasannya.</p>

    <template v-if="!finished">
      <p class="game-tebak__progress">Soal {{ round + 1 }} / {{ TOTAL_ROUNDS }} - Skor: {{ score }}</p>

      <LoadingSpinner v-if="loading" label="Memuat soal..." />
      <p v-else-if="loadError" class="error">{{ loadError }}</p>
      <template v-else-if="question">
        <div class="game-tebak__sample" :style="sampleStyle">{{ question.keterangan }}</div>

        <div class="game-tebak__choices">
          <button
            v-for="choice in question.choices"
            :key="choice.id"
            type="button"
            class="game-tebak__choice"
            :class="{
              'is-chosen': chosenAspekId === choice.id,
              'is-correct': result && choice.id === result.correct_aspek.id,
              'is-wrong': result && chosenAspekId === choice.id && !result.correct,
            }"
            :disabled="!!result"
            @click="choose(choice.id)"
          >
            {{ choice.nama }}
          </button>
        </div>

        <div v-if="result" class="game-tebak__result">
          <p :class="result.correct ? 'game-tebak__correct' : 'game-tebak__incorrect'">
            {{ result.correct ? 'Benar!' : 'Kurang tepat.' }} Jawaban: {{ result.correct_aspek.nama }}
          </p>
          <p class="game-tebak__penjelasan">{{ result.penjelasan }}</p>
          <button type="button" class="btn btn--primary" @click="next">
            {{ round + 1 >= TOTAL_ROUNDS ? 'Lihat Hasil' : 'Lanjut' }}
          </button>
        </div>
      </template>
    </template>

    <template v-else>
      <h2>Selesai! Skor Akhir: {{ score }} / {{ TOTAL_ROUNDS }}</h2>

      <div v-if="!submitted" class="game-tebak__submit">
        <label>
          Nama untuk papan skor
          <input v-model="nama" type="text" maxlength="30" placeholder="Nama Anda" />
        </label>
        <button type="button" class="btn btn--primary" :disabled="submitting || !nama" @click="submitScore">
          {{ submitting ? 'Mengirim...' : 'Kirim Skor' }}
        </button>
      </div>

      <GameLeaderboard v-if="submitted" game-type="tebak_kepribadian" />
      <button type="button" class="btn" @click="playAgain">Main Lagi</button>
    </template>
  </div>
</template>

<style scoped>
.game-tebak {
  max-width: 640px;
}
.game-tebak__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 16px;
}
.game-tebak__progress {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 16px;
}
.game-tebak__sample {
  font-family: var(--font-accent);
  text-align: center;
  padding: 32px 20px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  line-height: 1.4;
}
.game-tebak__choices {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 20px;
}
.game-tebak__choice {
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
  cursor: pointer;
  text-align: left;
}
.game-tebak__choice.is-chosen {
  border-color: var(--color-seal);
}
.game-tebak__choice.is-correct {
  background: var(--color-sage);
  color: #fff;
  border-color: var(--color-sage);
}
.game-tebak__choice.is-wrong {
  background: var(--color-seal);
  color: #fff;
  border-color: var(--color-seal);
}
.game-tebak__result {
  padding: 16px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-md);
}
.game-tebak__correct {
  color: var(--color-sage);
  font-weight: 600;
}
.game-tebak__incorrect {
  color: var(--color-seal);
  font-weight: 600;
}
.game-tebak__penjelasan {
  font-size: 13.5px;
  color: var(--color-text-soft);
  margin: 8px 0 14px;
}
.game-tebak__submit {
  margin: 16px 0;
}
.game-tebak__submit label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
</style>
