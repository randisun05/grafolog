<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import GameLeaderboard from '@/components/games/GameLeaderboard.vue'

const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const loadError = ref('')
const cards = ref([])
const flipped = ref([])
const matchedIds = ref(new Set())
const moves = ref(0)
const seconds = ref(0)
const timerHandle = ref(null)
const finished = ref(false)
const locked = ref(false)

const nama = ref(auth.user?.name ?? '')
const submitting = ref(false)
const submitted = ref(false)

const score = computed(() => Math.max(100, 1000 - moves.value * 15 - seconds.value * 3))

function shuffle(arr) {
  const result = [...arr]
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[result[i], result[j]] = [result[j], result[i]]
  }
  return result
}

function startTimer() {
  timerHandle.value = setInterval(() => {
    seconds.value++
  }, 1000)
}

function stopTimer() {
  if (timerHandle.value) clearInterval(timerHandle.value)
  timerHandle.value = null
}

async function loadTerms() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/games/memory-match/terms')
    const deck = data.flatMap((term) => [
      { pairId: term.id, kind: 'istilah', label: term.istilah },
      { pairId: term.id, kind: 'definisi', label: term.definisi },
    ])
    cards.value = shuffle(deck).map((card, i) => ({ ...card, cardId: i }))
    moves.value = 0
    seconds.value = 0
    matchedIds.value = new Set()
    flipped.value = []
    finished.value = false
    startTimer()
  } catch {
    loadError.value = 'Gagal memuat kartu.'
  } finally {
    loading.value = false
  }
}

function flip(card) {
  if (locked.value) return
  if (matchedIds.value.has(card.pairId)) return
  if (flipped.value.some((c) => c.cardId === card.cardId)) return
  if (flipped.value.length >= 2) return

  flipped.value.push(card)

  if (flipped.value.length === 2) {
    moves.value++
    locked.value = true
    const [a, b] = flipped.value
    if (a.pairId === b.pairId) {
      setTimeout(() => {
        matchedIds.value.add(a.pairId)
        flipped.value = []
        locked.value = false
        if (matchedIds.value.size === cards.value.length / 2) {
          finished.value = true
          stopTimer()
        }
      }, 500)
    } else {
      setTimeout(() => {
        flipped.value = []
        locked.value = false
      }, 900)
    }
  }
}

function isFlipped(card) {
  return matchedIds.value.has(card.pairId) || flipped.value.some((c) => c.cardId === card.cardId)
}

async function submitScore() {
  submitting.value = true
  try {
    await api.post('/games/scores', {
      game_type: 'memory_match', nama: nama.value, skor: score.value, durasi_detik: seconds.value,
    })
    submitted.value = true
  } catch {
    toast.push('Gagal mengirim skor.')
  } finally {
    submitting.value = false
  }
}

function playAgain() {
  submitted.value = false
  loadTerms()
}

onMounted(loadTerms)
onUnmounted(stopTimer)
</script>

<template>
  <div class="game-memory">
    <h1>Memory Match Istilah</h1>
    <p class="game-memory__note">Cocokkan kartu istilah grafologi dengan definisinya secepat mungkin.</p>

    <LoadingSpinner v-if="loading" label="Memuat kartu..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <template v-else>
      <p v-if="!finished" class="game-memory__stats">Langkah: {{ moves }} - Waktu: {{ seconds }} detik</p>

      <div v-if="!finished" class="game-memory__grid">
        <button
          v-for="card in cards"
          :key="card.cardId"
          type="button"
          class="game-memory__card"
          :class="{ 'is-flipped': isFlipped(card), 'is-matched': matchedIds.has(card.pairId) }"
          @click="flip(card)"
        >
          <span v-if="isFlipped(card)">{{ card.label }}</span>
          <span v-else>?</span>
        </button>
      </div>

      <template v-else>
        <h2>Selesai! {{ moves }} langkah, {{ seconds }} detik - Skor: {{ score }}</h2>

        <div v-if="!submitted" class="game-memory__submit">
          <label>
            Nama untuk papan skor
            <input v-model="nama" type="text" maxlength="30" placeholder="Nama Anda" />
          </label>
          <button type="button" class="btn btn--primary" :disabled="submitting || !nama" @click="submitScore">
            {{ submitting ? 'Mengirim...' : 'Kirim Skor' }}
          </button>
        </div>

        <GameLeaderboard v-if="submitted" game-type="memory_match" />
        <button type="button" class="btn" @click="playAgain">Main Lagi</button>
      </template>
    </template>
  </div>
</template>

<style scoped>
.game-memory {
  max-width: 700px;
}
.game-memory__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 16px;
}
.game-memory__stats {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.game-memory__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 20px;
}
.game-memory__card {
  aspect-ratio: 1;
  min-height: 90px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-seal);
  color: transparent;
  font-size: 11px;
  padding: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  line-height: 1.3;
  transition: background 0.15s ease;
}
.game-memory__card.is-flipped {
  background: var(--color-surface);
  color: var(--color-ink);
}
.game-memory__card.is-matched {
  background: var(--color-sage);
  color: #fff;
}
.game-memory__submit {
  margin: 16px 0;
}
.game-memory__submit label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
</style>
