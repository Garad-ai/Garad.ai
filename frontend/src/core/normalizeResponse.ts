const NAMED_ENTITIES: Record<string, string> = {
  lt: "<",

  gt: ">",

  amp: "&",

  quot: '"',

  apos: "'",

  nbsp: " ",
}

export function decodeEntities(text: string) {
  return text.replace(/&(#x?[0-9a-f]+|[a-z]+);/gi, (match, body: string) => {
    if (body[0] === "#") {
      const code =
        body[1].toLowerCase() === "x"
          ? parseInt(body.slice(2), 16)
          : parseInt(body.slice(1), 10)

      return Number.isFinite(code) && code > 0 && code < 0x110000
        ? String.fromCodePoint(code)
        : match
    }

    return NAMED_ENTITIES[body.toLowerCase()] ?? match
  })
}

// Backend appends "<br/>:::meta<br/>{entities...}<br/>:::" (sometimes "...meta").

const META_BLOCK =
  /(?:<br\s*\/?>|[ \t\r\n])*(?::{2,3}|\.{3})[ \t]*meta\b[\s\S]*$/i

const ENTITY_JSON =
  /(?:<br\s*\/?>|[ \t\r\n])*\{\s*"(?:entities|suggestions)"[\s\S]*$/

const TRAILING_FENCE = /(?:<br\s*\/?>|[ \t\r\n])*:{3}[ \t]*$/

// A marker still being typed while streaming: ":::me", "<br/>::", "<b"

const PARTIAL_MARKER =
  /(?:<br\s*\/?>|[ \t\r\n])*(?::{1,3}(?:m(?:e(?:t(?:a)?)?)?)?|\.{1,3}(?:m(?:e(?:t)?)?)?)$/i

const PARTIAL_TAG = /<(?:\/|b|br|br\s*|br\s*\/|t|th|thi|thin|think|thinking)?$/i

const REASONING_BLOCK =
  /(?:\[\s*START_REASONING\s*\][\s\S]*?(?:\[\s*END_REASONING\s*\]|$)|\[\s*END_REASONING\s*\][\s\S]*?$|<\s*(?:think|thinking|reasoning|analysis)\b[^>]*>[\s\S]*?(?:<\/\s*(?:think|thinking|reasoning|analysis)\s*>|$))/gi

function transformOutsideCode(text: string, fn: (part: string) => string) {
  return text

    .split(/(```[\s\S]*?(?:```|$))/g)

    .map((part, index) => (index % 2 === 1 ? part : fn(part)))

    .join("")
}

export function normalizeAssistantResponse(
  raw: string | undefined | null,

  options: { streaming?: boolean } = {},
): string {
  if (!raw) return ""

  let text = stripToolMarkup(String(raw).replace(/\r\n/g, "\n"))

  // The backend may flatten newlines to <br/>, including inside fenced blocks.
  text = text
    .split("```")
    .map((part, index) =>
      index % 2 === 1 ? part.replace(/[ \t]*<br\s*\/?>[ \t]*/gi, "\n") : part,
    )
    .join("```")

  text = text.replace(REASONING_BLOCK, "")

  // Escaped markup from the backend (&lt;br/&gt;) becomes real markup first.

  text = transformOutsideCode(text, decodeEntities)

  text = text.replace(META_BLOCK, "")

  text = text.replace(ENTITY_JSON, "")

  text = text.replace(TRAILING_FENCE, "")

  text = transformOutsideCode(text, (part) =>
    part

      .replace(/<think(?:ing)?>[\s\S]*?<\/think(?:ing)?>/gi, "")

      .replace(/<think(?:ing)?>[\s\S]*$/i, "")

      .replace(/(?:[ \t]*<br\s*\/?>[ \t]*){2,}/gi, "\n\n")

      .replace(/[ \t]*<br\s*\/?>[ \t]*/gi, "  \n"),
  )

  if (options.streaming) {
    text = text.replace(PARTIAL_MARKER, "").replace(PARTIAL_TAG, "")
  }

  return text.replace(/\n{3,}/g, "\n\n").trimEnd()
}

