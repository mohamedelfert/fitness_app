# 🚀 AI-Powered Fitness & Diet SaaS Platform — Execution Plan

---

## 1. 🧠 Product Vision

### The Problem
The fitness market is fragmented and broken:
- **End users** bounce between 4–6 apps (workout tracker, calorie counter, sleep, water, supplements) with no unified intelligence. Generic plans ignore their actual data, goals, allergies, equipment, and culture (e.g. Ramadan, halal diets, regional foods).
- **Coaches & trainers** still operate via WhatsApp + Excel. They lack a professional client management system, can't scale beyond ~30 clients, and can't prove outcomes to retain them.
- **Gyms & boutique studios** have no software layer that connects coach, client, and program in one place.

### Target Users (Personas)

| Persona | Profile | Core Pain | Core Job-To-Be-Done |
|---|---|---|---|
| **End User (Trainee)** | 18–45, mobile-first, intermediate fitness literacy, willing to pay for results | "I don't know what to eat or train today, and nothing adapts to me" | Get a daily plan that adapts to my progress, log fast, see results |
| **Coach (Pro)** | Independent PT or nutritionist, 10–80 clients, sells coaching online | "Managing clients in WhatsApp is killing me; I can't scale or prove ROI" | Onboard, plan, track, and bill clients in one branded tool |
| **Gym/Studio Owner** | Owns or manages a facility with 3–15 trainers | "I have no operational visibility on what my coaches deliver" | Manage trainers, members, retention, revenue |
| **Platform Admin** | Internal team | Operate platform, support, content moderation | Manage tenants, content library, billing, support tickets |

### Unique Selling Proposition (USP)
> **"The first fitness platform where AI personalizes the plan, the coach refines it, and the user just executes."**

Concretely — three USPs no single competitor combines:
1. **Coach + AI hybrid loop** — AI proposes plans, coach approves/edits in seconds, user gets a human-vetted personalized plan. Most apps are pure-AI (low trust) or pure-coach (no scale).
2. **Adaptive replanning** — plan recalculates from real adherence (logged sets, food, sleep, weight) every 7 days, not from a static questionnaire.
3. **Multi-tenant Coach SaaS** — every coach/gym gets their own branded tenant (white-label), billed monthly per active client.

### Why This Wins
- **Unit economics** stack: end-user subs (B2C) + coach SaaS (B2B) + per-active-client revenue share (B2B2C). Three revenue streams from one product.
- **Defensibility** comes from data — every logged meal/workout improves AI for the next user. Competitors starting fresh can't catch up.
- **Distribution** via coaches: each onboarded coach brings 20–80 paying clients. CAC drops dramatically vs. pure-B2C apps fighting for App Store keywords.

---

## 2. 🧩 Core Features Breakdown

### 👤 USER FEATURES (Mobile-First, Flutter)

| Feature | What It Does | Why It Matters | Priority |
|---|---|---|---|
| Onboarding wizard | 8-step intake: goal, body metrics, allergies, equipment, schedule, dietary preference, training experience, photo | Feeds the AI the data needed for a usable first plan | MVP |
| Daily Dashboard | Today's workout, today's meals, water, weight, streak | The single screen users open; reduces decision fatigue | MVP |
| Workout player | Exercise list with sets/reps/rest, rest timer, video demos, log weights | Core utility; logging quality drives AI quality | MVP |
| Meal log | Log meals from plan or barcode scan / photo | Adherence data is the #1 input to AI | MVP |
| Progress tracking | Weight, measurements, photos, strength PRs over time | Visible progress → retention | MVP |
| Body metrics & weigh-in reminders | Weekly check-in flow | Feeds adaptive replanning | MVP |
| Chat with coach | 1:1 messaging, voice notes, attachment | If user has a coach, this is their lifeline | MVP |
| Plan adaptation review | Weekly "your plan changed because…" screen | Trust + transparency for AI changes | V2 |
| Habit & streak system | Gamified streaks, badges | Retention | V2 |
| Social/community feed | Tenant-scoped community, posts, comments | Stickiness; coach-led communities | V2 |
| Wearable integration | Apple Health, Google Fit, Garmin sync | Higher-quality data → better AI | V2 |
| Grocery list & meal prep | Auto-generate from week's plan | Removes friction blocker | V2 |
| AI photo food estimator | Snap a plate → estimate macros | Differentiator; reduces logging friction | V3 |
| AI form-check (video) | User films set, AI reviews form | Premium feature, real moat | V3 |
| Voice-logging | "Hey, I just did 8 reps at 80kg" | Reduces logging friction further | V3 |

### 🧑‍🏫 COACH FEATURES (Web, TALL Stack)

