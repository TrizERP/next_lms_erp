> **Correction, added during Gate 3 (2026-10-14):** this audit under-counted K-12's existing decision
> infrastructure. A full `App\Domain\AI` namespace exists with a complete Signal → Evidence → Case →
> Recommendation → Explanation pipeline (`SignalStore`, `EvidenceStore`, `CaseBuilder`,
> `RecommendationDrafter`, `ExplanationBuilder`, `DecisionGate`, `GovernanceValidator`), already used by
> real detectors (e.g. `App\Domain\K12\AcademicRisk\AssessmentDeclineDetector`) and by other recent
> feature work (exam evaluation, leave/attendance/payroll, per git history). This is **consequential,
> human-approval-gated** machinery (a draft recommendation requires a named teacher's approval in
> `ai_decisions` before anything executes — see `CreateAcademicInterventionAction`'s own docblock) and is
> a different governance class from routine, real-time content suggestions. It was correctly **not**
> reused for the Gate 3 pilot below; `AiAuditLogger` (lightweight event capture, no approval semantics)
> was the right-weight fit instead. See `K12_IMPLEMENTATION.md` for the full reasoning.
>
> **Second correction, added during Gate 5 (2026-10-14):** §3.2 undersold G2G's decision-trace capture.
> hp_erp has its **own** `App\Domain\AI\Support\AiAuditLogger` class (structurally parallel to K-12's —
> same namespace shape, same `record()` signature — but a separate implementation), used by
> `RecommendationController::decide()` to audit every approve/reject/defer with `from`/`to` status,
> confidence, and evidence references. This is in addition to, not instead of, `g2g_event`/
> `g2g_audit_log`/`EventRecorder` (§3.3) — G2G has two parallel event mechanisms, one general-purpose
> (`g2g_event`) and one AI-recommendation-specific (`ai_audit_logs`, mirroring K-12's table name).
> Separately: `routes/ai.php`'s middleware stack (`AiAuth`→`AiRateLimit`→`AiContextHydrator`) is
> confirmed to apply to literally every route in that file, including `/ai/recommendations/*`, not just
> "AI routes" generically as originally phrased. See `G2G_IMPLEMENTATION.md` for the full reasoning,
> including why the analogous "wire event capture into the explain endpoint" move from the K-12 pilot
> was deliberately **not** repeated here — this codebase's own `EventCatalogue` explicitly dropped an
> equivalent event type (`competency.gap_detected`) with the documented reason "gaps are derived, not a
> state change," and recording `SkillMatchingController::explainMatch()` the way K-12's suggested-content
> was recorded would have reintroduced exactly that rejected pattern.
>
> **Third correction, added during Gate 6 (2026-10-14):** §4.7's "not confirmed wired" flag on
> `ExplainButton`/`CitationPanel` is now confirmed concretely: neither is imported anywhere in
> `hp-enterprise-brain/src`, and the gap is broader than those two components — four separate,
> fully-implemented, routed, permission-gated backend narrative endpoints
> (`SignalController::explainReasoning()`, `CapabilityController::graphNarrative()`,
> `RiskController::graphNarrative()`, `EntityIntelligenceController::graphNarrative()`,
> `AiWorkspaceController::graphNarrative()`) have zero frontend callers, confirmed by searching for
> their exact route segments rather than assuming from imports. Only `PersonProfileController::narrative()`
> and the executive-summary narrative are actually wired. §4 also never mentioned
> `PersonProfileController`'s Phase 7.4/7.5 cross-product identity feature (K-12/G2G/EB person-profile
> unification via a human-reviewed `cross_product_identity_links` table, currently zero confirmed links)
> — directly relevant to rule #13/Gate 7, found already compliant, recorded in `EB_IMPLEMENTATION.md`.
>
> This correction also surfaced a live defect: `ai_audit_logs` on the actual remote dev database
> (`vivek_erp@202.47.117.220`) was missing `duration_ms`/`knowledge_graph_used` — columns a migration
> already in this repo (`2026_09_29_100600_add_duration_and_knowledge_graph_to_ai_audit_logs.php`) was
> meant to add, but which had never been run against this database. Every existing caller of
> `AiAuditLogger::record()` across the whole AI domain had been silently falling back to a log line
> instead of a table row as a result — not a hypothetical risk, a confirmed, reproduced, live one. Fixed
> by running that single, narrow, additive, already-reviewed migration (user-approved) on 2026-10-14; see
> `K12_IMPLEMENTATION.md` for the before/after evidence. §5's "Event Capture: Implemented and verified"
> row for K-12 should be read as now-true-as-of-this-fix, not true-at-original-audit-time.

# Phase 0 — Context Graph Audit (Gate 1)

**Status:** Audit only. No code, schema, config, or migration was changed to produce this document.
**Scope:** All six repositories named in the Context Graph master prompt.
**Depth:** Prioritized (user-approved) — deep, cited evidence on anything load-bearing for context
assembly, decisions, graph/AI integration, and tenant isolation; a lighter pass (counts and
representative examples, not full enumeration) on generic CRUD routes/controllers/migrations.
**Location:** This document lives only in `next_lms_erp` (user-approved single canonical location) and
references the other five repositories by absolute path.

## 1. Scope and method

| # | Repo | Path | Branch | Working tree |
|---|---|---|---|---|
| 1 | K-12 frontend | `C:\lms_k12` | `sonika_frontend` | 1 uncommitted file (`app/course-master/[courseId]/chapters/ContentCard.tsx`) — left untouched |
| 2 | K-12 backend | `C:\xampp\htdocs\next_lms_erp` | `sonika_palv` | clean |
| 3 | G2G frontend | `C:\g2gv0` | `sonika_g2g` | clean |
| 4 | G2G backend | `C:\xampp\htdocs\hp_erp` | `sonika_hp` | clean |
| 5 | Enterprise Brain backend | `C:\hpbrain_backend` | `sonika_eb` | clean |
| 6 | Enterprise Brain frontend | `C:\hp-enterprise-brain` | `sonika_eb_frontend` | clean |

No `docs/context-graph/` existed anywhere before this audit — confirmed by glob before starting.

Evidence sources: K-12 backend was read directly (file reads, grep, glob) by the auditing session
itself. The other five repositories were surveyed by three parallel read-only Explore agents, one per
product pairing, each instructed to cite a real file path and a concrete piece of evidence (a quoted
snippet, a counted occurrence, a schema column) for every claim, and to state explicitly when something
was searched for and not found rather than silently omit it. Everywhere this document says a file
exists, it was opened — by the auditor or the delegated agent — not inferred from its name. Items this
pass did **not** verify are listed in §6.

This audit also found and read six pre-existing documents already in this repo's `docs/` tree that bear
directly on the Context Graph question — `neo4j-live-sync.md`, `neo4j-migration-status.md`, and others —
and treats them as authoritative current state where they are more current than this session's own
earlier working assumptions (see the correction in §2.1).

## 2. Product A — K-12 LMS + PAL (`lms_k12` + `next_lms_erp`)

### 2.1 Graph integration and sync — **the most mature of the three products**

Neo4j is live and not a toy integration. The sync path, per `docs/neo4j-live-sync.md` (already in this
repo, dated through 2026-10-10) is:

```
any writer → MariaDB → [DB trigger] → sync_log / neo4j_sync_queue → neo4j:drain → Neo4j
```

- **Why a DB trigger, not a controller call**: an earlier design called `GraphSync` from two API
  controllers. A 2026-08-21 incident found `sync_log` fully healthy (9,257 rows, zero pending/failed)
  while a real student (`tblstudent#281472`) was absent from the graph — 46 of the last 50 students
  created had never gone through one of the ~15 code paths that write a student. The fix was to move
  capture to a database trigger (`database/migrations/2026_08_21_100100_create_neo4j_sync_triggers.php`),
  which cannot be bypassed by a new, unenumerated writer.
- Pieces: `ProjectionRegistry.php` (table→projection, label→table), `*GraphProjection.php` (re-reads
  MariaDB, decides graph shape), `GraphOutbox.php` (writes node/relationship rows), `GraphDrain.php`
  (delivers to Neo4j), `GraphSchema.php` (label/key/relationship-type whitelist), `ReconcileCommand.php`
  (`neo4j:reconcile`, drift audit/repair across all 27 K12 relationship types).
- **Correction to this session's own earlier assumption**: prior memory on file characterized this
  pipeline as having "a history of dying silently" with no cron running `schedule:run`. `neo4j-live-sync.md`
  shows this was **fixed as of 2026-10-10** — a Windows Task Scheduler entry
  (`LaravelScheduler_next_lms_erp`) now runs `schedule:run` every minute under an S4U principal (so it
  runs non-interactively, not popping a console window and not silently failing when no one is logged
  in), confirmable via `Get-ScheduledTaskInfo -TaskName LaravelScheduler_next_lms_erp`. `neo4j:drain`
  runs every minute, `neo4j:reconcile --fix` nightly, both registered in `app/Console/Kernel.php`. This
  audit updates status to **Implemented and verified** on the local dev host; whether the same
  Task Scheduler registration exists on the deployed `dev.triz.co.in` host specifically is **not**
  verified by this pass (see §6) — a separate, still-live memory note says that host has historically
  run code without this sync layer deployed.
- There are **two independent graph pipelines** in this codebase with different key conventions and
  verification gates that must not be conflated: the `uid` migration pipeline (`neo4j:export/load/verify`,
  gated by `neo4j:verify`, currently at 881,734 nodes / 2,912,957 rels per `neo4j-migration-status.md`)
  and the live K12 sync above (legacy-keyed, intentionally fans `HAS_RESULT` across every enrolment).
  Running `neo4j:verify` against the live K12 graph produces 193,233 G1 and 110,966 G4 "failures" that
  are not real defects — the two pipelines encode opposite data models on purpose.

### 2.2 Context assembly — already exists, under a different name

- `App\Domain\AI\Workspace\AiContextService` (`app/Domain/AI/Workspace/AiContextService.php`) resolves
  "the user is on this route" into a usable AI context: normalizes the route, matches it to a
  data-driven `ai_modules` table row, resolves any named entity through `EntityResolver` (tenant-scoped
  — "a route naming a record the user cannot see resolves to no entity at all, and the workspace falls
  back to module-level suggestions rather than leaking that the record exists"), and returns an
  `AiContext` value object (module, entity, route params, selected records, page data, capabilities).
  This is functionally the master prompt's "Context Assembly Service."
- `App\Services\Eso\AiTutorContextService` (`app/Services/Eso/AiTutorContextService.php`) assembles the
  grounding+governance context for the AI tutor on one learner/one concept: authored learning content
  as the only permitted explanation source, the misconceptions ESO has actually flagged for that learner
  (not the whole library), and a governance decision (Socratic vs. direct) resolved server-side as data
  rather than left to a system prompt the model could be talked out of. Docblock states: "every field is
  derived from existing ESO state. Nothing new is recorded."
- Frontend: `contexts/PageAiContext.tsx` (`useRegisterPageAiContext`/`usePageAiContext`) is a real
  page-level context-assembly layer — pages register `pageTitle/filters/records/metrics/facets`, capped
  by a `LIMITS` constant, flattened into `page_data` sent to the AI backend. This is the closest existing
  frontend analog to a Context Graph payload and should be extended, not duplicated.

### 2.3 Decision trace and event capture — already exists, close to the prompt's own spec

- `App\Domain\AI\Decisions\DecisionGate` + `ai_decisions` table: durable approve/reject/defer record for
  AI recommendations, separate from the short-lived confirmation token (`McpConfirmationService`) used
  to transport the approval — the docblock is explicit that the token "expires in ten minutes and is
  consumed on use" and cannot answer "who approved this, and when" months later, which is why this table
  exists. `historyFor()` returns full decision history scoped by `sub_institute_id`.
- `eso_decision_log` (`App\Models\Eso\DecisionLog`): append-only audit of every D1–D5 adaptive-learning
  decision (`state_snapshot`, `rule_fired`, `action`, `llm_instruction`), write-once via
  `EsoPolicyService::log()`.
- `App\Domain\AI\Support\AiAuditLogger` + `ai_audit_logs`: general event capture whose event-type
  constants already match the master prompt's own illustrative examples almost verbatim —
  `SIGNAL_DETECTED`, `EVIDENCE_COLLECTED`, `CASE_OPENED`, `CASE_CLOSED`, `RECOMMENDATION_DRAFTED`,
  `DECISION_RECORDED`, `WORKFLOW_TRANSITION`, `GOVERNANCE_REJECTED`. Writes never throw (an audit
  failure must not roll back the thing being audited) and fall back to the application log on failure.
- Frontend: `lib/event-bus/{types,client,sources}.ts` is a documented **read-only** adapter over
  `sync_log`, `ai_audit_logs`, `workflow_runs` and send-logs — "no mock, no fallback," single
  `liveAdapter`. A `pal_telemetry_events` table is referenced in comments/audit docs but not yet wired
  into any frontend fetch found — **exists but not wired into runtime**.

### 2.4 Cross-product integration — a real, already-diagnosed gap

`App\Services\Integrations\G2GCompetencyGraphClient` (`app/Services/Integrations/G2GCompetencyGraphClient.php`)
is a genuine, working HTTP client aimed at hp_erp's (G2G's) competency/KASBA/Framework graph API — the
first real network call from this codebase to that one. It is currently **non-functional by design,
not by accident**: the class's own docblock records that `next_lms_erp`'s `.env` has no G2G credential,
that hp_erp's `personal_access_tokens` table has no token meant for cross-product use (every row is
either a browser session or a short-lived human dev-verification token), that reusing one of those
would be "borrowing a stranger's identity," and that a prior attempt to provision a new token was
correctly refused by the session's own secret-store permission classifier. `G2G_API_BASE_URL` /
`G2G_API_TOKEN` are read from config and the class returns an explicit `unavailable`/`auth_not_configured`
shape when either is blank, rather than silently succeeding with no data. An unauthenticated GET against
a live local hp_erp instance was confirmed to return a real `401 {"status":0,"message":"Token not
provided"}` — the endpoint and guard are real, only the credential is missing.

