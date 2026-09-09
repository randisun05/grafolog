<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'

const articles = ref([])
const meta = ref({ currentPage: 1, lastPage: 1 })
const loading = ref(true)
const loadError = ref('')

async function load(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/articles', { params: { page } })
    articles.value = data.data
    meta.value = { currentPage: data.current_page, lastPage: data.last_page }
  } catch {
    loadError.value = 'Gagal memuat artikel.'
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > meta.value.lastPage) return
  load(page)
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

onMounted(() => load(1))
</script>

<template>
  <div class="artikel-list">
    <h1>Artikel &amp; Berita</h1>
    <p class="artikel-list__note">Wawasan seputar grafologi, tulisan tangan, dan pengembangan diri.</p>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="articles.length === 0" class="artikel-list__empty">Belum ada artikel.</p>
    <div v-else class="artikel-list__grid">
      <RouterLink
        v-for="article in articles"
        :key="article.id"
        :to="`/artikel/${article.slug}`"
        class="artikel-card"
      >
        <img v-if="article.cover_image_url" :src="article.cover_image_url" :alt="article.title" />
        <div class="artikel-card__body">
          <span class="artikel-card__date">{{ formatDate(article.published_at) }}</span>
          <h2>{{ article.title }}</h2>
          <p v-if="article.excerpt">{{ article.excerpt }}</p>
        </div>
      </RouterLink>
    </div>

    <div v-if="meta.lastPage > 1" class="artikel-list__pagination">
      <button type="button" class="btn" :disabled="meta.currentPage <= 1" @click="goToPage(meta.currentPage - 1)">
        &larr; Sebelumnya
      </button>
      <span>Halaman {{ meta.currentPage }} / {{ meta.lastPage }}</span>
      <button
        type="button"
        class="btn"
        :disabled="meta.currentPage >= meta.lastPage"
        @click="goToPage(meta.currentPage + 1)"
      >
        Berikutnya &rarr;
      </button>
    </div>
  </div>
</template>

<style scoped>
.artikel-list {
  max-width: 1000px;
}
.artikel-list__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 24px;
}
.artikel-list__empty {
  color: var(--color-text-soft);
}
.artikel-list__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}
.artikel-card {
  display: block;
  text-decoration: none;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: var(--shadow-card);
  transition: transform 0.15s ease;
}
.artikel-card:hover {
  transform: translateY(-2px);
}
.artikel-card img {
  width: 100%;
  height: 150px;
  object-fit: cover;
  display: block;
}
.artikel-card__body {
  padding: 14px 16px 18px;
}
.artikel-card__date {
  font-size: 11.5px;
  color: var(--color-text-soft);
}
.artikel-card__body h2 {
  font-family: var(--font-heading);
  font-size: 17px;
  margin: 6px 0 8px;
}
.artikel-card__body p {
  font-size: 13.5px;
  color: var(--color-text-soft);
  line-height: 1.5;
}
.artikel-list__pagination {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 24px;
  font-size: 13px;
}
</style>
