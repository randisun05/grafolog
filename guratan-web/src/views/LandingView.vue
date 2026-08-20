<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'

// Default = teks yang identik dengan ContentBlockSeeder.php, supaya halaman
// tidak pernah kosong kalau API /content gagal/lambat (lihat CLAUDE.md).
const content = ref({
  landing_eyebrow: 'Analisis grafologi tepercaya',
  landing_hero_title: 'Tulisan tangan Anda, dibaca oleh ahli sungguhan.',
  landing_tagline:
    'Analisis kepribadian berbasis grafologi - laporan komprehensif dari tulisan tangan, disusun oleh grafolog bersertifikat.',
  landing_cta_label: 'Mulai Sekarang',
  landing_hero_trust: '8 Sindrom · 40 Aspek · 704 Indikator tulisan tangan dianalisis grafolog bersertifikat.',
  landing_compare_heading: 'Insight kepribadian, tanpa antre psikotes',
  landing_compare_subtext:
    'Psikotes konvensional lambat, mahal, dan butuh jadwal psikolog. Guratan menawarkan alternatif yang lebih cepat dan terjangkau - tanpa berpura-pura jadi pengganti diagnosis klinis.',
  landing_steps_heading: 'Cara Kerja',
  landing_steps_subtext: 'Empat langkah dari sampel tulisan tangan sampai laporan siap dibaca.',
  landing_analysis_heading: 'Bukan sekadar tebak-tebak kepribadian',
  landing_analysis_subtext: 'Setiap laporan ditarik dari basis pengetahuan grafologi profesional yang terstruktur.',
  landing_pricing_heading: 'Harga',
  landing_pricing_subtext: 'Dua tingkat layanan, keduanya dinilai manual oleh grafolog bersertifikat.',
  landing_honesty_quote: 'Insight reflektif untuk mengenal diri - bukan vonis, bukan diagnosis.',
  landing_signup_heading: 'Cara Daftar',
  landing_cta_band_heading: 'Penasaran apa kata tulisan tangan Anda?',
  landing_cta_band_subtext: 'Hasil disusun grafolog bersertifikat, harga transparan sejak awal.',
})

const listDefaults = {
  landing_compare_old: [
    'Jadwal psikolog, antre berhari-hari',
    'Biaya sesi tatap muka yang tinggi',
    'Laporan generik, kurang personal',
  ],
  landing_compare_new: [
    'Kirim sampel tulisan tangan kapan saja',
    'Harga transparan sejak awal',
    'Dinilai manual oleh grafolog bersertifikat',
  ],
  landing_steps: [
    { title: 'Pilih Tier & Kirim', desc: 'Daftar, pilih Comprehensive atau Master, kirim sampel tulisan tangan Anda.' },
    { title: 'Grafolog Mengukur', desc: 'Grafolog bersertifikat menilai tulisan tangan Anda memakai kaliper & pakem grafologi ilmiah.' },
    { title: 'Laporan Tersusun', desc: 'Sistem merangkai narasi dari basis pengetahuan grafologi - bukan karangan bebas.' },
    { title: 'Unduh & Baca', desc: 'Laporan lengkap tersedia di dashboard Anda, bisa diunduh sebagai PDF.' },
  ],
  landing_honesty_points: [
    { title: 'Dinilai manusia, bukan AI generatif', desc: 'Skor 40 aspek diisi manual oleh grafolog bersertifikat - bukan hasil tebakan model bahasa.' },
    { title: 'Narasi dari basis pengetahuan nyata', desc: 'Setiap kalimat laporan ditarik dari referensi grafologi terstruktur, bukan dikarang bebas.' },
    { title: 'Bukan pengganti diagnosis klinis', desc: 'Guratan adalah alat bantu refleksi diri, bukan alat diagnosis psikologis atau medis.' },
  ],
}
const lists = ref(structuredClone(listDefaults))

