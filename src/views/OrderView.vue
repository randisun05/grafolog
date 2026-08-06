<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const tierLabel = {
  comprehensive: 'Comprehensive',
  master: 'Master',
}
const tierDesc = {
  comprehensive: 'Analisis mendalam 40 aspek oleh grafolog bersertifikat.',
  master: 'Comprehensive + sesi konsultasi langsung dengan grafolog.',
}

const pricing = ref([])
const loading = ref(true)
const loadError = ref('')

const selectedTier = ref(null)
const discountCode = ref('')
const previewResult = ref(null)
const previewLoading = ref(false)
const previewError = ref('')

const paying = ref(false)
const payError = ref('')

const basePrice = computed(() => pricing.value.find((p) => p.tier === selectedTier.value)?.price ?? null)
const finalPrice = computed(() => {
  if (previewResult.value?.code_valid) return previewResult.value.final_price
  return basePrice.value
})

function formatRupiah(value) {
  if (value === null || value === undefined) return '–'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

async function loadPricing() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/pricing')
    pricing.value = data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat harga.'
  } finally {
    loading.value = false
  }
}

function selectTier(tier) {
  selectedTier.value = tier
  previewResult.value = null
  previewError.value = ''
}

async function applyCode() {
  if (!selectedTier.value || !discountCode.value) return
  previewLoading.value = true
  previewError.value = ''
  try {
    const { data } = await api.post('/pricing/preview', {
      tier: selectedTier.value,
      code: discountCode.value,
    })
    previewResult.value = data
    if (!data.code_valid) previewError.value = data.code_message ?? 'Kode diskon tidak berlaku.'
  } catch (e) {
    previewError.value = e.response?.data?.message ?? 'Gagal memeriksa kode diskon.'
  } finally {
    previewLoading.value = false
  }
}

async function pay() {
  if (!selectedTier.value) return
  paying.value = true
  payError.value = ''
  try {
    const { data: sample } = await api.post('/samples', { tier: selectedTier.value })
    const paymentBody = previewResult.value?.code_valid ? { discount_code: discountCode.value } : {}
    const { data: payment } = await api.post(`/samples/${sample.id}/payment`, paymentBody)
    window.location.href = payment.payment_url
  } catch (e) {
    payError.value = e.response?.data?.message ?? 'Gagal memulai pembayaran.'
    toast.push(payError.value)
    paying.value = false
  }
}

onMounted(loadPricing)
</script>

<template>
  <div class="order">
    <h1>Pesan Laporan</h1>
    <p class="order__note">Pilih tier, bayar online, grafolog kami akan segera menangani laporan Anda.</p>

    <LoadingSpinner v-if="loading" label="Memuat harga..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <div class="order__tiers">
        <button
          v-for="p in pricing"
          :key="p.tier"
          type="button"
          class="order__tier-card"
          :class="{ 'order__tier-card--selected': selectedTier === p.tier }"
          @click="selectTier(p.tier)"
        >
          <span class="order__tier-name">{{ tierLabel[p.tier] ?? p.tier }}</span>
          <span class="order__tier-price">{{ formatRupiah(p.price) }}</span>
          <span class="order__tier-desc">{{ tierDesc[p.tier] }}</span>
        </button>
      </div>

      <div v-if="selectedTier" class="order__checkout">
        <label class="order__code-label">
          Kode Diskon (opsional)
          <div class="order__code-row">
            <input v-model="discountCode" type="text" placeholder="mis. HEMAT10" />
            <button type="button" class="btn" :disabled="!discountCode || previewLoading" @click="applyCode">
              {{ previewLoading ? '...' : 'Terapkan' }}
            </button>
          </div>
        </label>
        <p v-if="previewError" class="error">{{ previewError }}</p>
        <p v-else-if="previewResult?.code_valid" class="order__discount-applied">
          Kode diterapkan — hemat {{ formatRupiah(previewResult.discount_amount) }}.
        </p>

        <div class="order__summary">
          <span>Total Bayar</span>
          <strong>{{ formatRupiah(finalPrice) }}</strong>
        </div>

        <p v-if="payError" class="error">{{ payError }}</p>
        <button type="button" class="btn btn--primary order__pay-btn" :disabled="paying" @click="pay">
          {{ paying ? 'Menyiapkan pembayaran...' : 'Bayar Sekarang' }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.order {
  max-width: 640px;
}
.order__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.order__tiers {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.order__tier-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  text-align: left;
  padding: 18px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  cursor: pointer;
  font-family: inherit;
}
.order__tier-card:hover {
  border-color: var(--color-seal);
}
.order__tier-card--selected {
  border-color: var(--color-seal);
  background: var(--color-seal-soft);
}
.order__tier-name {
  font-family: var(--font-heading);
  font-weight: 600;
  font-size: 16px;
  color: var(--color-ink);
}
.order__tier-price {
  font-size: 20px;
  font-weight: 600;
  color: var(--color-seal);
}
.order__tier-desc {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.order__checkout {
  padding: 20px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.order__code-label {
  display: block;
  font-size: 14px;
  color: var(--color-text-soft);
  margin-bottom: 12px;
}
.order__code-row {
  display: flex;
  gap: 8px;
}
.order__code-row input {
  margin-top: 4px;
}
.order__discount-applied {
  color: var(--color-success);
  font-size: 13px;
  margin-bottom: 8px;
}
.order__summary {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 14px 0;
  margin: 14px 0;
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
  font-size: 15px;
}
.order__summary strong {
  font-family: var(--font-heading);
  font-size: 22px;
  color: var(--color-ink);
}
.order__pay-btn {
  width: 100%;
  padding: 12px;
}
</style>
