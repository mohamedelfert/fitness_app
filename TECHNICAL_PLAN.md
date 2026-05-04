# 🛠️ Fetness App — Technical Execution Plan

> Companion to `PLAN.md`. This document is implementation-ready. A developer should be able to open it and start writing code without guessing.

**Stack:** Laravel 11 (PHP 8.3) · MySQL 8 · Redis 7 · Laravel Reverb · Horizon · Sanctum · Cashier · Filament v3 · Flutter 3 · TALL

---

## 1. 🧱 Module Breakdown (Backend)

Each module is a bounded context under `app/Domains/{Module}`. Modules expose **Services** (called by controllers/jobs) and **Actions** (single-purpose invokable classes). Models stay thin.

---

### 1.1 Identity

**Responsibilities:** authentication (mobile + web + admin), user profile, password/OTP, sessions, 2FA, role membership across tenants.

**Models:**
- `User` — global identity. Fields: id, email, phone, password, name, locale, timezone, avatar_path, email_verified_at, phone_verified_at, two_factor_secret, last_login_at.
- `TenantUser` — pivot: tenant_id, user_id, role (`owner|coach|client|staff`), status, joined_at.
- `OtpCode` — id, user_id, channel (`sms|email`), code_hash, purpose, expires_at, consumed_at.
- `PersonalAccessToken` (Sanctum default).

**Services:**
- `AuthService` — `loginWithPassword()`, `loginWithOtp()`, `requestOtp()`, `register()`, `verifyEmail()`, `logout()`, `rotateRefreshToken()`.
- `ProfileService` — `updateProfile()`, `changePassword()`, `enableTwoFactor()`.
- `TenantMembershipService` — `attachUser()`, `detachUser()`, `switchTenant()`.

