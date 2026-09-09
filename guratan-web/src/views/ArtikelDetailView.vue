<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import RichTextViewer from '@/components/shared/RichTextViewer.vue'

const route = useRoute()

const article = ref(null)
const loading = ref(true)
const loadError = ref('')

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/articles/${route.params.slug}`)
    article.value = data
  } catch {
    loadError.value = 'Artikel tidak ditemukan.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="artikel-detail">
    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <article v-else-if="article">
      <RouterLink to="/artikel" class="artikel-detail__back">&larr; Semua Artikel</RouterLink>
      <img v-if="article.cover_image_url" :src="article.cover_image_url" :alt="article.title" class="artikel-detail__cover" />
      <span class="artikel-detail__date">{{ formatDate(article.published_at) }}</span>
      <h1>{{ article.title }}</h1>
      <RichTextViewer :html="article.body" />
    </article>
  </div>
</template>

<style scoped>
.artikel-detail {
  max-width: 760px;
}
.artikel-detail__back {
  display: inline-block;
  margin-bottom: 16px;
  color: var(--color-text-soft);
  text-decoration: none;
  font-size: 13px;
}
.artikel-detail__back:hover {
  color: var(--color-seal);
}
.artikel-detail__cover {
  width: 100%;
  max-height: 360px;
  object-fit: cover;
  border-radius: var(--radius-md);
  margin-bottom: 16px;
}
.artikel-detail__date {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.artikel-detail h1 {
  font-family: var(--font-heading);
  font-size: 28px;
  margin: 8px 0 20px;
}
</style>
