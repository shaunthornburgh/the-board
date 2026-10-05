const pounds = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('en-GB')
const date = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })

export const money = (value: number | null | undefined) => (value == null ? '—' : pounds.format(value))
export const area = (value: number | null | undefined) => (value == null ? '—' : `${number.format(value)} sq ft`)
export const day = (value: string | null | undefined) => (value ? date.format(new Date(value)) : '—')
