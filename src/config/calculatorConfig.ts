import type {
  AddonOptionConfig,
  CalculatorPricingConfig,
  ComplexityOptionConfig,
  PricingRules,
  RemoteCalculatorConfig,
  ProjectOptionConfig,
  TimelineOptionConfig,
} from "../types/calculator"

/** Aligned with WordPress `get_default_pricing_rules()` — used when REST returns partial or invalid JSON. */
export const DEFAULT_PRICING_RULES: PricingRules = {
  basePrices: { app: 18000, website: 9000, saas: 28000 },
  featureMultipliers: { low: 1, medium: 1.2, high: 1.4 },
  multipliers: { backendComplexity: 1.2, userRoles: 1.1, aiFeatures: 1.25 },
  timelineAdjustment: { normal: 0, fast: 15, urgent: 25 },
  complexityThresholds: { basic: 0, medium: 2, advanced: 4, enterprise: 7 },
}

function mergeNumberRecord<T extends Record<string, number>>(defaults: T, patch: unknown): T {
  const out = { ...defaults }
  if (!patch || typeof patch !== "object" || Array.isArray(patch)) {
    return out
  }
  const record = patch as Record<string, unknown>
  for (const key of Object.keys(defaults) as (keyof T)[]) {
    const v = record[key as string]
    if (typeof v === "number" && Number.isFinite(v)) {
      out[key] = v as T[keyof T]
    } else if (typeof v === "string" && v.trim() !== "") {
      const n = Number(v)
      if (Number.isFinite(n)) {
        out[key] = n as T[keyof T]
      }
    }
  }
  return out
}

export function normalizePricingRules(input: unknown): PricingRules {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    return {
      basePrices: { ...DEFAULT_PRICING_RULES.basePrices },
      featureMultipliers: { ...DEFAULT_PRICING_RULES.featureMultipliers },
      multipliers: { ...DEFAULT_PRICING_RULES.multipliers },
      timelineAdjustment: { ...DEFAULT_PRICING_RULES.timelineAdjustment },
      complexityThresholds: { ...DEFAULT_PRICING_RULES.complexityThresholds },
    }
  }
  const raw = input as Partial<PricingRules>
  return {
    basePrices: mergeNumberRecord(DEFAULT_PRICING_RULES.basePrices, raw.basePrices),
    featureMultipliers: mergeNumberRecord(DEFAULT_PRICING_RULES.featureMultipliers, raw.featureMultipliers),
    multipliers: mergeNumberRecord(DEFAULT_PRICING_RULES.multipliers, raw.multipliers),
    timelineAdjustment: mergeNumberRecord(DEFAULT_PRICING_RULES.timelineAdjustment, raw.timelineAdjustment),
    complexityThresholds: mergeNumberRecord(DEFAULT_PRICING_RULES.complexityThresholds, raw.complexityThresholds),
  }
}

