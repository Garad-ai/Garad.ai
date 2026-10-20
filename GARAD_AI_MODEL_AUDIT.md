# Garad AI Model Catalogue Audit

Audit date: 2026-10-15 (updated)

## Scope

Queried the live Laravel database for enabled entities with word tokens. This produced 140 configured entries. Catalogue enablement is not proof of provider availability. Except where explicitly identified below, models were not individually called in this audit and are retained as unverified, not assumed operational or broken.

## Provider summary

| Provider | Configured entries | Live evidence in this audit | Action |
|---|---:|---|---|
| anthropic | 25 | `claude-fable-5-1` authenticated chat with native search/fetch passed (2026-10-15); Claude Opus 5 passed in a prior verified run; Claude Opus 4.7 historical pass only | Retain; unverified models require provider credentials and funded access. |
| azure | 1 | No live model call in this audit | Retain; unverified models require provider credentials and funded access. |
| deep_seek | 5 | No live model call in this audit | Retain; unverified models require provider credentials and funded access. |
| gemini | 18 | Deep Research blocked by Free Tier 0/day limit | Retain; unverified models require provider credentials and funded access. |
| open_router | 27 | No live model call in this audit | Retain; unverified models require provider credentials and funded access. |
| openai | 50 | o3 Deep Research denied by account access; gpt-5.4-mini historical pass only | Retain; unverified models require provider credentials and funded access. |
| perplexity | 1 | No live model call in this audit | Retain; unverified models require provider credentials and funded access. |
| serper | 1 | Authenticated image-search chat returned 10 cards; all thumbnails loaded and all source links were HTTPS (2026-10-15) | Retain; unverified models require provider credentials and funded access. |
| x_ai | 12 | No live model call in this audit | Retain; unverified models require provider credentials and funded access. |

## Per-model results