This is exactly the cross-product boundary the master prompt's rule #13 and Gate 7 care about. **This
audit does not attempt to resolve it** — minting a cross-product service credential is a decision for
whoever administers hp_erp, not something a Context Graph audit should do to itself.

### 2.5 Auth/tenant scoping

`sub_institute_id` is enforced per-request; `app/Http/Middleware/` has 13 files including
`RequireApiJwt`, `PalApiAuth`, `LmsApiAuth`, `McpAuth`, `RequirePermission`, `checkPermission` — multiple
narrow guards rather than one universal tenant-scope middleware (consistent with this repo's
long-documented pattern of per-module auth, not a single global scope class).

### 2.6 Jobs/schedulers/tests

Scheduler is registered and now confirmed firing (§2.1). No CI config found in either `lms_k12` or
`next_lms_erp` (no `.github/`, no other CI config file). `lms_k12` runs tests via Node's built-in test
runner (`node --import tsx --test`) over `lib/**/*.test.ts`, no Jest/Playwright/Cypress/Vitest found.

## 3. Product B — G2G (`g2gv0` + `hp_erp`)

### 3.1 Graph integration and sync — live, scheduled, documented vocabulary

- `app/Services/Neo4jService.php` opens a bolt driver (`laudis/neo4j-php-client`) from
  `NEO4J_URI`/`NEO4J_USERNAME`/`NEO4J_PASSWORD` (plus a second `NEO4J_LMS_*` set on a different port —
  not investigated further in this pass).
