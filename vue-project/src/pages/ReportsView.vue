<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
import {
  bookings,
  cars,
  maintenanceRecords,
} from '@/stores/carease'
import type { BookingStatus } from '@/data/types'
import { useTheme } from '@/composables/useTheme'
import { peso } from '@/lib/format'
import AppShell from '@/components/AppShell.vue'

const { isDark } = useTheme()
const reportYear = ref(new Date().getFullYear())

const statusKeys: BookingStatus[] = [
  'pending',
  'approved',
  'confirmed',
  'ongoing',
  'overdue',
  'completed',
  'rejected',
  'cancelled',
]

const report = computed(() => {
  const carRows = cars.value
  const bookingRows = bookings.value
  const maintenanceRows = maintenanceRecords.value

  const months: Array<{
    key: string
    label: string
    bookings: number
    value: number
    maintenance: number
  }> = []
  const now = new Date()
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    months.push({
      key,
      label: d.toLocaleString('en-US', { month: 'short', year: 'numeric' }),
      bookings: 0,
      value: 0,
      maintenance: 0,
    })
  }

  const statusCounts: Record<string, number> = {}
  for (const key of statusKeys) statusCounts[key] = 0

  let totalValue = 0
  const valueStatuses = ['approved', 'confirmed', 'ongoing', 'overdue', 'completed']
  for (const booking of bookingRows) {
    if (statusCounts[booking.status] !== undefined) {
      statusCounts[booking.status] = (statusCounts[booking.status] ?? 0) + 1
    }
    const monthKey = String(booking.created_at ?? '').slice(0, 7)
    const month = months.find((m) => m.key === monthKey)
    if (month) month.bookings += 1
    if (valueStatuses.includes(booking.status)) {
      const amount = Number(booking.total_amount ?? 0)
      totalValue += amount
      if (month) month.value += amount
    }
  }

  let available = 0
  let underMaintenance = 0
  for (const car of carRows) {
    if (car.status === 'available') available += 1
    if (car.status === 'maintenance') underMaintenance += 1
  }

  let maintenanceTotal = 0
  for (const record of maintenanceRows) {
    if (record.status !== 'completed') continue
    const cost = Number(record.cost ?? 0)
    maintenanceTotal += cost
    const monthKey = String(record.scheduled_date ?? '').slice(0, 7)
    const month = months.find((m) => m.key === monthKey)
    if (month) month.maintenance += cost
  }

  return {
    months,
    statusLabels: statusKeys.map((k) => k.charAt(0).toUpperCase() + k.slice(1)),
    statusCounts: statusKeys.map((k) => statusCounts[k] ?? 0),
    bookingsSeries: months.map((m) => m.bookings),
    valueSeries: months.map((m) => Math.round(m.value * 100) / 100),
    costSeries: months.map((m) => Math.round(m.maintenance * 100) / 100),
    available,
    underMaintenance,
    otherCars: Math.max(0, carRows.length - available - underMaintenance),
    totalValue,
    maintenanceTotal,
  }
})

const statusCanvas = ref<HTMLCanvasElement | null>(null)
const fleetCanvas = ref<HTMLCanvasElement | null>(null)
const bookingsCanvas = ref<HTMLCanvasElement | null>(null)
const valueCanvas = ref<HTMLCanvasElement | null>(null)
const costCanvas = ref<HTMLCanvasElement | null>(null)
const comboCanvas = ref<HTMLCanvasElement | null>(null)

let charts: Chart[] = []

function pesoLabel(v: number): string {
  return '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 })
}

function destroyCharts() {
  for (const chart of charts) chart.destroy()
  charts = []
}

