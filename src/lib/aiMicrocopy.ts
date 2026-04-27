import type { CalculatorFormState } from "../types/calculator"

export interface AiMicrocopy {
  helper_text: string
  explanation: string
  recommendation: string
}

const memoryCache = new Map<string, AiMicrocopy | null>()
const inFlight = new Map<string, Promise<AiMicrocopy | null>>()
const lastRequestedAt = new Map<string, number>()
const RATE_LIMIT_MS = 1200

const buildEndpoint = (): string => {
  if (typeof window === "undefined" || !window.PixactCalculator?.restUrl) {
    return "/wp-json/pixact/v1/microcopy"
  }
  return `${window.PixactCalculator.restUrl.replace(/\/?$/, "/")}microcopy`
}

const getNonce = (): string => {
  if (typeof window === "undefined") return ""
  return window.PixactCalculator?.nonce ?? ""
}

const cacheKey = (step: string, answers: unknown, calculatorType: string): string =>
  `${step}:${calculatorType}:${JSON.stringify(answers)}`

export const fetchMicrocopy = async (
  step: string,
  answers: Partial<CalculatorFormState>,
  calculatorType: string,
): Promise<AiMicrocopy | null> => {
  if (typeof window !== "undefined" && window.PixactCalculator?.aiEnabled === false) {
    return null
  }

  const key = cacheKey(step, answers, calculatorType)
  if (memoryCache.has(key)) {
    return memoryCache.get(key) ?? null
  }
  if (inFlight.has(key)) {
    return inFlight.get(key) ?? null
  }
  const now = Date.now()
  const lastAt = lastRequestedAt.get(key) ?? 0
  if (now - lastAt < RATE_LIMIT_MS) {
    return null
  }
  lastRequestedAt.set(key, now)

  const endpoint = buildEndpoint()
  const nonce = getNonce()

  const requestPromise = (async () => {
    try {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(nonce ? { "X-WP-Nonce": nonce } : {}),
      },
      body: JSON.stringify({
        step,
        answers,
        calculator_type: calculatorType,
      }),
    })

    if (!response.ok) {
      memoryCache.set(key, null)
      return null
    }

    const data = (await response.json()) as Partial<AiMicrocopy>
    if (!data.helper_text || !data.explanation || !data.recommendation) {
      memoryCache.set(key, null)
      return null
    }

    const normalized: AiMicrocopy = {
      helper_text: data.helper_text,
      explanation: data.explanation,
      recommendation: data.recommendation,
    }
    memoryCache.set(key, normalized)
    return normalized
    } catch {
      memoryCache.set(key, null)
      return null
    } finally {
      inFlight.delete(key)
    }
  })()

  inFlight.set(key, requestPromise)
  return requestPromise
}
