<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="landing">
    <p class="landing__eyebrow">Analisis grafologi tepercaya</p>
    <h1>Guratan</h1>
    <p class="landing__tagline">
      Analisis kepribadian berbasis grafologi - laporan komprehensif dari tulisan tangan,
      disusun oleh grafolog bersertifikat.
    </p>
    <div class="landing__actions">
      <RouterLink to="/register" class="btn btn--primary">Mulai Sekarang</RouterLink>
      <RouterLink to="/login" class="btn">Masuk</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.landing {
  text-align: center;
  padding: 60px 16px;
}
.landing__eyebrow {
  font-family: var(--font-accent);
  font-size: 20px;
  color: var(--color-seal);
  margin-bottom: 4px;
}
.landing h1 {
  font-size: 40px;
  margin-bottom: 12px;
}
.landing__tagline {
  color: var(--color-text-soft);
  max-width: 480px;
  margin: 0 auto 28px;
  line-height: 1.5;
}
.landing__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  justify-content: center;
}
</style>
