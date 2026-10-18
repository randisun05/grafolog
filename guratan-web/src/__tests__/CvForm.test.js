import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), put: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ push: vi.fn() }) }))
import api from '@/lib/api'
import CvForm from '@/components/cv/CvForm.vue'

const field = (over) => ({
  id: 1,
  key: 'k',
  label: 'K',
  type: 'text',
  options: null,
  help: null,
  required: false,
  is_hidden: false,
  value: null,
  ...over,
})
const payload = (editable = true) => ({
  editable,
  groups: [
    {
      id: 1,
      name: 'Profil',
      description: null,
      fields: [
        field({ id: 1, key: 'nama_panggilan', label: 'Nama panggilan', value: 'Budi' }),
        field({
          id: 2,
          key: 'hobi',
          label: 'Hobi',
          type: 'select',
          options: ['Baca', 'Olahraga'],
          value: 'Baca',
        }),
        field({ id: 3, key: 'alamat', label: 'Alamat', type: 'textarea', is_hidden: true }),
        field({ id: 4, key: 'menikah', label: 'Menikah', type: 'boolean', value: true }),
      ],
    },
  ],
})

describe('CvForm', () => {
  beforeEach(() => vi.clearAllMocks())

  it('mode edit: merender input sesuai tipe dan penanda tersembunyi', async () => {
    api.get.mockResolvedValue({ data: payload(true) })
    const w = mount(CvForm)
    await flushPromises()
    expect(w.find('#cv-nama_panggilan').element.value).toBe('Budi')
    expect(w.find('select#cv-hobi').exists()).toBe(true)
    expect(w.find('textarea#cv-alamat').exists()).toBe(true)
    expect(w.find('input[type=checkbox]#cv-menikah').element.checked).toBe(true)
    expect(w.text()).toContain('tersembunyi')
    expect(api.get).toHaveBeenCalledWith('/cv')
  })

  it('mode baca: tanpa input & tombol simpan', async () => {
    api.get.mockResolvedValue({ data: payload(false) })
    const w = mount(CvForm, { props: { userId: 7 } })
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/cv/users/7')
    expect(w.find('input').exists()).toBe(false)
    expect(w.text()).toContain('Budi')
    expect(w.text()).toContain('Ya') // boolean true -> "Ya"
    expect(w.find('button[class*=btn--primary]').exists()).toBe(false)
  })

  it('menyimpan nilai ke endpoint sendiri', async () => {
    api.get.mockResolvedValue({ data: payload(true) })
    api.put.mockResolvedValue({ data: payload(true) })
    const w = mount(CvForm)
    await flushPromises()
    await w.find('#cv-nama_panggilan').setValue('Budi S')
    await w.find('form').trigger('submit')
    await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/cv', {
      values: expect.objectContaining({ nama_panggilan: 'Budi S' }),
    })
  })
})
