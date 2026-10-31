// Dipakai semua tombol "Export CSV" di halaman Rekap Admin - sama pola
// blob-download yang sudah ada di ReportView.vue (unduh PDF), cuma
// diekstrak karena dipakai 4x+ dengan URL/filename berbeda.
export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
