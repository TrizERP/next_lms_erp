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
- **K-12's AI decision/recommendation tables have no idempotency key**, unlike G2G's `g2g_event`
  (database-enforced unique constraint) and EB's `hpbrain_event_store` (transactional `AlreadyPublished`
  guard).
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
- **Whether other `Schema::hasTable()`-guarded write paths in `next_lms_erp` have the same kind of
  live-vs-migration drift this gate found and fixed for `ai_audit_logs` was not swept exhaustively** —
  only that one table was confirmed and fixed, because it was the one this pilot depended on. Worth a
  dedicated check before building further on any other `App\Domain\AI` table.
- **396 pending migrations** (pre-existing, per standing memory) — this initiative ran exactly one of
  them, user-approved, narrowly scoped to the defect it was blocking. The other 395 were not reviewed as
  part of this work.

## Explicitly out of scope, not revisited

- The leaked `.env.bak-before-ai-provider` credential file (pre-existing memory item) — not touched or
  re-examined; rotation was already deferred by prior user decision.
- EB's dormant Neo4j adapter (ADR-008) — confirmed still correctly dormant, no action taken or needed.
- The K-12→G2G cross-product credential gap (`G2GCompetencyGraphClient`) — confirmed still blocked by
  design; resolving it is a credential decision for hp_erp's administrator, not this initiative.
- EB's Person Profile cross-product identity links (currently zero confirmed) — reviewing/approving
  pending identity-link proposals is a human governance decision outside this initiative's scope.
