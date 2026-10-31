<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import CvForm from '@/components/cv/CvForm.vue'
import SkeletonLoader from '@/components/shared/SkeletonLoader.vue'

/** Memilih grafolog dari direktori (kartu + portofolio). `month` = bulan pelaksanaan (YYYY-MM). */
const props = defineProps({
  month: { type: String, required: true },
  selectedId: { type: Number, default: null },
  excludeIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['select'])
const list = ref([])
const loading = ref(true)
const q = ref('')
const openId = ref(null)
let timer

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/grafologs/directory', {
      params: { month: props.month, q: q.value || undefined },
    })
    list.value = data.grafologs.filter((g) => !props.excludeIds.includes(g.id))
  } finally {
    loading.value = false
  }
}
function onSearch() {
  clearTimeout(timer)
  timer = setTimeout(load, 350)
}
onMounted(load)
watch(() => props.month, load)
</script>

<template>
  <div class="gp">
    <input
      v-model="q"
      type="search"
      class="gp__search"
      placeholder="Cari nama, kota, atau keahlian"
      aria-label="Cari grafolog"
      @input="onSearch"
    />
    <SkeletonLoader v-if="loading" variant="list" :rows="3" label="Memuat grafolog..." />
    <p v-else-if="!list.length" class="gp__empty">
      Tidak ada grafolog tersedia untuk bulan {{ month }}. Coba bulan lain atau minta rekomendasi
      tim kami.
    </p>
    <ul v-else class="gp__list">
      <li
        v-for="g in list"
        :key="g.id"
        class="gp__card"
        :class="{ 'gp__card--on': selectedId === g.id }"
      >
        <div class="gp__head">
          <div>
            <strong>{{ g.name }}</strong>
            <span class="gp__meta">
              {{ g.city || 'Kota belum diisi' }} · {{ g.experience_years ?? '-' }} th pengalaman ·
              {{ g.completed_assignments }} laporan selesai
            </span>
          </div>
          <span class="gp__slot">
            {{
              g.availability.slots_left === null
                ? 'Tersedia'
                : `Sisa ${g.availability.slots_left} slot`
            }}
          </span>
        </div>
        <p v-if="g.specializations?.length" class="gp__spec">{{ g.specializations.join(' · ') }}</p>
        <p v-if="g.bio" class="gp__bio">{{ g.bio }}</p>
        <div class="gp__actions">
          <button type="button" class="btn" @click="openId = openId === g.id ? null : g.id">
            {{ openId === g.id ? 'Tutup portofolio' : 'Lihat portofolio' }}
          </button>
          <button type="button" class="btn btn--primary" @click="emit('select', g)">
            {{ selectedId === g.id ? '✓ Dipilih' : 'Pilih grafolog ini' }}
          </button>
        </div>
        <div v-if="openId === g.id" class="gp__cv"><CvForm :user-id="g.id" /></div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.gp__search {
  max-width: 340px;
  margin-bottom: 10px;
}
.gp__empty {
  color: var(--color-text-soft);
  font-size: 14px;
}
.gp__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.gp__card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: 12px 14px;
}
.gp__card--on {
  border-color: var(--color-sage);
  box-shadow: 0 0 0 1px var(--color-sage);
}
.gp__head {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  justify-content: space-between;
}
.gp__meta {
  display: block;
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.gp__slot {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-paper-alt);
  border: 1px solid var(--color-border);
  align-self: flex-start;
}
.gp__spec {
  font-size: 13px;
  margin: 6px 0 0;
}
.gp__bio {
  font-size: 13.5px;
  color: var(--color-text-soft);
  margin: 6px 0;
}
.gp__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}
.gp__cv {
  margin-top: 10px;
  border-top: 1px dashed var(--color-border);
  padding-top: 10px;
}
</style>
