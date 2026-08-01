<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import SindromAccordion from '@/components/scoring/SindromAccordion.vue'
import ProgressTracker from '@/components/shared/ProgressTracker.vue'

const router = useRouter()
const steps = ['Pilih Klien', 'Isi Skor', 'Laporan Selesai']
const currentStep = computed(() => {
  if (!sample.value) return 1
  if (!submittedReport.value) return 2
  return 3
})

const sindromList = ref([])
const totalAspek = computed(() => sindromList.value.reduce((n, s) => n + s.aspek.length, 0))

const clientEmail = ref('')
const client = ref(null)
const tier = ref('comprehensive')
const sample = ref(null)

const scores = ref({}) // { [kode]: { skor, catatan_grafolog } }
const scoredCount = computed(
  () => Object.values(scores.value).filter((s) => Number.isInteger(s?.skor)).length,
)
const isFormComplete = computed(() => totalAspek.value > 0 && scoredCount.value === totalAspek.value)

const lookupError = ref('')
const lookupLoading = ref(false)
const creatingSample = ref(false)
const submitting = ref(false)
const submitError = ref('')
const submittedReport = ref(null)

onMounted(async () => {
  const { data } = await api.get('/sindrom')
  sindromList.value = data
})

async function lookupClient() {
  lookupError.value = ''
  client.value = null
  lookupLoading.value = true
  try {
    const { data } = await api.get('/users/lookup', { params: { email: clientEmail.value } })
    client.value = data
  } catch (e) {
    lookupError.value = e.response?.data?.message ?? 'Klien tidak ditemukan.'
  } finally {
    lookupLoading.value = false
  }
}

async function createSample() {
  if (!client.value) return
  creatingSample.value = true
  try {
    const { data } = await api.post('/samples', {
      tier: tier.value,
      client_user_id: client.value.id,
    })
    sample.value = data
  } catch (e) {
    lookupError.value = e.response?.data?.message ?? 'Gagal membuat sample.'
  } finally {
    creatingSample.value = false
  }
}

async function submitScores() {
  if (!sample.value || !isFormComplete.value) return
  submitting.value = true
  submitError.value = ''
  try {
    const payload = {
      skor: Object.entries(scores.value).map(([kode, v]) => ({
        kode,
        skor: v.skor,
        catatan_grafolog: v.catatan_grafolog || null,
      })),
    }
    const { data } = await api.post(`/samples/${sample.value.id}/scores`, payload)
    submittedReport.value = data
  } catch (e) {
    submitError.value = e.response?.data?.message ?? 'Gagal mengirim skor.'
  } finally {
    submitting.value = false
  }
}

function viewReport() {
  router.push({ name: 'report', params: { id: submittedReport.value.id } })
}
</script>

<template>
  <div class="portal-grafolog">
    <h1>Portal Grafolog</h1>
    <ProgressTracker :steps="steps" :current="currentStep" />

    <section v-if="!sample" class="portal-grafolog__step">
      <h2>1. Pilih Klien</h2>
      <div class="portal-grafolog__lookup">
        <input v-model="clientEmail" type="email" placeholder="Email klien" />
        <button type="button" class="btn" :disabled="lookupLoading" @click="lookupClient">
          Cari
        </button>
      </div>
      <p v-if="lookupError" class="error">{{ lookupError }}</p>
      <p v-if="client" class="portal-grafolog__client-found">
        Klien ditemukan: <strong>{{ client.name }}</strong> ({{ client.email }})
      </p>

      <div v-if="client" class="portal-grafolog__tier">
        <label>
          Tier:
          <select v-model="tier">
            <option value="comprehensive">Comprehensive</option>
            <option value="master">Master</option>
          </select>
        </label>
        <button type="button" class="btn btn--primary" :disabled="creatingSample" @click="createSample">
          {{ creatingSample ? 'Membuat...' : 'Buat Sample & Mulai Isi Skor' }}
        </button>
      </div>
    </section>

    <section v-else-if="!submittedReport" class="portal-grafolog__step">
      <h2>2. Isi Skor 40 Aspek</h2>
      <p class="portal-grafolog__progress">{{ scoredCount }} / {{ totalAspek }} aspek terisi</p>
      <p class="portal-grafolog__warning">
        ⚠ Progres tidak tersimpan otomatis — hindari refresh atau menutup halaman sebelum submit,
        isian skor akan hilang.
      </p>

      <SindromAccordion
        v-for="sindrom in sindromList"
        :key="sindrom.id"
        :sindrom="sindrom"
        :scores="scores"
        @update:scores="(v) => (scores = v)"
      />

      <p v-if="submitError" class="error">{{ submitError }}</p>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="!isFormComplete || submitting"
        @click="submitScores"
      >
        {{ submitting ? 'Mengirim...' : 'Kirim & Buat Laporan' }}
      </button>
    </section>

    <section v-else class="portal-grafolog__step">
      <h2>Laporan Berhasil Dibuat</h2>
      <button type="button" class="btn btn--primary" @click="viewReport">Lihat Laporan</button>
    </section>
  </div>
</template>

<style scoped>
.portal-grafolog {
  max-width: 100%;
}
.portal-grafolog__step {
  margin-bottom: 24px;
}
.portal-grafolog__lookup {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.portal-grafolog__lookup input {
  flex: 1;
  min-width: 200px;
  margin-top: 0;
}
.portal-grafolog__client-found {
  color: var(--color-success);
  margin-top: 8px;
}
.portal-grafolog__tier {
  margin-top: 16px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
.portal-grafolog__progress {
  color: var(--color-text-soft);
  font-size: 13px;
  margin-bottom: 8px;
}
.portal-grafolog__warning {
  background: var(--color-seal-soft);
  border: 1px solid var(--color-seal);
  color: var(--color-seal-dark);
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 13px;
  margin-bottom: 16px;
}
</style>
