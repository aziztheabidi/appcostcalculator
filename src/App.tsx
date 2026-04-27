import { lazy, Suspense, useEffect, useMemo, useState, type ReactNode } from "react"
import { CalculatorLayout } from "./components/CalculatorLayout"
import { ConversationStep } from "./components/ConversationStep"
import { OptionCard } from "./components/OptionCard"
import { ProgressBar } from "./components/ProgressBar"
import { loadRuntimeConfig } from "./config/runtime"
import { useRemoteCalculatorState } from "./hooks/useRemoteCalculatorState"
import { useCalculator } from "./hooks/useCalculator"
import { fetchMicrocopy } from "./lib/aiMicrocopy"
import { formatCurrency } from "./lib/pricingEngine"
import { buildGuidanceInsights } from "./lib/recommendationEngine"
import { submitCalculatorLead } from "./lib/wordpressApi"

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

const getOptionIcon = (iconKey?: string): ReactNode => {
  if (iconKey === "app") return <AppTypeIcon />
  if (iconKey === "website") return <WebsiteIcon />
  if (iconKey === "saas") return <SaasIcon />
  if (iconKey === "complexity") return <ComplexityIcon />
  if (iconKey === "timeline") return <TimelineIcon />
  if (iconKey === "addon") return <AddonIcon />
  return undefined
}

const normalizeAiText = (text?: string): string => {
  if (!text) return ""
  const cleaned = text
    .replace(/^[\s\-•*]+/g, "")
    .replace(/\n+/g, " ")
    .replace(/\s{2,}/g, " ")
    .trim()
  if (!cleaned) return ""
  const sentenceChunks = cleaned.match(/[^.!?]+[.!?]?/g)?.map((chunk) => chunk.trim()).filter(Boolean) ?? []
  return sentenceChunks.slice(0, 2).join(" ")
}