function buildCharts() {
  destroyCharts()
  const data = report.value
  const dark = isDark.value
  const textColor = dark ? '#9fb3c8' : '#53677a'
  const gridColor = dark ? 'rgba(255, 255, 255, 0.08)' : '#eef3f8'
  const borderColor = dark ? '#1c2733' : '#fff'
  const palette = [
    '#3577b5',
    '#f0a04b',
    '#5a9d94',
    '#8b6cc1',
    '#e06c75',
    '#6b7d90',
    '#3fa7a0',
    '#c48b2c',
  ]

  Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif"
  Chart.defaults.color = textColor

  if (statusCanvas.value) {
    charts.push(
      new Chart(statusCanvas.value, {
        type: 'doughnut',
        data: {
          labels: data.statusLabels,
          datasets: [
            {
              data: data.statusCounts,
              backgroundColor: palette,
              borderColor,
              borderWidth: 2,
              hoverOffset: 10,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '55%',
          plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } },
            tooltip: {
              callbacks: {
                label: (ctx: any) => {
                  const values = ctx.dataset.data as number[]
                  const total = values.reduce((a, b) => a + b, 0) || 1
                  const pct = Math.round((ctx.parsed / total) * 100)
                  return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)'
                },
              },
            },
          },
        },
      } as any),
    )
  }

  if (fleetCanvas.value) {
    charts.push(
      new Chart(fleetCanvas.value, {
        type: 'pie',
        data: {
          labels: ['Available', 'Under Maintenance', 'Other'],
          datasets: [
            {
              data: [data.available, data.underMaintenance, data.otherCars],
              backgroundColor: ['#5a9d94', '#f0a04b', '#6b7d90'],
              borderColor,
              borderWidth: 2,
              hoverOffset: 10,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } },
            tooltip: {
              callbacks: {
                label: (ctx: any) => {
                  const values = ctx.dataset.data as number[]
                  const total = values.reduce((a, b) => a + b, 0) || 1
                  const pct = Math.round((ctx.parsed / total) * 100)
                  return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)'
                },
              },
            },
          },
        },
      } as any),
    )
  }

  if (bookingsCanvas.value) {
    charts.push(
      new Chart(bookingsCanvas.value, {
        type: 'bar',
        data: {
          labels: data.months.map((m) => m.label),
          datasets: [
            {
              label: 'Bookings',
              data: data.bookingsSeries,
              backgroundColor: 'rgba(53, 119, 181, 0.75)',
              hoverBackgroundColor: '#3577b5',
              borderRadius: 6,
              maxBarThickness: 42,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
            x: { grid: { display: false } },
          },
        },
      } as any),
    )
  }

  if (valueCanvas.value) {
    charts.push(
      new Chart(valueCanvas.value, {
        type: 'line',
        data: {
          labels: data.months.map((m) => m.label),
          datasets: [
            {
              label: 'Booking Value',
              data: data.valueSeries,
              borderColor: '#5a9d94',
              backgroundColor: 'rgba(90, 157, 148, 0.15)',
              fill: true,
              tension: 0.35,
              pointBackgroundColor: '#5a9d94',
              pointBorderColor: borderColor,
              pointBorderWidth: 2,
              pointRadius: 5,
              pointHoverRadius: 7,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: { label: (ctx: any) => ' ' + pesoLabel(ctx.parsed.y) },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: gridColor },
              ticks: { callback: (v: any) => '₱' + v / 1000 + 'k' },
            },
            x: { grid: { display: false } },
          },
        },
      } as any),
    )
  }

  if (costCanvas.value) {
    charts.push(
      new Chart(costCanvas.value, {
        type: 'bar',
        data: {
          labels: data.months.map((m) => m.label),
          datasets: [
            {
              label: 'Maintenance Cost',
              data: data.costSeries,
              backgroundColor: 'rgba(240, 160, 75, 0.8)',
              hoverBackgroundColor: '#f0a04b',
              borderRadius: 6,
              maxBarThickness: 42,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: { label: (ctx: any) => ' ' + pesoLabel(ctx.parsed.y) },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: gridColor },
              ticks: { callback: (v: any) => '₱' + v / 1000 + 'k' },
            },
            x: { grid: { display: false } },
          },
        },
      } as any),
    )
  }

  if (comboCanvas.value) {
    charts.push(
      new Chart(comboCanvas.value, {
        type: 'bar',
        data: {
          labels: data.months.map((m) => m.label),
          datasets: [
            {
              type: 'bar',
              label: 'Bookings',
              data: data.bookingsSeries,
              backgroundColor: 'rgba(53, 119, 181, 0.7)',
              hoverBackgroundColor: '#3577b5',
              borderRadius: 6,
              maxBarThickness: 36,
              yAxisID: 'y',
            },
            {
              type: 'line',
              label: 'Booking Value',
              data: data.valueSeries,
              borderColor: '#8b6cc1',
              backgroundColor: 'rgba(139, 108, 193, 0.12)',
              fill: true,
              tension: 0.35,
              pointBackgroundColor: '#8b6cc1',
              pointBorderColor: borderColor,
              pointBorderWidth: 2,
              pointRadius: 4,
              yAxisID: 'y1',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              position: 'top',
              labels: { boxWidth: 12, usePointStyle: true },
            },
            tooltip: {
              callbacks: {
                label: (ctx: any) =>
                  ctx.dataset.yAxisID === 'y1'
                    ? ' Value: ' + pesoLabel(ctx.parsed.y)
                    : ' Bookings: ' + ctx.parsed.y,
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              position: 'left',
              ticks: { precision: 0 },
              title: { display: true, text: 'Bookings' },
              grid: { color: gridColor },
            },
            y1: {
              beginAtZero: true,
              position: 'right',
              title: { display: true, text: 'Value' },
              grid: { drawOnChartArea: false },
              ticks: { callback: (v: any) => '₱' + v / 1000 + 'k' },
            },
            x: { grid: { display: false } },
          },
        },
      } as any),
    )
  }
}

