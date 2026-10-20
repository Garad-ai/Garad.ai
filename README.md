<p align="center">
  <img src="frontend/src/assets/garad-ai-wordmark.png" alt="Garad AI" width="320">
</p>

# Garad AI

Garad AI is one private repository containing the Laravel application and the
React/Vite Chat Pro frontend. Backend and frontend dependencies are installed
separately; Laravel serves the built Chat Pro app and provides its authenticated
API base URL.

> This repository contains application source and static assets, not a
> development database, user records, credentials, or prebuilt dependency
> folders. Each engineer must create a local `.env` and development database.

## Technology stack

| Area | Technology |
| --- | --- |
| Backend framework | Laravel 10 (`laravel/framework` `^10.0`) |
| Backend language | PHP `^8.2` |
| Frontend | React 19 with Vite 8 |
| Frontend language | TypeScript 5 |
| Database | MySQL or MariaDB; SQLite is used by the automated tests |
| Backend dependencies | Composer, pinned by `composer.lock` |
| Frontend dependencies | pnpm, pinned by `frontend/pnpm-lock.yaml` |
| Laravel asset tooling | npm, pinned by the root `package-lock.json` |

## Repository structure

```text
.
├── app/                 Laravel application, extensions, services, models
├── bootstrap/            Laravel bootstrap and cache directory
├── config/               Laravel and integration configuration
├── database/
│   ├── migrations/       Database schema migrations
│   └── seeders/          Application catalog and permission seeders
├── docs/                 Project and engineering documentation
├── frontend/
│   ├── src/              React/TypeScript Chat Pro source and assets
│   ├── package.json      Frontend dependency manifest
│   ├── pnpm-lock.yaml    Pinned frontend dependency tree
│   ├── tsconfig.json     TypeScript configuration
│   └── vite.config.ts    Frontend build configuration
├── lang/                 Laravel translations
├── packages/             Local Composer packages
├── public/               Laravel web root and required runtime assets
├── resources/
│   ├── dev_tools/        Static catalog SQL used by application setup
│   └── views/            Laravel Blade templates and legacy assets
├── routes/               Web, API, panel, and webhook routes
├── scripts/              Project maintenance scripts
├── tests/                Laravel feature and unit tests
├── .env.example          Sanitized local environment template
├── artisan               Laravel command-line entry point
├── composer.json         Backend dependencies and commands
├── composer.lock         Pinned backend dependency tree
├── package.json          Root asset tooling and convenience scripts
├── package-lock.json     Pinned root npm dependency tree
└── README.md             Setup and development guide
```

Frontend media in `frontend/src/assets/` is tracked directly in Git. It does not
require Git LFS or a separate asset download.

The Laravel route `/agents` serves Chat Pro from its generated build and
injects the authenticated API URL. Do not create a physical
`public/agents` directory; it would shadow that route.

## Local data and secrets

The actual local `.env`, database exports, private uploads, logs, caches,
dependency directories, and generated builds are intentionally excluded from
Git. They may contain application keys, provider credentials, user records,
conversation content, or private documents. Do not add these files to a commit
or upload them to GitHub. Use `.env.example` to create a new, private local
configuration and use migrations to build a fresh development schema. Populate
it only with synthetic or explicitly anonymized data. Obtain any needed
development credentials through the team-approved secrets manager.

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
# Laravel package discovery needs the writable view/cache directories first.
$directories = @(
    'storage\app\private',
    'storage\app\public',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)
foreach ($directory in $directories) {
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
}

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

### Import an approved sanitized snapshot (optional)

This repository does not contain a database snapshot. If the team separately
provides an approved, sanitized development export, create a new empty database
first and confirm its name before importing. In phpMyAdmin, select that
development database, choose **Import**, and select the sanitized `.sql` file.
Alternatively, from Command Prompt (not PowerShell), use the MySQL client:

```bat
"C:\xampp\mysql\bin\mysql.exe" --user=root --password garad_ai_dev < "C:\secure\garad_ai_dev_sanitized.sql"
```

Replace the database and file paths with the verified development values. The
client prompts for the local database password. Never import a live database,
user-data dump, or unknown snapshot into an existing/shared database.

## Storage, cache, and uploads

Laravel needs writable local directories for compiled views, file sessions,
logs, cache data, and local uploads. From the repository root, create any
missing directories without replacing existing contents:

```powershell
$directories = @(
    'bootstrap\cache',
    'storage\app\private',
    'storage\app\public',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)
foreach ($directory in $directories) {
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
}
```

