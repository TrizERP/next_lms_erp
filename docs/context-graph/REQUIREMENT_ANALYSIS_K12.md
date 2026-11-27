# Context Graph — Requirement Analysis (K-12)

## 0. Scope & purpose

**Scope:** K-12 / `next_lms_erp` + `lms_k12` only. G2G (`hp_erp`/`g2gv0`) and Enterprise Brain
(`hpbrain_backend`/`hp-enterprise-brain`) are covered by the existing per-product docs below but live in
separate repos not checked out here, and are out of scope for this analysis.

**What this document is:** a direct response to a 5-point requirement-analysis ticket, written against
the current (2026-11-23) state of the code. A prior initiative already built and verified most of what
the ticket asks for — see the "Verified"/"Deferred" rows in
[`MASTER_IMPLEMENTATION_TRACKER.md`](./MASTER_IMPLEMENTATION_TRACKER.md) — so most sections below cite
that work rather than re-deriving it. This document's own contribution is: (a) naming one Context Graph
implementation that exists in code but is not mentioned in any file in this folder
(`LearnerContextGraphService`, §1–§2), (b) reproducing the LMS-module-to-graph-entity mapping as a single
list, which exists nowhere else as one list (§3), (c) describing the four live Neo4j sync pipelines,
which [`DATA_FLOW.md`](./DATA_FLOW.md) does not cover — it documents only the Gate-3 pilot's narrow flow
(§4), (d) four fresh Neo4j data-correctness findings (§5), and (e) three corrections to stale claims in
other documents (§6).

For the conceptual model, see [`SHARED_CONTEXT_MODEL.md`](./SHARED_CONTEXT_MODEL.md). For what was
actually built and verified, see [`ARCHITECTURE.md`](./ARCHITECTURE.md) and
[`K12_IMPLEMENTATION.md`](./K12_IMPLEMENTATION.md). For the full standing gap list, see
[`KNOWN_GAPS.md`](./KNOWN_GAPS.md).

---

## 1. Review of existing Context Graph implementation & recommendation requirements

There are **two** independent things in this codebase that answer to the name "Context Graph," and they
were built separately, by different work, for different purposes. Neither is a new system — both are
either a thin addition to, or a packaging of, pre-existing services.

**(a) The Gate-3 pilot — recommendation audit trail.** Per `ARCHITECTURE.md`'s headline fact, *"There is
no new Context Graph system... The 'Context Graph' is a name for capabilities that already existed,
independently, in each product."* For K-12, the concrete chain (per `K12_IMPLEMENTATION.md`, status
**Verified**) is:

```
PedagogySuggestedContentService::getSuggestions()   [unchanged scoring/selection logic]
  ├─ CoherenceMapRepository::rootBlockers()/contentFor()   [Neo4j, evidence — unchanged]
  └─ PedagogySuggestedContentContextRecorder::record()     [the one new, additive class]
       └─ AiAuditLogger::record()  →  ai_audit_logs        [existing table/mechanism]
```

Everything except the recorder pre-existed. The "recommendation requirement" this satisfies is: a
suggestion must be recorded with its evidence references (concept/content **ids only**, never full
records) so "why was this suggested" is reconstructable later. The one still-open requirement, per
`KNOWN_GAPS.md`: **no UI yet reads that audit trail back** to a teacher or student.

**(b) `LearnerContextGraphService` — a second, independent implementation, not covered by any doc in
this folder.** `app/Services/PAL/ContextGraph/LearnerContextGraphService.php`, served via
`app/Http/Controllers/api/PAL/ContextGraphController.php` at `GET /api/pal/context-graph/{learnerId}`.
The controller's own docblock already calls it, verbatim: *"Serves the Unified 4-Layer Context Graph for
Learners. Combines User Profile, Learning Activities, Knowledge Graph Prerequisite Closures, and
Historical Learning Trajectory into a single graph payload."* This is a real, already-built answer to
most of this ticket's point 2 (§2 below) — it was simply never cross-referenced into `docs/context-graph/`,
which is why the prior initiative's audit didn't surface it. Recommendation/requirement implications: the
controller already enforces one access-control rule worth noting as a requirement precedent — a student
caller can only request their own `learnerId` (`403` otherwise); tenant scoping is resolved from the
auth context, not from client input.

