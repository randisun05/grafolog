<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { EDUCATION, EMPLOYEE_RANGES } from '@/lib/format'
import AvailabilityEditor from '@/components/hr/AvailabilityEditor.vue'

const auth = useAuthStore()
const toast = useToast()
const role = computed(() => auth.user?.role)

const form = ref({})
const specText = ref('')
const company = ref({})
const errors = ref({})
const loading = ref(true)
const saving = ref(false)

function fill(user) {
  form.value = {
    name: user.name ?? '',
    phone: user.phone ?? '',
    city: user.city ?? '',
    bio: user.bio ?? '',
    experience_years: user.experience_years ?? '',
    birth_date: user.birth_date ?? '',
    gender: user.gender ?? '',
    education: user.education ?? '',
    occupation: user.occupation ?? '',
  }
  specText.value = (user.specializations ?? []).join(', ')
  company.value = { ...user.company }
}

function payload() {
  const f = form.value
  const blank = (v) => (v === '' ? null : v)
  const base = { name: f.name, phone: blank(f.phone), city: blank(f.city) }
  if (role.value === 'grafolog') {
    return {
      ...base,
      bio: blank(f.bio),
      experience_years: blank(f.experience_years),
      specializations: specText.value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean),
    }
  }
  if (role.value === 'user') {
    return {
      ...base,
      birth_date: blank(f.birth_date),
      gender: blank(f.gender),
      education: blank(f.education),
      occupation: blank(f.occupation),
    }
  }
  if (role.value === 'hr') {
    const c = company.value
    return {
      ...base,
      company: {
        industry: c.industry,
        employee_range: c.employee_range,
        phone: c.phone,
        address: c.address,
        website: c.website || null,
      },
    }
  }
  return base
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const { data } = await api.put('/profile', payload())
    auth.setUser(data.user)
    fill(data.user)
    toast.push('Profil disimpan.', 'success')
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan profil.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/profile')
    auth.setUser(data.user)
    fill(data.user)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="profile">
    <h1>Profil Saya</h1>
    <p v-if="role === 'grafolog'" class="profile__hint">
      Profil ini dilihat tim HR perusahaan saat memilih grafolog untuk kandidat mereka.
    </p>
    <p v-else-if="role === 'user'" class="profile__hint">
      Data diri bersifat opsional dan hanya membantu grafolog memahami konteks Anda.
    </p>

    <SkeletonLoader v-if="loading" variant="list" :rows="4" label="Memuat profil..." />
    <form v-else class="profile__form" @submit.prevent="save">
      <section>
        <h2>Akun</h2>
        <p class="profile__meta">{{ auth.user?.email }}</p>
        <label for="pf-name">Nama</label>
        <input id="pf-name" v-model="form.name" type="text" required />
        <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>
        <div class="profile__row">
          <div>
            <label for="pf-phone">Nomor HP</label>
            <input id="pf-phone" v-model="form.phone" type="text" />
          </div>
          <div>
            <label for="pf-city">Kota</label>
            <input id="pf-city" v-model="form.city" type="text" />
          </div>
        </div>
      </section>

      <section v-if="role === 'grafolog'">
        <h2>Profil grafolog</h2>
        <label for="pf-exp">Pengalaman (tahun)</label>
        <input id="pf-exp" v-model.number="form.experience_years" type="number" min="0" max="60" />
        <p v-if="errors.experience_years" class="error">{{ errors.experience_years[0] }}</p>
        <label for="pf-spec">Spesialisasi (pisahkan dengan koma, maks. 8)</label>
        <input
          id="pf-spec"
          v-model="specText"
          type="text"
          placeholder="mis. Rekrutmen, Konseling remaja"
        />
        <p v-if="errors.specializations" class="error">{{ errors.specializations[0] }}</p>
        <label for="pf-bio">Tentang saya</label>
        <textarea id="pf-bio" v-model="form.bio" rows="4" maxlength="2000"></textarea>
      </section>

      <section v-if="role === 'user'">
        <h2>Data diri (opsional)</h2>
        <div class="profile__row">
          <div>
            <label for="pf-birth">Tanggal lahir</label>
            <input id="pf-birth" v-model="form.birth_date" type="date" />
          </div>
          <div>
            <label for="pf-gender">Jenis kelamin</label>
            <select id="pf-gender" v-model="form.gender">
              <option value="">-</option>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
        </div>
        <div class="profile__row">
          <div>
            <label for="pf-edu">Pendidikan terakhir</label>
            <select id="pf-edu" v-model="form.education">
              <option value="">-</option>
              <option v-for="e in EDUCATION" :key="e" :value="e">{{ e }}</option>
            </select>
          </div>
          <div>
            <label for="pf-job">Pekerjaan</label>
            <input id="pf-job" v-model="form.occupation" type="text" />
          </div>
        </div>
        <p v-if="errors.birth_date" class="error">{{ errors.birth_date[0] }}</p>
      </section>

      <section v-if="role === 'hr'">
        <h2>Profil perusahaan · {{ company.name }}</h2>
        <div class="profile__row">
          <div>
            <label for="pf-ind">Bidang usaha</label>
            <input id="pf-ind" v-model="company.industry" type="text" />
          </div>
          <div>
            <label for="pf-size">Jumlah karyawan</label>
            <select id="pf-size" v-model="company.employee_range">
              <option v-for="r in EMPLOYEE_RANGES" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
        </div>
        <label for="pf-cphone">Telepon kantor</label>
        <input id="pf-cphone" v-model="company.phone" type="text" />
        <label for="pf-addr">Alamat</label>
        <textarea id="pf-addr" v-model="company.address" rows="2"></textarea>
        <label for="pf-web">Situs web</label>
        <input id="pf-web" v-model="company.website" type="text" placeholder="https://..." />
        <p v-if="errors['company.website']" class="error">{{ errors['company.website'][0] }}</p>
      </section>

      <button type="submit" class="btn btn--primary" :disabled="saving">
        {{ saving ? 'Menyimpan...' : 'Simpan profil' }}
      </button>
    </form>
    <AvailabilityEditor v-if="!loading && role === 'grafolog'" />
  </div>
</template>

<style scoped>
.profile {
  max-width: 640px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.profile__hint,
.profile__meta {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin: 0;
}
.profile__form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.profile__form section {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.profile__form h2 {
  font-size: 16px;
  margin: 0 0 4px;
}
.profile__form label {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-top: 8px;
}
.profile__row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}
.profile input[type='number'],
.profile input[type='date'] {
  display: block;
  width: 100%;
  padding: 9px 10px;
  margin-top: 4px;
  box-sizing: border-box;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}
.profile .btn {
  align-self: flex-start;
}
</style>
