<script setup>
import { ref, computed } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  sampleId: { type: Number, required: true },
  consultation: { type: Object, default: null },
  editable: { type: Boolean, default: false },
})
const emit = defineEmits(['saved'])

const toast = useToast()
const editing = ref(false)
const saving = ref(false)
const errors = ref({})

function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const form = ref({})
function startEdit() {
  form.value = {
    scheduled_at: toLocalInput(props.consultation?.scheduled_at),
    meeting_url: props.consultation?.meeting_url ?? '',
    notes: props.consultation?.notes ?? '',
    status: props.consultation?.status ?? 'dijadwalkan',
  }
  errors.value = {}
  editing.value = true
}

const statusLabel = { dijadwalkan: 'Dijadwalkan', selesai: 'Selesai', dibatalkan: 'Dibatalkan' }
const formattedTime = computed(() =>
  props.consultation
    ? new Date(props.consultation.scheduled_at).toLocaleString('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
      })
    : '',
)

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const { data } = await api.put(`/samples/${props.sampleId}/consultation`, {
      ...form.value,
      scheduled_at: new Date(form.value.scheduled_at).toISOString(),
    })
    toast.push('Jadwal konsultasi disimpan, klien diberi tahu lewat email.', 'success')
    editing.value = false
    emit('saved', data)
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    if (!e.response?.data?.errors)
      toast.push(e.response?.data?.message ?? 'Gagal menyimpan jadwal.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="consultation">
    <h2>Sesi Konsultasi (Master)</h2>

    <template v-if="!editing">
      <div v-if="consultation">
        <p>
          <strong>{{ formattedTime }}</strong> ·
          {{ statusLabel[consultation.status] ?? consultation.status }}
        </p>
        <p>
          <a :href="consultation.meeting_url" target="_blank" rel="noopener noreferrer">{{
            consultation.meeting_url
          }}</a>
        </p>
        <p v-if="consultation.notes" class="consultation__notes">{{ consultation.notes }}</p>
      </div>
      <p v-else class="consultation__empty">
        {{
          editable
            ? 'Belum dijadwalkan.'
            : 'Grafolog akan segera menghubungi Anda untuk menjadwalkan sesi konsultasi.'
        }}
      </p>
      <button v-if="editable" type="button" class="btn" @click="startEdit">
        {{ consultation ? 'Ubah Jadwal' : 'Jadwalkan Konsultasi' }}
      </button>
    </template>

    <form v-else class="consultation__form" @submit.prevent="save">
      <label>
        Waktu
        <input v-model="form.scheduled_at" type="datetime-local" required />
      </label>
      <p v-if="errors.scheduled_at" class="error">{{ errors.scheduled_at[0] }}</p>
      <label>
        Tautan meeting (https)
        <input
          v-model="form.meeting_url"
          type="url"
          placeholder="https://meet.google.com/..."
          required
        />
      </label>
      <p v-if="errors.meeting_url" class="error">{{ errors.meeting_url[0] }}</p>
      <label>
        Catatan untuk klien (opsional)
        <textarea v-model="form.notes" rows="2"></textarea>
      </label>
      <label v-if="consultation">
        Status
        <select v-model="form.status">
          <option value="dijadwalkan">Dijadwalkan</option>
          <option value="selesai">Selesai</option>
          <option value="dibatalkan">Dibatalkan</option>
        </select>
      </label>
      <div class="consultation__buttons">
        <button type="submit" class="btn btn--primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
        <button type="button" class="btn" @click="editing = false">Batal</button>
      </div>
    </form>
  </section>
</template>

<style scoped>
.consultation {
  border: 1px solid var(--color-border);
  border-radius: 6px;
  padding: 14px;
  margin: 18px 0;
  background: var(--color-surface);
  font-size: 14px;
}
.consultation h2 {
  font-size: 16px;
  margin: 0 0 8px;
}
.consultation p {
  margin: 0 0 6px;
  overflow-wrap: anywhere;
}
.consultation__empty,
.consultation__notes {
  color: var(--color-text-soft);
}
.consultation__form label {
  display: block;
  margin-bottom: 10px;
}
.consultation__form input,
.consultation__form textarea,
.consultation__form select {
  width: 100%;
}
.consultation__buttons {
  display: flex;
  gap: 8px;
}
</style>
