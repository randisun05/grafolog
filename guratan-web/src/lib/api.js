import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('guratan_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // 401 di sini selalu berarti token tidak valid/kedaluwarsa (login yang
    // gagal karena email/password salah balas 422, bukan 401 - lihat
    // AuthController::login) - jadi aman diperlakukan sebagai "sesi
    // berakhir" tanpa risiko salah-redirect saat submit form login.
    // Token Sanctum sekarang genuinely kedaluwarsa 24 jam setelah login
    // (lihat guratan-api/CLAUDE.md "Open security findings") - hard
    // redirect (bukan cuma clear storage) supaya state reaktif Pinia yang
    // sudah dimuat di memori ikut ter-reset, bukan diam-diam menampilkan
    // UI "masih login" sampai reload berikutnya.
    if (error.response?.status === 401 && window.location.pathname !== '/login') {
      localStorage.removeItem('guratan_token')
      localStorage.removeItem('guratan_user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  },
)

export default api
