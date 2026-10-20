import {
  useCallback,
  useEffect,
  useMemo,
  useReducer,
  useRef,
  useState,
} from "react"

import {
  ApiError,
  createApiClient,
  removeLegacyResponsePreferences,
} from "./api"

import { accountFixture, initialWorkspace, planCatalog } from "./fixtures"

import {
  getChatCapableModels,
  resolveDefaultChatModelId,
} from "./modelCapabilities"

import { createChatTransport } from "./transport"

import { chatErrorSummary } from "./normalizeResponse"

import { createAttachment, uploadFile } from "./uploads"

import type {
  Agent,
  AgentRun,
  Account,
  Attachment,
  CheckoutInput,
  SalesInquiry,
  ChatRequest,
  Connector,
  ConnectorStatus,
  Conversation,
  MemoryItem,
  Message,
  ModelDescriptor,
  NotificationItem,
  Project,
  ProfileUpdateInput,
  Preferences,
  Plan,
  ScheduledTask,
  StreamEvent,
  Subscription,
  WorkspaceState,
} from "./types"

const PLANS_CACHE_KEY = "garad-plans-cache-v1"

const isCachedPlans = (value: unknown): value is Plan[] =>
  Array.isArray(value) &&
  value.length > 0 &&
  value.every(
    (plan) =>
      plan !== null &&
      typeof plan === "object" &&
      typeof plan.id === "string" &&
      typeof plan.name === "string" &&
      typeof plan.monthlyPrice === "number" &&
      typeof plan.description === "string" &&
      Array.isArray(plan.features),
  )

const readCachedPlans = (): Plan[] | null => {
  if (typeof window === "undefined") return null

  try {
    const value = window.localStorage.getItem(PLANS_CACHE_KEY)
    if (!value) return null

    const parsed: unknown = JSON.parse(value)
    if (!isCachedPlans(parsed)) {
      window.localStorage.removeItem(PLANS_CACHE_KEY)
      return null
    }

    return parsed
  } catch (error) {
    console.warn("Unable to read cached Garad plans", error)
    return null
  }
}

const cachePlans = (plans: Plan[]): void => {
  if (typeof window === "undefined") return

  try {
    window.localStorage.setItem(PLANS_CACHE_KEY, JSON.stringify(plans))
  } catch (error) {
    console.warn("Unable to cache Garad plans", error)
  }
}

const mergePlanCatalog = (remote: Plan[]): Plan[] => {
  const merged = remote
    .filter((plan) => plan && plan.id)
    .map((plan, index) => {
      const fallback = planCatalog.find((item) => item.id === plan.id)

      return {
        ...fallback,
        ...plan,
        features: plan.features || fallback?.features || [],
        entitlements: {
          ...fallback?.entitlements,
          ...plan.entitlements,
        },
        monthlyPrice: Number(plan.monthlyPrice) || 0,
        rank: plan.rank ?? fallback?.rank ?? index,
      } as Plan
    })
    .sort((a, b) => (a.rank ?? 0) - (b.rank ?? 0))

  // Free and Enterprise are not paid DB plans, keep their fixtures.
  const extras = planCatalog.filter(
    (item) =>
      (item.monthlyPrice === 0 &&
        !merged.some((plan) => plan.monthlyPrice === 0)) ||
      (item.contactSales && !merged.some((plan) => plan.contactSales)),
  )

  return [
    ...extras.filter((item) => item.monthlyPrice === 0),
    ...merged,
    ...extras.filter((item) => item.contactSales),
  ].map((plan, index) => ({ ...plan, rank: index }))
}

type IdPayload<Value = string> = { id: Value }

type ConversationIdPayload = { conversationId: string }

interface ActionMap {
  HYDRATE: { state: WorkspaceState }

  SET_MODELS: { models: ModelDescriptor[] }

  SET_PROJECTS: { projects: Project[] }

  SET_AUTH_STATUS: {
    status: WorkspaceState["account"]["authStatus"]

    clearUser?: boolean
  }

  SET_ACTIVE: IdPayload<string | null>

  ADD_CONVERSATION: { conversation: Conversation }

  REMAP_CONVERSATION_ID: {
    fromId: string
    conversation: Conversation
  }

  MERGE_CONVERSATIONS: { conversations: Conversation[] }

  SET_CONVERSATION_MESSAGES: {
    id: string
    messages: Message[]
  }

  UPDATE_CONVERSATION: IdPayload & { patch: Partial<Conversation> }

  DELETE_CONVERSATION: IdPayload

  ADD_MESSAGE: ConversationIdPayload & { message: Message }

  UPDATE_MESSAGE: ConversationIdPayload & IdPayload & {
    patch: Partial<Message>
  }

  DELETE_MESSAGE: ConversationIdPayload & IdPayload

  TRUNCATE_AFTER: ConversationIdPayload & IdPayload

  ADD_ATTACHMENTS: { attachments: Attachment[] }

  UPDATE_ATTACHMENT: IdPayload & { patch: Partial<Attachment> }

  REMOVE_ATTACHMENT: IdPayload

  UPDATE_LIBRARY_ITEM: IdPayload & {
    patch: Partial<WorkspaceState["library"][number]>
  }

  DELETE_LIBRARY_ITEM: IdPayload

  UPDATE_ACCOUNT: { patch: Partial<WorkspaceState["account"]> }

  UPDATE_PREFERENCES: {
    patch: Partial<WorkspaceState["account"]["preferences"]>
  }

  SET_MEMORIES: { memories: MemoryItem[] }

  UPDATE_PROJECT: IdPayload & { patch: Partial<Project> }

  ADD_PROJECT: { project: Project }

  DELETE_PROJECT: IdPayload

  UPSERT_CONNECTOR: { connector: Connector }

  UPDATE_CONNECTOR: IdPayload & {
    status: ConnectorStatus
    patch?: Partial<Connector>
  }

  SET_AGENTS: { agents: Agent[] }

  UPSERT_AGENT: { agent: Agent }

  DELETE_AGENT: IdPayload

  UPSERT_AGENT_RUN: { run: AgentRun }

  UPDATE_SCHEDULE: IdPayload & { patch: Partial<ScheduledTask> }

  TOGGLE_SCHEDULE: IdPayload

  ADD_SCHEDULE: { task: ScheduledTask }

  DELETE_SCHEDULE: IdPayload

  SET_NOTIFICATIONS: { notifications: NotificationItem[] }

  ADD_NOTIFICATION: { notification: NotificationItem }

  MARK_NOTIFICATION_READ: IdPayload

  CLEAR_NOTIFICATIONS: Record<never, never>
}

type Action = {
  [Kind in keyof ActionMap]: { type: Kind } & ActionMap[Kind]
}[keyof ActionMap]

const createNotification = (
  type: NotificationItem["type"],

  title: string,

  detail: string,
): NotificationItem => ({
  id: crypto.randomUUID(),

  type,

  title,

  detail,

  read: false,

  createdAt: new Date().toISOString(),
})

const activeConversationStorageKey = (userId: string): string =>
  `garad-chat-active:${userId}`

const conversationMessagesStorageKey = (
  userId: string,
  conversationId: string,
): string => `garad-chat-messages:${userId}:${conversationId}`

const conversationListStorageKey = (userId: string): string =>
  `garad-chat-list:${userId}`

const GUEST_STORAGE_ID = "guest"

const storageOwnerIdForConversation = (
  conversationId: string | null | undefined,
  account: WorkspaceState["account"],
  legacyUserId: string | undefined,
  authenticatedUserId: string | undefined,
): string | undefined => {
  if (conversationId?.startsWith("guest-")) return GUEST_STORAGE_ID

  return (
    account.user?.id ||
    legacyUserId ||
    authenticatedUserId ||
    (account.authStatus === "unauthenticated" ? GUEST_STORAGE_ID : undefined)
  )
}

const readActiveConversationId = (
  userId: string | undefined,
): string | null => {
  if (!userId || typeof window === "undefined") return null

  try {
    return window.localStorage.getItem(activeConversationStorageKey(userId))
  } catch {
    return null
  }
}

const isStoredMessage = (value: unknown): value is Message => {
  if (!value || typeof value !== "object") return false

  const message = value as Partial<Message>
  return (
    typeof message.id === "string" &&
    (message.role === "user" ||
      message.role === "assistant" ||
      message.role === "system") &&
    typeof message.content === "string" &&
    typeof message.status === "string" &&
    typeof message.createdAt === "string" &&
    typeof message.updatedAt === "string" &&
    Array.isArray(message.attachmentIds) &&
    Array.isArray(message.citations) &&
    Array.isArray(message.artifacts) &&
    Array.isArray(message.toolCalls)
  )
}

const liveGenerationMessageIds = new Set<string>()

const normalizeInterruptedMessages = (messages: Message[]): Message[] =>
  messages.map((message) =>
    ["queued", "thinking", "tool_call", "streaming"].includes(message.status) &&
    !liveGenerationMessageIds.has(message.id)
      ? { ...message, status: "stopped" }
      : message,
  )

const readConversationMessages = (
  userId: string | undefined,
  conversationId: string,
): Message[] => {
  if (!userId || typeof window === "undefined") return []

  try {
    const value = window.localStorage.getItem(
      conversationMessagesStorageKey(userId, conversationId),
    )
    if (!value) return []

    const messages: unknown = JSON.parse(value)
    if (!Array.isArray(messages) || !messages.every(isStoredMessage)) return []

    const normalizedMessages = normalizeInterruptedMessages(messages).map(
      (message) =>
        message.role === "user"
          ? {
              ...message,
              content: removeLegacyResponsePreferences(message.content),
            }
          : message,
    )
    if (
      normalizedMessages.some(
        (message, index) => message.content !== messages[index]?.content,
      )
    ) {
      try {
        window.localStorage.setItem(
          conversationMessagesStorageKey(userId, conversationId),
          JSON.stringify(normalizedMessages),
        )
      } catch {
        // The normalized in-memory history remains usable when storage is unavailable.
      }
    }
    return normalizedMessages
  } catch {
    return []
  }
}

const writeConversationMessages = (
  userId: string | undefined,
  conversationId: string,
  messages: Message[],
): void => {
  if (!userId || typeof window === "undefined" || messages.length === 0) return

  try {
    window.localStorage.setItem(
      conversationMessagesStorageKey(userId, conversationId),
      JSON.stringify(messages),
    )
  } catch {
    // The server remains authoritative when browser storage is unavailable.
  }
}

const migrateConversationMessageCache = (
  userId: string | undefined,
  fromConversationId: string,
  toConversationId: string,
): void => {
  if (
    !userId ||
    typeof window === "undefined" ||
    fromConversationId === toConversationId
  ) {
    return
  }

  try {
    const fromKey = conversationMessagesStorageKey(userId, fromConversationId)
    const cachedMessages = window.localStorage.getItem(fromKey)
    if (cachedMessages === null) return

    window.localStorage.setItem(
      conversationMessagesStorageKey(userId, toConversationId),
      cachedMessages,
    )
    window.localStorage.removeItem(fromKey)
  } catch {
    // The server remains authoritative when browser storage is unavailable.
  }
}

const isStoredConversation = (value: unknown): value is Conversation => {
  if (!value || typeof value !== "object") return false

  const conversation = value as Partial<Conversation>
  return (
    typeof conversation.id === "string" &&
    typeof conversation.title === "string" &&
    typeof conversation.pinned === "boolean" &&
    typeof conversation.archived === "boolean" &&
    typeof conversation.shared === "boolean" &&
    (conversation.mode === "chat" || conversation.mode === "agents") &&
    typeof conversation.createdAt === "string" &&
    typeof conversation.updatedAt === "string"
  )
}

const readConversationList = (userId: string | undefined): Conversation[] => {
  if (!userId || typeof window === "undefined") return []

  try {
    const value = window.localStorage.getItem(
      conversationListStorageKey(userId),
    )
    if (!value) return []

    const conversations: unknown = JSON.parse(value)
    return Array.isArray(conversations) &&
      conversations.every(isStoredConversation)
      ? conversations.map((conversation) => ({ ...conversation, messages: [] }))
      : []
  } catch {
    return []
  }
}

const writeConversationList = (
  userId: string | undefined,
  conversations: Conversation[],
): void => {
  if (!userId || typeof window === "undefined") return

  try {
    const metadata = conversations.map(({ messages: _messages, ...item }) => ({
      ...item,
      messages: [],
    }))
    window.localStorage.setItem(
      conversationListStorageKey(userId),
      JSON.stringify(metadata),
    )
  } catch {
    // The server remains authoritative when browser storage is unavailable.
  }
}

const removeStoredConversation = (
  userId: string,
  conversationId: string,
): void => {
  writeConversationList(
    userId,
    readConversationList(userId).filter(
      (conversation) => conversation.id !== conversationId,
    ),
  )

  if (typeof window === "undefined") return

  try {
    window.localStorage.removeItem(
      conversationMessagesStorageKey(userId, conversationId),
    )
    if (readActiveConversationId(userId) === conversationId) {
      window.localStorage.removeItem(activeConversationStorageKey(userId))
    }
  } catch {
    // The server remains authoritative when browser storage is unavailable.
  }
}

const sortConversationsByRecent = (
  conversations: Conversation[],
): Conversation[] =>
  [...conversations].sort(
    (a, b) =>
      new Date(b.lastMessageAt || b.updatedAt).getTime() -
      new Date(a.lastMessageAt || a.updatedAt).getTime(),
  )

const mergeConversationMessages = (
  current: Message[],
  incoming: Message[],
): Message[] => {
  const updated = new Map<string, Message>()

  for (const message of [...current, ...incoming]) {
    const backendKey = message.backendMessageId
      ? `backend:${message.backendMessageId}:${message.role}`
      : null
    let existingKey = backendKey
    let existing = backendKey ? updated.get(backendKey) : undefined

    if (!existing) {
      const createdAt = new Date(message.createdAt).getTime()
      const equivalent = Array.from(updated.entries()).find(([, candidate]) => {
        if (
          candidate.role !== message.role ||
          candidate.backendMessageId ||
          !message.backendMessageId ||
          candidate.content.trim() !== message.content.trim()
        ) {
          return false
        }

        const candidateCreatedAt = new Date(candidate.createdAt).getTime()
        return (
          Number.isFinite(createdAt) &&
          Number.isFinite(candidateCreatedAt) &&
          Math.abs(createdAt - candidateCreatedAt) <= 10_000
        )
      })
      if (equivalent) {
        existingKey = equivalent[0]
        existing = equivalent[1]
      }
    }

    const key = backendKey || existingKey || `id:${message.id}`
    if (existingKey && existingKey !== key) updated.delete(existingKey)
    const merged = existing
      ? {
          ...existing,
          ...message,
          id: existing.id,
          content: message.content.trim() ? message.content : existing.content,
          status:
            existing.status === "stopped"
              ? "stopped"
              : ["queued", "thinking", "tool_call", "streaming"].includes(
                    existing.status,
                  ) &&
                  message.status === "stopped" &&
                  !message.content.trim()
                ? existing.status
                : message.status,
          backendMessageId:
            message.backendMessageId || existing.backendMessageId,
        }
      : message

    updated.set(key, merged)
  }

  return Array.from(updated.values()).sort(
    (a, b) =>
      new Date(a.createdAt || 0).getTime() -
      new Date(b.createdAt || 0).getTime(),
  )
}

