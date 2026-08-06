<script setup>
import { ref, onMounted } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const users = ref([])
const loading = ref(true)
const loadError = ref('')

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'grafolog',
})
const errors = ref({})
const submitting = ref(false)

const roleLabel = {
  administrator: 'Administrator',
  supervisor: 'Supervisor',
  grafolog: 'Grafolog',
  user: 'Klien',
}

async function loadUsers() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/users')
    users.value = data.data
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat daftar user.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  try {
    const { data } = await api.post('/admin/users', form.value)
    users.value = [{ ...data, created_at: new Date().toISOString() }, ...users.value]
    toast.push(`Akun ${roleLabel[data.role]} untuk ${data.name} berhasil dibuat.`, 'success')
    form.value = { name: '', email: '', password: '', password_confirmation: '', role: 'grafolog' }
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal membuat akun.')
  } finally {
    submitting.value = false
  }
}

onMounted(loadUsers)
</script>

<template>
  <div class="admin-users">
    <h1>Kelola Akun Staf</h1>
    <p class="admin-users__note">
      Buat akun Administrator, Supervisor, atau Grafolog. Akun Klien tetap mendaftar sendiri lewat
      halaman Daftar.
    </p>

    <form class="admin-users__form" @submit.prevent="submit">
      <label>
        Nama
        <input v-model="form.name" type="text" required />
      </label>
      <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>

      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

      <label>
        Kata Sandi
        <input v-model="form.password" type="password" required />
      </label>
      <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

      <label>
        Konfirmasi Kata Sandi
        <input v-model="form.password_confirmation" type="password" required />
      </label>

      <label>
        Role
        <select v-model="form.role">
          <option value="grafolog">Grafolog</option>
          <option value="supervisor">Supervisor</option>
          <option value="administrator">Administrator</option>
        </select>
      </label>
      <p v-if="errors.role" class="error">{{ errors.role[0] }}</p>

      <button type="submit" class="btn btn--primary" :disabled="submitting">
        {{ submitting ? 'Membuat...' : 'Buat Akun' }}
      </button>
    </form>

    <h2 class="admin-users__list-title">Semua User</h2>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <table v-else class="admin-users__table">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="u in users" :key="u.id">
          <td>{{ u.name }}</td>
          <td>{{ u.email }}</td>
          <td>
            <span class="admin-users__role-badge">{{ roleLabel[u.role] ?? u.role }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.admin-users {
  max-width: 720px;
}
.admin-users__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-users__form {
  padding: 20px;
  margin-bottom: 32px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-users__form label {
  display: block;
  margin-bottom: 14px;
  font-size: 14px;
  color: var(--color-text-soft);
}
.admin-users__form .error {
  font-size: 12px;
  margin: -8px 0 10px;
}
.admin-users__list-title {
  font-size: 16px;
  margin-bottom: 10px;
}
.admin-users__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.admin-users__table th {
  text-align: left;
  padding: 8px 10px;
  color: var(--color-text-soft);
  font-weight: 600;
  font-size: 12.5px;
  border-bottom: 1px solid var(--color-border);
}
.admin-users__table td {
  padding: 10px;
  border-bottom: 1px solid var(--color-border);
}
.admin-users__role-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: var(--color-sage-soft);
  color: var(--color-sage);
}
</style>