| Feature | What It Does | Why It Matters | Priority |
|---|---|---|---|
| Client roster & dashboard | List of all clients, status, last activity, adherence % | Coach's home base | MVP |
| Client profile view | Full intake, plan, progress, chat, notes | One-stop client view | MVP |
| Plan builder | Drag-drop workout & meal plan builder, save as template | Coaches need speed | MVP |
| AI-suggested plans | Generate plan draft from client intake; coach reviews/edits/approves | The 10× productivity feature for coaches | MVP |
| Exercise & food library | Coach can use platform library or add own | Customization | MVP |
| Messaging center | All client chats in one inbox | Replaces WhatsApp | MVP |
| Branded tenant | Custom logo, colors, subdomain (`coach.fetnessapp.io`) | White-label monetization | MVP |
| Plan templates | Save and reuse plan structures | Scaling beyond 30 clients | V2 |
| Automated check-ins | Weekly forms triggered to clients | Frees coach time | V2 |
| Billing & invoicing for clients | Coach charges client through platform; we take % | Revenue stream + lock-in | V2 |
| Group programs / cohorts | One plan, many clients | Margin expansion for coaches | V2 |
| Lead funnel & landing page | Coach gets a landing page to sell coaching | Distribution funnel | V2 |
| Outcome reports | Branded PDF: "client X lost Y kg in Z weeks" | Sales tool for coaches | V3 |

### 🛠 ADMIN FEATURES (Internal Web)

| Feature | What It Does | Priority |
|---|---|---|
| Tenant management | List tenants, suspend, impersonate, view metrics | MVP |
| User management | Search across tenants, GDPR delete | MVP |
| Content library curation | Approve/reject coach-submitted exercises/foods to global library | MVP |
| Billing & subscriptions | Stripe sub view, refunds, dunning | MVP |
| Support ticket inbox | Read/respond, link to user/tenant | MVP |
| Feature flags | Toggle features per tenant | V2 |
| Analytics dashboards | DAU/MAU, retention cohorts, churn, MRR | V2 |
| AI prompt & model management | Update AI prompts without redeploy; A/B test models | V2 |
| Content moderation | Community posts review queue | V2 |

### 🤖 AI FEATURES

| Feature | What It Does | Approach | Priority |
|---|---|---|---|
| Plan generator | Builds workout + meal plan from intake | Rule-based engine + LLM templater | MVP |
| Adaptive replanner | Adjusts plan weekly based on adherence + outcomes | Rules + heuristics initially, ML later | MVP |
| Smart food matcher | "chicken rice bowl" → matches food DB row | Embeddings + fuzzy match | MVP |
| Coach copilot | Suggests plan changes for coach to approve | LLM with client context as RAG | V2 |
| Form-check (video) | Reviews exercise form | 3rd-party pose API initially (e.g. MediaPipe), custom CV later | V3 |
| Photo macro estimator | Plate → macros | Vision model + food embedding | V3 |
| Anomaly detection | "Client X stopped logging — at-risk" | Simple heuristics → predictive model | V3 |

---

## 3. 🗺️ Product Roadmap

### MVP — Months 0–4 (Goal: 50 paying coaches, 1,000 paying users)
**Theme: "A coach can run their entire business in our app, and a client gets a personalized plan."**

Must-have:
- User mobile app: onboarding, dashboard, workout player, meal log, progress, chat
- Coach web: roster, client profile, plan builder, AI plan generator, messaging
- Admin: tenant + user management, billing
- Multi-tenancy + auth + Stripe billing
- Rule-based AI plan generator (templated)
- Exercise + food library (seeded with ~500 exercises, ~3,000 foods)

**Complexity:** High (greenfield). Critical-path: multi-tenancy, plan builder, Flutter app shell.

**Dependencies:** Stripe, Twilio (SMS OTP), AWS S3, OpenAI API.

---

### V2 — Months 5–9 (Goal: 500 coaches, 15K users, $80K MRR)
**Theme: "Stickiness, scale, and revenue expansion."**

- Adaptive replanning (weekly automatic)
- Wearable integrations (Apple Health, Google Fit)
- Coach plan templates + group programs
- In-app coach billing (coaches sell coaching, we take 10%)
- Community feed (tenant-scoped)
- Admin analytics dashboards
- Habit/streak gamification
- Branded landing page generator for coaches

**Complexity:** Medium. Most features build on existing data layer.

**Dependencies:** HealthKit/Google Fit certs, Stripe Connect (for coach payouts).

---

### V3 — Months 10–18 (Goal: market leader in MENA + EU, $500K MRR)
**Theme: "Premium AI features that justify a price hike."**

- Photo food estimator (vision model)
- AI form-check from user-recorded video
- Voice logging
- Predictive churn / at-risk client detection
- Custom-trained recommender (built from accumulated logs)
- Public API for partners (gyms, supplement brands)
- Native iPad coach app

**Complexity:** Very high (real ML). Requires data platform + ML team.

