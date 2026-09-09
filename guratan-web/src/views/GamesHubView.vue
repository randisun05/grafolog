<script setup>
import { RouterLink } from 'vue-router'

const games = [
  {
    to: '/games/tebak-kepribadian',
    title: 'Tebak Kepribadian dari Tulisan',
    desc: 'Tebak sifat kepribadian dari ciri tulisan tangan nyata, lalu lihat penjelasannya.',
    emoji: '✍️',
  },
  {
    to: '/games/trivia',
    title: 'Trivia Grafologi',
    desc: 'Uji wawasanmu seputar fakta dan istilah grafologi.',
    emoji: '🧠',
  },
  {
    to: '/games/memory-match',
    title: 'Memory Match Istilah',
    desc: 'Cocokkan kartu istilah grafologi dengan definisinya secepat mungkin.',
    emoji: '🃏',
  },
]
</script>

<template>
  <div class="games-hub">
    <h1>Games Grafologi</h1>
    <p class="games-hub__disclaimer">
      Game ini untuk hiburan &amp; edukasi ringan, bukan alat diagnosis kepribadian. Hasilnya tidak
      mencerminkan analisis tulisan tangan Anda sendiri.
    </p>

    <div class="games-hub__grid">
      <RouterLink v-for="game in games" :key="game.to" :to="game.to" class="games-hub__card">
        <span class="games-hub__emoji">{{ game.emoji }}</span>
        <h2>{{ game.title }}</h2>
        <p>{{ game.desc }}</p>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.games-hub {
  max-width: 900px;
}
.games-hub__disclaimer {
  font-size: 13px;
  color: var(--color-text-soft);
  background: var(--color-paper-alt);
  border-left: 3px solid var(--color-gold);
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  margin-bottom: 24px;
}
.games-hub__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 20px;
}
.games-hub__card {
  display: block;
  text-decoration: none;
  color: var(--color-ink);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 24px 20px;
  box-shadow: var(--shadow-card);
  transition: transform 0.15s ease;
}
.games-hub__card:hover {
  transform: translateY(-3px);
}
.games-hub__emoji {
  font-size: 32px;
}
.games-hub__card h2 {
  font-family: var(--font-heading);
  font-size: 17px;
  margin: 10px 0 8px;
}
.games-hub__card p {
  font-size: 13.5px;
  color: var(--color-text-soft);
  line-height: 1.5;
}
</style>
