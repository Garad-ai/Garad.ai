# Garad Chat history persistence

## Source of truth

Authenticated conversations are stored in the existing `user_openai_chat` table,
and message turns are stored in `user_openai_chat_messages`. The React client
uses Laravel's existing Chat Pro endpoints; it does not keep a second history
store. Local browser storage stores only the last selected conversation ID,
namespaced by authenticated user ID. Laravel verifies that ID against the
current user's history before the UI restores it.

## Routes used

| Operation | Route |
|---|---|
| List, search, and paginate conversations | `GET /dashboard/user/openai/chat/get-chats` |
| Create a conversation | `POST /dashboard/user/openai/chat/start-new-chat` |
| Load a conversation and its persisted turns | `POST /dashboard/user/openai/chat/open-chatbot-area` |
| Stream a generation and persist the turn | `POST /dashboard/user/generator/generate-stream` |
| Rename | `POST /dashboard/user/openai/chat/rename-chat` |
| Pin or unpin | `POST /dashboard/user/openai/chat/pin-conversation` |
| Delete | `POST /dashboard/user/openai/chat/delete-chat` |
| Move to an existing folder/project, when enabled | `POST /dashboard/user/ai-chat-pro/chats/{chatId}/move-to-folder` |

The React chat stream sends the `react_chat` marker while creating a legacy
conversation, so Laravel uses its current chat record without creating an
unneeded OpenAI Thread.

## History behavior

- On startup, the client requests the newest 20 conversations and hydrates the
  sidebar independently of projects, library, or other workspace resources.
- Results use Laravel's current user's ownership scope and are ordered with
  pinned chats first, then most recently updated chats. The Recents group is
  therefore newest-first within its group.
- The sidebar's Load more action requests the next Laravel page. Search is sent
  to Laravel and is not limited to the currently loaded client-side page.
- Selecting a conversation fetches all persisted message rows for that chat.
  The client uses the stored last-selected ID only if that ID appears in the
  authenticated user's server-returned conversation list.
- A new conversation is not inserted into React state until Laravel returns its
  real chat ID. The generation stream uses this same ID for the user turn,
  assistant response, attachments, and subsequent messages.
- If no prior chat supplies a category ID, Laravel selects the user's permitted
  default chat category rather than forcing a client-side/demo conversation.
- The existing generator stream writes user input and assistant output to the
  legacy message table. React's in-progress stream state is display state, not
  the permanent history source.
- Rename, pin/unpin, move-to-folder, and delete call the Laravel route. Rename
  and pin update the UI optimistically and restore the previous values if the
  request fails. Delete is removed from the UI only after server confirmation.

## Ownership and access control

Conversation listing/search is scoped to `Auth::id()`. Opening, renaming,
pinning, and deleting a chat now query by both chat ID and authenticated owner;
unknown or another user's chat returns 404. Folder moves use the existing
folder controller, which scopes chats and folders to the authenticated user.
Message reads are restricted to the owning user and owned chat.

## Archive and caching

The existing `user_openai_chat` implementation has no archive field or archive
route. The React UI therefore reports archive as unsupported instead of hiding
a chat using frontend-only state. Pinning remains server-backed.

No conversation/message cache is used as the source of truth. Only the last
active ID is stored in `localStorage`, under `garad-chat-active:{userId}`.
Unavailable IDs are ignored. If browser storage is blocked or cleared,
Laravel's newest valid conversation is selected instead.

## Failure handling

If history loading fails, the chat screen remains usable and the workspace
reports/retries the sync. A failed create does not add a fake local conversation.
A failed message fetch is reported rather than represented as a successful
empty history. Failed rename or pin is rolled back; failed delete leaves the
conversation in the sidebar. Generation failures retain the user turn and the
stream error state while preserving any turn data written by Laravel.
