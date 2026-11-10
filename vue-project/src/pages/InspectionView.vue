
<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  addInspection,
  loadBookingInspections,
  bookingById,
  carById,
  inspectionsForBooking,
} from '@/stores/carease'
import type {
  FuelLevel,
  InspectionType,
  VehicleCondition,
} from '@/data/types'
import AppShell from '@/components/AppShell.vue'

const route = useRoute()
const bookingId = Number(route.params.id)

const booking = computed(() => bookingById(bookingId))

const car = computed(() =>
  booking.value ? carById(booking.value.car_id) : null,
)

const title = 'Vehicle Inspection'
const subtitle = computed(() => `Booking #${bookingId}`)

const records = computed(() =>
  inspectionsForBooking(bookingId),
)

const preInspection = computed(
  () =>
    records.value.find(
      (r) => r.inspection_type === 'pre',
    ) ?? null,
)

const postInspection = computed(
  () =>
    records.value.find(
      (r) => r.inspection_type === 'post',
    ) ?? null,
)

const error = ref('')
const success = ref('')
const saving = ref(false)
const loading = ref(true)
onMounted(async () => {
  const loadError = await loadBookingInspections(bookingId)
  if (loadError) error.value = loadError
  loading.value = false
})

const form = reactive({
  fuel_level: 'full' as FuelLevel,
  mileage: '',
  vehicle_condition: 'good' as VehicleCondition,
  damage_notes: '',
})

function resetForm() {
  form.fuel_level = 'full'
  form.mileage = ''
  form.vehicle_condition = 'good'
  form.damage_notes = ''
}

function canShowForm(type: InspectionType): boolean {
  const status = booking.value?.status

  if (!status || loading.value || !!error.value) return false

  if (type === 'pre') {
    return ['approved', 'confirmed'].includes(status)
  }

  return ['ongoing', 'overdue'].includes(status)
}

