# Garad Chat history persistence test report

Environment: local Garad Laravel app and a shared React Chat browser page.

## Regression follow-up (2026-10-03)

Code tracing found that workspace hydration runs twice: once when conversation
history resolves and again after the other workspace requests settle. The
pending-created-chat cache was cleared after the first pass, so a conversation
created while the second pass was still pending could be removed when that
pass applied the older conversation-list response. The cache now survives until
the final hydration and is removed only when the server list contains the
conversation. A failed history request also no longer replaces known,
authenticated-user conversations with an empty list and retries in the
background.

If Laravel chat creation fails before a real message ID is returned, the
composer now restores the message text and selected attachments instead of
silently losing the unsent draft. Legacy history responses missing the
expected `chats` or `lastThreeMessage` arrays now fail explicitly instead of
being treated as valid empty history.

The browser attempt was not a valid dedicated-session test. It used a page
sharing the authenticated browser profile: the legacy `get-chats` request
returned HTTP 200, while the generic `/api/conversations` endpoint returned
HTTP 404 and the legacy fallback was selected. The attempted
`start-new-chat` request was reported as aborted and the new UI remained empty.
An empty chat may have been created during this attempt; no existing
conversation was renamed, pinned, or deleted. Further mutations were stopped.
This attempt is not counted as an end-to-end pass.

After the user authorized testing one uniquely created conversation in the
current normal-user session, a browser run showed the user bubble immediately,
then switched away from that chat when the stream finished. The generic stream
endpoint returned HTTP 404 as expected for this legacy backend; the legacy
`generate-stream` endpoint returned HTTP 200. A read-only database check found
the exact test prompt and a persisted assistant response under a replacement
chat ID, while the original empty chat ID returned HTTP 404. Root cause: the
legacy stream adapter recreated the deleted/unknown empty chat but did not
propagate its returned ID back into React state. The stream now emits that ID
and the workspace remaps the active conversation and messages to it.

| Test | Expected | Actual | Status | Backend route | Notes |
|---|---|---|---|---|---|
| Create chat and send the regression prompt | User bubble appears immediately; assistant stream and final turn remain in the same real chat | FAIL before ID-remapping fix: prompt and response persisted under a replacement ID, but React switched away; remapping fix now in place, post-fix browser retest pending | FAIL (pre-fix); PENDING (post-fix) | `POST /dashboard/user/openai/chat/start-new-chat`; `POST /dashboard/user/generator/generate-stream` | Only the uniquely created test conversation was sent to; no existing conversation was changed. |
| Refresh after send | Same server conversation remains in Recents | Not verified in an isolated account/session | BLOCKED | `GET /dashboard/user/openai/chat/get-chats` | The shared browser produced aborted/inconsistent history requests during reload. |
| Reopen a conversation | Full persisted messages render | Not verified in an isolated account/session | BLOCKED | `POST /dashboard/user/openai/chat/open-chatbot-area` | The route now returns an owner-checked conversation descriptor and full turns. |
| Paginate/search history | Older and matching server conversations can be retrieved | UI wired to Laravel pagination and search; browser verification not isolated | BLOCKED | `GET /dashboard/user/openai/chat/get-chats` | Uses Laravel `page`, `per_page`, and `search`. |
| Pin, refresh, unpin, refresh | Pin state survives reload and is removed after unpin | Not run | BLOCKED | `POST /dashboard/user/openai/chat/pin-conversation` | Mutation would alter shared account state. |
| Rename and refresh | Updated title survives reload | Not run | BLOCKED | `POST /dashboard/user/openai/chat/rename-chat` | Mutation would alter shared account state. |
| Delete and refresh | Deleted chat remains absent | Not run | BLOCKED | `POST /dashboard/user/openai/chat/delete-chat` | Destructive test not run against a shared account. |
| Archive | No unsupported frontend-only archive state is created | Archive is explicitly reported as unsupported | PASS | No archive route/field exists | Existing backend has no archive capability. |
| Cross-user conversation access | Another user's history/messages/mutations are denied | Ownership predicates added to list/detail/mutation queries; live second-account check not run | PARTIAL | Existing chat routes | Requires a second authenticated test account to verify end-to-end. |
| Logout/login and second user | Each account sees only its own server history | Not run | BLOCKED | Authenticated routes | Requires controlled account sessions. |
| Hard refresh | Server history rehydrates; stored active ID is verified against server history | Reload exercised, but result was inconclusive in the shared page | BLOCKED | `GET /dashboard/user/openai/chat/get-chats` | No pass claimed. |
| Message persistence after stream | User and assistant turn reload from Laravel message rows | Not verified in an isolated account/session | BLOCKED | `POST /dashboard/user/generator/generate-stream`; `POST /dashboard/user/openai/chat/open-chatbot-area` | Must be confirmed with the browser test above. |

## Automated validation

| Check | Result |
|---|---|
| `npx tsc --noEmit` | Passed after regression fix |
| `npm run build` | Passed after regression fix (existing non-fatal chunk-size warning) |
| PHP syntax check for `AIChatController.php` | Passed |
| Pint check for `AIChatController.php` | Passed |

The end-to-end persistence requirement remains unverified until the create,
stream, refresh, and reopen sequence passes in a dedicated authenticated test
session. Pin, rename, delete, logout/login, and cross-user checks also remain
unverified in isolated normal-user sessions.
