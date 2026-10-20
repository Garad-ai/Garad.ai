import { ApiError, createApiClient, type ApiClient } from "./api"
import type { ChatRequest, StreamEvent } from "./types"

export interface ChatTransport {
  stream(request: ChatRequest, signal: AbortSignal): AsyncIterable<StreamEvent>
}

async function* localStream(
  _request: ChatRequest,
  _signal: AbortSignal,
): AsyncIterable<StreamEvent> {
  throw new ApiError(
    "Connect to your authenticated Garad account to send messages and run tools.",
    503,
    "garad_backend_not_configured",
  )
}

class SseChatTransport implements ChatTransport {
  constructor(private readonly api: ApiClient) {}

  async *stream(
    request: ChatRequest,
    signal: AbortSignal,
  ): AsyncIterable<StreamEvent> {
    yield* this.api.chatStream(request, signal)
  }
}

class DevelopmentTransport implements ChatTransport {
  stream(request: ChatRequest, signal: AbortSignal) {
    return localStream(request, signal)
  }
}

export const createChatTransport = (
  api: ApiClient = createApiClient(),
): ChatTransport => {
  return api.configured ? new SseChatTransport(api) : new DevelopmentTransport()
}
