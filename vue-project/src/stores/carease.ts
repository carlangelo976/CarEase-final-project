import { computed, reactive } from 'vue'
import { createSeedState } from '@/data/seed'
import type {
  Booking,
  BookingStatus,
  Car,
  CareaseState,
  CareaseUser,
  Inspection,
  Maintenance,
  Payment,
  InspectionType,
  FuelLevel,
  VehicleCondition,
  MaintenanceStatus,
} from '@/data/types'
import { todayISO } from '@/lib/format'
import { api } from '../services/api'
const STORAGE_KEY = 'carease.state.v1'
function loadState(): CareaseState {
  const seed = createSeedState()
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw) as Partial<CareaseState>
      if (
        parsed &&
        Array.isArray(parsed.users) &&
        Array.isArray(parsed.cars)
      ) {
        return {
          ...seed,
          ...parsed,
          users: parsed.users.map(user => ({
            ...user,
            password_hash: '',
          })),
          cars: parsed.cars,
          bookings: parsed.bookings ?? seed.bookings,
          payments: parsed.payments ?? seed.payments,
          inspections: parsed.inspections ?? seed.inspections,
          maintenance: parsed.maintenance ?? seed.maintenance,
          session: { userId: null },
        }
      }
    }
  } catch {
    // Use default state
  }
  return {
    ...seed,
    users: [],
    session: { userId: null },
  }
}
export const state = reactive<CareaseState>(loadState())
function persist() {
  try {
    const saved = {
      ...state,
      users: state.users.map(user => ({
        ...user,
        password_hash: '',
      })),
    }
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify(saved),
    )
  } catch {
    // Keep state in memory
  }
}
function nextId(rows: Array<{ id: number }>): number {
  return rows.reduce(
    (max, row) => Math.max(max, Number(row.id)),
    0,
  ) + 1
}
// AUTHENTICATION
export const currentUser = computed<CareaseUser | null>(
  () =>
    state.users.find(
      user => user.id === state.session.userId,
    ) ?? null,
)
export const isAuthenticated = computed(
  () => currentUser.value !== null,
)
export const isAdmin = computed(
  () => currentUser.value?.role === 'admin',
)
export const isStaff = computed(
  () => currentUser.value?.role === 'staff',
)
export const canManageBookings = computed(
  () => isAdmin.value || isStaff.value,
)
export const currentName = computed(
  () => currentUser.value?.full_name ?? null,
)
interface ServerUser {
  id: number | string
  name?: string
  full_name?: string
  email: string
  role: 'admin' | 'client' | 'staff'
}
function setAuthenticatedUser(user: ServerUser): boolean {
  const id = Number(user.id)
  if (!Number.isInteger(id) || id <= 0) {
    return false
  }
  const fullName =
    user.full_name || user.name || user.email
  const existing = state.users.find(
    item => item.id === id,
  )
  if (existing) {
    existing.full_name = fullName
    existing.email = user.email
    existing.role = user.role
    existing.password_hash = ''
  } else {
    state.users.push({
      id,
      full_name: fullName,
      email: user.email,
      role: user.role,
      password_hash: '',
      created_at: new Date().toISOString(),
    })
  }
  state.session.userId = id
  persist()
  return true
}
export async function login(
  email: string,
  password: string,
): Promise<string | null> {
  try {
    const result = await api('/login', {
      email: email.trim().toLowerCase(),
      password,
    })
    if (!result.user) {
      return 'Invalid email or password.'
    }
    const success = setAuthenticatedUser(
      result.user as ServerUser,
    )
    if (!success) {
      return 'Invalid user returned by server.'
    }
    return null
  } catch (error: unknown) {
    return error instanceof Error
      ? error.message
      : 'Unable to connect to the server.'
  }
}
export async function restoreSession(): Promise<void> {
  try {
    const result = await api('/session')
    if (!result.user) {
      state.session.userId = null
      persist()
      return
    }
    const success = setAuthenticatedUser(
      result.user as ServerUser,
    )
    if (!success) {
      state.session.userId = null
      persist()
    }
  } catch {
    state.session.userId = null
    persist()
  }
}
export async function register(
  fullName: string,
  email: string,
  password: string,
  confirm: string,
): Promise<string | null> {
  const name = fullName.trim()
  const mail = email.trim().toLowerCase()
  if (
    name.length < 2 ||
    name.length > 120 ||
    /[0-9]/u.test(name)
  ) {
    return 'Enter a valid full name without numbers.'
  }
  if (
  !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail) ||
  mail.length > 190
) {
  return 'Enter a valid email.'
}
  if (password.length < 8) {
    return 'Password must be at least 8 characters.'
  }
  if (password !== confirm) {
    return 'Passwords do not match.'
  }
  try {
    const result = await api('/register', {
      full_name: name,
      email: mail,
      password,
      confirm_password: confirm,
    })
    if (result.error) {
      return String(result.error)
    }
    return null
  } catch (error: unknown) {
    return error instanceof Error
      ? error.message
      : 'Registration failed. Please try again.'
  }
}
export async function logout(): Promise<void> {
  try {
    await api('/logout', {})
  } finally {
    state.session.userId = null
    persist()
  }
}
// CARS AND FLEET
export const cars = computed(() => state.cars)
export async function refreshCarsFromBackend(): Promise<string | null> {
  try {
    const result = await api('/cars')
    if (result.error) {
      console.error('Failed to load cars:', result.error)
      return String(result.error)
    }
    if (Array.isArray(result.cars)) {
      const backendCars = result.cars as Car[]
      state.cars = backendCars.map((car) => ({
        ...car,
        id: Number(car.id),
      }))
      persist()
    }
  } catch (error) {
    console.error('Failed to refresh cars:', error)
    return error instanceof Error ? error.message : 'Unable to refresh car prices.'
  }
  return null
}
export const bookings = computed(() => state.bookings)
export const payments = computed(() => state.payments)
export const maintenanceRecords = computed(
  () => state.maintenance,
)
// LOAD ADMIN BOOKINGS FROM MYSQL
export async function loadAdminBookings(): Promise<string | null> {
  if (!canManageBookings.value) {
    return 'Admin or Staff access required.'
  }
  try {
    const result = await api('/admin/bookings')
    if (result.error) {
      return String(result.error)
    }
    const rows = (
      result as { bookings?: unknown }
    ).bookings
    if (!Array.isArray(rows)) {
      return 'Invalid bookings response from server.'
    }
    const databaseBookings = rows as Booking[]
    // Preserve return status included in the bookings response.
    // If the separate admin returns endpoint is available, use it to
    // fill missing statuses (e.g. Booking #40's pending return request).
    const returnStatusByBooking = new Map<number, string>()
    if (isAdmin.value) {
      const returnError = await loadReturnRequests()
      if (!returnError) {
        for (const request of returnRequests) {
          returnStatusByBooking.set(Number(request.booking_id), request.status)
        }
      } else {
        console.warn('Unable to refresh return requests:', returnError)
      }
    }

    state.bookings = databaseBookings.map(booking => {
      const row = booking as Booking & { return_request_status?: string | null }
      return {
        ...booking,
        id: Number(booking.id),
        user_id: Number(booking.user_id),
        car_id: Number(booking.car_id),
        return_request_status:
          returnStatusByBooking.get(Number(booking.id)) ?? row.return_request_status ?? null,
      }
    })

    persist()
    return null
  } catch (error: unknown) {
    return error instanceof Error
      ? error.message
      : 'Failed to load bookings from database.'
  }
}
export function carById(
  id: number | string,
): Car | null {
  return state.cars.find(
    car => car.id === Number(id),
  ) ?? null
}
export function availableCars(): Car[] {
  return state.cars.filter(
    car => car.status === 'available',
  )
}
export function addCar(data: Omit<Car, 'id'>): number {
  const id = nextId(state.cars)
  state.cars.push({
    id,
    ...data,
  })
  persist()
  return id
}
export function updateCar(
  id: number,
  data: Partial<Car>,
) {
  const car = state.cars.find(
    item => item.id === id,
  )
  if (!car) return
  Object.assign(car, data)
  persist()
}
export function deleteCar(
  id: number,
): string | null {
  const active = state.bookings.some(
    booking =>
      booking.car_id === id &&
      [
        'pending',
        'approved',
        'confirmed',
        'ongoing',
        'overdue',
      ].includes(booking.status),
  )
  if (active) {
    return 'Cannot delete this car: it has active or upcoming bookings.'
  }
  const index = state.cars.findIndex(
    car => car.id === id,
  )
  if (index >= 0) {
    state.cars.splice(index, 1)
  }
  persist()
  return null
}
export function fleetStats() {
  const customers = new Set(
    state.bookings
      .map(booking =>
        booking.email.trim().toLowerCase(),
      )
      .filter(Boolean),
  )
  const brands = new Set(
    state.cars
      .map(car =>
        (car.brand ?? '').trim().toLowerCase(),
      )
      .filter(Boolean),
  )
  return {
    customers: customers.size,
    cars: state.cars.length,
    locations: brands.size,
  }
}
// BOOKINGS
export const paymentsByBooking = computed<
  Record<number, Payment[]>
