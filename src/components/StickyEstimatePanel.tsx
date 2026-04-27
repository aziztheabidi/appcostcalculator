import { formatCurrency } from "../lib/pricingEngine"
import type { EstimateBreakdown } from "../types/calculator"

interface StickyEstimatePanelProps {
  estimate: EstimateBreakdown
  timeline: string
  complexity: string
}

const getHint = (total: number) => {
  if (total < 15000) return "Lean build path: great for validating quickly."
  if (total < 35000) return "Balanced build: strong foundation with growth-ready features."
  return "Advanced scope: ideal for high-quality launch and scale."
}

export const StickyEstimatePanel = ({ estimate, timeline, complexity }: StickyEstimatePanelProps) => {
  const min = Math.round(estimate.total * 0.9)
  const max = Math.round(estimate.total * 1.1)

  return (
    <aside className="sticky top-4 hidden rounded-3xl border border-white/60 bg-white/75 p-5 shadow-xl shadow-slate-200/60 backdrop-blur-md lg:block">
      <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Live estimate</p>
      <p className="mt-3 text-3xl font-bold text-slate-900">
        {formatCurrency(min)} - {formatCurrency(max)}
      </p>

      <div className="mt-4 flex flex-wrap gap-2">
        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
          Timeline: {timeline}
        </span>
        <span className="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
          Complexity: {complexity}
        </span>
      </div>

      <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-3">
        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">AI-style note</p>
        <p className="mt-2 text-sm text-slate-700">{getHint(estimate.total)}</p>
      </div>

      <p className="mt-5 text-xs leading-relaxed text-slate-500">
        This is an estimated range. Final scope may vary based on detailed requirements.
      </p>
    </aside>
  )
}
