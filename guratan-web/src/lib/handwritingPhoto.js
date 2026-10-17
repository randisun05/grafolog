// Aturan foto tulisan tangan - harus sama dengan StoreSampleImagesRequest di backend
// (2026-10-17): JPG/PNG/WEBP, maks. 30 MB, minimal 1920×1080 (FHD, orientasi bebas).
// Dicek di browser dulu supaya pengguna langsung tahu, bukan setelah unggah gagal.
export const PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp'
export const PHOTO_MAX_MB = 30
export const PHOTO_HINT =
  'Gunakan hasil scan (300 dpi, kertas A4 utuh, tanpa zoom/crop) agar skala ukur tetap. JPG/PNG, minimal 1920×1080 piksel, maks. 30 MB.'

function readSize(file) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      resolve({ w: img.naturalWidth, h: img.naturalHeight })
      URL.revokeObjectURL(url)
    }
    img.onerror = () => {
      resolve(null)
      URL.revokeObjectURL(url)
    }
    img.src = url
  })
}

/** @returns {Promise<string|null>} pesan error, atau null bila lolos */
export async function checkPhoto(file) {
  if (!PHOTO_ACCEPT.split(',').includes(file.type))
    return `${file.name}: format harus JPG, PNG, atau WEBP.`
  if (file.size > PHOTO_MAX_MB * 1024 * 1024)
    return `${file.name}: ukuran melebihi ${PHOTO_MAX_MB} MB.`
  const size = await readSize(file)
  if (!size) return `${file.name}: file gambar tidak bisa dibaca.`
  const long = Math.max(size.w, size.h)
  const short = Math.min(size.w, size.h)
  if (long < 1920 || short < 1080) {
    return `${file.name}: resolusi ${size.w}×${size.h} terlalu kecil (minimal 1920×1080). Gunakan hasil scan.`
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
