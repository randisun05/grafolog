<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

/** Form/tampilan CV. userId kosong = CV sendiri; selain itu CV orang lain (disaring server per peran). */
const emit = defineEmits(['loaded'])
const props = defineProps({
  userId: { type: [Number, String], default: null },
})
const toast = useToast()
const groups = ref([])
const editable = ref(false)
const values = ref({})
const errors = ref({})
const loading = ref(true)
const saving = ref(false)

const photos = ref({}) // key -> object URL
const uploading = ref('')
const fileBase = () => (props.userId ? `/cv/users/${props.userId}/files` : '/cv/files')

async function loadPhotos() {
  Object.values(photos.value).forEach((u) => URL.revokeObjectURL(u))
  photos.value = {}
  for (const f of groups.value.flatMap((g) => g.fields)) {
    if (f.type !== 'photo' || !f.value) continue
    try {
      const { data } = await api.get(f.value.url, { responseType: 'blob' })
      photos.value[f.key] = URL.createObjectURL(data)
    } catch {
      // foto tidak bisa dimuat: tampilkan placeholder teks
    }
  }
}

async function upload(f, event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  uploading.value = f.key
  try {
    apply((await api.post(`${fileBase()}/${f.key}`, fd)).data)
    await loadPhotos()
    toast.push('Berkas diunggah.')
  } catch (e) {
    toast.push(
      e.response?.data?.errors?.file?.[0] ?? e.response?.data?.message ?? 'Gagal mengunggah.',
    )
  } finally {
    uploading.value = ''
  }
}

async function removeFile(f) {
  if (!window.confirm(`Hapus berkas "${f.label}"?`)) return
  apply((await api.delete(`${fileBase()}/${f.key}`)).data)
  await loadPhotos()
}

async function download(f) {
  const { data } = await api.get(f.value.url, { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = f.value.name
  a.click()
  URL.revokeObjectURL(url)
}

const base = () => (props.userId ? `/cv/users/${props.userId}` : '/cv')

function apply(data) {
  emit('loaded', { missing_required: data.missing_required ?? 0, editable: data.editable })
  groups.value = data.groups
  editable.value = data.editable
  values.value = Object.fromEntries(
    data.groups.flatMap((g) => g.fields.map((f) => [f.key, f.value])),
  )
}

async function load() {
  loading.value = true
  try {
    apply((await api.get(base())).data)
    await loadPhotos()
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    apply((await api.put(base(), { values: values.value })).data)
    toast.push('CV disimpan.')
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan CV.')
  } finally {
    saving.value = false
  }
}

function show(v, f) {
  if (v === null || v === undefined || v === '') return '-'
  return f.type === 'boolean' ? (v ? 'Ya' : 'Tidak') : v
}

onMounted(load)
onBeforeUnmount(() => Object.values(photos.value).forEach((u) => URL.revokeObjectURL(u)))
watch(() => props.userId, load)
</script>

<template>
  <div class="cv">
    <SkeletonLoader v-if="loading" variant="form" :rows="3" label="Memuat CV..." />
    <p v-else-if="!groups.length" class="cv__hint">Belum ada isian CV yang perlu diisi.</p>
    <form v-else @submit.prevent="save">
      <fieldset v-for="g in groups" :key="g.id" class="cv__group">
        <legend>{{ g.name }}</legend>
        <p v-if="g.description" class="cv__hint">{{ g.description }}</p>
        <div v-for="f in g.fields" :key="f.id" class="cv__row">
          <label :for="`cv-${f.key}`"
            >{{ f.label }}<span v-if="f.required && editable" class="cv__req"> *</span
            ><span v-if="f.is_hidden" class="cv__hid"> 🔒 tersembunyi</span></label
          >
          <div v-if="f.type === 'photo' || f.type === 'file'" class="cv__file">
            <img
              v-if="f.type === 'photo' && photos[f.key]"
              :src="photos[f.key]"
              alt=""
              class="cv__photo"
            />
            <button v-else-if="f.value" type="button" class="btn" @click="download(f)">
              ⬇ {{ f.value.name }}
            </button>
            <span v-if="!f.value" class="cv__value">-</span>
            <template v-if="editable">
              <label class="btn cv__pick">
                {{ uploading === f.key ? 'Mengunggah...' : f.value ? 'Ganti' : 'Unggah' }}
                <input
                  type="file"
                  hidden
                  :accept="f.type === 'photo' ? 'image/jpeg,image/png,image/webp' : '.pdf,.docx'"
                  :disabled="uploading === f.key"
                  @change="upload(f, $event)"
                />
              </label>
              <button v-if="f.value" type="button" class="btn" @click="removeFile(f)">Hapus</button>
              <small v-if="f.help" class="cv__hint">{{ f.help }}</small>
            </template>
          </div>
          <template v-else-if="editable">
            <textarea
              v-if="f.type === 'textarea'"
              :id="`cv-${f.key}`"
              v-model="values[f.key]"
              rows="3"
            />
            <select v-else-if="f.type === 'select'" :id="`cv-${f.key}`" v-model="values[f.key]">
              <option :value="null">-</option>
              <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
            </select>
            <input
              v-else-if="f.type === 'boolean'"
              :id="`cv-${f.key}`"
              v-model="values[f.key]"
              type="checkbox"
              class="cv__check"
            />
            <input
              v-else
              :id="`cv-${f.key}`"
              v-model="values[f.key]"
              :type="
                { number: 'number', date: 'date', email: 'email', phone: 'tel' }[f.type] ?? 'text'
              "
              :step="f.type === 'number' ? 'any' : undefined"
            />
            <small v-if="f.help" class="cv__hint">{{ f.help }}</small>
            <small v-if="errors[f.key]" class="error">{{ errors[f.key][0] }}</small>
          </template>
          <span v-else class="cv__value">{{ show(values[f.key], f) }}</span>
        </div>
      </fieldset>
      <button v-if="editable" class="btn btn--primary" :disabled="saving">
        {{ saving ? 'Menyimpan...' : 'Simpan CV' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.cv__group {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin: 0 0 14px;
  padding: 10px 14px 14px;
}
.cv__group legend {
  font-weight: 600;
  padding: 0 6px;
}
.cv__row {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-top: 10px;
}
.cv__row label {
  font-size: 13px;
  color: var(--color-ink-soft);
}
.cv__check {
  width: auto;
  align-self: flex-start;
}
.cv__hid {
  font-size: 11.5px;
  color: var(--color-text-soft);
}
.cv__file {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.cv__photo {
  width: 96px;
  height: 96px;
  object-fit: cover;
  border-radius: 50%;
  border: 1px solid var(--color-border);
}
.cv__pick {
  cursor: pointer;
}
.cv__req {
  color: var(--color-seal);
}
.cv__hint {
  color: var(--color-text-soft);
  font-size: 12.5px;
  margin: 0;
}
.cv__value {
  font-size: 14px;
  white-space: pre-line;
}
</style>
