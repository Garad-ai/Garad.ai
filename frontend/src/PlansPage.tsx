import { useEffect, useMemo, useState } from "react"

import "./plans.css"

import type {
  CheckoutInput,
  CheckoutResult,
  PaymentMethod,
  Plan,
  PlanModelGroup,
} from "./core/types"

const MIN_SEATS = 2

const MAX_SEATS = 100

const fallbackMethods: PaymentMethod[] = [
  {
    id: "evc_plus",

    name: "EVC Plus",

    enabled: true,

    requiresPhone: true,

    description: "Pay from your Hormuud EVC Plus wallet",
  },
]

type PlansPageProps = {
  plans: Plan[]

  currentPlanId?: string

  authenticated: boolean

  onBack: () => void

  onLogin: () => void

  onSelectFree: (planId: string) => void

  onEnterprise: () => void

  getPaymentMethods: () => Promise<PaymentMethod[]>

  startCheckout: (input: CheckoutInput) => Promise<CheckoutResult>

  onPaid: () => void

  notify: (message: string) => void
}

function Check({ off = false }: { off?: boolean }) {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.4"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {off ? (
        <path d="M6 6l12 12M18 6L6 18" />
      ) : (
        <path d="M5 12.5l4.5 4.5L19 7" />
      )}
    </svg>
  )
}

const isTeamPlan = (plan: Plan) => Boolean(plan.team) || plan.id === "business"

import evcplus from "./assets/payments/evcplus.png"
import visa from "./assets/payments/visa.svg"
import visaDark from "./assets/payments/visa-dark.svg"
import mastercard from "./assets/payments/mastercard.svg"
import mastercardDark from "./assets/payments/mastercard-dark.svg"
import amex from "./assets/payments/americanexpress.svg"
import amexDark from "./assets/payments/americanexpress-dark.svg"
import unionpay from "./assets/payments/unionpay.svg"
import unionpayDark from "./assets/payments/unionpay-dark.svg"
import paypal from "./assets/payments/paypal.svg"
import paypalDark from "./assets/payments/paypal-dark.svg"
import applepay from "./assets/payments/applepay.svg"
import applepayDark from "./assets/payments/applepay-dark.svg"
import googlewallet from "./assets/payments/googlewallet.svg"
import googlewalletDark from "./assets/payments/googlewallet-dark.svg"

type Logo = { name: string; light: string; dark?: string }

const cardLogos: Logo[] = [
  { name: "Visa", light: visa, dark: visaDark },
  { name: "Mastercard", light: mastercard, dark: mastercardDark },
  { name: "American Express", light: amex, dark: amexDark },
  { name: "UnionPay", light: unionpay, dark: unionpayDark },
]

const localLogos: Logo[] = [{ name: "EVC Plus", light: evcplus }]

const walletLogos: Logo[] = [
  { name: "PayPal", light: paypal, dark: paypalDark },
  { name: "Apple Pay", light: applepay, dark: applepayDark },
  { name: "Google Pay", light: googlewallet, dark: googlewalletDark },
]

const allLogos = [...cardLogos, ...walletLogos, ...localLogos]

const methodLogo = (method: PaymentMethod): string | undefined => {
  const key = `${method.id} ${method.name}`.toLowerCase().replace(/[\s_-]/g, "")

  return allLogos.find((logo) =>
    key.includes(logo.name.toLowerCase().replace(/\s/g, "")),
  )?.light
}

const renderLogo = (logo: Logo) => (
  <span
    key={logo.name}
    className={`plans-logo ${logo.dark ? "" : "plain"}`}
    title={logo.name}
  >
    <img className="light" src={logo.light} alt={logo.name} />
    {logo.dark && (
      <img className="dark" src={logo.dark} alt="" aria-hidden="true" />
    )}
  </span>
)

function CostBadge({
  cost,
  credits,
  perMonth,
  category = "chat",
}: {
  cost: number
  credits?: number | null
  perMonth?: number | null
  category?: string
}) {
  const level = cost >= 10 ? "high" : cost >= 3 ? "mid" : "low"

  return (
    <em className={`plans-cost ${level}`}>
      {perMonth
        ? `~${perMonth.toLocaleString()}`
        : credits
          ? `${credits.toLocaleString()} tokens`
          : category !== "chat"
            ? "Model credits"
            : `${cost}×`}
    </em>
  )
}

const unitLabel = (key: string) =>
  key === "video" ? "videos" : key === "image" ? "images" : "uses"

