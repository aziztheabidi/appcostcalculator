import type { RuntimeConfigResult } from "../config/runtime"
import type { RemoteCalculatorConfig } from "../types/calculator"

const endpoint = (restUrl: string): string => `${restUrl.replace(/\/?$/, "/")}config`

export const fetchCalculatorConfig = async (
  runtime: RuntimeConfigResult,
): Promise<RemoteCalculatorConfig | null> => {
  if (runtime.mode !== "wordpress") return null

  try {
    const response = await fetch(endpoint(runtime.config.restUrl), {
      method: "GET",
      headers: {
        "X-WP-Nonce": runtime.config.nonce,
      },
    })
    if (!response.ok) return null
    return (await response.json()) as RemoteCalculatorConfig
  } catch {
    return null
  }
}
