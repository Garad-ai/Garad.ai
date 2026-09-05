# Booking Configuration Guide — ElevenLabs Provider

## Overview

ElevenLabs agents handle the full conversation loop internally (LLM + TTS). Our PHP code never sees tool calls in real time — instead, ElevenLabs calls a **Server Tool webhook** on our server mid-conversation whenever the agent decides to invoke a booking action.

When you enable booking on an ElevenLabs agent, the four server tools are **created automatically** on the ElevenLabs agent (inline, via the agent's `conversation_config`). There is **no manual dashboard setup** — toggling booking on/off and saving the agent adds or removes the tools for you.

**Flow:**
Caller speaks → ElevenLabs STT → ElevenLabs LLM decides to call a tool → ElevenLabs POSTs to our webhook URL → PHP executes Cal.com / Calendly API → returns `{"result": "..."}` JSON → ElevenLabs feeds result back to LLM → LLM speaks response to caller

The four tools and their webhook endpoints:

| Tool | Endpoint |
|------|----------|
| Check Availability | `POST /api/phone-call-agent/webhook/elevenlabs/tool/{agentUuid}/check_availability` |
| Create Booking | `POST /api/phone-call-agent/webhook/elevenlabs/tool/{agentUuid}/create_booking` |
| Cancel Booking | `POST /api/phone-call-agent/webhook/elevenlabs/tool/{agentUuid}/cancel_booking` |
| Reschedule Booking | `POST /api/phone-call-agent/webhook/elevenlabs/tool/{agentUuid}/reschedule_booking` |

---

## Prerequisites

- ElevenLabs agent fully configured and working (see `elevenlabs-configuration.md`)
- A [Cal.com](https://cal.com) account with at least one event type created
- Cal.com API key (Personal Access Token or OAuth access token)
- The event type ID from Cal.com
- Your app is publicly accessible over HTTPS (ElevenLabs cannot reach `localhost`)

> The webhook URLs are built from `APP_URL`. Make sure `APP_URL` is set to your public HTTPS domain, otherwise the auto-created tools will point at an unreachable address.

---

## Step 1 — Get Cal.com API Key

1. Log in to [app.cal.com](https://app.cal.com)
2. Go to **Settings → Developer → API Keys**
3. Click **Add** → give it a name → copy the key (shown once)

> The key starts with `cal_live_` for production or `cal_` for testing.

---

## Step 2 — Get Your Event Type ID

1. In Cal.com go to **Event Types**
2. Click the event type you want the agent to book
3. The URL contains the ID: `app.cal.com/event-types/123` → ID is `123`

---

## Step 3 — Enable Booking in the Agent Editor

1. Open the Phone Call Agent editor → **Configure** step
2. Scroll to the **Booking** section
3. Toggle **Booking Assistant** on
4. Set **Booking Provider** → `Cal.com`
5. Set **Event Type ID** → the numeric ID from Step 2
6. Set **API Key** → the key from Step 1
7. Click **Next** to save

On save, the four server tools are created on the ElevenLabs agent automatically. Toggling booking off and saving removes them again. No dashboard action is required.

> **Do not add these tools manually in the ElevenLabs dashboard.** Manually-added tools become `tool_ids` on the agent, and ElevenLabs rejects an agent that has both inline tools and tool IDs (`both_tools_and_tool_ids_provided`). The app manages the booking tools inline; leave them to it.

---

## Step 4 — Update the ElevenLabs Agent System Prompt

The tools are created for you, but the agent still needs guidance on *when* to use them. In the agent editor (**Configure** step → instructions), include booking guidance, for example:

```
You can check availability and book appointments for callers.
When they want to schedule: ask for their preferred date (a single date in YYYY-MM-DD format), then call check_availability with that date. Present the returned slots and let the caller pick one. Then collect full name, email, and timezone before booking.
Always confirm all details with the caller before calling create_booking.
For cancellations or rescheduling, ask for the booking UID or the email used when the booking was made.
If the caller doesn't know their timezone, ask for their city or country and infer it.

System Time is: {{system__time}}
```

---

## Webhook Request / Response Format

**ElevenLabs sends** (params at root level, no wrapper):
```json
{ "date": "2026-06-03" }
```

**Our server responds:**
```json
{ "result": "Available slots: 2026-06-03T09:00:00Z, 2026-06-03T11:00:00Z" }
```

The `result` string is passed directly to the ElevenLabs LLM as the tool output.

---

## Security

Server Tool webhook URLs contain the agent UUID, which acts as an unguessable token. No additional signature verification is applied to tool endpoints — ElevenLabs does not send `ElevenLabs-Signature` headers for Server Tool calls (only for conversation/post-call webhooks).

The `ElevenLabs-Signature` HMAC secret configured in admin settings applies only to other ElevenLabs webhooks (e.g. post-call), not to Server Tool endpoints.

---

## Troubleshooting

| Symptom | Likely cause |
|---------|-------------|
| Tools not created on the agent | Booking toggle off, or save failed. Check `APP_URL` is a public HTTPS domain and the ElevenLabs API key is set in admin settings. |
| `both_tools_and_tool_ids_provided` error on save | Booking tools were also added manually in the dashboard (stored as `tool_ids`). Remove the manual tools; the app manages them inline. |
| ElevenLabs can't reach the webhook | App not publicly accessible; `localhost` won't work — use a public HTTPS URL. |
| Tool returns "Booking is not configured" | `booking_enabled` is off, or `booking_provider` / `booking_event_type_id` fields are empty on the agent. |
| Agent calls wrong tool URL | UUID in the URL must match the agent's UUID — the app builds these automatically, so re-save the agent if it looks stale. |
| No slots returned | Wrong event type ID, or date range outside the event type's scheduling window in Cal.com. |
| 400 on booking creation | Event type may have required custom questions — check Cal.com event type settings. |