- `app/Services/Events/Neo4jProjector.php` MERGEs ~20 event types into labels (`Organization`,
  `Department`, `JobRole`, `Competency`, `KasbaItem`, `Person`, `Framework`, `ProficiencyLevel`,
  `Course`, `JobRoleTask`, `TalentPool`, `Goal`, `Training`) and relationships (`REQUIRES_COMPETENCY`,
  `HAS_KASBA_ITEM`, `REPORTS_TO`, `HAS_COMPETENCY`, `RATED`, `REQUIRES_SKILL`, `HAS_PROFICIENCY_LEVEL`,
  `ASSIGNED_LEARNING`, `HOLDS_JOBROLE`, `DEVELOPS`, `EXERCISES_COMPETENCY`, `TARGETS`, `HAS_GOAL`,
  `MEMBER_OF`, `WORKS_IN`, `ATTENDED`, `DEVELOPED`), declared in `app/Services/Graph/GraphVocabulary.php`.
  Driven by `php artisan events:project` (`app/Console/Commands/ProjectEvents.php`), scheduled
  `->everyFiveMinutes()` in `routes/console.php`.
- The projector's own docblock notes an older KASBA ontology
  (`REQUIRES_SKILL/KNOWLEDGE/ABILITY/BEHAVIOUR/ATTITUDE`) already exists in the live graph with "no
  reproducible writer anywhere in this codebase" — i.e., part of the live graph was populated by
  something not in this repo's history. Flagged, not resolved.
