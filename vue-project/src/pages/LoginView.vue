
<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { currentUser, login } from '@/stores/carease'
import SiteFooter from '@/components/SiteFooter.vue'
import SiteHeader from '@/components/SiteHeader.vue'

const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''

  if (!email.value.trim() || !password.value) {
    error.value = 'Please enter your email and password.'
    return
  }

  loading.value = true

  try {
    // Login through the store, which also updates the user session
    const message = await login(
      email.value.trim(),
      password.value,
    )

    if (message) {
      error.value = message
      return
    }

    if (!currentUser.value) {
      error.value = 'Login failed. Please try again.'
      return
    }

    // Redirect to requested page if valid
    const redirect = route.query.redirect

    if (
      typeof redirect === 'string' &&
      redirect.startsWith('/') &&
      !redirect.startsWith('//')
    ) {
      await router.push(redirect)
      return
    }

    // Redirect based on user role
    if (currentUser.value.role === 'admin') {
      await router.push('/admin')
    } else {
      await router.push('/account')
    }
  } catch (err: unknown) {
    if (err instanceof Error) {
      error.value = err.message
    } else {
      error.value = 'Unable to login. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <SiteHeader />

    <main class="site-main premium-auth-main">
      <div class="page-body premium-auth-layout">
        <aside class="premium-auth-showcase" aria-label="CarEase premium rentals">
          <div class="premium-brand"><span class="premium-brand-symbol">✦</span><div><strong>CarEase<span>Rentals</span></strong><small>PREMIUM CAR RENTAL</small></div></div>
          <div class="premium-visual-car" aria-hidden="true">
            <div class="premium-car-window"></div><div class="premium-car-body"></div>
            <div class="premium-wheel premium-wheel--front"></div><div class="premium-wheel premium-wheel--back"></div>
          </div>
          <div class="premium-auth-copy">
            <span class="premium-auth-kicker">YOUR ROAD. YOUR FREEDOM.</span>
            <h2>Drive anywhere.<br /><em>Arrive in style.</em></h2>
            <p>Comfortable vehicles, secure reservations, and effortless rental management—all in one place.</p>
            <div class="premium-auth-perks"><span>✓ Trusted fleet</span><span>✓ Secure booking</span><span>✓ Easy pickup</span></div>
          </div>
        </aside>
        <section class="panel narrow auth-panel">
          <p class="eyebrow">WELCOME BACK</p>

          <h1>Login to CarEase</h1>

          <p class="muted">
            Access your reservations and manage every trip in one place.
          </p>

          <p v-if="error" class="error" role="alert">
            {{ error }}
          </p>

          <form @submit.prevent="handleSubmit">
            <label>
              Email
              <input
                v-model="email"
                type="email"
                name="email"
                autocomplete="email"
                required
                maxlength="190"
                :disabled="loading"
              />
            </label>

            <label>
              Password
              <input
                v-model="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
                :disabled="loading"
              />
            </label>

            <button
              class="button"
              type="submit"
              :disabled="loading"
            >
              {{ loading ? 'Logging in...' : 'Login' }}
            </button>
          </form>

          <p>
            New here?
            <router-link to="/register">
              Create an account
            </router-link>
          </p>
        </section>
      </div>
    </main>

    <SiteFooter />
  </div>
</template>
