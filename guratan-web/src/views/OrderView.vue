<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/lib/api'
import { PHOTO_ACCEPT, PHOTO_HINT, partitionPhotos } from '@/lib/handwritingPhoto'
import { formatRupiah, TIPE_LAYANAN } from '@/lib/format'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

// Pemesanan berbasis katalog produk (2026-10-17): Master sebagai produk utama + produk pendukung.
// Pesanan dibuat sekali per sesi halaman; foto diunggah ke sample pesanan bila produk butuh
// tulisan tangan; lalu pembayaran (DOKU, atau langsung lunas di mode uji).
const toast = useToast()
const router = useRouter()
const route = useRoute()

const products = ref([])
const promos = ref([])
const loading = ref(true)
const loadError = ref('')

const selected = ref(null)
const discountCode = ref(route.query.kode ?? '')
const previewResult = ref(null)
const previewLoading = ref(false)
const previewError = ref('')

const paying = ref(false)
const agreed = ref(false)
const payError = ref('')

const MAX_PHOTOS = 5
const photos = ref([])
const photoErrors = ref([])
const createdOrder = ref(null)
const photosUploaded = ref(false)

const utama = computed(() => products.value.filter((p) => p.kategori === 'utama'))
const pendukung = computed(() => products.value.filter((p) => p.kategori !== 'utama'))
const needsPhotos = computed(() => !!selected.value?.requires_handwriting)
const finalPrice = computed(() =>
  previewResult.value?.code_valid
    ? previewResult.value.final_price
    : (selected.value?.price ?? null),
)

async function onPhotos(event) {
  const picked = Array.from(event.target.files ?? [])
  event.target.value = ''
  const { ok, errors } = await partitionPhotos(picked)
  photoErrors.value = errors
  photos.value = [...photos.value, ...ok].slice(0, MAX_PHOTOS)
}

function removePhoto(index) {
  photos.value = photos.value.filter((_, i) => i !== index)
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [{ data: list }, { data: promo }] = await Promise.all([
      api.get('/products'),
      api.get('/promos'),
    ])
    products.value = list
    promos.value = promo.codes
    const pre =
      list.find((p) => p.kode === route.query.produk) ?? list.find((p) => p.kode === 'master')
    if (pre) selectProduct(pre)
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat produk.'
  } finally {
    loading.value = false
  }
}

function selectProduct(p) {
  if (createdOrder.value && createdOrder.value.product?.id !== p.id) {
    createdOrder.value = null
    photosUploaded.value = false
  }
  selected.value = p
  previewResult.value = null
  previewError.value = ''
  if (discountCode.value) applyCode()
}

async function applyCode() {
  if (!selected.value || !discountCode.value) return
  previewLoading.value = true
  previewError.value = ''
  try {
    const { data } = await api.post('/orders/preview', {
      product_id: selected.value.id,
      discount_code: discountCode.value,
    })
    previewResult.value = data
    if (data.code_valid === false)
      previewError.value = data.code_message ?? 'Kode promo tidak berlaku.'
  } catch (e) {
    previewError.value = e.response?.data?.message ?? 'Gagal memeriksa kode promo.'
  } finally {
    previewLoading.value = false
  }
}

function usePromo(code) {
  discountCode.value = code
  applyCode()
}

