<script setup>
import CvForm from '@/components/cv/CvForm.vue'
</script>

<template>
  <div class="cvv">
    <h1>CV Saya</h1>
    <p class="cvv__hint">
      CV ini membantu pihak yang memeriksa dan diperiksa saling mengenal (klien melihat profil
      grafolog pemeriksa, begitu pula HR). Isian bertanda 🔒 hanya terlihat oleh Anda dan
      administrator.
    </p>
    <CvForm />
  </div>
</template>

<style scoped>
.cvv {
  max-width: 720px;
}
.cvv__hint {
  color: var(--color-text-soft);
  font-size: 13px;
}
</style>
