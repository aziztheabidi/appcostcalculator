import type { RuntimeConfigResult } from "../config/runtime"
import type { CalculatorFormState } from "../types/calculator"

export interface SubmitLeadInput {
  formState: CalculatorFormState
  estimateMin: number
  estimateMax: number
}

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

const resolveEndpoint = (restUrl: string): string => `${restUrl.replace(/\/?$/, "/")}lead`

const getFriendlyError = (status: number): string => {
  if (status === 403) return "Your session expired. Please refresh and try again."
  if (status === 422) return "Please complete the required fields and try again."
  if (status >= 500) return "Server is busy right now. Please retry in a moment."
  return "We could not submit your request. Please try again."
}

export const submitCalculatorLead = async (
  runtime: RuntimeConfigResult,
  input: SubmitLeadInput,
): Promise<{ ok: true }> => {
  const { formState, estimateMin, estimateMax } = input
  const payload = {
    name: formState.lead.fullName,
    email: formState.lead.email,
    phone: formState.lead.phone,
    project_type: formState.projectType,
    estimate_min: estimateMin,
    estimate_max: estimateMax,
    timeline: formState.timeline,
    complexity: formState.complexity,
    answers: formState,
  }

  if (runtime.mode === "mock") {
    console.warn("PixactCalculator config missing. Using simulated success mode.")
    await sleep(700)
    return { ok: true }
  }

  const response = await fetch(resolveEndpoint(runtime.config.restUrl), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": runtime.config.nonce,
    },
    body: JSON.stringify(payload),
  })

  if (!response.ok) {
    throw new Error(getFriendlyError(response.status))
  }

  return { ok: true }
}
