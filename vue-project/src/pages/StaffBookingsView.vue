
<script setup lang="ts">
import {
  computed,
  onMounted,
  onUnmounted,
  reactive,
  ref,
  watch,
} from 'vue'
import AppShell from '@/components/AppShell.vue'
import {
  bookings,
  isStaff,
  loadAdminBookings,
  updateBookingStatus,
} from '@/stores/carease'
import type { BookingStatus } from '@/data/types'
import { peso } from '@/lib/format'

const notice = ref('')
const noticeIsError = ref(false)
const loadingBookings = ref(false)

const statusDrafts = reactive<Record<number, BookingStatus>>({})
const updatingBookings = reactive<Record<number, boolean>>({})

const pendingCount = computed(() =>
  bookings.value.filter(b => b.status === 'pending').length
)

const approvedCount = computed(() =>
  bookings.value.filter(b => b.status === 'approved').length
)

const sortedBookings = computed(() =>
  [...bookings.value].sort(
    (a, b) => Number(b.id) - Number(a.id)
  )
)

function statusChoices(
  current: BookingStatus
): BookingStatus[] {
  switch (current) {
    case 'pending':
      return ['approved', 'rejected', 'cancelled']
    case 'approved':
      return ['approved', 'confirmed', 'cancelled']
    case 'confirmed':
      return ['confirmed', 'ongoing', 'cancelled']
    case 'ongoing':
      return ['ongoing', 'completed', 'overdue']
    case 'overdue':
      return ['overdue', 'completed']
    default:
      return [current]
  }
}

watch(
  bookings,
  items => {
    for (const booking of items) {
      if (!updatingBookings[booking.id]) {
        statusDrafts[booking.id] = booking.status
      }
    }
  },
  { immediate: true, deep: true }
)

function showNotice(
  text: string,
  isError = false
) {
  notice.value = text
  noticeIsError.value = isError
}

type PaymentBooking = {
  payment_status?: string
  paid_centavos?: number | string
  remaining_centavos?: number | string
  has_pending_checkout?: boolean
}

function paymentDetails(
  booking: (typeof bookings.value)[number]
) {
  const info = booking as typeof booking & PaymentBooking

  const total = Math.round(
    Number(booking.total_amount) * 100
  )

  const paid = Math.max(
    0,
    Number(info.paid_centavos ?? 0)
  )

  const remaining = Math.max(
    0,
    Number(info.remaining_centavos ?? total)
  )

  const status = info.payment_status ?? 'unpaid'

  let label = 'Unpaid'

  if (status === 'fully_paid') {
    label = 'Fully Paid'
  } else if (status === 'downpayment_paid') {
    label = '30% Paid'
  } else if (status === 'partially_paid') {
    label = 'Partially Paid'
  } else if (info.has_pending_checkout) {
    label = 'Awaiting Payment'
  }

  return {
    label,
    paid: paid / 100,
    remaining: remaining / 100,
  }
}

async function refreshBookings() {
  if (loadingBookings.value) return

  loadingBookings.value = true

  try {
    const result = await loadAdminBookings()

    if (result) {
      showNotice(result, true)
    } else {
      for (const booking of bookings.value) {
        if (!updatingBookings[booking.id]) {
          statusDrafts[booking.id] = booking.status
        }
      }
    }
  } catch (err) {
    showNotice(
      err instanceof Error
        ? err.message
        : 'Unable to load bookings.',
      true
    )
  } finally {
    loadingBookings.value = false
  }
}

async function applyStatus(
  id: number,
  selected?: BookingStatus
) {
  if (!isStaff.value || updatingBookings[id]) return

  const booking = bookings.value.find(
    b => b.id === id
  )

  if (!booking) {
    showNotice('Booking not found.', true)
    return
  }

  const next = selected ?? statusDrafts[id]

  if (!next || next === booking.status) {
    showNotice(
      'Please select a different status.',
      true
    )
    return
  }

  if (!statusChoices(booking.status).includes(next)) {
    showNotice(
      'This status change is not allowed.',
      true
    )
    statusDrafts[id] = booking.status
    return
  }

  const confirmed = window.confirm(
    `Change booking #${id} from ${booking.status} to ${next}?`
  )

  if (!confirmed) return

  updatingBookings[id] = true
  notice.value = ''

  try {
    const error = await updateBookingStatus(id, next)

    if (error) {
      showNotice(error, true)
      statusDrafts[id] = booking.status
      return
    }

    const refreshError = await loadAdminBookings()

    if (refreshError) {
      showNotice(
        `Updated, but refresh failed: ${refreshError}`,
        true
      )
      return
    }

    const updated = bookings.value.find(
      b => b.id === id
    )

    if (!updated || updated.status !== next) {
      showNotice(
        'Database did not confirm the new status.',
        true
      )
      return
    }

    statusDrafts[id] = updated.status

    showNotice(
      `Booking #${id} updated to ${next}.`
    )
  } catch (err) {
    showNotice(
      err instanceof Error
        ? err.message
        : 'Unable to update booking.',
      true
    )
  } finally {
    updatingBookings[id] = false
  }
}

let refreshTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  void refreshBookings()

  refreshTimer = setInterval(() => {
    if (
      !document.hidden &&
      !loadingBookings.value
    ) {
      void refreshBookings()
    }
  }, 10000)
})

onUnmounted(() => {
  if (refreshTimer !== null) {
    clearInterval(refreshTimer)
  }
})
</script>

<template>
  <AppShell
    shell="staff"
    nav-key="dashboard"
    title="CarEase Staff"
    subtitle="Booking management and reports"
  >
    <section class="premium-dashboard-hero premium-staff-hero"><div><span class="premium-overline">CAREASE STAFF PORTAL</span><h2>Every rental, handled with care.</h2><p>Manage assigned bookings and keep customer journeys on track.</p></div><div class="premium-admin-shortcuts"><RouterLink to="/staff/reports">View Reports <span aria-hidden="true">→</span></RouterLink><RouterLink to="/staff/bookings#bookings">Booking Queue <span aria-hidden="true">→</span></RouterLink></div></section>
    <div class="heading">
      <h1>Staff Dashboard</h1>

      <RouterLink
        class="button ghost"
        to="/staff/reports"
      >
        Reports &amp; Charts
      </RouterLink>
    </div>

    <p
      v-if="notice"
      :class="noticeIsError ? 'error' : 'pill'"
      role="alert"
    >
      {{ notice }}
    </p>

    <div class="stats">
      <div>
        <strong>{{ bookings.length }}</strong>
        <span>Total bookings</span>
      </div>

      <div>
        <strong>{{ pendingCount }}</strong>
        <span>Pending approval</span>
      </div>

      <div>
        <strong>{{ approvedCount }}</strong>
        <span>Approved bookings</span>
      </div>
    </div>

    <section
      id="bookings"
      class="panel"
    >
      <div class="panel-head">
        <div>
          <h2>Booking Management</h2>
          <p>
            Review requests and update booking statuses.
          </p>
        </div>

        <button
          class="button small ghost"
          type="button"
          :disabled="loadingBookings"
          @click="refreshBookings"
        >
          {{
            loadingBookings
              ? 'Loading...'
              : 'Refresh Bookings'
          }}
        </button>
      </div>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Car ID</th>
              <th>Dates</th>
              <th>Total</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="booking in sortedBookings"
              :key="booking.id"
            >
              <td>#{{ booking.id }}</td>

              <td>
                {{ booking.customer_name }}
                <small>{{ booking.email }}</small>
              </td>

              <td>{{ booking.car_id }}</td>

              <td>
                {{ booking.pickup_date }}
                to
                {{ booking.return_date }}
              </td>

              <td>
                {{ peso(booking.total_amount, 2) }}
              </td>

              <td>
                <span class="pill">
                  {{ booking.status }}
                </span>
              </td>

              <td>
                <span class="pill">
                  {{ paymentDetails(booking).label }}
                </span>

                <small>
                  Paid:
                  {{
                    peso(
                      paymentDetails(booking).paid,
                      2
                    )
                  }}
                </small>

                <small>
                  Balance:
                  {{
                    peso(
                      paymentDetails(booking).remaining,
                      2
                    )
                  }}
                </small>
              </td>

              <td>
                <span
                  v-if="
                    [
                      'completed',
                      'cancelled',
                      'rejected'
                    ].includes(booking.status)
                  "
                >
                  —
                </span>

                <div
                  v-else-if="booking.status === 'pending'"
                  class="row-actions"
                >
                  <button
                    class="button small"
                    type="button"
                    :disabled="
                      Boolean(
                        updatingBookings[booking.id]
                      )
                    "
                    @click="
                      applyStatus(
                        booking.id,
                        'approved'
                      )
                    "
                  >
                    Approve
                  </button>

                  <button
                    class="button small ghost"
                    type="button"
                    :disabled="
                      Boolean(
                        updatingBookings[booking.id]
                      )
                    "
                    @click="
                      applyStatus(
                        booking.id,
                        'rejected'
                      )
                    "
                  >
                    Reject
                  </button>
                </div>

                <form
                  v-else
                  class="row-actions"
                  @submit.prevent="
                    applyStatus(booking.id)
                  "
                >
                  <select
                    v-model="
                      statusDrafts[booking.id]
                    "
                    :disabled="
                      Boolean(
                        updatingBookings[booking.id]
                      )
                    "
                  >
                    <option
                      v-for="choice in statusChoices(
                        booking.status
                      )"
                      :key="choice"
                      :value="choice"
                    >
                      {{
                        choice.charAt(0).toUpperCase() +
                        choice.slice(1)
                      }}
                    </option>
                  </select>

                  <button
                    class="button small"
                    type="submit"
                    :disabled="
                      Boolean(
                        updatingBookings[booking.id]
                      )
                    "
                  >
                    {{
                      updatingBookings[booking.id]
                        ? 'Updating...'
                        : 'Update'
                    }}
                  </button>
                </form>
              </td>
            </tr>

            <tr v-if="sortedBookings.length === 0">
              <td
                colspan="8"
                class="empty"
              >
                {{
                  loadingBookings
                    ? 'Loading bookings...'
                    : 'No booking requests found.'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <p class="hint">
      Vehicle release and completion still require
      the existing contract and inspection checks.
    </p>
  </AppShell>
</template>

<style scoped>
.heading,
.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.heading {
  margin-bottom: 24px;
}

.heading h1 {
  margin: 0;
  font-size: 24px;
}

.panel-head p,
.hint {
  color: var(--muted, #64748b);
  margin: 0;
  font-size: 14px;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 24px;
}

.stats > div,
.panel {
  background: var(--surface, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 14px;
  padding: 22px;
}

.stats strong {
  display: block;
  font-size: 30px;
  color: var(--accent, #ed7a28);
}

.stats span {
  display: block;
  margin-top: 8px;
  color: var(--muted, #64748b);
  font-size: 14px;
}

.panel h2 {
  font-size: 20px;
  margin: 0 0 7px;
}

.panel-head {
  margin-bottom: 20px;
}

.scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

th,
td {
  padding: 13px 12px;
  border-bottom: 1px solid var(--border, #e5e7eb);
  font-size: 13px;
  vertical-align: middle;
}

th {
  background: var(--surface-2, #f8fafc);
  white-space: nowrap;
}

td small {
  display: block;
  color: var(--muted, #64748b);
  margin-top: 5px;
}

.pill {
  display: inline-block;
  background: #fff0e4;
  color: #a8541e;
  padding: 7px 10px;
  border-radius: 20px;
  font-size: 12px;
}

.row-actions {
  display: flex;
  align-items: center;
  gap: 7px;
}

.button {
  display: inline-block;
  text-decoration: none;
  border: 1px solid #ed7a28;
  background: #ed7a28;
  color: #fff;
  padding: 10px 14px;
  border-radius: 8px;
  cursor: pointer;
  white-space: nowrap;
  font-weight: 600;
}

.button.small {
  padding: 8px 11px;
  font-size: 12px;
}

.button.ghost {
  background: var(--surface, #fff);
  border-color: var(--border, #e5e7eb);
  color: var(--text, #334155);
}

button:disabled,
select:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

select {
  max-width: 135px;
  padding: 8px;
  border: 1px solid #dbe0e7;
  border-radius: 8px;
  background: #fff;
  color: #334155;
}

.error {
  color: #b91c1c;
  background: #fef2f2;
  padding: 12px;
  border-radius: 8px;
}

.empty {
  text-align: center;
  color: var(--muted, #64748b);
}

.hint {
  margin-top: 16px;
}

@media (max-width: 800px) {
  .stats {
    grid-template-columns: 1fr;
  }

  .heading,
  .panel-head {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
