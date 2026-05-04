# 🛠️ Fetness App — Production Blueprint v2

**Stack:** Laravel 11 · PHP 8.3 · MySQL 8 · Redis 7 · Sanctum · Cashier · Horizon · Reverb · Filament v3 · `stancl/tenancy` v3 · Flutter 3
**Tenancy mode:** Multi-database. One **Central DB** (`fetness_central`) + one **Tenant DB** per tenant (`tenant_{ulid}`).
**ID strategy:** ULIDs `CHAR(26)` PK, prefixed in API surface (`pln_01H…`).
**Timestamps:** UTC `TIMESTAMP(3)` everywhere.
**AI provider:** Primary `claude-opus-4-6`, fallback `gpt-4o`. Provider-agnostic `LlmClient` with retry/backoff/cost guard.

> **Cross-DB references.** Tenant-DB tables that point at IDs living in the Central DB (e.g. `client_profiles.user_id`) or that polymorphically reference either a central or tenant row (e.g. `plan_exercises.exercise_id` + `exercise_scope`) carry **no MySQL FK constraint** — MySQL cannot enforce referential integrity across databases. Every such column is annotated explicitly. Integrity is enforced at the application layer by FormRequest validators + service guards (`PlanBuilderService`, `LogSetAction`, `LogCustomMealAction`, `WorkoutLogService`).

---

## 1. 🗄️ FULL DATABASE DESIGN

### 1.A CENTRAL DATABASE — `fetness_central`

> Holds: identity, tenant registry, billing, global catalog, AI registry, system audit, support tickets, push routing. Never holds tenant business data.

---

#### `users`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| email | VARCHAR(190) | YES | NULL | unique |
| phone | VARCHAR(20) | YES | NULL | unique, E.164 |
| password | VARCHAR(255) | YES | NULL | bcrypt |
| name | VARCHAR(120) | NO | — | |
| locale | CHAR(5) | NO | 'en' | |
| timezone | VARCHAR(64) | NO | 'UTC' | |
| avatar_path | VARCHAR(255) | YES | NULL | S3 key |
| email_verified_at | TIMESTAMP(3) | YES | NULL | |
| phone_verified_at | TIMESTAMP(3) | YES | NULL | |
| two_factor_secret | TEXT | YES | NULL | encrypted |
| two_factor_recovery_codes | TEXT | YES | NULL | encrypted |
| last_login_at | TIMESTAMP(3) | YES | NULL | |
| last_login_ip | VARCHAR(45) | YES | NULL | |
| status | ENUM('active','locked','closed') | NO | 'active' | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(email)`, `UNIQUE(phone)`, `KEY(status)`.

---

#### `personal_access_tokens` (Sanctum)
Standard Sanctum schema. `abilities` JSON carries multi-tenant claims, e.g. `["tnt:01HABC…","role:client"]`. Tokens are 30-day TTL (see §6 Token Strategy).

---

#### `otp_codes`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| user_id | CHAR(26) | NO | — | FK users.id ON DELETE CASCADE |
| channel | ENUM('sms','email') | NO | — | |
| code_hash | CHAR(64) | NO | — | sha256 |
| purpose | ENUM('login','verify','password_reset') | NO | — | |
| expires_at | TIMESTAMP(3) | NO | — | |
| consumed_at | TIMESTAMP(3) | YES | NULL | |
| ip | VARCHAR(45) | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `KEY(user_id, purpose, consumed_at)`, `KEY(expires_at)`.

---

#### `tenants`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| slug | VARCHAR(60) | NO | — | unique |
| name | VARCHAR(120) | NO | — | |
| type | ENUM('solo_coach','gym','enterprise') | NO | 'solo_coach' | |
| subdomain | VARCHAR(60) | NO | — | unique |
| custom_domain | VARCHAR(190) | YES | NULL | unique |
| db_name | VARCHAR(64) | NO | — | physical tenant DB name |
| db_host | VARCHAR(190) | NO | — | shard host |
| logo_path | VARCHAR(255) | YES | NULL | |
| primary_color | CHAR(7) | NO | '#0EA5E9' | |
| secondary_color | CHAR(7) | NO | '#111827' | |
| font | VARCHAR(60) | NO | 'Inter' | |
| status | ENUM('trial','active','past_due','suspended','closed') | NO | 'trial' | **`past_due` included** (Fix 9) |
| trial_ends_at | TIMESTAMP(3) | YES | NULL | |
| owner_user_id | CHAR(26) | NO | — | FK users.id |
| stripe_customer_id | VARCHAR(60) | YES | NULL | |
| settings | JSON | NO | '{}' | feature flags, AI auto-approve, AI rate limit |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(slug)`, `UNIQUE(subdomain)`, `UNIQUE(custom_domain)`, `KEY(owner_user_id)`, `KEY(status)`, `KEY(stripe_customer_id)`.

---

#### `tenant_users` (membership pivot)
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| tenant_id | CHAR(26) | NO | — | FK tenants.id ON DELETE CASCADE |
| user_id | CHAR(26) | NO | — | FK users.id ON DELETE CASCADE |
| role | ENUM('owner','coach','client','staff') | NO | — | |
| status | ENUM('active','invited','suspended') | NO | 'invited' | |
| joined_at | TIMESTAMP(3) | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(tenant_id, user_id)`, `KEY(user_id)`, `KEY(tenant_id, role, status)`.

---

#### `tenant_invites`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| tenant_id | CHAR(26) | NO | — | FK tenants.id |
| email | VARCHAR(190) | NO | — | |
| role | ENUM('coach','client','staff') | NO | 'client' | |
| token | CHAR(64) | NO | — | unique, single-use |
| expires_at | TIMESTAMP(3) | NO | — | |
| accepted_at | TIMESTAMP(3) | YES | NULL | |
| invited_by | CHAR(26) | NO | — | FK users.id |
| created_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(token)`, `KEY(tenant_id, email)`.

---

#### `pricing_plans`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| slug | VARCHAR(40) | NO | — | unique |
| audience | ENUM('user','coach') | NO | — | |
| name | VARCHAR(80) | NO | — | |
| stripe_product_id | VARCHAR(60) | NO | — | |
| stripe_price_id_monthly | VARCHAR(60) | NO | — | |
| stripe_price_id_annual | VARCHAR(60) | YES | NULL | |
| monthly_price_cents | INT UNSIGNED | NO | 0 | |
| annual_price_cents | INT UNSIGNED | NO | 0 | |
| currency | CHAR(3) | NO | 'USD' | |
| features | JSON | NO | — | feature/limit map |
| trial_days | SMALLINT UNSIGNED | NO | 0 | |
| is_active | BOOL | NO | 1 | |
| sort_order | TINYINT UNSIGNED | NO | 0 | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(slug)`, `KEY(audience, is_active)`.

---

#### `subscriptions` (Cashier-extended)
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| tenant_id | CHAR(26) | YES | NULL | FK tenants.id (coach plans) |
| user_id | CHAR(26) | YES | NULL | FK users.id (B2C plans) |
| pricing_plan_id | CHAR(26) | NO | — | FK pricing_plans.id |
| type | VARCHAR(50) | NO | 'default' | Cashier |
| stripe_id | VARCHAR(60) | NO | — | unique |
| stripe_status | VARCHAR(40) | NO | — | |
| stripe_price | VARCHAR(60) | NO | — | |
| quantity | INT | NO | 1 | |
| trial_ends_at | TIMESTAMP(3) | YES | NULL | |
| ends_at | TIMESTAMP(3) | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(stripe_id)`, `KEY(tenant_id, stripe_status)`, `KEY(user_id, stripe_status)`.
**Constraint (CHECK):** `(tenant_id IS NULL) <> (user_id IS NULL)`.

---

#### `subscription_items` (Cashier)
Standard Cashier shape: `id, subscription_id, stripe_id, stripe_product, stripe_price, quantity`.

---

#### `invoices`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| stripe_id | VARCHAR(60) | NO | — | unique |
| tenant_id | CHAR(26) | YES | NULL | |
| user_id | CHAR(26) | YES | NULL | |
| amount_cents | INT UNSIGNED | NO | — | |
| currency | CHAR(3) | NO | — | |
| status | ENUM('draft','open','paid','uncollectible','void') | NO | — | |
| pdf_url | VARCHAR(255) | YES | NULL | |
| paid_at | TIMESTAMP(3) | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(stripe_id)`, `KEY(tenant_id)`, `KEY(user_id)`.

---

#### `tenant_usage_counters` (Fix 2 — split from old `usage_counters`)
> Coach-tier (B2B) usage. `tenant_id` is **NOT NULL** so the unique index is well-defined.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| tenant_id | CHAR(26) | NO | — | FK tenants.id ON DELETE CASCADE |
| scope | VARCHAR(40) | NO | — | `ai_plans`, `active_clients`, … |
| period | CHAR(7) | NO | — | `YYYY-MM` |
| count | INT UNSIGNED | NO | 0 | |
| limit_snapshot | INT | NO | -1 | -1 = unlimited at increment time |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(tenant_id, scope, period)`, `KEY(period)`.

---

#### `user_usage_counters` (Fix 2 — B2C side)
> End-user (B2C) usage. `user_id` is **NOT NULL** so the unique index is well-defined.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| user_id | CHAR(26) | NO | — | FK users.id ON DELETE CASCADE |
| scope | VARCHAR(40) | NO | — | `ai_plans`, `history_days`, … |
| period | CHAR(7) | NO | — | `YYYY-MM` |
| count | INT UNSIGNED | NO | 0 | |
| limit_snapshot | INT | NO | -1 | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(user_id, scope, period)`, `KEY(period)`.

> **Service contract.** `UsageMeter::increment($ctx, $scope)` and `UsageMeter::current($ctx, $scope)` route to the correct table based on `$ctx instanceof Tenant` vs `$ctx instanceof User`. Callers never write to either table directly.

---

#### `webhook_events`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| provider | ENUM('stripe','twilio','fcm') | NO | — | |
| event_id | VARCHAR(120) | NO | — | unique |
| type | VARCHAR(80) | NO | — | |
| payload | JSON | NO | — | |
| processed_at | TIMESTAMP(3) | YES | NULL | |
| error | TEXT | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(provider, event_id)`, `KEY(type, processed_at)`.

---

#### `support_tickets` (Fix 10 — NEW)
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| user_id | CHAR(26) | NO | — | FK users.id reporter |
| tenant_id | CHAR(26) | YES | NULL | FK tenants.id (if tenant-bound) |
| subject | VARCHAR(255) | NO | — | |
| body | TEXT | NO | — | |
| status | ENUM('open','in_progress','resolved','closed') | NO | 'open' | |
| priority | ENUM('low','medium','high','urgent') | NO | 'medium' | |
| assigned_to | CHAR(26) | YES | NULL | FK users.id internal agent |
| resolved_at | TIMESTAMP(3) | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `KEY(user_id)`, `KEY(status, priority)`, `KEY(tenant_id)`, `KEY(assigned_to, status)`.

---

#### Global Catalog (central, shared across tenants)

##### `global_exercises`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| slug | VARCHAR(120) | NO | — | unique |
| name | VARCHAR(160) | NO | — | |
| primary_muscle | VARCHAR(40) | NO | — | |
| secondary_muscles | JSON | NO | '[]' | |
| equipment | VARCHAR(40) | NO | — | |
| mechanic | ENUM('compound','isolation') | NO | — | |
| force | ENUM('push','pull','static') | NO | — | |
| level | ENUM('beginner','intermediate','advanced') | NO | — | |
| video_url | VARCHAR(255) | YES | NULL | |
| thumbnail_path | VARCHAR(255) | YES | NULL | |
| instructions | TEXT | YES | NULL | |
| injury_tags | JSON | NO | '[]' | `["shoulder","lower_back"]` |
| popularity | INT UNSIGNED | NO | 0 | |
| is_active | BOOL | NO | 1 | |

**Indexes:** `UNIQUE(slug)`, `FULLTEXT(name, primary_muscle)`, `KEY(equipment, level, is_active)`.

##### `global_foods`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| name | VARCHAR(190) | NO | — | |
| brand | VARCHAR(120) | YES | NULL | |
| serving_size_g | DECIMAL(7,2) | NO | 100 | |
| kcal | DECIMAL(7,2) | NO | 0 | per serving |
| protein_g | DECIMAL(7,2) | NO | 0 | |
| carbs_g | DECIMAL(7,2) | NO | 0 | |
| fat_g | DECIMAL(7,2) | NO | 0 | |
| fiber_g | DECIMAL(7,2) | NO | 0 | |
| sugar_g | DECIMAL(7,2) | NO | 0 | |
| sodium_mg | DECIMAL(8,2) | NO | 0 | |
| barcode | VARCHAR(20) | YES | NULL | |
| source | ENUM('curated','usda','off') | NO | 'curated' | |
| allergen_tags | JSON | NO | '[]' | `["peanut","dairy","gluten"]` |
| diet_tags | JSON | NO | '[]' | `["halal","vegan","keto"]` |
| is_active | BOOL | NO | 1 | |

**Indexes:** `KEY(barcode)`, `FULLTEXT(name, brand)`, `KEY(is_active)`.

##### `global_food_aliases` (Fix 6 — `locale` added)
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| food_id | CHAR(26) | NO | — | FK global_foods.id |
| alias | VARCHAR(190) | NO | — | |
| locale | CHAR(5) | NO | 'en' | **NEW (Fix 6)** |

**Indexes:** `FULLTEXT(alias)`, `KEY(food_id, locale)`.

---

#### AI Registry (central)

