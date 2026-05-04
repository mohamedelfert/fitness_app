# Fetness App — Execution Plan

**Blueprint:** BLUEPRINT.md v2
**Stack:** Laravel 11 · PHP 8.3 · MySQL 8 · Redis 7 · Sanctum · Cashier · Horizon · Reverb · Filament v3 · stancl/tenancy v3 · Flutter 3
**Tenancy Mode:** Multi-database (Central DB + per-tenant DB)

---

## 1. 🚀 PROJECT PHASES

| Phase | Name | Description | Days |
|-------|------|-------------|------|
| Phase 1 | Core Infrastructure | Project setup, database connections, base config | 1d |
| Phase 2 | Central Database | Migrations + models for central DB | 1d |
| Phase 3 | Tenant Architecture | Multi-tenancy, migrations, provisioning | 2d |
| Phase 4 | Identity & Auth | User auth, Sanctum, 2FA, OTP | 3d |
| Phase 5 | Tenancy Services | Tenant management, invites, middleware | 3d |
| Phase 6 | Catalog | Exercises, foods, search, global seeders | 3d |
| Phase 7 | Plan Module | Plans, workout days, exercises, meals | 5d |
| Phase 8 | Workout & Nutrition | Session logging, meal logging, adherence | 4d |
| Phase 9 | Progress & Messaging | Weigh-ins, measurements, chat, Reverb | 4d |
| Phase 10 | AI Engine | Plan generation, LLM, validator | 8d |
| Phase 11 | Billing SaaS | Subscriptions, Stripe, usage limits | 4d |
| Phase 12 | Notifications | FCM, push, reminders | 2d |
| Phase 13 | Adaptation | Weekly adaptation, cron jobs | 3d |
| Phase 14 | Admin Panel | Filament v3 | 2d |
| Phase 15 | Mobile + Web | Flutter + TALL | 8d |

**Total MVP: ~46 days**

---

## 2. 📋 TASKS PER PHASE

### Phase 1: Core Infrastructure (1d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 1.1 | Laravel 11 project bootstrap with laravel/laravel | Critical | Backend |
| 1.2 | Install Sanctum, Cashier, Horizon, Reverb, Scout | Critical | Backend |
| 1.3 | Install stancl/tenancy v3, Filament v3 | Critical | Backend |
| 1.4 | Setup docker-compose (MySQL 8 central + tenant_template, Redis 7, mailhog) | Critical | Infra |
| 1.5 | Configure database.php (central + tenant connections) | Critical | Backend |
| 1.6 | Install dev tools (Pint, PHPStan, Pest) | High | Backend |

### Phase 2: Central Database (1d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 2.1 | Create users table migration | Critical | Backend |
| 2.2 | Create personal_access_tokens migration | Critical | Backend |
| 2.3 | Create otp_codes table migration | Critical | Backend |
| 2.4 | Create tenants table migration | Critical | Backend |
| 2.5 | Create tenant_users pivot migration | Critical | Backend |
| 2.6 | Create tenant_invites migration | Critical | Backend |
| 2.7 | Create pricing_plans migration | Critical | Backend |
| 2.8 | Create subscriptions migration (Cashier-extended) | Critical | Backend |
| 2.9 | Create invoices migration | Critical | Backend |
| 2.10 | Create tenant_usage_counters migration | Critical | Backend |
| 2.11 | Create user_usage_counters migration | Critical | Backend |
| 2.12 | Create webhook_events migration | Critical | Backend |
| 2.13 | Create global_exercises migration | Critical | Backend |
| 2.14 | Create global_foods migration | Critical | Backend |
| 2.15 | Create global_food_aliases migration | Critical | Backend |
| 2.16 | Create ai_prompt_versions migration | Critical | Backend |
| 2.17 | Create ai_cost_ledger migration | Critical | Backend |
| 2.18 | Create audit_log migration | Critical | Backend |
| 2.19 | Create device_tokens migration | Critical | Backend |
| 2.20 | Create notification_preferences migration | Critical | Backend |
| 2.21 | Create support_tickets migration | Critical | Backend |

