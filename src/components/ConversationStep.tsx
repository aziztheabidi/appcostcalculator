import type { ReactNode } from "react"
import { HelpAccordion } from "./HelpAccordion"

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
  microcopy?: string
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
  microcopy,
}: ConversationStepProps) => {
  return (
    <section className="ds-card p-6 sm:p-8">
      <div className="animate-fade-slide space-y-5">
        <header className="space-y-2">
          <h2 className="ds-subheading">{title}</h2>
          <p className="ds-body">{description}</p>
        </header>

        <HelpAccordion text={helpText} />

        <div className="space-y-3">
          <p className="text-sm font-semibold text-slate-700">{questionLabel}</p>
          {children}
          {microcopy ? <p className="text-xs text-slate-500">{microcopy}</p> : null}
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
            className="ds-button-primary"
          >
            {nextLabel}
          </button>
        </div>
      </div>
    </section>
  )
}
