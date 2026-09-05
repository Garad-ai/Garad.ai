# Booking Configuration Guide — Twilio Provider

## Overview

When booking is enabled **and fully configured** on a Twilio agent (booking toggle on, plus a booking provider and event type ID set), the LLM receives four tools it can call mid-conversation:

- `check_availability` — queries available time slots from the booking provider
- `create_booking` — creates a confirmed appointment
- `cancel_booking` — cancels an existing booking by UID
- `reschedule_booking` — moves an existing booking to a new slot

The tool loop is handled entirely in PHP (`TwilioLlmService::completeWithTools`). No changes are needed on the Twilio or ElevenLabs side. The LLM engine (OpenAI, Anthropic, Gemini, DeepSeek) is determined by the agent's configured AI model — tools are formatted in the correct schema for each engine automatically.

**Flow:**
Caller speaks → Twilio STT → WebSocket prompt event → `ConversationRelayHandler` → `TwilioLlmService::completeWithTools` → LLM returns tool call → PHP executes Cal.com API → result fed back to LLM → LLM responds to caller → Twilio TTS speaks response

---

## Prerequisites

- Twilio agent fully configured and working (see `twilio-configuration.md`)
- A [Cal.com](https://cal.com) account with at least one event type created
- Cal.com API key (Personal Access Token or OAuth access token)
- The event type ID from Cal.com

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
3. The URL will contain the ID: `app.cal.com/event-types/123` → ID is `123`

Alternatively, call `GET https://api.cal.com/v2/event-types` with your API key to list all event types.

---

## Step 3 — Configure the Agent

1. Open the Phone Call Agent editor → **Configure** step
2. Scroll to the **Booking** section
3. Toggle **Booking Assistant** on
4. Set **Booking Provider** → `Cal.com`
5. Set **Event Type ID** → the numeric ID from Step 2 (e.g. `123`)
6. Set **API Key** → the key from Step 1
7. Click **Next** to save

---

## Step 4 — Update the System Prompt (optional but recommended)

The system automatically appends a booking capability section to the agent's prompt **only when booking is fully configured** (enabled, with a provider and event type ID). This keeps the prompt and the available tools in sync — the agent is never told it can book unless the booking tools are actually wired up, which prevents it from inventing fake confirmations. You can extend the agent's **Instructions** field to add context specific to your use case, for example:

```
When a caller wants to book, always confirm their timezone before booking.
Our office is in New York (America/New_York). If the caller doesn't know their timezone, assume America/New_York.
After booking, always read back the date and time in plain English.
```

---

## What the Agent Collects from Callers

Before calling `create_booking`, the LLM will gather:

| Field | How |
|-------|-----|
| Preferred date range | Agent asks, then calls `check_availability` |
| Chosen slot | Agent reads available slots and asks caller to pick one |
| Full name | Agent asks directly |
| Email address | Agent asks and spell-confirms if unclear |
| Timezone | Agent asks or infers from caller context |
| Phone (optional) | Only collected if SMS reminders are enabled on the event type |

---

## Tool Behaviour Reference

### `check_availability`
- Calls `GET /v2/slots?eventTypeId={id}&start={start}&end={end}`
- `start_date` / `end_date` are `YYYY-MM-DD` strings the LLM derives from the conversation
- Returns a list of ISO 8601 slot strings, e.g. `2026-06-03T09:00:00Z`

### `create_booking`
- Calls `POST /v2/bookings`
- Required: `slot`, `name`, `email`, `timezone`
- On success returns the booking `uid` — the LLM reads it back to the caller as a reference number

### `cancel_booking`
- Calls `POST /v2/bookings/{uid}/cancel`
- Agent must first obtain the booking UID from the caller (read from a confirmation email or previous call)

### `reschedule_booking`
- Calls `POST /v2/bookings/{uid}/reschedule`
- Cal.com moves the existing booking to the new slot in a single call (the UID may change — the agent reads back the new one)

---

## Conversation Memory

Twilio's ConversationRelay only feeds plain user/assistant text back to the LLM on later turns — it does not replay tool calls. To stop the agent from re-running lookups or forgetting a booking UID, the tool results from each turn are folded into the assistant's stored history as an internal note (not spoken to the caller). So once the agent has checked availability or created a booking, it remembers the returned slots and UID for the rest of the call without calling the tool again.

---

## Troubleshooting

| Symptom | Likely cause |
|---------|-------------|
| Agent says "no slots available" when there should be slots | Wrong event type ID, or date range outside event type's scheduling window |
| 400 error on booking | Event type has required custom fields — check Cal.com event type settings and add instructions for the agent to collect them |
| Agent doesn't use booking tools | `booking_enabled` toggle is off, or `booking_provider` / `booking_event_type_id` fields are empty |
| Tool calls work but agent uses wrong timezone | Add explicit timezone instructions to the agent's system prompt |
| API key rejected | Key may have expired or been revoked — regenerate in Cal.com settings |
