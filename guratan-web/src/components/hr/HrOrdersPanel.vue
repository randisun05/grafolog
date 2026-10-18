<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import {
  formatBulan,
  formatRupiah,
  formatTanggal,
  STATUS_KETERSEDIAAN,
  STATUS_PESANAN,
  TIPE_LAYANAN,
} from '@/lib/format'

const props = defineProps({ preselected: { type: Array, default: () => [] } })
const emit = defineEmits(['changed'])
const toast = useToast()

const products = ref([])
const grafologs = ref([])
const candidates = ref([])
const batches = ref([])
const loading = ref(true)

// Bulan layanan: jendela 12 bulan mulai bulan ini. Grafolog di daftar = yang bisa dipesan bulan itu.
const months = Array.from({ length: 12 }, (_, i) => {
  const d = new Date(new Date().getFullYear(), new Date().getMonth() + i, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
})
const month = ref(months[0])

async function loadGrafologs() {
  grafologs.value = (await api.get('/grafologs', { params: { month: month.value } })).data
  if (grafologId.value && !grafologs.value.some((g) => g.id === grafologId.value))
    grafologId.value = null
}

const productKode = ref('')
const chosen = ref([])
const grafologId = ref(null)
const code = ref('')
const quote = ref(null)
const submitting = ref(false)
const agreed = ref(false)
const paying = ref(null)

async function load() {
  loading.value = true
  try {
    const [p, g, c, b] = await Promise.all([
      api.get('/hr/products'),
      api.get('/grafologs', { params: { month: month.value } }),
      api.get('/hr/candidates'),
      api.get('/hr/orders'),
    ])
    // Paket khusus B2B tampil & terpilih lebih dulu.
    products.value = [...p.data].sort((x, y) => (y.target === 'b2b') - (x.target === 'b2b'))
    grafologs.value = g.data
    candidates.value = c.data
    batches.value = b.data
    if (!productKode.value && products.value.length) {
      productKode.value = products.value[0].kode
    }
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memuat data pesanan.')
  } finally {
    loading.value = false
  }
}

watch(
  () => props.preselected,
  (ids) => {
    if (ids?.length) chosen.value = [...ids]
  },
  { immediate: true },
)

const product = computed(() => products.value.find((p) => p.kode === productKode.value))

async function refreshQuote() {
  if (!productKode.value || !chosen.value.length) {
    quote.value = null
    return
  }
  try {
    quote.value = (
      await api.post('/hr/orders/preview', {
        product: productKode.value,
        count: chosen.value.length,
        discount_code: code.value || null,
      })
    ).data
  } catch {
    quote.value = null
  }
}
watch([productKode, () => chosen.value.length], refreshQuote)

async function submit() {
  submitting.value = true
  try {
    const { data } = await api.post('/hr/orders', {
      product: productKode.value,
      candidate_ids: chosen.value,
      grafolog_id: grafologId.value,
      discount_code: code.value || null,
      month: month.value,
    })
    toast.push('Pesanan dibuat. Lanjutkan pembayaran.', 'success')
    chosen.value = []
    code.value = ''
    quote.value = null
    await load()
    await pay(data)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membuat pesanan.')
  } finally {
    submitting.value = false
  }
}

async function pay(batch) {
  paying.value = batch.id
  try {
    const { data } = await api.post(`/hr/orders/${batch.id}/payment`)
    if (data.paid) {
      toast.push(
        'Pembayaran tercatat. Grafolog sudah ditugaskan - unggah scan tulisan kandidat.',
        'success',
      )
      await load()
      emit('changed')
    } else if (data.payment_url) {
      window.location.href = data.payment_url
    }
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal memulai pembayaran.')
  } finally {
    paying.value = null
  }
}

async function cancel(batch) {
  try {
    await api.post(`/hr/orders/${batch.id}/cancel`)
    toast.push('Pesanan dibatalkan.')
    await load()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal membatalkan.')
  }
}

const workload = (g) => (g.active_assignments ?? 0) + (g.active_services ?? 0)

defineExpose({ load })
onMounted(load)
</script>

<template>
  <div class="hro">
    <SkeletonLoader v-if="loading" variant="cards" :rows="3" label="Memuat..." />
    <template v-else>
      <section class="hro__card">
        <h2>1. Pilih layanan</h2>
        <div class="hro__products">
          <label
            v-for="p in products"
            :key="p.kode"
            :class="['hro__product', { 'hro__product--on': productKode === p.kode }]"
          >
            <input v-model="productKode" type="radio" name="hro-product" :value="p.kode" />
            <strong>{{ p.nama }}</strong>
            <span class="hro__price">{{ formatRupiah(p.price) }} / kandidat</span>
            <span class="hro__sub">{{ p.ringkasan }}</span>
            <span class="hro__sub">{{
              p.components.map((c) => TIPE_LAYANAN[c.tipe] ?? c.nama).join(' + ')
            }}</span>
          </label>
        </div>
      </section>

      <section class="hro__card">
        <h2>2. Pilih kandidat ({{ chosen.length }})</h2>
        <p v-if="!candidates.length" class="hro__sub">Tambahkan kandidat dulu di tab Kandidat.</p>
        <div class="hro__candidates">
          <label v-for="c in candidates" :key="c.id" class="hro__candidate">
            <input v-model="chosen" type="checkbox" :value="c.id" />
            {{ c.user.name }}
            <span class="hro__sub">{{ c.position?.title ?? '' }}</span>
          </label>
        </div>
      </section>

      <section class="hro__card">
        <h2>3. Pilih bulan layanan & grafolog</h2>
        <label class="hro__month"
          >Bulan layanan
          <select v-model="month" aria-label="Bulan layanan" @change="loadGrafologs">
            <option v-for="m in months" :key="m" :value="m">{{ formatBulan(m) }}</option>
          </select>
        </label>
        <p class="hro__sub">
          Hanya grafolog terverifikasi yang tersedia di bulan layanan terpilih. Beban = sampel &
          layanan yang sedang dikerjakan.
        </p>
        <div class="hro__grafologs">
          <label
            v-for="g in grafologs"
            :key="g.id"
            :class="['hro__grafolog', { 'hro__grafolog--on': grafologId === g.id }]"
          >
            <input v-model="grafologId" type="radio" name="hro-grafolog" :value="g.id" />
            <strong>{{ g.name }}</strong>
            <span class="hro__sub"
              >{{ g.city || 'Kota -' }} · {{ g.experience_years ?? '-' }} th pengalaman</span
            >
            <span v-if="g.specializations?.length" class="hro__tags">
              <span v-for="s in g.specializations" :key="s" class="hro__tag">{{ s }}</span>
            </span>
            <span v-if="g.bio" class="hro__bio">{{ g.bio }}</span>
            <span class="hro__sub"
              >{{ STATUS_KETERSEDIAAN[g.availability.status] }}
              {{ formatBulan(g.availability.month) }}
              <template v-if="g.availability.slots_left !== null"
                >· sisa kuota {{ g.availability.slots_left }}</template
              ><template v-if="g.availability.note"> · {{ g.availability.note }}</template></span
            >
            <span v-if="g.calendar?.length" class="hro__tags">
              <span
                v-for="c in g.calendar"
                :key="c.month"
                :class="[
                  'hro__tag',
                  { 'hro__tag--off': !['tersedia', 'terbatas'].includes(c.status) },
                ]"
                >{{ formatBulan(c.month) }}: {{ STATUS_KETERSEDIAAN[c.status] }}</span
              >
            </span>
            <span class="hro__sub"
              >Beban aktif: {{ workload(g) }} · Selesai: {{ g.completed_assignments ?? 0 }}</span
            >
          </label>
        </div>
      </section>

      <section class="hro__card hro__checkout">
        <div class="hro__code">
          <input v-model="code" type="text" placeholder="Kode promo (opsional)" />
          <button type="button" class="btn" @click="refreshQuote">Pakai</button>
        </div>
        <p v-if="quote?.code_message" class="error">{{ quote.code_message }}</p>
        <div v-if="quote" class="hro__total">
          <span
            >{{ chosen.length }} × {{ formatRupiah(quote.unit_final) }}
            <s v-if="quote.unit_final !== quote.unit_price">{{
              formatRupiah(quote.unit_price)
            }}</s></span
          >
          <strong>{{ formatRupiah(quote.final_total) }}</strong>
        </div>
        <label class="hro__toc">
          <input v-model="agreed" type="checkbox" />
          <span>
            Saya memahami bahwa pesanan yang <strong>sudah dibayar tidak dapat dibatalkan</strong>.
            Refund hanya oleh administrator bila terjadi kesalahan sistem atau pesanan belum selesai
            lebih dari 90 hari.
            <RouterLink to="/kebijakan-transaksi" target="_blank">Baca kebijakan</RouterLink>
          </span>
        </label>
        <button
          type="button"
          class="btn btn--primary"
          :disabled="submitting || !agreed || !product || !chosen.length || !grafologId"
          @click="submit"
        >
          {{ submitting ? 'Memproses...' : 'Buat pesanan & bayar' }}
        </button>
        <p class="hro__sub">
          Grafolog pilihan otomatis ditugaskan setelah pembayaran. Pembatalan hanya sebelum bayar.
        </p>
      </section>

      <section>
        <h2 class="hro__title">Riwayat pesanan</h2>
        <p v-if="!batches.length" class="hro__sub">Belum ada pesanan.</p>
        <ul class="hro__batches">
          <li v-for="b in batches" :key="b.id" class="hro__batch">
            <div>
              <strong>#{{ b.id }} · {{ b.product?.nama }}</strong>
              <span class="hro__sub"
                >{{ formatTanggal(b.created_at) }} · {{ b.orders.length }} kandidat ·
                {{ b.grafolog?.name ?? '-' }} · {{ formatRupiah(b.amount) }}</span
              >
              <span class="hro__sub">{{ b.orders.map((o) => o.user?.name).join(', ') }}</span>
            </div>
            <div class="hro__batch-actions">
              <span class="hro__status">{{ STATUS_PESANAN[b.status] ?? b.status }}</span>
              <template v-if="b.status === 'menunggu_pembayaran'">
                <button
                  type="button"
                  class="btn btn--primary"
                  :disabled="paying === b.id"
                  @click="pay(b)"
                >
                  Bayar
                </button>
                <button type="button" class="btn" @click="cancel(b)">Batalkan</button>
              </template>
            </div>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.hro {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hro__card {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.hro h2 {
  font-size: 15px;
  margin: 0;
}
.hro__title {
  margin-bottom: 8px !important;
}
.hro__sub {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 0;
}
.hro__products,
.hro__grafologs {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
}
.hro__product,
.hro__grafolog {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  background: var(--color-paper);
}
.hro__product input,
.hro__grafolog input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.hro__product--on,
.hro__grafolog--on {
  border-color: var(--color-seal);
  box-shadow: 0 0 0 1px var(--color-seal);
}
.hro__price {
  font-weight: 600;
}
.hro__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.hro__tag {
  font-size: 11.5px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-gold-soft);
}
.hro__bio {
  font-size: 12.5px;
}
.hro__candidates {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 6px;
}
.hro__candidate {
  display: flex;
  gap: 6px;
  align-items: baseline;
  font-size: 14px;
  flex-wrap: wrap;
}
.hro__candidate input {
  width: auto;
}
.hro__code {
  display: flex;
  gap: 8px;
  max-width: 360px;
}
.hro__code input {
  margin: 0;
}
.hro__total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  max-width: 360px;
}
.hro__total strong {
  font-size: 20px;
}
.hro__checkout .btn {
  align-self: flex-start;
}
.hro__batches {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.hro__batch {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}
.hro__batch-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}
.hro__status {
  font-size: 12.5px;
  font-weight: 600;
}
.hro__month {
  font-size: 13px;
  color: var(--color-text-soft);
  max-width: 260px;
}
.hro__tag--off {
  background: var(--color-border);
  color: var(--color-text-soft);
}
.hro__toc {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 13px;
  margin: 10px 0;
}
.hro__toc input {
  width: auto;
  margin-top: 3px;
}
</style>
