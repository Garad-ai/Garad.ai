# Garad AI Chat API inventory

This inventory covers the new React Chat model-routing path and its existing
Laravel integrations. Routes require the normal authenticated Garad web
session; browser requests retain cookies and CSRF protection.

| Method and route | Handler | Purpose |
|---|---|---|
| `GET /api/models` | `ReactChatController::models` | Returns user-available chat model IDs, configured provider slugs, capabilities, plan requirement, and availability. |
| `POST /dashboard/user/openai/chat/start-new-chat` | `AIChatController::startNewChat` | Creates the existing Garad chat/message records. The React client sets `react_chat: true` to avoid creating an unused OpenAI Thread for assistant-configured categories. |
| `POST /dashboard/user/generator/generate-stream` | `GeneratorController::buildStreamedOutput` | Existing authenticated Chat Pro generation endpoint. `chatbot_front_model` identifies the selected model; response is the existing SSE protocol. |
| `GET /api/composer/capabilities` | `ReactChatController::composerCapabilities` | Reports backend-backed composer capabilities. |
| `GET /api/composer/prompts` | `ReactChatController::composerPrompts` | Returns prompt-library data from Garad. |
| `GET /api/composer/brand-products/{companyId}` | `ReactChatController::composerBrandProducts` | Returns products accessible to the authenticated user for existing Brand Voice handling. |
| `GET /dashboard/user/ai-chat-pro/connectors` | Connector controller | Lists the existing user connector records and connection state. Existing connect, disconnect, access, and pause routes remain authoritative. |
| `POST /dashboard/user/deep-research/start` | Deep Research controller | Starts an existing research session. The React client polls and cancels through its existing authenticated routes. |
| `POST /api/uploads` | Existing Laravel upload integration | Uploads supported files; Laravel validates, authorizes, stores, and returns the asset reference consumed by chat. |

## Streaming contract

The generation endpoint emits Garad's existing SSE frames, including the
message identifier, `data` deltas, tool/status events where applicable, and
`stop`/`[DONE]` completion. The React adapter parses the stream and maps events
to UI state; it does not synthesize provider text. Provider errors remain
server-side and must not trigger a cross-provider retry.

## Provider route selection

The model descriptor's `provider` is read from the `Entity` engine mapping.
The request's selected model ID is resolved against the same entity catalog.
OpenAI Assistants routes are retained for legacy assistant features, but are
not used by the new Chat Pro generation path.
