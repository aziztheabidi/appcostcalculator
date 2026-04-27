import { formatCurrency } from "../lib/pricingEngine"
import type { EstimateBreakdown } from "../types/calculator"

interface StickyEstimatePanelProps {
  estimate: EstimateBreakdown
  timeline: string
  complexity: string
  insights: string[]
  recommendationText: string
  recommendationLoading?: boolean
}

const getTimelineRange = (timeline: string): string => {
  if (timeline === "standard") return "6 - 10 weeks"
  if (timeline === "accelerated") return "4 - 7 weeks"
  if (timeline === "urgent") return "2 - 4 weeks"
  return "TBD by scope"
}

const getComplexityBadge = (complexity: string, total: number): string => {
  if (total >= 60000) return "Enterprise"
  if (complexity === "basic") return "Basic"
  if (complexity === "advanced") return "Medium"
  if (complexity === "premium") return "Advanced"
  return "Basic"
}

export const StickyEstimatePanel = ({
  estimate,
  timeline,
  complexity,
  insights,
  recommendationText,
  recommendationLoading = false,
}: StickyEstimatePanelProps) => {
  const min = Math.round(estimate.total * 0.9)
  const max = Math.round(estimate.total * 1.1)
  const complexityBadge = getComplexityBadge(complexity, estimate.total)
  const timelineRange = getTimelineRange(timeline)

  return (
    <aside className="ds-card sticky top-4 hidden p-6 lg:block">
      <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Estimated cost range</p>
      <p className="ds-price mt-2 transition-all duration-200">
        {formatCurrency(min)} - {formatCurrency(max)}
      </p>

      <div className="mt-5 grid gap-3">
        <div className="rounded-xl border border-slate-200 bg-white/90 p-3">
          <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Timeline</p>
          <p className="mt-1 text-base font-semibold text-slate-900 transition-all duration-200">{timelineRange}</p>
        </div>
        <div className="rounded-xl border border-red-200 bg-red-50/70 p-3">
          <p className="text-[11px] font-semibold tracking-wide text-red-600 uppercase">Complexity</p>
          <p className="mt-1 inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700">
            {complexityBadge}
          </p>
        </div>
      </div>

      <div className="mt-5 min-h-20 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Recommendation</p>
        <div className="mt-2 min-h-10">
          {recommendationLoading ? (
            <div className="space-y-2">
              <div className="h-3 w-11/12 animate-pulse rounded bg-slate-200/80" aria-hidden="true" />
              <div className="h-3 w-8/12 animate-pulse rounded bg-slate-200/80" aria-hidden="true" />
            </div>
          ) : (
            <p className="text-sm leading-relaxed text-slate-700 transition-opacity duration-200">{recommendationText}</p>
          )}
        </div>
      </div>

      <div className="mt-4 min-h-40 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Dynamic insights</p>
        <ul className="mt-2 space-y-2 text-sm leading-relaxed text-slate-700">
          {insights.map((insight) => (
            <li key={insight} className="transition-all duration-200">- {insight}</li>
          ))}
        </ul>
      </div>

      <p className="mt-5 text-xs leading-relaxed text-slate-500">
        This is a guided estimate based on your inputs.
      </p>
    </aside>
  )
}
