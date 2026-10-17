<script setup>
// Kelola Produk (2026-10-17): dasar harga (riwayat di pricing_plans per kode produk) dan diskon
// (kode promo memilih produk). Tiap produk punya komponen; komponen non-grafologi punya template
// laporan yang diisi grafolog saat layanan selesai.
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatRupiah, formatTanggal, TIPE_LAYANAN } from '@/lib/format'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const products = ref([])
const loading = ref(true)
const loadError = ref('')
const openId = ref(null)
const edit = ref({})
const priceDraft = ref({})
const newProduct = ref({ kode: '', nama: '', kategori: 'pendukung', target: 'semua', price: 0 })
const newComponent = ref({ tipe: 'konsultasi', nama: '' })
const busy = ref(false)

const TIPE = Object.keys(TIPE_LAYANAN)
const FIELD_TYPES = { textarea: 'Teks', score: 'Skor 1-5', choice: 'Pilihan' }

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    products.value = (await api.get('/admin/products')).data
    priceDraft.value = Object.fromEntries(products.value.map((p) => [p.id, p.price]))
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat produk.'
  } finally {
    loading.value = false
  }
}

function errorOf(e, fallback) {
  return (
    Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? e.response?.data?.message ?? fallback
  )
}

function toggle(p) {
  if (openId.value === p.id) {
    openId.value = null
    return
  }
  openId.value = p.id
  edit.value = {
    nama: p.nama,
    ringkasan: p.ringkasan ?? '',
    deskripsi: p.deskripsi ?? '',
    kategori: p.kategori,
    target: p.target,
    requires_handwriting: p.requires_handwriting,
    is_active: p.is_active,
    is_featured: p.is_featured,
    promo_label: p.promo_label ?? '',
    sort: p.sort,
    components: p.components.map((c) => ({
      ...c,
      report_fields: (c.report_fields ?? []).map((f) => ({
        ...f,
        optionsText: (f.options ?? []).join(', '),
      })),
    })),
  }
  newComponent.value = { tipe: 'konsultasi', nama: '' }
}

async function create() {
  busy.value = true
  try {
    await api.post('/admin/products', newProduct.value)
    toast.push('Produk dibuat.', 'success')
    newProduct.value = { kode: '', nama: '', kategori: 'pendukung', target: 'semua', price: 0 }
    await load()
  } catch (e) {
    toast.push(errorOf(e, 'Gagal membuat produk.'))
  } finally {
    busy.value = false
  }
}

async function savePrice(p) {
  try {
    await api.put(`/admin/pricing/${p.kode}`, { price: Number(priceDraft.value[p.id]) })
    toast.push(`Harga ${p.nama} diperbarui.`, 'success')
    await load()
  } catch (e) {
    toast.push(errorOf(e, 'Gagal menyimpan harga.'))
  }
}

async function saveProduct(p) {
  busy.value = true
  try {
    const { components: _c, ...body } = edit.value // eslint-disable-line no-unused-vars
    await api.put(`/admin/products/${p.id}`, { ...body, promo_label: body.promo_label || null })
    toast.push('Produk disimpan.', 'success')
    const keep = openId.value
    await load()
    openId.value = null
    toggle(products.value.find((x) => x.id === keep))
  } catch (e) {
    toast.push(errorOf(e, 'Gagal menyimpan produk.'))
  } finally {
    busy.value = false
  }
}

async function remove(p) {
  try {
    await api.delete(`/admin/products/${p.id}`)
    toast.push('Produk dihapus.', 'success')
    openId.value = null
    await load()
  } catch (e) {
    toast.push(errorOf(e, 'Gagal menghapus produk.'))
  }
}

function addField(c) {
  c.report_fields.push({
    key: `field_${c.report_fields.length + 1}`,
    label: '',
    type: 'textarea',
    optionsText: '',
  })
}

async function saveComponent(c) {
  try {
    await api.put(`/admin/product-components/${c.id}`, {
      nama: c.nama,
      tipe: c.tipe,
      report_fields:
        c.tipe === 'grafologi'
          ? []
          : c.report_fields.map(({ optionsText, ...f }) => ({
              key: f.key,
              label: f.label,
              type: f.type,
              ...(f.type === 'choice'
                ? {
                    options: optionsText
                      .split(',')
                      .map((o) => o.trim())
                      .filter(Boolean),
                  }
                : {}),
            })),
    })
    toast.push('Komponen disimpan.', 'success')
  } catch (e) {
    toast.push(errorOf(e, 'Gagal menyimpan komponen.'))
  }
}

async function addComponent(p) {
  try {
    await api.post(`/admin/products/${p.id}/components`, newComponent.value)
    toast.push('Komponen ditambahkan dengan template bawaan.', 'success')
    const keep = p.id
    await load()
    openId.value = null
    toggle(products.value.find((x) => x.id === keep))
  } catch (e) {
    toast.push(errorOf(e, 'Gagal menambah komponen.'))
  }
}

