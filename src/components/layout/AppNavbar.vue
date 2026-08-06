<script setup>
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'

const auth = useAuthStore()
const router = useRouter()
const { theme, toggle: toggleTheme } = useTheme()

const isDark = computed(() => {
  if (theme.value === 'dark') return true
  if (theme.value === 'light') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
})

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
        <RouterLink to="/dashboard">Dashboard</RouterLink>
        <RouterLink to="/riwayat">Riwayat</RouterLink>
        <RouterLink v-if="auth.isClient" to="/pesan">Pesan Laporan</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/portal-grafolog">Portal Grafolog</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/grafolog/ditugaskan">Ditugaskan ke Saya</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/users">Kelola Staf</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/pricing">Kelola Harga</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/discounts">Kelola Diskon</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/content">Kelola Konten</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/announcements">Pengumuman</RouterLink>
        <RouterLink v-if="auth.isHr" to="/hr/candidates">Kandidat</RouterLink>
        <span class="app-navbar__user">{{ auth.user?.name }}</span>
        <span class="app-navbar__cmdk-hint" title="Buka command palette">Ctrl/&#8984;+K</span>
        <button type="button" class="app-navbar__logout" @click="handleLogout">Keluar</button>
      </template>
      <template v-else>
        <RouterLink to="/login">Masuk</RouterLink>
        <RouterLink to="/register">Daftar</RouterLink>
      </template>

      <button
        type="button"
        class="app-navbar__theme"
        :aria-label="isDark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
        :title="isDark ? 'Mode terang' : 'Mode gelap'"
        @click="toggleTheme"
      >
        <svg v-if="isDark" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="4" />
          <path
            d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
          />
        </svg>
        <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
      </button>
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
.app-navbar__cmdk-hint {
  font-size: 11px;
  padding: 3px 6px;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  color: var(--color-text-soft);
  font-family: monospace;
}
@media (max-width: 640px) {
  .app-navbar__cmdk-hint {
    display: none;
  }
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
.app-navbar__theme {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 1px solid var(--color-border-hover);
  background: var(--color-paper-alt);
  color: var(--color-ink-soft);
  border-radius: var(--radius-sm);
  cursor: pointer;
}
.app-navbar__theme:hover {
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