export const defaultCalculatorConfig: CalculatorPricingConfig = {
  steps: [
    {
      id: "platform",
      title: "Where do you want your product to be available?",
      helper: "Most startups choose cross-platform to launch faster and reduce initial cost.",
      explanation: "Platform choice determines architecture effort and release speed. Cross-platform often accelerates early validation while native can improve deep device-level experiences.",
      questionLabel: "Choose project type",
      type: "single",
      pricingImpactKey: "projectType",
      options: [
        {
          label: "Mobile or Web App",
          value: "app",
          description: "Product-focused application with user interactions.",
          badge: "Popular",
          icon: "app",
          priceModifier: "high",
          tags: ["app", "cross-platform"],
        },
        {
          label: "Marketing Website",
          value: "website",
          description: "High-converting brand or lead generation website.",
          icon: "website",
          priceModifier: "low",
          tags: ["web", "marketing"],
        },
        {
          label: "SaaS Platform",
          value: "saas",
          description: "Subscription product with accounts, billing, and dashboards.",
          badge: "High ROI",
          icon: "saas",
          priceModifier: "high",
          tags: ["saas", "product"],
        },
      ],
    },
    {
      id: "features",
      title: "How advanced should the first release be?",
      helper: "A focused MVP often helps teams validate faster before scaling scope.",
      explanation: "Complexity reflects feature depth, edge-case handling, and quality expectations. Higher complexity usually requires more planning, engineering, and QA effort.",
      questionLabel: "Choose complexity level",
      type: "single",
      pricingImpactKey: "complexity",
      options: [
        {
          label: "Lean MVP",
          value: "basic",
          description: "Essential flow, focused features, quick to market.",
          icon: "complexity",
          priceModifier: "low",
          tags: ["mvp", "lean"],
        },
        {
          label: "Growth Ready",
          value: "advanced",
          description: "Richer UX, more integrations, and polished components.",
          icon: "complexity",
          priceModifier: "medium",
          tags: ["growth", "integrations"],
        },
        {
          label: "Enterprise",
          value: "premium",
          description: "Deep custom workflows, scale-ready architecture.",
          icon: "complexity",
          priceModifier: "high",
          tags: ["enterprise", "advanced"],
        },
      ],
    },
    {
      id: "timeline",
      title: "When are you aiming to launch?",
      helper: "A realistic timeline usually improves quality and reduces rework.",
      explanation: "Timeline affects staffing and execution strategy. Compressed timelines often require parallel delivery, which can increase implementation overhead.",
      questionLabel: "Choose delivery timeline",
      type: "single",
      pricingImpactKey: "timeline",
      options: [
        {
          label: "Standard Timeline",
          value: "standard",
          description: "Balanced execution with full discovery cycle.",
          icon: "timeline",
          priceModifier: "none",
          tags: ["standard"],
        },
        {
          label: "Accelerated",
          value: "accelerated",
          description: "Faster delivery with prioritized milestones.",
          icon: "timeline",
          priceModifier: "medium",
          tags: ["fast"],
        },
        {
          label: "Urgent Launch",
          value: "urgent",
          description: "High-priority execution for immediate release.",
          badge: "Fastest",
          icon: "timeline",
          priceModifier: "high",
          tags: ["urgent"],
        },
      ],
    },
    {
      id: "roles",
      title: "Which capabilities are important for version one?",
      helper: "Prioritizing core features now can shorten delivery and control cost.",
      explanation: "Each added module expands scope and integration complexity. Selecting only core capabilities helps keep first-release delivery focused and predictable.",
      questionLabel: "Choose add-ons",
      type: "multi",
      pricingImpactKey: "addons",
      options: [
        {
          label: "Design System",
          value: "designSystem",
          description: "Reusable UI foundation for scale.",
          icon: "addon",
          priceModifier: "medium",
          tags: ["design"],
        },
        {
          label: "Analytics Stack",
          value: "analytics",
          description: "Event tracking and conversion visibility.",
          icon: "addon",
          priceModifier: "medium",
          tags: ["analytics", "data"],
        },
        {
          label: "Third-Party Integrations",
          value: "integrations",
          description: "CRM, payments, and workflow automation.",
          icon: "addon",
          priceModifier: "high",
          tags: ["integration", "backend"],
        },
        {
          label: "AI chatbot or AI features",
          value: "ai_features",
          description: "Assistant workflows, smart automation, or AI-enabled user flows.",
          icon: "addon",
          priceModifier: "high",
          tags: ["ai", "advanced"],
        },
      ],
    },
    {
      id: "budget",
      title: "Unlock final estimate",
      helper: "Share your details to receive a tailored follow-up scope.",
      explanation: "We use your contact details to send a structured estimate summary and recommended build path based on your selected priorities.",
      questionLabel: "Share your contact details",
      type: "input",
      pricingImpactKey: "lead",
      options: [],
    },
  ],
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
    { value: "ai_features", label: "AI chatbot or AI features", description: "Assistant workflows or AI-enabled product flows.", badge: "AI", price: 4800 },
    { value: "seo", label: "SEO + Content Setup", description: "Technical optimization and launch content structure.", badge: "", price: 1500 },
  ],
  ui: {
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
    steps: incoming.steps && incoming.steps.length ? incoming.steps : defaultCalculatorConfig.steps,
    projectOptions: mergeArray<ProjectOptionConfig>(defaultCalculatorConfig.projectOptions, incoming.projectOptions),
    complexityOptions: mergeArray<ComplexityOptionConfig>(defaultCalculatorConfig.complexityOptions, incoming.complexityOptions),
    timelineOptions: mergeArray<TimelineOptionConfig>(defaultCalculatorConfig.timelineOptions, incoming.timelineOptions),
    addonOptions: mergeArray<AddonOptionConfig>(defaultCalculatorConfig.addonOptions, incoming.addonOptions),
    ui: { ...defaultCalculatorConfig.ui, ...(incoming.ui ?? {}) },
  }
}