##### `ai_prompt_versions`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| kind | ENUM('plan_initial','plan_replan','copilot') | NO | — | |
| version | VARCHAR(20) | NO | — | `v3` |
| template | LONGTEXT | NO | — | mustache/blade |
| schema | JSON | NO | — | expected JSON output schema |
| model | VARCHAR(60) | NO | 'claude-opus-4-6' | **(Fix 1)** |
| temperature | DECIMAL(3,2) | NO | 0.4 | |
| max_tokens | INT | NO | 8000 | **bumped for full-plan single-call (Fix 13)** |
| is_active | BOOL | NO | 0 | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(kind, version)`, `KEY(kind, is_active)`. Only one row per `kind` may have `is_active=1` (enforced by `PromptManager` page).

##### `ai_cost_ledger` (billing-grade aggregate)
`id, tenant_id, user_id, request_id CHAR(26), model VARCHAR(60), input_tokens INT, output_tokens INT, cost_usd DECIMAL(8,4), occurred_at TIMESTAMP(3)`.
**Indexes:** `KEY(tenant_id, occurred_at)`, `KEY(user_id, occurred_at)`, `KEY(occurred_at)`.

---

#### `audit_log`
`id, actor_user_id, tenant_id NULL, action VARCHAR(60), subject_type VARCHAR(60), subject_id CHAR(26), changes JSON, ip, user_agent, created_at`.
**Indexes:** `KEY(tenant_id, created_at)`, `KEY(actor_user_id, created_at)`.

---

#### `device_tokens` (push routing)
`id, user_id, platform ENUM('ios','android','web'), token VARCHAR(255) UNIQUE, last_seen_at`.

---

#### `notification_preferences`
`id, user_id, channel ENUM('push','email','sms','inapp'), kind VARCHAR(60), enabled BOOL`.
**Indexes:** `UNIQUE(user_id, channel, kind)`.

---

#### `stripe_connect_accounts` *(V2 — schema-only, no migration in MVP)*
> Used when coaches start billing their own clients via Stripe Connect (B2B2C revenue share).

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| tenant_id | CHAR(26) | NO | — | FK tenants.id |
| stripe_account_id | VARCHAR(60) | NO | — | unique |
| onboarding_complete | BOOL | NO | 0 | |
| charges_enabled | BOOL | NO | 0 | |
| payouts_enabled | BOOL | NO | 0 | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `UNIQUE(stripe_account_id)`, `UNIQUE(tenant_id)`. *V2 only — schema reserved.*

---

#### `jobs`, `failed_jobs`, `cache`, `sessions`
Standard Laravel/Horizon tables in central DB.

---

### 1.B TENANT DATABASE — `tenant_{ulid}`

> Created automatically by `TenantProvisioner` on tenant creation. Migrations live in `database/migrations/tenant/`.
> Every row implicitly belongs to the tenant whose DB it lives in. **No `tenant_id` column anywhere.**
> All `user_id` columns reference `central.users.id` — **app-enforced, no DB FK** (cross-DB).

---

#### `client_profiles` *(Decision 5, 6 applied)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| user_id | CHAR(26) | NO | — | central users.id — **app-enforced cross-DB** |
| coach_user_id | CHAR(26) | YES | NULL | central users.id — **app-enforced cross-DB** |
| sex | ENUM('male','female','other') | NO | — | |
| dob | DATE | NO | — | |
| height_cm | DECIMAL(5,2) | NO | — | |
| start_weight_kg | DECIMAL(6,2) | NO | — | |
| target_weight_kg | DECIMAL(6,2) | YES | NULL | |
| target_date | DATE | YES | NULL | |
| goal | ENUM('fat_loss','maintain','muscle_gain','recomp','performance') | NO | — | |
| experience | ENUM('beginner','intermediate','advanced') | NO | — | |
| training_days_per_week | TINYINT UNSIGNED | NO | 3 | |
| session_duration_min | SMALLINT UNSIGNED | NO | 60 | |
| equipment | JSON | NO | '[]' | |
| injuries | JSON | NO | '[]' | |
| diet_preference | VARCHAR(40) | NO | 'standard' | |
| allergies | JSON | NO | '[]' | |
| disliked_foods | JSON | NO | '[]' | |
| coach_notes | TEXT | YES | NULL | **quick-access summary (Decision 6)** |
| internal_tags | JSON | NO | '[]' | **e.g. `["vip","at_risk","ramadan"]` (Decision 6)** |
| status | ENUM('onboarding','active','paused','archived') | NO | 'onboarding' | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |
| deleted_at | TIMESTAMP(3) | YES | NULL | **soft delete (Decision 5)** |

**Indexes:** `UNIQUE(user_id)`, `KEY(coach_user_id, status)`, `KEY(status, deleted_at)`.

---

#### `client_notes` (Improvement 1 — NEW)
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| client_profile_id | CHAR(26) | NO | — | FK client_profiles.id ON DELETE CASCADE |
| author_user_id | CHAR(26) | NO | — | central users.id — **app-enforced cross-DB** |
| body | TEXT | NO | — | |
| is_pinned | BOOL | NO | 0 | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `KEY(client_profile_id, is_pinned, created_at DESC)`.

> `client_profiles.coach_notes` remains as a fast-read summary; `client_notes` holds the timeline.

---

#### `exercises` (tenant-custom; merged with central `global_exercises` at read time)
Same shape as `global_exercises` (slug uniqueness scoped to tenant DB), plus `created_by_user_id CHAR(26)` (**app-enforced cross-DB**). **Indexes:** `UNIQUE(slug)`, `FULLTEXT(name)`, `KEY(equipment, level, is_active)`.

---

#### `foods` (tenant-custom)
Same shape as `global_foods` plus `created_by_user_id CHAR(26)` (**app-enforced cross-DB**). **Indexes:** `KEY(barcode)`, `FULLTEXT(name, brand)`, `KEY(is_active)`.

---

#### `plans` *(Decision 5, Fix 8, Fix 9 applied)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| client_profile_id | CHAR(26) | NO | — | FK client_profiles |
| parent_plan_id | CHAR(26) | YES | NULL | FK plans (versioning) |
| version | INT UNSIGNED | NO | 1 | |
| status | ENUM('draft','pending_review','approved','active','archived','rejected') | NO | 'draft' | **`rejected` included (Fix 9)** |
| source | ENUM('manual','ai','template') | NO | 'manual' | |
| starts_on | DATE | NO | — | |
| ends_on | DATE | NO | — | |
| weeks | TINYINT UNSIGNED | NO | 4 | |
| notes | TEXT | YES | NULL | |
| generated_by_user_id | CHAR(26) | NO | — | central users.id — **app-enforced cross-DB** |
| approved_by_user_id | CHAR(26) | YES | NULL | central users.id — **app-enforced cross-DB** |
| approved_at | TIMESTAMP(3) | YES | NULL | |
| activated_at | TIMESTAMP(3) | YES | NULL | **(Fix 8)** |
| ai_request_id | CHAR(26) | YES | NULL | FK ai_generation_requests.id |
| ai_meta | JSON | YES | NULL | model, prompt_version, cost |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |
| deleted_at | TIMESTAMP(3) | YES | NULL | **soft delete (Decision 5)** |

**Indexes:** `KEY(client_profile_id, status, starts_on DESC)`, `KEY(status, activated_at)`, `KEY(parent_plan_id)`, `KEY(deleted_at)`.

---

#### `plan_workout_days` *(Decision 7 — multiple sessions per day)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| plan_id | CHAR(26) | NO | — | FK plans |
| week_index | TINYINT UNSIGNED | NO | — | 0-based |
| day_of_week | TINYINT UNSIGNED | NO | — | 0-6 (Mon=0) |
| session_order | TINYINT UNSIGNED | NO | 1 | **NEW — supports AM/PM (Decision 7)** |
| session_label | VARCHAR(60) | YES | NULL | **NEW — e.g. "Morning Session" (Improvement 3)** |
| title | VARCHAR(120) | NO | — | |
| focus | VARCHAR(60) | NO | — | |
| notes | TEXT | YES | NULL | |

**Indexes:** `KEY(plan_id, week_index, day_of_week, session_order)` (**non-unique — Decision 7**).

---

#### `plan_exercises`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| plan_workout_day_id | CHAR(26) | NO | — | FK |
| exercise_id | CHAR(26) | NO | — | global OR tenant — **app-enforced cross-DB (Fix 3)** |
| exercise_scope | ENUM('global','tenant') | NO | 'global' | **(Fix 4)** |
| order | TINYINT UNSIGNED | NO | 1 | |
| target_sets | TINYINT UNSIGNED | NO | 3 | |
| target_reps | VARCHAR(15) | NO | '8-10' | regex `\d+(-\d+)?|AMRAP` |
| target_weight_kg | DECIMAL(6,2) | YES | NULL | |
| target_rpe | TINYINT UNSIGNED | YES | NULL | |
| target_rest_sec | SMALLINT UNSIGNED | NO | 90 | |
| notes | TEXT | YES | NULL | |
| superset_group_id | CHAR(26) | YES | NULL | |
| is_optional | BOOL | NO | 0 | |

**Indexes:** `KEY(plan_workout_day_id, order)`, `KEY(exercise_id, exercise_scope)`.

---

#### `plan_meal_days`
`id, plan_id, week_index, day_of_week, total_kcal DECIMAL(7,2), total_protein_g, total_carbs_g, total_fat_g`.
**Indexes:** `UNIQUE(plan_id, week_index, day_of_week)`.

---

#### `plan_meals`
`id, plan_meal_day_id, slot ENUM('breakfast','snack1','lunch','snack2','dinner','pre','post'), title VARCHAR(120), target_kcal, target_protein_g, target_carbs_g, target_fat_g`.
**Indexes:** `KEY(plan_meal_day_id)`.

---

#### `plan_meal_items`
`id, plan_meal_id, food_id CHAR(26), food_scope ENUM('global','tenant') NOT NULL DEFAULT 'global' /* Fix 4; app-enforced cross-DB Fix 3 */, quantity_g DECIMAL(7,2), kcal, protein_g, carbs_g, fat_g, order TINYINT`.
**Indexes:** `KEY(plan_meal_id, order)`, `KEY(food_id, food_scope)`.

---

#### `plan_templates`
`id, name VARCHAR(120), body JSON (full snapshot), is_global BOOL, created_by_user_id CHAR(26) /* app-enforced cross-DB */, created_at`.

---

#### `workout_sessions` *(Decision 5 applied)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| client_profile_id | CHAR(26) | NO | — | |
| plan_workout_day_id | CHAR(26) | YES | NULL | |
| started_at | TIMESTAMP(3) | NO | — | |
| ended_at | TIMESTAMP(3) | YES | NULL | |
| total_volume_kg | DECIMAL(10,2) | NO | 0 | |
| perceived_effort | TINYINT | YES | NULL | 1-10 |
| notes | TEXT | YES | NULL | |
| source | ENUM('app','wearable','manual') | NO | 'app' | |
| idempotency_key | CHAR(36) | YES | NULL | |
| deleted_at | TIMESTAMP(3) | YES | NULL | **soft delete (Decision 5)** |

**Indexes:** `KEY(client_profile_id, started_at DESC)`, `UNIQUE(idempotency_key)`, `KEY(deleted_at)`.

---

#### `set_logs`
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| workout_session_id | CHAR(26) | NO | — | |
| plan_exercise_id | CHAR(26) | YES | NULL | |
| exercise_id | CHAR(26) | NO | — | global OR tenant — **app-enforced cross-DB (Fix 3)** |
| exercise_scope | ENUM('global','tenant') | NO | 'global' | **(Fix 4)** |
| set_index | TINYINT UNSIGNED | NO | 1 | |
| reps | SMALLINT UNSIGNED | NO | 0 | |
| weight_kg | DECIMAL(6,2) | NO | 0 | |
| rpe | TINYINT UNSIGNED | YES | NULL | |
| is_warmup | BOOL | NO | 0 | |
| completed_at | TIMESTAMP(3) | NO | — | |
| idempotency_key | CHAR(36) | YES | NULL | |

**Indexes:** `KEY(workout_session_id, set_index)`, `KEY(exercise_id, exercise_scope, completed_at)`, `UNIQUE(idempotency_key)`.

---

#### `workout_swaps`
`id, workout_session_id, original_exercise_id, original_exercise_scope ENUM('global','tenant'), replacement_exercise_id, replacement_exercise_scope ENUM('global','tenant'), reason VARCHAR(160), created_at`.

---

#### `meal_logs` *(Fix 5 — `idempotency_key` confirmed)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| client_profile_id | CHAR(26) | NO | — | |
| logged_at | TIMESTAMP(3) | NO | — | |
| slot | ENUM('breakfast','snack1','lunch','snack2','dinner','pre','post') | NO | — | |
| plan_meal_id | CHAR(26) | YES | NULL | |
| source | ENUM('plan','manual','barcode','photo') | NO | 'manual' | |
| notes | TEXT | YES | NULL | |
| total_kcal | DECIMAL(7,2) | NO | 0 | |
| total_protein_g | DECIMAL(7,2) | NO | 0 | |
| total_carbs_g | DECIMAL(7,2) | NO | 0 | |
| total_fat_g | DECIMAL(7,2) | NO | 0 | |
| idempotency_key | CHAR(36) | YES | NULL | **(Fix 5)** |
| created_at | TIMESTAMP(3) | NO | CURRENT | |
| updated_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `KEY(client_profile_id, logged_at)`, `UNIQUE(idempotency_key)`.

---

#### `meal_log_items`
`id, meal_log_id, food_id CHAR(26), food_scope ENUM('global','tenant') NOT NULL DEFAULT 'global' /* Fix 4; app-enforced cross-DB Fix 3 */, quantity_g, kcal, protein_g, carbs_g, fat_g`.
**Indexes:** `KEY(meal_log_id)`, `KEY(food_id, food_scope)`.

---

#### `water_logs`
`id, client_profile_id, logged_at TIMESTAMP(3), amount_ml SMALLINT UNSIGNED`.
**Indexes:** `KEY(client_profile_id, logged_at)`.

---

#### `weigh_ins`
`id, client_profile_id, logged_at, weight_kg DECIMAL(6,2), body_fat_pct DECIMAL(4,1) NULL, source ENUM('app','wearable','manual')`.
**Indexes:** `KEY(client_profile_id, logged_at DESC)`.

---

#### `measurements`
`id, client_profile_id, logged_at, site ENUM('waist','hips','chest','arm_left','arm_right','thigh_left','thigh_right','neck'), value_cm DECIMAL(5,2)`.
**Indexes:** `KEY(client_profile_id, site, logged_at)`.

---

#### `progress_photos` *(Fix 7 confirmed)*
`id, client_profile_id, taken_at, angle ENUM('front','side','back'), s3_path VARCHAR(255), is_private BOOL NOT NULL DEFAULT 1`.

---

#### `personal_records` *(Decision 3 — insert history, Fix 9 — `max_weight` added)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| client_profile_id | CHAR(26) | NO | — | |
| exercise_id | CHAR(26) | NO | — | global or tenant — **app-enforced cross-DB** |
| exercise_scope | ENUM('global','tenant') | NO | 'global' | |
| kind | ENUM('1rm','max_reps','max_volume','max_weight') | NO | — | **`max_weight` added (Fix 9)** |
| value | DECIMAL(8,2) | NO | — | |
| achieved_at | TIMESTAMP(3) | NO | — | |
| set_log_id | CHAR(26) | YES | NULL | |

**Indexes:** `KEY(client_profile_id, exercise_id, kind, achieved_at DESC)` — **history query (Decision 3)**.
**No UNIQUE constraint** — every PR is INSERT-only; "current PR" is a `MAX(value)` query.

---

#### `client_metrics_weekly` (materialized rollup)
`id, client_profile_id, week_start DATE, workouts_done, workouts_planned, kcal_avg, kcal_target, protein_avg_g, adherence_pct DECIMAL(5,2), weight_kg DECIMAL(6,2), weight_delta_kg DECIMAL(5,2), recomputed_at`.
**Indexes:** `UNIQUE(client_profile_id, week_start)`.

---

#### `chat_threads` *(Decision 4 + Decision 5)*
```sql
-- MVP: 1:1 coach ↔ client only.
-- V2: Group chat requires schema revision (introduce chat_thread_members table).
```

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| coach_user_id | CHAR(26) | NO | — | central users.id — **app-enforced cross-DB** |
| client_user_id | CHAR(26) | NO | — | central users.id — **app-enforced cross-DB** |
| last_message_at | TIMESTAMP(3) | YES | NULL | |
| unread_for_coach | SMALLINT | NO | 0 | |
| unread_for_client | SMALLINT | NO | 0 | |
| archived_at | TIMESTAMP(3) | YES | NULL | |
| deleted_at | TIMESTAMP(3) | YES | NULL | **soft delete (Decision 5)** |

**Indexes:** `UNIQUE(coach_user_id, client_user_id)`, `KEY(last_message_at DESC)`, `KEY(deleted_at)`.

---

#### `messages`
`id, thread_id, sender_user_id /* app-enforced cross-DB */, body TEXT, attachment_path VARCHAR(255) NULL, attachment_mime VARCHAR(60) NULL, voice_duration_sec SMALLINT NULL, sent_at TIMESTAMP(3), read_at TIMESTAMP(3) NULL, deleted_at TIMESTAMP(3) NULL`.
**Indexes:** `KEY(thread_id, sent_at DESC)`.

---

#### `ai_generation_requests` *(Fix 9 — `validation_failed` confirmed; Improvement 2 — `retry_count`)*
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id | CHAR(26) | NO | — | PK |
| client_profile_id | CHAR(26) | YES | NULL | |
| kind | ENUM('plan_initial','plan_replan','copilot') | NO | — | |
| input_hash | CHAR(64) | NO | — | sha256 canonical input |
| status | ENUM('queued','running','succeeded','failed','validation_failed') | NO | 'queued' | **(Fix 9)** |
| model | VARCHAR(60) | NO | — | e.g. `claude-opus-4-6` |
| prompt_version | VARCHAR(20) | NO | — | |
| input | JSON | NO | — | |
| output | JSON | YES | NULL | |
| input_tokens | INT | NO | 0 | |
| output_tokens | INT | NO | 0 | |
| cost_usd | DECIMAL(8,4) | NO | 0 | |
| latency_ms | INT | YES | NULL | |
| error | TEXT | YES | NULL | |
| retry_count | TINYINT | NO | 0 | **max 2 before `validation_failed` (Improvement 2)** |
| requested_by | CHAR(26) | NO | — | central users.id — **app-enforced cross-DB** |
| completed_at | TIMESTAMP(3) | YES | NULL | |
| created_at | TIMESTAMP(3) | NO | CURRENT | |

**Indexes:** `KEY(status, created_at)`, `KEY(input_hash, created_at)`, `KEY(client_profile_id, kind)`.

---

#### `notifications` (Laravel default — per-tenant inbox)
`id, type, notifiable_type, notifiable_id (user_id), data JSON, read_at, created_at, updated_at`.
**Indexes:** `KEY(notifiable_id, read_at, created_at)`.

---

#### V2 Tables — Schema-Only (no migration in MVP)

```sql
-- check_in_forms (tenant DB) — V2
-- Coach-defined weekly/biweekly client questionnaires.
id CHAR(26) PK,
plan_id CHAR(26) NULL,
title VARCHAR(160),
questions JSON,                      -- structured form schema
frequency ENUM('weekly','biweekly') NOT NULL,
is_active BOOL NOT NULL DEFAULT 1,
created_by_user_id CHAR(26),         -- app-enforced cross-DB
created_at TIMESTAMP(3),
updated_at TIMESTAMP(3)
-- KEY(is_active), KEY(plan_id)

