interface HelpAccordionProps {
  text: string
}

export const HelpAccordion = ({ text }: HelpAccordionProps) => {
  return (
    <div className="group relative rounded-xl border border-slate-200 bg-slate-50/90 p-3">
      <div className="flex items-start gap-2">
        <span
          className="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600"
          aria-label="More info"
          title={text}
        >
          i
        </span>
        <p key={text} className="pixact-fade-in break-words text-xs leading-relaxed text-slate-500">
          {text}
        </p>
      </div>
      <div className="pointer-events-none absolute top-full left-0 z-10 mt-2 hidden w-72 rounded-lg border border-slate-200 bg-white p-2 text-xs leading-relaxed text-slate-600 shadow-md group-hover:block">
        {text}
      </div>
    </div>
  )
}