**Key APIs:**
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/otp/request`
- `POST /api/v1/auth/otp/verify`
- `POST /api/v1/auth/logout`
- `GET  /api/v1/me`
- `PATCH /api/v1/me`
- `POST /api/v1/me/password`

---

### 1.2 Tenancy

**Responsibilities:** tenant lifecycle, branding, subdomain routing, tenant-context middleware, member invites.

**Models:**
- `Tenant` — id, slug, name, type (`solo_coach|gym|enterprise`), subdomain, custom_domain, logo_path, primary_color, secondary_color, font, status (`active|trial|suspended`), trial_ends_at, owner_user_id, settings (JSON), created_at.
- `TenantInvite` — id, tenant_id, email, role, token, expires_at, accepted_at.
- `TenantSetting` — key/value JSON store (feature flags, AI auto-approve, etc.).

**Services:**
- `TenantProvisioner` — `create()`, `provisionDefaults()` (seeds default plan templates, exercise filters, library access).
- `TenantContextResolver` — resolves tenant from request (subdomain → custom domain → token claim).
- `BrandingService` — uploads/updates logo, validates color contrast.
- `InviteService` — `invite()`, `accept()`, `resend()`, `revoke()`.

**Key APIs:**
- `POST /api/v1/tenants` (create on signup)
- `GET  /api/v1/tenants/current`
- `PATCH /api/v1/tenants/current` (branding)
- `POST /api/v1/tenants/current/invites`
- `POST /api/v1/tenants/invites/{token}/accept`

---

### 1.3 Catalog (Exercises + Foods)

**Responsibilities:** global library, tenant-custom additions, search, embeddings.

**Models:**
- `Exercise` — id, tenant_id (nullable for global), slug, name, primary_muscle, secondary_muscles (JSON), equipment, mechanic (`compound|isolation`), force (`push|pull`), level, video_url, thumbnail_path, instructions, is_global, is_active.
- `Food` — id, tenant_id (nullable), name, brand, serving_size_g, kcal, protein_g, carbs_g, fat_g, fiber_g, sugar_g, sodium_mg, barcode, source (`global|usda|tenant_custom|user`), is_active, embedding_id.
- `FoodAlias` — id, food_id, alias (e.g. "chicken breast" / "poulet").
- `Embedding` — id, owner_type, owner_id, vector (BLOB or PGVector if migrated).

**Services:**
- `CatalogSearchService` — `searchExercises($q, filters)`, `searchFoods($q, $tenantId)`. Backed by MySQL FULLTEXT in MVP, Meilisearch from V2.
- `FoodMatchService` — `matchByText($text)` (returns ranked food_ids using embeddings + alias).
- `BarcodeLookupService` — local DB → OpenFoodFacts fallback.

**Key APIs:**
- `GET /api/v1/exercises?q=&muscle=&equipment=&page_token=`
- `GET /api/v1/exercises/{id}`
- `POST /api/v1/exercises` (coach-only)
- `GET /api/v1/foods?q=&page_token=`
- `GET /api/v1/foods/barcode/{code}`
- `POST /api/v1/foods` (coach/user-custom)

---

### 1.4 Plan

**Responsibilities:** workout + meal plan structure, versioning, approval, templates.

**Models:**
- `Plan` — id, tenant_id, client_id, parent_plan_id, version, status (`draft|pending_review|approved|active|archived`), source (`manual|ai|template`), starts_on, ends_on, weeks (int), notes, generated_by_user_id, approved_by_user_id, approved_at, ai_meta (JSON: prompt_id, model, cost).
- `PlanWorkoutDay` — id, plan_id, week_index, day_of_week (0-6), title, focus.
- `PlanExercise` — id, plan_workout_day_id, exercise_id, order, target_sets, target_reps, target_weight_kg, target_rpe, target_rest_sec, notes, superset_group_id (nullable), is_optional.
- `PlanMealDay` — id, plan_id, week_index, day_of_week, total_kcal, total_protein_g, total_carbs_g, total_fat_g.
- `PlanMeal` — id, plan_meal_day_id, slot (`breakfast|snack1|lunch|snack2|dinner`), title, target_kcal, target_protein_g, target_carbs_g, target_fat_g.
- `PlanMealItem` — id, plan_meal_id, food_id, quantity_g, kcal, protein_g, carbs_g, fat_g, order.
- `PlanTemplate` — id, tenant_id, name, body (JSON snapshot), is_global.

**Services:**
- `PlanBuilderService` — `createDraft()`, `addWorkoutDay()`, `addExerciseToDay()`, `addMealItem()`, `cloneFromTemplate()`, `cloneFromPreviousVersion()`.
- `PlanApprovalService` — `submitForReview()`, `approve()`, `reject()`, `activate()`.
- `PlanTemplateService` — `saveAsTemplate()`, `applyTemplate()`.

**Key APIs:**
- `GET  /api/v1/clients/{id}/plans`
- `POST /api/v1/clients/{id}/plans` (create draft)
- `GET  /api/v1/plans/{id}` (full tree)
- `PATCH /api/v1/plans/{id}`
- `POST /api/v1/plans/{id}/workout-days/{wd}/exercises`
- `PATCH /api/v1/plan-exercises/{id}`
- `DELETE /api/v1/plan-exercises/{id}`
- `POST /api/v1/plans/{id}/regenerate` (calls AI)
- `POST /api/v1/plans/{id}/approve`
- `POST /api/v1/plans/{id}/activate`
- `POST /api/v1/plans/{id}/save-as-template`

---

### 1.5 Workout (Logging)

**Responsibilities:** record what the user actually did.

**Models:**
- `WorkoutSession` — id, tenant_id, client_id, plan_workout_day_id (nullable), started_at, ended_at, total_volume_kg, perceived_effort, notes, source (`app|wearable|manual`).
- `SetLog` — id, workout_session_id, plan_exercise_id (nullable), exercise_id, set_index, reps, weight_kg, rpe, is_warmup, completed_at.
- `WorkoutSwap` — id, workout_session_id, original_exercise_id, replacement_exercise_id, reason.

**Services:**
- `WorkoutLogService` — `startSession()`, `logSet()`, `swapExercise()`, `endSession()`. Append-only writes.
- `AdherenceCalculator` — `recomputeForClient($clientId, $weekStart)` → updates `client_metrics_weekly`.

**Key APIs:**
- `POST /api/v1/workouts/sessions` (start)
- `PATCH /api/v1/workouts/sessions/{id}` (end)
- `POST /api/v1/workouts/sessions/{id}/sets`
- `POST /api/v1/workouts/sessions/{id}/swap`
- `GET  /api/v1/workouts/sessions?cursor=`
- `GET  /api/v1/workouts/sessions/{id}`

---

### 1.6 Nutrition (Logging)

**Responsibilities:** record meals eaten.

**Models:**
- `MealLog` — id, tenant_id, client_id, logged_at, slot, plan_meal_id (nullable), source (`plan|manual|barcode|photo`), notes, total_kcal, total_protein_g, total_carbs_g, total_fat_g.
- `MealLogItem` — id, meal_log_id, food_id, quantity_g, kcal, protein_g, carbs_g, fat_g.
- `WaterLog` — id, tenant_id, client_id, logged_at, amount_ml.

**Services:**
- `MealLogService` — `logFromPlan()`, `logCustom($items)`, `logFromBarcode()`, `logFromPhoto()` (V3).
- `MacroCalculator` — recomputes totals on item changes.

**Key APIs:**
- `POST /api/v1/meals` (log a meal)
- `PATCH /api/v1/meals/{id}`
- `DELETE /api/v1/meals/{id}`
- `GET  /api/v1/meals?from=&to=`
- `POST /api/v1/water` (log)
- `GET  /api/v1/water?from=&to=`
- `GET  /api/v1/nutrition/summary?date=` (daily macros)

---

### 1.7 Progress

**Responsibilities:** weigh-ins, measurements, photos, PRs, charts.

**Models:**
- `WeighIn` — id, tenant_id, client_id, logged_at, weight_kg, body_fat_pct (nullable), source.
- `Measurement` — id, tenant_id, client_id, logged_at, site (`waist|hips|chest|...`), value_cm.
- `ProgressPhoto` — id, tenant_id, client_id, taken_at, angle (`front|side|back`), s3_path, is_private.
- `PersonalRecord` — id, tenant_id, client_id, exercise_id, kind (`1rm|max_reps|max_volume`), value, achieved_at, set_log_id.
- `ClientMetricsWeekly` — id, tenant_id, client_id, week_start, workouts_done, workouts_planned, kcal_avg, kcal_target, adherence_pct, weight_kg, weight_delta_kg.

**Services:**
- `ProgressService` — `logWeighIn()`, `logMeasurement()`, `uploadPhoto()`, `seriesForChart()`.
- `PrDetector` — runs after every `SetLog` insert, updates `PersonalRecord`.

**Key APIs:**
- `POST /api/v1/progress/weigh-ins`
- `POST /api/v1/progress/measurements`
- `POST /api/v1/progress/photos` (multipart S3 presign)
- `GET  /api/v1/progress/series?metric=weight&from=&to=`
- `GET  /api/v1/progress/prs?exercise_id=`

---

### 1.8 Messaging

**Responsibilities:** 1:1 chat (coach ↔ client), attachments, real-time delivery.

**Models:**
- `ChatThread` — id, tenant_id, coach_user_id, client_user_id, last_message_at, unread_for_coach, unread_for_client.
- `Message` — id, thread_id, sender_user_id, body, attachment_path, attachment_mime, voice_duration_sec, sent_at, read_at, deleted_at.

**Services:**
- `MessagingService` — `sendMessage()`, `markRead()`, `attachFile()` (S3 presign).
- Broadcasts on `private-thread.{id}`.

**Key APIs:**
- `GET  /api/v1/chat/threads`
- `GET  /api/v1/chat/threads/{id}/messages?cursor=`
- `POST /api/v1/chat/threads/{id}/messages`
- `POST /api/v1/chat/threads/{id}/read`
- `POST /api/v1/chat/uploads/presign` (returns S3 PUT URL)

---

### 1.9 AI Orchestration

**Responsibilities:** generate plans, run adaptation, call LLM safely, log cost.

**Models:**
- `AiGenerationRequest` — id, tenant_id, client_id, kind (`plan_initial|plan_replan|copilot_suggestion`), input_hash, status (`queued|running|succeeded|failed`), model, prompt_version, input (JSON), output (JSON), cost_usd, latency_ms, error, requested_by, completed_at.
- `AiPromptVersion` — id, kind, version, template, is_active.
- `AiCostLedger` — id, tenant_id, request_id, cost_usd, occurred_at.

**Services:**
- `PlanGenerationOrchestrator` — top-level entry. Steps: `prepareInput()` → `runRuleEngine()` → `callLLMTemplater()` → `validate()` → `persistDraft()`.
- `RuleEngineService` — pure-PHP TDEE/macro/volume calc.
- `LlmClient` — provider-agnostic (`Claude|OpenAI`), retry/backoff, cost tracking.
- `PlanValidator` — rejects unsafe outputs (allergens, kcal bounds, volume bounds).
- `AdaptationEngine` — weekly cron-driven replan logic.

**Key APIs (internal/coach-side):**
- `POST /api/v1/ai/plans/generate` (body: client_id, kind) → returns `request_id`
- `GET  /api/v1/ai/requests/{id}` (poll status)
- `POST /api/v1/ai/copilot/suggest` (V2)

---

### 1.10 Billing

**Responsibilities:** Stripe subscriptions, plan gating, usage metering, dunning.

**Models:**
- `PricingPlan` — id, slug (`free|pro|coach_starter|coach_pro|coach_studio`), audience (`user|coach`), stripe_product_id, stripe_price_id, monthly_price_cents, annual_price_cents, features (JSON: `{max_clients, ai_plans_per_month, branding, ...}`), is_active.
- `Subscription` (extends Cashier) — tenant_id or user_id, stripe_id, stripe_status, stripe_price, quantity, trial_ends_at, ends_at.
- `UsageCounter` — id, tenant_id, scope (`ai_plans`, `active_clients`), period (`2026-05`), count.
- `Invoice` (Cashier).

**Services:**
- `BillingService` — `subscribe()`, `swapPlan()`, `cancel()`, `resume()`.
- `EntitlementService` — `can($tenant, $feature, $context)` → boolean. Source of truth for gates.
- `UsageMeter` — `increment($tenant, $scope)` + `check($tenant, $scope, $limit)`.
- `StripeWebhookHandler` — handles `customer.subscription.*`, `invoice.payment_*`.

**Key APIs:**
- `GET  /api/v1/billing/plans`
- `POST /api/v1/billing/subscribe` (returns Stripe Checkout URL)
- `POST /api/v1/billing/portal` (returns Customer Portal URL)
- `POST /api/v1/billing/cancel`
- `POST /webhooks/stripe`

---

### 1.11 Notification

**Responsibilities:** push (FCM), email, SMS, in-app, scheduling.

**Models:**
- `Notification` (Laravel default) — id, type, notifiable_type, notifiable_id, data (JSON), read_at.
- `DeviceToken` — id, user_id, platform (`ios|android`), token, last_seen_at.
- `NotificationPreference` — user_id, channel, kind, enabled.

**Services:**
- `NotificationDispatcher` — fan-out to FCM / SES / Twilio.
- `Reminder Scheduler` — schedules daily/weekly reminders via Laravel scheduler.

**Key APIs:**
- `POST /api/v1/devices` (register FCM token)
- `DELETE /api/v1/devices/{token}`
- `GET  /api/v1/notifications`
- `POST /api/v1/notifications/{id}/read`

---

### 1.12 Admin

**Responsibilities:** internal control plane (Filament).

**Resources:** TenantResource, UserResource, PlanResource, AiRequestResource, SubscriptionResource, SupportTicketResource. Filament pages for: AnalyticsDashboard, PromptManager, FeatureFlags, ContentModeration.

---

## 2. 🔗 API Design (Detailed)

### Conventions (apply to every endpoint)
- Base URL: `https://api.fetnessapp.io/api/v1`
- Auth: `Authorization: Bearer {token}` (mobile) or session cookie (web).
- Tenant: resolved from token's `tnt` claim or `X-Tenant-Slug` header for multi-tenant users.
- Content-Type: `application/json; charset=utf-8`.
- Cursor pagination: `?cursor=opaque&limit=20` → response includes `meta.next_cursor`.
- Idempotency: `Idempotency-Key: <uuid>` on all mutating endpoints that create resources.

