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
  microcopyLoading?: boolean
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
  microcopyLoading = false,
}: ConversationStepProps) => {
  return (
    <section className="ds-card p-6 sm:p-8">
      <div className="animate-fade-slide space-y-5">
        <header className="space-y-2">
          <h2 className="ds-subheading">{title}</h2>
          <p className="ds-body">{description}</p>
          <div className="min-h-5">
            {microcopyLoading ? (
              <div className="h-4 w-3/4 animate-pulse rounded bg-slate-200/80" aria-hidden="true" />
            ) : microcopy ? (
              <p className="text-xs text-slate-500 transition-opacity duration-200">{microcopy}</p>
            ) : null}
          </div>
        </header>

        <HelpAccordion text={helpText} />

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
            className="ds-button-primary"
          >
            {nextLabel}
          </button>
        </div>
      </div>
    </section>
  )
}
