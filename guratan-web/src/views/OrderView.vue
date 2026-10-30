<script setup>
import PaymentMethodPicker from '@/components/shared/PaymentMethodPicker.vue'
import CvForm from '@/components/cv/CvForm.vue'
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/lib/api'
import { PHOTO_ACCEPT, PHOTO_HINT, partitionPhotos } from '@/lib/handwritingPhoto'
import { formatRupiah, TIPE_LAYANAN } from '@/lib/format'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

// Wizard pemesanan klien individu (2026-10-30): produk -> pelaksanaan & waktu -> CV -> persyaratan -> bayar.
// Pesanan dibuat sekali per sesi halaman saat bayar; scan (bila ada) diunggah ke sampel pesanan -
// klien boleh juga mengunggah belakangan dari halaman Pesanan Saya, asal sebelum batas waktu yang ia tetapkan.
const toast = useToast()
const router = useRouter()
const route = useRoute()

const STEPS = [
  { key: 'produk', label: 'Produk' },
  { key: 'pelaksanaan', label: 'Pelaksanaan & waktu' },
  { key: 'cv', label: 'CV' },
  { key: 'persyaratan', label: 'Persyaratan' },
  { key: 'bayar', label: 'Bayar' },
]
const MODE_LABEL = { online: 'Online', offline: 'Tatap muka (offline)' }

const products = ref([])
const promos = ref([])
const loading = ref(true)
const loadError = ref('')
const step = ref(0)

const selected = ref(null)
const mode = ref('online')
const scheduledFor = ref('')
const uploadDeadline = ref('')
const locationNote = ref('')
const cvMissing = ref(0)
const reqAck = ref(false)

const paymentMethod = ref('')
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

const today = new Date().toISOString().slice(0, 10)
const utama = computed(() => products.value.filter((p) => p.kategori === 'utama'))
const pendukung = computed(() => products.value.filter((p) => p.kategori !== 'utama'))
const needsPhotos = computed(() => !!selected.value?.requires_handwriting)
const finalPrice = computed(() =>
  previewResult.value?.code_valid
    ? previewResult.value.final_price
    : (selected.value?.price ?? null),
)
const requirements = computed(() => selected.value?.requirements ?? [])

// Validasi per langkah: tombol "Lanjut" aktif hanya bila langkah ini lengkap.
const stepError = computed(() => {
  const k = STEPS[step.value].key
  if (k === 'produk' && !selected.value) return 'Pilih satu produk.'
  if (k === 'pelaksanaan') {
    if (!selected.value?.modes.includes(mode.value)) return 'Pilih mode pelaksanaan.'
    if (mode.value === 'offline' && !locationNote.value.trim())
      return 'Isi kota/tempat pelaksanaan.'
    if (needsPhotos.value && !uploadDeadline.value)
      return 'Tentukan batas waktu Anda mengunggah persyaratan.'
    if (scheduledFor.value && uploadDeadline.value && uploadDeadline.value > scheduledFor.value)
      return 'Batas unggah harus sebelum atau sama dengan tanggal pelaksanaan.'
  }
  if (k === 'cv' && cvMissing.value > 0)
    return `Lengkapi ${cvMissing.value} isian wajib CV lalu klik Simpan CV.`
  if (k === 'persyaratan' && requirements.value.length && !reqAck.value)
    return 'Centang bahwa Anda sudah memahami persyaratan.'
  return ''
})

