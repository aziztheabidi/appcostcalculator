import type {
  CalculatorFormState,
  CalculatorPricingConfig,
  EstimateBreakdown,
} from "../types/calculator"

export const calculateEstimate = (
  state: CalculatorFormState,
  pricingConfig: CalculatorPricingConfig,
): EstimateBreakdown => {
  if (!state.projectType || !state.complexity || !state.timeline) {
    return {
      baseCost: 0,
      complexityMultiplier: 1,
      timelineMultiplier: 1,
      addonsCost: 0,
      subtotal: 0,
      total: 0,
    }
  }

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

  return {
    baseCost,
    complexityMultiplier,
    timelineMultiplier,
    addonsCost,
    subtotal: Math.round(subtotal),
    total,
  }
}

export const formatCurrency = (amount: number): string =>
  new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 0,
  }).format(amount)