-- check_in_responses (tenant DB) — V2
id CHAR(26) PK,
form_id CHAR(26),
client_profile_id CHAR(26),
answers JSON,
submitted_at TIMESTAMP(3)
-- KEY(form_id, submitted_at), KEY(client_profile_id, submitted_at)

-- program_cohorts (tenant DB) — V2
id CHAR(26) PK,
plan_template_id CHAR(26),
name VARCHAR(160),
coach_user_id CHAR(26),              -- app-enforced cross-DB
starts_on DATE,
ends_on DATE,
max_members SMALLINT UNSIGNED
-- KEY(starts_on)

-- cohort_members (tenant DB) — V2
id CHAR(26) PK,
cohort_id CHAR(26),
client_profile_id CHAR(26),
joined_at TIMESTAMP(3),
status ENUM('active','dropped') NOT NULL DEFAULT 'active'
-- UNIQUE(cohort_id, client_profile_id)
```

---

### 1.C MIGRATION FILE LAYOUT

```
database/migrations/
├── central/
│   ├── 2026_05_01_000001_create_users_table.php
│   ├── 2026_05_01_000002_create_personal_access_tokens_table.php
│   ├── 2026_05_01_000003_create_otp_codes_table.php
│   ├── 2026_05_01_000010_create_tenants_table.php
│   ├── 2026_05_01_000011_create_tenant_users_table.php
│   ├── 2026_05_01_000012_create_tenant_invites_table.php
│   ├── 2026_05_01_000020_create_pricing_plans_table.php
│   ├── 2026_05_01_000021_create_subscriptions_table.php
│   ├── 2026_05_01_000022_create_subscription_items_table.php
│   ├── 2026_05_01_000023_create_invoices_table.php
│   ├── 2026_05_01_000024_create_tenant_usage_counters_table.php   # (Fix 2)
│   ├── 2026_05_01_000025_create_user_usage_counters_table.php     # (Fix 2)
│   ├── 2026_05_01_000026_create_webhook_events_table.php
│   ├── 2026_05_01_000030_create_global_exercises_table.php
│   ├── 2026_05_01_000031_create_global_foods_table.php
│   ├── 2026_05_01_000032_create_global_food_aliases_table.php     # (Fix 6 locale)
│   ├── 2026_05_01_000040_create_ai_prompt_versions_table.php      # (Fix 1 model default)
│   ├── 2026_05_01_000041_create_ai_cost_ledger_table.php
│   ├── 2026_05_01_000050_create_audit_log_table.php
│   ├── 2026_05_01_000051_create_device_tokens_table.php
│   ├── 2026_05_01_000052_create_notification_preferences_table.php
│   └── 2026_05_01_000060_create_support_tickets_table.php         # (Fix 10, Improvement 7)
└── tenant/
    ├── 2026_05_01_100001_create_client_profiles_table.php          # (Decisions 5, 6)
    ├── 2026_05_01_100002_create_client_notes_table.php             # (Improvement 1)
    ├── 2026_05_01_100003_create_exercises_table.php
    ├── 2026_05_01_100004_create_foods_table.php
    ├── 2026_05_01_100010_create_plans_table.php                    # (Decisions 5, Fix 8/9)
    ├── 2026_05_01_100011_create_plan_workout_days_table.php        # (Decision 7)
    ├── 2026_05_01_100012_create_plan_exercises_table.php           # (Fix 4 scope)
    ├── 2026_05_01_100013_create_plan_meal_days_table.php
    ├── 2026_05_01_100014_create_plan_meals_table.php
    ├── 2026_05_01_100015_create_plan_meal_items_table.php          # (Fix 4 scope)
    ├── 2026_05_01_100016_create_plan_templates_table.php
    ├── 2026_05_01_100020_create_workout_sessions_table.php         # (Decision 5)
    ├── 2026_05_01_100021_create_set_logs_table.php                 # (Fix 4 scope)
    ├── 2026_05_01_100022_create_workout_swaps_table.php
    ├── 2026_05_01_100030_create_meal_logs_table.php                # (Fix 5 idem key)
    ├── 2026_05_01_100031_create_meal_log_items_table.php           # (Fix 4 scope)
    ├── 2026_05_01_100032_create_water_logs_table.php
    ├── 2026_05_01_100040_create_weigh_ins_table.php
    ├── 2026_05_01_100041_create_measurements_table.php
    ├── 2026_05_01_100042_create_progress_photos_table.php          # (Fix 7)
    ├── 2026_05_01_100043_create_personal_records_table.php         # (Decision 3, Fix 9)
    ├── 2026_05_01_100044_create_client_metrics_weekly_table.php
    ├── 2026_05_01_100050_create_chat_threads_table.php             # (Decisions 4, 5)
    ├── 2026_05_01_100051_create_messages_table.php
    ├── 2026_05_01_100060_create_ai_generation_requests_table.php   # (Fix 9, Improvement 2)
    └── 2026_05_01_100070_create_notifications_table.php
