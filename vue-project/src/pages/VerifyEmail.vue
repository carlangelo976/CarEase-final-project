
<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '@/services/api'
import SiteHeader from '@/components/SiteHeader.vue'
import SiteFooter from '@/components/SiteFooter.vue'

const route = useRoute()
const router = useRouter()

const email = ref(
  typeof route.query.email === 'string'
    ? route.query.email
    : ''
)

const otp = ref('')
const error = ref('')
const message = ref('')
const loading = ref(false)
const resending = ref(false)

async function verifyEmail() {
  error.value = ''
  message.value = ''

  if (!email.value.trim()) {
    error.value = 'Please enter your email address.'
    return
  }

  if (!/^[0-9]{6}$/.test(otp.value)) {
    error.value = 'Please enter a valid 6-digit code.'
    return
  }

  loading.value = true

  try {
    await api('/verify-email', {
      email: email.value.trim(),
      otp: otp.value
    })

    message.value = 'Email verified successfully!'

    await router.push({
      path: '/login',
      query: { verified: '1' }
    })
  } catch (err) {
    error.value =
      err instanceof Error
        ? err.message
        : 'Verification failed. Please try again.'
  } finally {
    loading.value = false
  }
}

async function resendOtp() {
  error.value = ''
  message.value = ''

  if (!email.value.trim()) {
    error.value = 'Please enter your email address.'
    return
  }

  resending.value = true

  try {
    await api('/resend-otp', {
      email: email.value.trim()
    })

    message.value =
      'If your account needs verification, a new code will be sent to your email.'
  } catch (err) {
    error.value =
      err instanceof Error
        ? err.message
        : 'Unable to resend code. Please try again.'
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div>
    <SiteHeader />

    <main class="site-main">
      <div class="page-body">
        <section class="panel narrow auth-panel">
          <p class="eyebrow">CAREASE RENTALS</p>
          <h1>Verify Your Email</h1>

          <p>
            Enter the 6-digit verification code
            sent to your email address.
          </p>

          <p v-if="error" class="error">
            {{ error }}
          </p>

          <p v-if="message" role="status">
            {{ message }}
          </p>

          <form @submit.prevent="verifyEmail">
            <label>
              Email Address
              <input
                v-model="email"
                type="email"
                required
                autocomplete="email"
              />
            </label>

            <label>
              Verification Code
              <input
                v-model="otp"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="Enter 6-digit OTP"
                autocomplete="one-time-code"
                required
              />
            </label>

            <button
              class="button"
              type="submit"
              :disabled="loading || resending"
            >
              {{ loading ? 'Verifying...' : 'Verify Email' }}
            </button>
          </form>

          <p>Didn't receive the code?</p>

          <button
            class="button"
            type="button"
            :disabled="loading || resending"
            @click="resendOtp"
          >
            {{ resending ? 'Sending...' : 'Resend OTP' }}
          </button>

          <p>
            Already verified?
            <router-link to="/login">
              Login
            </router-link>
          </p>
        </section>
      </div>
    </main>

    <SiteFooter />
  </div>
</template>
