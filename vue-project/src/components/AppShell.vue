
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { useRouter } from 'vue-router'
import { currentUser, logout } from '@/stores/carease'
import { initialsOf } from '@/lib/carease'
import AppIcon from './AppIcon.vue'
import ThemeToggle from './ThemeToggle.vue'

interface NavItem {
  key: string
  label: string
  icon: string
  to: RouteLocationRaw
}

const props = withDefaults(
  defineProps<{
    shell?: 'admin' | 'client' | 'staff'
    navKey?: string
    title: string
    subtitle?: string
  }>(),
  {
    shell: 'client',
    navKey: '',
    subtitle: '',
  },
)

const router = useRouter()

const SIDEBAR_KEY = 'carease.sidebar.collapsed'
const MOBILE_MAX = 900

const collapsed = ref(false)
const isOpen = ref(false)
const isMobile = ref(false)
const loggingOut = ref(false)

// ADMIN NAVIGATION
const adminNav: NavItem[] = [
  {
    key: 'dashboard',
    label: 'Dashboard',
    icon: 'grid',
    to: '/admin',
  },
  {
    key: 'fleet',
    label: 'Fleet & Cars',
    icon: 'car',
    to: '/admin/fleet',
  },
  {
    key: 'bookings',
    label: 'Bookings',
    icon: 'list',
    to: '/admin/bookings',
  },

  {
  key: 'valid-id',
  label: 'Valid ID Management',
  icon: 'file',
  to: '/admin/valid-ids',
},
  {
    key: 'maintenance',
    label: 'Maintenance',
    icon: 'wrench',
    to: '/admin/maintenance',
  },
  {
    key: 'reports',
    label: 'Reports & Charts',
    icon: 'chart',
    to: '/admin/reports',
  },
  {
    key: 'staff',
    label: 'Staff Management',
    icon: 'users',
    to: '/admin/staff',
  },
  {
    key: 'calendar',
    label: 'Calendar',
    icon: 'calendar',
    to: '/calendar',
  },
]

// STAFF NAVIGATION
const staffNav: NavItem[] = [
  {
    key: 'dashboard',
    label: 'Dashboard',
    icon: 'grid',
    to: '/staff/bookings',
  },
  {
    key: 'bookings',
    label: 'Bookings',
    icon: 'list',
    to: {
      path: '/staff/bookings',
      hash: '#bookings',
    },
  },
  {
    key: 'reports',
    label: 'Reports & Charts',
    icon: 'chart',
    to: '/staff/reports',
  },
]

// CLIENT NAVIGATION
const clientNav: NavItem[] = [
  {
    key: 'home',
    label: 'My Account',
    icon: 'grid',
    to: '/account',
  },
  {
    key: 'fleet',
    label: 'Browse Cars',
    icon: 'car',
    to: {
      path: '/',
      hash: '#fleet',
    },
  },
  {
    key: 'bookings',
    label: 'My Bookings',
    icon: 'list',
    to: '/my-bookings',
  },
  {
    key: 'valid-id',
    label: 'Valid ID',
    icon: 'file',
    to: '/valid-id',
  },
  {
    key: 'support',
    label: 'Support',
    icon: 'headset',
    to: {
      path: '/',
      hash: '#support',
    },
  },
]

// NAVIGATION BASED ON ROLE
const nav = computed(() => {
  if (props.shell === 'admin') {
    return adminNav
  }

  if (props.shell === 'staff') {
    return staffNav
  }

  return clientNav
})

// USER INITIALS
const initials = computed(() =>
  initialsOf(currentUser.value?.full_name),
)

// PORTAL LABEL
const shellLabel = computed(() => {
  if (props.shell === 'admin') {
    return 'Admin Console'
  }

  if (props.shell === 'staff') {
    return 'Staff Portal'
  }

  return 'Client Portal'
})

// ROLE LABEL
const roleLabel = computed(() => {
  if (props.shell === 'admin') {
    return 'Administrator'
  }

  if (props.shell === 'staff') {
    return 'Staff'
  }

  return 'Renter'
})

// SIDEBAR SETTINGS
function readCollapsed() {
  try {
    collapsed.value =
      window.localStorage.getItem(SIDEBAR_KEY) === '1'
  } catch {
    collapsed.value = false
  }
}

function persistCollapsed() {
  try {
    window.localStorage.setItem(
      SIDEBAR_KEY,
      collapsed.value ? '1' : '0',
    )
  } catch {
    // Ignore storage errors
  }
}

function toggleSidebar() {
  if (isMobile.value) {
    isOpen.value = !isOpen.value
    return
  }

  collapsed.value = !collapsed.value
  persistCollapsed()
}

function closeDrawer() {
  if (isMobile.value) {
    isOpen.value = false
  }
}

