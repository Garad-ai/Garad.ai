# Outlook Connector Configuration Guide

## Overview

The Outlook connector requires an Azure AD App Registration. Credentials are configured in the admin panel — no `.env` changes needed.

---

## Step 1 — Create an Azure AD App Registration

1. Go to [https://portal.azure.com/](https://portal.azure.com/)
2. Search for **App registrations** → click **New registration**
3. Fill in:
   - **Name**: anything descriptive (e.g. `MagicAI Outlook`)
   - **Supported account types**: `Accounts in any organizational directory and personal Microsoft accounts` (multi-tenant + personal)
   - **Redirect URI**: select **Web**, leave blank for now
4. Click **Register** — note the **Application (client) ID** and **Directory (tenant) ID**.

---

## Step 2 — Add a Client Secret

1. In your app registration: **Certificates & secrets → New client secret**
2. Add a description, choose an expiry period → click **Add**
3. Copy the **Value** immediately — it is only shown once.

---

## Step 3 — Configure the Redirect URI

1. Go to **Authentication → Add a platform → Web**
2. Under **Redirect URIs**, add the callback URL — copy it from **Settings → Outlook Settings → Authorized Redirect URI**.
3. Under **Implicit grant and hybrid flows**: leave both checkboxes unchecked.
4. Click **Save**.

---

## Step 4 — Add API Permissions

1. Go to **API permissions → Add a permission → Microsoft Graph → Delegated permissions**
2. Search for and add each of the following:

| Permission | Purpose |
|---|---|
| `Mail.ReadWrite` | Read, update, delete emails |
| `Mail.Send` | Send emails |
| `Calendars.ReadWrite` | Read, create, update, delete calendar events |
| `Contacts.ReadWrite` | Read, create, update, delete contacts |
| `offline_access` | Obtain refresh tokens for persistent access |
| `openid`, `profile`, `email` | Identify the connected account |

3. Click **Grant admin consent** if your tenant requires it (optional for personal accounts).

---

## Step 5 — Enter Credentials in Admin Panel

1. Go to **Settings → Outlook Settings** in the admin panel.
2. Paste the **Client ID** and **Client Secret**.
3. Set **Tenant ID** — use `common` for both work/school and personal accounts, or your Directory (tenant) ID to restrict to a single Azure AD tenant.
4. Click **Save**.

---

## Step 6 — Connect Outlook

1. Go to **AI Agent → Connectors** in the dashboard.
2. Click the **Outlook** card.
3. Authorize with your Microsoft account.
4. The connector will appear in the **Manage Connectors** table.

---

## Scopes Requested

| Scope | Purpose |
|---|---|
| `https://graph.microsoft.com/Mail.ReadWrite` | Read, update, delete emails |
| `https://graph.microsoft.com/Mail.Send` | Send emails |
| `https://graph.microsoft.com/Calendars.ReadWrite` | Read, create, update, delete calendar events |
| `https://graph.microsoft.com/Contacts.ReadWrite` | Read, create, update, delete contacts |
| `offline_access` | Refresh tokens for long-lived access |
| `openid`, `profile`, `email` | Identify the connected account |

> Microsoft will show these scopes to the user on the consent screen.

---

## Config File Reference

**`app/Extensions/AIAgentOutlook/config/ai-agent-outlook.php`**

```php
return [
    'scopes' => [
        'https://graph.microsoft.com/Mail.ReadWrite',
        'https://graph.microsoft.com/Mail.Send',
        'https://graph.microsoft.com/Calendars.ReadWrite',
        'https://graph.microsoft.com/Contacts.ReadWrite',
        'offline_access',
        'openid',
        'profile',
        'email',
    ],
];
```

Client ID, Client Secret, and Tenant ID are stored in the database and managed via **Settings → Outlook Settings**. Scopes are fixed in the config file — edit them directly if needed.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `redirect_uri_mismatch` / `AADSTS50011` | Callback URL in Azure Portal must exactly match the URL shown in **Settings → Outlook Settings → Authorized Redirect URI** (including `http` vs `https`) |
| `access_denied` | User cancelled consent or permissions not granted — try reconnecting |
| `invalid_client` | Wrong Client Secret — secrets expire, create a new one and update it in Settings |
| Token expired immediately | `expires_at` stored in UTC — verify server timezone matches |
| `Outlook connector has no access token` | Connector exists but OAuth failed — reconnect via the Reconnect button |
| `Invalid or expired OAuth state` | State cache expired (10 min TTL) — start OAuth flow again |
| Personal account not supported | Set Tenant ID to `common` in **Settings → Outlook Settings** |
