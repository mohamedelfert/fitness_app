# Fetness App - SaaS Fitness Platform

A multi-tenant Laravel SaaS platform for fitness coaches and their clients, powered by AI.

## Tech Stack

- **Backend:** Laravel 12 (PHP 8.2+)
- **Database:** MySQL 8 (Multi-database per tenant)
- **Cache/Queue:** Redis 7
- **Authentication:** Laravel Sanctum
- **Multi-tenancy:** stancl/tenancy v3
- **Queue System:** Laravel Horizon
- **Real-time:** Laravel Reverb
- **Search:** Laravel Scout
- **Admin Panel:** Filament v3

## Architecture

### Database Design

- **Central DB** (`fetness_central`): Users, tenants, billing, global catalog, AI registry
- **Per-Tenant DB** (`tenant_{ulid}`): Client data, plans, workouts, meals, progress

### ID Strategy

- ULIDs `CHAR(26)` for all Primary Keys
- Prefixed in API responses (e.g., `pln_01H...`, `usr_01H...`)

### Multi-Tenancy

- Multi-database architecture
- Tenant isolation via `stancl/tenancy`
- Cross-DB references enforced in application layer

## Features

### MVP Features

- [x] Multi-tenant tenant provisioning
- [x] User registration + login + OTP authentication
- [x] Tenant creation and invite system
- [x] Exercise/Food catalog (global + custom)
- [x] Plan creation, approval, activation
- [x] Workout session logging with PR detection
- [x] Meal logging with macro tracking
- [x] Progress tracking (weigh-ins, measurements, photos)
- [x] Real-time chat (1:1 coach-client)
- [x] AI plan generation (2-call strategy)
- [x] SaaS billing with Stripe
- [x] Push notifications via FCM
- [x] Admin panel

### V2 Features (Post-MVP)

- Stripe Connect (B2B2C revenue share)
- Group chat
- Check-in forms
- Program cohorts
- Wearable sync (Apple Health / Google Fit)
- White-label branding
- Multi-coach support

## Installation

```bash
# Clone and install
composer install

# Start Docker services
docker compose up -d

# Configure environment
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations (central first)
php artisan migrate --database=central
php artisan migrate --database=tenant

# Install Horizon
php artisan horizon:install

# Build assets (if needed)
npm install && npm run build
```

## Configuration

### Environment Variables

```env
# Database (Central)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fetness_central
DB_USERNAME=fetness
DB_PASSWORD=secret

# Tenant Template Database
DB_TENANT_HOST=127.0.0.1
DB_TENANT_PORT=3307
DB_TENANT_DATABASE=tenant_template

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (Mailhog)
MAIL_MAILHOG_HOST=127.0.0.1
MAIL_MAILHOG_PORT=1025
```

## API Endpoints

Base URL: `https://api.fetnessapp.io/api/v1`

### Authentication
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/otp/request`
- `POST /auth/otp/verify`
- `POST /auth/logout`

### Tenancy
- `POST /tenants`
- `GET /tenants/current`
- `POST /tenants/current/invites`
- `POST /tenants/switch`

### Plans
- `GET /clients/{id}/plans`
- `POST /clients/{id}/plans`
- `PATCH /plans/{id}`
- `POST /plans/{id}/approve`
- `POST /plans/{id}/activate`

### Workouts
- `POST /workouts/sessions`
- `POST /workouts/sessions/{id}/sets`
- `PATCH /workouts/sessions/{id}`

### Meals
- `POST /meals`
- `GET /meals`
- `DELETE /meals/{id}`

### Progress
- `POST /progress/weigh-ins`
- `POST /progress/measurements`
- `POST /progress/photos/presign`

### AI
- `POST /ai/plans/generate`
- `GET /ai/requests/{id}`

### Billing
- `GET /billing/plans`
- `POST /billing/subscribe`
- `POST /webhooks/stripe`

## WebSocket Channels (Reverb)

- `private-thread.{thread_id}` - Chat messages
- `private-user.{user_id}` - User notifications
- `private-tenant.{tenant_id}` - Tenant events
- `private-admin` - Admin alerts

## Queue Jobs

- `GeneratePlanJob` - AI plan generation (ai queue)
- `RunWeeklyAdaptationJob` - Weekly adaptation
- `MaybeReplanJob` - Triggered on significant weight change
- `RecomputeAdherenceJob` - Weekly metrics

## Testing

```bash
# Run tests
php artisan test

# Run with coverage
php artisan test --coverage

# Lint code
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse
```

## Project Structure

```
app/
├── Domains/
│   ├── Identity/        # User auth, 2FA, OTP
│   ├── Tenancy/         # Tenant management
│   ├── Catalog/         # Exercises, foods
│   ├── Plan/            # Training plans
│   ├── Workout/         # Workout logging
│   ├── Nutrition/       # Meal logging
│   ├── Progress/        # Weigh-ins, measurements
│   ├── Messaging/       # Chat
│   ├── AI/              # Plan generation
│   ├── Billing/         # Subscriptions
│   └── Notification/   # Push, email, SMS

database/
├── migrations/
│   ├── central/        # Central DB tables
│   └── tenant/          # Per-tenant tables
└── seeders/
```

## Documentation

- [BLUEPRINT.md](./BLUEPRINT.md) - Complete technical blueprint
- [EXECUTION_PLAN.md](./EXECUTION_PLAN.md) - Development execution plan
- [TECHNICAL_PLAN.md](./TECHNICAL_PLAN.md) - Technical specifications

## License

MIT License