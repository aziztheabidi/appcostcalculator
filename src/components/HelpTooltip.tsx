import { useState } from "react"

interface HelpTooltipProps {
  text: string
}

export const HelpTooltip = ({ text }: HelpTooltipProps) => {
  const [open, setOpen] = useState(false)

  return (
    <div className="rounded-2xl border border-slate-200 bg-white/70 p-3">
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className="flex w-full items-center justify-between text-left text-sm font-semibold text-slate-700 transition hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-red-200 focus-visible:outline-none"
        aria-expanded={open}
      >
        <span>What does this mean?</span>
        <span className="text-slate-400">{open ? "−" : "+"}</span>
      </button>
      {open ? <p className="mt-2 text-sm leading-relaxed text-slate-600">{text}</p> : null}
    </div>
  )
}
