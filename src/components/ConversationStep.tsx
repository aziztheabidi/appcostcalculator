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
    <section className="ds-card min-w-0 p-5 sm:p-8">
      <div className="animate-fade-slide space-y-6">
        <header className="min-w-0 space-y-2">
          <h2 className="ds-subheading break-words">{title}</h2>
          <p className="ds-body break-words">{description}</p>
          <div className="min-h-5">
            {microcopyLoading ? (
              <div className="h-4 w-3/4 animate-pulse rounded bg-slate-200/80" aria-hidden="true" />
            ) : microcopy ? (
              <p key={microcopy} className="pixact-clamp-2 pixact-fade-in break-words text-xs text-slate-500">
                {microcopy}
              </p>
            ) : null}
          </div>
        </header>

        <HelpAccordion text={helpText} />

        <div className="min-w-0 space-y-2">
          <p className="break-words text-sm font-semibold text-slate-700">{questionLabel}</p>
          {children}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
          <button
            type="button"
            onClick={onBack}
            disabled={!onBack}
            className="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-40 sm:min-h-11 sm:w-auto"
          >
            Back
          </button>
          <button
            type="button"
            onClick={onNext}
            disabled={nextDisabled}
            className="ds-button-primary min-h-12 w-full justify-center sm:min-h-11 sm:min-w-[8.5rem] sm:w-auto"
          >
            {nextLabel}
          </button>
        </div>
      </div>
    </section>
  )
}
