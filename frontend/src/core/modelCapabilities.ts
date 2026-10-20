import type { ModelDescriptor } from "./types"

const CHAT_CAPABILITY_HINTS = [
  "chat",

  "conversation",

  "reasoning",

  "analysis",

  "data_analysis",

  "research",

  "coding",

  "code",

  "assistant",

  "completion",
]

const MEDIA_MODEL_HINTS = [
  "image_generation",

  "video_generation",

  "audio_generation",

  "voice",

  "speech",

  "speech_synthesis",

  "music",

  "image_to_image",

  "video_to_video",

  "text_to_image",

  "text_to_video",

  "dall_e",

  "dalle",
]

const AGENT_READY_MODEL_IDS = [
  "gpt-6-astra",
  "claude-opus-5",
  "claude-opus-4-8",
  "claude-opus-4-7",
  "gpt-5.6-terra",
  "gpt-5.6-sol",
  "gpt-5.6-luna",
  "gemini-3.1-pro-preview",
  "claude-sonnet-5",
  "claude-fable-5-1",
  "claude-fable-5",
  "gpt-5.5",
  "gpt-5.2-pro",
  "gemini-3-pro-preview",
  "claude-opus-4-6",
  "claude-sonnet-4-6",
  "grok-4.6",
  "deepseek-v4-pro",
  "gemini-deep-research",
  "grok-4.5",
]

const CHAT_CAPABILITY_LABELS: Record<string, string> = {
  chat: "Text chat",

  reasoning: "Reasoning",

  analysis: "Analysis",

  data_analysis: "Data analysis",

  coding: "Coding",

  code: "Coding",

  tools: "Tools",

  research: "Research",
}

const CHAT_CAPABILITY_ORDER = [
  "chat",

  "reasoning",

  "analysis",

  "data_analysis",

  "coding",

  "code",

  "tools",

  "research",
]

const normalizeSignal = (value: string): string =>
  value

    .toLowerCase()

    .trim()

    .replace(/[^a-z0-9]+/g, "_")

    .replace(/^_+|_+$/g, "")

const toStringArray = (value: unknown): string[] => {
  if (typeof value === "string") return value.trim() ? [value.trim()] : []

  if (Array.isArray(value))
    return value.flatMap((item) =>
      typeof item === "string" && item.trim() ? [item.trim()] : [],
    )

  return []
}

const readRecordValues = (
  record: Record<string, unknown>,

  key: string,
): string[] => toStringArray(record[key])

const collectModalitySignals = (model: ModelDescriptor): string[] => {
  const raw = model as unknown as Record<string, unknown>

  const signals = [
    ...readRecordValues(raw, "modalities"),

    ...readRecordValues(raw, "input_modalities"),

    ...readRecordValues(raw, "output_modalities"),

    ...readRecordValues(raw, "inputModalities"),

    ...readRecordValues(raw, "outputModalities"),
  ]

  const rawModalities = raw.modalities

  if (rawModalities && typeof rawModalities === "object") {
    const modalityObject = rawModalities as Record<string, unknown>

    signals.push(
      ...readRecordValues(modalityObject, "input"),

      ...readRecordValues(modalityObject, "output"),

      ...readRecordValues(modalityObject, "inputs"),

      ...readRecordValues(modalityObject, "outputs"),
    )
  }

  return signals.map(normalizeSignal).filter(Boolean)
}

const collectSignals = (model: ModelDescriptor): string[] => {
  const raw = model as unknown as Record<string, unknown>

  const signals = [
    ...toStringArray(model.capabilities),

    ...collectModalitySignals(model),

    ...readRecordValues(raw, "type"),

    ...readRecordValues(raw, "types"),

    ...readRecordValues(raw, "category"),

    ...readRecordValues(raw, "categories"),

    model.id,

    model.displayName,

    model.description,
  ]

  return signals.map(normalizeSignal).filter(Boolean)
}

const hasAnyHint = (signals: string[], hints: string[]): boolean =>
  hints.some((hint) =>
    signals.some((signal) => signal === hint || signal.includes(hint)),
  )

const supportsTextConversation = (model: ModelDescriptor): boolean => {
  const modalitySignals = collectModalitySignals(model)

  if (modalitySignals.length === 0) return false

  const hasRawText = modalitySignals.some(
    (signal) => signal === "text" || signal === "text_input_output",
  )

  const hasTextInput = modalitySignals.some(
    (signal) =>
      signal === "text_input" ||
      (signal.includes("text") && signal.includes("input")),
  )

  const hasTextOutput = modalitySignals.some(
    (signal) =>
      signal === "text_output" ||
      (signal.includes("text") && signal.includes("output")),
  )

  return (hasTextInput && hasTextOutput) || hasRawText
}

