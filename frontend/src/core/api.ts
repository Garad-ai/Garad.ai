import type {
  Account,
  Agent,
  AgentRun,
  AgentWorkflow,
  AgentWorkflowApproval,
  AgentWorkflowRun,
  AgentShare,
  AgentShareResourceType,
  AgentShareRecipient,
  ApiEnvelope,
  ApiListEnvelope,
  Artifact,
  Attachment,
  BrandProduct,
  ChatRequest,
  ComposerCapabilities,
  ComposerPrompt,
  ComposerSkill,
  Connector,
  ConnectorAccess,
  Conversation,
  ImageModelDescriptor,
  LibraryItem,
  LibrarySort,
  MemoryItem,
  Message,
  MessageMention,
  SharedAgentResource,
  MentionCandidate,
  ModelDescriptor,
  NotificationItem,
  Plan,
  PaymentMethod,
  CheckoutInput,
  SalesInquiry,
  CheckoutResult,
  PluginApp,
  Preferences,
  Project,
  PublicProfile,
  ProfileUpdateInput,
  TeamWorkspace,
  TeamWorkspaceMember,
  ScheduledTask,
  StreamEvent,
  EntityDetail,
  EntityHighlight,
  SmartImageResult,
  Subscription,
  Usage,
  User,
} from "./types"

declare global {
  interface Window {
    __GARAD_API_BASE_URL?: string
  }
}

export class ApiError extends Error {
  readonly status: number

  readonly code?: string

  readonly details?: unknown

  constructor(
    message: string,

    status: number,

    code?: string,

    details?: unknown,
  ) {
    super(message)

    this.name = "ApiError"

    this.status = status

    this.code = code

    this.details = details
  }
}

type Primitive = string | number | boolean

type QueryValue = Primitive | null | undefined

type QueryParams = Record<string, QueryValue>

type HttpMethod = "GET" | "POST" | "PATCH" | "PUT" | "DELETE"

const LEGACY_WEBSITE_URL =
  import.meta.env.VITE_GARAD_CHAT_WEBSITE_URL?.trim() || "chatpro"

const LEGACY_CHAT_CATEGORY_ID =
  import.meta.env.VITE_GARAD_CHAT_CATEGORY_ID?.trim() || ""

const DEFAULT_REQUEST_TIMEOUT_MS = 10000

const LEGACY_HISTORY_TIMEOUT_MS = 60000

// Account and models are embedded in the page HTML by Laravel; use each once.
const takeBootstrapSeed = <T>(key: "account" | "models"): T | null => {
  if (typeof window === "undefined") return null
  const holder = window as unknown as {
    __GARAD_BOOTSTRAP?: Record<string, unknown>
  }
  const value = holder.__GARAD_BOOTSTRAP?.[key]
  if (holder.__GARAD_BOOTSTRAP) delete holder.__GARAD_BOOTSTRAP[key]
  return value as T ?? null
}

const looksNumericId = (value: string): boolean => /^\d+$/.test(value)

const uploadedAssetsByAttachmentId = new Map<string, {
  paths: string[]
  names: string[]
  mimeType: string
  originalName: string
}>()

const looksLikeHtmlDocument = (value: string): boolean => {
  const text = value.trim().toLowerCase()

  return text.startsWith("<!doctype html") || text.startsWith("<html")
}

const isSafeHttpUrl = (value: string): boolean => {
  try {
    const url = new URL(value)
    return url.protocol === "https:" || url.protocol === "http:"
  } catch {
    return false
  }
}

const toIsoString = (value: unknown): string => {
  if (typeof value === "string" && value.trim()) return value

  return new Date().toISOString()
}

const coerceStringId = (value: unknown): string => {
  if (typeof value === "string" && value.trim()) return value

  if (typeof value === "number" && Number.isFinite(value)) return String(value)

  return crypto.randomUUID()
}

const toNumericIdString = (value: unknown): string | null => {
  if (typeof value === "number" && Number.isFinite(value)) {
    return String(Math.trunc(value))
  }

  if (typeof value === "string") {
    const trimmed = value.trim()

    if (trimmed && looksNumericId(trimmed)) return trimmed
  }

  return null
}

const mapLegacyToolState = (
  state: unknown,
): "queued" | "running" | "completed" | "failed" | "waiting_for_approval" | "cancelled" => {
  if (typeof state !== "string") return "running"

  if (state === "completed") return "completed"

  if (state === "queued") return "queued"

  if (state === "failed") return "failed"

  if (state === "waiting_for_approval") return "waiting_for_approval"

  if (state === "cancelled") return "cancelled"

  return "running"
}

const parseBooleanLike = (value: unknown): boolean => {
  if (typeof value === "boolean") return value

  if (typeof value === "number") return value !== 0

  if (typeof value !== "string") return false

  const normalized = value.trim().toLowerCase()

  return (
    normalized === "1" ||
    normalized === "true" ||
    normalized === "yes" ||
    normalized === "on" ||
    normalized === "active"
  )
}

const mapWorkflowStatusToAgentStatus = (value: unknown): Agent["status"] => {
  if (typeof value !== "string") return "draft"

  const status = value.trim().toLowerCase()

  if (status === "active") return "active"

  if (status === "paused") return "paused"

  if (status === "archived") return "archived"

  return "draft"
}

const mapWorkflowRunState = (value: unknown): AgentRun["state"] => {
  if (typeof value !== "string") return "queued"

  const status = value.trim().toLowerCase()

  if (status === "running") return "running"

  if (status === "completed") return "completed"

  if (status === "failed") return "failed"

  if (status === "cancelled") return "cancelled"

  if (status === "paused") return "paused"

  if (status === "blocked") return "blocked"

  return "queued"
}

const statusToProgress = (state: AgentRun["state"]): number => {
  if (state === "queued") return 10

  if (state === "planning") return 25

  if (state === "running") return 60

  if (state === "tool_call") return 70

  if (state === "waiting_for_approval") return 75

  if (state === "paused") return 80

  if (state === "completed") return 100

  if (state === "failed" || state === "cancelled" || state === "blocked")
    return 100

  return 0
}

const isMissingEndpointError = (error: unknown): boolean =>
  error instanceof ApiError && (error.status === 404 || error.status === 405)

const isLegacyChatNotFound = (error: unknown): boolean =>
  error instanceof ApiError &&
  (error.code === "chat_not_found" || /chat not found/i.test(error.message))

const LEGACY_MODEL_REPLACEMENT_PATTERN =
  /use\s+(models\/[a-z0-9._-]+)\s+for\s+the\s+latest/i

const extractLegacyReplacementModelId = (
  value: unknown,
): string | undefined => {
  if (typeof value !== "string") return undefined

  const match = LEGACY_MODEL_REPLACEMENT_PATTERN.exec(value)

  if (!match) return undefined

  const candidate = match[1]?.trim()

  return candidate || undefined
}

const readCookie = (name: string): string | null => {
  if (typeof document === "undefined") return null

  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")

  const match = document.cookie.match(
    new RegExp(`(?:^|; )${escapedName}=([^;]*)`),
  )

  if (!match) return null

  try {
    return decodeURIComponent(match[1])
  } catch {
    return match[1]
  }
}

const resolveCsrfToken = (): string | null => {
  const fromEnv = import.meta.env.VITE_GARAD_CSRF_TOKEN?.trim()

  if (fromEnv) return fromEnv

  if (typeof document === "undefined") return null

  const fromMeta = document

    .querySelector('meta[name="csrf-token"]')

    ?.getAttribute("content")

    ?.trim()

  if (fromMeta) return fromMeta

  const fromCookie = readCookie("XSRF-TOKEN")?.trim()

  return fromCookie || null
}

type RequestOptions = {
  method?: HttpMethod

  query?: QueryParams

  body?: unknown

  timeoutMs?: number

  headers?: Record<string, string>

  signal?: AbortSignal
}

export type VideoInputSchema = {
  type: string
  name: string
  label?: string
  required?: boolean
  options?: {
    value: string | number
    label: string
  }[]
  default?: string | number | boolean | null
  tooltip?: string
  advanced?: boolean
  accept?: string
  multiple?: boolean
  min_files?: number
  max_files?: number
  max_size?: number
  min?: number
  max?: number
  step?: number
  placeholder?: string
}

export type VideoMode = "text_to_video" | "image_to_video" | "video_to_video"

export type VideoModelDescriptor = {
  id: string
  action: string
  subModel: string
  feature: string
  displayName: string
  family: string
  description: string
  engine: string | null
  modes: VideoMode[]
  inputs: VideoInputSchema[]
  enabled: boolean
  configured: boolean
  creditBalance: number | null
  unlimited: boolean
  creditBlocked: boolean
  availability: "available" | "disabled" | "not_configured" | "credit_blocked"
  locked?: boolean
  requiredPlan?: string | null
}

export type VideoJob = {
  id: number
  prompt: string
  model: string
  state: "queued" | "processing" | "completed" | "failed"
  rawStatus: string
  videoUrl: string | null
  error: string | null
  durationSeconds: number | null
  resolution: string | null
  aspectRatio: string | null
  referenceUrl: string | null
  createdAt: string | null
  providerStatus?: string
  queuePosition?: number | null
}

export type PaginationQuery = {
  page?: number

  perPage?: number

  search?: string
}

export type ConversationsQuery = PaginationQuery & {
  mode?: Conversation["mode"]

  archived?: boolean

  pinned?: boolean

  projectId?: string
}

export type MessagesQuery = PaginationQuery & {
  role?: Message["role"]
}

export type LibraryQuery = PaginationQuery & {
  kind?: LibraryItem["kind"] | "all"

  sort?: LibrarySort

  projectId?: string
}

export type AgentRunInput = {
  task: string

  projectId?: string

  attachmentIds?: string[]
}

export type AppsQuery = PaginationQuery & {
  installed?: boolean

  enabled?: boolean

  kind?: PluginApp["kind"]
}

export type NotificationsQuery = PaginationQuery & {
  unread?: boolean

  type?: NotificationItem["type"]
}

export type UploadFileResult = {
  id?: string

  status?: Attachment["status"]

  progress?: number

  previewUrl?: string

  error?: string
}

export type UploadFileInput = {
  file: File

  attachmentId: string

  signal?: AbortSignal

  onProgress?: (progress: number) => void
}

export type ImageGenerationInput = {
  model: ImageModelDescriptor
  prompt: string
  size: string
  quality: string
  count: number
  style: string
  lighting: string
  mood: string
  negativePrompt: string
  reference?: File
}

export type GeneratedImage = {
  id: string
  url: string
  prompt: string
  name: string
}

export type CreateMessageInput = Pick<Message, "role" | "content"> & Partial<Pick<Message, "id" | "status" | "attachmentIds" | "parentId" | "toolCalls" | "citations" | "artifacts">>

export type UpdateMessageInput = Partial<Pick<Message, "content" | "status" | "attachmentIds" | "parentId" | "toolCalls" | "citations" | "artifacts" | "error" | "feedback" | "feedbackReason" | "feedbackDetails">>

export interface ApiClient {
  readonly baseUrl: string | null

  readonly configured: boolean

  readonly usesLegacyChatBackend: boolean

  readonly legacyUserId: string | undefined

  chatStream(
    request: ChatRequest,
    signal: AbortSignal,
  ): AsyncIterable<StreamEvent>

  searchSmartImages(
    query: string,
    messageId?: string,
  ): Promise<SmartImageResult[]>

  getEntityDetails(
    entity: EntityHighlight,
    messageId: string,
  ): Promise<EntityDetail>

  getEntityImage(
    query: string,
    entityText: string,
    messageId: string,
  ): Promise<{
    image_url: string | null
    image_source: string
    image_domain: string
    image_link: string
  }>

  getVoiceAccess(reasoningModelId: string): Promise<{
    available: boolean
    reason: "plan" | "credits" | "model" | null
    message: string
    reasoningModelId: string
    reasoningModelName: string
    reasoningProvider: string
    transcriptionModel: string
  }>

  createRealtimeVoiceSession(signal?: AbortSignal): Promise<{
    ephemeralKey: string
    model: string
  }>

  checkRealtimeVoiceBalance(signal?: AbortSignal): Promise<{
    available: boolean
    message: string
  }>

  transcribeAudio(
    file: File,
    signal?: AbortSignal,
    reasoningModelId?: string,
  ): Promise<string>

  generateVoiceAudio(
    text: string,
    signal?: AbortSignal,
    reasoningModelId?: string,
  ): Promise<string>

  getAudioHistory(): Promise<AudioStudioItem[]>

  getStudioMusicModels(): Promise<AudioMusicModel[]>

  getStudioVideoEditorUrl(): string | null

  generateStudioSpeech(input: {
    content: string
    voice: string
    model: "tts-1" | "tts-1-hd"
    language: string
    title: string
    kind: "voiceover" | "speech"
  }): Promise<AudioStudioItem>

  getStudioVoiceOptions(): Promise<{
    provider: string
    voices: Array<{
      id: string
      name: string
    }>
  }>

  generateStudioMusic(input: {
    prompt: string
    title: string
    model: string
    style?: string
    duration?: number
    lyrics?: string
    instrumental: boolean
    outputFormat: "mp3" | "wav"
    images: File[]
    video?: File | null
  }): Promise<AudioStudioItem>

  deleteStudioMusic(
    id: number,
    source?: AudioStudioItem["source"],
  ): Promise<void>

  uploadFile(input: UploadFileInput): Promise<UploadFileResult>

  getConversations(query?: ConversationsQuery): Promise<Conversation[]>

  getConversation(id: string): Promise<Conversation>

  createConversation(payload: Partial<Conversation>): Promise<Conversation>

  updateConversation(
    id: string,
    patch: Partial<Conversation>,
  ): Promise<Conversation>

  deleteConversation(id: string): Promise<void>

  getConversationMessages(
    conversationId: string,

    query?: MessagesQuery,
  ): Promise<Message[]>

  getMessage(id: string): Promise<Message>

  createMessage(
    conversationId: string,
    payload: CreateMessageInput,
  ): Promise<Message>

  updateMessage(id: string, patch: UpdateMessageInput): Promise<Message>

  deleteMessage(id: string): Promise<void>

  getProjects(query?: PaginationQuery): Promise<Project[]>

  getProject(id: string): Promise<Project>

  createProject(payload: Partial<Project>): Promise<Project>

  updateProject(id: string, patch: Partial<Project>): Promise<Project>

  deleteProject(id: string): Promise<void>

  getLibrary(query?: LibraryQuery): Promise<LibraryItem[]>

  getLibraryItem(id: string): Promise<LibraryItem>

  updateLibraryItem(
    id: string,
    patch: Partial<LibraryItem>,
  ): Promise<LibraryItem>

  deleteLibraryItem(id: string): Promise<void>

  getAgents(query?: PaginationQuery): Promise<Agent[]>

  getAgent(id: string): Promise<Agent>

  createAgent(payload: Partial<Agent>): Promise<Agent>

  updateAgent(id: string, patch: Partial<Agent>): Promise<Agent>

  deleteAgent(id: string): Promise<void>

  getAgentRuns(query?: PaginationQuery): Promise<AgentRun[]>

  getAgentRunsForAgent(
    agentId: string,
    query?: PaginationQuery,
  ): Promise<AgentRun[]>

  getAgentRun(id: string): Promise<AgentRun>

  runAgent(id: string, payload: AgentRunInput): Promise<AgentRun>

  stopAgent(id: string, runId?: string): Promise<AgentRun>

  createWorkflowFromPrompt(prompt: string): Promise<AgentWorkflow>

  getAgentWorkflows(): Promise<AgentWorkflow[]>

  getWorkflowRuns(): Promise<AgentWorkflowRun[]>

  getAgentConversationActivity(chatId: string): Promise<AgentWorkflowRun[]>

  cancelWorkflowRun(runId: string): Promise<AgentWorkflowRun>

  getWorkflowApprovals(): Promise<AgentWorkflowApproval[]>

  runWorkflow(id: string, idempotencyKey?: string): Promise<AgentWorkflowRun>

  toggleWorkflowStatus(id: string): Promise<"active" | "paused">

  decideWorkflowApproval(
    id: string,
    decision: "approve" | "reject",
    comment?: string,
  ): Promise<AgentWorkflowRun>

  getApps(query?: AppsQuery): Promise<PluginApp[]>

  getApp(id: string): Promise<PluginApp>

  installApp(id: string): Promise<PluginApp>

  updateApp(id: string, patch: Partial<PluginApp>): Promise<PluginApp>

  uninstallApp(id: string): Promise<void>

  getConnectors(query?: PaginationQuery): Promise<Connector[]>

  getConnector(id: string): Promise<Connector>

  connectConnector(id: string): Promise<Connector>

  disconnectConnector(id: string): Promise<void>

  toggleConnectorPause(id: string): Promise<Connector>

  getConnectorRedirectUrl(id: string): Promise<string>

  getConnectorAccess(id: string): Promise<ConnectorAccess>

  updateConnectorAccess(
    id: string,
    selection: string[],
    recentOnly: boolean,
  ): Promise<void>

  getScheduledTasks(query?: PaginationQuery): Promise<ScheduledTask[]>

  getScheduledTask(id: string): Promise<ScheduledTask>

  createScheduledTask(payload: Partial<ScheduledTask>): Promise<ScheduledTask>

  updateScheduledTask(
    id: string,
    patch: Partial<ScheduledTask>,
  ): Promise<ScheduledTask>

  deleteScheduledTask(id: string): Promise<void>

  runScheduledTask(id: string): Promise<ScheduledTask>
  tickScheduledTasks(): Promise<{
    delivered: number
    started: boolean
  }>

  getMemories(query?: PaginationQuery): Promise<MemoryItem[]>

  getMemory(id: string): Promise<MemoryItem>

  createMemory(payload: Partial<MemoryItem>): Promise<MemoryItem>

  updateMemory(id: string, patch: Partial<MemoryItem>): Promise<MemoryItem>

  deleteMemory(id: string): Promise<void>

  getNotifications(query?: NotificationsQuery): Promise<NotificationItem[]>

  markNotificationRead(id: string): Promise<NotificationItem>

  markAllNotificationsRead(): Promise<void>

  deleteNotification(id: string): Promise<void>

  searchMentionUsers(query: string): Promise<MentionCandidate[]>

  sendMention(userId: string, message: string): Promise<NotificationItem>

  searchAgentShareRecipients(
    query: string,
    resourceType: AgentShareResourceType,
    resourceId: string,
    resourcePart?: "user" | "assistant",
  ): Promise<AgentShareRecipient[]>

  createAgentShare(input: {
    resourceType: AgentShareResourceType
    resourceId: string
    resourcePart?: "user" | "assistant"
    recipientId: string
    accessLevel: "view"
  }): Promise<AgentShare>

  getAgentShares(input: {
    resourceType: AgentShareResourceType
    resourceId: string
    resourcePart?: "user" | "assistant"
  }): Promise<AgentShare[]>

  revokeAgentShare(shareId: string): Promise<void>

  getSharedAgentResource(token: string): Promise<SharedAgentResource>

  createCollaborationRequest(
    userId: string,
    message: string,
  ): Promise<{ requestId: string; status: "pending"; notificationId: string }>

  decideCollaborationRequest(
    requestId: string,
    decision: "accepted" | "declined",
  ): Promise<{ requestId: string; status: "accepted" | "declined"; decidedAt: string | null }>

  decideTeamWorkspaceInvitation(
    invitationId: string,
    decision: "accepted" | "declined",
  ): Promise<{ invitationId: string; workspaceId: string; status: "accepted" | "declined" }>

  getTeamWorkspaces(): Promise<TeamWorkspace[]>

  createTeamWorkspace(name: string): Promise<TeamWorkspace>

  inviteTeamWorkspaceMember(
    teamId: string,
    payload: { email: string; role: string },
  ): Promise<TeamWorkspaceMember>

  updateTeamWorkspaceMember(
    teamId: string,
    memberId: string,
    payload: { role: string; status?: "active" | "waiting" },
  ): Promise<TeamWorkspaceMember>

  removeTeamWorkspaceMember(teamId: string, memberId: string): Promise<void>

  getAccount(): Promise<Account>

  logout(): Promise<void>

  login(payload: {
    email: string
    password: string
    remember: boolean
    redirect: "chatPro"
  }): Promise<unknown>

