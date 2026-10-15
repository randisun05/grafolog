<script setup>
// Placeholder abu-abu berbentuk kira-kira seperti konten yang sedang dimuat,
// menggantikan spinner di state loading halaman/panel. Satu komponen dengan
// beberapa `variant` supaya tiap view cukup memilih bentuk yang paling mirip
// layout aslinya (list, tabel, kartu, dashboard, laporan, form).
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'list',
    validator: (v) => ['list', 'table', 'cards', 'dashboard', 'report', 'form', 'text'].includes(v),
  },
  // Jumlah baris (list/table/form/text) atau kartu (cards).
  rows: { type: Number, default: 0 },
  // Jumlah kolom untuk variant table.
  columns: { type: Number, default: 4 },
  label: { type: String, default: 'Memuat...' },
})

const DEFAULT_ROWS = { list: 4, table: 5, cards: 3, form: 4, text: 3, dashboard: 4, report: 4 }
const count = computed(() => props.rows || DEFAULT_ROWS[props.variant])

// Variasi lebar supaya baris tidak terlihat seragam/kaku.
const WIDTHS = ['72%', '58%', '84%', '64%', '78%', '52%']
const width = (i) => WIDTHS[(i - 1) % WIDTHS.length]
</script>

<template>
  <div
    class="skeleton"
    :class="`skeleton--${variant}`"
    role="status"
    aria-live="polite"
    aria-busy="true"
  >
    <span class="skeleton__sr">{{ label }}</span>

    <!-- LIST: baris kartu dengan judul, meta, dan tombol di kanan -->
    <ul v-if="variant === 'list'" class="skeleton__list" aria-hidden="true">
      <li v-for="i in count" :key="i" class="skeleton__list-item">
        <div class="skeleton__stack">
          <span class="skeleton__bar skeleton__bar--title" :style="{ width: width(i) }"></span>
          <span class="skeleton__bar skeleton__bar--sm" :style="{ width: width(i + 2) }"></span>
        </div>
        <span class="skeleton__bar skeleton__bar--btn"></span>
      </li>
    </ul>

    <!-- TABLE -->
    <div v-else-if="variant === 'table'" class="skeleton__table" aria-hidden="true">
      <div
        class="skeleton__tr skeleton__tr--head"
        :style="{ gridTemplateColumns: `repeat(${columns}, 1fr)` }"
      >
        <span
          v-for="c in columns"
          :key="c"
          class="skeleton__bar skeleton__bar--sm"
          style="width: 50%"
        ></span>
      </div>
      <div
        v-for="i in count"
        :key="i"
        class="skeleton__tr"
        :style="{ gridTemplateColumns: `repeat(${columns}, 1fr)` }"
      >
        <span
          v-for="c in columns"
          :key="c"
          class="skeleton__bar"
          :style="{ width: width(i + c) }"
        ></span>
      </div>
    </div>

    <!-- CARDS -->
    <div v-else-if="variant === 'cards'" class="skeleton__cards" aria-hidden="true">
      <div v-for="i in count" :key="i" class="skeleton__card">
        <span class="skeleton__bar skeleton__bar--title" style="width: 45%"></span>
        <span class="skeleton__bar skeleton__bar--lg" style="width: 60%"></span>
        <span class="skeleton__bar" style="width: 90%"></span>
        <span class="skeleton__bar" style="width: 70%"></span>
      </div>
    </div>

    <!-- DASHBOARD: baris KPI + daftar aktivitas -->
    <div v-else-if="variant === 'dashboard'" aria-hidden="true">
      <div class="skeleton__kpis">
        <div v-for="i in count" :key="i" class="skeleton__card">
          <span class="skeleton__bar skeleton__bar--sm" style="width: 55%"></span>
          <span class="skeleton__bar skeleton__bar--lg" style="width: 40%"></span>
        </div>
      </div>
      <div class="skeleton__card skeleton__card--block">
        <span class="skeleton__bar skeleton__bar--title" style="width: 30%"></span>
        <span v-for="i in 4" :key="i" class="skeleton__bar" :style="{ width: width(i) }"></span>
      </div>
    </div>

    <!-- REPORT: header + tombol, ringkasan, lalu blok sindrom berisi bar skor -->
    <div v-else-if="variant === 'report'" aria-hidden="true">
      <div class="skeleton__report-head">
        <span class="skeleton__bar skeleton__bar--h1"></span>
        <div class="skeleton__row">
          <span class="skeleton__bar skeleton__bar--btn"></span>
          <span class="skeleton__bar skeleton__bar--btn"></span>
        </div>
      </div>
      <div class="skeleton__card skeleton__card--block">
        <span v-for="i in 3" :key="i" class="skeleton__bar" :style="{ width: width(i + 1) }"></span>
      </div>
      <div v-for="s in count" :key="s" class="skeleton__card skeleton__card--block">
        <span class="skeleton__bar skeleton__bar--title" style="width: 38%"></span>
        <div v-for="a in 3" :key="a" class="skeleton__score">
          <span class="skeleton__bar skeleton__bar--sm" :style="{ width: width(s + a) }"></span>
          <span class="skeleton__bar skeleton__bar--track"></span>
        </div>
      </div>
    </div>

    <!-- FORM: label + input -->
    <div
      v-else-if="variant === 'form'"
      class="skeleton__card skeleton__card--block"
      aria-hidden="true"
    >
      <div v-for="i in count" :key="i" class="skeleton__field">
        <span class="skeleton__bar skeleton__bar--sm" :style="{ width: '28%' }"></span>
        <span class="skeleton__bar skeleton__bar--input"></span>
      </div>
      <span class="skeleton__bar skeleton__bar--btn"></span>
    </div>

    <!-- TEXT: paragraf polos, untuk panel kecil -->
    <div v-else class="skeleton__stack" aria-hidden="true">
      <span v-for="i in count" :key="i" class="skeleton__bar" :style="{ width: width(i) }"></span>
    </div>
  </div>
