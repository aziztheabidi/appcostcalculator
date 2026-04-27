import { formatCurrency } from "../lib/pricingEngine"
import type { EstimateBreakdown } from "../types/calculator"
import { useState } from "react"

interface MobileEstimateBarProps {
  estimate: EstimateBreakdown
  timeline: string
  complexity: string
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
      <div className="mx-auto max-w-6xl p-3">
        <div className="flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={() => setOpen((prev) => !prev)}
            className="min-w-0 text-left"
            aria-expanded={open}
          >
            <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Estimated range</p>
            <p className="truncate text-sm font-bold text-slate-900">{shortRange}</p>
          </button>
          <button
            type="button"
            onClick={onCtaClick}
            disabled={ctaDisabled}
            className="ds-button-primary rounded-lg px-4 py-2"
          >
            {ctaLabel}
          </button>
        </div>
        <div
          className={`grid transition-all duration-220 ease-out ${open ? "mt-3 grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"}`}
        >
          <div className="overflow-hidden">
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
              <p className="font-semibold text-slate-900">{formatCurrency(min)} - {formatCurrency(max)}</p>
              <p className="mt-1">Timeline: {timeline}</p>
              <p>Complexity: {complexity}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
