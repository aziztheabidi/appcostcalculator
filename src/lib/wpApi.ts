import type { CalculatorFormState, EstimateBreakdown } from "../types/calculator"
import type { RuntimeConfigResult } from "../config/runtime"

export interface LeadSubmissionPayload {
  answers: CalculatorFormState
  estimate: EstimateBreakdown
  estimateRange: string
  submittedAt: number
  startedAt: number
}

const mockSubmitDelayMs = 750

export const submitLead = async (
  runtime: RuntimeConfigResult,
  payload: LeadSubmissionPayload,
): Promise<{ ok: true }> => {
  if (runtime.mode === "mock") {
    await new Promise((resolve) => setTimeout(resolve, mockSubmitDelayMs))
    return { ok: true }
  }

  // Keep endpoint composable around the injected WP REST base URL.
  const endpoint = `${runtime.config.restUrl.replace(/\/$/, "")}/lead`
  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": runtime.config.nonce,
    },
    body: JSON.stringify(payload),
  })

  if (!response.ok) {
    throw new Error("Lead submission failed")
  }

  return { ok: true }
}
