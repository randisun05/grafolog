<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/lib/api'
import SindromAccordion from '@/components/scoring/SindromAccordion.vue'
import ProgressTracker from '@/components/shared/ProgressTracker.vue'
import AutoCalculationPanel from '@/components/scoring/AutoCalculationPanel.vue'

const router = useRouter()
const route = useRoute()
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

const showRegisterForm = ref(false)
const newClientName = ref('')
const newClientPassword = ref('')
const registering = ref(false)
const registerError = ref('')
const generatedPassword = ref('')
const submitting = ref(false)
const submitError = ref('')
const submittedReport = ref(null)

const preview = ref({ sindrom: [] })
const previewLoading = ref(false)
let previewTimer = null

const resuming = ref(false)
const resumeError = ref('')

onMounted(async () => {
  const { data } = await api.get('/sindrom')
  sindromList.value = data

  if (route.query.sampleId) {
    await resumeSample(route.query.sampleId)
  }
})

// Masuk lewat link "Ditugaskan ke Saya" (/portal-grafolog?sampleId=X) - sample
// dibuat HR/grafolog lain, bukan lewat lookup klien di langkah 1 di bawah.
// Lompat langsung ke langkah 2 (atau 3 kalau sudah pernah diisi).
async function resumeSample(sampleId) {
  resuming.value = true
  resumeError.value = ''
  try {
    const { data } = await api.get(`/samples/${sampleId}`)
    sample.value = data
    tier.value = data.tier
    client.value = data.user
    if (data.reports?.[0]?.status === 'completed') {
      submittedReport.value = data.reports[0]
    }
  } catch (e) {
    resumeError.value = e.response?.data?.message ?? 'Gagal memuat sample yang ditugaskan.'
  } finally {
    resuming.value = false
  }
}

function buildSkorPayload() {
  return Object.entries(scores.value)
    .filter(([, v]) => Number.isInteger(v?.skor))
    .map(([kode, v]) => ({ kode, skor: v.skor, catatan_grafolog: v.catatan_grafolog || null }))
}

// Kalkulasi otomatis di panel kanan Assessment Workspace: debounce 500ms
// biar tidak memanggil API tiap keystroke, lalu pratinjau (bukan submit)
// skor yang sudah terisi sejauh ini lewat ScoringController::preview.
watch(
  scores,
  () => {
    if (!sample.value || submittedReport.value) return
    clearTimeout(previewTimer)
    previewTimer = setTimeout(fetchPreview, 500)
  },
  { deep: true },
)

async function fetchPreview() {
  const skor = buildSkorPayload()
  if (skor.length === 0) {
    preview.value = { sindrom: [] }
    return
  }
  previewLoading.value = true
  try {
    const { data } = await api.post(`/samples/${sample.value.id}/scores/preview`, { skor })
    preview.value = data
  } catch {
    // Pratinjau gagal senyap - form pengisian skor utama tidak boleh terganggu.
  } finally {
    previewLoading.value = false
  }
}

