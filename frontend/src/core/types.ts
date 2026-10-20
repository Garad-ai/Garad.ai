export type Id = string

export type GenerationStatus = "idle" | "queued" | "thinking" | "tool_call" | "streaming" | "completed" | "stopped" | "error"

export type AuthStatus = "loading" | "authenticated" | "unauthenticated" | "expired" | "forbidden"

export type FileStatus = "selected" | "uploading" | "uploaded" | "processing" | "ready" | "failed" | "cancelled"

export type FileKind = "document" | "pdf" | "image" | "spreadsheet" | "presentation" | "video" | "generated"

export type ToolCall = {
  id: Id
  name: string
  label: string
  status: "queued" | "running" | "completed" | "failed" | "waiting_for_approval" | "cancelled"
  detail?: string
  approvalId?: Id
}

export type Citation = {
  id: Id
  title: string
  url: string
  source: string
}

export type Attachment = {
  id: Id
  name: string
  mimeType: string
  size: number
  kind: FileKind
  status: FileStatus
  progress: number
  url?: string
  downloadUrl?: string
  previewUrl?: string
  error?: string
  projectId?: Id
  createdAt: string
  updatedAt?: string
}

export type Artifact = {
  id: Id
  name: string
  kind: FileKind
  mimeType?: string
  extension?: string
  size?: number
  description: string
  status: "creating" | "ready" | "failed"
  generationId?: string
  progressMessage?: string
  url?: string
  downloadUrl?: string
  previewUrl?: string | null
  slideCount?: number | null
  projectId?: Id
  createdAt: string
  updatedAt?: string
}

export type Message = {
  id: Id
  role: "user" | "assistant" | "system"
  content: string
  status: GenerationStatus
  createdAt: string
  updatedAt: string
  attachmentIds: Id[]
  attachments?: Attachment[]
  citations: Citation[]
  artifacts: Artifact[]
  toolCalls: ToolCall[]
  smartImages?: SmartImageResult[]
  smartImageError?: string
  entities?: EntityHighlight[]
  mentions?: MessageMention[]
  parentId?: Id
  backendMessageId?: Id
  error?: string
  progress?: string
  feedback?: "like" | "dislike"
  feedbackReason?: string
  feedbackDetails?: string
}

export type MessageMention = {
  userId: Id | null
  username: string
}

export type SmartImageResult = {
  title: string
  imageUrl: string
  thumbnailUrl: string
  source: string
  domain: string
  link: string
}

export type EntityHighlight = {
  text: string
  type: string
  confidence: number
  occurrence_index: number
  context_snippet: string
}

export type EntityDetail = {
  title: string
  subtitle: string
  description: string
  image_url: string | null
  image_search_query: string | null
  image_source?: string
  image_domain?: string
  image_link?: string
  key_facts: string[]
  sections: Array<{
    title: string
    content: string
  }>
  related_entities: Array<{
    text: string
    type: string
  }>
}

export type Conversation = {
  id: Id
  title: string
  messages: Message[]
  modelId?: string
  pinned: boolean
  archived: boolean
  shared: boolean
  projectId?: Id
  lastMessageAt?: string
  mode: "chat" | "agents"
  createdAt: string
  updatedAt: string
}

export type Project = {
  id: Id
  name: string
  description: string
  instructions: string
  memoryEnabled: boolean
  memory: string
  agentIds: Id[]
  sourceIds: Id[]
  createdAt: string
  updatedAt?: string
}

export type MemoryScope = "user" | "project" | "agent"

export type MemoryItem = {
  id: Id
  title: string
  content: string
  enabled: boolean
  scope?: MemoryScope
  projectId?: Id
  agentId?: Id
  createdAt: string
  updatedAt: string
}

export type ConnectorStatus = "not_connected" | "not_installed" | "installing" | "installed" | "connecting" | "connected" | "paused" | "permission_denied" | "reconnect" | "disconnected"

export type ConnectorPermissions = {
  read: string[]
  write: string[]
  delete: string[]
  externalActions: string[]
  granted?: string[]
}

export type ConnectorAccessOption = {
  id: Id
  name: string
  meta?: string
}

export type ConnectorAccess = {
  connectorId: Id
  connectorType: string
  label: string
  type: string
  options: ConnectorAccessOption[]
  selected: string[]
  recentOnly: boolean
}

export type Connector = {
  id: Id
  name: string
  provider: string
  description?: string
  categories?: string[]
  featured?: boolean
  installed?: boolean
  status: ConnectorStatus
  paused?: boolean
  scopes: string[]
  permissions?: ConnectorPermissions
}

export type PluginAppKind = "plugin" | "app"

export type PluginAppStatus = "available" | "installing" | "installed" | "enabled" | "disabled" | "error"

