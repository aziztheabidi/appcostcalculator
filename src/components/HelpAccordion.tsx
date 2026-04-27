import { useId, useState } from "react"

interface HelpAccordionProps {
  text: string
}

export const HelpAccordion = ({ text }: HelpAccordionProps) => {
  const [open, setOpen] = useState(false)
  const panelId = useId()

  return (
    <div className="rounded-xl">
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className="inline-flex items-center gap-1 text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 transition hover:text-red-700 focus-visible:ring-2 focus-visible:ring-red-200 focus-visible:outline-none"
        aria-expanded={open}
        aria-controls={panelId}
      >
        <span>What does this mean?</span>
        <span className="text-slate-400 no-underline">{open ? "▲" : "▼"}</span>
      </button>
      <div
        id={panelId}
        className={`grid transition-all duration-300 ease-out ${open ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"}`}
      >
        <div className="overflow-hidden">
          <p className="mt-2 rounded-xl border border-slate-200 bg-white/75 p-3 text-sm leading-relaxed text-slate-600">
            {text}
          </p>
        </div>
      </div>
    </div>
  )
}
