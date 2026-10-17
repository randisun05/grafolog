<script setup>
// Satu layanan pendukung (wawancara/konsultasi/konseling/intervensi) dari pesanan produk.
// mode 'client': lihat status, jadwal, dan laporan (read-only).
// mode 'grafolog': grafolog yang ditugaskan mengatur jadwal lalu mengisi laporan sesuai
// template komponen (report_fields: textarea | score 1-5 | choice).
import { computed, ref } from 'vue'
import api from '@/lib/api'
import { formatTanggal, STATUS_LAYANAN, TIPE_LAYANAN } from '@/lib/format'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  delivery: { type: Object, required: true },
  mode: { type: String, default: 'client' },
})
const emit = defineEmits(['updated'])
const toast = useToast()

const detail = ref(props.delivery.component ? props.delivery : null)
const open = ref(false)
const loading = ref(false)
const d = computed(() => detail.value ?? props.delivery)
const fields = computed(() => d.value.component?.report_fields ?? [])
const tipe = computed(() => d.value.component?.tipe ?? props.delivery.tipe)
const closed = computed(() => ['selesai', 'dibatalkan'].includes(d.value.status))

const scheduleForm = ref({ scheduled_at: '', meeting_url: '' })
const reportForm = ref({})
const saving = ref(false)

async function toggle() {
  open.value = !open.value
  if (open.value && (!detail.value || !detail.value.component)) {
    loading.value = true
    try {
      detail.value = (await api.get(`/service-deliveries/${props.delivery.id}`)).data
    } catch {
      toast.push('Gagal memuat layanan.')
    } finally {
      loading.value = false
    }
  }
  if (open.value) {
    scheduleForm.value = {
      scheduled_at: d.value.scheduled_at ? d.value.scheduled_at.slice(0, 16) : '',
      meeting_url: d.value.meeting_url ?? '',
    }
    reportForm.value = { ...d.value.report }
  }
}

async function saveSchedule() {
  saving.value = true
  try {
    detail.value = (
      await api.put(`/service-deliveries/${d.value.id}/schedule`, {
        scheduled_at: new Date(scheduleForm.value.scheduled_at).toISOString(),
        meeting_url: scheduleForm.value.meeting_url || null,
      })
    ).data
    toast.push('Jadwal disimpan.', 'success')
    emit('updated')
  } catch (e) {
    toast.push(
      Object.values(e.response?.data?.errors ?? {}).flat()[0] ??
        e.response?.data?.message ??
        'Gagal menyimpan jadwal.',
    )
  } finally {
    saving.value = false
  }
}