- `app/Services/Competency/GraphRagRetriever.php` is confirmed **pure Cypher, no LLM call** — graph
  traversal for competency-gap narratives with zero generation risk in that class specifically.

### 3.2 AI provider / RAG

`config/ai.php` (Gemini/OpenRouter/DeepSeek with failover) and `app/Domain/AI/Support/AiModelClient.php`
is documented as "the one place this application calls a model." `app/Domain/AI/Configuration/*`
resolves per-module credentials. `routes/ai.php` stacks `AiAuth → AiRateLimit → AiContextHydrator` —
i.e., G2G already has its own context-hydration middleware on the AI route group, a second real analog
to a Context Assembly Service, separate from K-12's `AiContextService`.
`app/Http/Controllers/AI/RecommendationController.php` only reads/approves existing
`hpbrain_recommendations` rows via `RecommendationRepository` — it does not itself generate
recommendations.

### 3.3 Decision trace and event capture — a clean, already-correct implementation

- `g2g_event` (migration `2026_08_10_140000_create_g2g_event_store.php`): append-only event store —
  `type, sub_institute_id, entity_type, entity_id, actor_id, acting_for_id, payload(json),
  metadata(json), correlation_id, causation_id, idempotency_key, occurred_at, recorded_at`. This is a
  textbook implementation of the master prompt's "Event Capture" spec (idempotency key, correlation/
  causation IDs, tenant-scoped, attributable, timestamped).