function next() {
  if (!stepError.value && step.value < STEPS.length - 1) step.value++
}
function back() {
  if (step.value > 0) step.value--
}

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
    const pre = list.find((p) => p.kode === route.query.produk)
    if (pre) {
      selectProduct(pre)
      step.value = 1 // produk sudah dipilih dari katalog -> langsung ke pelaksanaan
    }
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
  mode.value = p.modes.includes(mode.value) ? mode.value : p.modes[0]
  reqAck.value = false
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
  if (!agreed.value || !selected.value) return
  paying.value = true
  payError.value = ''
  try {
    if (!createdOrder.value) {
      const { data } = await api.post('/orders', {
        product_id: selected.value.id,
        discount_code: previewResult.value?.code_valid ? discountCode.value : undefined,
        delivery_mode: mode.value,
        scheduled_for: scheduledFor.value || undefined,
        upload_deadline: uploadDeadline.value || undefined,
        location_note: mode.value === 'offline' ? locationNote.value : undefined,
      })
      createdOrder.value = data
    }
    if (needsPhotos.value && photos.value.length && !photosUploaded.value) {
      const formData = new FormData()
      photos.value.forEach((f) => formData.append('images[]', f))
      await api.post(`/samples/${createdOrder.value.sample_id}/images`, formData)
      photosUploaded.value = true
    }
    const { data: payment } = await api.post(`/orders/${createdOrder.value.id}/payment`, {
      payment_method: paymentMethod.value || undefined,
    })
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

    <ol class="wiz" aria-label="Langkah pemesanan">
      <li
        v-for="(s, i) in STEPS"
        :key="s.key"
        class="wiz__step"
        :class="{ 'wiz__step--on': i === step, 'wiz__step--done': i < step }"
        :aria-current="i === step ? 'step' : undefined"
      >
        <span class="wiz__n">{{ i < step ? '✓' : i + 1 }}</span>
        <span class="wiz__l">{{ s.label }}</span>
      </li>
    </ol>

    <SkeletonLoader v-if="loading" variant="cards" :rows="3" label="Memuat produk..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <template v-else>
      <!-- 1. Produk -->
      <section v-show="step === 0">
        <p class="order__note">
          Master Graphology adalah layanan utama kami. Layanan pendukung melengkapi hasilnya -
          masing-masing punya laporan sendiri. Belum yakin?
          <RouterLink to="/produk">Lihat detail produk</RouterLink>.
        </p>
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
                <span v-for="m in p.modes" :key="m" class="order__component">{{
                  MODE_LABEL[m]
                }}</span>
                <span v-for="c in p.components" :key="c.nama" class="order__component">{{
                  TIPE_LAYANAN[c.tipe] ?? c.tipe
                }}</span>
              </span>
            </button>
          </div>
        </template>
      </section>

      <!-- 2. Pelaksanaan & waktu -->
      <section v-if="selected" v-show="step === 1" class="order__checkout">
        <h2 class="order__checkout-title">{{ selected.nama }}</h2>
        <p v-if="selected.deskripsi" class="order__tier-desc">{{ selected.deskripsi }}</p>
        <fieldset class="wiz__field">
          <legend>Mode pelaksanaan</legend>
          <label v-for="m in selected.modes" :key="m" class="wiz__radio">
            <input v-model="mode" type="radio" name="mode" :value="m" /> {{ MODE_LABEL[m] }}
          </label>
        </fieldset>
        <label v-if="mode === 'offline'" class="wiz__field">
          Kota / tempat pelaksanaan
          <input
            v-model="locationNote"
            type="text"
            maxlength="255"
            placeholder="mis. Bandung - kafe dekat stasiun"
          />
        </label>
        <label class="wiz__field">
          Tanggal pelaksanaan yang diinginkan (opsional)
          <input v-model="scheduledFor" type="date" :min="today" />
        </label>
        <label v-if="needsPhotos" class="wiz__field">
          Saya akan mengunggah persyaratan paling lambat
          <input
            v-model="uploadDeadline"
            type="date"
            :min="today"
            :max="scheduledFor || undefined"
          />
          <small>Grafolog baru bisa mulai setelah scan Anda diterima.</small>
        </label>
      </section>

      <!-- 3. CV -->
      <section v-show="step === 2">
        <h2 class="order__group">Perbarui CV Anda</h2>
        <p class="order__note">
          CV membantu grafolog memahami konteks Anda. Isian bertanda 🔒 hanya terlihat oleh Anda dan
          administrator.
        </p>
        <CvForm @loaded="(e) => (cvMissing = e.missing_required)" />
      </section>

      <!-- 4. Persyaratan -->
      <section v-if="selected" v-show="step === 3">
        <h2 class="order__group">Persyaratan</h2>
        <ul v-if="requirements.length" class="order__includes">
          <li v-for="r in requirements" :key="r.judul">
            <strong>{{ r.judul }}</strong
            ><span v-if="r.keterangan"> - {{ r.keterangan }}</span>
          </li>
        </ul>
        <label v-if="requirements.length" class="order__toc">
          <input v-model="reqAck" type="checkbox" />
          <span>Saya sudah memahami dan akan menyiapkan persyaratan di atas.</span>
        </label>
        <p v-else-if="!needsPhotos" class="order__note">
          Produk ini tidak memerlukan persyaratan khusus.
        </p>

        <div v-if="needsPhotos" class="order__photos">
          <span class="order__photos-title"
            >Scan tulisan tangan - PDF ({{ photos.length }}/{{ MAX_PHOTOS }})</span
          >
          <p class="order__photos-hint">
            Tulis minimal satu halaman tangan di kertas polos tanpa garis dan tanda tangani di
            bawahnya. {{ PHOTO_HINT }} Belum siap? Lewati, lalu unggah dari halaman Pesanan Saya
            sebelum {{ uploadDeadline || 'batas waktu yang Anda tetapkan' }}.
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
      </section>

      <!-- 5. Bayar -->
      <section v-if="selected" v-show="step === 4" class="order__checkout">
        <h2 class="order__checkout-title">Ringkasan</h2>
        <ul class="order__includes">
          <li>
            <strong>{{ selected.nama }}</strong>
          </li>
          <li>
            Pelaksanaan: {{ MODE_LABEL[mode]
            }}<template v-if="mode === 'offline'"> - {{ locationNote }}</template>
          </li>
          <li v-if="scheduledFor">Tanggal diinginkan: {{ scheduledFor }}</li>
          <li v-if="uploadDeadline">Batas unggah persyaratan: {{ uploadDeadline }}</li>
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

        <div class="order__summary">
          <span>Total bayar</span>
          <strong>{{ formatRupiah(finalPrice) }}</strong>
        </div>

        <p v-if="payError" class="error">{{ payError }}</p>
        <PaymentMethodPicker
          v-model="paymentMethod"
          :allowed="previewResult?.code_valid ? previewResult.code_payment_methods : null"
        />
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
          :disabled="paying || !agreed"
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
      </section>

      <div class="wiz__nav">
        <button v-if="step > 0" type="button" class="btn" @click="back">‹ Kembali</button>
        <span v-if="stepError && step < STEPS.length - 1" class="wiz__err">{{ stepError }}</span>
        <button
          v-if="step < STEPS.length - 1"
          type="button"
          class="btn btn--primary"
          :disabled="!!stepError"
          @click="next"
        >
          Lanjut ›
        </button>
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
.wiz {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  list-style: none;
  padding: 0;
  margin: 0 0 18px;
}
.wiz__step {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--color-text-soft);
}
.wiz__n {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border-hover);
  font-size: 12px;
}
.wiz__step--on {
  color: var(--color-ink);
  font-weight: 600;
}
.wiz__step--on .wiz__n {
  background: var(--color-ink);
  color: var(--color-paper);
}
.wiz__step--done .wiz__n {
  background: var(--color-sage);
  color: #fff;
  border-color: var(--color-sage);
}
.wiz__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  margin: 12px 0;
  border: none;
  padding: 0;
}
.wiz__field input[type='text'],
.wiz__field input[type='date'] {
  width: auto;
  max-width: 320px;
}
.wiz__radio {
  display: flex;
  gap: 6px;
  align-items: center;
}
.wiz__radio input {
  width: auto;
}
.wiz__nav {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-top: 18px;
}
.wiz__err {
  font-size: 13px;
  color: var(--color-seal);
  flex: 1;
}
</style>
