<!--
SYNC IMPACT REPORT
==================
Version change: 1.0.0 → 1.1.0
Bump rationale: MINOR. BLUEPRINT.md was rewritten to v2 (multi-DB tenancy with no
`tenant_id` columns, scope-tagged catalog, single-call full-plan AI strategy,
insert-history personal records, idempotency on `meal_logs`, soft deletes on key
models, split usage counters, four explicit Reverb channels, `claude-opus-4-6`
default with `gpt-4o` fallback). The constitution's seven principles still hold,
but the rules under them are tightened and expanded with new specifics. No
principles renamed or removed. No backward-incompatible changes.

Modified principles (rules tightened/expanded — names unchanged):
- I. Tenant Isolation (NON-NEGOTIABLE)
    - Added: explicit cross-DB integrity rule. Tenant-DB columns that reference
      central-DB IDs (e.g. `user_id`, `coach_user_id`) MUST be enforced at the
      application layer (FormRequest + service guards) since MySQL cannot
      enforce cross-DB FKs.
    - Added: tenant-DB tables MUST NOT carry a `tenant_id` column — context is
      implicit from the connection.
- II. Coach-in-the-Loop AI Safety (NON-NEGOTIABLE)
    - Tightened: AI generation MUST use the two-call full-plan strategy
      (one call for the 4-week exercise plan, one for the 4-week meal plan).
      Per-day or per-muscle calls are forbidden.
    - Tightened: default model `claude-opus-4-6`; `gpt-4o` is the only sanctioned
      fallback. Model changes are an amendment.
- III. Idempotent, Versioned, Append-Only Writes
    - Tightened: `meal_logs` MUST carry `idempotency_key UNIQUE` (was implicit
      under the general POST rule; now explicit because v1.0 BLUEPRINT had
      omitted it).
    - Added: `personal_records` MUST be insert-only — every PR is a new row.
      "Current best" is derived via `MAX(value)`. No upsert.
    - Added: soft delete (`deleted_at TIMESTAMP(3) NULL`) MUST be present on
      `plans`, `client_profiles`, `workout_sessions`, `chat_threads`, and
      `messages` (already there).
- IV. API Contract Discipline
    - Added: every cross-scope reference (exercise/food ids that may resolve to
      either central global or tenant-custom rows) MUST carry an explicit
      `*_scope ENUM('global','tenant')` field on both the schema and the API
      surface. Mismatches are 422 errors with stable codes.
- V. Test-First & Isolation Verification (NON-NEGOTIABLE)
    - Tightened: AI release gate cost envelope dropped from p95 < $0.40 to
      p95 < $0.30 per generation, reflecting the two-call strategy. Latency
      gate (p95 < 30s) and validator pass-rate gate (≥ 98% over 50 generations)
      unchanged.
- VI. Observability & Cost Guardrails (no rule change; provider list updated below)
- VII. Security & Privacy by Default (no rule change)

Modified support sections:
- Technology & Architecture Constraints: AI provider line updated to
  `claude-opus-4-6` primary / `gpt-4o` fallback; added "split usage counters"
  (`tenant_usage_counters` + `user_usage_counters`) and "four named Reverb
  channels" (`private-thread.{id}`, `private-user.{id}`, `private-tenant.{id}`,
  `private-admin`).
- Development Workflow & Quality Gates: AI release-gate cost envelope updated
  to $0.30 to match Principle V.

Added sections: none.
Removed sections: none.

Templates requiring updates:
- ✅ .specify/memory/constitution.md — written (this file).
- ⚠ .specify/templates/plan-template.md — "Constitution Check" gate is still the
  generic stub. Recommended (deferred): replace with a per-principle checklist
  by Roman numeral. Carried over from v1.0.0.
- ✅ .specify/templates/spec-template.md — no change required.
- ✅ .specify/templates/tasks-template.md — no change required.
- N/A README.md, docs/quickstart.md, CLAUDE.md, AGENTS.md — none exist; runtime
  guidance is `BLUEPRINT.md` (now v2), `TECHNICAL_PLAN.md`, `PLAN.md`.
