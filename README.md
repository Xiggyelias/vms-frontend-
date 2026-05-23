# Vehicle Registration System (VRS)

Africa University — Vehicle Registration System built on a legacy PHP frontend backed by a Laravel API.

## Architecture

| Layer | Technology | Path |
|-------|-----------|------|
| Frontend | PHP 8.2 (Apache-served pages) | `frontend/` |
| Backend API | Laravel 10 + Eloquent | `backend/` |
| Database | MySQL 8+ | shared instance |
| Containerisation | Docker + Dokploy | `docker-compose.yml` |

---

## Local Development (XAMPP)

### Prerequisites

- XAMPP 8.2+ (Apache + MySQL + PHP 8.2)
- Composer

### First-time setup

```bash
# 1. Install Laravel dependencies
cd backend
composer install

# 2. Copy env and generate key
cp .env.example .env
php artisan key:generate

# 3. Run database migrations
php artisan migrate --force
```

### Starting the system

Start Apache and MySQL via the XAMPP Control Panel (or the batch files):

```
C:\xampp\apache_start.bat
C:\xampp\mysql_start.bat
```

The VirtualHost in `C:\xampp\apache\conf\extra\httpd-vhosts.conf` maps:

| URL | Serves |
|-----|--------|
| `http://localhost/frontend/frontend/` | PHP frontend pages |
| `http://localhost/backend/` | Laravel API (public/) |

No separate `php artisan serve` is needed — Apache handles both.

### Environment

The backend reads from `backend/.env`. Key variables:

```
APP_URL=http://localhost/backend
DB_DATABASE=vehicleregistrationsystem
ALLOWED_GOOGLE_DOMAIN=africau.edu
GOOGLE_CLIENT_ID=          # set your OAuth credentials
GOOGLE_CLIENT_SECRET=
```

---

## Security Highlights

- **Google OAuth** restricted to `@africau.edu` domain only
- **Session-based authentication** with CSRF protection on all state-changing routes
- **Rate limiters** on login (5/15 min), mutations (120/min), OAuth (30/min), search (30/min)
- **IDOR protection** — users can only view their own vehicles
- **Security headers** — CSP, HSTS (production), X-Frame-Options, Referrer-Policy
- **Input validation** via Laravel FormRequest on every API endpoint
- **Parameterised queries** throughout (no raw string interpolation in SQL)
- **Password minimum 12 characters** enforced on both frontend and backend
- **Notifications scoped per user** — no cross-user data leakage
- **Admin self-deletion blocked** server-side
- **Suspended accounts** blocked at login and Google OAuth callback
- **Credentials never committed** — `.env` contains no live secrets; see `.env.example`

---

## Production Deployment (Docker / Dokploy)

### Quick start with Docker

```bash
docker compose up --build -d
```

| Service | URL |
|---------|-----|
| Frontend | `http://localhost:8080` |
| Backend API | `http://localhost:8080/backend` |
| MySQL | Internal network only |

### Dokploy deployment

1. Create a **Compose Application** in Dokploy and point it to this repository.
2. Copy `.env.production.example` → project `.env` and fill in **all** `[REQUIRED]` values.
3. Dokploy detects `docker-compose.yml` automatically.
4. Set strong secrets for `MYSQL_ROOT_PASSWORD`, `APP_KEY`, `DB_PASSWORD` in Dokploy.
5. Deploy.
6. Run post-deploy commands inside the backend container:

```bash
php artisan migrate --force
php artisan optimize
```

### Critical production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `SESSION_DRIVER=database` or `redis` (not `file`)
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS only)
- [ ] `GOOGLE_CLIENT_ID` + `GOOGLE_CLIENT_SECRET` rotated and set
- [ ] `DB_USERNAME` / `DB_PASSWORD` — use a least-privilege DB user, not `root`
- [ ] `TRUSTED_PROXIES` set to your load-balancer IP(s)
- [ ] `CORS_ALLOWED_ORIGINS` restricted to your domain(s)
- [ ] `MAIL_*` configured for real transactional email
- [ ] Renewal reminder cron running: `php artisan schedule:run` every minute

See `backend/.env.production.example` for the full annotated checklist.

---

## Running Tests

```bash
cd backend
php artisan test
```

Tests use SQLite `:memory:` and never touch the real database.

---

## Scheduled Jobs

| Command | Schedule | Purpose |
|---------|----------|---------|
| `vehicles:send-renewal-reminders` | Daily 07:00 | Email + in-app alerts for expiring registrations; marks expired vehicles inactive |

In production, add to crontab:
```
* * * * * php /path/to/backend/artisan schedule:run >> /dev/null 2>&1
```
