
<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import {
  addCar,
  bookingById,
  bookings,
  cars,
  deleteCar,
  loadAdminBookings,
  maintenanceForCar,
  updateBookingStatus,
  updateCar,
} from '@/stores/carease'
import type { BookingStatus, Car, CarStatus } from '@/data/types'
import { carImageUrl } from '@/lib/carease'
import { peso } from '@/lib/format'
import { api } from '@/services/api'
import type { ValidIdRecord } from '@/services/api'
import AppIcon from '@/components/AppIcon.vue'
import AppShell from '@/components/AppShell.vue'

const props = withDefaults(defineProps<{ mode?: 'dashboard' | 'fleet' | 'bookings' | 'valid-id' }>(), { mode: 'dashboard' })

const pageTitle = computed(() => ({ dashboard: 'Admin Dashboard', fleet: 'Fleet Management', bookings: 'Booking Management', 'valid-id': 'Valid ID Management' })[props.mode])
const pageSubtitle = computed(() => ({ dashboard: 'Fleet, bookings and verification at a glance', fleet: 'Add, update and manage rental vehicles', bookings: 'Review and manage customer reservations', 'valid-id': 'Review and verify customer identification' })[props.mode])

const gcalUrl =
  'https://calendar.google.com/calendar/embed?src=' +
  encodeURIComponent(
    '1a6d16e669c118eb7b23d449d5910d4b5a9bf4cbb6b3e1469c86a41769d5ee04@group.calendar.google.com',
  ) +
  '&mode=MONTH&showTitle=0&showPrint=0&showCalendars=0&wkst=1&bgcolor=%23ffffff&timezone=Asia%2FManila'

const validIds = ref<ValidIdRecord[]>([])
const loadingValidIds = ref(false)
const verifyingId = ref<number | null>(null)
const rejectionReasons = reactive<Record<number, string>>({})

async function refreshValidIds() {
  if (loadingValidIds.value) return
  loadingValidIds.value = true
  try {
    const result = await api('/admin/valid-ids')
    validIds.value = result.valid_ids ?? []
  } catch (error) {
    showNotice(error instanceof Error ? error.message : 'Unable to load IDs.', true)
  } finally {
    loadingValidIds.value = false
  }
}

async function reviewValidId(record: ValidIdRecord, action: 'approve' | 'reject') {
  if (record.id_status === 'verified' || verifyingId.value !== null) return
  const notes = (rejectionReasons[record.id] ?? '').trim()
  if (action === 'reject' && !notes) {
    showNotice('Enter a rejection reason first.', true)
    return
  }
  verifyingId.value = record.id
  try {
    const result = await api(`/admin/valid-ids/${record.id}/verify`, {
      action,
      id_notes: action === 'reject' ? notes : '',
    })
    showNotice(result.message ?? 'ID status updated.')
    await refreshValidIds()
  } catch (error) {
    showNotice(error instanceof Error ? error.message : 'Unable to update ID.', true)
  } finally {
    verifyingId.value = null
  }
}

const notice = ref('')
const noticeIsError = ref(false)
const loadingBookings = ref(false)

const carId = ref('')
const brand = ref('')
const name = ref('')
const price = ref('')
const seats = ref('')
const status = ref<CarStatus>('available')
const imageData = ref<string | null>(null)
const existingPhoto = ref<string | null>(null)
const removeImage = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const statusDrafts = reactive<Record<number, BookingStatus>>({})
const updatingBookings = reactive<Record<number, boolean>>({})

const pendingCount = computed(() =>
  bookings.value.filter(b => b.status === 'pending').length,
)

