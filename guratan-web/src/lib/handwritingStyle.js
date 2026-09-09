// Pencocokan kata kunci sederhana untuk Game "Tebak Kepribadian dari
// Tulisan" - mengubah teks `Indikator.keterangan` (ciri fisik tulisan
// tangan nyata dari KB, bukan dikarang) jadi gaya CSS yang MEMVISUALKAN
// ciri itu di atas teks contoh bergaya tulisan tangan (font `--font-accent:
// Caveat` yang SUDAH ADA di design token, bukan font baru) - biar lebih
// menarik daripada cuma teks polos. Fallback ke gaya netral kalau tidak
// ada kata kunci cocok - tidak pernah gagal render.
export function styleForKeterangan(text) {
  const lower = (text ?? '').toLowerCase()
  const style = {
    fontSize: '28px',
    transform: 'skewX(0deg)',
    letterSpacing: 'normal',
    fontWeight: 400,
  }

  if (/\b(large|big|tall)\b/.test(lower)) style.fontSize = '38px'
  else if (/\b(small|narrow|short)\b/.test(lower)) style.fontSize = '20px'

  if (/right\b/.test(lower)) style.transform = 'skewX(-12deg)'
  else if (/left\b/.test(lower)) style.transform = 'skewX(12deg)'

  if (/\b(wide|broad|extended)\b/.test(lower)) style.letterSpacing = '0.12em'
  else if (/\b(narrow|close|compressed|tight)\b/.test(lower)) style.letterSpacing = '-0.02em'

  if (/\b(heavy|strong|thick)\b/.test(lower)) style.fontWeight = 700
  else if (/\b(light|thin|faint)\b/.test(lower)) style.fontWeight = 300

  return {
    fontSize: style.fontSize,
    fontWeight: style.fontWeight,
    letterSpacing: style.letterSpacing,
    transform: style.transform,
  }
}
