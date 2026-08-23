import { ref } from 'vue'
import api from '@/lib/api'

// Module-level singleton (same pattern as useTheme.js/useToast.js, not
// Pinia) - AppNavbar is mounted once globally, so this state is naturally
// shared across the whole app without needing a store.
const notifications = ref([])
const unreadCount = ref(0)
const loaded = ref(false)

export function useNotifications() {
  async function load() {
    try {
      const { data } = await api.get('/announcements')
      notifications.value = data.data
      unreadCount.value = data.unread_count
      loaded.value = true
    } catch {
      // Gagal muat notifikasi bukan fatal - bel cuma tidak menampilkan
      // badge, sisa aplikasi tetap jalan normal.
    }
  }

  function reset() {
    notifications.value = []
    unreadCount.value = 0
    loaded.value = false
  }

  async function markAllRead() {
    if (unreadCount.value === 0) return
    notifications.value = notifications.value.map((n) => ({ ...n, is_read: true }))
    unreadCount.value = 0
    try {
      await api.post('/announcements/read-all')
    } catch {
      // Optimistic - kalau request gagal, load() berikutnya akan
      // mengoreksi state dari server, tidak fatal untuk dibiarkan sesaat.
    }
  }

  return { notifications, unreadCount, loaded, load, reset, markAllRead }
}
