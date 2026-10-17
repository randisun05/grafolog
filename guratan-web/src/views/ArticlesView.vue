<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/lib/api'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'
import { formatTanggal } from '@/lib/format'

/** Daftar artikel (/artikel) & satu artikel (/artikel/:slug). Isi teks polos, tanpa HTML. */
const props = defineProps({ slug: { type: String, default: null } })

const list = ref([])
const article = ref(null)
const loading = ref(true)
const error = ref('')

const paragraphs = computed(() =>
  (article.value?.isi ?? '')
    .split(/\n\s*\n/)
    .map((p) => p.trim())
    .filter(Boolean),
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    if (props.slug) article.value = (await api.get(`/articles/${props.slug}`)).data
    else list.value = (await api.get('/articles')).data
  } catch {
    error.value = props.slug ? 'Artikel tidak ditemukan.' : 'Gagal memuat artikel.'
  } finally {
    loading.value = false
  }
}

watch(() => props.slug, load, { immediate: true })
</script>

<template>
  <div class="art">
    <SkeletonLoader v-if="loading" variant="list" :rows="3" label="Memuat artikel..." />
    <p v-else-if="error" class="error">{{ error }}</p>

    <article v-else-if="slug && article" class="art__article">
      <RouterLink to="/artikel" class="art__back">← Semua artikel</RouterLink>
      <span class="art__cat">{{ article.kategori }}</span>
      <h1>{{ article.judul }}</h1>
      <p class="art__meta">{{ formatTanggal(article.published_at) }}</p>
      <p v-for="(p, i) in paragraphs" :key="i">{{ p }}</p>
      <div class="art__cta">
        <RouterLink to="/cek-tulisan" class="btn btn--primary">Coba cek tulisan gratis</RouterLink>
        <RouterLink to="/kuis" class="btn">Main kuis ciri tulisan</RouterLink>
      </div>
    </article>

    <template v-else>
      <h1>Artikel</h1>
      <p class="art__meta">Panduan dan wawasan seputar grafologi & layanan Guratan.</p>
      <p v-if="!list.length" class="art__meta">Belum ada artikel.</p>
      <RouterLink v-for="a in list" :key="a.id" :to="`/artikel/${a.slug}`" class="art__card">
        <span class="art__cat">{{ a.kategori }}</span>
        <strong>{{ a.judul }}</strong>
        <span v-if="a.ringkasan" class="art__meta">{{ a.ringkasan }}</span>
        <span class="art__meta">{{ formatTanggal(a.published_at) }}</span>
      </RouterLink>
    </template>
  </div>
</template>

<style scoped>
.art {
  max-width: 720px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.art__meta {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin: 0;
}
.art__cat {
  font-size: 11.5px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-seal);
  font-weight: 600;
}
.art__card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  text-decoration: none;
}
.art__card:hover {
  border-color: var(--color-border-hover);
}
.art__card strong {
  font-size: 17px;
}
.art__article p {
  line-height: 1.75;
  font-size: 15.5px;
}
.art__article h1 {
  margin: 4px 0;
}
.art__back {
  font-size: 13px;
  display: block;
  margin-bottom: 10px;
}
.art__cta {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 16px;
}
</style>
