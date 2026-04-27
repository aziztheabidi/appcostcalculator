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
    step1Title: "Where do you want your product to be available?",
    step1Subtitle: "Pick the platform strategy that best matches your launch goals.",
    step1Explanation: "Project type sets the baseline effort. A SaaS platform includes core product architecture and usually carries a higher starting point than a brochure website.",
    step1Microcopy: "Most startups choose cross-platform to launch faster and reduce initial cost.",
    step2Title: "How advanced should the first release be?",
    step2Subtitle: "Choose the level of depth needed for your first version.",
    step2Explanation: "Higher complexity increases engineering and QA depth. It reflects integrations, edge cases, and the level of product quality expected at launch.",
    step2Microcopy: "A focused MVP often helps teams validate faster before scaling scope.",
    step3Title: "When are you aiming to launch?",
    step3Subtitle: "Timeline affects team allocation and delivery strategy.",
    step3Explanation: "Accelerated or urgent timelines require compressed planning and parallel implementation. That usually increases cost due to dedicated resourcing.",
    step3Microcopy: "A realistic timeline usually improves quality and reduces rework.",
    step4Title: "Which capabilities are important for version one?",
    step4Subtitle: "Select only what is essential to keep the first release efficient.",
    step4Explanation: "Add-ons are fixed modules layered on top of your base scope. They can increase launch impact while keeping planning transparent.",
    step4Microcopy: "Prioritizing core features now can shorten delivery and control cost.",
    step5Title: "Unlock final estimate",
    step5Subtitle: "Share your details to receive a tailored follow-up scope.",
    step5Explanation: "Lead capture appears before the final CTA so your team can follow up with a tailored discovery call and accurate proposal.",
    leadFramingMessage: "We’ve prepared a tailored estimate based on your inputs. Enter your details to view your full breakdown.",
    resultRecommendation: "We recommend starting with a focused MVP to validate your core idea and scale efficiently.",
    resultTrustLine: "We do not lock you into this estimate. We first show a working direction or prototype before you commit.",
    ctaConsultation: "Schedule Free Consultation",
    ctaPrototype: "Request Prototype Preview",
    ctaDetailedEstimate: "Send Me Detailed Estimate",
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