```

> V2 migrations (`check_in_forms`, `check_in_responses`, `program_cohorts`, `cohort_members`, `stripe_connect_accounts`) are **not** part of MVP — schema documented above for forward planning.

---

## 2. 🧱 LARAVEL MODULE STRUCTURE

> Layout: `app/Domains/{Module}/{Models|Services|Actions|Http/{Controllers,Requests,Resources}|DTOs|Events|Listeners|Policies|Jobs|Providers}`. Routes: `routes/api/{module}.php`. All Domain providers booted by `App\Providers\DomainServiceProvider`.

---

### 2.1 Identity (Central)

**Models:** `User`, `OtpCode`, `PersonalAccessToken`, `DeviceToken`, `NotificationPreference`.
**Services:** `AuthService`, `ProfileService`, `OtpService`, `TwoFactorService`.
**Actions:** `RegisterUserAction`, `LoginWithPasswordAction`, `LoginWithOtpAction`, `RequestOtpAction`, `VerifyOtpAction`, `LogoutAction`, `ChangePasswordAction`, `EnableTwoFactorAction`.
**Controllers:** `Api\AuthController`, `Api\MeController`, `Api\OtpController`, `Api\DeviceController`, `Api\NotificationController`.
**Requests:** `RegisterRequest`, `LoginRequest`, `OtpRequest`, `VerifyOtpRequest`, `UpdateProfileRequest`, `ChangePasswordRequest`, `RegisterDeviceRequest`.
**Resources:** `UserResource`, `MeResource`, `TokenResource`.
**DTOs:** `RegisterDto`, `LoginDto`, `OtpDto`.
**Events:** `UserRegistered`, `UserLoggedIn`, `OtpRequested`.
**Listeners:** `SendWelcomeEmail`, `SendOtpSms`.
**Policies:** `UserPolicy`.

---

### 2.2 Tenancy (Central + Bootstrapping)

**Models:** `Tenant`, `TenantUser`, `TenantInvite`.
**Services:** `TenantProvisioner`, `TenantContextResolver`, `BrandingService`, `InviteService`, `TenantMembershipService`.
**Actions:** `CreateTenantAction`, `ProvisionTenantDatabaseAction`, `InviteUserAction`, `AcceptInviteAction`, `SwitchTenantAction`.
**Controllers:** `Api\TenantController`, `Api\TenantInviteController`, `Api\TenantSwitchController`.
**Requests:** `CreateTenantRequest`, `UpdateBrandingRequest`, `InviteUserRequest`, `AcceptInviteRequest`.
**Resources:** `TenantResource`, `TenantInviteResource`, `TenantBrandingResource`.
**Middleware:** `ResolveTenant`, `EnsureTenantActive`, `RequireRole`.
**Events:** `TenantCreated`, `TenantSuspended`.
**Listeners:** `BootstrapTenantDatabase`, `SeedDefaultTemplates`.
**Jobs:** `MigrateTenantDatabaseJob`, `BackfillTenantDataJob`.

---

### 2.3 Catalog

**Models:** `GlobalExercise`, `GlobalFood`, `GlobalFoodAlias` (central) · `Exercise`, `Food` (tenant).
**Services:** `CatalogSearchService` (merges global + tenant), `FoodMatchService`, `BarcodeLookupService`, `EmbeddingService`.
**Actions:** `CreateCustomExerciseAction`, `CreateCustomFoodAction`, `LookupBarcodeAction`.
**Controllers:** `Api\ExerciseController`, `Api\FoodController`, `Api\BarcodeController`.
**Requests:** `SearchExercisesRequest`, `SearchFoodsRequest`, `CreateExerciseRequest`, `CreateFoodRequest`.
**Resources:** `ExerciseResource`, `FoodResource`, `FoodAliasResource`.

---

### 2.4 Plan (Tenant)

**Models:** `Plan`, `PlanWorkoutDay`, `PlanExercise`, `PlanMealDay`, `PlanMeal`, `PlanMealItem`, `PlanTemplate`, `ClientNote`.
**Services:** `PlanBuilderService`, `PlanApprovalService`, `PlanTemplateService`, `PlanCloneService`, `ClientNoteService`.
**Actions:** `CreateDraftPlanAction`, `AddWorkoutDayAction`, `AddExerciseToDayAction`, `AddMealItemAction`, `ApprovePlanAction`, `ActivatePlanAction`, `RejectPlanAction`, `ClonePlanAction`, `SaveAsTemplateAction`, `ApplyTemplateAction`, `AddClientNoteAction`.
**Controllers:** `Api\PlanController`, `Api\PlanWorkoutDayController`, `Api\PlanExerciseController`, `Api\PlanMealController`, `Api\PlanTemplateController`, `Api\PlanLifecycleController`, `Api\ClientNoteController`.
**Requests:** `CreatePlanRequest`, `UpdatePlanRequest`, `AddPlanExerciseRequest` (validates `exercise_scope`), `UpdatePlanExerciseRequest`, `AddPlanMealItemRequest` (validates `food_scope`), `RegeneratePlanRequest`, `ApprovePlanRequest`, `CreateClientNoteRequest`.
**Resources:** `PlanResource`, `PlanTreeResource`, `PlanWorkoutDayResource`, `PlanExerciseResource`, `PlanMealResource`, `PlanMealItemResource`, `PlanTemplateResource`, `ClientNoteResource`.
**Events:** `PlanDrafted`, `PlanApproved`, `PlanActivated`, `PlanRejected`.
**Policies:** `PlanPolicy`, `ClientNotePolicy`.

---

### 2.5 Workout (Tenant)

**Models:** `WorkoutSession`, `SetLog`, `WorkoutSwap`.
**Services:** `WorkoutLogService`, `AdherenceCalculator`, `PrDetector`, `VolumeAggregator`.
**Actions:** `StartSessionAction`, `LogSetAction` (validates `exercise_scope`), `EndSessionAction`, `SwapExerciseAction`.
**Controllers:** `Api\WorkoutSessionController`, `Api\SetLogController`, `Api\WorkoutSwapController`.
**Requests:** `StartSessionRequest`, `EndSessionRequest`, `LogSetRequest`, `SwapExerciseRequest`.
**Resources:** `WorkoutSessionResource`, `SetLogResource`, `WorkoutSwapResource`.
**Events:** `SessionStarted`, `SetLogged`, `SessionEnded`.
**Listeners:** `DetectPrListener`, `RecomputeAdherenceListener`.
**Policies:** `WorkoutSessionPolicy`.

---

### 2.6 Nutrition (Tenant)

**Models:** `MealLog`, `MealLogItem`, `WaterLog`.
**Services:** `MealLogService`, `MacroCalculator`, `WaterLogService`.
**Actions:** `LogMealFromPlanAction`, `LogCustomMealAction` (validates `food_scope`), `LogBarcodeMealAction`, `UpdateMealAction`, `DeleteMealAction`, `LogWaterAction`.
**Controllers:** `Api\MealController`, `Api\WaterController`, `Api\NutritionSummaryController`.
**Requests:** `LogMealRequest`, `UpdateMealRequest`, `LogWaterRequest`, `NutritionSummaryRequest`.
**Resources:** `MealLogResource`, `MealLogItemResource`, `WaterLogResource`, `NutritionSummaryResource`.
**Events:** `MealLogged`, `MealUpdated`.
**Policies:** `MealLogPolicy`.

---

### 2.7 Progress (Tenant)

**Models:** `WeighIn`, `Measurement`, `ProgressPhoto`, `PersonalRecord`, `ClientMetricsWeekly`.
**Services:** `ProgressService`, `ChartSeriesService`, `S3PresignService`.
**Actions:** `LogWeighInAction`, `LogMeasurementAction`, `RequestPhotoUploadAction`, `ConfirmPhotoUploadAction`.
**Controllers:** `Api\WeighInController`, `Api\MeasurementController`, `Api\ProgressPhotoController`, `Api\ProgressSeriesController`, `Api\PersonalRecordController`.
**Requests:** `LogWeighInRequest`, `LogMeasurementRequest`, `PresignPhotoRequest`, `ConfirmPhotoRequest`, `SeriesQueryRequest`.
**Resources:** `WeighInResource`, `MeasurementResource`, `ProgressPhotoResource`, `PersonalRecordResource`, `SeriesResource`.
**Events:** `WeighInRecorded`.

---

### 2.8 Messaging (Tenant)

**Models:** `ChatThread`, `Message`.
**Services:** `MessagingService`, `ThreadService`, `AttachmentPresignService`.
**Actions:** `SendMessageAction`, `MarkThreadReadAction`, `RequestAttachmentUploadAction`.
**Controllers:** `Api\ChatThreadController`, `Api\MessageController`, `Api\AttachmentController`.
**Requests:** `SendMessageRequest`, `PresignAttachmentRequest`.
**Resources:** `ChatThreadResource`, `MessageResource`.
**Broadcasting:** `Channels\ThreadChannel` (`private-thread.{id}`), `Events\MessageSent`, `Events\MessageRead`.

---

### 2.9 AI

**Models:** `AiPromptVersion` (central), `AiCostLedger` (central), `AiGenerationRequest` (tenant).
**Services:** `PlanGenerationOrchestrator`, `RuleEngineService`, `PlanValidator`, `AdaptationEngine`, `PromptRenderer`, `LlmClient`.
**Llm:** `Providers\ClaudeProvider`, `Providers\OpenAiProvider`, `LlmClient` (router with retry/backoff/cost guard).
**Actions:** `GeneratePlanAction`, `RegeneratePlanAction`, `ValidatePlanOutputAction`, `PersistDraftPlanAction`.
**Jobs:** `GeneratePlanJob`, `RunWeeklyAdaptationJob`, `MaybeReplanJob`, `SuggestCopilotJob`.
**Controllers:** `Api\AiPlanController`, `Api\AiRequestController`, `Api\AiCopilotController`.
**Requests:** `GenerateAiPlanRequest`, `RegenerateAiPlanRequest`, `CopilotSuggestRequest`.
**Resources:** `AiGenerationRequestResource`, `AiCostResource`.
**DTOs:** `PlanInputDto`, `GeneratedPlanDto`, `LlmCallDto`, `LlmResponseDto`, `FullExercisePlanSchema`, `FullMealPlanSchema`.

---

### 2.10 Billing (Central)

**Models:** `PricingPlan`, `Subscription`, `SubscriptionItem`, `Invoice`, `TenantUsageCounter`, `UserUsageCounter`, `WebhookEvent`.
**Services:** `BillingService`, `EntitlementService`, `UsageMeter` (routes Tenant ↔ User counters), `StripeService`, `DunningService`.
**Actions:** `SubscribeAction`, `SwapPlanAction`, `CancelSubscriptionAction`, `ResumeSubscriptionAction`, `OpenPortalAction`, `IncrementUsageAction`.
**Webhooks:** `Webhooks\StripeWebhookHandler` (sub-handlers per event type).
**Controllers:** `Api\BillingController`, `Api\PricingPlanController`, `StripeWebhookController`.
**Middleware:** `EnsureFeature`, `EnforceTenantStatus`, `EnforceClientCap`, `EnforceUsageLimit`.
**Requests:** `SubscribeRequest`, `SwapPlanRequest`.
**Resources:** `PricingPlanResource`, `SubscriptionResource`, `InvoiceResource`, `UsageResource`.
**Events:** `SubscriptionActivated`, `SubscriptionPastDue`, `SubscriptionCanceled`, `UsageLimitReached`.

---

### 2.11 Notification

**Models:** `DeviceToken`, `NotificationPreference`, `Notification` (per-tenant inbox).
**Services:** `NotificationDispatcher`, `ReminderScheduler`.
**Channels:** `FcmChannel`, `SesMailChannel`, `TwilioSmsChannel`, `InAppChannel`.
**Notifications:** `PlanApprovedNotification`, `PlanActivatedNotification`, `WorkoutReminderNotification`, `WeighInReminderNotification`, `MessageReceivedNotification`, `SubscriptionPastDueNotification`, `AiPlanReadyNotification`.
**Actions:** `RegisterDeviceAction`, `MarkNotificationReadAction`, `UpdatePreferencesAction`.
**Controllers:** `Api\DeviceController`, `Api\NotificationController`, `Api\NotificationPreferenceController`.

---

### 2.12 Admin (Filament v3)

**Resources:** `TenantResource`, `UserResource`, `PlanResource`, `AiGenerationRequestResource`, `SubscriptionResource`, `InvoiceResource`, `PricingPlanResource`, `AiPromptVersionResource`, `WebhookEventResource`, `SupportTicketResource`.
**Pages:** `AnalyticsDashboard`, `PromptManager`, `FeatureFlags`, `ContentModeration`, `AiCostReport`, `SupportInbox`.
**Widgets:** `MrrWidget`, `DauMauWidget`, `AiCostWidget`, `ActiveTenantsWidget`, `FailedJobsWidget`, `OpenTicketsWidget`.

---

## 3. 🔗 COMPLETE API SPEC

> **Base:** `https://api.fetnessapp.io/api/v1`
> **Auth header:** `Authorization: Bearer {sanctum_token}`
> **Tenant header:** `X-Tenant-Slug: {slug}` (multi-tenant users) — else resolved from token claim.
> **Idempotency:** `Idempotency-Key: <uuid>` required on every POST that creates a resource.
> **Pagination:** cursor — `?cursor=…&limit=20`. Response carries `meta.next_cursor`.

**Success envelope:** `{ "data": …, "meta": { "request_id": "…", "next_cursor": null } }`
**Error envelope:** `{ "error": { "code": "…", "message": "…", "details": {}, "trace_id": "…" } }`

---

### 3.1 AUTH

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | POST | `/auth/register` | none |
| 2 | POST | `/auth/login` | none |
| 3 | POST | `/auth/otp/request` | none |
| 4 | POST | `/auth/otp/verify` | none |
| 5 | POST | `/auth/password/forgot` | none |
| 6 | POST | `/auth/password/reset` | none |
| 7 | POST | `/auth/logout` | bearer |
| 8 | POST | `/auth/refresh` | bearer |

**`POST /auth/register`** *(Fix 12 — uses `tenant_invite_token`, never `tenant_slug`)*
Request:
```json
{
  "email": "user@example.com",
  "phone": "+201234567890",
  "password": "Strong#123",
  "name": "Ali Ahmed",
  "locale": "en",
  "tenant_invite_token": null
}
```
Response 201: `{ "data": { "user": { "id":"usr_01H…", "email":"…", "name":"Ali" }, "token":"1|abc…", "expires_at":"2026-06-04T00:00:00Z" } }`

**`POST /auth/login`**
Request: `{ "email":"u@x.io", "password":"Strong#1" }` *or* `{ "phone":"+20…", "password":"…" }`.
Response 200: same as register.
Errors: 401 `INVALID_CREDENTIALS`, 423 `ACCOUNT_LOCKED`, 429 `TOO_MANY_ATTEMPTS`.

**`POST /auth/otp/request`**
Request: `{ "channel":"sms", "phone":"+20…" }` (rate limit 5/min/IP). Response 202: `{ "data": { "expires_in": 300 } }`.

**`POST /auth/otp/verify`**
Request: `{ "phone":"+20…", "code":"123456" }`. Response 200: same shape as login.

**`POST /auth/logout`** Response 204. Deletes the calling token from `personal_access_tokens`.

---

### 3.2 ME / PROFILE / DEVICES

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | GET | `/me` | bearer |
| 2 | PATCH | `/me` | bearer |
| 3 | POST | `/me/password` | bearer |
| 4 | POST | `/me/2fa/enable` | bearer |
| 5 | POST | `/me/2fa/confirm` | bearer |
| 6 | DELETE | `/me/2fa` | bearer |
| 7 | POST | `/devices` | bearer |
| 8 | DELETE | `/devices/{token}` | bearer |
| 9 | GET | `/notifications` | bearer |
| 10 | POST | `/notifications/{id}/read` | bearer |
| 11 | GET | `/notifications/preferences` | bearer |
| 12 | PATCH | `/notifications/preferences` | bearer |

**`GET /me`** Response: `{ "data": { "id":"usr_…", "email":"…", "name":"…", "locale":"en", "timezone":"Africa/Cairo", "tenants":[ { "id":"tnt_01H…", "slug":"coach-mo", "role":"client", "name":"Coach Mo" } ], "active_tenant_id":"tnt_01H…" } }`.
**`POST /devices`** Body: `{ "platform":"ios", "token":"fcm-token-…" }` → 201.

---

### 3.3 TENANCY

| # | Method | URL | Auth | Role |
|---|---|---|---|---|
| 1 | POST | `/tenants` | bearer | any |
| 2 | GET | `/tenants/current` | bearer+tenant | member |
| 3 | PATCH | `/tenants/current` | bearer+tenant | owner |
| 4 | POST | `/tenants/current/invites` | bearer+tenant | owner/coach |
| 5 | GET | `/tenants/current/invites` | bearer+tenant | owner/coach |
| 6 | DELETE | `/tenants/current/invites/{id}` | bearer+tenant | owner/coach |
| 7 | POST | `/tenants/invites/{token}/accept` | bearer | any |
| 8 | POST | `/tenants/switch` | bearer | any |
| 9 | GET | `/tenants/current/members` | bearer+tenant | owner/coach |
| 10 | DELETE | `/tenants/current/members/{user_id}` | bearer+tenant | owner |

**`POST /tenants`** Body: `{ "name":"Coach Mo", "slug":"coach-mo", "type":"solo_coach" }` → 201 `{ "data": { "id":"tnt_…", "slug":"coach-mo", "subdomain":"coach-mo", "status":"trial", "trial_ends_at":"…" } }`.
**`POST /tenants/current/invites`** Body: `{ "email":"client@x.io", "role":"client" }` → 201 `{ "data": { "id":"inv_…", "token":"…", "expires_at":"…" } }`.

---

### 3.4 CATALOG

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | GET | `/exercises?q=&muscle=&equipment=&level=&cursor=` | bearer |
| 2 | GET | `/exercises/{id}?scope=global|tenant` | bearer |
| 3 | POST | `/exercises` | coach |
| 4 | GET | `/foods?q=&cursor=` | bearer |
| 5 | GET | `/foods/{id}?scope=global|tenant` | bearer |
| 6 | GET | `/foods/barcode/{code}` | bearer |
| 7 | POST | `/foods` | coach/client |

**`GET /exercises`** returns merged global + tenant set:
```json
{ "data": [
  { "id":"exe_01H…", "scope":"global", "slug":"barbell-bench-press", "name":"Barbell Bench Press", "primary_muscle":"chest", "equipment":"barbell" },
  { "id":"exe_01H…", "scope":"tenant", "slug":"coach-mo-cable-fly",   "name":"Coach Mo Cable Fly",   "primary_muscle":"chest", "equipment":"cable" }
], "meta": { "next_cursor":"…" } }
```

**`POST /foods`** Body: `{ "name":"Koshari", "serving_size_g":300, "kcal":520, "protein_g":18, "carbs_g":85, "fat_g":12, "barcode":null }` → 201 with `scope:"tenant"`.

---

