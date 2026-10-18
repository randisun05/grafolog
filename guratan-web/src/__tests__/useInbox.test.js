import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/lib/api', () => ({ default: { get: vi.fn() } }))
import api from '@/lib/api'
import { useInbox } from '@/composables/useInbox'

describe('useInbox', () => {
  beforeEach(() => vi.clearAllMocks())

  it('refresh mengisi hitungan belum dibaca', async () => {
    api.get.mockResolvedValue({ data: { unread: 4 } })
    const { unread, refresh } = useInbox()
    await refresh()
    expect(api.get).toHaveBeenCalledWith('/inbox/unread-count')
    expect(unread.value).toBe(4)
  })

  it('gagal refresh tidak melempar dan tidak mengubah hitungan', async () => {
    api.get.mockRejectedValue(new Error('offline'))
    const { unread, refresh } = useInbox()
    unread.value = 2
    await expect(refresh()).resolves.toBeUndefined()
    expect(unread.value).toBe(2)
  })
})
