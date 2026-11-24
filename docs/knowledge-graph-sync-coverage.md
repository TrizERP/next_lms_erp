# Decentralized Knowledge Graph — module coverage report

Tracks, module by module, which decentralized Knowledge Graph tab hops are genuinely
Neo4j-backed, which are intentionally SQL-only by architectural decision, and which
are a real, unfixed gap. A stage is recorded here only after live verification — a
migration or sync command exiting 0 is not evidence of coverage on its own.

Status legend: **GRAPH** (real `in_graph=true`, verified live) · **SQL-OWNED**
(correctly stays SQL by an existing architectural decision, not a gap) · **GAP**
(no real mapping exists yet, documented honestly) · **AUDIT PENDING** (not yet
reached in the staged plan).

## Student (`module_key=student`)

| View | Hop | Status | Evidence |
|---|---|---|---|
| `student-learning` | `enrolled_in_standard` (Student → Standard) | GRAPH | Live `ENROLLED_IN`, ~191k Student / ~186k edges. Required a `StuDetail`→`Student` bridge fix in `GraphQueryService::resolveGraphStartIds()` (2026-11-03) — a real person id resolves to zero results from the graph without it. |
| `student-learning` | `teaches` (Standard → Subject, UI label "Subjects") | GRAPH | Repointed from a `chapter_master` proxy join to the real, already-populated `(:Standard)-[:HAS_SUBJECT]->(:Subject)` edge (`sub_std_map`, 5,815 live edges). No new trigger/projection needed. |
| `student-assessments` | `attempts` (Student → Assessment) | GRAPH | `ATTEMPTED` was code-complete and trigger-wired (`ResultGraphProjection`) but never backfilled — live count was 330 of a possible ~295,669. Backfilled via the existing outbox (no new pipeline); live count now 294,849. The ~820-pair shortfall is a separate, pre-existing node-population gap (missing `Student`/`Assessment` nodes for a small number of historical rows), not an edge-sync defect. |
| `student-evidence` | `has_signal` → `creates` (case) → `has` (evidence) | **SQL-OWNED** | `ai_signals`/`ai_case_signals`/`ai_case_evidence` are the Context Graph half of this platform's own KG/CG split — confirmed in `docs/context-graph/KG_CG_USAGE_MAP.md` (lists `SignalStore` under CG/MySQL, not KG/Neo4j), Enterprise Brain's own ADR-008 (same Signal→Evidence→Case→Decision chain, "MySQL projection — not Neo4j, by deliberate standing decision"), and `GraphVocabulary.php`'s own docblock. **Decision: do not add a `Signal`/`Case`/`Evidence` Neo4j label.** Fixed a real latent bug instead — `has_signal`'s SQL join filtered only on `ai_signals.subject_id`, with no `subject_entity_key` check, risking a cross-subject-type id collision (`ai_signals` holds both `subject_entity_key='student'` and `='enquiry'` rows in the same id space). Fixed generically via a new `sql_filters` convention on the edge's `attributes` JSON. The view's `description` now explains the SQL-ownership decision in the UI rather than leaving it unexplained. |

## Fees (`module_key=fees`)

