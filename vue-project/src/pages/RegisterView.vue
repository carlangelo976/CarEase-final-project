
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/services/api'
import SiteFooter from '@/components/SiteFooter.vue'
import SiteHeader from '@/components/SiteHeader.vue'

const router = useRouter()

const fullName = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''

  if (password.value !== confirmPassword.value) {
    error.value = 'Passwords do not match.'
    return
  }

  if (password.value.length < 8) {
    error.value = 'Password must be at least 8 characters.'
    return
  }

  loading.value = true

  try {
    await api('/register', {
      full_name: fullName.value.trim(),
      email: email.value.trim(),
      password: password.value,
      confirm_password: confirmPassword.value
    })

    await router.push({
      path: '/verify-email',
      query: {
        email: email.value.trim()
      }
    })
  } catch (err) {
    error.value =
      err instanceof Error
        ? err.message
        : 'Registration failed. Please try again.'
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
            <h2>Your next journey<br /><em>starts here.</em></h2>
            <p>Comfortable vehicles, secure reservations, and effortless rental management—all in one place.</p>
            <div class="premium-auth-perks"><span>✓ Trusted fleet</span><span>✓ Secure booking</span><span>✓ Easy pickup</span></div>
          </div>
        </aside>
        <section class="panel narrow auth-panel">
          <p class="eyebrow">JOIN CAREASE</p>
          <h1>Create an account</h1>

          <p v-if="error" class="error">{{ error }}</p>

          <form @submit.prevent="handleSubmit">
            <label>
              Full Name
              <input
                v-model="fullName"
                name="full_name"
                required
                maxlength="120"
                autocomplete="name"
              />
            </label>

            <label>
              Email
              <input
                v-model="email"
                type="email"
                name="email"
                required
                maxlength="190"
                autocomplete="email"
              />
            </label>

            <label>
              Password (at least 8 characters)
              <input
                v-model="password"
                type="password"
                name="password"
                minlength="8"
                required
                autocomplete="new-password"
              />
            </label>

            <label>
              Confirm Password
              <input
                v-model="confirmPassword"
                type="password"
                name="confirm_password"
                minlength="8"
                required
                autocomplete="new-password"
              />
            </label>

            <button
              class="button"
              type="submit"
              :disabled="loading"
            >
              {{ loading ? 'Registering...' : 'Register' }}
            </button>
          </form>

          <p>
            Already registered?
            <router-link to="/login">Login</router-link>
          </p>
        </section>
      </div>
    </main>

    <SiteFooter />
  </div>
</template>
