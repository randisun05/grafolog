import { reactive } from 'vue'

const toasts = reactive([])
let nextId = 0

/** Toast bus sederhana - satu array reaktif dibagi semua pemanggil useToast(). */
export function useToast() {
  function push(message, type = 'error', duration = 4000) {
    const id = nextId++
    toasts.push({ id, message, type })
    setTimeout(() => dismiss(id), duration)
    return id
  }

  function dismiss(id) {
    const index = toasts.findIndex((t) => t.id === id)
    if (index !== -1) toasts.splice(index, 1)
  }

  return { toasts, push, dismiss }
}
