// Baca token warna CSS yang sudah ada (base.css) lewat getComputedStyle
// saat chart mount, BUKAN hex hardcoded - supaya chart otomatis ikut dark
// mode tanpa CSS khusus per komponen, sesuai aturan project "tidak ada
// komponen yang perlu dark-mode CSS sendiri" (lihat guratan-web/CLAUDE.md).
function cssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

export function chartColors() {
  return {
    seal: cssVar('--color-seal'),
    sage: cssVar('--color-sage'),
    gold: cssVar('--color-gold'),
    textSoft: cssVar('--color-text-soft'),
    border: cssVar('--color-border'),
  }
}

// Palet kategorikal untuk chart multi-seri - urutan seal->gold->sage
// konsisten dengan pemakaian badge/aksen di tempat lain di app ini.
export function categoricalPalette() {
  const c = chartColors()
  return [c.seal, c.gold, c.sage]
}

export function baseChartOptions() {
  const c = chartColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: c.textSoft } },
    },
    scales: {
      x: { ticks: { color: c.textSoft }, grid: { color: c.border } },
      y: { ticks: { color: c.textSoft }, grid: { color: c.border } },
    },
  }
}
