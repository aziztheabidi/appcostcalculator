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
  recommendationText: string
  recommendationLoading?: boolean
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
  recommendationText,
  recommendationLoading = false,
  mobileCtaLabel,
  onMobileCtaClick,
  mobileCtaDisabled = false,
}: CalculatorLayoutProps) => {
  return (
    <main className="pixact-calculator">
      <div className="pixact-calculator__surface w-full overflow-hidden">
        <div className="mx-auto w-full max-w-[1200px] px-4 md:px-6 lg:px-8">
          <div className="grid w-full grid-cols-1 gap-8 py-4 md:py-6 lg:grid-cols-[minmax(0,60%)_minmax(0,40%)] lg:py-8">
          <section className="min-w-0 space-y-6 break-words pb-28 lg:pb-0">{left}</section>
          <StickyEstimatePanel
            estimate={estimate}
            timeline={timeline}
            complexity={complexity}
            insights={insights}
            recommendationText={recommendationText}
            recommendationLoading={recommendationLoading}
          />
          </div>
        </div>
      </div>
      <MobileEstimateBar
        estimate={estimate}
        timeline={timeline}
        complexity={complexity}
        recommendationText={recommendationText}
        recommendationLoading={recommendationLoading}
        ctaLabel={mobileCtaLabel}
        onCtaClick={onMobileCtaClick}
        ctaDisabled={mobileCtaDisabled}
      />
    </main>
  )
}
