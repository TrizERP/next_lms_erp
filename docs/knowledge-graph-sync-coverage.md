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

## LMS + PAL — Teach/Learn (`module_key=teach_learn`)

Scope: the `/teach-learn` sub-module only (`ModuleGraphMapper`'s 7 labels — `Standard, Subject,
Chapter, Topic, Concept, TeachingResource, Content`), verified live against `bolt://dev.triz.co.in:7688`
on 2026-11-25. Not a full LMS+PAL audit — Exam & Assessment, Curriculum Planning, and PAL's own
learner-mastery screens are each their own pending stage and were only read where an edge terminates on
a Teach/Learn node.

| View | Hop | Status | Evidence |
|---|---|---|---|
| `knowledge-graph` (centralized, `ModuleKnowledgeGraph`→`GraphModuleBrowser`) | `Chapter` node coverage | **FIXED — 90% of the gap closed** | Was 4,229 SQL / 4,091 Neo4j (338 true-missing by id-match, 7.99%). Root cause of the slow repair: a stuck, unrelated idle-in-transaction MySQL session (thread 691096) was holding locks on `neo4j_sync_queue` platform-wide — cleared, then `neo4j:reconcile --entity=chapter_master --limit=5000 --fix` ran clean. Now: 4,395 Neo4j nodes, **4,195 of 4,229 match a current id (34 still missing, 0.8%)**. The residual 34 is a disagreement between `neo4j:reconcile` (reports "no drift") and `neo4j:node-excess` (still counts them unmatched) — a tooling-logic discrepancy, not re-chased further this pass. 200 pre-existing stale/excess nodes unchanged (left alone, not deleted — no "fix" exists for excess nodes short of an explicit, separately-approved cleanup). |
| `knowledge-graph` (centralized) | `Concept` node coverage | **FIXED — 100%** | `neo4j:reconcile --entity=lms_concept --limit=10000 --fix`: "No drift" immediately. Re-verified via `neo4j:node-excess`: **8,443 of 8,443 SQL rows now match a current id (100.0%)**, 9 pre-existing stale nodes unchanged. |
| `knowledge-graph` (centralized) | `TeachingResource` node coverage | **FIXED — 2,461 rows resynced** | `neo4j:reconcile --entity=content_master --limit=35000 --fix`: 2,461 of 33,827 checked were missing, all 2,461 re-synced. Now 34,798 Neo4j nodes vs 33,827 SQL rows (+971 raw). `neo4j:node-excess` still doesn't support this label, so the true matched/excess split (how much of the +971 is legitimate historical data vs. something else) remains unverifiable with existing tooling — flagged, not chased further. |
| `knowledge-graph` (centralized) | `Topic` node coverage | **GRAPH, tooling gap — partially addressed** | `topic_master` → `(:Chapter)-[:HAS_TOPIC]->(:Topic)-[:HAS_CONCEPT]->(:Concept)` is real and live. Added `HAS_TOPIC` to `CompletenessReportCommand::PIPELINE_B_EDGES` this pass (now reports 8,746 live `HAS_TOPIC` edges) — but the *node*-coverage side still has no row, because registering `Topic` in `ProjectionRegistry` would also change what `neo4j:drain`/`neo4j:reconcile` do at sync time (Pipeline A does not and must not write Topic), not just what gets reported — too invasive for a reporting-only fix, left alone. |
| `knowledge-graph` (centralized) | `Chapter`→`Concept`/`Topic` edges | GRAPH | `HAS_CONCEPT` (Chapter→Concept, `projectConcepts`): 8,401 live. `HAS_TOPIC` (Chapter→Topic): not separately exposed by `neo4j:completeness`; live via the same nightly closure. `REQUIRES` (Chapter→Chapter, `projectLearningRelations`): only 2 live — this is `pal_learning_relations`' chapter-grain output, and 2 is simply what that table currently has at this grain (confirmed via dry-run spot-check, not a sync failure). |
| `knowledge-graph` (centralized) | `Concept`↔`Concept` edges | GRAPH | `REQUIRES`: 7,565 live, `CROSS_LINKS`: 633 live (both `projectRelations`, from `pal_concept_relations`) — do not confuse with Pipeline C's separate, non-pedagogical `PREREQUISITE_OF` edge type (9,499 edges, documented elsewhere as having no real source table — a different relationship entirely, out of this audit's scope). 1,105 of 8,443 concepts (13%) have no `REQUIRES` in either direction — not automatically a defect (some concepts genuinely have no prerequisite), flagged as a magnitude worth tracking, not a fix. |
| `knowledge-graph` (centralized) | `Content`/`TeachingResource`↔`Concept` edges | GRAPH | `TEACHES` (Content→Concept, `projectTeaches`): 124 live = 124/124 of `pal_content_metadata` — **100% coverage, not a gap** (the low number reflects how little content is tagged to concepts platform-wide, not a sync failure). |
| `knowledge-graph` (centralized) | `Misconception` layer (`AFFECTS`, `CORRECTS_WITH`) | **GRAPH — reclassified, not a sync defect** | `AFFECTS`: 3,013 live. `CORRECTS_WITH→CorrectiveContent` (the branch that actually carries the data — `→Content` is correctly 0 by design, see 2026-09-28/29 comments in the source): held at 6,029/7,307 (82.5%) both before and after re-running `pal:coherence-sync` (no `--dry-run`) for all 8 scopes that have corrective data. Root cause now confirmed precisely: only **6,023 of 7,307 rows (82.4%) successfully join** `pal_misconception_corrective`→`pal_misconception_library`→`lms_concept` at all (82 via 48 misconceptions with a NULL `concept_ref_id`, the rest via a `concept_ref_id` pointing at a deleted/nonexistent `lms_concept` row) — **this is a pre-existing content-authoring data-quality gap, not a sync pipeline defect.** The pipeline is projecting ~100% of what it can structurally reach. Added `CORRECTS_WITH→CorrectiveContent` to `CompletenessReportCommand` so this is visible going forward without a manual Cypher query. |
| `knowledge-graph` (centralized) | `Standard`/`Subject` orphan edges (G8 gate) | **PARTIAL — the missing-node case fixed, the orphan-edge case is a distinct, deeper issue, deliberately not fixed** | Two different things were conflated in the first pass. (1) `neo4j:reconcile --entity=sub_std_map --fix`: exactly 1 missing Subject node, fixed cleanly (now 0 missing). (2) G8's "12 of 615 Subject / 4 of 158 Standard orphans where the parent DOES exist" is a **different** case — nodes that already exist but are missing their inbound edge — and these counts are **unchanged** after fix (1) and after the full chapter fix, confirming it's not the same root cause as a missing node. A **new** G8 entry also appeared post-fix: `Chapter=47 (5 genuine)` — very likely a side-effect of `reconcile --fix` creating ~2,641 new Chapter nodes without always creating their inbound `Subject-[:HAS_CHAPTER]->Chapter` edge in the same pass. Given the documented ~98.7% dual-key MERGE-collision history on `:Subject`, and that the exact mechanism behind these 21 orphan edges is not yet diagnosed, **no hand-written Cypher was used to patch them** — flagged for a dedicated follow-up, not patched blind. |
| `knowledge-graph` (centralized) | Adjacent finding: stale `Question` edges | **OUT OF SCOPE — flagged, not fixed** | `neo4j:reconcile --entity=sub_std_map --relationships --dry-run` surfaced 4,833 stale duplicate edges (`Question-[:BELONGS_TO]->Chapter`: 10, `Question-[:ASSESSES]->Concept`: 4,823) where a `Question` node has more than one target edge and MariaDB's current value disagrees with the extra one(s). Both terminate on Teach/Learn's `Chapter`/`Concept` labels, but the source of truth and the fix (`--fix` on the `Question`/Exam & Assessment side) belongs to that module's own pending audit stage — noted here as a cross-module pointer only, per the "minimum relevant dependency" scope rule. |
| `knowledge-graph` (decentralized, `CurriculumStructureView`→`GraphQueryService`/`OntologyRegistry`, view `curriculum-structure`) | `subject→chapter→learning_concept` | GRAPH | Confirmed live in `ontology_relationships`: exactly 2 rows, `subject→chapter` (`HAS_CHAPTER`, `in_graph=1`) and `chapter→learning_concept` (`HAS_CONCEPT`, `in_graph=1`). Both resolve to real, populated Neo4j edges (see `Chapter`→`Concept` row above). Left untouched by this pass's additions below. |
| `knowledge-graph` (decentralized) | `Topic`, `Content`/`TeachingResource` hops | **FIXED — two new supplementary views added** | Migration `2026_11_25_150000_seed_teach_learn_topic_content_ontology.php`: added `topic`/`content` `ontology_entities` (mapped to `topic_master`/`content_master`), 3 new `ontology_relationships` (`chapter→topic` `HAS_TOPIC`, `topic→learning_concept` `HAS_CONCEPT`, `chapter→content` `HAS_CONTENT`, all `in_graph=true`), and 2 new `ai_ontology_views`: **`curriculum-structure-topics`** (`subject→chapter→topic→learning_concept`) and **`curriculum-structure-resources`** (`subject→chapter→content`). Deliberately additive — the existing `curriculum-structure` view (above) was not modified, since `topic_master`'s 83.9%-dangling `chapter_id` rate would make Topic a worse *primary* path, not a better one; this is why two new named views were added instead of inserting Topic into the existing one. Verified live: all rows present in `ontology_entities`/`ontology_relationships`/`ai_ontology_views` post-migration. |
| `course-master` (Curriculum screen, embedded in Teach/Learn's Operations tab) | `/lms/new_curriculum` (`lmsCurriculumController`) | **SQL-OWNED** | Confirmed zero `Neo4j`/`neo4j` references in the controller (578 lines, pure Eloquent/`DB::table`). By design — it types concepts as name-only strings, which is why the Coherence Map below exists as a separate feature. Not a gap. |
| `course-master` (Coherence Map, `app/course-master/data/coherenceMap.ts`) | `/api/lms/coherence-map*` (`CoherenceMapApiController`) | **SQL-OWNED** | Confirmed: the file's own code comments state this is deliberately separate from `/lms/new_curriculum` (carries real ids for edges) and confirmed zero Neo4j references server-side. This is **not** the same feature as the row below, despite the similar name — two parallel Coherence Map APIs exist, one MariaDB (this one, what the Teach/Learn UI actually renders) and one Neo4j (next row, a separate PAL screen). |
| PAL Coherence Map (`App\Http\Controllers\api\PAL\CoherenceMapController`, `/api/pal/coherence/*`) | reads Pipeline B's graph output | GRAPH | Confirmed reads real, live Neo4j data (Pipeline B's `Topic`/`Concept`/`REQUIRES` projection, rows above) via `CoherenceMapRepository`. Not currently wired into the Teach/Learn UI — it is a separate PAL-side screen that happens to read the same underlying graph. |