  register(payload: {
    name: string
    surname?: string
    phone?: string
    country?: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<unknown>

  sendPasswordReset(email: string): Promise<unknown>

  verifyEmailOtp(otp: string[]): Promise<unknown>

  verifyTwoFactor(code: string): Promise<unknown>

  getGuestChatStatus(): Promise<{
    limit: number
    used: number
    remaining: number
  }>

  getAuthRequirements(): Promise<{ registrationRequiredFields: string[] }>

  updateAccount(patch: Partial<Account>): Promise<Account>

  updateProfile(input: ProfileUpdateInput): Promise<User>

  getPublicProfile(username: string): Promise<PublicProfile>

  toggleProfileFollow(
    userId: string,
    follow: boolean,
  ): Promise<{ following: boolean; followersCount: number }>

  updatePreferences(patch: Partial<Preferences>): Promise<Preferences>

  getUsage(): Promise<Usage>

  getComposerCapabilities(): Promise<ComposerCapabilities>

  getComposerPrompts(): Promise<ComposerPrompt[]>

  getComposerBrandProducts(companyId: string): Promise<BrandProduct[]>

  getComposerSkills(search?: string): Promise<ComposerSkill[]>

  addComposerSkill(id: string): Promise<void>

  saveComposerCanvas(messageId: string, content: string): Promise<void>

  exportDocument(
    format: "word" | "pdf" | "xlsx" | "md" | "txt",
    input: {
      title?: string
      content: string
      conversationId?: string
    },
  ): Promise<Artifact>

  startPresentation(input: {
    content: string
    title?: string
    slideCount?: number
  }): Promise<{ generationId: string }>
  pollPresentation(generationId: string): Promise<{
    state: "processing" | "ready" | "failed"
    transient?: boolean
    artifact?: Artifact
    message?: string
  }>
  getPresentationStatus(generationId: string): Promise<{
    state: "processing" | "ready" | "failed"
    url?: string
    message: string
  }>
  getVideoStatus(videoId: string): Promise<{
    state: "processing" | "ready" | "failed"
    url?: string
    message: string
  }>

  getPlans(): Promise<Plan[]>

  getSubscription(): Promise<Subscription>

  changeSubscription(planId: string): Promise<Subscription>

  cancelSubscription(): Promise<Subscription>
  requestEnterprisePlan(): Promise<unknown>
  submitSalesInquiry(input: SalesInquiry): Promise<unknown>
  getPaymentMethods(): Promise<PaymentMethod[]>
  startCheckout(input: CheckoutInput): Promise<CheckoutResult>

  resumeSubscription(): Promise<Subscription>

  getModels(): Promise<ModelDescriptor[]>

  getImageModels(): Promise<ImageModelDescriptor[]>

  generateImage(input: ImageGenerationInput): Promise<GeneratedImage[]>

  imageProxyUrl(url: string, download?: boolean): string

  getVideoModels(): Promise<VideoModelDescriptor[]>

  getVideoHistory(): Promise<VideoJob[]>

  getVideoJob(id: number): Promise<VideoJob>

  createVideo(form: FormData): Promise<{
    videoId: number
    message: string
  }>

  deleteVideos(ids: number[]): Promise<void>

  videoFileUrl(id: number): string

  socialRequest<T>(
    method: "GET" | "POST" | "PUT" | "DELETE",
    path: string,
    body?: unknown,
    signal?: AbortSignal,
  ): Promise<T>

  socialUpload(kind: "image" | "video", file: File): Promise<string>
}

export type AudioStudioItem = {
  id: string
  recordId?: number
  kind: "song" | "voiceover" | "speech"
  source?: "video-editor" | "legacy-music" | "legacy-tts"
  title: string
  audioUrl: string
  createdAt: string | null
  text?: string
}

export type AudioMusicModel = {
  id: string
  name: string
  description: string
  provider: string
  supportsDuration: boolean
  supportsLyrics: boolean
  supportsImages: boolean
  supportsVideo: boolean
  outputFormats: Array<"mp3" | "wav">
}

const normalizeBaseUrl = (value: string | undefined): string | null => {
  const trimmed = value?.trim()

  if (!trimmed) return null

  if (trimmed.startsWith("/") && typeof window !== "undefined")
    return new URL(trimmed, window.location.origin)
      .toString()
      .replace(/\/$/, "")

  if (typeof window !== "undefined") {
    const url = new URL(trimmed)
    if (url.host === window.location.host)
      url.protocol = window.location.protocol
    return url.toString().replace(/\/$/, "")
  }

  return trimmed.replace(/\/$/, "")
}

const resolveAudioUrl = (baseUrl: string, path: string): string =>
  /^https?:\/\//i.test(path)
    ? path
    : new URL(
        path.replace(/^\/+/, ""),
        `${baseUrl.replace(/\/$/, "")}/`,
      ).toString()

const resolveRuntimeBaseUrl = (): string | undefined => {
  if (typeof window === "undefined") return undefined

  const candidate = window.__GARAD_API_BASE_URL

  if (typeof candidate !== "string") return undefined

  const trimmed = candidate.trim()

  return trimmed || undefined
}

const buildUrl = (
  baseUrl: string,
  path: string,
  query?: QueryParams,
): string => {
  const url = new URL(`${baseUrl}${path}`)

  if (query)
    for (const [key, value] of Object.entries(query))
      if (value !== undefined && value !== null && value !== "")
        url.searchParams.set(key, String(value))

  return url.toString()
}

const paginationToQuery = (query?: PaginationQuery): QueryParams => ({
  page: query?.page,

  per_page: query?.perPage,

  search: query?.search,
})

const toPlainObject = (value: unknown): Record<string, unknown> | null =>
  value && typeof value === "object" && !Array.isArray(value)
    ? value as Record<string, unknown>
    : null

const parseResponseBody = async (response: Response): Promise<unknown> => {
  if (response.status === 204) return undefined

  const text = await response.text()

  if (!text) return undefined

  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

const unwrapEnvelope = <T>(payload: unknown): T => {
  const object = toPlainObject(payload)

  if (object && "data" in object) return object.data as T

  return payload as T
}

const unwrapListEnvelope = <T>(payload: unknown): T[] => {
  const data = unwrapEnvelope<unknown>(payload)

  return Array.isArray(data) ? data as T[] : []
}

const readErrorMessage = (payload: unknown, status: number): string => {
  const safeText = (message: string) =>
    looksLikeHtmlDocument(message)
      ? status >= 500
        ? "Server error. Please try again."
        : `Request failed (${status}). Please try again.`
      : message

  if (typeof payload === "string" && payload.trim()) {
    return safeText(payload)
  }

  const object = toPlainObject(payload)

  if (object) {
    const message = object.message

    if (typeof message === "string" && message.trim()) return safeText(message)

    const error = object.error

    if (typeof error === "string" && error.trim()) return safeText(error)

    const errors = object.errors
    if (Array.isArray(errors)) {
      const firstError = errors.find(
        (item): item is string =>
          typeof item === "string" && item.trim() !== "",
      )
      if (firstError) return safeText(firstError)
    }
  }

  if (status === 401) return "Session expired. Please sign in again."

  if (status === 403)
    return "You do not have permission to perform this action."

  if (status === 429) return "Request limit reached. Please try again shortly."

  if (status >= 500) return "Server error. Please try again."

  return `Request failed (${status}).`
}

const requestJson = async <T,>(
  baseUrl: string | null,

  path: string,

  options: RequestOptions = {},
): Promise<T> => {
  if (!baseUrl)
    throw new ApiError(
      "VITE_GARAD_API_URL is not configured.",

      0,

      "api_not_configured",
    )

  const headers: Record<string, string> = {
    Accept: "application/json",

    "X-Requested-With": "XMLHttpRequest",

    ...options.headers,
  }

  const method = options.method || "GET"

  if (method !== "GET") {
    const csrfToken = resolveCsrfToken()

    if (csrfToken && !headers["X-CSRF-TOKEN"] && !headers["X-XSRF-TOKEN"]) {
      headers["X-CSRF-TOKEN"] = csrfToken

      headers["X-XSRF-TOKEN"] = csrfToken
    }
  }

  let body: BodyInit | undefined

  if (options.body !== undefined) {
    if (typeof FormData !== "undefined" && options.body instanceof FormData) {
      body = options.body
    } else if (typeof options.body === "string") {
      headers["Content-Type"] = headers["Content-Type"] || "text/plain"

      body = options.body
    } else {
      headers["Content-Type"] = "application/json"

      body = JSON.stringify(options.body)
    }
  }

  const timeoutController =
    options.signal || (options.timeoutMs ?? DEFAULT_REQUEST_TIMEOUT_MS) <= 0
      ? null
      : new AbortController()

  const timeoutId =
    timeoutController === null
      ? null
      : window.setTimeout(() => {
          timeoutController.abort("request-timeout")
        }, options.timeoutMs ?? DEFAULT_REQUEST_TIMEOUT_MS)

  let response: Response

  try {
    response = await fetch(buildUrl(baseUrl, path, options.query), {
      method,

      headers,

      body,

      credentials: "include",

      signal: options.signal || timeoutController?.signal,
    })
  } catch (error) {
    if (
      timeoutController &&
      error instanceof DOMException &&
      error.name === "AbortError"
    ) {
      throw new ApiError(
        "Request timed out. Please try again.",
        504,
        "request_timeout",
      )
    }

    throw error
  } finally {
    if (timeoutId !== null) {
      window.clearTimeout(timeoutId)
    }
  }

  if (response.redirected && /\/(login|signin|auth)\b/i.test(response.url)) {
    throw new ApiError(
      "Session expired. Please sign in again.",
      401,
      "session_expired",
    )
  }

  const payload = await parseResponseBody(response)

  if (
    response.ok &&
    typeof payload === "string" &&
    looksLikeHtmlDocument(payload)
  ) {
    throw new ApiError(
      "Session expired. Please sign in again.",
      401,
      "session_expired",
    )
  }

  if (!response.ok) {
    const object = toPlainObject(payload)

    const code =
      object && typeof object.code === "string" ? object.code : undefined

    throw new ApiError(
      readErrorMessage(payload, response.status),

      response.status,

      code,

      payload,
    )
  }

  return payload as T
}

const requestWithFallback = async <T,>(
  baseUrl: string | null,

  paths: string[],

  options: RequestOptions = {},
): Promise<T> => {
  let lastError: unknown

  for (const path of paths)
    try {
      return await requestJson<T>(baseUrl, path, options)
    } catch (error) {
      if (
        error instanceof ApiError &&
        (error.status === 404 || error.status === 405)
      ) {
        lastError = error

        continue
      }

      throw error
    }

  if (lastError instanceof Error) throw lastError

  throw new ApiError("Request failed.", 0)
}

const parseStreamEvent = (payload: unknown): StreamEvent | null => {
  const object = toPlainObject(payload)

  if (!object) return null

  const type = object.type

  if (typeof type !== "string") return null

  if (type === "data") {
    const text = object.text

    if (typeof text === "string") return { type: "delta", text }

    return null
  }

  if (type === "stop") return { type: "done" }

  if (type === "status") {
    const status = object.status

    if (typeof status === "string")
      return { type: "status", status } as StreamEvent

    return null
  }

  if (type === "delta") {
    const text = object.text

    if (typeof text === "string") return { type: "delta", text }

    return null
  }

  if (type === "tool") {
    const tool = object.tool

    if (tool && typeof tool === "object")
      return { type: "tool", tool } as StreamEvent

    return null
  }

  if (type === "citation") {
    const citation = object.citation

    if (citation && typeof citation === "object")
      return { type: "citation", citation } as StreamEvent

    return null
  }

  if (type === "artifact") {
    const artifact = object.artifact

    if (artifact && typeof artifact === "object")
      return { type: "artifact", artifact } as StreamEvent

    return null
  }

  if (type === "done") {
    const usage = toPlainObject(object.usage) as Partial<Usage> | null

    return usage ? { type: "done", usage } : { type: "done" }
  }

  if (type === "error") {
    const message =
      typeof object.message === "string" ? object.message : "Streaming error"

    const code = typeof object.code === "string" ? object.code : undefined

    const status = typeof object.status === "number" ? object.status : undefined

    return { type: "error", message, code, status }
  }

  return null
}

const parseLegacySseEvent = (
  eventType: string | undefined,

  payload: unknown,
): StreamEvent | null => {
  if (!eventType) return null

  if (eventType === "stop") return { type: "done" }

  if (eventType === "message") {
    const messageId =
      typeof payload === "string"
        ? payload.trim()
        : typeof payload === "number"
          ? payload
          : toPlainObject(payload)?.id
    if (typeof messageId === "string" || typeof messageId === "number") {
      return { type: "legacyMessage", messageId: String(messageId) }
    }
    return null
  }

  if (eventType === "chat_not_found") {
    return {
      type: "error",

      message: "Conversation was not found on the backend.",

      code: "chat_not_found",

      status: 404,
    }
  }

  if (eventType === "smart_image_search") {
    const object = toPlainObject(payload)
    const query = object?.query
    if (typeof query !== "string" || !query.trim()) return null
    const messageId = object?.message_id

    return {
      type: "imageSearch",
      query,
      messageId:
        typeof messageId === "string" || typeof messageId === "number"
          ? String(messageId)
          : undefined,
    }
  }

  if (eventType === "entity_highlights" && Array.isArray(payload)) {
    const entities: EntityHighlight[] = []
    for (const item of payload as unknown[]) {
      const entity = toPlainObject(item)
      if (
        typeof entity?.text === "string" &&
        typeof entity.type === "string" &&
        typeof entity.confidence === "number" &&
        typeof entity.occurrence_index === "number" &&
        typeof entity.context_snippet === "string"
      ) {
        entities.push({
          text: entity.text,
          type: entity.type,
          confidence: entity.confidence,
          occurrence_index: entity.occurrence_index,
          context_snippet: entity.context_snippet,
        })
      }
    }

    return { type: "entityHighlights", entities }
  }

  if (eventType === "clear_content") {
    return { type: "delta", text: "" }
  }

  if (eventType === "progress") {
    const message =
      typeof payload === "string" ? payload : toPlainObject(payload)?.message
    return typeof message === "string" && message.trim()
      ? { type: "progress", message: message.trim() }
      : null
  }

  if (eventType === "data" || eventType === "delta") {
    const text = typeof payload === "string" ? payload : ""

    return text ? { type: "delta", text } : null
  }

  if (eventType === "function_call") {
    const toolName =
      typeof payload === "string" ? payload : toPlainObject(payload)?.name

    if (typeof toolName !== "string" || !toolName.trim()) return null

    return {
      type: "tool",

      tool: {
        id: `tool-${toolName}`,

        name: toolName,

        label: toolName,

        status: "running",
      },
    }
  }

  if (eventType === "tool_status") {
    const object = toPlainObject(payload)

    if (!object) return null

    const toolName =
      typeof object.tool === "string" && object.tool.trim()
        ? object.tool.trim()
        : "tool"

    const label =
      typeof object.label === "string" && object.label.trim()
        ? object.label
        : toolName

    return {
      type: "tool",

      tool: {
        id: `tool-${toolName}`,

        name: toolName,

        label,

        status: mapLegacyToolState(object.state),
        ...(typeof object.approvalId === "string"
          ? { approvalId: object.approvalId }
          : {}),
      },
    }
  }

  if (eventType === "error") {
    const object = toPlainObject(payload)

    if (object) {
      const message =
        typeof object.message === "string" && object.message.trim()
          ? object.message
          : "Streaming error"

      const code = typeof object.code === "string" ? object.code : undefined

      const status =
        typeof object.status === "number" ? object.status : undefined

      return { type: "error", message, code, status }
    }

    if (typeof payload === "string" && payload.trim()) {
      return { type: "error", message: payload }
    }
  }

  return null
}

const parseSseFrame = (frame: string): StreamEvent | null => {
  let eventType: string | undefined

  const dataLines: string[] = []

  for (const line of frame.split("\n")) {
    if (line.startsWith("event:")) {
      eventType = line.slice(6).trim() || undefined

      continue
    }

    if (line.startsWith("data:")) {
      const value = line.slice(5)

      dataLines.push(value.startsWith(" ") ? value.slice(1) : value)
    }
  }

  if (dataLines.length === 0) return null

  const payload = dataLines.join("\n")

  if (!payload || payload === "[DONE]") return { type: "done" }

  if (eventType === "stop") return { type: "done" }

  let parsedPayload: unknown = payload

  try {
    parsedPayload = (JSON.parse(payload) as unknown)
  } catch {
    parsedPayload = payload
  }

  // Bare numbers/booleans/null are text chunks (e.g. "425"), not JSON events.
  if (parsedPayload === null || typeof parsedPayload !== "object") {
    parsedPayload = payload
  }

  if (eventType) {
    const legacyEvent = parseLegacySseEvent(eventType, parsedPayload)

    if (legacyEvent) return legacyEvent
  }

  try {
    const object = toPlainObject(parsedPayload)

    if (object && !("type" in object) && eventType)
      return parseStreamEvent({ ...object, type: eventType })

    const parsed = parseStreamEvent(parsedPayload)

    if (parsed) return parsed
  } catch {
    // Fall through to plain-text delta handling.
  }

  if (typeof parsedPayload === "string" && parsedPayload.length > 0) {
    return { type: "delta", text: parsedPayload }
  }

  return null
}

export const removeLegacyResponsePreferences = (content: string): string => {
  const marker = "\n\nPlease follow these response preferences:\n"
  const markerIndex = content.lastIndexOf(marker)
  if (markerIndex < 0) return content

  const generatedPreferences = content.slice(markerIndex + marker.length)
  const preferenceLines = generatedPreferences.split("\n")
  if (
    !/^- Reply in (?:English|Somali)\.$/.test(preferenceLines[0] || "") ||
    preferenceLines.some((line) => line.length === 0 || !line.startsWith("- "))
  ) {
    return content
  }

  return content.slice(0, markerIndex)
}

const createApiClientFromBaseUrl = (baseUrl: string | null): ApiClient => {
  const configured = Boolean(baseUrl)

  const legacyConversationIds = new Map<string, string>()

  const pendingLegacyConversationCreations = new Map<string, Promise<string>>()

  let usesLegacyChatBackend = false

  let legacyUserId: string | undefined

  let detectedLegacyCategoryId: string | null = null

  const legacyAgentCache = new Map<string, Agent>()

  const legacyMemoryCache = new Map<string, MemoryItem>()

  const legacyConnectorMetaByKey = new Map<string, {
    connectorId?: string
    preConsentUrl?: string
  }>()

  const resolveLegacyChatbotFrontModel = (
    requestedModel: unknown,
  ): string | undefined => {
    if (typeof requestedModel !== "string") return undefined
    const normalized = requestedModel.trim()
    return normalized || undefined
  }

  const ensureConfigured = (): string => {
    if (!baseUrl)
      throw new ApiError(
        "VITE_GARAD_API_URL is not configured.",

        0,

        "api_not_configured",
      )

    return baseUrl
  }

  const emptyUsage = (): Usage => ({
    messages: { used: 0, limit: 0 },

    agentRuns: { used: 0, limit: 0 },

    storageBytes: { used: 0, limit: 0 },
  })

  const createFallbackAccount = (): Account => ({
    user: null,

    subscription: null,

    authStatus: "unauthenticated",

    usage: emptyUsage(),

    preferences: {
      theme: "light",

      language: "English",

      defaultMode: "agents",

      defaultModel: "gpt-4o-mini",

      reducedMotion: false,

      responseTraits: [],

      customInstructions: "",

      notifications: {
        tasks: true,

        artifacts: true,

        tips: true,
      },
    },

    memoryEnabled: true,

    memories: [],
  })

  const asAbsoluteUrl = (value: string): string => {
    if (!value.trim()) return value

    if (/^https?:\/\//i.test(value)) return value

    if (value.startsWith("//")) return `https:${value}`

    if (!baseUrl) return value

    return `${baseUrl}${value.startsWith("/") ? "" : "/"}${value}`
  }

  const mapLegacyConversation = (raw: unknown): Conversation => {
    const object = toPlainObject(raw) || {}

    const id = coerceStringId(object.id)

    legacyConversationIds.set(id, id)

    return {
      id,

      title:
        typeof object.title === "string" && object.title.trim()
          ? object.title
          : `Conversation ${id}`,

      pinned: parseBooleanLike(object.is_pinned),

      archived: false,

      shared: false,

      projectId:
        typeof object.folder_id === "string" ||
        typeof object.folder_id === "number"
          ? String(object.folder_id)
          : undefined,

      lastMessageAt:
        typeof object.last_message_at === "string"
          ? object.last_message_at
          : undefined,

      mode:
        object.mode === "agents" || parseBooleanLike(object.agent_mode)
          ? "agents"
          : "chat",

      createdAt: toIsoString(object.created_at),

      updatedAt: toIsoString(object.updated_at),

      messages: [],
    }
  }

  const mapLegacyArtifact = (
    raw: unknown,
    fallbackCreatedAt: string,
  ): Artifact | null => {
    const object = toPlainObject(raw)
    if (!object) return null

    const kind = object.kind
    if (
      kind !== "pdf" &&
      kind !== "document" &&
      kind !== "spreadsheet" &&
      kind !== "presentation" &&
      kind !== "generated" &&
      kind !== "image" &&
      kind !== "video"
    ) {
      return null
    }

    const id = coerceStringId(object.id)
    const name = typeof object.name === "string" ? object.name : ""
    if (!id || !name) return null

    return {
      id,
      name,
      kind,
      description:
        typeof object.description === "string" ? object.description : "",
      status:
        object.status === "creating" ||
        object.status === "failed" ||
        object.status === "ready"
          ? object.status
          : "ready",
      generationId:
        typeof object.generationId === "string"
          ? object.generationId
          : undefined,
      progressMessage:
        typeof object.progressMessage === "string"
          ? object.progressMessage
          : undefined,
      url: typeof object.url === "string" ? object.url : undefined,
      mimeType:
        typeof object.mimeType === "string" ? object.mimeType : undefined,
      extension:
        typeof object.extension === "string" ? object.extension : undefined,
      size:
        typeof object.size === "number" && Number.isFinite(object.size)
          ? object.size
          : undefined,
      downloadUrl:
        typeof object.downloadUrl === "string" ? object.downloadUrl : undefined,
      previewUrl:
        typeof object.previewUrl === "string" || object.previewUrl === null
          ? object.previewUrl
          : undefined,
      slideCount:
        typeof object.slideCount === "number" || object.slideCount === null
          ? object.slideCount
          : undefined,
      createdAt:
        typeof object.createdAt === "string"
          ? object.createdAt
          : fallbackCreatedAt,
      updatedAt:
        typeof object.updatedAt === "string" ? object.updatedAt : undefined,
    }
  }

  const mapLegacyMessageRows = (rows: unknown): Message[] => {
    if (!Array.isArray(rows)) return []

    const ordered = rows

      .map((item) => toPlainObject(item))

      .filter((item): item is Record<string, unknown> => Boolean(item))

      .reverse()

    const messages: Message[] = []

    for (const row of ordered) {
      const rowId = coerceStringId(row.id)

      const createdAt = toIsoString(row.created_at || row.createdAt)

      const updatedAt = toIsoString(
        row.updated_at || row.updatedAt || createdAt,
      )

      const userInput =
        typeof row.input === "string"
          ? removeLegacyResponsePreferences(row.input)
          : ""

      const attachmentReferences: Array<{
        path: string
        name: string
        mimeType: string
      }> = []
      const appendReferences = (paths: unknown, names: unknown) => {
        const pathList = (
          Array.isArray(paths) ? paths : String(paths || "").split(",")
        )
          .filter((value): value is string => typeof value === "string")
          .map((value) => value.trim())
          .filter(Boolean)
        const nameList = (
          Array.isArray(names) ? names : String(names || "").split(",")
        )
          .filter((value): value is string => typeof value === "string")
          .map((value) => value.trim())

        pathList.forEach((path, index) => {
          const query = path.split("?", 2)[1]?.split("#", 1)[0] || ""
          const referenceName = new URLSearchParams(query).get("name")
          const fallbackName =
            referenceName ||
            path.split(/[\\/]/).pop()?.split(/[?#]/)[0] ||
            "Attachment"
          const name = nameList[index] || fallbackName
          const extension = name.split(".").pop()?.toLowerCase() || ""
          const mimeType =
            /^https?:\/\//i.test(path) &&
            /\.(?:png|jpe?g|gif|webp|bmp)(?:[?#]|$)/i.test(path)
              ? "image/*"
              : extension === "pdf"
                ? "application/pdf"
                : ["png", "jpg", "jpeg", "gif", "webp", "bmp"].includes(
                      extension,
                    )
                  ? `image/${extension === "jpg" ? "jpeg" : extension}`
                  : ["mp4", "mov", "webm", "m4v", "avi"].includes(extension)
                    ? `video/${extension === "mov" ? "quicktime" : extension}`
                    : "application/octet-stream"

          attachmentReferences.push({ path, name, mimeType })
        })
      }

      appendReferences(row.pdfPath ?? row.pdfpath, row.pdfName ?? row.pdfname)
      appendReferences(row.images, undefined)

      const assistantOutput =
        typeof row.response === "string" && row.response.trim()
          ? row.response
          : typeof row.output === "string"
            ? row.output
            : ""

      if (userInput.trim() || attachmentReferences.length > 0) {
        const attachments: Attachment[] = attachmentReferences.map(
          (reference, index) => {
            const id = `legacy-${rowId}-attachment-${index}`
            const extension =
              reference.name.split(".").pop()?.toLowerCase() || ""
            const kind: Attachment["kind"] = reference.mimeType.startsWith(
              "image/",
            )
              ? "image"
              : reference.mimeType.startsWith("video/")
                ? "video"
                : reference.mimeType === "application/pdf"
                  ? "pdf"
                  : ["xls", "xlsx", "csv"].includes(extension)
                    ? "spreadsheet"
                    : ["ppt", "pptx"].includes(extension)
                      ? "presentation"
                      : "document"
            const createdAt = toIsoString(row.created_at || row.createdAt)
            uploadedAssetsByAttachmentId.set(id, {
              paths: [reference.path],
              names: [reference.name],
              mimeType: reference.mimeType,
              originalName: reference.name,
            })

            return {
              id,
              name: reference.name,
              mimeType: reference.mimeType,
              size: 0,
              kind,
              status: "ready",
              progress: 100,
              previewUrl: asAbsoluteUrl(reference.path),
              createdAt,
              updatedAt: toIsoString(
                row.updated_at || row.updatedAt || createdAt,
              ),
            }
          },
        )

        messages.push({
          id: `${rowId}-user`,

          role: "user",

          content: userInput,

          status: "completed",

          createdAt,

          updatedAt,

          attachmentIds: attachments.map((attachment) => attachment.id),

          attachments,

          backendMessageId: rowId,

          citations: [],

          artifacts: [],

          toolCalls: [],
        })
      }

      if (assistantOutput.trim() && assistantOutput !== "First Initiation") {
        const artifacts = Array.isArray(row.artifacts)
          ? row.artifacts
              .map((artifact) => mapLegacyArtifact(artifact, createdAt))
              .filter((artifact): artifact is Artifact => artifact !== null)
          : []

        messages.push({
          id: `${rowId}-assistant`,

          role: "assistant",

          content: assistantOutput,

          status: "completed",

          createdAt,

          updatedAt,

          attachmentIds: [],

          backendMessageId: rowId,

          citations: [],

          artifacts,

          toolCalls: [],
        })
      }
    }

    return messages
  }

  const attachAgentMentionReferences = async (
    messages: Message[],
  ): Promise<Message[]> => {
    if (
      typeof window === "undefined" ||
      !/\/agents\/?$/.test(window.location.pathname)
    ) {
      return messages
    }

    const messageIds = Array.from(
      new Set(
        messages
          .filter((message) => message.role === "user")
          .map((message) => message.backendMessageId)
          .filter((id): id is string => Boolean(id && looksNumericId(id))),
      ),
    )
    if (messageIds.length === 0) return messages

    const response = await requestJson<{
      data: Record<string, MessageMention[]>
    }>(
      ensureConfigured(),
      `/api/agent-mentions/messages?message_ids=${encodeURIComponent(messageIds.join(","))}`,
      { timeoutMs: LEGACY_HISTORY_TIMEOUT_MS },
    )

    return messages.map((message) => {
      if (message.role !== "user" || !message.backendMessageId) return message
      const mentions = response.data[message.backendMessageId]
      return mentions ? { ...message, mentions } : message
    })
  }

  const mapLegacyProject = (raw: unknown): Project => {
    const object = toPlainObject(raw) || {}
    const rawId = object.id
    if (
      !(
        (typeof rawId === "number" && Number.isFinite(rawId)) ||
        (typeof rawId === "string" && /^\d+$/.test(rawId))
      )
    ) {
      throw new ApiError(
        "Garad returned a project without a valid folder ID.",
        502,
        "invalid_project_id",
        raw,
      )
    }

    return {
      id: String(rawId),

      name:
        typeof object.name === "string" && object.name.trim()
          ? object.name
          : "Untitled project",

      description:
        typeof object.description === "string" ? object.description : "",

      instructions:
        typeof object.instructions === "string" ? object.instructions : "",

      memoryEnabled: true,

      memory: "",

      agentIds: [],

      sourceIds: [],

      createdAt: toIsoString(object.created_at),

      updatedAt: toIsoString(object.updated_at),
    }
  }

  const mapLegacyScheduledTask = (raw: unknown): ScheduledTask => {
    const object = toPlainObject(raw) || {}

    const rawStatus =
      typeof object.status === "string" ? object.status.toLowerCase() : "idle"

    const status: ScheduledTask["status"] =
      rawStatus === "paused"
        ? "paused"
        : rawStatus === "running"
          ? "running"
          : rawStatus === "queued"
            ? "queued"
            : rawStatus === "failed"
              ? "failed"
              : rawStatus === "completed"
                ? "completed"
                : "idle"

    return {
      id: coerceStringId(object.id),

      title:
        typeof object.title === "string" && object.title.trim()
          ? object.title
          : "Scheduled task",

      prompt:
        typeof object.instructions === "string"
          ? object.instructions
          : undefined,

      schedule:
        typeof object.schedule === "string" && object.schedule.trim()
          ? object.schedule
          : typeof object.run_at === "string" && object.run_at.trim()
            ? object.run_at
            : typeof object.cron === "string" && object.cron.trim()
              ? object.cron
              : "Scheduled",

      scheduleType:
        object.schedule_type === "one_time"
          ? "one_time"
          : object.schedule_type === "daily"
            ? "daily"
            : object.schedule_type === "weekly"
              ? "weekly"
              : object.schedule_type === "monthly"
                ? "monthly"
                : "custom",

      timezone:
        typeof object.timezone === "string" && object.timezone.trim()
          ? object.timezone
          : "UTC",

      taskType:
        object.task_type === "reminder" || object.task_type === "monitor"
          ? object.task_type
          : "agent_task",

      notificationEnabled: object.notification_enabled !== false,

      runAt: typeof object.run_at === "string" ? object.run_at : undefined,

      cron: typeof object.cron === "string" ? object.cron : undefined,

      enabled: rawStatus !== "paused",

      nextRun:
        typeof object.next_run_at === "string" && object.next_run_at.trim()
          ? object.next_run_at
          : "Pending",

      lastRun:
        typeof object.last_run_at === "string" && object.last_run_at.trim()
          ? object.last_run_at
          : undefined,

      status,

      lastRunStatus:
        object.last_run_status === "failed" ||
        object.last_run_status === "completed" ||
        object.last_run_status === "running" ||
        object.last_run_status === "queued"
          ? object.last_run_status
          : undefined,

      history: Array.isArray(object.history)
        ? object.history.map((item, index) => {
            const run = toPlainObject(item) || {}
            const runStatus =
              run.status === "failed" || run.status === "completed"
                ? run.status
                : "started"
            return {
              id: coerceStringId(run.id || index),
              startedAt:
                typeof run.started_at === "string" ? run.started_at : "",
              completedAt:
                typeof run.completed_at === "string"
                  ? run.completed_at
                  : undefined,
              status: runStatus,
              deliveryStatus:
                run.delivery_status === "delivered" ||
                run.delivery_status === "skipped" ||
                run.delivery_status === "failed"
                  ? run.delivery_status
                  : "pending",
              deliveryError:
                typeof run.delivery_error === "string"
                  ? run.delivery_error
                  : undefined,
              deliverySkipReason:
                run.delivery_skip_reason === "notification_disabled" ||
                run.delivery_skip_reason === "no_result"
                  ? run.delivery_skip_reason
                  : undefined,
              result: typeof run.result === "string" ? run.result : undefined,
              error: typeof run.error === "string" ? run.error : undefined,
              durationMs:
                typeof run.duration_ms === "number"
                  ? run.duration_ms
                  : undefined,
            }
          })
        : [],
    }
  }

  let htmlDecoder: HTMLTextAreaElement | null = null

  const decodeHtmlText = (value: string): string => {
    if (typeof document !== "undefined") {
      htmlDecoder ||= document.createElement("textarea")

      htmlDecoder.innerHTML = value

      return htmlDecoder.value
    }

    return value
      .replace(/&nbsp;/gi, " ")
      .replace(/&amp;/gi, "&")
      .replace(/&lt;/gi, "<")
      .replace(/&gt;/gi, ">")
      .replace(/&quot;/gi, '"')
      .replace(/&#39;/gi, "'")
  }

  const stripHtml = (value: string): string =>
    decodeHtmlText(value.replace(/<[^>]*>/g, " "))
      .replace(/\s+/g, " ")
      .trim()

  const readTagAttribute = (tag: string, attribute: string): string | null => {
    const match = tag.match(
      new RegExp(`${attribute}\\s*=\\s*["']([^"']*)["']`, "i"),
    )

    return match?.[1] || null
  }

  const inferScheduledTaskStatus = (
    rowHtml: string,
    enabled: boolean,
  ): ScheduledTask["status"] => {
    const text = stripHtml(rowHtml).toLowerCase()

    if (/\bfailed\b/.test(text)) return "failed"

    if (/\bcompleted\b/.test(text)) return "completed"

    if (/\brunning\b/.test(text)) return "running"

    if (/\bqueued\b/.test(text)) return "queued"

    return enabled ? "idle" : "paused"
  }

  const extractLegacyScheduledTasksFromHtml = (
    html: string,
  ): ScheduledTask[] => {
    const tasks: ScheduledTask[] = []

    const seen = new Set<string>()

    const statusFormPattern =
      /<form[^>]*action=["'][^"']*\/dashboard\/user\/openai\/chat\/pro\/scheduled\/(\d+)\/status[^"']*["'][^>]*>[\s\S]*?<\/form>/gi

    for (const match of html.matchAll(statusFormPattern)) {
      const id = match[1]

      if (!id || seen.has(id)) continue

      seen.add(id)

      const formHtml = match[0] || ""

      const formIndex = match.index || 0

      const inputTag =
        formHtml.match(/<input[^>]*name=["']status["'][^>]*>/i)?.[0] || ""

      const toggleTarget = (readTagAttribute(inputTag, "value") || "")
        .trim()
        .toLowerCase()

      const articleStart = html.lastIndexOf("<article", formIndex)

      const rowStartLegacy = html.lastIndexOf(
        '<div class="glist-row"',
        formIndex,
      )

      const rowStart = Math.max(articleStart, rowStartLegacy)

      const sliceStart =
        rowStart >= 0 ? rowStart : Math.max(0, formIndex - 1200)

      let sliceEnd = html.length

      if (rowStart === articleStart && articleStart >= 0) {
        const articleEnd = html.indexOf("</article>", formIndex)

        sliceEnd =
          articleEnd >= 0
            ? articleEnd + "</article>".length
            : Math.min(html.length, formIndex + 2600)
      } else if (rowStart === rowStartLegacy && rowStartLegacy >= 0) {
        const nextRow = html.indexOf('<div class="glist-row"', formIndex + 1)

        const sectionEnd = html.indexOf("</section>", formIndex)

        const endCandidates = [nextRow, sectionEnd].filter(
          (value) => value >= 0,
        )

        sliceEnd = endCandidates.length
          ? Math.min(...endCandidates)
          : Math.min(html.length, formIndex + 2200)
      } else {
        sliceEnd = Math.min(html.length, formIndex + 2200)
      }

      const rowHtml = html.slice(sliceStart, sliceEnd)

      const rowText = stripHtml(rowHtml)

      const titlePatterns = [
        /class=["'][^"']*glist-title[^"']*["'][^>]*>([\s\S]*?)<\/div>/i,
        /<h3[^>]*>([\s\S]*?)<\/h3>/i,
      ]

      let title = ""

      for (const pattern of titlePatterns) {
        const titleMatch = rowHtml.match(pattern)

        if (!titleMatch?.[1]) continue

        const candidate = stripHtml(titleMatch[1])

        if (!candidate) continue

        title = candidate

        break
      }

      const instructionMatch = rowHtml.match(
        /<p[^>]*class=["'][^"']*line-clamp-2[^"']*["'][^>]*>([\s\S]*?)<\/p>/i,
      )

      const subLines = [
        ...rowHtml.matchAll(
          /class=["'][^"']*glist-sub[^"']*["'][^>]*>([\s\S]*?)<\/div>/gi,
        ),
      ]
        .map((item) => stripHtml(item[1] || ""))
        .filter(Boolean)

      const promptFromSub = subLines.find((line) => {
        const normalized = line.toLowerCase()

        if (line.length < 10) return false

        if (/\b(next|not scheduled)\b/.test(normalized)) return false

        if (
          /\b(active|paused|running|failed|queued|completed)\b/.test(normalized)
        )
          return false

        return true
      })

      const prompt = instructionMatch?.[1]
        ? stripHtml(instructionMatch[1])
        : promptFromSub

      let nextRun = "Pending"

      const nextStrong = rowHtml.match(
        /<strong[^>]*>\s*Next\s*:?\s*<\/strong>\s*([^<]+)/i,
      )

      if (nextStrong?.[1]) {
        nextRun = stripHtml(nextStrong[1]) || nextRun
      } else {
        const nextSubLine = subLines.find((line) =>
          /\bnext\b|not scheduled/i.test(line),
        )

        if (nextSubLine) {
          const dotParts = nextSubLine
            .split("\u00b7")
            .map((part) => part.trim())
            .filter(Boolean)

          const nextPart =
            dotParts.find((part) => /\bnext\b|not scheduled/i.test(part)) ||
            nextSubLine

          if (/not scheduled/i.test(nextPart)) {
            nextRun = "Not scheduled"
          } else {
            nextRun = nextPart.replace(/^next\s*/i, "").trim() || nextRun
          }
        } else if (/not scheduled/i.test(rowText)) {
          nextRun = "Not scheduled"
        }
      }

      const lastStrong = rowHtml.match(
        /<strong[^>]*>\s*Last\s*:?\s*<\/strong>\s*([^<]+)/i,
      )

      const lastRun = lastStrong?.[1] ? stripHtml(lastStrong[1]) : undefined

      const scheduleType: ScheduledTask["scheduleType"] =
        /\bone[\s-]?time\b/i.test(rowText)
          ? "one_time"
          : /\bdaily\b/i.test(rowText)
            ? "daily"
            : /\bweekly\b/i.test(rowText)
              ? "weekly"
              : /\bmonthly\b/i.test(rowText)
                ? "monthly"
                : "custom"

      const enabled =
        toggleTarget === "paused"
          ? true
          : toggleTarget === "active"
            ? false
            : !/\bpaused\b/i.test(rowText)

      const schedule =
        scheduleType === "one_time"
          ? "One-time"
          : scheduleType === "custom"
            ? "Scheduled"
            : scheduleType

      tasks.push({
        id,

        title: title || `Scheduled task ${id}`,

        prompt: prompt || undefined,

        schedule,

        scheduleType,

        timezone: "UTC",

        enabled,

        nextRun,

        lastRun,

        status: inferScheduledTaskStatus(rowHtml, enabled),

        history: [],
      })
    }

    return tasks
  }

  const mapLegacyConnector = (raw: unknown): Connector => {
    const object = toPlainObject(raw) || {}

    const key =
      typeof object.key === "string" && object.key.trim()
        ? object.key
        : coerceStringId(object.connector_id)

    const isConnected = Boolean(object.is_connected)

    const isActive = Boolean(object.is_active)

    const isPaused = Boolean(object.is_paused)

    const status: Connector["status"] = !isConnected
      ? "not_connected"
      : isPaused
        ? "paused"
        : !isActive
          ? "reconnect"
          : "connected"

    const permissions = toPlainObject(object.permissions)
    const granted = Array.isArray(object.permissions)
      ? object.permissions.filter(
          (item): item is string => typeof item === "string",
        )
      : []

    const read = Array.isArray(permissions?.read)
      ? permissions.read.filter(
          (item): item is string => typeof item === "string",
        )
      : []

    const write = Array.isArray(permissions?.write)
      ? permissions.write.filter(
          (item): item is string => typeof item === "string",
        )
      : []

    const removePerms = Array.isArray(permissions?.delete)
      ? permissions.delete.filter(
          (item): item is string => typeof item === "string",
        )
      : []

    const externalActions = Array.isArray(permissions?.externalActions)
      ? permissions.externalActions.filter(
          (item): item is string => typeof item === "string",
        )
      : []

    const connectorId =
      typeof object.connector_id === "number" ||
      typeof object.connector_id === "string"
        ? String(object.connector_id)
        : undefined

    const preConsentUrl =
      typeof object.pre_consent_url === "string"
        ? object.pre_consent_url
        : undefined

    legacyConnectorMetaByKey.set(key, { connectorId, preConsentUrl })

    return {
      id: key,

      name:
        typeof object.label === "string" && object.label.trim()
          ? object.label
          : key,

      provider: key,

      description:
        typeof object.description === "string" ? object.description : undefined,

      categories: [],

      featured: false,

      installed: isConnected,

      status,

      paused: isPaused,

      scopes: [],

      permissions: {
        read,

        write,

        delete: removePerms,

        externalActions,

        granted,
      },
    }
  }

  const mapLegacyWorkflowToAgent = (
    raw: unknown,

    fallback?: Partial<Agent>,
  ): Agent => {
    const object = toPlainObject(raw) || {}

    const id = coerceStringId(object.id ?? fallback?.id)

    const createdAt = toIsoString(object.created_at || fallback?.createdAt)

    const updatedAt = toIsoString(
      object.updated_at || fallback?.updatedAt || createdAt,
    )

    const steps = Array.isArray(object.steps) ? object.steps : []

    const tools = steps

      .map((step) => toPlainObject(step)?.type)

      .filter(
        (value): value is string =>
          typeof value === "string" && value.trim().length > 0,
      )

    return {
      id,

      name:
        typeof object.name === "string" && object.name.trim()
          ? object.name
          : fallback?.name || "Agent",

      description:
        typeof object.description === "string"
          ? object.description
          : fallback?.description || "",

      role:
        typeof object.role === "string" && object.role.trim()
          ? object.role
          : fallback?.role || "Assistant",

      goal:
        typeof object.goal === "string" ? object.goal : fallback?.goal || "",

      instructions:
        typeof object.instructions === "string"
          ? object.instructions
          : fallback?.instructions || "",

      model:
        typeof object.model === "string" && object.model.trim()
          ? object.model
          : fallback?.model || "gpt-4o-mini",

      tools: tools.length ? tools : fallback?.tools || [],

      plugins: fallback?.plugins || [],

      knowledge: fallback?.knowledge || [],

      memory: fallback?.memory || "",

      permissions: fallback?.permissions || [],

      schedule: fallback?.schedule,

      status: mapWorkflowStatusToAgentStatus(object.status ?? fallback?.status),

      createdAt,

      updatedAt,
    }
  }

  const mapLegacyWorkflowRun = (
    raw: unknown,

    fallback?: Partial<AgentRun>,
  ): AgentRun => {
    const object = toPlainObject(raw) || {}

    const state = mapWorkflowRunState(object.status ?? fallback?.state)

    const startedAt = toIsoString(object.started_at || fallback?.startedAt)

    const updatedAt = toIsoString(
      object.completed_at ||
        object.updated_at ||
        fallback?.updatedAt ||
        startedAt,
    )

    return {
      id: coerceStringId(object.id ?? fallback?.id),

      agentId: coerceStringId(object.workflow_id ?? fallback?.agentId),

      task:
        typeof object.task === "string"
          ? object.task
          : fallback?.task || "Workflow execution",

      state,

      currentTask:
        typeof object.current_task === "string"
          ? object.current_task
          : fallback?.currentTask,

      currentTool:
        typeof object.current_tool === "string"
          ? object.current_tool
          : fallback?.currentTool,

      progress:
        typeof object.progress === "number" && Number.isFinite(object.progress)
          ? object.progress
          : statusToProgress(state),

      sources: Array.isArray(fallback?.sources) ? fallback.sources : [],

      outputs: Array.isArray(fallback?.outputs) ? fallback.outputs : [],

      startedAt,

      updatedAt,

      error:
        typeof object.error_message === "string"
          ? object.error_message
          : fallback?.error,
    }
  }

  const mapLegacyMemory = (
    raw: unknown,

    fallback?: Partial<MemoryItem>,
  ): MemoryItem => {
    const object = toPlainObject(raw) || {}

    const content =
      typeof object.memory === "string"
        ? object.memory
        : typeof object.content === "string"
          ? object.content
          : fallback?.content || ""

    const createdAt = toIsoString(object.created_at || fallback?.createdAt)

    const updatedAt = toIsoString(
      object.updated_at || fallback?.updatedAt || createdAt,
    )

    return {
      id: coerceStringId(object.id ?? fallback?.id),

      title:
        typeof object.title === "string" && object.title.trim()
          ? object.title
          : fallback?.title || "Memory",

      content,

      enabled: parseBooleanLike(object.enabled ?? fallback?.enabled ?? true),

      scope:
        fallback?.scope ||
        (typeof object.scope === "string" &&
        ["user", "project", "agent"].includes(object.scope)
          ? object.scope as MemoryItem["scope"]
          : "user"),

      projectId:
        typeof object.project_id === "string" ||
        typeof object.project_id === "number"
          ? String(object.project_id)
          : fallback?.projectId,

      agentId:
        typeof object.agent_id === "string" ||
        typeof object.agent_id === "number"
          ? String(object.agent_id)
          : fallback?.agentId,

      createdAt,

      updatedAt,
    }
  }

  const extractCategoryIdFromHtml = (html: string): string | null => {
    const patterns = [
      /id=["']category_id["'][^>]*value=["'](\d+)["']/i,
      /name=["']category_id["'][^>]*value=["'](\d+)["']/i,
      /value=["'](\d+)["'][^>]*id=["']category_id["']/i,
      /"openai_chat_category_id"\s*:\s*(\d+)/i,
      /"category_id"\s*:\s*(\d+)/i,
      /data-category-id=["'](\d+)["']/i,
    ]

    for (const pattern of patterns) {
      const match = html.match(pattern)

      if (match?.[1] && looksNumericId(match[1])) return match[1]
    }

    return null
  }

  const extractLegacyCategoryIdFromPayload = (
    payload: unknown,
  ): string | null => {
    const object = toPlainObject(payload)

    const chatObject = toPlainObject(object?.chat)
    const firstListedChat = Array.isArray(object?.chats)
      ? toPlainObject(object.chats[0])
      : null

    const directCandidate =
      toNumericIdString(chatObject?.openai_chat_category_id) ||
      toNumericIdString(chatObject?.category_id) ||
      toNumericIdString(object?.openai_chat_category_id) ||
      toNumericIdString(object?.category_id) ||
      toNumericIdString(firstListedChat?.openai_chat_category_id) ||
      toNumericIdString(firstListedChat?.category_id)

    if (directCandidate) return directCandidate

    const htmlSnippets = [object?.html, object?.html2, payload]

    for (const snippet of htmlSnippets) {
      if (typeof snippet !== "string" || !snippet.trim()) continue

      const categoryId = extractCategoryIdFromHtml(snippet)

      if (categoryId) return categoryId
    }

    return null
  }

  const toArrayData = (payload: unknown): unknown[] => {
    const data = unwrapEnvelope<unknown>(payload)

    return Array.isArray(data) ? data : []
  }

  const normalizeModelToken = (value: string): string =>
    value.toLowerCase().replace(/\s+/g, " ").trim()

  const inferChatCapabilitiesFromText = (
    value: string,
  ): ModelDescriptor["capabilities"] => {
    const text = value.toLowerCase()

    const capabilities: ModelDescriptor["capabilities"] = ["chat"]

    const addCapability = (
      capability: ModelDescriptor["capabilities"][number],
    ) => {
      if (!capabilities.includes(capability)) capabilities.push(capability)
    }

    if (/reason|thinking|analysis|sonnet|claude|hir|pro/.test(text)) {
      addCapability("reasoning")
      addCapability("data_analysis")
    }

    if (/vision|multimodal|omni/.test(text)) addCapability("vision")

    if (/research|realtime|search|perplexity|serper/.test(text)) {
      addCapability("research")
    }

    if (/code|coder|program|dev/.test(text)) addCapability("tools")

    return capabilities
  }

  const parseLegacyChatProModelsFromHtml = (
    html: string,
  ): ModelDescriptor[] => {
    const doc = new DOMParser().parseFromString(html, "text/html")

    const optionElements = Array.from(
      doc.querySelectorAll<HTMLSelectElement>("#chatbot_front_model option"),
    )

    if (optionElements.length === 0) return []

    type ModelMeta = {
      meta: string

      disabled: boolean
    }

    const metaByLabel = new Map<string, ModelMeta[]>()

    const optionButtons = Array.from(
      doc.querySelectorAll<HTMLButtonElement>(".garad-claude-model-option"),
    )

    optionButtons.forEach((button) => {
      const label = button.querySelector("strong")?.textContent?.trim() || ""

      if (!label) return

      const key = normalizeModelToken(label)

      const meta = button.querySelector("small")?.textContent?.trim() || ""

      const disabled =
        button.classList.contains("is-disabled") ||
        button.hasAttribute("disabled") ||
        /no credits available/i.test(meta)

      const queue = metaByLabel.get(key) || []

      queue.push({ meta, disabled })

      metaByLabel.set(key, queue)
    })

    const models = optionElements
      .map((option) => {
        const id = option.getAttribute("value")?.trim() || ""

        if (!id) return null

        const displayName =
          option.getAttribute("data-label")?.trim() ||
          option.textContent?.trim() ||
          id

        const key = normalizeModelToken(displayName)

        const queue = metaByLabel.get(key) || []

        const modelMeta = queue.length > 0 ? queue.shift() : undefined

        if (queue.length > 0) metaByLabel.set(key, queue)

        const description = modelMeta?.meta || "Chat model"

        const availability: ModelDescriptor["availability"] =
          modelMeta?.disabled || /no credits available/i.test(description)
            ? "unavailable"
            : "available"

        const descriptor: ModelDescriptor = {
          id,

          displayName,

          description,

          capabilities: inferChatCapabilitiesFromText(
            `${displayName} ${description}`,
          ),

          planRequirement: "free",

          availability,
        }

        return descriptor
      })
      .filter((item): item is ModelDescriptor => Boolean(item))

    const byId = new Map<string, ModelDescriptor>()

    models.forEach((model) => byId.set(model.id, model))

    return [...byId.values()]
  }

  const fetchLegacyChatProModels = async (): Promise<ModelDescriptor[]> => {
    const chatModelPages = [
      "/dashboard/user/openai/chat/pro/chat",
      "/dashboard/user/openai/chat",
    ]

    for (const page of chatModelPages)
      try {
        const html = await requestHtmlDocument(page)

        const models = parseLegacyChatProModelsFromHtml(html)

        if (models.length === 0) continue

        return models
      } catch (error) {
        if (
          error instanceof ApiError &&
          (error.status === 401 || error.status === 403)
        ) {
          throw error
        }

        if (
          error instanceof ApiError &&
          (error.status === 404 || error.status === 405)
        ) {
          continue
        }
      }

    return []
  }

  const paginateInMemory = <T>(items: T[], query?: PaginationQuery): T[] => {
    const perPage = Math.max(1, query?.perPage || items.length || 1)

    const page = Math.max(1, query?.page || 1)

    const start = (page - 1) * perPage

    return items.slice(start, start + perPage)
  }

  const fetchLegacyAgentsFromConversations = async (
    query?: PaginationQuery,
  ): Promise<Agent[]> => {
    const payload = await requestJson<unknown>(
      ensureConfigured(),

      "/dashboard/user/ai-agent/messages/conversations",

      { query: paginationToQuery(query) },
    )

    const rows = toArrayData(payload)

    const byId = new Map<string, Agent>(legacyAgentCache)

    for (const row of rows) {
      const workflow = toPlainObject(toPlainObject(row)?.workflow)

      if (!workflow) continue

      const agent = mapLegacyWorkflowToAgent(workflow)

      legacyAgentCache.set(agent.id, agent)

      byId.set(agent.id, agent)
    }

    return [...byId.values()]
  }

  const fetchLegacyWorkflowRuns = async (
    agentId: string,

    query?: PaginationQuery,
  ): Promise<AgentRun[]> => {
    if (!looksNumericId(agentId)) return []

    const payload = await requestJson<unknown>(
      ensureConfigured(),

      `/dashboard/user/ai-agent/workflows/${agentId}/runs`,

      { query: paginationToQuery(query) },
    )

    const rows = toArrayData(payload)

    return rows.map((row) =>
      mapLegacyWorkflowRun(row, {
        agentId,

        task: "Workflow execution",
      }),
    )
  }

  const buildLegacyWorkflowPayload = (agent: Partial<Agent>) => ({
    name: (typeof agent.name === "string" && agent.name.trim()) || "New Agent",

    description: typeof agent.description === "string" ? agent.description : "",

    role: (typeof agent.role === "string" && agent.role.trim()) || "Assistant",

    trigger_type: "webhook",

    trigger_config: {},

    steps: [
      {
        id: crypto.randomUUID(),

        type: "ai_call",

        app: "AI",

        action: "ai_call",

        config: {
          system_prompt:
            (typeof agent.instructions === "string" &&
              agent.instructions.trim()) ||
            (typeof agent.goal === "string" && agent.goal.trim()) ||
            "You are a helpful assistant.",

          user_message: "{message_text}",

          store_output_as: "ai_response",
        },

        order: 0,
      },
    ],
  })

  const performWebAction = async (
    path: string,

    method: HttpMethod,

    body?: unknown,
  ): Promise<void> => {
    const configuredBaseUrl = ensureConfigured()

    const headers: Record<string, string> = {
      Accept: "application/json",

      "X-Requested-With": "XMLHttpRequest",
    }

    if (method !== "GET") {
      const csrfToken = resolveCsrfToken()

      if (csrfToken) {
        headers["X-CSRF-TOKEN"] = csrfToken

        headers["X-XSRF-TOKEN"] = csrfToken
      }
    }

    let requestBody: BodyInit | undefined

    if (body !== undefined) {
      if (typeof FormData !== "undefined" && body instanceof FormData) {
        requestBody = body
      } else {
        headers["Content-Type"] = "application/json"

        requestBody = JSON.stringify(body)
      }
    }

    const response = await fetch(`${configuredBaseUrl}${path}`, {
      method,

      headers,

      body: requestBody,

      credentials: "include",
    })

    if (response.redirected && /\/(login|signin|auth)\b/i.test(response.url)) {
      throw new ApiError(
        "Session expired. Please sign in again.",
        401,
        "session_expired",
      )
    }

    if (response.ok) return

    const payload = await parseResponseBody(response)

    throw new ApiError(
      readErrorMessage(payload, response.status),

      response.status,

      toPlainObject(payload)?.code as string | undefined,

      payload,
    )
  }

  const list = async <T,>(
    path: string,
    query?: QueryParams,
    timeoutMs?: number,
  ): Promise<T[]> => {
    const payload =
      await requestJson<ApiListEnvelope<T> | ApiEnvelope<T[]> | T[]>(
        baseUrl,

        path,

        { query, timeoutMs },
      )

    return unwrapListEnvelope<T>(payload)
  }

  const listWithFallback = async <T,>(
    paths: string[],

    query?: QueryParams,
  ): Promise<T[]> => {
    const payload =
      await requestWithFallback<ApiListEnvelope<T> | ApiEnvelope<T[]> | T[]>(
        baseUrl,
        paths,
        { query },
      )

    return unwrapListEnvelope<T>(payload)
  }

  const getOne = async <T>(path: string, timeoutMs?: number): Promise<T> => {
    const payload = await requestJson<ApiEnvelope<T> | T>(baseUrl, path, {
      timeoutMs,
    })

    return unwrapEnvelope<T>(payload)
  }

  const getOneWithFallback = async <T>(paths: string[]): Promise<T> => {
    const payload = await requestWithFallback<ApiEnvelope<T> | T>(
      baseUrl,
      paths,
    )

    return unwrapEnvelope<T>(payload)
  }

  const createOne = async <T,>(
    path: string,
    body?: unknown,
    timeoutMs?: number,
  ): Promise<T> => {
    const payload = await requestJson<ApiEnvelope<T> | T>(baseUrl, path, {
      method: "POST",

      body,

      timeoutMs,
    })

    return unwrapEnvelope<T>(payload)
  }

  const createOneWithFallback = async <T,>(
    paths: string[],

    body?: unknown,
  ): Promise<T> => {
    const payload = await requestWithFallback<ApiEnvelope<T> | T>(
      baseUrl,
      paths,
      {
        method: "POST",

        body,
      },
    )

    return unwrapEnvelope<T>(payload)
  }

  const patchOne = async <T,>(
    path: string,
    body: unknown,
    timeoutMs?: number,
  ): Promise<T> => {
    const payload = await requestJson<ApiEnvelope<T> | T>(baseUrl, path, {
      method: "PATCH",

      body,

      timeoutMs,
    })

    return unwrapEnvelope<T>(payload)
  }

  const remove = async (path: string, timeoutMs?: number): Promise<void> => {
    await requestJson(baseUrl, path, { method: "DELETE", timeoutMs })
  }

  const removeWithFallback = async (paths: string[]): Promise<void> => {
    await requestWithFallback(baseUrl, paths, { method: "DELETE" })
  }

  const requestHtmlDocument = async (
    path: string,
    query?: QueryParams,
  ): Promise<string> => {
    const configuredBaseUrl = ensureConfigured()

    const response = await fetch(buildUrl(configuredBaseUrl, path, query), {
      method: "GET",

      headers: {
        Accept: "text/html,application/xhtml+xml",

        "X-Requested-With": "XMLHttpRequest",
      },

      credentials: "include",
    })

    if (response.redirected && /\/(login|signin|auth)\b/i.test(response.url)) {
      throw new ApiError(
        "Session expired. Please sign in again.",
        401,
        "session_expired",
      )
    }

    const html = await response.text()

    if (!response.ok) {
      throw new ApiError(
        readErrorMessage(html, response.status),

        response.status,

        undefined,

        html,
      )
    }

    return html
  }

  const cacheDetectedLegacyCategoryId = (value: unknown): string | null => {
    const id = toNumericIdString(value)

    if (!id) return null

    detectedLegacyCategoryId = id

    return id
  }

  const readLegacyChatIdFromPayload = (payload: unknown): string | null => {
    const object = toPlainObject(payload)

    const chatObject = toPlainObject(object?.chat ?? payload)

    if (!chatObject) return null

    const value = chatObject.id

    if (typeof value === "string" && value.trim()) return value

    if (typeof value === "number" && Number.isFinite(value))
      return String(value)

    return null
  }

  const ensureLegacyCategoryId = (): string | null => {
    if (LEGACY_CHAT_CATEGORY_ID) {
      if (!looksNumericId(LEGACY_CHAT_CATEGORY_ID)) {
        throw new ApiError(
          "VITE_GARAD_CHAT_CATEGORY_ID must be numeric for the Laravel chat routes.",

          422,

          "invalid_chat_category_id",
        )
      }

      return LEGACY_CHAT_CATEGORY_ID
    }

    return detectedLegacyCategoryId && looksNumericId(detectedLegacyCategoryId)
      ? detectedLegacyCategoryId
      : null
  }

  const createLegacyConversation = async (
    localConversationId?: string,
    force = false,
  ): Promise<string> => {
    if (localConversationId && !force) {
      const mapped = legacyConversationIds.get(localConversationId)
      if (mapped && looksNumericId(mapped)) return mapped

      const pending =
        pendingLegacyConversationCreations.get(localConversationId)
      if (pending) return pending
    }

    const creation = (async () => {
      const configuredBaseUrl = ensureConfigured()
      const categoryId = ensureLegacyCategoryId()

      const payload = await requestJson<unknown>(
        configuredBaseUrl,
        "/dashboard/user/openai/chat/start-new-chat",
        {
          method: "POST",

          body: {
            category_id: categoryId ? Number(categoryId) : undefined,

            website_url: LEGACY_WEBSITE_URL,

            react_chat: true,
          },
        },
      )

      cacheDetectedLegacyCategoryId(extractLegacyCategoryIdFromPayload(payload))

      const chatId = readLegacyChatIdFromPayload(payload)

      if (!chatId || !looksNumericId(chatId)) {
        throw new ApiError(
          "Laravel backend did not return a valid chat identifier.",

          500,

          "legacy_chat_id_missing",
        )
      }

      legacyConversationIds.set(chatId, chatId)

      if (localConversationId) {
        legacyConversationIds.set(localConversationId, chatId)
      }

      return chatId
    })()

    if (!localConversationId) return creation

    pendingLegacyConversationCreations.set(localConversationId, creation)
    try {
      return await creation
    } finally {
      if (
        pendingLegacyConversationCreations.get(localConversationId) === creation
      ) {
        pendingLegacyConversationCreations.delete(localConversationId)
      }
    }
  }

  const resolveConversationIdForLegacy = async (
    conversationId: string,
  ): Promise<string> => {
    if (looksNumericId(conversationId)) {
      legacyConversationIds.set(conversationId, conversationId)

      return conversationId
    }

    const mapped = legacyConversationIds.get(conversationId)

    if (mapped && looksNumericId(mapped)) return mapped

    return createLegacyConversation(conversationId)
  }

  const collectLegacyAttachmentPayload = (attachmentIds: string[]) => {
    const imagePaths: string[] = []

    const imageNames: string[] = []

    const documentPaths: string[] = []

    const documentNames: string[] = []

    for (const attachmentId of attachmentIds) {
      const item = uploadedAssetsByAttachmentId.get(attachmentId)

      if (!item) continue

      item.paths.forEach((path, index) => {
        const name = item.names[index] || item.originalName

        const isImage =
          item.mimeType.startsWith("image/") ||
          /\.(png|jpe?g|webp|gif|bmp)$/i.test(path)

        if (isImage) {
          imagePaths.push(path)

          imageNames.push(name)
        } else {
          documentPaths.push(path)

          documentNames.push(name)
        }
      })
    }

    return {
      images: imagePaths.length ? imagePaths : undefined,

      image_names: imageNames.length ? imageNames : undefined,

      pdfpath: documentPaths.length ? documentPaths : undefined,

      pdfname: documentNames.length ? documentNames : undefined,
    }
  }

  const streamResponse = async function* (
    response: Response,
    signal?: AbortSignal,
  ): AsyncIterable<StreamEvent> {
    if (!response.body)
      throw new ApiError(
        "Streaming response body is unavailable.",

        response.status,
      )

    const reader = response.body.getReader()

    const decoder = new TextDecoder()

    let buffer = ""

    while (true) {
      let result: ReadableStreamReadResult<Uint8Array>
      try {
        result = await reader.read()
      } catch (error) {
        if (
          !signal?.aborted &&
          !(error instanceof DOMException && error.name === "AbortError")
        ) {
          console.error("Garad response stream was interrupted", {
            endpoint: response.url,
            status: response.status,
            error,
          })
        }
        throw error
      }

      const { done, value } = result

      if (done) break

      buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, "\n")

      let frameEnd = buffer.indexOf("\n\n")

      while (frameEnd >= 0) {
        const frame = buffer.slice(0, frameEnd)

        buffer = buffer.slice(frameEnd + 2)

        const event = parseSseFrame(frame)

        if (event) yield event

        frameEnd = buffer.indexOf("\n\n")
      }
    }

    const trailing = buffer.trim()

    if (trailing) {
      const event = parseSseFrame(trailing)

      if (event) yield event
    }
  }

  const openStreamResponse = async (
    path: string,

    body: unknown,

    signal: AbortSignal,
  ): Promise<Response> => {
    const configuredBaseUrl = ensureConfigured()

    const headers: Record<string, string> = {
      Accept: "application/json, text/event-stream",

      "Content-Type": "application/json",

      "X-Requested-With": "XMLHttpRequest",
    }

    const csrfToken = resolveCsrfToken()

    if (csrfToken) {
      headers["X-CSRF-TOKEN"] = csrfToken

      headers["X-XSRF-TOKEN"] = csrfToken
    }

    let response: Response
    try {
      response = await fetch(`${configuredBaseUrl}${path}`, {
        method: "POST",

        headers,

        credentials: "include",

        body: JSON.stringify(body),

        signal,
      })
    } catch (error) {
      if (!(error instanceof DOMException && error.name === "AbortError")) {
        console.error(
          "Garad stream request failed before receiving a response",
          {
            endpoint: path,
            method: "POST",
            error,
          },
        )
      }
      throw error
    }

    if (response.redirected && /\/(login|signin|auth)\b/i.test(response.url)) {
      throw new ApiError(
        "Session expired. Please sign in again.",
        401,
        "session_expired",
      )
    }

    if (!response.ok) {
      const payload = await parseResponseBody(response)
      const message = readErrorMessage(payload, response.status)
      const code = toPlainObject(payload)?.code as string | undefined

      console.error("Garad stream endpoint returned an error", {
        endpoint: path,
        status: response.status,
        code,
        message,
      })

      throw new ApiError(
        message,

        response.status,

        code,

        payload,
      )
    }

    const contentType = response.headers.get("content-type") || ""

    if (/text\/html/i.test(contentType)) {
      throw new ApiError(
        "Session expired. Please sign in again.",
        401,
        "session_expired",
      )
    }

    return response
  }

  const fetchLegacyConversations = async (
    query?: ConversationsQuery,
  ): Promise<Conversation[]> => {
    const configuredBaseUrl = ensureConfigured()

    const payload = await requestJson<unknown>(
      configuredBaseUrl,
      "/dashboard/user/openai/chat/get-chats",
      {
        query: {
          ...paginationToQuery(query),

          mode: query?.mode,

          website_url: LEGACY_WEBSITE_URL,

          scope: "sidebar_all",

          folder_id: query?.projectId,

          is_pinned:
            query?.pinned === undefined ? undefined : query.pinned ? 1 : 0,
        },
        timeoutMs: LEGACY_HISTORY_TIMEOUT_MS,
      },
    )

    const object = toPlainObject(payload)

    if (!Array.isArray(object?.chats)) {
      throw new ApiError(
        "Laravel returned an invalid conversation history response.",
        502,
        "invalid_conversation_list",
        payload,
      )
    }

    const responseUserId = object?.user_id
    if (
      typeof responseUserId === "string" ||
      typeof responseUserId === "number"
    ) {
      legacyUserId = String(responseUserId)
    }

    const rows = object.chats

    const baseConversations = rows.map(mapLegacyConversation)

    const filtered = baseConversations.filter((conversation) => {
      if (query?.pinned !== undefined && conversation.pinned !== query.pinned)
        return false

      if (
        query?.archived !== undefined &&
        conversation.archived !== query.archived
      )
        return false

      if (query?.projectId && conversation.projectId !== query.projectId)
        return false

      return true
    })

    return filtered
  }

  const fetchLegacyConversationMessages = async (
    legacyConversationId: string,
  ): Promise<Message[]> => {
    if (!looksNumericId(legacyConversationId)) return []

    const payload = await requestJson<unknown>(
      ensureConfigured(),
      "/dashboard/user/openai/chat/open-chatbot-area",
      {
        method: "POST",
        body: {
          chat_id: Number(legacyConversationId),
          website_url: LEGACY_WEBSITE_URL,
          messages_only: true,
        },
        timeoutMs: LEGACY_HISTORY_TIMEOUT_MS,
      },
    )

    const object = toPlainObject(payload)
    const rows = Array.isArray(object?.messages)
      ? object.messages
      : object?.lastThreeMessage
    if (!Array.isArray(rows)) {
      throw new ApiError(
        "Laravel returned an invalid conversation message history response.",
        502,
        "invalid_conversation_messages",
        payload,
      )
    }
    const html = typeof object?.html === "string" ? object.html : ""

    cacheDetectedLegacyCategoryId(
      extractLegacyCategoryIdFromPayload(payload) ||
        (html ? extractCategoryIdFromHtml(html) : null),
    )

    return attachAgentMentionReferences(mapLegacyMessageRows(rows))
  }

  const fetchLegacyConversation = async (id: string): Promise<Conversation> => {
    if (!looksNumericId(id)) {
      throw new ApiError(
        "Conversation not found or unavailable to this account.",
        404,
        "chat_not_found",
      )
    }

    const payload = await requestJson<unknown>(
      ensureConfigured(),
      "/dashboard/user/openai/chat/open-chatbot-area",
      {
        method: "POST",
        body: {
          chat_id: Number(id),
          website_url: LEGACY_WEBSITE_URL,
        },
        timeoutMs: LEGACY_HISTORY_TIMEOUT_MS,
      },
    )
    const object = toPlainObject(payload)
    const chat = toPlainObject(object?.conversation)
    if (!chat || coerceStringId(chat.id) !== id) {
      throw new ApiError(
        "Conversation not found or unavailable to this account.",
        404,
        "chat_not_found",
      )
    }
    const html = typeof object?.html === "string" ? object.html : ""
    if (!Array.isArray(object?.lastThreeMessage)) {
      throw new ApiError(
        "Laravel returned an invalid conversation message history response.",
        502,
        "invalid_conversation_messages",
        payload,
      )
    }
    cacheDetectedLegacyCategoryId(
      extractLegacyCategoryIdFromPayload(payload) ||
        (html ? extractCategoryIdFromHtml(html) : null),
    )
    const conversation = mapLegacyConversation(chat)
    const messages = await attachAgentMentionReferences(
      mapLegacyMessageRows(object?.lastThreeMessage),
    )

    return {
      ...conversation,
      messages,
    }
  }

  const fetchLegacyProjects = async (): Promise<Project[]> => {
    const payload = await requestJson<unknown>(
      ensureConfigured(),
      "/dashboard/user/ai-chat-pro/folders",
    )

    const object = toPlainObject(payload)

    if (!Array.isArray(object?.folders)) {
      throw new ApiError(
        "Garad returned an invalid project folder list.",
        502,
        "invalid_project_list",
        payload,
      )
    }
    const folders = object.folders

    return folders.map(mapLegacyProject)
  }

  const fetchLegacyConnectors = async (): Promise<Connector[]> => {
    const payload = await requestJson<unknown>(
      ensureConfigured(),

      "/dashboard/user/ai-chat-pro/connectors",

      { query: paginationToQuery(undefined) },
    )

    const object = toPlainObject(payload)

    const rows = Array.isArray(object?.connectors) ? object.connectors : []

    return rows.map(mapLegacyConnector)
  }

  const resolveLegacyConnectorId = async (key: string): Promise<string> => {
    let connectorId = legacyConnectorMetaByKey.get(key)?.connectorId

    if (!connectorId) {
      await fetchLegacyConnectors()
      connectorId = legacyConnectorMetaByKey.get(key)?.connectorId
    }

    if (!connectorId) {
      throw new ApiError("Connector not found.", 404, "connector_not_found")
    }

    return connectorId
  }

  const fetchLegacyScheduledTasks = async (
    query?: PaginationQuery,
  ): Promise<ScheduledTask[]> => {
    const payload = await requestJson<unknown>(
      ensureConfigured(),
      "/dashboard/user/openai/chat/pro/scheduled",
      { timeoutMs: 60_000 },
    )
    const object = toPlainObject(payload)
    if (!Array.isArray(object?.tasks)) {
      throw new ApiError(
        "Garad returned an invalid scheduled task list.",
        502,
        "invalid_scheduled_task_list",
        payload,
      )
    }
    return paginateInMemory(object.tasks.map(mapLegacyScheduledTask), query)
  }

  const fetchLegacyMemories = async (
    query?: PaginationQuery,
  ): Promise<MemoryItem[]> => {
    const payload = await requestJson<unknown>(
      ensureConfigured(),
      "/dashboard/user/ai-agent/memory",
      { query: paginationToQuery(query) },
    )
    const memories = toArrayData(payload).map((item) => mapLegacyMemory(item))
    for (const memory of memories) legacyMemoryCache.set(memory.id, memory)
    return memories
  }

  const tryLegacySessionProbe = async (): Promise<boolean> => {
    const probes: Array<{
      path: string
      query?: QueryParams
    }> = [
      {
        path: "/dashboard/user/openai/generator/check/status",
      },

      {
        path: "/dashboard/user/openai/chat/get-chats",

        query: {
          per_page: 1,

          website_url: LEGACY_WEBSITE_URL,

          scope: "sidebar_all",
        },
      },
    ]

    for (const probe of probes) {
      try {
        await requestJson<unknown>(ensureConfigured(), probe.path, {
          query: probe.query,
        })

        return true
      } catch (error) {
        if (
          error instanceof ApiError &&
          (error.status === 401 || error.status === 403)
        ) {
          throw error
        }

        if (
          error instanceof ApiError &&
          (error.status === 404 || error.status === 405)
        ) {
          continue
        }
      }
    }

    return false
  }

  const chatStream = async function* (
    request: ChatRequest,

    signal: AbortSignal,
  ): AsyncIterable<StreamEvent> {
    ensureConfigured()

    if (request.guest) {
      const lastUserMessage = [...request.messages]
        .reverse()
        .find((message) => message.role === "user")
      const prompt = lastUserMessage?.content.trim()
      if (!prompt) {
        throw new ApiError(
          "Enter a message before starting guest chat.",
          422,
          "guest_chat_prompt_required",
        )
      }

      const response = await openStreamResponse(
        "/api/guest/chat-stream",
        { prompt },
        signal,
      )
      yield* streamResponse(response, signal)
      return
    }

    if (request.tools?.deepResearch) {
      const conversationId = await resolveConversationIdForLegacy(
        request.conversationId,
      )
      if (!looksNumericId(conversationId)) {
        throw new ApiError(
          "Deep Research requires a Garad conversation.",
          422,
          "deep_research_conversation_required",
        )
      }
      if (conversationId !== request.conversationId) {
        yield { type: "conversationId", conversationId }
      }

      const lastUserMessage = [...request.messages]
        .reverse()
        .find((message) => message.role === "user")
      const prompt = lastUserMessage?.content.trim()
      if (!prompt) {
        throw new ApiError(
          "Enter a research question before starting Deep Research.",
          422,
          "deep_research_prompt_required",
        )
      }

      yield { type: "status", status: "queued" }
      const startedPayload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/deep-research/start",
        {
          method: "POST",
          body: { prompt, chat_id: Number(conversationId) },
          signal,
        },
      )
      const started = toPlainObject(unwrapEnvelope<unknown>(startedPayload))
      const sessionId = started?.session_id
      if (
        typeof sessionId !== "string" &&
        (typeof sessionId !== "number" || !Number.isFinite(sessionId))
      ) {
        throw new ApiError(
          "Garad Deep Research did not return a research session.",
          502,
          "deep_research_session_missing",
          startedPayload,
        )
      }

      const id = String(sessionId)
      const toolId = `deep-research-${id}`
      const backendMessageId = started?.message_id
      if (
        typeof backendMessageId === "string" ||
        typeof backendMessageId === "number"
      ) {
        yield {
          type: "legacyMessage",
          messageId: String(backendMessageId),
        }
      }
      yield {
        type: "tool",
        tool: {
          id: toolId,
          name: "deep_research",
          label: "Deep Research",
          status: "running",
          detail: "Researching sources",
        },
      }
      yield { type: "status", status: "tool_call" }

      try {
        for (let attempt = 0; attempt < 600; attempt += 1) {
          if (signal.aborted) throw new DOMException("Aborted", "AbortError")
          await new Promise((resolve) => window.setTimeout(resolve, 2000))
          if (signal.aborted) throw new DOMException("Aborted", "AbortError")

          const pollPayload = await requestJson<unknown>(
            ensureConfigured(),
            `/dashboard/user/deep-research/poll/${encodeURIComponent(id)}`,
            { signal },
          )
          const result = toPlainObject(unwrapEnvelope<unknown>(pollPayload))
          const state = typeof result?.status === "string" ? result.status : ""
          if (state === "failed" || state === "cancelled") {
            const message =
              typeof result?.error === "string"
                ? result.error
                : `Deep Research ${state}.`
            yield {
              type: "error",
              message,
              code:
                typeof result?.code === "string" && result.code
                  ? result.code
                  : `deep_research_${state}`,
            }
            return
          }
          if (state !== "completed") {
            const sourceCount =
              typeof result?.sources_count === "number"
                ? result.sources_count
                : 0
            const searchCount =
              typeof result?.searches_count === "number"
                ? result.searches_count
                : 0
            yield {
              type: "tool",
              tool: {
                id: toolId,
                name: "deep_research",
                label: "Deep Research",
                status: "running",
                detail:
                  sourceCount > 0
                    ? `Reviewing ${sourceCount} sources`
                    : searchCount > 0
                      ? `Searching (${searchCount} searches)`
                      : "Researching sources",
              },
            }
            continue
          }

          const report = typeof result?.report === "string" ? result.report : ""
          if (!report.trim()) {
            yield {
              type: "error",
              message: "Deep Research completed without a report.",
              code: "deep_research_report_missing",
            }
            return
          }

          yield {
            type: "tool",
            tool: {
              id: toolId,
              name: "deep_research",
              label: "Deep Research",
              status: "completed",
              detail: "Research complete",
            },
          }
          yield { type: "status", status: "streaming" }
          yield { type: "delta", text: report }

          if (Array.isArray(result?.sources)) {
            for (const [index, rawSource] of result.sources.entries()) {
              const source = toPlainObject(rawSource)
              if (!source || typeof source.url !== "string" || !source.url)
                continue
              const title =
                typeof source.title === "string" && source.title.trim()
                  ? source.title
                  : source.url
              let host = title
              try {
                host = new URL(source.url).hostname
              } catch {
                // Keep the source title if the backend URL is not absolute.
              }
              yield {
                type: "citation",
                citation: {
                  id: `deep-research-source-${id}-${index}`,
                  title,
                  url: source.url,
                  source: host,
                },
              }
            }
          }
          yield { type: "done" }
          return
        }

        yield {
          type: "error",
          message:
            "Deep Research is taking longer than expected. Please try again.",
          code: "deep_research_timeout",
        }
      } catch (error) {
        if (signal.aborted) {
          try {
            await requestJson(
              ensureConfigured(),
              `/dashboard/user/deep-research/cancel/${encodeURIComponent(id)}`,
              { method: "POST", body: {} },
            )
          } catch (cancelError) {
            console.warn(
              "Could not cancel the Garad Deep Research session.",
              cancelError,
            )
          }
        }
        throw error
      }
      return
    }

    if (request.mode !== "agents" && !usesLegacyChatBackend) {
      try {
        const canonical = await openStreamResponse(
          "/api/chat/stream",
          request,
          signal,
        )

        yield* streamResponse(canonical, signal)

        return
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error
        usesLegacyChatBackend = true
      }
    }

    const lastUserMessage = [...request.messages]

      .reverse()

      .find((message) => message.role === "user")

    const attachmentPayload = collectLegacyAttachmentPayload(
      lastUserMessage?.attachmentIds || [],
    )

    const prompt =
      (lastUserMessage?.content || "").trim() ||
      (attachmentPayload.images || attachmentPayload.pdfpath
        ? "Please analyze the attached files."
        : "Continue.")
    const webSearchToolId = `web-search-${request.conversationId}`
    if (request.tools?.webSearch) {
      yield { type: "status", status: "queued" }
      yield {
        type: "tool",
        tool: {
          id: webSearchToolId,
          name: "web_search",
          label: "Web Search",
          status: "running",
          detail: "Searching...",
        },
      }
      yield { type: "status", status: "tool_call" }
    }

    const tryStream = async function* (
      forceRecreateConversation = false,
    ): AsyncIterable<StreamEvent> {
      let resolvedChatId =
        legacyConversationIds.get(request.conversationId) ||
        request.conversationId

      if (forceRecreateConversation || !looksNumericId(resolvedChatId)) {
        resolvedChatId = await createLegacyConversation(
          request.conversationId,
          forceRecreateConversation,
        )
        yield {
          type: "conversationId",
          conversationId: resolvedChatId,
        }
      }

      const chatbotFrontModel = resolveLegacyChatbotFrontModel(request.model)

      const legacyBody = {
        template_type: "chatPro",

        chat_id: Number(resolvedChatId),

        prompt,

        raw_user_input: lastUserMessage?.content ?? "",

        personalization: request.personalization,

        agent_mode: request.mode === "agents",

        realtime: request.mode !== "agents" && request.tools?.webSearch ? 1 : 0,

        chat_brand_voice: request.tools?.brandVoiceCompanyId || "",

        brand_voice_prod: request.tools?.brandVoiceProductId || "",

        skill_ids: request.tools?.skillIds?.join(",") || "",
        mentionUserIds: request.mentionUserIds || [],

        website_url: LEGACY_WEBSITE_URL,

        ...(lastUserMessage?.backendMessageId &&
        looksNumericId(String(lastUserMessage.backendMessageId))
          ? { retry_message_id: Number(lastUserMessage.backendMessageId) }
          : {}),

        ...(chatbotFrontModel
          ? { chatbot_front_model: chatbotFrontModel }
          : {}),

        ...attachmentPayload,
      }

      const stream = await openStreamResponse(
        "/dashboard/user/generator/generate-stream",

        legacyBody,

        signal,
      )

      let streamedText = ""

      for await (const event of streamResponse(stream, signal)) {
        if (
          event.type === "error" &&
          (event.code === "chat_not_found" ||
            /chat not found/i.test(event.message))
        ) {
          throw new ApiError(
            event.message,
            event.status || 404,
            event.code || "chat_not_found",
          )
        }

        if (event.type === "delta") {
          streamedText += event.text

          const replacementModelId =
            extractLegacyReplacementModelId(streamedText)

          if (replacementModelId) {
            throw new ApiError(
              "The selected model is unavailable. The provider suggested a replacement, but Garad will not switch models without your approval.",
              409,
              "selected_model_unavailable",
            )
          }
        }

        yield event
      }
    }

    try {
      yield* tryStream(false)
    } catch (error) {
      if (isLegacyChatNotFound(error)) {
        yield* tryStream(true)
      } else {
        if (
          error instanceof ApiError &&
          extractLegacyReplacementModelId(error.message)
        )
          throw new ApiError(
            "The selected model is unavailable. The provider suggested a replacement, but Garad will not switch models without your approval.",
            409,
            "selected_model_unavailable",
          )

        throw error
      }
    }
    if (request.tools?.webSearch) {
      yield {
        type: "tool",
        tool: {
          id: webSearchToolId,
          name: "web_search",
          label: "Web Search",
          status: "completed",
          detail: "Search complete",
        },
      }
    }
  }

  const toDataUrl = async (file: File): Promise<string> =>
    new Promise<string>((resolve, reject) => {
      const reader = new FileReader()

      reader.addEventListener("load", () => {
        const result = reader.result

        if (typeof result === "string") resolve(result)
        else
          reject(
            new ApiError(
              "Failed to encode upload.",
              0,
              "upload_encoding_failed",
            ),
          )
      })

      reader.addEventListener("error", () => {
        reject(new ApiError("Failed to read upload.", 0, "upload_read_failed"))
      })

      reader.readAsDataURL(file)
    })

  const uploadViaLegacy = async (
    input: UploadFileInput,
  ): Promise<UploadFileResult> => {
    input.onProgress?.(20)

    const data = await toDataUrl(input.file)

    input.onProgress?.(60)

    const payload = await requestJson<unknown>(
      ensureConfigured(),
      "/dashboard/user/ai-chat-pro/attachments",
      {
        method: "POST",

        body: {
          attachment_id: input.attachmentId,

          files: [
            {
              name: input.file.name,

              data,
            },
          ],
        },

        signal: input.signal,
      },
    )

    const object = toPlainObject(payload) || {}
    const files = Array.isArray(object.files) ? object.files : []
    const uploadedFile = toPlainObject(files[0]) || {}
    const path = typeof uploadedFile.path === "string" ? uploadedFile.path : ""

    if (!path.trim()) {
      throw new ApiError("Upload failed.", 422, "upload_failed", payload)
    }

    const name =
      typeof uploadedFile.name === "string"
        ? uploadedFile.name
        : input.file.name
    const referencePath = `${path}?name=${encodeURIComponent(name)}`

    uploadedAssetsByAttachmentId.set(input.attachmentId, {
      paths: [referencePath],

      names: [name],

      mimeType:
        typeof uploadedFile.mime_type === "string"
          ? uploadedFile.mime_type
          : input.file.type || "application/octet-stream",

      originalName: input.file.name,
    })

    input.onProgress?.(100)

    return {
      id: input.attachmentId,

      status: "ready",

      progress: 100,

      previewUrl: asAbsoluteUrl(path),
    }
  }

  const uploadFile = async (
    input: UploadFileInput,
  ): Promise<UploadFileResult> => {
    ensureConfigured()
    return uploadViaLegacy(input)
  }

  const createLegacyConversationRecord = async (
    payload: Partial<Conversation>,
  ): Promise<Conversation> => {
    const id =
      (payload.id && legacyConversationIds.get(payload.id)) ||
      (await createLegacyConversation(payload.id))
    const timestamp = new Date().toISOString()
    return {
      id,
      title:
        typeof payload.title === "string" && payload.title.trim()
          ? payload.title
          : "New conversation",
      messages: [],
      pinned: Boolean(payload.pinned),
      archived: Boolean(payload.archived),
      shared: Boolean(payload.shared),
      projectId: payload.projectId,
      modelId: payload.modelId,
      mode: payload.mode || "chat",
      createdAt: payload.createdAt || timestamp,
      updatedAt: payload.updatedAt || timestamp,
    }
  }

  const updateLegacyConversation = async (
    id: string,
    patch: Partial<Conversation>,
  ): Promise<Conversation> => {
    const resolvedId = await resolveConversationIdForLegacy(id)
    const legacyChatId = `chat_${resolvedId}`

    if (typeof patch.title === "string" && patch.title.trim()) {
      await requestJson(
        ensureConfigured(),
        "/dashboard/user/openai/chat/rename-chat",
        {
          method: "POST",
          body: {
            chat_id: legacyChatId,
            title: patch.title,
          },
        },
      )
    }

    if (typeof patch.pinned === "boolean") {
      await requestJson(
        ensureConfigured(),
        "/dashboard/user/openai/chat/pin-conversation",
        {
          method: "POST",
          body: {
            chat_id: legacyChatId,
            pinned: patch.pinned ? 1 : 0,
          },
        },
      )
    }

    if (Object.prototype.hasOwnProperty.call(patch, "projectId")) {
      if (patch.projectId != null && !looksNumericId(patch.projectId)) {
        throw new ApiError(
          "Garad folders require a real server project ID.",
          422,
          "invalid_project_id",
        )
      }
      await requestJson(
        ensureConfigured(),
        `/dashboard/user/ai-chat-pro/chats/${resolvedId}/move-to-folder`,
        {
          method: "POST",
          body: {
            folder_id: patch.projectId ? Number(patch.projectId) : null,
          },
        },
      )
    }

    const existing = await fetchLegacyConversation(resolvedId)
    return {
      ...existing,
      ...patch,
      id: resolvedId,
      updatedAt: new Date().toISOString(),
    }
  }

  return {
    baseUrl,

    configured,

    get usesLegacyChatBackend() {
      return usesLegacyChatBackend
    },

    get legacyUserId() {
      return legacyUserId
    },

    chatStream,

    async getVoiceAccess(reasoningModelId) {
      const payload = await requestJson<unknown>(
        baseUrl,
        `/dashboard/user/openai/chat/voice-status?reasoning_model=${encodeURIComponent(reasoningModelId)}`,
      )

      const access = unwrapEnvelope<Record<string, unknown>>(payload)

      return {
        available: Boolean(access.available),
        reason:
          access.reason === "plan" ||
          access.reason === "credits" ||
          access.reason === "model"
            ? access.reason
            : null,
        message:
          typeof access.message === "string" && access.message.trim()
            ? access.message
            : "Voice is currently unavailable.",
        reasoningModelId:
          typeof access.reasoningModelId === "string" &&
          access.reasoningModelId.trim()
            ? access.reasoningModelId
            : reasoningModelId,
        reasoningModelName:
          typeof access.reasoningModelName === "string"
            ? access.reasoningModelName
            : "",
        reasoningProvider:
          typeof access.reasoningProvider === "string"
            ? access.reasoningProvider
            : "OpenAI",
        transcriptionModel:
          typeof access.transcriptionModel === "string"
            ? access.transcriptionModel
            : "",
      }
    },

    async createRealtimeVoiceSession(signal) {
      const payload = await requestJson<unknown>(
        baseUrl,
        "/dashboard/user/realtime/chat/session",
        {
          method: "POST",
          body: {},
          signal,
          timeoutMs: 0,
        },
      )
      const session = toPlainObject(unwrapEnvelope<unknown>(payload))
      const ephemeralKey =
        typeof session?.ephemeral_key === "string"
          ? session.ephemeral_key.trim()
          : ""
      const model =
        typeof session?.model === "string" ? session.model.trim() : ""

      if (!ephemeralKey || !model) {
        throw new ApiError(
          "Garad could not create a Realtime Voice session.",
          502,
          "realtime_voice_session_invalid",
        )
      }

      return { ephemeralKey, model }
    },

    async checkRealtimeVoiceBalance(signal) {
      const payload = await requestJson<unknown>(
        baseUrl,
        "/dashboard/user/realtime/chat/checkBalance",
        {
          method: "POST",
          body: { onStart: false },
          signal,
          timeoutMs: 0,
        },
      )
      const balance = toPlainObject(unwrapEnvelope<unknown>(payload))
      const available = balance?.status === "success"

      return {
        available,
        message:
          typeof balance?.message === "string" && balance.message.trim()
            ? balance.message
            : "Garad Voice is unavailable because its realtime credit balance is insufficient.",
      }
    },

    async transcribeAudio(file, signal, reasoningModelId) {
      const body = new FormData()

      body.append("file", file, file.name)
      body.append("voice_context", "chat_voice")
      if (reasoningModelId) {
        body.append("reasoning_model", reasoningModelId)
      }

      const payload = await requestJson<unknown>(
        baseUrl,
        "/dashboard/user/openai/chat/transaudio",
        {
          method: "POST",

          body,

          signal,

          timeoutMs: 0,
        },
      )

      const transcript = unwrapEnvelope<unknown>(payload)

      return typeof transcript === "string" ? transcript : ""
    },

    async generateVoiceAudio(text, signal, reasoningModelId) {
      const body = new FormData()
      body.append(
        "speeches",
        JSON.stringify([
          {
            voice: "alloy",
            lang: "en-US",
            language: "en-US",
            pace: "tts-1",
            platform: "openai",
            break: "0",
            content: text,
          },
        ]),
      )
      body.append("from_api", "1")
      body.append("voice_context", "chat_voice")
      if (reasoningModelId) {
        body.append("reasoning_model", reasoningModelId)
      }

      const payload = await requestJson<unknown>(
        baseUrl,
        "/dashboard/user/openai/generate-speech",
        {
          method: "POST",
          body,
          signal,
          timeoutMs: 0,
        },
      )
      const result = toPlainObject(unwrapEnvelope<unknown>(payload))
      const audioPath =
        typeof result?.audioPath === "string" ? result.audioPath.trim() : ""

      if (!audioPath) {
        throw new ApiError(
          "Garad did not return generated voice audio.",
          502,
          "voice_audio_missing",
        )
      }

      return new URL(
        audioPath.replace(/^\/+/, ""),
        `${ensureConfigured()}/`,
      ).toString()
    },

    uploadFile,

    async getConversations(query) {
      if (usesLegacyChatBackend) {
        return fetchLegacyConversations(query)
      }

      try {
        return await list<Conversation>(
          "/api/conversations",
          {
            ...paginationToQuery(query),

            mode: query?.mode,

            archived: query?.archived,

            pinned: query?.pinned,

            project_id: query?.projectId,
          },
          LEGACY_HISTORY_TIMEOUT_MS,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        usesLegacyChatBackend = true
        return fetchLegacyConversations(query)
      }
    },

    async getConversation(id: string) {
      try {
        return await getOne<Conversation>(
          `/api/conversations/${id}`,
          LEGACY_HISTORY_TIMEOUT_MS,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        usesLegacyChatBackend = true
        return fetchLegacyConversation(id)
      }
    },

    async createConversation(payload: Partial<Conversation>) {
      if (usesLegacyChatBackend) {
        return createLegacyConversationRecord(payload)
      }

      try {
        return await createOne<Conversation>(
          "/api/conversations",
          payload,
          LEGACY_HISTORY_TIMEOUT_MS,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        usesLegacyChatBackend = true
        return createLegacyConversationRecord(payload)
      }
    },

    async updateConversation(id: string, patch: Partial<Conversation>) {
      if (usesLegacyChatBackend) {
        return updateLegacyConversation(id, patch)
      }

      try {
        return await patchOne<Conversation>(
          `/api/conversations/${id}`,
          patch,
          LEGACY_HISTORY_TIMEOUT_MS,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        usesLegacyChatBackend = true
        return updateLegacyConversation(id, patch)
      }
    },

    async deleteConversation(id: string) {
      try {
        await remove(`/api/conversations/${id}`, LEGACY_HISTORY_TIMEOUT_MS)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const mapped = legacyConversationIds.get(id)

        const resolvedId = mapped || id

        if (!looksNumericId(resolvedId)) {
          legacyConversationIds.delete(id)

          return
        }

        await requestJson(
          ensureConfigured(),
          "/dashboard/user/openai/chat/delete-chat",
          {
            method: "POST",

            body: {
              chat_id: `chat_${resolvedId}`,
            },
          },
        )

        legacyConversationIds.delete(id)
      }
    },

    async getConversationMessages(
      conversationId: string,
      query?: MessagesQuery,
    ) {
      if (usesLegacyChatBackend) {
        const legacyId =
          legacyConversationIds.get(conversationId) || conversationId
        const messages = looksNumericId(legacyId)
          ? await fetchLegacyConversationMessages(legacyId)
          : []

        return query?.role
          ? messages.filter((message) => message.role === query.role)
          : messages
      }

      try {
        const messages = await list<Message>(
          `/api/conversations/${conversationId}/messages`,
          {
            ...paginationToQuery(query),

            role: query?.role,
          },
          LEGACY_HISTORY_TIMEOUT_MS,
        )
        return attachAgentMentionReferences(messages)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const legacyId =
          legacyConversationIds.get(conversationId) || conversationId

        const messages = looksNumericId(legacyId)
          ? await fetchLegacyConversationMessages(legacyId)
          : []

        return query?.role
          ? messages.filter((message) => message.role === query.role)
          : messages
      }
    },

    async getMessage(id: string) {
      try {
        return await getOne<Message>(`/api/messages/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        return {
          id,

          role: "assistant",

          content: "",

          status: "completed",

          createdAt: timestamp,

          updatedAt: timestamp,

          attachmentIds: [],

          citations: [],

          artifacts: [],

          toolCalls: [],
        }
      }
    },

    async createMessage(conversationId: string, payload: CreateMessageInput) {
      try {
        return await createOne<Message>(
          `/api/conversations/${conversationId}/messages`,
          payload,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        return {
          id: payload.id || crypto.randomUUID(),

          role: payload.role,

          content: payload.content,

          status: payload.status || "completed",

          createdAt: timestamp,

          updatedAt: timestamp,

          attachmentIds: payload.attachmentIds || [],

          parentId: payload.parentId,

          citations: payload.citations || [],

          artifacts: payload.artifacts || [],

          toolCalls: payload.toolCalls || [],
        }
      }
    },

    async updateMessage(id: string, patch: UpdateMessageInput) {
      try {
        return await patchOne<Message>(`/api/messages/${id}`, patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        return {
          id,

          role: "assistant",

          content: typeof patch.content === "string" ? patch.content : "",

          status: patch.status || "completed",

          createdAt: timestamp,

          updatedAt: timestamp,

          attachmentIds: patch.attachmentIds || [],

          parentId: patch.parentId,

          citations: patch.citations || [],

          artifacts: patch.artifacts || [],

          toolCalls: patch.toolCalls || [],

          error: patch.error,

          feedback: patch.feedback,
          feedbackReason: patch.feedbackReason,
          feedbackDetails: patch.feedbackDetails,
        }
      }
    },

    async deleteMessage(id: string) {
      try {
        await remove(`/api/messages/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error
      }
    },

    async getProjects(query?: PaginationQuery) {
      try {
        return await list<Project>("/api/projects", paginationToQuery(query))
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return fetchLegacyProjects()
      }
    },

    async getProject(id: string) {
      try {
        return await getOne<Project>(`/api/projects/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const projects = await fetchLegacyProjects()

        const project = projects.find((item) => item.id === id)

        if (!project) throw new ApiError("Project not found.", 404)

        return project
      }
    },

    async createProject(payload: Partial<Project>) {
      try {
        return await createOne<Project>("/api/projects", payload)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const response = await requestJson<unknown>(
          ensureConfigured(),

          "/dashboard/user/ai-chat-pro/folders",

          {
            method: "POST",

            body: {
              name:
                (typeof payload.name === "string" && payload.name.trim()) ||
                "New project",
            },
          },
        )

        const object = toPlainObject(response)

        const folder = toPlainObject(object?.folder)
        if (!folder) {
          throw new ApiError(
            "Garad did not confirm the project creation.",
            502,
            "invalid_project_create_response",
            response,
          )
        }
        return mapLegacyProject(folder)
      }
    },

    async updateProject(id: string, patch: Partial<Project>) {
      try {
        return await patchOne<Project>(`/api/projects/${id}`, patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        if (typeof patch.name !== "string" || !patch.name.trim()) {
          throw new ApiError(
            "Garad chat projects currently support renaming only.",
            422,
            "unsupported_project_update",
          )
        }

        const response = await requestJson<unknown>(
          ensureConfigured(),
          `/dashboard/user/ai-chat-pro/folders/${id}`,
          {
            method: "PUT",
            body: { name: patch.name.trim() },
          },
        )

        const object = toPlainObject(response)
        const folder = toPlainObject(object?.folder)
        if (!folder) {
          throw new ApiError(
            "Garad did not confirm the project rename.",
            502,
            "invalid_project_update_response",
            response,
          )
        }
        return mapLegacyProject(folder)
      }
    },

    async deleteProject(id: string) {
      try {
        await remove(`/api/projects/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        await requestJson(
          ensureConfigured(),

          `/dashboard/user/ai-chat-pro/folders/${id}`,

          { method: "DELETE" },
        )
      }
    },

    async getLibrary(query?: LibraryQuery) {
      try {
        return await list<LibraryItem>("/api/library", {
          ...paginationToQuery(query),

          kind: query?.kind,

          sort: query?.sort,

          project_id: query?.projectId,
        })
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return []
      }
    },

    async getLibraryItem(id: string) {
      try {
        return await getOne<LibraryItem>(`/api/library/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        throw new ApiError("Library item not found.", 404)
      }
    },

    async updateLibraryItem(id: string, patch: Partial<LibraryItem>) {
      try {
        return await patchOne<LibraryItem>(`/api/library/${id}`, patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const createdAt = new Date().toISOString()

        const attachmentPatch = patch as Partial<Attachment>

        const hasAttachmentShape =
          "mimeType" in attachmentPatch ||
          "size" in attachmentPatch ||
          "progress" in attachmentPatch ||
          "previewUrl" in attachmentPatch

        if (hasAttachmentShape) {
          const rawStatus = attachmentPatch.status

          const status: Attachment["status"] =
            typeof rawStatus === "string" &&
            [
              "selected",

              "uploading",

              "uploaded",

              "processing",

              "ready",

              "failed",

              "cancelled",
            ].includes(rawStatus)
              ? rawStatus as Attachment["status"]
              : "ready"

          return {
            id,

            name: patch.name || "Library item",

            mimeType: attachmentPatch.mimeType || "application/octet-stream",

            size: attachmentPatch.size || 0,

            kind: patch.kind || "document",

            status,

            progress: attachmentPatch.progress || 100,

            previewUrl: attachmentPatch.previewUrl,

            error: attachmentPatch.error,

            projectId: patch.projectId,

            createdAt,

            updatedAt: createdAt,
          }
        }

        const artifactPatch = patch as Partial<Artifact>

        const artifactStatus: Artifact["status"] =
          artifactPatch.status === "creating" ||
          artifactPatch.status === "failed"
            ? artifactPatch.status
            : "ready"

        return {
          id,

          name: patch.name || "Library item",

          kind: patch.kind || "document",

          description: artifactPatch.description || "",

          status: artifactStatus,

          url: artifactPatch.url,

          projectId: patch.projectId,

          createdAt,

          updatedAt: createdAt,
        }
      }
    },

    async deleteLibraryItem(id: string) {
      try {
        const isAttachmentId =
          /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
            id,
          )
        await remove(
          isAttachmentId
            ? `/dashboard/user/ai-chat-pro/attachments/${encodeURIComponent(id)}`
            : `/api/library/${encodeURIComponent(id)}`,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error
      }
    },

    async getAgents(query?: PaginationQuery) {
      const legacyAgents = await fetchLegacyAgentsFromConversations()

      return paginateInMemory(legacyAgents, query)
    },

    async getAgent(id: string) {
      try {
        return await getOne<Agent>(`/api/agents/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const fromCache = legacyAgentCache.get(id)

        if (fromCache) return fromCache

        const discovered = await fetchLegacyAgentsFromConversations().catch(
          () => [],
        )

        const agent = discovered.find((item) => item.id === id)

        if (agent) return agent

        throw new ApiError("Agent not found.", 404)
      }
    },

    async createAgent(payload: Partial<Agent>) {
      try {
        return await createOne<Agent>("/api/agents", payload)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        const optimistic: Agent = {
          id: payload.id || crypto.randomUUID(),

          name: payload.name || "New Agent",

          description: payload.description || "",

          role: payload.role || "Assistant",

          goal: payload.goal || "",

          instructions: payload.instructions || "",

          model: payload.model || "gpt-4o-mini",

          tools: payload.tools || [],

          plugins: payload.plugins || [],

          knowledge: payload.knowledge || [],

          memory: payload.memory || "",

          permissions: payload.permissions || [],

          schedule: payload.schedule,

          status: payload.status || "draft",

          createdAt: payload.createdAt || timestamp,

          updatedAt: payload.updatedAt || timestamp,
        }

        try {
          const legacyPayload = buildLegacyWorkflowPayload(payload)

          const response = await requestJson<unknown>(
            ensureConfigured(),

            "/dashboard/user/ai-agent/workflows",

            {
              method: "POST",

              body: legacyPayload,
            },
          )

          const object = toPlainObject(response)

          const idValue = toPlainObject(object?.data)?.id ?? object?.id

          const created = mapLegacyWorkflowToAgent(
            {
              ...legacyPayload,

              id: idValue,

              status: "active",

              created_at: timestamp,

              updated_at: timestamp,
            },

            optimistic,
          )

          legacyAgentCache.set(created.id, created)

          return created
        } catch (legacyError) {
          if (!isMissingEndpointError(legacyError)) throw legacyError

          legacyAgentCache.set(optimistic.id, optimistic)

          return optimistic
        }
      }
    },

    async updateAgent(id: string, patch: Partial<Agent>) {
      try {
        return await patchOne<Agent>(`/api/agents/${id}`, patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        const cached = legacyAgentCache.get(id)

        const merged: Agent = {
          id,

          name: patch.name || cached?.name || "Agent",

          description: patch.description || cached?.description || "",

          role: patch.role || cached?.role || "Assistant",

          goal: patch.goal || cached?.goal || "",

          instructions: patch.instructions || cached?.instructions || "",

          model: patch.model || cached?.model || "gpt-4o-mini",

          tools: patch.tools || cached?.tools || [],

          plugins: patch.plugins || cached?.plugins || [],

          knowledge: patch.knowledge || cached?.knowledge || [],

          memory: patch.memory || cached?.memory || "",

          permissions: patch.permissions || cached?.permissions || [],

          schedule: patch.schedule || cached?.schedule,

          status: patch.status || cached?.status || "draft",

          createdAt: cached?.createdAt || patch.createdAt || timestamp,

          updatedAt: patch.updatedAt || timestamp,
        }

        if (looksNumericId(id)) {
          try {
            const legacyPayload = buildLegacyWorkflowPayload(merged)

            const response = await requestJson<unknown>(
              ensureConfigured(),

              `/dashboard/user/ai-agent/workflows/${id}`,

              {
                method: "PUT",

                body: legacyPayload,
              },
            )

            const statusValue = toPlainObject(toPlainObject(response)?.data)
              ?.status

            merged.status = mapWorkflowStatusToAgentStatus(
              statusValue ?? merged.status,
            )
          } catch (legacyError) {
            if (!isMissingEndpointError(legacyError)) throw legacyError
          }
        }

        legacyAgentCache.set(id, merged)

        return merged
      }
    },

    async deleteAgent(id: string) {
      try {
        await remove(`/api/agents/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        if (looksNumericId(id)) {
          await performWebAction(
            `/dashboard/user/ai-agent/workflows/${id}`,
            "DELETE",
          ).catch((legacyError) => {
            if (!isMissingEndpointError(legacyError)) throw legacyError
          })
        }
      }

      legacyAgentCache.delete(id)
    },

    async getAgentRuns(query?: PaginationQuery) {
      try {
        return await list<AgentRun>("/api/agent-runs", paginationToQuery(query))
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const agents =
          legacyAgentCache.size > 0
            ? [...legacyAgentCache.values()]
            : await fetchLegacyAgentsFromConversations().catch(() => [])

        if (agents.length === 0) return []

        const settled = await Promise.allSettled(
          agents.map((agent) => fetchLegacyWorkflowRuns(agent.id)),
        )

        const runs = settled

          .filter(
            (result): result is PromiseFulfilledResult<AgentRun[]> =>
              result.status === "fulfilled",
          )

          .flatMap((result) => result.value)

          .sort(
            (left, right) =>
              Date.parse(right.updatedAt || right.startedAt) -
              Date.parse(left.updatedAt || left.startedAt),
          )

        return paginateInMemory(runs, query)
      }
    },

    async getAgentRunsForAgent(agentId: string, query?: PaginationQuery) {
      try {
        return await list<AgentRun>(
          `/api/agents/${agentId}/runs`,
          paginationToQuery(query),
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const runs = await fetchLegacyWorkflowRuns(agentId).catch(() => [])

        return paginateInMemory(runs, query)
      }
    },

    async getAgentRun(id: string) {
      try {
        return await getOne<AgentRun>(`/api/agent-runs/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const agents =
          legacyAgentCache.size > 0
            ? [...legacyAgentCache.values()]
            : await fetchLegacyAgentsFromConversations().catch(() => [])

        for (const agent of agents) {
          const runs = await fetchLegacyWorkflowRuns(agent.id).catch(() => [])

          const found = runs.find((item) => item.id === id)

          if (found) return found
        }

        throw new ApiError("Agent run not found.", 404)
      }
    },

    async runAgent(id: string, payload: AgentRunInput) {
      try {
        return await createOneWithFallback<AgentRun>(
          [`/api/agents/${id}/runs`, `/api/agents/${id}/run`],

          payload,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        if (looksNumericId(id)) {
          try {
            await requestJson<unknown>(
              ensureConfigured(),

              "/dashboard/user/ai-agent/messages/send",

              {
                method: "POST",

                body: {
                  workflow_id: Number(id),

                  message: payload.task,
                },
              },
            )

            const runs = await fetchLegacyWorkflowRuns(id)

            const latest = runs[0]

            if (latest) {
              return {
                ...latest,

                agentId: id,

                task: payload.task || latest.task,
              }
            }
          } catch (legacyError) {
            if (!isMissingEndpointError(legacyError)) throw legacyError
          }
        }

        const timestamp = new Date().toISOString()

        return {
          id: crypto.randomUUID(),

          agentId: id,

          task: payload.task,

          state: "queued",

          progress: 10,

          sources: [],

          outputs: [],

          startedAt: timestamp,

          updatedAt: timestamp,
        }
      }
    },

    async createWorkflowFromPrompt(prompt: string) {
      const response = await requestJson<{
        status: string
        message?: string
        data?: AgentWorkflow
      }>(
        ensureConfigured(),
        "/dashboard/user/ai-agent/workflows/create-from-prompt",
        {
          method: "POST",
          body: { prompt },
        },
      )
      if (response.status !== "success" || !response.data) {
        throw new ApiError(
          response.message || "Workflow could not be saved.",
          422,
        )
      }
      return response.data
    },

    async getAgentWorkflows() {
      const response = await requestJson<{ data: AgentWorkflow[] }>(
        ensureConfigured(),
        "/dashboard/user/ai-agent/operations/workflows",
      )
      return response.data
    },

    async getWorkflowRuns() {
      const response = await requestJson<{ data: AgentWorkflowRun[] }>(
        ensureConfigured(),
        "/dashboard/user/ai-agent/operations/runs",
        { timeoutMs: 30000 },
      )
      return response.data
    },

    async getAgentConversationActivity(chatId) {
      const response = await requestJson<{ data: AgentWorkflowRun[] }>(
        ensureConfigured(),
        `/dashboard/user/ai-agent/operations/chats/${encodeURIComponent(chatId)}/activity`,
        { timeoutMs: 15000 },
      )
      return response.data
    },

    async cancelWorkflowRun(runId) {
      const response = await requestJson<{ data: AgentWorkflowRun }>(
        ensureConfigured(),
        `/dashboard/user/ai-agent/operations/runs/${encodeURIComponent(runId)}/cancel`,
        { method: "POST", body: {} },
      )
      return response.data
    },

    async getWorkflowApprovals() {
      const response = await requestJson<{ data: AgentWorkflowApproval[] }>(
        ensureConfigured(),
        "/dashboard/user/ai-agent/operations/approvals",
      )
      return response.data
    },

    async runWorkflow(id: string, idempotencyKey = crypto.randomUUID()) {
      const response = await requestJson<{ data: AgentWorkflowRun }>(
        ensureConfigured(),
        `/dashboard/user/ai-agent/operations/workflows/${encodeURIComponent(id)}/run`,
        {
          method: "POST",
          body: {},
          headers: { "Idempotency-Key": idempotencyKey },
        },
      )
      return response.data
    },

    async toggleWorkflowStatus(id: string) {
      const response = await requestJson<{
        data: { status: "active" | "paused" }
      }>(
        ensureConfigured(),
        `/dashboard/user/ai-agent/workflows/${encodeURIComponent(id)}/toggle-status`,
        { method: "PATCH", body: {} },
      )
      return response.data.status
    },

    async decideWorkflowApproval(
      id: string,
      decision: "approve" | "reject",
      comment?: string,
    ) {
      const response = await requestJson<{ data: AgentWorkflowRun }>(
        ensureConfigured(),
        `/dashboard/user/ai-agent/operations/approvals/${encodeURIComponent(id)}/decision`,
        { method: "POST", body: { decision, comment } },
      )
      return response.data
    },

    async stopAgent(id: string, runId?: string) {
      try {
        if (runId)
          return await createOneWithFallback<AgentRun>(
            [
              `/api/agent-runs/${runId}/cancel`,

              `/api/agent-runs/${runId}/stop`,

              `/api/agents/${id}/stop`,
            ],

            { runId },
          )

        return await createOneWithFallback<AgentRun>(
          [`/api/agents/${id}/stop`],
          { runId },
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        return {
          id: runId || crypto.randomUUID(),

          agentId: id,

          task: "",

          state: "cancelled",

          progress: 100,

          sources: [],

          outputs: [],

          startedAt: timestamp,

          updatedAt: timestamp,
        }
      }
    },

    async getApps(query?: AppsQuery) {
      try {
        return await listWithFallback<PluginApp>(
          ["/api/apps", "/api/plugins"],

          {
            ...paginationToQuery(query),

            installed: query?.installed,

            enabled: query?.enabled,

            kind: query?.kind,
          },
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return []
      }
    },

    async getApp(id: string) {
      try {
        return await getOneWithFallback<PluginApp>([
          `/api/apps/${id}`,
          `/api/plugins/${id}`,
        ])
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        throw new ApiError("App not found.", 404)
      }
    },

    async installApp(id: string) {
      try {
        return await createOneWithFallback<PluginApp>(
          [
            `/api/apps/${id}/install`,

            `/api/plugins/${id}/install`,

            `/api/apps/${id}/enable`,
          ],

          {},
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return {
          id,

          slug: id,

          name: id,

          kind: "app",

          status: "installed",

          installed: true,

          enabled: true,

          permissions: [],
        }
      }
    },

    async updateApp(id: string, patch: Partial<PluginApp>) {
      try {
        const payload =
          await requestWithFallback<ApiEnvelope<PluginApp> | PluginApp>(
            baseUrl,

            [`/api/apps/${id}`, `/api/plugins/${id}`],

            { method: "PATCH", body: patch },
          )

        return unwrapEnvelope<PluginApp>(payload)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return {
          id,

          slug: patch.slug || id,

          name: patch.name || id,

          description: patch.description,

          provider: patch.provider,

          category: patch.category,

          iconUrl: patch.iconUrl,

          kind: patch.kind || "app",

          status: patch.status || "enabled",

          installed: patch.installed ?? true,

          enabled: patch.enabled ?? true,

          permissions: patch.permissions || [],

          createdAt: patch.createdAt,

          updatedAt: patch.updatedAt,
        }
      }
    },

    async uninstallApp(id: string) {
      try {
        await removeWithFallback([`/api/apps/${id}`, `/api/plugins/${id}`])
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error
      }
    },

    async getConnectors(query?: PaginationQuery) {
      try {
        return await list<Connector>(
          "/api/connectors",
          paginationToQuery(query),
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return fetchLegacyConnectors()
      }
    },

    async getConnector(id: string) {
      try {
        return await getOne<Connector>(`/api/connectors/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const connectors = await fetchLegacyConnectors()

        const connector = connectors.find((item) => item.id === id)

        if (!connector) throw new ApiError("Connector not found.", 404)

        return connector
      }
    },

    async connectConnector(id: string) {
      try {
        return await createOneWithFallback<Connector>(
          [`/api/connectors/${id}/connect`, `/api/oauth/${id}/connect`],

          {},
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const connectors = await fetchLegacyConnectors()

        const connector = connectors.find((item) => item.id === id)

        if (!connector) throw new ApiError("Connector not found.", 404)

        return connector
      }
    },

    async disconnectConnector(id: string) {
      try {
        await requestWithFallback(
          baseUrl,
          [`/api/connectors/${id}/disconnect`],
          {
            method: "POST",

            body: {},
          },
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        let connectorId = legacyConnectorMetaByKey.get(id)?.connectorId

        if (!connectorId) {
          await fetchLegacyConnectors()

          connectorId = legacyConnectorMetaByKey.get(id)?.connectorId
        }

        if (connectorId) {
          await requestJson(
            ensureConfigured(),

            `/dashboard/user/ai-chat-pro/connectors/${connectorId}`,

            { method: "DELETE" },
          )

          return
        }

        await remove(`/api/connectors/${id}`)
      }
    },

    async toggleConnectorPause(id: string) {
      const connectorId = await resolveLegacyConnectorId(id)
      await requestJson<unknown>(
        ensureConfigured(),
        `/dashboard/user/ai-chat-pro/connectors/${encodeURIComponent(connectorId)}/toggle-pause`,
        { method: "POST", body: {} },
      )
      const connectors = await fetchLegacyConnectors()
      const updated = connectors.find((connector) => connector.id === id)
      if (!updated) {
        throw new ApiError("Connector not found after updating access.", 404, "connector_not_found")
      }
      return updated
    },

    async getConnectorRedirectUrl(id: string) {
      try {
        const payload = await requestWithFallback<ApiEnvelope<{
          url: string
        }> | { url: string } | string>(baseUrl, [
          `/api/connectors/${id}/redirect-url`,
          `/api/oauth/${id}/redirect`,
        ])

        if (typeof payload === "string") return payload

        const data = unwrapEnvelope<{ url: string }>(payload)

        if (typeof data.url === "string" && data.url.trim()) return data.url

        throw new ApiError("Connector redirect URL missing.", 500)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        if (!legacyConnectorMetaByKey.has(id)) {
          await fetchLegacyConnectors()
        }

        const preConsent = legacyConnectorMetaByKey.get(id)?.preConsentUrl

        if (preConsent) return asAbsoluteUrl(preConsent)

        return asAbsoluteUrl(
          `/dashboard/user/ai-chat-pro/connectors/pre-consent/${id}`,
        )
      }
    },

    async getConnectorAccess(id: string) {
      const connectorId = await resolveLegacyConnectorId(id)
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        `/dashboard/user/ai-chat-pro/connectors/${encodeURIComponent(connectorId)}/access`,
        { query: { json: 1 } },
      )
      const object = toPlainObject(payload)
      const access = toPlainObject(object?.access)
      const selected = toPlainObject(object?.selected)
      const options = Array.isArray(access?.options)
        ? access.options.flatMap((rawOption) => {
            const option = toPlainObject(rawOption)
            if (
              !option ||
              (typeof option.id !== "string" &&
                typeof option.id !== "number") ||
              typeof option.name !== "string"
            ) {
              return []
            }

            return [{
              id: String(option.id),
              name: option.name,
              meta: typeof option.meta === "string" ? option.meta : undefined,
            }]
          })
        : []
      const type = typeof access?.type === "string" ? access.type : "items"
      const selectedValues = Array.isArray(selected?.[type])
        ? (selected[type] as unknown[]).filter(
            (item): item is string => typeof item === "string",
          )
        : []

      return {
        connectorId: String(object?.connector_id ?? connectorId),
        connectorType: String(object?.connector_type ?? id),
        label: typeof object?.label === "string" ? object.label : "Resources",
        type,
        options,
        selected: selectedValues,
        recentOnly: selected?.recent_only === true,
      } satisfies ConnectorAccess
    },

    async updateConnectorAccess(id: string, selection: string[], recentOnly: boolean) {
      const connectorId = await resolveLegacyConnectorId(id)
      await requestJson<unknown>(
        ensureConfigured(),
        `/dashboard/user/ai-chat-pro/connectors/${encodeURIComponent(connectorId)}/access`,
        {
          method: "PUT",
          body: { selection, recent_only: recentOnly },
        },
      )
    },

    async getScheduledTasks(query?: PaginationQuery) {
      return fetchLegacyScheduledTasks(query)
    },

    async getScheduledTask(id: string) {
      try {
        return await getOne<ScheduledTask>(`/api/scheduled-tasks/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const tasks = await fetchLegacyScheduledTasks()

        const task = tasks.find((item) => item.id === id)

        if (task) return task

        throw new ApiError("Scheduled task not found.", 404)
      }
    },

    async createScheduledTask(payload: Partial<ScheduledTask>) {
      try {
        return await createOne<ScheduledTask>("/api/scheduled-tasks", payload)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const oneTime = payload.scheduleType === "one_time"
        if (oneTime && !payload.runAt) {
          throw new ApiError(
            "A date and time are required for a one-time scheduled task.",
            422,
            "missing_scheduled_run_at",
          )
        }
        if (!oneTime && !payload.cron) {
          throw new ApiError(
            "A valid repeat schedule is required for a recurring task.",
            422,
            "missing_scheduled_cron",
          )
        }

        const response = await requestJson<unknown>(
          ensureConfigured(),

          "/dashboard/user/openai/chat/pro/scheduled",

          {
            method: "POST",
            timeoutMs: 60_000,

            body: {
              title: payload.title || "Scheduled task",

              instructions: payload.prompt || payload.title || "",

              task_type: payload.taskType || "reminder",
              notification_enabled: payload.notificationEnabled ?? true,

              schedule_type: oneTime ? "one_time" : "recurring",

              run_at: oneTime ? payload.runAt : undefined,

              cron: oneTime ? undefined : payload.cron,

              timezone: payload.timezone || "UTC",
            },
          },
        )

        const object = toPlainObject(response)
        if (!object?.task) {
          throw new ApiError(
            "Garad did not confirm that the scheduled task was created.",
            502,
            "invalid_scheduled_task_response",
            response,
          )
        }
        return mapLegacyScheduledTask(object.task)
      }
    },

    async updateScheduledTask(id: string, patch: Partial<ScheduledTask>) {
      try {
        return await patchOne<ScheduledTask>(
          `/api/scheduled-tasks/${id}`,
          patch,
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        let responseTask: ScheduledTask | null = null
        const hasMetadata =
          patch.title !== undefined ||
          patch.prompt !== undefined ||
          patch.scheduleType !== undefined ||
          patch.schedule !== undefined ||
          patch.runAt !== undefined ||
          patch.cron !== undefined ||
          patch.timezone !== undefined ||
          patch.taskType !== undefined ||
          patch.notificationEnabled !== undefined
        if (hasMetadata) {
          const hasScheduleFields =
            patch.scheduleType !== undefined ||
            patch.schedule !== undefined ||
            patch.runAt !== undefined ||
            patch.cron !== undefined ||
            patch.timezone !== undefined
          const response = await requestJson<unknown>(
            ensureConfigured(),
            `/dashboard/user/openai/chat/pro/scheduled/${id}`,
            {
              method: "PATCH",
              body: {
                title: patch.title,
                instructions: patch.prompt,
                task_type: patch.taskType,
                notification_enabled: patch.notificationEnabled,
                ...(hasScheduleFields
                  ? {
                      ...(patch.scheduleType !== undefined
                        ? {
                            schedule_type:
                              patch.scheduleType === "one_time"
                                ? "one_time"
                                : "recurring",
                          }
                        : {}),
                      run_at: patch.runAt,
                      cron: patch.cron,
                      timezone: patch.timezone,
                    }
                  : {}),
              },
            },
          )
          const object = toPlainObject(response)
          if (!object?.task) {
            throw new ApiError(
              "Garad did not confirm the scheduled task update.",
              502,
              "invalid_scheduled_task_response",
              response,
            )
          }
          responseTask = mapLegacyScheduledTask(object.task)
        }

        if (typeof patch.enabled === "boolean") {
          const response = await requestJson<unknown>(
            ensureConfigured(),

            `/dashboard/user/openai/chat/pro/scheduled/${id}/status`,

            {
              method: "PATCH",

              body: {
                status: patch.enabled ? "active" : "paused",
              },
            },
          )

          const object = toPlainObject(response)
          if (!object?.task) {
            throw new ApiError(
              "Garad did not confirm the scheduled task status update.",
              502,
              "invalid_scheduled_task_response",
              response,
            )
          }
          responseTask = mapLegacyScheduledTask(object.task)
        }

        if (responseTask) return responseTask
        const current = (await fetchLegacyScheduledTasks()).find(
          (task) => task.id === id,
        )
        if (!current) throw new ApiError("Scheduled task not found.", 404)
        return current
      }
    },

    async deleteScheduledTask(id: string) {
      try {
        await remove(`/api/scheduled-tasks/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        await requestJson(
          ensureConfigured(),

          `/dashboard/user/openai/chat/pro/scheduled/${id}`,

          { method: "DELETE" },
        )
      }
    },

    async tickScheduledTasks() {
      const response = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/openai/chat/pro/scheduled/tick",
        { method: "POST", body: {}, timeoutMs: 30_000 },
      )
      const object = toPlainObject(response)
      return {
        delivered: Number(object?.delivered) || 0,
        started: Boolean(object?.started),
      }
    },

    async runScheduledTask(id: string) {
      try {
        return await createOne<ScheduledTask>(
          `/api/scheduled-tasks/${id}/run`,
          {},
        )
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const response = await requestJson<unknown>(
          ensureConfigured(),
          `/dashboard/user/openai/chat/pro/scheduled/${id}/run`,
          {
            method: "POST",
            body: {},
            headers: { "Idempotency-Key": crypto.randomUUID() },
          },
        )
        const object = toPlainObject(response)
        if (!object?.task) {
          throw new ApiError(
            "Garad did not confirm that the task execution started.",
            502,
            "invalid_scheduled_task_response",
            response,
          )
        }
        return mapLegacyScheduledTask(object.task)
      }
    },

    async getMemories(query?: PaginationQuery) {
      try {
        return await list<MemoryItem>("/api/memories", paginationToQuery(query))
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return fetchLegacyMemories(query)
      }
    },

    async getMemory(id: string) {
      try {
        return await getOne<MemoryItem>(`/api/memories/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const cached = legacyMemoryCache.get(id)

        if (cached) return cached

        throw new ApiError("Memory not found.", 404)
      }
    },

    async createMemory(payload: Partial<MemoryItem>) {
      try {
        return await createOne<MemoryItem>("/api/memories", payload)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        const optimistic: MemoryItem = {
          id: payload.id || crypto.randomUUID(),

          title: payload.title || "Memory",

          content: payload.content || "",

          enabled: payload.enabled ?? true,

          scope: payload.scope,

          projectId: payload.projectId,

          agentId: payload.agentId,

          createdAt: payload.createdAt || timestamp,

          updatedAt: payload.updatedAt || timestamp,
        }

        try {
          const response = await requestJson<unknown>(
            ensureConfigured(),

            "/dashboard/user/ai-agent/memory",

            {
              method: "POST",

              body: {
                memory: optimistic.content || optimistic.title,
              },
            },
          )

          const mapped = mapLegacyMemory(
            toPlainObject(response)?.memory,
            optimistic,
          )

          legacyMemoryCache.set(mapped.id, mapped)

          return mapped
        } catch (legacyError) {
          if (!isMissingEndpointError(legacyError)) throw legacyError

          legacyMemoryCache.set(optimistic.id, optimistic)

          return optimistic
        }
      }
    },

    async updateMemory(id: string, patch: Partial<MemoryItem>) {
      try {
        return await patchOne<MemoryItem>(`/api/memories/${id}`, patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        const timestamp = new Date().toISOString()

        const cached = legacyMemoryCache.get(id)

        const merged: MemoryItem = {
          id,

          title: patch.title || cached?.title || "Memory",

          content: patch.content || cached?.content || "",

          enabled: patch.enabled ?? cached?.enabled ?? true,

          scope: patch.scope || cached?.scope,

          projectId: patch.projectId || cached?.projectId,

          agentId: patch.agentId || cached?.agentId,

          createdAt: patch.createdAt || cached?.createdAt || timestamp,

          updatedAt: patch.updatedAt || timestamp,
        }

        if (looksNumericId(id)) {
          try {
            const response = await requestJson<unknown>(
              ensureConfigured(),

              `/dashboard/user/ai-agent/memory/${id}`,

              {
                method: "PUT",

                body: {
                  memory: merged.content,
                },
              },
            )

            const mapped = mapLegacyMemory(
              toPlainObject(response)?.memory,
              merged,
            )

            legacyMemoryCache.set(mapped.id, mapped)

            return mapped
          } catch (legacyError) {
            if (!isMissingEndpointError(legacyError)) throw legacyError
          }
        }

        legacyMemoryCache.set(id, merged)

        return merged
      }
    },

    async deleteMemory(id: string) {
      try {
        await remove(`/api/memories/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        if (looksNumericId(id)) {
          await performWebAction(
            `/dashboard/user/ai-agent/memory/${id}`,
            "DELETE",
          ).catch((legacyError) => {
            if (!isMissingEndpointError(legacyError)) throw legacyError
          })
        }
      }

      legacyMemoryCache.delete(id)
    },

    async getNotifications(query?: NotificationsQuery) {
      try {
        return await list<NotificationItem>("/api/notifications", {
          ...paginationToQuery(query),

          unread: query?.unread,

          type: query?.type,
        })
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return []
      }
    },

    async markNotificationRead(id: string) {
      try {
        return await patchOne<NotificationItem>(`/api/notifications/${id}`, {
          read: true,
        })
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return {
          id,

          type: "task_failed",

          title: "Notification",

          detail: "",

          read: true,

          createdAt: new Date().toISOString(),
        }
      }
    },

    async markAllNotificationsRead() {
      try {
        await createOne<unknown>("/api/notifications/read-all", {})
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error
      }
    },

    async deleteNotification(id: string) {
      try {
        await remove(`/api/notifications/${id}`)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error
      }
    },

    async searchMentionUsers(query: string) {
      const response = await requestJson<{ data: MentionCandidate[] }>(
        ensureConfigured(),
        `/api/mentions/search?q=${encodeURIComponent(query)}`,
      )
      return response.data
    },

    async sendMention(userId: string, message: string) {
      return createOne<NotificationItem>("/api/mentions", { userId, message })
    },

    async searchAgentShareRecipients(
      query: string,
      resourceType: AgentShareResourceType,
      resourceId: string,
      resourcePart?: "user" | "assistant",
    ) {
      const params = new URLSearchParams({
        q: query,
        resourceType,
        resourceId,
      })
      if (resourcePart) params.set("resourcePart", resourcePart)
      const response = await requestJson<{ data: AgentShareRecipient[] }>(
        ensureConfigured(),
        `/api/agent-shares/recipients?${params.toString()}`,
      )
      return response.data
    },

    async createAgentShare(input: {
      resourceType: AgentShareResourceType
      resourceId: string
      resourcePart?: "user" | "assistant"
      recipientId: string
      accessLevel: "view"
    }) {
      return createOne<AgentShare>("/api/agent-shares", input)
    },

    async getAgentShares(input: {
      resourceType: AgentShareResourceType
      resourceId: string
      resourcePart?: "user" | "assistant"
    }) {
      const params = new URLSearchParams({
        resourceType: input.resourceType,
        resourceId: input.resourceId,
      })
      if (input.resourcePart) params.set("resourcePart", input.resourcePart)
      const response = await requestJson<{ data: AgentShare[] }>(
        ensureConfigured(),
        `/api/agent-shares?${params.toString()}`,
      )
      return response.data
    },

    async revokeAgentShare(shareId: string) {
      await remove(`/api/agent-shares/${encodeURIComponent(shareId)}`)
    },

    async getSharedAgentResource(token: string) {
      return getOne<SharedAgentResource>(
        `/api/agent-shares/${encodeURIComponent(token)}`,
      )
    },

    async createCollaborationRequest(userId: string, message: string) {
      return createOne<{
        requestId: string
        status: "pending"
        notificationId: string
      }>(`/api/profile/${encodeURIComponent(userId)}/collaboration-requests`, {
        message,
      })
    },

    async decideCollaborationRequest(
      requestId: string,
      decision: "accepted" | "declined",
    ) {
      return createOne<{
        requestId: string
        status: "accepted" | "declined"
        decidedAt: string | null
      }>(`/api/collaboration-requests/${encodeURIComponent(requestId)}/decision`, {
        decision,
      })
    },

    async decideTeamWorkspaceInvitation(
      invitationId: string,
      decision: "accepted" | "declined",
    ) {
      return createOne<{
        invitationId: string
        workspaceId: string
        status: "accepted" | "declined"
      }>(
        `/api/workspaces/teams/invitations/${encodeURIComponent(invitationId)}/decision`,
        { decision },
      )
    },

    async getTeamWorkspaces() {
      return list<TeamWorkspace>("/api/workspaces/teams")
    },

    async createTeamWorkspace(name: string) {
      const result = await createOne<{ id: string } & TeamWorkspace>(
        "/api/workspaces/teams",
        { name },
      )
      return result
    },

    async inviteTeamWorkspaceMember(teamId: string, payload: { email: string; role: string }) {
      const result = await createOne<{ member: TeamWorkspaceMember }>(
        `/api/workspaces/teams/${encodeURIComponent(teamId)}/invitations`,
        payload,
      )
      return result.member
    },

    async updateTeamWorkspaceMember(
      teamId: string,
      memberId: string,
      payload: { role: string; status?: "active" | "waiting" },
    ) {
      const result = await patchOne<{ member: TeamWorkspaceMember }>(
        `/api/workspaces/teams/${encodeURIComponent(teamId)}/members/${encodeURIComponent(memberId)}`,
        payload,
      )
      return result.member
    },

    async removeTeamWorkspaceMember(teamId: string, memberId: string) {
      await remove(
        `/api/workspaces/teams/${encodeURIComponent(teamId)}/members/${encodeURIComponent(memberId)}`,
      )
    },

    async getAccount() {
      const seeded = takeBootstrapSeed<Account>("account")
      if (seeded) {
        usesLegacyChatBackend = true
        return seeded
      }
      try {
        return await getOne<Account>("/api/account")
      } catch (error) {
        if (!(error instanceof ApiError)) throw error

        let hasLegacySession = false

        try {
          hasLegacySession = await tryLegacySessionProbe()
        } catch (probeError) {
          if (
            probeError instanceof ApiError &&
            (probeError.status === 401 || probeError.status === 403)
          ) {
            throw probeError
          }
        }

        if (!hasLegacySession) throw error

        usesLegacyChatBackend = true
        return createFallbackAccount()
      }
    },

    async login(payload) {
      return await requestJson<unknown>(baseUrl, "/login", {
        method: "POST",
        body: payload,
      })
    },

    async register(payload) {
      return await requestJson<unknown>(baseUrl, "/register", {
        method: "POST",
        body: payload,
      })
    },

    async sendPasswordReset(email) {
      return await requestJson<unknown>(baseUrl, "/forgot-password", {
        method: "POST",
        body: { email },
      })
    },

    async verifyEmailOtp(otp) {
      return await requestJson<unknown>(baseUrl, "/verify-otp", {
        method: "POST",
        body: { otp },
      })
    },

    async verifyTwoFactor(code) {
      return await requestJson<unknown>(baseUrl, "/2fa/login", {
        method: "POST",
        body: { one_time_password: code },
      })
    },

    async getGuestChatStatus() {
      const response = await requestJson<unknown>(
        baseUrl,
        "/api/guest/chat-status",
      )
      const result = toPlainObject(response)
      if (
        !result ||
        typeof result.limit !== "number" ||
        typeof result.used !== "number" ||
        typeof result.remaining !== "number"
      ) {
        throw new ApiError(
          "Laravel returned an invalid guest chat status.",
          502,
          "invalid_guest_chat_status",
          response,
        )
      }
      return {
        limit: result.limit,
        used: result.used,
        remaining: result.remaining,
      }
    },

    async getAuthRequirements() {
      const response = await requestJson<unknown>(
        baseUrl,
        "/api/auth/requirements",
      )
      const result = toPlainObject(response)
      if (
        !result ||
        !Array.isArray(result.registrationRequiredFields) ||
        !result.registrationRequiredFields.every(
          (field) => typeof field === "string",
        )
      ) {
        throw new ApiError(
          "Laravel returned invalid sign-up requirements.",
          502,
          "invalid_auth_requirements",
          response,
        )
      }
      return {
        registrationRequiredFields: result.registrationRequiredFields,
      }
    },

    async logout() {
      await requestJson<void>(baseUrl, "/logout", { method: "POST" })
    },

    async updateAccount(patch: Partial<Account>) {
      try {
        return await patchOne<Account>("/api/account", patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return { ...createFallbackAccount(), ...patch }
      }
    },

    async updateProfile(input: ProfileUpdateInput) {
      const body = new FormData()
      body.append("_method", "PATCH")
      body.append("name", input.name)
      body.append("surname", input.surname)
      for (const key of [
        "username",
        "profileVisibility",
        "bio",
        "website",
        "phone",
        "country",
        "state",
        "city",
        "postal",
        "address",
        "old_password",
        "new_password",
        "new_password_confirmation",
      ] as const) {
        const value = input[key]
        if (typeof value === "string") body.append(key, value)
      }
      if (typeof input.allowMentions === "boolean")
        body.append("allowMentions", input.allowMentions ? "1" : "0")
      if (typeof input.allowCollaborationRequests === "boolean")
        body.append(
          "allowCollaborationRequests",
          input.allowCollaborationRequests ? "1" : "0",
        )
      if (input.avatar) body.append("avatar", input.avatar)
      const payload = await requestJson<ApiEnvelope<{ user: User }> | {
        user: User
      } | User>(baseUrl, "/api/account/profile", {
        method: "POST",
        body,
      })
      const result = unwrapEnvelope<{ user: User } | User>(payload)
      return "user" in result ? result.user : result
    },

    async getPublicProfile(username: string) {
      const payload = await requestJson<
        ApiEnvelope<{ user: PublicProfile }> | { user: PublicProfile }
      >(baseUrl, `/api/profile/${encodeURIComponent(username)}`)
      const result = unwrapEnvelope<{ user: PublicProfile }>(payload)
      return result.user
    },

    async toggleProfileFollow(userId: string, follow: boolean) {
      return requestJson<{ following: boolean; followersCount: number }>(
        baseUrl,
        `/api/profile/${encodeURIComponent(userId)}/follow`,
        { method: follow ? "POST" : "DELETE" },
      )
    },

    async updatePreferences(patch: Partial<Preferences>) {
      try {
        return await patchOne<Preferences>("/api/account/preferences", patch)
      } catch (error) {
        if (!isMissingEndpointError(error)) throw error

        return {
          ...createFallbackAccount().preferences,

          ...patch,

          notifications: {
            ...createFallbackAccount().preferences.notifications,

            ...(patch.notifications || {}),
          },
        }
      }
    },

    async getUsage() {
      try {
        return await getOne<Usage>("/api/usage")
      } catch (error) {
        if (!(error instanceof ApiError)) throw error

        const hasLegacySession = await tryLegacySessionProbe().catch(
          () => false,
        )

        if (!hasLegacySession) throw error

        return emptyUsage()
      }
    },

    async getComposerCapabilities() {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/api/composer/capabilities",
      )
      return unwrapEnvelope<ComposerCapabilities>(payload)
    },

    async searchSmartImages(query, messageId) {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/smart-image/search",
        {
          method: "POST",
          timeoutMs: 30_000,
          body: {
            query,
            message_id:
              messageId && looksNumericId(messageId)
                ? Number(messageId)
                : undefined,
          },
        },
      )
      const images = toPlainObject(payload)?.images
      if (!Array.isArray(images)) {
        throw new ApiError(
          "Image search returned an invalid response.",
          502,
          "smart_image_response_invalid",
        )
      }

      return images.flatMap((rawImage): SmartImageResult[] => {
        const image = toPlainObject(rawImage)
        if (
          !image ||
          typeof image.imageUrl !== "string" ||
          !isSafeHttpUrl(image.imageUrl)
        ) {
          return []
        }
        const link =
          typeof image.link === "string" && isSafeHttpUrl(image.link)
            ? image.link
            : ""
        const thumbnailUrl =
          typeof image.thumbnailUrl === "string" &&
          isSafeHttpUrl(image.thumbnailUrl)
            ? image.thumbnailUrl
            : image.imageUrl

        return [
          {
            title: typeof image.title === "string" ? image.title : "",
            imageUrl: image.imageUrl,
            thumbnailUrl,
            source: typeof image.source === "string" ? image.source : "",
            domain: typeof image.domain === "string" ? image.domain : "",
            link,
          },
        ]
      })
    },

    async getEntityDetails(entity, messageId) {
      if (!looksNumericId(messageId)) {
        throw new ApiError(
          "Entity details require a saved chat message.",
          400,
          "entity_message_id_invalid",
        )
      }
      const raw = await requestJson<Partial<EntityDetail>>(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro-entity-highlight/entity-detail",
        {
          method: "POST",
          body: {
            entity_text: entity.text,
            entity_type: entity.type,
            message_id: Number(messageId),
            context: entity.context_snippet,
          },
        },
      )
      const text = (value: unknown) =>
        typeof value === "string" ? value.trim() : ""
      const detail: EntityDetail = {
        title: text(raw?.title),
        subtitle: text(raw?.subtitle),
        description: text(raw?.description),
        image_url: null,
        image_search_query: text(raw?.image_search_query) || null,
        key_facts: Array.isArray(raw?.key_facts)
          ? raw.key_facts.map(text).filter(Boolean)
          : [],
        sections: Array.isArray(raw?.sections)
          ? raw.sections
              .map((section) => ({
                title: text(section?.title),
                content: text(section?.content),
              }))
              .filter((section) => section.content)
          : [],
        related_entities: [],
      }
      if (!detail.title) {
        throw new ApiError(
          "Entity details were empty.",
          502,
          "entity_detail_empty",
        )
      }
      return detail
    },

    async getEntityImage(query, entityText, messageId) {
      if (!looksNumericId(messageId)) {
        throw new ApiError(
          "Entity images require a saved chat message.",
          400,
          "entity_message_id_invalid",
        )
      }
      return requestJson<{
        image_url: string | null
        image_source: string
        image_domain: string
        image_link: string
      }>(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro-entity-highlight/entity-image",
        {
          method: "POST",
          body: {
            query,
            entity_text: entityText,
            message_id: Number(messageId),
          },
        },
      )
    },

    async getComposerPrompts() {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/api/composer/prompts",
      )
      return unwrapListEnvelope<ComposerPrompt>(payload)
    },

    async getComposerBrandProducts(companyId: string) {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        `/api/composer/brand-products/${encodeURIComponent(companyId)}`,
      )
      return unwrapListEnvelope<BrandProduct>(payload)
    },

    async getComposerSkills(search?: string) {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/skills",
        { query: { tab: "all", search } },
      )
      const object = toPlainObject(unwrapEnvelope<unknown>(payload))
      return Array.isArray(object?.skills)
        ? object.skills.flatMap((rawSkill) => {
            const skill = toPlainObject(rawSkill)
            if (
              !skill ||
              (typeof skill.id !== "string" && typeof skill.id !== "number") ||
              typeof skill.name !== "string" ||
              typeof skill.slug !== "string"
            ) {
              return []
            }
            return [
              {
                id: String(skill.id),
                name: skill.name,
                slug: skill.slug,
                description:
                  typeof skill.description === "string"
                    ? skill.description
                    : "",
                is_added: skill.is_added === true,
                auto_use: skill.auto_use === true,
              } satisfies ComposerSkill,
            ]
          })
        : []
    },

    async addComposerSkill(id: string) {
      await requestJson(
        ensureConfigured(),
        `/dashboard/user/skills/add/${encodeURIComponent(id)}`,
        { method: "POST", body: {} },
      )
    },

    async exportDocument(format, input) {
      const chatId = Number(
        legacyConversationIds.get(input.conversationId || "") ||
          input.conversationId,
      )
      return createOne<Artifact>(`/api/documents/${format}`, {
        title: input.title,
        content: input.content,
        chat_id: Number.isFinite(chatId) && chatId > 0 ? chatId : undefined,
      })
    },

    async startPresentation(input) {
      const payload = await requestJson<{ generationId?: string }>(
        ensureConfigured(),
        "/api/presentations",
        {
          method: "POST",
          body: {
            content: input.content,
            title: input.title,
            slide_count: input.slideCount,
          },
        },
      )
      if (!payload?.generationId)
        throw new Error("Presentation could not be started.")
      return { generationId: payload.generationId }
    },

    async pollPresentation(generationId) {
      const payload = await requestJson<{
        status?: string
        message?: string
        transient?: boolean
        artifact?: Artifact
      }>(
        ensureConfigured(),
        `/api/presentations/${encodeURIComponent(generationId)}`,
      )
      if (payload?.status === "completed" && payload.artifact)
        return { state: "ready" as const, artifact: payload.artifact }
      if (payload?.status === "failed")
        return {
          state: "failed" as const,
          message: payload.message || "Presentation failed.",
        }
      return {
        state: "processing" as const,
        transient: payload?.transient === true,
      }
    },

    async getPresentationStatus(generationId) {
      try {
        const payload = await requestJson<{
          ok?: boolean
          message?: string
        }>(
          ensureConfigured(),
          `/dashboard/user/openai/chat/pro/garad-presentation-status/${encodeURIComponent(generationId)}`,
        )
        const message = String(payload?.message || "")
        const url = /\]\(((?:https?:\/\/|\/)[^)\s]+)\)/.exec(message)?.[1]
        if (url) return { state: "ready" as const, url, message }
        if (/completed successfully/i.test(message))
          return {
            state: "failed" as const,
            message: "Presentation completed without a downloadable file.",
          }
        return { state: "processing" as const, message }
      } catch (error) {
        if (error instanceof ApiError && error.status === 422)
          return { state: "failed" as const, message: error.message }
        throw error
      }
    },

    async getVideoStatus(videoId) {
      try {
        const payload = await requestJson<{
          ok?: boolean
          message?: string
        }>(
          ensureConfigured(),
          `/dashboard/user/openai/chat/pro/garad-video-status/${encodeURIComponent(videoId)}`,
        )
        const message = String(payload?.message || "")
        const url = /\]\(((?:https?:\/\/|\/)[^)\s]+)\)/.exec(message)?.[1]
        if (url) return { state: "ready" as const, url, message }
        if (/completed successfully/i.test(message))
          return {
            state: "failed" as const,
            message: "Video generation completed without a playable video URL.",
          }
        return { state: "processing" as const, message }
      } catch (error) {
        if (error instanceof ApiError && error.status === 422)
          return { state: "failed" as const, message: error.message }
        throw error
      }
    },

    async saveComposerCanvas(messageId: string, content: string) {
      await requestJson(ensureConfigured(), "/tiptap-content-store", {
        method: "POST",
        body: { message_id: messageId, content, type: "output" },
      })
    },

    async getPlans() {
      const plansBaseUrl =
        baseUrl ||
        (typeof window !== "undefined"
          ? `${window.location.origin}${/^(.*?)(?:\/(?:chat|app|plans|contact-sales)(?:\/|$))/.exec(window.location.pathname)?.[1] || ""}`
          : null)

      const payload =
        await requestWithFallback<ApiListEnvelope<Plan> | ApiEnvelope<Plan[]> | Plan[]>(
          plansBaseUrl,
          ["/api/subscriptions/plans", "/api/plans"],
          { timeoutMs: 90_000 },
        )

      return unwrapListEnvelope<Plan>(payload)
    },

    async getSubscription() {
      return getOneWithFallback<Subscription>([
        "/api/subscription",

        "/api/subscriptions/current",
      ])
    },

    async changeSubscription(planId: string) {
      return createOneWithFallback<Subscription>(
        ["/api/subscriptions/change", "/api/subscription/change"],

        { planId },
      )
    },

    async getPaymentMethods() {
      return listWithFallback<PaymentMethod>([
        "/api/payments/methods",
        "/api/payment-methods",
      ])
    },

    async startCheckout(input: CheckoutInput) {
      return createOneWithFallback<CheckoutResult>(
        ["/api/payments/checkout", "/api/subscriptions/checkout"],

        input,
      )
    },

    async submitSalesInquiry(input: SalesInquiry) {
      return requestJson<unknown>(ensureConfigured(), "/api/contact-sales", {
        method: "POST",
        body: input,
      })
    },

    async requestEnterprisePlan() {
      return createOneWithFallback<unknown>(
        [
          "/api/subscriptions/enterprise-request",
          "/api/subscription/enterprise-request",
        ],

        { planId: "enterprise" },
      )
    },

    async cancelSubscription() {
      return createOneWithFallback<Subscription>(
        ["/api/subscription/cancel", "/api/subscriptions/cancel"],

        {},
      )
    },

    async resumeSubscription() {
      return createOneWithFallback<Subscription>(
        ["/api/subscription/resume", "/api/subscriptions/resume"],

        {},
      )
    },

    async getModels() {
      const seededModels = takeBootstrapSeed<ModelDescriptor[]>("models")
      if (seededModels) return seededModels
      try {
        return await list<ModelDescriptor>("/api/models")
      } catch (error) {
        if (
          error instanceof ApiError &&
          (error.status === 401 || error.status === 403)
        ) {
          throw error
        }

        const chatProModels = await fetchLegacyChatProModels()

        if (chatProModels.length > 0) return chatProModels

        try {
          const payload = await requestJson<unknown>(
            ensureConfigured(),

            "/dashboard/user/ai-agent/workflows/available-models",
          )

          const groups = toArrayData(payload)

          const models = groups.flatMap((group) => {
            const groupObject = toPlainObject(group) || {}

            const groupLabel =
              typeof groupObject.label === "string" && groupObject.label.trim()
                ? groupObject.label
                : "Model"

            const rows = Array.isArray(groupObject.models)
              ? groupObject.models
              : []

            return rows

              .map((row) => {
                const item = toPlainObject(row) || {}

                const id =
                  typeof item.value === "string" && item.value.trim()
                    ? item.value
                    : typeof item.id === "string" && item.id.trim()
                      ? item.id
                      : ""

                if (!id) return null

                const displayName =
                  typeof item.label === "string" && item.label.trim()
                    ? item.label
                    : id

                const descriptor: ModelDescriptor = {
                  id,

                  displayName,

                  description: `${groupLabel} model`,

                  capabilities: ["chat"],

                  planRequirement: "free",

                  availability: "available",
                }

                return descriptor
              })

              .filter((item): item is ModelDescriptor => Boolean(item))
          })

          const byId = new Map<string, ModelDescriptor>()

          models.forEach((model) => byId.set(model.id, model))

          const normalizedModels = [...byId.values()]

          return normalizedModels
        } catch {
          return []
        }
      }
    },

    async getImageModels() {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro/image-models",
        { timeoutMs: 30000 },
      )
      const rows = unwrapListEnvelope<unknown>(payload)

      return rows.flatMap((row) => {
        const model = toPlainObject(row)
        if (
          !model ||
          typeof model.id !== "string" ||
          typeof model.displayName !== "string" ||
          typeof model.description !== "string" ||
          typeof model.provider !== "string" ||
          typeof model.imageGenerator !== "string" ||
          typeof model.promptField !== "string" ||
          !["free", "pro", "business"].includes(
            String(model.planRequirement),
          ) ||
          !["available", "beta", "unavailable"].includes(
            String(model.availability),
          )
        ) {
          return []
        }

        const referenceMode =
          model.referenceMode === "image_src" ||
          model.referenceMode === "image_src_array"
            ? model.referenceMode
            : undefined

        return [
          {
            id: model.id,
            displayName: model.displayName,
            description: model.description,
            provider: model.provider,
            imageGenerator: model.imageGenerator,
            falAiModel:
              typeof model.falAiModel === "string"
                ? model.falAiModel
                : undefined,
            promptField: model.promptField,
            referenceMode,
            planRequirement:
              model.planRequirement as "free" | "pro" | "business",
            availability:
              model.availability as "available" | "beta" | "unavailable",
            locked: model.locked === true,
            requiredPlan:
              typeof model.requiredPlan === "string"
                ? model.requiredPlan
                : null,
          } satisfies ImageModelDescriptor,
        ]
      })
    },

    imageProxyUrl(url, download) {
      return buildUrl(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro/image-proxy",
        {
          url,
          download: download ? "1" : undefined,
        },
      )
    },

    async socialRequest(method, path, body, signal) {
      return requestJson(
        ensureConfigured(),
        `/dashboard/user/ai-chat-pro/social${path}`,
        { method, body, signal, timeoutMs: 120000 },
      )
    },

    async getAudioHistory() {
      const baseUrl = ensureConfigured()
      const payload = await requestJson<unknown>(
        baseUrl,
        "/dashboard/user/ai-audio/history",
      )
      const rows = Array.isArray(payload) ? payload : []
      return rows.flatMap((row) => {
        const item = toPlainObject(row)
        const audioUrl = typeof item?.audioUrl === "string" ? item.audioUrl : ""
        const kind = item?.kind
        if (
          !audioUrl ||
          (kind !== "song" && kind !== "voiceover" && kind !== "speech")
        )
          return []
        return [
          {
            id: String(item?.id ?? audioUrl),
            recordId:
              typeof item?.recordId === "number" ? item.recordId : undefined,
            kind,
            source:
              item?.source === "video-editor" ||
              item?.source === "legacy-music" ||
              item?.source === "legacy-tts"
                ? item.source
                : undefined,
            title:
              typeof item?.title === "string"
                ? item.source === "video-editor" && item.kind === "song"
                  ? item.title.replace(/^AI Music - /, "")
                  : item.title
                : "Generated audio",
            audioUrl: resolveAudioUrl(baseUrl, audioUrl),
            createdAt:
              typeof item?.createdAt === "string" ? item.createdAt : null,
            text: typeof item?.text === "string" ? item.text : undefined,
          } satisfies AudioStudioItem,
        ]
      })
    },

    async getStudioVoiceOptions() {
      const baseUrl = ensureConfigured()
      const payload = toPlainObject(
        unwrapEnvelope<unknown>(
          await requestJson<unknown>(
            baseUrl,
            "/dashboard/user/video-editor/ai/voice-models",
          ),
        ),
      )
      const voices = Array.isArray(payload?.elevenlabs_voices)
        ? payload.elevenlabs_voices.flatMap((voice) => {
            const item = toPlainObject(voice)
            const id =
              typeof item?.voice_id === "string" ? item.voice_id.trim() : ""
            const name = typeof item?.name === "string" ? item.name.trim() : id
            return id ? [{ id, name: name || id }] : []
          })
        : []
      return {
        provider:
          typeof payload?.provider === "string" ? payload.provider : "openai",
        voices,
      }
    },

    async getStudioMusicModels() {
      const payload = toPlainObject(
        unwrapEnvelope<unknown>(
          await requestJson<unknown>(
            ensureConfigured(),
            "/dashboard/user/video-editor/ai/music-models",
          ),
        ),
      )
      if (!Array.isArray(payload?.models)) {
        throw new ApiError(
          "Garad returned an invalid music-model list.",
          502,
          "invalid_music_models",
        )
      }
      return payload.models.flatMap((raw) => {
        const model = toPlainObject(raw)
        if (
          typeof model?.id !== "string" ||
          typeof model.name !== "string" ||
          typeof model.provider !== "string"
        )
          return []
        const outputFormats = Array.isArray(model.outputFormats)
          ? model.outputFormats.filter(
              (format): format is "mp3" | "wav" =>
                format === "mp3" || format === "wav",
            )
          : ["mp3" as const]
        return [
          {
            id: model.id,
            name: model.name,
            description:
              typeof model.description === "string" ? model.description : "",
            provider: model.provider,
            supportsDuration: model.supportsDuration === true,
            supportsLyrics: model.supportsLyrics === true,
            supportsImages: model.supportsImages === true,
            supportsVideo: model.supportsVideo === true,
            outputFormats,
          },
        ]
      })
    },

    getStudioVideoEditorUrl() {
      return baseUrl
        ? new URL("dashboard/user/video-editor", `${baseUrl}/`).toString()
        : null
    },

    async generateStudioSpeech(input) {
      const baseUrl = ensureConfigured()
      const payload = toPlainObject(
        unwrapEnvelope<unknown>(
          await requestJson<unknown>(
            baseUrl,
            "/dashboard/user/video-editor/ai/generate-voice",
            {
              method: "POST",
              body: {
                voice: input.voice,
                text: input.content,
                model: input.model,
                lang: input.language,
              },
              timeoutMs: 0,
            },
          ),
        ),
      )
      const media = toPlainObject(payload?.media)
      const audioPath = typeof media?.url === "string" ? media.url.trim() : ""
      if (!audioPath)
        throw new ApiError(
          "Garad did not return generated speech audio.",
          502,
          "audio_missing",
        )
      return {
        id: String(media?.id ?? `speech-${Date.now()}`),
        recordId: typeof media?.id === "number" ? media.id : undefined,
        kind: input.kind,
        source: "video-editor",
        title:
          typeof media?.original_filename === "string"
            ? media.original_filename
            : input.title || "Generated voice-over",
        audioUrl: resolveAudioUrl(baseUrl, audioPath),
        createdAt:
          typeof media?.created_at === "string"
            ? media.created_at
            : new Date().toISOString(),
        text: input.content,
      }
    },

    async generateStudioMusic(input) {
      const baseUrl = ensureConfigured()
      const form = new FormData()
      form.append("prompt", input.prompt)
      form.append("title", input.title)
      form.append("model", input.model)
      form.append("instrumental", input.instrumental ? "1" : "0")
      form.append("output_format", input.outputFormat)
      if (input.style) form.append("style", input.style)
      if (input.duration) form.append("duration", String(input.duration))
      if (input.lyrics) form.append("lyrics", input.lyrics)
      input.images.forEach((image) => form.append("images[]", image))
      if (input.video) form.append("video", input.video)
      const payload = toPlainObject(
        unwrapEnvelope<unknown>(
          await requestJson<unknown>(
            baseUrl,
            "/dashboard/user/video-editor/ai/generate-music",
            {
              method: "POST",
              body: form,
              timeoutMs: 0,
            },
          ),
        ),
      )
      const media = toPlainObject(payload?.media)
      const audioPath = typeof media?.url === "string" ? media.url.trim() : ""
      if (!audioPath)
        throw new ApiError(
          "Garad did not return generated song audio.",
          502,
          "audio_missing",
        )
      return {
        id: String(media?.id ?? `song-${Date.now()}`),
        recordId: typeof media?.id === "number" ? media.id : undefined,
        kind: "song",
        source: "video-editor",
        title:
          typeof media?.original_filename === "string"
            ? media.original_filename.replace(/^AI Music - /, "")
            : input.title || "Generated song",
        audioUrl: resolveAudioUrl(baseUrl, audioPath),
        createdAt:
          typeof media?.created_at === "string"
            ? media.created_at
            : new Date().toISOString(),
      }
    },

    async deleteStudioMusic(id, source) {
      if (source === "video-editor") {
        await requestJson(
          ensureConfigured(),
          `/dashboard/user/video-editor/media/${id}`,
          { method: "DELETE" },
        )
        return
      }
      await requestJson(
        ensureConfigured(),
        `/dashboard/user/ai-audio/music/${id}`,
        {
          method: "DELETE",
        },
      )
    },

    async socialUpload(kind, file) {
      const form = new FormData()
      form.append(kind === "image" ? "upload_image" : "upload_video", file)
      const payload = toPlainObject(
        await requestJson<unknown>(
          ensureConfigured(),
          `/dashboard/user/social-media/upload/${kind}`,
          { method: "POST", body: form, timeoutMs: 0 },
        ),
      )
      const url = payload?.url
      if (typeof url !== "string" || !url) {
        throw new ApiError("The upload did not return a file URL.", 422)
      }
      return url
    },
    async getVideoModels() {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro/video-models",
        { timeoutMs: 30000 },
      )
      return (Array.isArray(payload) ? payload : []) as VideoModelDescriptor[]
    },

    async getVideoHistory() {
      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro/video-history",
        { timeoutMs: 30000 },
      )
      return (Array.isArray(payload) ? payload : []) as VideoJob[]
    },

    async getVideoJob(id) {
      return requestJson<VideoJob>(
        ensureConfigured(),
        `/dashboard/user/ai-chat-pro/video-status/${id}`,
        { timeoutMs: 60000 },
      )
    },

    async createVideo(form) {
      const payload = toPlainObject(
        await requestJson<unknown>(
          ensureConfigured(),
          "/dashboard/user/ai-video-pro",
          {
            method: "POST",
            body: form,
            timeoutMs: 0,
          },
        ),
      )
      const id = Number(payload?.video_id)
      if (
        !payload ||
        payload.success === false ||
        !Number.isFinite(id) ||
        id <= 0
      ) {
        throw new ApiError(
          typeof payload?.message === "string" && payload.message.trim()
            ? payload.message
            : "Garad did not accept the video request.",
          422,
          "video_create_failed",
          payload,
        )
      }
      return { videoId: id, message: String(payload.message || "") }
    },

    async deleteVideos(ids) {
      await requestJson(
        ensureConfigured(),
        "/dashboard/user/ai-video-pro-bulk-delete",
        {
          method: "POST",
          body: { ids },
        },
      )
    },

    videoFileUrl(id) {
      return buildUrl(
        ensureConfigured(),
        "/dashboard/user/ai-chat-pro/video-file",
        {
          id: String(id),
        },
      )
    },

    async generateImage(input) {
      const form = new FormData()
      const size =
        input.model.imageGenerator === "stable_diffusion"
          ? input.size === "1536x1024"
            ? "1536x640"
            : input.size === "1024x1536"
              ? "640x1536"
              : "1024x1024"
          : input.size
      const prompt = [
        input.prompt,
        input.style ? `Art style: ${input.style.replace(/[_-]/g, " ")}.` : "",
        input.lighting ? `Lighting: ${input.lighting.replace(/_/g, " ")}.` : "",
        input.mood ? `Mood: ${input.mood.replace(/_/g, " ")}.` : "",
      ]
        .filter(Boolean)
        .join(" ")
      form.set("post_type", "ai_image_generator")
      form.set("image_generator", input.model.imageGenerator)
      form.set("description", prompt)
      form.set(input.model.promptField, prompt)
      form.set("image_number_of_images", String(input.count))
      form.set("size", size)
      form.set("quality", input.quality)
      form.set("image_style", input.style)
      form.set("image_lighting", input.lighting)
      form.set("image_mood", input.mood)
      form.set("mood", input.mood)
      form.set("negative_prompt", input.negativePrompt)

      if (input.model.falAiModel) {
        form.set("fal_ai_model", input.model.falAiModel)
      }

      if (input.model.imageGenerator === "stable_diffusion") {
        form.set("type", input.reference ? "image-to-image" : "text-to-image")
        form.set("stable_description", prompt)
        form.set("image_resolution", size)
        const stableStylePresets: Record<string, string> = {
          "3d_render": "3d-model",
          anime: "anime",
          cartoon: "comic-book",
          cyberpunk: "neon-punk",
          line: "line-art",
          pixel: "pixel-art",
          realistic: "photographic",
        }
        const stableStyle = stableStylePresets[input.style] || ""
        form.set("style_preset", stableStyle)
      } else if (input.model.id.startsWith("gpt-image-")) {
        form.set("stable_description", prompt)
      }

      if (input.reference) {
        const key =
          input.model.referenceMode === "image_src_array"
            ? "image_src[]"
            : "image_src"
        form.append(key, input.reference, input.reference.name)
      }

      const payload = await requestJson<unknown>(
        ensureConfigured(),
        "/dashboard/user/openai/generate",
        {
          method: "POST",
          body: form,
          timeoutMs: 0,
        },
      )
      const result = toPlainObject(unwrapEnvelope<unknown>(payload))
      if (result?.status !== "success" || !Array.isArray(result.images)) {
        const message =
          typeof result?.message === "string" && result.message.trim()
            ? result.message
            : "Garad did not return generated images."
        throw new ApiError(
          message,
          502,
          "image_generation_result_missing",
          payload,
        )
      }

      const images = result.images.flatMap((rawImage) => {
        const image = toPlainObject(rawImage)
        const url =
          typeof image?.output === "string"
            ? image.output
            : typeof image?.url === "string"
              ? image.url
              : ""
        if (!image || !url.trim()) return []

        return [
          {
            id: coerceStringId(image.id),
            url: asAbsoluteUrl(url),
            prompt:
              typeof image.input === "string" ? image.input : input.prompt,
            name:
              typeof image.slug === "string"
                ? image.slug
                : typeof image.nameOfImage === "string"
                  ? image.nameOfImage
                  : "Generated image",
          },
        ]
      })

      if (images.length === 0) {
        throw new ApiError(
          "Garad reported success but returned no image URL.",
          502,
          "image_url_missing",
          payload,
        )
      }
      return images
    },
  }
}

export const createApiClient = (): ApiClient =>
  createApiClientFromBaseUrl(
    normalizeBaseUrl(
      import.meta.env.VITE_GARAD_API_URL || resolveRuntimeBaseUrl(),
    ),
  )
