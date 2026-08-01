<script setup>
import TraitBar from './TraitBar.vue'

defineProps({
  data: { type: Object, required: true }, // { sindrom: [...] }
})
</script>

<template>
  <div class="report-document">
    <section v-for="sindrom in data.sindrom" :key="sindrom.id" class="report-document__sindrom">
      <header class="report-document__sindrom-header">
        <h2>{{ sindrom.kode_romawi }}. {{ sindrom.nama }}</h2>
        <p class="report-document__meta">
          Polaritas {{ sindrom.polaritas }} &middot; rata-rata skor
          {{ sindrom.rata_rata_skor }} ({{ sindrom.band_label_rata_rata }})
        </p>
      </header>

      <div v-for="aspek in sindrom.aspek" :key="aspek.kode" class="report-document__aspek">
        <TraitBar :nama="aspek.nama" :skor="aspek.skor" :band-label="aspek.band_label" />
        <p class="report-document__narasi">{{ aspek.narasi }}</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.report-document {
  max-width: 100%;
  overflow-wrap: break-word;
}
.report-document__sindrom {
  margin-bottom: 28px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--color-border);
}
.report-document__sindrom-header h2 {
  font-size: 18px;
  margin-bottom: 2px;
}
.report-document__meta {
  color: var(--color-text-soft);
  font-size: 12px;
  margin-bottom: 14px;
}
.report-document__aspek {
  margin-bottom: 16px;
  max-width: 100%;
}
.report-document__narasi {
  font-size: 13px;
  line-height: 1.5;
  color: var(--color-text);
  white-space: pre-line;
  overflow-wrap: break-word;
}
</style>
