<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { bookingById, resultPayment } from '@/stores/carease'
import { peso, ucfirst } from '@/lib/format'
import AppShell from '@/components/AppShell.vue'

const route = useRoute()
const bookingId = Number(route.params.id)
const booking = computed(() => bookingById(bookingId))
const payment = computed(() => resultPayment(bookingId))
const paid = computed(() => payment.value?.status === 'paid')
const amount = computed(() =>
  payment.value
    ? payment.value.amount_centavos / 100
    : booking.value
      ? Number(booking.value.total_amount)
      : 0,
)
</script>

<template>
  <AppShell
    shell="client"
    nav-key="bookings"
    title="Payment Result"
    :subtitle="`Booking #${bookingId}`"
  >
    <section class="panel">
      <h2>Payment for Booking #{{ bookingId }}</h2>
      <template v-if="paid">
        <p>Payment verified successfully. Thank you!</p>
        <table v-if="payment">
          <tbody>
            <tr>
              <th>Amount</th>
              <td>{{ peso(amount, 2) }}</td>
            </tr>
            <tr>
              <th>Payment Method</th>
              <td>{{ payment.payment_method || 'Not recorded' }}</td>
            </tr>
            <tr>
              <th>Status</th>
              <td>{{ ucfirst(payment.status) }}</td>
            </tr>
            <tr>
              <th>Paid At</th>
              <td>{{ payment.paid_at || '-' }}</td>
            </tr>
            <tr>
              <th>Reference</th>
              <td>{{ payment.checkout_session_id }}</td>
            </tr>
          </tbody>
        </table>
      </template>
      <template v-else>
        <p>Payment is not yet confirmed. Please return to My Bookings and check again later.</p>
        <p v-if="payment && payment.status === 'pending'">
          Amount due: {{ peso(amount, 2) }}
        </p>
      </template>
      <router-link class="button" to="/my-bookings">My Bookings</router-link>
    </section>
  </AppShell>
</template>
