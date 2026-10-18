<script setup>
import CvForm from '@/components/cv/CvForm.vue'
</script>

<template>
  <div class="cvv">
    <h1>CV Saya</h1>
    <p class="cvv__hint">
      Data diri ini membantu grafolog memahami konteks Anda. Siapa yang dapat melihat tiap isian
      diatur sistem; isian tidak wajib kecuali bertanda *.
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