export function cleanErrorMessage(
  raw: string | undefined | null,
): {
  summary: string

  technical?: string
} {
  const value = (raw || "").trim()

  if (!value) return { summary: "The request could not be completed." }

  if (/^(?:<!doctype\s+html|<html)\b/i.test(value)) {
    return { summary: "The server returned an unexpected error. Please try again." }
  }

  const start = value.indexOf("{")

  if (start >= 0) {
    try {
      const parsed = JSON.parse(value.slice(start))

      const message =
        parsed?.message ||
        parsed?.error?.message ||
        (typeof parsed?.error === "string" ? parsed.error : "") ||
        parsed?.detail

      if (
        typeof message === "string" &&
        /^(?:<!doctype\s+html|<html)\b/i.test(message.trim())
      ) {
        return {
          summary: "The server returned an unexpected error. Please try again.",
        }
      }

      if (typeof message === "string" && message.trim())
        return { summary: message.trim(), technical: value }
    } catch {
      // not JSON; fall through
    }

    const prefix = value
      .slice(0, start)
      .trim()
      .replace(/[:\-–]+$/, "")

    if (prefix) return { summary: prefix, technical: value }

    return {
      summary: "The server returned an unexpected error.",
      technical: value,
    }
  }

  return { summary: normalizeAssistantResponse(value) || value }
}

export function chatErrorSummary(
  message: string | undefined,
  code?: string,
  status?: number,
): string {
  const signal = `${code || ""} ${message || ""}`.toLowerCase()

  if (/provider_access_denied|not granted this account access to the deep research model/.test(signal)) {
    return "The AI provider has not granted this account access to the Deep Research model. Ask the administrator to enable it, or turn off Deep Research and use Web Search instead."
  }

  if (/provider_quota|quota or billing limit for deep research/.test(signal)) {
    return "The AI provider quota or billing limit for Deep Research has been reached. Try again later or use Web Search instead."
  }

  if (
    /insufficient.{0,30}(?:credits?|quota)|(?:credits?|quota).{0,30}(?:insufficient|exhausted|exceeded|depleted)|(?:exceeded|exhausted).{0,30}(?:credits?|quota)|billing.{0,30}(?:limit|disabled|exhausted)|(?:spending|usage).{0,30}limit/.test(
      signal,
    )
  ) {
    return "This AI model is temporarily unavailable because its provider has insufficient credits or quota. Please try another available model or come back later."
  }

  if (
    /(?:missing|invalid|incorrect|revoked|expired).{0,30}(?:api[_ -]?key|credentials?)|(?:api[_ -]?key|credentials?).{0,30}(?:missing|invalid|incorrect|revoked|expired)|provider[_ -]?not[_ -]?configured|provider.{0,20}not configured|not connected to a chat provider/.test(
      signal,
    )
  ) {
    return "This AI provider is not configured correctly right now. Please try another available model or contact support."
  }

  if (
    /api_not_configured|backend_not_configured|no_provider|no provider.{0,20}configured/.test(
      signal,
    )
  ) {
    return "Garad AI is not connected to a chat provider in this environment."
  }

  if (
    /(?:selected[_ -]?)?model[_ -]?(?:unavailable|not[_ -]?found|unsupported)|model.{0,20}(?:unavailable|not found|unsupported)|model_not_supported/.test(
      signal,
    )
  ) {
    return "The selected AI model is unavailable or no longer supported. Choose another available model and try again."
  }

  if (
    /(?:subscription|plan|entitlement).{0,30}(?:restrict|not allow|not available)|(?:not allowed|access restricted).{0,30}model/.test(
      signal,
    ) ||
    status === 403
  ) {
    return "Your current plan or account does not allow access to this model. Choose an available model or review your plan."
  }

  if (/rate[_ -]?limit|too many requests/.test(signal) || status === 429) {
    return "The AI provider is receiving too many requests right now. Please wait a moment and try again."
  }

  if (
    /timeout|timed out|temporarily unavailable|server error/.test(signal) ||
    status === 408 ||
    status === 502 ||
    status === 503 ||
    status === 504
  ) {
    return "The AI provider is temporarily unavailable. Please try again shortly."
  }

  if (status === 401) {
    return "Your session may have expired. Sign in again, then retry your message."
  }

  return "Garad AI could not complete this response. Your message is preserved; please retry or choose another available model."
}

const ARTIFACT_CARD =
  /<div class="garad-tool-result-card[^"]*"[^>]*>[\s\S]*?<\/div>/gi

const ARTIFACT_MARKDOWN_LINK =
  /\[(?:download|open\s*\/\s*download)\s+([^\]]+\.(?:pdf|docx?|xlsx?|pptx?|csv|txt|md|png|jpe?g|webp|gif|mp3|wav|m4a|mp4|mov|webm|zip|rar|7z))\]\(((?:https?:\/\/|\/)[^)]+)\)/gi

