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
      path: '/admin/users',
      name: 'admin-users',
      component: () => import('../views/AdminUsersView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
    {
      path: '/admin/pricing',
      name: 'admin-pricing',
      component: () => import('../views/AdminPricingView.vue'),
      meta: { requiresAuth: true, role: 'administrator' },
    },
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
      path: '/hr/candidates',
      name: 'hr-candidates',
      component: () => import('../views/HrCandidatesView.vue'),
      meta: { requiresAuth: true, role: 'hr' },
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

  if (to.meta.role && auth.user?.role !== to.meta.role) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
