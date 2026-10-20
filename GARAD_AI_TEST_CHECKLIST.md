# Garad AI chat model-routing test checklist

## Automated checks

- [x] TypeScript type-check: `npx tsc --noEmit`
- [x] React production build: `npm run build`
- [x] PHP syntax checks for the changed Laravel files
- [x] Direct PHP enum mapping check for every currently available chat-capable model
- [ ] Run the PHPUnit provider-mapping test after restoring the installed PHPUnit
      runner (`PHPUnit\TextUI\Application` is missing).
- [ ] Run the full Laravel test suite; the configured runner is unavailable and
      no pre-existing `tests/Feature` suite exists.
- [ ] Run `php artisan route:list` after the unrelated missing Gmail connector
      controller is corrected.

## Authenticated model smoke tests

For each model shown as `available` by `GET /api/models`:

- [ ] Select the model in the new React UI.
- [ ] Send a short prompt through the existing generation stream.
- [ ] Confirm the selected entity ID and engine in server-side diagnostics.
- [ ] Confirm the request reaches that provider's adapter and uses the selected
      model ID.
- [ ] Confirm streamed text, completion, and provider errors are rendered.
- [ ] Confirm no silent retry changes the provider or model.

Current live smoke results are recorded in
`GARAD_AI_MODEL_TEST_REPORT.md`. Run the remaining matrix using a dedicated
authenticated account/session to avoid modifying a shared conversation.

## Negative routing checks

- [x] A React-created chat skips category-driven OpenAI Thread creation.
- [x] The Chat Pro stream bypasses `assistantStream()` even when a legacy
      assistant ID is present.
- [x] Chat Pro's selected Claude model is passed to the Anthropic service.
- [x] Chat Pro Anthropic image input uses Anthropic image blocks.
- [x] Unsupported provider/image combinations return a typed SSE error instead
      of switching to OpenAI.
- [ ] Add an isolated integration test asserting no OpenAI request is issued
      for Claude, Gemini, or DeepSeek selections.
- [ ] Exercise a provider failure and verify the stream reports an error rather
      than switching providers.

## Manual provider debugging

Use server-side logs/HTTP instrumentation only. Never expose provider API keys
to the browser or put them in test output. Verify the model ID, engine enum,
adapter, and upstream host, while redacting request headers and credentials.
