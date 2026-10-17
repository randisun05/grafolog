<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

/**
 * Mini game "Kuis Ciri Tulisan" (Fase E): tebak aspek yang dikaitkan pakem grafologi dengan
 * satu ciri tulisan. Soal & kunci dari KB (Indikator -> Aspek), skor hanya di browser.
 */
const questions = ref([])
const index = ref(0)
const picked = ref(null)
const score = ref(0)
const loading = ref(true)
const error = ref('')

const current = computed(() => questions.value[index.value])
const done = computed(() => questions.value.length && index.value >= questions.value.length)

async function start() {
  loading.value = true
  error.value = ''
  index.value = 0
  score.value = 0
  picked.value = null
  try {
    questions.value = (await api.get('/games/quiz', { params: { n: 5 } })).data.questions
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memuat kuis.'
  } finally {
    loading.value = false
  }
}

function pick(id) {
  if (picked.value !== null) return
  picked.value = id
  if (id === current.value.answer_id) score.value++
}

function next() {
  picked.value = null
  index.value++
}

onMounted(start)
</script>

<template>
  <div class="quiz">
    <h1>Kuis Ciri Tulisan</h1>
    <p class="quiz__hint">
      Menurut pakem grafologi, ciri tulisan berikut paling terkait dengan aspek kepribadian yang
      mana? Soal diambil dari basis pengetahuan Guratan (nama ciri dalam bahasa Inggris, sesuai
      sumber).
    </p>

    <SkeletonLoader v-if="loading" variant="cards" :rows="1" label="Menyiapkan soal..." />
    <p v-else-if="error" class="error">{{ error }}</p>

    <section v-else-if="done" class="quiz__card quiz__done">
      <strong class="quiz__score">{{ score }} / {{ questions.length }}</strong>
      <p>
        {{
          score >= 4
            ? 'Mata grafolog! Anda cepat menangkap pola.'
            : 'Grafologi memang punya ratusan indikator - itu sebabnya grafolog mengukurnya dengan teliti.'
        }}
      </p>
      <div class="quiz__actions">
        <button type="button" class="btn btn--primary" @click="start">Main lagi</button>
        <RouterLink to="/cek-tulisan" class="btn">Cek tulisan Anda sendiri</RouterLink>
      </div>
    </section>

    <section v-else-if="current" class="quiz__card">
      <span class="quiz__progress"
        >Soal {{ index + 1 }} dari {{ questions.length }} · Skor {{ score }}</span
      >
      <p class="quiz__ciri">“{{ current.ciri }}”</p>
      <div class="quiz__options">
        <button
          v-for="o in current.options"
          :key="o.id"
          type="button"
          :class="[
            'quiz__option',
            {
              'quiz__option--right': picked !== null && o.id === current.answer_id,
              'quiz__option--wrong': picked === o.id && o.id !== current.answer_id,
            },
          ]"
          :disabled="picked !== null"
          @click="pick(o.id)"
        >
          {{ o.nama }} <span class="quiz__en">{{ o.nama_en }}</span>
        </button>
      </div>
      <template v-if="picked !== null">
        <p class="quiz__feedback">
          {{ picked === current.answer_id ? 'Tepat!' : 'Belum tepat.' }}
        </p>
        <details class="quiz__explain">
          <summary>Penjelasan dari sumber (bahasa Inggris)</summary>
          <p>{{ current.penjelasan_en }}</p>
        </details>
        <button type="button" class="btn btn--primary" @click="next">
          {{ index + 1 < questions.length ? 'Soal berikutnya' : 'Lihat skor' }}
        </button>
      </template>
    </section>
  </div>
</template>

<style scoped>
.quiz {
  max-width: 640px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.quiz__hint,
.quiz__progress,
.quiz__en {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 0;
}
.quiz__card {
  padding: 18px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.quiz__ciri {
  font-family: 'Segoe Script', 'Bradley Hand', cursive;
  font-size: 22px;
  margin: 0;
}
.quiz__options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 8px;
}
.quiz__option {
  padding: 12px;
  text-align: left;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-paper);
  color: var(--color-text);
  cursor: pointer;
  font-size: 14px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.quiz__option--right {
  border-color: var(--color-success);
  background: var(--color-sage-soft);
}
.quiz__option--wrong {
  border-color: var(--color-error);
  background: var(--color-seal-soft);
}
.quiz__feedback {
  font-weight: 600;
  margin: 0;
}
.quiz__explain summary {
  cursor: pointer;
  font-size: 13px;
  color: var(--color-text-soft);
}
.quiz__explain p {
  white-space: pre-line;
  font-size: 13px;
}
.quiz__card .btn {
  align-self: flex-start;
}
.quiz__done {
  align-items: flex-start;
}
.quiz__score {
  font-size: 40px;
}
.quiz__actions {
  display: flex;
  gap: 8px;
}
</style>
