<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const wallet = ref(null)
const loading = ref(true)
const loadError = ref('')

const quantity = ref(10)
const discountCode = ref('')
const previewResult = ref(null)
const previewLoading = ref(false)
const previewError = ref('')

const buying = ref(false)
const buyError = ref('')

const typeLabel = { purchase: 'Pembelian', consumption: 'Pemakaian' }

const finalAmount = computed(() => {
  if (previewResult.value?.code_valid) return previewResult.value.final_amount
  if (wallet.value?.price_per_token) return wallet.value.price_per_token * quantity.value
  return null
})

function formatRupiah(value) {
  if (value === null || value === undefined) return '–'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function loadWallet() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/tokens/wallet')
    wallet.value = data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat data token.'
  } finally {
    loading.value = false
  }
}

function resetPreview() {
  previewResult.value = null
  previewError.value = ''
}

async function applyCode() {
  if (!quantity.value || quantity.value < 1) return
  previewLoading.value = true
  previewError.value = ''
  try {
    const { data } = await api.post('/tokens/preview', {
      quantity: quantity.value,
      code: discountCode.value || undefined,
    })
    previewResult.value = data
    if (discountCode.value && !data.code_valid) {
      previewError.value = data.code_message ?? 'Kode diskon tidak berlaku.'
    }
  } catch (e) {
    previewError.value = e.response?.data?.message ?? 'Gagal memeriksa harga.'
  } finally {
    previewLoading.value = false
  }
}

async function buy() {
  if (!quantity.value || quantity.value < 1) return
  buying.value = true
  buyError.value = ''
  try {
    const body = { quantity: quantity.value }
    if (previewResult.value?.code_valid) body.discount_code = discountCode.value
    const { data } = await api.post('/tokens/purchase', body)
    window.location.href = data.payment_url
  } catch (e) {
    buyError.value = e.response?.data?.message ?? 'Gagal memulai pembayaran.'
    toast.push(buyError.value)
    buying.value = false
  }
}

onMounted(loadWallet)
</script>

<template>
  <div class="token-wallet">
    <h1>Token Saya</h1>
    <p class="token-wallet__note">
      Setiap laporan yang berhasil dibuat memotong sejumlah token dari saldo Anda (sesuai tier,
      diatur admin). Beli token di sini untuk menambah saldo.
    </p>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <div class="token-wallet__balance">
        <span>Saldo Saat Ini</span>
        <strong>{{ wallet.balance }} token</strong>
      </div>

      <section class="token-wallet__buy">
        <h2>Beli Token</h2>
        <p v-if="!wallet.price_per_token" class="error">
          Harga token belum diatur administrator — pembelian belum bisa dilakukan.
        </p>
        <template v-else>
          <label class="token-wallet__field">
            Jumlah Token
            <input v-model.number="quantity" type="number" min="1" step="1" @change="resetPreview" />
          </label>

          <label class="token-wallet__field">
            Kode Diskon (opsional)
            <div class="token-wallet__code-row">
              <input v-model="discountCode" type="text" placeholder="mis. HEMAT10" @input="resetPreview" />
              <button type="button" class="btn" :disabled="previewLoading" @click="applyCode">
                {{ previewLoading ? '...' : 'Terapkan' }}
              </button>
            </div>
          </label>
          <p v-if="previewError" class="error">{{ previewError }}</p>
          <p v-else-if="previewResult?.code_valid" class="token-wallet__discount-applied">
            Kode diterapkan — hemat {{ formatRupiah(previewResult.discount_amount) }}.
          </p>

          <div class="token-wallet__summary">
            <span>Total Bayar</span>
            <strong>{{ formatRupiah(finalAmount) }}</strong>
          </div>

          <p v-if="buyError" class="error">{{ buyError }}</p>
          <button type="button" class="btn btn--primary token-wallet__buy-btn" :disabled="buying" @click="buy">
            {{ buying ? 'Menyiapkan pembayaran...' : 'Beli Sekarang' }}
          </button>
        </template>
      </section>

      <section class="token-wallet__history">
        <h2>Riwayat Transaksi</h2>
        <p v-if="wallet.transactions.length === 0" class="token-wallet__empty">Belum ada transaksi.</p>
        <ul v-else class="token-wallet__history-list">
          <li v-for="t in wallet.transactions" :key="t.id" class="token-wallet__history-item">
            <span>
              {{ typeLabel[t.type] ?? t.type }}
              <span class="token-wallet__history-meta">{{ formatDate(t.created_at) }}</span>
            </span>
            <span :class="['token-wallet__delta', t.delta >= 0 ? 'token-wallet__delta--positive' : 'token-wallet__delta--negative']">
              {{ t.delta >= 0 ? '+' : '' }}{{ t.delta }}
            </span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.token-wallet {
  max-width: 640px;
}
.token-wallet__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.token-wallet__balance {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 18px 20px;
  margin-bottom: 24px;
  background: var(--color-seal-soft);
  border: 1px solid var(--color-seal);
  border-radius: var(--radius-md);
}
.token-wallet__balance span {
  font-size: 14px;
  color: var(--color-text-soft);
}
.token-wallet__balance strong {
  font-family: var(--font-heading);
  font-size: 26px;
  color: var(--color-ink);
}
.token-wallet__buy {
  padding: 20px;
  margin-bottom: 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.token-wallet__buy h2,
.token-wallet__history h2 {
  font-size: 16px;
  margin-bottom: 14px;
}
.token-wallet__field {
  display: block;
  font-size: 14px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.token-wallet__code-row {
  display: flex;
  gap: 8px;
}
.token-wallet__code-row input {
  margin-top: 4px;
}
.token-wallet__discount-applied {
  color: var(--color-success);
  font-size: 13px;
  margin-bottom: 8px;
}
.token-wallet__summary {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 14px 0;
  margin: 14px 0;
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
  font-size: 15px;
}
.token-wallet__summary strong {
  font-family: var(--font-heading);
  font-size: 22px;
  color: var(--color-ink);
}
.token-wallet__buy-btn {
  width: 100%;
  padding: 12px;
}
.token-wallet__empty {
  color: var(--color-text-soft);
}
.token-wallet__history-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.token-wallet__history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 4px;
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
}
.token-wallet__history-meta {
  display: block;
  font-size: 12px;
  color: var(--color-text-soft);
}
.token-wallet__delta {
  font-family: var(--font-heading);
  font-weight: 600;
  font-size: 15px;
}
.token-wallet__delta--positive {
  color: var(--color-success);
}
.token-wallet__delta--negative {
  color: var(--color-seal);
}
</style>
