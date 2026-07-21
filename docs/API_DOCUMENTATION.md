# API_DOCUMENTATION.md — REST API v1

Base URL: `{APP_URL}/api/v1` · Format: JSON · Auth: Bearer token.

## Conventions

**Success envelope**
```json
{ "success": true, "message": "OK", "data": { } }
```
**Error envelope**
```json
{ "success": false, "message": "Reason", "errors": { "field": ["msg"] } }
```
**Paginated list**
```json
{ "success": true, "data": [ ],
  "meta": { "total": 42, "per_page": 15, "current_page": 1, "last_page": 3, "from": 1, "to": 15 } }
```

## Authentication flow

1. `POST /auth/login` with credentials → receive a Bearer token.
2. Send `Authorization: Bearer <token>` on every protected request.
3. `POST /auth/logout` revokes the token; `POST /auth/refresh` rotates it.

### POST `/auth/login`  *(public)*
Request:
```json
{ "email": "admin@school.test", "password": "Password123!" }
```
Response `200`:
```json
{ "success": true, "message": "Login successful.",
  "data": { "token": "8f3c…", "token_type": "Bearer", "expires_in": 3600,
            "user": { "id": 1, "name": "Super Admin", "email": "admin@school.test", "roles": ["super_admin"] } } }
```
Errors: `401` invalid credentials, `422` validation.

### GET `/auth/me` · POST `/auth/logout` · POST `/auth/refresh`
Require a valid Bearer token. `me` returns the current user with roles & permissions.

## Endpoints

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/students` | `students.view` | Paginated list. Query: `search, class_id, section_id, status, page, per_page` |
| GET | `/students/{id}` | `students.view` | Full profile |
| POST | `/students` | `students.create` | Create → `201` |
| PUT | `/students/{id}` | `students.update` | Update |
| DELETE | `/students/{id}` | `students.delete` | Soft delete |
| GET | `/teachers` | `teachers.view` | Paginated teaching staff |
| GET | `/teachers/{id}` | `teachers.view` | Profile |
| GET | `/attendance` | `attendance.view` | Query: `class_id, section_id, date` |
| POST | `/attendance` | `attendance.mark` | Mark one student's attendance |
| GET | `/fees` | `fees.view` | Paginated invoices |
| GET | `/fees/student/{id}` | `fees.view` | Invoices for a student |
| GET | `/results/student/{id}` | `exams.view` | Report cards |
| GET | `/library/books` | `library.view` | Paginated catalogue. Query: `search` |
| GET | `/reports/{type}` | `reports.view` | `attendance\|fees\|academic\|students\|staff` |

### Example — create a student
```bash
curl -X POST {APP_URL}/api/v1/students \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"first_name":"Asha","last_name":"Rao","gender":"female","class_id":3}'
```
Response `201`:
```json
{ "success": true, "message": "Student created.",
  "data": { "id": 51, "admission_no": "ADM-2026-0051", "first_name": "Asha", ... } }
```

### Example — mark attendance
```bash
curl -X POST {APP_URL}/api/v1/attendance \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"student_id":51,"class_id":3,"section_id":5,"date":"2026-07-22","status":"present"}'
```

## Pagination, filtering & sorting
- `page` (default `1`), `per_page` (default `15`, max `100`).
- `search` runs a grouped `LIKE` across key text columns.
- Resource-specific filters (`class_id`, `section_id`, `status`, …).
- Lists are ordered newest-first by default.

## Error codes
| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 401 | Missing/invalid token |
| 403 | Authenticated but lacks permission |
| 404 | Resource not found |
| 422 | Validation failed (see `errors`) |
| 429 | Rate limit exceeded (`Retry-After` header) |
| 503 | Maintenance mode |

## Rate limiting
Default `100` requests / `60s` per IP+route. Responses carry
`X-RateLimit-Limit` and `X-RateLimit-Remaining`.

## Security
- Tokens are stored hashed (SHA-256) and expire (`API_TOKEN_TTL`, default 3600s).
- All queries use prepared statements; all output is escaped.
- Permissions mirror the web app's RBAC exactly.
