import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({
      data: [
        { key: 'va', label: 'Virtual Account' },
        { key: 'ewallet', label: 'E-Wallet' },
        { key: 'qris', label: 'QRIS' },
      ],
    }),
  },
}))
import PaymentMethodPicker from '@/components/shared/PaymentMethodPicker.vue'

describe('PaymentMethodPicker', () => {
  it('menampilkan semua metode bila tidak dibatasi promo', async () => {
    const w = mount(PaymentMethodPicker, { props: { modelValue: '' } })
    await flushPromises()
    expect(w.findAll('option').map((o) => o.text())).toEqual([
      'Pilih di halaman bayar',
      'Virtual Account',
      'E-Wallet',
      'QRIS',
    ])
  })

  it('membatasi pilihan sesuai metode yang diizinkan promo', async () => {
    const w = mount(PaymentMethodPicker, {
      props: { modelValue: '', allowed: ['ewallet', 'qris'] },
    })
    await flushPromises()
    const labels = w.findAll('option').map((o) => o.text())
    expect(labels).toContain('E-Wallet')
    expect(labels).not.toContain('Virtual Account')
    expect(w.text()).toContain('Promo ini hanya berlaku untuk: E-Wallet, QRIS')
  })

  it('memancarkan perubahan pilihan', async () => {
    const w = mount(PaymentMethodPicker, { props: { modelValue: '' } })
    await flushPromises()
    await w.find('select').setValue('qris')
    expect(w.emitted('update:modelValue')[0]).toEqual(['qris'])
  })
})

describe('PaymentMethodPicker - pilihan gateway', () => {
  it('menampilkan pilihan penyedia hanya bila lebih dari satu gateway tersedia', async () => {
    const { default: api } = await import('@/lib/api')
    api.get.mockImplementation((url) =>
      Promise.resolve({
        data:
          url === '/payment-gateways'
            ? { default: 'doku', available: ['doku', 'xendit'] }
            : [{ key: 'va', label: 'Virtual Account' }],
      }),
    )
    const w = mount(PaymentMethodPicker, { props: { modelValue: '', gateway: '' } })
    await flushPromises()
    const selects = w.findAll('select')
    expect(selects).toHaveLength(2)
    expect(selects[0].findAll('option').map((o) => o.text())).toEqual(['Otomatis', 'DOKU', 'Xendit'])

    await selects[0].setValue('xendit')
    expect(w.emitted('update:gateway')[0]).toEqual(['xendit'])
  })

  it('menyembunyikan pilihan penyedia bila hanya satu gateway', async () => {
    const { default: api } = await import('@/lib/api')
    api.get.mockImplementation((url) =>
      Promise.resolve({
        data: url === '/payment-gateways' ? { default: 'doku', available: ['doku'] } : [{ key: 'va', label: 'VA' }],
      }),
    )
    const w = mount(PaymentMethodPicker, { props: { modelValue: '' } })
    await flushPromises()
    expect(w.findAll('select')).toHaveLength(1)
  })
})
