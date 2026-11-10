
export type Role = 'admin' | 'staff' | 'client'

export type CarStatus = 'available' | 'maintenance'

export type BookingStatus =
  | 'pending'
  | 'approved'
  | 'confirmed'
  | 'ongoing'
  | 'overdue'
  | 'completed'
  | 'rejected'
  | 'cancelled'

export type PaymentStatus = 'pending' | 'paid' | 'expired'

export type FuelLevel =
  | 'empty'
  | 'quarter'
  | 'half'
  | 'three_quarter'
  | 'full'

export type VehicleCondition = 'good' | 'minor' | 'major'

export type InspectionType = 'pre' | 'post'

export type MaintenanceStatus =
  | 'scheduled'
  | 'in_progress'
  | 'completed'

export type CarCategory = 'Economy' | 'SUV' | 'Luxury' | 'Van'

export interface CareaseUser {
  id: number
  full_name: string
  email: string
  password_hash: string
  role: Role
  created_at: string
}

export interface Car {
  id: number
  brand: string
  name: string
  price_per_day: string
  seats: number
  status: CarStatus
  image_path: string | null
}

export interface Booking {
  id: number
  user_id: number
  car_id: number
  customer_name: string
  email: string
  phone: string
  pickup_date: string
  return_date: string
  total_amount: string
  status: BookingStatus
  created_at: string
  return_request_status?: string | null

  // Pickup / drop-off locations and route (Google Maps)
  pickup_location?: string | null
  pickup_lat?: number | null
  pickup_lng?: number | null
  dropoff_location?: string | null
  dropoff_lat?: number | null
  dropoff_lng?: number | null
  route_distance_m?: number | null
  route_duration_s?: number | null

  // PayMongo payment details
  payment_status?: string
  total_centavos?: number
  downpayment_centavos?: number
  paid_centavos?: number
  remaining_centavos?: number
  has_pending_checkout?: boolean
}

export interface Payment {
  id: number
  booking_id: number
  checkout_session_id: string
  checkout_url: string
  amount_centavos: number
  status: PaymentStatus
  payment_method: string | null
  paid_at: string | null
  created_at: string
}

export interface Inspection {
  id: number
  booking_id: number
  inspection_type: InspectionType
  fuel_level: FuelLevel
  mileage: number
  vehicle_condition: VehicleCondition
  damage_notes: string
  inspected_at: string
}

export interface Maintenance {
  id: number
  car_id: number
  maintenance_type: string
  scheduled_date: string
  cost: string
  status: MaintenanceStatus
  notes: string
  created_at: string
}

export interface CareaseState {
  users: CareaseUser[]
  cars: Car[]
  bookings: Booking[]
  payments: Payment[]
  inspections: Inspection[]
  maintenance: Maintenance[]
  session: {
    userId: number | null
  }
}