### Phase 3: Tenant Architecture (2d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 3.1 | Configure stancl/tenancy (bootstrappers: Database, Cache, Filesystem, Queue) | Critical | Backend |
| 3.2 | Create tenant migrations (client_profiles, plans, exercises, foods, etc.) | Critical | Backend |
| 3.3 | Implement TenantProvisioner service (CREATE DATABASE tenant_{ulid}) | Critical | Backend |
| 3.4 | Configure Horizon TenantAware jobs | Critical | Backend |
| 3.5 | Configure S3 filesystem tenant prefixes (tenant_{ulid}/) | Critical | Backend |
| 3.6 | Seed tenant template migrations | High | Backend |

### Phase 4: Identity & Auth (3d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 4.1 | Create User, OtpCode, DeviceToken models | Critical | Backend |
| 4.2 | Implement AuthService, OtpService | Critical | Backend |
| 4.3 | Create RegisterAction, LoginAction, OtpAction, LogoutAction | Critical | Backend |
| 4.4 | Build AuthController endpoints (/auth/register, /auth/login, /auth/otp/*, /auth/logout) | Critical | Backend |
| 4.5 | Configure Sanctum (30d token TTL, abilities with tnt:{ulid} claim) | Critical | Backend |
| 4.6 | Implement 2FA via Fortify (TOTP) | High | Backend |
| 4.7 | Create MeController (/me, /me/password, /me/2fa) | High | Backend |
| 4.8 | Create NotificationController (/notifications, /notifications/preferences) | High | Backend |
| 4.9 | Create DeviceController (/devices) | High | Backend |
| 4.10 | Pest tests: registration flow, OTP rate limit, 2FA, tenant claim resolution | High | Backend |

### Phase 5: Tenancy Services (3d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 5.1 | Create Tenant, TenantUser, TenantInvite models | Critical | Backend |
| 5.2 | Implement TenantProvisioner service | Critical | Backend |
| 5.3 | Build TenantContextResolver (subdomain > custom_domain > header > token-claim > sole-tenant) | Critical | Backend |
| 5.4 | Implement ResolveTenant middleware | Critical | Backend |
| 5.5 | Implement EnsureTenantActive middleware (blocks past_due/suspended/closed writes) | Critical | Backend |
| 5.6 | Implement RequireRole middleware | Critical | Backend |
| 5.7 | Build TenantController endpoints (/tenants, /tenants/current, /tenants/switch) | Critical | Backend |
| 5.8 | Build TenantInviteController (/tenants/current/invites, /tenants/invites/{token}/accept) | Critical | Backend |
| 5.9 | Implement EnforceClientCap middleware | Critical | Backend |
| 5.10 | Pest tests: isolation (two-tenant fixture, zero leakage) | High | Backend |

### Phase 6: Catalog (3d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 6.1 | Create GlobalExercise, GlobalFood, GlobalFoodAlias models (central) | Critical | Backend |
| 6.2 | Create Exercise, Food tenant models | Critical | Backend |
| 6.3 | Implement CatalogSearchService (merges global + tenant, returns scope) | Critical | Backend |
| 6.4 | Implement FoodMatchService (alias > FULLTEXT > embedding fallback) | Critical | Backend |
| 6.5 | Implement BarcodeLookupService (local > OpenFoodFacts) | High | Backend |
| 6.6 | Seed 500 global_exercises | High | Backend |
| 6.7 | Seed 3000 global_foods (USDA + MENA curated) | High | Backend |
| 6.8 | Seed global_food_aliases per locale | High | Backend |
| 6.9 | Build ExerciseController endpoints (/exercises, /exercises/{id}?scope=) | Critical | Backend |
| 6.10 | Build FoodController endpoints (/foods, /foods/{id}, /foods/barcode/{code}) | Critical | Backend |
| 6.11 | Pest tests: scope resolution, cross-scope rejection | High | Backend |

### Phase 7: Plan Module (5d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 7.1 | Create Plan model (parent_plan_id versioning, status with rejected) | Critical | Backend |
| 7.2 | Create PlanWorkoutDay model (session_order, session_label) | Critical | Backend |
| 7.3 | Create PlanExercise model (exercise_id + exercise_scope) | Critical | Backend |
| 7.4 | Create PlanMealDay, PlanMeal, PlanMealItem models | Critical | Backend |
| 7.5 | Create PlanTemplate model | High | Backend |
| 7.6 | Create ClientNote model | High | Backend |
| 7.7 | Implement PlanBuilderService (createDraft, addWorkoutDay, addExercise, addMeal) | Critical | Backend |
| 7.8 | Implement PlanApprovalService (approve, activate with SELECT FOR UPDATE) | Critical | Backend |
| 7.9 | Implement PlanTemplateService | High | Backend |
| 7.10 | Implement ClientNoteService | High | Backend |
| 7.11 | Build PlanController (/clients/{client_id}/plans, /plans/{id}) | Critical | Backend |
| 7.12 | Build PlanLifecycleController (/plans/{id}/approve, /activate, /reject, /clone) | Critical | Backend |
| 7.13 | Build PlanWorkoutDayController | Critical | Backend |
| 7.14 | Build PlanMealController | Critical | Backend |
| 7.15 | Build PlanTemplateController (/templates, /templates/{id}/apply) | High | Backend |
| 7.16 | Build ClientNoteController (/clients/{id}/notes) | High | Backend |
| 7.17 | Implement PlanPolicy | Critical | Backend |
| 7.18 | Implement ClientNotePolicy | High | Backend |
| 7.19 | Pest tests: clone keeps version chain, activate archives previous | High | Backend |

### Phase 8: Workout & Nutrition (4d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 8.1 | Create WorkoutSession model (soft delete, idempotency_key) | Critical | Backend |
| 8.2 | Create SetLog model (exercise_id + exercise_scope, idempotency_key) | Critical | Backend |
| 8.3 | Create MealLog model (idempotency_key, source) | Critical | Backend |
| 8.4 | Create MealLogItem model (food_id + food_scope) | Critical | Backend |
| 8.5 | Create WaterLog model | High | Backend |
| 8.6 | Implement WorkoutLogService | Critical | Backend |
| 8.7 | Implement LogSetAction (idempotency, DB transaction, scope validation) | Critical | Backend |
| 8.8 | Implement PrDetector listener (1rm, max_reps, max_volume, max_weight) | Critical | Backend |
| 8.9 | Implement MealLogService | Critical | Backend |
| 8.10 | Implement LogCustomMealAction (scope validation, food recompute) | Critical | Backend |
| 8.11 | Implement MacroCalculator | High | Backend |
| 8.12 | Build WorkoutSessionController (/workouts/sessions, /workouts/sessions/{id}) | Critical | Backend |
| 8.13 | Build SetLogController | Critical | Backend |
| 8.14 | Build MealController (/meals, /meals/{id}) | Critical | Backend |
| 8.15 | Build NutritionSummaryController (/nutrition/summary) | High | Backend |
| 8.16 | Build WaterController | High | Backend |
| 8.17 | Pest tests: idempotency replay returns 200, PR detection inserts history | High | Backend |

### Phase 9: Progress & Messaging (4d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 9.1 | Create WeighIn model | Critical | Backend |
| 9.2 | Create Measurement model | High | Backend |
| 9.3 | Create ProgressPhoto model (is_private default 1) | High | Backend |
| 9.4 | Create PersonalRecord model (history insert only) | Critical | Backend |
| 9.5 | Create ClientMetricsWeekly model | High | Backend |
| 9.6 | Create ChatThread model (soft delete, 1:1 only) | Critical | Backend |
| 9.7 | Create Message model | Critical | Backend |
| 9.8 | Create AiGenerationRequest model (retry_count, validation_failed) | Critical | Backend |
| 9.9 | Implement ProgressService | Critical | Backend |
| 9.10 | Implement S3PresignService (PUT 5-min, tenant-prefixed) | High | Backend |
| 9.11 | Implement ChartSeriesService (max 200 points) | High | Backend |
| 9.12 | Implement MessagingService | Critical | Backend |
| 9.13 | Configure Reverb channels (private-thread, private-user, private-tenant, private-admin) | Critical | Backend |
| 9.14 | Build WeighInController (/progress/weigh-ins) | Critical | Backend |
| 9.15 | Build MeasurementController (/progress/measurements) | High | Backend |
| 9.16 | Build ProgressPhotoController (/progress/photos/presign, /progress/photos) | High | Backend |
| 9.17 | Build PersonalRecordController (/progress/prs?history=true) | High | Backend |
| 9.18 | Build ProgressSeriesController (/progress/series, /progress/weekly) | High | Backend |
| 9.19 | Build ChatThreadController (/chat/threads) | Critical | Backend |
| 9.20 | Build MessageController (/chat/threads/{id}/messages) | Critical | Backend |
| 9.21 | Build AiRequestController (/ai/requests) | Critical | Backend |
| 9.22 | Build /broadcasting/auth endpoint | Critical | Backend |
| 9.23 | Pest tests: PR history query, chat threads | High | Backend |

### Phase 10: AI Engine (8d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 10.1 | Seed ai_prompt_versions (plan_initial.exercises.v3, plan_initial.meals.v3 — claude-opus-4-6, max_tokens=8000) | Critical | Backend |
| 10.2 | Implement PromptRenderer (mustache/blade) | Critical | Backend |
| 10.3 | Implement LlmClient (retry/backoff, cost guard) | Critical | Backend |
| 10.4 | Implement ClaudeProvider (primary) | Critical | Backend |
| 10.5 | Implement OpenAiProvider (fallback to gpt-4o) | High | Backend |
| 10.6 | Implement RuleEngineService (computeTargets, buildSkeleton) | Critical | Backend |
| 10.7 | Implement PlanValidator (hard rules: kcal, protein, allergens, injuries, volume) | Critical | Backend |
| 10.8 | Implement FoodMatchService::matchAll (batch resolution) | Critical | Backend |
| 10.9 | Implement PlanGenerationOrchestrator (2-call strategy: exercises then meals) | Critical | Backend |
| 10.10 | Implement GeneratePlanJob (TenantAware, 2 retries, validation) | Critical | Backend |
| 10.11 | Build AiPlanController (/ai/plans/generate) | Critical | Backend |
| 10.12 | Build AiRequestController (/ai/requests/{id}, /ai/requests) | Critical | Backend |
| 10.13 | Build AiCopilotController (/ai/copilot/suggest) | High | Backend |
| 10.14 | Build AiUsageController (/ai/usage) | High | Backend |
| 10.15 | Golden tests: 20 client profiles with golden output | High | Backend |
| 10.16 | Smoke run: 50 generations, verify >= 98% pass-rate, cost p95 < $0.30 | High | Backend |

### Phase 11: Billing SaaS (4d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 11.1 | Seed pricing_plans (free, pro, coach_starter, coach_pro, coach_studio) | Critical | Backend |
| 11.2 | Configure Cashier (Laravel\Cashier\Stripe) | Critical | Backend |
| 11.3 | Create PricingPlan, Subscription, Invoice models | Critical | Backend |
| 11.4 | Implement BillingService | Critical | Backend |
| 11.5 | Implement EntitlementService | Critical | Backend |
| 11.6 | Implement UsageMeter (routes Tenant ↔ User based on context) | Critical | Backend |
| 11.7 | Implement StripeWebhookHandler (sub-handlers per event) | Critical | Backend |
| 11.8 | Build BillingController | Critical | Backend |
| 11.9 | Build StripeWebhookController (/webhooks/stripe) | Critical | Backend |
| 11.10 | Implement EnsureFeature middleware | Critical | Backend |
| 11.11 | Implement EnforceTenantStatus middleware | Critical | Backend |
| 11.12 | Implement EnforceClientCap middleware | Critical | Backend |
| 11.13 | Implement DunningService (Stripe Smart Retries) | High | Backend |
| 11.14 | Pest tests: end-to-end (Stripe CLI), gating every limit | High | Backend |

### Phase 12: Notifications (2d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 12.1 | Create Notification model (per-tenant inbox) | Critical | Backend |
| 12.2 | Implement FcmChannel (FCM API) | Critical | Backend |
| 12.3 | Implement SesMailChannel | High | Backend |
| 12.4 | Implement TwilioSmsChannel | High | Backend |
| 12.5 | Implement InAppChannel | High | Backend |
| 12.6 | Create notification classes (PlanApproved, PlanActivated, WorkoutReminder, etc.) | Critical | Backend |
| 12.7 | Create DeviceToken model | Critical | Backend |
| 12.8 | Create NotificationPreference model | High | Backend |
| 12.9 | Build NotificationController | High | Backend |

### Phase 13: Adaptation (3d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 13.1 | Implement AdaptationEngine (adherence < 0.5 → reduce_load, weight_delta → adjust_kcal) | High | Backend |
| 13.2 | Implement RunWeeklyAdaptationJob (per-tenant fan-out, TenantAware) | High | Backend |
| 13.3 | Implement MaybeReplanJob (triggered by WeighInRecorded, delta > 0.7kg/7d) | High | Backend |
| 13.4 | Implement RecomputeAdherenceJob | High | Backend |
| 13.5 | Create adaptation cron command (scheduler) | High | Backend |
| 13.6 | Connect WeighInRecorded → MaybeReplanJob event | High | Backend |

### Phase 14: Admin Panel (2d)

| # | Task | Priority | Backend Type |
|---|------|----------|-------------|
| 14.1 | Install Filament v3 | Critical | Backend |
| 14.2 | Configure Filament admin guard | Critical | Backend |
| 14.3 | Enable mandatory 2FA (TOTP via Fortify) | Critical | Backend |
| 14.4 | Create TenantResource | Critical | Backend |
| 14.5 | Create UserResource | Critical | Backend |
| 14.6 | Create PlanResource | High | Backend |
| 14.7 | Create SubscriptionResource | High | Backend |
| 14.8 | Create PricingPlanResource | High | Backend |
| 14.9 | Create InvoiceResource | High | Backend |
| 14.10 | Create SupportTicketResource | Critical | Backend |
| 14.11 | Create AiPromptVersionResource | High | Backend |
| 14.12 | Create WebhookEventResource | High | Backend |
| 14.13 | Create AnalyticsDashboard (MRR, DAU/MAU, AI cost, failed jobs) | High | Backend |
| 14.14 | Create PromptManager page (hot-reload) | High | Backend |
| 14.15 | Create SupportInbox page | High | Backend |
| 14.16 | Create AuditLog read-only viewer | High | Backend |

### Phase 15: Mobile + Web (8d)

| # | Task | Priority | Type |
|---|------|----------|------|
| 15.1 | Flutter: Project setup | Critical | Mobile |
| 15.2 | Flutter: dio + interceptors (auth, retry, idempotency-key) | Critical | Mobile |
| 15.3 | Flutter: drift schema (outbox, caches) | Critical | Mobile |
| 15.4 | Flutter: Riverpod state | Critical | Mobile |
| 15.5 | Flutter: API client (generated from OpenAPI via openapi-generator) | Critical | Mobile |
| 15.6 | Flutter: Onboarding screen | Critical | Mobile |
| 15.7 | Flutter: Today screen | Critical | Mobile |
| 15.8 | Flutter: Workout Player | Critical | Mobile |
| 15.9 | Flutter: Meal Log screen | Critical | Mobile |
| 15.10 | Flutter: Progress screen | Critical | Mobile |
| 15.11 | Flutter: Chat screen | Critical | Mobile |
| 15.12 | Flutter: Plan View | Critical | Mobile |
| 15.13 | Flutter: Settings | High | Mobile |
| 15.14 | Flutter: FCM integration | High | Mobile |
| 15.15 | Flutter: Offline handling (outbox with exponential backoff) | High | Mobile |
| 15.16 | TALL: Install Livewire | Critical | Web |
| 15.17 | TALL: Fortify views (login, register, password) | Critical | Web |
| 15.18 | TALL: Client management pages | Critical | Web |
| 15.19 | TALL: Livewire plan builder (drag-drop, session_order support) | Critical | Web |
| 15.20 | TALL: Reverb client integration | Critical | Web |
| 15.21 | TALL: Analytics widgets | High | Web |
| 15.22 | TALL: Settings + branding | High | Web |

---

## 3. 🔗 DEPENDENCIES

### Phase Dependency Chain

```
Phase 1 ─────┬──► Phase 2
            │          │
            │          ▼
            └──► Phase 3 ◄── Phase 4 ──► Phase 5 ──► Phase 6 ──► Phase 7 ──► Phase 8 ──► Phase 9 ──► Phase 10 ──► Phase 11 ──► Phase 12 ──► Phase 13 ──► Phase 14
                                                                                      │                         │
                                                                                      │                         ▼
                                                                                      └─────────────────────────► Phase 15
```

### Phase Dependencies (Critical Path)

| Phase | Depends On |
|-------|------------|
| Phase 2 | Phase 1 |
| Phase 3 | Phase 1, Phase 2 |
| Phase 4 | Phase 1, Phase 2 |
| Phase 5 | Phase 1, Phase 2, Phase 3 |
| Phase 6 | Phase 1, Phase 2, Phase 3, Phase 5 |
| Phase 7 | Phase 1-6 |
| Phase 8 | Phase 1-6, Phase 7 |
| Phase 9 | Phase 1-6, Phase 7, Phase 8 |
| Phase 10 | Phase 1-6, Phase 9 |
| Phase 11 | Phase 1-6, Phase 10 |
| Phase 12 | Phase 1-6, Phase 11 |
| Phase 13 | Phase 1-10 |
| Phase 14 | Phase 1-13 |
| Phase 15 | Phase 1-14 (parallel from Phase 8) |

### Task-Level Dependencies

| Task | Depends On |
|------|-----------|
| 4.3 RegisterAction | 4.1 User model |
| 5.3 TenantContextResolver | 5.1 Tenant model |
| 6.3 CatalogSearchService | 6.1-2 models, 5.4 ResolveTenant |
| 7.7 PlanBuilderService | 7.1-3 models |
| 8.7 LogSetAction | 8.2 SetLog model, 7.8 PrDetector requires 9.4 |
| 9.12 Reverb channels | 9.6-7 ChatThread, Message models |
| 10.9 PlanGenerationOrchestrator | 10.1-8 services |
| 11.6 UsageMeter | 11.5 EntitlementService |
| 15.4 Riverpod state | 15.3 Drift schema |
| 15.8 Workout Player | 8.7 LogSetAction backend |

---

## 4. 🧠 PRIORITY DISTRIBUTION

| Priority | Count | % |
|----------|-------|---|
| Critical | 78 | 72% |
| High | 24 | 22% |
| Medium | 6 | 6% |

### Critical Path Items (Must Complete)

- Phase 1: Project bootstrap, docker, config (1d)
- Phase 2: All Central migrations (1d)
- Phase 3: Tenant migrations + stancl/tenancy (2d)
- Phase 4: Auth + Sanctum + 2FA (3d)
- Phase 5: Tenant provisioning (3d)
- Phase 7: Plan module endpoints (~25 endpoints)
- Phase 8: Workout + Nutrition (~17 endpoints)
- Phase 9: Progress + Messaging + Reverb
- Phase 10: AI Engine (2-call strategy)
- Phase 11: Billing + Stripe webhooks

---

## 5. ⏱️ COMPLEXITY ANALYSIS

| Phase | Complexity | Days | Rationale |
|-------|------------|------|----------|
| Phase 1: Core | Small | 1d | Standard setup |
| Phase 2: Central DB | Small | 1d | Standard migrations |
| Phase 3: Tenant | Medium | 2d | Tenancy bootstrapper complexity |
| Phase 4: Auth | Medium | 3d | Auth + Sanctum + 2FA |
| Phase 5: Tenancy | Medium | 3d | Tenant resolution + invites |
| Phase 6: Catalog | Medium | 3d | Search + merge + seeders |
| Phase 7: Plan | Large | 5d | 25+ endpoints, models, versioning |
| Phase 8: Workout | Medium | 4d | Idempotency + PR detection |
| Phase 9: Progress | Medium | 4d | Reverb channels + S3 |
| Phase 10: AI Engine | **Large** | 8d | 2-call LLM, validator, orchestrator |
| Phase 11: Billing | Medium | 4d | Cashier + usage metering |
| Phase 12: Notifications | Small | 2d | FCM channel |
| Phase 13: Adaptation | Medium | 3d | Decision engine + cron |
| Phase 14: Admin | Small | 2d | Filament resources |
| Phase 15: Frontend | **Large** | 8d | Flutter + TALL |

**Total: ~46 days**

---

## 6. 🎯 MVP vs FUTURE FEATURES

### MVP Features (Build in Order)

| Feature | Phase | Description |
|---------|-------|-------------|
| Multi-database tenant provisioning | 3 | CREATE DATABASE tenant_{ulid}, migrations |
| User registration + login + OTP | 4 | Sanctum token, 2FA |
| Tenant creation + invites | 5 | ResolveTenant middleware |
| Exercise/Food catalog | 6 | Merged global + tenant |
| Plan creation/approve/activate | 7 | Versioning, soft delete |
| Workout session logging | 8 | Idempotency, PR detection |
| Meal logging | 8 | Idempotency, food_scope |
| Progress tracking | 9 | Weigh-ins, measurements, photos |
| Chat (1:1) | 9 | Reverb channels |
| AI plan generation | 10 | 2-call strategy |
| Basic billing + Stripe | 11 | Cashier, EnsureFeature |
| Push notifications | 12 | FCM channel |
| Filament admin | 14 | Resources + dashboards |

### V2 Features (Post-MVP)

| Feature | Phase | Description |
|---------|-------|-------------|
| Stripe Connect | 11 | B2B2C revenue share, stripe_connect_accounts table |
| Group chat | 9 | chat_thread_members table |
| Check-in forms | — | check_in_forms, check_in_responses tables |
| Program cohorts | — | program_cohorts, cohort_members tables |
| Wearable sync | 15 | Apple Health / Google Fit |
| White-label | 5 | custom_domain + branding |
| Multi-coach | 5 | Staff role support |

---

## 7. 🧱 BACKEND vs FRONTEND SPLIT

### Backend (Laravel) - Phases 1-14
- All migrations (central + tenant)
- Models (User, Tenant, Plan, WorkoutSession, etc.)
- Services, Actions, Controllers
- Middleware, Jobs, Events
- Filament admin panel

### Web (TALL) - Phase 15
- Livewire components
- Fortify views
- Reverb client
- Plan builder drag-drop

### Mobile (Flutter) - Phase 15
- Dio + interceptors
- Drift local DB
- Riverpod state
- All client screens

---

## 8. 🚀 FINAL EXECUTION ORDER

```
STEP  PHASE                         DAYS   CUMULATIVE
────  ═════                         ═══   ══════════
 1    Phase 1: Core                 1d    1d
 2    Phase 2: Central DB          1d    2d
 3    Phase 3: Tenant              2d    4d
 4    Phase 4: Auth                3d    7d
 5    Phase 5: Tenancy             3d   10d
 6    Phase 6: Catalog             3d   13d
 7    Phase 7: Plan                5d   18d
 8    Phase 8: Workout             4d   22d
 9    Phase 9: Progress            4d   26d
10    Phase 10: AI Engine          8d   34d
11    Phase 11: Billing            4d   38d
12    Phase 12: Notifications      2d   40d
13    Phase 13: Adaptation        3d   43d
14    Phase 14: Admin             2d   45d
15    Phase 15: Frontend          8d   53d
16    Phase 16: Hardening         3d   56d
```

> **Note:** Final 3 days for hardening (tests, phpstan level 8, CI pipeline, security audit)

---

## 9. ⚠️ RISK POINTS

### Technical Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| AI validator pass-rate < 98% | Medium | High | Golden tests on 20 profiles, fallback to deterministic, 2 retries |
| Multi-tenant DB provisioning failure | Low | High | TenantProvisioningFailed event, ops alert, db_name=NULL for retry |
| Cross-DB reference miss | Medium | High | FormRequest validators, service guards (PlanBuilderService, LogSetAction) |
| Stripe webhook out of order | Medium | High | webhook_events table for idempotent processing |
| Token bucket rate limit hit | Medium | Medium | Redis pre-increment + DB persist on success |
| Reverb connection drops | Medium | Low | Polling fallback in Flutter |

### Dependency Risks

| Risk | Mitigation |
|------|------------|
| stancl/tenancy v3 migration | Seed template tenant, test destroy/recreate |
| Claude API failure | Failover to OpenAI (gpt-4o) |
| Large migration rollback | Separate central vs tenant migrations |

### Scaling Risks

| Risk | Mitigation |
|------|------------|
| Too many tenant DBs | Horizontal sharding (future) |
| AI cost overrun | Cost guard in LlmClient (max $1/request) |
| Redis high memory | Tenant-aware cache prefixing |

---

## 📋 QUICK START (Day 1)

Developers should start with:

1. Run `composer require laravel/sanctum laravel/cashier laravel/horizon laravel/reverb laravel/scout stancl/tenancy filament/filament:^3`
2. Run `composer require --dev laravel/pint phpstan/phpstan pestphp/pest pestphp/pest-plugin-laravel`
3. Setup docker-compose (MySQL 8, Redis 7)
4. Run migrations from `database/migrations/central/` then `tenant/`
5. Verify central DB seed data (500 exercises, 3000 foods)
6. Test tenant provisioning: `TenantProvisioner::provision($tenant)`
7. Test auth: register + login + token claims
8. Test ResolveTenant middleware with X-Tenant-Slug header

---

**END OF EXECUTION PLAN**