// Sindrom explorer: label & pengelompokan khusus halaman publik, dihaluskan
// ke Bahasa Indonesia - BUKAN terjemahan literal istilah teknis knowledge
// base (istilah asli tetap dipakai di form skor grafolog). Data statis
// sengaja, bukan dari /api/sindrom (endpoint itu perlu auth grafolog dan
// istilahnya memang beda tujuan - lihat catatan di guratan-web/CLAUDE.md).
const sindromData = [
  { roman: 'I', nama: 'Dorongan & Ambisi', hijau: true, aspek: ['Ketegasan', 'Kebutuhan Diakui', 'Kebutuhan Diterima', 'Cita-cita', 'Kepatuhan pada Norma'] },
  { roman: 'II', nama: 'Cara Berpikir', hijau: true, aspek: ['Ketajaman Berpikir', 'Fokus Berpikir', 'Motivasi', 'Daya Tangkap', 'Kreativitas'] },
  { roman: 'III', nama: 'Kepercayaan & Kendali Diri', hijau: true, aspek: ['Rasa Percaya Diri', 'Penghargaan Diri', 'Keselarasan Kepribadian', 'Integritas', 'Pengendalian Diri'] },
  { roman: 'IV', nama: 'Cara Bersosialisasi', hijau: true, aspek: ['Kemampuan Beradaptasi', 'Cara Berkomunikasi', 'Keterlibatan Sosial', 'Keramahan', 'Empati'] },
  { roman: 'V', nama: 'Semangat Berkarya', hijau: true, aspek: ['Keteguhan Hati', 'Energi & Stamina', 'Semangat Berkembang', 'Daya Cipta Solusi', 'Keteraturan'] },
  { roman: 'VI', nama: 'Ketahanan Menghadapi Tekanan', hijau: false, note: '5 aspek seputar ketahanan terhadap tekanan - dibahas lengkap dalam laporan, bukan di pratinjau publik.' },
  { roman: 'VII', nama: 'Cara Melindungi Diri', hijau: false, note: '5 aspek seputar pola pertahanan diri - dibahas lengkap dalam laporan, bukan di pratinjau publik.' },
  { roman: 'VIII', nama: 'Area yang Perlu Diperhatikan', hijau: false, note: '5 aspek seputar area yang perlu perhatian - dibahas lengkap dalam laporan, bukan di pratinjau publik.' },
]
const openSindrom = ref(null)
function toggleSindrom(i) {
  openSindrom.value = openSindrom.value === i ? null : i
}

const pricing = ref([])
function priceFor(tier) {
  return pricing.value.find((p) => p.tier === tier)?.price ?? null
}
function formatRupiah(value) {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

const statNums = ref([])
function registerStat(el) {
  if (el && !statNums.value.includes(el)) statNums.value.push(el)
}

onMounted(async () => {
  try {
    const { data } = await api.get('/content')
    content.value = { ...content.value, ...data }
    for (const key of Object.keys(listDefaults)) {
      if (!data[key]) continue
      try {
        lists.value[key] = JSON.parse(data[key])
      } catch {
        // Nilai tersimpan bukan JSON valid (seharusnya tidak terjadi lewat
        // AdminContentView) - diam-diam pakai default daripada halaman error.
      }
    }
  } catch {
    // Halaman utama tidak boleh kosong/error hanya karena CMS gagal dimuat.
  }

  try {
    const { data } = await api.get('/pricing')
    pricing.value = data
  } catch {
    // Kartu harga tetap tampil dengan "-" kalau publik pricing gagal dimuat.
  }

  await nextTick()
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return
        const el = entry.target
        const target = Number(el.dataset.count)
        if (reduceMotion) {
          el.textContent = target
          io.unobserve(el)
          return
        }
        let start = null
        const dur = 900
        function tick(ts) {
          if (!start) start = ts
          const progress = Math.min((ts - start) / dur, 1)
          el.textContent = Math.round(progress * target)
          if (progress < 1) requestAnimationFrame(tick)
        }
        requestAnimationFrame(tick)
        io.unobserve(el)
      })
    },
    { threshold: 0.6 },
  )
  statNums.value.forEach((el) => io.observe(el))
})
</script>