- `g2g_audit_log` (migration `2026_08_10_150000_...`): derived projection, `event_id` unique, same
  tenant/entity/actor columns plus `detail(json)`.
- Sole writer: `app/Services/Events/EventRecorder.php`.
- **Real gap found**: `RecommendationRepository::explain()` walks
  recommendation → reasoning step → evidence across `hpbrain_recommendations` and
  `hpbrain_recommendation_evidence`, but **no `CREATE TABLE` migration for either table exists anywhere
  in `hp_erp`'s `database/migrations/`**. The repository defensively guards every access with
  `Schema::hasTable()`. These tables are externally provisioned — status: **requires approval /
  undocumented dependency**, not something to silently build a migration for without finding out where
  they actually come from.

### 3.4 Auth/tenant scoping

Dual-mode auth: `app/Http/Middleware/authMiddleware.php` accepts either a Laravel session
(`session('user_id')`) or a Sanctum personal-access token. `RequireApiToken.php` guards individual
routes, not a blanket API middleware group — its own docblock records "a sweep... found 25 [routes]
that guarded nothing." No Eloquent global tenant-scope class exists; `sub_institute_id`/org scope is
resolved manually per request via `ResolvesApiIdentity` (general) or `AiScopeResolver` (AI routes,
attached as an `ai_scope` request attribute). **Risk to flag**: the absence of one universal enforcement
point means a shared Context Assembly contract must not assume tenant scoping is uniformly applied
across every route the way K-12's middleware stack does.

### 3.5 Jobs/schedulers/tests

`app/Console/Kernel.php::schedule()` is documented in its own code as "DELIBERATELY EMPTY — NEVER
CALLED" under Laravel 12 — a pattern this audit also found independently resolved in K-12 (§2.1). Real
scheduling lives in `routes/console.php` (334 lines). Whether an actual OS-level `schedule:run` cron is
provisioned on `hp_erp`'s production/dev host is **not verified** by this pass (see §6) — this is the
same class of risk K-12 had until 2026-10-10, and it should be checked the same way before relying on
any G2G-side scheduled sync for context assembly. hp_erp has a real PHPUnit suite (41 files, `phpunit.xml`,
cache shows tests have run); g2gv0 has 17 `*.test.ts` files via `node --test`. **No CI config in either
repo.**

## 4. Product C — Enterprise Brain (`hpbrain_backend` + `hp-enterprise-brain`)

### 4.1 Neo4j — definitively dormant, not absent, and correctly so

This was the one open question the master prompt specifically flagged for verification rather than
assumption. Finding: `laudis/neo4j-php-client ^3.4` is a real composer dependency with vendor code
installed, and `app/Domain/Graph/Neo4jGraphAdapter.php` is a **complete, working** `GraphQueryPort`
implementation issuing real Cypher — not a stub. It is registered in
`app/Providers/IntelligenceServiceProvider.php`, which binds `GraphQueryPort` via
`match(config('graph.driver', 'mysql'))`: `'neo4j' => Neo4jGraphAdapter::class`, `default =>
GraphProjection::class`. `config/graph.php` reads `GRAPH_DRIVER` (defaults `mysql`) and `NEO4J_URI/
USERNAME/PASSWORD`; a grep of `.env`/`.env.example` found **zero** `NEO4J_*` entries. Code comments cite
"ADR-008 (Defer Neo4j)" explicitly, and a mocked-client-only unit test exists
(`tests/Unit/Domain/Graph/Neo4jGraphAdapterTest.php`).

