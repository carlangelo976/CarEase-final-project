export function peso(value: number | string, digits = 2): string {
  return '\u20B1' + Number(value).toLocaleString('en-PH', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  })
}

export function pesoShort(value: number | string): string {
  return peso(value, 0)
}

export function ucfirst(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

export function todayISO(): string {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().slice(0, 10)
}

export function addDaysISO(days: number): string {
  const d = new Date()
  d.setDate(d.getDate() + days)
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().slice(0, 10)
}

export function timestamp(daysAgo = 0): string {
  const d = new Date()
  d.setDate(d.getDate() - daysAgo)
  const pad = (n: number) => String(n).padStart(2, '0')
  return (
    d.getFullYear() +
    '-' +
    pad(d.getMonth() + 1) +
    '-' +
    pad(d.getDate()) +
    ' ' +
    pad(d.getHours()) +
    ':' +
    pad(d.getMinutes()) +
    ':' +
    pad(d.getSeconds())
  )
}

export function money(value: number | string): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}