<template>
  <div class="landing">
    <section class="hero">
      <div>
        <span class="eyebrow">{{ content.landing_eyebrow }}</span>
        <h1>{{ content.landing_hero_title }}</h1>
        <p class="hero__tagline">{{ content.landing_tagline }}</p>
        <div class="hero__actions">
          <RouterLink to="/register" class="btn btn--primary">{{ content.landing_cta_label }}</RouterLink>
          <a href="#cara-kerja" class="btn btn--ghost">Lihat Cara Kerja ↓</a>
        </div>
        <p class="hero__trust">{{ content.landing_hero_trust }}</p>
      </div>
      <div class="hero__visual" aria-hidden="true">
        <svg viewBox="0 0 320 220" width="72%" role="img" aria-label="Ilustrasi tanda tangan tergambar secara animasi">
          <path
            class="signature-path"
            d="M30 150 C 50 90, 70 90, 85 140 C 95 175, 110 175, 118 130 C 124 100, 135 100, 140 140 C 146 180, 165 60, 175 130 C 182 175, 195 175, 205 100 C 212 55, 225 55, 230 110 C 234 145, 250 145, 258 100"
          />
          <g class="seal-stamp" transform="translate(250,150)">
            <circle cx="0" cy="0" r="30" fill="none" stroke="var(--color-gold)" stroke-width="3" />
            <circle cx="0" cy="0" r="23" fill="none" stroke="var(--color-gold)" stroke-width="1" />
            <text x="0" y="6" text-anchor="middle" font-family="Fraunces, serif" font-size="13" fill="var(--color-gold)" font-weight="700">G</text>
          </g>
        </svg>
      </div>
    </section>

    <section class="band">
      <div class="section-head">
        <span class="eyebrow">Kenapa grafologi</span>
        <h2>{{ content.landing_compare_heading }}</h2>
        <p>{{ content.landing_compare_subtext }}</p>
      </div>
      <div class="compare">
        <div class="compare__card compare__card--old">
          <h3>Cara Lama</h3>
          <ul>
            <li v-for="item in lists.landing_compare_old" :key="item">{{ item }}</li>
          </ul>
        </div>
        <div class="compare__card compare__card--new">
          <h3>Cara Guratan</h3>
          <ul>
            <li v-for="item in lists.landing_compare_new" :key="item"><span class="check">✓</span> {{ item }}</li>
          </ul>
        </div>
      </div>
    </section>

    <section id="cara-kerja">
      <div class="section-head">
        <span class="eyebrow">Prosesnya</span>
        <h2>{{ content.landing_steps_heading }}</h2>
        <p>{{ content.landing_steps_subtext }}</p>
      </div>
      <div class="steps">
        <div v-for="(step, i) in lists.landing_steps" :key="step.title" class="step">
          <div class="step__num">{{ String(i + 1).padStart(2, '0') }}</div>
          <h4>{{ step.title }}</h4>
          <p>{{ step.desc }}</p>
        </div>
      </div>
    </section>

    <section class="band" id="analisis">
      <div class="section-head">
        <span class="eyebrow">Kedalaman analisis</span>
        <h2>{{ content.landing_analysis_heading }}</h2>
        <p>{{ content.landing_analysis_subtext }}</p>
      </div>
      <div class="stat-row">
        <div class="stat"><div class="stat__num" :ref="registerStat" data-count="8">0</div><div class="stat__label">Sindrom</div></div>
        <div class="stat"><div class="stat__num" :ref="registerStat" data-count="40">0</div><div class="stat__label">Aspek</div></div>
        <div class="stat"><div class="stat__num" :ref="registerStat" data-count="704">0</div><div class="stat__label">Indikator</div></div>
      </div>
      <div class="explorer">
        <div v-for="(s, i) in sindromData" :key="s.roman" class="sindrom-row" :class="{ open: openSindrom === i }">
          <button type="button" class="sindrom-row__head" @click="toggleSindrom(i)">
            <span class="sindrom-row__title">
              <span class="sindrom-row__roman">{{ s.roman }}</span>
              <span class="sindrom-row__name">{{ s.nama }}</span>
            </span>
            <span class="sindrom-row__chevron">⌄</span>
          </button>
          <div class="sindrom-row__body">
            <div class="sindrom-row__inner">
              <div v-if="s.hijau" class="aspek-chips">
                <span v-for="a in s.aspek" :key="a" class="aspek-chip">{{ a }}</span>
              </div>
              <p v-else class="aspek-note">{{ s.note }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="harga">
      <div class="section-head">
        <span class="eyebrow">Transparan sejak awal</span>
        <h2>{{ content.landing_pricing_heading }}</h2>
        <p>{{ content.landing_pricing_subtext }}</p>
      </div>
      <div class="pricing-grid">
        <div class="price-card">
          <div class="price-card__tier">Comprehensive</div>
          <div class="price-card__amount">{{ formatRupiah(priceFor('comprehensive')) }}<span> /laporan</span></div>
          <p class="price-card__desc">Analisis mendalam 40 aspek kepribadian dari tulisan tangan Anda.</p>
          <ul>
            <li><span class="check">✓</span> Laporan 8 Sindrom &amp; 40 Aspek lengkap</li>
            <li><span class="check">✓</span> Disusun grafolog bersertifikat</li>
            <li><span class="check">✓</span> Unduh PDF kapan saja</li>
          </ul>
          <RouterLink to="/register" class="btn btn--ghost price-card__cta">Pilih Comprehensive</RouterLink>
        </div>
        <div class="price-card price-card--master">
          <span class="price-card__badge badge badge--gold">Paling Lengkap</span>
          <div class="price-card__tier">Master</div>
          <div class="price-card__amount">{{ formatRupiah(priceFor('master')) }}<span> /laporan</span></div>
          <p class="price-card__desc">Semua isi Comprehensive, plus sesi konsultasi langsung.</p>
          <ul>
            <li><span class="check">✓</span> Semua fitur Comprehensive</li>
            <li><span class="check">✓</span> Sesi konsultasi langsung dengan grafolog</li>
            <li><span class="check">✓</span> Prioritas pengerjaan</li>
          </ul>
          <RouterLink to="/register" class="btn btn--primary price-card__cta">Pilih Master</RouterLink>
        </div>
      </div>
    </section>

    <section class="band">
      <div class="honesty">
        <div>
          <span class="eyebrow">Kejujuran kami</span>
          <p class="honesty__quote">"{{ content.landing_honesty_quote }}"</p>
        </div>
        <div class="honesty__points">
          <div v-for="p in lists.landing_honesty_points" :key="p.title" class="honesty__point">
            <div class="honesty__point-mark">✓</div>
            <div><h4>{{ p.title }}</h4><p>{{ p.desc }}</p></div>
          </div>
        </div>
      </div>
    </section>

    <section>
      <div class="section-head"><span class="eyebrow">Mulai dalam 3 langkah</span><h2>{{ content.landing_signup_heading }}</h2></div>
      <div class="signup-flow">
        <div class="signup-node"><div class="signup-node__dot">1</div><p>Buat akun dengan email</p></div>
        <div class="signup-arrow"></div>
        <div class="signup-node"><div class="signup-node__dot">2</div><p>Pilih tier &amp; kirim sampel tulisan tangan</p></div>
        <div class="signup-arrow"></div>
        <div class="signup-node"><div class="signup-node__dot">3</div><p>Bayar &amp; tunggu laporan Anda</p></div>
      </div>
    </section>

    <section class="cta-band">
      <h2>{{ content.landing_cta_band_heading }}</h2>
      <p>{{ content.landing_cta_band_subtext }}</p>
      <RouterLink to="/register" class="btn btn--primary">{{ content.landing_cta_label }}</RouterLink>
    </section>
  </div>
</template>

<style scoped>
.landing {
  margin: 0 calc(50% - 50vw);
  max-width: 100vw;
  overflow-x: clip;
}
.landing section {
  padding: 72px 20px;
  max-width: 1080px;
  margin: 0 auto;
}
.landing .band {
  max-width: 100%;
  background: var(--color-surface);
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
}
.landing .band > * {
  max-width: 1080px;
  margin-left: auto;
  margin-right: auto;
}

.eyebrow {
  font-family: var(--font-accent);
  font-size: 21px;
  color: var(--color-seal);
  display: inline-block;
}

.hero {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  align-items: center;
  gap: 44px;
  padding-top: 40px !important;
}
.hero h1 {
  font-size: clamp(34px, 5vw, 54px);
  margin: 10px 0 16px;
  letter-spacing: -0.01em;
}
.hero__tagline {
  font-size: 16.5px;
  color: var(--color-text-soft);
  max-width: 440px;
  margin-bottom: 26px;
  line-height: 1.55;
}
.hero__actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 22px;
}
.hero__trust {
  font-size: 13px;
  color: var(--color-text-soft);
}
.hero__visual {
  position: relative;
  aspect-ratio: 1 / 1;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-card);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.hero__visual::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(var(--color-ink-faint) 1px, transparent 1px);
  background-size: 18px 18px;
  opacity: 0.5;
}
.signature-path {
  stroke: var(--color-seal);
  stroke-width: 3;
  fill: none;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 1400;
  stroke-dashoffset: 1400;
  animation: draw 2.6s ease forwards 0.3s;
}
.seal-stamp {
  transform-origin: center;
  opacity: 0;
  animation: stamp-in 0.5s ease forwards 2.4s;
}
@keyframes draw {
  to {
    stroke-dashoffset: 0;
  }
}
@keyframes stamp-in {
  from {
    opacity: 0;
    transform: scale(1.6) rotate(-8deg);
  }
  to {
    opacity: 1;
    transform: scale(1) rotate(-8deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .signature-path {
    animation: none;
    stroke-dashoffset: 0;
  }
  .seal-stamp {
    animation: none;
    opacity: 1;
  }
}
@media (max-width: 860px) {
  .hero {
    grid-template-columns: 1fr;
  }
  .hero__visual {
    max-width: 360px;
    margin: 0 auto;
  }
}

.section-head {
  max-width: 620px;
  margin: 0 auto 44px;
  text-align: center;
}
.section-head h2 {
  font-size: clamp(26px, 3.2vw, 36px);
  margin: 10px 0 12px;
}
.section-head p {
  color: var(--color-text-soft);
  font-size: 15.5px;
}

.compare {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}
.compare__card {
  padding: 28px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}
.compare__card--old {
  background: var(--color-background);
}
.compare__card--new {
  background: var(--color-sage-soft);
  border-color: var(--color-sage);
}
.compare__card h3 {
  font-size: 17px;
  margin-bottom: 14px;
}
.compare__card ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 11px;
}
.compare__card li {
  font-size: 14px;
  color: var(--color-text-soft);
}
.compare__card--new li {
  color: var(--color-ink);
}
@media (max-width: 700px) {
  .compare {
    grid-template-columns: 1fr;
  }
}

.steps {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}
.step {
  padding: 24px 18px 20px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.step:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-card);
}
.step__num {
  font-family: var(--font-heading);
  font-size: 30px;
  color: var(--color-seal-soft);
  -webkit-text-stroke: 1.4px var(--color-seal);
  font-weight: 700;
  margin-bottom: 8px;
}
.step h4 {
  font-size: 15px;
  margin-bottom: 5px;
}
.step p {
  font-size: 13px;
  color: var(--color-text-soft);
}
@media (max-width: 880px) {
  .steps {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 540px) {
  .steps {
    grid-template-columns: 1fr;
  }
}

.stat-row {
  display: flex;
  justify-content: center;
  gap: 52px;
  margin-bottom: 48px;
  flex-wrap: wrap;
}
.stat {
  text-align: center;
}
.stat__num {
  font-family: var(--font-heading);
  font-size: 40px;
  font-weight: 700;
}
.stat__label {
  font-size: 12.5px;
  color: var(--color-text-soft);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.explorer {
  display: grid;
  gap: 10px;
  max-width: 780px;
  margin: 0 auto;
}
.sindrom-row {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-background);
  overflow: hidden;
}
.sindrom-row__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  border: none;
  background: none;
  padding: 16px 20px;
  text-align: left;
  font-family: inherit;
  color: var(--color-ink);
}
.sindrom-row__title {
  display: flex;
  align-items: center;
  gap: 13px;
}
.sindrom-row__roman {
  font-family: var(--font-heading);
  color: var(--color-text-soft);
  font-size: 14px;
  width: 26px;
}
.sindrom-row__name {
  font-weight: 700;
  font-size: 15px;
}
.sindrom-row__chevron {
  transition: transform 0.2s ease;
  color: var(--color-text-soft);
}
.sindrom-row.open .sindrom-row__chevron {
  transform: rotate(180deg);
}
.sindrom-row__body {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.28s ease;
}
.sindrom-row.open .sindrom-row__body {
  max-height: 220px;
}
.sindrom-row__inner {
  padding: 0 20px 18px;
}
.aspek-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.aspek-chip {
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--color-sage-soft);
  color: var(--color-sage);
  font-size: 12px;
  font-weight: 600;
}
.aspek-note {
  font-size: 13px;
  color: var(--color-text-soft);
  font-style: italic;
}

