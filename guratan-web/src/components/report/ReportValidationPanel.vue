<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { formatTanggal, STATUS_VALIDASI } from '@/lib/format'

/**
 * Alur terbit & supervisi antar-grafolog (2026-10-18). Pembuat laporan: terbitkan sendiri atau
 * tugaskan grafolog lain (tenggat hari, token ditahan) lalu konfirmasi hasilnya. Validator:
 * setujui / minta revisi / tolak penugasan.
 */
const props = defineProps({
  report: { type: Object, required: true },
  canPublish: { type: Boolean, default: false },
})
const emit = defineEmits(['changed'])

const auth = useAuthStore()
const toast = useToast()
const history = ref([])
const validators = ref([])
const fee = ref(null)
const validatorId = ref('')
const days = ref(7)
const note = ref('')
const submitting = ref(false)

const ACTIVE = ['menunggu', 'disetujui', 'perlu_revisi']
const isDraft = computed(() => ['draf', 'perlu_revisi'].includes(props.report.review_status))
const active = computed(() => history.value.find((v) => ACTIVE.includes(v.status)) ?? null)
const iAmValidator = computed(() => active.value?.validator?.id === auth.user?.id)
const iAmRequester = computed(() => active.value?.requester?.id === auth.user?.id)
const canAssign = computed(() => props.canPublish && isDraft.value && !active.value)
const notEnough = computed(() => fee.value && fee.value.tokens > fee.value.balance)

async function load() {
  history.value = (await api.get(`/reports/${props.report.id}/validations`)).data
  if (canAssign.value) {
    const [f, v] = await Promise.all([
      api.get('/report-validation/fee'),
      api.get('/report-validation/validators'),
    ])
    fee.value = f.data
    validators.value = v.data
  }
}

async function run(fn, success) {
  submitting.value = true
  try {
    const { data } = await fn()
    toast.push(success, 'success')
    note.value = ''
    emit('changed', data)
    await load()
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(
      errors ? Object.values(errors).flat()[0] : (e.response?.data?.message ?? 'Gagal memproses.'),
    )
  } finally {
    submitting.value = false
  }
}

const publish = () =>
  run(
    () => api.post(`/reports/${props.report.id}/publish`),
    'Laporan diterbitkan dan dikirim ke klien.',
  )
const assign = () =>
  run(
    () =>
      api.post(`/reports/${props.report.id}/request-validation`, {
        validator_id: validatorId.value,
        deadline_days: days.value,
      }),
    'Penugasan supervisi dikirim.',
  )
const decide = (decision) =>
  run(
    () =>
      api.post(`/validations/${active.value.id}/decide`, { decision, note: note.value || null }),
    decision === 'approve'
      ? 'Laporan disetujui. Menunggu konfirmasi pembuat.'
      : 'Permintaan revisi dikirim.',
  )
const decline = () =>
  run(
    () => api.post(`/validations/${active.value.id}/decline`, { note: note.value || null }),
    'Penugasan ditolak.',
  )
const confirm = () =>
  run(
    () => api.post(`/validations/${active.value.id}/confirm`),
    'Dikonfirmasi. Token dikreditkan ke saldo token validator.',
  )

const statusLabel = {
  draf: 'Draf - belum terlihat klien',
  menunggu_review: 'Dalam supervisi',
  perlu_revisi: 'Perlu revisi',
  disetujui: 'Terbit - terlihat oleh klien',
}

onMounted(load)
</script>