### 3.5 PLAN

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | GET | `/clients/{client_id}/plans?cursor=` | coach/self |
| 2 | POST | `/clients/{client_id}/plans` | coach |
| 3 | GET | `/plans/{id}` | coach/self |
| 4 | PATCH | `/plans/{id}` | coach |
| 5 | DELETE | `/plans/{id}` | coach |
| 6 | POST | `/plans/{id}/workout-days` | coach |
| 7 | PATCH | `/plan-workout-days/{id}` | coach |
| 8 | POST | `/plan-workout-days/{id}/exercises` | coach |
| 9 | PATCH | `/plan-exercises/{id}` | coach |
| 10 | DELETE | `/plan-exercises/{id}` | coach |
| 11 | POST | `/plans/{id}/meal-days` | coach |
| 12 | POST | `/plan-meals/{id}/items` | coach |
| 13 | POST | `/plans/{id}/regenerate` | coach |
| 14 | POST | `/plans/{id}/approve` | coach |
| 15 | POST | `/plans/{id}/activate` | coach |
| 16 | POST | `/plans/{id}/reject` | coach |
| 17 | POST | `/plans/{id}/clone` | coach |
| 18 | POST | `/plans/{id}/save-as-template` | coach |
| 19 | GET | `/templates` | coach |
| 20 | POST | `/templates/{id}/apply` | coach |
| 21 | GET | `/clients/{id}/today` | self |
| 22 | GET | `/clients/{id}/notes` | coach |
| 23 | POST | `/clients/{id}/notes` | coach |
| 24 | PATCH | `/client-notes/{id}` | coach |
| 25 | DELETE | `/client-notes/{id}` | coach |

**`POST /plans/{id}/workout-days`** Body now accepts `session_order`:
```json
{ "week_index":0, "day_of_week":1, "session_order":1, "session_label":"Morning Session", "title":"Upper A", "focus":"chest/back" }
```

**`POST /plan-workout-days/{id}/exercises`** Body:
```json
{ "exercise_id":"exe_01H…", "exercise_scope":"global", "order":1, "target_sets":4, "target_reps":"6-8", "target_rest_sec":120 }
```

**`POST /clients/{client_id}/plans`** Body: `{ "source":"ai", "starts_on":"2026-05-04", "weeks":4, "from_template_id":null, "from_plan_id":null, "config": { "split_preference":"upper_lower" } }` → 202 `{ "data": { "plan_id":"pln_…", "ai_request_id":"air_…" } }`.

**`GET /plans/{id}`** Response (full tree — note `exercise_scope` and `food_scope`):
```json
{
  "data": {
    "id":"pln_01H…","version":3,"status":"active","source":"ai",
    "starts_on":"2026-05-04","ends_on":"2026-06-01","weeks":4,
    "activated_at":"2026-05-04T07:00:00Z",
    "workout_days": [
      { "id":"pwd_…","week_index":0,"day_of_week":1,"session_order":1,"session_label":"Morning Session","title":"Upper A","focus":"chest/back",
        "exercises": [
          { "id":"pex_…","exercise_id":"exe_…","exercise_scope":"global",
            "exercise":{ "name":"Barbell Bench Press" },
            "order":1,"target_sets":4,"target_reps":"6-8","target_rest_sec":120 }
        ] }
    ],
    "meal_days": [
      { "id":"pmd_…","week_index":0,"day_of_week":1,"total_kcal":2050,
        "meals": [
          { "id":"pml_…","slot":"breakfast","title":"Oats & Eggs","target_kcal":520,
            "items":[ { "id":"pmi_…","food_id":"fod_…","food_scope":"global","quantity_g":80,"kcal":300,"protein_g":11 } ] }
        ] }
    ]
  }
}
```

**`PATCH /plan-exercises/{id}`** Body: `{ "target_sets":4, "target_reps":"8-10", "target_weight_kg":60, "target_rpe":8, "notes":"control eccentric" }`.
**`POST /plans/{id}/regenerate`** Body: `{ "scope":"workout|meal|both", "instructions":"increase upper-body volume by 15%" }` → 202.
**`POST /plans/{id}/approve`** → 200 `{ "data": { "id":"pln_…", "status":"approved" } }`.
**`POST /plans/{id}/reject`** Body: `{ "reason":"…" }` → 200 `{ "data": { "id":"pln_…", "status":"rejected" } }`.

**`POST /clients/{id}/notes`** *(Improvement 1)* Body: `{ "body":"Recovering shoulder; reduce pressing volume", "is_pinned": true }` → 201.

---

### 3.6 WORKOUT

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | POST | `/workouts/sessions` | client |
| 2 | GET | `/workouts/sessions?cursor=` | client/coach |
| 3 | GET | `/workouts/sessions/{id}` | client/coach |
| 4 | PATCH | `/workouts/sessions/{id}` (end) | client |
| 5 | DELETE | `/workouts/sessions/{id}` | client |
| 6 | POST | `/workouts/sessions/{id}/sets` | client |
| 7 | PATCH | `/set-logs/{id}` | client |
| 8 | DELETE | `/set-logs/{id}` | client |
| 9 | POST | `/workouts/sessions/{id}/swap` | client |

**`POST /workouts/sessions/{id}/sets`** Headers: `Idempotency-Key: <uuid>`. Body:
```json
{ "plan_exercise_id":"pex_…","exercise_id":"exe_…","exercise_scope":"global","set_index":1,"reps":10,"weight_kg":60,"rpe":8,"is_warmup":false,"completed_at":"2026-05-04T07:35:12Z" }
```
→ 201 `{ "data": { "id":"set_…","is_pr":true } }`.

**`PATCH /workouts/sessions/{id}`** (end) Body: `{ "ended_at":"2026-05-04T08:25:00Z","perceived_effort":7,"notes":"felt strong" }` → 200.

---

### 3.7 NUTRITION

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | POST | `/meals` | client |
| 2 | GET | `/meals?from=&to=&cursor=` | client/coach |
| 3 | GET | `/meals/{id}` | client/coach |
| 4 | PATCH | `/meals/{id}` | client |
| 5 | DELETE | `/meals/{id}` | client |
| 6 | POST | `/water` | client |
| 7 | GET | `/water?from=&to=` | client |
| 8 | GET | `/nutrition/summary?date=` | client/coach |

**`POST /meals`** Headers: `Idempotency-Key: <uuid>` *(Fix 5)*. Body:
```json
{ "logged_at":"2026-05-04T13:00:00Z","slot":"lunch","plan_meal_id":"pml_…","source":"manual",
  "items":[ { "food_id":"fod_a","food_scope":"global","quantity_g":200 },
            { "food_id":"fod_b","food_scope":"tenant","quantity_g":80 } ] }
```
→ 201 with computed totals.

**`GET /nutrition/summary?date=2026-05-04`**
```json
{ "data": { "date":"2026-05-04",
  "kcal":{ "consumed":1820,"target":2200 },
  "protein_g":{ "consumed":140,"target":165 },
  "carbs_g":{ "consumed":180,"target":230 },
  "fat_g":{ "consumed":60,"target":70 },
  "water_ml":{ "consumed":1500,"target":2500 } } }
```

---

### 3.8 PROGRESS

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | POST | `/progress/weigh-ins` | client |
| 2 | GET | `/progress/weigh-ins?from=&to=` | client/coach |
| 3 | POST | `/progress/measurements` | client |
| 4 | GET | `/progress/measurements?site=&from=&to=` | client/coach |
| 5 | POST | `/progress/photos/presign` | client |
| 6 | POST | `/progress/photos` | client |
| 7 | GET | `/progress/photos` | client/coach |
| 8 | GET | `/progress/series?metric=&from=&to=` | client/coach |
| 9 | GET | `/progress/prs?exercise_id=&kind=&history=true` | client/coach |
| 10 | GET | `/progress/weekly?from=&to=` | client/coach |

**`GET /progress/prs?exercise_id=exe_…&kind=1rm&history=true`** *(Decision 3 — history view)*
```json
{ "data": [
  { "value": 110.0, "achieved_at": "2026-04-01T07:14:00Z" },
  { "value": 112.5, "achieved_at": "2026-04-15T07:22:00Z" },
  { "value": 115.0, "achieved_at": "2026-05-04T07:35:00Z" }
] }
```

**`POST /progress/weigh-ins`** Body: `{ "logged_at":"2026-05-04T06:30:00Z","weight_kg":78.4,"body_fat_pct":18.0,"source":"app" }` → 201.
**`POST /progress/photos/presign`** Body: `{ "content_type":"image/jpeg","angle":"front" }` → `{ "data": { "upload_url":"https://s3…","s3_path":"photos/pho_…/front.jpg","expires_in":300 } }`.
**`POST /progress/photos`** Body: `{ "s3_path":"photos/…","angle":"front","taken_at":"2026-05-04…","is_private":true }` → 201.

---

### 3.9 MESSAGING

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | GET | `/chat/threads?cursor=` | bearer+tenant |
| 2 | GET | `/chat/threads/{id}/messages?cursor=` | thread member |
| 3 | POST | `/chat/threads/{id}/messages` | thread member |
| 4 | POST | `/chat/threads/{id}/read` | thread member |
| 5 | POST | `/chat/uploads/presign` | thread member |
| 6 | POST | `/broadcasting/auth` | bearer |

**`POST /chat/threads/{id}/messages`** Body: `{ "body":"Hi coach", "attachment_path":null, "voice_duration_sec":null }` → 201; broadcasts `MessageSent` on `private-thread.{id}`.

---

### 3.10 AI

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | POST | `/ai/plans/generate` | coach |
| 2 | GET | `/ai/requests/{id}` | coach |
| 3 | GET | `/ai/requests?cursor=` | coach |
| 4 | POST | `/ai/copilot/suggest` | coach |
| 5 | GET | `/ai/usage?period=YYYY-MM` | owner |

**`POST /ai/plans/generate`** Headers: `Idempotency-Key`. Body: `{ "client_id":"cli_…","kind":"plan_initial","config":{ "weeks":4,"split_preference":"upper_lower" } }` → 202 `{ "data": { "ai_request_id":"air_…" } }`.

**`GET /ai/requests/{id}`** Response (succeeded):
```json
{ "data": { "id":"air_…","status":"succeeded","plan_id":"pln_…","model":"claude-opus-4-6","cost_usd":0.18,"latency_ms":14230 } }
```
Response (validation_failed):
```json
{ "data": { "id":"air_…","status":"validation_failed","retry_count":2,"reasons":["kcal_window","allergens"] } }
```

---

### 3.11 BILLING

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | GET | `/billing/plans` | bearer |
| 2 | GET | `/billing/subscription` | owner |
| 3 | POST | `/billing/subscribe` | owner |
| 4 | POST | `/billing/portal` | owner |
| 5 | POST | `/billing/cancel` | owner |
| 6 | POST | `/billing/resume` | owner |
| 7 | GET | `/billing/invoices?cursor=` | owner |
| 8 | GET | `/billing/usage?period=YYYY-MM` | owner |
| 9 | POST | `/webhooks/stripe` | none (HMAC) |

**`POST /billing/subscribe`** Body: `{ "plan_slug":"coach_pro","billing_cycle":"monthly" }` → `{ "data": { "checkout_url":"https://checkout.stripe.com/…" } }`.
**`POST /billing/portal`** Body: `{ "return_url":"https://app…/settings" }` → `{ "data": { "portal_url":"https://billing.stripe.com/…" } }`.

---

### 3.12 SUPPORT

| # | Method | URL | Auth |
|---|---|---|---|
| 1 | POST | `/support/tickets` | bearer |
| 2 | GET | `/support/tickets?cursor=` | bearer |
| 3 | GET | `/support/tickets/{id}` | bearer (own) / admin |

**`POST /support/tickets`** Body: `{ "subject":"Plan not loading","body":"…","priority":"medium" }` → 201.

---

## 4. 🧠 AI ENGINE — REAL IMPLEMENTATION

### 4.1 Input Tables (assembled by `PlanGenerationOrchestrator::prepareInput()`)

| Source DB | Table | Fields read |
|---|---|---|
| central | `users` | id, locale, timezone |
| central | `tenants.settings` | auto_approve, preferred_split, tone, ai.rate_limit |
| central | `pricing_plans.features` | ai_plans limit |
| tenant | `client_profiles` | sex, dob → age, height_cm, goal, target_weight_kg, training_days_per_week, equipment, injuries, diet_preference, allergies, disliked_foods, internal_tags |
| tenant | `weigh_ins` | latest weight, body_fat_pct |
| tenant | `client_metrics_weekly` (last 4 rows) | adherence_pct, kcal_avg, weight_delta_kg |
| tenant | `set_logs` (last 28 days) | volume per muscle (history fingerprint) |
| central | `global_exercises` (+ tenant `exercises`) | candidate pool filtered by equipment + injuries |
| central | `global_foods` (+ tenant `foods`, + `global_food_aliases` per locale) | candidate pool filtered by allergens + diet |
| central | `ai_prompt_versions` | active prompt for `kind` |

Canonical input persisted into `ai_generation_requests.input`. `input_hash = sha256(canonical_json(input))`.

---

### 4.2 Processing Rules (`PlanGenerationOrchestrator::handle($request)`) *(Fix 13 applied)*

```
Step 0  - mark request running, capture start_ms
Step 1  - RuleEngineService::computeTargets($input)
Step 2  - RuleEngineService::buildSkeleton($input)        // 4-week skeleton
Step 3  - LLM call #1 (single call) - FULL exercise plan
Step 4  - LLM call #2 (single call) - FULL meal plan
Step 5  - FoodMatchService::matchAll($mealPlan)            // batch
Step 6  - PlanValidator::validate($plan)                   // hard rules
Step 7  - PersistDraftPlanAction (DB transaction on tenant connection)
Step 8  - UsageMeter::increment(ctx, 'ai_plans')
Step 9  - Notify coach + broadcast 'ai.plan.ready' on private-tenant.{id}
Step 10 - Mark request succeeded, persist cost_usd + latency_ms
```

