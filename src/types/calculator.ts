export type ProjectType = "app" | "website" | "saas"

export type Complexity = "basic" | "advanced" | "premium"

export type Timeline = "standard" | "accelerated" | "urgent"

export type AddonId = "designSystem" | "analytics" | "integrations" | "seo" | "ai_features"

export interface LeadFormData {
  fullName: string
  email: string
  phone: string
  company: string
  message: string
  honeypot: string
  startedAt: number
}

export interface CalculatorFormState {
  projectType: ProjectType | null
  complexity: Complexity | null
  timeline: Timeline | null
  addons: AddonId[]
  lead: LeadFormData
}

export interface EstimateBreakdown {
  baseCost: number
  complexityMultiplier: number
  timelineMultiplier: number
  addonsCost: number
  subtotal: number
  total: number
}

export interface OptionItem<TValue extends string> {
  value: TValue
  label: string
  description: string
  badge?: string
}

export interface ProjectOptionConfig extends OptionItem<ProjectType> {
  price: number
}

export interface ComplexityOptionConfig extends OptionItem<Complexity> {
  multiplier: number
}

export interface TimelineOptionConfig extends OptionItem<Timeline> {
  multiplier: number
}

export interface AddonOptionConfig extends OptionItem<AddonId> {
  price: number
}

export type CalculatorStepType = "single" | "multi" | "input"

export type PriceModifierLevel = "none" | "low" | "medium" | "high"

export interface CalculatorStepOption {
  label: string
  value: string
  description: string
  badge?: string
  icon?: string
  priceModifier: PriceModifierLevel
  tags: string[]
}

export interface CalculatorStepConfig {
  id: string
  title: string
  helper: string
  explanation: string
  questionLabel: string
  type: CalculatorStepType
  options: CalculatorStepOption[]
  pricingImpactKey: string
}

export interface CalculatorUiConfig {
  leadFramingMessage: string
  resultRecommendation: string
  resultTrustLine: string
  ctaConsultation: string
  ctaPrototype: string
  ctaDetailedEstimate: string
}

export interface CalculatorPricingConfig {
  steps: CalculatorStepConfig[]
  projectOptions: ProjectOptionConfig[]
  complexityOptions: ComplexityOptionConfig[]
  timelineOptions: TimelineOptionConfig[]
  addonOptions: AddonOptionConfig[]
  ui: CalculatorUiConfig
}

export interface PricingRules {
  basePrices: {
    app: number
    website: number
    saas: number
  }
  featureMultipliers: {
    low: number
    medium: number
    high: number
  }
  multipliers: {
    backendComplexity: number
    userRoles: number
    aiFeatures: number
  }
  timelineAdjustment: {
    normal: number
    fast: number
    urgent: number
  }
  complexityThresholds: {
    basic: number
    medium: number
    advanced: number
    enterprise: number
  }
}

export interface RemoteCalculatorConfig {
  steps: Array<{
    id: string
    title?: string
    helper?: string
    explanation?: string
    type?: "single" | "multi" | "input"
    options?: Array<{
      label: string
      value: string
      priceModifier?: PriceModifierLevel
      tags?: string[]
    }>
  }>
  advancedFeaturesStepEnabled?: boolean
  aiRecommendationsEnabled?: boolean
}