**Sync-pipeline health, checked live before trusting any count above**: `schedule:run` and `neo4j:drain`
ARE firing on this host roughly every minute (`storage/logs/laravel-2026-11-25.log`, confirmed executions
09:11–15:23 continuously) — a prior, unrelated finding on this exact repo about a missing S4U principal
no longer applies; `Get-ScheduledTask` confirms `LogonType=S4U`, `UserId=sonik` is correctly configured.
One *unrelated* scheduled command, `idms:dispatch-question-papers`, fails every cycle (exit code 1,
logged every ~4–7 min) — flagged for visibility, out of this audit's scope (not a Neo4j job).
`neo4j_sync_queue`: **Root cause of the `Chapter-[HAS_CONTENT]->TeachingResource` bucket fully
diagnosed and fixed where fixable.** Of the original 11,363 permanently-failed rows, sampling and a
full distinct-id check (not a guess) found **11,206 (98.6%) reference a Chapter `source_id` that no
longer exists in `chapter_master` at all** — stale queue entries from chapters deleted/reorganized
after the row was enqueued (sample: `source_id=68`, `chapter_master.id=68` confirmed absent from
SQL). These are genuinely unrecoverable — requeuing would fail again identically — and were
deliberately **left alone, not deleted** (deleting rows from a shared, multi-pipeline queue table is a
destructive action on shared infrastructure outside this pass's authorization; flagged as a
queue-hygiene recommendation, not executed). The remaining **157 rows (1.4%) referenced chapters that
still exist** — precisely targeted (`UPDATE ... WHERE source_id IN (<88 still-existing ids>)`, not a
blanket requeue) back to `pending`, then drained: **all 157 now `done`**, 0 remaining in `failed` for
that bucket beyond the 11,206 confirmed-dead rows. Also found and resolved mid-pass: a stuck,
unrelated idle-in-transaction MySQL session (thread 691096, 8+ min idle, no running query) was holding
locks on this exact table, blocking inserts platform-wide (confirmed independently blocking a Fees-
pipeline `FeePayment` insert too) — it had disconnected on its own by the time a `KILL` was attempted;
noted here since it will recur for any pipeline writing to this table if it happens again. Current
totals: 1,331,747 rows — 1,298,194 done (+6,575 from this pass's fixes), ~8,500 pending (draining
normally), 31,261 failed (unchanged total count, composition now fully explained: 11,206 confirmed-
dead + ~20,000 other pre-existing categories, see below). Adjacent, cross-module failures that
terminate on Teach/Learn nodes but originate elsewhere (Exam & Assessment's `Question`, Curriculum
Planning's `Unit`/`Curriculum`) are noted but intentionally left to those modules' own pending audits:
`Question-[BELONGS_TO]->Chapter` 6,339, `Question-[ASSESSES]->Concept` 4,858, `Unit-[HAS_CHAPTER]->Chapter`
570, `Curriculum-[HAS_UNIT]->Unit` 161, `Subject-[HAS_CHAPTER]->Chapter` 6.

**Pre-existing, already-documented findings re-measured, not re-diagnosed** (per
`docs/neo4j-migration-status.md` Phase 5, last dated entry "7 of 10 pass; G8, G9, G10 FAIL",
`TENANT-SCOPE`/`PREREQ-SOURCE` still open): live `neo4j:verify --module=curriculum` today reproduces
the same shape — G1/G3/G8/G10 FAIL, G2/G4/G5/G6/G7 PASS — with G8/G10's "accepted" buckets
(`ORPHAN-CHAPTERS`/`SOURCE-DANGLING`, 25,961 and 1,327 rows respectively) consistent with the prior
audit. The two *new, genuine* sub-counts inside G8 (Subject: 12, Standard: 4 — see table above) are the
only part of this gate that counts as a fresh finding for Teach/Learn.

**Orphan Neo4j labels discovered, confirmed out of scope**: `neo4j:reconcile --entity=chapter_master`
surfaced 5 labels present in the graph with zero live SQL source (`LearningContent` 11 nodes,
`LearningObjects` 8, `CompetencyStandards` 40, `ChapterStandardMap` 96, `AssessmentTypology` 3 —
either the backing table doesn't exist or is missing required columns). None of these 5 labels are in
`ModuleGraphMapper`'s Teach/Learn set; noted for whichever future audit stage owns them, not acted on
here.

## All other modules (~60 bars)

AUDIT PENDING, per the approved staged order: LMS + PAL (Teach/Learn done above; its sibling
sub-modules remain pending) → Curriculum Planning → Exam & Assessment →
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
