
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  bookingsForUser,
  cars,
  currentName,
  currentUser,
  payNow,
  checkPaymentStatus,
  loadMyBookings,
} from '@/stores/carease'
import type { PaymentSummary } from '@/stores/carease'
import { carImageUrl } from '@/lib/carease'
import { money, peso, pesoShort } from '@/lib/format'
import AppIcon from '@/components/AppIcon.vue'
import AppShell from '@/components/AppShell.vue'

const subtitle = computed(
  () => `Welcome back, ${currentName.value || 'renter'}`,
)

const mine = computed(() =>
  currentUser.value
    ? bookingsForUser(currentUser.value.id)
    : [],
)

const active = computed(() =>
  mine.value.filter(b =>
    ['pending', 'approved', 'confirmed', 'ongoing', 'overdue']
      .includes(b.status),
  ),
)

const past = computed(() =>
  mine.value.filter(b =>
    ['completed', 'cancelled', 'rejected']
      .includes(b.status),
  ),
)

const availableCars = computed(() =>
  cars.value.filter(c => c.status === 'available'),
)

const totalBooked = computed(() =>
  mine.value
    .filter(b =>
      ['completed', 'approved', 'confirmed', 'ongoing']
        .includes(b.status),
    )
    .reduce((sum, b) => sum + money(b.total_amount), 0),
)

const paymentDetails = ref<Record<number, PaymentSummary>>({})
const paymentErrors = ref<Record<number, string>>({})
const loadingPayments = ref(false)
const paying = ref<Record<number, boolean>>({})
const notice = ref('')

function findCar(carId: number) {
  return cars.value.find(c => Number(c.id) === Number(carId)) ?? null
}

function fullCarName(carId: number): string {
  const car = findCar(carId)
  return car ? `${car.brand} ${car.name}` : `Car #${carId}`
}

function thumbFor(carId: number): string | null {
  const car = findCar(carId)
  return car ? carImageUrl(car) : null
}

function paidCentavos(id: number): number {
  return Math.max(0, paymentDetails.value[id]?.paid_centavos ?? 0)
}

function remainingCentavos(id: number): number {
  return Math.max(0, paymentDetails.value[id]?.remaining_centavos ?? 0)
}

function isFullyPaid(id: number): boolean {
  const summary = paymentDetails.value[id]

  return Boolean(
    summary &&
    summary.payment_status === 'fully_paid' &&
    summary.remaining_centavos <= 0,
  )
}

function paymentLabel(id: number): string {
  if (paymentErrors.value[id]) return 'Unable to verify'

  const summary = paymentDetails.value[id]

  if (!summary) return 'Checking...'
  if (isFullyPaid(id)) return 'Fully Paid'

  if (summary.payment_status === 'downpayment_paid') {
    return 'Downpayment Paid'
  }

  if (summary.payment_status === 'partially_paid') {
    return 'Partially Paid'
  }

  if (summary.has_pending_checkout) {
    return 'Payment Pending'
  }

  return 'Unpaid'
}

async function refreshPayments() {
  const results = await Promise.all(
    mine.value.map(async booking => ({
      bookingId: booking.id,
      result: await checkPaymentStatus(booking.id),
    })),
  )

  for (const item of results) {
    if (item.result.error) {
      paymentErrors.value[item.bookingId] = item.result.error
      delete paymentDetails.value[item.bookingId]
    } else {
      paymentDetails.value[item.bookingId] = item.result
      delete paymentErrors.value[item.bookingId]
    }
  }

  const failed = results.filter(item => item.result.error)

  if (failed.length > 0) {
    notice.value =
      `Could not verify payment for ${failed.length} booking(s).`
  }
}

async function refreshAccount() {
  if (loadingPayments.value) return

  loadingPayments.value = true
  notice.value = ''

  try {
    const error = await loadMyBookings()

    if (error) {
      notice.value = error
      return
    }

    await refreshPayments()
  } catch (error) {
    notice.value =
      error instanceof Error
        ? error.message
        : 'Unable to refresh account.'
  } finally {
    loadingPayments.value = false
  }
}