### Standard Response Envelope

Success:
```json
{
  "data": { ... },
  "meta": { "next_cursor": "abc...", "request_id": "req_..." }
}
```

Error:
```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Invalid input.",
    "details": { "weight_kg": ["must be > 0"] },
    "trace_id": "trace_..."
  }
}
```

---

### 2.1 Auth Module

#### `POST /api/v1/auth/register`
**Auth:** none.
**Request:**
```json
{
  "email": "user@example.com",
  "phone": "+201234567890",
  "password": "Strong#123",
  "name": "Ali Ahmed",
  "locale": "en",
  "tenant_slug": "coach-mo"   // optional — joins existing tenant as client
}
```
**Response 201:**
```json
{
  "data": {
    "user": { "id": "usr_...", "email": "...", "name": "..." },
    "token": "1|abc...",
    "expires_at": "2026-06-04T00:00:00Z"
  }
}
```

#### `POST /api/v1/auth/login`
**Auth:** none.
**Request:** `{ "email": "...", "password": "..." }` or `{ "phone": "...", "otp": "123456" }`.
**Response 200:** same as register.
**Errors:** `INVALID_CREDENTIALS`, `ACCOUNT_LOCKED`.

#### `POST /api/v1/auth/otp/request`
**Auth:** none.
**Request:** `{ "channel": "sms", "phone": "+20..." }`. Rate-limited 5/min/IP.
**Response 202:** `{ "data": { "expires_in": 300 } }`.

#### `POST /api/v1/auth/otp/verify`
**Request:** `{ "phone": "...", "code": "123456" }`. Returns token same shape as login.

#### `GET /api/v1/me`
**Auth:** required.
**Response 200:**
```json
{
  "data": {
    "id": "usr_...",
    "email": "...",
    "name": "...",
    "locale": "en",
    "tenants": [
      { "id": "tnt_...", "slug": "coach-mo", "role": "client", "name": "Coach Mo" }
    ],
    "active_tenant_id": "tnt_..."
  }
}
```

---

### 2.2 Plan Module

#### `GET /api/v1/clients/{client_id}/plans`
**Auth:** coach role on tenant OR self (client viewing own plans).
**Response:**
```json
{
  "data": [
    {
      "id": "pln_01H...",
      "version": 3,
      "status": "active",
      "starts_on": "2026-05-04",
      "ends_on": "2026-06-01",
      "source": "ai",
      "weeks": 4
    }
  ],
  "meta": { "next_cursor": null }
}
```

#### `POST /api/v1/clients/{client_id}/plans`
**Auth:** coach.
**Request:**
```json
{
  "source": "ai",
  "starts_on": "2026-05-04",
  "weeks": 4,
  "from_template_id": null,
  "from_plan_id": null
}
```
**Response 202:** `{ "data": { "plan_id": "pln_...", "ai_request_id": "air_..." } }` (when source=ai → async).

#### `GET /api/v1/plans/{id}`
**Response 200:** full tree (plan → workout_days → exercises; meal_days → meals → items). Use `?include=workout,meal` to scope.

#### `PATCH /api/v1/plan-exercises/{id}`
**Request:** `{ "target_sets": 4, "target_reps": "8-10", "target_weight_kg": 60 }`.

#### `POST /api/v1/plans/{id}/regenerate`
**Auth:** coach.
**Request:** `{ "scope": "workout|meal|both", "instructions": "increase upper body volume" }`.
**Response 202:** `{ "data": { "ai_request_id": "air_..." } }`.

#### `POST /api/v1/plans/{id}/approve`
**Response 200:** `{ "data": { "id": "pln_...", "status": "approved" } }`.

#### `POST /api/v1/plans/{id}/activate`
Marks current plan archived, this one active. Pushes notification to client.

---

### 2.3 Workout Module

#### `POST /api/v1/workouts/sessions`
**Auth:** client.
**Request:** `{ "plan_workout_day_id": "pwd_...", "started_at": "2026-05-04T07:30:00Z" }`.
**Response 201:** `{ "data": { "id": "wsn_...", "started_at": "..." } }`.

#### `POST /api/v1/workouts/sessions/{id}/sets`
**Idempotent.**
**Request:**
```json
{
  "plan_exercise_id": "pex_...",
  "exercise_id": "exe_...",
  "set_index": 1,
  "reps": 10,
  "weight_kg": 60,
  "rpe": 8,
  "is_warmup": false,
  "completed_at": "2026-05-04T07:35:12Z"
}
```
**Response 201:** `{ "data": { "id": "set_...", "is_pr": true } }`.

#### `PATCH /api/v1/workouts/sessions/{id}` (end)
**Request:** `{ "ended_at": "...", "perceived_effort": 7, "notes": "felt strong" }`.

---

### 2.4 Nutrition Module

#### `POST /api/v1/meals`
**Request:**
```json
{
  "logged_at": "2026-05-04T13:00:00Z",
  "slot": "lunch",
  "plan_meal_id": "pml_...",   // optional
  "source": "manual",
  "items": [
    { "food_id": "fod_...", "quantity_g": 200 },
    { "food_id": "fod_...", "quantity_g": 80 }
  ]
}
```
**Response 201:** logged meal with computed totals.

#### `GET /api/v1/nutrition/summary?date=2026-05-04`
**Response:**
```json
{
  "data": {
    "date": "2026-05-04",
    "kcal": { "consumed": 1820, "target": 2200 },
    "protein_g": { "consumed": 140, "target": 165 },
    "carbs_g":   { "consumed": 180, "target": 230 },
    "fat_g":     { "consumed": 60,  "target": 70 },
    "water_ml":  { "consumed": 1500, "target": 2500 }
  }
}
```

---

### 2.5 Progress Module

#### `POST /api/v1/progress/weigh-ins`
**Request:** `{ "logged_at": "...", "weight_kg": 78.4, "body_fat_pct": 18.0 }`.

#### `GET /api/v1/progress/series?metric=weight&from=2026-04-01&to=2026-05-04`
**Response:** array of `{ x: date, y: value }` pairs.

#### `POST /api/v1/progress/photos`
Two-step: client calls `POST /presign` → uploads to S3 → calls this endpoint with returned `s3_path`.

---

### 2.6 Messaging

#### `POST /api/v1/chat/threads/{id}/messages`
**Request:** `{ "body": "hi coach", "attachment_path": null }`. Triggers WS broadcast on `private-thread.{id}`.

#### `GET /api/v1/chat/threads/{id}/messages?cursor=`
Reverse-chrono with cursor. Marks unread = 0 when reading own thread.

---

### 2.7 AI

#### `POST /api/v1/ai/plans/generate`
**Auth:** coach. Tenant entitlement check: `ai_plans` quota.
**Request:**
```json
{
  "client_id": "cli_...",
  "kind": "plan_initial",
  "config": { "weeks": 4, "split_preference": "upper_lower" }
}
```
**Response 202:** `{ "data": { "ai_request_id": "air_..." } }`.

#### `GET /api/v1/ai/requests/{id}`
Returns status + (when succeeded) `plan_id`.

---

### 2.8 Billing

#### `POST /api/v1/billing/subscribe`
**Request:** `{ "plan_slug": "coach_pro", "billing_cycle": "monthly" }`.
**Response:** `{ "data": { "checkout_url": "https://checkout.stripe.com/..." } }`.

#### `POST /webhooks/stripe`
Handles: `customer.subscription.created/updated/deleted`, `invoice.paid`, `invoice.payment_failed`. Verifies HMAC, dispatches to `StripeWebhookHandler`.

---

## 3. 🗄️ Database Design (Detailed)

> All IDs are ULIDs (26 chars) prefixed by entity type for log-friendliness (`pln_01H...`). Stored as `CHAR(26)` PK. All tenant-owned tables include `tenant_id CHAR(26) NOT NULL` + composite indexes.

### 3.1 Identity

