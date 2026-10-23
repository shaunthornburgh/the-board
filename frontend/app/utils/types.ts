export interface Teaser {
  id: number
  is_sample: boolean
  photo_url: string
  town: string | null
  county: string | null
  asking_price: number | null
  teaser: string
  published_at: string | null
}

export type DealStatus = 'new' | 'awaiting_analyst' | 'analyst_done' | 'awaiting_review' | 'rejected' | 'published'

export type AnalystVerdict = 'send' | 'mention' | 'reject'

export interface Deal extends Omit<Teaser, 'photo_url'> {
  reference: string | null
  status: DealStatus
  origin: string
  title: string
  address: string | null
  postcode: string | null
  latitude: number | null
  longitude: number | null
  photo_url: string | null
  tenure: string | null
  type: string | null
  source_name: string | null
  source_url: string | null
  bot_note: string | null
  tenant: string | null
  vacant_area_sq_ft: number | null
  comps: { address?: string; price?: number; date?: string; note?: string }[]
  evidence: Record<string, string>
  appraised_at: string | null
  has_pack: boolean
}

export interface Strategy {
  slug: string
  label: string
}

/** The operator's view of a deal: everything, including the verdicts and pipeline state, with raw values for the edit form. */
export interface OperatorDeal extends Omit<Deal, 'type' | 'tenure' | 'teaser'> {
  status_label: string
  status_changed_at: string | null
  /** Statuses this deal can move to next. */
  next: DealStatus[]
  can_reject: boolean
  verdict: 'send' | 'mention' | null
  verdict_label: string | null
  strategies: Strategy[]
  scout: string | null
  source: string | null
  source_key: string | null
  scout_notes: string | null
  analyst_verdict: AnalystVerdict | null
  analyst_verdict_label: string | null
  analyst_notes: string | null
  analyst_requested_at: string | null
  analyst_completed_at: string | null
  analyst_error: string | null
  rejected_at: string | null
  rejection_reason: string | null
  type: string | null
  type_label: string | null
  tenure: string | null
  tenure_label: string | null
  teaser: string | null
  has_photo: boolean
  updated_at: string | null
}

export interface StatusCount {
  value: DealStatus
  label: string
  count: number
}

export interface StatusEvent {
  id: number
  from: DealStatus | null
  from_label: string | null
  to: DealStatus
  to_label: string
  by: string | null
  note: string | null
  at: string
}

export interface OperatorMember {
  id: number
  name: string
  email: string
  status: 'invited' | 'active' | 'revoked'
  invited_at: string | null
  activated_at: string | null
  minimum_term_ends_at: string | null
  revoked_at: string | null
  invite_link: string | null
}

export interface InviteRequest {
  id: number
  name: string
  email: string
  phone: string | null
  message: string | null
  created_at: string
}
