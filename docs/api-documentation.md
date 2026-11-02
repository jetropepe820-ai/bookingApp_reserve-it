# REST API v1

Base URL is the app origin plus `/api/v1`. Send and receive JSON. Protected endpoints require `Authorization: Bearer <token>`. Tokens expire after 30 days. Responses have `success`, optional `message`, and `data`. Statuses: 201 create, 401 unauthenticated, 403 forbidden, 404 missing, 409 slot conflict, 422 invalid input.

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| POST | `/auth/register` | Public | `{name,email,password,password_confirmation}`; creates customer and returns token |
| POST | `/auth/login` | Public | `{email,password}`; returns token |
| POST | `/auth/logout` | Token | Revokes current bearer token |
| GET | `/courts?search=&sport=` | Public | Lists active courts |
| GET | `/courts/{id}/availability?date=YYYY-MM-DD` | Public | Lists active bookings and maintenance blocks for date |
| GET | `/bookings?status=&date=` | Token | Lists own reservations; admin sees all |
| POST | `/bookings` | Token | `{court_id,start_at,end_at}` creates pending reservation |
| GET | `/bookings/{id}` | Owner/admin | Booking detail |
| PATCH | `/bookings/{id}` | Admin | `{status,admin_note}` status decision |
| POST | `/bookings/{id}/cancel` | Owner/admin | Cancels eligible future reservation |
| GET | `/admin/courts` | Admin | Lists all courts |
| POST | `/admin/courts` | Admin | Creates a court; accepts JSON or multipart photo upload |
| PUT/POST | `/admin/courts/{id}` | Admin | Updates a court |
| DELETE | `/admin/courts/{id}/deactivate` | Admin | Deactivates court |
| POST | `/admin/maintenance` | Admin | `{court_id,start_at,end_at,reason}` creates block |
| DELETE | `/admin/maintenance/{id}` | Admin | Removes block |

Example booking request:

```json
{"court_id":1,"start_at":"2026-11-05 18:00:00","end_at":"2026-11-05 20:00:00"}
```

Availability is checked again in the database transaction when the booking is submitted; a browser-side availability result is not a reservation hold.
