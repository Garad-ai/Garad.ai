# Garad AI Artifact UX Report

## Artifact card
Every generated file (Word, PDF, Excel, PowerPoint, Markdown, Text) renders as a card under the assistant answer: type icon, filename, "<Type> � <size>", Open, Download, More. Cards come from the backend artifact object (`id, name, type, format, size, url, downloadUrl, previewUrl, createdAt`, plus `slideCount`). PowerPoint shows a "creating" placeholder card while the real presentation job is polled every 3 s (max 10 min).

## Right preview panel (`src/core/ArtifactPanel.tsx`)
- Open ? panel slides in on the right; the conversation stays visible (main area gets a right margin on desktop). Esc or ? closes.
- Preview: PDF/Word/Excel via same-origin iframe (`?preview=1`); Markdown rendered with the chat markdown renderer; TXT as monospace text; PPT/PPTX slides are fetched from Garad's authenticated artifact endpoint and rendered in the browser inside the panel.
- Footer: Download, Open in new tab (pdf/doc/xls), Saved to Library (opens Library), Share (copies the owner-only link).
- Mobile (=820 px): full-screen sheet (`width:100vw`).
- Presentation viewing stays inside Garad AI and does not expose or open an external presentation URL.

## Download
Authenticated route `�/artifacts/{id}/download`; verified magic bytes: PDF `%PDF`, DOCX/XLSX/PPTX `PK`.

## Library
All artifacts are stored in `ai_chat_pro_artifacts` (user_id, chat id, type, original name, mime, size, metadata, created_at) and listed by `/api/library` with kind: document, pdf, spreadsheet, presentation, generated (md/txt). Note: no project_id column exists on the table; project association is not persisted.

## Ownership
`ArtifactController` checks `Auth::id() === artifact->user_id`; library delete filters by user; presentation status checks `AiPresentation.user_id`. Tested with other users' ids: download/preview 403, delete 404.
