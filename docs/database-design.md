# Database design

```mermaid
erDiagram
  USERS ||--o{ BOOKINGS : places
  USERS ||--o{ API_TOKENS : owns
  COURTS ||--o{ BOOKINGS : receives
  COURTS ||--o{ MAINTENANCE_BLOCKS : blocks
  USERS { bigint id PK string name string email UK string password_hash string role boolean is_active }
  COURTS { bigint id PK string name string sport decimal hourly_rate time opens_at time closes_at boolean is_active }
  BOOKINGS { bigint id PK string reference_code UK bigint user_id FK bigint court_id FK datetime start_at datetime end_at decimal total_price string status }
  MAINTENANCE_BLOCKS { bigint id PK bigint court_id FK datetime start_at datetime end_at string reason }
  API_TOKENS { bigint id PK bigint user_id FK string token_hash UK datetime expires_at }
  PASSWORD_RESETS { string email PK string token_hash datetime expires_at }
```

All tables use InnoDB and `utf8mb4`. `bookings` keeps restrictive user/court foreign keys to preserve history; a court is deactivated rather than hard-deleted. The court/window indexes support overlap checks. API tokens and password reset links are stored as SHA-256 hashes. Booking status is constrained to the lifecycle values. Imported tables are defined in `database/schema.sql`.
