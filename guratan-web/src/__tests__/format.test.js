import { describe, expect, it } from 'vitest'
import { formatBulan, formatRupiah, formatTanggal } from '@/lib/format'

describe('format', () => {
  it('memformat rupiah tanpa desimal dan null sebagai strip', () => {
    expect(formatRupiah(149000).replace(/\s/g, ' ')).toMatch(/Rp\s?149\.000/)
    expect(formatRupiah(null)).toBe('–')
  })

  it('memformat tanggal Indonesia dan kosong sebagai strip', () => {
    expect(formatTanggal('2026-10-19T10:00:00Z')).toMatch(/2026/)
    expect(formatTanggal(null)).toBe('–')
  })

  it('memformat bulan YYYY-MM', () => {
    expect(formatBulan('2026-10')).toMatch(/2026/)
  })
})
