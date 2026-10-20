# GARAD Backend Contract

This is the exact API contract expected by the Garad AI frontend integration layer.

## 1. Runtime Configuration

Environment variable used by the frontend:

VITE_GARAD_API_URL=http://127.0.0.1:8000

All paths below are relative to VITE_GARAD_API_URL.

## 2. Transport Rules

- Authentication: cookie/session based
- Frontend sends credentials with every request (credentials: include)
- JSON endpoints must return application/json
- Streaming endpoint must return text/event-stream

Global status handling expected by frontend:

- 401: an API request is unauthorized; the frontend rechecks `/api/account`
  before changing the user's authentication state
- 403: forbidden for the requested feature or resource
- 429: rate limited
- 500-599: server error

## 2.1 Role-based access

- The Laravel role source is `User.type`: `user`, `admin`, or `super_admin`.
- Authenticated normal users land on `/chat`; `/` and dashboard HTML routes
  redirect them there.
- Admin and Super Admin accounts retain the internal dashboard. Admin routes
  remain protected by the Laravel admin/permission middleware.
- Dashboard JSON and streaming endpoints used by Chat remain available to
  authenticated users. Ordinary users continue to be subject to their existing
  plan and credit checks.
- The authenticated account payload includes `user.isSuperAdmin`, derived
  server-side from `User::isSuperAdmin()`. Only Super Admin bypasses Chat Pro
  model plan locks. The model catalogue and generation endpoint both limit this
  access to enabled, configured chat entities with a word token; normal users
  continue to be restricted to their plan models.
- The Laravel entity credit driver grants authenticated Super Admin requests
  unlimited Garad application credits: internal balance checks pass and
  internal usage-credit deductions are skipped. This role check uses the bound
  or authenticated Laravel user, never client-supplied flags. It does not bypass
  provider-side funding, quota, or billing errors.
- A provider-credit warning does not remove a configured model from the
  catalogue. Super Admin may still explicitly select and try it; any provider
  quota or funding failure must be reported for that selected model rather than
  silently switching to a different model.
- The `/chat` page is available to guests. This does not remove authentication
  from account, workspace, dashboard, or administrative APIs.

## 2.2 Guest Chat

- `GET /api/guest/chat-status` returns the server-session allowance:
  `{ "limit": 3, "used": 0, "remaining": 3 }`.
- `POST /api/guest/chat-stream` accepts only `{ "prompt": "..." }` and returns
  the regular Garad `text/event-stream` response. The backend selects the
  configured default Chat Pro model/provider and runs the existing safety,
  guest-credit, and IP-based daily allowance checks.
- The Laravel session can reserve at most three guest answers. A fourth request
  returns HTTP 429 with `code: "guest_chat_limit"` before the generator is
  invoked. The endpoint is also request-rate-limited; it does not accept a
  client-selected model, chat ID, attachments, skills, or tool configuration.
- Guest conversation history is held locally in the browser until sign-in.
  When an authenticated user continues that conversation, the frontend creates
  an account conversation and copies the completed guest messages before
  sending the next prompt.

## 2.3 Session Cookies

- Session cookies are `HttpOnly`, `SameSite=Lax`, and secure by default in
  production. Local HTTP development defaults to a non-secure cookie so the
  browser can return the Laravel session on refresh.
- Deployments that genuinely require cross-site cookies can set
  `SESSION_SAME_SITE=none` and `SESSION_SECURE_COOKIE=true`.
- The existing Laravel session lifetime remains unchanged. The sign-in dialog
  offers Laravel's remember-me option; each device maintains its own session.

## 3. Envelope and Error Schemas

The frontend accepts either envelope or plain payload responses.

