
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  addBooking,
  carById,
  currentUser,
  payNow,
  refreshCarsFromBackend,
} from '@/stores/carease'
import { carImageUrl, transmissionOf } from '@/lib/carease'
import { peso, todayISO } from '@/lib/format'
import { BACKEND_BASE } from '@/lib/backend'
import type { MapPlace, RouteInfo } from '@/lib/maps'
import AppIcon from '@/components/AppIcon.vue'
import AppShell from '@/components/AppShell.vue'
import LocationMap from '@/components/LocationMap.vue'

type ReservedBooking = {
  pickup_date: string
  return_date: string
}

const route = useRoute()

const carId = Number(route.params.id)
const car = computed(() => carById(carId))

const customerName = computed(
  () => currentUser.value?.full_name ?? '',
)

const email = computed(
  () => currentUser.value?.email ?? '',
)

const phone = ref('')
const pickupLocation = ref<MapPlace | null>(null)
const dropoffLocation = ref<MapPlace | null>(null)
const routeInfo = ref<RouteInfo | null>(null)
const pickupDate = ref('')
const returnDate = ref('')
const error = ref('')
const submitting = ref(false)
const loadingDates = ref(true)
const reservedBookings = ref<ReservedBooking[]>([])
const signatureCanvas = ref<HTMLCanvasElement | null>(null)
const signatureImage = ref('')
const agreementAccepted = ref(false)
const signing = ref(false)
const idStatus = ref('')
const checkingId = ref(true)

async function checkIdVerification() {
  checkingId.value = true
  idStatus.value = ''
  try {
    const response = await fetch(`${BACKEND_BASE}/carease/api/session`, { credentials: 'include' })
    if (!response.ok) throw new Error('Unable to check ID verification.')
    const data = await response.json()
    idStatus.value = data.user?.id_status ?? ''
  } catch {
    error.value = 'Unable to verify your ID status. Please refresh the page.'
  } finally {
    checkingId.value = false
  }
}

const agreementTerms = computed(() => [
  `1. VEHICLE. The renter agrees to rent ${car.value?.brand ?? ''} ${car.value?.name ?? ''} from CarEase Rentals for the period ${pickupDate.value} to ${returnDate.value}.`,
  '2. VALID ID. The renter must present a valid government-issued ID. The ID must be verified by CarEase staff before the vehicle is released.',
  `3. PAYMENT. The total rental fee is PHP ${totalAmount.value.toFixed(2)}. Payment must be settled before the release of the vehicle unless another arrangement is approved in writing.`,
  '4. USE OF VEHICLE. The vehicle must be used only for lawful purposes. Subletting, racing, and transporting illegal goods are strictly prohibited.',
  '5. RETURN. The vehicle must be returned on or before the return date and time at the agreed location, in the same condition as released, with the same fuel level.',
  '6. LATE RETURN. Late returns may incur additional charges. CarEase may send reminder and overdue notices to the renter’s registered email address.',
  '7. DAMAGE AND VIOLATIONS. The renter is responsible for traffic violations, fines, tolls, and damage caused during the rental period, subject to the inspection report.',
  '8. CANCELLATION. CarEase may cancel this agreement for invalid documents, non-payment, or violation of these terms.',
  '9. PRIVACY. The renter’s ID and contact details are used only for verification, rental records, and notifications related to this booking.',
  '10. ACCEPTANCE. By signing, the renter confirms that they have read, understood, and accepted these terms.',
])

function signaturePosition(event: PointerEvent) {
  const canvas = signatureCanvas.value!
  const rect = canvas.getBoundingClientRect()
  return {
    x: (event.clientX - rect.left) * canvas.width / rect.width,
    y: (event.clientY - rect.top) * canvas.height / rect.height,
  }
}

