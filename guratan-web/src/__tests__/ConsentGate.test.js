import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/lib/api', () => ({ default: { post: vi.fn(), get: vi.fn() } }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ push: vi.fn() }) }))
import api from '@/lib/api'
import ConsentGate from '@/components/shared/ConsentGate.vue'
import { useAuthStore } from '@/stores/auth'

describe('ConsentGate', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  function login(needs) {
    const auth = useAuthStore()
    auth.token = 't'
    auth.user = { id: 1, role: 'user', needs_consent: needs }
    return auth
  }

  it('tersembunyi bila tidak perlu persetujuan ulang', () => {
    login(false)
    expect(mount(ConsentGate).find('.cg').exists()).toBe(false)
  })

  it('muncul bila perlu, tombol setuju butuh centang, lalu memanggil API', async () => {
    const auth = login(true)
    api.post.mockResolvedValue({ data: { user: { needs_consent: false } } })
    const w = mount(ConsentGate)
    expect(w.find('.cg').exists()).toBe(true)
    const btn = w.find('.btn--primary')
    expect(btn.attributes('disabled')).toBeDefined()
    await w.find('input[type=checkbox]').setValue(true)
    expect(btn.attributes('disabled')).toBeUndefined()
    await btn.trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/me/accept-terms', { accept: true })
    expect(auth.user.needs_consent).toBe(false)
    expect(w.find('.cg').exists()).toBe(false)
  })
})
