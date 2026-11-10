
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  bookingsForUser,
  cars,
 currentUser,
requestVehicleReturn,
payNow,
  checkPaymentStatus,
  type PaymentSummary,
} from '@/stores/carease'
import { peso } from '@/lib/format'
import AppShell from '@/components/AppShell.vue'

type RentalContract = {
  id: number
  booking_id: number
  contract_no: string
  customer_name: string
  customer_email: string
  customer_phone: string
  car_description: string
  pickup_date: string
  return_date: string
  total_amount: string
  terms: string
  status: string
  customer_signature?: string | null
  signature_image?: string | null
  signed_at?: string | null
}

const notice = ref('')
const returning = ref<Record<number, boolean>>({})
const submittedReturns = ref<Record<number, boolean>>({})
const returnSuccess = ref('')

async function submitReturn(bookingId: number) {
  if (returning.value[bookingId] || submittedReturns.value[bookingId]) return

  if (!window.confirm('Request vehicle return for Booking #' + bookingId + '?')) return

  returning.value[bookingId] = true
  notice.value = ''
  returnSuccess.value = ''

  try {
    const error = await requestVehicleReturn(bookingId)

    if (error) {
      notice.value = error
      return
    }

    submittedReturns.value[bookingId] = true
    returnSuccess.value =
      'Return request submitted for Booking #' + bookingId +
      '. Please wait for admin confirmation.'
  } catch (err: unknown) {
    notice.value = err instanceof Error
      ? err.message
      : 'Unable to submit return request.'
  } finally {
    returning.value[bookingId] = false
  }
}
const paymentSummaries = ref<Record<number, PaymentSummary>>({})
const loadingPayments = ref<Record<number, boolean>>({})

const contractError = ref('')
const contractNotice = ref('')
const selectedContract = ref<RentalContract | null>(null)
const selectedBookingId = ref<number | null>(null)
const signature = ref('')
const loadingContract = ref(false)
const signing = ref(false)

const API_BASE = '/carease/api'

const mine = computed(() =>
  currentUser.value
    ? bookingsForUser(currentUser.value.id)
    : [],
)


