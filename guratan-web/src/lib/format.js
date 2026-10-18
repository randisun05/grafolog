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
  dikembalikan: 'Dana dikembalikan',
}

export const STATUS_LAYANAN = {
  menunggu_penugasan: 'Menunggu penugasan',
  ditugaskan: 'Ditugaskan',
  dijadwalkan: 'Dijadwalkan',
  selesai: 'Selesai',
  dibatalkan: 'Dibatalkan',
}

export const STATUS_VALIDASI = {
  menunggu: 'Menunggu keputusan',
  disetujui: 'Disetujui (menunggu konfirmasi)',
  perlu_revisi: 'Perlu revisi (menunggu konfirmasi)',
  selesai: 'Selesai',
  ditolak: 'Penugasan ditolak',
  kedaluwarsa: 'Lewat tenggat',
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

export const STATUS_KETERSEDIAAN = {
  tersedia: 'Tersedia',
  terbatas: 'Terbatas',
  penuh: 'Penuh',
  libur: 'Libur',
}

export function formatBulan(ym) {
  const [y, m] = ym.split('-')
  return new Date(Number(y), Number(m) - 1, 1).toLocaleDateString('id-ID', {
    month: 'long',
    year: 'numeric',
  })
}
