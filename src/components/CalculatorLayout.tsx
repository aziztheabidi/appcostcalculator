import type { ReactNode } from "react"
import { MobileEstimateBar } from "./MobileEstimateBar"
import { StickyEstimatePanel } from "./StickyEstimatePanel"
import type { EstimateBreakdown } from "../types/calculator"

interface CalculatorLayoutProps {
  left: ReactNode
  estimate: EstimateBreakdown
  timeline: string
  complexity: string
}

export const CalculatorLayout = ({ left, estimate, timeline, complexity }: CalculatorLayoutProps) => {
  return (
    <main className="pixact-calculator">
      <div className="pixact-calculator__surface mx-auto w-full rounded-3xl p-3 sm:p-5 lg:p-8">
        <div className="mx-auto grid w-full max-w-6xl gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
          <section className="space-y-4 pb-20 lg:pb-0">{left}</section>
          <StickyEstimatePanel estimate={estimate} timeline={timeline} complexity={complexity} />
        </div>
      </div>
      <MobileEstimateBar total={estimate.total} timeline={timeline} />
    </main>
  )
}
