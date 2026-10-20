# Garad AI model test report

## Scope and method

The authenticated `GET /api/models` endpoint was queried in the local Garad
environment on 2026-10-03. Only entries reported as available and chat-capable
are listed below; search-only Serper/Perplexity entries and unavailable models
are not normal chat smoke-test candidates.

The live UI smoke prompt was `Reply with MODEL_TEST_OK only.` The tests were
run through the authenticated React Chat page and existing Laravel stream, not
through a local/mock response.

## Available chat model inventory

| Model ID | Display name | Provider | Availability | Live smoke |
|---|---|---|---|---|
| `claude-opus-4-7` | Claude Opus 4.7 | `anthropic` | available | Passed; returned `MODEL_TEST_OK` |
| `gpt-5.4-mini` | HIR-3.5 Pro | `openai` | available | Passed; returned `MODEL_TEST_OK` |
| `gemini-2.5-flash-preview-05-20` | Gemini 2.5 Flash Preview 05-20 | `gemini` | available | Not run |
| `gemini-3-pro-preview` | Gemini 3 Pro Preview | `gemini` | available | Not run |
| `gemini-3.1-pro-preview` | Gemini 3.1 Pro Preview | `gemini` | available | Not run |
| `gemini-1.5-flash` | Gemini 1.5 Flash | `gemini` | available | Not run |
| `deepseek-chat` | Deepseek Chat | `deep_seek` | available | Not run |

The UI/browser was observed to be concurrently receiving other chat activity
during the remaining live matrix. Those models are therefore left explicitly
untested rather than risking another user's conversation or attributing their
responses to this smoke run.

## Routing verification

- The authenticated model catalog returned HTTP 200 and provider slugs matching
  the Laravel entity engine mapping.
- The Claude smoke completed after disabling React-chat Thread creation. Its
  new chat used the normal selected-provider stream; no corresponding
  `/v1/threads` request was logged after the fix.
- Chat Pro now sends the selected Anthropic model ID to `/v1/messages` instead
  of replacing it with `anthropic_default_model`.
- Static provider-mapping tests cover the seven available chat model IDs.

## Validation commands

| Check | Result |
|---|---|
| `npx tsc --noEmit` | Passed |
| `npm run build` | Passed |
| PHP syntax checks for changed Laravel files | Passed |
| Direct PHP provider-mapping check | Passed for all seven available chat models |
| PHPUnit provider-mapping test | Added, but the installed PHPUnit runner is incomplete (`PHPUnit\TextUI\Application` is unavailable). |
| Full Laravel test suite | Not run; the configured runner is unavailable and no pre-existing `tests/Feature` suite exists. |

Live smoke testing is still required for the four Gemini models and DeepSeek
in an isolated authenticated test account. No provider credentials or keys
were printed or copied into the frontend.
