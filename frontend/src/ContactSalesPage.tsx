import { useState } from "react"

import "./plans.css"

import type { SalesInquiry } from "./core/types"

const COMPANY_SIZES = [
  "1-10",

  "11-50",

  "51-200",

  "201-1,000",

  "1,001-5,000",

  "5,000+",
]

const INTERESTS = [
  "Garad Chat and AI agents",

  "Image, video and audio tools",

  "Social media tools",

  "Custom models and API access",

  "Team workspace and billing",

  "Something else",
]

const FEATURES: Array<[string, React.ReactNode]> = [
  [
    "Your data is never used for training",
    <>
      <path d="M12 3l7 3v5c0 4.4-2.9 8.3-7 10-4.1-1.7-7-5.6-7-10V6l7-3z" />
      <circle cx="12" cy="10" r="2.2" />
      <path d="M8.2 16.2c.9-1.6 2.2-2.4 3.8-2.4s2.9.8 3.8 2.4" />
    </>,
  ],
  [
    "Priority access to the best models for work",
    <path d="M12 12c-1.6-2.4-3-4-4.8-4a4 4 0 100 8c1.8 0 3.2-1.6 4.8-4zm0 0c1.6 2.4 3 4 4.8 4a4 4 0 100-8c-1.8 0-3.2 1.6-4.8 4z" />,
  ],
  [
    "Easy member, role and billing management",
    <>
      <circle cx="9" cy="8" r="3" />
      <path d="M3.5 19c.4-3.2 2.6-5 5.5-5s5.1 1.8 5.5 5" />
      <circle cx="17" cy="9" r="2.3" />
      <path d="M16 14.2c2.6-.2 4.2 1.4 4.6 4.2" />
    </>,
  ],
  [
    "Custom limits, models and local payment options",
    <>
      <circle cx="6" cy="6" r="2.2" />
      <circle cx="18" cy="12" r="2.2" />
      <circle cx="6" cy="18" r="2.2" />
      <path d="M8 6.8l8 4M8 17.2l8-4" />
    </>,
  ],
]

const TRUSTED = [
  "Horn Telecom",
  "Hodan Group",
  "Salaam Bank",
  "Nomad Labs",
  "Barwaaqo",
  "Sahal Logistics",
  "Waaberi Media",
  "Dalmar Foods",
]

const EMPTY: SalesInquiry = {
  companySize: "",

  companyName: "",

  firstName: "",

  lastName: "",

  email: "",

  phone: "",

  interest: "",

  message: "",
}

export default function ContactSalesPage({
  onBack,

  onSubmit,
}: {
  onBack: () => void

  onSubmit: (input: SalesInquiry) => Promise<unknown>
}) {
  const [form, setForm] = useState<SalesInquiry>(EMPTY)

  const [busy, setBusy] = useState(false)

  const [error, setError] = useState("")

  const [sent, setSent] = useState(false)

  const set =
    (key: keyof SalesInquiry) =>
    (
      event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>,
    ) =>
      setForm((current) => ({ ...current, [key]: event.target.value }))

  const submit = (event: React.FormEvent) => {
    event.preventDefault()

    if (busy) return

    setBusy(true)

    setError("")

    onSubmit(form)

      .then(() => setSent(true))

      .catch(() =>
        setError(
          "We could not send your request. Please check the details and try again, or email info@garad.ai.",
        ),
      )

      .finally(() => setBusy(false))
  }

  return (
    <div className="plans-page">
      <header className="plans-topbar">
        <button type="button" className="plans-back" onClick={onBack}>
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.2"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="M15 5l-7 7 7 7" />
          </svg>
          Back to plans
        </button>
      </header>

      <main className="cs-main">
        <section className="cs-intro">
          <h1>Contact our sales team</h1>
          <p>
            Tell us about your organization and we will put together an
            Enterprise plan for you.
          </p>
          <h4>Features</h4>
          <ul>
            {FEATURES.map(([feature, icon]) => (
              <li key={feature}>
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  {icon}
                </svg>
                {feature}
              </li>
            ))}
          </ul>
          <h4>Trusted by thousands of teams like</h4>
          <div className="cs-logos" aria-hidden="true">
            <div className="cs-logos-track">
              {[...TRUSTED, ...TRUSTED].map((name, index) => (
                <span key={`${name}-${index}`}>{name}</span>
              ))}
            </div>
          </div>
          <p className="cs-note">
            Prefer email? Write to{" "}
            <a href="mailto:info@garad.ai">info@garad.ai</a>
          </p>
        </section>

        <section className="cs-card">
          {sent ? (
            <div className="cs-done">
              <span>
                <svg
                  width="26"
                  height="26"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.4"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M5 12.5l4.5 4.5L19 7.5" />
                </svg>
              </span>
              <h2>Thank you!</h2>
              <p>
                Your request was sent. Our sales team will contact you at{" "}
                <b>{form.email}</b> soon.
              </p>
              <button type="button" className="plans-cta" onClick={onBack}>
                Back to plans
              </button>
            </div>
          ) : (
            <form onSubmit={submit}>
              <div className="cs-grid">
                <label>
                  Company size *
                  <select
                    required
                    value={form.companySize}
                    onChange={set("companySize")}
                  >
                    <option value="" disabled>
                      Please select
                    </option>
                    {COMPANY_SIZES.map((size) => (
                      <option key={size}>{size}</option>
                    ))}
                  </select>
                </label>
                <label>
                  Company name *
                  <input
                    required
                    maxLength={160}
                    value={form.companyName}
                    onChange={set("companyName")}
                  />
                </label>
                <label>
                  First name *
                  <input
                    required
                    maxLength={100}
                    autoComplete="given-name"
                    value={form.firstName}
                    onChange={set("firstName")}
                  />
                </label>
                <label>
                  Last name *
                  <input
                    required
                    maxLength={100}
                    autoComplete="family-name"
                    value={form.lastName}
                    onChange={set("lastName")}
                  />
                </label>
                <label>
                  Work email *
                  <input
                    required
                    type="email"
                    autoComplete="email"
                    value={form.email}
                    onChange={set("email")}
                  />
                </label>
                <label>
                  Phone number *
                  <input
                    required
                    type="tel"
                    minLength={5}
                    autoComplete="tel"
                    value={form.phone}
                    onChange={set("phone")}
                  />
                </label>
              </div>

              <label>
                Which of our products or services are you interested in? *
                <select
                  required
                  value={form.interest}
                  onChange={set("interest")}
                >
                  <option value="" disabled>
                    Select one from the options below
                  </option>
                  {INTERESTS.map((item) => (
                    <option key={item}>{item}</option>
                  ))}
                </select>
              </label>

              <label>
                Can you share more about your business needs and challenges?
                <textarea
                  rows={6}
                  maxLength={3000}
                  value={form.message}
                  onChange={set("message")}
                />
              </label>

              {error && <p className="cs-error">{error}</p>}

              <button type="submit" className="plans-cta" disabled={busy}>
                {busy ? "Sending..." : "Submit"}
              </button>
            </form>
          )}
          {!sent && (
            <p className="cs-help">
              For other inquiries, visit our{" "}
              <a href="https://help.garad.ai" target="_blank" rel="noreferrer">
                help center
              </a>
              .
            </p>
          )}
        </section>
      </main>
    </div>
  )
}
