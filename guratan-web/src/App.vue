<script setup>
import { RouterView } from 'vue-router'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import ToastNotification from '@/components/shared/ToastNotification.vue'
import CommandPalette from '@/components/shared/CommandPalette.vue'
</script>

<template>
  <div class="app-shell">
    <AppNavbar />

    <main class="app-main">
      <RouterView />
    </main>

    <ToastNotification />
    <CommandPalette />
  </div>
</template>

<style scoped>
.app-main {
  max-width: 960px;
  margin: 0 auto;
  padding: 24px 20px;
}

@media (max-width: 640px) {
  .app-main {
    padding: 20px 16px;
  }
}
</style>
