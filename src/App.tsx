import { useMemo, useState } from "react"
import { EstimatePreview } from "./components/EstimatePreview"
import { ExplanationBox } from "./components/ExplanationBox"
import { LeadCaptureForm } from "./components/LeadCaptureForm"
import { OptionGrid } from "./components/OptionGrid"
import { ProgressBar } from "./components/ProgressBar"
import { StepLayout } from "./components/StepLayout"
import { loadRuntimeConfig } from "./config/runtime"
import { useCalculator } from "./hooks/useCalculator"
import { formatCurrency } from "./lib/pricingEngine"
import { submitCalculatorLead } from "./lib/wordpressApi"

const totalSteps = 5

function App() {
  const runtime = useMemo(() => loadRuntimeConfig(), [])
  const calculatorConfig = runtime.config.calculatorConfig
  const { formState, estimate, setProjectType, setComplexity, setTimeline, toggleAddon, updateLead } = useCalculator(calculatorConfig)
  const [step, setStep] = useState(1)
  const [submitting, setSubmitting] = useState(false)
  const [isSubmitted, setIsSubmitted] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const canContinue = (
    (step === 1 && Boolean(formState.projectType)) ||
    (step === 2 && Boolean(formState.complexity)) ||
    (step === 3 && Boolean(formState.timeline)) ||
    step === 4
  )

  const canSubmitLead = Boolean(formState.lead.fullName && formState.lead.email)

  const nextStep = () => {
    setStep((current) => Math.min(current + 1, totalSteps))
  }

  const previousStep = () => {
    setStep((current) => Math.max(current - 1, 1))
  }

  const handleSubmitLead = async () => {
    if (!canSubmitLead) {
      setError("Please add at least your full name and work email.")
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

  return (
    <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-10">
      <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
        <section className="space-y-5">
          <div className="rounded-3xl border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-200/60 backdrop-blur-sm sm:p-7">
            <p className="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold tracking-wide text-indigo-700 uppercase">
              Embedded cost calculator
            </p>
            <h1 className="mt-4 text-3xl leading-tight font-bold text-slate-900 sm:text-4xl">
              Plan your {formState.projectType ?? "digital"} project budget with clarity.
            </h1>
            <p className="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base">
              Premium, step-by-step estimate flow designed for WordPress shortcode embeds.
              This build runs with {modeLabel} and communicates through WordPress REST only.
            </p>
            <p className="mt-3 text-xs text-slate-500">Environment: {runtime.config.siteUrl}</p>
          </div>

          <ProgressBar currentStep={step} totalSteps={totalSteps} />

          {step === 1 ? (
            <StepLayout
              title={calculatorConfig.ui.step1Title}
              subtitle={calculatorConfig.ui.step1Subtitle}
              footer={
                <div className="flex justify-end">
                  <button
                    type="button"
                    onClick={nextStep}
                    disabled={!canContinue}
                    className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                  >
                    Continue
                  </button>
                </div>
              }
            >
              <OptionGrid
                options={calculatorConfig.projectOptions}
                selectedValues={formState.projectType ? [formState.projectType] : []}
                onToggle={(value) => setProjectType(value)}
              />
              <ExplanationBox text={calculatorConfig.ui.step1Explanation} />
            </StepLayout>
          ) : null}

          {step === 2 ? (
            <StepLayout
              title={calculatorConfig.ui.step2Title}
              subtitle={calculatorConfig.ui.step2Subtitle}
              footer={
                <div className="flex items-center justify-between">
                  <button type="button" onClick={previousStep} className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">
                    Back
                  </button>
                  <button
                    type="button"
                    onClick={nextStep}
                    disabled={!canContinue}
                    className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                  >
                    Continue
                  </button>
                </div>
              }
            >
              <OptionGrid
                options={calculatorConfig.complexityOptions}
                selectedValues={formState.complexity ? [formState.complexity] : []}
                onToggle={(value) => setComplexity(value)}
              />
              <ExplanationBox text={calculatorConfig.ui.step2Explanation} />
            </StepLayout>
          ) : null}

          {step === 3 ? (
            <StepLayout
              title={calculatorConfig.ui.step3Title}
              subtitle={calculatorConfig.ui.step3Subtitle}
              footer={
                <div className="flex items-center justify-between">
                  <button type="button" onClick={previousStep} className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">
                    Back
                  </button>
                  <button
                    type="button"
                    onClick={nextStep}
                    disabled={!canContinue}
                    className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                  >
                    Continue
                  </button>
                </div>
              }
            >
              <OptionGrid
                options={calculatorConfig.timelineOptions}
                selectedValues={formState.timeline ? [formState.timeline] : []}
                onToggle={(value) => setTimeline(value)}
              />
              <ExplanationBox text={calculatorConfig.ui.step3Explanation} />
            </StepLayout>
          ) : null}

          {step === 4 ? (
            <StepLayout
              title={calculatorConfig.ui.step4Title}
              subtitle={calculatorConfig.ui.step4Subtitle}
              footer={
                <div className="flex items-center justify-between">
                  <button type="button" onClick={previousStep} className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">
                    Back
                  </button>
                  <button type="button" onClick={nextStep} className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">
                    Continue
                  </button>
                </div>
              }
            >
              <OptionGrid options={calculatorConfig.addonOptions} selectedValues={formState.addons} onToggle={toggleAddon} multi />
              <ExplanationBox text={calculatorConfig.ui.step4Explanation} />
            </StepLayout>
          ) : null}

          {step === 5 ? (
            <StepLayout
              title={isSubmitted ? "Your estimate is ready" : calculatorConfig.ui.step5Title}
              subtitle={calculatorConfig.ui.step5Subtitle}
              footer={
                <div className="flex items-center justify-between">
                  <button type="button" onClick={previousStep} className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">
                    Back
                  </button>
                  <button
                    type="button"
                    onClick={handleSubmitLead}
                    disabled={submitting || isSubmitted}
                    className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-indigo-300"
                  >
                    {isSubmitted ? "Submitted" : submitting ? "Submitting..." : "Get my estimate"}
                  </button>
                </div>
              }
            >
              <LeadCaptureForm lead={formState.lead} onChange={updateLead} />
              {isSubmitted ? (
                <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                  Thanks! Your request is submitted successfully. Here is your estimate range:
                </div>
              ) : null}
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p className="font-semibold text-slate-900">Estimated range: {formatCurrency(estimate.total)}</p>
                <p className="mt-1">
                  This is a directional price based on your current selections. Final proposal details come after review.
                </p>
              </div>
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
              <ExplanationBox text={calculatorConfig.ui.step5Explanation} />
            </StepLayout>
          ) : null}
        </section>

        <aside className="space-y-4">
          <EstimatePreview estimate={estimate} />
          <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
            <p className="font-semibold text-slate-900">CTA-focused embed</p>
            <p className="mt-2">
              Use this app output in WordPress shortcode containers. It is frontend-only and REST-ready.
            </p>
          </div>
        </aside>
      </div>
    </main>
  )
}

export default App
