import { useMemo, useState } from "react"
import type {
  AddonId,
  CalculatorFormState,
  CalculatorPricingConfig,
  Complexity,
  LeadFormData,
  ProjectType,
  Timeline,
} from "../types/calculator"
import { calculateEstimate } from "../lib/pricingEngine"

const defaultLead: LeadFormData = {
  fullName: "",
  email: "",
  phone: "",
  company: "",
  message: "",
  honeypot: "",
}

const initialState: CalculatorFormState = {
  projectType: null,
  complexity: null,
  timeline: null,
  addons: [],
  lead: defaultLead,
}

export const useCalculator = (pricingConfig: CalculatorPricingConfig) => {
  const [formState, setFormState] = useState<CalculatorFormState>(initialState)

  const estimate = useMemo(() => calculateEstimate(formState, pricingConfig), [formState, pricingConfig])

  const setProjectType = (projectType: ProjectType) => {
    setFormState((prev) => ({ ...prev, projectType }))
  }

  const setComplexity = (complexity: Complexity) => {
    setFormState((prev) => ({ ...prev, complexity }))
  }

  const setTimeline = (timeline: Timeline) => {
    setFormState((prev) => ({ ...prev, timeline }))
  }

  const toggleAddon = (addon: AddonId) => {
    setFormState((prev) => ({
      ...prev,
      addons: prev.addons.includes(addon)
        ? prev.addons.filter((item) => item !== addon)
        : [...prev.addons, addon],
    }))
  }

  const updateLead = (patch: Partial<LeadFormData>) => {
    setFormState((prev) => ({
      ...prev,
      lead: { ...prev.lead, ...patch },
    }))
  }

  return {
    formState,
    estimate,
    setProjectType,
    setComplexity,
    setTimeline,
    toggleAddon,
    updateLead,
  }
}