>(() => {
  const grouped: Record<number, Payment[]> = {}
  for (const payment of state.payments) {
    ;(grouped[payment.booking_id] ??= []).push(payment)
  }
  return grouped
})
export function bookingsForUser(
  userId: number,
): Booking[] {
  return state.bookings.filter(
    booking => booking.user_id === userId,
  )
}
// Refresh the signed-in client's bookings from MySQL.
// This keeps payment summaries returned by the backend.
export async function loadMyBookings(): Promise<string | null> {
  const userId = currentUser.value?.id
  if (!userId) return 'Please log in first.'
  try {
    const result = await api('/my-bookings')
    if (result.error) return String(result.error)
    if (!Array.isArray(result.bookings)) {
      return 'Invalid bookings response from server.'
    }
    const serverBookings = (result.bookings as Booking[]).map(booking => ({
      ...booking,
      id: Number(booking.id),
      user_id: Number(booking.user_id),
      car_id: Number(booking.car_id),
    }))
    // Never allow an unexpected response to overwrite other users' bookings.
    if (serverBookings.some(booking => booking.user_id !== userId)) {
      return 'Unexpected bookings returned by server.'
    }
    state.bookings = [
      ...state.bookings.filter(booking => Number(booking.user_id) !== userId),
      ...serverBookings,
    ]
    persist()
    return null
  } catch (error: unknown) {
    return error instanceof Error ? error.message : 'Unable to load your bookings.'
  }
}
export function bookingById(
  id: number | string,
): Booking | null {
  return state.bookings.find(
    booking => booking.id === Number(id),
  ) ?? null
}
export function paymentsForBooking(
  id: number,
): Payment[] {
  return state.payments.filter(
    payment => payment.booking_id === id,
  )
}
function daysBetween(
  start: string,
  end: string,
): number {
  const a = Date.parse(start + 'T00:00:00Z')
  const b = Date.parse(end + 'T00:00:00Z')
  return Math.round((b - a) / 86400000)
}
export function carAvailable(
  carId: number,
  start: string,
  end: string,
): boolean {
  return !state.bookings.some(
    booking =>
      booking.car_id === carId &&
      [
        'pending',
        'approved',
        'confirmed',
        'ongoing',
        'overdue',
      ].includes(booking.status) &&
      booking.pickup_date < end &&
      booking.return_date > start,
  )
}
export interface BookingInput {
  car_id: number
  customer_name: string
  email: string
  phone: string
  pickup_date: string
  return_date: string
  signature_image: string
  agreement_accepted: string
  pickup_location: string
  pickup_lat: number
  pickup_lng: number
  dropoff_location: string
  dropoff_lat: number
  dropoff_lng: number
  route_distance_m?: number
  route_duration_s?: number
}
export async function addBooking(
  input: BookingInput,
): Promise<{ error?: string; id?: number }> {
  const user = currentUser.value
  if (!user) return { error: 'Please sign in to book a car.' }
  const car = carById(input.car_id)
  if (!car || car.status !== 'available') {
    return { error: 'Car not available.' }
  }
  const start = Date.parse(input.pickup_date + 'T00:00:00')
  const end = Date.parse(input.return_date + 'T00:00:00')
  if (
    !Number.isFinite(start) ||
    !Number.isFinite(end) ||
    input.pickup_date < todayISO() ||
    end <= start
  ) {
    return { error: 'Please choose valid future dates (return after pickup).' }
  }
  if (
  input.customer_name.trim() === '' ||
  !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email) ||
  !/^[0-9+() -]{7,20}$/.test(input.phone)
) {
  return { error: 'Enter valid name, email and phone.' }
}
  if (!input.pickup_location || !input.dropoff_location) {
    return { error: 'Please select pickup and drop-off locations.' }
  }
  if (
    !Number.isFinite(input.pickup_lat) ||
    !Number.isFinite(input.pickup_lng) ||
    !Number.isFinite(input.dropoff_lat) ||
    !Number.isFinite(input.dropoff_lng)
  ) {
    return { error: 'Please select valid pickup and drop-off locations.' }
  }
  try {
    if (input.agreement_accepted !== '1') {
      return { error: 'Please accept the Rental Agreement.' }
    }
    if (!/^data:image\/png;base64,[A-Za-z0-9+/]+={0,2}$/.test(input.signature_image) || input.signature_image.length > 400000) {
      return { error: 'Please draw a valid signature.' }
    }
    const bookingPayload: Record<string, string> = {
      car_id: String(input.car_id),
      customer_name: input.customer_name.trim(),
      email: input.email.trim(),
      phone: input.phone.trim(),
      pickup_date: input.pickup_date,
      return_date: input.return_date,
      signature_image: input.signature_image,
      agreement_accepted: input.agreement_accepted,
      pickup_location: input.pickup_location,
      pickup_lat: String(input.pickup_lat),
      pickup_lng: String(input.pickup_lng),
      dropoff_location: input.dropoff_location,
      dropoff_lat: String(input.dropoff_lat),
      dropoff_lng: String(input.dropoff_lng),
    }
    if (typeof input.route_distance_m === 'number') {
      bookingPayload.route_distance_m = String(input.route_distance_m)
    }
    if (typeof input.route_duration_s === 'number') {
      bookingPayload.route_duration_s = String(input.route_duration_s)
    }
    const result = await api('/bookings', bookingPayload)
    if (result.error) return { error: String(result.error) }
    const saved = (result as { booking?: Booking }).booking
    if (!saved || !Number(saved.id)) {
      return { error: 'Server did not confirm the saved booking.' }
    }
    const booking: Booking = {
      ...saved,
      id: Number(saved.id),
      user_id: Number(saved.user_id),
      car_id: Number(saved.car_id),
    }
    const index = state.bookings.findIndex(item => Number(item.id) === booking.id)
    if (index >= 0) state.bookings[index] = booking
    else state.bookings.push(booking)
    persist()
    return { id: booking.id }
  } catch (error: unknown) {
    return {
      error: error instanceof Error ? error.message : 'Unable to submit booking request.',
    }
  }
}
export function markOverdueBookings() {
  const today = todayISO()
  let changed = false
  for (const booking of state.bookings) {
    if (
      booking.status === 'ongoing' &&
      booking.return_date < today
    ) {
      booking.status = 'overdue'
      changed = true
    }
  }
  if (changed) persist()
}
// PAYMENT
export interface PaymentSummary {
  payment_status: 'unpaid' | 'downpayment_paid' | 'partially_paid' | 'fully_paid'
  total_centavos: number
  downpayment_centavos: number
  paid_centavos: number
  remaining_centavos: number
  has_pending_checkout: boolean
}
export async function checkPaymentStatus(
  bookingId: number,
): Promise<PaymentSummary & { error?: string }> {
  const empty: PaymentSummary = {
    payment_status: 'unpaid',
    total_centavos: 0,
    downpayment_centavos: 0,
    paid_centavos: 0,
    remaining_centavos: 0,
    has_pending_checkout: false,
  }
  if (!Number.isInteger(bookingId) || bookingId <= 0) {
    return {
      ...empty,
      error: 'Invalid booking ID.',
    }
  }
  try {
    // Correct route based on LavaLust routes.php
    const result = await api(
      `/payments/${bookingId}/status`,
    )
    if (result.error) {
      return {
        ...empty,
        error: String(result.error),
      }
    }
    return {
      payment_status:
        result.payment_status ?? 'unpaid',
      total_centavos:
        Number(result.total_centavos ?? 0),
      downpayment_centavos:
        Number(result.downpayment_centavos ?? 0),
      paid_centavos:
        Number(result.paid_centavos ?? 0),
      remaining_centavos:
        Number(result.remaining_centavos ?? 0),
      has_pending_checkout:
        Boolean(result.has_pending_checkout),
    }
  } catch (error: unknown) {
    return {
      ...empty,
      error:
        error instanceof Error
          ? error.message
          : 'Unable to check payment status.',
    }
  }
}
export async function payNow(
  bookingId: number,
): Promise<{
  checkoutUrl?: string
  error?: string
} | null> {
  if (!Number.isInteger(bookingId) || bookingId <= 0) {
    return { error: 'Invalid booking ID.' }
  }
  try {
    // The existing API helper fetches the session CSRF token
    // and submits the POST request with the same PHP session.
    const result = await api(`/payments/${bookingId}/checkout`, {}) as {
      checkout_url?: string
      checkoutUrl?: string
      error?: string
      message?: string
    }
    if (result.error) {
      return { error: String(result.error) }
    }
    const checkoutUrl = result.checkout_url || result.checkoutUrl
    if (!checkoutUrl) {
      return {
        error: 'Payment checkout URL was not returned by the backend.',
      }
    }
    const url = new URL(checkoutUrl)
    if (
      url.protocol !== 'https:' ||
      url.hostname !== 'checkout.paymongo.com'
    ) {
      return { error: 'Invalid PayMongo checkout URL.' }
    }
    return { checkoutUrl: url.href }
  } catch (error: unknown) {
    return {
      error: error instanceof Error
        ? error.message
        : 'Unable to start PayMongo checkout.',
    }
  }
}
export function resultPayment(
  bookingId: number | string,
) {
  const id = Number(bookingId)
  return payments.value
    .filter(
      payment =>
        Number(payment.booking_id) === id,
    )
    .slice()
    .sort((a, b) => b.id - a.id)
    .find(payment => payment.status === 'paid') ?? null
}
// MAINTENANCE AND INSPECTIONS
export function maintenanceForCar(
  carId: number | string,
) {
  const id = Number(carId)
  return maintenanceRecords.value.filter(
    record => Number(record.car_id) === id,
  )
}
// ADMIN BOOKING APPROVAL
export async function updateBookingStatus(
  bookingId: number | string,
  status: BookingStatus,
): Promise<string | null> {
  const id = Number(bookingId)
  if (!Number.isInteger(id) || id <= 0) {
    return 'Invalid booking ID.'
  }
  // Both Admin and Staff can manage booking statuses.
  if (!canManageBookings.value) {
    return 'Admin or Staff access required.'
  }
  try {
    const result = await api(
      `/admin/booking/${id}/status`,
      { status },
    )
    if (result.error) {
      return String(result.error)
    }
    const updated = (
      result as { booking?: Booking }
    ).booking
    if (
      !updated ||
      Number(updated.id) !== id ||
      updated.status !== status
    ) {
      return 'The server did not confirm the updated booking status.'
    }
    const local = state.bookings.find(
      item => Number(item.id) === id,
    )
    if (local) {
      local.status = updated.status
    } else {
      state.bookings.push({
        ...updated,
        id,
      })
    }
    persist()
    return null
  } catch (error: unknown) {
    return error instanceof Error
      ? error.message
      : 'Unable to update booking.'
  }
}
export async function loadBookingInspections(bookingId: number | string): Promise<string | null> {
  // Never present cached browser-only inspections as database records.
  state.inspections = state.inspections.filter(item => Number(item.booking_id) !== Number(bookingId))
  persist()
  try {
    const result = await api(`/admin/booking/${Number(bookingId)}/inspections`) as { inspections?: Inspection[]; error?: string }
    if (!Array.isArray(result.inspections)) return 'Invalid inspection response from server.'
    state.inspections = [
      ...state.inspections,
      ...result.inspections.map(item => ({ ...item, id: Number(item.id), booking_id: Number(item.booking_id), mileage: Number(item.mileage) })),
    ]
    persist()
    return null
  } catch (error: unknown) {
    return error instanceof Error ? error.message : 'Unable to load inspections.'
  }
}