#### `users`
| Column | Type | Notes |
|---|---|---|
| id | CHAR(26) PK | |
| email | VARCHAR(190) UNIQUE NULL | |
| phone | VARCHAR(20) UNIQUE NULL | E.164 |
| password | VARCHAR(255) NULL | bcrypt |
| name | VARCHAR(120) | |
| locale | CHAR(5) DEFAULT 'en' | |
| timezone | VARCHAR(64) | |
| avatar_path | VARCHAR(255) NULL | S3 key |
| email_verified_at | TIMESTAMP NULL | |
| phone_verified_at | TIMESTAMP NULL | |
| two_factor_secret | TEXT NULL | encrypted |
| last_login_at | TIMESTAMP NULL | |
| created_at, updated_at | | |

Indexes: `idx_users_email (email)`, `idx_users_phone (phone)`.

#### `tenant_user`
| Column | Type | Notes |
|---|---|---|
| id | CHAR(26) PK | |
| tenant_id | CHAR(26) FK | |
| user_id | CHAR(26) FK | |
| role | ENUM('owner','coach','client','staff') | |
| status | ENUM('active','invited','suspended') | |
| joined_at | TIMESTAMP | |

Indexes: UNIQUE `(tenant_id, user_id)`, `idx_tu_user (user_id)`, `idx_tu_role (tenant_id, role)`.

#### `otp_codes`
| Column | Type | Notes |
|---|---|---|
| id, user_id, channel, code_hash, purpose, expires_at, consumed_at | | code_hash = sha256 |

Index: `idx_otp_user_purpose (user_id, purpose, consumed_at)`.

---

### 3.2 Tenancy

#### `tenants`
| Column | Type | Notes |
|---|---|---|
| id | CHAR(26) PK | |
| slug | VARCHAR(60) UNIQUE | |
| name | VARCHAR(120) | |
| type | ENUM('solo_coach','gym','enterprise') | |
| subdomain | VARCHAR(60) UNIQUE | |
| custom_domain | VARCHAR(190) UNIQUE NULL | |
| logo_path | VARCHAR(255) NULL | |
| primary_color | CHAR(7) | hex |
| secondary_color | CHAR(7) | |
| font | VARCHAR(60) | |
| status | ENUM('active','trial','suspended','closed') | |
| trial_ends_at | TIMESTAMP NULL | |
| owner_user_id | CHAR(26) FK users | |
| settings | JSON | |
| created_at, updated_at | | |

#### `tenant_invites`
Standard fields + index `(token UNIQUE)`.

---

### 3.3 Catalog

#### `exercises`
| Column | Type | Notes |
|---|---|---|
| id | CHAR(26) PK | |
| tenant_id | CHAR(26) NULL | NULL = global |
| slug | VARCHAR(120) | |
| name | VARCHAR(160) | |
| primary_muscle | VARCHAR(40) | |
| secondary_muscles | JSON | |
| equipment | VARCHAR(40) | |
| mechanic | ENUM('compound','isolation') | |
| force | ENUM('push','pull','static') | |
| level | ENUM('beginner','intermediate','advanced') | |
| video_url | VARCHAR(255) | |
| thumbnail_path | VARCHAR(255) | |
| instructions | TEXT | |
| is_global | BOOL | |
| is_active | BOOL | |

Indexes: `idx_ex_tenant (tenant_id, is_active)`, FULLTEXT `(name, primary_muscle)`, UNIQUE `(tenant_id, slug)`.

#### `foods`
| Column | Type | Notes |
|---|---|---|
| id, tenant_id (NULL=global) | | |
| name | VARCHAR(190) | |
| brand | VARCHAR(120) NULL | |
| serving_size_g | DECIMAL(7,2) | |
| kcal | DECIMAL(7,2) | per serving |
| protein_g, carbs_g, fat_g, fiber_g, sugar_g | DECIMAL(7,2) | |
| sodium_mg | DECIMAL(8,2) | |
| barcode | VARCHAR(20) NULL | |
| source | ENUM('global','usda','off','tenant_custom','user') | |
| is_active | BOOL | |
| embedding_id | CHAR(26) NULL | |

Indexes: `idx_foods_tenant (tenant_id, is_active)`, `idx_foods_barcode (barcode)`, FULLTEXT `(name, brand)`.

#### `food_aliases`
`id, food_id, alias VARCHAR(190), locale CHAR(5)`. FULLTEXT on alias.

---

### 3.4 Plan

#### `plans`
| Column | Type | Notes |
|---|---|---|
| id | CHAR(26) PK | |
| tenant_id | CHAR(26) | |
| client_id | CHAR(26) FK tenant_user | |
| parent_plan_id | CHAR(26) NULL | |
| version | INT | |
| status | ENUM('draft','pending_review','approved','active','archived') | |
| source | ENUM('manual','ai','template') | |
| starts_on | DATE | |
| ends_on | DATE | |
| weeks | TINYINT | |
| notes | TEXT | |
| generated_by_user_id | CHAR(26) | |
| approved_by_user_id | CHAR(26) NULL | |
| approved_at | TIMESTAMP NULL | |
| ai_meta | JSON NULL | |
| created_at, updated_at | | |

Indexes: `idx_plans_client_status (tenant_id, client_id, status)`, `idx_plans_active (tenant_id, status, starts_on)`.

#### `plan_workout_days`
`id, plan_id, week_index TINYINT, day_of_week TINYINT, title VARCHAR(120), focus VARCHAR(60)`.
Index `(plan_id, week_index, day_of_week)` UNIQUE.

#### `plan_exercises`
| Column | Type | |
|---|---|---|
| id, plan_workout_day_id, exercise_id | | |
| order | TINYINT | |
| target_sets | TINYINT | |
| target_reps | VARCHAR(15) | "8-10" |
| target_weight_kg | DECIMAL(6,2) NULL | |
| target_rpe | TINYINT NULL | |
| target_rest_sec | SMALLINT | |
| notes | TEXT NULL | |
| superset_group_id | CHAR(26) NULL | |
| is_optional | BOOL | |

Index `(plan_workout_day_id, order)`.

#### `plan_meal_days`
`id, plan_id, week_index, day_of_week, total_kcal, total_protein_g, total_carbs_g, total_fat_g`.

#### `plan_meals`
`id, plan_meal_day_id, slot ENUM('breakfast','snack1','lunch','snack2','dinner','pre','post'), title, target_kcal, target_protein_g, target_carbs_g, target_fat_g`.

#### `plan_meal_items`
`id, plan_meal_id, food_id, quantity_g, kcal, protein_g, carbs_g, fat_g, order`.

#### `plan_templates`
`id, tenant_id NULL, name, body JSON (full snapshot), is_global, created_by_user_id`.

---

### 3.5 Workout Logging

#### `workout_sessions`
| Column | Type | |
|---|---|---|
| id, tenant_id, client_id | | |
| plan_workout_day_id | CHAR(26) NULL | |
| started_at, ended_at | | |
| total_volume_kg | DECIMAL(10,2) | |
| perceived_effort | TINYINT NULL | |
| notes | TEXT NULL | |
| source | ENUM('app','wearable','manual') | |

Indexes: `(tenant_id, client_id, started_at DESC)`.

#### `set_logs`
| Column | Type | |
|---|---|---|
| id, workout_session_id, plan_exercise_id (NULL), exercise_id | | |
| set_index | TINYINT | |
| reps | SMALLINT | |
| weight_kg | DECIMAL(6,2) | |
| rpe | TINYINT NULL | |
| is_warmup | BOOL | |
| completed_at | TIMESTAMP | |

Indexes: `(workout_session_id, set_index)`, `(exercise_id, completed_at)` (for PR queries).

---

### 3.6 Nutrition Logging

#### `meal_logs`
`id, tenant_id, client_id, logged_at, slot, plan_meal_id NULL, source, notes, total_kcal, total_protein_g, total_carbs_g, total_fat_g`.
Indexes: `(tenant_id, client_id, logged_at)`.

#### `meal_log_items`
`id, meal_log_id, food_id, quantity_g, kcal, protein_g, carbs_g, fat_g`.
Index: `(meal_log_id)`.

#### `water_logs`
`id, tenant_id, client_id, logged_at, amount_ml`.

---

### 3.7 Progress

#### `weigh_ins`, `measurements`, `progress_photos`, `personal_records`
Standard shapes (see §1.7). Each has `(tenant_id, client_id, logged_at|achieved_at)` index.

