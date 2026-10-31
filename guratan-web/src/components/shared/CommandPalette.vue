<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'

const router = useRouter()
const auth = useAuthStore()
const { toggle: toggleTheme } = useTheme()

const open = ref(false)
const query = ref('')
const activeIndex = ref(0)
const inputRef = ref(null)

const commands = computed(() => {
  if (!auth.isAuthenticated) return []

  const items = [
    { label: 'Dashboard', to: { name: 'dashboard' } },
    { label: 'Riwayat', to: { name: 'riwayat' } },
  ]
  if (auth.isClient) {
    items.push({ label: 'Pesan Laporan', to: { name: 'order' } })
  }
  items.push({ label: 'Kotak Masuk', to: { name: 'inbox' } })
  if (auth.isGrafolog) {
    items.push({ label: 'Klien Saya', to: { name: 'grafolog-clients' } })
    items.push({ label: 'Ditugaskan ke Saya', to: { name: 'assigned-to-me' } })
    items.push({ label: 'Token Saya', to: { name: 'token-wallet' } })
    items.push({ label: 'Supervisi Laporan', to: { name: 'validation-tasks' } })
  }
  if (auth.isAdministrator) {
    items.push({ label: 'Pesanan Masuk', to: { name: 'admin-orders' } })
    items.push({ label: 'Kelola Staf', to: { name: 'admin-users' } })
    items.push({ label: 'Verifikasi Grafolog & Perusahaan', to: { name: 'admin-verification' } })
    items.push({ label: 'Penugasan Supervisi', to: { name: 'admin-validations' } })
    items.push({ label: 'Kelola Produk & Harga', to: { name: 'admin-products' } })
    items.push({ label: 'Kelola Diskon', to: { name: 'admin-discounts' } })
    items.push({ label: 'Kelola Konten', to: { name: 'admin-content' } })
    items.push({ label: 'Cek Tulisan, Mitra & Artikel', to: { name: 'admin-engagement' } })
    items.push({ label: 'Pengumuman', to: { name: 'admin-announcements' } })
    items.push({ label: 'Pemakaian & Biaya AI', to: { name: 'admin-ai-usage' } })
    items.push({ label: 'Manajemen CV', to: { name: 'admin-cv' } })
    items.push({ label: 'Broadcast Kotak Masuk', to: { name: 'admin-broadcast' } })
    items.push({ label: 'Log Aktivitas', to: { name: 'admin-activity' } })
    items.push({ label: 'Log Notifikasi', to: { name: 'admin-notification-logs' } })
    items.push({ label: 'Log Audit', to: { name: 'admin-audit-logs' } })
    items.push({ label: 'Kelola Perusahaan', to: { name: 'admin-company-manage' } })
    items.push({ label: 'Rekap Pengguna', to: { name: 'admin-recap-users' } })
    items.push({ label: 'Rekap Grafolog', to: { name: 'admin-recap-grafolog' } })
    items.push({ label: 'Rekap Token', to: { name: 'admin-recap-token-purchases' } })
    items.push({ label: 'Rekap Pembayaran', to: { name: 'admin-recap-payments' } })
    items.push({ label: 'Analitik', to: { name: 'admin-analytics' } })
    items.push({ label: 'Kelola Kegiatan', to: { name: 'admin-events' } })
    items.push({ label: 'Kelola Games', to: { name: 'admin-games' } })
    items.push({ label: 'Kelola Token', to: { name: 'admin-tokens' } })
    items.push({ label: 'Knowledge Base', to: { name: 'admin-knowledge' } })
    items.push({ label: 'Log Audit', to: { name: 'admin-audit-logs' } })
  }
  if (auth.isHr) {
    items.push({ label: 'Rekrutmen: Kandidat & Analisis', to: { name: 'hr-candidates' } })
  }
  items.push({ label: 'Profil Saya', to: { name: 'profile' } })
  items.push({ label: 'Cek Tulisan Gratis', to: { name: 'self-check' } })
  items.push({ label: 'Artikel', to: { name: 'articles' } })
  items.push({ label: 'Produk Mitra', to: { name: 'partner-products' } })
  items.push({ label: 'Kuis Ciri Tulisan', to: { name: 'quiz' } })
  items.push({ label: 'Ganti Mode Terang/Gelap', action: toggleTheme })

  return items
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return commands.value
  return commands.value.filter((c) => c.label.toLowerCase().includes(q))
})

watch(query, () => {
  activeIndex.value = 0
})

function openPalette() {
  if (!auth.isAuthenticated) return
  open.value = true
  query.value = ''
  activeIndex.value = 0
  nextTick(() => inputRef.value?.focus())
}

function closePalette() {
  open.value = false
}

function runCommand(cmd) {
  if (!cmd) return
  if (cmd.to) router.push(cmd.to)
  if (cmd.action) cmd.action()
  closePalette()
}

function onKeydown(event) {
  const isTogglePressed = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'
  if (isTogglePressed) {
    event.preventDefault()
    if (open.value) {
      closePalette()
    } else {
      openPalette()
    }
    return
  }

  if (!open.value) return

  if (event.key === 'Escape') {
    closePalette()
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, filtered.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    runCommand(filtered.value[activeIndex.value])
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div v-if="open" class="cmdk-overlay" @click.self="closePalette">
    <div class="cmdk-panel" role="dialog" aria-modal="true" aria-label="Command palette">
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        class="cmdk-input"
        placeholder="Ketik untuk mencari halaman..."
      />
      <ul class="cmdk-list">
        <li
          v-for="(cmd, i) in filtered"
          :key="cmd.label"
          class="cmdk-item"
          :class="{ 'cmdk-item--active': i === activeIndex }"
          @mouseenter="activeIndex = i"
          @click="runCommand(cmd)"
        >
          {{ cmd.label }}
        </li>
        <li v-if="filtered.length === 0" class="cmdk-empty">Tidak ada hasil.</li>
      </ul>
      <div class="cmdk-hint">
        <span>&uarr;&darr; pilih</span>
        <span>&crarr; buka</span>
        <span>Esc tutup</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cmdk-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  justify-content: center;
  padding-top: 12vh;
}
.cmdk-panel {
  width: min(480px, calc(100vw - 32px));
  max-height: 60vh;
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}
.cmdk-input {
  margin: 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  border-radius: 0;
  padding: 14px 16px;
  font-size: 15px;
  background: transparent;
  color: var(--color-ink);
}
.cmdk-input:focus {
  outline: none;
}
.cmdk-list {
  list-style: none;
  padding: 6px;
  margin: 0;
  overflow-y: auto;
}
.cmdk-item {
  padding: 9px 10px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--color-ink);
  cursor: pointer;
}
.cmdk-item--active {
  background: var(--color-seal-soft);
  color: var(--color-seal);
}
.cmdk-empty {
  padding: 12px 10px;
  color: var(--color-text-soft);
  font-size: 13px;
}
.cmdk-hint {
  display: flex;
  gap: 14px;
  padding: 8px 14px;
  border-top: 1px solid var(--color-border);
  font-size: 11.5px;
  color: var(--color-text-soft);
}
</style>
