<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'

const props = defineProps({
  gameType: { type: String, required: true },
  refreshKey: { type: [String, Number], default: 0 },
})

const scores = ref([])
const loading = ref(true)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get(`/games/${props.gameType}/leaderboard`)
    scores.value = data
  } catch {
    loadError.value = 'Gagal memuat papan skor.'
  } finally {
    loading.value = false
  }
}

defineExpose({ load })

onMounted(load)
</script>

<template>
  <div class="game-leaderboard">
    <h3>Papan Skor</h3>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="scores.length === 0" class="game-leaderboard__empty">Belum ada skor tercatat.</p>
    <ol v-else class="game-leaderboard__list">
      <li v-for="(s, i) in scores" :key="i">
        <span class="game-leaderboard__rank">{{ i + 1 }}</span>
        <span class="game-leaderboard__nama">{{ s.nama }}</span>
        <span class="game-leaderboard__skor">{{ s.skor }}</span>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.game-leaderboard {
  padding: 16px;
  background: var(--color-paper-alt);
  border-radius: var(--radius-md);
}
.game-leaderboard h3 {
  font-size: 14px;
  margin-bottom: 10px;
}
.game-leaderboard__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.game-leaderboard__list {
  list-style: none;
  padding: 0;
  margin: 0;
  counter-reset: none;
}
.game-leaderboard__list li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 0;
  border-bottom: 1px solid var(--color-border);
  font-size: 13.5px;
}
.game-leaderboard__rank {
  width: 20px;
  text-align: right;
  color: var(--color-text-soft);
  font-weight: 600;
}
.game-leaderboard__nama {
  flex: 1;
}
.game-leaderboard__skor {
  font-weight: 600;
  color: var(--color-seal);
}
</style>
