<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

/** Produk mitra/afiliasi eksternal + jumlah klik. */
const toast = useToast()
const items = ref([])
const blank = () => ({
  nama: '',
  mitra: '',
  ringkasan: '',
  url: '',
  image_url: '',
  harga: '',
  kategori: 'lainnya',
  target: 'semua',
})
const form = ref(blank())

async function load() {
  items.value = (await api.get('/admin/affiliate-products')).data
}

async function create() {
  try {
    await api.post('/admin/affiliate-products', form.value)
    form.value = blank()
    toast.push('Produk mitra ditambahkan.', 'success')
    await load()
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(errors ? Object.values(errors).flat()[0] : 'Gagal menyimpan.')
  }
}

async function save(p, patch = {}) {
  try {
    const { data } = await api.put(`/admin/affiliate-products/${p.id}`, {
      nama: p.nama,
      mitra: p.mitra,
      ringkasan: p.ringkasan,
      url: p.url,
      image_url: p.image_url || null,
      harga: p.harga || null,
      kategori: p.kategori,
      target: p.target,
      ...patch,
    })
    Object.assign(p, data)
    toast.push('Disimpan.', 'success')
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(errors ? Object.values(errors).flat()[0] : 'Gagal menyimpan.')
  }
}

onMounted(load)
</script>

<template>
  <div class="aaf">
    <p class="aaf__hint">
      Ditampilkan di dashboard klien/HR dengan label pengungkapan afiliasi. Tautan wajib https.
    </p>
    <form class="aaf__form" @submit.prevent="create">
      <input v-model="form.nama" type="text" placeholder="Nama produk" required />
      <input v-model="form.mitra" type="text" placeholder="Nama mitra" required />
      <input v-model="form.url" type="text" placeholder="https://..." required />
      <select v-model="form.target" aria-label="Target">
        <option value="semua">Semua</option>
        <option value="b2c">Klien individu</option>
        <option value="b2b">Perusahaan (HR)</option>
      </select>
      <input v-model="form.image_url" type="text" placeholder="URL gambar https (opsional)" />
      <input v-model="form.harga" type="text" placeholder="Harga tampilan, mis. Rp 89.000" />
      <input v-model="form.kategori" type="text" placeholder="Kategori" />
      <input
        v-model="form.ringkasan"
        type="text"
        placeholder="Ringkasan (opsional)"
        class="aaf__wide"
      />
      <button type="submit" class="btn btn--primary">Tambah</button>
    </form>
    <div v-for="p in items" :key="p.id" class="aaf__item">
      <input v-model="p.nama" type="text" :aria-label="`Nama ${p.id}`" />
      <input v-model="p.mitra" type="text" :aria-label="`Mitra ${p.id}`" />
      <input v-model="p.url" type="text" :aria-label="`URL ${p.id}`" />
      <input
        v-model="p.image_url"
        type="text"
        placeholder="URL gambar"
        :aria-label="`Gambar ${p.id}`"
      />
      <input v-model="p.harga" type="text" placeholder="Harga" :aria-label="`Harga ${p.id}`" />
      <input v-model="p.kategori" type="text" :aria-label="`Kategori ${p.id}`" />
      <select v-model="p.target" :aria-label="`Target ${p.id}`">
        <option value="semua">Semua</option>
        <option value="b2c">Klien</option>
        <option value="b2b">HR</option>
      </select>
      <span class="aaf__hint">{{ p.clicks_count }} klik</span>
      <label class="aaf__toggle"
        ><input
          type="checkbox"
          :checked="p.is_active"
          @change="save(p, { is_active: $event.target.checked })"
        />
        Aktif</label
      >
      <button type="button" class="btn" @click="save(p)">Simpan</button>
    </div>
  </div>
</template>

<style scoped>
.aaf {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.aaf__hint {
  color: var(--color-text-soft);
  font-size: 12.5px;
  margin: 0;
}
.aaf__form,
.aaf__item {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
  align-items: center;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.aaf input,
.aaf select {
  margin: 0;
}
.aaf__toggle {
  display: flex;
  gap: 4px;
  align-items: center;
  font-size: 13px;
}
.aaf__toggle input {
  width: auto;
}
</style>
