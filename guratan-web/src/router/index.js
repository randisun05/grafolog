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
      path: '/admin/knowledge',
      name: 'admin-knowledge',
      component: () => import('../views/AdminKnowledgeView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/supervisor/reviews',
      name: 'supervisor-reviews',
      component: () => import('../views/SupervisorReviewView.vue'),
      meta: { requiresAuth: true, role: ['supervisor', 'administrator'] },
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

  const allowedRoles = [to.meta.role].flat().filter(Boolean)
  const effectiveRole = auth.isPendingGrafolog ? 'grafolog_pending' : auth.user?.role
  if (allowedRoles.length && !allowedRoles.includes(effectiveRole)) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