**Dependencies:** Sufficient logged data (~1M meals, ~500K workouts) before custom models are worth training.

---

## 4. 🏗️ System Architecture

```
┌───────────────────────────────────────────────────────────────────┐
│                         CLIENTS                                   │
│  ┌──────────────────┐   ┌──────────────────┐   ┌──────────────┐ │
│  │  Flutter Mobile  │   │  TALL Coach Web  │   │  Admin Panel │ │
│  │  (iOS + Android) │   │ (Livewire + Alp) │   │  (Filament)  │ │
│  └────────┬─────────┘   └────────┬─────────┘   └──────┬───────┘ │
└───────────┼──────────────────────┼─────────────────────┼─────────┘
            │ JSON/REST            │ Server-rendered     │ Server-rendered
            │ + WebSocket          │ + Livewire          │
            ▼                      ▼                     ▼
┌───────────────────────────────────────────────────────────────────┐
│                  LARAVEL API LAYER (PHP 8.3)                      │
│  ┌──────────────┐ ┌──────────────┐ ┌─────────────────┐           │
│  │ Public REST  │ │ Internal RPC │ │ WebSocket gw    │           │
│  │ /api/v1/*    │ │ for web/admin│ │ (Reverb)        │           │
│  └──────────────┘ └──────────────┘ └─────────────────┘           │
│                                                                   │
│  Domain Modules (DDD-lite):                                       │
│  ├ Identity      ├ Plan        ├ Workout                          │
│  ├ Tenancy       ├ Nutrition   ├ Progress                         │
│  ├ Billing       ├ Messaging   ├ AI Orchestration                 │
└───────────────────────────────────────────────────────────────────┘
            │                      │
            ▼                      ▼
┌──────────────────────┐   ┌──────────────────────┐
│ MySQL (primary)      │   │ Async Workers        │
│  - central DB        │   │  - Laravel Horizon   │
│  - tenant tables     │   │  - Queue: Redis      │
│    (single-DB row    │   │  - Plan generator    │
│     scoped)          │   │  - AI calls          │
└──────────────────────┘   │  - Notifications     │
            │              │  - Wearable sync     │
            ▼              └──────────────────────┘
┌──────────────────────┐              │
│ Redis (cache+queue)  │              ▼
└──────────────────────┘   ┌──────────────────────┐
            │              │ External Services    │
            ▼              │  - OpenAI / Claude   │
┌──────────────────────┐   │  - Stripe            │
│ S3 (media, photos)   │   │  - Twilio (SMS)      │
│ + CloudFront CDN     │   │  - Pusher/Reverb     │
└──────────────────────┘   │  - Sentry, Mixpanel  │
                           └──────────────────────┘
```

### Layer Responsibilities

**Backend — Laravel 11 (PHP 8.3)**
- Single API serving mobile + web. JSON for mobile (`/api/v1`), Livewire-internal endpoints for web.
- Owns business logic, multi-tenancy, billing, AI orchestration (calls LLM providers from queue jobs, never inline in HTTP).
- Modular monolith: organized as bounded contexts under `app/Domains/{Identity,Plan,Workout,...}`. Avoids microservice complexity until scale demands it.
- Auth: Sanctum for mobile (bearer tokens), session cookie for web/admin.

**Web — TALL stack (Tailwind + Alpine + Livewire + Laravel)**
- Coach dashboard, plan builder, admin panel.
- TALL chosen because: (a) speed to ship for a small team, (b) shares Laravel models — no API duplication, (c) Livewire is now mature enough for production. Filament v3 for admin to skip 80% of CRUD work.
- Plan builder uses Livewire + Alpine (drag-drop) for interactivity.

**Mobile — Flutter 3.x**
- iOS + Android single codebase.
- State management: Riverpod (preferred over Bloc for this team size).
- Offline-first: Drift (SQLite) for local cache; sync queue for logs entered offline.
- Why Flutter over native: 1 dev-team, 80% feature parity, faster iteration. Native channels only for HealthKit/Google Fit.

### Data Flow Examples

**1. User logs a workout set (mobile):**
`Flutter → POST /api/v1/workouts/{id}/sets → Laravel validates + writes MySQL → dispatches RecalculateAdherence job to queue → Horizon worker updates progress aggregates → if threshold crossed, queues ReplanIfNeeded job → notifies coach via WebSocket`.

**2. Coach approves AI plan (web):**
`Livewire component → Action class → updates plan status → fires PlanApproved event → listener pushes notification to user's mobile (FCM) and refreshes their cache`.

**3. AI plan generation:**
Always async. Coach clicks "Generate" → Livewire fires job → worker calls LLM with structured prompt + client RAG → result written as draft `Plan` row → coach UI polls (or WS pushes) → coach edits & approves.