Ensure the Windows account running Apache/PHP can write to `storage/` and
`bootstrap/cache/`. Do not grant broad write access to the whole repository.
If the app uses Laravel's public disk, create the public storage link once:

```powershell
C:\xampp\php\php.exe artisan storage:link
```

If `public\storage` already exists, inspect it first; do not overwrite an
existing directory or link. Uploaded files and local storage contents are not
part of the repository and should be recreated locally with synthetic data.

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
No demo password or preloaded customer data is provided.

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
- Chat Pro chat: `http://localhost/garad.ai/public/chat`
- Chat Pro: `http://localhost/garad.ai/public/agents`

The Apache configuration must allow `.htaccess` overrides and `mod_rewrite`.
Keep the Laravel `public/` directory as the web entry point. For a local
`artisan serve` workflow instead, set `APP_URL=http://127.0.0.1:8000`, then run:

```powershell
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/`. Do not run Apache and `artisan serve` on the same
port.

On a fresh checkout, the application redirects to `/license` until an
authorized Garad AI/Liquid Themes license is activated. Use the license
assigned to your development installation; activation requires access to the
license provider. The status is stored in ignored local storage, not in Git.
Do not copy a teammate's local license state or add a license key to `.env`.

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

- AI providers: OpenAI (`OPENAI_API_KEY`, `OPENAI_ORGANIZATION`) and Gemini
  (`GEMINI_API_KEY`). AI model/provider settings may also be stored in the
  application's local database. The current xAI config reads the OpenAI key
  variable.
- Social sign-in and OAuth: GitHub (`GITHUB_CLIENT_ID`,
  `GITHUB_CLIENT_SECRET`), Google (`GOOGLE_CLIENT_ID`,
  `GOOGLE_CLIENT_SECRET`), Facebook (`FACEBOOK_CLIENT_ID`,
  `FACEBOOK_CLIENT_SECRET`), Apple (`APPLE_BUNDLE_ID`), and reCAPTCHA
  (`RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`).
- Email: local logging is the default. Mailgun uses `MAILGUN_DOMAIN` and
  `MAILGUN_SECRET`; Postmark uses `POSTMARK_TOKEN`; SMTP requires the `MAIL_*`
  settings.
- Object storage: S3-compatible storage uses `AWS_ACCESS_KEY_ID`,
  `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, and `AWS_BUCKET`.
- Realtime: Pusher uses `PUSHER_APP_ID`, `PUSHER_APP_KEY`,
  `PUSHER_APP_SECRET`, and related `PUSHER_*` host/cluster settings. Ably or
  other connector configuration may instead be stored in the local app.
- Stripe uses `STRIPE_KEY`, `STRIPE_SECRET`, and `STRIPE_WEBHOOK_SECRET`.
  PayPal sandbox uses `PAYPAL_SANDBOX_CLIENT_ID` and
  `PAYPAL_SANDBOX_CLIENT_SECRET`. Other payment providers and social connectors
  may use credentials saved in the application's settings or connector tables,
  rather than `.env`.

Configure only the credentials needed for the feature being developed in the
local `.env` or the provider's documented secure configuration. Never commit
secrets, ask teammates to share personal tokens, or enable real payment
processing in local development. Obtain individual development/sandbox
credentials through the team-approved secrets manager or directly from each
provider's developer console. Share access through that approved system, never
through Git, source files, chat, or a production `.env`. Without the
corresponding credentials, provider-backed live requests, OAuth callbacks,
delivery, storage, or realtime features cannot be fully exercised.
In addition, the application license must be activated through `/license`
before the protected application routes leave the installer screen.

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

A detached clean worktree was validated on Windows with PHP 8.2.12. Composer
dependencies installed from `composer.lock` after creating Laravel's required
runtime directories; root `npm ci` and the frontend frozen-lockfile install
passed; the TypeScript check and both production builds passed; and all
migrations completed against a new, disposable SQLite database. The focused
Agent, sharing/mentions, workflow approval/state, and profile tests passed
(24 tests, 264 assertions; one PHPUnit deprecation notice). The test base class
disables only the external license-status middleware, so automated tests do
not require a private domain license.

The existing MySQL database and `.env` were not used or changed. A fresh
XAMPP/MySQL migration run has not been performed. HTTP requests to `/agents`
and `/chat` returned the license activation screen because no authorized
development license was supplied; the React application itself and
authenticated/provider-backed API calls therefore still need verification
after license activation and sign-in. External provider credentials are
optional for setup but required to exercise their respective integrations.