async function handlePay(id: number) {
  if (paying.value[id]) return

  paying.value[id] = true
  notice.value = ''

  try {
    const summary = await checkPaymentStatus(id)

    if (summary.error) {
      notice.value = summary.error
      return
    }

    paymentDetails.value[id] = summary
    delete paymentErrors.value[id]

    if (
      summary.payment_status === 'fully_paid' &&
      summary.remaining_centavos <= 0
    ) {
      notice.value = 'This booking is already fully paid.'
      return
    }

    const result = await payNow(id)

    if (result?.error) {
      notice.value = result.error
      return
    }

    if (result?.checkoutUrl) {
      window.location.assign(result.checkoutUrl)
      return
    }

    notice.value = 'PayMongo did not return a checkout link. Please try again without creating another booking.'
  } catch (error) {
    notice.value =
      error instanceof Error
        ? error.message
        : 'Unable to start payment.'
  } finally {
    paying.value[id] = false
  }
}

onMounted(() => {
  void refreshAccount()
})
</script>

<template>
  <AppShell
    shell="client"
    nav-key="home"
    title="My Account"
    :subtitle="subtitle"
  >
    <section class="premium-dashboard-hero premium-client-hero">
      <div><span class="premium-overline">CAREASE CLIENT PORTAL</span>
        <h2>Welcome to your next journey.</h2>
        <p>Manage your rentals, review payments, and discover your next ride.</p>
        <router-link class="premium-hero-button" :to="{ path: '/', hash: '#fleet' }">Explore Our Fleet <span aria-hidden="true">→</span></router-link>
      </div>
      <div class="premium-hero-mark" aria-hidden="true"><AppIcon name="car" /></div>
    </section>
    <div class="premium-section-heading"><div><span class="premium-overline">YOUR OVERVIEW</span><h2>Account at a glance</h2></div></div>
    <div class="stats">
      <div>
        <strong>{{ mine.length }}</strong>
        <span>Total bookings</span>
      </div>

      <div>
        <strong>{{ active.length }}</strong>
        <span>Active rentals</span>
      </div>

      <div>
        <strong>{{ availableCars.length }}</strong>
        <span>Cars available</span>
      </div>

      <div>
        <strong>{{ pesoShort(totalBooked) }}</strong>
        <span>Total booked</span>
      </div>
    </div>

    <p v-if="notice" class="account-notice" role="alert">
      {{ notice }}
    </p>

    <section class="panel">
      <div class="heading account-heading">
        <h2>Active &amp; Upcoming</h2>

        <div class="account-actions">
          <button
            class="button small"
            type="button"
            :disabled="loadingPayments"
            @click="refreshAccount"
          >
            {{ loadingPayments ? 'Checking...' : 'Refresh Payments' }}
          </button>

          <router-link
            class="button small"
            :to="{ path: '/', hash: '#fleet' }"
          >
            Browse Cars
          </router-link>
        </div>
      </div>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Car</th>
              <th>Pickup</th>
              <th>Return</th>
              <th>Total</th>
              <th>Booking Status</th>
              <th>Payment Status</th>
              <th>Amount Paid</th>
              <th>Balance</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="booking in active" :key="booking.id">
              <td>{{ booking.id }}</td>

              <td>
                <div class="thumb-cell">
                  <img
                    v-if="thumbFor(booking.car_id)"
                    class="thumb"
                    :src="thumbFor(booking.car_id)!"
                    :alt="fullCarName(booking.car_id)"
                  />

                  <span v-else class="thumb thumb--empty">
                    <AppIcon name="car" />
                  </span>

                  <span>{{ fullCarName(booking.car_id) }}</span>
                </div>
              </td>

              <td>{{ booking.pickup_date }}</td>
              <td>{{ booking.return_date }}</td>

              <td>{{ peso(booking.total_amount, 2) }}</td>

              <td>
                <span class="pill">{{ booking.status }}</span>
              </td>

              <td>
                <span
                  class="pill"
                  :class="{
                    'payment-paid': isFullyPaid(booking.id),
                    'payment-error': Boolean(paymentErrors[booking.id]),
                  }"
                >
                  {{ paymentLabel(booking.id) }}
                </span>
              </td>

              <td>
                {{
                  paymentErrors[booking.id]
                    ? '—'
                    : paymentDetails[booking.id]
                      ? peso(paidCentavos(booking.id) / 100, 2)
                      : 'Checking...'
                }}
              </td>

              <td>
                {{
                  paymentErrors[booking.id]
                    ? '—'
                    : paymentDetails[booking.id]
                      ? peso(remainingCentavos(booking.id) / 100, 2)
                      : 'Checking...'
                }}
              </td>

              <td>
                <span
                  v-if="isFullyPaid(booking.id)"
                  class="paid-text"
                >
                  Fully Paid
                </span>

                <button
                  v-else-if="
                    ['pending', 'approved', 'confirmed'].includes(
                      booking.status
                    ) &&
                    paymentDetails[booking.id] &&
                    !paymentErrors[booking.id]
                  "
                  class="button small"
                  type="button"
                  :disabled="
                    Boolean(paying[booking.id]) || loadingPayments
                  "
                  @click="handlePay(booking.id)"
                >
                  {{
                    paying[booking.id]
                      ? 'Processing...'
                      : paidCentavos(booking.id) > 0
                        ? 'Pay Balance'
                        : 'Pay Now'
                  }}
                </button>

                <span
                  v-else-if="paymentErrors[booking.id]"
                  class="account-muted"
                >
                  Verify payment first
                </span>

                <span
                  v-else-if="
                    ['ongoing', 'overdue'].includes(booking.status)
                  "
                  class="account-muted"
                >
                  Rental in progress
                </span>

                <span v-else class="account-muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="!active.length" class="empty">
        You have no active rentals.
        <router-link :to="{ path: '/', hash: '#fleet' }">
          Find a car
        </router-link>
        to get started.
      </p>
    </section>

    <section class="panel">
      <h2>Booking History</h2>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Car</th>
              <th>Pickup</th>
              <th>Return</th>
              <th>Total</th>
              <th>Booking Status</th>
              <th>Payment Status</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="booking in past" :key="booking.id">
              <td>{{ booking.id }}</td>
              <td>{{ fullCarName(booking.car_id) }}</td>
              <td>{{ booking.pickup_date }}</td>
              <td>{{ booking.return_date }}</td>
              <td>{{ peso(booking.total_amount, 2) }}</td>

              <td>
                <span class="pill">{{ booking.status }}</span>
              </td>

              <td>
                <span
                  class="pill"
                  :class="{
                    'payment-paid': isFullyPaid(booking.id),
                  }"
                >
                  {{ paymentLabel(booking.id) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="!past.length" class="empty">
        No completed bookings yet.
      </p>
    </section>
  </AppShell>
</template>

<style scoped>
.account-heading {
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.account-heading h2 {
  margin: 0;
}

.account-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.account-notice {
  padding: 12px 16px;
  margin-bottom: 16px;
  border: 1px solid #f0c3a2;
  border-radius: 10px;
  background: #fff3e9;
  color: #9a481a;
}

.payment-paid {
  background: #dcfce7;
  color: #166534;
}

.payment-error {
  background: #fee2e2;
  color: #991b1b;
}

.paid-text {
  color: #16a34a;
  font-weight: 700;
  white-space: nowrap;
}

.account-muted {
  color: var(--muted, #94a3b8);
  font-size: 13px;
}

.scroll {
  overflow-x: auto;
}

table {
  min-width: 1000px;
}

@media (max-width: 700px) {
  .account-heading {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
