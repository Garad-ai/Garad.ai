# Garad AI provider architecture

## Request path

1. The React chat model picker is populated from the authenticated
   `GET /api/models` catalog. Each descriptor's `provider` comes from the
   selected `EntityEnum`'s configured `engine()`.
2. The React API client passes the selected model ID unchanged as
   `chatbot_front_model` to Laravel's authenticated
   `POST /dashboard/user/generator/generate-stream` compatibility endpoint.
3. `GeneratorController::determineChatBot()` resolves the selected model from
   the `Entity` record. `determineAiEngine()` reads that record's `engine`;
   routing is based on that configured value, not on the display name.
4. `StreamService::ChatStream()` selects the provider adapter. Chat Pro uses
   Laravel's existing history and message records and retains its SSE stream.

## Provider adapters

| Configured engine | Existing adapter | Model source |
|---|---|---|
| `openai` | Chat Pro `openaiChatStream()`; Responses API for normal generation (the existing search-model path uses Chat Completions) | Selected `EntityEnum` |
| `anthropic` | `anthropicChatStream()` -> `AnthropicService` -> `/v1/messages` | Selected `chatbot_front_model`, sent as the Anthropic `model` |
| `gemini` | `geminiChatStream()` -> `GeminiService::streamGenerateContent()` | Selected `EntityEnum` |
| `deep_seek` | `deepseekChatStream()` -> DeepSeek chat completions | Selected model ID |
| `x_ai` | `xAiChatStream()` | Selected `EntityEnum` |
| `open_router` | Existing OpenRouter adapter | Selected OpenRouter model ID |

The adapters remain server-side. Credentials are resolved by the existing
Laravel key helpers and are never returned in the model catalog.

For image input, Anthropic Chat Pro converts Garad's image blocks to native
Anthropic image blocks, and Gemini retains the selected Gemini model. Where
the configured DeepSeek, xAI, or OpenRouter adapter cannot handle the image
payload, the stream returns a typed error instead of calling OpenAI.

## Threads and Garad history

OpenAI Assistants/Threads remain available to legacy assistant conversations.
They are not part of the new Chat Pro model stream:

- New React-created chats include `react_chat: true` when using the existing
  start-chat endpoint, so a category-level assistant setting does not trigger
  an unnecessary OpenAI Thread creation.
- `ChatStream()` does not enter `assistantStream()` for `chatPro`, even if a
  legacy assistant ID is present.
- OpenAI Chat Pro generation uses the existing provider stream rather than
  storing the conversation only in an OpenAI Thread.

Conversation messages and history continue to be stored in Garad's existing
`user_openai_chats` and `user_openai_chat_messages` tables. The React client
normalizes the existing SSE events without replacing them with local streaming.

## Model catalog contract

`GET /api/models` lists models available to the authenticated user. The
`provider` field is the `EngineEnum` slug from the model entity. The model ID is
the dispatch key; a UI label such as “HIR-3.5 Pro” is not used to select an
engine.

## Operational limitations

The server also has a legacy Anthropic-to-Bedrock setting used by non-Chat-Pro
flows. New Chat Pro requests use the selected Anthropic model and do not let
that unrelated global default reroute the selected model.
