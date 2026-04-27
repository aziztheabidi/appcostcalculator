import type { AddonOptionConfig, Complexity, Timeline } from "../types/calculator"

interface RecommendationParams {
  complexity: Complexity | null
  timeline: Timeline | null
  selectedAddons: string[]
  addonOptions: AddonOptionConfig[]
  totalEstimate: number
}

export const buildGuidanceInsights = ({
  complexity,
  timeline,
  selectedAddons,
  addonOptions,
  totalEstimate,
}: RecommendationParams): string[] => {
  const insights: string[] = []
  const selectedAddonLabels = addonOptions
    .filter((option) => selectedAddons.includes(option.value))
    .map((option) => option.label.toLowerCase())
  const featureCount = selectedAddons.length
  const hasAi = selectedAddonLabels.some((label) => /\bai\b/.test(label))
  const highComplexity = complexity === "premium"
  const lowBudgetSignal = totalEstimate < 18000

  if (featureCount >= 3) {
    insights.push("You selected several capabilities; starting with an MVP scope can reduce initial delivery risk.")
  }

  if (hasAi) {
    insights.push("AI-related features usually need additional planning for data quality, model behavior, and safeguards.")
  }

  if (timeline === "urgent") {
    insights.push("Urgent delivery typically increases cost because teams run discovery and implementation in parallel.")
  }

  if (lowBudgetSignal && highComplexity) {
    insights.push("Your current budget signal and complexity are far apart; a phased build approach can improve feasibility.")
  }

  if (!insights.length) {
    insights.push("Your current selections suggest a balanced build path with predictable delivery planning.")
  }

  if (insights.length < 3) {
    insights.push("We can refine this estimate in a short consultation to prioritize features and timeline trade-offs.")
  }

  return insights.slice(0, 3)
}
