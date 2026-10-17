export function formatRupiah(value) {
  if (value === null || value === undefined) return '–'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value)
}

export function formatTanggal(value, withTime = false) {
  if (!value) return '–'
  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

export const TIPE_LAYANAN = {
  grafologi: 'Laporan grafologi',
  wawancara: 'Wawancara',
  konsultasi: 'Konsultasi',
  konseling: 'Konseling',
  intervensi: 'Program intervensi',
  lainnya: 'Layanan',
}

export const STATUS_PESANAN = {
  menunggu_pembayaran: 'Menunggu pembayaran',
  diproses: 'Diproses',
  selesai: 'Selesai',
  dibatalkan: 'Dibatalkan',
}

export const STATUS_LAYANAN = {
  menunggu_penugasan: 'Menunggu penugasan',
  ditugaskan: 'Ditugaskan',
  dijadwalkan: 'Dijadwalkan',
  selesai: 'Selesai',
  dibatalkan: 'Dibatalkan',
}

export const EMPLOYEE_RANGES = ['1-10', '11-50', '51-200', '201-1000', '1000+']

export const EDUCATION = ['SMA', 'D3', 'S1', 'S2', 'S3', 'Lainnya']

export const TAHAP_KANDIDAT = {
  baru: 'Baru',
  asesmen: 'Asesmen',
  selesai: 'Hasil siap',
  lanjut: 'Lanjut',
  tidak_lanjut: 'Tidak lanjut',
}
