import type {
  AddonOptionConfig,
  CalculatorPricingConfig,
  ComplexityOptionConfig,
  ProjectOptionConfig,
  TimelineOptionConfig,
} from "../types/calculator"

export const defaultCalculatorConfig: CalculatorPricingConfig = {
  projectOptions: [
    { value: "app", label: "Mobile or Web App", description: "Product-focused application with user interactions.", badge: "Popular", price: 18000 },
    { value: "website", label: "Marketing Website", description: "High-converting brand or lead generation website.", badge: "", price: 9000 },
    { value: "saas", label: "SaaS Platform", description: "Subscription product with accounts, billing, and dashboards.", badge: "High ROI", price: 28000 },
  ],
  complexityOptions: [
    { value: "basic", label: "Lean MVP", description: "Essential flow, focused features, quick to market.", badge: "", multiplier: 1 },
    { value: "advanced", label: "Growth Ready", description: "Richer UX, more integrations, and polished components.", badge: "", multiplier: 1.35 },
    { value: "premium", label: "Enterprise", description: "Deep custom workflows, scale-ready architecture.", badge: "", multiplier: 1.7 },
  ],
  timelineOptions: [
    { value: "standard", label: "Standard Timeline", description: "Balanced execution with full discovery cycle.", badge: "", multiplier: 1 },
    { value: "accelerated", label: "Accelerated", description: "Faster delivery with prioritized milestones.", badge: "", multiplier: 1.18 },
    { value: "urgent", label: "Urgent Launch", description: "High-priority execution for immediate release.", badge: "Fastest", multiplier: 1.35 },
  ],
  addonOptions: [
    { value: "designSystem", label: "Design System", description: "Reusable UI foundation for scale.", badge: "", price: 2500 },
    { value: "analytics", label: "Analytics Stack", description: "Event tracking and conversion visibility.", badge: "", price: 1800 },
    { value: "integrations", label: "Third-Party Integrations", description: "CRM, payments, and workflow automation.", badge: "", price: 3200 },
    { value: "seo", label: "SEO + Content Setup", description: "Technical optimization and launch content structure.", badge: "", price: 1500 },
  ],
  ui: {
    step1Title: "What are you building?",
    step1Subtitle: "Select the primary product type so we can anchor your estimate.",
    step1Explanation: "Project type sets the baseline effort. A SaaS platform includes core product architecture and usually carries a higher starting point than a brochure website.",
    step2Title: "How complex should it be?",
    step2Subtitle: "Complexity defines how deep we go on features and UX polish.",
    step2Explanation: "Higher complexity increases engineering and QA depth. It reflects integrations, edge cases, and the level of product quality expected at launch.",
    step3Title: "What timeline are you targeting?",
    step3Subtitle: "Delivery speed impacts team allocation and pricing.",
    step3Explanation: "Accelerated or urgent timelines require compressed planning and parallel implementation. That usually increases cost due to dedicated resourcing.",
    step4Title: "Add strategic extras",
    step4Subtitle: "Choose add-ons to fine-tune the proposal range.",
    step4Explanation: "Add-ons are fixed modules layered on top of your base scope. They can increase launch impact while keeping planning transparent.",
    step5Title: "Unlock final estimate",
    step5Subtitle: "Share your details to receive a tailored follow-up scope.",
    step5Explanation: "Lead capture appears before the final CTA so your team can follow up with a tailored discovery call and accurate proposal.",
  },
}

const mergeArray = <T extends { value: string }>(defaults: T[], incoming?: T[]): T[] => {
  if (!incoming || !incoming.length) return defaults
  const map = new Map(incoming.map((item) => [item.value, item]))
  return defaults.map((item) => ({ ...item, ...(map.get(item.value) ?? {}) }))
}

export const mergeCalculatorConfig = (
  incoming?: Partial<CalculatorPricingConfig>,
): CalculatorPricingConfig => {
  if (!incoming) return defaultCalculatorConfig
  return {
    projectOptions: mergeArray<ProjectOptionConfig>(defaultCalculatorConfig.projectOptions, incoming.projectOptions),
    complexityOptions: mergeArray<ComplexityOptionConfig>(defaultCalculatorConfig.complexityOptions, incoming.complexityOptions),
    timelineOptions: mergeArray<TimelineOptionConfig>(defaultCalculatorConfig.timelineOptions, incoming.timelineOptions),
    addonOptions: mergeArray<AddonOptionConfig>(defaultCalculatorConfig.addonOptions, incoming.addonOptions),
    ui: { ...defaultCalculatorConfig.ui, ...(incoming.ui ?? {}) },
  }
}