const reducer = (state: WorkspaceState, action: Action): WorkspaceState => {
  switch (action.type) {
    case "HYDRATE":
      return action.state

    case "SET_MODELS":
      return { ...state, models: action.models }

    case "SET_PROJECTS":
      return { ...state, projects: action.projects }

    case "SET_AUTH_STATUS":
      return {
        ...state,

        conversations: action.clearUser ? [] : state.conversations,

        activeConversationId: action.clearUser
          ? null
          : state.activeConversationId,

        attachments: action.clearUser ? [] : state.attachments,

        library: action.clearUser ? [] : state.library,

        projects: action.clearUser ? [] : state.projects,

        connectors: action.clearUser ? [] : state.connectors,

        scheduledTasks: action.clearUser ? [] : state.scheduledTasks,

        agents: action.clearUser ? [] : state.agents,

        agentRuns: action.clearUser ? [] : state.agentRuns,

        notifications: action.clearUser ? [] : state.notifications,

        models: action.clearUser ? [] : state.models,

        account: {
          ...state.account,

          authStatus: action.status,

          user: action.clearUser ? null : state.account.user,

          subscription: action.clearUser ? null : state.account.subscription,

          memories: action.clearUser ? [] : state.account.memories,

          usage: action.clearUser
            ? {
                messages: { used: 0, limit: 0 },
                agentRuns: { used: 0, limit: 0 },
                storageBytes: { used: 0, limit: 0 },
              }
            : state.account.usage,
        },
      }

    case "SET_ACTIVE":
      return { ...state, activeConversationId: action.id }

    case "ADD_CONVERSATION":
      return {
        ...state,

        conversations: [
          action.conversation,
          ...state.conversations.filter(
            (item) => item.id !== action.conversation.id,
          ),
        ],

        activeConversationId: action.conversation.id,
      }

    case "REMAP_CONVERSATION_ID": {
      const existing = state.conversations.find(
        (conversation) => conversation.id === action.conversation.id,
      )
      const conversation = {
        ...existing,
        ...action.conversation,
        messages: mergeConversationMessages(
          existing?.messages || [],
          action.conversation.messages,
        ),
      }
      return {
        ...state,
        conversations: sortConversationsByRecent([
          ...state.conversations.filter(
            (item) =>
              item.id !== action.fromId && item.id !== action.conversation.id,
          ),
          conversation,
        ]),
        activeConversationId:
          state.activeConversationId === action.fromId
            ? action.conversation.id
            : state.activeConversationId,
      }
    }

    case "MERGE_CONVERSATIONS": {
      const conversations = new Map(
        state.conversations.map((conversation) => [
          conversation.id,
          conversation,
        ]),
      )
      for (const conversation of action.conversations) {
        conversations.set(conversation.id, {
          ...conversations.get(conversation.id),
          ...conversation,
        })
      }
      return {
        ...state,
        conversations: sortConversationsByRecent(
          Array.from(conversations.values()),
        ),
      }
    }

    case "SET_CONVERSATION_MESSAGES":
      return {
        ...state,
        conversations: state.conversations.map((conversation) =>
          conversation.id === action.id
            ? {
                ...conversation,
                messages: mergeConversationMessages(
                  conversation.messages,
                  action.messages,
                ),
              }
            : conversation,
        ),
      }

    case "UPDATE_CONVERSATION":
      return {
        ...state,

        conversations: sortConversationsByRecent(
          state.conversations.map((item) =>
            item.id === action.id
              ? {
                  ...item,
                  ...action.patch,
                  updatedAt: new Date().toISOString(),
                }
              : item,
          ),
        ),
      }

    case "DELETE_CONVERSATION": {
      const conversations = state.conversations.filter(
        (item) => item.id !== action.id,
      )

      const activeConversationId =
        state.activeConversationId === action.id
          ? conversations[0]?.id || null
          : state.activeConversationId

      return {
        ...state,

        conversations,

        activeConversationId,
      }
    }

    case "ADD_MESSAGE":
      return {
        ...state,

        conversations: sortConversationsByRecent(
          state.conversations.map((item) =>
            item.id === action.conversationId
              ? {
                  ...item,

                  messages: mergeConversationMessages(item.messages, [
                    action.message,
                  ]),

                  updatedAt: new Date().toISOString(),
                }
              : item,
          ),
        ),
      }

    case "UPDATE_MESSAGE":
      return {
        ...state,

        conversations: sortConversationsByRecent(
          state.conversations.map((item) => {
            if (item.id !== action.conversationId) return item

            const messages = item.messages.map((message) =>
              message.id === action.id
                ? {
                    ...message,
                    ...action.patch,
                    updatedAt: new Date().toISOString(),
                  }
                : message,
            )

            return {
              ...item,
              messages: action.patch.backendMessageId
                ? mergeConversationMessages(messages, [])
                : messages,
              updatedAt: new Date().toISOString(),
            }
          }),
        ),
      }

    case "DELETE_MESSAGE":
      return {
        ...state,

        conversations: state.conversations.map((item) =>
          item.id === action.conversationId
            ? {
                ...item,

                messages: item.messages.filter(
                  (message) => message.id !== action.id,
                ),
              }
            : item,
        ),
      }

    case "TRUNCATE_AFTER":
      return {
        ...state,

        conversations: state.conversations.map((item) => {
          if (item.id !== action.conversationId) return item

          const index = item.messages.findIndex(
            (message) => message.id === action.id,
          )

          return index < 0
            ? item
            : { ...item, messages: item.messages.slice(0, index + 1) }
        }),
      }

    case "ADD_ATTACHMENTS":
      return {
        ...state,

        attachments: [...state.attachments, ...action.attachments],
      }

    case "UPDATE_ATTACHMENT": {
      const attachments = state.attachments.map((item) =>
        item.id === action.id
          ? {
              ...item,

              ...action.patch,

              updatedAt: new Date().toISOString(),
            }
          : item,
      )

      const updated = attachments.find((item) => item.id === action.id)

      const existsInLibrary = state.library.some(
        (item) => item.id === action.id,
      )

      const library =
        updated?.status === "ready" && !existsInLibrary
          ? [...state.library, updated]
          : state.library.map((item) =>
              item.id === action.id
                ? { ...item, ...action.patch } as typeof item
                : item,
            )

      let notifications = state.notifications

      if (
        updated?.status === "ready" &&
        !state.notifications.some(
          (item) =>
            item.type === "file_ready" && item.detail.includes(updated.id),
        )
      ) {
        notifications = [
          createNotification(
            "file_ready",

            "File ready",

            `${updated.name} (${updated.id}) is ready to use.`,
          ),

          ...state.notifications,
        ]
      }

      return { ...state, attachments, library, notifications }
    }

    case "REMOVE_ATTACHMENT":
      return {
        ...state,

        attachments: state.attachments.filter((item) => item.id !== action.id),
      }

    case "UPDATE_LIBRARY_ITEM":
      return {
        ...state,

        library: state.library.map((item) =>
          item.id === action.id
            ? { ...item, ...action.patch } as typeof item
            : item,
        ),
      }

    case "DELETE_LIBRARY_ITEM":
      return {
        ...state,

        library: state.library.filter((item) => item.id !== action.id),
      }

    case "UPDATE_ACCOUNT":
      return { ...state, account: { ...state.account, ...action.patch } }

    case "UPDATE_PREFERENCES":
      return {
        ...state,
        account: {
          ...state.account,
          preferences: {
            ...state.account.preferences,
            ...action.patch,
            ...(action.patch.theme === "light" ||
            action.patch.theme === "dark" ||
            action.patch.theme === "system"
              ? { theme: action.patch.theme }
              : {}),
            notifications: {
              ...state.account.preferences.notifications,

              ...(action.patch.notifications || {}),
            },
          },
        },
      }

    case "SET_MEMORIES":
      return {
        ...state,

        account: { ...state.account, memories: action.memories },
      }

    case "UPDATE_PROJECT":
      return {
        ...state,

        projects: state.projects.map((item) =>
          item.id === action.id
            ? { ...item, ...action.patch, updatedAt: new Date().toISOString() }
            : item,
        ),
      }

    case "ADD_PROJECT":
      return { ...state, projects: [...state.projects, action.project] }

    case "DELETE_PROJECT":
      return {
        ...state,
        projects: state.projects.filter((item) => item.id !== action.id),
        conversations: state.conversations.map((conversation) =>
          conversation.projectId === action.id
            ? { ...conversation, projectId: undefined }
            : conversation,
        ),
        library: state.library.map((item) =>
          item.projectId === action.id
            ? { ...item, projectId: undefined }
            : item,
        ),
      }

    case "UPSERT_CONNECTOR":
      return {
        ...state,

        connectors: state.connectors.some(
          (item) => item.id === action.connector.id,
        )
          ? state.connectors.map((item) =>
              item.id === action.connector.id ? action.connector : item,
            )
          : [...state.connectors, action.connector],
      }

    case "UPDATE_CONNECTOR":
      return {
        ...state,

        connectors: state.connectors.map((item) =>
          item.id === action.id
            ? { ...item, ...action.patch, status: action.status }
            : item,
        ),
      }

    case "SET_AGENTS":
      return { ...state, agents: action.agents }

    case "UPSERT_AGENT":
      return {
        ...state,

        agents: state.agents.some((item) => item.id === action.agent.id)
          ? state.agents.map((item) =>
              item.id === action.agent.id ? action.agent : item,
            )
          : [action.agent, ...state.agents],
      }

    case "DELETE_AGENT":
      return {
        ...state,

        agents: state.agents.filter((item) => item.id !== action.id),
      }

    case "UPSERT_AGENT_RUN":
      return {
        ...state,

        agentRuns: state.agentRuns.some((item) => item.id === action.run.id)
          ? state.agentRuns.map((item) =>
              item.id === action.run.id ? action.run : item,
            )
          : [action.run, ...state.agentRuns],
      }

    case "UPDATE_SCHEDULE":
      return {
        ...state,

        scheduledTasks: state.scheduledTasks.map((item) =>
          item.id === action.id ? { ...item, ...action.patch } : item,
        ),
      }

    case "TOGGLE_SCHEDULE":
      return {
        ...state,

        scheduledTasks: state.scheduledTasks.map((item) =>
          item.id === action.id ? { ...item, enabled: !item.enabled } : item,
        ),
      }

    case "ADD_SCHEDULE":
      return {
        ...state,

        scheduledTasks: [...state.scheduledTasks, action.task],
      }

    case "DELETE_SCHEDULE":
      return {
        ...state,

        scheduledTasks: state.scheduledTasks.filter(
          (item) => item.id !== action.id,
        ),
      }

    case "SET_NOTIFICATIONS":
      return { ...state, notifications: action.notifications }

    case "ADD_NOTIFICATION": {
      const category =
        action.notification.type === "file_ready"
          ? "artifacts"
          : action.notification.type === "usage_warning"
            ? "tips"
            : "tasks"
      if (!state.account.preferences.notifications[category]) return state
      return {
        ...state,

        notifications: [action.notification, ...state.notifications].slice(
          0,
          50,
        ),
      }
    }

    case "MARK_NOTIFICATION_READ":
      return {
        ...state,

        notifications: state.notifications.map((item) =>
          item.id === action.id ? { ...item, read: true } : item,
        ),
      }

    case "CLEAR_NOTIFICATIONS":
      return { ...state, notifications: [] }

    default:
      return state
  }
}

const newMessage = (
  role: Message["role"],

  content: string,

  status: Message["status"],

  attachmentIds: string[] = [],
): Message => {
  const timestamp = new Date().toISOString()

  return {
    id: crypto.randomUUID(),

    role,

    content,

    status,

    createdAt: timestamp,

    updatedAt: timestamp,

    attachmentIds,

    citations: [],

    artifacts: [],

    toolCalls: [],
  }
}

const hasMeaningfulMessages = (messages: Message[]): boolean =>
  messages.some(
    (message) =>
      (message.role === "user" || message.role === "assistant") &&
      (message.content.trim().length > 0 || message.attachmentIds.length > 0),
  )

const hydrateAccount = (
  fallback: WorkspaceState["account"],

  incoming: Partial<WorkspaceState["account"]>,
): WorkspaceState["account"] => {
  const serverPreferences: Partial<Preferences> = incoming.preferences || {}
  const storedPreferences = readStoredPreferences(incoming.user?.id)
  const responseTraits =
    storedPreferences.responseTraits ?? serverPreferences.responseTraits
  const customInstructions =
    storedPreferences.customInstructions ?? serverPreferences.customInstructions
  const preferences = {
    ...fallback.preferences,
    ...serverPreferences,
    ...storedPreferences,
    language:
      serverPreferences.language === "Somali" ||
      serverPreferences.language === "English"
        ? serverPreferences.language
        : storedPreferences.language || fallback.preferences.language,
    responseTraits: Array.isArray(responseTraits)
      ? responseTraits.filter(
          (trait): trait is string =>
            typeof trait === "string" && trait.trim().length > 0,
        )
      : fallback.preferences.responseTraits,
    customInstructions:
      typeof customInstructions === "string"
        ? customInstructions
        : fallback.preferences.customInstructions,
    notifications: {
      ...fallback.preferences.notifications,
      ...(serverPreferences.notifications || {}),
      ...(storedPreferences.notifications || {}),
    },
  }

  return {
    ...fallback,
    ...incoming,
    usage: incoming.usage || fallback.usage,
    preferences,
    memories: incoming.memories || fallback.memories,
    authStatus: incoming.authStatus || fallback.authStatus,
  }
}

const PREFERENCES_STORAGE_KEY = "garad-ai-preferences"
const PROFILE_STORAGE_KEY = "garad-ai-profile"
const MODEL_CATALOG_STORAGE_KEY = "garad-model-catalog-v1"

const preferencesStorageKey = (userId?: string | null) =>
  `${PREFERENCES_STORAGE_KEY}:${userId || "guest"}`

const readStoredPreferences = (
  userId?: string | null,
): Partial<Preferences> => {
  if (typeof window === "undefined") return {}
  try {
    const scopedKey = preferencesStorageKey(userId)
    let raw = window.localStorage.getItem(scopedKey)
    if (!raw) {
      raw = window.localStorage.getItem(PREFERENCES_STORAGE_KEY)
      if (raw) {
        window.localStorage.setItem(scopedKey, raw)
        window.localStorage.removeItem(PREFERENCES_STORAGE_KEY)
      }
    }
    if (!raw) return {}
    const value: unknown = JSON.parse(raw)
    if (!value || typeof value !== "object") return {}
    const stored = value as Record<string, unknown>
    const result: Partial<Preferences> = {}
    if (
      stored.theme === "light" ||
      stored.theme === "dark" ||
      stored.theme === "system"
    )
      result.theme = stored.theme
    if (stored.language === "English" || stored.language === "Somali")
      result.language = stored.language
    if (stored.defaultMode === "chat" || stored.defaultMode === "agents")
      result.defaultMode = stored.defaultMode
    if (typeof stored.defaultModel === "string")
      result.defaultModel = stored.defaultModel
    if (typeof stored.reducedMotion === "boolean")
      result.reducedMotion = stored.reducedMotion
    if (Array.isArray(stored.responseTraits))
      result.responseTraits = stored.responseTraits.filter(
        (trait): trait is string => typeof trait === "string",
      )
    if (typeof stored.customInstructions === "string")
      result.customInstructions = stored.customInstructions
    if (stored.notifications && typeof stored.notifications === "object") {
      const notifications = stored.notifications as Record<string, unknown>
      result.notifications = {
        tasks:
          typeof notifications.tasks === "boolean"
            ? notifications.tasks
            : initialWorkspace.account.preferences.notifications.tasks,
        artifacts:
          typeof notifications.artifacts === "boolean"
            ? notifications.artifacts
            : initialWorkspace.account.preferences.notifications.artifacts,
        tips:
          typeof notifications.tips === "boolean"
            ? notifications.tips
            : initialWorkspace.account.preferences.notifications.tips,
      }
    }
    return result
  } catch (error) {
    console.warn("Unable to read saved Garad preferences", error)
    return {}
  }
}

