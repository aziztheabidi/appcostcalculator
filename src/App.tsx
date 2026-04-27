import { lazy, Suspense, useMemo, useState } from "react"
import { CalculatorLayout } from "./components/CalculatorLayout"
import { ConversationStep } from "./components/ConversationStep"
import { OptionCard } from "./components/OptionCard"
import { ProgressBar } from "./components/ProgressBar"
import { loadRuntimeConfig } from "./config/runtime"
import { useCalculator } from "./hooks/useCalculator"
import { formatCurrency } from "./lib/pricingEngine"
import { submitCalculatorLead } from "./lib/wordpressApi"

const totalSteps = 5
const LeadCaptureForm = lazy(() =>
  import("./components/LeadCaptureForm").then((module) => ({ default: module.LeadCaptureForm })),
)
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const phoneRegex = /^[+\d\s().-]{7,20}$/

function App() {
  const runtime = useMemo(() => loadRuntimeConfig(), [])
  const calculatorConfig = runtime.config.calculatorConfig
  const { formState, estimate, setProjectType, setComplexity, setTimeline, toggleAddon, updateLead } = useCalculator(calculatorConfig)
  const [step, setStep] = useState(1)
  const [submitting, setSubmitting] = useState(false)
  const [isSubmitted, setIsSubmitted] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; phone?: string }>({})

  const canContinue = (
    (step === 1 && Boolean(formState.projectType)) ||
    (step === 2 && Boolean(formState.complexity)) ||
    (step === 3 && Boolean(formState.timeline)) ||
    step === 4
  )

  const canSubmitLead = Boolean(formState.lead.fullName && formState.lead.email && !submitting && !isSubmitted)

  const nextStep = () => {
    setStep((current) => Math.min(current + 1, totalSteps))
  }

  const previousStep = () => {
    setStep((current) => Math.max(current - 1, 1))
  }

  const handleSubmitLead = async () => {
    if (submitting || isSubmitted) return

    if (!canSubmitLead) {
      setError("Please add at least your full name and work email.")
      return
    }

    const nextFieldErrors: { email?: string; phone?: string } = {}
    if (!emailRegex.test(formState.lead.email.trim())) {
      nextFieldErrors.email = "Enter a valid email address."
    }
    if (formState.lead.phone.trim() && !phoneRegex.test(formState.lead.phone.trim())) {
      nextFieldErrors.phone = "Enter a valid phone number."
    }
    setFieldErrors(nextFieldErrors)
    if (Object.keys(nextFieldErrors).length) {
      setError("Please fix the highlighted fields before submitting.")
      return
    }

    setError(null)
    setSubmitting(true)
    try {
      const estimateMin = Math.max(0, Math.round(estimate.total * 0.9))
      const estimateMax = Math.round(estimate.total * 1.1)

      await submitCalculatorLead(runtime, {
        formState,
        estimateMin,
        estimateMax,
      })
      setIsSubmitted(true)
      setStep(5)
    } catch (submissionError) {
      setError(
        submissionError instanceof Error
          ? submissionError.message
          : "Something went wrong. Please retry.",
      )
    } finally {
      setSubmitting(false)
    }
  }

  const modeLabel = runtime.mode === "wordpress" ? "WordPress mode" : "Mock mode"
  const timelineLabel = formState.timeline ?? "Not selected"
  const complexityLabel = formState.complexity ?? "Not selected"

  const renderSelectableCards = <T extends string,>(
    options: Array<{ value: T; label: string; description: string; badge?: string }>,
    selectedValues: T[],
    onToggle: (value: T) => void,
  ) => (
    <div className="grid gap-3 sm:grid-cols-2">
      {options.map((option) => {
        const selected = selectedValues.includes(option.value)
        return (
          <OptionCard
            key={option.value}
            label={option.label}
            description={option.description}
            badge={option.badge}
            selected={selected}
            onClick={() => onToggle(option.value)}
          />
        )
      })}
    </div>
  )

  const leftConversation = (
    <>
      <div className="rounded-3xl border border-white/60 bg-white/75 p-5 shadow-lg shadow-slate-200/60 backdrop-blur-md sm:p-7">
        <p className="inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold tracking-wide text-red-700 uppercase">
          Conversational cost calculator
        </p>
        <h1 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
          Build your project estimate in minutes.
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">
          Smart step-by-step flow for WordPress embeds. Current mode: {modeLabel}.
        </p>
      </div>

      <ProgressBar currentStep={step} totalSteps={totalSteps} />

      {step === 1 ? (
        <ConversationStep
          title={calculatorConfig.ui.step1Title}
          description={calculatorConfig.ui.step1Subtitle}
          helpText={calculatorConfig.ui.step1Explanation}
          questionLabel="Choose project type"
          onNext={nextStep}
          nextDisabled={!canContinue}
        >
          {renderSelectableCards(
            calculatorConfig.projectOptions,
            formState.projectType ? [formState.projectType] : [],
            setProjectType,
          )}
        </ConversationStep>
      ) : null}

      {step === 2 ? (
        <ConversationStep
          title={calculatorConfig.ui.step2Title}
          description={calculatorConfig.ui.step2Subtitle}
          helpText={calculatorConfig.ui.step2Explanation}
          questionLabel="Choose complexity level"
          onBack={previousStep}
          onNext={nextStep}
          nextDisabled={!canContinue}
        >
          {renderSelectableCards(
            calculatorConfig.complexityOptions,
            formState.complexity ? [formState.complexity] : [],
            setComplexity,
          )}
        </ConversationStep>
      ) : null}

      {step === 3 ? (
        <ConversationStep
          title={calculatorConfig.ui.step3Title}
          description={calculatorConfig.ui.step3Subtitle}
          helpText={calculatorConfig.ui.step3Explanation}
          questionLabel="Choose delivery timeline"
          onBack={previousStep}
          onNext={nextStep}
          nextDisabled={!canContinue}
        >
          {renderSelectableCards(
            calculatorConfig.timelineOptions,
            formState.timeline ? [formState.timeline] : [],
            setTimeline,
          )}
        </ConversationStep>
      ) : null}

      {step === 4 ? (
        <ConversationStep
          title={calculatorConfig.ui.step4Title}
          description={calculatorConfig.ui.step4Subtitle}
          helpText={calculatorConfig.ui.step4Explanation}
          questionLabel="Choose add-ons"
          onBack={previousStep}
          onNext={nextStep}
          nextLabel="Continue"
        >
          {renderSelectableCards(calculatorConfig.addonOptions, formState.addons, toggleAddon)}
        </ConversationStep>
      ) : null}

      {step === 5 ? (
        <ConversationStep
          title={isSubmitted ? "Your estimate is ready" : calculatorConfig.ui.step5Title}
          description={calculatorConfig.ui.step5Subtitle}
          helpText={calculatorConfig.ui.step5Explanation}
          questionLabel="Share your contact details"
          onBack={previousStep}
          onNext={handleSubmitLead}
          nextLabel={isSubmitted ? "Submitted" : submitting ? "Submitting..." : "Get my estimate"}
          nextDisabled={submitting || isSubmitted}
        >
          <Suspense fallback={<div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Loading form...</div>}>
            <LeadCaptureForm
              lead={formState.lead}
              onChange={updateLead}
              emailError={fieldErrors.email}
              phoneError={fieldErrors.phone}
            />
          </Suspense>
          {isSubmitted ? (
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
              Thanks! Submission successful. Your current estimate range is {formatCurrency(Math.round(estimate.total * 0.9))} - {formatCurrency(Math.round(estimate.total * 1.1))}.
            </div>
          ) : null}
          {error ? (
            <div className="rounded-2xl border border-red-200 bg-red-50 p-4">
              <p className="text-sm font-medium text-red-700">{error}</p>
              <button
                type="button"
                onClick={handleSubmitLead}
                disabled={submitting}
                className="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-red-300"
              >
                {submitting ? "Retrying..." : "Retry submission"}
              </button>
            </div>
          ) : null}
        </ConversationStep>
      ) : null}
    </>
  )

  return (
    <CalculatorLayout
      left={leftConversation}
      estimate={estimate}
      timeline={timelineLabel}
      complexity={complexityLabel}
    />
  )
}

export default App
