import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { trackPageview } from '@/lib/analytics'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('../views/LandingView.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/daftar-grafolog',
      name: 'register-grafolog',
      component: () => import('../views/GrafologRegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/status-grafolog',
      name: 'grafolog-status',
      component: () => import('../views/GrafologStatusView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/daftar-perusahaan',
      name: 'register-company',
      component: () => import('../views/CompanyRegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/status-perusahaan',
      name: 'company-status',
      component: () => import('../views/CompanyStatusView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/ganti-password',
      name: 'change-password',
      component: () => import('../views/ChangePasswordView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/tugas-asesmen',
      name: 'candidate-tasks',
      component: () => import('../views/CandidateTasksView.vue'),
      meta: { requiresAuth: true, role: 'user' },
    },
    {
      path: '/profil',
      name: 'profile',
      component: () => import('../views/ProfileView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/lupa-password',
      name: 'forgot-password',
      component: () => import('../views/ForgotPasswordView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('../views/ResetPasswordView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/riwayat',
      name: 'riwayat',
      component: () => import('../views/RiwayatView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/pesan',
      name: 'order',
      component: () => import('../views/OrderView.vue'),
      meta: { requiresAuth: true, role: 'user' },
    },
    {
      path: '/token-saya',
      name: 'token-wallet',
      component: () => import('../views/TokenWalletView.vue'),
      meta: { requiresAuth: true, role: 'grafolog' },
    },
    {
      path: '/reports/:id',
      name: 'report',
      component: () => import('../views/ReportView.vue'),
      meta: { requiresAuth: true },
      props: true,
    },
    {
      path: '/portal-grafolog',
      name: 'portal-grafolog',
      component: () => import('../views/PortalGrafologView.vue'),
      meta: { requiresAuth: true, role: 'grafolog' },
    },
    {
      path: '/grafolog/klien',
      name: 'grafolog-clients',
      component: () => import('../views/GrafologClientsView.vue'),
      meta: { requiresAuth: true, role: 'grafolog' },
    },
    {
      path: '/grafolog/ditugaskan',
      name: 'assigned-to-me',
      component: () => import('../views/AssignedToMeView.vue'),
      meta: { requiresAuth: true, role: 'grafolog' },
    },
    {
      path: '/admin/orders',
      name: 'admin-orders',
      component: () => import('../views/AdminOrdersView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: () => import('../views/AdminUsersView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/products',
      name: 'admin-products',
      component: () => import('../views/AdminProductsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    // Harga kini dikelola per produk (2026-10-17).
    { path: '/admin/pricing', redirect: { name: 'admin-products' } },
    {
      path: '/admin/discounts',
      name: 'admin-discounts',
      component: () => import('../views/AdminDiscountsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/content',
      name: 'admin-content',
      component: () => import('../views/AdminContentView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/announcements',
      name: 'admin-announcements',
      component: () => import('../views/AdminAnnouncementsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/tokens',
      name: 'admin-tokens',
      component: () => import('../views/AdminTokensView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/grafolog',
      name: 'admin-grafolog',
      component: () => import('../views/AdminGrafologView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/perusahaan',
      name: 'admin-companies',
      component: () => import('../views/AdminCompaniesView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/knowledge',
      name: 'admin-knowledge',
      component: () => import('../views/AdminKnowledgeView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/grafolog/supervisi',
      name: 'validation-tasks',
      component: () => import('../views/ValidationTasksView.vue'),
      meta: { requiresAuth: true, role: 'grafolog' },
    },
    {
      path: '/admin/validasi',
      name: 'admin-validations',
      component: () => import('../views/AdminValidationsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/cv-saya',
      name: 'cv',
      component: () => import('../views/CvView.vue'),
      meta: { requiresAuth: true, role: ['user', 'grafolog'] },
    },
    {
      path: '/admin/cv',
      name: 'admin-cv',
      component: () => import('../views/AdminCvView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/ai-usage',
      name: 'admin-ai-usage',
      component: () => import('../views/AdminLlmUsageView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/produk',
      name: 'catalog',
      component: () => import('../views/ProductCatalogView.vue'),
    },
    {
      path: '/produk/:kode',
      name: 'product-detail',
      component: () => import('../views/ProductDetailView.vue'),
      props: true,
    },
    {
      path: '/pesanan/:id/chat',
      name: 'order-chat',
      component: () => import('../views/OrderChatView.vue'),
      props: true,
      meta: { requiresAuth: true, role: ['user', 'grafolog', 'administrator'] },
    },
    {
      path: '/kotak-masuk',
      name: 'inbox',
      component: () => import('../views/InboxView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/broadcast',
      name: 'admin-broadcast',
      component: () => import('../views/AdminBroadcastView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/events',
      name: 'admin-events',
      component: () => import('../views/AdminEventsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/games',
      name: 'admin-games',
      component: () => import('../views/AdminGamesView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/recap/users',
      name: 'admin-recap-users',
      component: () => import('../views/AdminRecapUsersView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/recap/grafolog',
      name: 'admin-recap-grafolog',
      component: () => import('../views/AdminRecapGrafologView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/recap/token-purchases',
      name: 'admin-recap-token-purchases',
      component: () => import('../views/AdminRecapTokenPurchasesView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/recap/payments',
      name: 'admin-recap-payments',
      component: () => import('../views/AdminRecapPaymentsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/analytics',
      name: 'admin-analytics',
      component: () => import('../views/AdminAnalyticsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/notification-logs',
      name: 'admin-notification-logs',
      component: () => import('../views/AdminNotificationLogsView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/log-aktivitas',
      name: 'admin-activity',
      component: () => import('../views/AdminActivityView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/hr/candidates',
      name: 'hr-candidates',
      component: () => import('../views/HrCandidatesView.vue'),
      meta: { requiresAuth: true, role: 'hr' },
    },
    {
      path: '/privasi',
      name: 'privacy',
      component: () => import('../views/LegalView.vue'),
      props: { doc: 'privasi' },
    },
    {
      path: '/syarat',
      name: 'terms',
      component: () => import('../views/LegalView.vue'),
      props: { doc: 'syarat' },
    },
    {
      path: '/kebijakan-transaksi',
      name: 'transaction-policy',
      component: () => import('../views/LegalView.vue'),
      props: { doc: 'transaksi' },
    },
    // Fase E (2026-10-17): lead magnet, konten, mini game - publik.
    {
      path: '/cek-tulisan',
      name: 'self-check',
      component: () => import('../views/SelfCheckView.vue'),
    },
    {
      path: '/cek-tulisan/:uuid',
      name: 'self-check-result',
      component: () => import('../views/SelfCheckView.vue'),
      props: true,
    },
    {
      path: '/artikel',
      name: 'articles',
      component: () => import('../views/ArticlesView.vue'),
    },
    {
      path: '/artikel/:slug',
      name: 'article',
      component: () => import('../views/ArticlesView.vue'),
      props: true,
    },
    {
      path: '/produk-mitra',
      name: 'partner-products',
      component: () => import('../views/PartnerProductsView.vue'),
    },
    {
      path: '/admin/verifikasi',
      name: 'admin-verification',
      component: () => import('../views/AdminVerificationView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/kegiatan',
      name: 'kegiatan-list',
      component: () => import('../views/KegiatanListView.vue'),
    },
    {
      path: '/kegiatan/:slug',
      name: 'kegiatan-detail',
      component: () => import('../views/KegiatanDetailView.vue'),
      props: true,
    },
    {
      path: '/games',
      name: 'games-hub',
      component: () => import('../views/GamesHubView.vue'),
    },
    {
      path: '/games/tebak-kepribadian',
      name: 'game-tebak-kepribadian',
      component: () => import('../views/GameTebakKepribadianView.vue'),
    },
    {
      path: '/games/trivia',
      name: 'game-trivia',
      component: () => import('../views/GameTriviaView.vue'),
    },
    {
      path: '/games/memory-match',
      name: 'game-memory-match',
      component: () => import('../views/GameMemoryMatchView.vue'),
    },
    {
      path: '/kuis',
      name: 'quiz',
      component: () => import('../views/QuizView.vue'),
    },
    {
      path: '/admin/engagement',
      name: 'admin-engagement',
      component: () => import('../views/AdminEngagementView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // Grafolog yang belum diverifikasi hanya boleh melihat halaman statusnya.
  if (to.meta.requiresAuth && auth.isPendingGrafolog && to.name !== 'grafolog-status') {
    return { name: 'grafolog-status' }
  }

  // Password sementara (kandidat dibuat perusahaan): wajib diganti dulu.
  if (to.meta.requiresAuth && auth.user?.must_change_password && to.name !== 'change-password') {
    return { name: 'change-password' }
  }

  // Kandidat B2B tidak punya dashboard/pembelian - hanya tugas asesmen.
  if (auth.isCandidate && ['dashboard', 'order', 'riwayat'].includes(to.name)) {
    return { name: 'candidate-tasks' }
  }

  // HR perusahaan yang belum diverifikasi hanya boleh melihat status perusahaannya.
  if (to.meta.requiresAuth && auth.isPendingHr && to.name !== 'company-status') {
    return { name: 'company-status' }
  }

  const allowedRoles = [to.meta.role].flat().filter(Boolean)
  const effectiveRole = auth.isPendingGrafolog ? 'grafolog_pending' : auth.user?.role
  if (allowedRoles.length && !allowedRoles.includes(effectiveRole)) {
    return { name: 'dashboard' }
  }

  return true
})

// SPA: pageview GA dikirim manual per navigasi (no-op bila GA belum dikonfigurasi).
router.afterEach((to) => {
  trackPageview(to.fullPath)
})

export default router
