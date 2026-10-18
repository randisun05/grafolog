<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { useToast } from '@/composables/useToast'

/** Form/tampilan CV. userId kosong = CV sendiri; selain itu CV orang lain (disaring server per peran). */
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

const base = () => (props.userId ? `/cv/users/${props.userId}` : '/cv')

function apply(data) {
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
            >{{ f.label }}<span v-if="f.required && editable" class="cv__req"> *</span></label
          >
          <template v-if="editable">
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