---

## 2. User context, learning activities, relationships, historical information, data sources

`LearnerContextGraphService::build()` answers this ticket point almost directly — its own docblock
(lines 11–23) names exactly four layers, in this order:

| Layer | What it is | How it's resolved |
|---|---|---|
| **A — User Context** | Student identity, active enrollment, standard, division, institute | SQL (`resolveUserContext()`), tenant-scoped by `sub_institute_id` |
| **B — Learning Activities** | Diagnostic attempts, practice scores, quizzes, engagement telemetry | SQL (`resolveLearningActivities()`) |
| **C — Knowledge Graph Relationships** | Subject curriculum, concepts, prerequisite edges, readiness (mastered/ready/blocked), root prerequisite blockers | Neo4j, via `CoherenceMapRepository::map()`/`readiness()`/`rootBlockers()` |
| **D — Historical Trajectory** | BKT mastery states, spaced-repetition schedules, decision audit logs | SQL (`resolveHistoricalContext()`) |

**Relevant data sources**, concretely: MariaDB for layers A, B, D (enrollment/telemetry/mastery tables);
Neo4j for layer C, read exclusively through `CoherenceMapRepository` — the service never issues its own
Cypher. Layer C degrades gracefully (`try/catch`, non-blocking) if the graph connection fails, returning
`available: false` for that layer rather than failing the whole request.

This maps onto `SHARED_CONTEXT_MODEL.md`'s conceptual Context Object schema (identity/scope, temporal,
activity, knowledge, decision/provenance fields — see that document for the full field-by-field mapping
across all three products) — layers A/D correspond to its "identity and scope" + "temporal" dimensions,
B to its "activity context," and C to its "knowledge context." `LearnerContextGraphService` is a
concrete, K-12-specific realization of that shared shape, built independently of the Gate 1–2 proposal.

**Important, verified nuance for layer C**: `CoherenceMapRepository`'s actual Cypher
(`readEdges()`, line ~880) reads `(:Concept)-[:REQUIRES|:CROSS_LINKS]->(:Concept)` — **not**
`PREREQUISITE_OF`. This is deliberate and documented in the repository's own code (quoted in full in §5
below): `PREREQUISITE_OF` is excluded by design because it is a machine artifact, not real prerequisite
signal. Practical effect: layer C's "relationships" are structurally insulated from §5's
`PREREQUISITE_OF` data-quality finding — they were never exposed to it in the first place.

---

## 3. Relationship between LMS modules and Context Graph entities

Two **independently built, parallel** mechanisms connect LMS modules to graph entities — there is no
single mapping layer:

- **Centralized** — `GraphModuleBrowser` (`app/Domain/KnowledgeGraph/GraphModuleBrowser.php`) resolves
  ERP modules/submodules from `tblmenumaster` (tenant + rights-scoped via `MenuRightsResolver`) and maps
  each to Neo4j labels via `ModuleGraphMapper`, reading Neo4j directly through `GraphSchema`/
  `GraphVocabulary` whitelists. Powers the Modules→Submodules→live-data UI.
- **Decentralized** — `GraphQueryService` (`app/Domain/KnowledgeGraph/GraphQueryService.php`) backs the
  per-module ontology tabs. Its `traverse()`/`neighbours()`/`expandNode()` walk relationships declared in
  `OntologyRegistry` (`app/Domain/Ontology/OntologyRegistry.php`, a tenant-aware cached reader of SQL
  tables `ontology_entities`/`ontology_relationships`), preferring Neo4j (`expandViaGraph()`) only where a
  relationship is flagged `in_graph`, else falling back to SQL joins (`expandViaSql()`) — the caller
  never knows which store actually answered.

**The concrete entity graph** (node labels and relationship types), verified directly against
`app/Services/Graph/GraphSchema.php`'s whitelist and `config/neo4j.php`'s live-measured counts:

