import type { ReactNode } from "react"

interface OptionCardProps {
  label: string
  description: string
  icon?: ReactNode
  badge?: string
  selected: boolean
  onClick: () => void
}

export const OptionCard = ({ label, description, icon, badge, selected, onClick }: OptionCardProps) => {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      className={`w-full rounded-2xl border p-5 text-left transition-all duration-200 focus-visible:ring-2 focus-visible:ring-red-200 focus-visible:outline-none ${
        selected
          ? "border-red-500 bg-red-50/90 shadow-lg shadow-red-100/60"
          : "border-slate-200 bg-white/80 hover:border-red-300 hover:bg-white hover:shadow-md"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          {icon ? (
            <span className="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
              {icon}
            </span>
          ) : null}
          <div>
            <p className="text-base font-semibold text-slate-900">{label}</p>
            <p className="mt-2 text-sm leading-relaxed text-slate-600">{description}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {badge ? (
            <span className="rounded-full bg-slate-900 px-2 py-1 text-[10px] font-semibold tracking-wide text-white uppercase">
              {badge}
            </span>
          ) : null}
          {selected ? (
            <span
              className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white"
              aria-hidden="true"
            >
              ✓
            </span>
          ) : null}
        </div>
      </div>
    </button>
  )
}