const creditLabel = (
  model: {
    credits?: number | null
    unlimited?: boolean
    cost?: number
    perMonth?: number | null
  },
  key = "chat",
) =>
  model.unlimited
    ? "Unlimited"
    : model.perMonth
      ? `~${model.perMonth.toLocaleString()} ${unitLabel(key)}`
      : model.credits
        ? `${model.credits.toLocaleString()} tokens`
        : model.cost
          ? `${model.cost}×`
          : key === "chat"
            ? "Included"
            : "Model credits"

function ModelsExplorer({ groups }: { groups: PlanModelGroup[] }) {
  const [open, setOpen] = useState(false)

  const visible = groups.filter((group) => group.providers.length > 0)

  const [category, setCategory] = useState(visible[0]?.key ?? "chat")

  const active = visible.find((group) => group.key === category) ?? visible[0]

  const total = visible.reduce(
    (sum, group) =>
      sum +
      group.providers.reduce((count, item) => count + item.models.length, 0),
    0,
  )

  return (
    <section className="plans-explorer">
      <button
        type="button"
        className="plans-explorer-toggle"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <span>View all models · {total}</span>
        <span className={open ? "chev open" : "chev"}>⌄</span>
      </button>
      {open && active && (
        <>
          <div className="plans-explorer-tabs" role="tablist">
            {visible.map((group) => (
              <button
                key={group.key}
                type="button"
                role="tab"
                aria-selected={group.key === active.key}
                className={group.key === active.key ? "active" : ""}
                onClick={() => setCategory(group.key)}
              >
                {group.label}
              </button>
            ))}
          </div>
          {active.providers.map((provider) => (
            <details key={provider.name} className="plans-provider">
              <summary>
                <strong>{provider.name}</strong>
                <small>{provider.models.length}</small>
              </summary>
              <ul>
                {provider.models.map((model) => (
                  <li key={model.name}>
                    <span>{model.name}</span>
                    <em>{creditLabel(model, active.key)}</em>
                  </li>
                ))}
              </ul>
            </details>
          ))}
        </>
      )}
    </section>
  )
}