**Conclusion, directly answering the master prompt's instruction**: Neo4j in EB is a dormant, selectable
adapter behind a driver switch, not a live dependency and not something anyone forgot to configure. The
master prompt's own rule against adding Neo4j without demonstrated need is already this product's
existing, documented decision (ADR-008). **This audit's recommendation is to leave it exactly as is** —
there is no gap here to close.

(Housekeeping note: a stray git worktree at `.kilo/worktrees/hyper-parsley`, detached HEAD, contains
near-duplicate Graph files. It is not part of `sonika_eb` branch history and was not touched or counted
in any finding above.)

### 4.2 MySQL graph projection — mature, with a documented vocabulary layer

Not `graph_nodes`/`graph_edges` tables — a runtime projection over ordinary relational tables with FKs
(`hpbrain_signals`, `hpbrain_evidence`, `hpbrain_cases`, `hpbrain_hypotheses`, `hpbrain_recommendations`,
`hpbrain_decisions`, `hpbrain_capabilities`, `hpbrain_capability_assignments`,
`hpbrain_reporting_structures`), assembled at read time by `app/Domain/Graph/GraphProjection.php`
(3,413 lines of `DB::table(...)` joins) into graph-shaped JSON via `app/Domain/Graph/GraphBuilder.php`.
`app/Domain/Graph/GraphVocabulary.php` is a static PHP map of allowed node labels and relationship
types, each entry documenting the exact column/service it derives from (e.g. `has_department` ←
`OrganizationStructureService`). Exposed via `GraphController` at
`graph/{tenantId}/overview|expand|node|summary|vocabulary|search|trail`.

### 4.3 AI provider / RAG — no new infrastructure, by design

`app/Domain/Ai/AiGateway.php`/`AiProvider.php` abstract multiple providers (Anthropic, DeepSeek, Gemini,
plus a `NullAiProvider`). RAG lives in `RetrievalService.php`, `RerankService.php`, `RagService.php`,
`GroundingService.php`, `KnowledgeIngestionService.php`/`Embeddings.php`. Vector storage, per migration
`2026_10_10_120000_add_embedding_to_knowledge_assets.php`, is explicitly "no new infrastructure (no
Pinecone/Weaviate/Qdrant/pgvector)" — a JSON column storing `[{index,hash,text,vector:float[]}]`, cosine
similarity computed in PHP. Consistent with the master prompt's own instruction not to introduce a
vector DB without demonstrated need — already this product's practice.

### 4.4 Decision trace and audit — the most complete schema match of the three products

`hpbrain_audit_logs` has `entity_type, entity_id, action, actor_id, changes(json)`. `hpbrain_decisions`
has `confidence DECIMAL(6,4)`, `explanation TEXT`, `trace(json)` — a genuine, direct schema match to the
master prompt's own decision-context field list (confidence, explanation, evidence trace), not just a
similarly-named table.

### 4.5 Auth/tenant scoping

`AuthenticateJwt.php` verifies an HS256 bearer JWT (custom `App\Support\Jwt`) and stashes
`tenantId`/`role`/`userId` as request attributes; `EnsureTenantScope.php` then rejects any route-param
tenant that doesn't match the token's tenant (`403 tenant_mismatch`), fully server-side with no
client-supplied override. This is the single cleanest, most centrally-enforced tenant boundary found
across all three products.

### 4.6 Jobs/schedulers/tests — the only product with confirmed-real CI

`routes/console.php` schedules real jobs with explicit cost/safety reasoning in comments:
`brain:process-events` (every minute), `brain:snapshot` (daily 02:00), `brain:detect` (hourly :10),
`brain:open-cases`/`brain:propose-hypotheses` (hourly :15/:20), `intelligence:warm`/`operations:warm`
(hourly :25/:40), all `withoutOverlapping()->runInBackground()`. Some commands
(`brain:reason-signals`, `brain:compute-eso-efficacy`) are deliberately **not** scheduled for
cost/destructive reasons, and a test (`tests/Feature/SchedulerDefinitionTest`) pins that. Whether actual
host cron/Task Scheduler invokes `schedule:run` in production is still not verified from the repo alone
(see §6), but this is the only product where the scheduler's own intended configuration is pinned by a
test. Backend: 133 PHPUnit files, CI at `.github/workflows/backend.yml` (SQLite in-memory, no secrets).
Frontend: 45 real Vitest files, CI at `.github/workflows/frontend.yml` (`tsc -b --noEmit` + `npm test`;
ESLint explicitly not a gate yet).

