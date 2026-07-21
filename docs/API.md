# REST API Reference

Base URL: `{APP_URL}/api/v1`
All responses are JSON. Protected endpoints require a Bearer token.

## Response envelope

```json
{ "success": true, "message": "OK", "data": { ... } }
```

Errors:

```json
{ "success": false, "message": "Reason", "errors": { "field": ["..."] } }
```

List endpoints return pagination metadata:

```json
{ "success": true, "data": [ ... ], "meta": { "total": 42, "per_page": 15, "current_page": 1, "last_page": 3, "from": 1, "to": 15 } }
```

## Authentication

### `POST /auth/login` (public)
Body: `{ "email": "admin@school.test", "password": "Password123!" }`
Returns a Bearer token:

```json
{ "success": true, "message": "Login successful.",
  "data": { "token": "…", "token_type": "Bearer", "expires_in": 3600, "user": { … } } }
```

Send the token on subsequent requests:

```
Authorization: Bearer <token>
```

| Method | Endpoint            | Auth   | Description                     |
|--------|---------------------|--------|---------------------------------|
| POST   | `/auth/login`       | Public | Issue an access token           |
| POST   | `/auth/refresh`     | Public\* | Rotate a token (send Bearer)  |
| GET    | `/auth/me`          | Bearer | Current user + roles/permissions|
| POST   | `/auth/logout`      | Bearer | Revoke the current token        |

## Resources

All resource endpoints are permission-gated (same RBAC as the web app).

| Method | Endpoint                       | Permission        | Notes                          |
|--------|--------------------------------|-------------------|--------------------------------|
| GET    | `/students`                    | `students.view`   | `?search=&class_id=&status=&page=&per_page=` |
| GET    | `/students/{id}`               | `students.view`   | Full profile                   |
| POST   | `/students`                    | `students.create` | Create (201)                   |
| PUT    | `/students/{id}`               | `students.update` | Update                         |
| DELETE | `/students/{id}`               | `students.delete` | Soft delete                    |
| GET    | `/teachers`                    | `teachers.view`   | Teaching staff, paginated      |
| GET    | `/teachers/{id}`               | `teachers.view`   |                                |
| GET    | `/attendance`                  | `attendance.view` | `?class_id=&section_id=&date=` |
| POST   | `/attendance`                  | `attendance.mark` | Mark one student               |
| GET    | `/fees`                        | `fees.view`       | Invoices, paginated            |
| GET    | `/fees/student/{id}`           | `fees.view`       | Invoices for a student         |
| GET    | `/results/student/{id}`        | `exams.view`      | Report cards                   |
| GET    | `/library/books`               | `library.view`    | Catalogue, paginated           |
| GET    | `/reports/{type}`              | `reports.view`    | `attendance\|fees\|academic\|students\|staff` |

## Pagination, filtering, sorting

- `page` (default 1), `per_page` (default 15, max 100)
- `search` performs a grouped LIKE across key text columns
- Filters vary per resource (e.g. `class_id`, `section_id`, `status`)

## Status codes

`200` OK · `201` Created · `401` Unauthenticated · `403` Forbidden ·
`404` Not Found · `422` Validation error · `429` Rate limited.

## Rate limiting

API requests are throttled (default `100/60s`). Responses include
`X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.

## Example (cURL)

```bash
# 1. Login
TOKEN=$(curl -s -X POST {APP_URL}/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@school.test","password":"Password123!"}' | jq -r .data.token)

# 2. List students
curl -s {APP_URL}/api/v1/students?per_page=20 \
  -H "Authorization: Bearer $TOKEN"
```