- N/A .specify/templates/commands/ — directory does not exist in this repo.

Deferred TODOs: none. RATIFICATION_DATE held at 2026-05-04 (original);
LAST_AMENDED_DATE updated to 2026-05-05.
-->

# Fetness App Constitution

## Core Principles

### I. Tenant Isolation (NON-NEGOTIABLE)

The platform is multi-tenant. Every coach, gym, or studio account is a tenant, and a
tenant's business data MUST never be readable, writable, or inferable from another
tenant's request path.

Rules:
- The Central database (`fetness_central`) holds ONLY platform-wide data: identity,
  tenant registry, billing, global catalog, AI prompt registry, audit log, support
  tickets, push routing. It MUST NOT hold tenant-business records (plans, logs,
  messages, progress, AI generation requests).
- Each tenant's business data lives in its own database (`tenant_{ulid}`),
  provisioned by `TenantProvisioner` on tenant creation. Tenant migrations live
  under `database/migrations/tenant/`.
- Tenant-DB tables MUST NOT carry a `tenant_id` column. Tenant context is
  implicit from the active database connection. Any column suggesting otherwise
  is a defect.
- Cross-DB references (e.g. `client_profiles.user_id`, `coach_user_id`,
  `generated_by_user_id`, `created_by_user_id` referencing `central.users.id`)
  MUST NOT use a MySQL FK constraint — MySQL cannot enforce integrity across
  databases. They MUST be enforced at the application layer by FormRequest
  validators plus service-layer guards (`PlanBuilderService`, `LogSetAction`,
  `LogCustomMealAction`, `WorkoutLogService`, `ClientNoteService`,
  `MessagingService`). Each such column MUST be annotated explicitly in the
  schema docs.
- Every tenant-bound model MUST resolve to the tenant connection at runtime. A
  static-analysis test MUST assert this for every model under
  `app/Domains/**/Models` (allow-list only for explicitly central models such as
  `User`, `Tenant`, `GlobalExercise`, `GlobalFood`, `GlobalFoodAlias`,
  `PricingPlan`, `SupportTicket`).
- A two-tenant fixture isolation test MUST run on CI and MUST exercise every
  list/read endpoint, asserting zero cross-tenant leakage. New endpoints without
  a matching isolation assertion MUST be blocked at PR review.
- Tenant context is resolved per request from subdomain, custom domain, header
  (`X-Tenant-Slug`), or token claim (`tnt:{ulid}` ability) — in that order.
  Requests that cannot resolve to a single tenant MUST fail with
  `400 TENANT_REQUIRED`; they MUST NOT silently fall back to a "default" tenant.

Rationale: Cross-tenant data leakage is catastrophic — it is simultaneously a trust
breach, a legal/GDPR breach, and a contractual breach with every coach using the
platform. The cost of preventing it (per-DB tenancy + isolation tests + explicit
app-layer guards on cross-DB refs) is dwarfed by the cost of one incident.

### II. Coach-in-the-Loop AI Safety (NON-NEGOTIABLE)

The product's USP is "AI proposes, coach approves, user executes." Direct LLM
output is never the source of truth for what a user trains or eats.

Rules:
- LLM output MUST pass the `PlanValidator` hard-rule pipeline before persistence:
  per-day kcal within ±10% of computed target; per-day protein ≥ 90% of target
  and fat ≥ 50% of target; per-day kcal floor of 1500 (male) / 1200 (female);
  per-item allergen-tag intersection with client allergies MUST be empty;
  diet-tag compliance MUST hold for halal/vegan/etc.; per-week per-muscle volume
  in [50%, 150%] of computed target with frequency ≥ 2× per muscle; no exercise
  whose `injury_tags` intersect the client's `injuries`.
- Targets (TDEE, macros, volume distribution, split selection) MUST be computed
  by the deterministic `RuleEngineService` in pure PHP, NOT by the LLM.