| Provider | Model identifier | Test status | Actual failure category | Corrective action |
|---|---|---|---|---|
| anthropic | `claude-sonnet-4-5-20250929` | DEPRECATED; retirement scheduled for 2026-11-30 | Still active as of this audit; not yet routed to a replacement | Keep available until retirement; plan migration to `claude-sonnet-5-5`. |
| anthropic | `claude-sonnet-4-6` | LIVE-TESTED SUCCESS (HTTP 200, 2026-10-15) | None - provider returned HTTP 200 | Retain. |
| anthropic | `claude-sonnet-4-20250514` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-sonnet-5-5` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-3-7-sonnet-20250219` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-sonnet-5-5` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-3-5-sonnet-20241022` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-sonnet-5-5` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-3-5-sonnet-20240620` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-sonnet-5-5` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-3-sonnet-20240229` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-sonnet-5-5` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-opus-4-7` | Historical live chat passed (2026-10-03); not retested | No current-run failure; historical evidence only | Retain; retest before making a current availability claim. |
| anthropic | `claude-opus-4-6` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `claude-opus-4-5-20251101` | LIVE-TESTED SUCCESS (HTTP 200, 2026-10-15) | None - provider returned HTTP 200 | Retain. |
| anthropic | `claude-opus-4-1-20250805` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-opus-4-8` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-opus-4-20250514` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-opus-4-8` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-3-opus-20240229` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-opus-4-8` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-3-haiku-20241022` | Noncanonical legacy selection key; migrated | The persisted default was updated to `claude-haiku-4-5-20251001` | Keep as a compatibility alias only; no longer an active catalogue selection. |
| anthropic | `claude-3-haiku-20240307` | RETIRED; catalogue migrated | Replaced by Anthropic recommendation `claude-haiku-4-5-20251001` | Retired entity disabled; active selection and access moved to Haiku 4.5. |
| anthropic | `claude-2.1` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-opus-4-8` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `claude-2.0` | RETIRED; request alias configured | Replaced by Anthropic recommendation `claude-opus-4-8` | Map retired selection at request time; preserve catalogue access and credit mapping. |
| anthropic | `voyage-2` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `voyage-large-2` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `voyage-code-2` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `davinci-002` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `text-davinci-003` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-3.5-turbo` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-3.5-turbo-0125` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-3.5-turbo-1106` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4-turbo` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4-1106-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4-0125-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4o` | LIVE-TESTED SUCCESS (HTTP 200, 2026-10-15) | None - provider returned HTTP 200 | Retain. |
| openai | `gpt-4o-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4o-search-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4o-mini-search-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o1-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o1-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o1` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o3-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4o-realtime-preview-2024-12-17` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4.1` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4.1-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-4.1-nano` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o4-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o3` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5-nano` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5-chat-latest` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5-pro` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.1` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.1-chat-latest` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.2` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.2-pro` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.3-chat-latest` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.4` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.4-mini` | Historical live chat passed (2026-10-03); not retested | No current-run failure; historical evidence only | Retain; retest before making a current availability claim. |
| openai | `gpt-5.4-nano` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.5` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `o3-deep-research` | Live Deep Research start attempted; provider rejected the account | Provider says model is nonexistent or account lacks access; official OpenAI docs confirm this model ID is valid | Retain; grant access/funding or configure an explicitly supported research model. No silent model switch. |
| openai | `o4-mini-deep-research` | Not called; absent from this API key model list | Account access unverified; no evidence of discontinuation | Retain; test only after provider confirms account access. |
| openai | `text-embedding-ada-002` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `text-embedding-3-small` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `text-embedding-3-large` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-2.5-flash-preview-05-20` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3-pro-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3.1-pro-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-2.5-pro` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-deep-research` | Live research start attempted; provider returned 429 | Gemini Free Tier limit is 0 requests/day for deep-research-pro-preview | Retain; upgrade provider tier/enable quota, then retest. |
| gemini | `gemini-2.0-flash` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-2.0-flash-lite` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-1.5-pro` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-embedding-exp` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-1.5-flash` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3-flash-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3.1-flash-live-preview` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `text-embedding-004` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| deep_seek | `deepseek-chat` | Historical catalogue availability only; no live call | Not tested | Retain; do not infer operation from catalogue status. |
| deep_seek | `deepseek-reasoner` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| azure | `azure-openai` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| serper | `serper` | Direct fallback probe returned 3 valid image/source pairs; authenticated chat returned 10 cards with all thumbnails loaded (2026-10-15) | No failure observed | Retain; configured fallback was operational for the tested queries. |
| perplexity | `perplexity` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-2-1212` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-2-vision-1212` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-3` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-3-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-3-fast` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-3-mini-fast` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-4-0709` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-4-fast-reasoning` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-4-1-fast-reasoning` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-4-1-fast-non-reasoning` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `anthropic/claude-3-5-haiku-20241022` | UNTESTED; request alias configured | Original ID absent from OpenRouter public list; replacement ID is listed | Route to `anthropic/claude-haiku-4.5`; provider access not tested. |
| open_router | `anthropic/claude-3-5-haiku-20241022:beta` | UNTESTED; request alias configured | Original beta ID absent; replacement has no self-moderated beta variant | Route to standard `anthropic/claude-haiku-4.5` with selector disclosure. |
| open_router | `anthropic/claude-3-5-haiku` | UNTESTED; request alias configured | Original ID absent from OpenRouter public list; replacement ID is listed | Route to `anthropic/claude-haiku-4.5`; provider access not tested. |
| open_router | `anthropic/claude-3-5-haiku:beta` | UNTESTED; request alias configured | Original beta ID absent; replacement has no self-moderated beta variant | Route to standard `anthropic/claude-haiku-4.5` with selector disclosure. |
| open_router | `neversleep/llama-3.1-lumimaid-70b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `anthracite-org/magnum-v4-72b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `x-ai/grok-beta` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `mistralai/ministral-8b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `mistralai/ministral-3b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `qwen/qwen-2.5-7b-instruct` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `nvidia/llama-3.1-nemotron-70b-instruct` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `inflection/inflection-3-pi` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `inflection/inflection-3-productivity` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `liquid/lfm-40b:free` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `liquid/lfm-40b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `thedrummer/rocinante-12b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `eva-unit-01/eva-qwen-2.5-14b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `anthracite-org/magnum-v2-72b` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `meta-llama/llama-3.2-3b-instruct:free` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `meta-llama/llama-3.2-1b-instruct:free` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `meta-llama/llama-3.2-3b-instruct` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `meta-llama/llama-3.2-1b-instruct` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `perplexity/llama-3.1-sonar-huge-128k-online` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `perplexity/llama-3.1-sonar-large-128k-online` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `perplexity/llama-3.1-sonar-large-128k-chat` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `perplexity/llama-3.1-sonar-small-128k-online` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| open_router | `perplexity/llama-3.1-sonar-small-128k-chat` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `claude-fable-5` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `claude-opus-4-8` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-realtime` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `claude-sonnet-5` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.6-sol` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.6-terra` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.6-luna` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3.7-flash` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-4.6` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-5.6-cyber` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| deep_seek | `deepseek-v4-flash` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| deep_seek | `deepseek-v4-pro` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| deep_seek | `deepseek-v4-flash-vision-exp` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-6-astra` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3.8-flash` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| anthropic | `claude-fable-5-1` | Authenticated chat and native web search/fetch completed with official source links (2026-10-15) | Initial uncapped research stream ended before `message_stop`; no model/provider error was reported | Retain; cap each native search/fetch tool at three uses and fail visibly on incomplete streams. |
| anthropic | `claude-opus-5` | Authenticated live chat passed (prior verified run) | None observed | Retain; preserve the selected-model route and Super Admin internal-credit exemption. |
| openai | `gpt-live-1` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| openai | `gpt-live-1-mini` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3.5-flash` | LIVE-TESTED SUCCESS (HTTP 200, 2026-10-15) | None - provider returned HTTP 200 | Retain. |
| gemini | `gemini-3.5-flash-lite` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| gemini | `gemini-3.6-flash` | UNTESTED | None observed; operational status unknown | Retain; test with valid provider credentials and quota before asserting availability. |
| x_ai | `grok-4.5` | UNTESTED - no xAI API key configured in this environment | None observed; operational status unknown | Retain; add an xAI API key (Settings) and re-test. |

## Status legend and live smoke results (2026-10-15)

Statuses used in the table: LIVE-TESTED SUCCESS, LIVE-TESTED FAILURE, HISTORICALLY TESTED, MOCKED, UNTESTED. UNTESTED means no call was made; it is not evidence of failure. Each smoke test sent one tiny prompt, with no retries.

| Provider | Model | Result |
|---|---|---|
| anthropic | claude-opus-4-7, claude-sonnet-4-6, claude-opus-4-5-20251101 | HTTP 200 |
| anthropic | claude-3-haiku-20240307 | LIVE-TESTED FAILURE: HTTP 404 (2026-10-15); migrated to `claude-haiku-4-5-20251001` |
| openai | gpt-5.4-mini, gpt-4o | HTTP 200 |
| openai | o3-deep-research | LIVE-TESTED FAILURE: account access denied (needs OpenAI approval/tier for this model; do not disable) |
| gemini | gemini-3.5-flash | HTTP 200 |
| gemini | gemini-2.5-flash-preview-05-20 | LIVE-TESTED FAILURE: HTTP 404 (retired preview identifier; not in the enabled catalogue) |
| gemini | Deep Research | LIVE-TESTED FAILURE: Free Tier quota 0/day (needs billing-enabled Gemini key) |
| deep_seek | deepseek-chat | HTTP 200 |
| x_ai | grok-4.5 (all 12 x_ai entries) | UNTESTED: no xAI key configured; add one to test |
| azure, open_router, perplexity | all entries | UNTESTED: keys/endpoints not exercised in this audit |
| serper | image search | Live success earlier (10 cards) |

Every other entry marked UNTESTED in the table below shares its provider's credential, so a provider-level HTTP 200 shows the key works but does not prove each individual identifier is valid.

## Anthropic retirement follow-up (2026-10-17)

This follow-up supersedes the earlier statement that the failed Haiku 3 identifier was not enabled. The live catalogue did contain the retired model. The migration re-keyed its active entity to `claude-haiku-4-5-20251001`, preserved the display role, enabled/selected state, plan links, token types, and credit rates, and disabled the retired entity. Current database verification finds the replacement enabled and selected with three plan links and a word token; the old Haiku 3 key is no longer present in the entity catalogue. The persisted Anthropic default and the retired Bedrock Sonnet default were updated.

The API adapter maps these additional Anthropic-retired identifiers to Anthropic's documented replacements at request time, leaving existing catalogue entitlements and billing mappings intact. Their model-selector labels disclose the routing destination so users are not silently moved to another model:

| Retired Anthropic API identifier | Request replacement |
|---|---|
| `claude-3-haiku-20240307`, `claude-3-5-haiku-20241022` | `claude-haiku-4-5-20251001` |
| `claude-3-7-sonnet-20250219`, `claude-3-5-sonnet-20241022`, `claude-3-5-sonnet-20240620`, `claude-3-sonnet-20240229`, `claude-sonnet-4-20250514` | `claude-sonnet-5-5` |
| `claude-3-opus-20240229`, `claude-opus-4-1-20250805`, `claude-opus-4-20250514` | `claude-opus-4-8` |
| `claude-2.0`, `claude-2.1` | `claude-opus-4-8` |

Anthropic's current deprecation table lists `claude-sonnet-4-5-20250929` as deprecated, not retired, with a 2026-11-30 retirement date and `claude-sonnet-5-5` as its replacement. It remains selectable for now; schedule its migration before that date. The noncanonical `claude-3-haiku-20241022` key found in the pre-migration default is retained only as a request-time compatibility alias.

Four OpenRouter catalogue identifiers remain as compatibility keys: `anthropic/claude-3-5-haiku`, `anthropic/claude-3-5-haiku:beta`, `anthropic/claude-3-5-haiku-20241022`, and `anthropic/claude-3-5-haiku-20241022:beta`. The public OpenRouter model list no longer contains those IDs and does list `anthropic/claude-haiku-4.5`; all four request keys now route there. Selector labels disclose the destination, and the two former beta/self-moderated variants disclose that this specific variant is unavailable. OpenRouter credentials were not used, so route access and exact moderation parity remain unverified.

The code's existing `web_search_20250305` is Anthropic's basic web-search tool version and remains unchanged; newer tool versions provide features not required here. Anthropic documents model-specific support discovery through the Models API. It was not queried with the production key, so per-account access remains unverified. Haiku 4.5 request construction omits both `temperature` and `top_p`; the service also rejects any payload containing both. Empty `refusal` stop reasons are surfaced as a user-facing refusal rather than an empty response.

The retired Haiku request was not retried after migration. No real replacement-model request was sent because provider access/quota was not verified. Post-migration log inspection found no direct Anthropic retired-model IDs. The only later OpenRouter alias log entries were generated by the HTTP-faked unit tests; no provider requests were made. Historical pre-migration log entries remain.

Sources: [Anthropic model deprecations](https://docs.anthropic.com/en/docs/about-claude/model-deprecations), [Claude migration guide](https://docs.anthropic.com/en/docs/about-claude/models/migrating-to-claude-4), [web search tool](https://docs.anthropic.com/en/docs/agents-and-tools/tool-use/web-search-tool), and [tool reference](https://docs.anthropic.com/en/docs/agents-and-tools/tool-use/tool-reference).
## Changes and limitations

- The Anthropic migration disabled the retired Haiku 3 entity, moved its active selection and access to Haiku 4.5, and preserved the old entity's plan/token links and credit behavior. No conversation data was changed.
- Deep Research OpenAI default fallback now matches the settings UI default (`o3-deep-research`); this does not grant account access or switch an existing setting.
- Gemini completion is mapped to its internal `gemini-deep-research` credit entity; completion is idempotent and transactionally updates the session, chat, message, and credits.
- Provider polling failures now terminate with an explicit failed state rather than polling indefinitely. Research source citations are restricted to HTTP(S).
- Authenticated live chat with `claude-fable-5-1` completed a current-scholarship search after limiting native web search and fetch to three uses each; the first uncapped attempt ended without Anthropic's completion event.
- A real Somali-flag image-search chat returned 10 cards; all thumbnails loaded, source links used HTTPS, and the gallery had no horizontal overflow at 390px.
- Current live Deep Research could not complete: OpenAI denied access to `o3-deep-research`, and Gemini returned a zero-per-day Free Tier quota. Therefore no real report, citations, completion event, or follow-up cycle was generated in this environment.
- The OpenAI model-list endpoint returned no deep-research models for the configured key. OpenAI documentation identifies `o3-deep-research` as a valid model; this is not evidence that the model is discontinued.
