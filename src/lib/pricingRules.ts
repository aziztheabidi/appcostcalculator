import type { CalculatorFormState, CalculatorPricingConfig, EstimateBreakdown } from "../types/calculator"

const clampRange = (total: number): { estimateMin: number; estimateMax: number } => ({
  estimateMin: Math.max(0, Math.round(total * 0.9)),
  estimateMax: Math.round(total * 1.1),
})

const getComplexityLevel = (state: CalculatorFormState): string => {
  if (state.complexity === "premium" || state.addons.includes("ai_features")) return "enterprise"
  if (state.complexity === "advanced" || state.addons.length >= 2) return "advanced"
  return "basic"
}

const getTimelineLabel = (state: CalculatorFormState): string => {
  if (state.timeline === "urgent") return "4-8 weeks"
  if (state.timeline === "accelerated") return "6-10 weeks"
  return "8-14 weeks"
}

export interface PricingSummary {
  estimate: EstimateBreakdown
  estimateMin: number
  estimateMax: number
  timeline: string
  complexityLevel: string
}

export const applyPricingRules = (
  state: CalculatorFormState,
  pricingConfig: CalculatorPricingConfig,
): PricingSummary => {
  const projectOption = pricingConfig.projectOptions.find((option) => option.value === state.projectType)
  const complexityOption = pricingConfig.complexityOptions.find((option) => option.value === state.complexity)
  const timelineOption = pricingConfig.timelineOptions.find((option) => option.value === state.timeline)

  const baseCost = projectOption?.price ?? 0
  const complexityMultiplier = complexityOption?.multiplier ?? 1
  const timelineMultiplier = timelineOption?.multiplier ?? 1
  const addonsCost = state.addons.reduce((sum, addon) => {
    const addonOption = pricingConfig.addonOptions.find((option) => option.value === addon)
    return sum + (addonOption?.price ?? 0)
  }, 0)

  const subtotal = baseCost * complexityMultiplier * timelineMultiplier
  const total = Math.round(subtotal + addonsCost)
  const estimate: EstimateBreakdown = {
    baseCost,
    complexityMultiplier,
    timelineMultiplier,
    addonsCost,
    subtotal: Math.round(subtotal),
    total,
  }
  const { estimateMin, estimateMax } = clampRange(total)

  return {
    estimate,
    estimateMin,
    estimateMax,
    timeline: getTimelineLabel(state),
    complexityLevel: getComplexityLevel(state),
  }
}
