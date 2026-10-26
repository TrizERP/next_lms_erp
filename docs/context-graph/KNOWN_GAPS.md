# Context Graph — Known Gaps

Everything below was found during audit or implementation, deliberately not acted on, and should not be
lost. Grouped by whether it's a candidate for a future gate or an environmental/operational risk outside
this initiative's scope.

## Candidate future gates (each is additive, low-risk, same pattern as Gates 3/6)

- **K-12**: no frontend surfaces the new `ai_audit_logs` recommendation events back to a teacher or
  student (e.g., "why was this suggested" shown in the UI, reading the audit trail this gate created).
  Deferred — `K12_IMPLEMENTATION.md` "Outstanding."
- **Enterprise Brain**: three more graph-grounded narrative endpoints are built, routed, and
  permission-gated with zero frontend callers — `GraphExplanationController::explain()` (generic, needs
  a UI decision about which entity screens get it), `CapabilityController::graphNarrative()`,
  `RiskController::graphNarrative()`, `EntityIntelligenceController::graphNarrative()`,
  `AiWorkspaceController::graphNarrative()`. Same minimal wiring pattern as the Signal one already done.
  `EB_IMPLEMENTATION.md` "Outstanding."
- **G2G**: `explainMatch()` (graph-grounded competency explanation) and `hpbrain_recommendations`
  (the audited, approval-gated pipeline) are disconnected — an approved recommendation doesn't currently
  cite graph/KASBA evidence the way the standalone explain tool does. Bridging them is a bigger change
  (connecting two previously-independent systems) than any gate so far and needs its own scoping and
  approval. `G2G_IMPLEMENTATION.md` "One real, deliberately-deferred observation."
- **K-12**: `content_master` has no `bloom_level`/`difficulty_level`/`pedagogy_tag`/`content_type`/
  `presentation_type` columns live, despite five pending migrations meaning to add them (found during the
  `Schema::hasTable()` drift sweep below). `PedagogySuggestedContentService`'s content scoring reads these
  with safe fallbacks, so nothing throws, but the categorisation can never actually vary by difficulty or
  pedagogy tag on this path — a permanently-degraded signal, not an occasional one. Fixing it means either
  running the relevant pending migrations and backfilling real values (a content-authoring/data-pipeline
  decision, not a guard fix) or deciding the signal isn't worth capturing; either way it's a product
  decision, not something to invent unilaterally here.
- **K-12**: `PalMasteryTrendResolver` (Phase 10) is built and registered, but `AcademicRiskAgent`'s only
  `eso_binding` draft site still hardcodes `AcademicRiskMetrics::ASSESSMENT_AVERAGE`, even for its
  content-gap hypothesis branch. `EsoBindingRule` supports exactly one metric per recommendation, and
  PAL/ESO coverage is not universal (most chapters lack a full learner journey), so switching that live,
  already-tested branch to the new metric risks turning most real interventions' outcomes
  "inconclusive" instead of measured — a regression for a general-purpose flow. Deferred pending a
  PAL-specific recommendation path where PAL coverage is guaranteed by construction, or an explicit
  product decision to accept the coverage tradeoff. `MASTER_IMPLEMENTATION_TRACKER.md` Phase 10.

## Environmental / operational risks found during the Phase 7 security/governance pass

- **No time-based retention/purge policy for any product's AI decision/event/audit tables**
  (`ai_audit_logs`, `ai_decisions`, `ai_recommendations`, `ai_evidence`, `g2g_event`, `g2g_audit_log`).
  EB has a real tenant-offboarding purge (`TenantPurgeService`), but nothing addresses ordinary
  data-minimization over time for an active tenant, in any of the three products.
- **G2G's `GraphRagRetriever` tenant safety is caller-enforced, not structural** — the graph nodes it
  reads carry no `sub_institute_id` property, so every caller must re-check SQL ownership itself (5 of
  13 known callers verified correct). K-12's `CoherenceMapRepository` already fixed the identical risk
  class structurally; G2G's retriever would benefit from the same fix, but that's a code change for a
  future, separately-approved phase, not something to do unilaterally here.
- ~~**K-12's AI decision/recommendation tables have no idempotency key**~~ — **Resolved** (see
  `MASTER_IMPLEMENTATION_TRACKER.md` Phase 7). `DecisionGate::record()` now guards the race atomically,
  and a DB-enforced unique index on `ai_outcomes.recommendation_id` mirrors G2G's `g2g_event`/EB's
  `hpbrain_event_store` pattern. The fix was validated against a real defect, not a hypothetical one: live
  `vivek_erp` already had 6 duplicate-outcome pairs from this exact race, cleaned up (later row of each
  pair deleted, user-approved) before the migration ran.
- **Routine context-assembly reads are not logged as access events** in any product — only decisions,
  recommendations, and explanations are audited. Reasonable trade-off against log volume, but means
  "who viewed this sensitive record" isn't reconstructable from any audit trail today.
- **No dedicated sensitive-field filter** distinct from allow-list field selection, and no explicit
  "purpose" parameter on any context-assembly service — both satisfied in practice/by narrow scope, not
  by a named, auditable mechanism.

## Pre-existing test failures found during the Phase 8 regression sweep (not caused by this initiative)