export type PluginApp = {
  id: Id
  slug: string
  name: string
  description?: string
  provider?: string
  category?: string
  iconUrl?: string
  kind: PluginAppKind
  status: PluginAppStatus
  installed: boolean
  enabled: boolean
  permissions: string[]
  createdAt?: string
  updatedAt?: string
}

export type ScheduledTaskRecurrence = "one_time" | "daily" | "weekly" | "monthly" | "custom"

export type ScheduledTaskStatus = "idle" | "queued" | "running" | "paused" | "completed" | "failed"

export type ScheduledTaskHistory = {
  id: Id
  startedAt: string
  completedAt?: string
  status: "started" | "completed" | "failed"
  deliveryStatus?: "pending" | "delivered" | "skipped" | "failed"
  deliveryError?: string
  deliverySkipReason?: "notification_disabled" | "no_result"
  result?: string
  error?: string
  durationMs?: number
  model?: string
  provider?: string
  credits?: number
}

export type ScheduledTask = {
  id: Id
  title: string
  prompt?: string
  schedule: string
  scheduleType?: ScheduledTaskRecurrence
  timezone?: string
  taskType?: "reminder" | "agent_task" | "monitor"
  notificationEnabled?: boolean
  runAt?: string
  cron?: string
  agentId?: Id
  enabled: boolean
  nextRun: string
  lastRun?: string
  status?: ScheduledTaskStatus
  lastRunStatus?: "completed" | "failed" | "running" | "queued"
  history?: ScheduledTaskHistory[]
}

export type ProfileVisibility = "public" | "private" | "connections"

export type PublicProfile = {
  id: Id
  name: string
  username: string
  profileVisibility: ProfileVisibility
  bio: string
  website: string
  avatarUrl: string
  allowMentions: boolean
  allowCollaborationRequests: boolean
  followersCount: number
  followingCount: number
  isFollowing: boolean
}

export type MentionCandidate = {
  id: Id
  name: string
  username: string
  avatarUrl: string
}

export type TeamWorkspaceMember = {
  id: Id
  userId: Id | null
  name: string | null
  email: string | null
  role: "owner" | "admin" | "manager" | "member" | "approver" | "viewer" | string
  status: "active" | "waiting" | string
  joinedAt: string | null
}

export type TeamWorkspace = {
  id: Id
  name: string
  role: string
  isOwner: boolean
  canManageMembers: boolean
  seatLimit: number
  usedSeats: number
  members: TeamWorkspaceMember[]
}

export type User = {
  id: Id
  name: string
  givenName?: string
  surname?: string
  email: string
  username?: string | null
  profileVisibility?: ProfileVisibility
  bio?: string | null
  website?: string | null
  publicProfileUrl?: string | null
  allowMentions?: boolean
  allowCollaborationRequests?: boolean
  type?: string
  isSuperAdmin?: boolean
  avatarUrl?: string
  phone?: string
  country?: string
  state?: string
  city?: string
  postal?: string
  address?: string
}

export type ProfileUpdateInput = {
  name: string
  surname: string
  username?: string
  profileVisibility?: ProfileVisibility
  bio?: string
  website?: string
  allowMentions?: boolean
  allowCollaborationRequests?: boolean
  phone?: string
  country?: string
  state?: string
  city?: string
  postal?: string
  address?: string
  avatar?: File
  old_password?: string
  new_password?: string
  new_password_confirmation?: string
}

export type Subscription = {
  id: Id
  planId: string
  status: "active" | "trialing" | "past_due" | "cancelled"
  renewsAt?: string
  billingProvider: "none" | "stripe" | "paddle" | "manual"
}

export type UsageCounter = Record<"used" | "limit", number>

export type Usage = {
  messages: UsageCounter
  agentRuns: UsageCounter
  storageBytes: UsageCounter
}

export type EntitlementKey = "messages" | "storage" | "agents" | "agent_runs" | "scheduled_tasks" | "plugins" | "voice" | "image_generation" | "video_generation" | "research" | "data_analysis"

export type Entitlements = Record<EntitlementKey, number | boolean>

export type Preferences = {
  theme: "light" | "dark" | "system"
  language: "English" | "Somali"
  defaultMode: "chat" | "agents"
  defaultModel: string
  reducedMotion: boolean
  responseTraits: string[]
  customInstructions: string
  notifications: {
    tasks: boolean
    artifacts: boolean
    tips: boolean
  }
}

export type Account = {
  user: User | null
  subscription: Subscription | null
  authStatus: AuthStatus
  sessionExpiresAt?: string
  usage: Usage
  entitlements?: Partial<Entitlements>
  preferences: Preferences
  memoryEnabled: boolean
  memories: MemoryItem[]
}

