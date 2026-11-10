
const API_BASE = '/carease/api'

export interface ApiUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'staff' | 'client'
}

export interface ValidIdRecord {
  id: number
  full_name: string
  email: string
  id_type: string
  id_number: string
  id_status: string
  id_notes: string
  has_photo: boolean
}

export interface StaffAccount {
  id: number
  full_name: string
  email: string
  role: 'staff'
}

export interface ApiResponse {
  user?: ApiUser | null
  csrf?: string
  message?: string
  error?: string
  cars?: unknown[]
  bookings?: unknown[]
  staff?: StaffAccount[]
  valid_ids?: ValidIdRecord[]

  payment_status?:
    | 'unpaid'
    | 'downpayment_paid'
    | 'partially_paid'
    | 'fully_paid'

  total_centavos?: number
  downpayment_centavos?: number
  paid_centavos?: number
  remaining_centavos?: number
  has_pending_checkout?: boolean
}

export async function api(
  path: string,
  data?: Record<string, string>,
): Promise<ApiResponse> {
  const options: RequestInit = {
    credentials: 'include',
  }

  if (data !== undefined) {
    const session = await api('/session')

    if (!session.csrf) {
      throw new Error('Missing CSRF token.')
    }

    options.method = 'POST'
    options.headers = {
      'Content-Type': 'application/x-www-form-urlencoded',
    }

    options.body = new URLSearchParams({
      ...data,
      csrf: session.csrf,
    }).toString()
  }

  const response = await fetch(
    `${API_BASE}${path}`,
    options,
  )

  const responseText = await response.text()

  let result: ApiResponse

  try {
    result = JSON.parse(responseText) as ApiResponse

    if (
      result === null ||
      typeof result !== 'object' ||
      Array.isArray(result)
    ) {
      throw new Error('Invalid JSON response')
    }
  } catch {
    throw new Error(
      `Invalid server response (${response.status}). Check PHP backend URL and API routes.`,
    )
  }

  if (!response.ok) {
    throw new Error(
      result.error ||
      `API request failed (${response.status})`,
    )
  }

  return result
}