// Status choices depend on the booking's current status.
function statusChoices(currentStatus: BookingStatus): BookingStatus[] {
  switch (currentStatus) {
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

    case 'completed':
    case 'rejected':
    case 'cancelled':
      return [currentStatus]

    default:
      return [currentStatus]
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
  { immediate: true, deep: true },
)

const preview = computed(
  () => imageData.value ?? existingPhoto.value,
)

function showNotice(message: string, isError = false) {
  notice.value = message
  noticeIsError.value = isError
}

// LOAD BOOKINGS FROM MYSQL

async function refreshBookings() {
  if (loadingBookings.value) return

  loadingBookings.value = true

  try {
    const error = await loadAdminBookings()

    if (error) {
      showNotice(error, true)
      return
    }

    for (const booking of bookings.value) {
      statusDrafts[booking.id] = booking.status
    }
  } catch (error: unknown) {
    showNotice(
      error instanceof Error
        ? error.message
        : 'Unable to load bookings.',
      true,
    )
  } finally {
    loadingBookings.value = false
  }
}

let bookingRefreshTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  void refreshValidIds()
  void refreshBookings()
  bookingRefreshTimer = setInterval(() => {
    if (!document.hidden && !loadingBookings.value) {
      void refreshBookings()
    }
  }, 10000)
})

onUnmounted(() => {
  if (bookingRefreshTimer !== null) {
    clearInterval(bookingRefreshTimer)
    bookingRefreshTimer = null
  }
})

function thumbFor(car: Car): string | null {
  return carImageUrl(car)
}

// Payment totals are returned by CarEaseApi::adminBookings().
// They are in centavos and are verified by the backend against PayMongo.
type AdminPaymentBooking = {
  payment_status?: string
  paid_centavos?: number | string
  remaining_centavos?: number | string
  downpayment_centavos?: number | string
  has_pending_checkout?: boolean
  return_request_status?: string | null
}

function paymentDetails(booking: (typeof bookings.value)[number]) {
  const details = booking as typeof booking & AdminPaymentBooking
  const total = Math.round(Number(booking.total_amount) * 100)
  const paid = Math.max(0, Number(details.paid_centavos ?? 0))
  const remaining = Math.max(0, Number(details.remaining_centavos ?? total))
  const status = details.payment_status ?? 'unpaid'

  let label = 'Unpaid'
  if (status === 'fully_paid') label = 'Fully Paid'
  else if (status === 'downpayment_paid') label = '30% Paid'
  else if (status === 'partially_paid') label = 'Partially Paid'
  else if (details.has_pending_checkout) label = 'Awaiting Payment'

  return { label, paid: paid / 100, remaining: remaining / 100 }
}


function returnRequestStatus(booking: (typeof bookings.value)[number]): string | null {
  return (booking as typeof booking & AdminPaymentBooking).return_request_status ?? null
}


// CAR MANAGEMENT

function editCar(car: Car) {
  carId.value = String(car.id)
  brand.value = car.brand
  name.value = car.name
  price.value = String(Number(car.price_per_day))
  seats.value = String(car.seats)
  status.value = car.status
  imageData.value = null
  existingPhoto.value = thumbFor(car)
  removeImage.value = false
  notice.value = ''

  document.getElementById('fleet')?.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  })
}

function resetForm() {
  carId.value = ''
  brand.value = ''
  name.value = ''
  price.value = ''
  seats.value = ''
  status.value = 'available'
  imageData.value = null
  existingPhoto.value = null
  removeImage.value = false

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handleFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  if (file.size > 4 * 1024 * 1024) {
    showNotice('The photo must be 4 MB or smaller.', true)
    input.value = ''
    return
  }

  const reader = new FileReader()

  reader.onload = event => {
    imageData.value = String(event.target?.result ?? '')
  }

  reader.readAsDataURL(file)
}