function beginSignature(event: PointerEvent) {
  if (event.pointerType === 'mouse' && event.button !== 0) return
  const canvas = signatureCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  canvas.setPointerCapture(event.pointerId)
  const { x, y } = signaturePosition(event)
  ctx.lineWidth = 3
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#24364a'
  ctx.beginPath()
  ctx.moveTo(x, y)
  signing.value = true
}

function drawSignature(event: PointerEvent) {
  if (!signing.value || !signatureCanvas.value) return
  const ctx = signatureCanvas.value.getContext('2d')
  if (!ctx) return
  const { x, y } = signaturePosition(event)
  ctx.lineTo(x, y)
  ctx.stroke()
}

function finishSignature() {
  if (!signing.value || !signatureCanvas.value) return
  signing.value = false
  signatureImage.value = signatureCanvas.value.toDataURL('image/png')
}

function clearSignature() {
  const canvas = signatureCanvas.value
  if (canvas) canvas.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height)
  signatureImage.value = ''
  signing.value = false
}

watch([pickupDate, returnDate], () => {
  agreementAccepted.value = false
  clearSignature()
})

const MAX_RENTAL_DAYS = 5

const title = 'Book a Car'

const subtitle = computed(() =>
  car.value
    ? `${car.value.brand} ${car.value.name}`
    : 'Vehicle not available',
)

const thumb = computed(() =>
  car.value ? carImageUrl(car.value) : null,
)

function addDays(date: string, days: number): string {
  const d = new Date(`${date}T00:00:00Z`)
  d.setUTCDate(d.getUTCDate() + days)
  return d.toISOString().slice(0, 10)
}

function dateTimestamp(date: string): number {
  return new Date(`${date}T00:00:00Z`).getTime()
}

const rentalDays = computed(() => {
  if (!pickupDate.value || !returnDate.value) return 0

  return Math.round(
    (dateTimestamp(returnDate.value) -
      dateTimestamp(pickupDate.value)) /
      86400000,
  )
})

const totalAmount = computed(() => {
  if (!car.value) return 0

  if (
    rentalDays.value < 1 ||
    rentalDays.value > MAX_RENTAL_DAYS
  ) {
    return 0
  }

  return Number(car.value.price_per_day) * rentalDays.value
})

const downpaymentAmount = computed(() => {
  const totalCentavos = Math.round(totalAmount.value * 100)
  return Math.round(totalCentavos * 0.30) / 100
})

const remainingAmount = computed(() => {
  return Math.round(
    (totalAmount.value - downpaymentAmount.value) * 100,
  ) / 100
})

function isReserved(date: string): boolean {
  return reservedBookings.value.some(
    booking =>
      booking.pickup_date <= date &&
      date < booking.return_date,
  )
}

function hasConflict(start: string, end: string): boolean {
  return reservedBookings.value.some(
    booking =>
      booking.pickup_date < end &&
      booking.return_date > start,
  )
}

const dateError = computed(() => {
  if (!pickupDate.value || !returnDate.value) return ''

  if (pickupDate.value < todayISO()) {
    return 'Pickup date cannot be in the past.'
  }

  if (rentalDays.value < 1) {
    return 'Return date must be after pickup date.'
  }

  if (rentalDays.value > MAX_RENTAL_DAYS) {
    return 'Maximum rental period is 5 days.'
  }

  if (hasConflict(pickupDate.value, returnDate.value)) {
    return 'These dates overlap an existing reservation.'
  }

  return ''
})

const currentMonth = ref(todayISO().slice(0, 7))

const monthTitle = computed(() => {
  const [year, month] = currentMonth.value
    .split('-')
    .map(Number)

  return new Date(Date.UTC(year!, month! - 1, 1))
    .toLocaleDateString('en-PH', {
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC',
    })
})