#### `client_metrics_weekly`
Materialized view (rebuilt by `AdherenceCalculator`).
`id, tenant_id, client_id, week_start DATE, workouts_done, workouts_planned, kcal_avg, kcal_target, adherence_pct, weight_kg, weight_delta_kg, recomputed_at`.
UNIQUE `(client_id, week_start)`.

---

### 3.8 Messaging

#### `chat_threads`
`id, tenant_id, coach_user_id, client_user_id, last_message_at, unread_for_coach SMALLINT, unread_for_client SMALLINT`.
UNIQUE `(tenant_id, coach_user_id, client_user_id)`.

#### `messages`
`id, thread_id, sender_user_id, body TEXT, attachment_path, attachment_mime, voice_duration_sec, sent_at, read_at, deleted_at`.
Index `(thread_id, sent_at DESC)`.

---

### 3.9 AI

#### `ai_generation_requests`
| Column | Type | |
|---|---|---|
| id, tenant_id, client_id NULL | | |
| kind | ENUM(...) | |
| input_hash | CHAR(64) | sha256 of canonical input |
| status | ENUM('queued','running','succeeded','failed','validation_failed') | |
| model | VARCHAR(60) | |
| prompt_version | VARCHAR(20) | |
| input | JSON | |
| output | JSON NULL | |
| cost_usd | DECIMAL(8,4) | |
| latency_ms | INT NULL | |
| error | TEXT NULL | |
| requested_by | CHAR(26) | |
| completed_at | TIMESTAMP NULL | |
| created_at | | |

Indexes: `(tenant_id, status)`, `(input_hash)` (for caching).

#### `ai_prompt_versions`
`id, kind, version, template TEXT, is_active, created_at`. Only one `is_active=true` per kind.

---

### 3.10 Billing

#### `pricing_plans`
`id, slug UNIQUE, audience ENUM('user','coach'), stripe_product_id, stripe_price_id_monthly, stripe_price_id_annual, monthly_price_cents, annual_price_cents, features JSON, is_active`.

#### `subscriptions` (Cashier-compatible)
Cashier shape + `tenant_id NULL` (for coach subs) and `user_id NULL` (for B2C subs). Exactly one is non-null per row.

#### `usage_counters`
`id, tenant_id, scope, period CHAR(7) ('YYYY-MM'), count INT`.
UNIQUE `(tenant_id, scope, period)`.

---

### 3.11 Multi-Tenancy Handling

**Resolution order at request time:**
1. If subdomain matches `tenants.subdomain` → use it.
2. Else if header `X-Tenant-Slug` present and user has membership → use it.
3. Else if Sanctum token has `tnt` ability → use it.
4. Else if user has exactly one tenant → use it.
5. Else → 400 `TENANT_REQUIRED`.

**Enforcement:**
- All tenant-owned models extend `TenantScopedModel` which boots a global scope `BelongsToTenant` adding `WHERE tenant_id = ?`.
- Creating a model auto-fills `tenant_id = app('tenant')->id` via observer.
- Static analysis test (Pest): asserts every model in `app/Domains/**/Models` either uses `BelongsToTenant` trait OR is in an allow-list (User, Tenant, GlobalExercise, etc.).
- A unit test seeds two tenants and verifies no leakage on every list endpoint (run on CI).

---

## 4. 🧠 AI Engine (Implementation Level)

### 4.1 Input Data (exact fields)

Captured into `AiGenerationRequest.input` JSON:

```json
{
  "client": {
    "id": "cli_...",
    "age": 28,
    "sex": "male",
    "height_cm": 178,
    "weight_kg": 82.4,
    "body_fat_pct": 22,
    "goal": "fat_loss",
    "target_weight_kg": 75,
    "target_date": "2026-08-04",
    "experience": "intermediate",
    "training_days_per_week": 4,
    "session_duration_min": 60,
    "equipment": ["barbell", "dumbbell", "machine", "bodyweight"],
    "injuries": ["left_shoulder"],
    "diet_preference": "halal",
    "allergies": ["peanuts"],
    "disliked_foods": ["liver"]
  },
  "history": {
    "last_4_weeks_avg_adherence": 0.82,
    "last_4_weeks_workouts_completed": 14,
    "weight_trend_kg_per_week": -0.3,
    "kcal_avg": 2100
  },
  "tenant_settings": {
    "auto_approve": false,
    "preferred_split": "upper_lower",
    "tone": "professional"
  },
  "config": { "weeks": 4, "kind": "plan_initial" }
}
```

`input_hash = sha256(canonical_json(input))` — used to dedupe identical requests within 24h.

---

### 4.2 Processing Logic (Rules)

`PlanGenerationOrchestrator::handle()`:

```
1. Compute targets (RuleEngineService)
   - bmr  = Mifflin-St Jeor(weight, height, age, sex)
   - tdee = bmr × activity_factor (1.4–1.7 by training days)
   - kcal_target:
       fat_loss     → tdee − 500   (clamp ≥ 1500 male, ≥ 1200 female)
       maintain     → tdee
       muscle_gain  → tdee + 300
   - protein_g  = 1.8 × weight_kg  (fat_loss)
                  2.0 × weight_kg  (muscle_gain)
   - fat_g      = max(0.8 × weight, 25% of kcal / 9)
   - carbs_g    = (kcal_target − protein_g×4 − fat_g×9) / 4

2. Build training skeleton
   - split = config.split_preference || pickSplit(days, experience)
       3 days → full_body
       4 days → upper_lower
       5 days → push_pull_legs+upper+lower
       6 days → ppl_x2
   - sets_per_muscle_per_week:
       beginner     10
       intermediate 14
       advanced     18
   - apply injury filter: drop exercises tagged with conflicting joint
   - distribute volume across days respecting muscle frequency (every muscle ≥ 2× / week)

3. Pick exercises (LLM call #1)
   - For each (day, muscle_target) tuple, send LLM:
       "Pick {n} exercises for muscle={x}, equipment={list}, exclude={injuries+already_picked}, level={x}"
   - LLM returns exercise slugs (must exist in catalog — validated)
   - Fallback: deterministic top-N by popularity if LLM fails

4. Build meal plan (LLM call #2)
   - Day buckets per meal slot from config:
       breakfast 25%, lunch 35%, dinner 30%, snacks 10%
   - For each day's meal slot: send LLM target macros + diet_preference + allergies + disliked
   - LLM returns: meal title + items (food_name, qty_g)
   - Match food_name → food_id via FoodMatchService (embedding + alias)
   - Recompute item macros from actual food row (LLM macros are not trusted)

5. Validate (PlanValidator)
   - Per day: kcal in [target − 10%, target + 10%]
   - Per day: protein ≥ 90% target, fat ≥ 50% target
   - No item with allergen tag matching client.allergies
   - Volume per muscle per week within [50%, 150%] of computed target
   - No exercise tagged with injured_joint
   - If any rule fails → status='validation_failed', queue retry up to 2× then surface to coach

6. Persist as draft Plan
   - Wrap in DB transaction
   - Set Plan.source='ai', status='pending_review' (or 'approved' if auto_approve)
   - Update AiGenerationRequest status='succeeded', cost_usd, output
   - Increment UsageCounter('ai_plans')

7. Notify coach (Notification + WS broadcast)
```

---

### 4.3 Output Format

Stored as `AiGenerationRequest.output`:

```json
{
  "plan_id": "pln_...",
  "summary": {
    "kcal_target": 2050,
    "protein_target_g": 165,
    "split": "upper_lower",
    "weeks": 4
  },
  "validations_passed": ["kcal_window","macros","allergens","volume","injuries"],
  "model": "claude-opus-4-7",
  "prompt_version": "plan_initial.v3",
  "tokens": { "input": 4120, "output": 2870 },
  "cost_usd": 0.182,
  "latency_ms": 14230
}
```

API surface returns the resulting `Plan` tree (§2.2 GET `/plans/{id}`).

---

### 4.4 Triggers

| Trigger | Source | Job |
|---|---|---|
| Coach clicks "Generate AI plan" | API `POST /ai/plans/generate` | `GeneratePlanJob` (queue: `ai`) |
| Coach clicks "Regenerate" on existing | API `POST /plans/{id}/regenerate` | same |
| Weekly adaptation | Scheduler `0 3 * * MON` per tenant | `RunWeeklyAdaptationJob` → fan-out per active client |
| Client checks in (V2 trigger) | Event `WeighInRecorded` if Δ > threshold | `MaybeReplanJob` |
| Coach copilot suggestion (V2) | API `POST /ai/copilot/suggest` | `SuggestCopilotJob` |

