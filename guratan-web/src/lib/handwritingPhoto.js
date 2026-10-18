// Aturan unggah scan tulisan tangan - harus sama dengan StoreSampleImagesRequest di backend
// (2026-10-18): PDF hasil scan, maks. 30 MB, halaman A4/F4 (skala ukur tetap). Nama berkas lama
// (`PHOTO_*`, `partitionPhotos`) dipertahankan supaya pemakai komponen tidak berubah.
// Dicek di browser dulu supaya pengguna langsung tahu, bukan setelah unggah gagal.
export const PHOTO_ACCEPT = 'application/pdf'
export const PHOTO_MAX_MB = 30
export const PHOTO_HINT =
  'Unggah PDF hasil scan (bukan foto): kertas A4 utuh, 300 dpi, tanpa zoom/crop agar skala ukur tetap. Maks. 30 MB.'

const PAPERS = [
  ['A4', 210, 297],
  ['F4', 215, 330],
]
const TOLERANCE = 0.04

async function readPageSizeMm(file) {
  // Ukuran halaman ada di /MediaBox; bila objek terkompresi tidak terbaca, backend yang memutuskan.
  const text = new TextDecoder('latin1').decode(await file.arrayBuffer())
  if (!text.trimStart().startsWith('%PDF-')) return { valid: false }
  const m = text.match(/\/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]/)
  if (!m) return { valid: true, size: null }
  const toMm = 25.4 / 72
  return {
    valid: true,
    size: [Math.abs(m[3] - m[1]) * toMm, Math.abs(m[4] - m[2]) * toMm].map(Math.round),
  }
}

function paperName([a, b]) {
  const [short, long] = [Math.min(a, b), Math.max(a, b)]
  return PAPERS.find(
    ([, pw, ph]) => Math.abs(short - pw) / pw <= TOLERANCE && Math.abs(long - ph) / ph <= TOLERANCE,
  )?.[0]
}

/** @returns {Promise<string|null>} pesan error, atau null bila lolos */
export async function checkPhoto(file) {
  if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf'))
    return `${file.name}: unggah PDF hasil scan, bukan foto.`
  if (file.size > PHOTO_MAX_MB * 1024 * 1024)
    return `${file.name}: ukuran melebihi ${PHOTO_MAX_MB} MB.`
  const info = await readPageSizeMm(file)
  if (!info.valid) return `${file.name}: bukan PDF yang valid.`
  if (info.size && !paperName(info.size)) {
    return `${file.name}: ukuran halaman ${info.size[0]}×${info.size[1]} mm bukan A4/F4. Scan kertas utuh tanpa zoom/crop.`
  }
  return null
}

/** Pisahkan file yang lolos dan pesan error untuk yang gagal. */
export async function partitionPhotos(files) {
  const ok = []
  const errors = []
  for (const f of files) {
    const err = await checkPhoto(f)
    if (err) errors.push(err)
    else ok.push(f)
  }
  return { ok, errors }
}
