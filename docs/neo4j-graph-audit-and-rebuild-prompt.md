# Neo4j Live Graph Audit + Rebuild Master Prompt

**Date:** 2026-08-10
**Audited instance:** `bolt://dev.triz.co.in:7688` (browser `http://dev.triz.co.in:7475`), database `neo4j`
**Source of record:** MariaDB `vivek_erp` @ `202.47.117.220`
**Companion docs:** [`neo4j-migration-report.md`](./neo4j-migration-report.md), [`neo4j-module-graph-scope.md`](./neo4j-module-graph-scope.md)

All figures below were measured live against the running instance on 2026-08-10, not estimated.

---

## 1. Connections as they exist today

| Layer | Target | Status |
|---|---|---|
| Laravel → Neo4j | `bolt://dev.triz.co.in:7688`, user `neo4j`, password `admin` | ✅ Live, verified `RETURN 1` |
| Laravel → MariaDB | `vivek_erp` @ `202.47.117.220`, user `vivek_user` | ✅ Live, verified |
| Next.js (`C:\lms_k12`) → Neo4j | — | ❌ **No connection exists.** No driver, no env var, no client code |
| Next.js → Laravel | `NEXT_PUBLIC_API_BASE_URL_PROD=https://dev.triz.co.in` | ✅ The only path the frontend has |

Two facts worth calling out:

1. **The frontend has no graph feature.** The only match in `C:\lms_k12` source is the string `'knowledge-graph'` as a toolbar button id in [`RightFloatingToolbar.tsx:52`](../../../lms_k12/app/components/RightFloatingToolbar.tsx#L52). Nothing renders a graph. Every graph endpoint in Laravel (`/api/student-results/{stuId}/graph`, `/get-personalized-learning-path/{studentId}`, `/graph-view`, `/avionics-graph`) is consumed only by Blade views, not by Next.js.
2. **The two repos point at different source databases.** Backend `.env` → `vivek_erp` @ `202.47.117.220`. Frontend `.env` → `development_erp` @ `128.199.17.97` (under an `# mcp` heading). Decide which is authoritative before loading anything, or the graph will disagree with whichever app the user is looking at.

---

## 2. What is actually in the graph

**≈261,828 nodes / ≈618,991 relationships / 19 labels / 26 distinct edge patterns.**

| Label | Nodes | Has `sub_institute_id` | Orphaned |
|---|---:|---:|---:|
| Result | 143,360 | **0** | 31,781 |
| Question | 94,052 | 94,052 | 7,653 |
| Student | 12,801 | 11,611 | 7,208 |
| Chapter | 5,536 | 5,534 | 1,760 |
| StuDetail | 4,609 | 4,608 | 389 |
| Lesson | 719 | 719 | 0 |
| Assessment | 166 | 166 | — |
| Subject | 150 | 150 | 0 |
| Teacher | 118 | 118 | **118 (100%)** |
| Standard | 110 | 110 | 29 |
| ChapterStandardMap | 96 | **0** | **96 (100%)** |
| CompetencyStandards | 40 | **0** | **40 (100%)** |
| Curriculum | 31 | 31 | 0 |
| Concept | 11 | 11 | 0 |
| LearningContent | 11 | 11 | 10 |
| LearningObjects | 8 | **0** | 8 |
| Unit | 6 | **0** | 0 |
| AssessmentTypology | 3 | **0** | 3 |
| Misconception | 1 | 1 | 0 |

Tenants present: `195` (68,704 nodes), `1` (41,927), `76` (3,197), `47` (2,661), then `341`, `203`, `201`, `254`, `202`, `61`, `335`, `334` with under 250 each.

Edge patterns, by volume:

```
Student        -[HAS_RESULT]->            Result       501,034
Question       -[BELONGS_TO]->            Chapter       86,265
Student        -[ATTENDED]->              Lesson        16,350
StuDetail      -[HAS_STUDENT]->           Student        5,590
Student        -[ENROLLED_IN]->           Standard       5,472
Subject        -[HAS_CHAPTER]->           Chapter        2,001
Standard       -[HAS_SUBJECT]->           Subject          679
Chapter        -[HAS_LESSON]->            Lesson           557
Assessment     -[HAS_QUESTION]->          Question         545
Result         -[FOR_ASSESSMENT]->        Assessment       112
Subject        -[HAS_ASSESSMENT]->        Assessment       105
Assessment     -[ASSESSES_CHAPTER]->      Chapter           90
Student        -[ATTEMPTED]->             Assessment        40
Curriculum     -[INCLUDES]->              Subject           31
Subject        -[BELONGS_TO_CURRICULUM]-> Curriculum        31
Concept        -[PREREQUISITE_OF]->       Concept           28
Student        -[MASTERS]->               Chapter           18
Unit           -[HAS_CHAPTER]->           Chapter           15
Lesson         -[COVERS]->                Concept           11
Curriculum     -[HAS_UNIT]->              Unit               6
Student        -[MASTERS]->               Concept            3
Student        -[HAS_MISCONCEPTION]->     Misconception      3
Assessment     -[ASSESSES]->              Concept            2
Misconception  -[OCCURS_IN]->             Concept            1
LearningContent-[TEACHES]->               Concept            1
LearningContent-[REMEDIATES]->            Misconception      1
```

---

## 3. Verdict

**The approach is correct. The current graph is not.**

Keep: curated projection (not a 1:1 mirror), idempotent `MERGE` writes, the parameterised `neo4jCreateNode()` / `neo4jCreateRelationship()` helpers in [`Helper.php:3052`](../app/Helpers/Helper.php#L3052), the `displayLabel` convention, and keeping MariaDB as system of record.

Fix by **reload, not patch** — the graph is a derived read-model, so dropping and reloading is cheap and is the only way to clear the defects below.

### D1 — `Student` conflates person and enrollment · **critical**

12,801 `Student` nodes carry only **11,112 distinct `student_id`** values. The node is keyed on `stuId` but carries `syear`, `grade_id`, `section_id`, `standard_id` — it is an *enrollment row*, not a person.

Consequence, measured:

```
Students per Result node:  6 → 33,024 Results
                           5 → 32,534
                           4 → 18,840
                           3 → 14,274
                           2 →  8,700
```

501,034 `HAS_RESULT` edges over 143,360 Result nodes. There are **no duplicate `(Student, Result)` pairs** — and 500,817 of 501,034 edges correctly satisfy `s.student_id = r.student_id`. The fan-out is entirely because one person owns ~3.5 `Student` nodes.

**Every per-student count, average, and mastery aggregate computed from this graph is inflated roughly 3.5×.** This is silent — no query errors, the numbers are just wrong.

### D2 — 7 of 24 unique constraints enforce nothing

Constraints exist on `record_id` for `Chapter`, `Student`, `Subject`, `Standard`, `Question`, `Result`, `Assessment`. **`record_id` is NULL on 100% of nodes** (Chapter 0/5,536; Student 0/12,801). Neo4j uniqueness constraints skip nulls, so these are inert — and each carries a backing index that costs write throughput for nothing.

What that lets through:

- `Result.resultId` has **no** constraint → 143,360 nodes vs 143,269 distinct `resultId` = **91 duplicate Result nodes**.
- `Student.stuId` → 12,801 nodes vs 12,739 distinct = **62 nodes with a null `stuId`** slipped past the constraint.

### D3 — Tenancy is partial, and leaking

**143,513 nodes (55% of the graph) carry no `sub_institute_id`** — all 143,360 `Result` plus `ChapterStandardMap`, `CompetencyStandards`, `LearningObjects`, `Unit`, `AssessmentTypology`. Another 1,190 `Student` nodes are missing it.

88 relationships already cross tenants: `BELONGS_TO` 46, `ENROLLED_IN` 24, `HAS_STUDENT` 15, `ASSESSES_CHAPTER` 3.

> ### ⚠️ Correction to `neo4j-migration-report.md`
>
> §4 and §7 (blocker 3) state that a composite `(sub_institute_id, id)` key "prevents the current bug where all 56 institutes collapse into one graph."
>
> **Verified against MariaDB: this is not the failure mode.** In `chapter_master`, `standard`, `subject`, `tblstudent` and `lms_concept`, `COUNT(DISTINCT id)` equals `COUNT(DISTINCT CONCAT(sub_institute_id,'-',id))` in every case — `id` is a globally unique auto-increment PK. MERGE-on-id cannot collapse tenants.
>
> The collapse risk is real **only for MERGE-on-name**, which is what [`Neo4jSyncController.php:26-47`](../app/Http/Controllers/Neo4jSyncController.php#L26-L47) does (`MERGE (subject:Subject {subject: $subject})`).
>
> Also verified: **that controller has never run against this instance** — there is no `AcademicSection` label and no `OFFERS` relationship in the graph. The bug is latent, not manifested. Delete the controller.
>
> Keep `sub_institute_id` as a **mandatory, indexed property** for filtering and isolation. Do not make it part of the uniqueness key — that adds no correctness and slows every lookup.

### D4 — ~49,084 orphaned nodes (19% of the graph)

Loaded but never linked: 31,781 Results, 7,653 Questions, **7,208 Students (56% of all students)**, 1,760 Chapters, and 100% of `Teacher`, `ChapterStandardMap`, `CompetencyStandards`, `AssessmentTypology`.

### D5 — Three competing representations of one learner

`StuDetail` (4,609) `-[HAS_STUDENT]->` `Student` (12,801, = enrollments) plus `Result.student_id` as a raw property. `StuDetail` is `sync_log`-era legacy. Pick one identity and delete the rest.

### D6 — Model drift across writers

- `Curriculum-[INCLUDES]->Subject` (31) **and** `Subject-[BELONGS_TO_CURRICULUM]->Curriculum` (31) — the same fact stored twice in both directions. Neo4j traverses edges in both directions at equal cost; the reverse edge is pure overhead and a consistency hazard.
- `Student-[MASTERS]->Chapter` (18) **and** `Student-[MASTERS]->Concept` (3) — mastery written at two grains.
- `Subject-[HAS_CHAPTER]->Chapter` (2,001) **and** `Unit-[HAS_CHAPTER]->Chapter` (15) — one type, two parents.
- `Question-[BELONGS_TO]->Chapter` (86,265) — generic name; the scope doc specifies `ASSESSES`.
- `Student-[ATTEMPTED]->Assessment` (40) vs `Student-[HAS_RESULT]->Result-[FOR_ASSESSMENT]->Assessment` — two paths to the same fact.

### D7 — Legacy service code is unsafe

[`Neo4jService::createRelationship()`](../app/Services/Neo4jService.php#L118-L119) concatenates values into Cypher and does an **unlabelled** `MATCH` — injection risk plus a full node scan per write. `createNode()` is hard-coded to a `Content` label with career fields and has unreachable code after its `return`. Both predate the Helper functions; nothing should call them.

### D8 — The semantic layer is ~1% populated

`Concept` = **11 nodes** in Neo4j against **1,372 rows** in `lms_concept`. `PREREQUISITE_OF` = 28. `LearningContent` = 11. Prerequisite tracing and genuine recommendation cannot work at this density — this is the actual blocker on PAL, and it is a data problem, not an engineering one.

---

## 4. Corrected target model

```
(:Institute {instituteId, name})
  -[:HAS_STANDARD]->  (:Standard {standardId, name})
  -[:HAS_SUBJECT]->   (:Subject  {subjectId, name})
  -[:HAS_CHAPTER]->   (:Chapter  {chapterId, name, sortOrder})
  -[:HAS_TOPIC]->     (:Topic    {topicId, name})
  -[:COVERS]->        (:Concept  {conceptId, name, masteryThreshold, estimatedMasteryMinutes})

(:Concept)-[:PREREQUISITE_OF]->(:Concept)

(:Student {studentId, name})                        // the PERSON — one node per human
  -[:HAS_ENROLLMENT]-> (:Enrollment {enrollmentId, syear})
                          -[:IN_STANDARD]-> (:Standard)
                          -[:IN_DIVISION]-> (:Division)

(:Student)-[:MASTERS  {score, attempts, lastAt}]->(:Concept)   // Concept grain ONLY
(:Student)-[:HAS_MISCONCEPTION {severity}]->(:Misconception)

(:Content  {contentId, type})-[:TEACHES]->(:Concept)
(:Question {questionId, difficulty})-[:ASSESSES]->(:Concept)
(:Assessment {assessmentId})-[:HAS_QUESTION]->(:Question)
(:Student)-[:ATTEMPTED {score, obtainedMarks, totalRight, totalWrong, attemptedAt}]->(:Assessment)
```

Rules:

1. **One node per real-world entity.** `Student` = person, keyed on `tblstudent.id`. Enrollment is its own node.
2. **Key on `<label>Id` = the MariaDB PK.** Never on a name. Never two key properties per label.
3. **`sub_institute_id` mandatory and indexed on every node** — but not part of the uniqueness key (§D3).
4. **Cast at the boundary.** `(int)` / `(float)`. `"5" ≠ 5` in a `MERGE` and silently forks the node.
5. **Store each fact once, in one direction.**
6. **Aggregate before projecting.** `lms_online_exam_answer` (2.4M rows) becomes one `MASTERS` edge per student-concept — never nodes. `Result` should not be 143k nodes; collapse it into `ATTEMPTED` edge properties.
7. **No node without at least one relationship** — assert it after load.

---

## 5. Master prompt

> **This is a rebuild, not an in-place repair.** The prompt wipes the graph (STEP 2) and reloads it
> from MariaDB (STEPS 3–4). None of the current 261,828 nodes are edited — they are deleted and
> replaced. That is deliberate: the new model keys `:Student` on `studentId` where the old one keys
> on `stuId`, so loading on top of the existing graph would create a *second* set of nodes under the
> same labels rather than correcting the first. Neo4j here is a derived read-model whose whole point
> is that a reload costs ~15 minutes and risks nothing in MariaDB.

Paste the block below into Claude Code, run from `c:\xampp\htdocs\next_lms_erp`.

````text
You are working on the K-12 ERP Neo4j knowledge graph.

REPOS
  Backend (Laravel):  c:\xampp\htdocs\next_lms_erp
  Frontend (Next.js): c:\lms_k12

SYSTEMS
  MariaDB (system of record): vivek_erp @ 202.47.117.220, user vivek_user  [.env]
  Neo4j (derived read-model):  bolt://dev.triz.co.in:7688, browser :7475, user neo4j
  Never write to MariaDB. Neo4j is rebuildable from scratch — treat it as disposable.

READ FIRST
  docs/neo4j-migration-report.md
  docs/neo4j-module-graph-scope.md
  docs/neo4j-graph-audit-and-rebuild-prompt.md   <- audit of the live graph; §3 lists the defects

CONTEXT — the live graph has ~261,828 nodes / ~618,991 relationships and eight verified defects:
  D1 :Student conflates person and enrollment — 12,801 nodes, 11,112 distinct student_id.
     501,034 HAS_RESULT edges over 143,360 Results. Every per-student aggregate is ~3.5x inflated.
  D2 7 of 24 unique constraints key on record_id, which is NULL on 100% of nodes — they enforce
     nothing. Result.resultId is unconstrained: 91 duplicate Result nodes exist.
  D3 143,513 nodes (55%) have no sub_institute_id. 88 relationships cross tenants.
  D4 ~49,084 orphaned nodes, including 56% of all Students and 100% of Teachers.
  D5 StuDetail / Student / Result.student_id are three representations of one learner.
  D6 Reciprocal duplicate edges (INCLUDES vs BELONGS_TO_CURRICULUM); MASTERS written at two grains.
  D7 Neo4jService::createRelationship() concatenates Cypher and does an unlabelled MATCH.
  D8 Concept = 11 nodes vs 1,372 rows in lms_concept. The semantic layer is ~1% populated.

NON-NEGOTIABLE RULES
  1. One node per real-world entity. :Student is a PERSON keyed on tblstudent.id.
     Enrollment is a separate :Enrollment node. Do not reintroduce :StuDetail.
  2. Key every label on exactly one property, <label>Id, equal to the MariaDB PK. Never on a name.
  3. sub_institute_id is mandatory on every node and indexed — but NOT part of the uniqueness key.
     MariaDB ids are globally unique auto-increment PKs; composite keys add cost, not safety.
  4. Cast to (int)/(float) at the boundary. "5" and 5 MERGE into different nodes.
  5. Every fact stored once, in one direction only.
  6. Aggregate before projecting. Never mirror lms_online_exam_answer (2.4M rows) as nodes.
  7. Only use neo4jCreateNode() / neo4jCreateRelationship() from app/Helpers/Helper.php.
     Both parameterise values and interpolate only labels/rel-types. Never build Cypher by
     concatenating values.
  8. Verify every claim with a live query before reporting it. Do not estimate counts.

TASKS, in order — stop after each and report with the query output that proves it.

  STEP 0 — BASELINE
    Snapshot current label counts, relationship-type counts, constraints and indexes to
    docs/neo4j-baseline-<date>.md so the rebuild is comparable and reversible.

  STEP 1 — CLEAN HOUSE (code)
    Delete app/Http/Controllers/Neo4jSyncController.php. It MERGEs on names, which would collapse
    all 56 tenants. Confirmed never run: the graph has no :AcademicSection and no OFFERS edge.
    Remove createNode() and createRelationship() from app/Services/Neo4jService.php — legacy,
    unsafe, superseded by the Helper functions. Keep the constructor, run(), getClient(),
    testConnection(). Update every caller. Report which callers you changed.

  STEP 2 — SCHEMA RESET (Neo4j). THIS IS A REBUILD, NOT AN IN-PLACE REPAIR.
    Read this whole step before running any of it. The existing graph must be DESTROYED, not
    migrated. The new model keys :Student on studentId while the current one keys on stuId, so a
    MERGE against the old graph creates a SECOND set of nodes under the same labels instead of
    correcting them — and the old stuId constraint will not stop it, because the new nodes have a
    null stuId and Neo4j uniqueness constraints skip nulls (this is defect D2). Loading on top of
    the current graph doubles it.

    2A. Confirm STEP 0's baseline snapshot exists and is readable. It is the only record of the
        current graph. Do not proceed without it.
    2B. Drop ALL 24 existing constraints and ALL 24 non-LOOKUP indexes — not just the 7 inert
        record_id ones. Bulk delete is much faster with no indexes to maintain.
    2C. Wipe every node and relationship, batched so the transaction does not blow up on ~619k
        relationships:
            CALL { MATCH (n) DETACH DELETE n } IN TRANSACTIONS OF 10000 ROWS;
        Then assert the graph is empty: MATCH (n) RETURN count(n)  -->  must be 0.
    2D. Create the new schema: exactly one uniqueness constraint per label on <label>Id, plus a
        plain (non-unique) index on sub_institute_id for every label.
        Print SHOW CONSTRAINTS and SHOW INDEXES afterwards and confirm no record_id entry remains.

    If a stakeholder will not accept a full wipe, STOP and ask me. Do not attempt an in-place
    migration of the existing nodes — relabelling 143,360 Result nodes and splitting 12,801
    Student nodes into person + enrollment is strictly harder, slower and less verifiable than a
    15-minute reload from MariaDB, which is the entire reason Neo4j is a derived read-model here.

  STEP 3 — EXPORTER
    Write app/Console/Commands/Neo4jExport.php (signature: neo4j:export {--out=} {--tenant=}).
    Stream with DB::cursor so 2M-row aggregates never hit memory. Emit one CSV per node label and
    one per relationship type, matching the target model in the audit doc §4.
    Source tables: school_setup, standard, subject, sub_std_map, chapter_master, topic_master,
    lms_concept, content_master, lms_question_master, lms_question_mapping, tblstudent,
    tblstudent_enrollment, division, academic_year, question_paper, lms_online_exam.
    VERIFY every join column against the live schema before writing the query — the SQL in
    neo4j-migration-report.md §5.1 is illustrative, not tested. Report any column that does not exist.

  STEP 4 — LOADER
    Cypher scripts using LOAD CSV + CALL {} IN TRANSACTIONS OF 10000 ROWS. All MERGE, so the whole
    load is idempotent and re-runnable after a failure. Nodes first, then relationships.

  STEP 5 — ASSERTIONS (this is the deliverable that proves the rebuild worked)
    Write artisan neo4j:verify that FAILS loudly on any of:
      - any node missing sub_institute_id
      - any node with a null or non-integer <label>Id
      - COUNT(n) <> COUNT(DISTINCT n.<label>Id) for any label
      - any relationship whose endpoints have different sub_institute_id
      - any orphaned node
      - COUNT(:Student) <> COUNT(DISTINCT :Student.studentId)
      - Neo4j label counts vs the corresponding MariaDB COUNT(*), per tenant
    Report the actual pass/fail table.

  STEP 6 — LIVE SYNC (only after 0-5 pass)
    Revive the sync_log outbox with a supervised neo4j:drain consumer, per migration report §6 Tier 1.
    BLOCKER: .env has QUEUE_CONNECTION=sync — every save would block on a WAN round-trip to
    dev.triz.co.in. Switch to redis and run a worker BEFORE enabling. Add queue-depth alerting;
    a silently dead consumer is what killed the April 2026 attempt.
    Also resolve the 8 rows stranded PENDING in both sync_log and neo4j_sync_queue: replay or truncate.

OPEN QUESTIONS — ask me, do not assume:
  a. Which database is authoritative? Backend .env says vivek_erp @202.47.117.220; the frontend
     .env says development_erp @128.199.17.97. They disagree.
  b. Scope: institute 1 only (the sole tenant with a complete Chapter->Concept->Content->Student
     chain), or all tenants with whatever data exists?
  c. Where does PREREQUISITE_OF come from? No table supplies it. Authored by curriculum staff,
     or LLM-inferred from lms_concept descriptions? This is the critical path for PAL.
  d. Does the Next.js frontend need to read the graph? It currently has no Neo4j connection and no
     graph UI — only a 'knowledge-graph' toolbar id. If yes, it goes through Laravel, not a direct
     bolt connection from the browser.

DO NOT
  - mirror all 484 tables
  - project fees, HRMS, payroll, inventory, hostel, library, or transport
  - add Kafka/Debezium (2.3 GB; LOAD CSV finishes in ~15 minutes)
  - create a node label that has no traversal query justifying it
  - report a count you have not queried
````

---

## 6. Suggested sequencing

| Step | Work | Gate |
|---|---|---|
| 0 | Answer the four open questions + snapshot baseline | Nothing loads until (a) is settled |
| 1 | Delete `Neo4jSyncController`, strip legacy `Neo4jService` methods | No caller regressions |
| 2 | **Wipe the graph**, then schema reset — one key per label, tenant index | `MATCH (n) RETURN count(n)` = 0 |
| 3–4 | `neo4j:export` + `LOAD CSV` reload | ~15 min, idempotent |
| 5 | `neo4j:verify` assertions | **All green, or do not proceed** |
| 6 | Outbox live sync, after `QUEUE_CONNECTION=redis` | Queue-depth alert wired |
| 7 | Populate the concept layer — the real blocker (D8) | Curriculum work, not engineering |

Steps 1–5 are roughly a week and produce a correct, verifiable graph. It still will not answer *"why is this student failing?"* until D8 is closed, because that needs 1,372 concepts and authored `PREREQUISITE_OF` edges, not more migration code. Plan the schedule around that dependency.