const persistPreferences = (
  patch: Partial<Preferences>,
  userId?: string | null,
): boolean => {
  if (typeof window === "undefined") return false
  try {
    const current = readStoredPreferences(userId)
    window.localStorage.setItem(
      preferencesStorageKey(userId),
      JSON.stringify({
        ...current,
        ...patch,
        ...(patch.notifications
          ? {
              notifications: {
                ...current.notifications,
                ...patch.notifications,
              },
            }
          : {}),
      }),
    )
    return true
  } catch (error) {
    console.warn("Unable to save Garad preferences locally", error)
    return false
  }
}

const clearStoredPreferences = (
  userId?: string | null,
  syncedPatch?: Partial<Preferences>,
) => {
  if (typeof window === "undefined") return
  try {
    if (!syncedPatch) {
      window.localStorage.removeItem(preferencesStorageKey(userId))
      return
    }
    const stored = readStoredPreferences(userId)
    const remaining: Partial<Preferences> = { ...stored }
    for (const [key, value] of Object.entries(syncedPatch)) {
      if (key === "notifications" || value === undefined) continue
      if (
        JSON.stringify(stored[(key as keyof Preferences)]) ===
        JSON.stringify(value)
      )
        delete remaining[(key as keyof Preferences)]
    }
    if (syncedPatch.notifications && stored.notifications) {
      const notifications = { ...stored.notifications }
      for (const [key, value] of Object.entries(syncedPatch.notifications)) {
        if (
          notifications[(key as keyof Preferences["notifications"])] === value
        )
          delete notifications[(key as keyof Preferences["notifications"])]
      }
      if (Object.keys(notifications).length) {
        remaining.notifications = notifications
      } else {
        delete remaining.notifications
      }
    }
    if (Object.keys(remaining).length) {
      window.localStorage.setItem(
        preferencesStorageKey(userId),
        JSON.stringify(remaining),
      )
    } else {
      window.localStorage.removeItem(preferencesStorageKey(userId))
    }
  } catch (error) {
    console.warn("Unable to clear saved Garad preferences", error)
  }
}

const readStoredProfile = (): Account["user"] => {
  if (typeof window === "undefined") return null
  try {
    const raw = window.localStorage.getItem(PROFILE_STORAGE_KEY)
    if (!raw) return null
    const value: unknown = JSON.parse(raw)
    if (!value || typeof value !== "object") return null
    const stored = value as Record<string, unknown>
    if (
      typeof stored.id !== "string" ||
      typeof stored.name !== "string" ||
      typeof stored.email !== "string"
    )
      return null
    return {
      id: stored.id,
      name: stored.name,
      givenName:
        typeof stored.givenName === "string" ? stored.givenName : undefined,
      surname: typeof stored.surname === "string" ? stored.surname : undefined,
      email: stored.email,
      avatarUrl:
        typeof stored.avatarUrl === "string" ? stored.avatarUrl : undefined,
      phone: typeof stored.phone === "string" ? stored.phone : undefined,
      country: typeof stored.country === "string" ? stored.country : undefined,
      state: typeof stored.state === "string" ? stored.state : undefined,
      city: typeof stored.city === "string" ? stored.city : undefined,
      postal: typeof stored.postal === "string" ? stored.postal : undefined,
      address: typeof stored.address === "string" ? stored.address : undefined,
      type:
        stored.type === "admin" || stored.type === "super_admin"
          ? stored.type
          : "user",
    }
  } catch (error) {
    console.warn("Unable to read saved Garad profile", error)
    return null
  }
}

const persistStoredProfile = (user: NonNullable<Account["user"]>): boolean => {
  if (typeof window === "undefined") return false
  try {
    window.localStorage.setItem(PROFILE_STORAGE_KEY, JSON.stringify(user))
    return true
  } catch (error) {
    console.warn("Unable to save Garad profile locally", error)
    return false
  }
}

const readStoredModels = (): ModelDescriptor[] => {
  if (typeof window === "undefined") return []
  try {
    const raw = window.localStorage.getItem(MODEL_CATALOG_STORAGE_KEY)
    if (!raw) return []
    const value: unknown = JSON.parse(raw)
    if (
      !Array.isArray(value) ||
      !value.every(
        (model): model is ModelDescriptor =>
          typeof model === "object" &&
          model !== null &&
          "id" in model &&
          typeof model.id === "string" &&
          "displayName" in model &&
          typeof model.displayName === "string" &&
          "description" in model &&
          typeof model.description === "string" &&
          "capabilities" in model &&
          Array.isArray(model.capabilities) &&
          "planRequirement" in model &&
          ["free", "pro", "business"].includes(String(model.planRequirement)) &&
          "availability" in model &&
          ["available", "beta", "unavailable"].includes(
            String(model.availability),
          ),
      )
    ) {
      return []
    }
    return value
  } catch (error) {
    console.warn("Unable to read cached Garad models", error)
    return []
  }
}

const persistStoredModels = (models: ModelDescriptor[]): void => {
  if (typeof window === "undefined") return
  try {
    window.localStorage.setItem(
      MODEL_CATALOG_STORAGE_KEY,
      JSON.stringify(models),
    )
  } catch (error) {
    console.warn("Unable to cache Garad models", error)
  }
}

const configuredApiBaseUrl =
  import.meta.env.VITE_GARAD_API_URL?.trim() ||
  (typeof window !== "undefined"
    ? window.__GARAD_API_BASE_URL?.trim() || ""
    : "")

const createBootstrapWorkspace = (apiConfigured: boolean): WorkspaceState => {
  const workspace = structuredClone(initialWorkspace)

  if (!apiConfigured) {
    workspace.account.user = null
    workspace.account.subscription = null
    workspace.account.authStatus = "unauthenticated"
    workspace.account.preferences = {
      ...workspace.account.preferences,
      ...readStoredPreferences(),
      notifications: {
        ...workspace.account.preferences.notifications,
        ...readStoredPreferences().notifications,
      },
    }
    return workspace
  }

  workspace.account = {
    ...workspace.account,
    user: null,
    subscription: null,
    authStatus: "loading",
    usage: {
      messages: { used: 0, limit: 0 },
      agentRuns: { used: 0, limit: 0 },
      storageBytes: { used: 0, limit: 0 },
    },
    preferences: {
      ...workspace.account.preferences,
      defaultModel: "",
      responseTraits: [],
      customInstructions: "",
    },
    memories: [],
  }

  workspace.conversations = []
  workspace.activeConversationId = null
  workspace.attachments = []
  workspace.library = []
  workspace.projects = []
  workspace.connectors = []
  workspace.scheduledTasks = []
  workspace.agents = []
  workspace.agentRuns = []
  workspace.models = readStoredModels()
  workspace.notifications = []

  return workspace
}

const readGuestConversations = (): Conversation[] =>
  readConversationList(GUEST_STORAGE_ID)
    .filter((conversation) => conversation.id.startsWith("guest-"))
    .map((conversation) => ({
      ...conversation,
      messages: readConversationMessages(GUEST_STORAGE_ID, conversation.id),
    }))

const createGuestBootstrapWorkspace = (): WorkspaceState => {
  const workspace = createBootstrapWorkspace(true)
  const conversations = readGuestConversations()
  const savedActiveId = readActiveConversationId(GUEST_STORAGE_ID)

  workspace.account.authStatus = "unauthenticated"
  workspace.conversations = conversations
  workspace.activeConversationId =
    savedActiveId &&
    conversations.some((conversation) => conversation.id === savedActiveId)
      ? savedActiveId
      : conversations[0]?.id || null

  return workspace
}

