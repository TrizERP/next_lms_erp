# KG/CG Business Functionality Roadmap

Cross-product roadmap for using existing Knowledge Graph (KG) and Context Graph
(CG) data in real application features across K-12 (`next_lms_erp` + `lms_k12`),
G2G (`hp_erp`), and EB (`hpbrain_backend`) — not for building another graph
visualization. Produced after Tier 1 (K-12 KG/CG consuming features: graph-aware
ESO prerequisite gating, Pedagogy content merge, the Recommendation Feed UI),
which proved the pattern: wire already-built-but-unused graph intelligence into
real decisions before building anything new.

## Step 1 — Architecture Findings (verified directly, not assumed)

### There is no single Centralized KG spanning all three products

K-12, G2G, and EB each have their own separate, non-federated backend, and only
one thin bridge connects any of them:

| Product | Graph DB | Real data? | Bridge to other products |
|---|---|---|---|
| K-12 (`next_lms_erp`) | Neo4j, own instance | Yes — ~191k Student, real Chapter/Question/Concept graph | None into G2G/EB |
| G2G (`hp_erp`) | Neo4j, separate instance (`bolt://dev.triz.co.in:7687`) | Mixed — see below | None into K-12/EB |
| EB (`hpbrain_backend`) | None — deliberately deferred (`adr/ADR-008-defer-neo4j.md`: "the implementation never did this"; MySQL `WITH RECURSIVE` CTEs used instead) | N/A | SQL-only `CrossProductIdentityResolver` (below) |

The only real cross-product asset today is
`App\Domain\Universal\CrossProductIdentityResolver` (lives in `hp_erp`, table
`cross_product_identity_links`) — matches the same person by email across
K-12/`tbluser`, G2G/`tbluser`, EB/`hpbrain_people`. Live state (2026-11-18):
**67 confirmed, 6 rejected, 0 draft**, but 100% of those are K-12↔G2G pairs — the
EB-side matching method (`proposeEbMatches()`) is written but has produced zero
confirmed or draft rows against live data. `PersonProfileController` (in
`hpbrain_backend`) already aggregates G2G + EB + K-12 (via the confirmed link)
into one person record with an optional AI narrative — a real, working,
SQL-only cross-product feature, already more than "just displaying
relationships."

**Why this matters:** any "Centralized KG + CG" cross-product feature
(context-aware assistant, impact analysis spanning products, cross-product
next-best-action) is not achievable by consuming an existing graph, because no
graph spans products. The one achievable cross-product work is built on the
SQL identity bridge, not Neo4j.

### K-12 — confirmed rich, mostly already covered by Tier 1; two new gaps found

Already real and wired (Tier 1 + prior): Centralized KG (`GraphModuleBrowser`),
Decentralized KG (Student/Fees only), Context Graph (`LearnerContextGraphService`),
graph-aware ESO prerequisite gate, Pedagogy content merge, Recommendation Feed UI.

Two gaps found this pass:
- **`CoherenceRiskAgent` / `CompoundingRootBlockerDetector`**
  (`app/Domain/PAL/CoherenceRisk/`) — a fully governed agent that calls
  `CoherenceMapRepository::rootBlockers()` (Neo4j) to find students whose weak
  concepts share root-blockers spanning ≥2 chapters, with hypothesis/evidence/
  explanation and a draft recommendation (`max_verb = Verb::Recommend`, never
  auto-executes). Seeded into `ai_agents`, reachable generically via
  `POST /api/ai/agents/{agent}/run`. Zero matches anywhere in `lms_k12` — no UI
  calls it at all.
- **`CoherenceMapRepository::health()`** (`without_content`/`without_questions`/
  `fit_to_use` per standard+subject) is already fetched by the Coherence Map
  frontend and then only 3 of its 6 fields are rendered — not a new feature,
  finishing one that's already half-displayed.

Still true: the Teacher Insights roster
(`StudentActivityReportService::buildCohortRoster()`) is 100% SQL/mastery-score
— no prerequisite/root-blocker signal informs it. Question-paper generation and
`ContentIntelligenceService` still pick concepts from SQL only —
`:Question-[:ASSESSES]->:Concept` has no consumer for coverage balancing today.

### G2G — has its own real Neo4j graph, but with mixed data maturity (verified live)

`hp_erp` has an independent, genuinely wired graph: `Neo4jService`, an
event-driven `Neo4jProjector`, a `GraphVocabulary`, read controllers
(`JobRoleGraphController`, `DepartmentGraphController`,
`OrganizationGraphController`), and a `GraphRagRetriever` built for
skill-match/competency-gap explanation. Verified live: 35,327 total nodes.

