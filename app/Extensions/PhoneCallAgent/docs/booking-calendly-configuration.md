# Booking Configuration Guide — Calendly

## Overview

This guide covers configuring Calendly as the booking provider for a Phone Call Agent. The same Calendly setup works for both ElevenLabs and Twilio agents — the difference is only in how the tool loop is triggered (see [booking-elevenlabs-configuration.md](booking-elevenlabs-configuration.md) and [booking-twilio-configuration.md](booking-twilio-configuration.md) for phone agent setup).

**Flow (ElevenLabs):**
Caller speaks → ElevenLabs LLM decides to call a tool → ElevenLabs POSTs to our webhook → PHP executes Calendly API → returns `{"result": "..."}` → ElevenLabs speaks response

**Flow (Twilio):**
Caller speaks → Twilio STT → `TwilioLlmService::completeWithTools` → LLM returns tool call → PHP executes Calendly API → result fed back to LLM → Twilio TTS speaks response

---

## Plan Requirements

| Feature | Free plan | Paid plan |
|---------|-----------|-----------|
| `check_availability` | ✓ | ✓ |
| `cancel_booking` | ✓ | ✓ |
| `create_booking` | ✗ | ✓ |
| `reschedule_booking` | ✗ | ✓ |

Booking creation and rescheduling use the Calendly Scheduling API, which is gated to paid plans. Attempting to create a booking on a free plan returns a 403 error and the agent will tell the caller the booking could not be completed. Upgrade at [calendly.com/pricing](https://calendly.com/pricing).

---

## Prerequisites

- Phone Call Agent fully configured (see `elevenlabs-configuration.md` or `twilio-configuration.md`)
- A [Calendly](https://calendly.com) account — paid plan required for booking creation and rescheduling
- Calendly Personal Access Token
- The event type URI from Calendly

---

## Step 1 — Get a Calendly Personal Access Token

1. Log in to [app.calendly.com](https://app.calendly.com)
2. Go to **Integrations → API & Webhooks**
3. Under **Personal Access Tokens**, click **Generate New Token**
4. Give it a name (e.g. "Phone Agent") → copy the token (shown once)

> The token starts with `eyJ...` (a JWT). Keep it secret.

---

## Step 2 — Get Your Event Type URI

1. In Calendly go to **Event Types**
2. Click the **three-dot menu (⋯)** on the event type you want the agent to book → **Copy link**
   - This gives you a booking URL like `https://calendly.com/yourname/event-name`
3. To get the API URI, call:

```
GET https://api.calendly.com/event_types
Authorization: Bearer {your_token}
```

Find the matching event type and copy its `uri` field, e.g.:

```
https://api.calendly.com/event_types/AAABBBCCC111222
```

Paste this full URI as the **Event Type ID** in the agent editor.

---

## Step 3 — Enable Booking in the Agent Editor

1. Open the Phone Call Agent editor → **Configure** step
2. Scroll to the **Booking** section
3. Toggle **Booking Assistant** on
4. Set **Booking Provider** → `Calendly`
5. Set **Event Type ID** → the full URI from Step 2 (e.g. `https://api.calendly.com/event_types/AAABBBCCC111222`)
6. Set **API Key** → the Personal Access Token from Step 1
7. Click **Next** to save

---

## Step 4 — Phone Agent Setup

### ElevenLabs agents
After saving, copy the four **ElevenLabs Server Tool URLs** shown in the editor and add them as Server Tools in the ElevenLabs dashboard. See [booking-elevenlabs-configuration.md](booking-elevenlabs-configuration.md) Steps 4–5 for the full Server Tool configuration (tool parameter schemas and system prompt additions are identical for Calendly and Cal.com).

### Twilio agents
No extra steps. Booking tools are wired automatically in the WebSocket handler when `booking_enabled` is on. See [booking-twilio-configuration.md](booking-twilio-configuration.md) for system prompt guidance.

---

## Calendly-Specific Behaviour

### Booking creation requires a paid plan

The Calendly Scheduling API (`POST /scheduled_events/invitees`) is only available on paid Calendly plans. If the agent tries to create a booking and Calendly returns a `403`, the tool will return an error message to the LLM and the agent will tell the caller the booking could not be completed.

### Booking UIDs are full Calendly URIs

When a booking is created, the UID returned to the LLM is the full Calendly invitee URI, e.g.:

```
https://api.calendly.com/scheduled_events/EVTUUID/invitees/INVUUID
```

The agent reads this to the caller as their booking reference. The caller should save it for cancellations or rescheduling.

### Rescheduling = cancel + re-book

Calendly has no reschedule API endpoint. When `reschedule_booking` is called, the system:
1. Fetches the original booking's invitee info (name, email, timezone) from Calendly
2. Cancels the original event
3. Creates a new booking at the new slot with the same invitee details

The agent receives a new UID for the rescheduled booking. Instruct callers to note the new reference number.

### Availability check window

`check_availability` queries a 7-day window starting from the date provided by the caller. This matches Calendly's `GET /event_type_available_times` limit of 7 days per request.

---

## What the Agent Collects from Callers

| Field | How |
|-------|-----|
| Preferred date | Agent asks, then calls `check_availability` |
| Chosen slot | Agent reads available slots and asks caller to pick one |
| Full name | Agent asks directly |
| Email address | Agent asks and spell-confirms if unclear |
| Timezone | Agent asks or infers from caller context |
| Phone (optional) | Only passed if the event type has SMS reminders enabled |

---

## Tool Behaviour Reference

### `check_availability`
- Calls `GET /event_type_available_times?event_type={uri}&start_time={ISO}&end_time={ISO}`
- Returns flat list of ISO 8601 slot strings

### `create_booking`
- Calls `POST /scheduled_events/invitees` with event type URI, start time, and invitee details
- Requires paid Calendly plan; returns error message on 403
- On success, returns the invitee URI as the booking UID

### `cancel_booking`
- Calls `POST /scheduled_events/{uuid}/cancellation`
- Accepts full invitee URI or bare UUID as `booking_uid`
- Agent must first obtain the booking UID from the caller

### `reschedule_booking`
- No direct Calendly API — executes 3 calls: GET invitees → cancel → create
- Returns a new booking UID for the rescheduled event

---

## Troubleshooting

| Symptom | Likely cause |
|---------|-------------|
| "Booking failed" on create | Account not on a paid Calendly plan — upgrade at [calendly.com/pricing](https://calendly.com/pricing) |
| No slots returned | Wrong event type URI, or dates outside the event type's scheduling window in Calendly |
| 401 on any API call | Personal Access Token expired or revoked — regenerate in Calendly → Integrations → API & Webhooks |
| Reschedule returns empty | The original booking UUID was not found — check the caller provided the correct reference |
| Agent says "booking not configured" | `booking_enabled` is off, or `booking_provider` / `booking_event_type_id` / `booking_api_key` fields empty |
| Event type URI rejected | Must be the API URI (`https://api.calendly.com/event_types/...`), not the public booking link |
