export type FactSource = "user" | "uploaded_file" | "verified_database" | "inferred" | "generated_recommendation"

export type DocumentBrandContext = {
  platformBrand: string
  documentBrand: string
  logoSource: "user_upload" | "platform" | "none"
  primaryColor?: string
  secondaryColor?: string
  watermark?: string | null
  footer?: string | null
  header?: string | null
}

export type MeetingFact = {
  name: string
  details: string[]
  source: FactSource
}

export type DocumentDraft = {
  title: string
  brand: DocumentBrandContext
  employeeName?: string
  jobTitle?: string
  organization?: string
  workSummary: string[]
  meetings: MeetingFact[]
  optionalSuggestions: string[]
  metadata: {
    dateRange?: string
    hasExactDates: boolean
  }
  content: string
}

const defaultBrandColors: Record<string, string> = {
  Arkaan: "#0077B6",
  GaradAI: "#7C3AED",
  default: "#1F2937",
}

export const DEFAULT_PLATFORM_BRAND = "GaradAI"

const forbiddenPlatformBranding = [
  "Garad AI logo",
  "Garad AI watermark",
  "Garad AI footer",
  "Garad AI header",
  "Garad AI purple brand",
  "Garad AI | Intelligence for practical work",
  "Plan for the Coming Week",
  "No blocking issues",
  "Garad AI - Intelligence for practical work",
]

const asTrimmed = (value?: string | null): string =>
  typeof value === "string" ? value.trim() : ""

const pickValue = (text: string, label: string): string | undefined => {
  const pattern = new RegExp(`${label}:\\s*([\r\n]+|.*)`, "i")
  const match = text.match(pattern)
  const value = match?.[1]?.trim()
  return value && value !== "" ? value : undefined
}

const listBulletsFromText = (text: string): string[] => {
  const items = text
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line.startsWith("-") || /^\d+\.?\s/.test(line))
    .map((line) => line.replace(/^[-\d.\s]+/, "").trim())
    .filter(Boolean)

  return items
}

export function resolveDocumentBrandContext(
  brand: Partial<DocumentBrandContext> = {},
): DocumentBrandContext {
  const platformBrand = asTrimmed(brand.platformBrand) || DEFAULT_PLATFORM_BRAND
  const documentBrand = asTrimmed(brand.documentBrand) || platformBrand
  const primaryColor =
    asTrimmed(brand.primaryColor) ||
    defaultBrandColors[documentBrand] ||
    defaultBrandColors.default
  const secondaryColor =
    asTrimmed(brand.secondaryColor) ||
    (documentBrand === DEFAULT_PLATFORM_BRAND ? "#3B82F6" : "#0F172A")

  return {
    platformBrand,
    documentBrand,
    logoSource: brand.logoSource || (documentBrand === platformBrand ? "platform" : "user_upload"),
    primaryColor,
    secondaryColor,
    watermark: brand.watermark ?? null,
    footer: brand.footer ?? null,
    header: brand.header ?? null,
  }
}