function saveCar() {
  notice.value = ''

  const id = Number(carId.value || 0)
  const seatsNumber = Number(seats.value)
  const priceNumber = Number(price.value)

  if (
    name.value.trim() === '' ||
    brand.value.trim() === '' ||
    !Number.isFinite(priceNumber) ||
    priceNumber <= 0 ||
    !Number.isFinite(seatsNumber) ||
    seatsNumber < 1 ||
    seatsNumber > 30 ||
    !['available', 'maintenance'].includes(status.value)
  ) {
    showNotice('Invalid car details.', true)
    return
  }

  if (status.value === 'available') {
    const blocking = maintenanceForCar(id).some(
      m => ['scheduled', 'in_progress'].includes(m.status),
    )

    if (blocking) {
      showNotice(
        'Cannot mark this car available while maintenance is ongoing or scheduled.',
        true,
      )
      return
    }
  }

  const data = {
    brand: brand.value.trim(),
    name: name.value.trim(),
    price_per_day: priceNumber.toFixed(2),
    seats: seatsNumber,
    status: status.value,
  }

  let savedId = id

  if (id > 0) {
    updateCar(id, data)
  } else {
    savedId = addCar({
      ...data,
      image_path: null,
    })
  }

  if (removeImage.value) {
    updateCar(savedId, {
      image_path: null,
    })
  } else if (imageData.value) {
    updateCar(savedId, {
      image_path: imageData.value,
    })
  }

  resetForm()
  showNotice('Car saved successfully.')
}

function removeCar(car: Car) {
  if (!window.confirm('Are you sure you want to delete this car?')) {
    return
  }

  const error = deleteCar(car.id)

  showNotice(
    error ?? 'Car deleted successfully.',
    Boolean(error),
  )
}

// BOOKING APPROVAL / STATUS UPDATE

async function applyStatus(
  bookingId: number,
  selectedStatus?: BookingStatus,
) {
  if (updatingBookings[bookingId]) return

  const booking = bookingById(bookingId)

  if (!booking) {
    showNotice('Booking not found.', true)
    return
  }

  const next = selectedStatus ?? statusDrafts[bookingId]

  if (!next) {
    showNotice('Please select a booking status.', true)
    return
  }

  if (booking.status === next) {
    showNotice('This booking already has that status.', true)
    return
  }

  // Prevent changing to a status not allowed by the current state.
  if (!statusChoices(booking.status).includes(next)) {
    showNotice('This status change is not allowed.', true)
    statusDrafts[bookingId] = booking.status
    return
  }

  if (next === 'rejected') {
    const confirmed = window.confirm(
      `Reject booking #${bookingId}?`,
    )

    if (!confirmed) return
  }

  notice.value = ''
  updatingBookings[bookingId] = true

  try {
    const error = await updateBookingStatus(bookingId, next)

    if (error) {
      showNotice(error, true)
      statusDrafts[bookingId] = booking.status
      return
    }

    const refreshError = await loadAdminBookings()

    if (refreshError) {
      showNotice(
        `Status update submitted, but refreshing failed: ${refreshError}`,
        true,
      )
      return
    }

    const updated = bookingById(bookingId)

    if (!updated || updated.status !== next) {
      showNotice(
        'The database did not confirm the requested booking status.',
        true,
      )
      return
    }

    statusDrafts[bookingId] = updated.status

    showNotice(
      `Booking #${bookingId} status updated to ${updated.status}.`,
    )
  } catch (error: unknown) {
    showNotice(
      error instanceof Error
        ? error.message
        : 'Failed to update booking status.',
      true,
    )

    const current = bookingById(bookingId)

    if (current) {
      statusDrafts[bookingId] = current.status
    }
  } finally {
    updatingBookings[bookingId] = false
  }
}

function inspectionAvailable(
  bookingStatus: BookingStatus,
): boolean {
  return [
    'approved',
    'confirmed',
    'ongoing',
    'overdue',
    'completed',
  ].includes(bookingStatus)
}
</script>

