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
