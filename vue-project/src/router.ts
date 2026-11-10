
import { createRouter, createWebHashHistory } from 'vue-router'
import {
  isAdmin,
  isStaff,
  isAuthenticated,
  markOverdueBookings,
  restoreSession,
} from '@/stores/carease'

import HomeView from '@/pages/HomeView.vue'
import LoginView from '@/pages/LoginView.vue'
import RegisterView from '@/pages/RegisterView.vue'
import VerifyEmail from '@/pages/VerifyEmail.vue'
import BookView from '@/pages/BookView.vue'
import BookingsView from '@/pages/BookingsView.vue'
import AccountView from '@/pages/AccountView.vue'
import AdminView from '@/pages/AdminView.vue'
import MaintenanceView from '@/pages/MaintenanceView.vue'
import ReportsView from '@/pages/ReportsView.vue'
import CalendarView from '@/pages/CalendarView.vue'
import InspectionView from '@/pages/InspectionView.vue'
import PaymentReturnView from '@/pages/PaymentReturnView.vue'
import ValidIdView from '@/pages/ValidIdView.vue'
import StaffManagementView from '@/pages/StaffManagementView.vue'
import StaffBookingsView from '@/pages/StaffBookingsView.vue'
import StaffReportsView from '@/pages/StaffReportsView.vue'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),

  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
    },

    // EMAIL OTP VERIFICATION
    {
      path: '/verify-email',
      name: 'verify-email',
      component: VerifyEmail,
    },

    {
      path: '/book/:id',
      name: 'book',
      component: BookView,
      meta: { requiresClient: true },
    },
    {
      path: '/my-bookings',
      name: 'my-bookings',
      component: BookingsView,
      meta: { requiresClient: true },
    },
    {
      path: '/account',
      name: 'account',
      component: AccountView,
      meta: { requiresClient: true },
    },
    {
      path: '/valid-id',
      name: 'valid-id',
      component: ValidIdView,
      meta: { requiresClient: true },
    },
    {
      path: '/payment/return/:id',
      name: 'payment-return',
      component: PaymentReturnView,
      meta: { requiresClient: true },
    },

    // ADMIN ROUTES
    {
      path: '/admin',
      name: 'admin',
      component: AdminView,
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/fleet',
      name: 'admin-fleet',
      component: AdminView,
      props: { mode: 'fleet' },
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/bookings',
      name: 'admin-bookings',
      component: AdminView,
      props: { mode: 'bookings' },
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/valid-ids',
      name: 'admin-valid-ids',
      component: AdminView,
      props: { mode: 'valid-id' },
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/staff',
      name: 'staff-management',
      component: StaffManagementView,
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/maintenance',
      name: 'maintenance',
      component: MaintenanceView,
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/reports',
      name: 'reports',
      component: ReportsView,
      meta: { requiresAdmin: true },
    },
    {
      path: '/admin/inspection/:id',
      name: 'inspection',
      component: InspectionView,
      meta: { requiresAdmin: true },
    },
    {
      path: '/calendar',
      name: 'calendar',
      component: CalendarView,
      meta: { requiresAdmin: true },
    },

    // STAFF ROUTES
    {
      path: '/staff',
      redirect: '/staff/bookings',
      meta: { requiresStaff: true },
    },
    {
      path: '/staff/bookings',
      name: 'staff-bookings',
      component: StaffBookingsView,
      meta: { requiresStaff: true },
    },
    {
      path: '/staff/reports',
      name: 'staff-reports',
      component: StaffReportsView,
      meta: { requiresStaff: true },
    },

    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],

  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition

    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' }
    }

    return { top: 0 }
  },
})

let sessionPromise: Promise<unknown> | null = null

function ensureSession() {
  if (!sessionPromise) {
    sessionPromise = Promise.resolve()
      .then(() => restoreSession())
      .catch(error => {
        console.error('Session restoration failed:', error)
      })
  }

  return sessionPromise
}

router.beforeEach(async to => {
  await ensureSession()
  markOverdueBookings()

  const authed = isAuthenticated.value
  const admin = isAdmin.value
  const staff = isStaff.value

  if (
    (
      to.meta.requiresClient ||
      to.meta.requiresAdmin ||
      to.meta.requiresStaff
    ) &&
    !authed
  ) {
    return {
      path: '/login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta.requiresAdmin && !admin) {
    return staff ? '/staff' : '/account'
  }

  if (to.meta.requiresStaff && !staff) {
    return admin ? '/admin' : '/account'
  }

  if (to.meta.requiresClient && (admin || staff)) {
    return admin ? '/admin' : '/staff'
  }

  if (
    (
      to.path === '/login' ||
      to.path === '/register' ||
      to.path === '/verify-email'
    ) &&
    authed
  ) {
    return admin ? '/admin' : staff ? '/staff' : '/account'
  }

  return true
})

export default router