| Label | Count | Verified quality |
|---|---|---|
| JobRole | 4,962 | Real — detailed taxonomy |
| Skill | 5,641 | Real — structured category/sub_category/micro_category taxonomy |
| Department | 1,272 | Plausible real (across Organization's 20 orgs) |
| Organization | 20 | Mixed — at least one sampled row looks like seed/test data |
| Competency | 10 | Demo-scale, inconsistent domain |
| Person | 13 | Stub-scale — nowhere near the real employee count |
| Student (reused label) | 129 | Not students — these are course names |
| Content (reused label) | — | Demo seed data (`institute_name:"Sample Institute"`) |

Relationship types present: `REQUIRES_SKILL`, `REQUIRES_COMPETENCY`,
`REQUIRES_KNOWLEDGE`, `REQUIRES_ABILITY`, `REQUIRES_ATTITUTE` [sic, live typo],
`REQUIRES_BEHAVIOUR`, `HAS_COMPETENCY`, `RATED`, `REPORTS_TO`, `IS_IN_DEPT`,
`BELONGS_TO_ORG`, `DEVELOPS`.

**Reading this correctly:** the JobRole↔Skill/Competency taxonomy side is real
and traversable; the people side (who has which competency, rated) is
pilot/demo-scale. A feature walking JobRole→REQUIRES_SKILL/COMPETENCY is on
solid ground; a feature needing rich per-employee graph data is not, yet.

Existing G2G task/skill logic (all SQL, not graph): `CapacityController`
(rule-based over-capacity check), `RoleFitController` (deterministic
`min(measured/required)` — does not use the graph today), `SkillMatchingController`,
a real `task_management_dependencies` table (predecessor/successor, FS type, lag
days), and a generalized `ApprovalEngine`.

**Caveat to carry into implementation:** a prior cross-repo audit
(`docs/cross-repo-audit/AUDIT-CROSS-REPO.md`, Oct 9) found G2G's Neo4j "routed
but unconfigured." The projector/vocabulary code and `.env` credentials are
dated after that audit, and the counts above are freshly verified live — but
re-confirm the connection is the intended one before building on it.

### EB — no graph, but a real and substantially underused SQL intelligence layer

`hpbrain_backend` has 129 tables implementing a genuine Signal→Evidence→Case→
Hypothesis→Recommendation→Decision→Execution→Outcome→Learning loop, with real
volume (`hpbrain_signals`=25,783, `hpbrain_evidence`=25,948, `hpbrain_cases`=54,
`hpbrain_recommendations`=42, `hpbrain_capability_assignments`=351). A
rule-based `RecommendationEngine` and a single-tenant `SearchController` already
exist. None of this is cross-product or graph-based.

## Step 2 & 3 — Candidate Features, Classified

Legend: ✅ Already implemented · 🟡 Partial · 🆕 New

### K-12

| # | Feature | KG/CG required | Which graph | Backend to reuse | Trigger | Behavior | Caveats | Effort | Status |
|---|---|---|---|---|---|---|---|---|---|
| K1 | Graph-aware prerequisite gating | `:Concept-[:REQUIRES]->:Concept` via `rootBlockers()` | CG | `EsoPolicyService` | Student advances in Practice/Check | Routes to true root-cause concept | Needs graph coverage per standard+subject (self-falls-back) | Done | ✅ |
| K2 | Pedagogy content merge from graph | `:Content-[:TEACHES]->:Concept` | CG | `PedagogySuggestedContentService` | Student views suggested content | Graph-sourced remediation content appears | Currently a no-op — all 124 live TEACHES edges are `draft` | Done | ✅ (inert) |
| K3 | Student-facing next-best-action card | `CoherenceRecommender` | CG | `RecommendationFeedController` | Student opens `/pal` | Shows consolidate/unblock/advance | `content_recommendations` thin | Done | ✅ |
| K4 | Teacher-facing intervention signal | `rootBlockers()` across weak concepts | CG | `CoherenceRiskAgent`, generic `AgentController` | Teacher opens class view | Surfaces "stuck on concept blocking N others across ≥2 chapters" | Never auto-executes; no UI entry point exists | Small | 🟡 → implementing |
| K5 | Expose coherence-map health fields already fetched | `without_content`/`without_questions`/`fit_to_use` | Centralized-adjacent | Already-called `fetchCoherenceHealth` | Curriculum/admin views Coherence Map | Shows content/question gaps per chapter | None — data already fetched | Trivial | 🟡 → implementing |
| K6 | Graph-balanced question-paper generation | `:Question-[:ASSESSES]->:Concept` | Centralized | none (SQL today) | Teacher generates AI question paper | Balances concept coverage via graph | `ASSESSES` has a known double-writer bug — reconcile first | Medium | 🆕 |
| K7 | Recall-queue reprioritization by fan-out | `rootBlockers()` reverse edges | CG | `MasteryOverviewService::recallQueue` | Spaced-repetition queue | High-fan-out concepts surface earlier | Purely additive re-ranking | Medium | 🆕 |
| K8 | Misconception cross-concept linkage | New edge or reuse REQUIRES | CG | `MisconceptionIntelligenceEngine` | Misconception detected | "This traces to a weak prerequisite" | Real gap, needs new signal | Medium-Large | 🆕 |
| K9 | Decentralized KG rollout to Curriculum/Question Bank | `ai_ontology_views` seeding | Decentralized | `OntologyViewResolver`/`GraphQueryService` | User opens module's KG tab | Real data instead of "not available yet" | Depends on K6 for Question Bank | Small (seeding) | 🆕 |

### G2G

| # | Feature | KG/CG required | Which graph | Backend to reuse | Trigger | Behavior | Caveats | Effort | Status |
|---|---|---|---|---|---|---|---|---|---|
| G1 | Graph-explained role-fit/competency-gap | `JobRole-[:REQUIRES_SKILL\|REQUIRES_COMPETENCY]->Skill/Competency` | G2G's own Neo4j | `GraphRagRetriever` (wiring to `RoleFitController` unconfirmed) | Manager/employee views role-fit screen | SQL score stays; graph adds *why* | Confirm `GraphRagRetriever`'s current callers first | Small-Medium | 🟡 |
| G2 | Smart task assignment by skill match | `Person-[:HAS_COMPETENCY]`, `JobRole-[:REQUIRES_SKILL]` | G2G's own Neo4j | `CapacityController` + new logic | Task created/reassigned | Suggests assignee by skill fit + capacity | Blocked: only 13 real `Person` nodes | Large | 🆕 |
| G3 | Task dependency/critical-path analysis | None — plain SQL | Neither | New service over `task_management_dependencies` | PM views project | Flags blocking chains | Table already real | Medium | 🆕 |
| G4 | Workload intelligence dashboard | None | Neither | `CapacityController` | Manager views workload | Extends existing over-capacity rule | Already real logic | Small-Medium | 🆕 |
| G5 | Workflow/approval recommendations | None | Neither | `ApprovalEngine` | Approval step reached | Suggests approver/flags stuck steps | Pure SQL extension | Medium | 🆕 |

### EB

| # | Feature | KG/CG required | Which layer | Backend to reuse | Trigger | Behavior | Caveats | Effort | Status |
|---|---|---|---|---|---|---|---|---|---|
| E1 | Cross-product identity/relationship discovery, completed | None (SQL bridge) | `cross_product_identity_links` + `PersonProfileController` | `CrossProductIdentityResolver::proposeEbMatches()`, `PersonProfileController` | Admin/HR looks up a person | Unified G2G+EB+K-12 record, AI narrative | `proposeEbMatches()` never produced a result against live data | Small | 🟡 |
| E2 | Cross-product intelligent search | None | `SearchController` (single-tenant) | Extend existing LIKE-search | User searches EB | Results span confirmed-linked records | Blocked on E1 | Medium | 🆕 |
| E3 | Shared entity intelligence / cross-product recommendations | None | `hpbrain_*`, `RecommendationEngine` | Existing engine | Case/signal created | Considers cross-product context via bridge | Blocked on E1 | Large | 🆕 |

## Step 4 — Recommended First Feature Per Product

**K-12 → K4** (teacher-facing `CoherenceRiskAgent` surfacing). Highest business
value of anything unimplemented, backend 100% complete and already
safety-governed, directly fills the "teacher intervention recommendations" gap.
K5 ships alongside it as a near-zero-effort addition.

**G2G → G1** (graph-explained role-fit/competency-gap). The only G2G graph data
that's both real and relevant is the JobRole/Skill/Competency taxonomy — G1 uses
exactly that without depending on the weak `Person` coverage that blocks G2
outright. Additive to an existing screen, zero risk to current ranking logic.

**EB → E1** (complete cross-product identity discovery). The most
"already-built" feature across all three products — the resolver, the matching
method, and the aggregating controller all exist; the EB side has simply never
run successfully against live data. Zero new architecture; E2/E3 are explicitly
blocked behind it.

## Step 5 — Restrictions Carried Into Implementation

No KG/CG architecture change, no new graph nodes/relationships, no new
pipelines, no PAL flow change, no auto-approving the 124 draft TEACHES edges,
tenant isolation and existing permissions preserved as-is, reuse existing
services rather than writing new ones, no changes to unrelated modules, no
commits/deploys without explicit approval.