export async function addInspection(input: {
  booking_id: number | string
  car_id?: number | string
  inspector_id?: number | null
  inspection_type: InspectionType
  fuel_level: FuelLevel
  mileage: string | number
  vehicle_condition: VehicleCondition
  damage_notes?: string
}): Promise<string | null> {
  try {
    const result = await api(`/admin/booking/${Number(input.booking_id)}/inspections`, {
      inspection_type: input.inspection_type,
      fuel_level: input.fuel_level,
      mileage: String(input.mileage),
      vehicle_condition: input.vehicle_condition,
      damage_notes: input.damage_notes || '',
    }) as { inspection?: Inspection; error?: string }
    if (!result.inspection?.id) return 'Server did not confirm inspection save.'
    return await loadBookingInspections(input.booking_id)
  } catch (error: unknown) {
    return error instanceof Error ? error.message : 'Unable to save inspection.'
  }
}

export function inspectionsForBooking(
  bookingId: number | string,
) {
  const id = Number(bookingId)
  if (!Array.isArray(state.inspections)) {
    return []
  }
  return state.inspections.filter(
    inspection =>
      Number(inspection.booking_id) === id,
  )
}
export function addMaintenance(input: {
  car_id: number | string
  maintenance_type?: string
  description?: string
  cost?: number | string
  maintenance_date?: string
  scheduled_date?: string
  status?: MaintenanceStatus | string
  notes?: string
}): number {
  const record = {
    id: nextId(state.maintenance || []),
    car_id: Number(input.car_id),
    maintenance_type:
      input.maintenance_type ||
      input.description ||
      'General',
    scheduled_date:
      input.scheduled_date ||
      input.maintenance_date ||
      new Date().toISOString().slice(0, 10),
    cost:
      input.cost !== undefined && input.cost !== ''
        ? String(input.cost)
        : '0',
    status:
      (input.status as MaintenanceStatus) ||
      'scheduled',
    notes: input.notes || '',
    created_at: new Date().toISOString(),
  } as Maintenance
  if (!Array.isArray(state.maintenance)) {
    state.maintenance = []
  }
  state.maintenance.push(record)
  persist()
  return record.id
}
export function updateMaintenanceStatus(
  id: number | string,
  status: MaintenanceStatus | string,
): string | null {
  const record = state.maintenance.find(
    item => Number(item.id) === Number(id),
  )
  if (!record) {
    return 'Maintenance record not found.'
  }
  record.status = status as MaintenanceStatus
  persist()
  return null
}
// RETURN VEHICLE REQUESTS (MySQL backend)
export interface ReturnVehicleRequest {
  id: number
  booking_id: number
  user_id: number
  status: 'pending' | 'accepted' | 'rejected'
  requested_at?: string
  reviewed_at?: string | null
  reviewed_by?: number | null
  booking?: Booking | null
}
export const returnRequests = reactive<ReturnVehicleRequest[]>([])
export async function requestVehicleReturn(bookingId: number): Promise<string | null> {
  if (!currentUser.value) return 'Please log in first.'
  if (!Number.isInteger(bookingId) || bookingId <= 0) return 'Invalid booking ID.'
  const booking = bookingById(bookingId)
  if (!booking || Number(booking.user_id) !== currentUser.value.id) {
    return 'This booking does not belong to your account.'
  }
  if (!['ongoing', 'overdue'].includes(booking.status)) {
    return 'Return requests are only available for ongoing or overdue rentals.'
  }
  try {
    const result = await api(`/returns/${bookingId}/request`, {})
    if (result.error) return String(result.error)
    await loadMyBookings()
    return null
  } catch (error: unknown) {
    return error instanceof Error ? error.message : 'Unable to request vehicle return.'
  }
}
export async function loadReturnRequests(): Promise<string | null> {
  if (!isAdmin.value) return 'Admin access required.'
  try {
    const result = await api('/admin/returns') as {
      error?: string
      requests?: ReturnVehicleRequest[]
      return_requests?: ReturnVehicleRequest[]
    }
    if (result.error) return String(result.error)
    const rows = result.requests ?? result.return_requests
    if (!Array.isArray(rows)) return 'Invalid return requests response from server.'
    returnRequests.splice(0, returnRequests.length, ...rows.map(row => ({
      ...row,
      id: Number(row.id),
      booking_id: Number(row.booking_id),
      user_id: Number(row.user_id),
    })))
    return null
  } catch (error: unknown) {
    return error instanceof Error ? error.message : 'Unable to load return requests.'
  }
}
export async function acceptVehicleReturn(bookingId: number): Promise<string | null> {
  if (!isAdmin.value) return 'Only admin can accept a vehicle return.'
  if (!Number.isInteger(bookingId) || bookingId <= 0) return 'Invalid booking ID.'
  try {
    const result = await api(`/admin/returns/${bookingId}/accept`, {})
    if (result.error) return String(result.error)
    await Promise.all([loadReturnRequests(), loadAdminBookings()])
    return null
  } catch (error: unknown) {
    return error instanceof Error ? error.message : 'Unable to accept vehicle return.'
  }
}