export function useWorkspace(initialConversationMode: Conversation["mode"] = "chat") {
  const [state, dispatch] = useReducer(
    reducer,
    createBootstrapWorkspace(Boolean(configuredApiBaseUrl)),
    (value) => structuredClone(value),
  )

  const stateRef = useRef(state)
  stateRef.current = state
  const activeConversationIdRef = useRef(state.activeConversationId)
  activeConversationIdRef.current = state.activeConversationId

  const [bootstrapping, setBootstrapping] = useState(false)

  const [bootstrapError, setBootstrapError] = useState<string | null>(null)

  const [guestLimitReached, setGuestLimitReached] = useState(false)

  const [guestChatStatus, setGuestChatStatus] = useState<{
    limit: number
    used: number
    remaining: number
  } | null>(null)

  const api = useMemo(createApiClient, [])

  const [plans, setPlans] = useState<Plan[]>(
    () => readCachedPlans() ?? planCatalog,
  )

  useEffect(() => {
    let cancelled = false

    api
      .getPlans()
      .then((remote) => {
        if (cancelled || !Array.isArray(remote) || remote.length === 0) return

        const all = mergePlanCatalog(remote)
        if (all.length > 0) {
          setPlans(all)
          cachePlans(all)
        }
      })
      .catch((error) => {
        console.error("Unable to load plans", error)
      })

    return () => {
      cancelled = true
    }
  }, [api])

  const hydratedRef = useRef(false)

  const authProbeRef = useRef<Promise<void> | null>(null)

  const guestLimitReachedRef = useRef(false)

  const retryAttemptRef = useRef(0)

  const retryTimerRef = useRef<number | undefined>(undefined)

  const conversationCreationRef = useRef<Promise<Conversation | null> | null>(
    null,
  )

  const conversationPageRef = useRef(1)

  const conversationModeRef = useRef(initialConversationMode)

  const loadMoreConversationsRef = useRef(false)

  const authenticatedUserIdRef = useRef<string | undefined>(undefined)

  const conversationOwnerByIdRef = useRef(new Map<string, string>())

  const messageRequestsRef = useRef(new Map<string, Promise<Message[]>>())

  const [loadingMessageRequestKeys, setLoadingMessageRequestKeys] = useState(
    () => new Set<string>(),
  )

  const pendingPersistedConversationsRef = useRef(
    new Map<string, {
      userId: string
      conversation: Conversation
    }>(),
  )

  const pendingDraftConversationRef = useRef<Conversation | null>(null)

  const [hasMoreConversations, setHasMoreConversations] = useState(false)

  const [isLoadingMoreConversations, setIsLoadingMoreConversations] =
    useState(false)

  const refreshRef = useRef<() => Promise<void>>(async () => {})

  const transport = useMemo(() => createChatTransport(api), [api])

  const generationControllers = useRef(new Map<string, AbortController>())

  const generationControllersByMessage = useRef(
    new Map<string, AbortController>(),
  )

  const generationRequestIdsByMessage = useRef(new Map<string, string>())

  const fileRegistry = useRef(new Map<string, File>())

  const uploadCancels = useRef(new Map<string, () => void>())

  const streamRegistry = useRef(new Map<string, string>())

  const citationRegistry = useRef(new Map<string, Message["citations"]>())

  const toolRegistry = useRef(new Map<string, Message["toolCalls"]>())

  const artifactRegistry = useRef(new Map<string, Message["artifacts"]>())

  const activeConversation = useMemo(
    () =>
      state.conversations.find(
        (item) => item.id === state.activeConversationId,
      ) || null,

    [state.activeConversationId, state.conversations],
  )

  const handleApiError = useCallback(
    (error: unknown, notify = true): string => {
      const message =
        error instanceof Error ? error.message : "Request failed unexpectedly."

      if (
        !(error instanceof Error && error.name === "AbortError") &&
        !(error instanceof DOMException && error.name === "AbortError")
      ) {
        console.error(
          "Garad request failed",
          error instanceof ApiError
            ? {
                status: error.status,
                code: error.code,
                message: error.message,
              }
            : error,
        )
      }

      if (error instanceof ApiError && error.code === "guest_chat_limit") {
        guestLimitReachedRef.current = true
        setGuestLimitReached(true)
      }

      if (
        error instanceof ApiError &&
        error.status === 401 &&
        stateRef.current.account.authStatus === "authenticated" &&
        !authProbeRef.current
      ) {
        const probe = api
          .getAccount()
          .then((account) => {
            if (account.user) {
              dispatch({
                type: "SET_AUTH_STATUS",
                status: "authenticated",
              })
            } else {
              const guestWorkspace = createGuestBootstrapWorkspace()
              dispatch({ type: "HYDRATE", state: guestWorkspace })
              void refreshGuestModels()
              activeConversationIdRef.current =
                guestWorkspace.activeConversationId
              authenticatedUserIdRef.current = undefined
              void api
                .getGuestChatStatus()
                .then(setGuestChatStatus)
                .catch((statusError) =>
                  console.error(
                    "Unable to load guest chat allowance",
                    statusError,
                  ),
                )
            }
          })
          .catch((probeError: unknown) => {
            if (probeError instanceof ApiError && probeError.status === 401) {
              const guestWorkspace = createGuestBootstrapWorkspace()
              dispatch({ type: "HYDRATE", state: guestWorkspace })
              void refreshGuestModels()
              activeConversationIdRef.current =
                guestWorkspace.activeConversationId
              authenticatedUserIdRef.current = undefined
              void api
                .getGuestChatStatus()
                .then(setGuestChatStatus)
                .catch((statusError) =>
                  console.error(
                    "Unable to load guest chat allowance",
                    statusError,
                  ),
                )
            } else if (
              probeError instanceof ApiError &&
              probeError.status === 403
            ) {
              const forbiddenWorkspace = createBootstrapWorkspace(true)
              forbiddenWorkspace.account.authStatus = "forbidden"
              dispatch({ type: "HYDRATE", state: forbiddenWorkspace })
              dispatch({
                type: "SET_AUTH_STATUS",
                status: "forbidden",
                clearUser: true,
              })
            } else {
              console.error("Unable to verify Garad session status", probeError)
            }
          })
          .finally(() => {
            authProbeRef.current = null
          })
        authProbeRef.current = probe
      }

      if (notify)
        dispatch({
          type: "ADD_NOTIFICATION",

          notification: createNotification(
            "task_failed",
            "Request failed",
            message,
          ),
        })

      return message
    },

    [api],
  )

  const refreshGuestChatStatus = useCallback(async () => {
    if (!api.configured) return

    try {
      setGuestChatStatus(await api.getGuestChatStatus())
    } catch (error) {
      handleApiError(error, false)
    }
  }, [api, handleApiError])

  const refreshGuestModels = useCallback(async () => {
    if (!api.configured) return

    try {
      dispatch({ type: "SET_MODELS", models: await api.getModels() })
    } catch (error) {
      console.error("Unable to load chat models for guest browsing", error)
    }
  }, [api])

  const refreshAccountUsage = useCallback(async () => {
    if (!api.configured) return

    try {
      dispatch({
        type: "UPDATE_ACCOUNT",
        patch: { usage: await api.getUsage() },
      })
    } catch (error) {
      handleApiError(error)
    }
  }, [api, handleApiError])

  const refreshWorkspace = useCallback(async () => {
    if (!api.configured) {
      dispatch({ type: "SET_AUTH_STATUS", status: "unauthenticated" })

      return
    }

    setBootstrapping(true)

    setBootstrapError(null)

    if (!hydratedRef.current)
      dispatch({ type: "SET_AUTH_STATUS", status: "loading" })

    const modelsResultPromise = Promise.allSettled([api.getModels()]).then(
      ([result]) => {
        if (result.status === "fulfilled") persistStoredModels(result.value)
        return result
      },
    )

    const applyPreloadedModels = async (logError = false): Promise<void> => {
      const result = await modelsResultPromise
      if (result.status === "fulfilled") {
        dispatch({ type: "SET_MODELS", models: result.value })
      } else if (logError) {
        console.error(
          "Unable to load chat models for guest browsing",
          result.reason,
        )
      }
    }

    try {
      const account = await api.getAccount()
      authenticatedUserIdRef.current = account.user?.id

      const conversationsPromise = api.getConversations({
        page: 1,
        perPage: 20,
        mode: initialConversationMode,
      })

      const applyState = (
        conversationsResult: PromiseSettledResult<Conversation[]>,
        rest: PromiseSettledResult<any>[],
        modelsResult: PromiseSettledResult<any>,
      ): string | null => {
        const settled = [conversationsResult, ...rest, modelsResult]

        const [
          ,
          projectsResult,
          libraryResult,
          connectorsResult,
          scheduledResult,
          agentsResult,
          agentRunsResult,
          memoriesResult,
          usageResult,
          notificationsResult,
          subscriptionResult,
          hydratedModelsResult,
        ] = settled

        const nextState = createBootstrapWorkspace(api.configured)

        nextState.account = hydrateAccount(nextState.account, {
          ...account,

          authStatus: account.authStatus || "authenticated",

          usage:
            usageResult.status === "fulfilled"
              ? usageResult.value
              : account.usage,

          memories:
            memoriesResult.status === "fulfilled"
              ? memoriesResult.value
              : account.memories,

          subscription:
            subscriptionResult.status === "fulfilled"
              ? subscriptionResult.value
              : account.subscription,
        })

        const hasServerConversationList =
          conversationsResult.status === "fulfilled"
        const serverConversations = hasServerConversationList
          ? conversationsResult.value
          : []
        const userId = nextState.account.user?.id || api.legacyUserId
        if (userId) authenticatedUserIdRef.current = userId
        if (userId) {
          const ownedPendingConversations = Array.from(
            pendingPersistedConversationsRef.current.values(),
          )
            .filter((entry) => entry.userId === userId)
            .map(
              (entry) =>
                stateRef.current.conversations.find(
                  (conversation) => conversation.id === entry.conversation.id,
                ) || entry.conversation,
            )
          if (
            userId &&
            pendingDraftConversationRef.current &&
            !pendingDraftConversationRef.current.id.startsWith("guest-")
          ) {
            const currentDraft = stateRef.current.conversations.find(
              (conversation) =>
                conversation.id === pendingDraftConversationRef.current?.id,
            )
            if (currentDraft) ownedPendingConversations.push(currentDraft)
          }
          const cachedConversationList = hasServerConversationList
            ? []
            : readConversationList(userId)
          for (const conversation of cachedConversationList) {
            conversationOwnerByIdRef.current.set(conversation.id, userId)
          }
          const cachedOwnedConversations = hasServerConversationList
            ? []
            : [
                ...cachedConversationList,
                ...stateRef.current.conversations.filter(
                  (conversation) =>
                    conversationOwnerByIdRef.current.get(conversation.id) ===
                    userId,
                ),
              ]
          for (const conversation of serverConversations) {
            conversationOwnerByIdRef.current.set(conversation.id, userId)
          }
          const merged = new Map(
            [...cachedOwnedConversations, ...ownedPendingConversations].map(
              (conversation) => [conversation.id, conversation],
            ),
          )
          for (const conversation of serverConversations) {
            const existing =
              merged.get(conversation.id) ||
              (conversationOwnerByIdRef.current.get(conversation.id) === userId
                ? stateRef.current.conversations.find(
                    (item) => item.id === conversation.id,
                  )
                : undefined)
            merged.set(
              conversation.id,
              existing && conversation.messages.length === 0
                ? { ...existing, ...conversation, messages: existing.messages }
                : conversation,
            )
          }
          for (const conversation of readGuestConversations()) {
            merged.set(conversation.id, conversation)
          }
          nextState.conversations = sortConversationsByRecent(
            Array.from(merged.values()),
          )
        } else {
          nextState.conversations = sortConversationsByRecent([
            ...serverConversations,
            ...readGuestConversations(),
          ])
        }

        const savedActiveId = readActiveConversationId(userId)
        const currentActiveId = stateRef.current.activeConversationId
        nextState.activeConversationId =
          (currentActiveId &&
          nextState.conversations.some(
            (conversation) => conversation.id === currentActiveId,
          )
            ? currentActiveId
            : savedActiveId &&
                nextState.conversations.some(
                  (conversation) => conversation.id === savedActiveId,
                )
              ? savedActiveId
              : nextState.conversations[0]?.id) || null

        const restoredConversation = nextState.conversations.find(
          (conversation) => conversation.id === nextState.activeConversationId,
        )
        if (restoredConversation) {
          const storageOwner = restoredConversation.id.startsWith("guest-")
            ? GUEST_STORAGE_ID
            : userId
          restoredConversation.messages = mergeConversationMessages(
            normalizeInterruptedMessages(restoredConversation.messages),
            readConversationMessages(storageOwner, restoredConversation.id),
          )
        }

        if (userId && hasServerConversationList) {
          writeConversationList(
            userId,
            nextState.conversations.filter(
              (conversation) => !conversation.id.startsWith("guest-"),
            ),
          )
        }
        writeConversationList(
          GUEST_STORAGE_ID,
          nextState.conversations.filter((conversation) =>
            conversation.id.startsWith("guest-"),
          ),
        )

        nextState.projects =
          projectsResult.status === "fulfilled"
            ? projectsResult.value
            : stateRef.current.projects

        nextState.library =
          libraryResult.status === "fulfilled"
            ? libraryResult.value
            : nextState.library

        nextState.connectors =
          connectorsResult.status === "fulfilled"
            ? connectorsResult.value
            : nextState.connectors

        nextState.scheduledTasks =
          scheduledResult.status === "fulfilled"
            ? scheduledResult.value
            : nextState.scheduledTasks

        nextState.agents =
          agentsResult.status === "fulfilled"
            ? agentsResult.value
            : nextState.agents

        nextState.agentRuns =
          agentRunsResult.status === "fulfilled"
            ? agentRunsResult.value
            : nextState.agentRuns

        nextState.notifications =
          notificationsResult.status === "fulfilled"
            ? notificationsResult.value
            : nextState.notifications

        nextState.models =
          hydratedModelsResult.status === "fulfilled"
            ? hydratedModelsResult.value
            : nextState.models

        const availableModelIds = nextState.models
          .map((model) => model.id)
          .filter((id): id is string => typeof id === "string" && id.length > 0)

        const availableChatModelIds = getChatCapableModels(nextState.models)
          .map((model) => model.id)
          .filter((id): id is string => typeof id === "string" && id.length > 0)

        const preferredModelIds =
          availableChatModelIds.length > 0
            ? availableChatModelIds
            : availableModelIds

        const resolvedDefaultModelId = resolveDefaultChatModelId(
          nextState.models,
          nextState.account.preferences.defaultModel,
        )

        if (resolvedDefaultModelId) {
          nextState.account.preferences.defaultModel = resolvedDefaultModelId
        }

        if (
          !resolvedDefaultModelId &&
          preferredModelIds.length > 0 &&
          !preferredModelIds.includes(
            nextState.account.preferences.defaultModel,
          )
        ) {
          nextState.account.preferences.defaultModel = preferredModelIds[0]
        }

        dispatch({ type: "HYDRATE", state: nextState })
        if (userId && pendingDraftConversationRef.current) {
          pendingDraftConversationRef.current = null
        }
        return conversationsResult.status === "fulfilled"
          ? nextState.activeConversationId
          : null
      }

      const pendingConversation = {
        status: "rejected" as const,
        reason: null,
      }
      const pendingRest = Array.from({ length: 10 }, () => ({
        status: "rejected" as const,
        reason: null,
      }))
      const pendingModels = {
        status: "rejected" as const,
        reason: null,
      }

      applyState(pendingConversation, pendingRest, pendingModels)
      void applyPreloadedModels()

      hydratedRef.current = true

      setBootstrapping(false)

      const conversationsResult = (
        await Promise.allSettled([conversationsPromise])
      )[0]
      const firstPageCount =
        conversationsResult.status === "fulfilled"
          ? conversationsResult.value.length
          : null
      const userId = account.user?.id || api.legacyUserId
      if (userId) authenticatedUserIdRef.current = userId
      const currentActiveId = stateRef.current.activeConversationId
      const currentActiveIsOwned =
        currentActiveId &&
        conversationOwnerByIdRef.current.get(currentActiveId) === userId
      const activeCandidate =
        (currentActiveIsOwned ? currentActiveId : null) ||
        readActiveConversationId(userId)
      if (
        activeCandidate &&
        conversationsResult.status === "fulfilled" &&
        !conversationsResult.value.some(
          (conversation) => conversation.id === activeCandidate,
        )
      ) {
        try {
          const savedConversation = await api.getConversation(activeCandidate)
          conversationsResult.value = [
            ...conversationsResult.value,
            savedConversation,
          ]
        } catch (error) {
          if (!(error instanceof ApiError && error.status === 404)) {
            handleApiError(error)
          }
        }
      }

      const activeId = applyState(
        conversationsResult,
        pendingRest,
        pendingModels,
      )
      if (conversationsResult.status === "fulfilled") {
        conversationPageRef.current = 1
        setHasMoreConversations((firstPageCount || 0) >= 20)
      }
      const activeLoadedMessages =
        conversationsResult.status === "fulfilled"
          ? conversationsResult.value.find(
              (conversation) => conversation.id === activeId,
            )?.messages.length
          : 0
      let activeMessagesPromise: Promise<void> = Promise.resolve()
      if (activeId && !activeId.startsWith("guest-") && !activeLoadedMessages) {
        activeMessagesPromise = api.getConversationMessages(activeId).then(
          (messages) => {
            if (
              userId &&
              (stateRef.current.account.user?.id ||
                api.legacyUserId ||
                authenticatedUserIdRef.current) === userId
            ) {
              const cachedMessages =
                stateRef.current.conversations.find(
                  (conversation) => conversation.id === activeId,
                )?.messages || []
              writeConversationMessages(
                userId,
                activeId,
                mergeConversationMessages(cachedMessages, messages),
              )
            }
            dispatch({
              type: "SET_CONVERSATION_MESSAGES",
              id: activeId,
              messages,
            })
          },
          (error) => {
            handleApiError(error)
          },
        )
      }

      await activeMessagesPromise

      const projectsPromise = api.getProjects()
      const restPromise = Promise.allSettled([
        projectsPromise,

        api.getLibrary(),

        api.getConnectors(),

        api.getScheduledTasks(),

        api.getAgents(),

        api.getAgentRuns(),

        api.getMemories(),

        api.getUsage(),

        api.getNotifications(),

        api.getSubscription(),
      ])

      void projectsPromise.then(
        (projects) => {
          const currentUserId =
            stateRef.current.account.user?.id || authenticatedUserIdRef.current
          if (currentUserId === (account.user?.id || api.legacyUserId)) {
            dispatch({ type: "SET_PROJECTS", projects })
          }
        },
        () => {
          // The settled workspace sync below reports project loading errors.
        },
      )

      const [restSettled, modelsResult] = await Promise.all([
        restPromise,
        modelsResultPromise,
      ])
      applyState(conversationsResult, restSettled, modelsResult)
      if (conversationsResult.status === "fulfilled") {
        const listedConversationIds = new Set(
          conversationsResult.value.map((conversation) => conversation.id),
        )
        for (const [id, entry] of pendingPersistedConversationsRef.current) {
          if (
            entry.userId === account.user?.id &&
            listedConversationIds.has(id)
          ) {
            pendingPersistedConversationsRef.current.delete(id)
          }
        }
      }

      const failedParts = [
        conversationsResult,
        ...restSettled,
        modelsResult,
      ].filter((item) => item.status === "rejected")

      if (failedParts.length > 0)
        dispatch({
          type: "ADD_NOTIFICATION",
          notification: createNotification(
            "task_failed",
            "Workspace partially synced",
            "Some workspace data could not be loaded. Retrying in the background.",
          ),
        })
      if (conversationsResult.status === "fulfilled") {
        retryAttemptRef.current = 0
        if (failedParts.length === 0) setBootstrapError(null)
      }

      if (
        conversationsResult.status === "rejected" &&
        retryAttemptRef.current < 5
      ) {
        const delay = Math.min(30000, 4000 * 2 ** retryAttemptRef.current)
        retryAttemptRef.current += 1
        window.clearTimeout(retryTimerRef.current)
        retryTimerRef.current = window.setTimeout(
          () => void refreshRef.current(),
          delay,
        )
      }
    } catch (error) {
      const message = handleApiError(error, false)

      const authFailure =
        error instanceof ApiError && [401, 403].includes(error.status)

      if (error instanceof ApiError && error.status === 401) {
        const guestWorkspace = createGuestBootstrapWorkspace()
        dispatch({ type: "HYDRATE", state: guestWorkspace })
        void applyPreloadedModels(true)
        activeConversationIdRef.current = guestWorkspace.activeConversationId
        authenticatedUserIdRef.current = undefined
        hydratedRef.current = true
        setGuestLimitReached(false)
        guestLimitReachedRef.current = false
        void refreshGuestChatStatus()
      } else if (error instanceof ApiError && error.status === 403) {
        dispatch({
          type: "SET_AUTH_STATUS",
          status: "forbidden",
          clearUser: true,
        })
      } else if (!authFailure) {
        if (!stateRef.current.account.user) {
          dispatch({
            type: "SET_AUTH_STATUS",
            status: "unauthenticated",
            clearUser: true,
          })
        }

        void applyPreloadedModels()

        dispatch({
          type: "ADD_NOTIFICATION",
          notification: createNotification(
            "task_failed",
            "Workspace sync failed",
            message,
          ),
        })

        // Retry quietly with backoff; the chat UI stays usable meanwhile.
        if (retryAttemptRef.current < 5) {
          const delay = Math.min(30000, 4000 * 2 ** retryAttemptRef.current)

          retryAttemptRef.current += 1

          window.clearTimeout(retryTimerRef.current)

          retryTimerRef.current = window.setTimeout(
            () => void refreshRef.current(),
            delay,
          )
        }
      }

      setBootstrapError(message)
    } finally {
      setBootstrapping(false)
    }
  }, [api, handleApiError, refreshGuestChatStatus])

  useEffect(() => {
    refreshRef.current = refreshWorkspace

    void refreshWorkspace()

    return () => window.clearTimeout(retryTimerRef.current)
  }, [refreshWorkspace])

  useEffect(() => {
    const activeId = state.activeConversationId
    const userId = storageOwnerIdForConversation(
      activeId,
      state.account,
      api.legacyUserId,
      authenticatedUserIdRef.current,
    )
    if (
      !userId ||
      !activeId ||
      !state.conversations.some((conversation) => conversation.id === activeId)
    ) {
      return
    }
    try {
      window.localStorage.setItem(
        activeConversationStorageKey(userId),
        activeId,
      )
    } catch {
      // Active-chat memory is optional; Laravel remains the source of truth.
    }
  }, [
    state.account.authStatus,
    state.account.user?.id,
    state.activeConversationId,
    state.conversations,
    api.legacyUserId,
  ])

  useEffect(() => {
    const userId = storageOwnerIdForConversation(
      state.activeConversationId,
      state.account,
      api.legacyUserId,
      authenticatedUserIdRef.current,
    )
    if (!userId) return

    const timer = window.setTimeout(() => {
      const guestConversations = state.conversations.filter((conversation) =>
        conversation.id.startsWith("guest-"),
      )
      writeConversationList(GUEST_STORAGE_ID, guestConversations)
      if (userId !== GUEST_STORAGE_ID) {
        writeConversationList(
          userId,
          state.conversations.filter(
            (conversation) => !conversation.id.startsWith("guest-"),
          ),
        )
      }
    }, 500)

    return () => window.clearTimeout(timer)
  }, [
    api.legacyUserId,
    state.account,
    state.activeConversationId,
    state.conversations,
  ])

  useEffect(() => {
    const conversation = activeConversation
    const userId = storageOwnerIdForConversation(
      conversation?.id,
      state.account,
      api.legacyUserId,
      authenticatedUserIdRef.current,
    )
    if (!userId || !conversation?.messages.length) return

    const timer = window.setTimeout(() => {
      writeConversationMessages(userId, conversation.id, conversation.messages)
    }, 500)

    return () => window.clearTimeout(timer)
  }, [activeConversation, api.legacyUserId, state.account])

  const applyEvent = useCallback(
    (conversationId: string, messageId: string, event: StreamEvent) => {
      if (event.type === "conversationId") return

      if (event.type === "status") {
        dispatch({
          type: "UPDATE_MESSAGE",

          conversationId,

          id: messageId,

          patch: { status: event.status, progress: undefined },
        })

        return
      }

      if (event.type === "progress") {
        dispatch({
          type: "UPDATE_MESSAGE",
          conversationId,
          id: messageId,
          patch: { progress: event.message },
        })
        return
      }

      if (event.type === "legacyMessage") {
        dispatch({
          type: "UPDATE_MESSAGE",
          conversationId,
          id: messageId,
          patch: { backendMessageId: event.messageId },
        })
        return
      }

      if (event.type === "delta") {
        const next = (streamRegistry.current.get(messageId) || "") + event.text

        streamRegistry.current.set(messageId, next)

        dispatch({
          type: "UPDATE_MESSAGE",

          conversationId,

          id: messageId,

          patch: { content: next, status: "streaming", progress: undefined },
        })

        window.__garadStreams = {
          ...(window.__garadStreams || {}),

          [messageId]: next,
        }

        return
      }

      if (event.type === "tool") {
        const current = toolRegistry.current.get(messageId) || []

        const existing = current.findIndex((item) => item.id === event.tool.id)

        const next =
          existing >= 0
            ? current.map((item, index) =>
                index === existing ? { ...item, ...event.tool } : item,
              )
            : [...current, event.tool]

        toolRegistry.current.set(messageId, next)

        dispatch({
          type: "UPDATE_MESSAGE",

          conversationId,

          id: messageId,

          patch: {
            progress: undefined,
            ...(event.tool.status === "queued" ||
            event.tool.status === "running"
              ? { status: "tool_call" as const }
              : {}),
            toolCalls: next,
          },
        })

        return
      }

      if (event.type === "citation") {
        const current = citationRegistry.current.get(messageId) || []

        const next = [...current, event.citation]

        citationRegistry.current.set(messageId, next)
        const currentTools = toolRegistry.current.get(messageId) || []
        const hasRunningSearch = currentTools.some(
          (tool) => tool.name === "web_search" && tool.status === "running",
        )
        const nextTools = hasRunningSearch
          ? currentTools.map((tool) =>
              tool.name === "web_search" && tool.status === "running"
                ? {
                    ...tool,
                    detail: `Sources found: ${next.length}`,
                  }
                : tool,
            )
          : currentTools
        if (hasRunningSearch) toolRegistry.current.set(messageId, nextTools)

        window.__garadCitations = {
          ...(window.__garadCitations || {}),

          [messageId]: next,
        }

        dispatch({
          type: "UPDATE_MESSAGE",

          conversationId,

          id: messageId,

          patch: {
            citations: next,
            ...(hasRunningSearch ? { toolCalls: nextTools } : {}),
          },
        })

        return
      }

      if (event.type === "artifact") {
        const current = artifactRegistry.current.get(messageId) || []

        const next = [...current, event.artifact]

        artifactRegistry.current.set(messageId, next)

        dispatch({
          type: "UPDATE_MESSAGE",

          conversationId,

          id: messageId,

          patch: { artifacts: next },
        })

        return
      }

      if (event.type === "imageSearch") {
        if (event.messageId) {
          dispatch({
            type: "UPDATE_MESSAGE",
            conversationId,
            id: messageId,
            patch: { backendMessageId: event.messageId },
          })
        }
        void api
          .searchSmartImages(event.query, event.messageId)
          .then((smartImages) => {
            dispatch({
              type: "UPDATE_MESSAGE",
              conversationId,
              id: messageId,
              patch: {
                smartImages,
                smartImageError:
                  smartImages.length === 0
                    ? "No image results were returned."
                    : undefined,
              },
            })
          })
          .catch((error: unknown) => {
            console.error("Smart image search failed.", {
              code: error instanceof ApiError ? error.code : undefined,
              status: error instanceof ApiError ? error.status : undefined,
            })
            dispatch({
              type: "UPDATE_MESSAGE",
              conversationId,
              id: messageId,
              patch: {
                smartImageError:
                  "Image search is temporarily unavailable. The response is still available.",
              },
            })
          })
        return
      }

      if (event.type === "entityHighlights") {
        dispatch({
          type: "UPDATE_MESSAGE",
          conversationId,
          id: messageId,
          patch: { entities: event.entities },
        })
        return
      }

      if (event.type === "done") {
        dispatch({
          type: "UPDATE_MESSAGE",

          conversationId,

          id: messageId,

          patch: { status: "completed", progress: undefined },
        })

        return
      }

      const currentTools = toolRegistry.current.get(messageId) || []
      const failedTools = currentTools.map((tool) =>
        tool.status === "running"
          ? { ...tool, status: "failed" as const, detail: event.message }
          : tool,
      )
      toolRegistry.current.set(messageId, failedTools)
      dispatch({
        type: "UPDATE_MESSAGE",

        conversationId,

        id: messageId,

        patch: {
          status: "error",
          error: event.message,
          toolCalls: failedTools,
        },
      })
    },

    [api],
  )

  const streamAssistant = useCallback(
    async (
      conversation: Conversation,
      assistant: Message,
      messages: Message[],
      tools?: ChatRequest["tools"],
      selectedModelId?: string,
      mentionUserIds?: string[],
    ) => {
      const controller = new AbortController()
      const requestId = crypto.randomUUID()
      const guestRequest =
        stateRef.current.account.authStatus === "unauthenticated"

      let streamConversationId = conversation.id
      generationControllers.current.set(streamConversationId, controller)
      generationControllersByMessage.current.set(assistant.id, controller)
      generationRequestIdsByMessage.current.set(assistant.id, requestId)
      liveGenerationMessageIds.add(assistant.id)

      streamRegistry.current.set(assistant.id, "")

      citationRegistry.current.set(assistant.id, [])

      toolRegistry.current.set(assistant.id, [])

      artifactRegistry.current.set(assistant.id, [])

      window.__garadStreams = {
        ...(window.__garadStreams || {}),

        [assistant.id]: "",
      }

      let finalStatus: Message["status"] = "completed"

      let finalError: string | undefined

      const startedAt = Date.now()
      const progressTimer = window.setInterval(() => {
        if (streamRegistry.current.get(assistant.id)) return

        const elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000)
        const progress =
          elapsedSeconds < 10
            ? "Sending your request…"
            : elapsedSeconds < 30
              ? "Waiting for the model's first response…"
              : `Still waiting for the model's first response · ${elapsedSeconds}s`

        dispatch({
          type: "UPDATE_MESSAGE",
          conversationId: streamConversationId,
          id: assistant.id,
          patch: { progress },
        })
      }, 5000)
      let receivedDoneEvent = false

      const requestedModelId =
        conversation.modelId || state.account.preferences.defaultModel

      const requestedModel =
        selectedModelId?.trim() || requestedModelId || state.models[0]?.id
      const selectedModel = state.models.find(
        (model) =>
          model.id === requestedModel || model.displayName === requestedModel,
      )
      const requestModelId =
        selectedModel?.id || requestedModel || "gpt-4o-mini"

      try {
        if (
          !guestRequest &&
          selectedModel &&
          (selectedModel.locked ||
            (selectedModel.availability === "unavailable" &&
              state.account.user?.isSuperAdmin !== true))
        ) {
          throw new ApiError(
            selectedModel.locked
              ? "Your current plan does not allow access to the selected model."
              : "The selected model is temporarily unavailable.",
            403,
            selectedModel.locked
              ? "chat_model_plan_required"
              : "selected_model_unavailable",
          )
        }

        for await (const event of transport.stream(
          {
            conversationId: streamConversationId,

            model: requestModelId,

            mode: conversation.mode,

            guest: guestRequest,

            projectId: conversation.projectId,

            mentionUserIds,

            messages: messages.map(
              ({ role, content, attachmentIds, backendMessageId }) => ({
                role,

                content,

                attachmentIds,

                backendMessageId,
              }),
            ),

            personalization: {
              language: stateRef.current.account.preferences.language,
              responseTraits: Array.isArray(
                stateRef.current.account.preferences.responseTraits,
              )
                ? stateRef.current.account.preferences.responseTraits.filter(
                    (trait): trait is string => typeof trait === "string",
                  )
                : [],
              customInstructions:
                typeof stateRef.current.account.preferences
                  .customInstructions === "string"
                  ? stateRef.current.account.preferences.customInstructions
                  : "",
            },

            tools,
          },

          controller.signal,
        )) {
          if (
            controller.signal.aborted ||
            generationRequestIdsByMessage.current.get(assistant.id) !==
              requestId
          ) {
            break
          }

          if (event.type === "conversationId") {
            const previousId = streamConversationId
            streamConversationId = event.conversationId
            generationControllers.current.set(streamConversationId, controller)

            const currentConversation =
              stateRef.current.conversations.find(
                (item) => item.id === previousId,
              ) || conversation
            const latestUserMessage = [...messages]
              .reverse()
              .find((message) => message.role === "user")
            const title =
              currentConversation.title === "New conversation"
                ? latestUserMessage?.content.slice(0, 42) || "File analysis"
                : currentConversation.title
            const migratedConversation = {
              ...currentConversation,
              id: streamConversationId,
              title,
              updatedAt: new Date().toISOString(),
            }

            dispatch({
              type: "REMAP_CONVERSATION_ID",
              fromId: previousId,
              conversation: migratedConversation,
            })

            const ownerId =
              conversationOwnerByIdRef.current.get(previousId) ||
              authenticatedUserIdRef.current
            if (ownerId) {
              migrateConversationMessageCache(
                ownerId,
                previousId,
                streamConversationId,
              )
              conversationOwnerByIdRef.current.delete(previousId)
              conversationOwnerByIdRef.current.set(
                streamConversationId,
                ownerId,
              )
            }
            const pending =
              pendingPersistedConversationsRef.current.get(previousId)
            if (pending) {
              pendingPersistedConversationsRef.current.delete(previousId)
              pendingPersistedConversationsRef.current.set(
                streamConversationId,
                {
                  ...pending,
                  conversation: migratedConversation,
                },
              )
            }

            if (
              api.configured &&
              !guestRequest &&
              title !== currentConversation.title
            ) {
              void api
                .updateConversation(streamConversationId, { title })
                .catch((error) => handleApiError(error, false))
            }
            continue
          }

          if (event.type === "error") {
            throw new ApiError(
              event.message,
              event.status || 500,
              event.code || "stream_error",
            )
          }

          if (
            controller.signal.aborted ||
            generationRequestIdsByMessage.current.get(assistant.id) !==
              requestId
          ) {
            break
          }
          if (event.type === "done") receivedDoneEvent = true
          if (event.type === "legacyMessage") {
            applyEvent(streamConversationId, assistant.id, event)
            const latestUserMessage = [...messages]
              .reverse()
              .find((message) => message.role === "user")
            if (latestUserMessage) {
              dispatch({
                type: "UPDATE_MESSAGE",
                conversationId: streamConversationId,
                id: latestUserMessage.id,
                patch: { backendMessageId: event.messageId },
              })
            }
            continue
          }
          applyEvent(streamConversationId, assistant.id, event)
        }

        if (controller.signal.aborted) {
          finalStatus = "stopped"
          dispatch({
            type: "UPDATE_MESSAGE",
            conversationId: streamConversationId,
            id: assistant.id,
            patch: { status: "stopped", progress: undefined },
          })
        } else if (!receivedDoneEvent) {
          finalStatus = "error"
          finalError =
            "The response stream ended before the model finished. Please retry."
          dispatch({
            type: "UPDATE_MESSAGE",
            conversationId: streamConversationId,
            id: assistant.id,
            patch: {
              status: "error",
              error: finalError,
              progress: undefined,
            },
          })
        } else if (conversation.mode === "agents")
          dispatch({
            type: "ADD_NOTIFICATION",

            notification: createNotification(
              "agent_completed",

              "Agent completed",

              `Conversation task in ${conversation.title} finished successfully.`,
            ),
          })
      } catch (error) {
        if (
          generationRequestIdsByMessage.current.get(assistant.id) !== requestId
        ) {
          return
        }

        const currentTools = toolRegistry.current.get(assistant.id) || []
        const closedTools = currentTools.map((tool) =>
          tool.status === "running"
            ? {
                ...tool,
                status: "failed" as const,
                detail: controller.signal.aborted
                  ? "Cancelled"
                  : "Request failed",
              }
            : tool,
        )
        toolRegistry.current.set(assistant.id, closedTools)
        if (controller.signal.aborted) {
          finalStatus = "stopped"

          dispatch({
            type: "UPDATE_MESSAGE",

            conversationId: streamConversationId,

            id: assistant.id,

            patch: {
              status: "stopped",
              progress: undefined,
              toolCalls: closedTools,
            },
          })
        } else {
          handleApiError(error, false)

          const message =
            error instanceof ApiError
              ? chatErrorSummary(error.message, error.code, error.status)
              : chatErrorSummary(
                  error instanceof Error ? error.message : undefined,
                )

          finalStatus = "error"

          finalError = message

          dispatch({
            type: "UPDATE_MESSAGE",

            conversationId: streamConversationId,

            id: assistant.id,

            patch: {
              status: "error",
              error: message,
              progress: undefined,
              toolCalls: closedTools,
            },
          })
        }
      } finally {
        window.clearInterval(progressTimer)
        const ownsRequest =
          generationRequestIdsByMessage.current.get(assistant.id) === requestId
        if (ownsRequest) {
          generationControllers.current.delete(streamConversationId)
          generationControllers.current.delete(conversation.id)
          generationControllersByMessage.current.delete(assistant.id)
          generationRequestIdsByMessage.current.delete(assistant.id)
          liveGenerationMessageIds.delete(assistant.id)
        }

        if (ownsRequest) {
          const finalMessages = mergeConversationMessages(messages, [
            {
              ...assistant,
              content: streamRegistry.current.get(assistant.id) || "",
              status: finalStatus,
              citations: citationRegistry.current.get(assistant.id) || [],
              toolCalls: toolRegistry.current.get(assistant.id) || [],
              artifacts: artifactRegistry.current.get(assistant.id) || [],
              error: finalError,
              progress: undefined,
            },
          ])
          const storageOwner = storageOwnerIdForConversation(
            streamConversationId,
            stateRef.current.account,
            api.legacyUserId,
            authenticatedUserIdRef.current,
          )
          writeConversationMessages(
            storageOwner,
            streamConversationId,
            finalMessages,
          )
        }

        if (
          ownsRequest &&
          !guestRequest &&
          api.configured &&
          !api.usesLegacyChatBackend
        )
          void api

            .updateMessage(assistant.id, {
              content:
                streamRegistry.current.get(assistant.id) || assistant.content,

              status: finalStatus,

              citations: citationRegistry.current.get(assistant.id) || [],

              toolCalls: toolRegistry.current.get(assistant.id) || [],

              artifacts: artifactRegistry.current.get(assistant.id) || [],

              error: finalError,
            })

            .catch((error) => {
              handleApiError(error, false)
            })
      }

      return finalStatus === "completed"
    },

    [
      api,
      applyEvent,
      handleApiError,
      state.account.preferences.defaultModel,
      transport,
    ],
  )

  const createConversation = useCallback(
    async (
      mode: Conversation["mode"] = state.account.preferences.defaultMode,
    ): Promise<Conversation | null> => {
      if (conversationCreationRef.current) {
        return conversationCreationRef.current
      }

      const timestamp = new Date().toISOString()
      const isGuest = stateRef.current.account.authStatus === "unauthenticated"

      const draft: Conversation = {
        id: `${isGuest ? "guest-" : ""}${crypto.randomUUID()}`,

        title: "New conversation",

        messages: [],

        modelId: state.account.preferences.defaultModel,

        pinned: false,

        archived: false,

        shared: false,

        mode,

        createdAt: timestamp,

        updatedAt: timestamp,
      }

      const pending = (async () => {
        try {
          const conversation =
            api.configured && !isGuest
              ? await api.createConversation(draft)
              : draft
          const ownerId = authenticatedUserIdRef.current
          if (api.configured && ownerId) {
            conversationOwnerByIdRef.current.set(conversation.id, ownerId)
            pendingPersistedConversationsRef.current.set(conversation.id, {
              userId: ownerId,
              conversation,
            })
          }
          if (isGuest) pendingDraftConversationRef.current = conversation
          activeConversationIdRef.current = conversation.id
          dispatch({ type: "ADD_CONVERSATION", conversation })
          return conversation
        } catch (error) {
          handleApiError(error)
          return null
        }
      })()
      conversationCreationRef.current = pending
      void pending.finally(() => {
        if (conversationCreationRef.current === pending) {
          conversationCreationRef.current = null
        }
      })
      return pending
    },

    [
      api,
      handleApiError,
      state.account.preferences.defaultMode,
      state.account.preferences.defaultModel,
    ],
  )

  const createDraftConversation = useCallback(
    (mode: Conversation["mode"] = state.account.preferences.defaultMode) => {
      const currentState = stateRef.current
      const activeConversation = currentState.conversations.find(
        (conversation) => conversation.id === currentState.activeConversationId,
      )
      const ownerId =
        currentState.account.user?.id ||
        api.legacyUserId ||
        authenticatedUserIdRef.current ||
        (currentState.account.authStatus === "unauthenticated"
          ? GUEST_STORAGE_ID
          : undefined)
      const canReuseDraft = (conversation: Conversation) => {
        const requestKey = `${ownerId || "anonymous"}:${conversation.id}`
        return (
          conversation.mode === "chat" &&
          conversation.title === "New conversation" &&
          !conversation.lastMessageAt &&
          conversation.messages.every(
            (message) =>
              message.role === "system" ||
              (!message.content.trim() &&
                message.attachmentIds.length === 0 &&
                ["idle", "completed"].includes(message.status)),
          ) &&
          !messageRequestsRef.current.has(requestKey) &&
          !loadingMessageRequestKeys.has(requestKey)
        )
      }
      const pendingDraft = pendingDraftConversationRef.current
      const reusableConversation =
        (pendingDraft && canReuseDraft(pendingDraft) ? pendingDraft : null) ||
        (activeConversation && canReuseDraft(activeConversation)
          ? activeConversation
          : null) ||
        currentState.conversations.find(canReuseDraft)

      if (reusableConversation) {
        activeConversationIdRef.current = reusableConversation.id
        dispatch({ type: "SET_ACTIVE", id: reusableConversation.id })
        pendingDraftConversationRef.current =
          pendingDraft?.id === reusableConversation.id ||
          pendingPersistedConversationsRef.current.has(reusableConversation.id)
            ? reusableConversation
            : null
        return reusableConversation
      }

      const timestamp = new Date().toISOString()
      const isGuest = currentState.account.authStatus === "unauthenticated"
      const conversation: Conversation = {
        id: `${isGuest ? "guest-" : ""}${crypto.randomUUID()}`,
        title: "New conversation",
        messages: [],
        modelId: state.account.preferences.defaultModel,
        pinned: false,
        archived: false,
        shared: false,
        mode,
        createdAt: timestamp,
        updatedAt: timestamp,
      }

      if (ownerId) {
        if (ownerId !== GUEST_STORAGE_ID) {
          conversationOwnerByIdRef.current.set(conversation.id, ownerId)
          pendingPersistedConversationsRef.current.set(conversation.id, {
            userId: ownerId,
            conversation,
          })
        }
      }
      if (isGuest) pendingDraftConversationRef.current = conversation

      activeConversationIdRef.current = conversation.id
      pendingDraftConversationRef.current = conversation
      dispatch({ type: "ADD_CONVERSATION", conversation })
      return conversation
    },
    [
      api.legacyUserId,
      state.account.preferences.defaultMode,
      state.account.preferences.defaultModel,
      loadingMessageRequestKeys,
    ],
  )

  const sendMessage = useCallback(
    async (
      content: string,
      attachmentIds: string[] = [],
      tools?: ChatRequest["tools"],
      modelId?: string,
      options?: {
        skipAssistantResponse?: boolean
        assistantContent?: string
        conversation?: Conversation
        mentionUserIds?: string[]
      },
    ) => {
      const clean = content.trim()
      const guestRequest =
        stateRef.current.account.authStatus === "unauthenticated"
      guestLimitReachedRef.current = false

      if (!clean && attachmentIds.length === 0) return

      if (guestRequest && attachmentIds.length > 0) {
        dispatch({
          type: "ADD_NOTIFICATION",
          notification: createNotification(
            "task_failed",
            "Sign in required",
            "Sign in to upload files or analyze attachments.",
          ),
        })
        return null
      }

      const activeId =
        options?.conversation?.id ||
        activeConversationIdRef.current ||
        stateRef.current.activeConversationId
      const currentConversation =
        stateRef.current.conversations.find(
          (conversation) => conversation.id === activeId,
        ) ||
        (pendingDraftConversationRef.current?.id === activeId
          ? pendingDraftConversationRef.current
          : null) ||
        (options?.conversation?.id === activeId
          ? options.conversation
          : null) ||
        createDraftConversation()
      if (!currentConversation) return

      const isUnpersistedDraft =
        pendingDraftConversationRef.current?.id === currentConversation.id
      const isGuestConversation = currentConversation.id.startsWith("guest-")
      const shouldPersistConversation =
        api.configured &&
        !guestRequest &&
        (isUnpersistedDraft || isGuestConversation)
      const title =
        currentConversation.title === "New conversation"
          ? clean.slice(0, 42) || "File analysis"
          : currentConversation.title
      let conversation = { ...currentConversation, title }

      if (shouldPersistConversation) {
        void (async () => {
          try {
            const persisted = await api.createConversation(conversation)
            const persistedConversation = {
              ...conversation,
              ...persisted,
              title,
              messages: conversation.messages,
            }
            const previousId = conversation.id
            conversation = persistedConversation

            if (isGuestConversation) {
              for (const message of currentConversation.messages) {
                if (message.status !== "completed" || !message.content.trim()) {
                  continue
                }
                await api.createMessage(persistedConversation.id, {
                  role: message.role,
                  content: message.content,
                  attachmentIds: [],
                })
              }
              removeStoredConversation(GUEST_STORAGE_ID, currentConversation.id)
            }

            if (persistedConversation.id !== previousId) {
              dispatch({
                type: "REMAP_CONVERSATION_ID",
                fromId: previousId,
                conversation: persistedConversation,
              })
              if (activeConversationIdRef.current === previousId) {
                activeConversationIdRef.current = persistedConversation.id
              }

              const ownerId =
                conversationOwnerByIdRef.current.get(previousId) ||
                authenticatedUserIdRef.current
              if (ownerId) {
                conversationOwnerByIdRef.current.delete(previousId)
                conversationOwnerByIdRef.current.set(
                  persistedConversation.id,
                  ownerId,
                )
              }
              const pending =
                pendingPersistedConversationsRef.current.get(previousId)
              if (pending) {
                pendingPersistedConversationsRef.current.delete(previousId)
                pendingPersistedConversationsRef.current.set(
                  persistedConversation.id,
                  { ...pending, conversation: persistedConversation },
                )
              }
            } else {
              dispatch({
                type: "UPDATE_CONVERSATION",
                id: persistedConversation.id,
                patch: { title },
              })
            }
          } catch (error) {
            handleApiError(error)
          }
        })()
      }

      if (
        pendingDraftConversationRef.current?.id === currentConversation.id &&
        stateRef.current.account.authStatus !== "loading"
      ) {
        pendingDraftConversationRef.current = null
      }

      if (modelId && conversation.modelId !== modelId) {
        conversation = { ...conversation, modelId }
      }

      if (modelId && conversation.modelId !== currentConversation.modelId) {
        dispatch({
          type: "UPDATE_CONVERSATION",
          id: conversation.id,
          patch: { modelId },
        })
      }

      const user = newMessage("user", content, "completed", attachmentIds)

      const assistant = newMessage(
        "assistant",
        options?.assistantContent || "",
        options?.skipAssistantResponse ? "completed" : "queued",
      )

      dispatch({
        type: "ADD_MESSAGE",

        conversationId: conversation.id,

        message: user,
      })

      dispatch({
        type: "ADD_MESSAGE",

        conversationId: conversation.id,

        message: assistant,
      })

      const userId = storageOwnerIdForConversation(
        conversation.id,
        stateRef.current.account,
        api.legacyUserId,
        authenticatedUserIdRef.current,
      )
      const messagesWithPendingAssistant = mergeConversationMessages(
        conversation.messages,
        [user, assistant],
      )
      writeConversationMessages(
        userId,
        conversation.id,
        messagesWithPendingAssistant,
      )
      const updatedConversationList = sortConversationsByRecent([
        { ...conversation, messages: messagesWithPendingAssistant },
        ...stateRef.current.conversations.filter(
          (item) =>
            item.id !== conversation.id && item.id !== currentConversation.id,
        ),
      ])
      writeConversationList(
        GUEST_STORAGE_ID,
        updatedConversationList.filter((item) => item.id.startsWith("guest-")),
      )
      if (userId) {
        writeConversationList(
          userId,
          userId === GUEST_STORAGE_ID
            ? updatedConversationList
            : updatedConversationList.filter(
                (item) => !item.id.startsWith("guest-"),
              ),
        )
      }

      if (api.configured && !guestRequest && !api.usesLegacyChatBackend) {
        void Promise.allSettled([
          api.createMessage(conversation.id, user),
          api.createMessage(conversation.id, assistant),
        ]).catch((error) => {
          const message = handleApiError(error)
          dispatch({
            type: "UPDATE_MESSAGE",
            conversationId: conversation.id,
            id: assistant.id,
            patch: { status: "error", error: message },
          })
          writeConversationMessages(
            userId,
            conversation.id,
            mergeConversationMessages(conversation.messages, [
              user,
              { ...assistant, status: "error", error: message },
            ]),
          )
        })
      }

      if (conversation.title !== currentConversation.title) {
        dispatch({
          type: "UPDATE_CONVERSATION",

          id: conversation.id,

          patch: { title },
        })

        if (
          api.configured &&
          !guestRequest &&
          (!api.usesLegacyChatBackend || /^\d+$/.test(conversation.id))
        ) {
          void api
            .updateConversation(conversation.id, { title })
            .catch((error) => {
              handleApiError(error, false)
            })
        }
      }

      if (options?.skipAssistantResponse) return assistant.id

      await streamAssistant(
        conversation,
        assistant,
        [...conversation.messages, user],
        tools,
        modelId,
        options?.mentionUserIds,
      )

      if (guestRequest) {
        await refreshGuestChatStatus()
        if (guestLimitReachedRef.current) {
          dispatch({
            type: "DELETE_MESSAGE",
            conversationId: conversation.id,
            id: user.id,
          })
          dispatch({
            type: "DELETE_MESSAGE",
            conversationId: conversation.id,
            id: assistant.id,
          })
          writeConversationMessages(
            GUEST_STORAGE_ID,
            conversation.id,
            conversation.messages,
          )
          return null
        }
      }

      return assistant.id
    },

    [
      api,
      createConversation,
      handleApiError,
      refreshGuestChatStatus,
      streamAssistant,
    ],
  )

  const openConversation = useCallback(
    async (id: string, fallbackConversation?: Conversation) => {
      if (pendingDraftConversationRef.current?.id !== id) {
        pendingDraftConversationRef.current = null
      }

      const conversation =
        state.conversations.find((item) => item.id === id) ||
        (fallbackConversation?.id === id ? fallbackConversation : undefined)
      if (!conversation) return

      if (!state.conversations.some((item) => item.id === id)) {
        dispatch({
          type: "MERGE_CONVERSATIONS",
          conversations: [{ ...conversation, messages: [] }],
        })
      }

      activeConversationIdRef.current = id
      dispatch({ type: "SET_ACTIVE", id })

      const userId = storageOwnerIdForConversation(
        conversation.id,
        state.account,
        api.legacyUserId,
        authenticatedUserIdRef.current,
      )
      const activeGenerationIds = generationRequestIdsByMessage.current
      const cachedMessages = mergeConversationMessages(
        conversation.messages,
        readConversationMessages(userId, id).filter(
          (message) => !activeGenerationIds.has(message.id),
        ),
      )
      const requestKey = `${userId || "anonymous"}:${id}`
      if (cachedMessages.length > 0) {
        dispatch({
          type: "SET_CONVERSATION_MESSAGES",
          id,
          messages: cachedMessages,
        })
      }

      if (!api.configured || id.startsWith("guest-")) return

      if (cachedMessages.length === 0) {
        setLoadingMessageRequestKeys((current) =>
          new Set(current).add(requestKey),
        )
      }

      let request = messageRequestsRef.current.get(requestKey)
      if (!request) {
        request = api.getConversationMessages(id)
        messageRequestsRef.current.set(requestKey, request)
      }

      void request
        .then(
          (messages) => {
            const currentState = stateRef.current
            const currentUserId = storageOwnerIdForConversation(
              id,
              currentState.account,
              api.legacyUserId,
              authenticatedUserIdRef.current,
            )
            const currentConversation =
              currentState.conversations.find((item) => item.id === id) ||
              (fallbackConversation?.id === id ? fallbackConversation : null)
            if (!currentConversation || (userId && currentUserId !== userId)) {
              return
            }

            const mergedMessages = mergeConversationMessages(
              currentConversation.messages,
              messages,
            )
            writeConversationMessages(userId, id, mergedMessages)
            dispatch({
              type: "SET_CONVERSATION_MESSAGES",
              id,
              messages: mergedMessages,
            })
          },
          (error) => {
            if (stateRef.current.activeConversationId === id) {
              handleApiError(error)
            }
          },
        )
        .finally(() => {
          if (messageRequestsRef.current.get(requestKey) === request) {
            messageRequestsRef.current.delete(requestKey)
          }
          setLoadingMessageRequestKeys((current) => {
            if (!current.has(requestKey)) return current
            const next = new Set(current)
            next.delete(requestKey)
            return next
          })
        })
    },
    [
      api,
      dispatch,
      handleApiError,
      state.account.user?.id,
      state.conversations,
    ],
  )

  const updateConversation = useCallback(
    async (id: string, patch: Partial<Conversation>) => {
      const previous = state.conversations.find((item) => item.id === id)
      if (!previous) return false

      dispatch({ type: "UPDATE_CONVERSATION", id, patch })
      if (!api.configured) return true

      try {
        await api.updateConversation(id, patch)
        return true
      } catch (error) {
        dispatch({
          type: "UPDATE_CONVERSATION",
          id,
          patch: {
            title: previous.title,
            pinned: previous.pinned,
            archived: previous.archived,
            projectId: previous.projectId,
            modelId: previous.modelId,
          },
        })
        handleApiError(error)
        return false
      }
    },
    [api, handleApiError, state.conversations],
  )

  const deleteConversation = useCallback(
    async (id: string) => {
      try {
        if (api.configured) await api.deleteConversation(id)
        pendingPersistedConversationsRef.current.delete(id)
        conversationOwnerByIdRef.current.delete(id)
        const shouldSelectNext = state.activeConversationId === id
        const nextConversation = state.conversations.find(
          (item) => item.id !== id,
        )
        dispatch({ type: "DELETE_CONVERSATION", id })
        if (shouldSelectNext && nextConversation && api.configured) {
          void api.getConversationMessages(nextConversation.id).then(
            (messages) =>
              dispatch({
                type: "SET_CONVERSATION_MESSAGES",
                id: nextConversation.id,
                messages,
              }),
            (error) => handleApiError(error),
          )
        }
        return true
      } catch (error) {
        handleApiError(error)
        return false
      }
    },
    [api, handleApiError, state.activeConversationId, state.conversations],
  )

  const searchConversations = useCallback(
    async (search: string) => {
      const term = search.trim()
      if (!term) return []
      if (!api.configured) {
        return stateRef.current.conversations.filter((conversation) =>
          conversation.title.toLowerCase().includes(term.toLowerCase()),
        )
      }
      try {
        return await api.getConversations({
          page: 1,
          perPage: 100,
          search: term,
          mode: conversationModeRef.current,
        })
      } catch (error) {
        handleApiError(error)
        return []
      }
    },
    [api, handleApiError],
  )

  const refreshConversations = useCallback(
    async (mode: Conversation["mode"]) => {
      conversationModeRef.current = mode
      conversationPageRef.current = 1
      loadMoreConversationsRef.current = false
      setIsLoadingMoreConversations(false)
      setHasMoreConversations(false)
      if (!api.configured) return
      try {
        const conversations = await api.getConversations({
          page: 1,
          perPage: 20,
          mode,
        })
        dispatch({ type: "MERGE_CONVERSATIONS", conversations })
        setHasMoreConversations(conversations.length >= 20)
      } catch (error) {
        handleApiError(error)
      }
    },
    [api, handleApiError],
  )

  const loadMoreConversations = useCallback(async () => {
    if (
      !api.configured ||
      !hasMoreConversations ||
      loadMoreConversationsRef.current
    )
      return

    loadMoreConversationsRef.current = true
    setIsLoadingMoreConversations(true)
    const nextPage = conversationPageRef.current + 1
    try {
      const conversations = await api.getConversations({
        page: nextPage,
        perPage: 20,
        mode: conversationModeRef.current,
      })
      dispatch({ type: "MERGE_CONVERSATIONS", conversations })
      conversationPageRef.current = nextPage
      setHasMoreConversations(conversations.length >= 20)
    } catch (error) {
      handleApiError(error)
    } finally {
      loadMoreConversationsRef.current = false
      setIsLoadingMoreConversations(false)
    }
  }, [api, handleApiError, hasMoreConversations])

  const regenerate = useCallback(
    async (messageId: string) => {
      if (!activeConversation) return

      const index = activeConversation.messages.findIndex(
        (message) => message.id === messageId,
      )

      if (index < 0) return

      const assistant = {
        ...activeConversation.messages[index],

        content: "",

        status: "queued" as const,

        citations: [],

        artifacts: [],

        toolCalls: [],

        error: undefined,
      }

      dispatch({
        type: "UPDATE_MESSAGE",

        conversationId: activeConversation.id,

        id: messageId,

        patch: assistant,
      })

      await streamAssistant(
        activeConversation,

        assistant,

        activeConversation.messages.slice(0, index),
      )
    },

    [activeConversation, streamAssistant],
  )

  const retryFromUser = useCallback(
    async (messageId: string, replacement?: string) => {
      if (!activeConversation) return

      const index = activeConversation.messages.findIndex(
        (message) => message.id === messageId,
      )

      const user = activeConversation.messages[index]

      if (!user || user.role !== "user") return

      const updatedUser = {
        ...user,

        content: replacement?.trim() || user.content,

        updatedAt: new Date().toISOString(),
      }

      const assistant = newMessage("assistant", "", "queued")

      dispatch({
        type: "UPDATE_MESSAGE",

        conversationId: activeConversation.id,

        id: user.id,

        patch: { content: updatedUser.content },
      })

      if (api.configured && !api.usesLegacyChatBackend)
        void api

          .updateMessage(user.id, { content: updatedUser.content })

          .catch((error) => handleApiError(error, false))

      dispatch({
        type: "TRUNCATE_AFTER",

        conversationId: activeConversation.id,

        id: user.id,
      })

      dispatch({
        type: "ADD_MESSAGE",

        conversationId: activeConversation.id,

        message: assistant,
      })

      if (api.configured && !api.usesLegacyChatBackend)
        void api

          .createMessage(activeConversation.id, assistant)

          .catch((error) => handleApiError(error, false))

      await streamAssistant(activeConversation, assistant, [
        ...activeConversation.messages.slice(0, index),

        updatedUser,
      ])

      return assistant.id
    },

    [activeConversation, api, handleApiError, streamAssistant],
  )

  const continueResponse = useCallback(async () => {
    if (!activeConversation) return

    const assistant = newMessage("assistant", "", "queued")

    dispatch({
      type: "ADD_MESSAGE",

      conversationId: activeConversation.id,

      message: assistant,
    })

    if (api.configured && !api.usesLegacyChatBackend)
      void api

        .createMessage(activeConversation.id, assistant)

        .catch((error) => handleApiError(error, false))

    await streamAssistant(activeConversation, assistant, [
      ...activeConversation.messages,

      newMessage("user", "Continue from the previous response.", "completed"),
    ])
  }, [activeConversation, api, handleApiError, streamAssistant])

  const stopGenerationFor = useCallback(
    (conversationId: string) => {
      const conversation = stateRef.current.conversations.find(
        (item) => item.id === conversationId,
      )
      if (!conversation) return

      const activeAssistants = conversation.messages.filter(
        (message) =>
          message.role === "assistant" &&
          ["queued", "thinking", "tool_call", "streaming"].includes(
            message.status,
          ),
      )

      for (const assistant of activeAssistants) {
        dispatch({
          type: "UPDATE_MESSAGE",
          conversationId: conversation.id,
          id: assistant.id,
          patch: { status: "stopped" },
        })
        generationControllersByMessage.current.get(assistant.id)?.abort()
      }
    },
    [dispatch],
  )

  const stopGeneration = useCallback(() => {
    if (activeConversation) stopGenerationFor(activeConversation.id)
  }, [activeConversation, stopGenerationFor])

  const addFiles = useCallback(
    (files: File[]) => {
      const attachments = files.map(createAttachment)
      const uploads: Promise<void>[] = []

      attachments.forEach((attachment, index) => {
        fileRegistry.current.set(attachment.id, files[index])
      })

      dispatch({ type: "ADD_ATTACHMENTS", attachments })

      attachments.forEach((attachment, index) => {
        const task = uploadFile(files[index], attachment, (patch) =>
          dispatch({ type: "UPDATE_ATTACHMENT", id: attachment.id, patch }),
        )

        uploadCancels.current.set(attachment.id, task.cancel)
        uploads.push(task.promise)

        void task.promise.finally(() =>
          uploadCancels.current.delete(attachment.id),
        )
      })

      void Promise.all(uploads).then(refreshAccountUsage)

      return attachments.map((item) => item.id)
    },
    [refreshAccountUsage],
  )

  const retryAttachment = useCallback(
    (id: string) => {
      const file = fileRegistry.current.get(id)

      const attachment = state.attachments.find((item) => item.id === id)

      if (!file || !attachment) return

      const task = uploadFile(file, attachment, (patch) =>
        dispatch({ type: "UPDATE_ATTACHMENT", id, patch }),
      )

      uploadCancels.current.set(id, task.cancel)

      void task.promise
        .finally(() => uploadCancels.current.delete(id))
        .then(refreshAccountUsage)
    },

    [refreshAccountUsage, state.attachments],
  )

  const connectConnector = useCallback(
    async (id: string) => {
      if (!api.configured) {
        throw new ApiError(
          "Live connector access is blocked until the Garad backend is configured.",
          503,
          "live_execution_blocked",
        )
      }
 
      try {
        const consentUrl = await api.getConnectorRedirectUrl(id)
        window.location.assign(consentUrl)
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError],
  )

  const disconnectConnector = useCallback(
    async (id: string) => {
      if (!api.configured) {
        throw new ApiError(
          "Live connector access is blocked until the Garad backend is configured.",
          503,
          "live_execution_blocked",
        )
      }

      try {
        await api.disconnectConnector(id)

        dispatch({
          type: "UPDATE_CONNECTOR",
          id,
          status: "not_connected",
          patch: { installed: false, paused: false },
        })
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError],
  )

  const toggleConnectorPause = useCallback(
    async (id: string) => {
      if (!api.configured) {
        throw new ApiError(
          "Live connector access is blocked until the Garad backend is configured.",
          503,
          "live_execution_blocked",
        )
      }

      try {
        const connector = await api.toggleConnectorPause(id)
        dispatch({ type: "UPSERT_CONNECTOR", connector })
      } catch (error) {
        handleApiError(error)
      }
    },
    [api, handleApiError],
  )

  const createScheduledTask = useCallback(
    async (task: Partial<ScheduledTask>) => {
      if (!api.configured) {
        throw new ApiError(
          "Connect to Garad to create a scheduled task.",
          503,
          "scheduled_tasks_unavailable",
        )
      }

      try {
        const created = await api.createScheduledTask(task)

        dispatch({ type: "ADD_SCHEDULE", task: created })

        return created
      } catch (error) {
        handleApiError(error)
        throw error
      }
    },

    [api, handleApiError],
  )

  const updateScheduledTask = useCallback(
    async (id: string, patch: Partial<ScheduledTask>) => {
      if (!api.configured) {
        throw new ApiError(
          "Connect to Garad to update scheduled tasks.",
          503,
          "scheduled_tasks_unavailable",
        )
      }

      try {
        const updated = await api.updateScheduledTask(id, patch)

        dispatch({ type: "UPDATE_SCHEDULE", id, patch: updated })
      } catch (error) {
        handleApiError(error)
        throw error
      }
    },

    [api, handleApiError],
  )

  const toggleScheduledTask = useCallback(
    async (id: string) => {
      const current = state.scheduledTasks.find((task) => task.id === id)

      if (!current) return

      const nextEnabled = !current.enabled

      if (!api.configured) {
        throw new ApiError(
          "Connect to Garad to update scheduled tasks.",
          503,
          "scheduled_tasks_unavailable",
        )
      }

      try {
        const updated = await api.updateScheduledTask(id, {
          enabled: nextEnabled,
        })
        dispatch({ type: "UPDATE_SCHEDULE", id, patch: updated })
      } catch (error) {
        handleApiError(error)
        throw error
      }
    },

    [api, handleApiError, state.scheduledTasks],
  )

  const deleteScheduledTask = useCallback(
    async (id: string) => {
      if (!api.configured) {
        throw new ApiError(
          "Connect to Garad to delete scheduled tasks.",
          503,
          "scheduled_tasks_unavailable",
        )
      }

      try {
        await api.deleteScheduledTask(id)
        dispatch({ type: "DELETE_SCHEDULE", id })
      } catch (error) {
        handleApiError(error)
        throw error
      }
    },

    [api, handleApiError],
  )

  const runScheduledTaskNow = useCallback(
    async (id: string) => {
      const task = state.scheduledTasks.find((item) => item.id === id)

      if (!task) return

      if (!api.configured) {
        throw new ApiError(
          "Connect to Garad to run scheduled tasks.",
          503,
          "scheduled_tasks_unavailable",
        )
      }

      try {
        const updated = await api.runScheduledTask(id)

        dispatch({ type: "UPDATE_SCHEDULE", id, patch: updated })
      } catch (error) {
        handleApiError(error)
        throw error
      }
    },

    [api, handleApiError, state.scheduledTasks],
  )

  const deleteLibraryItem = useCallback(
    async (id: string): Promise<boolean> => {
      if (!api.configured) {
        dispatch({ type: "DELETE_LIBRARY_ITEM", id })
        return true
      }

      try {
        await api.deleteLibraryItem(id)
      } catch (error) {
        handleApiError(error, false)
        return false
      }

      dispatch({ type: "DELETE_LIBRARY_ITEM", id })
      await refreshAccountUsage()

      return true
    },

    [api, handleApiError, refreshAccountUsage],
  )

  const renameLibraryItem = useCallback(
    async (id: string, name: string) => {
      dispatch({ type: "UPDATE_LIBRARY_ITEM", id, patch: { name } })

      if (!api.configured) return

      try {
        await api.updateLibraryItem(id, { name })
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError],
  )

  const moveLibraryItem = useCallback(
    async (id: string, projectId?: string) => {
      dispatch({ type: "UPDATE_LIBRARY_ITEM", id, patch: { projectId } })

      if (!api.configured) return

      try {
        await api.updateLibraryItem(id, { projectId })
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError],
  )

  const upsertMemory = useCallback(
    async (memory: MemoryItem) => {
      const exists = state.account.memories.some(
        (item) => item.id === memory.id,
      )

      const next = exists
        ? state.account.memories.map((item) =>
            item.id === memory.id ? { ...item, ...memory } : item,
          )
        : [...state.account.memories, memory]

      dispatch({ type: "SET_MEMORIES", memories: next })

      if (!api.configured) return

      try {
        if (exists) {
          const updated = await api.updateMemory(memory.id, memory)

          dispatch({
            type: "SET_MEMORIES",

            memories: next.map((item) =>
              item.id === updated.id ? updated : item,
            ),
          })

          return
        }

        const created = await api.createMemory(memory)

        dispatch({
          type: "SET_MEMORIES",

          memories: next.map((item) =>
            item.id === memory.id ? created : item,
          ),
        })
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError, state.account.memories],
  )

  const deleteMemory = useCallback(
    async (id: string) => {
      dispatch({
        type: "SET_MEMORIES",

        memories: state.account.memories.filter((item) => item.id !== id),
      })

      if (!api.configured) return

      try {
        await api.deleteMemory(id)
      } catch (error) {
        handleApiError(error)
      }
    },
    [api, handleApiError, state.account.memories],
  )

  const clearMemories = useCallback(async () => {
    const memoryIds = state.account.memories.map((item) => item.id)

    dispatch({ type: "SET_MEMORIES", memories: [] })

    if (!api.configured || memoryIds.length === 0) return

    const settled = await Promise.allSettled(
      memoryIds.map((id) => api.deleteMemory(id)),
    )

    const rejected = settled.find((result) => result.status === "rejected")
    if (rejected && rejected.status === "rejected")
      handleApiError(rejected.reason)
  }, [api, handleApiError, state.account.memories])

  const createAgent = useCallback(
    async (payload: Partial<Agent>) => {
      const timestamp = new Date().toISOString()

      const fallback: Agent = {
        id: payload.id || crypto.randomUUID(),

        name: payload.name || "New Agent",

        description: payload.description || "",

        role: payload.role || "Assistant",

        goal: payload.goal || "",

        instructions: payload.instructions || "",

        model: payload.model || state.account.preferences.defaultModel,

        tools: payload.tools || [],

        plugins: payload.plugins || [],

        knowledge: payload.knowledge || [],

        memory: payload.memory || "",

        permissions: payload.permissions || [],

        schedule: payload.schedule,

        status: payload.status || "draft",

        createdAt: timestamp,

        updatedAt: timestamp,
      }

      if (!api.configured) {
        dispatch({ type: "UPSERT_AGENT", agent: fallback })

        return fallback
      }

      try {
        const created = await api.createAgent(payload)

        dispatch({ type: "UPSERT_AGENT", agent: created })

        return created
      } catch (error) {
        handleApiError(error)

        dispatch({ type: "UPSERT_AGENT", agent: fallback })

        return fallback
      }
    },

    [api, handleApiError, state.account.preferences.defaultModel],
  )

  const updateAgent = useCallback(
    async (id: string, patch: Partial<Agent>) => {
      const existing = state.agents.find((item) => item.id === id)

      if (!existing) return

      const optimistic: Agent = {
        ...existing,

        ...patch,

        updatedAt: new Date().toISOString(),
      }

      dispatch({ type: "UPSERT_AGENT", agent: optimistic })

      if (!api.configured) return

      try {
        const updated = await api.updateAgent(id, patch)

        dispatch({ type: "UPSERT_AGENT", agent: updated })
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError, state.agents],
  )

  const deleteAgent = useCallback(
    async (id: string) => {
      dispatch({ type: "DELETE_AGENT", id })

      if (!api.configured) return

      try {
        await api.deleteAgent(id)
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError],
  )

  const runAgent = useCallback(
    async (agentId: string, task: string, projectId?: string) => {
      const timestamp = new Date().toISOString()

      const localRun: AgentRun = {
        id: crypto.randomUUID(),

        agentId,

        task,

        state: "queued",

        progress: 0,

        sources: [],

        outputs: [],

        startedAt: timestamp,

        updatedAt: timestamp,
      }

      dispatch({ type: "UPSERT_AGENT_RUN", run: localRun })

      if (!api.configured) {
        const blockedRun: AgentRun = {
          ...localRun,

          state: "blocked",

          progress: 0,

          currentTask: "Live execution blocked",

          error:
            "Garad live agent execution is blocked until the backend API is configured.",

          updatedAt: new Date().toISOString(),
        }

        dispatch({ type: "UPSERT_AGENT_RUN", run: blockedRun })

        return blockedRun
      }

      try {
        const result = await api.runAgent(agentId, { task, projectId })

        dispatch({ type: "UPSERT_AGENT_RUN", run: result })

        return result
      } catch (error) {
        handleApiError(error)

        const blockedError =
          error instanceof Error && error.message
            ? error.message
            : "Agent run failed"

        dispatch({
          type: "UPSERT_AGENT_RUN",

          run: {
            ...localRun,

            state: "blocked",

            progress: 0,

            currentTask: "Live execution blocked",

            error: blockedError,

            updatedAt: new Date().toISOString(),
          },
        })

        return {
          ...localRun,

          state: "blocked",

          progress: 0,

          currentTask: "Live execution blocked",

          error: blockedError,

          updatedAt: new Date().toISOString(),
        }
      }
    },

    [api, handleApiError],
  )

  const stopAgent = useCallback(
    async (agentId: string, runId?: string) => {
      const run =
        (runId && state.agentRuns.find((item) => item.id === runId)) ||
        state.agentRuns.find((item) => item.agentId === agentId)

      if (!run) return

      dispatch({
        type: "UPSERT_AGENT_RUN",

        run: {
          ...run,

          state: "cancelled",

          updatedAt: new Date().toISOString(),
        },
      })

      if (!api.configured) return

      try {
        const updated = await api.stopAgent(agentId, run.id)

        dispatch({ type: "UPSERT_AGENT_RUN", run: updated })
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError, state.agentRuns],
  )

  const markNotificationRead = useCallback(
    async (id: string) => {
      dispatch({ type: "MARK_NOTIFICATION_READ", id })

      if (!api.configured) return

      try {
        await api.markNotificationRead(id)
      } catch (error) {
        handleApiError(error)
      }
    },

    [api, handleApiError],
  )

  const clearNotifications = useCallback(async () => {
    dispatch({ type: "CLEAR_NOTIFICATIONS" })

    if (!api.configured) return

    try {
      await api.markAllNotificationsRead()
    } catch (error) {
      handleApiError(error)
    }
  }, [api, handleApiError])

  const createProject = useCallback(
    async (name: string): Promise<Project | null> => {
      const cleanName = name.trim()
      if (!cleanName) return null
      try {
        const project = api.configured
          ? await api.createProject({ name: cleanName })
          : {
              id: crypto.randomUUID(),
              name: cleanName,
              description: "",
              instructions: "",
              memoryEnabled: true,
              memory: "",
              agentIds: [],
              sourceIds: [],
              createdAt: new Date().toISOString(),
            }
        dispatch({ type: "ADD_PROJECT", project })
        return project
      } catch (error) {
        handleApiError(error)
        return null
      }
    },
    [api, handleApiError],
  )

  const updateProject = useCallback(
    async (id: string, patch: Partial<Project>): Promise<boolean> => {
      const previous = stateRef.current.projects.find(
        (project) => project.id === id,
      )
      if (!previous) return false
      dispatch({ type: "UPDATE_PROJECT", id, patch })
      if (!api.configured) return true
      try {
        const saved = await api.updateProject(id, patch)
        dispatch({
          type: "UPDATE_PROJECT",
          id,
          patch: saved,
        })
        return true
      } catch (error) {
        dispatch({
          type: "UPDATE_PROJECT",
          id,
          patch: previous,
        })
        handleApiError(error)
        return false
      }
    },
    [api, handleApiError],
  )

  const deleteProject = useCallback(
    async (id: string): Promise<boolean> => {
      if (!stateRef.current.projects.some((project) => project.id === id))
        return false
      try {
        if (api.configured) await api.deleteProject(id)
        dispatch({ type: "DELETE_PROJECT", id })
        return true
      } catch (error) {
        handleApiError(error)
        return false
      }
    },
    [api, handleApiError],
  )

  const savePreferences = useCallback(
    async (patch: Partial<Preferences>): Promise<boolean> => {
      const userId = stateRef.current.account.user?.id
      if (!api.configured) return true

      try {
        const preferences = await api.updatePreferences(patch)
        clearStoredPreferences(userId, patch)
        dispatch({
          type: "UPDATE_PREFERENCES",
          patch: {
            ...patch,
            ...preferences,
            notifications: {
              ...patch.notifications,
              ...preferences.notifications,
            },
          },
        })
        return true
      } catch (error) {
        persistPreferences(patch, userId)
        handleApiError(error, false)
        return false
      }
    },
    [api, handleApiError],
  )

  const cachePreferences = useCallback(
    (patch: Partial<Preferences>) =>
      persistPreferences(patch, stateRef.current.account.user?.id),
    [],
  )

  const clearPreferencesCache = useCallback(
    () => clearStoredPreferences(stateRef.current.account.user?.id),
    [],
  )

  const saveProfile = useCallback(
    async (input: ProfileUpdateInput): Promise<Account["user"]> => {
      if (api.configured) {
        try {
          const user = await api.updateProfile(input)
          persistStoredProfile(user)
          dispatch({ type: "UPDATE_ACCOUNT", patch: { user } })
          return user
        } catch (error) {
          handleApiError(error, false)
          throw error
        }
      }

      if (
        input.old_password ||
        input.new_password ||
        input.new_password_confirmation
      )
        throw new Error("Password changes need a connected Garad AI account.")

      const currentUser = stateRef.current.account.user
      if (!currentUser) throw new Error("Sign in to edit your Garad profile.")
      if (input.avatar && input.avatar.size > 1_500_000)
        throw new Error(
          "Without a server connection, profile photos must be under 1.5 MB.",
        )

      let avatarUrl = currentUser.avatarUrl
      const avatarFile = input.avatar
      if (avatarFile) {
        avatarUrl = await new Promise<string>((resolve, reject) => {
          const reader = new FileReader()
          reader.onload = () =>
            typeof reader.result === "string"
              ? resolve(reader.result)
              : reject(new Error("The profile photo could not be read."))
          reader.onerror = () =>
            reject(
              reader.error || new Error("The profile photo could not be read."),
            )
          reader.readAsDataURL(avatarFile)
        })
      }

      const user = {
        ...currentUser,
        name: `${input.name.trim()} ${input.surname.trim()}`.trim(),
        givenName: input.name.trim(),
        surname: input.surname.trim(),
        phone: input.phone || "",
        country: input.country || "",
        state: input.state || "",
        city: input.city || "",
        postal: input.postal || "",
        address: input.address || "",
        avatarUrl,
      }
      if (!persistStoredProfile(user))
        throw new Error("The profile could not be saved in this browser.")
      dispatch({ type: "UPDATE_ACCOUNT", patch: { user } })
      return user
    },
    [api, handleApiError],
  )

  const selectPlan = useCallback(
    async (planId: string) => {
      const previous = state.account.subscription

      const optimistic: Subscription = {
        id: previous?.id || crypto.randomUUID(),

        planId,

        status: previous?.status || "active",

        renewsAt: previous?.renewsAt,

        billingProvider: previous?.billingProvider || "none",
      }

      dispatch({
        type: "UPDATE_ACCOUNT",

        patch: {
          subscription: optimistic,

          entitlements: undefined,
        },
      })

      if (!api.configured) return optimistic

      try {
        const subscription = await api.changeSubscription(planId)

        const latestAccount = await api.getAccount().catch(() => null)

        if (latestAccount)
          dispatch({
            type: "UPDATE_ACCOUNT",

            patch: {
              subscription: latestAccount.subscription || subscription,

              usage: latestAccount.usage,

              entitlements: latestAccount.entitlements,
            },
          })
        else {
          dispatch({
            type: "UPDATE_ACCOUNT",

            patch: { subscription },
          })

          const usage = await api.getUsage().catch(() => null)
          if (usage)
            dispatch({
              type: "UPDATE_ACCOUNT",

              patch: { usage },
            })
        }

        return subscription
      } catch (error) {
        dispatch({
          type: "UPDATE_ACCOUNT",

          patch: { subscription: previous || null },
        })

        handleApiError(error)

        return optimistic
      }
    },

    [api, handleApiError, state.account.subscription],
  )

  const logout = useCallback(async () => {
    if (api.configured && state.account.authStatus === "authenticated") {
      try {
        await api.logout()
      } catch (error) {
        handleApiError(error)
        throw error
      }
    }

    authenticatedUserIdRef.current = undefined
    pendingPersistedConversationsRef.current.clear()
    conversationOwnerByIdRef.current.clear()
    const guestWorkspace = createGuestBootstrapWorkspace()
    activeConversationIdRef.current = guestWorkspace.activeConversationId
    setGuestLimitReached(false)
    guestLimitReachedRef.current = false
    dispatch({ type: "HYDRATE", state: guestWorkspace })
    void refreshGuestChatStatus()
  }, [api, handleApiError, refreshGuestChatStatus, state.account.authStatus])

  const authenticatedForScheduled = state.account.authStatus === "authenticated"

  useEffect(() => {
    if (!api.configured || !authenticatedForScheduled) return

    let cancelled = false
    const timers = new Set<number>()

    const tick = async () => {
      if (cancelled || document.visibilityState === "hidden") return

      try {
        const result = await api.tickScheduledTasks()

        if (cancelled) return

        if (result.delivered > 0) void refreshRef.current()

        if (result.started) {
          for (const delay of [30_000, 90_000, 180_000]) {
            const id = window.setTimeout(() => void tick(), delay)

            timers.add(id)
          }
        }
      } catch (error) {
        console.error("Scheduled task background check failed.", error)
      }
    }

    const first = window.setTimeout(() => void tick(), 8_000)
    const interval = window.setInterval(() => void tick(), 60_000)

    return () => {
      cancelled = true
      window.clearTimeout(first)
      window.clearInterval(interval)
      timers.forEach((id) => window.clearTimeout(id))
    }
  }, [api, authenticatedForScheduled])

  const restoreSession = useCallback(() => {
    if (api.configured) {
      void refreshWorkspace()

      return
    }

    dispatch({
      type: "UPDATE_ACCOUNT",

      patch: {
        ...accountFixture,

        authStatus: "authenticated",
      },
    })
  }, [api.configured, refreshWorkspace])

  const clearGuestLimit = useCallback(() => {
    guestLimitReachedRef.current = false
    setGuestLimitReached(false)
  }, [])

  return {
    state,

    isBootstrapping: bootstrapping,

    activeConversation,

    isLoadingConversationMessages: (id: string) => {
      const userId = storageOwnerIdForConversation(
        id,
        state.account,
        api.legacyUserId,
        authenticatedUserIdRef.current,
      )
      return loadingMessageRequestKeys.has(`${userId || "anonymous"}:${id}`)
    },

    dispatch,

    apiConfigured: api.configured,

    plans,

    requestEnterprisePlan: () => api.requestEnterprisePlan(),

    submitSalesInquiry: (input: SalesInquiry) => api.submitSalesInquiry(input),

    getPaymentMethods: () => api.getPaymentMethods(),

    startCheckout: (input: CheckoutInput) => api.startCheckout(input),

    bootstrapError,

    guestLimitReached,

    guestChatStatus,

    clearGuestLimit,

    refreshWorkspace,

    hasMoreConversations,

    isLoadingMoreConversations,

    loadMoreConversations,

    searchConversations,

    refreshConversations,

    createConversation,

    createDraftConversation,

    openConversation,

    updateConversation,

    deleteConversation,

    sendMessage,

    regenerate,

    retryFromUser,

    continueResponse,

    stopGeneration,

    stopGenerationFor,

    addFiles,

    retryAttachment,

    cancelAttachment: (id: string) => uploadCancels.current.get(id)?.(),

    connectConnector,

    disconnectConnector,

    toggleConnectorPause,

    createScheduledTask,

    updateScheduledTask,

    toggleScheduledTask,

    deleteScheduledTask,

    runScheduledTaskNow,

    deleteLibraryItem,

    renameLibraryItem,

    moveLibraryItem,

    upsertMemory,

    deleteMemory,

    clearMemories,

    createAgent,

    updateAgent,

    deleteAgent,

    runAgent,

    stopAgent,

    markNotificationRead,

    clearNotifications,

    createProject,

    updateProject,

    deleteProject,

    selectPlan,

    savePreferences,

    cachePreferences,

    clearPreferencesCache,

    saveProfile,

    logout,

    restoreSession,
  }
}

declare global {
  interface Window {
    __garadStreams?: Record<string, string>

    __garadCitations?: Record<string, Message["citations"]>
  }
}
