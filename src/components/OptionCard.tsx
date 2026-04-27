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
      className={`relative w-full min-w-0 overflow-hidden rounded-xl border bg-white p-4 text-left shadow-sm transition-all duration-200 focus-visible:ring-2 focus-visible:ring-red-200 focus-visible:outline-none md:p-5 ${
        selected
          ? "border-red-500 bg-red-50/50 shadow-md shadow-red-100/40"
          : "border-gray-200 hover:border-gray-300 hover:shadow-md"
      }`}
    >
      {selected ? (
        <span
          className="absolute top-3 right-3 inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-[11px] font-bold text-white"
          aria-hidden="true"
        >
          ✓
        </span>
      ) : null}
      <div className="flex min-w-0 items-start gap-3 pr-8">
        <div className="flex min-w-0 items-start gap-3">
          {icon ? (
            <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              {icon}
            </span>
          ) : null}
          <div className="min-w-0">
            <p className="truncate text-base font-semibold text-gray-900">{label}</p>
            <p className="mt-1 overflow-hidden break-words text-sm leading-relaxed text-gray-500">{description}</p>
          </div>
        </div>
      </div>
      {badge ? (
        <span className="mt-3 inline-block max-w-full truncate rounded-full bg-gray-900 px-2 py-1 text-[10px] font-semibold tracking-wide text-white uppercase">
          {badge}
        </span>
      ) : null}
    </button>
  )
}