const totalBooked = computed(() =>
  mine.value.reduce((sum, booking) => sum + Number(booking.total_amount || 0), 0),
)
const activeBookings = computed(() =>
  mine.value.filter((booking) =>
    ['approved', 'confirmed', 'ongoing', 'overdue'].includes(booking.status),
  ).length,
)
const sortedBookings = computed(() =>
  [...mine.value].sort((a, b) => b.id - a.id),
)
function displayStatus(status: string) {
  return status.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

async function refreshPayment(bookingId: number) {
  loadingPayments.value[bookingId] = true

  try {
    const result = await checkPaymentStatus(bookingId)

    if (result.error) {
      notice.value = result.error
    } else {
      paymentSummaries.value[bookingId] = result
      notice.value = ''
    }
  } finally {
    loadingPayments.value[bookingId] = false
  }
}

watch(
  mine,
  (bookings) => {
    for (const booking of bookings) {
      if (
        !paymentSummaries.value[booking.id] &&
        !loadingPayments.value[booking.id]
      ) {
        void refreshPayment(booking.id)
      }
    }
  },
  { immediate: true },
)

function paymentInfo(bookingId: number) {
  return paymentSummaries.value[bookingId]
}

function moneyFromCentavos(value?: number) {
  return peso((value ?? 0) / 100, 2)
}

function paymentLabel(bookingId: number) {
  const payment = paymentInfo(bookingId)

  if (!payment) return 'Checking payment...'

  if (payment.payment_status === 'fully_paid') {
    return 'Fully Paid'
  }

  if (payment.payment_status === 'downpayment_paid') {
    return 'Downpayment Paid'
  }

  if (payment.payment_status === 'partially_paid') {
    return 'Partially Paid'
  }

  if (payment.has_pending_checkout) {
    return 'Payment Pending'
  }

  return 'Unpaid'
}

function payLabel(bookingId: number) {
  const payment = paymentInfo(bookingId)

  if (!payment) return 'Checking payment...'

  if (payment.payment_status === 'downpayment_paid') {
    return 'Pay Remaining Balance'
  }

  if (payment.payment_status === 'partially_paid') {
    return 'Continue Payment'
  }

  if (payment.has_pending_checkout) {
    return 'Continue Checkout'
  }

  return 'Pay 30% Downpayment'
}

async function pay(bookingId: number) {
  notice.value = ''

  try {
    const result = await payNow(bookingId)

    if (result?.error) {
      notice.value = result.error
      await refreshPayment(bookingId)
    }
  } catch (err: unknown) {
    notice.value =
      err instanceof Error
        ? err.message
        : 'Unable to process payment.'
  }
}

function carName(carId: number): string {
  const car = cars.value.find((c) => c.id === carId)
  return car ? car.name : 'Car #' + carId
}

function todayISO(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function daysUntilReturn(returnDate: string): number {
  const today = new Date(`${todayISO()}T00:00:00`)
  const due = new Date(`${returnDate}T00:00:00`)

  return Math.round(
    (due.getTime() - today.getTime()) / 86400000,
  )
}

function returnNotice(booking: {
  status: string
  return_date: string
}): string {
  if (
    !['approved', 'confirmed', 'ongoing', 'overdue'].includes(
      booking.status,
    )
  ) {
    return ''
  }

  const days = daysUntilReturn(booking.return_date)

  if (booking.status === 'overdue' || days < 0) {
    return 'OVERDUE NOTICE: Your rental return date has passed. Please contact CarEase and return the vehicle immediately.'
  }

  if (days === 0) {
    return 'RETURN DUE TODAY: Please return the vehicle on the agreed date.'
  }

  if (days === 1) {
    return 'RETURN REMINDER: Your vehicle is due for return tomorrow.'
  }

  return ''
}

async function openContract(bookingId: number) {
  contractError.value = ''
  contractNotice.value = ''
  selectedContract.value = null
  selectedBookingId.value = bookingId
  signature.value = ''
  loadingContract.value = true

  try {
    const response = await fetch(
      `${API_BASE}/contracts/${bookingId}`,
      { credentials: 'include' },
    )

    const data = await response.json()

    if (!response.ok || data.error) {
      contractError.value =
        data.error || 'Unable to load rental contract.'
      return
    }

    selectedContract.value = data.contract ?? null

    if (!selectedContract.value) {
      contractError.value =
        'Rental agreement is not available yet. Please wait for admin approval.'
    }
  } catch (err: unknown) {
    contractError.value =
      err instanceof Error
        ? err.message
        : 'Unable to load rental contract.'
  } finally {
    loadingContract.value = false
  }
}

async function signContract() {
  if (
    !selectedContract.value ||
    selectedBookingId.value === null
  ) {
    return
  }

  if (!signature.value.trim()) {
    contractError.value =
      'Please enter your registered full name.'
    return
  }

  contractError.value = ''
  contractNotice.value = ''
  signing.value = true

  try {
    const sessionResponse = await fetch(
      `${API_BASE}/session`,
      { credentials: 'include' },
    )

    const sessionData = await sessionResponse.json()

    if (!sessionResponse.ok || !sessionData.csrf) {
      contractError.value =
        'Unable to verify your session. Please sign in again.'
      return
    }

    const form = new URLSearchParams()
    form.set('csrf', sessionData.csrf)
    form.set('signature', signature.value.trim())

    const response = await fetch(
      `${API_BASE}/contracts/${selectedBookingId.value}/sign`,
      {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: form.toString(),
      },
    )

    const data = await response.json()

    if (!response.ok || data.error) {
      contractError.value =
        data.error || 'Unable to sign rental agreement.'
      return
    }

    selectedContract.value = data.contract
    contractNotice.value =
      data.message || 'Rental agreement signed successfully.'
    signature.value = ''
  } catch (err: unknown) {
    contractError.value =
      err instanceof Error
        ? err.message
        : 'Unable to sign rental agreement.'
  } finally {
    signing.value = false
  }
}

// Opens a printable copy. In the browser print dialog, choose "Save as PDF".
function saveContractPDF() {
  const contract = selectedContract.value
  if (!contract || contract.status !== 'signed') return

  const escapeHTML = (value: unknown): string =>
    String(value ?? '').replace(/[&<>"']/g, (character) => {
      const entities: Record<string, string> = {
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
      }
      return entities[character] ?? character
    })

  const signatureImage = contract.signature_image &&
    /^data:image\/png;base64,[A-Za-z0-9+/]+={0,2}$/.test(contract.signature_image)
      ? `<img src="${contract.signature_image}" alt="Customer handwritten signature" class="signature" />`
      : '<p>No handwritten signature image available.</p>'

  const printable = window.open('', '_blank')
  if (!printable) {
    contractError.value = 'Please allow pop-ups to save the contract as PDF.'
    return
  }

  const documentHTML = `<!doctype html>
<html lang="en"><head><meta charset="utf-8" />
<title>Rental Agreement - ${escapeHTML(contract.contract_no)}</title>
<style>
  @page { size: A4; margin: 18mm; }
  body { font-family: Arial, sans-serif; color: #222; line-height: 1.55; font-size: 12px; }
  h1 { font-size: 22px; margin-bottom: 4px; }
  h2 { font-size: 15px; margin-top: 25px; }
  .sub { color: #555; margin-bottom: 20px; }
  .details { width: 100%; border-collapse: collapse; }
  .details td { border-bottom: 1px solid #ddd; padding: 7px 4px; vertical-align: top; }
  .details td:first-child { width: 35%; font-weight: bold; }
  .terms { white-space: pre-wrap; overflow-wrap: anywhere; }
  .signature { width: 300px; max-height: 120px; object-fit: contain; display: block; border-bottom: 1px solid #888; }
  .signed { break-inside: avoid; margin-top: 24px; }
  .actions { margin-bottom: 22px; }
  .actions button { padding: 10px 16px; cursor: pointer; }
  @media print { .actions { display: none; } }
</style></head><body>
<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>CarEase Rentals</h1><div class="sub">Rental Agreement</div>
<table class="details">
<tr><td>Contract No.</td><td>${escapeHTML(contract.contract_no)}</td></tr>
<tr><td>Status</td><td>${escapeHTML(contract.status)}</td></tr>
<tr><td>Customer</td><td>${escapeHTML(contract.customer_name)}</td></tr>
<tr><td>Email</td><td>${escapeHTML(contract.customer_email)}</td></tr>
<tr><td>Phone</td><td>${escapeHTML(contract.customer_phone)}</td></tr>
<tr><td>Vehicle</td><td>${escapeHTML(contract.car_description)}</td></tr>
<tr><td>Pickup</td><td>${escapeHTML(contract.pickup_date)}</td></tr>
<tr><td>Return</td><td>${escapeHTML(contract.return_date)}</td></tr>
<tr><td>Total Rental Fee</td><td>${escapeHTML(peso(Number(contract.total_amount), 2))}</td></tr>
</table>
<h2>Terms and Conditions</h2><div class="terms">${escapeHTML(contract.terms)}</div>
<div class="signed"><h2>Customer Signature</h2>
${signatureImage}
<p><strong>Signed by:</strong> ${escapeHTML(contract.customer_signature || contract.customer_name)}</p>
<p><strong>Date signed:</strong> ${escapeHTML(contract.signed_at)}</p>
</div></body></html>`

  printable.document.open()
  printable.document.write(documentHTML)
  printable.document.close()
  contractError.value = ''
}

function closeContract() {
  selectedBookingId.value = null
  selectedContract.value = null
  contractError.value = ''
  contractNotice.value = ''
  signature.value = ''
}
</script>

<template>
  <AppShell
    shell="client"
    nav-key="bookings"
    title="My Bookings"
    subtitle="Track your rental requests, contracts and payments"
  >
    <section class="rental-hero">
      <div class="rental-hero-content">
        <span class="rental-eyebrow">MY RENTAL JOURNEY</span>
        <h2>Your bookings, all in one place.</h2>
        <p>Track reservations, payments, contracts, and vehicle returns with ease.</p>
        <router-link to="/" class="rental-hero-link">Explore Our Fleet <span aria-hidden="true">→</span></router-link>
      </div>
      <div class="rental-hero-decoration" aria-hidden="true">✦</div>
    </section>

    <section class="rental-summary" aria-label="Booking overview">
      <div class="rental-stat">
        <span class="rental-stat-icon">▦</span>
        <strong>{{ mine.length }}</strong>
        <span>Total bookings</span>
      </div>
      <div class="rental-stat">
        <span class="rental-stat-icon">◉</span>
        <strong>{{ activeBookings }}</strong>
        <span>Active rentals</span>
      </div>
      <div class="rental-stat">
        <span class="rental-stat-icon">₱</span>
        <strong>{{ peso(totalBooked, 2) }}</strong>
        <span>Total booked</span>
      </div>
    </section>

    <section class="rental-section">
      <div class="rental-section-head">
        <div>
          <span class="rental-eyebrow rental-eyebrow-dark">YOUR RESERVATIONS</span>
          <h2>My Bookings</h2>
          <p>All your rental details, organized in one place.</p>
        </div>
        <router-link class="rental-outline-button" to="/">Browse Cars <span aria-hidden="true">→</span></router-link>
      </div>

      <p v-if="notice" class="error" role="alert">{{ notice }}</p>
      <p v-if="returnSuccess" role="status">
  {{ returnSuccess }}
</p>

      <div v-if="mine.length === 0" class="rental-empty">
        <h3>No bookings yet</h3>
        <p>Your upcoming adventures will appear here after you reserve a car.</p>
        <router-link class="rental-gold-button" to="/">Browse Fleet</router-link>
      </div>

      <div v-else class="rental-booking-list">
        <article v-for="booking in sortedBookings" :key="booking.id" class="rental-booking-card">
          <header class="rental-card-header">
            <div class="rental-car-identity">
              <span class="rental-car-icon" aria-hidden="true">◆</span>
              <div>
                <span class="rental-booking-number">BOOKING #{{ booking.id }}</span>
                <h3>{{ carName(booking.car_id) }}</h3>
              </div>
            </div>
            <span class="rental-status" :data-status="booking.status">
              <span class="rental-status-dot"></span>{{ displayStatus(booking.status) }}
            </span>
          </header>

          <div class="rental-card-body">
            <div class="rental-details-grid">
              <div class="rental-detail">
                <span class="rental-detail-label">PICKUP DATE</span>
                <strong>{{ booking.pickup_date }}</strong>
              </div>
              <div class="rental-detail">
                <span class="rental-detail-label">RETURN DATE</span>
                <strong>{{ booking.return_date }}</strong>
              </div>
              <div class="rental-detail">
                <span class="rental-detail-label">RENTAL TOTAL</span>
                <strong class="rental-amount">{{ peso(Number(booking.total_amount), 2) }}</strong>
              </div>
            </div>

            <div v-if="returnNotice(booking)" class="rental-return-notice"
              :class="{ 'is-overdue': booking.status === 'overdue' || daysUntilReturn(booking.return_date) < 0 }">
              {{ returnNotice(booking) }}
            </div>
          </div>

          <footer class="rental-card-footer">
            <div class="rental-payment">
              <div class="rental-payment-title">
                <span class="rental-payment-icon" aria-hidden="true">₱</span>
                <div>
                  <span class="rental-detail-label">PAYMENT STATUS</span>
                  <strong>{{ paymentLabel(booking.id) }}</strong>
                </div>
              </div>
              <div v-if="paymentInfo(booking.id)" class="rental-payment-breakdown">
                <span>Downpayment <strong>{{ moneyFromCentavos(paymentInfo(booking.id)?.downpayment_centavos) }}</strong></span>
                <span>Paid <strong>{{ moneyFromCentavos(paymentInfo(booking.id)?.paid_centavos) }}</strong></span>
                <span>Balance <strong>{{ moneyFromCentavos(paymentInfo(booking.id)?.remaining_centavos) }}</strong></span>
              </div>
            </div>

            <div class="rental-card-actions">
        <button
  v-if="['ongoing', 'overdue'].includes(booking.status)"
  class="rental-gold-button"
  type="button"
  :disabled="
    !!returning[booking.id] ||
    !!submittedReturns[booking.id] ||
    !!booking.return_request_status
  "
  @click="submitReturn(booking.id)"
>
  {{
    returning[booking.id]
      ? 'Submitting...'
      : submittedReturns[booking.id] || booking.return_request_status
        ? 'Request Submitted'
        : 'Request Vehicle Return'
  }}
</button>
              <button
                v-if="['approved', 'confirmed', 'ongoing', 'overdue', 'completed'].includes(booking.status)"
                class="rental-outline-button"
                type="button"
                @click="openContract(booking.id)"
              >View Contract</button>
              <button
                v-if="['approved', 'confirmed'].includes(booking.status) &&
                  paymentInfo(booking.id) &&
                  paymentInfo(booking.id)?.payment_status !== 'fully_paid'"
                class="rental-gold-button"
                type="button"
                :disabled="loadingPayments[booking.id]"
                @click="pay(booking.id)"
              >{{ payLabel(booking.id) }}</button>
              <button class="rental-outline-button" type="button"
                :disabled="loadingPayments[booking.id]"
                @click="refreshPayment(booking.id)"
              >{{ loadingPayments[booking.id] ? 'Checking...' : 'Refresh Payment' }}</button>
            </div>
          </footer>
        </article>
      </div>
    </section>

    <section
      v-if="selectedBookingId !== null"
      class="panel"
      style="margin-top: 20px"
    >
      <div class="heading">
        <h2>Rental Agreement</h2>

        <button
          class="button ghost"
          type="button"
          @click="closeContract"
        >
          Close
        </button>
      </div>

      <p v-if="loadingContract" class="muted">
        Loading rental agreement...
      </p>

      <p v-if="contractError" class="error">
        {{ contractError }}
      </p>

      <p v-if="contractNotice" class="muted">
        {{ contractNotice }}
      </p>

      <div v-if="selectedContract">
        <p>
          <strong>Contract No:</strong>
          {{ selectedContract.contract_no }}
        </p>

        <p>
          <strong>Status:</strong>
          {{ selectedContract.status }}
        </p>

        <p>
          <strong>Customer:</strong>
          {{ selectedContract.customer_name }}
        </p>

        <p>
          <strong>Email:</strong>
          {{ selectedContract.customer_email }}
        </p>

        <p>
          <strong>Phone:</strong>
          {{ selectedContract.customer_phone }}
        </p>

        <p>
          <strong>Vehicle:</strong>
          {{ selectedContract.car_description }}
        </p>

        <p>
          <strong>Pickup:</strong>
          {{ selectedContract.pickup_date }}
        </p>

        <p>
          <strong>Return:</strong>
          {{ selectedContract.return_date }}
        </p>

        <p>
          <strong>Total Rental Fee:</strong>
          {{ peso(Number(selectedContract.total_amount), 2) }}
        </p>

        <h3>Terms and Conditions</h3>

        <div
          style="
            white-space: pre-line;
            line-height: 1.7;
            margin: 16px 0;
          "
        >
          {{ selectedContract.terms }}
        </div>

        <div v-if="selectedContract.status === 'signed'">
          <button
            type="button"
            class="button"
            style="margin: 12px 0 20px"
            @click="saveContractPDF"
          >
            Save as PDF
          </button>
          <p>
            <strong>Signed by:</strong>
            {{ selectedContract.customer_signature }}
          </p>

          <p>
            <strong>Date signed:</strong>
            {{ selectedContract.signed_at }}
          </p>

          <div
            v-if="selectedContract.signature_image?.startsWith('data:image/png;base64,')"
            class="contract-signature"
          >
            <h3>Customer's Handwritten Signature</h3>
            <img
              :src="selectedContract.signature_image"
              alt="Customer's handwritten signature"
              class="signature-image"
            />
          </div>
        </div>

        <form
          v-else-if="
            ['draft', 'sent'].includes(selectedContract.status)
          "
          @submit.prevent="signContract"
        >
          <h3>Sign Rental Agreement</h3>

          <p class="muted">
            Please read the agreement carefully.
            Your valid ID must be verified before signing.
            Type your full registered name to accept the terms.
          </p>

          <label>
            Full Name / Signature

            <input
              v-model="signature"
              required
              maxlength="120"
              placeholder="Enter your registered full name"
            />
          </label>

          <button
            class="button"
            type="submit"
            :disabled="signing"
          >
            {{
              signing
                ? 'Signing...'
                : 'I Agree and Sign Contract'
            }}
          </button>
        </form>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>

/* My Bookings: scoped premium navy / white / gold layout */
.rental-hero { position:relative; overflow:hidden; min-height:200px; display:flex; align-items:center; padding:34px 40px; border-radius:22px; color:#fff; background:linear-gradient(115deg,#081b31 0%,#102e4b 67%,#244b68 100%); box-shadow:0 16px 35px rgba(12,32,54,.14); margin-bottom:22px; }
.rental-hero:after { content:""; position:absolute; width:320px; height:320px; border:1px solid rgba(224,185,97,.24); border-radius:50%; right:-90px; top:-90px; box-shadow:0 0 0 40px rgba(224,185,97,.035),0 0 0 85px rgba(224,185,97,.025); }
.rental-hero-content { position:relative; z-index:1; max-width:750px; }
.rental-eyebrow { display:block; color:#e4bf69; font-size:11px; font-weight:800; letter-spacing:2px; margin-bottom:10px; }
.rental-hero h2 { color:#fff; font-size:clamp(24px,3vw,36px); letter-spacing:-1px; line-height:1.2; margin:0 0 12px; }
.rental-hero p { color:#d1dce8; font-size:14px; margin:0 0 19px; }
.rental-hero-link { color:#f2d18a; font-weight:700; text-decoration:none; font-size:13px; }
.rental-hero-link:hover { text-decoration:underline; }
.rental-hero-decoration { position:absolute; right:110px; top:65px; font-size:90px; color:rgba(224,185,97,.12); }
.rental-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; margin-bottom:30px; }
.rental-stat { background:var(--surface,#fff); border:1px solid var(--border,#e1e8f0); border-radius:18px; padding:23px 25px; box-shadow:0 9px 25px rgba(16,39,64,.05); display:flex; flex-direction:column; gap:6px; }
.rental-stat-icon { color:#b78b31; font-size:18px; font-weight:700; }
.rental-stat strong { color:#ad7d22; font-size:clamp(23px,2.3vw,30px); font-weight:800; line-height:1.2; overflow-wrap:anywhere; }
.rental-stat>span:last-child { color:var(--muted,#6b7b8e); font-size:13px; }
.rental-section { margin-bottom:25px; }
.rental-section-head { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:18px; }
.rental-eyebrow-dark { color:#b58b3b; margin-bottom:5px; }
.rental-section-head h2 { font-size:24px; margin:0 0 4px; color:var(--text,#152b43); }
.rental-section-head p { color:var(--muted,#6b7b8e); margin:0; font-size:13px; }
.rental-booking-list { display:grid; gap:19px; }
.rental-booking-card { overflow:hidden; background:var(--surface,#fff); border:1px solid var(--border,#dfe7ef); border-radius:20px; box-shadow:0 12px 30px rgba(13,35,58,.06); }
.rental-card-header { display:flex; justify-content:space-between; align-items:center; gap:14px; padding:22px 26px; border-bottom:1px solid var(--border,#e7edf3); }
.rental-car-identity { display:flex; gap:15px; align-items:center; min-width:0; }
.rental-car-icon { width:54px; height:54px; display:grid; place-items:center; flex-shrink:0; border-radius:14px; background:linear-gradient(140deg,#0b2744,#244967); color:#e4bf69; font-size:24px; }
.rental-booking-number { font-size:11px; letter-spacing:1.2px; color:var(--muted,#77889b); font-weight:750; }
.rental-car-identity h3 { margin:5px 0 0; font-size:20px; color:var(--text,#10243b); }
.rental-status { display:inline-flex; align-items:center; gap:7px; text-transform:capitalize; white-space:nowrap; border-radius:30px; padding:8px 12px; font-size:12px; font-weight:750; color:#976d20; background:#fbf2df; }
.rental-status-dot { width:7px; height:7px; background:currentColor; border-radius:50%; }
.rental-status[data-status="completed"], .rental-status[data-status="confirmed"], .rental-status[data-status="ongoing"] { color:#167451; background:#e5f7ef; }
.rental-status[data-status="rejected"], .rental-status[data-status="cancelled"], .rental-status[data-status="overdue"] { color:#aa3e39; background:#fff0ef; }
.rental-card-body { padding:24px 26px 18px; }
.rental-details-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
.rental-detail { display:flex; flex-direction:column; gap:8px; }
.rental-detail-label { display:block; font-size:10px; letter-spacing:1.1px; font-weight:800; color:var(--muted,#77889b); }
.rental-detail strong { font-size:15px; color:var(--text,#142c45); }
.rental-detail .rental-amount { color:#ac7c24; font-size:20px; }
.rental-return-notice { padding:12px 14px; margin-top:19px; border-radius:10px; font-size:12px; color:#755617; background:#fff7e8; }
.rental-return-notice.is-overdue { color:#a53731; background:#fff0ef; }
.rental-card-footer { padding:18px 26px 23px; background:var(--surface-secondary,#f8fafc); border-top:1px solid var(--border,#e7edf3); display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.rental-payment { display:flex; flex-direction:column; gap:10px; min-width:180px; }
.rental-payment-title { display:flex; align-items:center; gap:10px; }
.rental-payment-title strong { display:block; margin-top:4px; font-size:13px; color:var(--text,#152b43); }
.rental-payment-icon { width:34px; height:34px; border-radius:10px; display:grid; place-items:center; color:#9c711f; background:#fbf0d8; font-weight:800; }
.rental-payment-breakdown { display:flex; flex-wrap:wrap; gap:9px 16px; font-size:11px; color:var(--muted,#718198); }
.rental-payment-breakdown strong { color:var(--text,#1a324b); margin-left:4px; }
.rental-card-actions { display:flex; gap:9px; flex-wrap:wrap; align-items:center; }
.rental-outline-button,.rental-gold-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:40px; border-radius:10px; padding:10px 15px; font-size:12px; font-weight:750; cursor:pointer; text-decoration:none; transition:transform .15s ease,box-shadow .15s ease; }
.rental-outline-button { border:1px solid var(--border,#d4dfe9); color:var(--text,#18324d); background:var(--surface,#fff); }
.rental-gold-button { border:1px solid #d4a447; background:linear-gradient(135deg,#e9c16d,#c59632); color:#11283f; }
.rental-outline-button:hover,.rental-gold-button:hover { transform:translateY(-1px); box-shadow:0 5px 12px rgba(11,33,55,.1); }
.rental-outline-button:disabled,.rental-gold-button:disabled { opacity:.55; cursor:wait; transform:none; }
.rental-empty { text-align:center; padding:55px 20px; background:var(--surface,#fff); border:1px solid var(--border,#e1e8f0); border-radius:18px; }
.rental-empty h3 { margin:0 0 8px; color:var(--text,#132a42); }
.rental-empty p { color:var(--muted,#718198); margin-bottom:20px; }
@media(max-width:900px) { .rental-card-footer { align-items:stretch; } .rental-card-actions { width:100%; } }
@media(max-width:600px) { .rental-hero { padding:27px 22px; min-height:180px; } .rental-summary { grid-template-columns:1fr; gap:11px; } .rental-stat { padding:15px 18px; } .rental-section-head { align-items:flex-start; flex-direction:column; } .rental-card-header,.rental-card-body,.rental-card-footer { padding-left:17px; padding-right:17px; } .rental-details-grid { grid-template-columns:1fr 1fr; } .rental-detail:last-child { grid-column:1/-1; } .rental-car-identity h3 { font-size:17px; } .rental-card-actions>* { flex:1 1 auto; } }

.contract-signature {
  margin-top: 20px;
  padding: 16px;
  border: 1px solid #dbe5ef;
  border-radius: 8px;
  background: #ffffff;
}

.signature-image {
  display: block;
  width: 100%;
  max-width: 350px;
  height: 130px;
  object-fit: contain;
  background: #ffffff;
  border-bottom: 1px solid #999;
}
</style>
