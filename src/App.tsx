import { lazy, Suspense, useEffect, useMemo, useState, type ReactNode } from "react"
import { CalculatorLayout } from "./components/CalculatorLayout"
import { ConversationStep } from "./components/ConversationStep"
import { OptionCard } from "./components/OptionCard"
import { ProgressBar } from "./components/ProgressBar"
import { loadRuntimeConfig } from "./config/runtime"
import { useCalculator } from "./hooks/useCalculator"
import { fetchMicrocopy } from "./lib/aiMicrocopy"
import { formatCurrency } from "./lib/pricingEngine"
import { buildGuidanceInsights } from "./lib/recommendationEngine"
import { submitCalculatorLead } from "./lib/wordpressApi"

const totalSteps = 5
const LeadCaptureForm = lazy(() =>
  import("./components/LeadCaptureForm").then((module) => ({ default: module.LeadCaptureForm })),
)
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const phoneRegex = /^[+\d\s().-]{7,20}$/

const iconClass = "h-4 w-4"
const AppTypeIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className={iconClass} aria-hidden="true">
    <rect x="4" y="3" width="16" height="18" rx="2" />
    <path d="M9 7h6M9 12h6M9 17h3" />
  </svg>
)
const WebsiteIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className={iconClass} aria-hidden="true">
    <rect x="3" y="4" width="18" height="16" rx="2" />
    <path d="M3 9h18M8 4v5" />
  </svg>
)
const SaasIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className={iconClass} aria-hidden="true">
    <ellipse cx="12" cy="6" rx="7" ry="3" />
    <path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6" />
  </svg>
)
const ComplexityIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className={iconClass} aria-hidden="true">
    <path d="M4 16l4-4 3 3 6-6 3 3" />
  </svg>
)
const TimelineIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className={iconClass} aria-hidden="true">
    <circle cx="12" cy="12" r="8" />
    <path d="M12 8v5l3 2" />
  </svg>
)
const AddonIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className={iconClass} aria-hidden="true">
    <path d="M12 5v14M5 12h14" />
  </svg>
)

