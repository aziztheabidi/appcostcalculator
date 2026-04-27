import type { ReactNode } from "react"
import { MobileEstimateBar } from "./MobileEstimateBar"
import { StickyEstimatePanel } from "./StickyEstimatePanel"
import type { EstimateBreakdown } from "../types/calculator"

interface CalculatorLayoutProps {
  left: ReactNode
  estimate: EstimateBreakdown
  timeline: string
  complexity: string
  insights: string[]
  mobileCtaLabel: "Continue" | "See Estimate"
  onMobileCtaClick: () => void
  mobileCtaDisabled?: boolean
}

export const CalculatorLayout = ({
  left,
  estimate,
  timeline,
  complexity,
  insights,
  mobileCtaLabel,
  onMobileCtaClick,
  mobileCtaDisabled = false,
}: CalculatorLayoutProps) => {
  return (
    <main className="pixact-calculator">
      <div className="pixact-calculator__surface mx-auto w-full rounded-3xl p-4 sm:p-6 lg:p-10">
        <div className="mx-auto grid w-full max-w-6xl gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
          <section className="space-y-4 pb-28 lg:pb-0">{left}</section>
          <StickyEstimatePanel
            estimate={estimate}
            timeline={timeline}
            complexity={complexity}
            insights={insights}
          />
        </div>
      </div>
      <MobileEstimateBar
        estimate={estimate}
        timeline={timeline}
        complexity={complexity}
        ctaLabel={mobileCtaLabel}
        onCtaClick={onMobileCtaClick}
        ctaDisabled={mobileCtaDisabled}
      />
    </main>
  )
}
