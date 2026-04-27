import type { OptionItem } from "../types/calculator"

interface OptionGridProps<TValue extends string> {
  options: OptionItem<TValue>[]
  selectedValues: TValue[]
  onToggle: (value: TValue) => void
  multi?: boolean
}

export const OptionGrid = <TValue extends string>({
  options,
  selectedValues,
  onToggle,
  multi = false,
}: OptionGridProps<TValue>) => {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {options.map((option) => {
        const selected = selectedValues.includes(option.value)
        return (
          <button
            key={option.value}
            type="button"
            onClick={() => onToggle(option.value)}
            className={`rounded-2xl border p-4 text-left transition-all duration-200 focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:outline-none ${
              selected
                ? "border-indigo-500 bg-indigo-50 shadow-md shadow-indigo-100"
                : "border-slate-200 bg-white hover:border-indigo-300 hover:shadow-sm"
            }`}
            aria-pressed={selected}
          >
            <div className="flex items-center justify-between">
              <p className="font-semibold text-slate-900">{option.label}</p>
              {option.badge ? (
                <span className="rounded-full bg-slate-900 px-2 py-1 text-xs font-medium text-white">
                  {option.badge}
                </span>
              ) : null}
            </div>
            <p className="mt-2 text-sm text-slate-600">{option.description}</p>
            {!multi && selected ? (
              <p className="mt-3 text-xs font-semibold text-indigo-700">Selected</p>
            ) : null}
          </button>
        )
      })}
    </div>
  )
}
