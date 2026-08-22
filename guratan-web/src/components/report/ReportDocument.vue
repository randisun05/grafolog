<script setup>
import { ref } from 'vue'
import TraitBar from './TraitBar.vue'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  data: { type: Object, required: true }, // { sindrom: [...] }
  editable: { type: Boolean, default: false }, // grafolog pemilik sample - lihat CLAUDE.md
  reportId: { type: [Number, String], default: null },
})
const emit = defineEmits(['narasi-updated'])

const toast = useToast()
const editingKode = ref(null)
const draftNarasi = ref('')
const saving = ref(false)

function startEdit(aspek) {
  editingKode.value = aspek.kode
  draftNarasi.value = aspek.narasi
}
function cancelEdit() {
  editingKode.value = null
}

async function saveEdit(kode) {
  saving.value = true
  try {
    const { data } = await api.patch(`/reports/${props.reportId}/aspek/${encodeURIComponent(kode)}/narasi`, {
      narasi: draftNarasi.value,
    })
    toast.push('Narasi berhasil diperbarui.', 'success')
    editingKode.value = null
    emit('narasi-updated', data)
  } catch (e) {
    toast.push(e.response?.data?.message ?? 'Gagal menyimpan narasi.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="report-document">
    <section v-for="sindrom in data.sindrom" :key="sindrom.id" class="report-document__sindrom">
      <header class="report-document__sindrom-header">
        <h2>{{ sindrom.kode_romawi }}. {{ sindrom.nama }}</h2>
        <p class="report-document__meta">
          Polaritas {{ sindrom.polaritas }} &middot; rata-rata skor
          {{ sindrom.rata_rata_skor }} ({{ sindrom.band_label_rata_rata }})
        </p>
      </header>

      <div v-for="aspek in sindrom.aspek" :key="aspek.kode" class="report-document__aspek">
        <TraitBar :nama="aspek.nama" :skor="aspek.skor" :band-label="aspek.band_label" />

        <template v-if="editingKode === aspek.kode">
          <textarea v-model="draftNarasi" class="report-document__narasi-edit"></textarea>
          <div class="report-document__narasi-actions">
            <button type="button" class="btn btn--primary" :disabled="saving" @click="saveEdit(aspek.kode)">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
            <button type="button" class="btn" :disabled="saving" @click="cancelEdit">Batal</button>
          </div>
        </template>
        <template v-else>
          <p class="report-document__narasi">
            {{ aspek.narasi }}
            <span v-if="aspek.narasi_diedit_manual" class="report-document__edited-badge">✏️ diedit manual</span>
          </p>
          <button v-if="editable" type="button" class="report-document__edit-btn" @click="startEdit(aspek)">
            Edit narasi
          </button>
        </template>

        <ul v-if="aspek.indikator_terkait?.length" class="report-document__indikator">
          <li v-for="ind in aspek.indikator_terkait" :key="ind.kode">
            <span class="report-document__indikator-kode">{{ ind.kode }}</span>
            {{ ind.nama }}
            <p v-if="ind.keterangan" class="report-document__indikator-keterangan">{{ ind.keterangan }}</p>
          </li>
        </ul>
      </div>
    </section>

    <section v-if="data.kombinasi_ditemukan?.length" class="report-document__kombinasi">
      <h2>Pola Kombinasi Ditemukan</h2>
      <p class="report-document__meta">Temuan dari kombinasi beberapa Aspek/Indikator/Sindrom sekaligus, bukan 1 aspek saja.</p>
      <div v-for="temuan in data.kombinasi_ditemukan" :key="temuan.id" class="report-document__kombinasi-item">
        <h3>{{ temuan.nama }}</h3>
        <p>{{ temuan.teks_interpretasi }}</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.report-document {
  max-width: 100%;
  overflow-wrap: break-word;
}
.report-document__sindrom {
  margin-bottom: 28px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--color-border);
}
.report-document__sindrom-header h2 {
  font-size: 18px;
  margin-bottom: 2px;
}
.report-document__meta {
  color: var(--color-text-soft);
  font-size: 12px;
  margin-bottom: 14px;
}
.report-document__aspek {
  margin-bottom: 16px;
  max-width: 100%;
}
.report-document__narasi {
  font-size: 13px;
  line-height: 1.5;
  color: var(--color-text);
  white-space: pre-line;
  overflow-wrap: break-word;
}
.report-document__edited-badge {
  display: inline-block;
  margin-left: 6px;
  font-size: 11px;
  font-style: normal;
  color: var(--color-text-soft);
  white-space: nowrap;
}
.report-document__edit-btn {
  margin-top: 4px;
  border: none;
  background: none;
  color: var(--color-seal);
  font-size: 12px;
  cursor: pointer;
  padding: 0;
}
.report-document__edit-btn:hover {
  text-decoration: underline;
}
.report-document__narasi-edit {
  display: block;
  width: 100%;
  min-height: 100px;
  font-size: 13px;
  line-height: 1.5;
  box-sizing: border-box;
  margin-top: 4px;
}
.report-document__narasi-actions {
  display: flex;
  gap: 8px;
  margin-top: 6px;
}
.report-document__indikator {
  margin-top: 10px;
  padding: 8px 10px;
  border-left: 2px solid var(--color-border);
  list-style: none;
}
.report-document__indikator li {
  font-size: 12px;
  color: var(--color-text-soft);
  margin-bottom: 6px;
}
.report-document__indikator-kode {
  font-family: var(--font-heading);
  color: var(--color-ink);
  margin-right: 6px;
}
.report-document__indikator-keterangan {
  margin: 2px 0 0;
  font-size: 11.5px;
  font-style: italic;
  line-height: 1.4;
}
.report-document__kombinasi {
  margin-top: 24px;
  padding-top: 16px;
  border-top: 2px solid var(--color-border);
}
.report-document__kombinasi h2 {
  font-size: 18px;
  margin-bottom: 2px;
}
.report-document__kombinasi-item {
  margin-top: 12px;
  padding: 8px 10px;
  border-left: 2px solid var(--color-gold, #c9a227);
}
.report-document__kombinasi-item h3 {
  font-size: 14px;
  margin-bottom: 4px;
}
.report-document__kombinasi-item p {
  font-size: 13px;
  line-height: 1.5;
  color: var(--color-text);
  margin: 0;
}
</style>