**Step 1 — Targets (pure-PHP, no LLM):**
```
age = floor((today - dob) / 365)
bmr = sex == 'male'
        ? 10*weight + 6.25*height - 5*age + 5
        : 10*weight + 6.25*height - 5*age - 161
activity_factor = match training_days_per_week:
        0..2 -> 1.4
        3..4 -> 1.55
        5    -> 1.65
        6..7 -> 1.75
tdee = bmr * activity_factor

IF goal == 'fat_loss':       kcal_target = max(tdee - 500, sex=='male'?1500:1200)
ELIF goal == 'muscle_gain':  kcal_target = tdee + 300
ELIF goal == 'recomp':       kcal_target = tdee - 150
ELSE:                        kcal_target = tdee

protein_g = (goal == 'fat_loss')   ? 1.8 * weight :
            (goal == 'muscle_gain')? 2.0 * weight :
                                     1.6 * weight
fat_g     = max(0.8 * weight, (kcal_target * 0.25) / 9)
carbs_g   = max(0, (kcal_target - protein_g*4 - fat_g*9) / 4)
water_ml  = round(weight * 35)
```

**Step 2 — 4-week training skeleton:**
```
split = config.split_preference || (
   days==3 ? 'full_body' :
   days==4 ? 'upper_lower' :
   days==5 ? 'ppl_upper_lower' :
   days==6 ? 'ppl_x2' : 'full_body')

sets_per_muscle_per_week = match experience:
   beginner     -> 10
   intermediate -> 14
   advanced     -> 18

IF 'shoulder' IN injuries: drop exercises tagged shoulder_impact
IF 'lower_back' IN injuries: drop deadlift_variants, weighted_squats
IF 'knee' IN injuries: drop deep_squats, lunges_loaded

distribute volume so EACH muscle hit >= 2x/week
emit a structural skeleton: weeks=[ days=[ {muscle_target, slots, focus} ] ]
```

**Step 3 — LLM call #1 (FULL exercise plan, ONE call):** *(Fix 13)*
```
prompt = render('plan_initial.exercises.v3', {
   skeleton:           full_4_week_skeleton,
   equipment:          client.equipment,
   injuries:           client.injuries,
   level:              client.experience,
   exclude_slugs:      already_picked_in_recent_plans,
   prior_pick_slugs:   last_4w_history,
   locale:             user.locale,
   catalog_pool:       hash-or-list of allowed exercise slugs (global + tenant)
})

resp = LlmClient::complete(
   prompt,
   model='claude-opus-4-6',
   max_tokens=8000,
   response_format='json_object',
   schema=FullExercisePlanSchema
)
// Schema:
//   { "weeks": [
//       { "days": [
//           { "week_index":0, "day_of_week":1, "session_order":1,
//             "focus":"…",
//             "exercises":[ { "slug":"…", "scope":"global|tenant",
//                             "target_sets":4, "target_reps":"6-8",
//                             "target_rest_sec":120 } ] } ] } ] }

IF resp.fail OR resp.slugs not in catalog:
   fallback -> deterministic top-N by global_exercises.popularity matching skeleton filters
```

**Step 4 — LLM call #2 (FULL meal plan, ONE call):** *(Fix 13)*
```
slot_split = breakfast 25%, lunch 35%, dinner 30%, snacks 10%

prompt = render('plan_initial.meals.v3', {
   targets_4_weeks:    { week_index, day_of_week, kcal, protein_g, carbs_g, fat_g, slot_split },
   diet_preference:    client.diet_preference,
   allergies:          client.allergies,
   disliked_foods:     client.disliked_foods,
   locale:             user.locale,
   cuisine_hint:       'mena' | 'eu' | …
})

resp = LlmClient::complete(
   prompt,
   model='claude-opus-4-6',
   max_tokens=8000,
   response_format='json_object',
   schema=FullMealPlanSchema
)
// Schema:
//   { "weeks":[ { "days":[ { "week_index":0,"day_of_week":1,
//       "meals":[ { "slot":"breakfast","title":"Oats & Eggs",
//                   "items":[ {"name":"oats","quantity_g":80}, … ] } ] } ] } ] }

IF resp.fail: fallback to deterministic seed meals matching macro buckets per locale
```

**Step 5 — FoodMatchService (batch):**
```
1. Collect every (item.name, locale) across all weeks/days/meals.
2. Normalize text (lower, strip diacritics).
3. Try alias exact match (global_food_aliases per locale + tenant.food_aliases).
4. ELSE FULLTEXT match foods.name (tenant) UNION global_foods.name.
5. ELSE embedding cosine similarity (top 1, threshold 0.78).
6. ELSE mark item as "unmatched" -> validator rejects.
7. For each matched item, return { food_id, food_scope, food_row } and recompute
   item macros = food.kcal * (qty_g / food.serving_size_g) etc. — LLM macros NOT trusted.
```

**Step 6 — PlanValidator hard rules (returns ok|fail with reasons):**
```
PER DAY:
  - kcal_total IN [target*0.90, target*1.10]
  - protein_g >= target*0.90
  - fat_g     >= target*0.50
  - kcal_total >= 1200 (female) / 1500 (male)

PER ITEM:
  - food.allergen_tags ∩ client.allergies == ∅
  - food.diet_tags ⊇ client.diet_preference (when client demands halal/vegan/etc)
  - quantity_g IN [10, 1500]

PER WEEK:
  - volume per muscle IN [target*0.5, target*1.5]
  - frequency per muscle >= 2

PER EXERCISE:
  - exercise.injury_tags ∩ client.injuries == ∅
  - target_sets IN [1, 8]
  - target_reps regex: \d+|\d+-\d+|AMRAP

ON FAIL:
  - retry_count++ (Improvement 2); re-call with corrective prompt
  - max retries = 2
  - on third fail (retry_count == 2 → 3rd attempt): status='validation_failed',
    surface reasons[] to coach via AiPlanReadyNotification with kind='failed'
```

**Step 7 — Persistence (DB::transaction in TENANT connection):**
```
plans INSERT (status='pending_review', or 'approved' if tenant.settings.auto_approve)
For each week × day × session_order:
   plan_workout_days INSERT (with session_order, session_label)
   plan_exercises INSERT (with exercise_scope)
For each week × day:
   plan_meal_days INSERT
   plan_meals INSERT
   plan_meal_items INSERT (with food_scope)
ai_generation_requests UPDATE: status='succeeded', output, cost_usd, latency_ms, completed_at
```

**Step 8 — Usage:** `UsageMeter::increment($tenantOrUser, 'ai_plans')` routes to `tenant_usage_counters` or `user_usage_counters` (Fix 2).
**Step 9 — Notify:** dispatch `AiPlanReadyNotification` (push + in-app) + broadcast `ai.plan.ready` on `private-tenant.{id}`.

---

### 4.3 Output Table — Insights

Stored in `ai_generation_requests.output` (tenant DB):

```json
{
  "plan_id": "pln_01H…",
  "summary": {
    "kcal_target": 2050,
    "protein_target_g": 165,
    "fat_target_g": 70,
    "carbs_target_g": 230,
    "split": "upper_lower",
    "weeks": 4,
    "exercise_count": 56,
    "meal_count": 140
  },
  "validations_passed": ["kcal_window","macros","allergens","volume","injuries","frequency"],
  "warnings": [],
  "model": "claude-opus-4-6",
  "prompt_version": "plan_initial.v3",
  "tokens": { "input": 4120, "output": 5870 },
  "llm_calls": 2,
  "cost_usd": 0.182,
  "latency_ms": 14230
}
```

Also written: `ai_cost_ledger` row (central) `{ tenant_id, request_id, model, input_tokens, output_tokens, cost_usd, occurred_at }`.

---

### 4.4 Triggers

| Trigger | Type | Source | Job | Queue |
|---|---|---|---|---|
| Coach: Generate AI plan | event-based | `POST /ai/plans/generate` | `GeneratePlanJob` | `ai` |
| Coach: Regenerate | event-based | `POST /plans/{id}/regenerate` | `GeneratePlanJob` | `ai` |
| Weekly adaptation | cron | scheduler `0 3 * * 1` per tenant | `RunWeeklyAdaptationJob` → fan-out per active client | `ai` |
| Significant Δ weight | event-based | event `WeighInRecorded` if abs(delta_kg) > 0.7 in 7d | `MaybeReplanJob` | `ai` |
| Coach copilot suggestion | event-based | `POST /ai/copilot/suggest` | `SuggestCopilotJob` | `ai` |
| Daily catalog reindex | cron | `0 2 * * *` | `ReindexCatalogJob` | `default` |
| Weekly metrics rollup | cron | `15 3 * * 1` per tenant | `RecomputeAdherenceJob` | `default` |

`ai` queue: max 4 workers, per-tenant token bucket (default 6 req/min, configurable in `tenants.settings.ai.rate_limit`).

**Cost envelope (post-Fix 13):** 2 LLM calls per generation (was up to ~160). Target: p95 cost < $0.30 per generation; max-tokens 8000 per call.

---

## 5. 💳 SAAS IMPLEMENTATION

### 5.1 Plans Table (seeded into central `pricing_plans`)

| slug | audience | name | monthly | annual | trial | features (JSON) |
|---|---|---|---|---|---|---|
| `free` | user | Free | $0 | $0 | 0 | `{ "ai_plans_per_month":1, "history_days":7, "wearable":false, "support":"community" }` |
| `pro` | user | Pro | $9.99 | $99 | 7 | `{ "ai_plans_per_month":-1, "history_days":-1, "wearable":true, "support":"email" }` |
| `coach_starter` | coach | Coach Starter | $29 | $290 | 14 | `{ "max_clients":10, "ai_plans_per_month":30, "branding":"logo", "templates":true, "white_label":false, "group_programs":false, "support":"standard" }` |
| `coach_pro` | coach | Coach Pro | $79 | $790 | 14 | `{ "max_clients":40, "ai_plans_per_month":200, "branding":"full", "templates":true, "white_label":false, "group_programs":true, "support":"standard" }` |
| `coach_studio` | coach | Coach Studio | $199 | $1990 | 14 | `{ "max_clients":150, "ai_plans_per_month":1000, "branding":"full", "templates":true, "white_label":true, "group_programs":true, "multi_coach":true, "support":"priority" }` |

> Convention: `-1` = unlimited; numeric = hard cap.

### 5.2 Feature Limits (canonical schema)

```json
{
  "max_clients": 40,
  "ai_plans_per_month": 200,
  "history_days": -1,
  "branding": "full",
  "white_label": false,
  "templates": true,
  "group_programs": true,
  "multi_coach": false,
  "wearable": true,
  "in_app_billing": false,
  "support": "standard"
}
```

### 5.3 Middleware Logic

#### `EnsureFeature($feature)` — used as `->middleware('feature:ai_plans')`

```
function handle(Request $r, Closure $next, string $feature):
    $ctx = (tenant_id present) ? Tenant : User
    $sub = $ctx->activeSubscription()  // null on free
    $plan = $sub?->pricingPlan ?? PricingPlan::free()
    $val = $plan->features[$feature] ?? false

    IF $val === false OR $val === null:
        abort(403, code='FEATURE_NOT_INCLUDED', details=['feature'=>$feature, 'plan'=>$plan->slug])

    IF is_numeric($val):
        IF $val == -1: return $next($r)              // unlimited
        $period = now()->format('Y-m')
        // Fix 2: route to correct counter table
        $used = $ctx instanceof Tenant
              ? TenantUsageCounter::firstOrCreate(['tenant_id'=>$ctx->id, 'scope'=>$feature, 'period'=>$period])->count
              : UserUsageCounter::firstOrCreate(['user_id'=>$ctx->id,    'scope'=>$feature, 'period'=>$period])->count

        IF $used >= $val:
            abort(429, code='USAGE_LIMIT_REACHED',
                  details=['feature'=>$feature,'limit'=>$val,'used'=>$used,'reset_at'=>endOfMonth()])

        $r->attributes->set('_usage_to_increment', [
            'ctx'=>$ctx, 'scope'=>$feature, 'period'=>$period, 'limit'=>$val
        ])

    $response = $next($r)
    IF $response->getStatusCode() < 300 AND $r->attributes->has('_usage_to_increment'):
        UsageMeter::increment(...$r->attributes->get('_usage_to_increment'))
    return $response
```

#### `checkPlan($featureKey)` (used inside services / Livewire)

```
function checkPlan(Tenant|User $ctx, string $featureKey): bool {
    $plan = $ctx->activeSubscription()?->pricingPlan ?? PricingPlan::free();
    $v = $plan->features[$featureKey] ?? false;
    return $v === true OR $v === -1 OR (is_string($v) AND $v !== 'none')
        OR (is_numeric($v) AND $v > 0);
}
```

#### `checkLimit($scope, $proposedAdd = 1)`

```
function checkLimit(Tenant|User $ctx, string $scope, int $add = 1): array {
    $plan = $ctx->activeSubscription()?->pricingPlan ?? PricingPlan::free();
    $limit = $plan->features[$scope] ?? 0;
    if ($limit === -1) return ['ok'=>true,'remaining'=>PHP_INT_MAX];
    $used = UsageMeter::current($ctx, $scope);   // routes Tenant/User
    return [
        'ok'        => ($used + $add) <= $limit,
        'limit'     => $limit,
        'used'      => $used,
        'remaining' => max(0, $limit - $used),
    ];
}
```

#### `EnforceTenantStatus`
Blocks all writes when `tenants.status IN ('past_due','suspended','closed')`. Reads allowed for `past_due` (with banner), denied for `suspended/closed`.

#### `EnforceClientCap`
Hooked on `POST /tenants/current/invites` (role=client) and on `POST /tenants/invites/{token}/accept`:
```
$count = TenantUser::where('tenant_id',$tid)->where('role','client')->where('status','active')->count();
if ($count >= $features['max_clients']) abort(403, 'CLIENT_CAP_REACHED');
```

### 5.4 Stripe Lifecycle

```
Subscribe   POST /billing/subscribe -> Cashier ensureCustomer -> Checkout Session
            -> webhook customer.subscription.created -> set tenant.status='active'

Renewal     webhook invoice.paid -> create invoices row

Failed pay  webhook invoice.payment_failed -> tenant.status='past_due'
            -> DunningService::start (3 attempts, Stripe Smart Retries)

Cancel      POST /billing/cancel -> Cashier subscription->cancel()
            -> webhook customer.subscription.deleted -> tenant.status='suspended'

Plan chg    POST /billing/portal -> Customer Portal URL
            -> webhook customer.subscription.updated -> sync row + features cache invalidate
```

---

## 6. 🔐 TENANCY IMPLEMENTATION (`stancl/tenancy` v3)