### 4.7 Frontend

`src/api/client.ts` is the single HTTP entry point (`VITE_API_URL` + `/api/v1`), 65 per-module API files.
`src/components/ai/ExplainButton.tsx` and `CitationPanel.tsx` exist as explanation/citation UI but are
thin presentational shells (logic injected via prop) — **not confirmed wired to a specific
recommendation screen** without further tracing; status: partially implemented.

## 5. Feature inventory

| Capability (master-prompt term) | K-12 | G2G | Enterprise Brain |
|---|---|---|---|
| Context Assembly Service | **Implemented and verified** — `AiContextService`, `AiTutorContextService` | **Partially implemented** — `AiContextHydrator`/`AiScopeResolver` on AI routes only | Not found as a named service; read-time `GraphBuilder`/`GraphProjection` play a similar role for graph context specifically — **partially implemented, narrower scope** |
| Decision Trace | **Implemented and verified** — `ai_decisions` + `eso_decision_log` | **Implemented and verified** — `g2g_event` + `g2g_audit_log` | **Implemented and verified** — `hpbrain_decisions` (confidence/explanation/trace columns), `hpbrain_audit_logs` |
| Event Capture | **Implemented and verified** — `ai_audit_logs` (`AiAuditLogger`) | **Implemented and verified** — `g2g_event` (`EventRecorder`) | **Implemented and verified** — `hpbrain_audit_logs`; signal/case pipeline (`brain:process-events` etc.) |
| Graph sync/projection (SQL→graph) | **Implemented and verified** (fixed 2026-10-10) — trigger → outbox → `GraphDrain`, with reconcile | **Implemented and verified** — `Neo4jProjector`, every 5 min | **Implemented and verified, by design, as MySQL-only** — Neo4j adapter exists but dormant (ADR-008); no gap |
| GraphRAG (graph-grounded retrieval, no generation) | Exists (`App\Services\PAL\Coherence\GraphRagRetriever` found by glob; not read in this pass) — **not independently verified this session** | **Implemented and verified** — `GraphRagRetriever`, confirmed pure Cypher | RAG exists (`RagService`, `GroundingService`) but graph-specific grounding not separately confirmed — **partially implemented** |
| AI provider abstraction | Exists (`AiServiceProvider`, multiple AI domain classes found by glob) — **not independently verified this session** | **Implemented and verified** — `AiModelClient`, `config/ai.php` | **Implemented and verified** — `AiGateway`/`AiProvider` with 4 providers incl. `NullAiProvider` |
| Explainable recommendation UI | **Implemented and verified** — `LifecycleTrace.tsx`, `ResolvedEvidence.tsx` | **Implemented and verified** — `skill-matching-explain.tsx` | **Partially implemented** — `ExplainButton`/`CitationPanel` exist but wiring to a live recommendation screen unconfirmed |
| Cross-product integration (K-12 ↔ G2G) | **Blocked** — `G2GCompetencyGraphClient` built, no service credential; requires hp_erp admin action | n/a (no reverse client found) | n/a |
| Tenant/org scope enforcement | Implemented, multiple narrow middleware (no single global scope class) | **Partially implemented** — resolved per-request, no global scope, 25 routes found historically unguarded | **Implemented and verified** — single centrally-enforced `EnsureTenantScope` middleware, the cleanest of the three |
| Scheduler actually running (not just registered) | **Implemented and verified on local dev host** (Task Scheduler + S4U, confirmed 2026-10-10); deployed-host status **unverified** | **Unverified** — scheduling code exists in `routes/console.php`; real cron/Task Scheduler provisioning not checked | **Unverified** — scheduler intent is test-pinned; real cron/Task Scheduler provisioning not checked |
| CI | **Missing** (both repos) | **Missing** (both repos) | **Implemented and verified** — real workflows in both repos |

## 6. Explicit unknowns — not verified by this pass

- Whether `dev.triz.co.in` (or any other deployed host beyond this local dev box) actually has the
  Task Scheduler / cron registration for `schedule:run` in K-12, G2G, or EB. Each product's live-sync
  and event pipelines depend on this; the master prompt's own Phase-8 reliability tests should check it
  directly rather than infer it from this document.
