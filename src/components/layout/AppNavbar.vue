<script setup>
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'landing' })
}
</script>

<template>
  <header class="app-navbar">
    <RouterLink to="/" class="app-navbar__brand">Guratan</RouterLink>

    <nav class="app-navbar__nav">
      <template v-if="auth.isAuthenticated">
        <RouterLink v-if="!auth.isGrafolog" to="/upload">Unggah</RouterLink>
        <RouterLink to="/riwayat">Riwayat</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/portal-grafolog">Portal Grafolog</RouterLink>
        <span class="app-navbar__user">{{ auth.user?.name }}</span>
        <button type="button" class="app-navbar__logout" @click="handleLogout">Keluar</button>
      </template>
      <template v-else>
        <RouterLink to="/login">Masuk</RouterLink>
        <RouterLink to="/register">Daftar</RouterLink>
      </template>
    </nav>
  </header>
</template>

<style scoped>
.app-navbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px 16px;
  padding: 14px 20px;
  background: var(--color-paper-alt);
  border-bottom: 1px solid var(--color-border);
}
.app-navbar__brand {
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 22px;
  text-decoration: none;
  color: var(--color-ink);
}
.app-navbar__nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 16px;
  font-size: 14px;
}
.app-navbar__nav a {
  color: var(--color-ink-soft);
  text-decoration: none;
}
.app-navbar__nav a:hover,
.app-navbar__nav a.router-link-exact-active {
  color: var(--color-seal);
}
.app-navbar__user {
  color: var(--color-ink-soft);
}
.app-navbar__logout {
  border: 1px solid var(--color-border-hover);
  background: var(--color-paper-alt);
  color: var(--color-ink);
  border-radius: var(--radius-sm);
  padding: 6px 12px;
  font-size: 13px;
  cursor: pointer;
}
.app-navbar__logout:hover {
  border-color: var(--color-seal);
  color: var(--color-seal);
}

@media (max-width: 640px) {
  .app-navbar {
    padding: 12px 16px;
  }
  .app-navbar__nav {
    width: 100%;
    gap: 8px 14px;
    font-size: 13px;
  }
}
</style>
