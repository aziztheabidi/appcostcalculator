import { defaultCalculatorConfig, mergeCalculatorConfig } from "./calculatorConfig"
import type { WordPressCalculatorConfig } from "../types/wordpress"

const fallbackConfig: WordPressCalculatorConfig = {
  restUrl: "/wp-json",
  nonce: "mock-nonce",
  siteUrl: "http://localhost",
  calculatorConfig: defaultCalculatorConfig,
}

export interface RuntimeConfigResult {
  mode: "wordpress" | "mock"
  config: WordPressCalculatorConfig
}

const isValidConfig = (
  candidate?: Partial<WordPressCalculatorConfig>,
): candidate is WordPressCalculatorConfig => {
  return Boolean(candidate?.restUrl && candidate?.nonce && candidate?.siteUrl)
}

export const loadRuntimeConfig = (): RuntimeConfigResult => {
  if (typeof window !== "undefined" && isValidConfig(window.PixactCalculator)) {
    return {
      mode: "wordpress",
      config: {
        ...window.PixactCalculator,
        calculatorConfig: mergeCalculatorConfig(window.PixactCalculator.calculatorConfig),
      },
    }
  }

  return {
    mode: "mock",
    config: fallbackConfig,
  }
}