- `tests/Feature/Eso/EsoKnowledgeMapTest.php::test_edges_to_a_concept_in_a_different_chapter_are_excluded`
  and 3 tests in `PilotMetricsServiceTest.php` (D5/retention arm-A/arm-B mastery computation) fail on
  this shared remote dev database. Confirmed unrelated: grepped the whole `tests/Feature/Eso` directory
  for any reference to `ai_audit_logs`/`PedagogySuggestedContentService`/this initiative's migration —
  zero matches. Likely the same class of issue as the standing memory note that this dev database
  "shifts under you" — fixture-dependent tests drifting against live, changing data. Not investigated
  further; out of scope for this initiative.
- `hp_erp`'s `OpportunityIngestionTest::test_each_supported_format_is_extracted_with_references_even_when_ai_is_unavailable`
  fails (expects 201, gets 422) in the GTM opportunity-ingestion domain — untouched by this initiative
  (zero G2G files changed). Not investigated further.

## Environmental / operational risks (not fixed by this initiative, pre-existing)

- **Production scheduler provisioning unverified beyond this local dev box.** K-12's live Neo4j sync now
  confirmed running on this host (Task Scheduler, S4U principal); whether the equivalent is provisioned
  on `dev.triz.co.in` or any other deployed host was not and cannot be checked from here. Same open
  question for G2G's `events:project` (every 5 min) and EB's `brain:*` jobs.
- **G2G's `hpbrain_recommendations`/`hpbrain_recommendation_evidence` tables have no migration anywhere
  in `hp_erp`'s history** — externally provisioned, undocumented. Flagged in Phase 0, not resolved.
- ~~**Whether other `Schema::hasTable()`-guarded write paths in `next_lms_erp` have the same kind of
  live-vs-migration drift this gate found and fixed for `ai_audit_logs` was not swept exhaustively**~~ —
  **Swept** (audit only, no code changes). Scope: every `Schema::hasTable()`/`Schema::hasColumn()` guard
  under `App\Domain\AI`, `App\Domain\K12`, `App\Domain\Governance`, `App\Services\Eso`, `App\Services\PAL`
  (≈70 distinct tables, ~150 call sites), cross-referenced against the then-414 pending migrations and the
  live `vivek_erp` schema via `information_schema.columns`.
  - **No new `ai_audit_logs`-class defect found** (an unguarded write silently failing and getting
    swallowed) within this scope. Every specific "missing column" case checked turned out to be either
    (a) already present live via a different, earlier-applied migration — the pending one is stale/
    redundant (e.g. `chapter_master.extraction_id`, `lms_question_master.concept_id`/`category`,
    `semantic_intelligence`'s PAL fields, `pal_telemetry_events.actor_id`/`context_id`) — or (b) genuinely
    missing but already defensively handled in code: `FeesKnowledgeBaseService` guards every
    `knowledge_base_detail.category`/`title`/`content`/`tags` read with `Schema::hasColumn()` and degrades
    to `[]`; `CurriculumGraphBuilder::chapters()` already discovered (comment dated 2026-09-16) that
    `chapter_master` has no `planned_periods` column — the real one is `no_of_periods` — and selects
    explicit columns so a drift like that "fails loudly... rather than silently returning nulls";
    `StudentGraphProjection` already documents that `tblstudent_enrollment` has no `preferred_pedagogy`/
    `engagement_score`/`learning_velocity` and deliberately never writes them from that projection.
  - **One real finding, different in kind from `ai_audit_logs`**: `content_master` has none of
    `bloom_level`, `difficulty_level`, `pedagogy_tag`, `content_type`, or `presentation_type` live, despite
    five pending migrations meaning to add them. `PedagogySuggestedContentService` (the Phase 3 pilot's own
    target service) reads `$content['difficulty_level']`/`$content['pedagogy_tag']` with `?? 0`/`?? ''`
    fallbacks when scoring/categorising suggested content — so it never throws, but the categorisation can
    never actually discriminate by difficulty or pedagogy tag on this path, because the signal it reads is
    permanently absent, not occasionally missing. Not fixed here (outside this gate's scope: wiring real
    `difficulty_level`/`pedagogy_tag` data into `content_master` is a content-authoring/data-pipeline
    decision, not a guard fix) — recorded as a candidate future gate above.
  - **Scope boundary**: the actual `suggested_content` INSERT call sites live in
    `app/Http/Controllers/lms/pal/palController.php`, outside the five directories this sweep covered (a
    different layer entirely). Not checked here; flagged rather than silently expanded into.
  - Full methodology: `php artisan migrate:status` for the pending set, a single ripgrep pass across
    `database/migrations` for `Schema::table('<any of the ~70 tables>'`, filtered to pending files, then
    `information_schema.columns` for live columns — not a line-by-line read of all ~150 call sites.
- **414 pending migrations** (grown from the standing memory figure of 396) — this initiative ran one of
  them during the idempotency-key gate (user-approved, see Phase 7), on top of the one run during Phase 3.
  The sweep above cross-referenced all 414 against this gate's 5-directory scope; it did not review every
  migration's intent individually, and tables outside that scope were not checked.

## Explicitly out of scope, not revisited

- The leaked `.env.bak-before-ai-provider` credential file (pre-existing memory item) — not touched or
  re-examined; rotation was already deferred by prior user decision.
- EB's dormant Neo4j adapter (ADR-008) — confirmed still correctly dormant, no action taken or needed.
- The K-12→G2G cross-product credential gap (`G2GCompetencyGraphClient`) — confirmed still blocked by
  design; resolving it is a credential decision for hp_erp's administrator, not this initiative.
- EB's Person Profile cross-product identity links (currently zero confirmed) — reviewing/approving
  pending identity-link proposals is a human governance decision outside this initiative's scope.
