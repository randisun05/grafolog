import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({
      data: {
        sindrom: [{ nama: 'Driving Forces', rata_rata: 6.5, band: 'Nilai Sedang' }],
        menonjol: [
          {
            nama: 'Ambisi',
            sindrom: 'Driving Forces',
            skor: 9,
            band: 'Nilai Tinggi',
            ringkas: 'Berorientasi pada pencapaian.',
          },
        ],
        catatan: 'Bukan diagnosis.',
      },
    }),
  },
}))
import api from '@/lib/api'
import ReportResume from '@/components/report/ReportResume.vue'

describe('ReportResume', () => {
  it('menampilkan rata-rata sindrom, ciri menonjol, dan catatan', async () => {
    const w = mount(ReportResume, { props: { reportId: 5, bahasa: 'id' } })
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/reports/5/resume', { params: { lang: 'id' } })
    expect(w.text()).toContain('Ringkasan untuk Anda')
    expect(w.text()).toContain('Driving Forces')
    expect(w.text()).toContain('Ambisi')
    expect(w.text()).toContain('Bukan diagnosis.')
    expect(w.find('[role=img]').attributes('aria-label')).toBe('Driving Forces: 6.5 / 10')
  })

  it('memuat ulang saat bahasa berubah', async () => {
    const w = mount(ReportResume, { props: { reportId: 5, bahasa: 'id' } })
    await flushPromises()
    await w.setProps({ bahasa: 'en' })
    await flushPromises()
    expect(api.get).toHaveBeenLastCalledWith('/reports/5/resume', { params: { lang: 'en' } })
  })
})