<template>
  <section class="vp" :class="`vp--${report.review_status}`">
    <p class="vp__status">
      <strong>Status:</strong> {{ statusLabel[report.review_status] ?? report.review_status }}
      <span v-if="report.review_status === 'disetujui'">
        ·
        {{
          report.validation === 'supervisor' ? 'divalidasi grafolog lain' : 'terbit mandiri'
        }}</span
      >
    </p>
    <p v-if="report.review_note && report.review_status === 'perlu_revisi'" class="vp__note">
      <strong>Catatan validator:</strong> {{ report.review_note }}
    </p>

    <!-- Validator: giliran memutuskan -->
    <div v-if="active && iAmValidator && active.status === 'menunggu'" class="vp__box">
      <p>
        Anda ditugaskan memvalidasi laporan ini oleh <strong>{{ active.requester.name }}</strong
        >. Tenggat {{ formatTanggal(active.due_at, true) }}. Token {{ active.fee }} cair setelah
        pembuat mengonfirmasi hasil Anda.
      </p>
      <textarea v-model="note" rows="2" placeholder="Catatan (wajib bila minta revisi)"></textarea>
      <div class="vp__buttons">
        <button
          type="button"
          class="btn btn--primary"
          :disabled="submitting"
          @click="decide('approve')"
        >
          Setujui
        </button>
        <button
          type="button"
          class="btn"
          :disabled="submitting || !note.trim()"
          @click="decide('request_revision')"
        >
          Minta revisi (tolak)
        </button>
        <button type="button" class="btn" :disabled="submitting" @click="decline">
          Tolak penugasan
        </button>
      </div>
    </div>
    <p v-else-if="active && iAmValidator" class="vp__hint">
      Keputusan Anda ({{ STATUS_VALIDASI[active.status] }}) menunggu konfirmasi pembuat laporan.
    </p>

    <!-- Pembuat: konfirmasi hasil / menunggu -->
    <div
      v-if="active && iAmRequester && ['disetujui', 'perlu_revisi'].includes(active.status)"
      class="vp__box"
    >
      <p>
        <strong>{{ active.validator.name }}</strong> memutuskan:
        <strong>{{ active.status === 'disetujui' ? 'Setuju' : 'Perlu revisi' }}</strong>
        <template v-if="active.note"> - “{{ active.note }}”</template>. Konfirmasi untuk
        {{ active.status === 'disetujui' ? 'menerbitkan laporan dan ' : '' }}mengkreditkan
        {{ active.fee }} token ke saldo validator (otomatis dalam 3 hari).
      </p>
      <button type="button" class="btn btn--primary" :disabled="submitting" @click="confirm">
        Konfirmasi hasil
      </button>
    </div>
    <p v-else-if="active && iAmRequester" class="vp__hint">
      Menunggu {{ active.validator.name }} sampai {{ formatTanggal(active.due_at, true) }}. Bila
      lewat tenggat, token kembali dan Anda bisa menugaskan ulang.
    </p>

    <!-- Pembuat: pilih jalur -->
    <div v-if="canAssign" class="vp__box">
      <p class="vp__hint">
        Periksa laporan, lalu terbitkan langsung (tanggung jawab Anda), atau tugaskan grafolog lain
        untuk memvalidasi lebih dulu.
      </p>
      <div class="vp__buttons">
        <button type="button" class="btn btn--primary" :disabled="submitting" @click="publish">
          Terbitkan langsung
        </button>
      </div>
      <div class="vp__assign">
        <label
          >Grafolog supervisor
          <select v-model="validatorId">
            <option value="" disabled>
              {{
                validators.length
                  ? 'Pilih grafolog...'
                  : 'Belum ada supervisor yang tersedia - Anda tetap dapat menerbitkan langsung'
              }}
            </option>
            <option v-for="v in validators" :key="v.id" :value="v.id">
              {{ v.name }}{{ v.city ? ` · ${v.city}` : ''
              }}{{ v.experience_years != null ? ` · ${v.experience_years} th` : '' }}
            </option>
          </select>
        </label>
        <label
          >Tenggat (hari, maks. {{ fee?.max_days ?? 30 }})
          <input v-model.number="days" type="number" min="1" :max="fee?.max_days ?? 30" />
        </label>
        <button
          type="button"
          class="btn"
          :disabled="submitting || !validatorId || notEnough"
          @click="assign"
        >
          Tugaskan supervisi{{ fee ? ` (${fee.tokens} token)` : '' }}
        </button>
      </div>
      <p v-if="fee" class="vp__hint">
        Token ditahan saat penugasan, dikembalikan bila ditolak/lewat tenggat. Saldo Anda:
        {{ fee.balance }}
        <RouterLink v-if="notEnough" to="/token-saya">· Beli token</RouterLink>
      </p>
    </div>

    <details v-if="history.length" class="vp__history">
      <summary>Riwayat supervisi ({{ history.length }})</summary>
      <ul>
        <li v-for="v in history" :key="v.id">
          {{ v.validator.name }} - {{ STATUS_VALIDASI[v.status] }} · tenggat
          {{ formatTanggal(v.due_at) }}<template v-if="v.note"> · “{{ v.note }}”</template>
        </li>
      </ul>
    </details>
  </section>
</template>

<style scoped>
.vp {
  border: 1px solid var(--color-border);
  border-left: 4px solid var(--color-gold);
  border-radius: 6px;
  padding: 12px 14px;
  margin-bottom: 18px;
  font-size: 14px;
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.vp--disetujui {
  border-left-color: var(--color-success);
}
.vp--perlu_revisi {
  border-left-color: var(--color-error);
}
.vp p {
  margin: 0;
}
.vp__hint {
  color: var(--color-text-soft);
  font-size: 13px;
}
.vp__box {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.vp__box textarea {
  width: 100%;
  margin: 0;
}
.vp__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.vp__assign {
  display: grid;
  grid-template-columns: 1fr 140px auto;
  gap: 8px;
  align-items: end;
}
.vp__assign label {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.vp__assign input[type='number'] {
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
.vp__history summary {
  cursor: pointer;
  font-size: 13px;
  color: var(--color-text-soft);
}
.vp__history ul {
  margin: 6px 0 0;
  padding-left: 18px;
  font-size: 13px;
}
@media (max-width: 720px) {
  .vp__assign {
    grid-template-columns: 1fr;
  }
}
</style>
