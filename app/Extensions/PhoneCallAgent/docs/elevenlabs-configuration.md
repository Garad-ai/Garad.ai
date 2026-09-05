# ElevenLabs Configuration Guide — Phone Call Agent

## Overview

ElevenLabs is the **AI engine** for all phone call agents regardless of provider. It handles:
- The conversational AI agent (prompt, voice, language)
- Real-time audio synthesis (text-to-speech)
- Knowledge base / training documents

When the provider is set to **ElevenLabs**, ElevenLabs also manages the phone number directly (no Twilio needed).

**Flow:**  
Caller dials ElevenLabs number → ElevenLabs routes directly to the agent → AI responds in real-time

---

## Prerequisites

- An [ElevenLabs account](https://elevenlabs.io) with Conversational AI access
- ElevenLabs API key

---

## Step 1 — Get ElevenLabs API Key

1. Log in to [elevenlabs.io](https://elevenlabs.io)
2. Go to **Profile → API Keys**
3. Copy your API key

---

## Step 2 — Configure API Key in Admin Settings

1. Go to **Admin → Settings → TTS** → [http://127.0.0.1:8000/dashboard/admin/settings/tts](http://127.0.0.1:8000/dashboard/admin/settings/tts)
2. Find the ElevenLabs section
3. Paste your API key
4. Save

> The ElevenLabs API key is shared across all ElevenLabs-powered features (Voice Clone, Voice Chat, Phone Call Agent, etc.).

---

## Step 3 — Set Provider in Admin Settings

1. Go to **Admin → Settings → Phone Call Agent Settings** → [http://127.0.0.1:8000/dashboard/phone-call-agent/admin/settings](http://127.0.0.1:8000/dashboard/phone-call-agent/admin/settings)
2. Set **Phone Call Provider** to `ElevenLabs`
3. Save

---

## Step 4 — Create an Agent

1. Go to **Phone Call Agents** → [http://127.0.0.1:8000/dashboard/phone-call-agent](http://127.0.0.1:8000/dashboard/phone-call-agent)
2. Click **+ New Agent**
3. Fill in the **Configure** tab:
   - **Title** — internal name for the agent
   - **Welcome Message** — first thing the AI says when the call connects
   - **Instructions** — system prompt for the AI's behavior and persona
   - **Language** — sets the TTS/STT language (`eleven_turbo_v2` for English, `eleven_flash_v2_5` for multilingual)
4. Click **Next** — this creates the ElevenLabs agent via `POST v1/convai/agents/create` and stores the `agent_id`

**Default values set on creation:**
- Voice: `cjVigY5qzO86Huf0OWal` (Rachel)
- Model: `eleven_turbo_v2` (English) or `eleven_flash_v2_5` (multilingual)

---

## Step 5 — Train the Agent (Optional)

On the **Train** tab, add knowledge base documents:

| Type | Description |
|------|-------------|
| **URL** | Crawl a webpage and embed its content |
| **PDF / File** | Upload a document |
| **Text** | Paste raw text directly |

Each document is uploaded to ElevenLabs (`POST v1/convai/knowledge-base/`) and linked to the agent on the next update.

---

## Step 6 — Import & Attach a Phone Number (ElevenLabs Provider)

The agent's **Phone Numbers & Usage** step manages the number in-app — no need to use the ElevenLabs dashboard.

**Import a number** — open the agent → **Phone Numbers & Usage** step → **Import Phone Number**, then pick a source under **Import From**:

| Source | Fields |
|--------|--------|
| **Twilio** | Twilio Account SID, Twilio Auth Token |
| **SIP Trunk** | SIP Address, Transport (auto/udp/tcp/tls), Username (optional), Password (optional) |
| **Exotel** | Account SID, API Key, API Token, API Subdomain (`api.exotel.com` / `api.in.exotel.com`), App ID, Applet URL (optional) |

All three also take the **Phone Number** (E.164) and an optional **Label**. Click **Import & Attach** — the app imports the number into the ElevenLabs workspace (`POST v1/convai/phone-numbers` with the matching `provider` discriminator) and assigns this agent to it (`PATCH v1/convai/phone-numbers/{id}`) in one step. ElevenLabs then routes inbound calls straight to the agent — no Twilio relay server.

The number and its ElevenLabs `phone_number_id` are stored on the agent. For the **Twilio** source the SID/token are also kept (token encrypted); SIP/Exotel secrets live only in ElevenLabs.

**Pick an already-imported number:** numbers previously imported by **this user's** agents appear under "Your Imported Numbers" — click **Attach** to reassign one to the current agent. The list is scoped per user (the workspace API key is shared across tenants), so you never see other tenants' numbers.

**Remove** deletes the number from the ElevenLabs workspace and unlinks it locally.

> The ElevenLabs inbound/post-call webhook is workspace-global and configured once in admin settings — see below.

---

## Webhook Endpoint (ElevenLabs Provider)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/phone-call-agent/webhook/elevenlabs/inbound` | POST | Receives ElevenLabs conversation events (initiation, transcripts, end) |

**Supported event types:**

| `type` | Action |
|--------|--------|
| `conversation_initiation_metadata` | Creates `ExtPhoneCallAgentCall` record |
| `transcript` | Appends `ExtPhoneCallAgentTranscript` row |
| `conversation_ended` | Updates call status to `completed` + sets duration |

---

## ElevenLabs API Methods Used

| Method | Endpoint | Used For |
|--------|----------|---------|
| `createAgent` | `POST v1/convai/agents/create` | Agent creation on store |
| `updateAgent` | `PATCH v1/convai/agents/{id}` | Agent update + knowledge base sync |
| `deleteAgent` | `DELETE v1/convai/agents/{id}` | Agent deletion |
| `getListOfVoices` | `GET v1/voices` | Voice selector in configure tab |
| `createKnowledgebaseDocFromText` | `POST v1/convai/knowledge-base/text` | Text training |
| `createKnowledgebaseDocFromUrl` | `POST v1/convai/knowledge-base/url` | URL training |
| `createKnowledgebaseDocFromFile` | `POST v1/convai/knowledge-base/file` | File training |
| `deleteKnowledgebaseDocument` | `DELETE v1/convai/knowledge-base/{id}` | Training data deletion |
| `importPhoneNumber` | `POST v1/convai/phone-numbers` | Import a number (provider: `twilio` / `sip_trunk` / `exotel`) into the workspace |
| `assignAgent` | `PATCH v1/convai/phone-numbers/{id}` | Attach an agent to a phone number |
| `listPhoneNumbers` | `GET v1/convai/phone-numbers` | List workspace numbers (scoped per user in-app) |
| `removePhoneNumber` | `DELETE v1/convai/phone-numbers/{id}` | Remove a number from the workspace |

---

## Default Models

| Constant | Value | Use Case |
|----------|-------|---------|
| `DEFAULT_ELEVENLABS_MODEL` | `eleven_flash_v2_5` | Multilingual (non-English) |
| `DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH` | `eleven_turbo_v2` | English only (lower latency) |
| `DEFAULT_ELEVENLABS_VOICE_ID` | `cjVigY5qzO86Huf0OWal` | Default voice (Rachel) |

Model is auto-selected on agent creation based on the `language` field. Can be overridden on the Configure tab.

---

## Testing

### Option 1 — Browser Test (No Phone Needed, Easiest)

1. Open agent editor → step 3 (Usage tab)
2. Copy the **ElevenLabs Agent ID**
3. Go to [elevenlabs.io](https://elevenlabs.io) → **Conversational AI → Agents**
4. Find your agent → click the **microphone / Test** button
5. Talk directly in browser — no phone or webhook needed

### Option 2 — Real Phone Call

1. In ElevenLabs dashboard → **Conversational AI → Phone Numbers**
2. Purchase a phone number (US ~$2/month)
3. Assign it to your agent
4. Dial the number from your phone — call connects to the AI

No webhook configuration required for this flow; ElevenLabs routes calls directly.

### Option 3 — Full End-to-End (Webhooks + Call Records)

Your app must be publicly reachable for ElevenLabs to POST webhook events (call records, transcripts).

For local development, expose your app via ngrok:

```bash
ngrok http 8000
```

Then in ElevenLabs dashboard → agent settings → set the **Post-call webhook URL** to:

```
https://<your-ngrok-id>.ngrok.io/api/phone-call-agent/webhook/elevenlabs/inbound
```

**Webhook events logged to DB:**

| `type` | Action |
|--------|--------|
| `conversation_initiation_metadata` | Creates `ExtPhoneCallAgentCall` record |
| `transcript` | Appends `ExtPhoneCallAgentTranscript` row |
| `conversation_ended` | Updates call status + duration |

View saved records in **Call History** (Usage tab → "View Call History" button).

---

## Troubleshooting

| Symptom | Likely Cause |
|---------|-------------|
| `invalid_api_key` on agent creation | API key not set in [Admin → Settings → TTS](http://127.0.0.1:8000/dashboard/admin/settings/tts) |
| Agent creation returns 422 | ElevenLabs API error; check Laravel logs (`storage/logs/laravel.log`) |
| Voice list empty in configure tab | API key invalid or ElevenLabs service unavailable |
| Training upload fails | File too large or unsupported format; ElevenLabs supports PDF, DOCX, TXT |
| Knowledge base not reflecting on calls | Click "Save" on Configure tab after adding training data to sync to ElevenLabs |