</template>

<style scoped>
.skeleton {
  width: 100%;
}
.skeleton__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.skeleton__bar {
  display: block;
  height: 12px;
  border-radius: var(--radius-sm);
  background: linear-gradient(
    90deg,
    var(--color-ink-faint) 0%,
    var(--color-border-hover) 50%,
    var(--color-ink-faint) 100%
  );
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.4s ease-in-out infinite;
  opacity: 0.7;
}
.skeleton__bar--sm {
  height: 10px;
}
.skeleton__bar--title {
  height: 14px;
}
.skeleton__bar--lg {
  height: 22px;
}
.skeleton__bar--h1 {
  height: 28px;
  width: min(320px, 70%);
}
.skeleton__bar--btn {
  flex-shrink: 0;
  width: 112px;
  height: 34px;
  border-radius: var(--radius-md);
}
.skeleton__bar--input {
  width: 100%;
  height: 38px;
  border-radius: var(--radius-md);
}
.skeleton__bar--track {
  width: 100%;
  height: 8px;
}

.skeleton__stack {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}
.skeleton__row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.skeleton__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.skeleton__list-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.skeleton__table {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--color-surface);
}
.skeleton__tr {
  display: grid;
  gap: 16px;
  padding: 14px 16px;
  border-top: 1px solid var(--color-border);
}
.skeleton__tr--head {
  border-top: none;
}

.skeleton__cards,
.skeleton__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}
.skeleton__kpis {
  margin-bottom: 24px;
}
.skeleton__card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}
.skeleton__card--block {
  margin-bottom: 16px;
}

.skeleton__report-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}
.skeleton__score {
  display: grid;
  grid-template-columns: minmax(120px, 1fr) 2fr;
  align-items: center;
  gap: 16px;
}
.skeleton__field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

@media (max-width: 560px) {
  .skeleton__score {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .skeleton__tr {
    gap: 10px;
  }
}

@keyframes skeleton-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton__bar {
    animation: none;
  }
}
</style>