export type Plan = {
  id: string
  name: string
  monthlyPrice: number
  description: string
  features: string[]
  entitlements: Partial<Entitlements>
  yearlyMonthlyPrice?: number
  tokens?: number | null
  models?: PlanModel[]
  modelGroups?: PlanModelGroup[]
  flagships?: PlanFlagship[]
  credits?: number | null
  tools?: PlanTool[]
  agents?: string[]
  popular?: boolean
  contactSales?: boolean
  team?: boolean
  rank?: number
}

export type PaymentMethod = {
  id: string
  name: string
  enabled: boolean
  requiresPhone?: boolean
  description?: string
}

export type SalesInquiry = {
  companySize: string
  companyName: string
  firstName: string
  lastName: string
  email: string
  phone: string
  interest: string
  message: string
}

export type CheckoutInput = {
  planId: string
  seats: number
  method: string
  phone?: string
}

export type CheckoutResult = {
  status: "paid" | "pending" | "redirect" | "failed"
  redirectUrl?: string
  message?: string
  reference?: string
}

export type PlanModel = {
  name: string
  multiplier?: number
}

export type PlanModelGroup = {
  key: string
  label: string
  providers: {
    name: string
    models: {
      name: string
      credits?: number | null
      unlimited?: boolean
      cost?: number
      perMonth?: number | null
      tokens?: number | null
    }[]
  }[]
}

export type PlanFlagship = {
  provider: string
  name: string
  cost: number
  credits?: number | null
}

export type PlanTool = {
  key: "image" | "video" | "audio" | "social" | string
  label: string
  included: boolean
  detail?: string
}

export type ModelCapability = "chat" | "vision" | "tools" | "reasoning" | "research" | "data_analysis" | "image_generation" | "video_generation" | string & {}

export type ModelAvailability = "available" | "beta" | "unavailable"

export type ModelDescriptor = {
  id: Id
  displayName: string
  description: string
  provider?: string
  capabilities: ModelCapability[]
  planRequirement: "free" | "pro" | "business"
  availability: ModelAvailability
  locked?: boolean
  requiredPlan?: string | null
}

export type ImageModelDescriptor = {
  id: Id
  displayName: string
  description: string
  provider: string
  imageGenerator: string
  falAiModel?: string
  promptField: string
  referenceMode?: "image_src" | "image_src_array"
  planRequirement: "free" | "pro" | "business"
  availability: ModelAvailability
  locked?: boolean
  requiredPlan?: string | null
}

export type AgentExecutionState = "queued" | "planning" | "running" | "tool_call" | "waiting_for_approval" | "paused" | "completed" | "failed" | "cancelled" | "blocked"

export type AgentStatus = "draft" | "active" | "paused" | "archived" | "template" | "featured"

export type AgentSchedule = {
  enabled: boolean
  recurrence: ScheduledTaskRecurrence
  timezone: string
  expression: string
}

export type Agent = {
  id: Id
  name: string
  description: string
  role: string
  goal: string
  instructions: string
  model: string
  tools: string[]
  plugins: string[]
  knowledge: string[]
  memory: string
  permissions: string[]
  schedule?: AgentSchedule
  status: AgentStatus
  createdAt: string
  updatedAt: string
}

export type AgentRun = {
  id: Id
  agentId: Id
  task: string
  state: AgentExecutionState
  currentTask?: string
  currentTool?: string
  progress: number
  sources: Citation[]
  outputs: Artifact[]
  startedAt: string
  updatedAt: string
  error?: string
}

export type AgentWorkflow = {
  id: Id
  name: string
  description?: string | null
  status: "active" | "paused" | "archived"
  triggerType: string
  triggerConfig?: Record<string, unknown>
  steps: Array<Record<string, unknown>>
  latestRun?: AgentWorkflowRun | null
  createdAt?: string | null
}

export type AgentWorkflowRunStep = {
  id: Id
  stepId?: string | null
  index: number
  type: string
  label?: string
  summary?: string
  status: "pending" | "running" | "completed" | "waiting_for_approval" | "failed" | "rejected" | "cancelled"
  input?: Record<string, unknown>
  output?: Record<string, unknown>
  error?: string | null
  approvalId?: Id | null
  startedAt?: string | null
  completedAt?: string | null
}

export type AgentWorkflowRun = {
  id: Id
  workflowId: Id
  workflowName: string
  messageId?: Id | null
  status: "running" | "cancelling" | "pending_approval" | "completed" | "failed" | "cancelled"
  startedAt?: string | null
  completedAt?: string | null
  error?: string | null
  steps: AgentWorkflowRunStep[]
}

export type AgentWorkflowApproval = {
  id: Id
  workflowId: Id
  workflowName: string
  runId: Id
  stepId: Id
  action: string
  payload: Record<string, unknown>
  requestedAt?: string | null
  status: "pending" | "approved" | "rejected"
}

