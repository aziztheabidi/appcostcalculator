import type { CalculatorPricingConfig } from "./calculator"

export interface WordPressCalculatorConfig {
  restUrl: string
  nonce: string
  siteUrl: string
  calculatorConfig: CalculatorPricingConfig
  aiEnabled?: boolean
}

declare global {
  interface Window {
    PixactCalculator?: Partial<WordPressCalculatorConfig>
  }
}

export {}