async function submitReport() {
  saving.value = true
  try {
    detail.value = (
      await api.post(`/service-deliveries/${d.value.id}/report`, { report: reportForm.value })
    ).data
    toast.push('Laporan layanan tersimpan.', 'success')
    emit('updated')
  } catch (e) {
    toast.push(
      Object.values(e.response?.data?.errors ?? {}).flat()[0] ??
        e.response?.data?.message ??
        'Gagal menyimpan laporan.',
    )
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="svc">
    <div class="svc__head">
      <div class="svc__info">
        <span class="svc__tipe">{{ TIPE_LAYANAN[tipe] ?? tipe }}</span>
        <strong>{{ d.component?.nama ?? d.nama }}</strong>
        <span class="svc__meta">
          <template v-if="mode === 'grafolog'">Klien: {{ d.user?.name }} · </template>
          {{ STATUS_LAYANAN[d.status] ?? d.status }}
          <template v-if="d.scheduled_at"> · {{ formatTanggal(d.scheduled_at, true) }}</template>
          <template v-if="mode === 'client' && (d.assignee?.name || d.assignee)">
            · {{ d.assignee?.name ?? d.assignee }}</template
          >
        </span>
        <a
          v-if="d.meeting_url && !closed"
          :href="d.meeting_url"
          target="_blank"
          rel="noopener"
          class="svc__link"
          >Tautan sesi</a
        >
      </div>
      <button
        v-if="mode === 'grafolog' || d.has_report || d.report_submitted_at"
        type="button"
        class="btn"
        @click="toggle"
      >
        {{ open ? 'Tutup' : mode === 'grafolog' && !closed ? 'Kerjakan' : 'Lihat laporan' }}
      </button>
    </div>

    <div v-if="open" class="svc__body">
      <p v-if="loading" class="svc__meta">Memuat...</p>
      <template v-else>
        <form
          v-if="mode === 'grafolog' && !closed"
          class="svc__form"
          @submit.prevent="saveSchedule"
        >
          <h4>Jadwal sesi</h4>
          <div class="svc__row">
            <label :for="`svc-at-${d.id}`">Waktu</label>
            <input
              :id="`svc-at-${d.id}`"
              v-model="scheduleForm.scheduled_at"
              type="datetime-local"
              required
            />
          </div>
          <div class="svc__row">
            <label :for="`svc-url-${d.id}`">Tautan (https, opsional)</label>
            <input
              :id="`svc-url-${d.id}`"
              v-model="scheduleForm.meeting_url"
              type="url"
              placeholder="https://..."
            />
          </div>
          <button type="submit" class="btn" :disabled="saving">Simpan jadwal</button>
        </form>

        <form
          v-if="mode === 'grafolog' && !closed"
          class="svc__form"
          @submit.prevent="submitReport"
        >
          <h4>Laporan layanan</h4>
          <div v-for="f in fields" :key="f.key" class="svc__row">
            <label :for="`svc-${d.id}-${f.key}`">{{ f.label }}</label>
            <textarea
              v-if="f.type === 'textarea'"
              :id="`svc-${d.id}-${f.key}`"
              v-model="reportForm[f.key]"
              rows="3"
              required
            ></textarea>
            <div
              v-else-if="f.type === 'score'"
              class="svc__scores"
              role="radiogroup"
              :aria-label="f.label"
            >
              <label v-for="n in 5" :key="n" class="svc__score">
                <input
                  v-model.number="reportForm[f.key]"
                  type="radio"
                  :name="`svc-${d.id}-${f.key}`"
                  :value="n"
                  required
                />
                {{ n }}
              </label>
              <span class="svc__meta">1 = sangat rendah, 5 = sangat tinggi</span>
            </div>
            <select v-else :id="`svc-${d.id}-${f.key}`" v-model="reportForm[f.key]" required>
              <option value="" disabled>Pilih...</option>
              <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <button type="submit" class="btn btn--primary" :disabled="saving">
            Kirim laporan &amp; tandai selesai
          </button>
        </form>

        <dl v-if="d.report" class="svc__report">
          <template v-for="f in fields" :key="f.key">
            <dt>{{ f.label }}</dt>
            <dd>
              <template v-if="f.type === 'score'">{{ d.report[f.key] }} / 5</template>
              <template v-else>{{ d.report[f.key] }}</template>
            </dd>
          </template>
          <dt>Dilaporkan</dt>
          <dd>{{ formatTanggal(d.report_submitted_at, true) }}</dd>
        </dl>
      </template>
    </div>
  </div>
</template>

<style scoped>
.svc {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 12px 14px;
}
.svc__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.svc__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.svc__tipe {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--color-seal-dark);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.svc__meta {
  font-size: 13px;
  color: var(--color-text-soft);
}
.svc__link {
  font-size: 13px;
}
.svc__body {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.svc__form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.svc__form h4 {
  margin: 0;
  font-size: 14px;
}
.svc__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13.5px;
}
.svc__scores {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.svc__score {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.svc__score input {
  width: auto;
}
.svc__report {
  display: grid;
  grid-template-columns: minmax(140px, 1fr) 2fr;
  gap: 6px 14px;
  margin: 0;
  font-size: 13.5px;
}
.svc__report dt {
  color: var(--color-text-soft);
}
.svc__report dd {
  margin: 0;
  white-space: pre-wrap;
}
@media (max-width: 560px) {
  .svc__report {
    grid-template-columns: 1fr;
  }
}
</style>
