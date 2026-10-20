# Garad AI

Garad AI is one private repository containing the Laravel application and the
React/Vite Chat Pro frontend. Backend and frontend dependencies are installed
separately; Laravel serves the built Chat Pro app and provides its authenticated
API base URL.

> This repository contains application source and static assets, not a
> development database, user records, credentials, or prebuilt dependency
> folders. Each engineer must create a local `.env` and development database.

## Project structure

| Path | Purpose |
| --- | --- |
| `app/`, `routes/`, `resources/`, `database/` | Laravel backend, routes, migrations, and application seeders |
| `frontend/src/` | React/Vite Chat Pro source |
| `frontend/package.json`, `frontend/pnpm-lock.yaml` | Separate frontend dependency manifest and pinned lockfile |
| `frontend/vite.config.ts`, `frontend/tsconfig.json` | Frontend build and TypeScript configuration |
| `public/garad-ai-chat-pro/dist/` | Generated Chat Pro build; create it locally and do not commit it |
| `tests/` | Laravel feature and unit tests |
| `composer.json`, `composer.lock` | Backend PHP dependencies |
| root `package.json`, `package-lock.json` | Existing Laravel asset-build tooling and convenience commands |

Frontend media in `frontend/src/assets/` is tracked directly in Git. It does not
require Git LFS or a separate asset download.

The Laravel route `/agents` serves Chat Pro from its generated build and
injects the authenticated API URL. Do not create a physical
`public/agents` directory; it would shadow that route.

## Requirements

- Windows 10/11 and Git.
- XAMPP with 64-bit PHP 8.2 or newer and MySQL/MariaDB.
- PHP extensions used by this application: `bcmath`, `ctype`, `curl`, `dom`,
  `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`,
  `sodium`, `tokenizer`, `xml`, and `zip`. Enable them in the PHP configuration
  used by both XAMPP Apache and CLI. The Laravel test suite also requires
  `pdo_sqlite`. Composer will report any additional platform requirement from
  the locked dependency set.
- Composer 2.
- Node.js 22.12 or newer (or 20.19 or newer) and Corepack. These versions meet
  the Vite 8 runtime requirement. Use a current Node.js LTS release.
- A code editor and a local GitHub authorization method (Git Credential Manager
  or SSH) to clone this private repository.

Check the PHP used by the terminal before installing dependencies:

```powershell
C:\xampp\php\php.exe -v
C:\xampp\php\php.exe -m
composer --version
node --version
npm --version
corepack pnpm --version
```

If `php` or `composer` is not on `PATH`, use the full XAMPP PHP path shown
above and install/configure Composer for Windows.

## Clone and install

Run these commands in PowerShell. GitHub access is required for the private
repository; do not put a password or access token in the clone URL.

```powershell
Set-Location 'C:\xampp\htdocs'
git clone https://github.com/Garad-ai/Garad.ai.git garad.ai
Set-Location 'C:\xampp\htdocs\garad.ai'
```

Install the backend dependencies and the two separate JavaScript dependency
sets:

```powershell
composer install
npm ci
corepack pnpm --dir frontend install --frozen-lockfile
```

`composer install` uses `composer.lock`; `npm ci` uses the root
`package-lock.json`; and pnpm uses `frontend/pnpm-lock.yaml`. Do not run
`npm install` in `frontend/` or mix its dependencies into the Laravel root.

## Configure a new local environment

Create `.env` only if the checkout does not already have one. Never replace an
existing local `.env` with the example:

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai'
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item '.env.example' '.env'
}
```

Edit `.env` locally. For the default XAMPP Apache URL, configure:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/garad.ai/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garad_ai_dev
DB_USERNAME=root
DB_PASSWORD=
```

Use the database username/password configured for your local MySQL installation;
the example's blank password is only a local XAMPP default and must not be used
as a production configuration.

Generate an application key **only for a new local installation whose `.env`
has an empty `APP_KEY`**:

```powershell
C:\xampp\php\php.exe artisan key:generate
```

Do not generate a new key for an existing installation: changing `APP_KEY`
can make existing encrypted application data unreadable.

## Create and migrate a development database

1. Start **MySQL** from the XAMPP Control Panel. Apache can be started now or
   after setup.
2. Open `http://localhost/phpmyadmin/`, select the SQL tab, and create a
   dedicated empty development database:

   ```sql
   CREATE DATABASE garad_ai_dev
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_unicode_ci;
   ```

3. Make `.env`'s `DB_*` values match that database.
4. From the repository root, run the normal migrations:

   ```powershell
   C:\xampp\php\php.exe artisan migrate
   ```

Run migrations only against the new development database. Never use
`migrate:fresh`, `db:wipe`, or a production/live database as part of setup.

### Optional application seed data

There is no sanitized demo-user or customer-data fixture in this repository.
The default `DatabaseSeeder` initializes application catalog data and
permissions; it does not create a documented demo login. On a newly created,
isolated development database only, you may inspect the seeders and run:

```powershell
C:\xampp\php\php.exe artisan db:seed
```

Do not run this against an existing or shared database without reviewing its
effects first. It is safe to skip seeding during initial setup; create a local
account through the application's supported installation or registration flow.

## Build and run

Build Chat Pro and Laravel's existing asset bundle separately:

