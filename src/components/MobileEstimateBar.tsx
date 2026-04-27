import { formatCurrency } from "../lib/pricingEngine"
import type { EstimateBreakdown } from "../types/calculator"
import { useState } from "react"

interface MobileEstimateBarProps {
  estimate: EstimateBreakdown
  timeline: string
  complexity: string
  recommendationText: string
  recommendationLoading?: boolean
  ctaLabel: "Continue" | "See Estimate"
  onCtaClick: () => void
  ctaDisabled?: boolean
}

const shortCurrency = (value: number): string => {
  if (value >= 1000) {
    return `$${(value / 1000).toFixed(1).replace(".0", "")}k`
  }
  return formatCurrency(value)
}

export const MobileEstimateBar = ({
  estimate,
  timeline,
  complexity,
  recommendationText,
  recommendationLoading = false,
  ctaLabel,
  onCtaClick,
  ctaDisabled = false,
}: MobileEstimateBarProps) => {
  const [open, setOpen] = useState(false)
  const min = Math.round(estimate.total * 0.9)
  const max = Math.round(estimate.total * 1.1)
  const shortRange = `${shortCurrency(min)} - ${shortCurrency(max)}`

  return (
    <div className="fixed right-0 bottom-0 left-0 z-20 border-t border-slate-200 bg-white/95 shadow-2xl backdrop-blur-md lg:hidden">
      <div className="mx-auto max-w-6xl p-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)]">
        <div className="grid grid-cols-1 gap-2">
          <button
            type="button"
            onClick={() => setOpen((prev) => !prev)}
            className="min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left"
            aria-expanded={open}
          >
            <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Estimated range</p>
            <p className="truncate text-sm font-bold text-slate-900 transition-all duration-300">{shortRange}</p>
          </button>
          <button
            type="button"
            onClick={onCtaClick}
            disabled={ctaDisabled}
            className="ds-button-primary min-h-12 w-full justify-center rounded-lg px-4 py-2"
          >
            {ctaLabel}
          </button>
        </div>
        <div
          className={`grid transition-all duration-300 ease-out ${open ? "mt-3 grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"}`}
        >
          <div className="overflow-hidden">
            <div className="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
              <p className="text-base font-bold text-slate-900 transition-all duration-300">{formatCurrency(min)} - {formatCurrency(max)}</p>
              <p className="break-words">Timeline: {timeline}</p>
              <p className="break-words">Complexity: {complexity}</p>
              <div className="rounded-lg border border-slate-200 bg-white p-2">
                <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Recommendation</p>
                {recommendationLoading ? (
                  <div className="mt-2 space-y-2">
                    <div className="h-3 w-11/12 animate-pulse rounded bg-slate-200/80" aria-hidden="true" />
                    <div className="h-3 w-8/12 animate-pulse rounded bg-slate-200/80" aria-hidden="true" />
                  </div>
                ) : (
                  <p key={recommendationText} className="pixact-clamp-2 pixact-fade-in mt-1 break-words text-sm leading-relaxed text-slate-700">
                    {recommendationText}
                  </p>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
