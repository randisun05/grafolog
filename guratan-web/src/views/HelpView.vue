<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'

const content = ref({})
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get('/content')
    content.value = data
  } catch {
    // Halaman tetap tampil dengan kartu kosong kalau CMS gagal dimuat -
    // bukan halaman kritikal, tidak perlu pesan error khusus.
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="help-page">
    <h1>Bantuan</h1>
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <template v-else>
      <p class="help-page__note">{{ content.support_note || 'Ada pertanyaan? Tim kami siap membantu.' }}</p>

      <div class="help-page__card">
        <div v-if="content.support_email" class="help-page__row">
          <span class="help-page__label">Email</span>
          <a :href="`mailto:${content.support_email}`">{{ content.support_email }}</a>
        </div>
        <div v-if="content.support_whatsapp" class="help-page__row">
          <span class="help-page__label">WhatsApp</span>
          <a :href="`https://wa.me/${content.support_whatsapp.replace(/\D/g, '')}`" target="_blank" rel="noopener">
            {{ content.support_whatsapp }}
          </a>
        </div>
        <div v-if="content.support_hours" class="help-page__row">
          <span class="help-page__label">Jam Layanan</span>
          <span>{{ content.support_hours }}</span>
        </div>
        <p v-if="!content.support_email && !content.support_whatsapp" class="help-page__empty">
          Info kontak dukungan belum tersedia — silakan cek kembali nanti.
        </p>
      </div>

      <p class="help-page__hint">
        Untuk permintaan terkait data pribadi Anda (salinan, koreksi, atau penghapusan), lihat
        <RouterLink to="/kebijakan-privasi">Kebijakan Privasi</RouterLink> bagian "Hak Anda" dan hubungi kami
        lewat kanal di atas.
      </p>
    </template>
  </div>
</template>

<style scoped>
.help-page {
  max-width: 560px;
}
.help-page__note {
  color: var(--color-ink-soft);
  font-size: 14.5px;
  margin-bottom: 20px;
}
.help-page__card {
  padding: 20px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.help-page__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--color-border);
}
.help-page__row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}
.help-page__label {
  font-size: 12.5px;
  color: var(--color-text-soft);
  font-weight: 600;
}
.help-page__row a {
  color: var(--color-seal);
  text-decoration: none;
}
.help-page__row a:hover {
  text-decoration: underline;
}
.help-page__empty {
  color: var(--color-text-soft);
  font-size: 13.5px;
}
.help-page__hint {
  margin-top: 18px;
  font-size: 13px;
  color: var(--color-text-soft);
}
.help-page__hint a {
  color: var(--color-seal);
}
</style>