async function pay() {
  if (!agreed.value || !selected.value || (needsPhotos.value && !photos.value.length)) return
  paying.value = true
  payError.value = ''
  try {
    if (!createdOrder.value) {
      const { data } = await api.post('/orders', {
        product_id: selected.value.id,
        discount_code: previewResult.value?.code_valid ? discountCode.value : undefined,
      })
      createdOrder.value = data
    }
    if (needsPhotos.value && !photosUploaded.value) {
      const formData = new FormData()
      photos.value.forEach((f) => formData.append('images[]', f))
      await api.post(`/samples/${createdOrder.value.sample_id}/images`, formData)
      photosUploaded.value = true
    }
    const { data: payment } = await api.post(`/orders/${createdOrder.value.id}/payment`)
    // Mode uji (akun DOKU belum ada): backend langsung menandai lunas.
    if (payment.paid) {
      toast.push('Mode uji: pembayaran dinonaktifkan, pesanan langsung dianggap lunas.')
      router.push({ name: 'riwayat' })
      return
    }
    window.location.href = payment.payment_url
  } catch (e) {
    const errors = e.response?.data?.errors
    payError.value = errors
      ? Object.values(errors).flat()[0]
      : (e.response?.data?.message ?? 'Gagal memulai pembayaran.')
    toast.push(payError.value)
    paying.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="order">
    <h1>Pesan Layanan</h1>
    <p class="order__note">
      Master Graphology adalah layanan utama kami. Layanan pendukung melengkapi hasilnya -
      masing-masing punya laporan sendiri yang tersimpan di akun Anda.
    </p>

    <SkeletonLoader v-if="loading" variant="cards" :rows="3" label="Memuat produk..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <div v-if="promos.length" class="order__promos">
        <span class="order__promos-title">Promo berlaku</span>
        <button
          v-for="pr in promos"
          :key="pr.code"
          type="button"
          class="order__promo"
          @click="usePromo(pr.code)"
        >
          <strong>{{ pr.code }}</strong>
          <span>{{
            pr.description ||
            (pr.type === 'percentage' ? `Hemat ${pr.value}%` : `Hemat ${formatRupiah(pr.value)}`)
          }}</span>
        </button>
      </div>

      <template
        v-for="group in [
          { title: 'Layanan utama', list: utama },
          { title: 'Layanan pendukung', list: pendukung },
        ]"
        :key="group.title"
      >
        <h2 v-if="group.list.length" class="order__group">{{ group.title }}</h2>
        <div v-if="group.list.length" class="order__tiers">
          <button
            v-for="p in group.list"
            :key="p.id"
            type="button"
            class="order__tier-card"
            :class="{ 'order__tier-card--selected': selected?.id === p.id }"
            @click="selectProduct(p)"
          >
            <span v-if="p.promo_label" class="order__badge">{{ p.promo_label }}</span>
            <span class="order__tier-name">{{ p.nama }}</span>
            <span class="order__tier-price">{{ formatRupiah(p.price) }}</span>
            <span class="order__tier-desc">{{ p.ringkasan }}</span>
            <span class="order__components">
              <span v-for="c in p.components" :key="c.nama" class="order__component">{{
                TIPE_LAYANAN[c.tipe] ?? c.tipe
              }}</span>
            </span>
          </button>
        </div>
      </template>

      <div v-if="selected" class="order__checkout">
        <h2 class="order__checkout-title">{{ selected.nama }}</h2>
        <p v-if="selected.deskripsi" class="order__tier-desc">{{ selected.deskripsi }}</p>
        <ul class="order__includes">
          <li v-for="c in selected.components" :key="c.nama">{{ c.nama }}</li>
        </ul>

        <label class="order__code-label">
          Kode promo (opsional)
          <div class="order__code-row">
            <input v-model="discountCode" type="text" placeholder="mis. HEMAT10" />
            <button
              type="button"
              class="btn"
              :disabled="!discountCode || previewLoading"
              @click="applyCode"
            >
              {{ previewLoading ? '...' : 'Terapkan' }}
            </button>
          </div>
        </label>
        <p v-if="previewError" class="error">{{ previewError }}</p>
        <p v-else-if="previewResult?.code_valid" class="order__discount-applied">
          Kode diterapkan — hemat {{ formatRupiah(previewResult.discount_amount) }}.
        </p>

        <div v-if="needsPhotos" class="order__photos">
          <span class="order__photos-title"
            >Foto/scan tulisan tangan ({{ photos.length }}/{{ MAX_PHOTOS }})</span
          >
          <p class="order__photos-hint">
            Tulis minimal satu halaman tangan di kertas polos tanpa garis dan tanda tangani di
            bawahnya.
            {{ PHOTO_HINT }}
          </p>
          <p v-for="(msg, i) in photoErrors" :key="`pe-${i}`" class="error">{{ msg }}</p>
          <ul v-if="photos.length" class="order__photos-list">
            <li v-for="(f, i) in photos" :key="i">
              <span>{{ f.name }}</span>
              <button
                type="button"
                class="order__photos-remove"
                :disabled="photosUploaded"
                @click="removePhoto(i)"
              >
                Hapus
              </button>
            </li>
          </ul>
          <label v-if="photos.length < MAX_PHOTOS && !photosUploaded" class="btn order__photos-add">
            Pilih File
            <input type="file" :accept="PHOTO_ACCEPT" multiple hidden @change="onPhotos" />
          </label>
        </div>

        <div class="order__summary">
          <span>Total bayar</span>
          <strong>{{ formatRupiah(finalPrice) }}</strong>
        </div>

        <p v-if="payError" class="error">{{ payError }}</p>
        <label class="order__toc">
          <input v-model="agreed" type="checkbox" />
          <span>
            Saya memahami bahwa pesanan yang <strong>sudah dibayar tidak dapat dibatalkan</strong>.
            Refund hanya dilakukan administrator bila terjadi kesalahan sistem atau pesanan belum
            selesai lebih dari 90 hari.
            <RouterLink to="/kebijakan-transaksi" target="_blank">Baca kebijakan</RouterLink>
          </span>
        </label>
        <button
          type="button"
          class="btn btn--primary order__pay-btn"
          :disabled="paying || !agreed || (needsPhotos && !photos.length)"
          @click="pay"
        >
          {{ paying ? 'Menyiapkan pembayaran...' : 'Bayar Sekarang' }}
        </button>
        <p class="order__sop">
          Dengan membayar, Anda menyetujui
          <RouterLink to="/syarat" target="_blank">Syarat Layanan</RouterLink> dan
          <RouterLink to="/kebijakan-transaksi" target="_blank"
            >Kebijakan Pembelian, Pembatalan &amp; Refund</RouterLink
          >.
        </p>
      </div>
    </template>
  </div>
</template>

<style scoped>
.order {
  max-width: 760px;
}
.order__group {
  font-size: 17px;
  margin: 8px 0 10px;
}
.order__badge {
  align-self: flex-start;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-gold-soft);
}
.order__components {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}
.order__component {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  color: var(--color-text-soft);
}
.order__promos {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  margin-bottom: 18px;
  border: 1px dashed var(--color-gold);
  border-radius: var(--radius-md);
  background: var(--color-gold-soft);
}
.order__promos-title {
  font-size: 13px;
  font-weight: 600;
  margin-right: 4px;
}
.order__promo {
  display: inline-flex;
  gap: 6px;
  align-items: baseline;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid var(--color-gold);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 12.5px;
  cursor: pointer;
}
.order__checkout-title {
  font-size: 18px;
  margin: 0 0 6px;
}
.order__includes {
  margin: 10px 0 16px;
  padding-left: 18px;
  font-size: 13.5px;
}
.order__sop {
  margin-top: 10px;
  font-size: 12px;
  color: var(--color-text-soft);
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
.order__photos {
  margin: 4px 0 18px;
}
.order__photos-title {
  font-size: 14px;
  font-weight: 600;
}
.order__photos-hint {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 4px 0 10px;
}
.order__photos-list {
  list-style: none;
  padding: 0;
  margin: 0 0 10px;
  font-size: 13px;
}
.order__photos-list li {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  border-bottom: 1px dashed var(--color-border);
  overflow-wrap: anywhere;
}
.order__photos-remove {
  background: none;
  border: none;
  color: var(--color-error);
  cursor: pointer;
  font-size: 12px;
}
.order__photos-add {
  display: inline-block;
  cursor: pointer;
}
.order__photos-add input {
  display: none;
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
.order__toc {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 13px;
  margin: 10px 0;
}
.order__toc input {
  width: auto;
  margin-top: 3px;
}
</style>