```json
{
  "data": {},
  "message": "optional"
}
```

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 120
  },
  "message": "optional"
}
```

Error payload schema:

```json
{
  "message": "Human readable message",
  "code": "optional_machine_code",
  "errors": {
    "field": ["validation error"]
  }
}
```

Chat streaming errors should include a stable `code` and HTTP `status` whenever
the backend can identify the cause. The frontend uses provider-specific codes
to show safe user-facing guidance for insufficient credits or quota, billing
limits, missing or invalid credentials, unavailable models, rate limits,
temporary provider failures, restricted model access, and missing provider
configuration. Unknown provider errors are shown as a generic retry message;
raw provider details are not displayed in the chat.

Authenticated chat requests must honor the requested `model`. If it cannot be
used, return `selected_model_unavailable` rather than silently substituting a
different model. Any explicitly configured fallback must disclose its effective
model in the response before generation begins.

## 4. Core Object Schemas

## 4.1 Conversation

```json
{
  "id": "string",
  "title": "string",
  "messages": [],
  "pinned": false,
  "archived": false,
  "shared": false,
  "projectId": "string optional",
  "mode": "chat",
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string"
}
```

## 4.2 Message

```json
{
  "id": "string",
  "role": "user",
  "content": "string",
  "status": "idle",
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string",
  "attachmentIds": ["string"],
  "citations": [],
  "artifacts": [],
  "toolCalls": [],
  "smartImages": [],
  "entities": [],
  "parentId": "string optional",
  "error": "string optional",
  "feedback": "like optional"
}
```

### Existing Chat Pro stream extensions

- `smart_image_search` carries `{ "query": "...", "message_id": 123 }`. The
  React client requests actual results from the authenticated
  `POST /dashboard/user/smart-image/search` endpoint and renders only returned
  HTTP(S) image URLs and source links. No provider credentials are sent to the
  browser. For explicit image requests on Anthropic, the server emits this
  event directly rather than relying on provider-specific forced tool choice;
  normal text generation continues using the selected model.
- Explicit requests to find current jobs, scholarships, grants, and similar
  opportunities enable the configured provider's native web search when
  `default_realtime=tool_calling`. The assistant must cite only returned
 sources and disclose when current information cannot be verified. The
 Anthropic stream also exposes its native web-fetch tool for these requests so
 direct source pages can be checked; search snippets, generic page shells,
 redirects, and unavailable/closed markers are not evidence that a listing is
 live. Each Anthropic search and fetch tool is capped at three uses per request.
 Actual server-tool execution is reflected in `tool_status` events. Provider
 error events and streams that end without `message_stop` fail visibly rather
 than persisting partial output as a completed answer; explicit client
 cancellation retains the partial response under the remapped conversation ID
 and does not turn an intentionally empty cancellation into a provider error.
- `entity_highlights` carries the validated entity annotation array. The client
  may request details through
  `POST /dashboard/user/ai-chat-pro-entity-highlight/entity-detail` and image
  metadata through `.../entity-image`; both require authentication and enforce
  ownership for supplied message IDs. Entity detail prose is AI-generated and
  is presented as such, not as independently verified or cited research.

## 4.3 Project

```json
{
  "id": "string",
  "name": "string",
  "description": "string",
  "instructions": "string",
  "memoryEnabled": true,
  "memory": "string",
  "agentIds": ["string"],
  "sourceIds": ["string"],
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string optional"
}
```

## 4.4 Library Item

Attachment variant:

```json
{
  "id": "string",
  "name": "string",
  "mimeType": "string",
  "size": 123,
  "kind": "document",
  "status": "ready",
  "progress": 100,
  "previewUrl": "string optional",
  "error": "string optional",
  "projectId": "string optional",
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string optional"
}
```

Artifact variant:

```json
{
  "id": "string",
  "name": "string",
  "kind": "generated",
  "description": "string",
  "status": "ready",
  "url": "string optional",
  "projectId": "string optional",
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string optional"
}
```

## 4.5 Agent

```json
{
  "id": "string",
  "name": "string",
  "description": "string",
  "role": "string",
  "goal": "string",
  "instructions": "string",
  "model": "string",
  "tools": ["string"],
  "plugins": ["string"],
  "knowledge": ["string"],
  "memory": "string",
  "permissions": ["string"],
  "schedule": {
    "enabled": true,
    "recurrence": "weekly",
    "timezone": "Africa/Nairobi",
    "expression": "MON 08:00"
  },
  "status": "active",
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string"
}
```

## 4.6 Agent Run

```json
{
  "id": "string",
  "agentId": "string",
  "task": "string",
  "state": "queued",
  "currentTask": "string optional",
  "currentTool": "string optional",
  "progress": 0,
  "sources": [],
  "outputs": [],
  "startedAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string",
  "error": "string optional"
}
```

## 4.7 Plugin/App

```json
{
  "id": "string",
  "slug": "string",
  "name": "string",
  "description": "string optional",
  "provider": "string optional",
  "category": "string optional",
  "iconUrl": "string optional",
  "kind": "app",
  "status": "installed",
  "installed": true,
  "enabled": true,
  "permissions": ["string"],
  "createdAt": "ISO-8601 string optional",
  "updatedAt": "ISO-8601 string optional"
}
```

## 4.8 Connector

```json
{
  "id": "string",
  "name": "string",
  "provider": "string",
  "description": "string optional",
  "categories": ["string"],
  "featured": true,
  "installed": true,
  "status": "connected",
  "scopes": ["string"],
  "permissions": {
    "read": ["string"],
    "write": ["string"],
    "delete": ["string"],
    "externalActions": ["string"]
  }
}
```

## 4.9 Scheduled Task

```json
{
  "id": "string",
  "title": "string",
  "prompt": "string optional",
  "schedule": "string",
  "scheduleType": "weekly",
  "timezone": "string optional",
  "agentId": "string optional",
  "enabled": true,
  "nextRun": "string",
  "lastRun": "string optional",
  "status": "idle optional",
  "history": [
    {
      "id": "string",
      "startedAt": "ISO-8601 string",
      "completedAt": "ISO-8601 string optional",
      "status": "started",
      "result": "string optional"
    }
  ]
}
```

## 4.10 Memory

```json
{
  "id": "string",
  "title": "string",
  "content": "string",
  "enabled": true,
  "scope": "user optional",
  "projectId": "string optional",
  "agentId": "string optional",
  "createdAt": "ISO-8601 string",
  "updatedAt": "ISO-8601 string"
}
```

## 4.11 Notification

```json
{
  "id": "string",
  "type": "agent_completed",
  "title": "string",
  "detail": "string",
  "read": false,
  "createdAt": "ISO-8601 string"
}
```

## 4.12 Account

```json
{
  "user": {
    "id": "string",
    "name": "string",
    "givenName": "string",
    "surname": "string",
    "email": "string",
    "type": "user | admin | super_admin",
    "avatarUrl": "string optional",
    "phone": "string",
    "country": "string",
    "state": "string",
    "city": "string",
    "postal": "string",
    "address": "string"
  },
  "subscription": {
    "id": "string",
    "planId": "string",
    "status": "active",
    "renewsAt": "ISO-8601 string optional",
    "billingProvider": "none"
  },
  "authStatus": "authenticated",
  "sessionExpiresAt": "ISO-8601 string optional",
  "usage": {
    "messages": { "used": 0, "limit": 0 },
    "agentRuns": { "used": 0, "limit": 0 },
    "storageBytes": { "used": 0, "limit": 2147483648 }
  },
  "entitlements": {
    "messages": 1000,
    "storage": 2147483648,
    "agents": true,
    "agent_runs": 50,
    "scheduled_tasks": 25,
    "plugins": true,
    "voice": true,
    "image_generation": true,
    "video_generation": false,
    "research": true,
    "data_analysis": true
  },
  "preferences": {
    "theme": "light",
    "language": "English",
    "defaultMode": "chat",
    "defaultModel": "garad-pro",
    "reducedMotion": false,
    "responseTraits": ["string"],
    "customInstructions": "string",
    "notifications": {
      "tasks": true,
      "artifacts": true,
      "tips": true
    }
  },
  "memoryEnabled": true,
  "memories": []
}
```

## 4.13 Usage

```json
{
  "messages": { "used": 0, "limit": 0 },
  "agentRuns": { "used": 0, "limit": 0 },
  "storageBytes": { "used": 0, "limit": 2147483648 }
}
```

`storageBytes.used` is the total byte size of a user's uploaded Chat Pro
attachments and saved Library artifacts. The per-plan limits are Basic (free):
2 GiB, Standard: 7 GiB, Professional: 20 GiB, and Business: 100 GiB. An account
without an active paid plan receives the Basic allowance. Uploads and generated
artifacts are rejected by the backend when they exceed the remaining allowance.
Users manage a full library by choosing files to delete or upgrading; Garad AI
does not automatically delete user files.

## 4.14 Subscription Plan

```json
{
  "id": "professional",
  "name": "Professional",
  "monthlyPrice": 20,
  "description": "string",
  "features": ["string"],
  "entitlements": {
    "messages": 1000,
    "storage": 21474836480
  }
}
```

## 4.15 Model Descriptor

```json
{
  "id": "garad-pro",
  "displayName": "Garad Pro",
  "description": "string",
  "capabilities": ["chat", "tools"],
  "planRequirement": "pro",
  "availability": "available"
}
```

## 4.9 Chat Model Descriptor

```json
{
  "id": "claude-opus-4-7",
  "displayName": "Claude Opus 4.7",
  "description": "Anthropic • Credits: 5000",
  "provider": "anthropic",
  "capabilities": ["chat", "tools"],
  "planRequirement": "free",
  "availability": "available"
}
```

`provider` is the configured Laravel engine value for the model entity, not
inferred from its display name. The selected `id` is passed to the corresponding
provider stream and must not be silently replaced by another provider's model.

## 5. Endpoint Contract

Canonical endpoints are listed first. Compatibility aliases accepted by the frontend are listed under each section.

### 5.1 Streaming Chat

POST /api/chat/stream

Request body:

```json
{
  "conversationId": "string",
  "model": "string",
  "mode": "chat",
  "projectId": "string optional",
  "personalization": {
    "language": "English",
    "responseTraits": ["string"],
    "customInstructions": "string"
  },
  "tools": {
    "webSearch": false,
    "deepResearch": false,
    "brandVoiceCompanyId": "string optional",
    "brandVoiceProductId": "string optional",
    "skillIds": ["string"]
  },
  "messages": [
    {
      "role": "user",
      "content": "string",
      "attachmentIds": ["string"]
    }
  ]
}
```

Response:

- Content-Type: text/event-stream
- SSE frames with data: lines containing JSON payloads

SSE event payload union:

```json
{ "type": "status", "status": "queued" }
{ "type": "delta", "text": "partial token" }
{ "type": "tool", "tool": { "id": "string", "name": "string", "label": "string", "status": "running", "detail": "string optional" } }
{ "type": "citation", "citation": { "id": "string", "title": "string", "url": "string", "source": "string" } }
{ "type": "artifact", "artifact": { "id": "string", "name": "string", "kind": "document", "description": "string", "status": "ready", "createdAt": "ISO-8601 string", "updatedAt": "ISO-8601 string optional", "url": "string optional", "projectId": "string optional" } }
{ "type": "done", "usage": { "messages": { "used": 0, "limit": 0 }, "agentRuns": { "used": 0, "limit": 0 }, "storageBytes": { "used": 0, "limit": 0 } } }
{ "type": "error", "message": "string", "code": "string optional", "status": 500 }
```

The frontend also treats data: [DONE] as done.

When the canonical streaming route is unavailable, the frontend forwards tool
choices to the existing authenticated Chat Pro generation stream. Web Search
uses its `realtime` parameter; Brand Voice uses the existing company/product
IDs; Skills uses the existing `skill_ids` parameter. Deep Research uses the
existing authenticated start/poll/cancel routes and reports their actual
status and source data. Provider polling failures transition the research
session to a terminal error instead of remaining in a researching loop.
Completion and credit/message updates are transactional and idempotent, and
source citations accept only HTTP(S) URLs. Provider access or quota errors are
reported as provider failures, not as internal Garad credit failures.

### 5.2 Uploads

POST /api/uploads

Request:

- Content-Type: multipart/form-data
- file: binary
- attachment_id: string

Response:

ApiEnvelope<UploadFileResult> or UploadFileResult:

```json
{
  "id": "string optional",
  "status": "processing optional",
  "progress": 100,
  "previewUrl": "string optional",
  "error": "string optional"
}
```

### 5.3 Conversations

- GET /api/conversations
  - query: page, per_page, search, mode, archived, pinned, project_id
  - response: ApiListEnvelope<Conversation> or Conversation[]
- GET /api/conversations/{id}
  - response: ApiEnvelope<Conversation> or Conversation
- POST /api/conversations
  - body: CreateConversationInput
  - response: ApiEnvelope<Conversation> or Conversation
- PATCH /api/conversations/{id}
  - body: UpdateConversationInput
  - response: ApiEnvelope<Conversation> or Conversation
- DELETE /api/conversations/{id}
  - response: 204 or empty envelope

### 5.4 Messages

- GET /api/conversations/{conversationId}/messages
  - query: page, per_page, search, role
  - response: ApiListEnvelope<Message> or Message[]
- GET /api/messages/{id}
  - response: ApiEnvelope<Message> or Message
- POST /api/conversations/{conversationId}/messages
  - body: CreateMessageInput
  - response: ApiEnvelope<Message> or Message
- PATCH /api/messages/{id}
  - body: UpdateMessageInput
  - response: ApiEnvelope<Message> or Message
- DELETE /api/messages/{id}
  - response: 204 or empty envelope

### 5.5 Projects

- GET /api/projects
  - query: page, per_page, search, memory_enabled
- GET /api/projects/{id}
- POST /api/projects
- PATCH /api/projects/{id}
- DELETE /api/projects/{id}

All responses are ApiEnvelope<Project> / ApiListEnvelope<Project> or plain Project payloads.

Garad's Laravel Chat Projects compatibility API is also supported:

- GET /dashboard/user/ai-chat-pro/folders returns `{ success, folders }`
- POST /dashboard/user/ai-chat-pro/folders accepts `{ name }`
- PUT /dashboard/user/ai-chat-pro/folders/{id} accepts `{ name }`
- DELETE /dashboard/user/ai-chat-pro/folders/{id} deletes the folder and returns its chats to Chats
- POST /dashboard/user/ai-chat-pro/chats/{chatId}/move-to-folder accepts `{ folder_id }` (nullable)

These legacy project folders persist names and chat membership only. The frontend
does not claim that descriptions, instructions, or memory are saved by this API.

### 5.6 Library

- GET /api/library
  - query: page, per_page, search, kind, sort, project_id
- GET /api/library/{id}
- PATCH /api/library/{id}
- DELETE /api/library/{id}
- DELETE /dashboard/user/ai-chat-pro/attachments/{uuid}

The Library list includes saved artifacts and uploaded attachments. Artifact
deletion uses `/api/library/{id}`; uploaded attachment deletion uses the
authenticated attachment route above and removes the stored file and its
derived document record.

### 5.7 Agents

- GET /api/agents
  - query: page, per_page, search, status
- GET /api/agents/{id}
- POST /api/agents
- PATCH /api/agents/{id}
- DELETE /api/agents/{id}

### 5.8 Agent Runs

Canonical:

- GET /api/agent-runs
  - query: page, per_page, search, state, agent_id
- GET /api/agent-runs/{id}
- GET /api/agents/{agentId}/runs
- POST /api/agents/{agentId}/runs
  - body: { task: string, projectId?: string, attachmentIds?: string[] }
- POST /api/agent-runs/{runId}/cancel
  - body: { runId?: string }

Compatibility aliases accepted by frontend:

- POST /api/agents/{agentId}/run
- POST /api/agent-runs/{runId}/stop
- POST /api/agents/{agentId}/stop

### 5.9 Plugins / Apps

Canonical:

- GET /api/apps
  - query: page, per_page, search, installed, enabled, kind
- GET /api/apps/{id}
- POST /api/apps/{id}/install
- PATCH /api/apps/{id}
- DELETE /api/apps/{id}

Compatibility aliases accepted by frontend:

- GET /api/plugins
- GET /api/plugins/{id}
- POST /api/plugins/{id}/install
- PATCH /api/plugins/{id}
- DELETE /api/plugins/{id}
- POST /api/apps/{id}/enable

### 5.10 Connectors

Canonical:

- GET /api/connectors
  - query: page, per_page, search, category, featured
- GET /api/connectors/{id}
- POST /api/connectors/{id}/connect
- POST /api/connectors/{id}/disconnect
- GET /api/connectors/{id}/redirect-url

Compatibility aliases accepted by frontend:

- POST /api/oauth/{id}/connect
- GET /api/oauth/{id}/redirect
- DELETE /api/connectors/{id}

### 5.11 Scheduled Tasks

- GET /api/scheduled-tasks
  - query: page, per_page, search, enabled, status
- GET /api/scheduled-tasks/{id}
- POST /api/scheduled-tasks
- PATCH /api/scheduled-tasks/{id}
- DELETE /api/scheduled-tasks/{id}
- POST /api/scheduled-tasks/{id}/run

### 5.12 Memories

- GET /api/memories
  - query: page, per_page, search, scope, project_id, agent_id
- GET /api/memories/{id}
- POST /api/memories
- PATCH /api/memories/{id}
- DELETE /api/memories/{id}

### 5.13 Notifications

- GET /api/notifications
  - query: page, per_page, search, unread, type
- PATCH /api/notifications/{id}
  - request body: { read: true }
- POST /api/notifications/read-all
- DELETE /api/notifications/{id}

### 5.14 Account

- GET /api/account
- PATCH /api/account
- PATCH /api/account/preferences
  - persists supported preferences per user, including theme, language, default
    mode/model, reduced motion, custom instructions, response traits, and
    notification switches; requires the AI Chat Pro preferences migration
- POST /api/account/profile
  - multipart/form-data with `_method=PATCH`
  - accepts `name`, `surname`, `phone`, `country`, `state`, `city`, `postal`,
    `address`, optional `avatar` (jpg/png/webp, max 5 MB), and optional
    `old_password`, `new_password`, `new_password_confirmation`
  - returns the refreshed `user` profile; email is read-only
- The authenticated user payload exposes Laravel's `User.type` role (`user`,
  `admin`, or `super_admin`) so the chat client can select the appropriate UI.
- POST /logout accepts JSON and returns `204 No Content` after invalidating the
  Laravel session.

### 5.15 Usage

- GET /api/usage

### 5.16 Subscriptions

Canonical:

- GET /api/subscriptions/plans
- GET /api/subscription
- POST /api/subscriptions/change
  - request body: { planId: string }
- POST /api/subscription/cancel
- POST /api/subscription/resume

Compatibility aliases accepted by frontend:

- GET /api/plans
- GET /api/subscriptions/current
- POST /api/subscription/change
- POST /api/subscriptions/cancel
- POST /api/subscriptions/resume

### 5.17 Models

- GET /api/models

Response: ApiListEnvelope<ModelDescriptor> or ModelDescriptor[].
`ModelDescriptor.provider` is the configured engine slug (for example,
`openai`, `anthropic`, `gemini`, or `deep_seek`).

The authenticated compatibility stream uses
`POST /dashboard/user/generator/generate-stream` with `template_type: "chatPro"`
and the selected model in `chatbot_front_model`. New React conversations are
created through `POST /dashboard/user/openai/chat/start-new-chat` with
`react_chat: true`; that marker suppresses creation of an unused Assistants
Thread while retaining the Garad chat and message records.

### 5.18 Composer capabilities

The Chat Pro React page exposes read-only, authenticated capability and picker
data for its composer:

- GET /api/composer/capabilities
- GET /api/composer/prompts
- GET /api/composer/brand-products/{companyId}

The existing authenticated Skills routes provide available skills and user
skill membership (`/dashboard/user/skills`) and add a selected skill
(`/dashboard/user/skills/add/{skill}`). Canvas content continues to use the
existing authenticated `/tiptap-content-store` route.

### 5.19 Voice conversation

The authenticated Chat voice flow uses the existing Garad services:

- GET `/dashboard/user/openai/chat/voice-status?reasoning_model={modelId}`
  - resolves a voice-ready reasoning model for the session
  - does not require the currently selected chat model to be directly
    voice-eligible; a compatible configured reasoning model is selected
    internally when needed
  - response includes `available`, `reason`, `message`, and model metadata
- POST `/dashboard/user/openai/chat/transaudio`
  - Content-Type: multipart/form-data
  - `file`: recorded audio (`mp3`, `mp4`, `mpeg`, `mpga`, `m4a`, `wav`, or `webm`)
  - `voice_context=chat_voice` enables chat-voice STT entitlement mapping
    (voice-session billing path) instead of forcing the standalone Whisper
    credit bucket
  - optional `reasoning_model` informs STT fallback entitlement resolution when
    chat-voice billing fallback is required
  - response: JSON transcript string; no-speech, credit, and provider failures
    retain their non-success HTTP status
- POST `/dashboard/user/openai/generate-speech`
  - Content-Type: multipart/form-data
  - `speeches`: JSON array containing the text, OpenAI voice, language, and
    `tts-1` model selection
  - `from_api`: `1` to return `{ "audioPath": "/uploads/..." }`
  - `voice_context=chat_voice` enables the chat-voice entitlement mapping
    (voice-session billing path) rather than the standalone voiceover bucket
  - optional `reasoning_model` informs the voice-session fallback entitlement
    resolution
  - response audio is generated server-side through the existing OpenAI TTS
    path and remains subject to Garad credit checks
  - speech generation credit checks occur at generation time in this endpoint

The recognized transcript is sent to the selected Chat model through the
existing Chat streaming path for reasoning (without exposing provider internals
to the voice UI). Provider credentials are never sent to the browser. This
keeps Garad reasoning in the existing stack rather than routing reasoning
through the separate `gpt-realtime` session endpoint.
The current OpenAI TTS endpoint returns a generated MP3 URL (not incremental
audio-token deltas); the browser plays that real server-generated audio and
reopens the microphone for the next turn. During playback, the microphone stays
active with browser echo cancellation so user speech can interrupt playback
and begin a new captured turn.

### 5.20 Creative image generation

The creative tool routes are served by the authenticated Chat Pro React page:

- GET `/app/ai-image-generator`
- GET `/app/ai-video-generator`
- GET `/app/social-media`

Image model options are discovered from Garad's enabled, plan-available image
entities and image-token capability:

- GET `/dashboard/user/ai-chat-pro/image-models`

The response contains the model's Garad entity ID, display name, provider,
generation-engine mapping, prompt field, optional reference-upload mode, plan
requirement, and current availability. The frontend must not substitute a
hardcoded model list if this endpoint is unavailable.

Image generation uses the existing authenticated Laravel generator:

- POST `/dashboard/user/openai/generate`
  - Content-Type: multipart/form-data
  - `post_type=ai_image_generator`
  - `image_generator`, `description`, `image_number_of_images`, `size`,
    `quality`, `image_style`, `image_lighting`, and `image_mood`
  - optional model-specific prompt field and `fal_ai_model` where applicable
  - optional reference uploaded as `image_src` or `image_src[]`, as indicated by
    the selected model descriptor

Successful responses contain the persisted `images` entries; the UI only
reports a generated result when an entry includes a real output URL. Template
presets fill the prompt and supported generator settings before submission.

## 6. Field and Enum Constraints

Key enum values consumed by frontend:

- Conversation.mode: chat | agents
- Message.role: user | assistant | system
- Message.status: idle | queued | thinking | tool_call | streaming | completed | stopped | error
- Connector.status: not_connected | not_installed | installing | installed | connecting | connected | permission_denied | reconnect | disconnected
- Subscription.status: active | trialing | past_due | cancelled
- Notification.type: agent_completed | scheduled_task_completed | task_failed | approval_required | plugin_disconnected | file_ready | usage_warning

## 7. Security Requirements

- Never return provider secrets, OAuth client secrets, API keys, or internal credentials to the browser.
- Keep provider token exchange and privileged calls server-side.
- Frontend only requires VITE_GARAD_API_URL as public runtime configuration.
