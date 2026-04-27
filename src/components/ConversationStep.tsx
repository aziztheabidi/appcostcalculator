import type { ReactNode } from "react"
import { HelpTooltip } from "./HelpTooltip"

interface ConversationStepProps {
  title: string
  description: string
  helpText: string
  questionLabel: string
  children: ReactNode
  onBack?: () => void
  onNext: () => void
  nextDisabled?: boolean
  nextLabel?: string
}

export const ConversationStep = ({
  title,
  description,
  helpText,
  questionLabel,
  children,
  onBack,
  onNext,
  nextDisabled,
  nextLabel = "Next",
}: ConversationStepProps) => {
  return (
    <section className="rounded-3xl border border-white/60 bg-white/70 p-5 shadow-xl shadow-slate-200/50 backdrop-blur-md sm:p-7">
      <div className="animate-fade-slide space-y-5">
        <header className="space-y-2">
          <h2 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{title}</h2>
          <p className="text-sm text-slate-600 sm:text-base">{description}</p>
        </header>

        <HelpTooltip text={helpText} />

        <div className="space-y-3">
          <p className="text-sm font-semibold text-slate-700">{questionLabel}</p>
          {children}
        </div>

        <div className="flex items-center justify-between pt-2">
          <button
            type="button"
            onClick={onBack}
            disabled={!onBack}
            className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-40"
          >
            Back
          </button>
          <button
            type="button"
            onClick={onNext}
            disabled={nextDisabled}
            className="rounded-xl bg-[#D61414] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b91010] focus-visible:ring-2 focus-visible:ring-red-200 focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-red-300"
          >
            {nextLabel}
          </button>
        </div>
      </div>
    </section>
  )
}
