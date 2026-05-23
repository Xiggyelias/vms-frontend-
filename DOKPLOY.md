# Dokploy Deployment Guide (Frontend + Backend + DB)

## 1. Recommended App Type
- Use a **Compose Application** in Dokploy pointing to this repository root.
- Dokploy will load `docker-compose.yml`.

## 2. Environment Setup
- Create root `.env` from `.env.docker.example`.
- Set strong secrets:
  - `BACKEND_APP_KEY`
  - `BACKEND_DB_PASSWORD`
  - `MYSQL_ROOT_PASSWORD`
  - `REDIS_PASSWORD`
- Use your real domain in:
  - `FRONTEND_BASE_URL`
  - `FRONTEND_BACKEND_URL`
  - `BACKEND_APP_URL`

## 3. First Deployment
- Deploy stack.
- Open backend service terminal and run:
  - `php artisan migrate --force`
  - `php artisan system:preflight --strict`

## 4. DNS / Routing
- Public entrypoint should be `frontend` service.
- Frontend proxies `/backend` to backend container.

## 5. Production Safety Checks
- Ensure:
  - `BACKEND_APP_ENV=production`
  - `BACKEND_APP_DEBUG=false`
  - `BACKEND_SESSION_SECURE_COOKIE=true`
  - `FRONTEND_SESSION_SECURE=true`
  - `ALLOW_LEGACY_MUTATIONS=false`

## 6. Optional Managed Database
- If using managed MySQL/Redis:
  - Remove `mysql` and/or `redis` services from compose in your deployment branch.
  - Point backend env vars to managed endpoints.

## 7. Upgrade Procedure
- Deploy new release.
- Run:
  - `php artisan migrate --force`
  - `php artisan optimize:clear`
  - `php artisan system:preflight --strict`

