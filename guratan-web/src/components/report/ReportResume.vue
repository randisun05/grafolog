<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'

/** Resume (ringkasan) laporan untuk klien: rata-rata per sindrom + ciri paling menonjol. Tanpa AI. */
const props = defineProps({
  reportId: { type: [Number, String], required: true },
  bahasa: { type: String, default: 'id' },
})
const data = ref(null)
const error = ref(false)

async function load() {
  try {
    data.value = (
      await api.get(`/reports/${props.reportId}/resume`, { params: { lang: props.bahasa } })
    ).data
    error.value = false
  } catch {
    error.value = true
  }
}
onMounted(load)
watch(() => [props.reportId, props.bahasa], load)
const pct = (v) => Math.min(100, Math.max(0, (v / 10) * 100))
</script>

<template>
  <section v-if="data && !error" class="resume">
    <h2>{{ bahasa === 'en' ? 'Your summary' : 'Ringkasan untuk Anda' }}</h2>
    <h3>{{ bahasa === 'en' ? 'Average per area' : 'Rata-rata per bidang' }}</h3>
    <ul class="resume__bars">
      <li v-for="s in data.sindrom" :key="s.nama">
        <span class="resume__name">{{ s.nama }}</span>
        <span class="resume__bar" role="img" :aria-label="`${s.nama}: ${s.rata_rata} / 10`"
          ><span :style="{ width: pct(s.rata_rata) + '%' }"
        /></span>
        <span class="resume__val"
          >{{ s.rata_rata }}<small v-if="s.band"> · {{ s.band }}</small></span
        >
      </li>
    </ul>
    <h3>{{ bahasa === 'en' ? 'Most prominent traits' : 'Ciri paling menonjol' }}</h3>
    <ol class="resume__top">
      <li v-for="a in data.menonjol" :key="a.nama">
        <strong>{{ a.nama }}</strong> <small>({{ a.sindrom }} · {{ a.skor }}/10)</small>
        <p>{{ a.ringkas }}</p>
      </li>
    </ol>
    <p class="resume__note">{{ data.catatan }}</p>
  </section>
</template>

<style scoped>
.resume {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 14px 16px;
  margin: 0 0 20px;
}
.resume h2 {
  font-size: 18px;
  margin: 0 0 8px;
}
.resume h3 {
  font-size: 14px;
  margin: 12px 0 6px;
}
.resume__bars,
.resume__top {
  margin: 0;
  padding: 0;
}
.resume__bars {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.resume__bars li {
  display: grid;
  grid-template-columns: minmax(110px, 1.2fr) 2fr minmax(80px, auto);
  gap: 8px;
  align-items: center;
  font-size: 13px;
}
.resume__bar {
  height: 8px;
  background: var(--color-border);
  border-radius: 999px;
  overflow: hidden;
}
.resume__bar span {
  display: block;
  height: 100%;
  background: var(--color-sage);
}
.resume__val small {
  color: var(--color-text-soft);
}
.resume__top {
  padding-left: 18px;
}
.resume__top p {
  margin: 2px 0 8px;
  font-size: 13.5px;
  color: var(--color-text-soft);
}
.resume__note {
  font-size: 12.5px;
  color: var(--color-text-soft);
  margin: 10px 0 0;
}
</style>
