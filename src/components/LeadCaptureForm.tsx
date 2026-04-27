import type { LeadFormData } from "../types/calculator"

interface LeadCaptureFormProps {
  lead: LeadFormData
  onChange: (value: Partial<LeadFormData>) => void
  emailError?: string
  phoneError?: string
}

const inputClass =
  "w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-100"

export const LeadCaptureForm = ({ lead, onChange, emailError, phoneError }: LeadCaptureFormProps) => {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <label htmlFor="pixact-fullName" className="block text-sm text-slate-700">
        Full name
        <input
          id="pixact-fullName"
          className={inputClass}
          value={lead.fullName}
          onChange={(event) => onChange({ fullName: event.target.value })}
          autoComplete="name"
          required
        />
      </label>
      <label htmlFor="pixact-email" className="block text-sm text-slate-700">
        Work email
        <input
          id="pixact-email"
          type="email"
          className={`${inputClass} ${emailError ? "border-red-400 focus:border-red-500 focus:ring-red-100" : ""}`}
          value={lead.email}
          onChange={(event) => onChange({ email: event.target.value })}
          autoComplete="email"
          aria-invalid={Boolean(emailError)}
          aria-describedby={emailError ? "pixact-email-error" : undefined}
          required
        />
        {emailError ? (
          <p id="pixact-email-error" className="mt-1 text-xs font-medium text-red-600">{emailError}</p>
        ) : null}
      </label>
      <label htmlFor="pixact-phone" className="block text-sm text-slate-700 sm:col-span-2">
        Phone
        <input
          id="pixact-phone"
          className={`${inputClass} ${phoneError ? "border-red-400 focus:border-red-500 focus:ring-red-100" : ""}`}
          value={lead.phone}
          onChange={(event) => onChange({ phone: event.target.value })}
          placeholder="+1 555 123 4567"
          autoComplete="tel"
          aria-invalid={Boolean(phoneError)}
          aria-describedby={phoneError ? "pixact-phone-error" : undefined}
        />
        {phoneError ? (
          <p id="pixact-phone-error" className="mt-1 text-xs font-medium text-red-600">{phoneError}</p>
        ) : null}
      </label>
      <label htmlFor="pixact-company" className="block text-sm text-slate-700 sm:col-span-2">
        Company
        <input
          id="pixact-company"
          className={inputClass}
          value={lead.company}
          onChange={(event) => onChange({ company: event.target.value })}
          autoComplete="organization"
        />
      </label>
      <label htmlFor="pixact-message" className="block text-sm text-slate-700 sm:col-span-2">
        Project context
        <textarea
          id="pixact-message"
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