### 6.1 Bootstrappers (Improvement 6)

```php
// config/tenancy.php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,    // swaps DB connection
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,       // prefixes Redis cache keys
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,  // S3 path prefix tenant_{ulid}/
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,       // attaches tenant ctx to queued jobs
],

'database' => [
    'central_connection' => 'central',
    'template_tenant_connection' => 'tenant',
    'prefix' => 'tenant_',
    'suffix' => '',
],

'identification_middleware' => [
    App\Http\Middleware\ResolveTenant::class,    // subdomain | header | token claim | sole-tenant | 400
],
```

**Job rules.** Every job that touches tenant data MUST use the `Stancl\Tenancy\Concerns\TenantAware` trait. Jobs serialize the resolved tenant; on dequeue, `QueueTenancyBootstrapper` re-initializes the connection before `handle()` runs.

**Filesystem.** S3 bucket is single + shared; tenant filesystem prefix is `tenant_{ulid}/`. Presigned URLs always include the prefix.

**Cache.** Redis keys are auto-prefixed `tenant:{ulid}:…` while inside `tenancy()->initialize($tenant)`.

### 6.2 Resolution Order (`TenantContextResolver`)

1. Subdomain matches `tenants.subdomain` → use it.
2. `custom_domain` matches → use it.
3. Header `X-Tenant-Slug` present + user has membership → use it.
4. Sanctum token has `tnt:{ulid}` ability → use it.
5. User has exactly one active tenant membership → use it.
6. Else → `400 TENANT_REQUIRED`.

### 6.3 Token Strategy (Improvement 5)

| Surface | Auth | Storage | TTL | Notes |
|---|---|---|---|---|
| Mobile (Flutter) | Sanctum Personal Access Token | `flutter_secure_storage` (Keychain / Keystore) | 30 days | Long-lived; no refresh tokens |
| Coach Web (TALL) | Sanctum stateful session cookie | HttpOnly, Secure, SameSite=Lax | session | CSRF protected |
| Admin (Filament) | Separate guard + session cookie | HttpOnly, Secure, SameSite=Strict | session | **Mandatory 2FA (TOTP via Fortify)** |
| Webhooks (Stripe/Twilio/FCM) | HMAC signature verification | — | — | Stored in `webhook_events` for idempotency |
| Public API (V3) | OAuth2 client_credentials + per-key rate limit | — | configurable | V3 only |

**Multi-tenant claim**: tokens carry `tnt:{ulid}` in `personal_access_tokens.abilities` JSON. `ResolveTenant` reads this claim when no header/subdomain is present.
**Refresh model**: Sanctum PAT is long-lived (30 days). On 401, the client re-runs login; **no refresh-token endpoint** in MVP.
**Revocation**: `POST /auth/logout` deletes the calling token row from `personal_access_tokens`. Admin can revoke all of a user's tokens via Filament.

### 6.4 Reverb Channels (Improvement 4)

| Channel | Events | Authorized for |
|---|---|---|
| `private-thread.{thread_id}` | `MessageSent`, `MessageRead` | thread members (coach + client of thread) |
| `private-user.{user_id}` | `PlanApproved`, `PlanActivated`, `AiPlanReady` (client-targeted) | the user themself |
| `private-tenant.{tenant_id}` | `PlanDrafted` (coach inbox), `NewClientJoined`, `AiPlanReady` (coach-targeted) | tenant members with role ∈ {owner, coach, staff} |
| `private-admin` | `SystemAlert`, `AiCostThresholdHit` | platform admins (Filament guard) |

`POST /broadcasting/auth` validates membership against the resolved tenant before issuing the Reverb auth signature.

### 6.5 Tenant Provisioning

```
TenantProvisioner::provision(Tenant $t):
    1. CREATE DATABASE `tenant_{ulid}` (utf8mb4, utf8mb4_unicode_ci)
    2. Set tenants.db_name = "tenant_{ulid}", db_host = current shard
    3. tenancy()->initialize($t)
    4. Run migrations from database/migrations/tenant
    5. Seed default plan templates (PlanTemplateService::seedDefaults($t))
    6. event(new TenantCreated($t))
    7. tenancy()->end()
```

Failure mid-provision → `TenantProvisioningFailed` event → ops alert; tenant left in `status='trial'` with `db_name=NULL` so retries are safe.

---

## 7. 🔄 DATA FLOW DIAGRAMS

### Flow A — User Logs a Meal (offline-first)

```
[Flutter]
  1. UI: MealLogScreen — user picks foods, enters quantities, taps "Save".
  2. Riverpod NotifierProvider: MealLogNotifier.log(items)
  3. Optimistic write to local Drift DB (table: outbox_meal_logs, status='pending')
  4. dio HTTP request:
        POST /api/v1/meals
        Authorization: Bearer 1|abc…
        X-Tenant-Slug: coach-mo
        Idempotency-Key: 7e6c-…-uuid
        Body: { logged_at, slot:"lunch", plan_meal_id, source:"manual",
                items:[ {food_id, food_scope, quantity_g}, ... ] }

[API Gateway / Nginx]
  5. TLS terminated; Host header parsed.

[Laravel Router → Middleware]
  6. EnsureFrontendRequestsAreStateful (Sanctum)
  7. auth:sanctum -> validates token -> sets Auth::user()
  8. ResolveTenant -> initialize tenant by header -> swap DB connection to tenant_{ulid}
  9. EnsureTenantActive -> blocks if status in [suspended, closed]
 10. RequireRole:client
 11. ThrottleRequests:60,1 (per IP+user)

[FormRequest: LogMealRequest]
 12. Validates payload (logged_at, slot, items[].food_id, items[].food_scope ∈ {global,tenant},
     items[].quantity_g ∈ [1,1500]). Rejects unknown ULIDs.

[Action: LogCustomMealAction]
 13. Gate::authorize('create', MealLog::class)
 14. Resolve client_profile_id from auth user (tenant connection)
 15. Idempotency check: SELECT * FROM meal_logs WHERE idempotency_key = ?  -> if exists, return 200
 16. DB::transaction() on tenant connection:
       a. Resolve foods per item:
            food_scope='global' -> central GlobalFood::find($id)   (CENTRAL connection)
            food_scope='tenant' -> tenant Food::find($id)          (TENANT connection)
            -> recompute kcal/protein/carbs/fat from food row × quantity_g/serving_size_g
       b. INSERT meal_logs (idempotency_key, …, totals)
       c. INSERT meal_log_items batch (with food_scope)
 17. event(new MealLogged($mealLog))

[Sync listeners]
 18. RecomputeDailyMacrosListener -> updates daily macro cache (Redis: macros:{client}:{date})
 19. Broadcast 'meal.logged' on private-user.{user_id}

[Queued listeners (default queue)]
 20. RecomputeAdherenceListener -> upsert client_metrics_weekly row
 21. CheckTargetsListener -> if kcal/protein deficit > threshold for 3+ days -> AlertCoachNotification

[Resource & Response]
 22. MealLogResource::toArray -> 201 Created with computed totals.

[Flutter]
 23. On 201: mark outbox row synced, replace local id with server id.
 24. On 4xx/5xx: keep in outbox, retry with exponential backoff (1s, 2s, 4s, 8s, 30s, 5m, 30m).
```

### Flow B — AI Plan Generation (2-call strategy)

```
[Coach Web / Flutter]
  POST /api/v1/ai/plans/generate
       Idempotency-Key: <uuid>
       Body: { client_id, kind:"plan_initial", config:{ weeks:4, split_preference:"upper_lower" } }

[Middleware]
  auth:sanctum -> ResolveTenant -> EnsureTenantActive -> EnsureFeature:ai_plans

[AiPlanController::generate]
  1. Compute input_hash = sha256(canonical_json(input))
  2. If cached (input_hash exists, status='succeeded', age<24h) -> return cached ai_request_id
  3. INSERT ai_generation_requests (status='queued', input, model='claude-opus-4-6')
  4. dispatch GeneratePlanJob(req->id) onQueue('ai')
  5. Return 202 { ai_request_id }

[Horizon worker — ai queue (TenantAware)]
  GeneratePlanJob::handle
    PlanGenerationOrchestrator::handle($request)
      Step 0: status='running', start_ms
      Step 1: RuleEngineService::computeTargets()
      Step 2: RuleEngineService::buildSkeleton() — full 4-week
      Step 3: LLM call #1 — FULL exercise plan       (claude-opus-4-6, 8000 max_tokens, JSON schema)
      Step 4: LLM call #2 — FULL meal plan           (claude-opus-4-6, 8000 max_tokens, JSON schema)
      Step 5: FoodMatchService::matchAll() — batch resolution to food_id + food_scope
      Step 6: PlanValidator::validate() — hard rules; up to 2 retries (retry_count++)
      Step 7: PersistDraftPlanAction (DB::transaction in tenant connection)
              -> plans (status='pending_review' or 'approved' if auto_approve)
              -> plan_workout_days (with session_order/session_label)
              -> plan_exercises (with exercise_scope)
              -> plan_meal_days, plan_meals, plan_meal_items (with food_scope)
      Step 8: UsageMeter::increment(tenant, 'ai_plans')   -> tenant_usage_counters
      Step 9: Notify + broadcast
      Step 10: ai_generation_requests UPDATE status='succeeded', cost_usd, latency_ms
    on success: event(PlanDrafted)
    on failure: event(PlanGenerationFailed) -> status='failed' or 'validation_failed'

[Listeners]
  -> NotifyCoach (FCM + DB notification + AiPlanReadyNotification)
  -> Broadcast 'ai.plan.ready' on private-tenant.{id}

[Coach UI]
  Polls GET /ai/requests/{id} (or receives WS)
  On status=succeeded -> redirects to plan editor with draft loaded
```

### Flow C — Workout Set Logged → PR Detected → Coach Notified

```
[Flutter Workout Player]
  POST /workouts/sessions/{id}/sets
       Idempotency-Key: <uuid>
       Body: { plan_exercise_id, exercise_id, exercise_scope:"global", set_index, reps, weight_kg, rpe, completed_at }

[Middleware → LogSetRequest → LogSetAction]
  1. Validate idempotency_key uniqueness (UNIQUE constraint)
  2. INSERT set_logs (with exercise_scope)
  3. event(new SetLogged($setLog))

[Sync listeners]
  4. UpdateSessionVolumeListener -> workout_sessions.total_volume_kg += weight_kg * reps

[DetectPrListener (sync)]
  5. Compute candidate PR kinds:
        max_weight    = if weight_kg > current MAX(set_logs WHERE exercise_id=$id, exercise_scope=$s).weight_kg
        max_reps      = at given weight bucket
        max_volume    = weight_kg * reps for the day
        1rm (Epley)   = weight_kg * (1 + reps/30)
  6. For each kind whose value > previous best:
        INSERT personal_records (NEW row, no UPSERT — Decision 3)
        is_pr = true on response
  7. Broadcast 'set.logged' on private-user.{user_id}
  8. dispatch NotifyCoachOfPrJob if any new PR kind

[Queued: RecomputeAdherenceListener]
  9. Upsert client_metrics_weekly for the current week_start.
```

---

## 8. ⚙️ EDGE CASES

### 8.1 Invalid Data
| Case | Behavior |
|---|---|
| Body fails FormRequest | 422 `VALIDATION_FAILED` with per-field errors |
| Unknown food_id / cross-scope leak | 422 `FOOD_NOT_FOUND` (no DB leak; verified by FormRequest) |
| `food_scope` mismatch (id resolves only in opposite scope) | 422 `FOOD_SCOPE_MISMATCH` |
| `exercise_scope` mismatch | 422 `EXERCISE_SCOPE_MISMATCH` |
| `quantity_g` outside [1, 1500] | 422 `INVALID_QUANTITY` |
| `logged_at` more than +1h in future | 422 `LOGGED_AT_FUTURE` |
| `logged_at` more than 30 days in past | 422 `LOGGED_AT_TOO_OLD` (override flag for coach) |
| Reps > 100 / Weight > 500kg | 422 `IMPLAUSIBLE_VALUE` |
| Duplicate `Idempotency-Key` | 200 with original response (replay) |
| Missing `Idempotency-Key` on POST | 400 `IDEMPOTENCY_KEY_REQUIRED` |
| ULID malformed | 400 `INVALID_ID_FORMAT` |
| Allergen in plan items at write | 422 `ALLERGEN_VIOLATION` |
| Cross-DB id collision (same ulid in tenant + global) | resolution prefers explicit `*_scope`; ambiguous → 422 `SCOPE_REQUIRED` |

### 8.2 Subscription Expired / Invalid
| Tenant State | Reads | Writes | Side effects |
|---|---|---|---|
| `trial` (< trial_ends_at) | ✅ | ✅ | banner: "X days trial left" |
| `trial` expired, no sub | ✅ | ❌ | 402 `TRIAL_EXPIRED` on every write; redirect coach to /billing |
| `active` | ✅ | ✅ | — |
| `past_due` | ✅ | ✅ but flagged | banner; daily DunningEmail |
| `past_due` > 14d | ✅ | ❌ | 402 `PAYMENT_REQUIRED` on writes |
| `suspended` | ❌ | ❌ | 423 `TENANT_SUSPENDED`, only `/billing` reachable |
| `closed` | ❌ | ❌ | 410 `TENANT_CLOSED` |
| Feature not in plan | reads ✅ | 403 `FEATURE_NOT_INCLUDED` |
| Quota exceeded | reads ✅ | 429 `USAGE_LIMIT_REACHED` w/ reset_at |
| Client cap reached | reads ✅ | 403 `CLIENT_CAP_REACHED` on invite/accept |

### 8.3 Flutter — No Internet
| Scenario | Behavior |
|---|---|
| App start, no network | Bootstrap from local Drift cache; banner "Offline" |
| Log set/meal/water offline | Write to local Drift; enqueue in outbox with idempotency_key |
| Outbox sync on reconnect | Replay POSTs in chronological order; on 2xx mark synced |
| Outbox conflict (server has same idem_key) | Treat as success, no duplicate |
| Outbox 4xx (validation) | Move to `outbox_dead` table; surface error to user |
| Outbox 5xx | Exponential backoff (1s,2s,4s,8s,30s,5m,30m); cap 24h then dead-letter |
| Token expired offline | Queue refresh attempt; if login fails on reconnect, force re-login keeping outbox |
| Photo upload offline | Defer multipart; keep file in app sandbox; clean on success |
| Plan stale (server pushed new active plan) | On next /me/today fetch, reconcile; show "Plan updated" toast |
| Time zone mismatch | All `logged_at` sent in UTC; server validates against device skew (≤ 5min) |

