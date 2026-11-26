const rupiahFormatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

const numberFormatter = new Intl.NumberFormat('id-ID', {
  maximumFractionDigits: 1,
})

export function formatRupiah(value: string | number | null | undefined): string {
  const num = typeof value === 'string' ? Number.parseFloat(value) : (value ?? 0)
  if (Number.isNaN(num)) return 'Rp0'
  return rupiahFormatter.format(Math.round(num)).replace(/\s+/g, '')
}

export function formatCompactRupiah(value: string | number | null | undefined): string {
  const num = typeof value === 'string' ? Number.parseFloat(value) : (value ?? 0)
  if (Number.isNaN(num)) return 'Rp0'
  const abs = Math.abs(num)
  const sign = num < 0 ? '-' : ''
  if (abs >= 1_000_000_000) return `${sign}Rp${(abs / 1_000_000_000).toFixed(1)}M`
  if (abs >= 1_000_000) return `${sign}Rp${(abs / 1_000_000).toFixed(1)}jt`
  if (abs >= 1_000) return `${sign}Rp${Math.round(abs / 1_000)}rb`
  return `${sign}Rp${Math.round(abs)}`
}

export function formatNumber(value: string | number | null | undefined): string {
  const num = typeof value === 'string' ? Number.parseFloat(value) : (value ?? 0)
  if (Number.isNaN(num)) return '0'
  return numberFormatter.format(num)
}

export function formatDateId(dateStr: string | null | undefined): string {
  if (!dateStr) return '-'
  const date = new Date(`${dateStr}T00:00:00+07:00`)
  if (Number.isNaN(date.getTime())) return dateStr
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
  }).format(date)
}

export function todayJakarta(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())

  const y = parts.find(p => p.type === 'year')?.value ?? '2026'
  const m = parts.find(p => p.type === 'month')?.value ?? '01'
  const d = parts.find(p => p.type === 'day')?.value ?? '01'
  return `${y}-${m}-${d}`
}

export function nowTimeJakarta(): string {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(new Date())

  const h = parts.find(p => p.type === 'hour')?.value ?? '00'
  const m = parts.find(p => p.type === 'minute')?.value ?? '00'
  return `${h}:${m}`
}

export function currentMonthJakarta(): string {
  return todayJakarta().slice(0, 7)
}