### Why This Architecture
- **Modular monolith over microservices**: at <100K users, microservices add 10× ops cost for 0× product value. Bounded contexts give us a clean upgrade path when one module truly needs to split (likely AI service first, around year 2).
- **Single Laravel codebase for API + Web**: eliminates a whole class of contract bugs and lets a small team ship 2× faster.
- **Flutter** for mobile because we need iOS + Android day one with one team.
- **MySQL single-DB tenancy** (see §5) over per-tenant DB: aggregations across tenants (admin dashboards, AI training data) are trivial; backups simpler; ops cheaper.

---

## 5. 🗄️ Database Design (High-Level)

### Multi-Tenancy Strategy
**Single shared MySQL, row-level tenant scoping via `tenant_id` on every tenant-owned table.**

Why this over schema-per-tenant or DB-per-tenant:
- Cross-tenant analytics + AI training without ETL.
- Migrations run once, not N times.
- Cheaper at small scale; can shard later by `tenant_id` when one DB becomes a bottleneck.
- Risk (cross-tenant data leak) mitigated with: global Eloquent scope `BelongsToTenant`, automated tests that assert isolation, and a CI check that flags any tenant-scoped query missing the scope.

### Two Logical Layers

**Central (platform-wide, no tenant_id):**
- `tenants` (the coach/gym account)
- `users` (global identity; user can belong to multiple tenants in different roles)
- `subscriptions`, `invoices`, `payment_methods`
- `exercises_global`, `foods_global` (curated platform library)
- `plan_templates_global` (platform-published)
- `audit_log`

**Tenant-scoped (every row carries `tenant_id`):**
- `clients` (a user as a client of this tenant)
- `coaches` (a user as a coach of this tenant)
- `plans`, `plan_workouts`, `plan_meals`
- `workouts_logged`, `sets_logged`, `meals_logged`, `weigh_ins`, `measurements`, `progress_photos`
- `messages`, `chat_threads`
- `exercises_custom`, `foods_custom` (tenant overrides/additions)
- `notifications`, `tasks`

### Main Entities & Relationships

```
Tenant ─┬─< Coach >── User
        │
        ├─< Client >── User
        │     │
        │     ├─< Plan >─┬─< PlanWorkout >──< PlanExercise >── Exercise
        │     │          └─< PlanMeal >─────< PlanMealItem >── Food
        │     │
        │     ├─< WorkoutLogged >── SetLogged
        │     ├─< MealLogged >── MealItemLogged
        │     ├─< WeighIn >
        │     ├─< Measurement >
        │     └─< ProgressPhoto >
        │
        ├─< ChatThread >─< Message >
        └─< Subscription >── PricingPlan
```

### Key Design Notes
- **`User` is global**, decoupled from tenant. A user can be (a) a client of Coach A and Coach B simultaneously, (b) a coach in their own tenant + a client in another. Tenant membership = role join table.
- **Soft deletes everywhere** for user-facing data; hard delete on GDPR request via dedicated job.
- **Append-only log tables** for `sets_logged`, `meals_logged` — never updated, only inserted. Simplifies AI training pipelines and audit.
- **`Plan` is versioned** — every replan creates a new `Plan` row with `parent_plan_id`, never mutates the old one. Coaches and users can see history.
- **Search**: MySQL full-text on small lookups (foods/exercises). Move to Meilisearch in V2 when food DB > 50K rows.

---

## 6. 🔗 API Design Strategy

### Structure
- **REST + JSON**, namespaced by version: `/api/v1/...`
- Resource-oriented URLs, plural nouns: `/api/v1/clients/{id}/plans`
- Verbs only for actions that aren't CRUD: `/api/v1/plans/{id}/approve`, `/api/v1/plans/{id}/regenerate`
- **Cursor pagination** (not offset) for any list that grows: logged sets, meals, messages.
- WebSockets (Laravel Reverb) for: chat messages, coach notifications, plan-ready events.
- **No GraphQL** — adds complexity, mobile clients are well-served by REST + tailored endpoints.

### Versioning
- URL versioning (`/v1`, `/v2`). Mobile app pins a major version; backend supports the last 2 majors for ~12 months.
- Breaking changes get a new version. Additive changes go in the current version.

### Auth Flow

| Client | Method | Notes |
|---|---|---|
| Mobile | Sanctum bearer tokens via email/password or OTP (SMS) | Refresh on 401, biometric unlock locally |
| Coach Web | Session cookies + CSRF | Standard Laravel auth |
| Admin | Session + 2FA mandatory | TOTP via Laravel Fortify |
| Webhooks (Stripe, Twilio) | HMAC signature verification | |
| Public API (V3) | OAuth2 client_credentials + per-key rate limit | |

Tenant context is resolved per request from: subdomain (web) or token claim (mobile). Middleware sets `app('tenant')` and Eloquent global scopes auto-filter.

