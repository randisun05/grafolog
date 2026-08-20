<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/lib/api'
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const plans = ref([])
const loading = ref(true)
const loadError = ref('')
const draft = ref({ comprehensive: '', master: '' })
const saving = ref({ comprehensive: false, master: false })

const tierLabel = { comprehensive: 'Comprehensive', master: 'Master' }

const activeByTier = computed(() => {
  const result = {}
  for (const plan of plans.value) {
    if (plan.is_active) result[plan.tier] = plan
  }
  return result
})

const historyByTier = computed(() => {
  const result = { comprehensive: [], master: [] }
  for (const plan of plans.value) {
    if (!plan.is_active) result[plan.tier]?.push(plan)
  }
  return result
})

async function loadPlans() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/pricing')
    plans.value = data.data
    for (const tier of ['comprehensive', 'master']) {
      draft.value[tier] = activeByTier.value[tier]?.price ?? ''
    }
  } catch (e) {
    loadError.value = e.response?.data?.message ?? 'Gagal memuat harga.'
  } finally {
    loading.value = false
  }
}

async function savePrice(tier) {
  const price = Number(draft.value[tier])
  if (!Number.isInteger(price) || price < 0) {
    toast.push('Harga harus berupa angka bulat, minimal 0.')
    return
  }
  saving.value[tier] = true
  try {
    await api.put(`/admin/pricing/${tier}`, { price })
    toast.push(`Harga ${tierLabel[tier]} berhasil diubah.`, 'success')
    await loadPlans()
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal mengubah harga.')
  } finally {
    saving.value[tier] = false
  }
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

onMounted(loadPlans)
</script>

<template>
  <div class="admin-pricing">
    <h1>Kelola Harga</h1>
    <p class="admin-pricing__note">
      Mengubah harga tidak menghapus harga lama — riwayat tetap tersimpan. Perubahan tercatat di
      log audit.
    </p>

    <LoadingSpinner v-if="loading" label="Memuat..." />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <div v-else class="admin-pricing__grid">
      <div v-for="tier in ['comprehensive', 'master']" :key="tier" class="admin-pricing__card">
        <h3>{{ tierLabel[tier] }}</h3>
        <p class="admin-pricing__current">
          Harga aktif:
          <strong>{{ activeByTier[tier] ? formatRupiah(activeByTier[tier].price) : 'Belum diatur' }}</strong>
        </p>

        <div class="admin-pricing__edit">
          <input v-model="draft[tier]" type="number" min="0" step="1000" />
          <button type="button" class="btn btn--primary" :disabled="saving[tier]" @click="savePrice(tier)">
            {{ saving[tier] ? 'Menyimpan...' : 'Ubah Harga' }}
          </button>
        </div>

        <div v-if="historyByTier[tier].length" class="admin-pricing__history">
          <h4>Riwayat</h4>
          <ul>
            <li v-for="p in historyByTier[tier]" :key="p.id">
              {{ formatRupiah(p.price) }}
              <span class="admin-pricing__history-meta">
                — diganti {{ new Date(p.updated_at).toLocaleDateString('id-ID') }}
              </span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-pricing {
  max-width: 720px;
}
.admin-pricing__note {
  color: var(--color-text-soft);
  font-size: 13.5px;
  margin-bottom: 20px;
}
.admin-pricing__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}
.admin-pricing__card {
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}
.admin-pricing__card h3 {
  font-size: 16px;
  margin-bottom: 8px;
}
.admin-pricing__current {
  font-size: 13.5px;
  color: var(--color-text-soft);
  margin-bottom: 14px;
}
.admin-pricing__current strong {
  color: var(--color-ink);
  font-family: var(--font-heading);
  font-size: 17px;
}
.admin-pricing__edit {
  display: flex;
  gap: 8px;
}
.admin-pricing__edit input {
  margin-top: 0;
}
.admin-pricing__history {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.admin-pricing__history h4 {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--color-text-soft);
  margin-bottom: 6px;
}
.admin-pricing__history ul {
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 12.5px;
}
.admin-pricing__history li {
  padding: 4px 0;
  color: var(--color-text-soft);
}
.admin-pricing__history-meta {
  color: var(--color-ink-faint);
}
</style>