### 8.4 AI Failure Modes
| Failure | Detection | Behavior |
|---|---|---|
| LLM 5xx / timeout | LlmClient try/catch | Retry 2× with exponential backoff (3s, 9s) |
| LLM 429 (rate limit) | response code | Backoff + queue: re-dispatch job with delay |
| LLM returns invalid JSON | json_decode fail | Re-call with stricter `response_format:json_object` + schema; max 2 retries |
| LLM picks unknown exercise/food slug | catalog lookup miss | Replace with deterministic fallback (top by popularity / alias matching) |
| Validator rejects (kcal/macros/allergen/injury) | PlanValidator | Re-call with corrective prompt; `retry_count++`; max 2 retries → status='validation_failed' with reasons surfaced to coach |
| Cost ceiling hit per request (> $1) | LlmClient cost guard | Abort, mark failed `BUDGET_EXCEEDED` |
| Per-tenant token bucket empty | rate limiter | 429 `AI_RATE_LIMITED`, retry_after |
| Job times out (> 90s) | Horizon `timeout` | Mark request `failed`, allow manual retry |
| Tenant DB unreachable mid-persist | DB exception | Transaction rollback; request `failed`; alert ops |
| Provider down (Anthropic outage) | health check | Failover to OpenAI provider (`gpt-4o`) transparently |
| AI cost ledger write fails | post-success | Log + retry via `WriteAiCostLedgerJob`; never block user response |
| Webhook event duplicate | `webhook_events.event_id` unique | Idempotent skip |
| Stripe webhook arrives before Checkout success | sequence | Use `webhook_events` queue; reconcile by `stripe_id` |

### 8.5 Race Conditions

| Race | Mitigation |
|---|---|
| Two coaches approve the same plan concurrently | `UPDATE plans SET status='approved' WHERE id=? AND status='pending_review'` — affected_rows=0 → 409 `STATE_CONFLICT` |
| Activate plan while previous activate still running | DB transaction with `SELECT … FOR UPDATE` on the previous active row |
| Same set logged twice (duplicate POST) | `set_logs.idempotency_key` UNIQUE — second insert rejected; controller returns original |
| Concurrent meal log on two devices | `meal_logs.idempotency_key` UNIQUE per device-generated UUID |
| Concurrent PR detection on same set | `personal_records` is INSERT-only (Decision 3) — duplicates harmless; "current" derived via MAX |
| Tenant marked `past_due` while a write is in flight | `EnforceTenantStatus` runs after auth → write fails 402 cleanly |
| Stripe subscription updated while checkout in progress | webhook idempotent on `event_id`; latest event wins via timestamp |
| AI generation cached (input_hash) but cached plan was archived | check cached `plan.deleted_at IS NULL AND status != 'archived'` before returning hit |

---

## 9. 🚀 BUILD ORDER

```
Step 1  - Project bootstrap & infra
Step 2  - Central migrations
Step 3  - Tenant migrations + provisioner (stancl/tenancy bootstrappers)
Step 4  - Models (central + tenant) with relationships only
Step 5  - Identity services + auth API (Sanctum + token strategy)
Step 6  - Tenancy services + middleware + tenant DB bootstrap
Step 7  - Catalog services + endpoints + global seeders (with food locales)
Step 8  - Plan services + endpoints (incl. session_order, scope fields, client_notes)
Step 9  - Workout + Nutrition logging services + endpoints (idempotency on meals)
Step 10 - Progress + Messaging services + endpoints + Reverb (4 channels)
Step 11 - AI engine (RuleEngine, PromptRenderer, LlmClient, Orchestrator-2-call, Validator, Jobs)
Step 12 - Billing (PricingPlan seed, Cashier, webhooks, EnsureFeature, split usage counters)
Step 13 - Notifications (FCM channel, devices, preferences)
Step 14 - Adaptation cron + scheduled jobs
Step 15 - Filament admin panel (incl. SupportTicketResource)
Step 16 - Flutter mobile + TALL coach web
Step 17 - Hardening (tests, isolation tests, p95, security headers, CI, observability)
```

### Detailed Step Output

**Step 1 — Bootstrap (1d)**
```
laravel new fetness --using=laravel/laravel
composer require laravel/sanctum laravel/cashier laravel/horizon laravel/reverb laravel/scout
composer require stancl/tenancy filament/filament:^3 dedoc/scramble
composer require --dev laravel/pint phpstan/phpstan pestphp/pest pestphp/pest-plugin-laravel
docker-compose: mysql:8 (central + tenant_template), redis:7, mailhog
.env: DB_CONNECTION=central, TENANT_DB_HOST=…, REDIS_*
config/database.php: 'central' + 'tenant' connections
```

**Step 2 — Central Migrations (1d)** — all files in §1.C central. `php artisan migrate --path=database/migrations/central`. Includes `support_tickets`, split `tenant_usage_counters` + `user_usage_counters`.

**Step 3 — Tenant Migrations + Provisioner (2d)** *(Improvement 6)*
```
config/tenancy.php:
  database.template_tenant_connection => 'tenant'
  database.prefix => 'tenant_'
  bootstrappers => [
      DatabaseTenancyBootstrapper::class,
      CacheTenancyBootstrapper::class,
      FilesystemTenancyBootstrapper::class,
      QueueTenancyBootstrapper::class,
  ]

TenantProvisioner::provision($tenant):
  CREATE DATABASE tenant_{ulid}
  Run tenant migrations from database/migrations/tenant
  Seed PlanTemplate defaults

Horizon: every job that touches tenant data uses TenantAware trait.
S3 filesystem prefix = tenant_{ulid}/   (set in FilesystemTenancyBootstrapper)
```

**Step 4 — Models (1d)** — every model in §2 (relationships only). `phpstan` level 6 passing. `User`, `Tenant`, all global models on `central` connection; everything in `app/Domains/{Plan,Workout,Nutrition,Progress,Messaging,AI}` on `tenant` connection.

**Step 5 — Identity API (3d)**
```
- AuthController + RegisterAction (uses tenant_invite_token, Fix 12) /
  LoginAction / OtpAction / LogoutAction
- Sanctum config; token TTL 30d; abilities[] carries tnt:{ulid} claim
- Twilio driver (sandbox in tests)
- 2FA via Fortify (TOTP)
- Pest tests: registration happy path + 4 errors; OTP rate limit; 2FA flow; tenant claim resolution
- Postman collection auto-published from Scramble OpenAPI
```

**Step 6 — Tenancy (3d)**
```
- TenantContextResolver: subdomain | custom_domain | header | token-claim | sole-tenant
- ResolveTenant middleware -> tenancy()->initialize()
- EnsureTenantActive middleware (handles past_due/suspended/closed)
- TenantProvisioner with InitializesTenant event listener BootstrapTenantDatabase
- Tenant invite flow + accept (creates tenant_users row; cap-checked)
- Isolation test: seed two tenants, run every list endpoint, assert zero leakage
```

**Step 7 — Catalog (3d)**
```
- Migrations + seeders: 500 global_exercises (open-source dataset), 3000 global_foods (USDA + MENA curated)
- global_food_aliases with locale per row (Fix 6); FULLTEXT indexes
- CatalogSearchService with merged tenant+global query (returns scope on each row)
- Embeddings: nightly job ReindexCatalogJob
- BarcodeLookupService: local -> OpenFoodFacts fallback
- Endpoints + Pest tests (incl. scope=global|tenant on ID detail endpoints)
```

**Step 8 — Plan (5d)**
```
- All plan migrations + models with parent/child relationships
- plan_workout_days has session_order + session_label (Decision 7)
- plan_exercises / plan_meal_items carry exercise_scope / food_scope (Fix 4)
- client_notes table + ClientNoteService (Improvement 1)
- PlanBuilderService: createDraft / addWorkoutDay / addPlanExercise / addMealItem
- PlanApprovalService: submit, approve, activate (archives previous active version atomically)
- PlanResource (full tree with scope fields and session_order)
- Plan policy (coach scope, client read own active)
- 25 endpoints from §3.5 (incl. notes routes)
- Tests: clone plan keeps version chain; activate archives previous; cross-tenant isolation; multi-session day
```

**Step 9 — Logging (4d)**
```
- WorkoutSession + SetLog migrations (set_logs.exercise_scope, idempotency_key)
- LogSetAction with idempotency check + DB transaction
- PrDetector listener (after SetLogged): query latest 1RM/maxReps/maxVolume/maxWeight,
  INSERT new personal_records row (no upsert; Decision 3)
- AdherenceCalculator job (recompute client_metrics_weekly)
- MealLog (with idempotency_key, Fix 5) + items (food_scope, Fix 4); MacroCalculator
- WaterLog
- 9 + 8 endpoints
- Pest tests: PR detection produces history rows; idempotency replay returns 200; macros recompute
```

**Step 10 — Progress + Messaging (4d)**
```
- WeighIn / Measurement / ProgressPhoto (is_private default 1) / PersonalRecord / ClientMetricsWeekly
- S3PresignService (PUT presigned URL, 5-min expiry, content-type pinned, tenant-prefixed path)
- ChartSeriesService (decimation: max 200 points)
- ChatThread (1:1 only with V2 comment, soft-delete) / Message
- Reverb: 4 channels (Improvement 4) — private-thread.{id}, private-user.{id}, private-tenant.{id}, private-admin
- MessageSent/MessageRead broadcast events
- 10 + 6 endpoints + GET /progress/prs?history=true
```

**Step 11 — AI Engine (8d, 2-call strategy)**
```
Day 1-2: ai_prompt_versions seed (plan_initial.exercises.v3, plan_initial.meals.v3) — model='claude-opus-4-6', max_tokens=8000
         PromptRenderer (mustache), LlmClient with retry/backoff/cost guard
Day 3-4: ClaudeProvider + OpenAiProvider (interface), failover policy (claude-opus-4-6 → gpt-4o)
Day 5: RuleEngineService (pure-PHP, golden tests on 20 client profiles)
Day 6: PlanGenerationOrchestrator with 2-call strategy (Fix 13) + PlanValidator with all rules + retry_count
Day 7: GeneratePlanJob (TenantAware), AiPlanController, status polling endpoint
Day 8: Smoke run 50 generations, cost telemetry, fix outliers, ship
Exit: 50 generations, validator pass-rate >= 98%, p95 latency < 30s, cost p95 < $0.30 (Fix 13 envelope)
```

**Step 12 — Billing (4d)**
```
- Cashier install; PricingPlan seeder with 5 tiers
- BillingController + Checkout flow
- StripeWebhookController + StripeWebhookHandler with sub-handlers per event
- webhook_events table + idempotent processing
- EnsureFeature, EnforceTenantStatus, EnforceClientCap middlewares
- UsageMeter routes to tenant_usage_counters or user_usage_counters (Fix 2)
  (Redis pre-increment + DB persist on success)
- Trial logic (14d coach / 7d user-Pro); auto-tenant.status transitions (incl. past_due)
- Cancel/resume/portal endpoints
- DunningService (email schedule)
- Tests: end-to-end with Stripe CLI; gating tests for every limit; 402/423/410 transitions
```

**Step 13 — Notifications (2d)**
```
- DeviceToken model + register/delete endpoints
- FcmChannel custom Notification channel
- TwilioSmsChannel + SesMailChannel + InAppChannel
- 7 Notification classes from §2.11
- NotificationPreference enforcement at dispatch time
```

**Step 14 — Adaptation (3d)**
```
- AdaptationEngine decision tree (deterministic):
    adherence < 0.5            -> 'reduce_load'
    weight_delta opposite goal -> 'adjust_kcal' (±10%)
    weeks since deload >= 6    -> 'deload'
    significant goal-progress  -> 'replan'
    else                       -> 'maintain'
- RunWeeklyAdaptationCommand + RunWeeklyAdaptationJob (per-tenant fan-out, TenantAware)
- MaybeReplanJob triggered by WeighInRecorded event when |delta| > 0.7kg/7d
- Coach approval UI; auto-approve setting
```

**Step 15 — Filament (2d)**
```
- Admin guard + 2FA mandatory
- Resources for all entities listed in §2.12 (incl. SupportTicketResource)
- AnalyticsDashboard widgets (MRR, DAU/MAU, AI cost per tenant, failed jobs, open tickets)
- PromptManager: hot-reload prompt versions; only one is_active per kind
- SupportInbox page
- Audit log read-only viewer
```

**Step 16 — Frontend (8d, parallel from Step 8)**
```
Flutter (5d):
  - dio + interceptors (auth refresh-via-relogin, retry, idempotency-key)
  - drift schema (outbox + caches)
  - riverpod state + freezed DTOs (codegen from OpenAPI via openapi-generator)
  - Screens: Onboarding, Today, Workout Player, Meal Log, Progress, Chat, Plan View, Settings
  - flutter_secure_storage for token (Improvement 5)
  - FCM integration; deep-links
  - Sentry + Firebase Crashlytics

TALL Coach Web (3d):
  - Fortify views (login, register, password)
  - Livewire pages from §2.12 / §3
  - Alpine drag-drop in plan builder (multiple sessions per day supported)
  - Reverb client for real-time chat + private-tenant inbox
```

**Step 17 — Hardening (3d)**
```
- pint + phpstan level 8
- pest coverage >= 85% on Domains
- Static analysis test: every tenant model uses tenant connection
- Isolation test: two-tenant fixture, list endpoints, zero leakage
- Performance: cache today-dashboard, kill N+1 (Telescope), index tuning
- Security: rate limits per endpoint (60/min default, 10/min AI, 5/min auth),
  CSP headers, HSTS, CORS lock-down
- Observability: Sentry, OpenTelemetry, structured logs (tenant_id, user_id, trace_id, request_id)
- CI pipeline (GitHub Actions): pint -> phpstan -> pest -> migrate (central+tenant template) -> deploy
- Backup: nightly mysqldump per tenant DB to S3 with 30d retention
- Disaster recovery runbook (target: < 4h)
```

---

**End of Blueprint v2.** All 13 fixes, 8 architectural decisions, and 7 improvements applied. Day 1 starts with Step 1.
