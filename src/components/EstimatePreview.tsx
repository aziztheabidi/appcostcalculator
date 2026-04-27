import { formatCurrency } from "../lib/pricingEngine"
import type { EstimateBreakdown } from "../types/calculator"

interface EstimatePreviewProps {
  estimate: EstimateBreakdown
}

export const EstimatePreview = ({ estimate }: EstimatePreviewProps) => {
  return (
    <section className="sticky top-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/50">
      <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Live estimate</p>
      <p className="mt-3 text-4xl font-bold text-slate-900">{formatCurrency(estimate.total)}</p>
      <p className="mt-2 text-sm text-slate-600">Indicative range updates instantly with each choice.</p>
      <dl className="mt-5 space-y-3 text-sm">
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">Base cost</dt>
          <dd className="font-medium text-slate-900">{formatCurrency(estimate.baseCost)}</dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">Complexity impact</dt>
          <dd className="font-medium text-slate-900">{estimate.complexityMultiplier.toFixed(2)}x</dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">Timeline impact</dt>
          <dd className="font-medium text-slate-900">{estimate.timelineMultiplier.toFixed(2)}x</dd>
        </div>
        <div className="flex items-center justify-between">
          <dt className="text-slate-500">Add-ons</dt>
          <dd className="font-medium text-slate-900">{formatCurrency(estimate.addonsCost)}</dd>
        </div>
      </dl>
    </section>
  )
}