const TOOL_MARKER = /\[\[GARAD_[A-Z_]+:[^\]]*\]\]/g
const INTERNAL_TOOL_TRACE =
  /<\s*[\u007c\uFF5C]{1,2}\s*DSML\s*[\u007c\uFF5C]{1,2}\s*calls\s*>[\s\S]*?(?:<\s*\/\s*[\u007c\uFF5C]{1,2}\s*DSML\s*[\u007c\uFF5C]{1,2}\s*calls\s*>|$)/gi

function stripToolMarkup(text: string) {
  return text
    .replace(INTERNAL_TOOL_TRACE, "")
    .replace(ARTIFACT_CARD, "")

    .replace(ARTIFACT_MARKDOWN_LINK, "")

    .replace(TOOL_MARKER, "")

    .replace(
      /<div class="garad-tool[^>]*$|<div class="garad-tool[^"]*"[\s\S]*$/i,
      "",
    )

    .replace(/\[\[GARAD_[^\]]*$/, "")
}

export type ToolFile = {
  name: string
  url: string
  label: string
  mimeType?: string
  extension?: string
  kind?: string
  size?: number
}

export type ToolImage = { url: string }

export type PresentationJob = {
  id: string
  exportAs: "pptx" | "pdf"
}

const decodeBase64Url = (value: string): string | null => {
  try {
    const base64 = value.replace(/-/g, "+").replace(/_/g, "/")

    return atob(base64 + "=".repeat((4 - (base64.length % 4)) % 4))
  } catch {
    return null
  }
}

export function extractToolResults(
  raw: string | undefined | null,
): {
  files: ToolFile[]

  images: ToolImage[]

  presentationJobs: PresentationJob[]

  videoJobs: string[]
} {
  const files: ToolFile[] = []

  const images: ToolImage[] = []

  const presentationJobs: PresentationJob[] = []

  const videoJobs: string[] = []

  const text = String(raw || "")

  for (const card of text.match(ARTIFACT_CARD) || []) {
    const label = /<strong>([\s\S]*?)<\/strong>/i.exec(card)?.[1]

    const name = /<span>([\s\S]*?)<\/span>/i.exec(card)?.[1]

    const url = /href="([^"]+)"/i.exec(card)?.[1]
    const mimeType = /data-mime-type="([^"]*)"/i.exec(card)?.[1]
    const extension = /data-extension="([^"]*)"/i.exec(card)?.[1]
    const kind = /data-kind="([^"]*)"/i.exec(card)?.[1]
    const size = Number(/data-size="(\d+)"/i.exec(card)?.[1])

    if (name && url)
      files.push({
        name: decodeEntities(name),

        url: decodeEntities(url),

        label: decodeEntities(label || ""),

        mimeType: mimeType ? decodeEntities(mimeType) : undefined,

        extension: extension ? decodeEntities(extension) : undefined,

        kind: kind ? decodeEntities(kind) : undefined,

        size: Number.isFinite(size) && size > 0 ? size : undefined,
      })
  }

  for (const match of text.matchAll(ARTIFACT_MARKDOWN_LINK)) {
    const name = decodeEntities(match[1].trim())

    const url = decodeEntities(match[2].trim())

    if (name && url && !files.some((file) => file.url === url))
      files.push({
        name,

        url,

        label: `${extOfName(name)} file ready`,

        extension: extOfName(name).toLowerCase(),
      })
  }

  for (const match of text.matchAll(
    /\[\[GARAD_IMAGE_RESULT:([A-Za-z0-9_-]+)\]\]/g,
  )) {
    const url = decodeBase64Url(match[1])

    if (url && !images.some((image) => image.url === url)) images.push({ url })
  }

  for (const match of text.matchAll(
    /\[\[GARAD_PRESENTATION_JOB:([A-Za-z0-9_-]+)\]\]/g,
  )) {
    if (!presentationJobs.some((job) => job.id === match[1])) {
      const metadata = new RegExp(
        `\\[\\[GARAD_PRESENTATION_META:\\d+\\|(pptx|pdf)\\]\\]`,

        "i",
      ).exec(text)

      presentationJobs.push({
        id: match[1],

        exportAs: metadata?.[1]?.toLowerCase() === "pdf" ? "pdf" : "pptx",
      })
    }
  }

  for (const match of text.matchAll(/\[\[GARAD_VIDEO_JOB:(\d+)\]\]/g))
    if (!videoJobs.includes(match[1])) videoJobs.push(match[1])

  return { files, images, presentationJobs, videoJobs }
}

const extOfName = (name: string) =>
  (name.split(".").pop() || "file").toUpperCase()
