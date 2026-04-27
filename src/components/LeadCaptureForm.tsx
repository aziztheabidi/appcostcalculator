import type { LeadFormData } from "../types/calculator"

interface LeadCaptureFormProps {
  lead: LeadFormData
  onChange: (value: Partial<LeadFormData>) => void
}

const inputClass =
  "w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"

export const LeadCaptureForm = ({ lead, onChange }: LeadCaptureFormProps) => {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <label className="block text-sm text-slate-700">
        Full name
        <input
          className={inputClass}
          value={lead.fullName}
          onChange={(event) => onChange({ fullName: event.target.value })}
          required
        />
      </label>
      <label className="block text-sm text-slate-700">
        Work email
        <input
          type="email"
          className={inputClass}
          value={lead.email}
          onChange={(event) => onChange({ email: event.target.value })}
          required
        />
      </label>
      <label className="block text-sm text-slate-700 sm:col-span-2">
        Phone
        <input
          className={inputClass}
          value={lead.phone}
          onChange={(event) => onChange({ phone: event.target.value })}
          placeholder="+1 555 123 4567"
        />
      </label>
      <label className="block text-sm text-slate-700 sm:col-span-2">
        Company
        <input
          className={inputClass}
          value={lead.company}
          onChange={(event) => onChange({ company: event.target.value })}
        />
      </label>
      <label className="block text-sm text-slate-700 sm:col-span-2">
        Project context
        <textarea
          className={inputClass}
          rows={4}
          value={lead.message}
          onChange={(event) => onChange({ message: event.target.value })}
          placeholder="Goals, launch date, integrations, or anything else."
        />
      </label>
      <label className="hidden">
        Leave this empty
        <input
          tabIndex={-1}
          autoComplete="off"
          value={lead.honeypot}
          onChange={(event) => onChange({ honeypot: event.target.value })}
        />
      </label>
    </div>
  )
}
