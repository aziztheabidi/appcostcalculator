export type ProjectType = "app" | "website" | "saas"

export type Complexity = "basic" | "advanced" | "premium"

export type Timeline = "standard" | "accelerated" | "urgent"

export type AddonId = "designSystem" | "analytics" | "integrations" | "seo"

export interface LeadFormData {
  fullName: string
  email: string
  phone: string
  company: string
  message: string
  honeypot: string
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

export interface CalculatorUiConfig {
  step1Title: string
  step1Subtitle: string
  step1Explanation: string
  step2Title: string
  step2Subtitle: string
  step2Explanation: string
  step3Title: string
  step3Subtitle: string
  step3Explanation: string
  step4Title: string
  step4Subtitle: string
  step4Explanation: string
  step5Title: string
  step5Subtitle: string
  step5Explanation: string
}

export interface CalculatorPricingConfig {
  projectOptions: ProjectOptionConfig[]
  complexityOptions: ComplexityOptionConfig[]
  timelineOptions: TimelineOptionConfig[]
  addonOptions: AddonOptionConfig[]
  ui: CalculatorUiConfig
}
