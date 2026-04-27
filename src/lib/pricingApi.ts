import { normalizePricingRules } from "../config/calculatorConfig"
import type { RuntimeConfigResult } from "../config/runtime"
import type { PricingRules } from "../types/calculator"

const endpoint = (restUrl: string): string => `${restUrl.replace(/\/?$/, "/")}pricing`

export const fetchPricingRules = async (
  runtime: RuntimeConfigResult,
): Promise<PricingRules | null> => {
  if (runtime.mode !== "wordpress") {
    return null
  }

  try {
    const response = await fetch(endpoint(runtime.config.restUrl), {
      method: "GET",
      headers: {
        "X-WP-Nonce": runtime.config.nonce,
      },
    })
    if (!response.ok) {
      return null
    }
    const data: unknown = await response.json()
    return normalizePricingRules(data)
  } catch {
    return null
  }
}
