interface OptionCardProps {
  label: string
  description: string
  badge?: string
  selected: boolean
  onClick: () => void
}

export const OptionCard = ({ label, description, badge, selected, onClick }: OptionCardProps) => {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      className={`rounded-2xl border p-4 text-left transition-all duration-200 focus-visible:ring-2 focus-visible:ring-red-200 focus-visible:outline-none ${
        selected
          ? "border-red-400 bg-red-50/80 shadow-lg shadow-red-100/50"
          : "border-slate-200 bg-white/80 hover:border-red-300 hover:bg-white hover:shadow-md"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <p className="text-base font-semibold text-slate-900">{label}</p>
        {badge ? (
          <span className="rounded-full bg-slate-900 px-2 py-1 text-[10px] font-semibold tracking-wide text-white uppercase">
            {badge}
          </span>
        ) : null}
      </div>
      <p className="mt-2 text-sm text-slate-600">{description}</p>
    </button>
  )
}
