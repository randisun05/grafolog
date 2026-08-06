<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const fields = [
  { key: 'landing_eyebrow', label: 'Eyebrow (teks kecil di atas judul)', type: 'text' },
  { key: 'landing_tagline', label: 'Tagline', type: 'textarea' },
  { key: 'landing_cta_label', label: 'Label Tombol Utama', type: 'text' },
]

const values = ref({})
const loading = ref(true)
const loadError = ref('')
const saving = ref({})

async function loadContent() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/content')
    values.value = Object.fromEntries(data.map((b) => [b.key, b.value ?? '']))
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat konten.'
  } finally {
    loading.value = false
  }
}

async function save(key) {
  saving.value = { ...saving.value, [key]: true }
  try {
    await api.put(`/admin/content/${key}`, { value: values.value[key] })
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
      dibatasi (bukan editor bebas).
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
        <input v-else :id="field.key" v-model="values[field.key]" type="text" />
        <button type="button" class="btn btn--primary" :disabled="saving[field.key]" @click="save(field.key)">
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
</style>
