# Context Graph — Student & Learning Context: Status Report

## 0. Why this document exists

A requirement ticket titled **"Context Graph - Student & Learning Context"** asked for five things:

1. Implement student-centric context relationships.
2. Connect students with standards, sections, subjects, chapters, topics, and learning activities.
3. Integrate historical learning progress and student interaction data.
4. Enable context retrieval for student-specific queries.
5. Validate student learning context using actual LMS data.

**This feature is already built and shipped, end to end — backend and frontend.** This document
is a status report confirming that, explaining how the system works, and naming the handful of
real gaps that remain (none of them block the ticket's core ask). It is not a build plan.

**Naming collision to resolve first:** this repo already uses "Context Graph" for a *different*
thing. `docs/context-graph/*.md` (ARCHITECTURE.md, K12_IMPLEMENTATION.md, KNOWN_GAPS.md, etc.)
documents the **Gate-3 pilot** — a per-request AI-decision audit trail
(`PedagogySuggestedContentService` → `CoherenceMapRepository` →
`PedagogySuggestedContentContextRecorder` → `ai_audit_logs`), which answers "why was this
recommendation made," not "what is this student's learning context." The ticket above is asking
about the **second, independent implementation** — `LearnerContextGraphService` — which that
same folder's own `REQUIREMENT_ANALYSIS_K12.md` (2026-11-23) already flagged as "not covered by
any doc in this folder." This report is about that second one. Don't conflate the two when
reading the rest of `docs/context-graph/`.

## 1. What's already live

- **Backend**: `app/Services/PAL/ContextGraph/LearnerContextGraphService.php` — a 4-layer
  per-student context assembler — plus
  `app/Http/Controllers/api/PAL/ContextGraphController.php`, exposing
  `GET /api/pal/context-graph/{learnerId}` (`routes/pal_api.php:69`). Registered inside the
  `pal.auth`-protected group (`App\Http\Middleware\PalApiAuth`). Shipped in commit `dabdab8a4`
  ("feat: add context graph and recommendation feed services with Neo4j browser integration"),
  **2026-11-05** — three weeks before this ticket was written.
- **Frontend**: `lms_k12`'s `app/pal/learning-intelligence/context-inspector/page.tsx`, titled in
  its own source **"Context Graph Inspector — Unified 4-Layer Context Graph,"** calling the above
  endpoint via `fetchUnifiedContextGraph(learnerId, subjectId)` in
  `app/pal/learning-intelligence/data/context-inspector.ts`. Already committed (`4689be2e`), not
  in-flight work.
- Both halves are wired to each other and to real data right now — no scaffolding, no feature
  flag, no TODO stub.

## 2. How it works

```
Browser (lms_k12)
  app/pal/learning-intelligence/context-inspector/page.tsx
        │  fetchUnifiedContextGraph(learnerId, subjectId)
        ▼
GET /api/pal/context-graph/{learnerId}?subject_id=...      (routes/pal_api.php:69)
        │
        ▼
PalApiAuth middleware   — verifies JWT, resolves {role, user_id, sub_institute_id},
        │                  enforces student-self / teacher-class-scope / admin-wide access
        ▼
ContextGraphController::show()
        │  - re-checks student-self ownership (belt-and-suspenders with the middleware)
        │  - resolves tenant id from the authenticated context, never from client input
        ▼
LearnerContextGraphService::build($learnerId, $subjectId, $tenantId)
        │
        ├─ Layer A: resolveUserContext()        SQL: tblstudent, tblstudent_enrollment,
        │                                        standard, division, student_optional_subject
        │                                        (falls back to sub_std_map), school_setup
        ├─ Layer B: resolveLearningActivities()  SQL: pal_diagnostic_attempts,
        │                                        pal_practice_sessions, lms_student_engagement
        ├─ Layer C: (inline in build())          Neo4j via CoherenceMapRepository::map()/
        │                                        readiness()/rootBlockers()
        └─ Layer D: resolveHistoricalContext()   SQL: pal_competencies, eso_decision_log,
                                                   pal_recommendation_log
        │
        ▼
{ available, user_context, learning_activities, knowledge_graph, historical_context,
  next_best_action, assembled_at }
```

Each layer, in the service's own terms:

- **Layer A — User Context** (`resolveUserContext()`): identity, the latest enrollment row
  (ordered by `syear` then `id`), standard name, division ("section") name, enrolled subjects
  (elective list, or the standard's full subject list if no electives are recorded), and
  institute name. If the student has no enrollment row at all, `build()` short-circuits to
  `{available: false}` — there is no partial context for an unenrolled student.
- **Layer B — Learning Activities**: last 5 diagnostic attempts (chapter-scoped), last 5 practice
  sessions (concept-scoped, with accuracy computed on read), and a raw count of
  `lms_student_engagement` rows. Each table is guarded with `Schema::hasTable()`, so a tenant
  missing one of these tables degrades gracefully rather than erroring.
- **Layer C — Knowledge Graph Relationships**: calls into `CoherenceMapRepository` (the same
  Neo4j-backed repository that drives the PAL learner-facing Coherence Map) to classify every
  concept in the student's standard+subject scope as mastered/ready/blocked, then walks
  `rootBlockers()` for every blocked concept to surface the deepest unmet prerequisite. Wrapped
  in `try/catch` — a Neo4j failure degrades this layer to `{available: false}` without breaking
  the request.
- **Layer D — Historical Trajectory**: last 10 mastery/confidence snapshots
  (`pal_competencies`), last 5 engine decisions (`eso_decision_log`), last 5 recommendation
  events (`pal_recommendation_log`).
- **Next Best Action**: if Layer C succeeded, calls `CoherenceRecommender::nextBestAction()` for
  a graph-aware "what should this student do next" suggestion.

Access control is layered twice: `PalApiAuth` resolves the caller's role/tenant/class scope
generically for any `{learnerId}`-bearing PAL route, and `ContextGraphController::show()` adds an
explicit re-check that a `student`-role caller can only request their own id.

## 3. Requirement-by-requirement verdict

| # | Requirement | Verdict | Evidence |
|---|---|---|---|
| 1 | Student-centric context relationships | ✅ Done | `LearnerContextGraphService::build()`, `app/Services/PAL/ContextGraph/LearnerContextGraphService.php:36-143` |
| 2 | Connect to standard/section/subject/chapter/topic/activities | ✅ Done except **topic** | see §4 below |
| 3 | Historical learning progress & interaction data | ⚠️ Done, with caveats | `resolveHistoricalContext()`, same file lines 289-351; see §4 |
| 4 | Context retrieval for student-specific queries | ✅ Done | `GET /api/pal/context-graph/{learnerId}`, `app/Http/Controllers/api/PAL/ContextGraphController.php:26-45` |
| 5 | Validate against actual LMS data | ❌ Not done | no test file anywhere references `ContextGraphController` or `LearnerContextGraphService` |

Requirement 2 in detail:

- Standard: `resolveUserContext()` lines 174-176 — done.
- Section (this ERP calls it "division," table `division`, joined via `tblstudent_enrollment.section_id`): lines 178-180 — done.
- Subject: lines 184-198 (elective `student_optional_subject`, fallback `sub_std_map`) — done.
- Chapter: done, but only indirectly — reached via `pal_diagnostic_attempts.chapter_id` (Layer B) and via Concept nodes' `chapter_id` property when Layer C groups them (`CoherenceMapRepository::readNodes()`, `app/Services/PAL/Coherence/CoherenceMapRepository.php:806`).
- **Topic: not surfaced anywhere in this service.** `(:Chapter)-[:HAS_TOPIC]->(:Topic)-[:HAS_CONCEPT]->(:Concept)` is real and synced in Neo4j (`app/Services/Graph/CoherenceGraphProjection.php`, `projectTopics()`), but `CoherenceMapRepository::readNodes()` (confirmed by direct grep — zero occurrences of "Topic" in that file) never reads the `Topic` label, so Layer C's `chapters` grouping never includes topic-level detail. This is the one real relationship gap in requirement 2.
- Learning activities: done (Layer B), though `lms_student_engagement` is reduced to a bare count — no type, no timestamp, no content reference.

Requirement 3 in detail — two caveats worth flagging plainly rather than letting the report overclaim:

- The service's own docblock claims Layer D covers "BKT mastery states, spaced repetition schedules, and decision audit logs" (`LearnerContextGraphService.php:20`). The actual code reads `pal_competencies`/`eso_decision_log`/`pal_recommendation_log` — there is no spaced-repetition query anywhere in `resolveHistoricalContext()`. The docblock overstates what's implemented.
- `pal_competencies` is the mastery source this service reads, but a different, more heavily used PAL service — `StudentActivityReportService` — documents in its own docblock that `pal_competencies` is "a DERIVED, secondary signal with a known null-`bloom_level` data quality issue" and deliberately reads `learner_node_state` (the ESO engine's live mastery state) instead. The two services disagree on which table is authoritative for a student's mastery history. This report doesn't resolve that disagreement — it's noted here so it isn't silently inherited as fact.

## 4. Known gaps and recommended remediation (not done in this pass)

These are the two gaps worth closing, with the remediation approach already decided:

- **Topic support — read-through only.** Extend `CoherenceMapRepository`'s read path (or add a
  sibling method called from Layer C) to also pull the already-synced `(:Topic)` nodes and
  `HAS_TOPIC` edges, and surface them in the `knowledge_graph.chapters`/`nodes` payload. No new
  Neo4j projection or sync work is needed — the data is already there. This is deliberately scoped
  narrow: the curriculum graph (`Chapter→Concept→Content→Student`) is structurally complete for
  only 1 of 48 institutes today (see §5), so investing in new write-side graph work for Topic
  coverage would be high effort for low current payoff. A read-through extension has no such cost.

- **Validation — automated Feature test suite.** Add Laravel Feature tests exercising
  `LearnerContextGraphService`/`ContextGraphController` against real seeded data, covering at
  minimum: a fully enrolled student (happy path), a student with zero enrollment rows (the
  `available: false` short-circuit), a student in a tenant without curriculum-graph coverage (Layer
  C degrading to `available: false` without breaking the response), and access-control enforcement
  (student requesting someone else's id → 403; teacher outside their class scope; admin unrestricted).
  This closes requirement 5 without requiring a one-off manual audit.

Smaller cleanup items worth tracking alongside the above, not urgent on their own:

- `LearnerContextGraphService`'s constructor accepts an optional `PalEvidenceRepository $evidence`
  (`LearnerContextGraphService.php:29`) that is never referenced inside `build()` — dead
  dependency, safe to remove whenever this file is next touched.
- `lms_student_engagement` detail is collapsed to a count; if "student interaction data" needs to
  get richer later, this is the field to expand first.
- The docblock/code mismatch on BKT/spaced-repetition (§3) should be fixed by editing the
  docblock to match reality, or implementing the missing read — whichever is decided when this
  file is next worked on.

## 5. Caveats — things this report will not let stand unqualified

- **Curriculum-graph coverage is effectively single-tenant.** Per prior Neo4j migration
  auditing, `tblstudent` spans 48 institutes, but `chapter_master`/`lms_concept` each cover only 1,
  and `content_master` covers 4. Only institute 1 has a complete `Chapter → Concept → Content →
  Student` chain. For the other 47 institutes, Layer C of this service will read back
  `knowledge_graph.available: false` — structurally empty, not merely stale. Any claim that "the
  Context Graph connects students to curriculum" should be read as true for institute 1 today, and
  true for the rest only once their curriculum graphs are populated.
- **Neo4j only ever projects `Student→Standard` (`ENROLLED_IN`).** There is no `Section`/`Division`
  label wired to `Student` in the graph, and no `(:Student)-[:HAS_SUBJECT]->(:Subject)` edge either
  — subjects hang off `Standard`, not `Student`, in the graph. Section and subject reach the student
  only through the SQL joins in Layer A, never as direct Neo4j edges. This is fine for current
  behavior (Layer A is SQL by design) but should not be described as "the graph connects students to
  sections/subjects" — it doesn't; the relational database does, and the service reads both stores
  to assemble one payload.
