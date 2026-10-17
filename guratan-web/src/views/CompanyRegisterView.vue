<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { EMPLOYEE_RANGES } from '@/lib/format'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  company_name: '',
  industry: '',
  employee_range: '',
  company_phone: '',
  address: '',
  website: '',
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  consent: false,
})
const errors = ref({})
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    await auth.registerCompany({ ...form.value, website: form.value.website || null })
    router.push({ name: 'company-status' })
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {
      company_name: [e.response?.data?.message ?? 'Pendaftaran gagal.'],
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-form auth-form--wide">
    <h1>Daftar sebagai Perusahaan</h1>
    <p class="auth-form__hint">
      Untuk tim HR yang ingin memakai asesmen grafologi & wawancara dalam rekrutmen. Admin
      memverifikasi data perusahaan sebelum akun aktif.
    </p>
    <form @submit.prevent="submit">
      <fieldset>
        <legend>Data perusahaan</legend>
        <label for="co-name">Nama perusahaan</label>
        <input id="co-name" v-model="form.company_name" type="text" required />
        <p v-if="errors.company_name" class="error">{{ errors.company_name[0] }}</p>

        <label for="co-industry">Bidang usaha</label>
        <input
          id="co-industry"
          v-model="form.industry"
          type="text"
          placeholder="mis. Distribusi, Teknologi"
          required
        />
        <p v-if="errors.industry" class="error">{{ errors.industry[0] }}</p>

        <label for="co-size">Jumlah karyawan</label>
        <select id="co-size" v-model="form.employee_range" required>
          <option value="" disabled>Pilih...</option>
          <option v-for="r in EMPLOYEE_RANGES" :key="r" :value="r">{{ r }} orang</option>
        </select>
        <p v-if="errors.employee_range" class="error">{{ errors.employee_range[0] }}</p>

        <label for="co-phone">Telepon kantor</label>
        <input id="co-phone" v-model="form.company_phone" type="tel" required />
        <p v-if="errors.company_phone" class="error">{{ errors.company_phone[0] }}</p>

        <label for="co-address">Alamat</label>
        <textarea id="co-address" v-model="form.address" rows="2" required></textarea>
        <p v-if="errors.address" class="error">{{ errors.address[0] }}</p>

        <label for="co-web">Situs web (opsional)</label>
        <input id="co-web" v-model="form.website" type="url" placeholder="https://..." />
        <p v-if="errors.website" class="error">{{ errors.website[0] }}</p>
      </fieldset>

      <fieldset>
        <legend>Penanggung jawab HR</legend>
        <label for="hr-name">Nama lengkap</label>
        <input id="hr-name" v-model="form.name" type="text" required />
        <p v-if="errors.name" class="error">{{ errors.name[0] }}</p>

        <label for="hr-email">Email kerja</label>
        <input id="hr-email" v-model="form.email" type="email" required />
        <p v-if="errors.email" class="error">{{ errors.email[0] }}</p>

        <label for="hr-phone">Nomor HP / WhatsApp</label>
        <input id="hr-phone" v-model="form.phone" type="tel" required />
        <p v-if="errors.phone" class="error">{{ errors.phone[0] }}</p>

        <label for="hr-pass">Kata sandi</label>
        <input id="hr-pass" v-model="form.password" type="password" required />
        <p v-if="errors.password" class="error">{{ errors.password[0] }}</p>

        <label for="hr-pass2">Konfirmasi kata sandi</label>
        <input id="hr-pass2" v-model="form.password_confirmation" type="password" required />
      </fieldset>

      <label class="consent">
        <input v-model="form.consent" type="checkbox" required />
        <span>
          Saya menyetujui
          <RouterLink to="/privasi" target="_blank">Kebijakan Privasi</RouterLink> dan
          <RouterLink to="/syarat" target="_blank">Syarat Layanan</RouterLink>, dan berwenang
          mendaftarkan perusahaan ini.
        </span>
      </label>
      <p v-if="errors.consent" class="error">{{ errors.consent[0] }}</p>

      <button type="submit" class="btn btn--primary" :disabled="loading">
        {{ loading ? 'Mengirim...' : 'Kirim Pendaftaran' }}
      </button>
    </form>
    <RouterLink to="/login" class="auth-form__link">Sudah punya akun? Masuk</RouterLink>
  </div>
</template>

<style scoped src="./auth-form.css"></style>
<style scoped>
.auth-form--wide {
  max-width: 480px;
}
input,
select,
textarea {
  margin: 4px 0 14px;
}
input[type='tel'],
input[type='url'] {
  display: block;
  width: 100%;
  padding: 9px 10px;
  box-sizing: border-box;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}
label {
  margin-bottom: 0;
}
fieldset {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 12px 14px 2px;
  margin: 0 0 16px;
}
legend {
  font-weight: 600;
  font-size: 14px;
  padding: 0 6px;
}
.consent {
  display: flex;
  gap: 0.5rem;
  align-items: flex-start;
  font-size: 0.875rem;
  margin: 4px 0 14px;
}
.consent input {
  width: auto;
  margin: 0.2rem 0 0;
}
</style>
