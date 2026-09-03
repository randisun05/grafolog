<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'
import { useNotifications } from '@/composables/useNotifications'

const auth = useAuthStore()
const router = useRouter()
const { theme, toggle: toggleTheme } = useTheme()
const { notifications, unreadCount, load: loadNotifications, reset: resetNotifications, markAllRead } = useNotifications()

const isDark = computed(() => {
  if (theme.value === 'dark') return true
  if (theme.value === 'light') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
})

async function handleLogout() {
  mobileMenuOpen.value = false
  await auth.logout()
  router.push({ name: 'landing' })
}

// Menu hamburger - nav ini punya ~20 link admin, di layar HP (<=640px)
// semuanya di-collapse ke sini alih-alih di-wrap jadi berbaris (lihat
// guratan-web/CLAUDE.md "Fix: admin tables overflow..." - temuan mobile
// yang sama juga berlaku ke navbar, cuma bukan overflow, cuma makan
// ruang). Di desktop toggle-nya disembunyikan lewat CSS dan nav selalu
// tampil seperti sebelumnya - JS-nya sama untuk kedua ukuran layar, cuma
// visual collapse-nya murni CSS.
const mobileMenuOpen = ref(false)
const headerRoot = ref(null)

function closeMobileMenuOnLinkClick(e) {
  if (e.target.closest('a')) mobileMenuOpen.value = false
}

// Bel notifikasi (pengumuman/promo/diskon per role - lihat
// guratan-api/CLAUDE.md "Notifikasi/Pengumuman/Promo") - mengganti banner
// dashboard-only lama yang cuma dismiss lokal (tidak persisten, muncul
// lagi tiap kunjungan). Global di navbar supaya kelihatan dari halaman
// manapun, bukan cuma saat buka /dashboard.
const notifOpen = ref(false)
const notifRoot = ref(null)

function toggleNotif() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) markAllRead()
}

function closeOnOutsideClick(e) {
  if (notifRoot.value && !notifRoot.value.contains(e.target)) notifOpen.value = false
  // Pakai composedPath(), bukan e.target langsung: klik toggle sendiri
  // membuat Vue langsung menukar ikon hamburger<->X (v-if), jadi node
  // e.target (elemen <path> yang diklik) sudah lepas dari DOM begitu
  // handler ini jalan - headerRoot.contains(e.target) selalu false untuk
  // node yang sudah detached, walau klik itu jelas di dalam header.
  // composedPath() adalah snapshot rantai leluhur SAAT event ditembakkan,
  // jadi tetap valid meski elemen paling dalamnya sudah diganti.
  const path = e.composedPath()
  if (headerRoot.value && !path.includes(headerRoot.value)) mobileMenuOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', closeOnOutsideClick)
  if (auth.isAuthenticated) loadNotifications()
})
onUnmounted(() => document.removeEventListener('click', closeOnOutsideClick))

watch(
  () => auth.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) loadNotifications()
    else resetNotifications()
  },
)

function formatNotifDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <header ref="headerRoot" class="app-navbar">
    <RouterLink to="/" class="app-navbar__brand">Guratan</RouterLink>

    <button
      type="button"
      class="app-navbar__toggle"
      :aria-expanded="mobileMenuOpen"
      aria-controls="app-navbar-nav"
      :aria-label="mobileMenuOpen ? 'Tutup menu' : 'Buka menu'"
      @click="mobileMenuOpen = !mobileMenuOpen"
    >
      <svg v-if="!mobileMenuOpen" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 6h18M3 12h18M3 18h18" />
      </svg>
      <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 6l12 12M18 6L6 18" />
      </svg>
    </button>

    <nav
      id="app-navbar-nav"
      class="app-navbar__nav"
      :class="{ 'app-navbar__nav--open': mobileMenuOpen }"
      @click="closeMobileMenuOnLinkClick"
    >
      <template v-if="auth.isAuthenticated">
        <RouterLink to="/dashboard">Dashboard</RouterLink>
        <RouterLink to="/riwayat">Riwayat</RouterLink>
        <RouterLink v-if="auth.isClient" to="/pesan">Pesan Laporan</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/portal-grafolog">Portal Grafolog</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/grafolog/ditugaskan">Ditugaskan ke Saya</RouterLink>
        <RouterLink v-if="auth.isGrafolog" to="/token-saya">Token Saya</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/users">Kelola Staf</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/products">Kelola Produk</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/pricing">Kelola Harga</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/discounts">Kelola Diskon</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/content">Kelola Konten</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/announcements">Pengumuman</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/tokens">Kelola Token</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/knowledge">Knowledge Base</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/audit-logs">Log Audit</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/grafolog-applications">Verifikasi Grafolog</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/recap/users">Rekap Pengguna</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/recap/grafolog">Rekap Grafolog</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/recap/token-purchases">Rekap Token</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/recap/payments">Rekap Pembayaran</RouterLink>
        <RouterLink v-if="auth.isAdministrator" to="/admin/analytics">Analitik</RouterLink>
        <RouterLink v-if="auth.isHr" to="/hr/candidates">Kandidat</RouterLink>

        <div ref="notifRoot" class="app-navbar__notif">
          <button
            type="button"
            class="app-navbar__theme"
            aria-label="Notifikasi"
            title="Notifikasi"
            @click="toggleNotif"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span v-if="unreadCount > 0" class="app-navbar__notif-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
          </button>

          <div v-if="notifOpen" class="app-navbar__notif-panel">
            <h3>Notifikasi</h3>
            <p v-if="notifications.length === 0" class="app-navbar__notif-empty">Belum ada notifikasi.</p>
            <ul v-else class="app-navbar__notif-list">
              <li v-for="n in notifications" :key="n.id" class="app-navbar__notif-item">
                <strong>{{ n.title }}</strong>
                <p>{{ n.body }}</p>
                <span class="app-navbar__notif-date">{{ formatNotifDate(n.created_at) }}</span>
              </li>
            </ul>
          </div>
        </div>

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
.app-navbar__toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 1px solid var(--color-border-hover);
  background: var(--color-paper-alt);
  color: var(--color-ink);
  border-radius: var(--radius-sm);
  cursor: pointer;
}
.app-navbar__toggle:hover {
  border-color: var(--color-seal);
  color: var(--color-seal);
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
.app-navbar__notif {
  position: relative;
}
.app-navbar__notif-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 16px;
  height: 16px;
  padding: 0 3px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--color-seal);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  border-radius: 999px;
  line-height: 1;
}
.app-navbar__notif-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 320px;
  max-width: calc(100vw - 32px);
  max-height: 420px;
  overflow-y: auto;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
  padding: 14px;
  z-index: 30;
}
.app-navbar__notif-panel h3 {
  font-size: 14px;
  margin-bottom: 10px;
}
.app-navbar__notif-empty {
  font-size: 13px;
  color: var(--color-text-soft);
}
.app-navbar__notif-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.app-navbar__notif-item {
  padding: 10px;
  border-radius: var(--radius-sm);
  background: var(--color-paper-alt);
}
.app-navbar__notif-item strong {
  display: block;
  font-family: var(--font-heading);
  font-size: 13.5px;
  margin-bottom: 3px;
  color: var(--color-ink);
}
.app-navbar__notif-item p {
  font-size: 12.5px;
  color: var(--color-ink-soft);
  margin: 0 0 4px;
}
.app-navbar__notif-date {
  font-size: 11px;
  color: var(--color-text-soft);
}

@media (max-width: 640px) {
  .app-navbar {
    padding: 12px 16px;
  }
  .app-navbar__toggle {
    display: inline-flex;
  }
  .app-navbar__nav {
    display: none;
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
    gap: 14px;
    font-size: 14px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
  }
  .app-navbar__nav--open {
    display: flex;
  }
}
</style>