| View | Hop | Status | Evidence |
|---|---|---|---|
| `fees-recommendation` (Graph Reasoning / Cross-Module PAL pair) | `fee_schedule` (`Standard` → `FeeRecord`, edge `HAS_FEE_SCHEDULE`) | GRAPH | Live, Pipeline-A-synced from `fees_breackoff` (`config/neo4j.php:1497-1513`). Verified 2026-11-24 via `neo4j:reconcile --entity=fees_breackoff`: 224,135 SQL rows, 80,864 `:FeeRecord` nodes (36% coverage), 143,271 still missing — the drain is live (scheduled every minute) but has a long way to go, and was found starved for 22+ hours by `fees_collect`'s much larger backlog in the fair-share rotation (separate, unfixed finding — see the fees sync audit). 0 duplicate nodes; 416 orphan `FeeRecord` nodes (no inbound `HAS_FEE_SCHEDULE`); 20/20 property spot-check matched SQL exactly. |
| `fees-recommendation` (Graph Reasoning / Cross-Module PAL pair) | `fee_payment` (`StuDetail` → `FeePayment`, edge `MADE_PAYMENT`) | GRAPH | Live, Pipeline-A-synced from `fees_collect` (`config/neo4j.php:1523-1540`). Verified 2026-11-24: 376,547 SQL rows (366,443 currently valid, `is_deleted='N'`), ~95k `:FeePayment` nodes and climbing (drain actively running, ~500 rows/min), ~296k still missing. 0 duplicate nodes; 14,164 orphan `FeePayment` nodes, of which 13,019 are a pre-existing `StuDetail` population gap (a Student-module issue, out of this audit's scope) and 1,145 have a null `student_id`. **Fixed this pass**: cancelling a receipt (`is_deleted` 'N'→'Y') never fired the sync trigger, because `is_deleted` wasn't a projected/watched column — 780 cancelled payments were sitting in the graph looking like valid ones. Added `is_deleted` to the projection, regenerated the trigger (`2026_11_24_120000_resync_neo4j_trigger_fees_collect_is_deleted.php`), backfilled existing nodes, and added the filter to every `FeesGraphRecommendationRepository` query that reads `FeePayment`. |
| `fees-recommendation` (Cross-Module attendance pair) | n/a | **SQL-OWNED** | `FeesCrossModuleInsightRepository::feeAttendanceCorrelation()` uses a Neo4j ontology-configuration check only; the actual numbers come from `DB::table('attendance_student')` — by design, not a gap. |
| `fees-recommendation` (Cross-Module admissions pair, Predictive, Recommendation, Agentic capabilities) | n/a | **SQL-OWNED** | No `Neo4jService`/`GraphQueryService` dependency exists anywhere in these classes — confirmed by reading every constructor in the call chain (`docs/FEES_RECOMMENDATION_NEO4J_DATA_FLOW_VERIFICATION.md`). Architectural decision, not a gap. |
| `fees/intelligence` ("Enterprise Brain") | n/a | **SQL-OWNED** | 100% SQL (`ai_signals`/`ai_evidence`/demand-receipt aggregates) — do not confuse with the KG-backed `fees/recommendation` screen above. |

## All other modules (~60 bars)

AUDIT PENDING, per the approved staged order: LMS + PAL → Curriculum Planning → Exam & Assessment →
Organization Management → HR/People Management → Engagement → Reports → remaining modules. Each tab
already renders an honest "Knowledge Graph isn't set up for {module} yet" empty state — not a bug,
a true data-coverage gap pending its stage.

## Shared-infrastructure fixes made along the way

These affect every module above and below, not just Student:

- **`GraphQueryService::resolveGraphStartIds()`** (new) — the `student` ontology entity's SQL identity
  (`tblstudent`, person) and its Neo4j identity for outgoing edges (`:Student`, one node per *enrollment*,
  `tblstudent_enrollment`) are two different real nodes connected by `(:StuDetail)-[:HAS_STUDENT]->
  (:Student)`. Every graph-rooted edge from a real person id silently returned zero rows without this —
  indistinguishable from "not synced yet." Scoped to the one entity; every other entity is an unchanged
  passthrough (regression-tested against `fee_student`/`standard`/`subject`).
- **`OntologyRegistry::flushAll()`** (fixed) — used to enumerate which tenants' caches to clear from
  `ontology_entities.sub_institute_id`, missing any tenant with no override row (the common case). Any
  `in_graph` flip could silently take up to an hour (`CACHE_TTL`) to actually take effect for most
  tenants. Replaced with a cache-version bump — invalidates every previously-built key with no
  enumeration needed, verified the cache still works (not bypassed) afterward.
- **`labelFor()` display fallback** — added `display_name`/`displayLabel` to the candidate list so
  graph-sourced nodes (whose properties rarely match a SQL `labelColumn` literally) show a real name
  instead of `{Label} #?`.