const calendarDays = computed(() => {
  const [year, month] = currentMonth.value
    .split('-')
    .map(Number)

  const firstDay = new Date(
    Date.UTC(year!, month! - 1, 1),
  ).getUTCDay()

  const daysInMonth = new Date(
    Date.UTC(year!, month!, 0),
  ).getUTCDate()

  const cells: Array<string | null> = []

  for (let i = 0; i < firstDay; i++) {
    cells.push(null)
  }

  for (let day = 1; day <= daysInMonth; day++) {
    cells.push(
      `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
    )
  }

  return cells
})

function changeMonth(offset: number) {
  const [year, month] = currentMonth.value
    .split('-')
    .map(Number)

  const next = new Date(
    Date.UTC(year!, month! - 1 + offset, 1),
  )

  currentMonth.value = next.toISOString().slice(0, 7)
}

const canGoPrevious = computed(
  () => currentMonth.value > todayISO().slice(0, 7),
)

function selectDate(date: string) {
  if (
    loadingDates.value ||
    date < todayISO() ||
    isReserved(date)
  ) {
    return
  }

  error.value = ''

  if (
    !pickupDate.value ||
    returnDate.value ||
    date <= pickupDate.value
  ) {
    pickupDate.value = date
    returnDate.value = ''
    return
  }

  if (
    date > addDays(pickupDate.value, MAX_RENTAL_DAYS)
  ) {
    error.value =
      'You can only rent for a maximum of 5 days.'
    return
  }

  if (hasConflict(pickupDate.value, date)) {
    error.value =
      'Your selected dates include a reserved day.'
    return
  }

  returnDate.value = date
}

function isSelected(date: string): boolean {
  return (
    date === pickupDate.value ||
    date === returnDate.value
  )
}

function isInRange(date: string): boolean {
  return !!(
    pickupDate.value &&
    returnDate.value &&
    date > pickupDate.value &&
    date < returnDate.value
  )
}

async function loadReservedDates() {
  loadingDates.value = true
  error.value = ''

  try {
    const response = await fetch(
      `${BACKEND_BASE}/carease/api/cars/${carId}/reserved-dates`,
      {
        credentials: 'include',
      },
    )

    const data = await response.json()

    if (
      !response.ok ||
      !Array.isArray(data.reservations)
    ) {
      throw new Error(
        data.error || 'Unable to load reserved dates.',
      )
    }

    reservedBookings.value = data.reservations
  } catch (err: unknown) {
    error.value =
      err instanceof Error
        ? err.message
        : 'Unable to load reserved dates.'
  } finally {
    loadingDates.value = false
  }
}

watch([pickupDate, returnDate], () => {
  if (dateError.value) return
  error.value = ''
})

async function handleSubmit() {
  if (
    !car.value ||
    submitting.value ||
    loadingDates.value
  ) {
    return
  }

  error.value = ''

  if (!currentUser.value) {
    error.value =
      'Please log in before renting a car.'
    return
  }

  if (checkingId.value || idStatus.value !== 'verified') {
    error.value = 'Please verify your Valid ID before booking a vehicle.'
    return
  }

  if (!/^[0-9+() -]{7,20}$/.test(phone.value.trim())) {
    error.value =
      'Please enter a valid contact number.'
    return
  }

  const pickup = pickupLocation.value
  const dropoff = dropoffLocation.value

  if (!pickup || !dropoff) {
    error.value =
      'Please select your pickup and drop-off locations on the map.'
    return
  }

  if (!pickupDate.value || !returnDate.value) {
    error.value =
      'Please select pickup and return dates.'
    return
  }

  if (dateError.value) {
    error.value = dateError.value
    return
  }

  if (totalAmount.value <= 0) {
    error.value = 'Invalid rental amount.'
    return
  }

  if (!signatureImage.value || !agreementAccepted.value) {
    error.value = 'Please sign the rental agreement and accept the terms before booking.'
    return
  }

  submitting.value = true

  let savedBookingId: number | null = null

  try {
    // Verify dates again before saving.
    const response = await fetch(
      `${BACKEND_BASE}/carease/api/cars/${carId}/reserved-dates`,
      {
        credentials: 'include',
      },
    )

    if (!response.ok) {
      throw new Error(
        'Unable to verify date availability.',
      )
    }

    const data = await response.json()

    if (!Array.isArray(data.reservations)) {
      throw new Error(
        'Invalid availability response.',
      )
    }

    reservedBookings.value = data.reservations

    if (
      hasConflict(
        pickupDate.value,
        returnDate.value,
      )
    ) {
      error.value =
        'These dates have already been reserved.'
      return
    }

    // Refresh car prices from PHP.
    const priceError = await refreshCarsFromBackend()

    if (priceError) {
      error.value = priceError
      return
    }

    // Save booking to the database.
    const result = await addBooking({
      car_id: car.value.id,
      customer_name: customerName.value,
      email: email.value,
      phone: phone.value.trim(),
      pickup_date: pickupDate.value,
      return_date: returnDate.value,
      signature_image: signatureImage.value,
      agreement_accepted: '1',
      pickup_location: pickup.address,
      pickup_lat: pickup.lat,
      pickup_lng: pickup.lng,
      dropoff_location: dropoff.address,
      dropoff_lat: dropoff.lat,
      dropoff_lng: dropoff.lng,
      route_distance_m: routeInfo.value?.distance_m,
      route_duration_s: routeInfo.value?.duration_s,
    })

    if (result.error) {
      error.value = result.error
      return
    }

    if (!result.id) {
      error.value =
        'Booking was not confirmed by the server.'
      return
    }

    savedBookingId = Number(result.id)

    // Start PayMongo checkout.
    const paymentResult = await payNow(savedBookingId)

    if (paymentResult?.error) {
      throw new Error(paymentResult.error)
    }

    if (paymentResult?.checkoutUrl) {
      window.location.assign(paymentResult.checkoutUrl)
      return
    }

    // payNow may redirect internally.
    error.value =
      `Booking #${savedBookingId} was saved. ` +
      'If PayMongo did not open, go to My Bookings ' +
      'and use the payment option. Do not submit again.'

  } catch (err: unknown) {
    const message =
      err instanceof Error
        ? err.message
        : 'Unable to process booking.'

    error.value = savedBookingId
      ? `Booking #${savedBookingId} was saved, ` +
        `but payment could not start. ${message} ` +
        'Do not submit another booking.'
      : message
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await checkIdVerification()
  const priceError = await refreshCarsFromBackend()

  if (priceError) {
    error.value = priceError
  }

  await loadReservedDates()
})
</script>

<template>
  <AppShell
    shell="client"
    nav-key="fleet"
    :title="title"
    :subtitle="subtitle"
  >
    <section v-if="car" class="panel premium-book-layout">
      <p class="eyebrow">CAR RENTAL REQUEST</p>

      <h1>{{ car.brand }} {{ car.name }}</h1>

      <p>
        {{ peso(car.price_per_day, 2) }} per day
      </p>

      <div
        class="upload-field"
        style="margin: 16px 0"
      >
        <img
          v-if="thumb"
          class="upload-preview"
          :src="thumb"
          :alt="`${car.brand} ${car.name}`"
        />

        <span
          v-else
          class="upload-preview upload-preview--empty"
        >
          <AppIcon name="car" />
        </span>

        <div>
          <strong>
            {{ car.brand }} {{ car.name }}
          </strong>

          <p class="upload-hint">
            {{ car.seats }} seats ·
            {{ transmissionOf(car.id) }}
          </p>
        </div>
      </div>

      <p v-if="error" class="error">
        {{ error }}
      </p>

      <div v-if="currentUser && idStatus !== 'verified'" class="id-warning">
        <strong>Valid ID Verification Required</strong>
        <p v-if="checkingId">Checking your ID verification status...</p>
        <p v-else>Please upload your valid ID and wait for admin verification before booking a vehicle.</p>
        <p v-if="!checkingId">Current status: {{ idStatus || 'not submitted' }}</p>
        <button type="button" class="button ghost" @click="checkIdVerification">Refresh ID Status</button>
      </div>

      <form @submit.prevent="handleSubmit">
        <h3>Customer Information</h3>

        <p class="muted">
          Automatically filled using your
          logged-in account.
        </p>

        <label>
          Full Name

          <input
            :value="customerName"
            type="text"
            readonly
          />
        </label>

        <label>
          Email Address

          <input
            :value="email"
            type="email"
            readonly
          />
        </label>

        <label>
          Contact Number

          <input
            v-model="phone"
            type="tel"
            placeholder="09XXXXXXXXX"
            maxlength="20"
            required
          />
        </label>

        <h3 style="margin-top: 24px">
          Pickup &amp; Drop-off Locations
        </h3>

        <p class="muted">
          Search for your addresses. The map shows the driving
          route, distance, and estimated travel time.
        </p>

        <LocationMap
          v-model:pickup="pickupLocation"
          v-model:dropoff="dropoffLocation"
          v-model:route="routeInfo"
        />

        <h3 style="margin-top: 24px">
          Select Rental Dates
        </h3>

        <p class="muted">
          Click your pickup date, then your
          return date. Gray dates are
          already reserved.
        </p>

        <p v-if="loadingDates" class="muted">
          Loading reserved dates...
        </p>

        <div class="rental-calendar">
          <div class="calendar-heading">
            <button
              type="button"
              class="calendar-nav"
              :disabled="!canGoPrevious"
              @click="changeMonth(-1)"
            >
              ‹
            </button>

            <strong>
              {{ monthTitle }}
            </strong>

            <button
              type="button"
              class="calendar-nav"
              @click="changeMonth(1)"
            >
              ›
            </button>
          </div>

          <div class="calendar-grid">
            <span
              v-for="day in [
                'Sun',
                'Mon',
                'Tue',
                'Wed',
                'Thu',
                'Fri',
                'Sat',
              ]"
              :key="day"
              class="weekday"
            >
              {{ day }}
            </span>

            <template
              v-for="(date, index) in calendarDays"
              :key="index"
            >
              <span v-if="!date"></span>

              <button
                v-else
                type="button"
                class="calendar-day"
                :class="{
                  reserved: isReserved(date),
                  selected: isSelected(date),
                  inRange: isInRange(date),
                }"
                :disabled="
                  loadingDates ||
                  date < todayISO() ||
                  isReserved(date)
                "
                @click="selectDate(date)"
              >
                {{ Number(date.slice(-2)) }}
              </button>
            </template>
          </div>

          <div class="calendar-legend">
            <span>⬜ Available</span>
            <span>◼ Reserved</span>
            <span>● Selected</span>
          </div>
        </div>

        <div
          class="two"
          style="margin-top: 20px"
        >
          <label>
            Pickup Date

            <input
              :value="pickupDate"
              type="text"
              readonly
              placeholder="Select on calendar"
              required
            />
          </label>

          <label>
            Return Date

            <input
              :value="returnDate"
              type="text"
              readonly
              placeholder="Select on calendar"
              required
            />
          </label>
        </div>

        <p class="muted">
          Maximum rental period: 5 days.
        </p>

        <p v-if="dateError" class="error">
          {{ dateError }}
        </p>

        <div
          v-if="
            pickupDate &&
            returnDate &&
            !dateError
          "
          class="panel"
          style="margin: 16px 0; padding: 16px"
        >
          <h3>Rental Summary</h3>

          <p>
            Price per day:
            <strong>
              {{ peso(car.price_per_day, 2) }}
            </strong>
          </p>

          <p>
            Number of days:
            <strong>
              {{ rentalDays }}
            </strong>
          </p>

          <hr />

          <div class="payment-breakdown">
            <div class="payment-row">
              <span>Total Rental Fee</span>
              <strong>
                {{ peso(totalAmount, 2) }}
              </strong>
            </div>

            <div class="payment-row">
              <span>30% Downpayment</span>
              <strong>
                {{ peso(downpaymentAmount, 2) }}
              </strong>
            </div>

            <div class="payment-row">
              <span>70% Remaining Balance</span>
              <strong>
                {{ peso(remainingAmount, 2) }}
              </strong>
            </div>
          </div>

          <p class="muted">
            Pay 30% now through PayMongo.
            The remaining 70% can be paid
            after admin approval.
          </p>
        </div>

        <section v-if="pickupDate && returnDate && !dateError" class="agreement-section">
          <h3>Rental Agreement</h3>
          <p class="muted">Please read all terms before signing.</p>
          <div class="agreement-terms" tabindex="0">
            <p v-for="term in agreementTerms" :key="term">{{ term }}</p>
          </div>

          <p><strong>Customer Signature</strong></p>
          <p class="muted">Draw your signature using your mouse or touchscreen.</p>
          <canvas
            ref="signatureCanvas"
            class="signature-canvas"
            width="600"
            height="180"
            aria-label="Draw your signature"
            @pointerdown="beginSignature"
            @pointermove="drawSignature"
            @pointerup="finishSignature"
            @pointercancel="finishSignature"
            @lostpointercapture="finishSignature"
          ></canvas>
          <div class="signature-actions">
            <span class="muted">{{ signatureImage ? 'Signature added' : 'Signature required' }}</span>
            <button type="button" class="button" @click="clearSignature">Clear Signature</button>
          </div>
          <label class="agreement-check">
            <input v-model="agreementAccepted" type="checkbox" />
            <span>I have read and agree to the Rental Agreement and authorize use of my signature for this booking.</span>
          </label>
        </section>

        <p class="muted">
          Your booking will be submitted as
          Pending. You will be redirected to
          PayMongo to pay the 30% downpayment.
        </p>

        <button
          class="button"
          type="submit"
          :disabled="
            submitting ||
            checkingId ||
            idStatus !== 'verified' ||
            loadingDates ||
            !pickupDate ||
            !returnDate ||
            !pickupLocation ||
            !dropoffLocation ||
            !!dateError ||
            !currentUser ||
            totalAmount <= 0 ||
            !signatureImage ||
            !agreementAccepted
          "
        >
          {{
            submitting
              ? 'Processing...'
              : 'Submit & Pay 30% Downpayment'
          }}
        </button>
      </form>
    </section>

    <section v-else class="panel">
      <p class="error">
        Car not available.
      </p>

      <router-link
        class="button"
        :to="{ path: '/', hash: '#fleet' }"
      >
        Browse Cars
      </router-link>
    </section>
  </AppShell>
</template>

<style scoped>
.id-warning { margin: 16px 0; padding: 16px; border: 1px solid #d9b46b; border-radius: 8px; background: #fffaf0; }
.id-warning p { margin: 8px 0; }
.rental-calendar {
  margin-top: 16px;
  padding: 16px;
  border: 1px solid #d8dce4;
  border-radius: 12px;
}
.agreement-section { margin: 24px 0; padding: 16px; border: 1px solid #d8dce4; border-radius: 12px; }
.agreement-terms { max-height: 260px; overflow-y: auto; padding: 12px; border: 1px solid #d8dce4; border-radius: 8px; background: #fafbfc; white-space: pre-wrap; }
.agreement-terms p { margin: 0 0 12px; }
.signature-canvas { display: block; width: 100%; height: auto; border: 1px solid #b9cfe2; border-radius: 8px; background: white; touch-action: none; cursor: crosshair; }
.signature-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 10px 0 18px; }
.agreement-check { display: flex; align-items: flex-start; gap: 10px; }
.agreement-check input { width: auto; margin-top: 4px; }
</style>
