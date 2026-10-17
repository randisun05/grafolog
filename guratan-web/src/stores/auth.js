import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('guratan_user') ?? 'null'))
  const token = ref(localStorage.getItem('guratan_token'))

  const isAuthenticated = computed(() => !!token.value)
  // Grafolog pendaftar mandiri berstatus 'pending' sampai diverifikasi admin - selama itu
  // bukan "grafolog" untuk keperluan menu/akses (cermin User::isGrafolog() di backend).
  const isGrafolog = computed(
    () => user.value?.role === 'grafolog' && [null, undefined, 'verified'].includes(user.value?.grafolog_status),
  )
  const isPendingGrafolog = computed(() => user.value?.role === 'grafolog' && !isGrafolog.value)
  const isAdministrator = computed(() => user.value?.role === 'administrator')
  const isHr = computed(() => user.value?.role === 'hr')
  const isSupervisor = computed(() => user.value?.role === 'supervisor')
  const isClient = computed(() => user.value?.role === 'user')

  function persist(newUser, newToken) {
    user.value = newUser
    token.value = newToken
    localStorage.setItem('guratan_user', JSON.stringify(newUser))
    localStorage.setItem('guratan_token', newToken)
  }

  async function register(payload) {
    const { data } = await api.post('/auth/register', payload)
    persist(data.user, data.token)
    return data
  }

  async function registerGrafolog(formData) {
    const { data } = await api.post('/auth/register-grafolog', formData)
    persist(data.user, data.token)
    return data
  }

  async function login(payload) {
    const { data } = await api.post('/auth/login', payload)
    persist(data.user, data.token)
    return data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      user.value = null
      token.value = null
      localStorage.removeItem('guratan_user')
      localStorage.removeItem('guratan_token')
    }
  }

  async function fetchMe() {
    const { data } = await api.get('/auth/me')
    user.value = data.user
    localStorage.setItem('guratan_user', JSON.stringify(data.user))
    return data.user
  }

  return {
    user,
    token,
    isAuthenticated,
    isGrafolog,
    isPendingGrafolog,
    isAdministrator,
    isHr,
    isSupervisor,
    isClient,
    register,
    registerGrafolog,
    login,
    logout,
    fetchMe,
  }
})