export const applyPricingRulesToConfig = (
  baseConfig: CalculatorPricingConfig,
  pricingRules: PricingRules | unknown,
): CalculatorPricingConfig => {
  const pr = normalizePricingRules(pricingRules)
  const projectPriceMap = pr.basePrices
  const featureWeight = pr.featureMultipliers
  const timeline = pr.timelineAdjustment

  const getFeatureMultiplierForOption = (option: AddonOptionConfig): number => {
    const tags = option.value.toLowerCase()
    if (tags.includes("ai")) {
      return featureWeight.high * (pr.multipliers.aiFeatures ?? 1)
    }
    if (tags.includes("integration") || tags.includes("role")) {
      return featureWeight.medium * (pr.multipliers.userRoles ?? 1)
    }
    return featureWeight.low
  }

  return {
    ...baseConfig,
    projectOptions: baseConfig.projectOptions.map((option) => ({
      ...option,
      price: projectPriceMap[option.value as keyof PricingRules["basePrices"]] ?? option.price,
    })),
    complexityOptions: baseConfig.complexityOptions.map((option) => ({
      ...option,
      multiplier:
        option.value === "basic"
          ? 1
          : option.value === "advanced"
            ? pr.multipliers.backendComplexity ?? option.multiplier
            : Math.max(1, (pr.multipliers.backendComplexity ?? 1) + 0.35),
    })),
    timelineOptions: baseConfig.timelineOptions.map((option) => {
      if (option.value === "accelerated") {
        return { ...option, multiplier: 1 + (timeline.fast ?? 0) / 100 }
      }
      if (option.value === "urgent") {
        return { ...option, multiplier: 1 + (timeline.urgent ?? 0) / 100 }
      }
      return option
    }),
    addonOptions: baseConfig.addonOptions.map((option) => ({
      ...option,
      price: Math.round(option.price * getFeatureMultiplierForOption(option)),
    })),
  }
}

export const applyRemoteCalculatorConfig = (
  baseConfig: CalculatorPricingConfig,
  remoteConfig: RemoteCalculatorConfig,
): CalculatorPricingConfig => {
  const fallbackStepMap = new Map(baseConfig.steps.map((step) => [step.id, step]))
  const pricingImpactMap: Record<string, string> = {
    platform: "projectType",
    features: "complexity",
    timeline: "timeline",
    roles: "addons",
    budget: "lead",
  }

  const normalizedSteps = (remoteConfig.steps ?? [])
    .filter((step) => Boolean(step?.id))
    .map((step) => {
      const fallback = fallbackStepMap.get(step.id)
      const type: "single" | "multi" | "input" =
        step.type === "multi" || step.type === "input" ? step.type : "single"
      const options = Array.isArray(step.options)
        ? step.options
            .filter((option) => option?.value && option?.label)
            .map((option) => ({
              label: option.label,
              value: option.value,
              description: "",
              priceModifier: option.priceModifier ?? "none",
              tags: Array.isArray(option.tags) ? option.tags : [],
            }))
        : fallback?.options ?? []

      return {
        id: step.id,
        title: step.title?.trim() || fallback?.title || "New step",
        helper: step.helper?.trim() || fallback?.helper || "",
        explanation: step.explanation?.trim() || fallback?.explanation || "",
        questionLabel: fallback?.questionLabel || "Select an option",
        type: fallback?.type === "input" ? "input" : type,
        pricingImpactKey: fallback?.pricingImpactKey || pricingImpactMap[step.id] || "addons",
        options,
      }
    })

  const steps = normalizedSteps.length ? normalizedSteps : baseConfig.steps

  if (!steps.length) {
    return baseConfig
  }

  return { ...baseConfig, steps }
}