### Naming Conventions
- snake_case in JSON request/response (matches DB).
- Endpoints: lowercase plural nouns. `GET /api/v1/clients`, `POST /api/v1/clients/{id}/measurements`.
- Booleans prefixed `is_`, `has_`. Timestamps suffixed `_at`. IDs always `*_id`.
- Enums returned as strings, never integers (`"status": "active"` not `"status": 1`).

### Error Handling
Single envelope. Always.

```json
{
  "error": {
    "code": "PLAN_NOT_FOUND",
    "message": "Plan does not exist or is not accessible.",
    "details": { "plan_id": "..." },
    "trace_id": "abc-123"
  }
}
```

- HTTP status codes used semantically (400 validation, 401 auth, 403 authz, 404, 409 conflict, 422 domain error, 429 rate-limit, 5xx server).
- `code` is a stable string enum — clients switch on it, not on `message`.
- `trace_id` correlates to Sentry + Laravel logs for support.
- Validation errors: 422 with `details: { field_name: ["msg"] }`.
- All errors logged with tenant_id, user_id, trace_id.

### Cross-cutting
- Idempotency keys on POSTs that create money/plans (`Idempotency-Key` header).
- Rate limits: 60 req/min default, 10 req/min on AI generation endpoints, 5 req/min on auth.
- ETag + `If-None-Match` on heavy GETs (plan, today's dashboard).

---

## 7. 🧠 AI System Design

### Three-Stage Maturity
**Stage 1 (MVP) — Rules + LLM templates.** Stage 2 (V2) — Rules + LLM with retrieval. Stage 3 (V3) — Custom models trained on platform data.

### Data the AI Uses

**Static (intake):** goal, age, weight, height, body fat est., training experience, equipment available, injuries, allergies, dietary preferences (halal, vegan…), schedule, target date.

**Dynamic (logged):** workouts completed, sets/reps/weight, RPE, meals logged, macros hit vs. target, weigh-ins, measurements, sleep (wearable), step count, mood/energy check-ins, adherence rate, message sentiment.

**Contextual:** coach's preferred templates, time of year (Ramadan), local culture/food availability.

### What It Generates
1. **Initial workout plan**: 4-week mesocycle, periodized.
2. **Initial meal plan**: 7-day rotation hitting macro targets, respecting prefs.
3. **Weekly adaptations**: deload week, increase load, swap exercises if injury reported, adjust calories if weight stalled.
4. **Coach copilot suggestions**: "Client X has missed 3 leg days. Suggest replacing with 2 shorter sessions?"
5. **At-risk alerts** (V3): predicted churn flag for coach.

### Architecture: Rule-Based + LLM Hybrid

The MVP **is not "ChatGPT generates a plan"**. That's unreliable, slow, and expensive. Instead:

```
Intake → Rule Engine (deterministic) ──► Structured plan skeleton
                                                 │
                                                 ▼
                                         LLM Templater
                                  (fills exercise variants,
                                   meal swaps, recipes, copy)
                                                 │
                                                 ▼
                                       Validator (rules)
                                 (rejects unsafe volume,
                                  allergies, kcal bounds)
                                                 │
                                                 ▼
                                     Draft Plan (DB)
                                                 │
                                                 ▼
                                       Coach reviews & approves
```

**Rule engine** computes:
- TDEE, macro split (rules: protein g/kg by goal, fats min 0.8 g/kg, carbs fill remainder)
- Training volume (sets/muscle/week by experience)
- Split (3/4/5/6-day) by schedule
- Progressive overload schedule

**LLM** (Claude or GPT-4-class, called from queue jobs) handles only the unstructured parts:
- Pick concrete exercises from a constrained list matching equipment + experience
- Generate meal recipes hitting macro buckets
- Write motivational/instructional copy
- Personalize tone for tenant brand

Why this hybrid: LLMs are creative but unreliable. Rules guarantee safety (no 8000-kcal plans, no 30-set leg days, no peanuts when allergic). LLMs guarantee variety and personality.

### Adaptation Loop
Cron job, weekly per active client:
1. Compute adherence (workouts done %, calorie variance %).
2. Compute outcome (weight delta vs. target slope).
3. Decision tree:
   - Adherence < 60% → simplify plan, fewer sessions.
   - Adherence > 85% + weight stalled → adjust calories ±10%.
   - 4 weeks completed → deload + new mesocycle.
4. Generate next plan version, notify coach to approve (or auto-approve based on tenant settings).

### Evolution Over Time
- **Months 0–6**: 100% rule-based + LLM. Log every generation + outcome.
- **Months 6–12**: A/B test prompts; introduce embeddings-based food/exercise matching.
- **Year 2**: Train recommender on logged data — "users like you who succeeded did X." Replace exercise/meal selection with learned model. Keep rules as safety guardrails.
- **Year 2+**: Vision models for food photos and form check.

### Cost & Performance
- LLM calls always async via queue, never block HTTP.
- Cache plan-generation prompts (intake hash → cached output for 24h) to dedupe accidental regenerations.
- Per-tenant LLM spend cap (admin-configurable) with alerting.
- Target: < $0.40 LLM cost per plan generation, < $2 per active user/month.

---

## 8. 💳 SaaS & Monetization Plan

### Three Revenue Streams

**1. B2C — End-user subscriptions** (no coach, AI-only experience)

| Tier | Price (USD/mo) | Includes |
|---|---|---|
| Free | $0 | Log meals/workouts, library access, 1 AI plan/month, 7-day history |
| Pro | $9.99 | Unlimited AI plans, adaptive replanning, full history, wearable sync, photo macro estimator (V3) |
| Pro Annual | $59 (50% off) | Everything in Pro |

**2. B2B — Coach SaaS subscriptions**

| Tier | Price | Includes |
|---|---|---|
| Coach Starter | $29/mo | Up to 10 clients, branded subdomain, plan builder, AI generator |
| Coach Pro | $79/mo | Up to 40 clients, templates, group programs, custom branding, analytics |
| Coach Studio | $199/mo | Up to 150 clients, multi-coach, white-label domain, priority support |
| Enterprise (Gym) | Custom | Unlimited, SSO, custom integrations, dedicated CSM |

**3. B2B2C — Per-active-client revenue share (V2)**
When a coach uses our platform to bill their own clients (Stripe Connect), we take **10%** + standard Stripe fees. Aligns incentives — we make more when coaches succeed.

### Feature Gating Strategy
- Free tier exists primarily as a lead magnet, not a permanent home. Heavy gating on AI replanning, history, advanced analytics.
- All AI generation calls are gated by tier limits (e.g. Free = 1/month, Pro = unlimited).
- Coach tiers gate by client count, not feature — coaches need every feature on day one to evaluate. We grow with them.
- Hard gate (UI shows upgrade modal) vs. soft gate (works but shows "powered by Fetness — upgrade to remove"). Hard for client count limits, soft for branding.

### Billing Flow
- Stripe is the single source of truth.
- Laravel Cashier for sub management.
- Webhook-driven — Stripe webhook updates `subscriptions.status` → middleware enforces access.
- Dunning: 3 retries (day 1, 3, 7), then read-only mode for 14 days, then suspended.
- Stripe Connect for coach payouts (Standard accounts — coaches own their Stripe relationship, lower compliance burden on us).

### Trial Strategy
- **Coach**: 14-day free trial, no credit card required. Card required to invite > 5 clients (qualifies serious leads).
- **End user (B2C)**: 7-day Pro trial, card required (industry standard converts 3–5×).
- **Coach-invited end user**: their coach's subscription covers them — no individual paywall, but coach must be on a paid plan.

### Pricing Psychology Notes
- Coach pricing anchors high ($199 Studio). Most coaches land on $79 — that's the target ARPU.
- Annual discount at 50% (not 20%) because LTV impact of annual is huge — users who pay annual churn 4× less.
- No per-seat for end users on coach plans — keeps coach acquisition friction low.

---

## 9. 🎨 UX/UI Strategy

### Design Principles
1. **One screen, one decision.** The user opens the app to know "what do I do right now." Don't bury it.
2. **Logging in < 10 seconds.** Every friction-second loses logs, and logs are the product. Defaults from yesterday, swipe-to-confirm, voice option.
3. **Show progress aggressively.** Every screen reinforces "you are improving." Streaks, deltas, before/after.
4. **Coach trust > AI flash.** Plans are presented as "your plan" — not "AI-generated." Coach approval is visible.
5. **Calm, not gamified.** Avoid Duolingo-tier dopamine spam. Target audience is adults paying real money for results.

### Mobile-First Approach
- Mobile (Flutter) is the canonical UX. Web (TALL) is for coaches/admins, designed desktop-first.
- Bottom-tab nav (5 tabs): Today / Workouts / Nutrition / Progress / More.
- Designed for one-handed use: primary CTAs in lower 60% of screen.
- Dark mode + light mode from day one.

### Key Screens (Mobile)

| Screen | Purpose | Critical UX |
|---|---|---|
| Today | Today's workout, meals, water, weight prompt | Must load < 1s; works offline |
| Workout Player | Active workout: exercise, sets logger, rest timer, swap | Big tap targets, screen-on lock, voice control optional |
| Meal Log | Search/scan/photo to add meals | Recent + favorites pinned; barcode opens camera in 1 tap |
| Progress | Weight chart, photo timeline, PR history | Weekly comparison view |
| Coach Chat | Conversation with coach | Voice notes, attachments, read receipts |
| Plan View | Read-only plan structure | Visual week calendar |

### Key Screens (Coach Web)

| Screen | Purpose | Critical UX |
|---|---|---|
| Dashboard | Active clients, at-risk, today's tasks | Information-dense, sortable, keyboard-shortcuts |
| Client Profile | Tabs: Overview / Plan / Logs / Chat / Notes | Everything 1 click from list |
| Plan Builder | Drag-drop weekly calendar | Inline edits, copy-paste days, "AI suggest" button |
| Roster | Filterable client list | Bulk actions for messaging |

### UX Goals (Measurable)
- App cold-start < 1.5s on mid-tier Android.
- "Log a set" flow ≤ 3 taps from home screen.
- "Generate plan" coach action: < 30s end-to-end.
- Coach onboarding to first client invited: < 10 minutes.

### Design System
- Tailwind on web — shared design tokens (colors, spacing, typography) generated as JSON, consumed by Flutter via codegen.
- Component library: ~40 components (Button, Card, FormField, Chart, Calendar…).
- Per-tenant theming: 2 colors + logo + font from a curated set. No fully open CSS — protects brand consistency.

---

## 10. ⚙️ Development Plan

### Team Structure (lean, ~7 people)
- **1 Tech Lead / Backend Architect** — owns Laravel, multi-tenancy, AI orchestration
- **2 Backend Engineers** — Laravel + Livewire
- **2 Mobile Engineers** — Flutter
- **1 Product Designer** — design system, mobile + web
- **1 Product Manager** (often the founder at this stage)
- (Add later: 1 ML engineer in Month 8, 1 DevOps in Month 6)

### Build Order (Months 0–4 MVP)

**Month 0 — Foundations (2–3 weeks)**
1. Repo setup (Laravel + Flutter monorepos), CI/CD, staging env
2. Auth (Sanctum, Fortify) + multi-tenant scaffolding + middleware
3. Design system v0 + Figma library
4. Stripe integration scaffold (no live billing yet)

**Month 1 — Core Domain Models**
5. User, Tenant, Client, Coach entities + relationships
6. Exercise + Food libraries (seed data import)
7. Plan, PlanWorkout, PlanMeal models + APIs
8. Admin panel (Filament) basic CRUD on the above

**Month 2 — Coach Web MVP**
9. Coach dashboard + roster
10. Client profile (tabs)
11. Plan builder (drag-drop, manual)
12. Messaging (1:1 chat with WebSocket via Reverb)

**Month 3 — Mobile MVP**
13. Onboarding wizard
14. Today dashboard + workout player + set logger
15. Meal log (search-based; barcode v2)
16. Progress + weigh-in
17. Mobile chat with coach
18. Push notifications (FCM)

**Month 4 — AI + Billing + Polish**
19. AI plan generator (rule engine + LLM templater)
20. Coach reviews/edits AI plan flow
21. Stripe billing live (coach subs)
22. Email/SMS notifications
23. Bug bash, performance pass, App Store + Play Store submission
24. Closed beta launch (50 coaches)

### Cadence & Process
- 2-week sprints, demo every Friday.
- Trunk-based development on main branch with feature flags for unfinished work.
- Required: PR review by 1 engineer + automated tests (Pest for Laravel, Flutter test). No direct push to main.
- Daily 15-min standup, weekly retro.

### Estimated Timeline
- **Month 4**: MVP closed beta (50 coaches, 500 users)
- **Month 6**: Public launch, V2 features start
- **Month 9**: V2 complete, scale to 500 coaches / 15K users
- **Month 12**: Series A milestone (~$80K MRR)
- **Month 18**: V3 AI features rolled out, market expansion

---

## 11. 🚀 Deployment & Scaling Plan

### Infrastructure (MVP → ~50K users)
- **Hosting**: AWS (eu-west-1 primary) — single region MVP, multi-region V2 if user base demands.
- **Compute**: 2× ECS Fargate services (web + workers) behind ALB. Auto-scale on CPU + queue depth.
- **DB**: RDS MySQL 8 (db.r6g.large to start) — primary + 1 read replica.
- **Cache/Queue**: ElastiCache Redis (cache.r6g.large) — separate clusters for cache vs. queue if traffic warrants.
- **Storage**: S3 (private bucket, signed URLs) + CloudFront CDN for media + static assets.
- **WebSocket**: Laravel Reverb on a dedicated Fargate task; sticky sessions via ALB.
- **Mobile distribution**: App Store + Google Play; CodePush-style OTA via Shorebird (Flutter) for non-native fixes.
- **Observability**: Sentry (errors), Mixpanel (product analytics), CloudWatch (infra), Pingdom (uptime).
- **Secrets**: AWS Secrets Manager. No secrets in env files.

### CI/CD
- GitHub Actions: lint → test → build → deploy.
- Staging auto-deploys on merge to `main`. Production deploys are manual one-click from staging.
- DB migrations run via job in deploy pipeline; backwards-compatible migrations enforced (no destructive changes in same release as code that needs the new schema — expand-then-contract pattern).

### Scaling Strategy by Stage

| Stage | Users | Strategy |
|---|---|---|
| MVP | < 5K | Single region, single primary DB, vertical scale |
| Growth | 5K–50K | Read replicas, queue separation, CDN for all static, Redis-backed cache layer |
| Scale | 50K–500K | DB read replicas per workload, search moves to Meilisearch, dedicated AI service, queue priorities |
| Mature | 500K+ | Shard DB by tenant_id, regional replicas, edge caching, separate microservices for AI, plan, messaging |

### Performance Considerations
- **N+1 hunt**: Laravel Telescope in staging + `Model::preventLazyLoading()` enabled in non-prod.
- **DB hot paths**: Today dashboard, workout player. Aggressive Redis caching with event-driven invalidation.
- **AI calls**: always queued, never inline. Per-tenant cost cap.
- **Mobile**: payload compression (gzip + protobuf for high-volume endpoints in V2), aggressive client-side cache, optimistic UI for logging.
- **Cold-start budget**: API p95 < 250ms, mobile cold-start < 1.5s, plan generation < 20s end-to-end.

### Backup & DR
- RDS automated daily snapshots, 30-day retention.
- Point-in-time recovery enabled.
- S3 versioning + cross-region replication.
- Quarterly DR drill: spin up a clone, validate recovery time < 4h.

---

## 12. ⚠️ Risks & Challenges

### Technical Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Cross-tenant data leak | Medium | Catastrophic (trust + legal) | Global Eloquent scope, automated tenant-isolation tests, per-PR check that flags unscoped queries, regular pentests |
| LLM unreliability (bad plans) | High | High (user safety) | Rule-based validator on every AI output; never let LLM directly persist a plan; unsafe-output detection (kcal bounds, volume bounds, allergen check) |
| LLM cost runaway | Medium | High (margin) | Per-tenant cost caps with alerts, prompt caching, async-only, fallback to smaller models on free tier |
| Scaling bottleneck on single DB | Medium (year 2) | High | Built tenant_id-based sharding path from day one; read replicas earlier; observability on slow queries |
| Mobile offline-sync conflicts | High | Medium | Append-only log model (no conflicts on logs); last-write-wins with server timestamps for editable entities; explicit conflict UI for plan acceptance |
| Wearable API breakage (Apple/Google) | Medium | Medium | Treat wearables as enhancement, not core; degrade gracefully; abstract behind adapter |

### Product Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Users find generic AI plans untrustworthy | High | Catastrophic | The whole product is built around coach-in-the-loop. Pure-AI tier is the upsell path, not the default |
| Coaches don't switch from WhatsApp | Medium | Catastrophic | Build the coach side as the headliner — plan templates + AI copilot saves them ≥10 hrs/week. Direct sales to first 50 coaches, not self-serve |
| Logging friction kills retention | High | High | Every release measures "time to log a set" as a KPI. UX team owns this metric |
| Liability — bad plan harms a user | Low | Catastrophic | Hard rules in validator (allergens, max volume), prominent disclaimers, coach-approval for medical conditions, professional indemnity insurance |
| GDPR / health data compliance | Medium | High | Treat as health data from day one — encryption at rest, audit logs, data export + delete flows, DPA with all subprocessors |
| Cultural mismatch (food DB) | High | Medium | Seed regional food DBs (MENA + EU first), allow coaches/users to add custom foods, prioritize Arabic + French i18n early |

### Business Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| MyFitnessPal / Trainerize copies coach AI feature | Medium | High | Speed of iteration + tenant white-label is the moat; data accumulates; coaches who white-label rarely switch |
| Coach churn after trial | High | High | Onboarding success team for first 100 coaches; in-app health checks; coach-success tier ($79+) gets dedicated CSM |
| App Store rejection (medical claims) | Medium | High | Avoid medical/clinical language; clear "fitness, not medical" disclaimers; legal review before launch copy ships |
| AI provider lock-in / outage | Medium | Medium | Provider abstraction layer; support 2 providers (Claude primary, GPT fallback); prompt portability |

---

## ✅ Final Notes for the Team

**Three things that matter most for execution:**
1. **Coach side is the wedge.** It's tempting to over-invest in B2C AI features early. Don't. Coaches bring the first thousand paying users with us doing zero performance marketing.
2. **Logging UX is the product.** If users don't log, the AI is useless and they churn. Every sprint should measure log-completion rates.
3. **Multi-tenancy can't be retrofitted.** Get the tenant scoping right in the first 2 weeks. Every shortcut here costs 10× later.

**What to NOT build in MVP** (frequent traps): community feed, gamified streaks, advanced analytics, public API, integrations with anything except Stripe and FCM. They're seductive and wasteful pre-PMF.

This plan is execution-ready. Hand it to the team and start Week 1.