- Plans created via `source='ai'` MUST land as `status='pending_review'` (or
  `status='approved'` only when the tenant has explicitly enabled
  `settings.auto_approve`). Activation requires either coach action or that opt-in.
- Validator failures retry up to 2× with corrective prompts and incrementing
  `ai_generation_requests.retry_count`; on the third failure, the request MUST
  be marked `validation_failed` with reasons surfaced to the coach. The system
  MUST NOT relax rules to make a draft "fit."
- Plan generation MUST use the **two-call full-plan strategy**: exactly one
  LLM call returns the full 4-week exercise plan, and exactly one LLM call
  returns the full 4-week meal plan. Per-day or per-muscle fan-out calls are
  forbidden — they were measured to balloon cost and latency without quality
  gain. Both calls use `response_format: json_object` with a strict schema and
  `max_tokens: 8000`.
- The LLM provider layer MUST be abstracted. The default model is
  `claude-opus-4-6` (Claude provider); the only sanctioned fallback is
  `gpt-4o` (OpenAI provider). Any other model is an amendment-level decision.
  A per-request hard cost ceiling (default $1) MUST abort generation with
  `BUDGET_EXCEEDED` rather than complete-at-any-price.
- LLM-returned macro values are NOT trusted. Item macros MUST be recomputed
  from the resolved `food_id` row × `quantity_g`/`serving_size_g` in
  `FoodMatchService` before the validator runs.

Rationale: LLMs are creative but unsafe by themselves — they will happily prescribe
peanut-laden meals to peanut-allergic users and 30-set leg days. Safety comes from
deterministic rules; variety and personality come from the LLM. The validator is a
non-negotiable seat belt, not a polish layer. The two-call strategy is the only
shape that hits the cost envelope (Principle V) without sacrificing safety.

### III. Idempotent, Versioned, Append-Only Writes

Mobile clients are offline-first; webhooks retry; coaches and clients edit
concurrently. The data model MUST tolerate replays and retain history.

Rules:
- Every POST that creates a resource (logs, meals, sets, plans, AI requests)
  MUST require an `Idempotency-Key` header. The server MUST persist the key on
  the resource row (`UNIQUE(idempotency_key)` where applicable) and MUST return
  the original response on replay. Missing keys → `400 IDEMPOTENCY_KEY_REQUIRED`.
  Tables that MUST carry `idempotency_key UNIQUE` include at minimum
  `workout_sessions`, `set_logs`, `meal_logs`, and `ai_generation_requests`.
- Webhook ingestion (Stripe, Twilio, FCM) MUST deduplicate by
  `(provider, event_id)` via the `webhook_events` unique constraint. Duplicate
  events are a no-op success.
- Workout logs (`set_logs`, `workout_sessions`) and nutrition logs
  (`meal_logs`, `meal_log_items`, `water_logs`) are append-only at the API
  surface. Edits MUST create new rows or use soft-delete; bulk mutation paths
  are forbidden.
- `personal_records` is **insert-only**: every detected PR is a new row, never
  an upsert. The `(client_profile_id, exercise_id, kind)` tuple MUST NOT carry
  a UNIQUE constraint. "Current best" is derived via `MAX(value)`. This
  preserves PR history for charting and AI training data.
- Plans are versioned via `parent_plan_id` and `version`. Replanning MUST
  insert a new `plans` row and MUST NOT mutate an existing approved or active
  plan. Activating a new version MUST archive the previous active version
  atomically in the same DB transaction.
- Soft-delete (`deleted_at TIMESTAMP(3) NULL`) MUST be present on `plans`,
  `client_profiles`, `workout_sessions`, `chat_threads`, and `messages`.
  Hard delete only via the GDPR purge job.
- All timestamps MUST be UTC `TIMESTAMP(3)`. Client-supplied `logged_at` MUST
  be validated against device clock skew (≤ 5 min into the future).