function ComparePlans({ plans }: { plans: Plan[] }) {
  const categories = useMemo(() => {
    const map = new Map<string, {
      label: string
      providers: Map<string, Map<string, Map<string, {
        cost?: number
        credits?: number | null
        perMonth?: number | null
      }>>>
    }>()

    plans.forEach((plan) => {
      const modelGroups = plan.modelGroups ?? []
      const hasChatModels = modelGroups.some(
        (group) =>
          group.key === "chat" &&
          group.providers.some((provider) => provider.models.length > 0),
      )

      modelGroups.forEach((group) => {
        if (group.providers.length === 0) return
        if (!map.has(group.key))
          map.set(group.key, { label: group.label, providers: new Map() })
        const category = map.get(group.key)!
        group.providers.forEach((provider) => {
          if (!category.providers.has(provider.name))
            category.providers.set(provider.name, new Map())
          const models = category.providers.get(provider.name)!
          provider.models.forEach((model) => {
            if (!models.has(model.name)) models.set(model.name, new Map())
            models.get(model.name)!.set(plan.id, {
              cost: model.cost,
              credits: model.credits,
              perMonth: model.perMonth,
            })
          })
        })
      })

      if (!hasChatModels) {
        const fallbackModels = plan.flagships ?? []

        if (fallbackModels.length > 0) {
          if (!map.has("chat")) {
            map.set("chat", { label: "Chat models", providers: new Map() })
          }
          const chat = map.get("chat")!
          fallbackModels.forEach((model) => {
            if (!chat.providers.has(model.provider)) {
              chat.providers.set(model.provider, new Map())
            }
            const models = chat.providers.get(model.provider)!
            if (!models.has(model.name)) models.set(model.name, new Map())
            models.get(model.name)!.set(plan.id, {
              cost: model.cost,
              credits: "credits" in model ? model.credits : null,
            })
          })
        }
      }
    })

    return Array.from(map.entries())
  }, [plans])

  const toolRows = useMemo(() => {
    const labels = new Map<string, string>()
    plans.forEach((plan) =>
      plan.tools?.forEach((tool) => labels.set(tool.key, tool.label)),
    )
    return Array.from(labels.entries())
  }, [plans])

  const agentRows = useMemo(
    () => Array.from(new Set(plans.flatMap((plan) => plan.agents ?? []))),
    [plans],
  )

  if (
    categories.length === 0 &&
    toolRows.length === 0 &&
    agentRows.length === 0
  )
    return null

  const cols = {
    gridTemplateColumns: `minmax(180px, 1.6fr) repeat(${plans.length}, minmax(90px, 1fr))`,
  }
  const dash = <span className="plans-compare-dash">—</span>

  return (
    <section className="plans-compare">
      <h2>Compare plans: models, AI toolkit and agents</h2>
      <div className="plans-compare-scroll">
        <div className="plans-compare-table">
          <div className="plans-compare-row head" style={cols}>
            <span />
            {plans.map((plan) => (
              <strong key={plan.id}>
                {plan.name}
                <small>
                  {plan.monthlyPrice > 0 ? `$${plan.monthlyPrice}` : "Free"}
                </small>
              </strong>
            ))}
          </div>

          {toolRows.length > 0 && (
            <>
              <div className="plans-compare-cat">AI toolkit</div>
              {toolRows.map(([key, label]) => (
                <div className="plans-compare-row" style={cols} key={key}>
                  <span>{label}</span>
                  {plans.map((plan) => {
                    const tool = plan.tools?.find((item) => item.key === key)
                    return (
                      <span key={plan.id} className="plans-compare-cell">
                        {tool?.included ? (
                          <>
                            <Check />
                            {tool.detail && <small>{tool.detail}</small>}
                          </>
                        ) : (
                          dash
                        )}
                      </span>
                    )
                  })}
                </div>
              ))}
            </>
          )}

          {agentRows.length > 0 && (
            <>
              <div className="plans-compare-cat">Agents</div>
              {agentRows.map((agent) => (
                <div className="plans-compare-row" style={cols} key={agent}>
                  <span>{agent}</span>
                  {plans.map((plan) => (
                    <span key={plan.id} className="plans-compare-cell">
                      {plan.agents?.includes(agent) ? <Check /> : dash}
                    </span>
                  ))}
                </div>
              ))}
            </>
          )}

          {categories.map(([key, category]) => (
            <div key={key}>
              <div className="plans-compare-cat">{category.label}</div>
              {Array.from(category.providers.entries()).map(
                ([provider, models]) => (
                  <div key={provider}>
                    <div className="plans-compare-provider">{provider}</div>
                    {Array.from(models.entries())
                      .sort(([aName, a], [bName, b]) => {
                        const cost = (m: typeof a) =>
                          Math.max(
                            ...Array.from(m.values()).map((v) => v.cost ?? 1),
                          )
                        const ver = (n: string) =>
                          parseFloat(n.match(/\d+(\.\d+)?/)?.[0] ?? "0")
                        return cost(b) - cost(a) || ver(bName) - ver(aName)
                      })
                      .map(([name, byPlan]) => (
                        <div
                          className="plans-compare-row"
                          style={cols}
                          key={name}
                        >
                          <span>{name}</span>
                          {plans.map((plan) => {
                            const entry = byPlan.get(plan.id)
                            return (
                              <span
                                key={plan.id}
                                className="plans-compare-cell"
                              >
                                {entry ? (
                                  <>
                                    <CostBadge
                                      cost={entry.cost ?? 1}
                                      credits={entry.credits}
                                      perMonth={entry.perMonth}
                                      category={key}
                                    />
                                    {entry.perMonth ? (
                                      <small>{unitLabel(key)}</small>
                                    ) : null}
                                  </>
                                ) : (
                                  dash
                                )}
                              </span>
                            )
                          })}
                        </div>
                      ))}
                  </div>
                ),
              )}
            </div>
          ))}
        </div>
      </div>
      <p className="plans-cost-hint">
        Chat models show a relative cost (1× lightest). Images, videos and audio
        show roughly how many you can generate per month with your tokens.
      </p>
    </section>
  )
}

