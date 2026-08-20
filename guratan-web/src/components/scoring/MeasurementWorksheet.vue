<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'

const props = defineProps({
  sampleId: { type: [Number, String], required: true },
})
const emit = defineEmits(['saved'])

const variables = ref([]) // [{ id, nama, axis, kategori: [...] }]
const nilai = ref({}) // { [variable_id]: number|string }
const nilaiMin = ref({}) // { [variable_id]: number|string } - dipakai rule irregularity (selisih maks-min)
const nilaiMax = ref({})
const loading = ref(true)
const saving = ref(false)
const saveError = ref('')
const savedAt = ref(null)

onMounted(async () => {
  try {
    const [{ data: vars }, { data: existing }] = await Promise.all([
      api.get('/measurement-variables'),
      api.get(`/samples/${props.sampleId}/measurements`),
    ])
    variables.value = vars
    for (const reading of existing) {
      nilai.value[reading.variable_id] = reading.nilai
      nilaiMin.value[reading.variable_id] = reading.nilai_min
      nilaiMax.value[reading.variable_id] = reading.nilai_max
    }
  } finally {
    loading.value = false
  }
})

function toNumberOrNull(raw) {
  return raw === '' || raw === null || raw === undefined ? null : Number(raw)
}

async function save() {
  // Kirim SEMUA variabel, bukan cuma yang terisi - field yang dikosongkan
  // grafolog harus terkirim sebagai nilai:null supaya backend menghapus
  // hasil ukur lamanya, bukan diam-diam dibiarkan basi (bug ditemukan
  // lewat review 2026-08-08: sebelumnya field kosong difilter keluar dari
  // payload sama sekali, jadi "Tersimpan" tapi nilai lama tidak terhapus).
  const pengukuran = variables.value.map((v) => ({
    variable_id: v.id,
    nilai: toNumberOrNull(nilai.value[v.id]),
    nilai_min: toNumberOrNull(nilaiMin.value[v.id]),
    nilai_max: toNumberOrNull(nilaiMax.value[v.id]),
  }))
  if (pengukuran.length === 0) return

  saving.value = true
  saveError.value = ''
  try {
    await api.post(`/samples/${props.sampleId}/measurements`, { pengukuran })
    savedAt.value = new Date()
    emit('saved')
  } catch (e) {
    saveError.value = e.response?.data?.message ?? 'Gagal menyimpan hasil ukur.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="measurement-worksheet">
    <h3>Measurement Worksheet</h3>
    <p class="measurement-worksheet__hint">
      Isi hasil ukur fisik (kaliper) untuk variabel yang relevan. Tidak perlu semua variabel -
      Indikator yang punya aturan operator akan otomatis tercentang di langkah berikutnya begitu
      variabelnya terisi. Kolom <strong>Min</strong>/<strong>Maks</strong> opsional - isi kalau ukurannya
      bervariasi di tulisan (dipakai rule "irregular", dihitung dari selisih maks-min).
    </p>

    <p v-if="loading" class="measurement-worksheet__note">Memuat variabel ukur...</p>
    <template v-else>
      <div class="measurement-worksheet__grid">
        <div v-for="v in variables" :key="v.id" class="measurement-worksheet__field">
          <span class="measurement-worksheet__label">{{ v.nama }}</span>
          <div class="measurement-worksheet__inputs">
            <input v-model="nilai[v.id]" type="number" step="0.01" placeholder="nilai" title="Nilai" />
            <input v-model="nilaiMin[v.id]" type="number" step="0.01" placeholder="min" title="Min (untuk rentang)" />
            <input v-model="nilaiMax[v.id]" type="number" step="0.01" placeholder="maks" title="Maks (untuk rentang)" />
          </div>
        </div>
      </div>

      <p v-if="saveError" class="error">{{ saveError }}</p>
      <div class="measurement-worksheet__actions">
        <button type="button" class="btn btn--primary" :disabled="saving" @click="save">
          {{ saving ? 'Menyimpan...' : 'Simpan Hasil Ukur' }}
        </button>
        <span v-if="savedAt" class="measurement-worksheet__saved">Tersimpan.</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.measurement-worksheet {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  margin-bottom: 16px;
}
.measurement-worksheet h3 {
  font-size: 15px;
  margin-bottom: 6px;
}
.measurement-worksheet__hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.measurement-worksheet__note {
  color: var(--color-text-soft);
  font-size: 13px;
}
.measurement-worksheet__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px 14px;
}
.measurement-worksheet__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: var(--color-text-soft);
}
.measurement-worksheet__field input {
  margin-top: 0;
}
.measurement-worksheet__inputs {
  display: flex;
  gap: 4px;
}
.measurement-worksheet__inputs input {
  min-width: 0;
  flex: 1;
}
.measurement-worksheet__inputs input:first-child {
  flex: 1.4;
}
.measurement-worksheet__label {
  line-height: 1.3;
}
.measurement-worksheet__actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
}
.measurement-worksheet__saved {
  font-size: 12.5px;
  color: var(--color-success);
}
</style>