export const isChatCapableModel = (model: ModelDescriptor): boolean => {
  const signals = collectSignals(model)

  const hasChatSignals = hasAnyHint(signals, CHAT_CAPABILITY_HINTS)

  const hasTextConversation = supportsTextConversation(model)

  const looksLikeMediaModel = hasAnyHint(signals, MEDIA_MODEL_HINTS)

  if (!hasChatSignals && !hasTextConversation) return false

  // Chat selector must stay chat-only: media generation families are excluded.

  if (looksLikeMediaModel) return false

  return true
}

export const getChatCapableModels = (
  models: ModelDescriptor[],
): ModelDescriptor[] => models.filter(isChatCapableModel)

export const isAgentReadyModel = (model: ModelDescriptor): boolean =>
  AGENT_READY_MODEL_IDS.includes(model.id)

export const getAgentReadyModels = (
  models: ModelDescriptor[],
): ModelDescriptor[] =>
  AGENT_READY_MODEL_IDS.flatMap((id) => {
    const model = models.find((candidate) => candidate.id === id)

    return model ? [model] : []
  })

export const resolveChatModelId = (
  models: ModelDescriptor[],

  requested: string | undefined,
): string | undefined => {
  const chatModels = getChatCapableModels(models)

  if (chatModels.length === 0) return undefined

  const trimmed = typeof requested === "string" ? requested.trim() : ""

  if (trimmed) {
    const exact = chatModels.find((model) => model.id === trimmed)

    if (exact) return exact.id

    const byName = chatModels.find((model) => model.displayName === trimmed)

    if (byName) return byName.id
  }

  return chatModels[0].id
}

export const resolveModelDisplayName = (
  models: ModelDescriptor[],

  modelIdOrName: string,
): string => {
  const byId = models.find((model) => model.id === modelIdOrName)

  if (byId) return byId.displayName

  const byName = models.find((model) => model.displayName === modelIdOrName)

  if (byName) return byName.displayName

  return modelIdOrName
}

const ANTHROPIC_HINTS = ["anthropic", "claude"]

const isAnthropicModel = (model: ModelDescriptor): boolean => {
  const raw = model as unknown as Record<string, unknown>

  const signals = [
    model.id,

    model.displayName,

    model.description,

    ...readRecordValues(raw, "provider"),

    ...readRecordValues(raw, "vendor"),

    ...readRecordValues(raw, "family"),

    ...readRecordValues(raw, "company"),
  ]

    .map(normalizeSignal)

    .filter(Boolean)

  return hasAnyHint(signals, ANTHROPIC_HINTS)
}

export const resolveDefaultChatModelId = (
  models: ModelDescriptor[],

  requested?: string,
): string | undefined => {
  const chatModels = getChatCapableModels(models)

  if (chatModels.length === 0) return undefined

  const trimmedRequested = typeof requested === "string" ? requested.trim() : ""

  const resolvedRequested = resolveChatModelId(models, requested)

  // Keep the explicit user selection when it resolves to a valid chat model.

  if (trimmedRequested && resolvedRequested) return resolvedRequested

  const availableChatModels = chatModels.filter(
    (model) => model.availability !== "unavailable",
  )

  const preferredPool =
    availableChatModels.length > 0 ? availableChatModels : chatModels

  const anthropic = preferredPool.find(isAnthropicModel)

  if (anthropic) return anthropic.id

  return resolvedRequested || preferredPool[0]?.id
}

export const describeChatCapabilities = (model: ModelDescriptor): string => {
  const capabilitySignals = toStringArray(model.capabilities)

    .map(normalizeSignal)

    .filter(Boolean)

  const labels = CHAT_CAPABILITY_ORDER.flatMap((capability) =>
    capabilitySignals.includes(capability) && CHAT_CAPABILITY_LABELS[capability]
      ? [CHAT_CAPABILITY_LABELS[capability]]
      : [],
  )

  const uniqueLabels = [...new Set(labels)]

  if (uniqueLabels.length > 0) return uniqueLabels.join(", ")

  if (supportsTextConversation(model)) return "Text chat"

  return "Chat"
}
