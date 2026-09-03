<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const products = ref([])
const prices = ref([])
const costs = ref([])
const loading = ref(true)
const loadError = ref('')

const priceDraft = ref('')
const savingPrice = ref(false)
const costDraft = ref({})
const savingCost = ref({})

const activePrice = computed(() => prices.value.find((p) => p.is_active) ?? null)
const priceHistory = computed(() => prices.value.filter((p) => !p.is_active))

const activeCostByTier = computed(() => {
  const result = {}
  for (const cost of costs.value) {
    if (cost.is_active) result[cost.tier] = cost
  }
  return result
})
const costHistoryByTier = computed(() => {
  const result = {}
  for (const product of products.value) result[product.code] = []
  for (const cost of costs.value) {
    if (!cost.is_active) result[cost.tier]?.push(cost)
  }
  return result
})

// Sistem Products data-driven (2026-09-03, lihat guratan-api/CLAUDE.md) -
// daftar tier TIDAK lagi hardcoded ['comprehensive', 'master'] di sini,
// diambil dari /api/products supaya produk baru langsung dapat kartu
// biaya token tanpa perubahan kode lagi.
async function loadProducts() {
  const { data } = await api.get('/products')
  products.value = data
  for (const product of data) {
    if (!(product.code in costDraft.value)) costDraft.value[product.code] = ''
    if (!(product.code in savingCost.value)) savingCost.value[product.code] = false
  }
}

async function loadAll() {
  loading.value = true
  loadError.value = ''
  try {
    await loadProducts()
    const [priceRes, costRes] = await Promise.all([
      api.get('/admin/token-price'),
      api.get('/admin/token-costs'),
    ])
    prices.value = priceRes.data.data
    costs.value = costRes.data.data
    priceDraft.value = activePrice.value?.price_per_token ?? ''
    for (const product of products.value) {
      costDraft.value[product.code] = activeCostByTier.value[product.code]?.tokens_required ?? ''
    }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat pengaturan token.'
  } finally {
    loading.value = false
  }
}

async function savePrice() {
  const price = Number(priceDraft.value)
  if (!Number.isInteger(price) || price < 0) {
    toast.push('Harga per token harus berupa angka bulat, minimal 0.')
    return
  }
  savingPrice.value = true
  try {
    await api.put('/admin/token-price', { price_per_token: price })
    toast.push('Harga per token berhasil diubah.', 'success')
    await loadAll()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah harga token.')
  } finally {
    savingPrice.value = false
  }
}

async function saveCost(tier, label) {
  const tokens = Number(costDraft.value[tier])
  if (!Number.isInteger(tokens) || tokens < 0) {
    toast.push('Jumlah token harus berupa angka bulat, minimal 0.')
    return
  }
  savingCost.value[tier] = true
  try {
    await api.put(`/admin/token-costs/${tier}`, { tokens_required: tokens })
    toast.push(`Biaya token ${label} berhasil diubah.`, 'success')
    await loadAll()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah biaya token.')
  } finally {
    savingCost.value[tier] = false
  }
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

onMounted(loadAll)
</script>

<template>
  <div class="admin-tokens">
    <h1>Kelola Token Grafolog</h1>
    <p class="admin-tokens__note">
      Grafolog membeli token untuk membuat laporan. Harga per token dan jumlah token yang
      terpotong per laporan bisa diubah di sini — perubahan tidak menghapus riwayat lama, dan
      setiap perubahan tercatat di log audit. Kalau biaya token satu tier belum diatur (kosong),
      laporan tier itu tidak memotong token sama sekali.
    </p>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <section class="admin-tokens__card">
        <h3>Harga per Token</h3>
        <p class="admin-tokens__current">
          Harga aktif:
          <strong>{{ activePrice ? formatRupiah(activePrice.price_per_token) : 'Belum diatur' }}</strong>
        </p>
        <div class="admin-tokens__edit">
          <input v-model="priceDraft" type="number" min="0" step="500" />
          <button type="button" class="btn btn--primary" :disabled="savingPrice" @click="savePrice">
            {{ savingPrice ? 'Menyimpan...' : 'Ubah Harga' }}
          </button>
        </div>
        <div v-if="priceHistory.length" class="admin-tokens__history">
          <h4>Riwayat</h4>
          <ul>
            <li v-for="p in priceHistory" :key="p.id">
              {{ formatRupiah(p.price_per_token) }}
              <span class="admin-tokens__history-meta">
                — diganti {{ new Date(p.updated_at).toLocaleDateString('id-ID') }}
              </span>
            </li>
          </ul>
        </div>
      </section>

      <h2 class="admin-tokens__section-title">Biaya Token per Tier Laporan</h2>
      <p v-if="products.length === 0" class="admin-tokens__note">
        Belum ada produk aktif — tambahkan lewat <RouterLink to="/admin/products">Kelola Produk</RouterLink> dulu.
      </p>
      <div v-else class="admin-tokens__grid">
        <div v-for="product in products" :key="product.code" class="admin-tokens__card">
          <h3>{{ product.name }}</h3>
          <p class="admin-tokens__current">
            Biaya aktif:
            <strong>
              {{
                activeCostByTier[product.code]
                  ? `${activeCostByTier[product.code].tokens_required} token / laporan`
                  : 'Belum diatur (tidak memotong token)'
              }}
            </strong>
          </p>
          <div class="admin-tokens__edit">
            <input v-model="costDraft[product.code]" type="number" min="0" step="1" />
            <button
              type="button"
              class="btn btn--primary"
              :disabled="savingCost[product.code]"
              @click="saveCost(product.code, product.name)"
            >
              {{ savingCost[product.code] ? 'Menyimpan...' : 'Ubah Biaya' }}
            </button>
          </div>
          <div v-if="costHistoryByTier[product.code]?.length" class="admin-tokens__history">
            <h4>Riwayat</h4>
            <ul>
              <li v-for="c in costHistoryByTier[product.code]" :key="c.id">
                {{ c.tokens_required }} token / laporan
                <span class="admin-tokens__history-meta">
                  — diganti {{ new Date(c.updated_at).toLocaleDateString('id-ID') }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-tokens {
  max-width: 720px;
}
.admin-tokens__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-tokens__section-title {
  font-size: 16px;
  margin: 28px 0 12px;
}
.admin-tokens__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}
.admin-tokens__card {
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-tokens__card h3 {
  font-size: 16px;
  margin-bottom: 8px;
}
.admin-tokens__current {
  font-size: 13.5px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.admin-tokens__current strong {
  color: var(--color-ink);
  font-family: var(--font-heading);
  font-size: 17px;
}
.admin-tokens__edit {
  display: flex;
  gap: 8px;
}
.admin-tokens__edit input {
  margin-top: 0;
}
.admin-tokens__history {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.admin-tokens__history h4 {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-soft);
  margin-bottom: 6px;
}
.admin-tokens__history ul {
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 12.5px;
}
.admin-tokens__history li {
  padding: 4px 0;
  color: var(--color-text-soft);
}
.admin-tokens__history-meta {
  color: var(--color-ink-faint);
}
</style>
