<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { bookings as localBookings } from '@/stores/carease'
import { BACKEND_BASE } from '@/lib/backend'
import AppShell from '@/components/AppShell.vue'

interface SyncBooking {
  id: number
  customer_name: string
  pickup_date: string
  return_date: string
  status: string
}

interface CleanupFailure {
  booking_id: number
  error: string
}

interface CleanupResult {
  status?: boolean
  error?: string
  deleted_count?: number
  deleted_booking_ids?: number[]
  skipped_booking_ids?: number[]
  failed?: CleanupFailure[]
}

const SYNCABLE = ['approved', 'confirmed', 'ongoing', 'overdue', 'completed']

const gcalUrl =
  'https://calendar.google.com/calendar/embed?src=' +
  encodeURIComponent(
    '1a6d16e669c118eb7b23d449d5910d4b5a9bf4cbb6b3e1469c86a41769d5ee04@group.calendar.google.com',
  ) +
  '&mode=MONTH&showTitle=0&showPrint=0&showCalendars=0&wkst=1&bgcolor=%23ffffff&timezone=Asia%2FManila'

const connectUrl = `${BACKEND_BASE}/carease/calendar-oauth/connect`
const loginUrl = `${BACKEND_BASE}/carease/login`
const backendHost = (() => {
  try {
    return new URL(BACKEND_BASE).hostname
  } catch {
    return ''
  }
})()
const sameHostAsBackend = !backendHost || window.location.hostname === backendHost

const message = ref('')
const connected = ref(false)
const backendReady = ref(false)
const csrf = ref('')
const remoteBookings = ref<SyncBooking[]>([])
const busyId = ref<number | null>(null)
const busyAll = ref(false)
const busyCleanup = ref(false)
const cleanupDetails = ref('')
const previewKey = ref(0)

function refreshPreview() {
  previewKey.value += 1
}

const syncable = computed<SyncBooking[]>(() => {
  if (backendReady.value) {
    return remoteBookings.value.filter((b) => SYNCABLE.includes(b.status))
  }
  return localBookings.value
    .filter((b) => SYNCABLE.includes(b.status))
    .map((b) => ({
      id: b.id,
      customer_name: b.customer_name,
      pickup_date: b.pickup_date,
      return_date: b.return_date,
      status: b.status,
    }))
})

async function loadStatus() {
  try {
    const res = await fetch(`${BACKEND_BASE}/carease/calendar/status`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) {
      backendReady.value = false
      connected.value = false
      message.value =
        res.status === 403
          ? sameHostAsBackend
            ? 'Sign in to the CarEase PHP admin in this browser to use calendar sync.'
            : `Sign in at ${backendHost} and open this app at http://${backendHost}:${window.location.port} so the PHP session cookie is shared.`
          : `Calendar API returned HTTP ${res.status}.`
      return
    }
    const data = await res.json()
    backendReady.value = true
    connected.value = Boolean(data.connected)
    csrf.value = String(data.csrf ?? '')
    remoteBookings.value = Array.isArray(data.bookings) ? (data.bookings as SyncBooking[]) : []
    if (data.message) message.value = String(data.message)
  } catch {
    backendReady.value = false
    connected.value = false
    message.value = 'Cannot reach the CarEase calendar API. Is the PHP app running?'
  }
}

async function sync(bookingId: number) {
  if (!connected.value) {
    message.value = 'Connect Google Calendar first.'
    return
  }
  busyId.value = bookingId
  try {
    const body = new URLSearchParams({ csrf: csrf.value, booking_id: String(bookingId) })
    const res = await fetch(`${BACKEND_BASE}/carease/calendar/sync`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body,
    })
    const data = await res.json().catch(() => ({}))
    message.value = String(
      data.message ?? data.error ?? (res.ok ? 'Synced.' : `Sync failed (HTTP ${res.status}).`),
    )
    if (res.ok) refreshPreview()
    await loadStatus()
  } catch {
    message.value = 'Network error while syncing the booking.'
  } finally {
    busyId.value = null
  }
}

async function syncAll() {
  if (!connected.value) {
    message.value = 'Connect Google Calendar first.'
    return
  }
  busyAll.value = true
  try {
    const body = new URLSearchParams({ csrf: csrf.value })
    const res = await fetch(`${BACKEND_BASE}/carease/calendar/sync-all`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body,
    })
    const data = await res.json().catch(() => ({}))
    message.value = String(
      data.message ?? data.error ?? (res.ok ? 'Synced.' : `Sync failed (HTTP ${res.status}).`),
    )
    if (res.ok) refreshPreview()
    await loadStatus()
  } catch {
    message.value = 'Network error while syncing the calendar.'
  } finally {
    busyAll.value = false
  }
}