async function lookupClient() {
  lookupError.value = ''
  client.value = null
  showRegisterForm.value = false
  generatedPassword.value = ''
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

function openRegisterForm() {
  showRegisterForm.value = true
  registerError.value = ''
  newClientName.value = ''
  newClientPassword.value = ''
}

// Klien walk-in yang belum pernah /register sendiri - grafolog daftarkan
// langsung dari sini. Password opsional (kosongkan = sistem generate acak,
// ditampilkan sekali lewat generatedPassword untuk disampaikan ke klien -
// lihat catatan di UserLookupController::store kenapa bukan email undangan).
async function registerClient() {
  registerError.value = ''
  registering.value = true
  try {
    const { data } = await api.post('/clients', {
      name: newClientName.value,
      email: clientEmail.value,
      password: newClientPassword.value || undefined,
    })
    client.value = { id: data.id, name: data.name, email: data.email }
    lookupError.value = ''
    showRegisterForm.value = false
    generatedPassword.value = data.generated_password ?? ''
  } catch (e) {
    registerError.value = e.response?.data?.message ?? 'Gagal mendaftarkan klien.'
  } finally {
    registering.value = false
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
    const { data } = await api.post(`/samples/${sample.value.id}/scores`, { skor: buildSkorPayload() })
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

    <p v-if="resuming" class="portal-grafolog__note">Memuat sample yang ditugaskan...</p>
    <p v-else-if="resumeError" class="error">{{ resumeError }}</p>

    <section v-else-if="!sample" class="portal-grafolog__step">
      <h2>1. Pilih Klien</h2>
      <div class="portal-grafolog__lookup">
        <input v-model="clientEmail" type="email" placeholder="Email klien" />
        <button type="button" class="btn" :disabled="lookupLoading" @click="lookupClient">
          Cari
        </button>
      </div>
      <p v-if="lookupError" class="error">{{ lookupError }}</p>

      <div v-if="lookupError && !client" class="portal-grafolog__register-trigger">
        <button v-if="!showRegisterForm" type="button" class="btn" @click="openRegisterForm">
          Klien belum terdaftar? Daftarkan sekarang
        </button>
        <div v-else class="portal-grafolog__register">
          <p class="portal-grafolog__register-note">
            Mendaftarkan <strong>{{ clientEmail }}</strong> sebagai klien baru.
          </p>
          <label>
            Nama Klien
            <input v-model="newClientName" type="text" placeholder="Nama lengkap klien" />
          </label>
          <label>
            Kata Sandi Awal (opsional)
            <input v-model="newClientPassword" type="text" placeholder="Kosongkan untuk dibuatkan otomatis" />
          </label>
          <p v-if="registerError" class="error">{{ registerError }}</p>
          <div class="portal-grafolog__register-actions">
            <button
              type="button"
              class="btn btn--primary"
              :disabled="!newClientName || registering"
              @click="registerClient"
            >
              {{ registering ? 'Mendaftarkan...' : 'Daftarkan Klien' }}
            </button>
            <button type="button" class="btn" @click="showRegisterForm = false">Batal</button>
          </div>
        </div>
      </div>

      <p v-if="generatedPassword" class="portal-grafolog__generated-password">
        Klien terdaftar. Kata sandi awal: <strong>{{ generatedPassword }}</strong> — sampaikan
        langsung ke klien, mereka bisa menggantinya nanti.
      </p>

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
      <p class="portal-grafolog__warning">
        ⚠ Progres tidak tersimpan otomatis — hindari refresh atau menutup halaman sebelum submit,
        isian skor akan hilang.
      </p>

      <div class="assessment-workspace">
        <aside class="assessment-workspace__col assessment-workspace__col--info">
          <div class="assessment-workspace__card">
            <h3>Klien</h3>
            <p>{{ client.name }}</p>
            <p class="assessment-workspace__meta">{{ client.email }}</p>
          </div>
          <div class="assessment-workspace__card">
            <h3>Sample</h3>
            <p class="assessment-workspace__meta">Tier: {{ tier }}</p>
            <p class="assessment-workspace__meta">Progress: {{ scoredCount }} / {{ totalAspek }} aspek</p>
          </div>
        </aside>

        <div class="assessment-workspace__col assessment-workspace__col--form">
          <SindromAccordion
            v-for="sindrom in sindromList"
            :key="sindrom.id"
            :sindrom="sindrom"
            :scores="scores"
            @update:scores="(v) => (scores = v)"
          />
        </div>

        <aside class="assessment-workspace__col assessment-workspace__col--calc">
          <AutoCalculationPanel :sindrom="preview.sindrom" :loading="previewLoading" />
        </aside>
      </div>

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
.portal-grafolog__register-trigger {
  margin-top: 10px;
}
.portal-grafolog__register {
  margin-top: 8px;
  padding: 14px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  max-width: 380px;
}
.portal-grafolog__register-note {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 12px;
}
.portal-grafolog__register label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
.portal-grafolog__register-actions {
  display: flex;
  gap: 8px;
  margin-top: 4px;
}
.portal-grafolog__generated-password {
  margin-top: 12px;
  padding: 10px 14px;
  background: var(--color-sage-soft);
  border: 1px solid var(--color-sage);
  border-radius: var(--radius-sm);
  color: var(--color-ink);
  font-size: 13.5px;
}
.portal-grafolog__tier {
  margin-top: 16px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
.portal-grafolog__note {
  color: var(--color-text-soft);
  margin-bottom: 16px;
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

.assessment-workspace {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr) 260px;
  gap: 20px;
  align-items: start;
}
.assessment-workspace__col--form {
  min-width: 0;
}
.assessment-workspace__card {
  padding: 14px;
  margin-bottom: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.assessment-workspace__card h3 {
  font-size: 13px;
  margin-bottom: 6px;
}
.assessment-workspace__meta {
  font-size: 12.5px;
  color: var(--color-text-soft);
}

@media (max-width: 900px) {
  .assessment-workspace {
    grid-template-columns: 1fr;
  }
  .assessment-workspace__col--calc .auto-calc {
    position: static;
  }
}
</style>
