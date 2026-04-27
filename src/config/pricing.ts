import type { AddonId, Complexity, ProjectType, Timeline } from "../types/calculator"

export const projectBasePricing: Record<ProjectType, number> = {
  app: 18000,
  website: 9000,
  saas: 28000,
}

export const complexityMultipliers: Record<Complexity, number> = {
  basic: 1,
  advanced: 1.35,
  premium: 1.7,
}

export const timelineMultipliers: Record<Timeline, number> = {
  standard: 1,
  accelerated: 1.18,
  urgent: 1.35,
}

export const addonPricing: Record<AddonId, number> = {
  designSystem: 2500,
  analytics: 1800,
  integrations: 3200,
  seo: 1500,
}