async function save(type: InspectionType) {
  if (saving.value || loading.value || error.value) return
  success.value = ''
  error.value = ''

  const current = booking.value

  if (!current) {
    error.value = 'Booking not found.'
    return
  }

  const existing =
    type === 'pre'
      ? preInspection.value
      : postInspection.value

  if (!canShowForm(type) || existing) {
    error.value =
      'Inspection is not allowed at this stage or already exists.'
    return
  }

  const mileage = Number(form.mileage)
  const notes = form.damage_notes.trim()

  if (
    !['empty', 'quarter', 'half', 'three_quarter', 'full'].includes(
      form.fuel_level,
    ) ||
    !Number.isInteger(mileage) ||
    mileage < 0 ||
    mileage > 99999999 ||
    !['good', 'minor', 'major'].includes(
      form.vehicle_condition,
    ) ||
    notes.length > 2000
  ) {
    error.value = 'Please enter valid inspection details.'
    return
  }

  if (
    type === 'post' &&
    preInspection.value &&
    mileage < preInspection.value.mileage
  ) {
    error.value =
      'Return mileage cannot be lower than pickup mileage.'
    return
  }

  saving.value = true
  try {
  const saveError = await addInspection({
    booking_id: bookingId,
    inspection_type: type,
    fuel_level: form.fuel_level,
    mileage,
    vehicle_condition: form.vehicle_condition,
    damage_notes: notes,
  })

  if (saveError) {
    error.value = saveError
    return
  }
  success.value = 'Inspection saved to database successfully.'
  resetForm()
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppShell
    shell="admin"
    nav-key="bookings"
    :title="title"
    :subtitle="subtitle"
  >
    <template v-if="booking && car">
      <div class="heading">
        <h1>
          Vehicle Inspection — Booking #{{ booking.id }}
        </h1>

        <router-link
          class="button ghost"
          to="/admin"
        >
          Back to Admin
        </router-link>
      </div>

      <section class="panel">
        <p>
          Client: {{ booking.customer_name }} |
          Vehicle: {{ car.brand }} {{ car.name }} |
          Booking status: {{ booking.status }}
        </p>

        <p v-if="error" class="error" role="alert">
          {{ error }}
        </p>

        <p v-if="success" role="status">{{ success }}</p>

        <h2>Before Rental</h2>

        <template v-if="preInspection">
          <p>
            Recorded: {{ preInspection.inspected_at }} |
            Fuel: {{ preInspection.fuel_level }} |
            Mileage: {{ preInspection.mileage }} km |
            Condition: {{ preInspection.vehicle_condition }}
          </p>

          <p>
            Damage notes:
            {{ preInspection.damage_notes || 'None' }}
          </p>
        </template>

        <form
          v-else-if="canShowForm('pre')"
          @submit.prevent="save('pre')"
        >
          <div class="two">
            <label>
              Fuel Level

              <select
                v-model="form.fuel_level"
                name="fuel_level"
                required
              >
                <option value="full">Full</option>
                <option value="three_quarter">3/4</option>
                <option value="half">1/2</option>
                <option value="quarter">1/4</option>
                <option value="empty">Empty</option>
              </select>
            </label>

            <label>
              Mileage (km)

              <input
                v-model="form.mileage"
                type="number"
                name="mileage"
                min="0"
                max="99999999"
                required
              />
            </label>

            <label>
              Vehicle Condition

              <select
                v-model="form.vehicle_condition"
                name="vehicle_condition"
                required
              >
                <option value="good">Good</option>
                <option value="minor">Minor Damage</option>
                <option value="major">Major Damage</option>
              </select>
            </label>

            <label>
              Damage Notes

              <textarea
                v-model="form.damage_notes"
                name="damage_notes"
                maxlength="2000"
                placeholder="Describe any damage"
              ></textarea>
            </label>
          </div>

          <button class="button" type="submit" :disabled="saving">
            Save Before Rental Inspection
          </button>
        </form>

        <p v-else>
          Inspection form will be available at the
          appropriate rental stage.
        </p>

        <h2>After Return</h2>

        <template v-if="postInspection">
          <p>
            Recorded: {{ postInspection.inspected_at }} |
            Fuel: {{ postInspection.fuel_level }} |
            Mileage: {{ postInspection.mileage }} km |
            Condition: {{ postInspection.vehicle_condition }}
          </p>

          <p>
            Damage notes:
            {{ postInspection.damage_notes || 'None' }}
          </p>
        </template>

        <form
          v-else-if="canShowForm('post')"
          @submit.prevent="save('post')"
        >
          <div class="two">
            <label>
              Fuel Level

              <select
                v-model="form.fuel_level"
                name="fuel_level"
                required
              >
                <option value="full">Full</option>
                <option value="three_quarter">3/4</option>
                <option value="half">1/2</option>
                <option value="quarter">1/4</option>
                <option value="empty">Empty</option>
              </select>
            </label>

            <label>
              Mileage (km)

              <input
                v-model="form.mileage"
                type="number"
                name="mileage"
                min="0"
                max="99999999"
                required
              />
            </label>

            <label>
              Vehicle Condition

              <select
                v-model="form.vehicle_condition"
                name="vehicle_condition"
                required
              >
                <option value="good">Good</option>
                <option value="minor">Minor Damage</option>
                <option value="major">Major Damage</option>
              </select>
            </label>

            <label>
              Damage Notes

              <textarea
                v-model="form.damage_notes"
                name="damage_notes"
                maxlength="2000"
                placeholder="Describe any damage"
              ></textarea>
            </label>
          </div>

          <button class="button" type="submit" :disabled="saving">
            Save After Return Inspection
          </button>
        </form>

        <p v-else>
          Inspection form will be available at the
          appropriate rental stage.
        </p>
      </section>
    </template>

    <section v-else class="panel">
      <p class="error">
        Booking not found.
      </p>

      <router-link
        class="button"
        to="/admin"
      >
        Back to Admin
      </router-link>
    </section>
  </AppShell>
</template>