Rationale: Append-only + idempotent + versioned + insert-history is the simplest
joint guarantee that makes offline sync, financial reconciliation, audit, AI
training data, and progress charts all work correctly with no special cases.
Any violation here cascades into corrupted analytics and unfixable user reports.

### IV. API Contract Discipline

A single Laravel API serves Flutter, the TALL coach web, and the Filament admin.
Contracts are the boundary; drift is a bug.

Rules:
- Public API is REST + JSON, mounted at `/api/v1` (URL versioning). Breaking
  changes ship as `/v2`. The two most recent majors MUST be supported for at
  least 12 months after a new major ships.
- Identifiers are ULIDs stored as `CHAR(26)`, prefixed in the API surface
  (`pln_…`, `usr_…`, `cli_…`, `exe_…`, `fod_…`). Integer surrogate IDs are
  forbidden in API responses.
- Every reference to an entity that can resolve to either a central-DB row or a
  tenant-DB row (currently exercises and foods) MUST carry an explicit
  `*_scope ENUM('global','tenant')` field both in the schema (`exercise_scope`,
  `food_scope`) and in the API surface (`exercise_scope`, `food_scope`,
  `original_exercise_scope`, `replacement_exercise_scope`). Mismatches return
  `422 EXERCISE_SCOPE_MISMATCH` / `FOOD_SCOPE_MISMATCH`. Lookups without an
  explicit scope, when ambiguous, MUST return `422 SCOPE_REQUIRED`.
- JSON uses snake_case keys. Booleans use `is_`/`has_` prefixes. Timestamps end
  in `_at` and are RFC 3339 UTC. Enums are returned as strings, never integers.
- Every successful response uses the envelope
  `{ "data": …, "meta": { "request_id": "…", "next_cursor": null|string } }`.
  Every error uses
  `{ "error": { "code": "…", "message": "…", "details": {}, "trace_id": "…" } }`.
  `code` is a stable string enum; clients switch on `code`, never on `message`.
- List endpoints whose result set can grow (logs, messages, requests) MUST use
  cursor pagination. Offset pagination is forbidden for those.
- Mutating endpoints that create resources MUST honor `Idempotency-Key`
  (Principle III). Money-affecting endpoints (billing, AI generation) MUST also
  enforce per-tenant rate limits.
- HTTP status codes are used semantically: `400` malformed/idempotency, `401`
  unauthenticated, `402` payment required, `403` authorization, `404` missing,
  `409` conflict, `410` closed, `422` domain validation, `423` locked/suspended,
  `429` rate/usage, `5xx` server fault.

Rationale: The Flutter team, the web team, and external integrators all read
the same contract. Stable shape, stable error codes, and explicit scope flags
on cross-DB references are the difference between a 2-day mobile release and a
2-week firefight.

### V. Test-First & Isolation Verification (NON-NEGOTIABLE)

Tests are written before implementation, and the tests that protect tenant
isolation, billing, and AI safety MUST exist for every change in those areas.

Rules:
- TDD discipline applies to every new feature: tests are written first, reviewed
  by the requesting party, observed to fail, and only then implemented (Red →
  Green → Refactor).
- Pest coverage MUST be ≥ 85% across `app/Domains/**`. PRs that drop coverage
  below this floor are rejected.
- `phpstan` level 8 MUST pass on CI. Lint (`pint`) MUST pass.
- A static-analysis test MUST verify every tenant model uses the tenant
  connection (Principle I).
- The two-tenant isolation suite MUST be re-run on every PR that touches API
  routes, controllers, services, or migrations.
- The AI engine MUST hold these gates after Step 11 of the build order, measured
  on a 50-generation smoke run:
  - validator pass-rate ≥ 98%;
  - p95 latency < 30s;
  - **p95 cost < $0.30 USD per generation** (tightened from $0.40 to reflect
    the two-call strategy in Principle II).
  Regressions on these gates MUST block release.
- Stripe billing MUST have end-to-end tests using Stripe CLI fixtures, plus a
  gating test for every `EnsureFeature`, `EnforceTenantStatus`, and
  `EnforceClientCap` path. Usage-counter routing (Tenant ↔ User) MUST be
  exercised on both sides.