async function removeComponent(p, c) {
  try {
    await api.delete(`/admin/product-components/${c.id}`)
    edit.value.components = edit.value.components.filter((x) => x.id !== c.id)
    toast.push('Komponen dihapus.', 'success')
    await load()
  } catch (e) {
    toast.push(errorOf(e, 'Gagal menghapus komponen.'))
  }
}

onMounted(load)
</script>

<template>
  <div class="ap">
    <h1>Kelola Produk</h1>
    <p class="ap__intro">
      Produk adalah dasar harga dan kode promo. Master Graphology adalah produk utama; produk
      pendukung (wawancara, konsultasi, konseling, intervensi) masing-masing punya template laporan
      yang diisi grafolog.
    </p>

    <form class="ap__create" @submit.prevent="create">
      <h2>Produk baru</h2>
      <div class="ap__grid">
        <label>Nama <input v-model="newProduct.nama" type="text" required /></label>
        <label
          >Kode (URL)
          <input v-model="newProduct.kode" type="text" placeholder="mis. tes-minat-karier" required
        /></label>
        <label
          >Kategori
          <select v-model="newProduct.kategori">
            <option value="utama">Utama</option>
            <option value="pendukung">Pendukung</option>
          </select>
        </label>
        <label
          >Target
          <select v-model="newProduct.target">
            <option value="semua">Semua</option>
            <option value="b2c">Klien individu</option>
            <option value="b2b">Perusahaan (B2B)</option>
          </select>
        </label>
        <label
          >Harga (Rp)
          <input v-model.number="newProduct.price" type="number" min="0" step="1000" required
        /></label>
      </div>
      <button type="submit" class="btn btn--primary" :disabled="busy">Tambah produk</button>
    </form>

    <SkeletonLoader v-if="loading" variant="list" :rows="4" label="Memuat produk..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <ul v-else class="ap__list">
      <li
        v-for="p in products"
        :key="p.id"
        :class="['ap__item', { 'ap__item--off': !p.is_active }]"
      >
        <div class="ap__row">
          <div class="ap__info">
            <strong>{{ p.nama }}</strong>
            <span class="ap__meta">
              {{ p.kode }} · {{ p.kategori }} · {{ p.target }} · {{ p.orders_count }} pesanan
              <template v-if="!p.is_active"> · nonaktif</template>
              <template v-if="p.is_featured"> · unggulan</template>
            </span>
            <span class="ap__chips">
              <span v-for="c in p.components" :key="c.id" class="ap__chip">{{
                TIPE_LAYANAN[c.tipe]
              }}</span>
            </span>
          </div>
          <div class="ap__price">
            <input
              v-model.number="priceDraft[p.id]"
              type="number"
              min="0"
              step="1000"
              :aria-label="`Harga ${p.nama}`"
            />
            <button
              type="button"
              class="btn"
              :disabled="priceDraft[p.id] === p.price"
              @click="savePrice(p)"
            >
              Ubah harga
            </button>
            <span class="ap__meta">Aktif: {{ formatRupiah(p.price) }}</span>
          </div>
          <button type="button" class="btn" @click="toggle(p)">
            {{ openId === p.id ? 'Tutup' : 'Ubah' }}
          </button>
        </div>

        <div v-if="openId === p.id" class="ap__panel">
          <div class="ap__grid">
            <label>Nama <input v-model="edit.nama" type="text" /></label>
            <label
              >Label promo
              <input v-model="edit.promo_label" type="text" placeholder="mis. Terlaris"
            /></label>
            <label
              >Kategori
              <select v-model="edit.kategori">
                <option value="utama">Utama</option>
                <option value="pendukung">Pendukung</option>
              </select>
            </label>
            <label
              >Target
              <select v-model="edit.target">
                <option value="semua">Semua</option>
                <option value="b2c">Klien individu</option>
                <option value="b2b">Perusahaan (B2B)</option>
              </select>
            </label>
            <label>Urutan <input v-model.number="edit.sort" type="number" min="0" /></label>
          </div>
          <label class="ap__wide"
            >Ringkasan (nilai produk) <input v-model="edit.ringkasan" type="text"
          /></label>
          <label class="ap__wide"
            >Kegunaan lengkap <textarea v-model="edit.deskripsi" rows="3"></textarea>
          </label>
          <div class="ap__checks">
            <label><input v-model="edit.is_active" type="checkbox" /> Aktif dijual</label>
            <label
              ><input v-model="edit.is_featured" type="checkbox" /> Tampilkan sebagai
              unggulan/promo</label
            >
            <label
              ><input v-model="edit.requires_handwriting" type="checkbox" /> Butuh scan tulisan
              tangan</label
            >
          </div>
          <div class="ap__actions">
            <button type="button" class="btn btn--primary" :disabled="busy" @click="saveProduct(p)">
              Simpan produk
            </button>
            <button v-if="!p.orders_count" type="button" class="btn" @click="remove(p)">
              Hapus produk
            </button>
          </div>

          <h3>Komponen &amp; template laporan</h3>
          <div v-for="c in edit.components" :key="c.id" class="ap__component">
            <div class="ap__grid">
              <label>Nama komponen <input v-model="c.nama" type="text" /></label>
              <label
                >Tipe
                <select v-model="c.tipe">
                  <option v-for="t in TIPE" :key="t" :value="t">{{ TIPE_LAYANAN[t] }}</option>
                </select>
              </label>
            </div>
            <p v-if="c.tipe === 'grafologi'" class="ap__meta">
              Laporan grafologi memakai measurement worksheet dan laporan 40 aspek - tanpa template
              tambahan.
            </p>
            <template v-else>
              <div v-for="(f, i) in c.report_fields" :key="i" class="ap__field">
                <input
                  v-model="f.label"
                  type="text"
                  placeholder="Label pertanyaan"
                  :aria-label="`Label field ${i + 1}`"
                />
                <input
                  v-model="f.key"
                  type="text"
                  placeholder="kunci_data"
                  :aria-label="`Kunci field ${i + 1}`"
                />
                <select v-model="f.type" :aria-label="`Tipe field ${i + 1}`">
                  <option v-for="(lbl, k) in FIELD_TYPES" :key="k" :value="k">{{ lbl }}</option>
                </select>
                <input
                  v-if="f.type === 'choice'"
                  v-model="f.optionsText"
                  type="text"
                  placeholder="Pilihan, pisahkan koma"
                  :aria-label="`Pilihan field ${i + 1}`"
                />
                <button
                  type="button"
                  class="ap__x"
                  :aria-label="`Hapus field ${i + 1}`"
                  @click="c.report_fields.splice(i, 1)"
                >
                  ×
                </button>
              </div>
              <button type="button" class="btn" @click="addField(c)">+ Field laporan</button>
            </template>
            <div class="ap__actions">
              <button type="button" class="btn btn--primary" @click="saveComponent(c)">
                Simpan komponen
              </button>
              <button
                v-if="!p.orders_count"
                type="button"
                class="btn"
                @click="removeComponent(p, c)"
              >
                Hapus
              </button>
            </div>
          </div>

          <form class="ap__add-comp" @submit.prevent="addComponent(p)">
            <select v-model="newComponent.tipe" aria-label="Tipe komponen baru">
              <option v-for="t in TIPE" :key="t" :value="t">{{ TIPE_LAYANAN[t] }}</option>
            </select>
            <input
              v-model="newComponent.nama"
              type="text"
              placeholder="Nama komponen baru"
              required
            />
            <button type="submit" class="btn">Tambah komponen</button>
          </form>

          <h3>Riwayat harga</h3>
          <ul class="ap__history">
            <li v-for="(h, i) in p.price_history" :key="i">
              {{ formatRupiah(h.price) }} · {{ formatTanggal(h.created_at, true) }}
              <strong v-if="h.is_active">(aktif)</strong>
            </li>
          </ul>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.ap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.ap__intro,
