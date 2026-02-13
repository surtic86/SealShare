# SealShare

A simple, self-hosted file sharing solution built with Laravel. Upload files, get a shareable link, done. All files are encrypted at rest with AES-256-GCM.

## Features

- **File Uploading** — Drag & drop or browse to upload single/multiple files and folders with real-time progress
- **Shareable Links** — Each upload generates a unique link for recipients
- **End-to-End Encryption** — All files encrypted at rest using AES-256-GCM (chunked, streaming)
- **Password Protection** — Optionally protect shares with a password
- **Expiration** — Shares auto-expire after a configurable duration (1 hour to 30 days)
- **Download Limits** — Set a maximum number of downloads per share
- **ZIP Downloads** — Download all files in a share as a single ZIP archive
- **Auto-Cleanup** — Expired shares and files are automatically deleted (hourly)
- **Admin Dashboard** — View, manage, and delete all shares
- **Admin Settings** — Configure upload limits, storage quotas, branding, and more
- **Site Branding** — Custom logo, title, and description
- **System Password** — Optional global password gate to restrict upload access
- **User Authentication** — Login, registration, password reset, email verification
- **Two-Factor Authentication** — TOTP-based 2FA via Laravel Fortify
- **Dark Mode** — Dark themed UI with DaisyUI components
- **Setup Wizard** — First-run wizard to create the initial admin account

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Framework** | Laravel 12 |
| **Application Server** | FrankenPHP (via Laravel Octane) |
| **Frontend** | Livewire 4, Alpine.js, Tailwind CSS 4, DaisyUI 5, Mary UI |
| **Authentication** | Laravel Fortify |
| **Encryption** | Chunked AES-256-GCM with PBKDF2-SHA256 key derivation |
| **ZIP Streaming** | maennchen/zipstream-php |
| **Testing** | Pest 4 |
| **Code Style** | Laravel Pint |
| **Build Tool** | Vite |

## Installation — Development

### Docker (recommended)

```bash
# Build and start the dev container
docker compose -f docker-compose.dev.yml up -d --build

# View logs (including Vite output)
docker compose -f docker-compose.dev.yml logs -f
```

The app is available at `http://localhost:8000` with Vite HMR on port `5173`.


## Installation — Production

### Docker (recommended)

```bash
mkdir sealshare && cd sealshare
curl -O https://raw.githubusercontent.com/surtic86/SealShare/main/docker-compose.example.yml
cp docker-compose.example.yml docker-compose.yml

# Generate an app key and paste it into docker-compose.yml
docker run --rm ghcr.io/surtic86/sealshare:latest php artisan key:generate --show

# Edit docker-compose.yml — set APP_KEY, APP_URL, and SERVER_NAME
# Then start:
docker compose up -d
```

Migrations run automatically on startup. Open your configured domain — the Setup Wizard will create the first admin account.

**Key environment variables:**

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | Laravel encryption key |
| `APP_URL` | Yes | Full URL (e.g. `https://share.example.com`) |
| `SERVER_NAME` | Yes | Domain for auto-TLS (e.g. `share.example.com`) |

**Volumes:**

| Volume | Path | Purpose |
|--------|------|---------|
| `sealshare_storage` | `/app/storage/app` | Encrypted uploaded files |
| `sealshare_database` | `/app/database` | SQLite database |
| `caddy_data` | `/data` | TLS certificates |
| `caddy_config` | `/config` | Caddy configuration |

### Manual (without Docker)

```bash
git clone https://github.com/surtic86/SealShare.git
cd SealShare

composer install --no-dev --optimize-autoloader
npm install && npm run build

cp .env.example .env
php artisan key:generate

# Edit .env — set APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain.com

touch database/database.sqlite
php artisan migrate --force
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Start with Octane:

```bash
php artisan octane:frankenphp --host=0.0.0.0 --port=80
```

Or point your web server (Nginx/Apache) to the `public/` directory for a traditional PHP-FPM setup.

Add the scheduler to your crontab:

```bash
* * * * * cd /path-to-sealshare && php artisan schedule:run >> /dev/null 2>&1
```

## License

This project is open-source software licensed under the [MIT License](LICENSE).