Rationale: Without test-first discipline, the rule-engine validator, the tenant
isolation guarantee, and the entitlement matrix all rot silently. These three
systems are the parts that, when broken, harm users or sink the business — they
get the strictest test gates. Tightening the cost gate is what keeps the
two-call strategy honest.

### VI. Observability & Cost Guardrails

The system MUST be debuggable in production and MUST NOT become a runaway cost
center on third-party APIs.

Rules:
- Every log line MUST carry `tenant_id` (when present), `user_id`, `request_id`,
  and `trace_id`. Logs are structured JSON.
- Errors are sent to Sentry with `trace_id` matched to the API error envelope.
  OpenTelemetry tracing covers HTTP → queue → external API.
- LLM usage MUST be metered per request into `ai_cost_ledger` (central) and per
  generation request (tenant). A per-tenant token bucket on the `ai` queue
  (default 6 req/min, configurable in `tenants.settings.ai.rate_limit`) MUST be
  enforced.
- Quota-bound features (`ai_plans_per_month`, `max_clients`, etc.) MUST flow
  through `EnsureFeature` middleware that pre-checks the appropriate counter
  table (`tenant_usage_counters` for B2B, `user_usage_counters` for B2C — see
  Tech Constraints) and post-increments only on `2xx`. Free tier defaults are
  the floor.
- Per-tenant LLM spend MUST have an admin-configurable cap with alerting.
  Exceeding the cap MUST stop further generations and notify the platform admin
  and the tenant owner.
- Performance budgets: API p95 < 250ms, mobile cold-start < 1.5s on mid-tier
  Android, plan generation p95 < 30s end-to-end. Regressions trigger a
  performance pass before further feature work in the affected area.

Rationale: An AI-heavy SaaS product has two failure modes that look identical
from the application's perspective: "the model is broken" and "the model is
fine but we're hemorrhaging money." Telemetry, metering, and caps are how we
keep them apart.

### VII. Security & Privacy by Default

Fitness data is health data. The platform MUST treat it that way from day one.

Rules:
- All data at rest is encrypted (RDS encryption, S3 SSE). Secrets live in AWS
  Secrets Manager — never in `.env` files committed to source.
- 2FA is mandatory for the Filament admin guard. Coach and client 2FA is
  available via `me/2fa/*` and SHOULD be encouraged for coach accounts that
  hold > 10 clients.
- Sanctum bearer tokens for mobile (30-day TTL, stored in
  `flutter_secure_storage`); session cookies + CSRF for web; HMAC verification
  for every webhook. Public API (V3) requires OAuth2 client_credentials with
  per-key rate limits. Refresh is by re-login (no refresh-token endpoint).
- Rate limits MUST be enforced per endpoint class: 60 req/min default, 10
  req/min on AI generation endpoints, 5 req/min on auth endpoints.
- Security headers (HSTS, CSP, X-Content-Type-Options, X-Frame-Options) MUST
  be set globally. CORS is locked down to known frontends.
- An immutable `audit_log` (central) MUST capture actor, tenant, action,
  subject, changes, IP, and user-agent for every privileged operation
  (subscription changes, plan approval/activation, member removal, admin
  impersonation, GDPR delete).
- GDPR data export and hard-delete flows MUST be implemented in MVP. Hard
  deletes run via dedicated job that purges across central + tenant DB + S3
  (using the `tenant_{ulid}/` filesystem prefix).
- Health/medical claims are forbidden in product copy. Disclaimers
  ("fitness, not medical") MUST appear on onboarding, plan view, and store
  listings before launch.

Rationale: Health data + multi-tenant + financial data is the worst possible
breach surface. Default-secure beats retrofitted-secure by an order of
magnitude in cost and trust impact, and App Store review will reject medical
claims regardless.

## Technology & Architecture Constraints

The following choices are ratified and MAY NOT be changed without a constitutional
amendment (Governance below):

