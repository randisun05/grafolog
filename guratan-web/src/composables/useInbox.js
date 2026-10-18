import { ref } from 'vue'
import api from '@/lib/api'

// State bersama (modul-level) supaya lonceng navbar & halaman kotak masuk sinkron.
const unread = ref(0)

async function refresh() {
  try {
    unread.value = (await api.get('/inbox/unread-count')).data.unread
  } catch {
    // diam: lonceng tidak boleh mengganggu halaman
  }
}

export function useInbox() {
  return { unread, refresh }
}