function App() {
  const runtime = useMemo(() => loadRuntimeConfig(), [])
  const { calculatorConfig, remoteConfig } = useRemoteCalculatorState(runtime)
  const [aiRecommendationsEnabled, setAiRecommendationsEnabled] = useState(
    runtime.config.aiEnabled ?? true,
  )
  const totalSteps = calculatorConfig.steps.length
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

  useEffect(() => {
    if (remoteConfig) {
      setAiRecommendationsEnabled(remoteConfig.aiRecommendationsEnabled ?? true)
    }
  }, [remoteConfig])

  useEffect(() => {
    if (typeof window === "undefined") return
    if (!window.PixactCalculator) return
    window.PixactCalculator.aiEnabled = aiRecommendationsEnabled
  }, [aiRecommendationsEnabled])

  const currentStepConfig = calculatorConfig.steps[Math.max(0, renderedStep - 1)]
  const selectionFieldByImpact: Record<string, "projectType" | "complexity" | "timeline" | "addons" | "lead"> = {
    projectType: "projectType",
    complexity: "complexity",
    timeline: "timeline",
    addons: "addons",
    lead: "lead",
  }
  const selectedField = currentStepConfig
    ? selectionFieldByImpact[currentStepConfig.pricingImpactKey] ?? null
    : null
  const canContinue = (() => {
    if (!currentStepConfig) return false
    if (selectedField === "projectType") return Boolean(formState.projectType)
    if (selectedField === "complexity") return Boolean(formState.complexity)
    if (selectedField === "timeline") return Boolean(formState.timeline)
    if (selectedField === "addons" || selectedField === "lead") return true
    return currentStepConfig.type !== "single"
  })()
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
      setRenderedStep(totalSteps)
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
  const activeStepKey = currentStepConfig?.id ?? calculatorConfig.steps[0]?.id ?? "platform"
  const activeAi = aiMicrocopy[activeStepKey]
  const activeMicrocopyLoading = Boolean(microcopyLoadingByStep[activeStepKey])
  const activeAiHelper = normalizeAiText(activeAi?.helper)
  const activeAiExplanation = normalizeAiText(activeAi?.explanation)
  const activeAiRecommendation = normalizeAiText(activeAi?.recommendation)
  const activeRecommendation = activeAiRecommendation || calculatorConfig.ui.resultRecommendation
  const mergedInsights = activeAi?.recommendation
    ? [activeAi.recommendation, ...guidanceInsights].slice(0, 3)
    : guidanceInsights
  const mobileCtaLabel: "Continue" | "See Estimate" = renderedStep < totalSteps ? "Continue" : "See Estimate"
  const mobileCtaDisabled = renderedStep < totalSteps ? (!canContinue || isStepTransitioning) : (submitting || isSubmitted)
  const handleMobileCta = () => {
    if (renderedStep < totalSteps) {
      nextStep()
      return
    }
    handleSubmitLead()
  }

  const meaningfulAnswers = useMemo(() => {
    if (selectedField === "projectType") {
      return { projectType: formState.projectType }
    }
    if (selectedField === "complexity") {
      return { projectType: formState.projectType, complexity: formState.complexity }
    }
    if (selectedField === "timeline") {
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
  }, [selectedField, formState.projectType, formState.complexity, formState.timeline, formState.addons])

  useEffect(() => {
    const stepKey = currentStepConfig?.id
    if (!stepKey) return
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
  }, [currentStepConfig?.id, meaningfulAnswers])

  const renderSelectableCards = <T extends string,>(
    options: Array<{ value: T; label: string; description: string; badge?: string }>,
    selectedValues: T[],
    onToggle: (value: T) => void,
    iconResolver?: (value: T) => ReactNode,
  ) => (
    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
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

  const getStepSelectedValues = (impactKey: string): string[] => {
    const field = selectionFieldByImpact[impactKey]
    if (field === "projectType") return formState.projectType ? [formState.projectType] : []
    if (field === "complexity") return formState.complexity ? [formState.complexity] : []
    if (field === "timeline") return formState.timeline ? [formState.timeline] : []
    if (field === "addons") return formState.addons
    return []
  }

  const handleStepOptionToggle = (impactKey: string, value: string) => {
    const field = selectionFieldByImpact[impactKey]
    if (field === "projectType") {
      setProjectType(value as "app" | "website" | "saas")
      return
    }
    if (field === "complexity") {
      setComplexity(value as "basic" | "advanced" | "premium")
      return
    }
    if (field === "timeline") {
      setTimeline(value as "standard" | "accelerated" | "urgent")
      return
    }
    if (field === "addons") {
      toggleAddon(value as "designSystem" | "analytics" | "integrations" | "seo" | "ai_features")
    }
  }

  const leftConversation = (
    <>
      <div className="ds-card space-y-2 p-6 sm:p-8">
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
      {currentStepConfig && currentStepConfig.type !== "input" ? (
        <ConversationStep
          title={currentStepConfig.title}
          description={currentStepConfig.helper}
          helpText={activeAiExplanation || currentStepConfig.explanation}
          questionLabel={currentStepConfig.questionLabel}
          microcopy={activeAiHelper || currentStepConfig.helper}
          microcopyLoading={activeMicrocopyLoading}
          onBack={renderedStep > 1 ? previousStep : undefined}
          onNext={nextStep}
          nextDisabled={!canContinue || isStepTransitioning}
        >
          {renderSelectableCards(
            currentStepConfig.options,
            getStepSelectedValues(currentStepConfig.pricingImpactKey),
            (value) => handleStepOptionToggle(currentStepConfig.pricingImpactKey, value),
            (value) => getOptionIcon(currentStepConfig.options.find((option) => option.value === value)?.icon),
          )}
        </ConversationStep>
      ) : null}

      {currentStepConfig?.type === "input" ? (
        <ConversationStep
          title={isSubmitted ? "Your estimate is ready" : currentStepConfig.title}
          description={currentStepConfig.helper}
          helpText={activeAiExplanation || currentStepConfig.explanation}
          questionLabel={currentStepConfig.questionLabel}
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
              <ul className="space-y-2 text-sm">
                {mergedInsights.map((insight) => (
                  <li key={insight} className="flex items-start gap-2 break-words">
                    <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500/80" aria-hidden="true" />
                    <span>{insight}</span>
                  </li>
                ))}
              </ul>
              <p className="text-xs text-emerald-800/80">{calculatorConfig.ui.resultTrustLine}</p>
              <p className="text-xs text-emerald-800/80">
                We can help you refine this into a working product direction before you commit.
              </p>
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
      recommendationText={activeRecommendation}
      recommendationLoading={activeMicrocopyLoading}
      mobileCtaLabel={mobileCtaLabel}
      onMobileCtaClick={handleMobileCta}
      mobileCtaDisabled={mobileCtaDisabled}
    />
  )
}

export default App
