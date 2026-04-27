interface ExplanationBoxProps {
  text: string
}

export const ExplanationBox = ({ text }: ExplanationBoxProps) => {
  return (
    <aside className="rounded-2xl border border-indigo-100 bg-indigo-50/80 p-4 text-left shadow-sm">
      <p className="text-xs font-semibold tracking-wide text-indigo-700 uppercase">
        What does this mean?
      </p>
      <p className="mt-2 text-sm leading-relaxed text-slate-700">{text}</p>
    </aside>
  )
}