All AI jobs go on a **dedicated `ai` queue** with concurrency cap (e.g. 4 workers) to control LLM cost burst. Per-tenant rate limit via Redis token bucket.

---

## 5. 💳 SaaS System (Real Implementation)

### 5.1 Plans (seeded into `pricing_plans`)

```
free            audience=user   $0      features={ai_plans:1, history_days:7}
pro             audience=user   $9.99   features={ai_plans:unlimited, history_days:unlimited, wearable:true}
coach_starter   audience=coach  $29     features={max_clients:10, ai_plans:30, branding:logo}
coach_pro       audience=coach  $79     features={max_clients:40, ai_plans:200, branding:full, templates:true}
coach_studio    audience=coach  $199    features={max_clients:150, ai_plans:1000, multi_coach:true, white_label:true}
```

### 5.2 Feature Limits (`features` JSON shape)

```json
{
  "max_clients": 40,
  "ai_plans_per_month": 200,
  "history_days": null,
  "branding": "full",
  "white_label": false,
  "templates": true,
  "group_programs": false,
  "in_app_billing": false,
  "support_tier": "standard"
}
```

### 5.3 Middleware Logic

#### `EnsureFeature` middleware
Used as `->middleware('feature:ai_plans')`. Implementation:

```
1. Resolve current tenant (or user for B2C).
2. Load active subscription → pricing_plan.features.
3. If feature is boolean false or missing → throw 403 FEATURE_NOT_INCLUDED.
4. If feature is numeric (limit):
   - Get current period UsageCounter
   - If count >= limit → throw 429 USAGE_LIMIT_REACHED with `details.limit, details.used`.
   - Else attach a deferred increment callback on response (UsageMeter::increment after 2xx).
5. Continue.
```

#### `EnforceTenantStatus` middleware
Blocks all writes when `tenant.status IN ('suspended','closed')`. Allows reads with banner.

#### `EnforceClientCap` middleware
On `POST /clients` and accept-invite: counts current active clients on tenant, rejects if `>= features.max_clients`.

### 5.4 Stripe Flow (step-by-step)

**Subscribe (coach signs up):**
1. Coach completes signup → `Tenant` created with `status='trial', trial_ends_at=now+14d`.
2. Coach hits `/billing/subscribe` with `plan_slug`.
3. Backend ensures `stripe_customer_id` exists on `Tenant` (Cashier `createAsStripeCustomer()`).
4. Backend creates Stripe Checkout Session: `mode=subscription`, `price=pricing_plan.stripe_price_id`, `success_url`, `cancel_url`, `customer=...`, `subscription_data.trial_end=trial_ends_at`.
5. Returns `checkout_url`. Coach completes payment on Stripe.
6. Stripe sends `customer.subscription.created` webhook.
7. Webhook handler: validates HMAC, locates `Tenant` by `stripe_customer_id`, creates/updates row in `subscriptions`, sets `tenant.status='active'`.
8. Pushes notification "Welcome to Coach Pro" + sends receipt email.

**Renewal:**
- Stripe auto-charges. `invoice.paid` webhook → no app action other than write `Invoice` row.

**Failed payment:**
- `invoice.payment_failed` webhook → set `subscriptions.stripe_status='past_due'` → trigger `DunningEmail` job.
- After 3 attempts (Stripe Smart Retries) → Stripe sends `customer.subscription.updated` with `status='unpaid'` → middleware switches tenant to read-only → 14-day grace → `customer.subscription.deleted` → tenant suspended.

**Plan change:**
- `POST /billing/portal` → returns Stripe Customer Portal URL. Customer changes plan there. Webhook `customer.subscription.updated` syncs locally.

**Cancellation:**
- `POST /billing/cancel` → Cashier `subscription->cancel()`. Status `canceled` at period end. Tenant downgrades to read-only after period end.

**Coach Connect (V2 — coach billing their clients):**
- Onboard: `POST /billing/connect/onboard` → creates Stripe Connect Standard account, returns onboarding URL.
- Coach charges client: backend creates Stripe Checkout with `application_fee_percent=10`, `transfer_data.destination=coach_acct_id`. Funds flow to coach minus our 10% + Stripe fee.

---

## 6. ⚙️ Laravel Structure

```
app/
├── Console/
│   └── Commands/
│       ├── RecomputeAdherenceCommand.php
│       └── RunWeeklyAdaptationCommand.php
├── Domains/
│   ├── Identity/
│   │   ├── Models/{User.php, OtpCode.php, TenantUser.php}
│   │   ├── Services/{AuthService.php, ProfileService.php, TenantMembershipService.php}
│   │   ├── Actions/{LoginWithPasswordAction.php, RequestOtpAction.php, VerifyOtpAction.php}
│   │   ├── DTOs/{LoginDto.php, RegisterDto.php}
│   │   ├── Events/{UserRegistered.php, UserLoggedIn.php}
│   │   ├── Listeners/{SendWelcomeEmail.php}
│   │   ├── Http/
│   │   │   ├── Controllers/Api/{AuthController.php, MeController.php}
│   │   │   ├── Requests/{LoginRequest.php, RegisterRequest.php, ...}
│   │   │   └── Resources/{UserResource.php, MeResource.php}
│   │   ├── Policies/UserPolicy.php
│   │   └── Providers/IdentityServiceProvider.php
│   ├── Tenancy/
│   │   ├── Models/{Tenant.php, TenantInvite.php}
│   │   ├── Services/{TenantProvisioner.php, TenantContextResolver.php, BrandingService.php}
│   │   ├── Middleware/{ResolveTenant.php, EnsureTenantActive.php}
│   │   ├── Scopes/BelongsToTenant.php
│   │   ├── Concerns/TenantScopedModel.php
│   │   └── Http/Controllers/Api/TenantController.php
│   ├── Catalog/
│   │   ├── Models/{Exercise.php, Food.php, FoodAlias.php}
│   │   ├── Services/{CatalogSearchService.php, FoodMatchService.php, BarcodeLookupService.php}
│   │   └── Http/Controllers/Api/{ExerciseController.php, FoodController.php}
│   ├── Plan/
│   │   ├── Models/{Plan.php, PlanWorkoutDay.php, PlanExercise.php, PlanMealDay.php, PlanMeal.php, PlanMealItem.php, PlanTemplate.php}
│   │   ├── Services/{PlanBuilderService.php, PlanApprovalService.php, PlanTemplateService.php}
│   │   ├── Actions/{ClonePlanAction.php, ApprovePlanAction.php, ActivatePlanAction.php}
│   │   ├── Http/Controllers/Api/{PlanController.php, PlanExerciseController.php}
│   │   └── Http/Resources/{PlanResource.php, PlanTreeResource.php}
│   ├── Workout/
│   │   ├── Models/{WorkoutSession.php, SetLog.php, WorkoutSwap.php}
│   │   ├── Services/{WorkoutLogService.php, AdherenceCalculator.php, PrDetector.php}
│   │   ├── Actions/{StartSessionAction.php, LogSetAction.php, EndSessionAction.php}
│   │   ├── Events/{SetLogged.php, SessionEnded.php}
│   │   └── Http/Controllers/Api/WorkoutSessionController.php
│   ├── Nutrition/
│   │   ├── Models/{MealLog.php, MealLogItem.php, WaterLog.php}
│   │   ├── Services/{MealLogService.php, MacroCalculator.php}
│   │   └── Http/Controllers/Api/{MealController.php, WaterController.php, NutritionSummaryController.php}
│   ├── Progress/
│   │   ├── Models/{WeighIn.php, Measurement.php, ProgressPhoto.php, PersonalRecord.php, ClientMetricsWeekly.php}
│   │   ├── Services/{ProgressService.php}
│   │   └── Http/Controllers/Api/ProgressController.php
│   ├── Messaging/
│   │   ├── Models/{ChatThread.php, Message.php}
│   │   ├── Services/MessagingService.php
│   │   ├── Broadcasting/ThreadChannel.php
│   │   └── Http/Controllers/Api/{ChatThreadController.php, MessageController.php}
│   ├── Ai/
│   │   ├── Models/{AiGenerationRequest.php, AiPromptVersion.php}
│   │   ├── Services/{PlanGenerationOrchestrator.php, RuleEngineService.php, PlanValidator.php, AdaptationEngine.php}
│   │   ├── Llm/{LlmClient.php, ClaudeProvider.php, OpenAiProvider.php, PromptRenderer.php}
│   │   ├── Jobs/{GeneratePlanJob.php, RunWeeklyAdaptationJob.php, MaybeReplanJob.php}
│   │   ├── DTOs/{PlanInputDto.php, GeneratedPlanDto.php}
│   │   └── Http/Controllers/Api/{AiPlanController.php, AiRequestController.php}
│   ├── Billing/
│   │   ├── Models/{PricingPlan.php, Subscription.php, UsageCounter.php}
│   │   ├── Services/{BillingService.php, EntitlementService.php, UsageMeter.php}
│   │   ├── Middleware/{EnsureFeature.php, EnforceTenantStatus.php, EnforceClientCap.php}
│   │   ├── Webhooks/StripeWebhookHandler.php
│   │   └── Http/Controllers/{Api/BillingController.php, StripeWebhookController.php}
│   └── Notification/
│       ├── Models/DeviceToken.php
│       ├── Channels/{FcmChannel.php}
│       ├── Notifications/{PlanApprovedNotification.php, ...}
│       └── Http/Controllers/Api/DeviceController.php
├── Filament/                   # Admin (Filament v3)
│   ├── Resources/{TenantResource.php, UserResource.php, ...}
│   └── Pages/{AnalyticsDashboard.php, PromptManager.php}
├── Http/
│   ├── Kernel.php              # registers tenant + auth middleware groups
│   └── Middleware/{...global middleware...}
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── EventServiceProvider.php
│   ├── DomainServiceProvider.php   # boots all Domain providers
│   └── HorizonServiceProvider.php
└── Support/
    ├── Ulid.php
    ├── ApiResponse.php
    └── ResolvesTenant.php
```