export type NotificationType = "agent_completed" | "scheduled_task_completed" | "task_failed" | "approval_required" | "plugin_disconnected" | "file_ready" | "usage_warning" | "mention" | "collaboration_request" | "collaboration_response" | "workspace_invitation" | "resource_shared"

export type NotificationItem = {
  id: Id
  type: NotificationType
  title: string
  detail: string
  read: boolean
  createdAt: string
  actorId?: Id | null
  actorUsername?: string | null
  collaborationRequestId?: Id | null
  teamInvitationId?: Id | null
  workspaceId?: Id | null
  workspaceName?: string | null
  decision?: "accepted" | "declined" | null
  resourceUrl?: string | null
}

export type AgentShareResourceType = "message" | "conversation" | "task"

export type AgentShareRecipient = MentionCandidate

export type AgentShare = {
  id: Id
  resourceType: AgentShareResourceType
  resourceId: Id
  recipientId: Id
  recipient: string
  accessLevel: "view"
  status: "active" | "revoked" | "expired"
  createdAt: string | null
  revokedAt?: string | null
  expiresAt?: string | null
  url?: string
}

export type SharedAgentResource = {
  resourceType: AgentShareResourceType
  resource: {
    id: Id
    type: AgentShareResourceType
    part?: "user" | "assistant"
    title?: string
    name?: string
    status?: string
    startedAt?: string | null
    completedAt?: string | null
    content?: string
    messages?: Array<{
      id: Id
      role: "user" | "assistant"
      author: string
      content: string
      createdAt: string | null
    }>
    steps?: Array<{ index: number; type: string; status: string }>
    deliverables?: Array<{ name: string; url: string }>
  }
  owner: { id: Id; name: string; username: string | null }
  accessLevel: "view"
}

export type LibrarySort = "newest" | "oldest" | "name_asc" | "name_desc" | "kind"

export type LibraryItem = Attachment | Artifact

export type VoiceState = "idle" | "starting" | "listening" | "user-speaking" | "processing" | "responding" | "complete" | "unavailable" | "speaking" | "interrupted"

export type WorkspaceState = {
  account: Account
  conversations: Conversation[]
  activeConversationId: Id | null
  attachments: Attachment[]
  library: LibraryItem[]
  projects: Project[]
  connectors: Connector[]
  scheduledTasks: ScheduledTask[]
  agents: Agent[]
  agentRuns: AgentRun[]
  models: ModelDescriptor[]
  notifications: NotificationItem[]
}

export type ChatMessageInput = Pick<Message, "role" | "content" | "attachmentIds" | "backendMessageId">

export type ChatRequest = {
  conversationId: string
  model: string
  mode: "chat" | "agents"
  guest?: boolean
  messages: ChatMessageInput[]
  projectId?: string
  personalization?: {
    language: "English" | "Somali"
    responseTraits: string[]
    customInstructions: string
  }
  tools?: {
    webSearch?: boolean
    deepResearch?: boolean
    brandVoiceCompanyId?: string
    brandVoiceProductId?: string
    skillIds?: string[]
  }
  mentionUserIds?: Id[]
}

export type ComposerCapabilities = {
  webSearch: {
    available: boolean
    provider: string
  }
  deepResearch: {
    available: boolean
    allowed: boolean
  }
  canvas: { available: boolean }
  promptLibrary: { available: boolean }
  skills: {
    available: boolean
    allowed: boolean
  }
  brandVoice: {
    available: boolean
    companies: Array<{
      id: string
      name: string
      toneOfVoice: string
    }>
  }
}

export type ComposerPrompt = {
  id: string
  title: string
  prompt: string
  favorite: boolean
  shared: boolean
}

export type ComposerSkill = {
  id: string
  name: string
  slug: string
  description: string
  is_added: boolean
  auto_use: boolean
}

export type BrandProduct = {
  id: string
  name: string
  type: number
}

interface StreamEventMap {
  conversationId: { conversationId: Id }
  legacyMessage: { messageId: string }
  status: { status: GenerationStatus }
  progress: { message: string }
  delta: { text: string }
  tool: { tool: ToolCall }
  citation: { citation: Citation }
  artifact: { artifact: Artifact }
  imageSearch: {
    query: string
    messageId?: string
  }
  entityHighlights: { entities: EntityHighlight[] }
  done: { usage?: Partial<Usage> }
  error: {
    message: string
    code?: string
    status?: number
  }
}

export type StreamEvent = {
  [Kind in keyof StreamEventMap]: { type: Kind } & StreamEventMap[Kind]
}[keyof StreamEventMap]

export type ApiEnvelope<T,> = {
  data: T
  message?: string
}

export type ApiListEnvelope<T,> = {
  data: T[]
  meta?: Record<string, unknown>
  message?: string
}
