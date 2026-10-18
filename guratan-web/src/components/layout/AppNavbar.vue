<script setup>
import { computed, ref } from 'vue'
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

const adminMenu = ref(null)
function closeAdminMenu() {
  if (adminMenu.value) adminMenu.value.open = false
}

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
        <RouterLink v-if="auth.isPendingGrafolog" to="/status-grafolog"
          >Status Pendaftaran</RouterLink
        >
        <RouterLink v-else-if="auth.isPendingHr" to="/status-perusahaan"
          >Status Pendaftaran</RouterLink
        >
        <RouterLink v-else-if="auth.isCandidate" to="/tugas-asesmen">Tugas Asesmen</RouterLink>
        <template v-else>
          <RouterLink to="/dashboard">Dashboard</RouterLink>
          <RouterLink to="/riwayat">Riwayat</RouterLink>
        </template>
        <RouterLink v-if="auth.isClient" to="/pesan">Pesan Layanan</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/portal-grafolog">Portal Grafolog</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/grafolog/ditugaskan">Ditugaskan ke Saya</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/grafolog/supervisi">Supervisi</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/token-saya">Token Saya</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/orders">Pesanan Masuk</RouterLink>
        <details v-if="auth.isAdministrator" ref="adminMenu" class="app-navbar__menu">
          <summary>Admin</summary>
          <div class="app-navbar__menu-list" @click="closeAdminMenu">
            <RouterLink to="/admin/products">Kelola Produk</RouterLink>
            <RouterLink to="/admin/discounts">Kelola Promo &amp; Diskon</RouterLink>
            <RouterLink to="/admin/users">Kelola Staf</RouterLink>
            <RouterLink to="/admin/grafolog">Verifikasi Grafolog</RouterLink>
            <RouterLink to="/admin/perusahaan">Verifikasi Perusahaan</RouterLink>
            <RouterLink to="/admin/validasi">Penugasan Supervisi</RouterLink>
            <RouterLink to="/admin/tokens">Kelola Token</RouterLink>
            <RouterLink to="/admin/content">Kelola Konten</RouterLink>
            <RouterLink to="/admin/engagement">Cek Tulisan, Mitra &amp; Artikel</RouterLink>
            <RouterLink to="/admin/announcements">Pengumuman</RouterLink>
            <RouterLink to="/admin/knowledge">Knowledge Base</RouterLink>
          </div>
        </details>
        <RouterLink v-if="auth.isHr && !auth.isPendingHr" to="/hr/candidates">Rekrutmen</RouterLink>
        <RouterLink
          v-if="!auth.isPendingGrafolog && !auth.isPendingHr"
          to="/profil"
          class="app-navbar__user"
          title="Profil saya"
          >{{ auth.user?.name }}</RouterLink
        >
        <span v-else class="app-navbar__user">{{ auth.user?.name }}</span>
        <span class="app-navbar__cmdk-hint" title="Buka command palette">Ctrl/&#8984;+K</span>
        <button type="button" class="app-navbar__logout" @click="handleLogout">Keluar</button>
      </template>
      <template v-else>
        <RouterLink to="/cek-tulisan">Cek Tulisan Gratis</RouterLink>
        <RouterLink to="/artikel">Artikel</RouterLink>
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
        <svg
          v-if="isDark"
          viewBox="0 0 24 24"
          width="16"
          height="16"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="12" cy="12" r="4" />
          <path
            d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
          />
        </svg>
        <svg
          v-else
          viewBox="0 0 24 24"
          width="16"
          height="16"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
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
.app-navbar__menu {
  position: relative;
}
.app-navbar__menu summary {
  cursor: pointer;
  color: var(--color-ink-soft);
  list-style: none;
}
.app-navbar__menu summary::-webkit-details-marker {
  display: none;
}
.app-navbar__menu summary::after {
  content: ' ▾';
  font-size: 11px;
}
.app-navbar__menu[open] summary {
  color: var(--color-seal);
}
.app-navbar__menu-list {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  z-index: 30;
  display: flex;
  flex-direction: column;
  min-width: 220px;
  padding: 6px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.app-navbar__menu-list a {
  padding: 8px 10px;
  border-radius: var(--radius-sm);
}
.app-navbar__menu-list a:hover {
  background: var(--color-seal-soft);
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
