export interface MapPlace {
  place_id: string
  address: string
  name?: string
  lat: number
  lng: number
}
export interface PlaceSuggestion {
  place_id: string
  text: string
  main_text: string
  secondary_text: string
  lat: number
  lng: number
}

export interface RouteInfo {
  distance_m: number
  duration_s: number
  // [lat, lng] pairs returned by our backend.
  polyline: Array<[number, number]>
}

export interface MapsConfig {
  browser_key: string
  map_id: string
  region: string
  language: string
}

// The Google Maps loader is injected at runtime, so we keep a small ambient
// type instead of pulling in the full @types/google.maps package.
export type GoogleMaps = any

declare global {
  interface Window {
    google?: any
  }
}

const API_BASE = '/carease/api/maps'
const SCRIPT_ID = 'carease-google-maps'

let configPromise: Promise<MapsConfig> | null = null
let mapsPromise: Promise<GoogleMaps> | null = null

async function jsonRequest<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, { credentials: 'include', ...init })
  const text = await response.text()

  let data: unknown = {}

  try {
    data = text ? JSON.parse(text) : {}
  } catch {
    throw new Error('Invalid response from the Maps API.')
  }

  if (!response.ok) {
    const error = (data as { error?: unknown }).error
    throw new Error(
      typeof error === 'string' && error
        ? error
        : `Maps request failed (${response.status}).`,
    )
  }

  return data as T
}

export function getMapsConfig(): Promise<MapsConfig> {
  if (!configPromise) {
    configPromise = jsonRequest<MapsConfig>(`${API_BASE}/config`).catch(error => {
      configPromise = null
      throw error
    })
  }

  return configPromise
}

function loadScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null

    const onError = () => reject(new Error('Unable to load Google Maps.'))

    if (existing) {
      if (window.google?.maps) {
        resolve()
        return
      }

      existing.addEventListener('load', () => resolve(), { once: true })
      existing.addEventListener('error', onError, { once: true })
      return
    }

    const script = document.createElement('script')
    script.id = SCRIPT_ID
    script.src = src
    script.async = true
    script.defer = true
    script.addEventListener('load', () => resolve(), { once: true })
    script.addEventListener('error', onError, { once: true })

    document.head.appendChild(script)
  })
}

export async function loadGoogleMaps(): Promise<GoogleMaps> {
  if (window.google?.maps) return window.google.maps

  if (!mapsPromise) {
    mapsPromise = (async () => {
      const config = await getMapsConfig()

      if (!config.browser_key) {
        throw new Error('Google Maps API key is not configured.')
      }

      const params = new URLSearchParams({
        key: config.browser_key,
        v: 'weekly',
        language: config.language || 'en',
        region: config.region || 'PH',
      })

      await loadScript(
        `https://maps.googleapis.com/maps/api/js?${params.toString()}`,
      )

      if (!window.google?.maps) {
        throw new Error('Google Maps did not initialize.')
      }

      return window.google.maps
    })().catch(error => {
      mapsPromise = null
      throw error
    })
  }

  return mapsPromise
}

export async function fetchSuggestions(
  input: string,
  session: string,
  signal?: AbortSignal,
): Promise<PlaceSuggestion[]> {
  const params = new URLSearchParams({ input, session })

  const data = await jsonRequest<{ suggestions?: PlaceSuggestion[] }>(
    `${API_BASE}/autocomplete?${params.toString()}`,
    { signal },
  )

  return Array.isArray(data.suggestions) ? data.suggestions : []
}

export async function resolvePlace(placeId: string): Promise<MapPlace> {
  const params = new URLSearchParams({ place_id: placeId })

  return jsonRequest<MapPlace>(`${API_BASE}/place?${params.toString()}`)
}

export async function fetchDirections(
  origin: MapPlace,
  destination: MapPlace,
): Promise<RouteInfo> {
  const body = new URLSearchParams({
    origin_lat: String(origin.lat),
    origin_lng: String(origin.lng),
    dest_lat: String(destination.lat),
    dest_lng: String(destination.lng),
  })

  return jsonRequest<RouteInfo>(`${API_BASE}/directions`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  })
}

export function formatDistance(meters: number): string {
  if (!Number.isFinite(meters) || meters <= 0) return '—'

  if (meters < 1000) return `${Math.round(meters)} m`

  return `${(meters / 1000).toFixed(1)} km`
}

export function formatDuration(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds <= 0) return '—'

  const totalMinutes = Math.round(seconds / 60)

  if (totalMinutes < 60) return `${totalMinutes} min`

  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60

  return minutes === 0 ? `${hours} hr` : `${hours} hr ${minutes} min`
}
