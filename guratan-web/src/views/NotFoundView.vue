<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="not-found">
    <h1>404</h1>
    <p>Halaman tidak ditemukan.</p>
    <RouterLink to="/" class="btn btn--primary">Kembali ke beranda</RouterLink>
  </div>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 80px 16px;
}
.not-found h1 {
  font-size: 56px;
  color: var(--color-seal);
}
.not-found p {
  color: var(--color-text-soft);
  margin: 8px 0 20px;
}
</style>