async function cleanupCalendar() {
  if (busyCleanup.value || busyAll.value || busyId.value !== null) return
  if (!connected.value || !backendReady.value || !csrf.value) {
    message.value = 'Calendar admin connection is not ready.'
    return
  }

  if (!window.confirm('Delete the 23 previously reviewed orphaned CarEase calendar events? Only events without a matching booking will be removed.')) return

  busyCleanup.value = true
  cleanupDetails.value = ''
  try {
    const res = await fetch(`${BACKEND_BASE}/carease/calendar/cleanup-delete`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({ csrf: csrf.value }),
    })
    const data: CleanupResult = await res.json().catch(() => ({}))
    if (!res.ok) {
      message.value = String(data.error ?? `Calendar cleanup failed (HTTP ${res.status}).`)
      return
    }

    const deleted = Number(data.deleted_count ?? 0)
    const failed = Array.isArray(data.failed) ? data.failed : []
    const skipped = Array.isArray(data.skipped_booking_ids) ? data.skipped_booking_ids : []
    message.value = `Calendar cleanup: ${deleted} deleted, ${skipped.length} skipped, ${failed.length} failed.`
    cleanupDetails.value = failed.length
      ? `Failed bookings: ${failed.map((item) => `#${item.booking_id}: ${item.error}`).join(' | ')}`
      : ''
    if (deleted > 0) refreshPreview()
    // Do not call loadStatus() here: it may replace the cleanup result message.
  } catch {
    message.value = 'Network error during calendar cleanup. Check the preview before retrying.'
  } finally {
    busyCleanup.value = false
  }
}

onMounted(loadStatus)
</script>

<template>
  <AppShell
    shell="admin"
    nav-key="calendar"
    title="Google Calendar Sync"
    subtitle="Push approved bookings to the rental calendar"
  >
    <div class="heading">
      <h1>Google Calendar Sync</h1>
      <router-link class="button ghost" to="/admin">Back to Admin</router-link>
    </div>

    <section class="calendar-panel">
      <div class="calendar-panel-head">
        <div>
          <h2>Calendar Preview</h2>
          <p>Live month view of the rental calendar.</p>
        </div>
        <button class="button ghost small" type="button" @click="refreshPreview">
          Refresh preview
        </button>
      </div>
      <iframe
        :key="previewKey"
        class="gcal-frame"
        :src="gcalUrl"
        title="CarEase Google Calendar"
        loading="lazy"
        referrerpolicy="no-referrer"
      ></iframe>
      <p class="gcal-hint">
        If the month view stays empty, share the calendar with
        <strong>Anyone with the link&nbsp;&rarr;&nbsp;Viewer</strong> in Google Calendar settings,
        then reload this page.
      </p>
    </section>

    <section class="panel">
      <p>
        <strong>Google connection:</strong>
        {{ connected ? 'Authorized (token saved)' : 'Not connected' }}
      </p>
      <p>
        <a class="button" :href="connectUrl">
          {{ connected ? 'Reconnect Google Calendar' : 'Connect Google Calendar' }}
        </a>
        <a class="button ghost" :href="loginUrl" target="_blank" rel="noopener">
          PHP admin login
        </a>
      </p>
      <p>
        Approved bookings only. Click Sync to create an all-day event in the rental calendar.
        Repeating Sync will not create a duplicate event.
      </p>
      <p v-if="message" role="status">{{ message }}</p>
      <p v-if="cleanupDetails">{{ cleanupDetails }}</p>

      <div v-if="connected && backendReady" class="panel">
        <h3>Calendar Cleanup</h3>
        <p>Remove only the 23 previously reviewed CarEase events whose bookings no longer exist in the database. The server checks each booking again before deletion.</p>
        <button
          class="button"
          type="button"
          :disabled="busyCleanup || busyAll || busyId !== null || !csrf"
          @click="cleanupCalendar"
        >
          {{ busyCleanup ? 'Cleaning up...' : 'Delete Old Calendar Events' }}
        </button>
      </div>

      <p v-if="connected">
        <button class="button" type="button" :disabled="busyAll || busyCleanup" @click="syncAll">
          {{ busyAll ? 'Syncing…' : 'Sync all bookings' }}
        </button>
      </p>
      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>Booking</th>
              <th>Client</th>
              <th>Pickup</th>
              <th>Return</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="booking in syncable" :key="booking.id">
              <td>#{{ booking.id }}</td>
              <td>{{ booking.customer_name }}</td>
              <td>{{ booking.pickup_date }}</td>
              <td>{{ booking.return_date }}</td>
              <td>{{ booking.status }}</td>
              <td>
                <form @submit.prevent="sync(booking.id)">
                  <button
                    class="button small"
                    type="submit"
                    :disabled="!connected || busyId === booking.id || busyCleanup"
                  >
                    {{ busyId === booking.id ? 'Syncing…' : 'Sync to Google Calendar' }}
                  </button>
                </form>
              </td>
            </tr>
            <tr v-if="syncable.length === 0">
              <td colspan="6">No approved bookings to sync yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppShell>
</template>