```
Standard -[HAS_SUBJECT]-> Subject -[HAS_CHAPTER]-> Chapter -[HAS_CONCEPT]-> Concept
                                              Chapter -[HAS_LESSON]-> Lesson
Curriculum -[INCLUDES]-> Subject
Question -[BELONGS_TO]-> Chapter
Question -[ASSESSES]-> Concept                 (hangs off lms_question_master.concept_id, not question_paper)
Chapter -[HAS_CONTENT]-> TeachingResource
StuDetail -[HAS_STUDENT]-> Student
Student -[ENROLLED_IN]-> Standard
Student -[ATTEMPTED]-> Assessment
StuDetail -[ENGAGED_WITH]-> Concept | TeachingResource   (target label picked per-row from activity_type)
StuDetail -[MADE_PAYMENT]-> FeePayment
Standard -[HAS_FEE_SCHEDULE]-> FeeRecord
```

This list does not appear as a single artifact anywhere else in `docs/context-graph/` or
`docs/KG_CG_BUSINESS_FUNCTIONALITY_ROADMAP.md`; both only reference individual edges in passing. It is
the direct, literal answer to this ticket point.

---

## 4. Required graph structure & context retrieval mechanism

**Graph structure** is the schema in §3 above, already built and populated — not a hypothetical design.
What populates it is **four separate, independently-scheduled pipelines**, none of which is documented
in `DATA_FLOW.md` (that file covers only the Gate-3 recording pilot's own narrow flow):

| Pipeline | Mechanism | Cadence | Scope |
|---|---|---|---|
| **A** | DB triggers write `sync_log` (nodes) / `neo4j_sync_queue` (relationships) → `php artisan neo4j:drain` (`app/Services/Graph/GraphDrain.php`) via `ProjectionRegistry`/`TableGraphProjection`, plus bespoke `StudentGraphProjection`, `ResultGraphProjection`, `StaffGraphProjection`, `EngagementGraphProjection` | Every minute (`neo4j:drain`); nightly `neo4j:reconcile --fix` | Core LMS tables: students, enrollment, curriculum, chapters, questions, exams, HR, fees, front-desk/admissions, PTM, etc. |
| **B** | `CoherenceGraphProjection.php` (PAL) — writes Bolt directly, **not** through the outbox | Nightly, per `(tenant, standard, subject)` scope; also runnable via `pal:coherence-sync` | Concepts, relations, teaches/assesses, learning relations, misconceptions, concept nodes, node mastery, learning outcomes, topics, chapter intelligence |
| **C** | `neo4j:export` / `neo4j:load` / `neo4j:verify` / `neo4j:reconcile` — a separate, CSV-driven, uid-keyed rebuild tool (`uid` = `Label:tenant:syear:id`) | On demand / batch | Migration and backfill, tracked in `docs/neo4j-migration-status.md` |
| **D** | 3 legacy direct-write routes (`POST /lms/pal`, `POST /assessment_question/store`, `POST /neo4j/assessment`) calling `neo4jCreateNode()`/`neo4jCreateRelationship()` in `app/Helpers/Helper.php` and raw Cypher in `addAssesmentController@store` | On request, but gated off | Disabled by default (`NEO4J_WRITES_ENABLED=false`) since they key nodes under the pre-uid convention |

**Dual-key handling**: `GraphSchema.php` is the single authoritative whitelist resolving the split
between legacy native keys (e.g. `Subject.subId`) and uid-keyed nodes (`uid_syear`/`uid_tenant`
fallback). `GraphDrain::nodeExistsEitherConvention()`/`repairEndpoints()` checks both conventions before
creating a node, specifically to prevent minting a duplicate twin under the wrong key — this is a
structural guard, not a convention people are trusted to remember.

**Retrieval mechanism**, by caller: `GraphQueryService::traverse()`/`neighbours()`/`expandNode()`
(decentralized ontology tabs), `GraphModuleBrowser` (centralized module browser), and
`CoherenceMapRepository::rootBlockers()`/`contentFor()`/`map()`/`readiness()` (the PAL recommendation
path — the one `LearnerContextGraphService` layer C and the Gate-3 pilot both depend on).

**The one genuine structure gap**: `CONTEXT_LAYER_DESIGN.md` §5 proposes a `ContextAssemblyContract`
interface (`assemble(scope, hints) -> ContextObject|Rejected`, `historyFor(decision_id)`) meant to unify
how each product assembles context. Per `ARCHITECTURE.md`: *"It was **not implemented as code** in any
repo... The contract remains a documentation-level mapping... rather than a runtime abstraction, consistent
with the master prompt's instruction not to force new infrastructure where in-process reuse suffices."*
If this ticket is asking whether a unifying retrieval contract is *required*, the prior work's explicit,
reasoned answer is: not yet, and not unless a concrete need for cross-product context assembly emerges.

---

## 5. Review of existing Neo4j data — missing relationships

**Already documented**: `KNOWN_GAPS.md` (line 66) notes `Question-[:ASSESSES]->Concept` "has no consumer
for coverage balancing today" — the edge exists and is correctly written, but nothing reads it for the
graph-balanced question-paper generation use case (roadmap item K6).

**Four fresh findings from this session's direct code verification**, not previously written down:

1. **`content_master.chapter_id` dangling** for ~26,000 pre-renumbering rows. A permanent historical
   artifact from an earlier chapter-id renumbering, not an ongoing sync defect — no pipeline fix closes
   this; it needs a one-time backfill decision if it matters for a specific use case.
2. **`tblstudent.admission_id` has no live sync trigger** — only a one-off historical backfill exists.
   Any student admitted after that backfill has no live-synced `admission_id` relationship in the graph
   going forward, unless Pipeline A is extended to cover it.
3. **`(:MappingType)-[:SCOPED_TO]` is implemented in Pipeline C only, not the live sync.** Per
   `docs/neo4j-migration-status.md`, this relationship was decided and coded in `LoadCommand::loadScoped()`
   — the periodic CSV migration tool — not in the continuously-running Pipeline A/B sync. State precisely:
   this is "built in the batch tool, not yet extended to live sync," not "forgotten."
4. **`PREREQUISITE_OF` is the single largest relationship type in the entire graph, and is not
   pedagogy.** This is the most significant finding in this section, and it required reconciling two
   documents that disagree on scale:
   - `docs/neo4j-migration-status.md` (lines 264–274, dated to an August 2026 migration-status snapshot)
     describes 28 live edges confined to one chapter (`chapter_id` 8560, 8 concepts), calling the set
     "the complete transitive closure of a linear ordering by concept id... machine-generated, not
     pedagogy."
   - The current, authoritative statement is in live production code —
     `app/Services/PAL/Coherence/CoherenceMapRepository.php` (lines 715–719), quoted exactly: *"`PREREQUISITE_OF`
     is deliberately absent from the edge pattern. It is the largest edge set in the graph (9,499) and it
     is a machine artefact — every pair of concepts inside a chapter, ordered by id, which is the
     transitive closure of a sort and not pedagogy. Including it turns every chapter into a solid blob."*
     This is corroborated independently by `app/Console/Commands/Neo4j/ReconcileCommand.php` (line 95),
     which lists it as a known non-outbox relationship: *"derived by pairing concepts in one chapter; not
     a MariaDB relationship."*
   - **Read these together, not as a contradiction**: the August snapshot measured one chapter at one
     point in time; the current code comment states the live, system-wide total. The pattern (pairwise,
     id-ordered, per-chapter) is identical in both — what changed is scale, consistent with this pairing
     having since been generated across every chapter with concepts, not just the one sampled in August.
   - **Practical consequence, already handled correctly**: because `CoherenceMapRepository` — the sole
     reader behind both `LearnerContextGraphService` layer C and the Gate-3 pilot's `rootBlockers()` —
     deliberately excludes `PREREQUISITE_OF` from its traversal (reading `REQUIRES|CROSS_LINKS` instead,
     per §2 above), **no currently-live Context Graph consumer is exposed to this bad data.** The "missing
     relationship" here is not a sync failure to fix; it is a standing data-quality fact about one
     relationship type that every real consumer has already learned to avoid. Anyone adding a *new*
     consumer that walks `PREREQUISITE_OF` directly (rather than through `CoherenceMapRepository`) would
     reintroduce exactly the "solid blob" problem this comment warns about.

---

## 6. Corrections to existing documentation

> **Correction 1 — the roadmap's ship-status column is stale.**
> `docs/KG_CG_BUSINESS_FUNCTIONALITY_ROADMAP.md` (lines 129–134) marks K4 ("Teacher-facing intervention
> signal") and K5 ("Expose coherence-map health fields") as `🟡 → implementing`, and K7 ("Recall-queue
> reprioritization"), K8 ("Misconception cross-concept linkage"), K9 ("Decentralized KG rollout") as `🆕`
> (not started). Git history shows otherwise: commit `24a77b0c3` ("use KG in recommendation and
> intervention," Wed Nov 18 2026 17:30:11 +0530) shipped K4/K5 together; commit `205f4c906` ("use
> knowledge graph in LMS+PAL," Thu Nov 19 2026 17:38:56 +0530) shipped a teacher-scoping fix
> (`TeacherClassScopeService`, `GraphBrowserTeacherScopingTest`) alongside K7 (`MasteryOverviewRecallQueueTest`),
> K8 (a misconception/weak-prerequisite test), and a K9 curriculum-ontology seed migration (seed only —
> K9's full rollout still depends on K6, which remains unshipped). The status column was simply never
> updated after these commits landed; it should not be trusted for current ship status without
> cross-checking git log.

> **Correction 2 — K6's cited blocker is resolved, but K6 is still blocked for a different reason.**
> The same roadmap doc (line 131) cites, as K6's blocker: *"`ASSESSES` has a known double-writer bug —
> reconcile first."* `config/neo4j.php` (lines ~141–166) records this was re-measured on 2026-10-10,
> after the drain backlog cleared: *"the split is now 16,135 edges with no `quality_status` (this
> pipeline) vs 20,259 tagged (CoherenceGraphProjection's nightly pass)... The two pipelines still can't
> clobber each other: this spec sets no properties on ASSESSES at all... so it can only ever MERGE the
> bare edge, never touch `quality_status`."* The double-writer risk is resolved. **This does not mean K6
> is unblocked** — `KNOWN_GAPS.md` (line 66) independently confirms `ASSESSES` "has no consumer for
> coverage balancing today," a separate, still-valid, and still-open reason K6 remains unbuilt. Do not
> read this correction as "K6 is ready to build" — only as "the specific blocker named in the roadmap no
> longer applies; a different one does."

> **Correction 3 — `pal_learning_relations`/`pal_concept_nodes` are wired, via Pipeline B, not unwired.**
> A standing memory note (outside this doc set) characterized these PAL tables as unwired/not projected
> into Neo4j. Verified false as of this session: `CoherenceGraphProjection::projectLearningRelations()`
> and `projectConceptNodes()` both exist and run nightly as part of Pipeline B (§4) — writing, among
> other things, `(:Concept)-[:HAS_NODE]->(:ConceptNode)` and `(:StuDetail)-[:MASTERS_NODE]->(:ConceptNode)`.
> They were previously unwired; they are not unwired now.

---

## 7. Summary — ticket item → answer

| Ticket item | One-line answer | Primary source |
|---|---|---|
| 1. Review implementation & requirements | Two independent implementations exist: the Gate-3 audit-trail pilot (Verified) and `LearnerContextGraphService` (a net-new finding, not in any prior doc). Open requirement: no UI reads the audit trail back. | §1; `ARCHITECTURE.md`, `K12_IMPLEMENTATION.md` |
| 2. User context / activities / relationships / history / data sources | `LearnerContextGraphService`'s 4 layers (A–D) map directly onto this; dual data sources are MariaDB (A/B/D) + Neo4j via `CoherenceMapRepository` (C). | §2; `LearnerContextGraphService.php` |
| 3. LMS modules ↔ CG entities | Two parallel mapping mechanisms (`ModuleGraphMapper` centralized, `OntologyRegistry` decentralized); concrete node/edge list reproduced in §3. | §3; `GraphSchema.php`, `GraphModuleBrowser.php`, `GraphQueryService.php` |
| 4. Required graph structure & retrieval mechanism | Structure = §3's schema, populated by 4 real pipelines (A–D, not documented elsewhere as a set); retrieval = `GraphQueryService`/`GraphModuleBrowser`/`CoherenceMapRepository`. One true gap: `ContextAssemblyContract` remains proposed, unbuilt. | §4; `CONTEXT_LAYER_DESIGN.md` |
| 5. Neo4j data review — missing relationships | One documented consumer gap (`ASSESSES`); four fresh findings, the most significant being `PREREQUISITE_OF`'s 9,499-edge, non-pedagogical, deliberately-avoided data quality issue. | §5; `CoherenceMapRepository.php`, `ReconcileCommand.php` |
