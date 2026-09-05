# Twilio Configuration Guide — Phone Call Agent

## Overview

When the provider is set to **Twilio**, the system operates fully independently from ElevenLabs. Twilio handles the phone number, call routing, STT (via Deepgram), and TTS (via Amazon Polly or Google Cloud TTS). The AI conversation is powered by your app's LLM (configurable per agent: OpenAI, Anthropic, Claude, Gemini, DeepSeek). Knowledge base data is stored locally in the database.

**Flow:**
Caller dials Twilio number → Twilio POSTs webhook to your app → App returns ConversationRelay TwiML → Twilio connects WebSocket to your Ratchet relay server → Twilio does STT → sends transcript text → your app runs RAG + LLM → sends response text back → Twilio TTS speaks it to caller → transcripts stored real-time

---

## Prerequisites

- A [Twilio account](https://www.twilio.com)
- A Twilio phone number capable of receiving voice calls
- Twilio Account SID + Auth Token — entered **per agent** in the Phone Numbers step (each user brings their own Twilio account). Admin Settings / env vars are an optional global fallback only.
- Ratchet WebSocket relay server running (see Step 5)
- nginx proxy rule configured (see Step 5)
- An AI provider API key set in global AI settings (OpenAI, Anthropic, etc.)

> **Multi-tenant:** credentials and the phone number live on the agent, so every user can connect their own Twilio number against the same shared webhook URLs. Inbound calls are routed to the right agent by the dialed number.

---

## Step 1 — Get Twilio Credentials

1. Log in to [console.twilio.com](https://console.twilio.com)
2. From the dashboard, copy:
   - **Account SID** (starts with `AC...`)
   - **Auth Token**

---

## Step 2 — (Optional) Global Fallback Credentials

Credentials are entered **per agent** (Step 6) — there are no Twilio credential fields in admin settings. For a single-tenant install you may optionally set an env-based global fallback:

```env
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token_here
```

This is only consumed by the internal `TwilioPhoneService` fallback (no inbound/outbound path uses it once numbers are connected per agent). Most installs can skip this entirely.

---

## Step 3 — Set Provider in Admin Settings

1. Go to **Admin → Settings → Phone Call Agent Settings**
2. Set **Phone Call Provider** to `Twilio`
3. Save

New agents created after this will default to the Twilio provider.

---

## Step 4 — Create an Agent

1. Go to the Phone Call Agent dashboard and create a new agent
2. **Configure step** (Twilio-specific fields):
   - **AI Model** — pick the LLM for the conversation (GPT-4o, Claude, Gemini, etc.)
   - **Voice (TTS)** — pick a Twilio built-in voice (Amazon Polly Neural or Google Cloud TTS)
   - **Language** — sets the STT language for Deepgram
   - **Agent Instructions** — system prompt injected into the LLM on every call
   - **Welcome Message** — spoken to the caller when they connect
3. **Train step** (optional) — add text, URLs, or files; click "Generate" to extract content locally. Content is injected into the LLM system prompt at call time.
4. **Phone Numbers & Usage step** — copy the **ConversationRelay WebSocket URL** for this agent (needed for Step 5)

> **Note:** Twilio agents have `agent_id = null` — no ElevenLabs agent is created.

---

## Step 5 — Start the Relay WebSocket Server

Twilio ConversationRelay requires a persistent WebSocket connection. This runs as a separate process alongside Octane.

**Start the server:**
```bash
php artisan phone-call-agent:relay-server --port=8090
```

For production, run it as a supervised process (e.g. Supervisor):

```ini
[program:twilio-relay]
command=php /var/www/html/artisan phone-call-agent:relay-server --port=8090
autostart=true
autorestart=true
stderr_logfile=/var/log/twilio-relay.err.log
stdout_logfile=/var/log/twilio-relay.out.log
```

**Add nginx proxy rule** (inside your server block):

```nginx
location /api/phone-call-agent/ws/twilio/ {
    proxy_pass http://127.0.0.1:8090;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600s;
}
```

This routes `wss://yourdomain.com/api/phone-call-agent/ws/twilio/*` to the Ratchet server. All other HTTP traffic continues through Octane/RoadRunner.

---

## Step 6 — Connect the Phone Number to the Agent

Open the agent → **Phone Numbers & Usage** step → **Connect Phone Number**. In the modal enter:

- **Twilio Account SID** (`AC...`)
- **Twilio Auth Token**
- **Phone Number** in E.164 format (e.g. `+16184237161`)

Click **Connect & Configure**. The app then, using the supplied credentials:

1. Looks up the number in that Twilio account (rejects with an error if the credentials are invalid or the number isn't in the account).
2. Sets the number's **Voice webhook** → `/api/phone-call-agent/webhook/twilio/inbound` (POST) and **Status callback** → `/api/phone-call-agent/webhook/twilio/status` (POST) automatically — no Twilio-console step needed.
3. Stores the number + credentials + Twilio number SID on the agent (`phone_number`, `twilio_account_sid`, `twilio_auth_token` (encrypted), `phone_number_id`).

Inbound calls are then matched to the agent by `phone_number === To` — the **first call works immediately**, no seeding required. **Remove** clears our webhook off the number and unlinks it locally.

> **Manual fallback:** if you'd rather not store API credentials, you can still set the two webhook URLs by hand in **Twilio Console → Phone Numbers → Active Numbers**. The legacy resolution (matching by a prior `called_number` call record) remains as a fallback when an agent has no stored `phone_number`.

---

## How It Works

### TwiML Response (ConversationRelay)

When a Twilio call arrives, the app returns:

```xml
<Response>
    <Connect>
        <ConversationRelay
            url="wss://yourdomain.com/api/phone-call-agent/ws/twilio/{agent-uuid}"
            voice="Polly.Joanna-Neural"
            welcomeGreeting="Hello, how can I help you today?"
            language="en-US"
        />
    </Connect>
</Response>
```

### WebSocket Protocol (ConversationRelay)

Twilio sends JSON messages over the WebSocket:

| Event | Direction | Meaning |
|-------|-----------|---------|
| `connected` | Twilio → App | WebSocket opened, includes `callSid` |
| `prompt` | Twilio → App | Caller spoke; includes `voicePrompt` (transcribed text) |
| `interrupt` | Twilio → App | Caller interrupted agent speech |
| `disconnect` | Twilio → App | Call ended |

App responds to `prompt` with:
```json
{ "type": "text", "token": "LLM response text here", "last": true }
```

### Knowledge Base Injection

At call time the handler builds the LLM system prompt as:

```
{agent.instructions}

# Knowledge Base

{train1.content}

---

{train2.content}
```

All train records with `trained_at` set are included. "Generate" in the Train step extracts and stores content locally — no external API is called.

### LLM Engine Selection

The `ai_model` field on the agent determines which LLM provider is used:

| Model prefix | Engine |
|-------------|--------|
| `gpt-*`, `o1-*`, `o3-*` | OpenAI |
| `claude-*` | Anthropic |
| `gemini-*` | Google Gemini |
| `deepseek-*` | DeepSeek |

API keys are read from the global AI settings (same keys used elsewhere in the app).

---

## Webhook Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/phone-call-agent/webhook/twilio/inbound` | POST | Returns ConversationRelay TwiML |
| `/api/phone-call-agent/webhook/twilio/status` | POST | Updates call duration + status on completion |

These routes have **no auth middleware** — Twilio calls them directly. When the resolved agent has its own **Twilio Auth Token**, the request's `X-Twilio-Signature` is validated against that token (HMAC-SHA1 over the URL + sorted params); an invalid or missing signature returns `403`. Agents without a stored token skip validation (global/legacy setups).

---

## Call History

Call records and transcripts are stored identically to ElevenLabs agents:
- `ext_phone_call_agent_calls` — created at call start, duration updated by status webhook
- `ext_phone_call_agent_transcripts` — stored in real-time per conversation turn during the WebSocket session

The call history UI, filtering (including `?provider=twilio`), and export all work without any changes.

---

## Plan Limits

If a user's plan has `phone_call_agent_seconds_limit` set:

- **`-1`** → Unlimited calls
- **`0`** → Calls disabled (returns `<Say>` + `<Hangup>`)
- **`> 0`** → Monthly seconds budget; exceeded budget returns `<Say>` + `<Hangup>`

---

## Troubleshooting

| Symptom | Likely Cause |
|---------|-------------|
| Twilio shows "Application Error" | Webhook URL unreachable or returning non-200; check app logs |
| Twilio shows "ConversationRelay failed" | Relay server not running or nginx proxy rule missing |
| Caller hears "This number is not in service" | No active agent has `phone_number` matching the dialed number (and no legacy `called_number` call record). Reconnect the number in the Phone Numbers step |
| Caller hears "Request could not be verified" / `403` in logs | `X-Twilio-Signature` failed validation — the agent's stored Auth Token doesn't match the Twilio account that owns the number, or the public webhook URL differs from what Twilio signed (proxy rewriting host/scheme) |
| "Invalid Twilio credentials" / "number not found" when connecting | SID/Auth Token wrong, or the number isn't in that Twilio account |
| Caller hears "Monthly call limit reached" | User's plan seconds budget is exhausted for the month |
| Caller connects but hears silence | Relay server running but LLM API key missing or wrong; check global AI settings |
| LLM returns error in logs | Check the API key for the selected model's provider in global AI settings |
| `TWILIO_ACCOUNT_SID` errors in logs | Credentials not set in Admin Settings or env vars |
| Transcripts not appearing | Relay server crashed mid-call; check relay server logs |
