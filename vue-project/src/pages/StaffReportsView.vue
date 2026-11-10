
<script setup lang="ts">
import {
  computed,
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'
import Chart from 'chart.js/auto'
import AppShell from '@/components/AppShell.vue'
import {
  bookings,
  loadAdminBookings,
} from '@/stores/carease'
import { peso } from '@/lib/format'

const loading = ref(false)
const error = ref('')
const exportError = ref('')

const filterStart = ref('')
const filterEnd = ref('')

const statusCanvas = ref<HTMLCanvasElement | null>(null)
const monthlyCanvas = ref<HTMLCanvasElement | null>(null)
const valueCanvas = ref<HTMLCanvasElement | null>(null)

const statusKeys = [
  'pending',
  'approved',
  'confirmed',
  'ongoing',
  'overdue',
  'completed',
  'rejected',
  'cancelled',
]

const counts = computed(() =>
  statusKeys.map(
    status =>
      bookings.value.filter(
        b => b.status === status
      ).length
  )
)

const pending = computed(() =>
  bookings.value.filter(
    b => b.status === 'pending'
  ).length
)

const completed = computed(() =>
  bookings.value.filter(
    b => b.status === 'completed'
  ).length
)

const bookingValue = computed(() =>
  bookings.value
    .filter(b =>
      [
        'approved',
        'confirmed',
        'ongoing',
        'overdue',
        'completed',
      ].includes(b.status)
    )
    .reduce(
      (sum, b) =>
        sum + Number(b.total_amount || 0),
      0
    )
)

const months = computed(() => {
  const now = new Date()

  return Array.from(
    { length: 6 },
    (_, i) => {
      const date = new Date(
        now.getFullYear(),
        now.getMonth() - (5 - i),
        1
      )

      const key = `${date.getFullYear()}-${String(
        date.getMonth() + 1
      ).padStart(2, '0')}`

      const rows = bookings.value.filter(
        b =>
          String(b.created_at ?? '').slice(0, 7) === key
      )

      return {
        label: date.toLocaleDateString(
          'en-US',
          {
            month: 'short',
            year: 'numeric',
          }
        ),

        count: rows.length,

        value: rows
          .filter(b =>
            [
              'approved',
              'confirmed',
              'ongoing',
              'overdue',
              'completed',
            ].includes(b.status)
          )
          .reduce(
            (sum, b) =>
              sum + Number(b.total_amount || 0),
            0
          ),
      }
    }
  )
})

let charts: Chart[] = []

function drawCharts() {
  charts.forEach(chart => chart.destroy())
  charts = []

  const colors = [
    '#f28a35',
    '#3577b5',
    '#5a9d94',
    '#8b6cc1',
    '#e06c75',
    '#3fa7a0',
    '#6b7d90',
    '#c48b2c',
  ]

  if (statusCanvas.value) {
    charts.push(
      new Chart(statusCanvas.value, {
        type: 'doughnut',
        data: {
          labels: statusKeys.map(
            s =>
              s.charAt(0).toUpperCase() +
              s.slice(1)
          ),
          datasets: [
            {
              data: counts.value,
              backgroundColor: colors,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
        },
      })
    )
  }

  if (monthlyCanvas.value) {
    charts.push(
      new Chart(monthlyCanvas.value, {
        type: 'bar',
        data: {
          labels: months.value.map(
            m => m.label
          ),
          datasets: [
            {
              label: 'Bookings',
              data: months.value.map(
                m => m.count
              ),
              backgroundColor: '#f28a35',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
        },
      })
    )
  }

  if (valueCanvas.value) {
    charts.push(
      new Chart(valueCanvas.value, {
        type: 'line',
        data: {
          labels: months.value.map(
            m => m.label
          ),
          datasets: [
            {
              label: 'Booking Value (PHP)',
              data: months.value.map(
                m => m.value
              ),
              borderColor: '#3577b5',
              backgroundColor: '#3577b5',
              tension: 0.25,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
        },
      })
    )
  }
}

watch(
  bookings,
  async () => {
    await nextTick()
    drawCharts()
  },
  { deep: true }
)

async function refreshReports() {
  if (loading.value) return

  loading.value = true
  error.value = ''

  try {
    const result = await loadAdminBookings()

    if (result) {
      error.value = result
    }

    await nextTick()
    drawCharts()
  } catch (e) {
    error.value =
      e instanceof Error
        ? e.message
        : 'Unable to load reports.'
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  exportError.value = ''

  if (
    filterStart.value &&
    filterEnd.value &&
    filterStart.value > filterEnd.value
  ) {
    exportError.value =
      'From date must not be after To date.'
    return
  }

  const rows = bookings.value.filter(b => {
    const date = String(
      b.created_at ?? ''
    ).slice(0, 10)

    return (
      (!filterStart.value ||
        date >= filterStart.value) &&
      (!filterEnd.value ||
        date <= filterEnd.value)
    )
  })

  const fields = [
    'id',
    'customer_name',
    'email',
    'car_id',
    'pickup_date',
    'return_date',
    'total_amount',
    'status',
  ] as const

  const csv = [
    fields.join(','),
    ...rows.map(b =>
      fields
        .map(
          k =>
            '"' +
            String(b[k] ?? '').replace(
              /"/g,
              '""'
            ) +
            '"'
        )
        .join(',')
    ),
  ].join('\r\n')

  const url = URL.createObjectURL(
    new Blob(
      ['\uFEFF', csv],
      { type: 'text/csv;charset=utf-8' }
    )
  )

  const link = document.createElement('a')

  link.href = url
  link.download = 'carease-staff-bookings.csv'
  link.click()

  URL.revokeObjectURL(url)
}

onMounted(() => {
  void refreshReports()
})

onUnmounted(() => {
  charts.forEach(chart => chart.destroy())
  charts = []
})
</script>

<template>
  <AppShell
    shell="staff"
    nav-key="reports"
    title="Reports & Charts"
    subtitle="Booking reports and performance"
  >
    <div class="heading">
      <h1>CarEase Reports &amp; Charts</h1>

      <div class="actions">
        <RouterLink
          class="button ghost"
          to="/staff/bookings"
        >
          Back to Dashboard
        </RouterLink>

        <button
          class="button"
          type="button"
          :disabled="loading"
          @click="refreshReports"
        >
          {{
            loading
              ? 'Loading...'
              : 'Refresh Reports'
          }}
        </button>
      </div>
    </div>

    <p
      v-if="error"
      class="error"
      role="alert"
    >
      {{ error }}
    </p>

    <div class="ce-report-stats">
      <div class="ce-report-stat">
        <strong>{{ bookings.length }}</strong>
        <span>Total Bookings</span>
      </div>

      <div class="ce-report-stat">
        <strong>{{ pending }}</strong>
        <span>Pending Bookings</span>
      </div>

      <div class="ce-report-stat">
        <strong>{{ completed }}</strong>
        <span>Completed Bookings</span>
      </div>

      <div class="ce-report-stat">
        <strong>
          {{ peso(bookingValue, 2) }}
        </strong>

        <span>
          Approved / Active / Completed
          Booking Value (not paid revenue)
        </span>
      </div>
    </div>

    <div class="ce-report-grid">
      <section class="ce-report-card">
        <h3>1. Booking Status</h3>

        <div class="ce-report-chart">
          <canvas ref="statusCanvas"></canvas>
        </div>
      </section>

      <section class="ce-report-card">
        <h3>2. Monthly Bookings</h3>

        <div class="ce-report-chart">
          <canvas ref="monthlyCanvas"></canvas>
        </div>
      </section>

      <section class="ce-report-card">
        <h3>
          3. Monthly Booking Value
          (Not Paid Revenue)
        </h3>

        <div class="ce-report-chart">
          <canvas ref="valueCanvas"></canvas>
        </div>
      </section>
    </div>

    <section class="panel">
      <h2>Booking Report</h2>

      <p>
        Export booking records as CSV.
        Date filters use booking creation date.
      </p>

      <p
        v-if="exportError"
        class="error"
      >
        {{ exportError }}
      </p>

      <form
        class="ce-report-filter"
        @submit.prevent="exportCsv"
      >
        <label>
          From
          <input
            v-model="filterStart"
            type="date"
          />
        </label>

        <label>
          To
          <input
            v-model="filterEnd"
            type="date"
          />
        </label>

        <button
          class="button"
          type="submit"
        >
          Export CSV
        </button>
      </form>

      <div class="scroll">
        <table class="ce-report-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Pickup</th>
              <th>Return</th>
              <th>Booking Value</th>
              <th>Status</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="booking in bookings"
              :key="booking.id"
            >
              <td>{{ booking.id }}</td>
              <td>{{ booking.customer_name }}</td>
              <td>{{ booking.pickup_date }}</td>
              <td>{{ booking.return_date }}</td>

              <td>
                {{ peso(booking.total_amount, 2) }}
              </td>

              <td>{{ booking.status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.heading,
.actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.heading {
  margin-bottom: 22px;
}

.heading h1 {
  margin: 0;
  font-size: 24px;
}

.ce-report-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.ce-report-stat,
.ce-report-card,
.panel {
  background: var(--surface, #fff);
  border: 1px solid var(--border, #dce3ed);
  border-radius: 14px;
  padding: 20px;
}

.ce-report-stat strong {
  display: block;
  color: var(--accent, #ed7a28);
  font-size: 26px;
  overflow-wrap: anywhere;
}

.ce-report-stat span {
  display: block;
  margin-top: 7px;
  font-size: 13px;
  color: var(--muted, #64748b);
}

.ce-report-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 24px;
}

.ce-report-card h3 {
  font-size: 16px;
  margin: 0 0 16px;
}

.ce-report-chart {
  height: 285px;
  position: relative;
}

.panel h2 {
  margin: 0 0 8px;
}

.ce-report-filter {
  display: flex;
  gap: 12px;
  align-items: end;
  flex-wrap: wrap;
  margin: 16px 0;
}

.ce-report-filter label {
  display: grid;
  gap: 6px;
  font-size: 13px;
}

.ce-report-filter input {
  padding: 9px;
  border: 1px solid #dbe0e7;
  border-radius: 7px;
}

.scroll {
  overflow-x: auto;
}

.ce-report-table {
  width: 100%;
  border-collapse: collapse;
}

.ce-report-table th,
.ce-report-table td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  font-size: 13px;
}

.button {
  display: inline-block;
  padding: 10px 14px;
  background: #ed7a28;
  color: white;
  border: 1px solid #ed7a28;
  border-radius: 8px;
  text-decoration: none;
  cursor: pointer;
}

.button.ghost {
  background: transparent;
  color: inherit;
  border-color: #cbd5e1;
}

.error {
  padding: 12px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
}

@media (max-width: 1000px) {
  .ce-report-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 700px) {
  .ce-report-stats,
  .ce-report-grid {
    grid-template-columns: 1fr;
  }
}
</style>
