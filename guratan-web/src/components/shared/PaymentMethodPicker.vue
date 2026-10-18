<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'

/** Pilihan metode bayar (opsional). `allowed` dari kode promo membatasi pilihan; kosong = bebas. */
const props = defineProps({
  modelValue: { type: String, default: '' },
  allowed: { type: Array, default: null },
})
const emit = defineEmits(['update:modelValue'])
const groups = ref([])
onMounted(async () => {
  try {
    groups.value = (await api.get('/payment-methods')).data
  } catch {
    groups.value = []
  }
})
const options = computed(() =>
  props.allowed?.length ? groups.value.filter((g) => props.allowed.includes(g.key)) : groups.value,
)
</script>

<template>
  <label v-if="groups.length" class="pmp">
    Metode pembayaran
    <select :value="modelValue" @change="emit('update:modelValue', $event.target.value)">
      <option value="">
        {{ allowed?.length ? 'Semua metode promo' : 'Pilih di halaman bayar' }}
      </option>
      <option v-for="g in options" :key="g.key" :value="g.key">{{ g.label }}</option>
    </select>
    <small v-if="allowed?.length" class="pmp__note">
      Promo ini hanya berlaku untuk: {{ options.map((g) => g.label).join(', ') }}.
    </small>
  </label>
</template>

<style scoped>
.pmp {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  margin: 10px 0;
}
.pmp select {
  width: auto;
  align-self: flex-start;
}
.pmp__note {
  color: var(--color-text-soft);
}
</style>
