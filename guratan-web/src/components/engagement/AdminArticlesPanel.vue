<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import { formatTanggal } from '@/lib/format'

/** Artikel informasi: teks polos, paragraf dipisah baris kosong. */
const toast = useToast()
const articles = ref([])
const editing = ref(null)
const form = ref({})

async function load() {
  articles.value = (await api.get('/admin/articles')).data
}

function startNew() {
  editing.value = 'new'
  form.value = { judul: '', ringkasan: '', isi: '', kategori: 'umum', is_published: false }
}

function startEdit(a) {
  editing.value = a.id
  form.value = {
    judul: a.judul,
    ringkasan: a.ringkasan ?? '',
    isi: a.isi,
    kategori: a.kategori,
    is_published: a.is_published,
  }
}

async function save() {
  try {
    if (editing.value === 'new') await api.post('/admin/articles', form.value)
    else await api.put(`/admin/articles/${editing.value}`, form.value)
    toast.push('Artikel disimpan.', 'success')
    editing.value = null
    await load()
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(errors ? Object.values(errors).flat()[0] : 'Gagal menyimpan artikel.')
  }
}

onMounted(load)
</script>

<template>
  <div class="aar">
    <button
      v-if="editing === null"
      type="button"
      class="btn btn--primary aar__new"
      @click="startNew"
    >
      + Artikel baru
    </button>
    <form v-else class="aar__form" @submit.prevent="save">
      <input v-model="form.judul" type="text" placeholder="Judul" required />
      <input v-model="form.ringkasan" type="text" placeholder="Ringkasan" />
      <input v-model="form.kategori" type="text" placeholder="Kategori (mis. panduan)" />
      <textarea
        v-model="form.isi"
        rows="12"
        placeholder="Isi - pisahkan paragraf dengan baris kosong"
        required
      ></textarea>
      <label class="aar__toggle"
        ><input v-model="form.is_published" type="checkbox" /> Terbitkan</label
      >
      <div class="aar__buttons">
        <button type="submit" class="btn btn--primary">Simpan</button>
        <button type="button" class="btn" @click="editing = null">Batal</button>
      </div>
    </form>
    <ul class="aar__list">
      <li v-for="a in articles" :key="a.id">
        <div>
          <strong>{{ a.judul }}</strong>
          <span class="aar__meta"
            >{{ a.kategori }} ·
            {{ a.is_published ? `terbit ${formatTanggal(a.published_at)}` : 'draf' }} · /artikel/{{
              a.slug
            }}</span
          >
        </div>
        <button type="button" class="btn" @click="startEdit(a)">Ubah</button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.aar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.aar__new {
  align-self: flex-start;
}
.aar__form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.aar__form input,
.aar__form textarea {
  margin: 0;
}
.aar__toggle {
  display: flex;
  gap: 6px;
  align-items: center;
  font-size: 13px;
}
.aar__toggle input {
  width: auto;
}
.aar__buttons {
  display: flex;
  gap: 8px;
}
.aar__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.aar__list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.aar__meta {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
}
</style>
