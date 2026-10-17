<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

const emit = defineEmits(['changed'])
const toast = useToast()

const positions = ref([])
const sindrom = ref([])
const loading = ref(true)
const editing = ref(null) // null | 'new' | position id
const form = ref({ title: '', description: '', key_aspek_ids: [], is_active: true })
const saving = ref(false)
const MAX_KEY = 10

async function load() {
  loading.value = true
  try {
    const [p, s] = await Promise.all([api.get('/hr/positions'), api.get('/sindrom')])
    positions.value = p.data
    sindrom.value = s.data
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memuat posisi.')
  } finally {
    loading.value = false
  }
}

function startNew() {
  editing.value = 'new'
  form.value = { title: '', description: '', key_aspek_ids: [], is_active: true }
}

function startEdit(p) {
  editing.value = p.id
  form.value = {
    title: p.title,
    description: p.description ?? '',
    key_aspek_ids: [...(p.key_aspek_ids ?? [])],
    is_active: p.is_active,
  }
}

function toggleAspek(id) {
  const ids = form.value.key_aspek_ids
  if (ids.includes(id)) form.value.key_aspek_ids = ids.filter((x) => x !== id)
  else if (ids.length < MAX_KEY) form.value.key_aspek_ids = [...ids, id]
  else toast.push(`Maksimal ${MAX_KEY} aspek kunci.`)
}

async function save() {
  saving.value = true
  try {
    const body = { ...form.value, description: form.value.description || null }
    if (editing.value === 'new') await api.post('/hr/positions', body)
    else await api.put(`/hr/positions/${editing.value}`, body)
    toast.push('Posisi disimpan.', 'success')
    editing.value = null
    await load()
    emit('changed')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan posisi.')
  } finally {
    saving.value = false
  }
}

const aspekName = (a) => a.nama_id || a.nama

onMounted(load)
</script>

<template>
  <div class="hrp">
    <div class="hrp__head">
      <p class="hrp__hint">
        Aspek kunci menentukan kolom yang ditonjolkan saat membandingkan kandidat - bukan skor
        kecocokan otomatis.
      </p>
      <button v-if="editing === null" type="button" class="btn btn--primary" @click="startNew">
        + Posisi baru
      </button>
    </div>

    <form v-if="editing !== null" class="hrp__form" @submit.prevent="save">
      <label for="pos-title">Nama posisi</label>
      <input id="pos-title" v-model="form.title" type="text" required />
      <label for="pos-desc">Deskripsi / tanggung jawab (opsional)</label>
      <textarea id="pos-desc" v-model="form.description" rows="2"></textarea>
      <label class="hrp__check"
        ><input v-model="form.is_active" type="checkbox" /> Posisi masih dibuka</label
      >
      <p class="hrp__label">Aspek kunci ({{ form.key_aspek_ids.length }}/{{ MAX_KEY }})</p>
      <div class="hrp__sindrom-list">
        <fieldset v-for="s in sindrom" :key="s.id" class="hrp__sindrom">
          <legend>{{ s.kode_romawi }}. {{ s.nama_id || s.nama }}</legend>
          <button
            v-for="a in s.aspek"
            :key="a.id"
            type="button"
            :class="['hrp__chip', { 'hrp__chip--on': form.key_aspek_ids.includes(a.id) }]"
            :aria-pressed="form.key_aspek_ids.includes(a.id)"
            @click="toggleAspek(a.id)"
          >
            {{ aspekName(a) }}
          </button>
        </fieldset>
      </div>
      <div class="hrp__buttons">
        <button type="submit" class="btn btn--primary" :disabled="saving">Simpan</button>
        <button type="button" class="btn" @click="editing = null">Batal</button>
      </div>
    </form>

    <SkeletonLoader v-if="loading" variant="list" :rows="2" label="Memuat posisi..." />
    <p v-else-if="!positions.length" class="hrp__hint">Belum ada posisi.</p>
    <ul v-else class="hrp__list">
      <li v-for="p in positions" :key="p.id" class="hrp__item">
        <div>
          <strong>{{ p.title }}</strong>
          <span v-if="!p.is_active" class="hrp__closed">ditutup</span>
          <p class="hrp__meta">
            {{ p.candidates_count }} kandidat · {{ (p.key_aspek_ids ?? []).length }} aspek kunci
          </p>
          <p v-if="p.description" class="hrp__meta">{{ p.description }}</p>
        </div>
        <button type="button" class="btn" @click="startEdit(p)">Ubah</button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.hrp {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hrp__head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}
.hrp__hint,
.hrp__meta {
  color: var(--color-text-soft);
  font-size: 13px;
  margin: 2px 0 0;
}
.hrp__form,
.hrp__item {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.hrp__form {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.hrp__form label,
.hrp__label {
  font-size: 13px;
  color: var(--color-text-soft);
  margin: 6px 0 0;
}
.hrp__check {
  display: flex;
  gap: 6px;
  align-items: center;
}
.hrp__check input {
  width: auto;
}
.hrp__sindrom-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 10px;
}
.hrp__sindrom {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.hrp__sindrom legend {
  font-size: 12.5px;
  font-weight: 600;
  padding: 0 4px;
}
.hrp__chip {
  border: 1px solid var(--color-border);
  background: var(--color-paper);
  color: var(--color-text);
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 12.5px;
  cursor: pointer;
}
.hrp__chip--on {
  background: var(--color-ink);
  border-color: var(--color-ink);
  color: var(--color-paper);
}
.hrp__buttons {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}
.hrp__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hrp__item {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}
.hrp__closed {
  margin-left: 8px;
  font-size: 12px;
  color: var(--color-text-soft);
}
</style>