**Conventions:**
- **Controllers** are thin: validate, call Service or Action, return Resource. ≤ 30 LoC each.
- **Services** orchestrate; **Actions** are single-purpose invokable classes (`__invoke`). Pick Action when there's one verb; Service when there are several related verbs sharing state.
- **DTOs** as readonly classes (PHP 8.3) for cross-layer payloads; never pass arrays.
- **Events/Listeners** for cross-domain side effects (e.g. `SetLogged` → triggers `RecomputeAdherence` listener in Workout domain). No service directly calls another domain's service — go through events for decoupling, or through a public service interface.
- **Policies** for authorization on every mutation endpoint.
- **Routes** registered per domain in `routes/api/{domain}.php`, all loaded from `RouteServiceProvider`.

---

## 7. 🔄 Data Flow

### Flow A — User logs a set (mobile)

```
[Flutter]
  Riverpod state machine: WorkoutSessionNotifier.logSet()
  Optimistic update → local Drift DB
  Outbox queue: enqueue POST /workouts/sessions/{id}/sets
        │
        ▼ (network)
[API]
  Route → WorkoutSessionController@storeSet
  Middleware: auth:sanctum → ResolveTenant → EnsureTenantActive
  FormRequest validates payload + idempotency key
        │
  Action: LogSetAction(sessionId, dto)
    ├─ Gate::authorize('update', $session)        // policy
    ├─ DB::transaction(function() {
    │     $set = SetLog::create([... tenant_id auto-injected ...]);
    │     $session->increment('total_volume_kg', $set->reps * $set->weight_kg);
    │  })
    ├─ event(new SetLogged($set))                  // sync listeners
    └─ return $set
        │
  Listeners (queued, on `default`):
    ├─ DetectPrListener → updates personal_records
    └─ RecomputeAdherenceListener → upserts client_metrics_weekly
        │
  Resource: SetLogResource → JSON response { id, is_pr }
        │
        ▼
[Flutter]
  On 201 → mark outbox row as synced; reconcile id
  On 4xx/5xx → retry with exponential backoff
```

Latency target: p95 < 200ms (server) for the synchronous path; PR detection is async, broadcast via WS to refresh badge.

---

### Flow B — Coach generates an AI plan

```
[TALL Web]
  Livewire `PlanBuilder.generateAi()`
        │
        ▼
[API]
  POST /ai/plans/generate
  Middleware: auth → ResolveTenant → EnsureFeature:ai_plans
        │
  Controller → AiPlanController@generate
    ├─ Create AiGenerationRequest (status=queued, input_hash)
    ├─ if hash exists & < 24h old → return cached request_id
    ├─ Else dispatch GeneratePlanJob onQueue('ai')
    └─ return { ai_request_id }
        │
        ▼ (queue, Horizon)
[GeneratePlanJob]
  PlanGenerationOrchestrator->handle($request):
    1. mark running
    2. RuleEngineService → targets
    3. LlmClient->complete(prompt, model='claude-opus-4-7')   ← LLM call #1 (exercises)
    4. LlmClient->complete(prompt) ← LLM call #2 (meals)
    5. Map foods via FoodMatchService (embeddings)
    6. PlanValidator::validate() → throw on fail
    7. PlanBuilderService::createDraft($input, $output)
    8. mark succeeded, store cost
        │
  event(new PlanDrafted($plan))
        │
[Listeners]
  ├─ NotifyCoach via FCM + DB notification
  └─ Broadcast WS event 'plan.drafted' on tenant.{id}
        │
        ▼
[TALL Web]
  Livewire polls /ai/requests/{id} (or receives WS)
  On status=succeeded → redirect to plan editor with draft loaded
```

---

### Flow C — Weekly adaptation cron

```
Scheduler (kernel.php):
  $schedule->command('adaptation:run-weekly')->mondays()->at('03:00');

RunWeeklyAdaptationCommand:
  for each tenant where status='active':
    dispatch(RunWeeklyAdaptationJob::for($tenant));

RunWeeklyAdaptationJob:
  for each active client of tenant:
    metrics = ClientMetricsWeekly::latest()
    decision = AdaptationEngine::decide($metrics, $currentPlan)
    switch (decision):
      'maintain'   → no-op
      'adjust_kcal'→ create draft Plan with kcal±10%, status=pending_review
      'deload'     → create draft with reduced volume
      'replan'     → trigger GeneratePlanJob(kind=plan_replan)
    if tenant.settings.auto_approve == true → auto-approve & activate
    else → notify coach
```

---

## 8. 📱 Frontend Integration

### 8.1 Flutter Consumes

**Endpoints used by mobile (canonical):**

| Screen | Endpoints |
|---|---|
| Onboarding | `POST /auth/register`, `POST /auth/otp/*`, `PATCH /me`, `POST /clients/{id}/intake` |
| Today dashboard | `GET /me`, `GET /clients/{me}/today`, `GET /nutrition/summary?date=`, `GET /plans/{active_id}` |
| Workout player | `POST /workouts/sessions`, `POST /sessions/{id}/sets`, `PATCH /sessions/{id}`, `POST /sessions/{id}/swap` |
| Meal log | `GET /foods?q=`, `GET /foods/barcode/{code}`, `POST /meals`, `PATCH /meals/{id}`, `DELETE /meals/{id}` |
| Progress | `POST /progress/weigh-ins`, `POST /progress/measurements`, `GET /progress/series`, `GET /progress/prs` |
| Chat | `GET /chat/threads`, `GET /chat/threads/{id}/messages`, `POST /chat/threads/{id}/messages`, WS `private-thread.{id}` |
| Plan view | `GET /plans/{id}` |
| Notifications | `GET /notifications`, `POST /devices` (FCM token register) |

**Real-time channels (Laravel Reverb):**
- `private-thread.{id}` — new messages, read receipts.
- `private-user.{id}` — plan-approved, plan-activated, coach-message.

