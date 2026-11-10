
<script setup lang="ts">
import { ref } from 'vue'
import {
  addMaintenance,
  carById,
  cars,
  maintenanceRecords,
  updateMaintenanceStatus,
} from '@/stores/carease'
import type { Maintenance } from '@/data/types'
import { peso } from '@/lib/format'
import AppShell from '@/components/AppShell.vue'

const notice = ref('')
const carId = ref('')
const maintenanceType = ref('')
const scheduledDate = ref('')
const cost = ref('0')
const notes = ref('')

function resetForm() {
  carId.value = ''
  maintenanceType.value = ''
  scheduledDate.value = ''
  cost.value = '0'
  notes.value = ''
}

function handleSubmit() {
  notice.value = ''

  const id = Number(carId.value)
  const type = maintenanceType.value.trim()
  const date = scheduledDate.value
  const costNumber = Number(cost.value)
  const noteText = notes.value.trim()

  if (
    !id ||
    !carById(id) ||
    type === '' ||
    type.length > 100 ||
    !/^\d{4}-\d{2}-\d{2}$/.test(date) ||
    !Number.isFinite(costNumber) ||
    costNumber < 0 ||
    costNumber > 9999999999.99 ||
    noteText.length > 2000
  ) {
    notice.value = 'Invalid maintenance details.'
    return
  }

  addMaintenance({
    car_id: id,
    maintenance_type: type,
    scheduled_date: date,
    cost: costNumber.toFixed(2),
    status: 'scheduled',
    notes: noteText,
  })

  notice.value = 'Maintenance record created.'
  resetForm()
}

function nextStatus(
  status: Maintenance['status'],
): Maintenance['status'] | null {
  if (status === 'scheduled') {
    return 'in_progress'
  }

  if (status === 'in_progress') {
    return 'completed'
  }

  return null
}

function advance(record: Maintenance) {
  const next = nextStatus(record.status)

  if (!next) return

  const error = updateMaintenanceStatus(
    record.id,
    next,
  )

  notice.value =
    error ?? 'Maintenance status updated.'
}
</script>

<template>
  <AppShell
    shell="admin"
    nav-key="maintenance"
    title="Vehicle Maintenance"
    subtitle="Schedule and track servicing for the fleet"
  >
    <div class="heading">
      <h1>Vehicle Maintenance</h1>

      <router-link
        class="button ghost"
        to="/admin"
      >
        Back to Admin
      </router-link>
    </div>

    <p v-if="notice" role="status">
      {{ notice }}
    </p>

    <section class="panel">
      <h2>Schedule Maintenance</h2>

      <form @submit.prevent="handleSubmit">
        <div class="two">
          <label>
            Vehicle

            <select
              v-model="carId"
              name="car_id"
              required
            >
              <option value="">
                Choose vehicle
              </option>

              <option
                v-for="car in cars"
                :key="car.id"
                :value="String(car.id)"
              >
                {{ car.brand }} {{ car.name }}
              </option>
            </select>
          </label>

          <label>
            Maintenance Type

            <input
              v-model="maintenanceType"
              name="maintenance_type"
              maxlength="100"
              required
              placeholder="Oil Change"
            />
          </label>

          <label>
            Scheduled Date

            <input
              v-model="scheduledDate"
              type="date"
              name="scheduled_date"
              required
            />
          </label>

          <label>
            Cost (PHP)

            <input
              v-model="cost"
              type="number"
              name="cost"
              min="0"
              max="9999999999.99"
              step="0.01"
              required
            />
          </label>
        </div>

        <label>
          Notes

          <textarea
            v-model="notes"
            name="notes"
            maxlength="2000"
            rows="3"
          ></textarea>
        </label>

        <button
          class="button"
          type="submit"
        >
          Add Maintenance
        </button>
      </form>
    </section>

    <section class="panel">
      <h2>Maintenance History</h2>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Car ID</th>
              <th>Type</th>
              <th>Date</th>
              <th>Cost</th>
              <th>Notes</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="record in maintenanceRecords"
              :key="record.id"
            >
              <td>{{ record.id }}</td>
              <td>{{ record.car_id }}</td>
              <td>{{ record.maintenance_type }}</td>
              <td>{{ record.scheduled_date }}</td>

              <td>
                {{ peso(record.cost, 2) }}
              </td>

              <td>
                {{ record.notes ?? '' }}
              </td>

              <td>
                {{ record.status }}
              </td>

              <td>
                <button
                  v-if="nextStatus(record.status)"
                  class="button small"
                  type="button"
                  @click="advance(record)"
                >
                  {{
                    nextStatus(record.status) === 'in_progress'
                      ? 'Start Maintenance'
                      : 'Mark Completed'
                  }}
                </button>

                <template v-else>
                  Done
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AppShell>
</template>
