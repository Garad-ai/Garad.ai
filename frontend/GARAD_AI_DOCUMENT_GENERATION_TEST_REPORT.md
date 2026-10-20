# Garad AI Document Generation Test Report
| Test | Result | Notes |
|---|---|---|
| POST /api/documents/word | PASS | 201, download 200, valid ZIP (PK) |
| POST /api/documents/pdf | PASS | 201, download 200, %PDF |
| POST /api/documents/xlsx | PASS | 201 after fixing preg_replace bug in GaradArtifactService |
| xlsx without table | PASS | 422 |
| invalid format | PASS | 422 |
| GET /api/library | PASS | 200, lists artifacts |
| UI Create menu: Word, Excel | PASS | file cards appear with Open/Download |
| UI PDF, Markdown, TXT | Not tested in UI (PDF verified via API; md/txt are client Blob) |
| PowerPoint | Not tested: depends on Gamma extension configuration |
| Cross-user ownership | Not tested (download is owner-authorized in ArtifactController) |

---

# Artifact finalization run (latest)

## Root causes fixed
- **Truncated answers**: `AnthropicService::stream()` sent `max_tokens = setting('anthropic_max_output_length')` (cached value 200). Now floored to 16000. DeepSeek paths floored to 8000. Verified: 20-row table + trailing sentences complete; 3-row table + summary complete.
- **Dropped SSE chunks**: `parseSseFrame` discarded numeric/whitespace-only chunks (e.g. "425" ? "1,.7M"). Fixed in `src/core/api.ts`.

## PowerPoint (reuses existing engine)
`POST /api/presentations` calls `GaradPresentationToolService::create()` (AiPresentation + Gamma). `GET /api/presentations/{id}` calls `GammaService::updatePresentationStatus()`, imports the finished PPTX into an owner-scoped artifact (type `pptx`, `slide_count`, `external_url`). No redirect to `/dashboard/user/ai-presentation`. Gamma is configured on this install; real job completed, 1.5 MB PPTX.

## Matrix (user stays on /chat in all cases)
| Format | Generate | Card | Open ? right panel | Download bytes | Library kind | Ownership |
|---|---|---|---|---|---|---|
| Word | PASS | PASS | PASS (HTML preview, XFO SAMEORIGIN, content verified) | PASS `PK` | document | PASS |
| PDF | PASS | PASS | PASS (inline application/pdf) | PASS `%PDF` | pdf | PASS |
| Excel | PASS | PASS | PASS (HTML grid with data) | PASS `PK` | spreadsheet | PASS |
| PowerPoint | PASS (real Gamma job, polled) | PASS | metadata panel (file, slides, created, online link) | PASS `PK`, 1,552,701 bytes | presentation | PASS |
| Markdown | PASS | PASS | PASS (rendered table in panel, verified in browser) | PASS | generated | PASS |
| TXT | PASS | PASS | PASS (pre text) | PASS | generated | PASS |

Ownership: another user's artifact ids (3, 27, 28) returned 403 on download and preview, 404 on delete.
Refresh: artifacts persist in `ai_chat_pro_artifacts` and appear in `/api/library` after reload (37 items listed with correct kinds).

## Build
`corepack pnpm exec tsc --noEmit` clean; `corepack pnpm build` OK; `php -l` OK on DocumentExportController, GaradArtifactService, AIChatProServiceProvider, AnthropicService, StreamService.

## Not verified / notes
- Long report and research responses were verified only via the 20-row table test, not a separate multi-page report.
- The browser matrix for Word/PDF/Excel panel rendering was verified at the HTTP preview endpoints (status, headers, content); the panel iframe was visually exercised only for Markdown.
