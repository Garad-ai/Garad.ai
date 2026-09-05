# AI Agent: Slack Channel — Configuration Guide

## Prerequisites

- A Slack workspace where you have permission to create apps
- MagicAI running and publicly accessible (use [ngrok](https://ngrok.com) for local dev)
- AI Agent extension installed and active

---

## Step 1: Create a Slack App

1. Go to [api.slack.com/apps](https://api.slack.com/apps)
2. Click **Create New App** → **From scratch**
3. Enter an app name (e.g. `MagicAI Bot`) and select your workspace
4. Click **Create App**

---

## Step 2: Add Bot Token Scopes

1. In your app settings → **OAuth & Permissions** (left sidebar)
2. Scroll to **Scopes** → **Bot Token Scopes** → **Add an OAuth Scope**
3. Add all of the following:

| Scope | Purpose |
|---|---|
| `chat:write` | Send messages |
| `channels:history` | Read messages in public channels |
| `im:history` | Read direct messages |
| `im:write` | Enable DM input box |
| `files:read` | Download shared files (images, PDFs) |

---

## Step 3: Install App to Workspace

1. **OAuth & Permissions** → click **Install to Workspace**
2. Authorize the app
3. Copy the **Bot User OAuth Token** (starts with `xoxb-`) — you will need this later

---

## Step 4: Enable Event Subscriptions

1. Left sidebar → **Event Subscriptions** → toggle **Enable Events** ON
2. Set **Request URL** to your webhook endpoint:
   ```
   https://your-domain.com/api/ai-agent/slack/{channel_id}/webhook
   ```
   Replace `{channel_id}` with the numeric ID of the channel record created in Step 6.
   Slack will send a verification challenge — the app handles it automatically.
3. Scroll to **Subscribe to bot events** → **Add Bot User Event** → add:
   - `message.channels` — messages in public channels where bot is a member
   - `message.im` — direct messages to the bot
4. Click **Save Changes**

---

## Step 5: Enable Direct Messages (App Home)

To allow users to DM the bot:

1. Left sidebar → **App Home**
2. Scroll to **Show Tabs** → enable **Messages Tab**
3. Check **"Allow users to send Slash commands and messages from the messages tab"**
4. **Reinstall the app** (banner will appear, or go to OAuth & Permissions → Reinstall)
5. Hard-refresh Slack (`Cmd+R` or quit and reopen)

---

## Step 6: Connect Channel in MagicAI

1. Go to **AI Agent → Channels → Connect a Channel**
2. Set **Channel Type** to `Slack`
3. Fill in credentials:

| Field | Where to find it |
|---|---|
| **Bot Token** | OAuth & Permissions → Bot User OAuth Token (`xoxb-...`) |
| **Signing Secret** | Basic Information → App Credentials → Signing Secret |

4. Click **Connect** — note the numeric **channel ID** from the URL (e.g. `/channels/21/edit`)
5. Update the Event Subscriptions Request URL with this ID (Step 4)

---

## Step 7: Invite Bot to Channels

For public channel messages, invite the bot:

```
/invite @YourBotName
```

DMs work without an invite — users can message the bot directly from App Home.

---

## Webhook URL Reference

```
POST /api/ai-agent/slack/{channel_id}/webhook
```

Handles:
- `url_verification` — Slack challenge on first setup (automatic)
- `event_callback` → `message` — incoming text and file messages
- Bot messages are automatically ignored (no echo loops)

---

## Supported Message Types

| Type | Behaviour |
|---|---|
| Text | Passed directly to AI Agent workflow |
| Image (jpg, png, gif, webp) | Downloaded, base64-encoded, sent as attachment |
| PDF document | Downloaded, base64-encoded, sent as attachment |
| Other files (video, zip, etc.) | Silently ignored |

---

## Troubleshooting

| Error | Cause | Fix |
|---|---|---|
| `not_allowed_token_type` | Used `xapp-` token instead of `xoxb-` | Use Bot User OAuth Token from OAuth & Permissions |
| `channel_not_found` | Wrong recipient ID or bot not in channel | Invite bot with `/invite @BotName` |
| Webhook URL verification fails | Wrong channel ID in URL | Check channel ID matches DB record |
| DM input box missing | Messages Tab not enabled or app not reinstalled | Enable in App Home, reinstall, hard-refresh Slack |
| Events not received | Scopes added after initial install | Reinstall app after scope changes |