- **Backend**: Laravel 11, PHP 8.3. Modular monolith — bounded contexts under
  `app/Domains/{Identity, Tenancy, Catalog, Plan, Workout, Nutrition, Progress,
  Messaging, AI, Billing, Notification, Admin}`. Each module exposes Services
  (called by controllers/jobs) and Actions (single-purpose invokables). Models
  stay thin.
- **Datastore**: MySQL 8 (multi-database tenancy via `stancl/tenancy` v3 —
  central + per-tenant DBs, no `tenant_id` columns on tenant tables). Redis 7
  for cache, queue, and broadcasting state. Bootstrappers configured:
  `DatabaseTenancyBootstrapper`, `CacheTenancyBootstrapper`,
  `FilesystemTenancyBootstrapper`, `QueueTenancyBootstrapper`.
- **Async**: Laravel Horizon over Redis. The `ai` queue is bounded to 4 workers
  with a per-tenant token bucket. AI calls are NEVER inline in HTTP handlers.
  Every queued job that touches tenant data uses the `TenantAware` trait.
- **Realtime**: Laravel Reverb on a dedicated task with sticky sessions. Four
  named channels are sanctioned: `private-thread.{thread_id}` (chat),
  `private-user.{user_id}` (client-targeted plan/AI events),
  `private-tenant.{tenant_id}` (coach inbox), `private-admin` (platform alerts).
  New channels are an amendment-level decision.
- **Auth**: Sanctum (mobile bearer, 30-day PAT), session+CSRF (web),
  Fortify+TOTP (admin, mandatory 2FA).
- **Billing**: Stripe via Laravel Cashier; Connect for coach payouts (V2).
  Usage is metered in two separate counter tables — `tenant_usage_counters`
  for B2B (coach plans) and `user_usage_counters` for B2C (Pro plan). The
  `UsageMeter` service routes between them based on caller context.
- **AI**: provider-abstracted `LlmClient` with primary `claude-opus-4-6`
  (Anthropic) and fallback `gpt-4o` (OpenAI). Two-call full-plan strategy
  (Principle II). `max_tokens: 8000` per call. Provider list and model
  defaults change only via amendment.
- **Catalog**: split into central globals (`global_exercises`, `global_foods`,
  `global_food_aliases`) and tenant-custom (`exercises`, `foods`).
  `CatalogSearchService` merges results and tags every row with
  `scope: 'global'|'tenant'` (Principle IV).
- **Frontends**: Flutter 3 (mobile, Riverpod + Drift, offline-first); TALL
  stack (coach web — Tailwind + Alpine + Livewire + Laravel); Filament v3
  (admin).
- **External services**: Stripe, Twilio (SMS OTP), AWS S3 + CloudFront, FCM,
  Anthropic (`claude-opus-4-6`), OpenAI (`gpt-4o`), Sentry, Mixpanel.
- **IDs**: ULID `CHAR(26)`, type-prefixed in API surface.
- **Time**: UTC `TIMESTAMP(3)` everywhere. Client-supplied timestamps validated
  against device-clock skew.
- **Migrations**: split into `database/migrations/central` and
  `database/migrations/tenant`. Schema changes follow expand-then-contract — no
  destructive migration ships in the same release as the code requiring it.
- **Seeding**: ~500 global exercises and ~3,000 global foods (USDA + MENA-curated)
  MUST be present at MVP. Five `pricing_plans` seeded: `free`, `pro`,
  `coach_starter`, `coach_pro`, `coach_studio`.

Deviations from any item above MUST be raised as an amendment, justified in the
Complexity Tracking section of the affected plan, and accepted before merge.

## Development Workflow & Quality Gates

The project ships in 2-week sprints with trunk-based development. The pipeline
that protects the principles above:

- **Branching**: trunk-based on `main`. Feature work happens on short-lived
  branches behind feature flags. Direct push to `main` is forbidden; every
  change requires PR + at least one engineer review + green CI.
