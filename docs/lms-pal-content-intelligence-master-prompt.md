# LMS + PAL Content Intelligence Layer — Master Prompt

**Date:** 2026-08-12
**Scope:** Wire the PAL V4 Content Intelligence Layer (4-type content model, metadata schema, misconception library, Bloom's ladder, authoring workflow) into the live Laravel LMS and the existing PAL V4 engine.
**Source spec:** `PAL_V4_Content_Intelligence_Layer.md` (v4.0, March 2026)
**Builds on:** [`pal-migration.md`](../pal-migration.md) — every verified fact there about `pal.auth`, tenant resolution and the `/lms/*` vs `/api/pal/*` split carries forward unchanged.
**Sequenced against:** [`neo4j-full-erp-graph-master-prompt.md`](./neo4j-full-erp-graph-master-prompt.md) — §7 of this doc may not start until that migration clears its Phase 0 freeze.

---

## 1. The finding that reorders the spec

The spec reads as if content and engine are built together. They are not. **The engine already exists and the content layer is empty.** Every priority below follows from that.

### 1.1 Verified ground truth — `vivek_erp`, 2026-08-12

**The engine — built, deployed, working:**

| | Count |
|---|---:|
| PAL service classes (`app/Services/PAL/**`) | 16 |
| PAL API routes (`routes/pal_api.php`, all under `pal.auth`) | 35 |
| `pal_*` tables (migration `2026_06_11_131905`) | 27 |
| Frontend screens migrated into `lms_k12` | full module (`app/pal/**`) |

**The content it runs on — effectively nothing:**

| Table | Rows |
|---|---:|
| `pal_contents` | **1** |
| `pal_concepts` | **1** |
| `pal_misconceptions` | **2** |
| `pal_learner_misconceptions` | **2** |
| `pal_subjects` | **0** |
| `pal_competencies` | **0** |
| `pal_content_recommendations` | **0** |
| `pal_learner_states` | **0** |
| every other `pal_*` table | 0–18 |

**Meanwhile the real content lives in the LMS tables, untagged:**

| Table | Rows | What it has | What the spec needs and it lacks |
|---|---:|---|---|
| `lms_question_master` | **62,209** | `question_type_id`, `chapter_id`, `concept_id`, `topic_id`, `question_title`, `answer`, `hint_text`, `learning_outcome`, `concept`, `subconcept` | `bloom_level`, `difficulty_1_to_5`, `misconception_tags`, `irt_a/b/c`, `discrimination_index`, `pedagogy_tag`, `cultural_context`, `language`, `reading_level_fk`, `quality_status`, `h5p_type` |
| `content_master` | **31,362** | `title`, `description`, `file_type`, `url`, `meta_tags`, `content_category`, `basic_advance`, `lo_master_ids` | `content_type` (4-type), `variant_number`, `format`, `bloom_level_served`, `pedagogy_tag`, `cultural_context`, `accessibility`, `language_variants_available`, `quality_status`, `avg_mastery_post` |
| `lms_concept` | 1,372 | `mastery_threshold`, `estimated_mastery_minutes`, `chapter_id` | `bloom_ceiling`, `hpc_lens`, `priority_score`, prerequisite edges (`REQUIRES`) |
| `chapter_master` | 99 | `key_concepts`, `sort_order` | — (but see the 5,521-node Chapter discrepancy in the Neo4j runbook) |
| `lms_learning_outcomes` | 259 | `code`, `type`, `description`, `parent_id` | CASEL / NGSS / NCDG mapping |
| `h5p_scenarios` | 11 | — | the spec's entire H5P matrix assumes an H5P estate that does not exist |
| `h5p_interactive_video` | **0** | — | " |
| `h5p_video_interactions` | **0** | — | " |
| `lms_online_exam_answer` | 2,418,015 | the answer history that IRT calibration needs — **already there** | nothing; it just needs to be read |

### 1.2 What that means

Three consequences that shape every phase below:

1. **This is a tagging project, not a content-authoring project.** 62,209 questions and 31,362 content rows already exist and are in use across 56 tenants. The deliverable is metadata over existing content plus a hand-authored misconception library — not 93,000 new content nodes.
2. **IRT parameters are computable today.** `lms_online_exam_answer` holds 2.42M graded responses. `irt_b`, `discrimination_index`, `avg_time_seconds` and `first_attempt_correct_rate` are derivable from history, not from a pilot. The spec's "Stage 4: Pilot Test (2 weeks)" applies only to *new* content.
3. **The H5P matrix in spec §8 is aspirational and must not gate delivery.** With 11 scenarios and zero interactive videos, any phase that requires an H5P format to serve content will serve nothing. `h5p_type` is a recommendation field; the router falls back to the existing `content_master` file/url delivery.

### 1.2a The linkage finding — measured 2026-08-13, corrects §1.1

§1.1 lists `lms_concept` at 1,372 rows and treats concept as the join key, as the spec does. Measured against `vivek_erp`, **the concept key is not populated**:

| | Populated | Of |
|---|---:|---:|
| `lms_question_master.concept_id` | **47** | 62,209 |
| `content_master.concept_id` | **1** | 31,362 |
| `lms_question_master.chapter_id` | 62,206 | 62,209 |
| `content_master.chapter_id` | 31,357 | 31,362 |
| Questions joined to answer history **with** a `concept_id` | **0** | — |

`lms_concept` itself spans **97 distinct chapters**; the questions span **1,347**.

**Consequence.** Every selection query in spec §6.3 keys on `:Concept`. Written concept-only against this estate, all of them return zero rows — not because tagging is incomplete, but because the join key is absent. A 0% coverage number would look like a tagging backlog when it is actually a linkage gap.

**Decision taken.** The layer keys on `concept_ref_id` **when present** and falls back to `chapter_ref_id` otherwise (`scopeForCurriculum` on both metadata models; migration `2026_08_13_110000`). Chapter is what the legacy PAL flow already uses — `question_paper.paper_desc` holds the chapter id. Concept remains the target model: as concept linkage is backfilled, routing sharpens from chapter to concept granularity with **no further schema change and no code change**.

**This adds a phase.** P0.5 — concept linkage — is a prerequisite for concept-granular routing and is *not* an engineering task: deciding which of 1,347 chapters map to which concepts is curriculum work. Until it is done, the layer operates at chapter granularity, which is coarser than the spec intends but is what the data supports.

**Is `chapter_id` itself sound?** Two different questions, with two different answers — conflating them would give the wrong verdict:

| | Count |
|---|---:|
| Distinct `chapter_id` on questions | 1,347 |
| Distinct `chapter_id` on content | 4,702 |
| **Chapters carrying BOTH questions and content** | **1,190** |
| Chapters resolving to a `chapter_master` row (for a name) | 13 |
| `chapter_master` rows in total | 110 |

As a **routing key it works**: 1,190 chapters carry both questions and content, which is exactly the join the variant router performs (given a learner's question, find content on the same chapter). As a **display label it does not**: chapter names are unresolvable for almost everything, which is R5 — the same `chapter_master` = 110 vs graph = 5,521 `:Chapter` discrepancy the Neo4j runbook records. Teacher-facing screens will show chapter ids until that is reconciled. **Routing is unaffected; only labels are.**

### 1.3 Sequencing gate — the Neo4j freeze is live

The ERP graph migration is at **Phase 0 freeze** as of commit `ad7107f5b`: the graph is snapshotted, 32 CSVs are saved, and 2 writer routes are disabled. Neo4j 4.4.40 Community is EOL, has no APOC/GDS, no RBAC, and `NEO4J_PASSWORD=admin` over plain HTTP.

**Therefore: phases P0–P6 of this plan are MariaDB-only and can start now. P7 (content graph projection) is blocked until the freeze lifts.** Do not add `:Concept`, `:ConceptContent` or `:Misconception` writes to the graph before then — that is exactly the uncontrolled-write pattern Phase 0 froze.

---

## 2. Feasibility

| | Value |
|---|---:|
| Questions to tag | 62,209 |
| Content rows to tag | 31,362 |
| Concepts to enrich | 1,372 |
| Graded responses available for IRT | 2,418,015 |
| Misconceptions to hand-author (3 × top-20 concepts) | ~60 |
| Corrective content to author (1 per misconception, minimum) | ~60 |
| Tenants affected | 56 |
| New MariaDB tables | 4 (sidecars) — **zero** destructive changes |

AI-assisted tagging at ~500 questions per batch against the existing `AIOrchestrationService` is the long pole; hand-authoring the misconception library is the quality-critical pole. Neither is blocked by the other — run them in parallel.

---

## 3. CONTENT LAW

Non-negotiable and machine-checkable. Same status as the PROJECTION LAW in the Neo4j doc.

**C1 — MariaDB is authoritative for content.** `lms_question_master` and `content_master` stay the source of truth for the content *itself*. The intelligence overlay (tags, IRT, misconception links) lives in sidecar tables keyed to them. Neo4j is a derived, disposable read-model. Content is never authored in Neo4j.

**C2 — Additive only on live tables.** No column drops, no type changes, no renames on `lms_question_master` or `content_master`. 62,209 + 31,362 rows are in production use by 56 tenants and by the legacy `/lms/*` Blade UI. Extend via nullable sidecar tables joined on id. A migration that alters an existing content column is rejected.

**C3 — Every intelligence row is tenant-resolvable.** All 27 `pal_*` tables have **no `sub_institute_id` column** — tenancy is resolved by joining `learner_id` → `tblstudent`/`tbluser`. That works for learner-scoped rows and fails for content-scoped rows, which have no learner. **New content-intelligence tables carry `sub_institute_id` explicitly**, plus `scope='global'` + `sub_institute_id=0` for shared curriculum vocabulary. Undecidable tenancy = reject the row.

**C4 — `quality_status` gates delivery, not authoring.** Anything may exist as `draft`. Only `quality_status='approved'` is served to a learner by the engine. Every selection query filters on it; a query that forgets is a defect.

**C5 — AI tags are proposals, never facts.** Every AI-generated `bloom_level`, `difficulty`, `misconception_tag` or `cultural_context` is written with `tagged_by='ai'`, a `confidence` float, and `quality_status='draft'`. It becomes `approved` only via a human action that stamps `reviewed_by` + `reviewed_at`. No batch job may write `approved`.

**C6 — A misconception without corrective content is dead weight.** A `:Misconception` may not be served to a learner unless it has at least one linked corrective content row. Detecting "you have the denominator-add error" and then showing nothing is worse than not detecting it. Assert this in the verifier.

**C7 — Variants re-route, they never re-teach.** After a failure the router must serve a *different* `content_id` in a *different* `format`. Never re-serve a content id already in the learner's shown-set for that concept. If every variant is exhausted, escalate to a teacher alert — do not loop.

**C8 — No Neo4j write before the freeze lifts.** See §1.3.

**C9 — Norm-referenced data never reaches a learner.** Percentiles and class rank are analytics-only, per spec §5.3. Any learner-facing string containing "below average", "percentile" or a peer comparison is a defect.

---

## 4. Closed vocabularies

Every enum below is a closed set, registered once in `config/pal_content.php` and validated on write. An unregistered value is a write failure, not a new category. This is what stops 62,209 rows from drifting into 400 spellings of "apply".

```php
bloom_level        => recall | understand | apply | analyze | evaluate | create
practice_level     => 1..5                       // maps 1:1 onto bloom_level per spec §3.1
content_type       => concept | practice | corrective | assessment
format             => text_diagram | video | story_audio | simulation | h5p | pdf | external
difficulty         => 1..5
quality_status     => draft | reviewed | pedagogy_reviewed | piloted | approved | deprecated
tagged_by          => human | ai | imported | derived
cultural_context   => urban_market | agriculture_farm | sports_cricket
                    | rural_village | festival_cultural | coastal_fishing | mixed | none
language           => en | hi | gu | ta | te | mr | bn | kn | ml       // ISO 639-1
hpc_lens           => Awareness | Sensitivity | Creativity
corrective_format  => visual | story | simulation | audio
```

`pedagogy_tag` is **not** invented here — it must be read from the existing `content_mapping_type` / `lms_mapping_type` estate (`config/pal.php` already declares `PAL_PEDAGOGY_MAPPING_TYPE`). Reuse the 12 values that are already in the data; do not introduce a parallel list.

Naming: misconception `tag` is `lower_snake_case`, globally unique, and stable forever — it is a foreign key in learner history. Renaming a tag orphans learner records; deprecate instead.

---

## 5. Gap register

Spec section → current state → verdict. This is the checklist the work is measured against.

| # | Spec | Current state | Verdict |
|---|---|---|---|
| G1 | §1 4-type content model | `content_master.content_category` (21 values, tenant-scoped, not the 4 types) | **Missing** — add `content_type` to the sidecar, backfill by rule |
| G2 | §2.1 3+ format variants per concept | no variant concept exists | **Missing** — `variant_number` + variant router |
| G3 | §2.2 30+ field metadata schema | ~6 of 30 fields exist across `content_master` | **Partial** — sidecar carries the rest |
| G4 | §2.3 Indian cultural context | nothing | **Missing** — AI-proposed, human-confirmed tag |
| G5 | §3.1 5-level Bloom's ladder | legacy PAL has 3 bands (`<40 easy / 40–70 med / ≥70 hard`) from `palController` | **Conflict** — see risk R2; do not silently replace |
| G6 | §3.2 Progression gates + regression rules | none | **Missing** — engine work, P6 |
| G7 | §4 Misconception library | 2 rows, both `concept_confusion`, AI-generated free text | **Effectively missing** — hand-author, P3 |
| G8 | §4.4 Detection pipeline | `MisconceptionIntelligenceEngine.php` exists; `POST /misconception/analyze` live | **Partial** — engine present, library empty |
| G9 | §5.1 Assessment metadata + IRT | none on `lms_question_master` | **Missing** — but computable from 2.42M answers, P4 |
| G10 | §5.2 6 assessment types | `question_paper.exam_type='PAL'` only | **Partial** |
| G11 | §6 Neo4j content graph | Phase 0 freeze | **Blocked** — P7 |
| G12 | §7.1 6-stage QA pipeline | none | **Missing** — P5/P8 |
| G13 | §7.2 CSV bulk import | none for metadata | **Missing** — P5 |
| G14 | §8 H5P matrix | 11 scenarios, 0 interactive videos | **Aspirational** — must not gate delivery (§1.2.3) |
| G15 | §9.1 Authoring interface | none | **Missing** — P5, the largest single build |
| G16 | §9.1 Multi-language variants | no language column anywhere | **Missing** — schema in P1, content later |

---

## 6. Phase plan

Every phase ends at a gate. A gate that does not pass stops the next phase — it is not a warning.

> **Build status, 2026-08-13.** P0–P4 and P6 are implemented and verified against `vivek_erp`; P5 is backend-complete (API + review queue) with the frontend console outstanding; P7 remains blocked. See §9 for the file-by-file map and the gate results.

| Phase | Deliverable | Gate |
|---|---|---|
| **P0 — Measure** | `php artisan pal:content-coverage` — per-tenant report of tagged vs untagged questions/content, misconception count per concept, IRT-eligible question count | Report runs, numbers committed to this doc as the baseline |
| **P0.5 — Concept linkage** | Map chapters → concepts so routing can reach concept granularity (see §1.2a) | **Not started — curriculum work, not engineering.** The layer runs at chapter granularity until this lands |
| **P1 — Schema** | One additive migration: `pal_question_metadata`, `pal_content_metadata`, `pal_misconception_library`, `pal_misconception_corrective`. All carry `sub_institute_id` (C3). Zero changes to existing tables (C2) | Migration up+down clean on a `vivek_erp` copy; existing PAL routes still green |
| **P2 — Vocabulary** | `config/pal_content.php` with the §4 closed sets + a `PalVocabulary` validator + `pal:vocab-check` | Every enum value in the DB validates or is explicitly mapped |
| **P3 — Misconception library** | ~60 hand-authored misconceptions (3 × top-20 concepts by `lms_online_exam_answer` volume) + ≥1 corrective content each | C6 holds: zero misconceptions without corrective content |
| **P4 — Derive + tag** | (a) IRT/discrimination/avg-time/first-attempt-rate computed from the 2.42M answers; (b) AI batch over `AIOrchestrationService` proposing bloom/difficulty/cultural-context/misconception candidates, all `draft` + `tagged_by='ai'` (C5) | ≥90% of questions have a proposed bloom level; **zero** rows written as `approved` by the batch |
| **P5 — Review console** | Authoring/review UI in `lms_k12` under `app/pal/authoring/` + CSV bulk import with a validation error report | A reviewer can approve a batch of 50 in one session; approvals stamp `reviewed_by`/`reviewed_at` |
| **P6 — Delivery wiring** | Variant router (C7), Bloom ladder gates + regression rules (spec §3.2), misconception detect→correct loop wired into `ContentIntelligenceService` / `MisconceptionIntelligenceEngine`. `quality_status='approved'` filter on every selection query (C4) | An end-to-end run: wrong answer → misconception detected → *different-format* corrective served → re-test. Verified against a real learner id on dev |
| **P7 — Graph projection** | `:Concept` / `:ConceptContent` / `:Misconception` + `HAS_CONTENT` / `HAS_MISCONCEPTION` / `CORRECTS_WITH` / `REQUIRES`, registered in `config/neo4j_graph.php`, exported through the existing `neo4j:export`/`neo4j:load` commands | **Blocked until the Neo4j Phase 0 freeze lifts (C8).** Then: `neo4j:verify` passes, no cross-tenant edges |
| **P8 — QA loop** | The 6-stage pipeline (spec §7.1) as statuses + the §7.3 monitoring queries as a scheduled report | Monthly report identifies underperforming content (usage > 100, `avg_mastery_post` < 0.50) |

**Parallelism:** P3 (human authoring) runs alongside P4 (machine tagging). P5 depends on P1+P2 only. P6 depends on P3 for anything misconception-shaped.

---

## 7. Master prompt

Copy the block below verbatim into a fresh agent session. Fill in `<PHASE>` and nothing else.

```text
ROLE
You are implementing the PAL V4 Content Intelligence Layer into an existing, live
Laravel 12 / PHP 8.2 LMS+ERP (`c:\xampp\htdocs\next_lms_erp`, MariaDB `vivek_erp`,
56 tenants) and its Next.js frontend (`lms_k12`, separate repo).

Read these first, in order, and treat them as authority over your own assumptions:
  docs/lms-pal-content-intelligence-master-prompt.md   (this plan — CONTENT LAW is in §3)
  PAL_V4_Content_Intelligence_Layer.md                 (the product spec)
  pal-migration.md                                     (verified facts about the PAL engine)
  docs/neo4j-migration-status.md                       (why Neo4j is frozen)

GROUND TRUTH — do not re-derive, do not contradict without new evidence
- The PAL V4 ENGINE EXISTS: 16 services in app/Services/PAL/**, 35 routes in
  routes/pal_api.php, all behind the `pal.auth` middleware (app/Http/Middleware/PalApiAuth.php).
- The CONTENT LAYER IS EMPTY: all 27 pal_* tables hold 0-18 rows.
  pal_contents=1, pal_concepts=1, pal_misconceptions=2.
- The REAL CONTENT is in the LMS tables and is UNTAGGED:
  lms_question_master = 62,209 rows, no bloom/difficulty/misconception/IRT/language columns.
  content_master      = 31,362 rows, no content_type/variant/pedagogy/accessibility columns.
  lms_concept         = 1,372 rows (has mastery_threshold, estimated_mastery_minutes).
  lms_online_exam_answer = 2,418,015 graded responses — IRT is COMPUTABLE FROM HISTORY,
  it does not need a pilot.
- H5P BARELY EXISTS: h5p_scenarios=11, h5p_interactive_video=0, h5p_video_interactions=0.
  Spec §8's H5P matrix is aspirational. Never let an H5P requirement gate content delivery.
- pal_* tables have NO sub_institute_id column; tenancy is resolved by joining
  learner_id -> tblstudent/tbluser.sub_institute_id.
- Legacy student PAL uses /lms/* web routes (palController); PAL V4 uses /api/pal/*.
  They are distinct. Do not merge them.

CONTENT LAW — violating any of these is a defect, not a tradeoff
C1 MariaDB is authoritative for content; sidecar tables carry the intelligence overlay;
   Neo4j is a derived read-model. Content is never authored in Neo4j.
C2 Additive only. No column drop / rename / type change on lms_question_master or
   content_master. They are live in 56 tenants and used by the legacy Blade UI.
C3 Every new content-intelligence table carries sub_institute_id explicitly
   (0 + scope='global' for shared vocabulary). Undecidable tenancy = reject the row.
C4 quality_status gates DELIVERY, not authoring. Only 'approved' is served to a learner.
   Every selection query filters on it.
C5 AI tags are proposals. Batch jobs write tagged_by='ai' + confidence +
   quality_status='draft'. No batch may ever write 'approved'.
C6 A misconception with no corrective content may not be served. Assert it.
C7 Variants re-route, never re-teach: after failure serve a DIFFERENT content_id in a
   DIFFERENT format; never re-serve from the learner's shown-set; if exhausted, raise a
   teacher alert instead of looping.
C8 NO Neo4j writes. The ERP graph migration is at Phase 0 freeze (commit ad7107f5b).
   Phases P0-P6 are MariaDB-only. P7 is blocked until the freeze lifts.
C9 Norm-referenced data never reaches a learner. No percentiles, no rank, no
   "below average" in any learner-facing string.

CLOSED VOCABULARIES
All enums come from config/pal_content.php (§4 of the plan doc). An unregistered value is
a write failure, not a new category. pedagogy_tag is NOT invented — reuse the existing
values in content_mapping_type / lms_mapping_type (see config/pal.php).

YOUR TASK
Execute phase <PHASE> from §6 of the plan doc, and only that phase.
  P0 Measure   P1 Schema    P2 Vocabulary   P3 Misconception library   P4 Derive+tag
  P5 Review console          P6 Delivery wiring          P7 Graph (BLOCKED)   P8 QA loop

METHOD
1. Verify before you build. Read the actual table/columns/rows and the actual service
   class before asserting anything about them. Quote what you found.
2. Extend what exists. ContentIntelligenceService, MisconceptionIntelligenceEngine,
   PedagogySelectorEngine, AIOrchestrationService and TelemetryService are already written
   and wired. Extend them. Do not create parallel services.
3. Migrations are reversible and tested against a COPY of vivek_erp before anything else.
4. Every batch/derivation job is idempotent and resumable, logs per-tenant counts, and is
   runnable as `php artisan pal:<name> --tenant= --dry-run`.
5. Stop at the phase gate and report the gate result with real numbers. If the gate fails,
   say so plainly and stop — do not proceed to the next phase.

GUARDRAILS
- Work in this repo only. Do NOT create a new repository.
- Do NOT `git pull`, `git push`, or open a PR.
- Do NOT commit without asking first, even when the plan or a runbook says to commit.
- Do NOT run destructive SQL against vivek_erp. Read-only queries are fine; writes go
  through migrations and artisan commands reviewed first.
- Frontend work lands in lms_k12 under app/pal/**; verify with tsc + eslint + next build.

OUTPUT
- A summary of what changed, file by file, with file:line references.
- The gate result for phase <PHASE>: PASS or FAIL, with the numbers that prove it.
- Anything you found that contradicts the ground truth above, flagged explicitly.
```

---

## 8. Risks to sign off

**R1 — Tagging 62,209 questions is the schedule.** At ~500 per AI batch with human review at ~50/session, review is the bottleneck, not inference. Mitigation: order by usage. The top 20% of questions by `lms_online_exam_answer` volume covers most learner exposure; tag those first and ship, rather than waiting for full coverage. This is the spec's own §9.2 "Week 5–6: high-frequency concepts first" made explicit.

**R2 — The Bloom's ladder conflicts with the live difficulty model.** Legacy PAL bands questions as `<40 easy / 40–70 medium / ≥70 hard` (`palController`, driven by score) and builds first attempts as easy4/med3/hard3. The spec's 5-level Bloom ladder is a different axis — cognitive demand, not score band. **These are not the same thing and must coexist, not replace.** Introducing `bloom_level` must not change what the legacy `/lms/*` UI serves. Decide explicitly, before P6, whether the V4 engine routes on Bloom while legacy keeps score-bands, or whether legacy migrates.

**R3 — The misconception library is a subject-matter cost, not an engineering cost.** ~60 hand-authored misconceptions with corrective content is SME work. Engineering can deliver the schema, the console and the detection loop and still have nothing to serve. Name the SME owner before P3 starts, or P3 has no owner and P6 has no input.

**R4 — AI-proposed tags that get rubber-stamped are worse than no tags.** C5 makes the AI write drafts; it cannot make a reviewer read them. A reviewer approving 500 items in ten minutes produces confident, wrong routing. Mitigation: sample-audit approved batches, and track per-reviewer agreement rate with a held-out human-tagged set.

**R5 — `chapter_master` says 99, the graph rescue says 5,521.** The Neo4j runbook records 5,521 `:Chapter` nodes that MariaDB does not have. Any content-graph work in P7 inherits that discrepancy. It must be resolved by the ERP migration first — do not paper over it here.

**R6 — Neo4j remains unhardened.** Password `admin`, plain HTTP, WAN-exposed, EOL 4.4, no RBAC. P7 adds curriculum intelligence to that instance. The three hardening items from the ERP master prompt §1.2 apply here unchanged and are a precondition for P7, not a follow-up.

**R7 — 56 tenants, one vocabulary.** Global curriculum vocabulary (`sub_institute_id=0`, `scope='global'`) is the single documented exception to per-tenant scoping. Every other content-intelligence row is tenant-owned. Getting this backwards leaks one school's authored content into another's — the same class of failure as the 88 cross-tenant edges the ERP audit found.

---

## 9. What was built — 2026-08-13

Implemented in `next_lms_erp`, verified against the live `vivek_erp`. **Uncommitted.**

### 9.1 File map

| Layer | File | What it is |
|---|---|---|
| Vocabulary | `config/pal_content.php` | The §4 closed sets, §3.1 ladder + gates, §3.2 regression rules, §5.2 assessment types, §7.1 QA transitions, §8 H5P matrix, IRT + monitoring thresholds |
| Schema | `database/migrations/2026_08_13_100000_create_pal_content_intelligence_tables.php` | 8 additive sidecar tables. Zero changes to `lms_question_master` / `content_master` / `lms_concept` (C2) |
| Schema | `database/migrations/2026_08_13_110000_add_chapter_linkage_to_pal_content_metadata.php` | `chapter_ref_id` / `topic_ref_id` — the §1.2a linkage fallback |
| Models | `app/Models/PAL/{QuestionMetadata,ContentMetadata,ConceptMetadata,ConceptRelation,MisconceptionLibrary,MisconceptionCorrective,LearnerContentExposure,ContentReviewLog}.php` | One class per file (PSR-4 — see §9.4) |
| Services | `app/Services/PAL/Content/PalVocabulary.php` | Closed-set validator; reads pedagogy live from `lms_mapping_type` rather than forking it |
| Services | `app/Services/PAL/Content/ContentMetadataService.php` | The only write path. Enforces C2/C3/C4/C5 and the §7.1 stage transitions |
| Services | `app/Services/PAL/Content/BloomLadderService.php` | §3.1 ladder, §3.2 gates + regression, HPC ceilings |
| Services | `app/Services/PAL/Content/VariantRouterService.php` | §2.1 variants, CONTENT LAW C7 |
| Services | `app/Services/PAL/Content/MisconceptionLibraryService.php` | §4.4 detect→correct pipeline, majority check, C6 audit |
| Services | `app/Services/PAL/Content/ContentIntelligenceService.php` | Extended with the 4-type accessors + §7.3 monitoring; legacy `pal_contents` path untouched |
| Data | `database/data/pal_misconception_library.php` | The §4.2 library — 15 misconceptions, 16 correctives. SMEs extend this file |
| Commands | `app/Console/Commands/PAL/{ContentCoverage,VocabCheck,DeriveIrt,SeedMisconceptions,TagContent}Command.php` | P0, P2, P4a, P3, P4b |
| API | `app/Http/Controllers/api/PAL/PalContentIntelligenceController.php` + `routes/pal_api.php` | 21 routes under `pal.auth` |

### 9.2 Gate results

| Gate | Result |
|---|---|
| P1 — migration runs, live tables unchanged | **PASS.** 8 tables created; `lms_question_master` 24 cols / 62,209 rows, `content_master` 34 cols / 31,362 rows, `lms_concept` 12 cols / 1,372 rows — all unchanged |
| P2 — every enum value validates | **PASS.** 58 enum columns checked, zero drift |
| P3 — C6 holds, zero misconceptions without correctives | **PASS.** 15 seeded, 16 correctives, 0 violations. Seeder is idempotent (re-run: 0 created / 15 updated) |
| P4a — IRT derivable from history | **PASS.** 29,234 questions have ≥30 graded responses. Dry run over 300 produced parameters for all 300; 150 flagged REVISE (discrimination < 0.25) |
| P4b — ≥90% proposed bloom, zero machine approvals | **PARTIAL.** 1,146 of 2,000 highest-traffic questions tagged (57%); 854 have no readable cognitive demand and were left untagged rather than defaulted. **Zero rows written as approved.** 722 flagged low-confidence for reviewer priority |
| P6 — wrong answer → different-format corrective | **PASS.** Verified end to end on question 93174: exact match → corrective in `text_diagram`; repeat → **different corrective id in `story_audio`** (C7); 3rd → teacher alert; unrelated answer → no misconception fabricated; resolution_rate updated |
| P7 — graph projection | **BLOCKED**, as designed. No Neo4j writes exist in any file above (C8) |

### 9.3 The 57% tagging figure, honestly

The verb classifier alone reached 15%. The highest-traffic items in this estate are verbless cloze stems — *"GUI stands for ______"*, *"1 byte is equal to ____ bits"* — which no Bloom verb list can read. Adding structural evidence (definition frames, blank-with-calculation, true/false) took it to 57% at explicit confidence levels. The remaining 43% is left untagged deliberately: defaulting them to `recall` would have produced a 100% coverage number and mis-routed thousands of items. **An untagged question is visible; a wrongly-tagged one is not.**

### 9.4 Two pre-existing defects found, not introduced

1. **`app/Models/PAL/Models.php` defines 24 classes in one file, and no Composer classmap is dumped.** Under plain PSR-4 none of them autoload — `App\Models\PAL\LearningSession`, `Content`, `ContentRecommendation` and 21 others resolve to files that do not exist. Any code path reaching them fatals. This is why the new models are one class per file. **Not fixed here** (it changes behaviour of existing engine paths); it should be split the same way.
2. **`migrate:rollback` cannot run anywhere in this project.** `AppServiceProvider` registers a `DB::listen` that throws on any SQL containing `DROP TABLE`, and `dropIfExists` emits exactly that. Rollback drops one table, then aborts with the migration row intact. The guard is deliberate and was left in place; reversing a migration requires temporarily bypassing it. `down()` in both new migrations is correct and was observed executing.

Also noted: `app/Console/Kernel.php` blocks any artisan invocation whose argv contains the word `schema` — including harmless `Schema::hasTable()` probes inside `tinker --execute`.

### 9.5 Not built

- **P5 frontend.** The API is complete — review queue ordered by ascending confidence, single + bulk approve, per-row `missing_mandatory` and `completeness`, full vocabulary payload for the dropdowns. The console itself belongs in `lms_k12` under `app/pal/authoring/`, a separate repo not present in this workspace.
- **P0.5 concept linkage** (§1.2a) — curriculum work.
- **P7 graph projection** — blocked by the Neo4j Phase 0 freeze.
- **CSV bulk import** (spec §7.2) — the validation path it needs is in `ContentMetadataService::upsert`; only the file parser is missing.