onMounted(buildCharts)
onBeforeUnmount(destroyCharts)
watch(isDark, buildCharts)

const filterStart = ref('')
const filterEnd = ref('')
const exportError = ref('')

function isValidDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const parts = value.split('-')
  const y = Number(parts[0])
  const m = Number(parts[1])
  const d = Number(parts[2])
  const date = new Date(y, m - 1, d)
  return (
    date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d
  )
}

function csvCell(value: unknown): string {
  let text = String(value ?? '')
  if (/^[\s]*[=+\-@]/u.test(text)) text = "'" + text
  return '"' + text.replace(/"/g, '""') + '"'
}

function exportCsv() {
  exportError.value = ''
  const start = filterStart.value
  const end = filterEnd.value
  for (const date of [start, end]) {
    if (date !== '' && !isValidDate(date)) {
      exportError.value = 'Invalid date filter.'
      return
    }
  }
  if (start !== '' && end !== '' && start > end) {
    exportError.value = 'Invalid date range.'
    return
  }

  const header = [
    'Booking ID',
    'Customer',
    'Email',
    'Car ID',
    'Pickup',
    'Return',
    'Booking Value PHP',
    'Status',
    'Created At',
  ]
  const rows: unknown[][] = [header]
  for (const booking of bookings.value) {
    const created = String(booking.created_at ?? '').slice(0, 10)
    if (start !== '' && created < start) continue
    if (end !== '' && created > end) continue
    rows.push([
      booking.id,
      booking.customer_name,
      booking.email,
      booking.car_id,
      booking.pickup_date,
      booking.return_date,
      booking.total_amount,
      booking.status,
      booking.created_at,
    ])
  }

  const csv = rows.map((row) => row.map(csvCell).join(',')).join('\r\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const now = new Date()
  const stamp = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(
    now.getDate(),
  ).padStart(2, '0')}`
  const link = document.createElement('a')
  link.href = url
  link.download = `carease-bookings-${stamp}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <AppShell
    shell="admin"
    nav-key="reports"
    title="Reports &amp; Charts"
    subtitle="Bookings, revenue and fleet utilisation"
  >
    <div class="heading">
      <h1>CarEase Reports &amp; Charts</h1>
      <div class="ce-report-actions">
        <label class="ce-year-picker">Report year <input v-model.number="reportYear" type="number" min="2020" max="2100" step="1" /></label>
        <a class="button ce-pdf-button" :href="`/carease/admin/reports/annual-pdf?year=${reportYear}`" target="_blank" rel="noopener noreferrer">View Annual PDF</a>
        <router-link class="button" to="/admin">Back to Admin</router-link>
      </div>
    </div>

    <div class="ce-report-stats">
      <div class="ce-report-stat">
        <strong>{{ bookings.length }}</strong><span>Total Bookings</span>
      </div>
      <div class="ce-report-stat">
        <strong>{{ cars.length }}</strong><span>Fleet Vehicles</span>
      </div>
      <div class="ce-report-stat">
        <strong>₱{{ report.totalValue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
        <span>Approved/Active/Completed Booking Value (not paid revenue)</span>
      </div>
      <div class="ce-report-stat">
        <strong>₱{{ report.maintenanceTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
        <span>Completed Maintenance Costs</span>
      </div>
    </div>

    <div class="ce-report-grid">
      <section class="ce-report-card">
        <h3>1. Booking Status</h3>
        <div class="ce-report-chart tall">
          <canvas ref="statusCanvas" id="ceStatusChart"></canvas>
        </div>
      </section>
      <section class="ce-report-card">
        <h3>2. Vehicle Availability</h3>
        <div class="ce-report-chart tall">
          <canvas ref="fleetCanvas" id="ceFleetChart"></canvas>
        </div>
      </section>
      <section class="ce-report-card">
        <h3>3. Monthly Bookings</h3>
        <div class="ce-report-chart">
          <canvas ref="bookingsCanvas" id="ceBookingsChart"></canvas>
        </div>
      </section>
      <section class="ce-report-card">
        <h3>4. Monthly Booking Value (Not Paid Revenue)</h3>
        <div class="ce-report-chart">
          <canvas ref="valueCanvas" id="ceValueChart"></canvas>
        </div>
      </section>
      <section class="ce-report-card">
        <h3>5. Monthly Completed Maintenance Costs</h3>
        <div class="ce-report-chart">
          <canvas ref="costCanvas" id="ceCostChart"></canvas>
        </div>
      </section>
      <section class="ce-report-card">
        <h3>6. Bookings vs Booking Value</h3>
        <div class="ce-report-chart">
          <canvas ref="comboCanvas" id="ceComboChart"></canvas>
        </div>
      </section>
    </div>

    <section class="panel">
      <h2>Booking Report</h2>
      <p>Export booking records as CSV. Date filters apply to the booking creation date.</p>
      <p v-if="exportError" class="error">{{ exportError }}</p>
      <form class="ce-report-filter" @submit.prevent="exportCsv">
        <label>From<input v-model="filterStart" type="date" name="start" /></label>
        <label>To<input v-model="filterEnd" type="date" name="end" /></label>
        <button class="button" type="submit">Export CSV</button>
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
            <tr v-for="booking in bookings" :key="booking.id">
              <td>{{ booking.id }}</td>
              <td>{{ booking.customer_name }}</td>
              <td>{{ booking.pickup_date }}</td>
              <td>{{ booking.return_date }}</td>
              <td>{{ peso(booking.total_amount, 2) }}</td>
              <td>{{ booking.status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.ce-report-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.ce-pdf-button { background: linear-gradient(110deg, #0e365a, #167fc4) !important; color: #fff !important; border-color: #167fc4 !important; text-decoration: none; }
.ce-pdf-button:hover { filter: brightness(1.12); }
@media (max-width: 600px) { .ce-report-actions { width: 100%; } }
</style>

<style scoped>
.ce-year-picker { display: inline-flex; align-items: center; gap: 8px; color: inherit; font-size: 13px; font-weight: 600; }
.ce-year-picker input { width: 90px; padding: 8px 10px; border: 1px solid #aebdcc; border-radius: 8px; background: var(--surface, #fff); color: inherit; }
</style>
