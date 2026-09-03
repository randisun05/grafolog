<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

// Sistem Products data-driven (2026-09-03, lihat guratan-api/CLAUDE.md
// "Sistem Products data-driven") - CRUD tier/produk. Daftar TIDAK
// dipaginasi (pendek, di-scan sekilas) dan MENYERTAKAN produk nonaktif
// juga (termasuk 'rapid') supaya admin bisa lihat semua dan aktifkan
// lagi kalau perlu.
const products = ref([])
const loading = ref(true)
const loadError = ref('')

async function loadProducts() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/products')
    products.value = data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat produk.'
    toast.push(loadError.value)
  } finally {
    loading.value = false
  }
}

// --- buat produk baru ---
const createForm = ref({ code: '', name: '', description: '', sort_order: 0 })
const createErrors = ref({})
const creating = ref(false)

async function createProduct() {
  creating.value = true
  createErrors.value = {}
  try {
    await api.post('/admin/products', createForm.value)
    toast.push(`Produk ${createForm.value.name} berhasil dibuat.`, 'success')
    createForm.value = { code: '', name: '', description: '', sort_order: 0 }
    await loadProducts()
  } catch (e) {
    createErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat produk.')
  } finally {
    creating.value = false
  }
}

// --- edit/toggle produk - expand-row, sama pola AdminUsersView.vue ---
const editingId = ref(null)
const editForm = ref({})
const editErrors = ref({})
const savingId = ref(null)

function startEdit(product) {
  editingId.value = product.id
  editErrors.value = {}
  editForm.value = {
    name: product.name,
    description: product.description ?? '',
    sort_order: product.sort_order,
    is_active: product.is_active,
  }
}

function cancelEdit() {
  editingId.value = null
}

async function saveProduct(product) {
  savingId.value = product.id
  editErrors.value = {}
  try {
    const { data } = await api.patch(`/admin/products/${product.id}`, editForm.value)
    Object.assign(product, data)
    toast.push(`Produk ${product.name} berhasil diperbarui.`, 'success')
    editingId.value = null
  } catch (e) {
    editErrors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal memperbarui produk.')
  } finally {
    savingId.value = null
  }
}

const togglingId = ref(null)

async function toggleActive(product) {
  togglingId.value = product.id
  try {
    const { data } = await api.patch(`/admin/products/${product.id}`, { is_active: !product.is_active })
    Object.assign(product, data)
    toast.push(`Produk ${product.name} ${product.is_active ? 'diaktifkan' : 'dinonaktifkan'}.`, 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah status produk.')
  } finally {
    togglingId.value = null
  }
}

onMounted(loadProducts)
</script>

<template>
  <div class="admin-products">
    <h1>Kelola Produk</h1>
    <p class="admin-products__note">
      Katalog tier/produk laporan (Comprehensive, Master, dst). Menonaktifkan produk membuatnya
      hilang dari halaman pemesanan/pengelolaan lain, tapi tidak menghapus riwayat laporan/harga
      yang sudah memakainya. Kode produk (slug) tidak bisa diubah setelah dibuat.
    </p>

    <div class="admin-products__create">
      <h2>Tambah Produk Baru</h2>
      <form @submit.prevent="createProduct">
        <label>
          Kode (slug, mis. "deluxe")
          <input v-model="createForm.code" type="text" placeholder="deluxe" required />
        </label>
        <p v-if="createErrors.code" class="error">{{ createErrors.code[0] }}</p>

        <label>
          Nama
          <input v-model="createForm.name" type="text" placeholder="Deluxe" required />
        </label>
        <p v-if="createErrors.name" class="error">{{ createErrors.name[0] }}</p>

        <label>
          Deskripsi (opsional)
          <textarea v-model="createForm.description" rows="2"></textarea>
        </label>

        <label>
          Urutan Tampil
          <input v-model.number="createForm.sort_order" type="number" min="0" />
        </label>

        <button type="submit" class="btn btn--primary" :disabled="creating">
          {{ creating ? 'Menyimpan...' : 'Tambah Produk' }}
        </button>
      </form>
    </div>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <table v-else class="admin-products__table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama</th>
          <th>Urutan</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="product in products" :key="product.id">
          <tr>
            <td><code>{{ product.code }}</code></td>
            <td>{{ product.name }}</td>
            <td>{{ product.sort_order }}</td>
            <td>
              <span class="badge" :class="product.is_active ? 'badge--active' : 'badge--inactive'">
                {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="admin-products__actions">
              <button type="button" class="btn" @click="editingId === product.id ? cancelEdit() : startEdit(product)">
                {{ editingId === product.id ? 'Batal' : 'Ubah' }}
              </button>
              <button
                type="button"
                class="btn"
                :disabled="togglingId === product.id"
                @click="toggleActive(product)"
              >
                {{ product.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
            </td>
          </tr>
          <tr v-if="editingId === product.id" class="admin-products__edit-row">
            <td colspan="5">
              <div class="admin-products__edit-form">
                <label>
                  Nama
                  <input v-model="editForm.name" type="text" />
                </label>
                <p v-if="editErrors.name" class="error">{{ editErrors.name[0] }}</p>

                <label>
                  Deskripsi
                  <textarea v-model="editForm.description" rows="2"></textarea>
                </label>

                <label>
                  Urutan Tampil
                  <input v-model.number="editForm.sort_order" type="number" min="0" />
                </label>

                <label class="admin-products__checkbox">
                  <input v-model="editForm.is_active" type="checkbox" />
                  Aktif
                </label>

                <button
                  type="button"
                  class="btn btn--primary"
                  :disabled="savingId === product.id"
                  @click="saveProduct(product)"
                >
                  {{ savingId === product.id ? 'Menyimpan...' : 'Simpan' }}
                </button>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.admin-products {
  max-width: 800px;
}
.admin-products__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-products__create {
  margin-bottom: 24px;
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-products__create h2 {
  font-size: 15px;
  margin-bottom: 12px;
}
.admin-products__create label,
.admin-products__edit-form label {
  display: block;
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 10px;
}
.admin-products__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.admin-products__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.admin-products__table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-products__actions {
  display: flex;
  gap: 6px;
}
.badge {
  font-size: 11.5px;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}
.badge--active {
  background: var(--color-sage);
  color: #fff;
}
.badge--inactive {
  background: var(--color-ink-faint);
  color: var(--color-ink-soft);
}
.admin-products__edit-row {
  background: var(--color-paper-alt);
}
.admin-products__edit-form {
  padding: 14px 4px;
  max-width: 400px;
}
.admin-products__checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
}
.admin-products__checkbox input {
  margin: 0;
  width: auto;
}
</style>
