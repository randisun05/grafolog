import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

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

export default router
