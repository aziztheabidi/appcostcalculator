import { formatCurrency } from "../lib/pricingEngine"

interface MobileEstimateBarProps {
  total: number
  timeline: string
}

export const MobileEstimateBar = ({ total, timeline }: MobileEstimateBarProps) => {
  const min = Math.round(total * 0.9)
  const max = Math.round(total * 1.1)

  return (
    <div className="fixed right-0 bottom-0 left-0 z-20 border-t border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur-md lg:hidden">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
        <div>
          <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Estimated range</p>
          <p className="text-sm font-bold text-slate-900">
            {formatCurrency(min)} - {formatCurrency(max)}
          </p>
        </div>
        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
          {timeline}
        </span>
      </div>
    </div>
  )
}
