<script setup lang="ts">
import { useRouter } from 'vue-router'
import { currentUser, isAdmin, logout } from '@/stores/carease'
import AppIcon from './AppIcon.vue'
import ThemeToggle from './ThemeToggle.vue'

const router = useRouter()

function handleLogout() {
  logout()
  router.push('/login')
}
</script>

<template>
  <header class="site-header">
    <div class="header-inner">
      <router-link class="brand" to="/">
        <span class="brand-mark">
          <AppIcon name="car" />
        </span>
        <span class="brand-text">
          <strong>CarEase</strong>
          <small>Car Rental Booking</small>
        </span>
      </router-link>

      <nav class="site-nav">
        <router-link to="/">Home</router-link>
        <router-link :to="{ path: '/', hash: '#fleet' }">Cars</router-link>
        <router-link :to="{ path: '/', hash: '#why' }">About</router-link>
        <router-link :to="{ path: '/', hash: '#how' }">How It Works</router-link>
        <router-link :to="{ path: '/', hash: '#support' }">Support</router-link>
      </nav>

      <div class="header-actions">
        <template v-if="currentUser">
          <router-link class="btn btn-on-dark btn-sm" :to="isAdmin ? '/admin' : '/account'">
            {{ isAdmin ? 'Dashboard' : 'My Account' }}
          </router-link>
          <span class="nav-user">Hi, {{ currentUser.full_name }}</span>
          <form class="logout-form" @submit.prevent="handleLogout">
            <button class="btn btn-on-dark btn-sm" type="submit">Logout</button>
          </form>
        </template>
        <template v-else>
          <router-link class="btn btn-on-dark btn-sm" to="/login">Sign In</router-link>
          <router-link class="btn btn-accent btn-sm" to="/register">Register</router-link>
        </template>
        <ThemeToggle />
      </div>
    </div>
  </header>
</template>
