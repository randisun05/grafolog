<script setup>
import { ref, computed } from 'vue'
import AspekRow from './AspekRow.vue'

const props = defineProps({
  sindrom: { type: Object, required: true }, // { nama, kode_romawi, aspek: [{kode, nama}] }
  scores: { type: Object, required: true }, // { [kode]: { skor, catatan_grafolog } }
})
const emit = defineEmits(['update:scores'])

const open = ref(false)

const isComplete = computed(() =>
  props.sindrom.aspek.every((a) => Number.isInteger(props.scores[a.kode]?.skor)),
)

function updateAspekScore(kode, value) {
  emit('update:scores', { ...props.scores, [kode]: value })
}
</script>

<template>
  <div class="sindrom-accordion">
    <button type="button" class="sindrom-accordion__toggle" @click="open = !open">
      <span>{{ sindrom.kode_romawi }}. {{ sindrom.nama }}</span>
      <span class="sindrom-accordion__status" :class="{ complete: isComplete }">
        {{ isComplete ? 'Lengkap' : `${sindrom.aspek.filter((a) => scores[a.kode]?.skor).length}/${sindrom.aspek.length}` }}
      </span>
    </button>
    <div v-show="open" class="sindrom-accordion__body">
      <AspekRow
        v-for="aspek in sindrom.aspek"
        :key="aspek.kode"
        :aspek="aspek"
        :model-value="scores[aspek.kode] ?? { skor: null, catatan_grafolog: '' }"
        @update:model-value="(value) => updateAspekScore(aspek.kode, value)"
      />
    </div>
  </div>
</template>

<style scoped>
.sindrom-accordion {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: 10px;
  overflow: hidden;
  background: var(--color-surface);
}
.sindrom-accordion__toggle {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--color-paper);
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-family: var(--font-heading);
  color: var(--color-ink);
  text-align: left;
}
.sindrom-accordion__status {
  font-size: 12px;
  font-weight: normal;
  font-family: var(--font-body);
  color: var(--color-seal-dark);
}
.sindrom-accordion__status.complete {
  color: var(--color-success);
}
.sindrom-accordion__body {
  padding: 4px 16px 8px;
}
</style>
