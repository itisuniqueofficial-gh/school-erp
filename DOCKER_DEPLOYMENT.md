# Docker Deployment

The repo ships a production `Dockerfile` (PHP 8.3 + Apache, document root
`public/`) and `docker/entrypoint.sh`. Configuration is entirely via environment
variables — no `.env` is baked into the image.

## Build & run (single container)

```bash
docker build -t school-erp:latest .

docker run -d --name school-erp -p 8080:8000 \
  -e APP_ENV=production -e APP_DEBUG=false \
  -e APP_KEY="base64:..." \
  -e DB_HOST=db -e DB_PORT=3306 -e DB_DATABASE=school_erp \
  -e DB_USERNAME=erp -e DB_PASSWORD=secret \
  -e DB_CONNECT_RETRIES=10 \
  -v erp_uploads:/var/www/html/storage/uploads \
  school-erp:latest
```

Open `http://localhost:8080`. Leave `APP_URL` unset to auto-detect the host.
On boot the entrypoint binds Apache to `$PORT` (default 8000), ensures storage
dirs, generates `APP_KEY` if absent, and runs a safe `upgrade` (backup +
pending migrations only).

## Docker Compose

```yaml
services:
  db:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: school_erp
      MYSQL_USER: erp
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "-uerp", "-psecret"]
      interval: 5s
      timeout: 5s
      retries: 20

  app:
    build: .
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "8080:8000"
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: "base64:REPLACE_ME"
      DB_HOST: db
      DB_PORT: "3306"
      DB_DATABASE: school_erp
      DB_USERNAME: erp
      DB_PASSWORD: secret
      DB_CONNECT_RETRIES: "10"
      SESSION_SECURE_COOKIE: "false"
    volumes:
      - uploads:/var/www/html/storage/uploads

volumes:
  db_data:
  uploads:
```

```bash
docker compose up -d --build
```

## Persistence & upgrades

- Mount a volume at `/var/www/html/storage/uploads` so uploaded files survive
  redeploys. Never bake secrets or `.env` into the image.
- Upgrading = rebuild/redeploy the image; the entrypoint's `upgrade` applies only
  pending, additive migrations (no data loss). `migrate:fresh` is blocked in
  production.

## Health

`GET /health` returns 200 (healthy) / 503 (degraded); the image also declares a
Docker `HEALTHCHECK`. See `ENVIRONMENT_VARIABLES.md` for all variables and
`KOYEB_DEPLOYMENT.md` for managed-PaaS deployment.
