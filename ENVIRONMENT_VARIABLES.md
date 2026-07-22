# Environment Variables — School ERP

All configuration is read from the process environment (Koyeb env vars) or a local
`.env` file. Real environment variables take precedence and the app works with **no
`.env` file** present (see `core/Support/Env.php`). Never commit secrets.

## Application
| Key | Default | Description |
|-----|---------|-------------|
| `APP_NAME` | `School ERP` | Fallback name (branding is also DB-configurable in Admin → ERP Configuration) |
| `APP_ENV` | `production` | `local` \| `production` |
| `APP_DEBUG` | `false` | Show detailed errors. **Keep `false` in production.** |
| `APP_URL` | `http://localhost:8000` | Base URL for links & cookies |
| `APP_KEY` | *(none)* | **Secret.** `base64:...` AES-256 key for encryption |
| `APP_TIMEZONE` | `UTC` | e.g. `Asia/Kolkata` |
| `APP_LOCALE` | `en` | Default language |
| `APP_MAINTENANCE` | `false` | Maintenance mode toggle |

## Database
| Key | Default | Description |
|-----|---------|-------------|
| `DB_CONNECTION` | `mysql` | Driver |
| `DB_HOST` | `127.0.0.1` | Host |
| `DB_PORT` | `3306` | Port |
| `DB_DATABASE` | `school_erp` | Schema name |
| `DB_USERNAME` | `root` | **Secret** |
| `DB_PASSWORD` | *(empty)* | **Secret** |
| `DB_CHARSET` | `utf8mb4` | |
| `DB_COLLATION` | `utf8mb4_unicode_ci` | |
| `DB_CONNECT_RETRIES` | `1` | Connect attempts before failing (set higher for cold DBs) |

## Session & Security
| Key | Default | Description |
|-----|---------|-------------|
| `SESSION_DRIVER` | `file` | |
| `SESSION_LIFETIME` | `120` | Minutes |
| `SESSION_SECURE_COOKIE` | `false` | Set `true` behind HTTPS |
| `SESSION_SAME_SITE` | `Lax` | |
| `CSRF_TOKEN_NAME` | `_token` | |
| `BCRYPT_COST` | `12` | Password hashing cost |
| `RATE_LIMIT_MAX` | `60` | Requests per window |
| `RATE_LIMIT_WINDOW` | `60` | Seconds |

## Uploads & Logging
| Key | Default | Description |
|-----|---------|-------------|
| `UPLOAD_LIMIT` | `10M` | Max upload size (also `app.upload_max_size`) |
| `LOG_LEVEL` | `debug`/`warning` | Minimum log level |

## Mail (SMTP)
| Key | Example | Description |
|-----|---------|-------------|
| `MAIL_MAILER` | `smtp` | |
| `MAIL_HOST` | `smtp.mailtrap.io` / `smtp.gmail.com` / `smtp.sendgrid.net` | |
| `MAIL_PORT` | `587` | |
| `MAIL_USERNAME` | *(secret)* | |
| `MAIL_PASSWORD` | *(secret)* | |
| `MAIL_FROM_ADDRESS` | `no-reply@school.test` | |
| `MAIL_FROM_NAME` | `${APP_NAME}` | |

## Payment gateways
Payment credentials are configured & **encrypted at rest** in Admin → Settings →
Payment Gateways (stored in the DB, not env). Optionally seed via env:

| Key | Description |
|-----|-------------|
| `PAYMENT_DEFAULT_GATEWAY` | `razorpay` \| `phonepe` \| `mobikwik` |

> Gateway API keys/secrets are managed in the admin UI and encrypted with `APP_KEY`.
> Do not place live gateway secrets in plain environment variables.