function App() {
  const runtime = useMemo(() => loadRuntimeConfig(), [])
  const calculatorConfig = runtime.config.calculatorConfig
  const { formState, estimate, setProjectType, setComplexity, setTimeline, toggleAddon, updateLead } = useCalculator(calculatorConfig)
  const [renderedStep, setRenderedStep] = useState(1)
  const [transitionStage, setTransitionStage] = useState<"idle" | "exiting" | "entering">("idle")
  const [transitionDirection, setTransitionDirection] = useState<1 | -1>(1)
  const [submitting, setSubmitting] = useState(false)
  const [isSubmitted, setIsSubmitted] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; phone?: string }>({})
  const [aiMicrocopy, setAiMicrocopy] = useState<Record<string, { helper: string; explanation: string; recommendation: string }>>({})
  const [microcopyLoadingByStep, setMicrocopyLoadingByStep] = useState<Record<string, boolean>>({})

  const canContinue = (
    (renderedStep === 1 && Boolean(formState.projectType)) ||
    (renderedStep === 2 && Boolean(formState.complexity)) ||
    (renderedStep === 3 && Boolean(formState.timeline)) ||
    renderedStep === 4
  )
  const isStepTransitioning = transitionStage !== "idle"

  const canSubmitLead = Boolean(formState.lead.fullName && formState.lead.email && !submitting && !isSubmitted)

  const nextStep = () => {
    if (isStepTransitioning) return
    if (renderedStep >= totalSteps) return
    setTransitionDirection(1)
    setTransitionStage("exiting")
    window.setTimeout(() => {
      setRenderedStep((current) => Math.min(current + 1, totalSteps))
      setTransitionStage("entering")
      window.setTimeout(() => setTransitionStage("idle"), 220)
    }, 220)
  }

  const previousStep = () => {
    if (isStepTransitioning) return
    if (renderedStep <= 1) return
    setTransitionDirection(-1)
    setTransitionStage("exiting")
    window.setTimeout(() => {
      setRenderedStep((current) => Math.max(current - 1, 1))
      setTransitionStage("entering")
      window.setTimeout(() => setTransitionStage("idle"), 220)
    }, 220)
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
      setRenderedStep(5)
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
  const timelineLabel = formState.timeline ?? "standard"
  const complexityLabel = formState.complexity ?? "basic"
  const guidanceInsights = buildGuidanceInsights({
    complexity: formState.complexity,
    timeline: formState.timeline,
    selectedAddons: formState.addons,
    addonOptions: calculatorConfig.addonOptions,
    totalEstimate: estimate.total,
  })
  const activeStepKey = `step${renderedStep}`
  const activeAi = aiMicrocopy[activeStepKey]
  const activeMicrocopyLoading = Boolean(microcopyLoadingByStep[activeStepKey])
  const activeRecommendation = activeAi?.recommendation ?? calculatorConfig.ui.resultRecommendation
  const mergedInsights = activeAi?.recommendation
    ? [activeAi.recommendation, ...guidanceInsights].slice(0, 3)
    : guidanceInsights
  const mobileCtaLabel: "Continue" | "See Estimate" = renderedStep < 5 ? "Continue" : "See Estimate"
  const mobileCtaDisabled = renderedStep < 5 ? (!canContinue || isStepTransitioning) : (submitting || isSubmitted)
  const handleMobileCta = () => {
    if (renderedStep < 5) {
      nextStep()
      return
    }
    handleSubmitLead()
  }

  const meaningfulAnswers = useMemo(() => {
    if (renderedStep === 1) {
      return { projectType: formState.projectType }
    }
    if (renderedStep === 2) {
      return { projectType: formState.projectType, complexity: formState.complexity }
    }
    if (renderedStep === 3) {
      return {
        projectType: formState.projectType,
        complexity: formState.complexity,
        timeline: formState.timeline,
      }
    }
    return {
      projectType: formState.projectType,
      complexity: formState.complexity,
      timeline: formState.timeline,
      addons: [...formState.addons].sort(),
    }
  }, [renderedStep, formState.projectType, formState.complexity, formState.timeline, formState.addons])

  useEffect(() => {
    const stepKey = `step${renderedStep}`
    const timer = window.setTimeout(async () => {
      setMicrocopyLoadingByStep((prev) => ({ ...prev, [stepKey]: true }))
      const data = await fetchMicrocopy(stepKey, meaningfulAnswers, "app-web-cost-calculator")
      if (data) {
        setAiMicrocopy((prev) => ({
          ...prev,
          [stepKey]: {
            helper: data.helper_text,
            explanation: data.explanation,
            recommendation: data.recommendation,
          },
        }))
      }
      setMicrocopyLoadingByStep((prev) => ({ ...prev, [stepKey]: false }))
    }, 300)

    return () => window.clearTimeout(timer)
  }, [renderedStep, meaningfulAnswers])

  const renderSelectableCards = <T extends string,>(
    options: Array<{ value: T; label: string; description: string; badge?: string }>,
    selectedValues: T[],
    onToggle: (value: T) => void,
    iconResolver?: (value: T) => ReactNode,
  ) => (
    <div className="grid gap-3 sm:grid-cols-2">
      {options.map((option) => {
        const selected = selectedValues.includes(option.value)
        return (
          <OptionCard
            key={option.value}
            label={option.label}
            description={option.description}
            icon={iconResolver ? iconResolver(option.value) : undefined}
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
      <div className="ds-card p-6 sm:p-8">
        <p className="inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold tracking-wide text-red-700 uppercase">
          Conversational cost calculator
        </p>
        <h1 className="ds-heading mt-4">
          Build your project estimate in minutes.
        </h1>
        <p className="ds-body mt-2">
          Step-by-step cost calculator for app and web projects. Current mode: {modeLabel}.
        </p>
      </div>

      <ProgressBar currentStep={renderedStep} totalSteps={totalSteps} />

      <div
        className={`step-transition ${
          transitionStage === "exiting"
            ? `step-exit-${transitionDirection === 1 ? "forward" : "back"}`
            : transitionStage === "entering"
              ? `step-enter-${transitionDirection === 1 ? "forward" : "back"}`
              : ""
        }`}
      >
      {renderedStep === 1 ? (
        <ConversationStep
          title={calculatorConfig.ui.step1Title}
          description={calculatorConfig.ui.step1Subtitle}
          helpText={activeAi?.explanation ?? calculatorConfig.ui.step1Explanation}
          questionLabel="Choose project type"
          microcopy={activeAi?.helper ?? calculatorConfig.ui.step1Microcopy}
          microcopyLoading={activeMicrocopyLoading}
          onNext={nextStep}
          nextDisabled={!canContinue || isStepTransitioning}
        >
          {renderSelectableCards(
            calculatorConfig.projectOptions,
            formState.projectType ? [formState.projectType] : [],
            setProjectType,
            (value) => {
              if (value === "app") return <AppTypeIcon />
              if (value === "website") return <WebsiteIcon />
              return <SaasIcon />
            },
          )}
        </ConversationStep>
      ) : null}

      {renderedStep === 2 ? (
        <ConversationStep
          title={calculatorConfig.ui.step2Title}
          description={calculatorConfig.ui.step2Subtitle}
          helpText={activeAi?.explanation ?? calculatorConfig.ui.step2Explanation}
          questionLabel="Choose complexity level"
          microcopy={activeAi?.helper ?? calculatorConfig.ui.step2Microcopy}
          microcopyLoading={activeMicrocopyLoading}
          onBack={previousStep}
          onNext={nextStep}
          nextDisabled={!canContinue || isStepTransitioning}
        >
          {renderSelectableCards(
            calculatorConfig.complexityOptions,
            formState.complexity ? [formState.complexity] : [],
            setComplexity,
            () => <ComplexityIcon />,
          )}
        </ConversationStep>
      ) : null}

      {renderedStep === 3 ? (
        <ConversationStep
          title={calculatorConfig.ui.step3Title}
          description={calculatorConfig.ui.step3Subtitle}
          helpText={activeAi?.explanation ?? calculatorConfig.ui.step3Explanation}
          questionLabel="Choose delivery timeline"
          microcopy={activeAi?.helper ?? calculatorConfig.ui.step3Microcopy}
          microcopyLoading={activeMicrocopyLoading}
          onBack={previousStep}
          onNext={nextStep}
          nextDisabled={!canContinue || isStepTransitioning}
        >
          {renderSelectableCards(
            calculatorConfig.timelineOptions,
            formState.timeline ? [formState.timeline] : [],
            setTimeline,
            () => <TimelineIcon />,
          )}
        </ConversationStep>
      ) : null}

      {renderedStep === 4 ? (
        <ConversationStep
          title={calculatorConfig.ui.step4Title}
          description={calculatorConfig.ui.step4Subtitle}
          helpText={activeAi?.explanation ?? calculatorConfig.ui.step4Explanation}
          questionLabel="Choose add-ons"
          microcopy={activeAi?.helper ?? calculatorConfig.ui.step4Microcopy}
          microcopyLoading={activeMicrocopyLoading}
          onBack={previousStep}
          onNext={nextStep}
          nextDisabled={isStepTransitioning}
          nextLabel="Continue"
        >
          {renderSelectableCards(calculatorConfig.addonOptions, formState.addons, toggleAddon, () => <AddonIcon />)}
        </ConversationStep>
      ) : null}

      {renderedStep === 5 ? (
        <ConversationStep
          title={isSubmitted ? "Your estimate is ready" : calculatorConfig.ui.step5Title}
          description={calculatorConfig.ui.step5Subtitle}
          helpText={activeAi?.explanation ?? calculatorConfig.ui.step5Explanation}
          questionLabel="Share your contact details"
          onBack={previousStep}
          onNext={handleSubmitLead}
          nextLabel={isSubmitted ? "Submitted" : submitting ? "Submitting..." : "Get my estimate"}
          nextDisabled={submitting || isSubmitted || isStepTransitioning}
        >
          <Suspense fallback={<div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Loading form...</div>}>
            <LeadCaptureForm
              lead={formState.lead}
              onChange={updateLead}
              emailError={fieldErrors.email}
              phoneError={fieldErrors.phone}
            />
          </Suspense>
          {!isSubmitted ? (
            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
              {calculatorConfig.ui.leadFramingMessage}
            </div>
          ) : null}
          {isSubmitted ? (
            <div className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
              <p className="text-base font-semibold">
                Estimated range: {formatCurrency(Math.round(estimate.total * 0.9))} - {formatCurrency(Math.round(estimate.total * 1.1))}
              </p>
              <p>Timeline: {timelineLabel}</p>
              <p>Complexity: {complexityLabel}</p>
              <div className="rounded-xl border border-emerald-200 bg-white/70 p-3 text-sm">
                <p className="font-semibold">Recommendation</p>
                <p className="mt-1">{activeRecommendation}</p>
              </div>
              <ul className="space-y-1 text-sm">
                {mergedInsights.map((insight) => (
                  <li key={insight}>- {insight}</li>
                ))}
              </ul>
              <p className="text-xs text-emerald-800/80">{calculatorConfig.ui.resultTrustLine}</p>
              <div className="grid gap-2 sm:grid-cols-3">
                <button type="button" className="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold">
                  {calculatorConfig.ui.ctaConsultation}
                </button>
                <button type="button" className="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold">
                  {calculatorConfig.ui.ctaPrototype}
                </button>
                <button type="button" className="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold">
                  {calculatorConfig.ui.ctaDetailedEstimate}
                </button>
              </div>
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
      </div>
    </>
  )

  return (
    <CalculatorLayout
      left={leftConversation}
      estimate={estimate}
      timeline={timelineLabel}
      complexity={complexityLabel}
      insights={mergedInsights}
      mobileCtaLabel={mobileCtaLabel}
      onMobileCtaClick={handleMobileCta}
      mobileCtaDisabled={mobileCtaDisabled}
    />
  )
}

export default App
