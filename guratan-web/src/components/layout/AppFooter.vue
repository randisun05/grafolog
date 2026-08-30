<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'

// Entitas hukum/mitra pengawas (mis. biro psikologi) - lihat
// guratan-api/CLAUDE.md untuk konteks lengkap. Kosong = baris disembunyikan,
// bukan tampil placeholder ke publik.
const legalEntityName = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/content')
    legalEntityName.value = data.legal_entity_name || ''
  } catch {
    // Footer tetap tampil tanpa baris entitas kalau CMS gagal dimuat.
  }
})
</script>

<template>
  <footer class="app-footer">
    <nav class="app-footer__links">
      <RouterLink to="/bantuan">Bantuan</RouterLink>
      <RouterLink to="/kebijakan-privasi">Kebijakan Privasi</RouterLink>
      <RouterLink to="/ketentuan-layanan">Ketentuan Layanan</RouterLink>
    </nav>
    <p class="app-footer__meta">
      &copy; {{ new Date().getFullYear() }} Guratan<span v-if="legalEntityName"> · {{ legalEntityName }}</span>
    </p>
  </footer>
</template>

<style scoped>
.app-footer {
  margin-top: 40px;
  padding: 20px;
  border-top: 1px solid var(--color-border);
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px 16px;
}
.app-footer__links {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  font-size: 13px;
}
.app-footer__links a {
  color: var(--color-ink-soft);
  text-decoration: none;
}
.app-footer__links a:hover {
  color: var(--color-seal);
}
.app-footer__meta {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
</style>
