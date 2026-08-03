<script setup>
defineProps({
  sindrom: { type: Array, default: () => [] }, // preview() response: [{ nama, kode_romawi, rata_rata_skor, band_label_rata_rata, aspek }]
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <div class="auto-calc">
    <h3 class="auto-calc__title">Auto Calculation</h3>
    <p v-if="sindrom.length === 0" class="auto-calc__empty">
      Isi skor untuk melihat kalkulasi otomatis per sindrom.
    </p>
    <ul v-else class="auto-calc__list" :class="{ 'auto-calc__list--loading': loading }">
      <li v-for="s in sindrom" :key="s.id" class="auto-calc__item">
        <div class="auto-calc__item-head">
          <span class="auto-calc__item-nama">{{ s.kode_romawi }}. {{ s.nama }}</span>
          <span class="auto-calc__item-skor">{{ s.rata_rata_skor }}</span>
        </div>
        <span class="auto-calc__item-band">{{ s.band_label_rata_rata }}</span>
        <span class="auto-calc__item-count">{{ s.aspek.length }} aspek dinilai</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.auto-calc {
  position: sticky;
  top: 16px;
}
.auto-calc__title {
  font-size: 15px;
  margin-bottom: 12px;
}
.auto-calc__empty {
  color: var(--color-text-soft);
  font-size: 13px;
}
.auto-calc__list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: opacity 0.15s ease;
}
.auto-calc__list--loading {
  opacity: 0.5;
}
.auto-calc__item {
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
}
.auto-calc__item-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}
.auto-calc__item-nama {
  font-weight: 600;
  font-size: 13px;
}
.auto-calc__item-skor {
  font-family: var(--font-heading);
  font-size: 18px;
  color: var(--color-seal);
}
.auto-calc__item-band {
  display: block;
  font-size: 12px;
  color: var(--color-text-soft);
  margin-top: 2px;
}
.auto-calc__item-count {
  display: block;
  font-size: 11px;
  color: var(--color-text-soft);
  margin-top: 4px;
}
</style>
