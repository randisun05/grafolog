<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

// type: 'text' | 'textarea' | 'list-string' (array of string, count tetap) |
// 'list-object' (array of {title, desc}, count tetap). Field tetap by design
// (bukan page-builder bebas) - lihat ContentBlock::EDITABLE_KEYS/LIST_KEYS.
const fields = [
  { key: 'landing_eyebrow', label: 'Eyebrow (teks kecil di atas judul)', type: 'text' },
  { key: 'landing_hero_title', label: 'Judul Hero (H1)', type: 'textarea' },
  { key: 'landing_tagline', label: 'Tagline', type: 'textarea' },
  { key: 'landing_cta_label', label: 'Label Tombol Utama', type: 'text' },
  { key: 'landing_hero_trust', label: 'Baris Kepercayaan (di bawah tombol hero)', type: 'text' },

  { key: 'landing_compare_heading', label: 'Judul Section Perbandingan', type: 'text' },
  { key: 'landing_compare_subtext', label: 'Subteks Perbandingan', type: 'textarea' },
  { key: 'landing_compare_old', label: '"Cara Lama" - 3 poin', type: 'list-string', count: 3 },
  { key: 'landing_compare_new', label: '"Cara Guratan" - 3 poin', type: 'list-string', count: 3 },

  { key: 'landing_steps_heading', label: 'Judul Section Cara Kerja', type: 'text' },
  { key: 'landing_steps_subtext', label: 'Subteks Cara Kerja', type: 'textarea' },
  { key: 'landing_steps', label: 'Langkah Cara Kerja - 4 langkah', type: 'list-object', count: 4 },

  { key: 'landing_analysis_heading', label: 'Judul Section Kedalaman Analisis', type: 'text' },
  { key: 'landing_analysis_subtext', label: 'Subteks Kedalaman Analisis', type: 'textarea' },

  { key: 'landing_pricing_heading', label: 'Judul Section Harga', type: 'text' },
  { key: 'landing_pricing_subtext', label: 'Subteks Harga', type: 'textarea' },

  { key: 'landing_honesty_quote', label: 'Kutipan "Kejujuran Kami"', type: 'textarea' },
  { key: 'landing_honesty_points', label: 'Poin Kejujuran - 3 poin', type: 'list-object', count: 3 },

  { key: 'landing_signup_heading', label: 'Judul Section Cara Daftar', type: 'text' },

  { key: 'landing_cta_band_heading', label: 'Judul CTA Penutup', type: 'text' },
  { key: 'landing_cta_band_subtext', label: 'Subteks CTA Penutup', type: 'textarea' },
]

const values = ref({})
const listValues = ref({})
const loading = ref(true)
const loadError = ref('')
const saving = ref({})

function emptyListValue(field) {
  return field.type === 'list-object'
    ? Array.from({ length: field.count }, () => ({ title: '', desc: '' }))
    : Array.from({ length: field.count }, () => '')
}

async function loadContent() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/content')
    const byKey = Object.fromEntries(data.map((b) => [b.key, b.value ?? '']))
    values.value = byKey

    for (const field of fields) {
      if (field.type !== 'list-string' && field.type !== 'list-object') continue
      let parsed = null
      try {
        parsed = byKey[field.key] ? JSON.parse(byKey[field.key]) : null
      } catch {
        parsed = null
      }
      listValues.value[field.key] = Array.isArray(parsed) && parsed.length === field.count ? parsed : emptyListValue(field)
    }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat konten.'
  } finally {
    loading.value = false
  }
}

async function save(field) {
  const key = field.key
  saving.value = { ...saving.value, [key]: true }
  try {
    const value =
      field.type === 'list-string' || field.type === 'list-object'
        ? JSON.stringify(listValues.value[key])
        : values.value[key]
    await api.put(`/admin/content/${key}`, { value })
    toast.push('Konten berhasil disimpan.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan konten.')
  } finally {
    saving.value = { ...saving.value, [key]: false }
  }
}

onMounted(loadContent)
</script>

<template>
  <div class="admin-content">
    <h1>Kelola Konten Homepage</h1>
    <p class="admin-content__note">
      Perubahan langsung tampil di halaman utama setelah disimpan. Field yang tersedia sengaja
      dibatasi (bukan editor bebas) - setiap section punya jumlah item tetap.
    </p>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <div v-else class="admin-content__fields">
      <div v-for="field in fields" :key="field.key" class="admin-content__field">
        <label :for="field.key">{{ field.label }}</label>

        <textarea
          v-if="field.type === 'textarea'"
          :id="field.key"
          v-model="values[field.key]"
          rows="3"
        ></textarea>
        <input v-else-if="field.type === 'text'" :id="field.key" v-model="values[field.key]" type="text" />

        <div v-else-if="field.type === 'list-string'" class="admin-content__list">
          <input
            v-for="(item, i) in listValues[field.key]"
            :key="i"
            v-model="listValues[field.key][i]"
            type="text"
            :placeholder="`Poin ${i + 1}`"
          />
        </div>

        <div v-else-if="field.type === 'list-object'" class="admin-content__list">
          <div v-for="(item, i) in listValues[field.key]" :key="i" class="admin-content__list-item">
            <input v-model="item.title" type="text" :placeholder="`Judul ${i + 1}`" />
            <textarea v-model="item.desc" rows="2" :placeholder="`Deskripsi ${i + 1}`"></textarea>
          </div>
        </div>

        <button type="button" class="btn btn--primary" :disabled="saving[field.key]" @click="save(field)">
          {{ saving[field.key] ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-content {
  max-width: 640px;
}
.admin-content__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-content__fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.admin-content__field {
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-content__field label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 6px;
}
.admin-content__field input,
.admin-content__field textarea {
  margin-bottom: 10px;
  font-family: inherit;
}
.admin-content__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 10px;
}
.admin-content__list-item {
  padding: 10px;
  border: 1px dashed var(--color-border-hover);
  border-radius: var(--radius-sm);
}
.admin-content__list-item input {
  margin-bottom: 6px;
  font-weight: 600;
}
.admin-content__list-item textarea {
  margin-bottom: 0;
}
</style>
