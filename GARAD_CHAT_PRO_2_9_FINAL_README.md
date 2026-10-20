# Garad Chat Pro 2.9 Global Complete

Date: 2026-09-03

This release is scoped to Garad AI Chat Pro and its direct runtime dependencies. It does not modify the admin dashboard design.

## Restored / fixed

- Real Chat Pro top header is visible again.
- Garad AI logo is shown in the real Chat Pro header.
- Guest Login and Sign Up buttons are restored.
- Authenticated Dashboard and profile controls are restored.
- Real configured model selector is restored to the Chat Pro header.
- Model list now supports models represented as enums/objects OR string plan keys.
- Low / Medium / High / Max reasoning modes remain connected to the request backend.
- Duplicate composer-level model selector was removed so there is one authoritative model control.
- Unified sidebar, Pinned / Projects / Chats, Library, Scheduled, Agents and Garad Code are retained.
- Existing AI Agent workflows continue through WorkflowEngine.
- PDF, DOCX, XLSX, PPTX, image and video tool routing is retained.
- Presentation async polling and video terminal-state handling from 2.8 are retained.
- Temporary Chat and edit-message backend support are retained.
- Chat Pro guest footer now uses Garad AI branding.

## Production installation

Back up the live project first. Extract the production package at the Garad AI project root, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
bash GARAD_CHAT_PRO_2_9_VERIFY.sh
```

Then hard-refresh the browser and purge Cloudflare/CDN cache if enabled.

## Important

Static source checks can verify syntax, wiring and package integrity. Live generation completion still depends on the production database, configured providers, API credentials, credits and queue/network health.
