import type { PropsWithChildren, ReactNode } from "react"

interface StepLayoutProps extends PropsWithChildren {
  title: string
  subtitle: string
  footer: ReactNode
}

export const StepLayout = ({ title, subtitle, footer, children }: StepLayoutProps) => {
  return (
    <section className="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/40 sm:p-7">
      <header className="space-y-2">
        <h2 className="text-2xl font-semibold text-slate-900">{title}</h2>
        <p className="text-sm text-slate-600">{subtitle}</p>
      </header>
      {children}
      <footer>{footer}</footer>
    </section>
  )
}
