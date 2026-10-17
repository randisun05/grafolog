<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import { formatTanggal } from '@/lib/format'

/** Pertanyaan cek tulisan + pemetaan opsi ke Indikator KB (id indikator), dan daftar lead. */
const toast = useToast()
const questions = ref([])
const leads = ref({ total: 0, leads: [] })
const indikatorDraft = ref({})

async function load() {
  const [q, r] = await Promise.all([
    api.get('/admin/self-check'),
    api.get('/admin/self-check/results'),
  ])
  questions.value = q.data
  leads.value = r.data
  indikatorDraft.value = Object.fromEntries(
    q.data.flatMap((x) => x.options).map((o) => [o.id, o.indikator_id ?? '']),
  )
}

async function saveQuestion(q, patch) {
  try {
    await api.put(`/admin/self-check/questions/${q.id}`, patch)
    Object.assign(q, patch)
    toast.push('Pertanyaan disimpan.', 'success')
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan.')
  }
}

async function saveOption(o) {
  try {
    const raw = indikatorDraft.value[o.id]
    const { data } = await api.put(`/admin/self-check/options/${o.id}`, {
      label: o.label,
      indikator_id: raw === '' ? null : Number(raw),
    })
    Object.assign(o, data)
    toast.push('Opsi disimpan.', 'success')
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(errors ? Object.values(errors).flat()[0] : 'Gagal menyimpan opsi.')
  }
}

onMounted(load)
</script>

<template>
  <div class="asc">
    <p class="asc__hint">
      Setiap opsi menunjuk satu Indikator KB (lihat id di Admin KB). Hasil cek tulisan hanya
      menampilkan aspek & teks dari indikator tersebut - ubah pemetaan, bukan teksnya.
    </p>
    <section v-for="q in questions" :key="q.id" class="asc__q">
      <div class="asc__q-head">
        <input v-model="q.pertanyaan" type="text" :aria-label="`Pertanyaan ${q.id}`" />
        <label class="asc__toggle"
          ><input
            type="checkbox"
            :checked="q.is_active"
            @change="saveQuestion(q, { is_active: $event.target.checked })"
          />
          Aktif</label
        >
        <button
          type="button"
          class="btn"
          @click="saveQuestion(q, { pertanyaan: q.pertanyaan, petunjuk: q.petunjuk })"
        >
          Simpan
        </button>
      </div>
      <input v-model="q.petunjuk" type="text" placeholder="Petunjuk (opsional)" />
      <table class="asc__table">
        <tr v-for="o in q.options" :key="o.id">
          <td><input v-model="o.label" type="text" :aria-label="`Label opsi ${o.id}`" /></td>
          <td class="asc__ind">
            <input
              v-model="indikatorDraft[o.id]"
              type="number"
              min="1"
              placeholder="id indikator"
              :aria-label="`Indikator opsi ${o.id}`"
            />
          </td>
          <td class="asc__meta">
            <template v-if="o.indikator"
              >{{ o.indikator.aspek?.kode }}
              {{ o.indikator.aspek?.nama_id || o.indikator.aspek?.nama }} ·
              {{ o.indikator.nama }}</template
            >
            <template v-else>Tidak memengaruhi hasil</template>
          </td>
          <td><button type="button" class="btn" @click="saveOption(o)">Simpan</button></td>
        </tr>
      </table>
    </section>

    <section class="asc__leads">
      <h3>Lead ({{ leads.leads.length }} email dari {{ leads.total }} cek tulisan)</h3>
      <table class="asc__table">
        <tr v-for="l in leads.leads" :key="l.id">
          <td>{{ l.email }}</td>
          <td class="asc__meta">{{ l.user ? `${l.user.name} (${l.user.role})` : 'tamu' }}</td>
          <td class="asc__meta">{{ formatTanggal(l.created_at, true) }}</td>
          <td><RouterLink :to="`/cek-tulisan/${l.uuid}`" target="_blank">Hasil</RouterLink></td>
        </tr>
      </table>
    </section>
  </div>
</template>

<style scoped>
.asc {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.asc__hint,
.asc__meta {
  color: var(--color-text-soft);
  font-size: 12.5px;
}
.asc__q,
.asc__leads {
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.asc__q-head {
  display: flex;
  gap: 8px;
  align-items: center;
}
.asc__q-head input[type='text'] {
  margin: 0;
  font-weight: 600;
}
.asc__toggle {
  display: flex;
  gap: 4px;
  align-items: center;
  font-size: 13px;
  white-space: nowrap;
}
.asc__toggle input {
  width: auto;
}
.asc__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.asc__table td {
  padding: 4px 6px;
  border-top: 1px solid var(--color-border);
  vertical-align: middle;
}
.asc__table input {
  margin: 0;
}
.asc__ind {
  width: 110px;
}
.asc__ind input {
  width: 100%;
  padding: 7px;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
}
.asc__leads h3 {
  margin: 0;
  font-size: 15px;
}
</style>
