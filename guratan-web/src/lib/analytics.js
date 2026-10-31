// Google Analytics (GA4) - dinonaktifkan otomatis kalau VITE_GA_MEASUREMENT_ID
// kosong (belum diisi admin), supaya aman dipasang di kode duluan sebelum
// Measurement ID sungguhan tersedia. Tanpa dependency npm tambahan - cuma
// injeksi gtag.js, sama semangat minimal-dependency dengan composable lain
// di project ini (useTheme.js, useToast.js).
const MEASUREMENT_ID = import.meta.env.VITE_GA_MEASUREMENT_ID

let initialized = false

export function initAnalytics() {
  if (!MEASUREMENT_ID || initialized) return
  initialized = true

  const script = document.createElement('script')
  script.async = true
  script.src = `https://www.googletagmanager.com/gtag/js?id=${MEASUREMENT_ID}`
  document.head.appendChild(script)

  window.dataLayer = window.dataLayer || []
  function gtag() {
    window.dataLayer.push(arguments)
  }
  window.gtag = gtag
  gtag('js', new Date())
  // send_page_view: false - pageview dikirim manual lewat trackPageview() di
  // router.afterEach, karena ini SPA (navigasi tidak reload dokumen).
  gtag('config', MEASUREMENT_ID, { send_page_view: false })
}

export function trackPageview(path) {
  if (!MEASUREMENT_ID || typeof window.gtag !== 'function') return
  window.gtag('event', 'page_view', { page_path: path })
}