<template>
  <AppShell
    shell="admin"
    :nav-key="props.mode"
    :title="pageTitle"
    :subtitle="pageSubtitle"
  >
    <template v-if="props.mode === 'dashboard'">
    <section class="premium-dashboard-hero premium-admin-hero">
      <div><span class="premium-overline">CAREASE ADMIN CONSOLE</span>
        <h2>Welcome to your workspace.</h2>
        <p>Bookings, ID approvals, and fleet activity at a glance.</p>
      </div>
      <div class="admin-hero-aside"><span>CAR RENTAL OPERATIONS</span><strong>One place.<br />Every rental.</strong></div>
    </section>
    <div class="premium-section-heading admin-overview-title">
      <div><span class="premium-overline">OPERATIONS OVERVIEW</span><h2>Dashboard overview</h2></div>
      <span class="admin-live-label"><span aria-hidden="true">●</span> Live fleet &amp; bookings</span>
    </div>

    <!-- Compact dashboard: real store data, no duplicated navigation -->
    <div class="stats admin-compact-stats">
      <div><span class="admin-stat-icon"><AppIcon name="car" /></span><strong>{{ cars.length }}</strong><span>Fleet cars</span></div>
      <div><span class="admin-stat-icon"><AppIcon name="list" /></span><strong>{{ bookings.length }}</strong><span>Total bookings</span></div>
      <div><span class="admin-stat-icon"><AppIcon name="calendar" /></span><strong>{{ pendingCount }}</strong><span>Pending bookings</span></div>
      <div><span class="admin-stat-icon"><AppIcon name="file" /></span><strong>{{ validIds.filter(id => id.id_status === 'pending').length }}</strong><span>IDs to review</span></div>
    </div>

    <div class="admin-overview-grid">
      <section class="panel admin-overview-bookings" aria-labelledby="admin-recent-heading">
        <div class="admin-overview-head">
          <div><span class="premium-overline">LATEST ACTIVITY</span><h2 id="admin-recent-heading">Recent bookings</h2><p>Quick overview of your latest rental requests.</p></div>
          <router-link to="/admin/bookings" class="admin-overview-link" aria-label="Go to Booking Management">Manage bookings <span aria-hidden="true">→</span></router-link>
        </div>
        <div class="scroll">
          <table><thead><tr><th>Booking</th><th>Customer</th><th>Pickup</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="booking in bookings.slice().sort((a, b) => Number(b.id) - Number(a.id)).slice(0, 5)" :key="booking.id">
              <td><strong>#{{ booking.id }}</strong></td><td>{{ booking.customer_name }}</td><td>{{ booking.pickup_date }}</td>
              <td><span class="admin-status-tag" :class="'admin-status-' + booking.status">{{ booking.status }}</span></td>
            </tr>
            <tr v-if="!bookings.length"><td colspan="4">No bookings to display yet.</td></tr>
          </tbody></table>
        </div>
      </section>
      <section class="panel admin-overview-ids" aria-labelledby="admin-id-heading">
        <div class="admin-overview-head">
          <div><span class="premium-overline">CUSTOMER SECURITY</span><h2 id="admin-id-heading">ID approvals</h2><p>Verification requests needing attention.</p></div>
          <router-link to="/admin/valid-ids" class="admin-overview-link" aria-label="Go to Valid ID Verification">Review IDs <span aria-hidden="true">→</span></router-link>
        </div>
        <div class="admin-id-queue">
          <div v-for="record in validIds.filter(id => id.id_status === 'pending').slice(0, 4)" :key="record.id" class="admin-id-queue-row">
            <span class="admin-queue-avatar">{{ (record.full_name || '?').slice(0, 1).toUpperCase() }}</span>
            <span><strong>{{ record.full_name }}</strong><small>{{ record.id_type || 'Valid ID' }}</small></span>
            <span class="admin-status-tag admin-status-pending">Pending</span>
          </div>
          <p v-if="loadingValidIds">Loading ID requests...</p>
          <p v-else-if="!validIds.some(id => id.id_status === 'pending')" class="admin-empty-queue">No pending ID approvals.</p>
        </div>
      </section>
    </div>

    <section class="panel admin-calendar-preview" aria-labelledby="admin-calendar-heading">
      <div class="admin-overview-head">
        <div><span class="premium-overline">RENTAL SCHEDULE</span><h2 id="admin-calendar-heading">Calendar preview</h2><p>Synced bookings at a glance. Open Calendar for the full month and sync controls.</p></div>
        <router-link class="button ghost small" to="/calendar">Open full calendar →</router-link>
      </div>
      <iframe class="gcal-frame admin-calendar-iframe" :src="gcalUrl" title="Rental calendar preview" loading="lazy" referrerpolicy="no-referrer"></iframe>
    </section>

    </template>

    <p
      v-if="notice"
      :class="noticeIsError ? 'error' : 'pill'"
      role="alert"
    >
      {{ notice }}
    </p>


    <section v-if="props.mode !== 'dashboard'" class="premium-subpage-hero">
      <div><span class="premium-overline">CAREASE ADMIN CONSOLE</span><h2>{{ pageTitle }}</h2><p>{{ pageSubtitle }}</p></div>
      <router-link class="premium-subpage-back" to="/admin">← Dashboard</router-link>
    </section>

    <!-- ADD / EDIT CAR -->

    <template v-if="props.mode === 'fleet'">
    <section class="panel" id="fleet">
      <h2>Add / Edit Car</h2>

      <form @submit.prevent="saveCar">
        <div class="two">
          <label>
            Car ID (leave empty to add)
            <input
              v-model="carId"
              type="number"
              min="1"
              name="id"
              id="car-id"
              placeholder="Existing ID for edit"
            />
          </label>

          <label>
            Brand
            <input
              v-model="brand"
              name="brand"
              id="car-brand"
              required
              maxlength="80"
              placeholder="Toyota"
            />
          </label>

          <label>
            Model / car name
            <input
              v-model="name"
              name="name"
              id="car-name"
              required
              maxlength="120"
              placeholder="Vios"
            />
          </label>

          <label>
            Daily rate (₱)
            <input
              v-model="price"
              type="number"
              min="1"
              step="0.01"
              name="price_per_day"
              id="car-price"
              required
            />
          </label>

          <label>
            Seats
            <input
              v-model="seats"
              type="number"
              min="1"
              max="30"
              name="seats"
              id="car-seats"
              required
            />
          </label>

          <label>
            Status
            <select
              v-model="status"
              name="status"
              id="car-status"
            >
              <option value="available">Available</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </label>
        </div>

        <!-- CAR PHOTO -->

        <div class="upload-field">
          <img
            v-if="preview"
            class="upload-preview"
            :src="preview"
            alt="Car photo"
          />

          <span
            v-else
            class="upload-preview upload-preview--empty"
          >
            <AppIcon name="image" />
          </span>

          <div>
            <label class="upload-control">
              Car photo

              <input
                ref="fileInput"
                type="file"
                name="car_image"
                accept="image/jpeg,image/png,image/webp,image/gif"
                @change="handleFile"
              />

              <span class="upload-hint">
                JPG, PNG, WEBP or GIF · max 4 MB.
                Leave empty to keep the current photo.
              </span>
            </label>

            <label class="upload-hint">
              <input
                v-model="removeImage"
                type="checkbox"
                name="remove_image"
                value="1"
                id="car-remove-image"
              />
              Remove current photo
            </label>
          </div>
        </div>

        <div class="button-row">
          <button class="button" type="submit">
            Save Car
          </button>

          <button
            class="button ghost"
            type="button"
            @click="resetForm"
          >
            Clear form / Add new
          </button>
        </div>
      </form>
    </section>

    <!-- FLEET MANAGEMENT -->

    <section class="panel">
      <h2>Fleet Management</h2>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Car</th>
              <th>Photo</th>
              <th>Price</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="car in cars"
              :key="car.id"
            >
              <td>{{ car.id }}</td>

              <td>
                {{ car.brand }} {{ car.name }}
              </td>

              <td>
                <div class="thumb-cell">
                  <img
                    v-if="thumbFor(car)"
                    class="thumb"
                    :src="thumbFor(car)!"
                    :alt="`${car.brand} ${car.name}`"
                  />

                  <span
                    v-else
                    class="thumb thumb--empty"
                  >
                    <AppIcon name="image" />
                  </span>

                  <span class="upload-hint">
                    {{ thumbFor(car) ? 'Uploaded' : 'No photo' }}
                  </span>
                </div>
              </td>

              <td>{{ peso(car.price_per_day, 2) }}</td>
              <td>{{ car.status }}</td>

              <td>
                <div class="row-actions">
                  <button
                    type="button"
                    class="button small ghost"
                    @click="editCar(car)"
                  >
                    Edit
                  </button>

                  <form @submit.prevent="removeCar(car)">
                    <button type="submit">
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <tr v-if="cars.length === 0">
              <td colspan="6">
                No cars available.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    </template>

    <!-- VALID ID VERIFICATION -->
    <section v-if="props.mode === 'valid-id'" class="panel" id="valid-ids">
      <div class="calendar-panel-head">
        <div>
          <h2>Valid ID Verification</h2>
          <p>Review customer ID submissions before they can book a car.</p>
        </div>
        <button type="button" class="button small ghost" :disabled="loadingValidIds" @click="refreshValidIds">
          {{ loadingValidIds ? 'Loading...' : 'Refresh IDs' }}
        </button>
      </div>
      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>Client</th><th>ID Type / Number</th><th>Status</th>
              <th>Photo</th><th>Notes</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in validIds" :key="record.id">
              <td>{{ record.full_name }}<small>{{ record.email }}</small></td>
              <td>{{ record.id_type }}<small>{{ record.id_number }}</small></td>
              <td><span class="pill">{{ record.id_status }}</span></td>
              <td>
                <a v-if="record.has_photo" :href="`http://127.0.0.1:3000/index.php/carease/id/image/${record.id}`" target="_blank" rel="noopener noreferrer">View ID</a>
                <span v-else>—</span>
              </td>
              <td>{{ record.id_notes || '—' }}</td>
              <td>
                <span v-if="record.id_status === 'verified'" class="pill">ID Approved</span>
                <div v-else class="id-review-actions">
                  <button type="button" class="button small" :disabled="verifyingId !== null || !record.has_photo" @click="reviewValidId(record, 'approve')">
                    {{ verifyingId === record.id ? 'Saving...' : 'Approve ID' }}
                  </button>
                  <input v-model="rejectionReasons[record.id]" maxlength="255" placeholder="Rejection reason" aria-label="Rejection reason" />
                  <button type="button" class="button small ghost" :disabled="verifyingId !== null" @click="reviewValidId(record, 'reject')">Reject</button>
                </div>
              </td>
            </tr>
            <tr v-if="validIds.length === 0">
              <td colspan="6">{{ loadingValidIds ? 'Loading submitted IDs...' : 'No submitted IDs yet.' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- BOOKING MANAGEMENT -->

    <section v-if="props.mode === 'bookings'" class="panel" id="bookings">
      <div class="calendar-panel-head">
        <div>
          <h2>Booking Management</h2>
          <p>
            Review pending requests and update their booking status.
          </p>
        </div>

        <button
          type="button"
          class="button small ghost"
          :disabled="loadingBookings"
          @click="refreshBookings"
        >
          {{ loadingBookings ? 'Loading...' : 'Refresh Bookings' }}
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
<th>Return Request</th>
<th>Action</th>
<th>Inspection</th>
</tr>
          </thead>

          <tbody>
            <tr
              v-for="booking in bookings"
              :key="booking.id"
            >
              <td>{{ booking.id }}</td>

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
                <span class="pill">{{ paymentDetails(booking).label }}</span>
                <small>Paid: {{ peso(paymentDetails(booking).paid, 2) }}</small>
                <small>Balance: {{ peso(paymentDetails(booking).remaining, 2) }}</small>
              </td>

             

<td>
  <span v-if="returnRequestStatus(booking) === 'pending'" class="pill">
    Pending Return
  </span>
  <span v-else-if="returnRequestStatus(booking)" class="pill">
    {{ returnRequestStatus(booking) }}
  </span>
  <span v-else>—</span>
</td>


              <td>
                <!-- FINAL STATUSES -->
                <template
                  v-if="
                    booking.status === 'completed' ||
                    booking.status === 'cancelled' ||
                    booking.status === 'rejected'
                  "
                >
                  {{
                    booking.status.charAt(0).toUpperCase() +
                    booking.status.slice(1)
                  }}
                </template>

                <div v-else class="row-actions">
                  <!-- PENDING: APPROVE OR REJECT -->
                  <template v-if="booking.status === 'pending'">
                    <button
                      type="button"
                      class="button small"
                      :disabled="Boolean(updatingBookings[booking.id])"
                      @click="applyStatus(booking.id, 'approved')"
                    >
                      {{
                        updatingBookings[booking.id]
                          ? 'Updating...'
                          : 'Approve'
                      }}
                    </button>

                    <button
                      type="button"
                      class="button small ghost"
                      :disabled="Boolean(updatingBookings[booking.id])"
                      @click="applyStatus(booking.id, 'rejected')"
                    >
                      Reject
                    </button>
                  </template>

                  <!-- OTHER ACTIVE STATUSES -->
                  <form
                    v-else
                    @submit.prevent="applyStatus(booking.id)"
                  >
                    <select
                      v-model="statusDrafts[booking.id]"
                      name="status"
                      :disabled="Boolean(updatingBookings[booking.id])"
                    >
                      <option
                        v-for="choice in statusChoices(booking.status)"
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
                      :disabled="Boolean(updatingBookings[booking.id])"
                    >
                      {{
                        updatingBookings[booking.id]
                          ? 'Updating...'
                          : 'Update'
                      }}
                    </button>
                  </form>
                </div>
              </td>

              <td>
                <router-link
                  v-if="inspectionAvailable(booking.status)"
                  class="button small"
                  :to="`/admin/inspection/${booking.id}`"
                >
                  Inspect Vehicle
                </router-link>

                <span v-else>
                  Not available
                </span>
              </td>
            </tr>

            <tr v-if="bookings.length === 0">
             <td colspan="10">
                No booking requests found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.id-review-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.id-review-actions input { min-width: 160px; max-width: 260px; padding: 9px 12px; border: 1px solid #d5dce6; border-radius: 9px; background: var(--surface, #fff); color: inherit; }

/* Keep card headings and their navigation aligned at every screen size. */
.admin-overview-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  align-items: stretch;
}
.admin-overview-grid > .panel { min-width: 0; }
.admin-overview-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px 20px;
  margin-bottom: 20px;
}
.admin-overview-head > div:first-child { flex: 1 1 220px; min-width: 0; }
.admin-overview-head h2 { margin: 5px 0 4px; line-height: 1.25; }
.admin-overview-head p { margin: 0; line-height: 1.5; }
.admin-overview-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex: 0 0 auto;
  min-height: 40px;
  padding: 9px 14px;
  border: 1px solid #dfc994;
  border-radius: 10px;
  background: #fff8e8;
  color: #8a621b;
  font-size: 12px;
  font-weight: 750;
  text-decoration: none;
  line-height: 1.2;
  white-space: nowrap;
  transition: background .2s, transform .2s, border-color .2s;
}
.admin-overview-link:hover { background: #f7e6bc; border-color: #c49b45; transform: translateY(-1px); }
.admin-overview-link:focus-visible { outline: 3px solid #c49b45; outline-offset: 3px; }
.admin-overview-link span { font-size: 16px; line-height: 1; }
#bookings, #valid-ids { scroll-margin-top: 105px; }
@media (max-width: 1150px) {
  .admin-overview-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .admin-overview-head { align-items: flex-start; }
  .admin-overview-head > div:first-child { flex-basis: 100%; }
  .admin-overview-link { width: 100%; justify-content: space-between; }
}
</style>

<style>
.premium-subpage-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;padding:28px 32px;margin-bottom:25px;border-radius:19px;background:linear-gradient(120deg,#091d34,#1b4262);color:#fff;box-shadow:0 14px 35px #0a284329}.premium-subpage-hero h2{font-size:clamp(23px,2.5vw,33px);margin:8px 0;color:white;letter-spacing:-.045em}.premium-subpage-hero p{margin:0;color:#c5d7e5}.premium-subpage-back{padding:11px 16px;border:1px solid #e7c47777;border-radius:10px;color:#f1d48b;text-decoration:none;font-weight:700}.premium-subpage-back:hover{background:#ffffff15}@media(max-width:600px){.premium-subpage-hero{padding:22px}}
</style>