```powershell
# React/Vite Chat Pro; output is public/garad-ai-chat-pro/dist/
npm run frontend:build

# Existing Laravel Vite assets
npm run build
```

For the standard XAMPP URL, start Apache in the XAMPP Control Panel and open:

- Application: `http://localhost/garad.ai/public/`
- Chat Pro: `http://localhost/garad.ai/public/agents`

The Apache configuration must allow `.htaccess` overrides and `mod_rewrite`.
Keep the Laravel `public/` directory as the web entry point. For a local
`artisan serve` workflow instead, set `APP_URL=http://127.0.0.1:8000`, then run:

```powershell
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/`. Do not run Apache and `artisan serve` on the same
port.

### Frontend development server (optional)

For frontend hot reload, keep Laravel available through Apache and add this
local-only setting to `.env`:

```dotenv
GARAD_CHAT_FRONTEND_DEV_URL=http://localhost:5173
```

Then start the Vite server in a second PowerShell window:

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai'
npm run frontend:dev
```

Remove the override and run `npm run frontend:build` when returning to the
normal built-asset flow. For Laravel-integrated Chat Pro, Laravel injects the
API URL; do not set `VITE_GARAD_API_URL` to a production address.

## Tests and verification

The focused tests below cover Agent access, sharing/mentions, workflow
approvals, and profile visibility. PHPUnit configures its test database as
in-memory SQLite; these tests should not use the MySQL development database.

```powershell
Set-Location 'C:\xampp\htdocs\garad.ai'
C:\xampp\php\php.exe vendor\bin\pest `
  tests\Feature\AgentWorkspaceAccessTest.php `
  tests\Feature\AgentCollaborationSharingTest.php `
  tests\Feature\AIAgentWorkflowApprovalTest.php `
  tests\Feature\ProfileVisibilityAndWorkspaceMembershipTest.php `
  tests\Feature\AIAgentWorkflowStateMigrationTest.php
```

Run the frontend type check and production build from the repository root:

```powershell
npm run frontend:typecheck
npm run frontend:build
```

To run the complete Laravel suite, use `C:\xampp\php\php.exe
vendor\bin\pest`. Check `composer.json` for additional Composer scripts.

## External credentials and integrations

No production credentials are included in the repository. The core local
application, migrations, and frontend build can be prepared without provider
credentials, but features that contact external providers require the
appropriate engineer-owned development credentials. Depending on the feature,
these may include:

- AI providers such as OpenAI (`OPENAI_API_KEY`), Gemini
  (`GEMINI_API_KEY`), or xAI.
- OAuth integrations such as Google, Microsoft, or other configured services.
- Payment gateways, mail delivery, object storage (AWS/S3), and realtime
  broadcasting (Pusher/Ably).

Configure only the credentials needed for the feature being developed in the
local `.env` or the provider's documented secure configuration. Never commit
secrets, ask teammates to share personal tokens, or enable real payment
processing in local development. Without the corresponding credentials, those
external features will not complete live requests.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Composer reports a PHP/platform error | Confirm `C:\xampp\php\php.exe -v`, enable the required PHP extensions, and ensure the terminal and Apache use compatible PHP versions. |
| `could not find driver` or MySQL connection refused | Enable `pdo_mysql`, start MySQL in XAMPP, and check `DB_HOST`, `DB_PORT`, `DB_DATABASE`, and local credentials in `.env`. |
| `No application encryption key has been specified` | For a new checkout only, set an empty `APP_KEY` and run `C:\xampp\php\php.exe artisan key:generate`. |
| HTTP 404, Apache rewrite errors, or `/agents` shows a directory | Enable `mod_rewrite` and `.htaccess` overrides; use `/garad.ai/public/agents`; do not create `public/agents`. |
| Chat Pro CSS/JS returns 404 or the Laravel route reports a missing manifest | Run `npm run frontend:build` from the root and confirm `public/garad-ai-chat-pro/dist/.vite/manifest.json` exists. Do not commit the generated `dist/` directory. |
| Login/API request returns 419 | Check that the browser uses the same host and scheme as `APP_URL`, that Laravel sessions/cookies work, and that the page is served by Laravel rather than a standalone Vite URL. |
| API redirects to login or returns 401 | This is expected for unauthenticated protected API calls. Sign in through Laravel and retry the request. |
| An AI/OAuth/payment feature reports missing configuration | Set up the provider's development credentials locally; the feature cannot reach an external provider without them. |
| Vite reports an unsupported Node version | Install Node 22.12+ or 20.19+, reopen PowerShell, and rerun the frozen-lockfile pnpm install. |

## Safe development practices

- Keep `.env`, credentials, database exports, uploads, logs, caches,
  `vendor/`, `node_modules/`, and generated build output out of Git.
- Do not copy production data into local development. Use synthetic,
  anonymized data only.
- Do not run destructive database commands against an existing database.
- Keep Laravel/Composer and frontend/pnpm dependencies separate.
- Review `git status` and staged files before every commit.

## Clean-install verification status

The repository's source tree and frontend build have been validated in the
maintainer checkout, and focused Laravel tests have been run there. A fresh
Windows/XAMPP installation against a newly created empty database has **not**
been verified in this environment: Composer is not available on its `PATH`,
and the existing local database and `.env` were deliberately left untouched.
Treat a clean install as unverified until a teammate follows the setup above
with Composer installed and confirms migrations and the application boot on a
new local development database.