- Full enumeration of routes, controllers, and migrations was explicitly out of scope for this
  prioritized pass. Counts and representative files are cited above; nothing is claimed as the complete
  set.
- `App\Services\PAL\Coherence\GraphRagRetriever` and the AI-provider classes found by glob in K-12
  (`AiServiceProvider`, `PALServiceProvider`, etc.) were located but not opened and read in this pass —
  flagged "exists, not independently verified" rather than assumed working, per rule #8.
- EB's `ExplainButton`/`CitationPanel` wiring to an actual live recommendation screen was not traced end
  to end.
- Whether the second `NEO4J_LMS_*` connection in `hp_erp`'s `.env` (separate from the main `NEO4J_URI`)
  is a distinct live graph or a leftover — not investigated.
- G2G's `hpbrain_recommendations`/`hpbrain_recommendation_evidence` tables (§3.3) are read by
  `RecommendationRepository` but have no migration in `hp_erp` — where they are actually provisioned
  from was not traced further in this pass.

## 7. Proposed direction (non-binding — Gate 2 approval required before any of this is designed in detail)

The headline finding changes the shape of the whole initiative: **none of the three products needs a
new Context Assembly Service, Decision Trace, or Event Capture system built from scratch.** Each already
has at least a partial, independently-evolved implementation of every core Context Graph capability the
master prompt describes. The work that remains is narrower than "build a Context Graph layer" — it is:

1. **Normalize, don't rebuild.** Define one small, shared logical contract (what a "context object," a
   "decision trace," and an "event" look like across products) that each product's existing classes can
   be read through — `AiContextService`/`AiTutorContextService` (K-12), `AiContextHydrator`/
   `AiScopeResolver` (G2G), `GraphBuilder`/`GraphProjection` (EB) — without renaming or replacing any of
   them. This is almost entirely a documentation/interface exercise, not new persistence.
2. **Reuse each product's own decision-trace and event tables** (`ai_decisions`+`eso_decision_log`,
   `g2g_event`+`g2g_audit_log`, `hpbrain_decisions`+`hpbrain_audit_logs`) as the durable layer. No new
   event or decision schema is needed anywhere.
3. **Leave the one real cross-product wire (K-12→G2G) exactly where it is** — blocked on a human
   credential decision, not something a Context Graph initiative resolves as a side effect.
4. **Leave EB's Neo4j decision exactly where it is** (ADR-008, dormant adapter) — the master prompt's own
   caution against adding Neo4j without need is already satisfied.
5. **Verify production scheduler provisioning in all three products** (§6) before relying on any of
   their sync/event pipelines for context assembly — this is infrastructure, not design, and is cheap to
   check before Gate 3.

**Risks carried forward to Gate 2:**
- K-12's graph sync has a documented history of silent failure modes (controller-based capture missing
  writers; scheduler not running) that were fixed by specific, non-obvious changes (DB triggers; S4U
  principal). A shared Context Layer must not reintroduce either failure mode by routing around the
  trigger-based capture or the now-working scheduler.
- G2G's undocumented `hpbrain_recommendations`/`hpbrain_recommendation_evidence` tables are a real
  schema dependency with no migration history in that repo — must be resolved (even just "where do
  these come from") before anything is built on top of `RecommendationRepository`.
- G2G and EB both lack a single universal tenant-scope enforcement point (G2G) or have one but it's
  product-specific (EB's `EnsureTenantScope`) — a shared contract cannot assume uniform enforcement
  across products and must do its own tenant check at the context-assembly boundary regardless of what
  the underlying product does.
- No CI exists for K-12 or G2G (either repo, either side) — any Context Layer code added there has no
  automated regression safety net; EB is the only product where that currently exists.

**Suggested first pilot, pending Gate 2/3 approval**: K-12 Suggested Content / Recommendation Reason, as
the master prompt itself suggests — it is the product with the most complete existing context-assembly
and decision-trace machinery already in place (§2.2–2.3), meaning the pilot would mostly be wiring and
documentation rather than new mechanism.

---

*This document reflects the state of all six repositories as read during this audit session. It does
not reflect any change made as a result of it — none was made. The next step is Gate 2 (shared
conceptual Context Model) and requires separate user approval before any architectural or schema
proposal is finalized, per the master prompt's own gating.*
