# Garad AI

Garad AI is a Laravel application with the React/Vite Chat Pro client kept as an
independent frontend package in `frontend/`.

## Project layout

- `app/`, `routes/`, `resources/`, `database/` — Laravel backend and its
  existing Laravel Vite assets.
- `frontend/src/`, `frontend/package.json`, `frontend/pnpm-lock.yaml` —
  React/Vite Chat Pro source and its separate dependency/tooling setup.
- Frontend media under `frontend/src/assets/` is stored directly in Git rather
  than Git LFS so clones do not depend on a machine-specific LFS filter or quota.
- `public/garad-ai-chat-pro/dist/` — generated Chat Pro assets. The Laravel
  `/agents` route reads this build's Vite manifest and injects its asset URLs
  alongside the authenticated API base URL and bootstrap data. This directory
  is generated and must not be committed.

The local XAMPP URL for the authenticated Agent interface is
`http://localhost/garad.ai/public/agents`. Keep this Laravel route; do not add a
physical `public/agents` directory, which would shadow it in Apache.

## Windows/XAMPP setup

Run commands from PowerShell. Start Apache and MySQL in the XAMPP Control Panel.
Install Composer and Node.js (Node 20 or newer) if they are not already
available.

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai'

# Preserve an existing local .env. Copy the template only for a new checkout.
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item '.env.example' '.env'
}

# Install Laravel dependencies. Keep the existing database and local .env.
composer install

# Install the Laravel application's existing npm dependencies.
npm ci

# Install the independent frontend using its checked-in pnpm lockfile.
corepack pnpm --dir frontend install --frozen-lockfile
```

For a new local environment only, set `APP_KEY` in `.env` with
`C:\xampp\php\php.exe artisan key:generate`. For this XAMPP folder, set
`APP_URL` to `http://localhost/garad.ai/public` and configure the existing
database connection values for your local database. Never copy example
credentials over an existing `.env`, and never use `migrate:fresh` against
existing data.

Build the React/Vite frontend from the repository root:

```powershell
npm run frontend:build
```

Or run the commands directly from the frontend:

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai\frontend'
npx tsc --noEmit
npm run build
```

The normal Laravel application assets remain a separate, existing Vite build:

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai'
npm run build
```

Open the application at `http://localhost/garad.ai/public/`. The Agent client
is at `http://localhost/garad.ai/public/agents`. Laravel serves the React
application shell and supplies the authenticated API URL and bootstrap data;
the client sends its API requests to that same Laravel application.

For frontend hot reload, set `GARAD_CHAT_FRONTEND_DEV_URL=http://localhost:8443`
in the existing Laravel `.env`. Start `npm run frontend:dev` in a second
PowerShell window, then load `/garad.ai/public/agents` through Apache. Remove
that override and run `npm run frontend:build` when returning to the production
build. Do not set `VITE_GARAD_API_URL` for the Laravel-integrated app; Laravel
injects the correct runtime API base URL. The optional frontend `.env.example`
documents settings for standalone frontend work.

## Verification

Run the focused Laravel feature tests for Agent access, sharing, workflow
approvals, and profiles:

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai'
C:\xampp\php\php.exe vendor\bin\pest tests\Feature\AgentWorkspaceAccessTest.php tests\Feature\AgentCollaborationSharingTest.php tests\Feature\AIAgentWorkflowApprovalTest.php tests\Feature\ProfileVisibilityAndWorkspaceMembershipTest.php
```

Run both frontend checks from the root:

```powershell
npm run frontend:typecheck
npm run frontend:build
```

Or run `npx tsc --noEmit` and `npm run build` inside `frontend/`.

## Deployment

Deploy from this repository root. Install backend and frontend dependencies
separately, build both asset sets, then deploy the application files and built
assets together:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
corepack pnpm --dir frontend install --frozen-lockfile
npm run frontend:build
```

Run only reviewed Laravel migrations with `php artisan migrate --force` when
your deployment requires them. The frontend build writes its manifest and
assets to `public/garad-ai-chat-pro/dist/`; Laravel consumes that manifest at
runtime. Do not commit `.env`, dependencies, uploaded files, database dumps,
or generated build output.
