<script setup>
import { computed } from 'vue'

/**
 * Ilustrasi ciri tulisan untuk opsi cek tulisan (token `ilustrasi` dari backend). Murni CSS -
 * hanya membantu pengguna mengenali ciri, bukan contoh pengukuran.
 */
const props = defineProps({ token: { type: String, default: '' } })

const WORDS = ['tulisan', 'tangan', 'saya']
const lines = computed(() => {
  const t = props.token
  if (t.startsWith('margin-')) {
    const pads = {
      'margin-widening': [2, 8, 14, 20],
      'margin-narrowing': [20, 14, 8, 2],
      'margin-even': [8, 8, 8, 8],
      'margin-irregular': [4, 16, 6, 18],
    }[t]
    return pads.map((p) => ({ style: { paddingLeft: `${p}px` }, words: ['tulisan tangan'] }))
  }
  return [{ style: {}, words: WORDS }]
})

function wordStyle(i) {
  const t = props.token
  const alt = i % 2 === 0
  switch (t) {
    case 'slant-right':
      return { transform: 'skewX(-18deg)' }
    case 'slant-left':
      return { transform: 'skewX(18deg)' }
    case 'slant-mixed':
      return { transform: `skewX(${alt ? -18 : 14}deg)` }
    case 'pressure-heavy':
      return { fontWeight: 800, textShadow: '0 0 1px currentColor' }
    case 'pressure-light':
      return { fontWeight: 300, opacity: 0.55 }
    case 'size-large':
      return { fontSize: '26px' }
    case 'size-small':
      return { fontSize: '11px' }
    case 'size-mixed':
      return { fontSize: alt ? '24px' : '13px' }
    case 'spacing-narrow':
      return { marginRight: '1px' }
    case 'spacing-broad':
      return { marginRight: '26px' }
    case 'spacing-mixed':
      return { marginRight: ['2px', '24px', '8px'][i % 3] }
    case 'speed-fast':
      return { transform: 'skewX(-10deg)', letterSpacing: '-1px' }
    case 'speed-slow':
      return { letterSpacing: '2px' }
    default:
      return {}
  }
}

const signature = computed(() => {
  const t = props.token
  if (!t.startsWith('sig-')) return null
  const base = { text: 'Budi S.', style: { fontSize: '20px' } }
  if (t === 'sig-larger') base.style.fontSize = '30px'
  if (t === 'sig-smaller') base.style.fontSize = '12px'
  if (t === 'sig-illegible') base.text = 'Bdſ~ʃ'
  if (t === 'sig-underlined') base.style.textDecoration = 'underline'
  return base
})
</script>

<template>
  <div class="hw" aria-hidden="true">
    <template v-if="signature">
      <span class="hw__text">tulisan tangan saya</span>
      <span class="hw__sig" :style="signature.style">{{ signature.text }}</span>
    </template>
    <template v-else>
      <div v-for="(line, li) in lines" :key="li" class="hw__line" :style="line.style">
        <span v-for="(w, i) in line.words" :key="i" class="hw__word" :style="wordStyle(i)">{{
          w
        }}</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.hw {
  font-family: 'Segoe Script', 'Bradley Hand', 'Comic Sans MS', cursive;
  color: var(--color-ink);
  font-size: 17px;
  min-height: 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 2px;
  overflow: hidden;
}
.hw__line {
  white-space: nowrap;
  border-bottom: 1px dashed var(--color-border);
  line-height: 1.5;
}
.hw__word {
  display: inline-block;
  margin-right: 9px;
}
.hw__text {
  font-size: 16px;
}
.hw__sig {
  margin-top: 2px;
  align-self: flex-end;
}
</style>