**Mobile internals:**
- HTTP client: `dio` with interceptors for auth refresh + retry.
- Local DB: `drift` for offline log queue (set_logs, meal_logs).
- State: `riverpod` (NotifierProvider per screen).
- Codegen: `freezed` for DTOs from OpenAPI spec (`api/v1.openapi.yaml` exported from Laravel via `dedoc/scramble`).

### 8.2 TALL Stack Consumes

**Coach Web (Livewire) does NOT call the JSON API**. It uses Eloquent directly via Service classes — same backend, no HTTP. Specifically:

| Page | Service / Component |
|---|---|
| Login | Fortify default views |
| Dashboard (`/dashboard`) | Livewire `Coach\Dashboard` → calls `ClientRosterService::activeFor($tenant)` |
| Client roster (`/clients`) | Livewire `Coach\Clients\Index` |
| Client profile (`/clients/{id}`) | Livewire `Coach\Clients\Show` with tabs `Overview / Plan / Logs / Chat / Notes` |
| Plan builder (`/plans/{id}/edit`) | Livewire `Coach\Plans\Builder` (Alpine for drag-drop) → `PlanBuilderService` |
| Generate AI plan | Livewire button → dispatches `GeneratePlanJob`; polls request status |
| Messaging | Livewire `Coach\Chat\Inbox` + Reverb for live updates |
| Templates | Livewire `Coach\Templates\Index` |
| Settings (branding) | Livewire `Coach\Settings\Branding` |

**Admin (Filament v3)** — auto-CRUD over `Tenant`, `User`, `Plan`, `AiGenerationRequest`, `Subscription`. Custom pages for analytics, prompt management, feature flag toggles. Filament uses Eloquent directly with admin policies.

---

## 9. 🚀 Development Order (Step-by-Step)

This is the **only correct order** — earlier steps unblock later ones. Each step ends with a deployable, testable cut.

### Step 1 — Database Foundation (Week 1)
- [ ] Set up Laravel 11 project, MySQL 8 docker-compose, Redis, Horizon, Reverb.
- [ ] Configure ULID PKs, JSON columns, strict mode.
- [ ] Migrations: `users`, `tenants`, `tenant_user`, `pricing_plans`, `subscriptions`.
- [ ] Models with relationships only (no business logic yet).
- [ ] Factories + seeders for dev data (`DevSeeder` creates 2 tenants, 5 users).
- [ ] CI pipeline: lint (`pint`), static (`phpstan` level 6), tests (`pest`).
- **Exit criteria:** `php artisan migrate:fresh --seed` works; `Pest` green.

### Step 2 — Auth (Week 2)
- [ ] Sanctum + Fortify setup.
- [ ] Register/login/logout endpoints + tests.
- [ ] OTP flow (Twilio dev sandbox or fake driver in tests).
- [ ] `me` endpoint returning user + tenants.
- [ ] Email verification, password reset.
- [ ] Rate limits on auth endpoints.
- **Exit criteria:** Postman collection covers auth flows; coverage ≥ 90% on Identity domain.

### Step 3 — Tenancy (Week 3)
- [ ] `ResolveTenant` middleware + `BelongsToTenant` global scope.
- [ ] `TenantScopedModel` concern + observer.
- [ ] Tenant provisioning on signup (TenantProvisioner).
- [ ] Subdomain routing config (Nginx/ALB + app `config/tenancy.php`).
- [ ] Multi-tenant isolation tests (assert leakage = 0 across all list endpoints).
- [ ] Tenant invites + accept flow.
- **Exit criteria:** Two tenants in same DB, zero cross-tenant data visible in any endpoint.

### Step 4 — Catalog (Week 4)
- [ ] Migrations: `exercises`, `foods`, `food_aliases`.
- [ ] Seed ~500 exercises (from open dataset) + ~3,000 foods (USDA + curated MENA list).
- [ ] FULLTEXT indexes; basic search endpoints.
- [ ] Coach-custom exercise/food creation.
- **Exit criteria:** Search returns < 100ms p95 on seeded dataset.

### Step 5 — Plan Module (Weeks 5–6)
- [ ] Migrations for plan tree.
- [ ] PlanBuilderService (manual creation).
- [ ] Plan CRUD endpoints + nested resources.
- [ ] Plan templates.
- [ ] Coach Web (TALL): plan builder Livewire component with Alpine drag-drop.
- **Exit criteria:** A coach can build a 4-week plan manually in the UI, save, and view as JSON.

### Step 6 — Workout + Nutrition Logging (Weeks 6–7, parallel)
- [ ] Migrations + models for sessions, set_logs, meal_logs, water_logs.
- [ ] Append-only services with idempotency keys.
- [ ] PR detection listener.
- [ ] Adherence recompute job.
- [ ] Mobile: workout player + meal log screens (Flutter).
- **Exit criteria:** End-to-end log → DB → progress chart works on mobile.

### Step 7 — Progress + Messaging (Week 8)
- [ ] Weigh-ins, measurements, progress photos (S3 presign flow).
- [ ] Chart series endpoint.
- [ ] Chat threads + messages.
- [ ] Reverb WebSocket setup; private-thread channel auth.
- [ ] Mobile + web chat UI.
- **Exit criteria:** Coach and client can chat real-time; photos upload to S3.

### Step 8 — AI Engine (Weeks 9–10)
- [ ] `ai_generation_requests`, `ai_prompt_versions` tables.
- [ ] `LlmClient` with Claude + OpenAI providers.
- [ ] `RuleEngineService` (pure-PHP) + tests with golden cases.
- [ ] `PromptRenderer` + version `plan_initial.v1`.
- [ ] `PlanGenerationOrchestrator` end-to-end.
- [ ] `PlanValidator` with safety rules.
- [ ] AI endpoints + Coach Web "Generate" button.
- [ ] Cost tracking + per-tenant rate limit.
- [ ] Smoke test: 50 generated plans across diverse intakes — all pass validator.
- **Exit criteria:** Coach clicks "Generate" → plan draft appears in < 30s; cost < $0.40; validator passes 100%.

### Step 9 — Billing (Week 11)
- [ ] Cashier install; `PricingPlan` seeded.
- [ ] Subscribe → Checkout → webhook → activation.
- [ ] `EnsureFeature`, `EnforceTenantStatus`, `EnforceClientCap` middlewares.
- [ ] `UsageMeter` increments on AI generation.
- [ ] Customer Portal link.
- [ ] Trial logic (14d coach, 7d user-Pro).
- [ ] Dunning emails.
- **Exit criteria:** End-to-end paid signup works in Stripe test mode; gating proven by tests.

### Step 10 — Notifications + Polish (Week 12)
- [ ] FCM channel + DeviceToken model + register endpoint.
- [ ] In-app notifications (Laravel default).
- [ ] Email templates (transactional via SES).
- [ ] Reminder scheduler (daily check-in, weekly weigh-in).
- [ ] Performance pass: cache today-dashboard, kill N+1.
- [ ] App Store + Play Store submission.
- **Exit criteria:** Closed beta launch (50 invited coaches).

### Step 11 — Adaptation Loop (Weeks 13–14)
- [ ] `AdaptationEngine` decision tree.
- [ ] Weekly cron job per tenant.
- [ ] Coach approval UI for replans.
- [ ] Auto-approve setting per tenant.
- **Exit criteria:** A 4-week plan replans automatically based on actual logs.

### Step 12 — Filament Admin + Analytics (Week 15)
- [ ] Filament install with admin guard + 2FA.
- [ ] CRUD resources for all key entities.
- [ ] Analytics dashboard (DAU/MAU, MRR, AI cost per tenant).
- [ ] Prompt manager (live-update LLM prompts).
- **Exit criteria:** Internal team can run platform without DB access.

---

## 10. ✅ Definition of Done (per feature)

A feature is "done" only when:
1. Migrations include rollback path; migration tested on staging.
2. Models have factories + at least one feature test for happy path + 2 edge cases.
3. Endpoints documented in OpenAPI spec (auto-generated, reviewed).
4. Authorization policy in place + tested.
5. Tenant scoping verified by isolation test.
6. Mobile + web consume the endpoint and pass an integration test.
7. Logs have `tenant_id` + `user_id` + `trace_id`.
8. p95 latency under target.
9. Sentry release tagged.

This plan is implementation-ready. Day 1 is migrations.