- **CI pipeline (GitHub Actions)**, in order, all gating:
  1. `pint` (style) MUST pass.
  2. `phpstan` level 8 MUST pass.
  3. `pest` suite MUST pass, including the two-tenant isolation suite.
  4. Coverage report MUST be ≥ 85% on `app/Domains/**`.
  5. `php artisan migrate --pretend` against central + tenant template MUST
     be clean.
  6. Build artifacts produced; staging auto-deploys on merge.
- **Production deploys** are manual one-click promotions from staging. DB
  migrations run via the deploy job. Backwards-compatible expand → deploy code →
  contract is the ordering rule.
- **Constitution Check gate**: `/speckit.plan` MUST evaluate every plan against
  the seven principles before Phase 0 research and again after Phase 1 design.
  Violations land in the plan's Complexity Tracking table with a justification
  and a rejected simpler alternative.
- **AI engine release gate**: per Principle V, validator pass-rate ≥ 98%, p95
  latency < 30s, **p95 cost < $0.30** per generation over a 50-generation smoke
  run. Re-measured before any prompt-version promotion to `is_active=true`.
- **Backups & DR**: nightly `mysqldump` per tenant DB to S3 with 30-day
  retention; RDS automated daily snapshots with PITR; quarterly DR drill that
  validates recovery time < 4h.
- **Observability before launch**: every new public endpoint MUST emit a
  structured access log line with the standard correlation fields before it
  can ship to staging.

## Governance

This constitution supersedes ad-hoc team conventions, individual preferences, and
prior unratified planning notes. Where it conflicts with `BLUEPRINT.md`,
`PLAN.md`, or `TECHNICAL_PLAN.md`, the constitution wins; those documents are
runtime guidance for implementation detail (schemas, endpoint shapes, build
order) and SHOULD be amended in lockstep when this constitution changes.

Amendment procedure:
1. A proposed amendment is a PR that edits this file. The PR description MUST
   explain the change, the affected principle(s), the migration plan for code or
   processes that violate the new rule, and the proposed version bump.
2. The PR MUST update the Sync Impact Report at the top of this file (above the
   `# Fetness App Constitution` heading), the version line at the bottom, and
   the `LAST_AMENDED_DATE`.
3. Versioning follows semantic rules:
   - **MAJOR**: backward-incompatible governance or principle removals or
     redefinitions (e.g., dropping a NON-NEGOTIABLE principle, changing
     tenancy model, abandoning the validator gate).
   - **MINOR**: a new principle or section, or a materially expanded rule.
   - **PATCH**: clarifications, wording, typos, non-semantic refinements.
4. The PR MUST cross-link any dependent template updates
   (`.specify/templates/plan-template.md`, `.specify/templates/spec-template.md`,
   `.specify/templates/tasks-template.md`, runtime guidance docs) and either
   land them in the same PR or list them as follow-up TODOs in the Sync Impact
   Report.
5. Two engineer approvals are required for MAJOR amendments; one is sufficient
   for MINOR and PATCH.

Compliance review:
- Every PR review MUST verify the change is consistent with the seven
  principles. Reviewers cite the principle by Roman numeral when blocking.
- Every `/speckit.plan` invocation MUST run the Constitution Check gate. Plans
  that justify a deviation populate Complexity Tracking; reviewers may reject
  insufficient justification.
- Quarterly: a maintainer audits this file against actual codebase state and
  opens amendment PRs to close drift.

Runtime guidance:
- `BLUEPRINT.md` (v2) — full database, module, API, AI, billing, tenancy,
  edge-case, and build specification. Treat as the implementation-ready
  reference. Latest substantive update aligned with constitution v1.1.0.
- `TECHNICAL_PLAN.md` — module breakdown and detailed API/data shapes.
- `PLAN.md` — product vision, roadmap, USP, and risks. Use for "why" context
  when scope decisions are ambiguous.

**Version**: 1.1.0 | **Ratified**: 2026-05-04 | **Last Amended**: 2026-05-05
