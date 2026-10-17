<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import HandwritingSample from '@/components/engagement/HandwritingSample.vue'
import { formatRupiah } from '@/lib/format'

/**
 * Cek tulisan terpandu (lead magnet, Fase E): pengguna menilai ciri tulisannya sendiri,
 * hasil = aspek KB yang terkait. Tanpa skor - skor hanya dari pengukuran grafolog (Master).
 */
const props = defineProps({ uuid: { type: String, default: null } })
const router = useRouter()
const auth = useAuthStore()

const questions = ref([])
const answers = ref({})
const step = ref(0)
const email = ref('')
const consent = ref(false)
const result = ref(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')

const current = computed(() => questions.value[step.value])
const answeredCount = computed(() => Object.keys(answers.value).length)
const isLast = computed(() => step.value === questions.value.length - 1)

async function loadResult(uuid) {
  loading.value = true
  try {
    result.value = (await api.get(`/self-check/${uuid}`)).data
  } catch {
    error.value = 'Hasil tidak ditemukan.'
  } finally {
    loading.value = false
  }
}

async function loadQuestions() {
  loading.value = true
  try {
    questions.value = (await api.get('/self-check')).data
  } catch {
    error.value = 'Gagal memuat pertanyaan.'
  } finally {
    loading.value = false
  }
}

function choose(optionId) {
  answers.value = { ...answers.value, [current.value.id]: optionId }
  if (!isLast.value) step.value++
}

async function submit() {
  submitting.value = true
  error.value = ''
  try {
    const { data } = await api.post('/self-check', {
      answers: answers.value,
      email: email.value || null,
      consent: consent.value,
    })
    result.value = data
    router.replace({ name: 'self-check-result', params: { uuid: data.uuid } })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal memproses jawaban.'
  } finally {
    submitting.value = false
  }
}

function restart() {
  answers.value = {}
  step.value = 0
  result.value = null
  router.push({ name: 'self-check' })
  if (!questions.value.length) loadQuestions()
}

watch(
  () => props.uuid,
  (uuid) => {
    if (uuid && result.value?.uuid !== uuid) loadResult(uuid)
  },
)

onMounted(() => (props.uuid ? loadResult(props.uuid) : loadQuestions()))
</script>

<template>
  <div class="sc">
    <SkeletonLoader v-if="loading" variant="cards" :rows="2" label="Memuat..." />
    <p v-else-if="error && !questions.length && !result" class="error">{{ error }}</p>

    <!-- Hasil -->
    <section v-else-if="result" class="sc__result">
      <span class="eyebrow">Hasil cek tulisan</span>
      <h1>Ciri tulisan Anda menyentuh {{ result.aspek.length }} aspek kepribadian</h1>
      <p class="sc__disclaimer">{{ result.disclaimer }}</p>

      <p v-if="!result.aspek.length" class="sc__hint">
        Jawaban Anda belum terhubung ke aspek mana pun. Coba jawab lebih banyak pertanyaan.
      </p>
      <article v-for="a in result.aspek" :key="a.id" class="sc__aspek">
        <span class="sc__sindrom">{{ a.sindrom }}</span>
        <h2>{{ a.nama }}</h2>
        <p v-if="a.keterangan">{{ a.keterangan }}</p>
        <ul class="sc__ciri">
          <li v-for="(c, i) in a.ciri" :key="i">
            <strong>{{ c.jawaban }}</strong> <span class="sc__hint">— {{ c.pertanyaan }}</span>
            <details>
              <summary>Rujukan pakem grafologi ({{ c.indikator }}, bahasa Inggris)</summary>
              <p class="sc__source">{{ c.keterangan_en }}</p>
            </details>
          </li>
        </ul>
      </article>

      <div v-if="result.cta" class="sc__cta">
        <div>
          <strong>Ingin hasil yang diukur, bukan ditebak?</strong>
          <p class="sc__hint">
            Di {{ result.cta.nama }}, grafolog bersertifikat mengukur 37 variabel tulisan Anda dari
            scan dan menyusun laporan 40 aspek lengkap dengan skor, plus sesi konsultasi.
          </p>
        </div>
        <RouterLink
          :to="auth.isAuthenticated ? `/pesan?produk=${result.cta.kode}` : '/register'"
          class="btn btn--primary"
        >
          Pesan {{ result.cta.nama }} · {{ formatRupiah(result.cta.price) }}
        </RouterLink>
      </div>
      <div class="sc__links">
        <button type="button" class="btn" @click="restart">Ulangi cek</button>
        <RouterLink to="/kuis" class="btn">Main kuis ciri tulisan</RouterLink>
        <RouterLink to="/artikel" class="btn">Baca artikel</RouterLink>
      </div>
    </section>

    <!-- Pertanyaan -->
    <section v-else-if="current" class="sc__quiz">
      <span class="eyebrow">Cek tulisan gratis · {{ step + 1 }}/{{ questions.length }}</span>
      <div class="sc__progress">
        <span :style="{ width: `${((step + 1) / questions.length) * 100}%` }"></span>
      </div>
      <h1>{{ current.pertanyaan }}</h1>
      <p v-if="current.petunjuk" class="sc__hint">{{ current.petunjuk }}</p>

      <div class="sc__options">
        <button
          v-for="o in current.options"
          :key="o.id"
          type="button"
          :class="['sc__option', { 'sc__option--on': answers[current.id] === o.id }]"
          @click="choose(o.id)"
        >
          <HandwritingSample :token="o.ilustrasi" />
          <span>{{ o.label }}</span>
        </button>
      </div>

      <div class="sc__nav">
        <button type="button" class="btn" :disabled="step === 0" @click="step--">Kembali</button>
        <button v-if="!isLast" type="button" class="btn" @click="step++">Lewati</button>
      </div>

      <div v-if="isLast" class="sc__finish">
        <label class="sc__field"
          >Email (opsional, untuk menyimpan hasil & menerima info promo)
          <input v-model="email" type="email" placeholder="nama@email.com"
        /></label>
        <label v-if="email" class="sc__consent">
          <input v-model="consent" type="checkbox" />
          Saya setuju email saya disimpan Guratan sesuai
          <RouterLink to="/privasi" target="_blank">Kebijakan Privasi</RouterLink>.
        </label>
        <p v-if="error" class="error">{{ error }}</p>
        <button
          type="button"
          class="btn btn--primary"
          :disabled="submitting || answeredCount < 3"
          @click="submit"
        >
          {{ submitting ? 'Memproses...' : `Lihat hasil (${answeredCount} dijawab)` }}
        </button>
        <p v-if="answeredCount < 3" class="sc__hint">Jawab minimal 3 pertanyaan.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.eyebrow {
  font-family: var(--font-accent);
  font-size: 18px;
  color: var(--color-seal);
}
.sc {
  max-width: 760px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.sc h1 {
  font-size: 26px;
  margin: 6px 0;
}
.sc__hint {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin: 0;
}
.sc__progress {
  height: 4px;
  border-radius: 4px;
  background: var(--color-border);
  overflow: hidden;
  margin-top: 8px;
}
.sc__progress span {
  display: block;
  height: 100%;
  background: var(--color-seal);
  transition: width 0.2s;
}
.sc__options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
  margin-top: 12px;
}
.sc__option {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  text-align: left;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  font-size: 14px;
}
.sc__option:hover {
  border-color: var(--color-border-hover);
}
.sc__option--on {
  border-color: var(--color-seal);
  box-shadow: 0 0 0 1px var(--color-seal);
}
.sc__nav {
  display: flex;
  gap: 8px;
}
.sc__finish {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.sc__field {
  font-size: 13px;
  color: var(--color-text-soft);
}
.sc__consent {
  display: flex;
  gap: 8px;
  font-size: 13px;
  align-items: flex-start;
}
.sc__consent input {
  width: auto;
  margin-top: 3px;
}
.sc__finish .btn {
  align-self: flex-start;
}
.sc__disclaimer {
  margin: 0;
  padding: 10px 12px;
  border-left: 3px solid var(--color-gold);
  background: var(--color-gold-soft);
  font-size: 13px;
  border-radius: var(--radius-sm);
}
.sc__aspek {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.sc__aspek h2 {
  font-size: 19px;
  margin: 2px 0 6px;
}
.sc__aspek p {
  font-size: 14px;
  line-height: 1.6;
}
.sc__sindrom {
  font-size: 11.5px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-seal);
  font-weight: 600;
}
.sc__ciri {
  margin: 8px 0 0;
  padding-left: 18px;
  font-size: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.sc__ciri summary {
  cursor: pointer;
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.sc__source {
  white-space: pre-line;
  font-size: 13px !important;
  color: var(--color-text-soft);
}
.sc__cta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  padding: 18px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-seal);
  background: var(--color-seal-soft);
}
.sc__links {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
