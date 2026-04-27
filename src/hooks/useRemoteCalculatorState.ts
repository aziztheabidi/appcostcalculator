import { useEffect, useState } from "react"
import { applyPricingRulesToConfig, applyRemoteCalculatorConfig } from "../config/calculatorConfig"
import { fetchCalculatorConfig } from "../lib/calculatorConfigApi"
import { fetchPricingRules } from "../lib/pricingApi"
import type { PricingRules, RemoteCalculatorConfig, CalculatorPricingConfig } from "../types/calculator"
import type { RuntimeConfigResult } from "../config/runtime"

interface RemoteCalculatorState {
  calculatorConfig: CalculatorPricingConfig
  remoteConfig: RemoteCalculatorConfig | null
  pricingRules: PricingRules | null
}

export const useRemoteCalculatorState = (runtime: RuntimeConfigResult): RemoteCalculatorState => {
  const [state, setState] = useState<RemoteCalculatorState>({
    calculatorConfig: runtime.config.calculatorConfig,
    remoteConfig: null,
    pricingRules: null,
  })

  useEffect(() => {
    let cancelled = false

    void (async () => {
      const [configResult, pricingResult] = await Promise.allSettled([
        fetchCalculatorConfig(runtime),
        fetchPricingRules(runtime),
      ])
      if (cancelled) return

      const remoteConfig = configResult.status === "fulfilled" ? configResult.value : null
      const pricingRules = pricingResult.status === "fulfilled" ? pricingResult.value : null

      let nextConfig = runtime.config.calculatorConfig
      if (remoteConfig) {
        nextConfig = applyRemoteCalculatorConfig(nextConfig, remoteConfig)
      }
      if (pricingRules) {
        nextConfig = applyPricingRulesToConfig(nextConfig, pricingRules)
      }

      setState({
        calculatorConfig: nextConfig,
        remoteConfig,
        pricingRules,
      })
    })()

    return () => {
      cancelled = true
    }
  }, [runtime])

  return state
}