.ap__meta {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin: 0;
}
.ap__create,
.ap__panel,
.ap__component {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ap__create {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.ap__create h2,
.ap__panel h3 {
  margin: 0;
  font-size: 15px;
}
.ap__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}
.ap__grid label,
.ap__wide {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  color: var(--color-text-soft);
}
.ap__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ap__item {
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.ap__item--off {
  opacity: 0.7;
}
.ap__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.ap__info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.ap__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.ap__chip {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}
.ap__price {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.ap input[type='number'] {
  display: block;
  width: 100%;
  padding: 9px 10px;
  margin-top: 4px;
  box-sizing: border-box;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}
.ap__price input[type='number'] {
  width: 130px;
  margin-top: 0;
}
.ap__panel {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--color-border);
}
.ap__checks {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  font-size: 13.5px;
}
.ap__checks input {
  width: auto;
}
.ap__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ap__component {
  padding: 12px;
  border: 1px dashed var(--color-border-hover);
  border-radius: var(--radius-md);
}
.ap__field {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 2fr auto;
  gap: 6px;
  align-items: center;
}
.ap__x {
  border: 0;
  background: none;
  font-size: 18px;
  cursor: pointer;
  color: var(--color-error);
}
.ap__add-comp {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ap__add-comp input {
  flex: 1;
  min-width: 160px;
}
.ap__history {
  margin: 0;
  padding-left: 18px;
  font-size: 13px;
}
@media (max-width: 640px) {
  .ap__field {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