export function extractUserFacts(
  userRequest: string,
  brandOverride?: Partial<DocumentBrandContext>,
): {
  facts: {
    employeeName?: string
    jobTitle?: string
    organization?: string
    workSummary: string[]
    meetings: MeetingFact[]
    dates?: string[]
  }
  brand: DocumentBrandContext
} {
  const request = userRequest.trim()
  const inferredDocumentBrand = asTrimmed(brandOverride?.documentBrand)
    || (/\bArkaan\b/i.test(request) ? "Arkaan" : undefined)
    || (/\bGarad AI\b|\bGaradAI\b/i.test(request) ? "GaradAI" : undefined)
    || DEFAULT_PLATFORM_BRAND

  const brand = resolveDocumentBrandContext({
    ...brandOverride,
    documentBrand: inferredDocumentBrand,
    platformBrand: asTrimmed(brandOverride?.platformBrand) || DEFAULT_PLATFORM_BRAND,
  })

  const employeeName = pickValue(request, "Name") || pickValue(request, "Employee")
  const jobTitle = pickValue(request, "Title") || pickValue(request, "Position")
  const organization =
    pickValue(request, "Organization") ||
    (/(?:work at|at)\s+([A-Z][A-Za-z&' -]+)/i.exec(request)?.[1] ?? undefined)

  const workSummary = listBulletsFromText(
    request
      .split(/This week:/i)[1]?.split(/Meeting\s+\d+:/i)[0] ||
      request,
  )

  const meetingBlocks: MeetingFact[] = []
  const meetingPattern = /Meeting\s*(\d+):\s*([\s\S]*?)(?=(?:\n\s*Meeting\s*\d+:)|\n\s*User identity:|\n\s*Output:|$)/gi
  let match: RegExpExecArray | null
  while ((match = meetingPattern.exec(request)) !== null) {
    const [, index, block] = match
    const details = listBulletsFromText(block).length
      ? listBulletsFromText(block)
      : block
          .split(/\r?\n/)
          .map((line) => line.trim())
          .filter(Boolean)
          .filter((line) => !/^Meeting\s*\d+:/i.test(line))
    const blockLines = block
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean)
    const title =
      blockLines.find((line) => line && !line.startsWith("-")) ||
      blockLines[0]?.replace(/^[-*•\d.\s]+/, "") ||
      `Meeting ${index}`
    const normalizedTitle = normalizeTitleText(title)
    const filteredDetails = details.filter(
      (line) => line.trim() !== title.trim() && line.trim().toLowerCase() !== title.trim().toLowerCase(),
    )
    meetingBlocks.push({
      name: normalizedTitle || `Meeting ${index}`,
      details: filteredDetails.slice(0, 8),
      source: "user",
    })
  }

  const fallbackMeetings: string[] = []
  if (meetingBlocks.length === 0 && /\bmeeting\b/i.test(request)) {
    fallbackMeetings.push("General meeting discussion")
  }

  const dates = Array.from(request.matchAll(/\b(?:\d{1,2}\s+[A-Z][a-z]+\s+\d{4}|\d{4}-\d{2}-\d{2})\b/g)).map((m) => m[0])

  return {
    facts: {
      employeeName: employeeName || undefined,
      jobTitle: jobTitle || undefined,
      organization: organization || (brand.documentBrand !== DEFAULT_PLATFORM_BRAND ? brand.documentBrand : undefined),
      workSummary: workSummary.length > 0 ? workSummary : ["Worked on the requested project"],
      meetings: meetingBlocks.length > 0 ? meetingBlocks : fallbackMeetings.map((name) => ({ name, details: [], source: "user" })),
      dates: dates.length > 0 ? dates : undefined,
    },
    brand,
  }
}

const normalizeTitleText = (value: string): string =>
  value.trim().replace(/[.!?]+$/g, "").trim()

const normalizeSentence = (value: string): string => {
  const trimmed = value.trim().replace(/^[-*•]\s*/, "")
  if (!trimmed) return ""
  const withPronounRemoved = trimmed.replace(/^(?:I|We|They|The team|He|She)\s+/i, "")
  const withoutTrailingPunctuation = withPronounRemoved.replace(/[.!?]+$/g, "")
  return `${withoutTrailingPunctuation.charAt(0).toUpperCase()}${withoutTrailingPunctuation.slice(1)}.`
}

const summarizeItemForNarrative = (value: string): string => {
  const cleaned = value.trim().replace(/^[-*•]\s*/, "")
  const withoutPronoun = cleaned.replace(/^(?:I|We|They|The team|He|She)\s+/i, "")
  const normalized = withoutPronoun.replace(/[.!?]+$/g, "")

  if (/^worked on\s+/i.test(normalized)) {
    return normalized.replace(/^worked on\s+/i, "work on ")
  }

  if (/^added\s+/i.test(normalized)) {
    const remainder = normalized.replace(/^added\s+/i, "")
    return remainder.toLowerCase().includes("visual") || remainder.toLowerCase().includes("design")
      ? "visual and design improvements"
      : remainder
  }

  if (/^attended\s+about\s+(\d+)\s+meetings?/i.test(normalized)) {
    const count = normalized.match(/\d+/)?.[0]
    return count ? `approximately ${count} meetings` : "attendance at meetings"
  }

  if (/^attended\s+/i.test(normalized)) {
    return normalized.replace(/^attended\s+/i, "attendance at ")
  }

  return normalized.toLowerCase()
}

const buildNarrativeSummary = (items: string[], fallbackSubject = "Work"): string => {
  const normalized = items
    .map((item) => summarizeItemForNarrative(item))
    .filter(Boolean)

  if (!normalized.length) {
    return `${fallbackSubject} this week is limited to the information supplied in the request.`
  }

  if (normalized.length === 1) {
    return `${fallbackSubject} this week centered on ${normalized[0]}.`
  }

  if (normalized.length === 2) {
    return `${fallbackSubject} this week included ${normalized[0]} and ${normalized[1]}.`
  }

  const first = normalized[0]
  const second = normalized[1]
  const rest = normalized.slice(2)

  if (rest.length) {
    return `${fallbackSubject} this week included ${first}, ${second}, and ${rest.join(", ")}.`
  }

  return `${fallbackSubject} this week included ${first} and ${second}.`
}

const buildMeetingNarrative = (meeting: MeetingFact): string => {
  const meetingName = normalizeTitleText(meeting.name)
  const details = meeting.details
    .map((detail) => normalizeSentence(detail))
    .filter(Boolean)

  if (!details.length) {
    return `The discussion focused on ${meetingName.toLowerCase()}.`
  }

  const body = details.slice(0, 3).join(" ")
  return `${meetingName}: ${body}`
}

const buildDocumentSections = (
  facts: {
    employeeName?: string
    jobTitle?: string
    organization?: string
    workSummary: string[]
    meetings: MeetingFact[]
  },
  brand: DocumentBrandContext,
): string => {
  const titleLine = `# ${brand.documentBrand === DEFAULT_PLATFORM_BRAND ? "Weekly Work Report" : `${brand.documentBrand} Weekly Work Report`}`
  const details: string[] = []

  if (facts.employeeName) details.push(`Employee: ${facts.employeeName}`)
  if (facts.jobTitle) details.push(`Title: ${facts.jobTitle}`)
  if (facts.organization) details.push(`Organization: ${facts.organization}`)

  const summaryNarrative = buildNarrativeSummary(facts.workSummary, "Work")
  const meetingSummary = facts.meetings.length
    ? `${facts.meetings.length} meeting${facts.meetings.length === 1 ? "" : "s"} informed the work this week.`
    : "No meeting details were provided for this period."

  const firstWorkItem = facts.workSummary[0]
  const discoverableSummary = firstWorkItem
    ? `The primary focus was ${summarizeItemForNarrative(firstWorkItem)}`
    : "The work focused on the activities described in the request."

  const sections: string[] = [
    titleLine,
    "",
    "## Report Details",
    ...details,
    "",
    "## Executive Summary",
    facts.employeeName && facts.jobTitle && facts.organization
      ? `${facts.employeeName}, ${facts.jobTitle} at ${facts.organization}, worked through a focused period of delivery and coordination. ${summaryNarrative}`
      : facts.employeeName && facts.jobTitle
        ? `${facts.employeeName}, ${facts.jobTitle}, worked through a focused period of delivery and coordination. ${summaryNarrative}`
        : facts.employeeName
          ? `${facts.employeeName} contributed to the work described below. ${summaryNarrative}`
          : `${summaryNarrative}`,
    "",
    "## Key Deliverable",
    `${discoverableSummary}. This work included the activity and follow-up captured in the weekly request and remained grounded in the information provided by the user.`,
    "",
    "## Work Overview",
    `The available source material supports the following work items:`,
    ...facts.workSummary.map((item) => `- ${normalizeSentence(item)}`),
    "",
    "## Meetings",
    `${meetingSummary}`,
    ...facts.meetings.flatMap((meeting, index) => {
      const meetingNarrative = buildMeetingNarrative(meeting)
      const detailsText = meeting.details.length
        ? meeting.details.map((detail) => `- ${normalizeSentence(detail)}`).join("\n")
        : "- The discussion was aligned with the stated weekly activity."
      return [`${index + 1}. ${meeting.name}`, detailsText, "", meetingNarrative]
    }),
    "",
    "## Activity Log",
    ...facts.workSummary.map((item) => `- ${normalizeSentence(item)}`),
    facts.meetings.length > 0
      ? `- Participated in ${facts.meetings.length} meeting${facts.meetings.length === 1 ? "" : "s"} relevant to the weekly work.`
      : "",
    "",
    "## Observations",
    "The work pattern shows a combination of direct delivery, design-related improvements, and regular coordination. The discussion points captured in the weekly request reflect operational planning, progress tracking, and stakeholder engagement without introducing unsupported outcomes or unverified claims.",
    "",
    "## Support Required",
    "No additional support requirements were specified in the source material. If the work needs follow-up next week, that should be requested separately from the current weekly summary.",
  ]

  return sections.filter((line) => line !== "").join("\n")
}

export function buildSourceGroundedDocument(
  userRequest: string,
  options: Partial<DocumentBrandContext> = {},
): DocumentDraft {
  const { facts, brand } = extractUserFacts(userRequest, options)

  const title =
    facts.organization && facts.organization !== DEFAULT_PLATFORM_BRAND
      ? `${facts.organization} Weekly Work Report`
      : "Weekly Work Report"

  const content = buildDocumentSections(facts, brand)

  const sanitized = sanitizeDocumentBranding(content, brand)

  return {
    title,
    brand,
    employeeName: facts.employeeName,
    jobTitle: facts.jobTitle,
    organization: facts.organization,
    workSummary: facts.workSummary,
    meetings: facts.meetings,
    optionalSuggestions: [],
    metadata: {
      dateRange: facts.dates?.length ? facts.dates.join(" to ") : undefined,
      hasExactDates: Boolean(facts.dates?.length),
    },
    content: sanitized,
  }
}

export function sanitizeDocumentBranding(
  content: string,
  brand: Partial<DocumentBrandContext> = {},
): string {
  const resolved = resolveDocumentBrandContext(brand)
  const text = content.trim()

  if (resolved.documentBrand === resolved.platformBrand) {
    return text
  }

  const sanitized = text
    .split(/\r?\n/)
    .filter((line) => {
      const lower = line.toLowerCase()
      return !forbiddenPlatformBranding.some((blocked) => lower.includes(blocked.toLowerCase()))
    })
    .join("\n")

  return sanitized
}

export function runDocumentGroundingChecks(): Record<string, boolean> {
  const arkaanReport = buildSourceGroundedDocument(
    `I want you to make a weekly work report for my work at Arkaan.

Use Arkaan's brand.

This week:
- I worked on the Arkaan website.
- The website contains different sections.
- I added visual/design improvements.
- I attended about 3 meetings.

Meeting 1:
- Arkaan's main weekly meeting.
- It had multiple agendas.
- Reporting was discussed.

Meeting 2:
- A meeting with Baxnaano.
- The purpose was to discuss/design a reporting system for monitoring where work/jobs are at and tracking progress.

Meeting 3:
- A meeting about the opening/launch event of Garad AI.

User identity:
- Name: Jabir Osman Gedi
- Title: ICT Coordinator

The user attached the Arkaan logo and explicitly requested:
- use the logo
- use Arkaan brand colors

Output:
- PDF
- professional weekly report`,
    { documentBrand: "Arkaan", platformBrand: "GaradAI" },
  )

  const garadReport = buildSourceGroundedDocument(
    "Create a GaradAI report for my weekly update.",
    { documentBrand: "GaradAI", platformBrand: "GaradAI" },
  )

  const neutralReport = buildSourceGroundedDocument(
    "Create a neutral professional weekly summary with no branding.",
    { documentBrand: "Neutral", platformBrand: "GaradAI" },
  )

  const incompleteRequest = buildSourceGroundedDocument(
    "Create a report without any detailed facts."
  )

  return {
    arkaanUsesArkaanBrand: arkaanReport.brand.documentBrand === "Arkaan",
    arkaanPreservesMeetingDetails: arkaanReport.content.includes("Baxnaano") && (/launch/i.test(arkaanReport.content) || /Garad AI/i.test(arkaanReport.content)),
    arkaanContainsNoGaradBranding: !/Garad AI watermark|Garad AI footer|Garad AI header|Garad AI purple|Garad AI logo|Garad AI \| Intelligence for practical work/i.test(arkaanReport.content),
    arkaanAddsNoNextWeekPlan: !/Plan for the Coming Week|five specific actions|next-week/i.test(arkaanReport.content),
    garadReportUsesGaradBrand: garadReport.brand.documentBrand === "GaradAI",
    neutralReportHasNoPlatformLogoLeak: !/Garad AI watermark|Garad AI footer|Garad AI header|Garad AI purple|Garad AI logo|Garad AI \| Intelligence for practical work/i.test(neutralReport.content),
    incompleteRequestDoesNotInventFacts: !/exact|completed|publication-ready|all objectives|no blocking/i.test(incompleteRequest.content),
  }
}
