<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

/** Override komisi per grafolog untuk satu produk (menggantikan komisi default produk). */
const props = defineProps({ productId: { type: Number, required: true } })
const toast = useToast()
const overrides = ref([])
const grafologs = ref([])
const draft = ref({ grafolog_id: '', type: 'percent', value: 0 })

async function load() {
  overrides.value = (await api.get(`/admin/products/${props.productId}/commissions`)).data.overrides
}
async function add() {
  try {
    await api.put(`/admin/products/${props.productId}/commissions/${draft.value.grafolog_id}`, {
      type: draft.value.type,
      value: Number(draft.value.value),
    })
    draft.value = { grafolog_id: '', type: 'percent', value: 0 }
    await load()
  } catch (e) {
    toast.push(
      e.response?.data?.errors?.value?.[0] ?? e.response?.data?.message ?? 'Gagal menyimpan.',
    )
  }
}
async function remove(o) {
  await api.delete(`/admin/products/${props.productId}/commissions/${o.grafolog_id}`)
  await load()
}
onMounted(async () => {
  grafologs.value = (await api.get('/grafologs')).data
  await load()
})
</script>

<template>
  <div class="pco">
    <strong>Override per grafolog</strong>
    <ul v-if="overrides.length">
      <li v-for="o in overrides" :key="o.grafolog_id">
        {{ o.grafolog }}:
        {{ o.type === 'fixed' ? 'Rp ' + o.value.toLocaleString('id-ID') : o.value + '%' }}
        <button type="button" class="btn" @click="remove(o)">Hapus</button>
      </li>
    </ul>
    <div class="pco__add">
      <select v-model="draft.grafolog_id" aria-label="Grafolog">
        <option value="" disabled>Pilih grafolog</option>
        <option v-for="g in grafologs" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
      <select v-model="draft.type" aria-label="Jenis">
        <option value="percent">%</option>
        <option value="fixed">Rp</option>
      </select>
      <input v-model.number="draft.value" type="number" min="0" aria-label="Nilai" />
      <button type="button" class="btn" :disabled="!draft.grafolog_id" @click="add">
        Simpan override
      </button>
    </div>
  </div>
</template>

<style scoped>
.pco ul {
  padding-left: 18px;
  font-size: 13.5px;
}
.pco__add {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 6px 0;
}
.pco__add select,
.pco__add input {
  width: auto;
}
</style>
