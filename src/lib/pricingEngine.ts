import type {
  CalculatorFormState,
  CalculatorPricingConfig,
  EstimateBreakdown,
} from "../types/calculator"
import { applyPricingRules } from "./pricingRules"

export const calculateEstimate = (
  state: CalculatorFormState,
  pricingConfig: CalculatorPricingConfig,
): EstimateBreakdown => {
  return applyPricingRules(state, pricingConfig).estimate
}

export const formatCurrency = (amount: number): string =>
  new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 0,
  }).format(amount)