.pricing-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 22px;
  max-width: 720px;
  margin: 0 auto;
}
.price-card {
  position: relative;
  padding: 30px 26px;
  border-radius: var(--radius-lg);
  border: 1.5px solid var(--color-border);
  background: var(--color-surface);
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.price-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-card);
}
.price-card--master {
  border-color: var(--color-gold);
  background: linear-gradient(160deg, var(--color-gold-soft), var(--color-surface) 55%);
}
.price-card__badge {
  position: absolute;
  top: -12px;
  right: 24px;
}
.badge {
  display: inline-flex;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
}
.badge--gold {
  background: var(--color-gold-soft);
  color: var(--color-gold);
}
.price-card__tier {
  font-size: 12.5px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-soft);
  margin-bottom: 8px;
}
.price-card__amount {
  font-family: var(--font-heading);
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 4px;
}
.price-card__amount span {
  font-size: 13px;
  font-weight: 400;
  color: var(--color-text-soft);
}
.price-card__desc {
  font-size: 13px;
  color: var(--color-text-soft);
  margin-bottom: 20px;
}
.price-card ul {
  list-style: none;
  margin: 0 0 22px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.price-card li {
  display: flex;
  gap: 8px;
  font-size: 13.5px;
}
.check {
  color: var(--color-sage);
  font-weight: 700;
}
.price-card__cta {
  width: 100%;
  justify-content: center;
}
@media (max-width: 620px) {
  .pricing-grid {
    grid-template-columns: 1fr;
  }
}

.honesty {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 36px;
  align-items: center;
}
.honesty__quote {
  font-family: var(--font-accent);
  font-size: 26px;
  color: var(--color-seal);
  line-height: 1.35;
  margin-top: 12px;
}
.honesty__points {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.honesty__point {
  display: flex;
  gap: 13px;
}
.honesty__point-mark {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  background: var(--color-sage-soft);
  color: var(--color-sage);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-weight: 700;
}
.honesty__point h4 {
  font-size: 14px;
  margin-bottom: 3px;
}
.honesty__point p {
  font-size: 13px;
  color: var(--color-text-soft);
}
@media (max-width: 740px) {
  .honesty {
    grid-template-columns: 1fr;
  }
}

.signup-flow {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  gap: 0;
  flex-wrap: wrap;
}
.signup-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 150px;
  text-align: center;
}
.signup-node__dot {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  background: var(--color-ink);
  color: var(--color-paper-alt, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-heading);
  font-size: 17px;
}
.signup-node p {
  font-size: 12.5px;
  color: var(--color-text-soft);
}
.signup-arrow {
  width: 56px;
  height: 2px;
  background: var(--color-border);
  position: relative;
  margin-top: 21px;
}
.signup-arrow::after {
  content: '›';
  position: absolute;
  right: -4px;
  top: -11px;
  color: var(--color-border-hover);
  font-size: 19px;
}
@media (max-width: 740px) {
  .signup-arrow {
    display: none;
  }
}

.cta-band {
  text-align: center;
  padding: 80px 20px !important;
  background: var(--color-ink);
  color: var(--color-paper);
  max-width: 100% !important;
}
.cta-band h2 {
  color: var(--color-paper);
  font-size: clamp(24px, 3.4vw, 34px);
  margin-bottom: 12px;
}
.cta-band p {
  color: color-mix(in srgb, var(--color-paper) 72%, transparent);
  margin-bottom: 26px;
  font-size: 14.5px;
}
</style>
