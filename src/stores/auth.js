import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('guratan_user') ?? 'null'))
  const token = ref(localStorage.getItem('guratan_token'))

  const isAuthenticated = computed(() => !!token.value)
  const isGrafolog = computed(() => user.value?.role === 'grafolog')

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

  return { user, token, isAuthenticated, isGrafolog, register, login, logout, fetchMe }
})
