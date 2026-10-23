import type { AnalystVerdict, DealStatus, OperatorDeal } from '~/utils/types'

/** API path segment for each pipeline move, under /api/operator/deals/{id}/. */
export type PipelineAction = 'send-to-analyst' | 'awaiting-review' | 'publish' | 'unpublish' | 'reject'

/** The one forward move for a deal in its current status, if any. Reject is offered separately. */
export function forwardAction(deal: OperatorDeal): { action: PipelineAction; label: string } | null {
  switch (deal.status) {
    case 'new':
      return { action: 'send-to-analyst', label: 'Send to Analyst' }
    case 'awaiting_analyst':
      return deal.analyst_error ? { action: 'send-to-analyst', label: 'Retry analyst' } : null
    case 'analyst_done':
      return { action: 'awaiting-review', label: 'Move to review' }
    case 'awaiting_review':
      return { action: 'publish', label: 'Publish' }
    case 'published':
      return { action: 'unpublish', label: 'Unpublish' }
    default:
      return null
  }
}

export const statusTone: Record<DealStatus, string> = {
  new: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  awaiting_analyst: 'bg-violet-50 text-violet-700 ring-violet-600/20',
  analyst_done: 'bg-amber-50 text-amber-800 ring-amber-600/20',
  awaiting_review: 'bg-orange-50 text-orange-700 ring-orange-600/20',
  published: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
  rejected: 'bg-gray-50 text-gray-600 ring-gray-500/20',
}

export const verdictTone: Record<AnalystVerdict, string> = {
  send: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  mention: 'bg-amber-50 text-amber-800 ring-amber-600/20',
  reject: 'bg-red-50 text-red-700 ring-red-600/20',
}

/** What each tab is for, shown above its list. */
export const statusHelp: Record<DealStatus, string> = {
  new: 'Pushed by scouts or entered by hand. Send the promising ones to the Analyst, reject the rest.',
  awaiting_analyst: 'With the Analyst bot. If a call fails the error shows here and you can retry.',
  analyst_done: 'The Analyst has answered. Move the keepers to review, reject the rest.',
  awaiting_review: 'Final check before members see it. Needs an address and its own photo to publish.',
  published: 'Members can see these. Unpublishing sends a deal back to awaiting review.',
  rejected: 'Out of the pipeline. Kept for the record.',
}
