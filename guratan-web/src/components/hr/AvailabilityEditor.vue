<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import { STATUS_KETERSEDIAAN, formatBulan } from '@/lib/format'

/** Grafolog mengatur ketersediaan per bulan; HR hanya melihat yang tersedia/terbatas. */
const toast = useToast()
const months = ref([])
const saving = ref(null)

async function load() {
  months.value = (await api.get('/grafolog/availability')).data.map((m) => ({
    ...m,
    capacity: m.capacity ?? '',
    note: m.note ?? '',
  }))
  // Bulan ini terbuka, sisanya dilipat supaya setahun tidak memenuhi halaman.
}

async function save(m) {
  saving.value = m.month
  try {
    const { data } = await api.put('/grafolog/availability', {
      month: m.month,
      status: m.status,
      capacity: m.capacity === '' ? null : Number(m.capacity),
      supervision: !!m.supervision,
      note: m.note || null,
    })
    Object.assign(m, data, { capacity: data.capacity ?? '', note: data.note ?? '' })
    toast.push(`Ketersediaan ${formatBulan(m.month)} disimpan.`, 'success')
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.push(errors ? Object.values(errors).flat()[0] : 'Gagal menyimpan.')
  } finally {
    saving.value = null
  }
}

onMounted(load)
</script>

<template>
  <section class="av">
    <h2>Jadwal ketersediaan setahun</h2>
    <p class="av__hint">
      Perusahaan hanya bisa memilih Anda di bulan berstatus <strong>Tersedia</strong> atau
      <strong>Terbatas</strong>; bulan-bulan yang Anda atur tampil sebagai catatan di profil Anda.
      Kuota membatasi jumlah kandidat per bulan layanan - bila habis Anda otomatis tidak muncul.
      Centang <strong>Bersedia supervisi</strong> bila Anda mau menerima penugasan memvalidasi
      laporan grafolog lain di bulan itu. Bulan yang belum diatur: tersedia untuk dipesan, belum
      bersedia supervisi.
    </p>
    <div v-for="m in months" :key="m.month" class="av__row">
      <strong class="av__month">{{ formatBulan(m.month) }}</strong>
      <select v-model="m.status" :aria-label="`Status ${m.month}`">
        <option v-for="(label, key) in STATUS_KETERSEDIAAN" :key="key" :value="key">
          {{ label }}
        </option>
      </select>
      <label class="av__check"
        ><input v-model="m.supervision" type="checkbox" :aria-label="`Supervisi ${m.month}`" />
        Supervisi</label
      >
      <input
        v-model="m.capacity"
        type="number"
        min="1"
        placeholder="Kuota (opsional)"
        :aria-label="`Kuota ${m.month}`"
      />
      <input
        v-model="m.note"
        type="text"
        placeholder="Catatan"
        :aria-label="`Catatan ${m.month}`"
      />
      <span class="av__meta"
        >Beban {{ m.workload
        }}<template v-if="m.slots_left !== null"> · sisa {{ m.slots_left }}</template></span
      >
      <button type="button" class="btn" :disabled="saving === m.month" @click="save(m)">
        Simpan
      </button>
    </div>
  </section>
</template>

<style scoped>
.av {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.av h2 {
  font-size: 16px;
  margin: 0;
}
.av__hint,
.av__meta {
  color: var(--color-text-soft);
  font-size: 12.5px;
  margin: 0;
}
.av__row {
  display: grid;
  grid-template-columns: 120px 120px auto 110px 1fr auto auto;
  gap: 8px;
  align-items: center;
}
.av__row select,
.av__row input {
  margin: 0;
}
.av__row input[type='number'] {
  width: 100%;
  padding: 9px 10px;
  box-sizing: border-box;
  border: 1px solid var(--color-border-hover);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-ink);
  font-size: 14px;
}
@media (max-width: 720px) {
  .av__row {
    grid-template-columns: 1fr 1fr;
  }
}
.av__check {
  display: flex;
  gap: 4px;
  align-items: center;
  font-size: 13px;
  white-space: nowrap;
}
.av__check input {
  width: auto;
  margin: 0;
}
</style>