function handleResize() {
  isMobile.value =
    window.innerWidth <= MOBILE_MAX

  if (!isMobile.value) {
    isOpen.value = false
  }
}

// LOGOUT
async function handleLogout() {
  if (loggingOut.value) return

  loggingOut.value = true

  try {
    await logout()
    await router.replace('/login')
  } catch (err) {
    console.error('Logout failed:', err)
  } finally {
    loggingOut.value = false
  }
}

onMounted(() => {
  readCollapsed()
  handleResize()

  window.addEventListener(
    'resize',
    handleResize,
  )
})

onUnmounted(() => {
  window.removeEventListener(
    'resize',
    handleResize,
  )
})
</script>

<template>
  <div
    id="appShell"
    class="app-shell"
    :class="{
      'is-collapsed': collapsed,
      'is-open': isOpen,
    }"
  >
    <!-- SIDEBAR -->
    <aside
      id="appSidebar"
      class="app-sidebar"
    >
      <div class="sidebar-top">
        <router-link
          class="sidebar-brand"
          to="/"
          @click="closeDrawer"
        >
          <span class="brand-mark">
            <AppIcon name="car" />
          </span>

          <span class="sidebar-brand-text">
            <strong>CarEase</strong>
            <small>{{ shellLabel }}</small>
          </span>
        </router-link>

        <button
          class="sidebar-toggle"
          type="button"
          aria-label="Toggle sidebar"
          :aria-expanded="
            isMobile ? isOpen : !collapsed
          "
          aria-controls="appSidebar"
          @click="toggleSidebar"
        >
          <span class="sidebar-toggle-icon">
            <AppIcon name="menu" />
          </span>
        </button>
      </div>

      <!-- NAVIGATION -->
      <nav
        class="sidebar-nav"
        aria-label="Main navigation"
        @click="closeDrawer"
      >
        <p class="sidebar-label">
          {{
            props.shell === 'admin' ||
            props.shell === 'staff'
              ? 'Manage'
              : 'Menu'
          }}
        </p>

        <router-link
          v-for="item in nav"
          :key="item.key"
          class="sidebar-link"
          :class="{
            'is-active':
              props.navKey === item.key,
          }"
          :to="item.to"
          :title="item.label"
        >
          <span class="sidebar-icon">
            <AppIcon :name="item.icon" />
          </span>

          <span class="sidebar-text">
            {{ item.label }}
          </span>
        </router-link>
      </nav>

      <!-- SIDEBAR FOOTER -->
      <div class="sidebar-foot">
        <div class="sidebar-user">
          <span class="sidebar-avatar">
            {{ initials }}
          </span>

          <span class="sidebar-user-text">
            <strong>
              {{
                currentUser?.full_name ||
                'Guest'
              }}
            </strong>

            <small>
              {{ roleLabel }}
            </small>
          </span>
        </div>

        <router-link
          class="sidebar-link"
          to="/"
          title="Back to Website"
          @click="closeDrawer"
        >
          <span class="sidebar-icon">
            <AppIcon name="globe" />
          </span>

          <span class="sidebar-text">
            Back to Website
          </span>
        </router-link>

        <button
          class="
            sidebar-link
            sidebar-logout-btn
          "
          type="button"
          title="Logout"
          :disabled="loggingOut"
          @click="handleLogout"
        >
          <span class="sidebar-icon">
            <AppIcon name="logout" />
          </span>

          <span class="sidebar-text">
            {{
              loggingOut
                ? 'Logging out...'
                : 'Logout'
            }}
          </span>
        </button>
      </div>
    </aside>

    <!-- MOBILE BACKDROP -->
    <div
      class="app-backdrop"
      :hidden="!isOpen"
      @click="isOpen = false"
    ></div>

    <!-- MAIN CONTENT -->
    <div class="app-body">
      <header class="app-topbar">
        <button
          class="app-menu-btn"
          type="button"
          aria-label="Toggle sidebar"
          :aria-expanded="
            isMobile ? isOpen : !collapsed
          "
          @click="toggleSidebar"
        >
          <AppIcon name="menu" />
        </button>

        <div class="app-topbar-text">
          <h1>{{ props.title }}</h1>
          <p>{{ props.subtitle }}</p>
        </div>

        <div class="app-topbar-actions">
          <!-- ADMIN AND STAFF -->
          <router-link
            v-if="
              props.shell === 'admin' ||
              props.shell === 'staff'
            "
            class="btn btn-ghost btn-sm"
            to="/"
          >
            View Website
          </router-link>

          <!-- CLIENT -->
          <router-link
            v-else
            class="btn btn-accent btn-sm"
            :to="{
              path: '/',
              hash: '#fleet',
            }"
          >
            Browse Cars
          </router-link>

          <ThemeToggle />
        </div>
      </header>

      <!-- PAGE CONTENT -->
      <main class="app-content">
        <slot />
      </main>
    </div>
  </div>
</template>
