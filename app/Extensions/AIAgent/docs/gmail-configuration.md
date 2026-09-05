# Gmail Connector Configuration Guide

## Overview

The Gmail connector requires a Google Cloud OAuth 2.0 app. Credentials are configured in the admin panel — no `.env` changes needed.

---

## Step 1 — Create a Google Cloud Project

1. Go to [https://console.cloud.google.com/](https://console.cloud.google.com/)
2. Create a new project (or select an existing one).
3. In the left sidebar: **APIs & Services → Library**
4. Search for **Gmail API** → click it → click **Enable**.

---

## Step 2 — Configure the OAuth Consent Screen

1. Go to **APIs & Services → OAuth consent screen**
2. Select **External** (or Internal if G Suite org) → **Create**
3. Fill in:
   - **App name**: your app name
   - **User support email**: your email
   - **Developer contact email**: your email
4. Click **Save and Continue** through Scopes and Test Users screens.
5. Add test users (the Gmail accounts you want to connect) while in **Testing** publish status.
6. To go live: click **Publish App** (requires Google verification for sensitive scopes).

---

## Step 3 — Create OAuth 2.0 Credentials

1. Go to **APIs & Services → Credentials**
2. Click **Create Credentials → OAuth client ID**
3. Application type: **Web application**
4. Name: anything descriptive (e.g. `MagicAI Gmail`)
5. Under **Authorized redirect URIs**, add your callback URL — copy it from **Settings → Gmail Settings → Authorized Redirect URI**.
6. Click **Create** — copy the **Client ID** and **Client Secret**.

---

## Step 4 — Enter Credentials in Admin Panel

1. Go to **Settings → Gmail Settings** in the admin panel.
2. Paste the **Client ID** and **Client Secret**.
3. Click **Save**.

---

## Step 5 — Connect Gmail

1. Go to **AI Agent → Connectors** in the dashboard.
2. Click the **Gmail** card.
3. Authorize with your Google account.
4. The connector will appear in the **Manage Connectors** table.

---

## Scopes Requested

| Scope | Purpose |
|---|---|
| `https://mail.google.com/` | Full mailbox access (send, read, delete) |
| `https://www.googleapis.com/auth/gmail.modify` | Modify messages and labels |
| `https://www.googleapis.com/auth/gmail.compose` | Create drafts and send |
| `openid`, `email`, `profile` | Identify the connected account |

> Google will show these scopes to the user on the consent screen.

---

## Config File Reference

**`app/Extensions/AIAgentGmail/config/ai-agent-gmail.php`**

```php
return [
    'scopes' => [
        'https://www.googleapis.com/auth/gmail.modify',
        'https://www.googleapis.com/auth/gmail.compose',
        'https://mail.google.com/',
        'openid',
        'email',
        'profile',
    ],
];
```

Client ID, Client Secret, and Redirect URI are stored in the database and managed via **Settings → Gmail Settings**. Scopes are fixed in the config file — edit them directly if needed.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `redirect_uri_mismatch` | Callback URL in Google Console must exactly match the URL shown in **Settings → Gmail Settings → Authorized Redirect URI** (including `http` vs `https`) |
| `access_denied` | User is not added as a test user while app is in Testing mode |
| Token expired immediately | `expires_at` is stored in UTC — verify server timezone matches |
| `Gmail connector has no access token` | Connector exists but OAuth failed — reconnect via the Reconnect button |
| `Invalid or expired OAuth state` | State cache expired (10 min TTL) — start OAuth flow again |