export default function PlansPage({
  plans,

  currentPlanId,

  authenticated,

  onBack,

  onLogin,

  onSelectFree,

  onEnterprise,

  getPaymentMethods,

  startCheckout,

  onPaid,

  notify,
}: PlansPageProps) {
  const [audience, setAudience] = useState<"individual" | "teams">("individual")

  const [seats, setSeats] = useState(MIN_SEATS)

  const [checkoutPlan, setCheckoutPlan] = useState<Plan | null>(null)

  const [methods, setMethods] = useState<PaymentMethod[]>(fallbackMethods)

  const [methodId, setMethodId] = useState("evc_plus")

  const [phone, setPhone] = useState("")

  const [paying, setPaying] = useState(false)

  const [error, setError] = useState("")

  useEffect(() => {
    let cancelled = false

    getPaymentMethods()

      .then((remote) => {
        const enabled = (remote || []).filter((method) => method.enabled)

        if (cancelled || enabled.length === 0) return

        setMethods(enabled)

        setMethodId((current) =>
          enabled.some((method) => method.id === current)
            ? current
            : enabled[0].id,
        )
      })

      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [getPaymentMethods])

  const individualPlans = useMemo(
    () => plans.filter((plan) => !plan.contactSales && !isTeamPlan(plan)),

    [plans],
  )

  const teamPlans = useMemo(() => plans.filter(isTeamPlan), [plans])

  const enterprise = plans.find((plan) => plan.contactSales)

  const clampSeats = (value: number) =>
    Math.min(MAX_SEATS, Math.max(MIN_SEATS, Math.round(value) || MIN_SEATS))

  const seatsFor = (plan: Plan) => (isTeamPlan(plan) ? seats : 1)

  const openCheckout = (plan: Plan) => {
    if (!authenticated) {
      onLogin()

      return
    }

    if (plan.monthlyPrice === 0) {
      onSelectFree(plan.id)

      return
    }

    setError("")

    setCheckoutPlan(plan)
  }

  const selectedMethod = methods.find((method) => method.id === methodId)

  const pay = async () => {
    if (!checkoutPlan || !selectedMethod) return

    const digits = phone.replace(/[^\d]/g, "")

    if (selectedMethod.requiresPhone && digits.length < 9) {
      setError("Enter the EVC Plus number you will pay from.")

      return
    }

    setPaying(true)

    setError("")

    try {
      const result = await startCheckout({
        planId: checkoutPlan.id,

        seats: seatsFor(checkoutPlan),

        method: selectedMethod.id,

        phone: selectedMethod.requiresPhone ? digits : undefined,
      })

      if (result.status === "failed") {
        setError(result.message || "The payment could not be started.")
      } else if (result.status === "redirect" && result.redirectUrl) {
        window.location.href = result.redirectUrl
      } else {
        notify(
          result.status === "paid"
            ? `${checkoutPlan.name} plan activated.`
            : result.message ||
                "Confirm the payment on your phone. Your plan activates automatically.",
        )

        setCheckoutPlan(null)

        onPaid()
      }
    } catch (caught) {
      const status = (caught as { status?: number })?.status

      setError(
        status && caught instanceof Error && caught.message
          ? caught.message
          : "The payment service is not reachable. Try again shortly.",
      )
    } finally {
      setPaying(false)
    }
  }

  const renderCard = (plan: Plan) => {
    const active = plan.id === currentPlanId

    const team = isTeamPlan(plan)

    const total = plan.monthlyPrice * seatsFor(plan)

    return (
      <article
        key={plan.id}
        className={`plans-card ${plan.popular ? "popular" : ""} ${
          active ? "current" : ""
        }`}
      >
        <header>
          <h3>{plan.name}</h3>
          {active ? (
            <span className="plans-badge">Current</span>
          ) : (
            plan.popular && <span className="plans-badge">Most popular</span>
          )}
        </header>
        <p className="plans-desc">{plan.description}</p>
        <div className="plans-price">
          <strong>
            {plan.monthlyPrice === 0 ? "Free" : `$${plan.monthlyPrice}`}
          </strong>
          {plan.monthlyPrice > 0 && (
            <span>{team ? "/ user / month" : "/ month"}</span>
          )}
        </div>

        {plan.credits ? (
          <p className="plans-credits">
            <strong>{plan.credits.toLocaleString()}</strong> tokens / month for
            images &amp; videos
            {team ? " per user" : ""}
          </p>
        ) : (
          <p className="plans-credits">
            {plan.monthlyPrice === 0
              ? "Image generation uses model credits · no videos"
              : "\u00a0"}
          </p>
        )}

        {team && (
          <div className="plans-seats">
            <div className="plans-seats-head">
              <span>Team size</span>
              <div className="plans-stepper">
                <button
                  type="button"
                  aria-label="Fewer users"
                  onClick={() => setSeats((value) => clampSeats(value - 1))}
                >
                  −
                </button>
                <input
                  type="number"
                  min={MIN_SEATS}
                  max={MAX_SEATS}
                  value={seats}
                  onChange={(event) =>
                    setSeats(clampSeats(Number(event.target.value)))
                  }
                  aria-label="Number of users"
                />
                <button
                  type="button"
                  aria-label="More users"
                  onClick={() => setSeats((value) => clampSeats(value + 1))}
                >
                  +
                </button>
              </div>
            </div>
            <input
              className="plans-range"
              type="range"
              min={MIN_SEATS}
              max={MAX_SEATS}
              value={seats}
              onChange={(event) =>
                setSeats(clampSeats(Number(event.target.value)))
              }
              aria-label="Team size"
            />
            <div className="plans-seats-total">
              <span>
                {seats} users × ${plan.monthlyPrice}
              </span>
              <strong>${total.toLocaleString()} / month</strong>
            </div>
          </div>
        )}

        <button
          type="button"
          className={`plans-cta ${active ? "is-current" : ""}`}
          disabled={active}
          onClick={() => openCheckout(plan)}
        >
          {active
            ? "Current plan"
            : plan.monthlyPrice === 0
              ? `Switch to ${plan.name}`
              : team
                ? `Get ${plan.name} for ${seats} users`
                : `Get ${plan.name}`}
        </button>

        {plan.flagships && plan.flagships.length > 0 ? (
          <section className="plans-top">
            <h4>Top models</h4>
            {["OpenAI", "Anthropic"].map((provider) => {
              const rows = plan.flagships!.filter(
                (item) => item.provider === provider,
              )

              return rows.length > 0 ? (
                <div key={provider} className="plans-top-group">
                  <small>{provider}</small>
                  {rows.map((item) => (
                    <div key={item.name} className="plans-top-row">
                      <span>{item.name}</span>
                      <CostBadge cost={item.cost} credits={item.credits} />
                    </div>
                  ))}
                </div>
              ) : null
            })}
            <p className="plans-cost-hint">
              Tokens are used for images &amp; videos only. Chat models show a
              relative cost: 1× is lightest.
            </p>
          </section>
        ) : (
          plan.models &&
          plan.models.length > 0 && (
            <section className="plans-top">
              <h4>Top models</h4>
              <div className="plans-top-group">
                {plan.models.map((model) => (
                  <div key={model.name} className="plans-top-row">
                    <span>{model.name}</span>
                    <CostBadge cost={model.multiplier ?? 1} />
                  </div>
                ))}
              </div>
            </section>
          )
        )}
        {plan.modelGroups?.some((group) => group.providers.length > 0) && (
          <ModelsExplorer groups={plan.modelGroups} />
        )}
        {plan.tools && plan.tools.length > 0 && (
          <section>
            <h4>AI Toolkit</h4>
            <ul>
              {plan.tools.map((tool) => (
                <li key={tool.key} className={tool.included ? "" : "off"}>
                  <Check off={!tool.included} />
                  {tool.label}
                  {tool.included && tool.detail ? ` · ${tool.detail}` : ""}
                </li>
              ))}
            </ul>
          </section>
        )}
        {plan.agents && plan.agents.length > 0 && (
          <section>
            <h4>Agents</h4>
            <ul>
              {plan.agents.map((agent) => (
                <li key={agent}>
                  <Check />
                  {agent}
                </li>
              ))}
            </ul>
          </section>
        )}
        <section>
          <h4>Includes</h4>
          <ul>
            {plan.features.map((feature) => (
              <li key={feature}>
                <Check />
                {feature}
              </li>
            ))}
          </ul>
        </section>
      </article>
    )
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
          Back to Garad
        </button>
      </header>

      <main className="plans-main">
        <div className="plans-hero">
          <span>GARAD AI PRICING</span>
          <h1>Plans that grow with your work</h1>
          <p>
            Pick the models, AI tools and agents that fit you or your whole
            team. Upgrade or switch any time.
          </p>
          <div className="plans-toggle" role="tablist">
            <button
              type="button"
              role="tab"
              aria-selected={audience === "individual"}
              className={audience === "individual" ? "active" : ""}
              onClick={() => setAudience("individual")}
            >
              Individual
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={audience === "teams"}
              className={audience === "teams" ? "active" : ""}
              onClick={() => setAudience("teams")}
            >
              Teams
            </button>
          </div>
        </div>

        {audience === "individual" ? (
          <div className="plans-grid cols-3">
            {individualPlans.map(renderCard)}
          </div>
        ) : (
          <div className="plans-grid cols-2">
            {teamPlans.map(renderCard)}
            {enterprise && (
              <article className="plans-card enterprise">
                <header>
                  <h3>{enterprise.name}</h3>
                </header>
                <p className="plans-desc">
                  {enterprise.description}. More than {MAX_SEATS} users, custom
                  models and limits, set up with our team.
                </p>
                <div className="plans-price">
                  <strong>Custom</strong>
                </div>
                <button
                  type="button"
                  className="plans-cta"
                  onClick={onEnterprise}
                >
                  Contact sales
                </button>
                <section>
                  <h4>Includes</h4>
                  <ul>
                    {enterprise.features.map((feature) => (
                      <li key={feature}>
                        <Check />
                        {feature}
                      </li>
                    ))}
                  </ul>
                </section>
              </article>
            )}
          </div>
        )}

        <ComparePlans
          plans={audience === "individual" ? individualPlans : teamPlans}
        />

        <section className="plans-secure">
          <h3>
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <rect x="4" y="10" width="16" height="10" rx="2" />
              <path d="M8 10V7a4 4 0 018 0v3" />
            </svg>
            Pay safe &amp; secure
          </h3>
          <div className="plans-secure-row">
            <div className="plans-secure-group">
              <span>Cards</span>
              <div>{cardLogos.map(renderLogo)}</div>
            </div>
            <i className="plans-secure-sep" aria-hidden="true" />
            <div className="plans-secure-group">
              <span>International</span>
              <div>{walletLogos.map(renderLogo)}</div>
            </div>
            <i className="plans-secure-sep" aria-hidden="true" />
            <div className="plans-secure-group">
              <span>Local · Somalia</span>
              <div>{localLogos.map(renderLogo)}</div>
            </div>
          </div>
          <p>
            Payments are processed by our payment partners. We never see your
            PIN or card details.
          </p>
        </section>
      </main>

      {checkoutPlan && (
        <div
          className="plans-overlay"
          role="dialog"
          aria-modal="true"
          onClick={() => !paying && setCheckoutPlan(null)}
        >
          <div className="plans-checkout" onClick={(e) => e.stopPropagation()}>
            <header>
              <div>
                <span>CHECKOUT</span>
                <h2>{checkoutPlan.name} plan</h2>
              </div>
              <button
                type="button"
                aria-label="Close"
                disabled={paying}
                onClick={() => setCheckoutPlan(null)}
              >
                <Check off />
              </button>
            </header>

            <div className="plans-summary">
              <div>
                <span>
                  {isTeamPlan(checkoutPlan)
                    ? `${seats} users × $${checkoutPlan.monthlyPrice}`
                    : `${checkoutPlan.name} · monthly`}
                </span>
                <strong>
                  $
                  {(
                    checkoutPlan.monthlyPrice * seatsFor(checkoutPlan)
                  ).toLocaleString()}
                </strong>
              </div>
              <small>Billed monthly</small>
            </div>

            <h4>Payment method</h4>
            <div className="plans-methods">
              {methods.map((method) => (
                <label
                  key={method.id}
                  className={method.id === methodId ? "selected" : ""}
                >
                  <input
                    type="radio"
                    name="payment-method"
                    checked={method.id === methodId}
                    onChange={() => setMethodId(method.id)}
                  />
                  {methodLogo(method) ? (
                    <span className="plans-method-mark logo">
                      <img src={methodLogo(method)} alt={method.name} />
                    </span>
                  ) : (
                    <span className="plans-method-mark">
                      {method.name.slice(0, 2).toUpperCase()}
                    </span>
                  )}
                  <div>
                    <strong>{method.name}</strong>
                    {method.description && <small>{method.description}</small>}
                  </div>
                </label>
              ))}
            </div>

            {selectedMethod?.requiresPhone && (
              <label className="plans-field">
                <span>{selectedMethod.name} number</span>
                <input
                  type="tel"
                  inputMode="tel"
                  placeholder="61XXXXXXX"
                  value={phone}
                  onChange={(event) => setPhone(event.target.value)}
                />
              </label>
            )}

            {error && <p className="plans-error">{error}</p>}

            <button
              type="button"
              className="plans-cta"
              disabled={paying || !selectedMethod}
              onClick={() => void pay()}
            >
              {paying
                ? "Sending payment request…"
                : `Pay $${(checkoutPlan.monthlyPrice * seatsFor(checkoutPlan)).toLocaleString()}`}
            </button>
            <p className="plans-note">
              You will get a prompt on your phone to approve the payment.
            </p>
          </div>
        </div>
      )}
    </div>
  )
}
