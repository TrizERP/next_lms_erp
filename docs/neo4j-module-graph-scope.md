# Neo4j Module Graph Scope — which tables become nodes, per module

**Date:** 2026-08-03
**Companion to:** [`neo4j-migration-report.md`](./neo4j-migration-report.md)
**Decision recorded:** Only tables a module actually uses are projected into Neo4j, following the pattern already established by the Assessment module.

**Source of figures:** `vivek_erp` @ `202.47.117.220`. Table→module attribution comes from static scanning of `app/Http/Controllers/<module>` and `app/Models/<module>` for `DB::table('…')`, `protected $table`, and `->join('…')`. Row counts are from `information_schema` (approximate for InnoDB).

---

## 1. The pattern the Assessment module already establishes

This is the contract every other module should follow. It is already correct — it does **not** have the name-collision or tenancy bugs found in `Neo4jSyncController`.

### 1.1 Write side — global helpers

`neo4jCreateNode()` and `neo4jCreateRelationship()` in [`app/Helpers/Helper.php:3052`](../app/Helpers/Helper.php#L3052) and [`:3088`](../app/Helpers/Helper.php#L3088):

```php
neo4jCreateNode(
    'Assessment',
    ['assId' => (int)$questionPaperId, 'sub_institute_id' => (int)$sub_institute_id], // identity
    ['displayLabel' => 'Assessment:'.$paper_name, 'exam_type' => 'pal', ...]          // payload
);

neo4jCreateRelationship(
    'Student',   ['student_id' => (int)$user_id],
    'MASTERS',
    'Chapter',   ['chId' => (int)$chId],
    ['proficiency_score' => (int)$score]
);
```

Both emit `MERGE`, bind all *values* as parameters, and interpolate only labels/relationship types (which are code constants). Reuse these — do not write raw Cypher per module.

### 1.2 Read side — visualisation payload

[`StudentResultGraphController::show()`](../app/Http/Controllers/neo4jGraph/StudentResultGraphController.php) returns the shape the graph UI consumes:

```json
{ "rootNode": {...}, "nodes": [{"id","labels","properties"}], "relationships": [{"id","type","startNode","endNode","properties"}] }
```

### 1.3 The four rules to carry into every module

1. **Identity = MariaDB PK + `sub_institute_id`.** Never `MERGE` on a name. (`Assessment` does this; `Neo4jSyncController` does not.)
2. **Every node carries `displayLabel`** for the visualiser.
3. **Cast to `(int)`/`(float)` at the boundary** — Neo4j is strictly typed, and `"5" ≠ 5` in a `MERGE`, which silently creates duplicate nodes.
4. **Aggregate before projecting.** See `MASTERS` in [`palController.php:1360-1395`](../app/Http/Controllers/lms/pal/palController.php#L1360-L1395): it reads 2.29M-row exam history, collapses to one score per chapter, writes **one** edge. Never mirror raw transaction rows.

---

## 2. Correction to the earlier report

The migration report stated the concept layer was empty, based on `pal_concepts` = 1 row. That was **incomplete**. The LMS module uses a *different* table:

| Table | Rows | Used by |
|---|---:|---|
| `lms_concept` | **1,338** | LMS module (13 refs) — the real concept layer |
| `topic_master` | **13,362** | LMS module (6 refs) |
| `pal_concepts` | 1 | PAL V4 services — unpopulated |

So a concept layer **does** exist with 1,338 concepts and 13,362 topics; it simply lives in `lms_concept`/`topic_master`, not in the `pal_*` tables the V4 intelligence services read. That is a *data-routing* problem, not a missing-data problem, and it is a materially smaller task than the report implied. Phase 3 is less blocked than stated — the practical work is pointing the PAL engines at `lms_concept`, or backfilling `pal_concepts` from it.

Everything else in the report stands: the `pal_*` tables are still empty, `chapter_master` is still only 74–98 rows across 8 subjects, and `content_master.concept_id` is still populated on 1 of 26,154 rows.

---

## 2A. Coverage finding — curriculum and students live in different tenants

Re-verification surfaced something that materially limits what a curriculum graph can cover today. Counting distinct `sub_institute_id` per table:

| Table | Rows | Institutes with data |
|---|---:|---:|
| `standard` | 1,000 | 73 |
| `subject` | 2,025 | 70 |
| `tblstudent` | 83,715 | **48** |
| `sub_std_map` | 6,652 | 39 |
| `topic_master` | 13,561 | 6 |
| `lms_question_master` | 62,206 | 6 |
| `content_master` | 26,154 | **4** |
| `chapter_master` | 98 | **1** |
| `lms_concept` | 1,372 | **1** |

Content is concentrated in two institutes — `1` (14,195 rows) and `195` (11,914) — with institutes `76` (40) and `47` (5) holding trace amounts. But the largest *student* populations are institutes `61` (7,652), `203` (6,032), `47` (5,730), `254` (5,157) and `76` (5,019). Institute `1` has 3,432 students; institute `195` does not appear in the top ten at all.

**Only institute 1 has a complete `Chapter → Concept → Content → Student` chain** (3,432 students, 98 chapters, 1,372 concepts, 14,195 content rows). The other 47 institutes with students have essentially no curriculum or concept data.

This confirms the earlier single-tenant observation about `lms_data_content_neo4j` was not an artifact of that one view — it reflects the real distribution.

**Consequence:** Phase 1 will produce a correct graph **for institute 1 only**. That is a legitimate pilot and worth doing, but a multi-tenant curriculum graph is **not achievable from current data**. The gap is content authoring, not engineering, and no migration approach closes it. Anyone promised a graph across all 56 schools should be told this now, not after Phase 1 lands.

---

## 3. Modules to project — IN

### 3.1 Curriculum / LMS — **Priority 1** (82 tables touched, 13 projected)

The spine. Everything else attaches here.

All row counts below are **exact `COUNT(*)`**, re-verified 2026-08-03.

| Table | Rows | Refs | Institutes | Becomes |
|---|---:|---:|---:|---|
| `school_setup` | 56 | 15 | — | `(:Institute)` |
| `academic_section` | 237 | 17 | — | `(:AcademicSection)` |
| `standard` | 1,000 | 28 | 73 | `(:Standard)` |
| `subject` | 2,025 | 18 | 70 | `(:Subject)` |
| `sub_std_map` | 6,652 | 17 | 39 | edge `(:Standard)-[:HAS_SUBJECT]->(:Subject)` |
| `chapter_master` | **98** | 12 | **1** | `(:Chapter)` |
| `topic_master` | 13,561 | 6 | 6 | `(:Topic)` |
| `lms_concept` | **1,372** | 13 | **1** | `(:Concept)` ← **the important one** |
| `content_master` | 26,154 | 12 | 4 | `(:Content)` |
| `lms_question_master` | 62,206 | 17 | 6 | `(:Question)` |
| `lms_question_mapping` | 516,016 | 6 | — | edge `(:Question)-[:ASSESSES]->(:Chapter\|:Concept)` |
| `content_mapping_type` | 1,907 | 2 | — | edge `(:Content)-[:TEACHES]->(:Concept)` |
| `lms_mapping_type` | 71,532 | **51** | — | `(:MappingType)` + `PARENT_OF` — **not edges**, see §3.1.1 |

```
(:Institute)-[:HAS_SECTION]->(:AcademicSection)-[:HAS_STANDARD]->(:Standard)
(:Standard)-[:HAS_SUBJECT]->(:Subject)-[:HAS_CHAPTER]->(:Chapter)
(:Chapter)-[:HAS_TOPIC]->(:Topic)-[:COVERS]->(:Concept)
(:Concept)-[:PREREQUISITE_OF]->(:Concept)          ← must be authored; no source table
(:Content)-[:TEACHES]->(:Concept)
(:Question)-[:ASSESSES]->(:Concept)
```

> `PREREQUISITE_OF` has **no source table**. It is the edge that makes the graph worth building, and it must be authored by curriculum staff or inferred. Plan for it explicitly.

#### 3.1.1 `lms_mapping_type` — resolved, and it is not what was assumed

The earlier draft assumed this was a polymorphic mapping table that should become edges. **That was wrong.** Its actual schema:

```
id, name (text), parent_id, globally, chapter_id, topic_id, status, type, element_id, created_at
```

Sample row: `{"name":"Depth of Knowledge (Easy, Medium, Hard)","parent_id":0,"globally":1,"chapter_id":0,...}`

It is a **self-referencing taxonomy tree** (`parent_id`) holding classification vocabulary, optionally scoped to a chapter or topic. Correct modelling:

```
(:MappingType {id, name})-[:PARENT_OF]->(:MappingType)
(:MappingType)-[:SCOPED_TO]->(:Chapter|:Topic)      // only where chapter_id/topic_id ≠ 0
```

⚠️ **Data quality:** the `type` column is **empty on 71,368 of 71,532 rows (99.8%)**. The ~164 populated values are `Abilities` (52), `Knowledge` (31), `content_library` (30), `Skills` (10), `Interests` (6) — plus stray numerics (`39`, `3978`, `4073`, `37`) that look like IDs leaked into a type column. Do not key anything on `type` without cleaning it first.

#### 3.1.2 `lms_concept` — confirmed authoritative

```
id, extraction_id, name, description, subject_id, standard_id, chapter_id,
sub_institute_id, mastery_threshold, estimated_mastery_minutes, syear, created_at
```

Sample: `{"name":"Chemical Reaction Indicators","description":"Observations like change in state, color, gas evolution, or temperature indicate that a chemical reaction has taken place.","subject_id":3975,"standard_id":43,"chapter_id":1012,"sub_institute_id":1,"mastery_threshold":90,"estimated_mastery_minutes":15,"syear":2026}`

This is a **well-formed concept layer** — real names and descriptions, correctly linked to chapter/subject/standard/institute, and it already carries `mastery_threshold` and `estimated_mastery_minutes`, which is exactly what the PAL engines need. `extraction_id` indicates it was AI-extracted from content; rows were created 2026-06-15.

**Decision: `lms_concept` is authoritative. Repoint PAL V4 at it rather than backfilling `pal_concepts`.**

### 3.2 Assessment — **already implemented** ✅

| Table | Rows | Becomes |
|---|---:|---|
| `question_paper` | 5,440 | `(:Assessment)` |
| `lms_online_exam` | 147,390 | `(:Result)` |
| `lms_online_exam_answer` | 2,294,151 | ❌ **never as nodes** — aggregate into `[:MASTERS {proficiency_score}]` |

```
(:Result)-[:FOR_ASSESSMENT]->(:Assessment)-[:ASSESSES_CHAPTER]->(:Chapter)
(:Student)-[:HAS_RESULT]->(:Result)
(:Student)-[:MASTERS {proficiency_score}]->(:Chapter)
```

Existing work: [`addAssesmentController.php`](../app/Http/Controllers/neo4J/addAssesmentController.php) (write), [`StudentResultGraphController.php`](../app/Http/Controllers/neo4jGraph/StudentResultGraphController.php) (read), [`palController.php:1294+`](../app/Http/Controllers/lms/pal/palController.php#L1294) (live node creation on quiz submit).

### 3.3 Student — **Priority 1** (70 touched, 7 projected)

| Table | Rows | Refs | Becomes |
|---|---:|---:|---|
| `tblstudent` | 79,804 | 17 | `(:Student)` |
| `tblstudent_enrollment` | 176,634 | **40** | edge `[:ENROLLED_IN {syear}]` |
| `division` | 655 | 18 | `(:Division)` |
| `batch` | 3,000 | 12 | `(:Batch)` |
| `academic_year` | 522 | 8 | `(:AcademicYear)` |
| `student_optional_subject` | 102,990 | 7 | edge `[:STUDIES]` |
| `tblstudent_siblings` | 2 | 11 | edge `[:SIBLING_OF]` — 11 refs, **2 rows** |

Exclude from this module: `fees_breackoff`, `fees_title`, `fees_collect`, `transport_map_student`, `hrms_leave_allocation`, `student_height_weight` — they surface in student controllers but belong to excluded domains.

### 3.4 Result — **Priority 2** (47 touched, 5 projected)

| Table | Rows | Refs | Becomes |
|---|---:|---:|---|
| `result_create_exam` | 33,820 | 12 | `(:Exam)` |
| `result_exam_master` | 1,225 | 11 | `(:ExamType)` |
| `grade_master_data` | 394 | 13 | `(:Grade)` |
| `result_remarks` | 11,029 | 24 | property on the result edge |
| `academic_year` | 522 | **105** | `(:AcademicYear)` (shared) |

⚠️ Several heavily-coded result tables are **empty**: `result_html` (25 refs, 0 rows), `result_activity_marks` (14 refs, 0 rows), `result_marks` (8 refs, 0 rows), `result_sub_activity` (12 refs, 0 rows). Confirm which reporting path is actually live before projecting this module.

### 3.5 PAL — **Priority 2**, blocked on data

| Table | Rows | Becomes |
|---|---:|---|
| `pal_concepts` | 1 | `(:Concept)` — **empty; use `lms_concept` instead** |
| `pal_misconceptions` | 2 | `(:Misconception)` |
| `pal_session_events` | 18 | ❌ telemetry, keep in MariaDB |
| all other `pal_*` | 0 | ❌ nothing to project |

Resolve the `lms_concept` vs `pal_concepts` split (§2) before doing graph work here.

### 3.6 Learning Outcome — **Priority 3** (7 touched — the cleanest module in the codebase)

| Table | Rows | Refs | Becomes |
|---|---:|---:|---|
| `learning_outcome_indicator` | 11 | 38 | `(:LearningOutcome)` |
| `learning_outcome_question_master` | 15 | 6 | edge `[:ASSESSED_BY]->(:Question)` |
| `learning_outcome_student_marks` | 14 | 21 | edge `[:ACHIEVED]` |
| `school_sections` | 10 | 7 | `(:AcademicSection)` (shared) |

Only 7 tables and no cross-module bleed — an ideal low-risk pilot. But with 11/15/14 rows it is a *structural* pilot, not a demo with meaningful data.

### 3.7 Skill / Career — **Priority 3** (4 touched in-module, plus O*NET)

| Table | Rows | Becomes |
|---|---:|---|
| `master_skills` | 15,848 | `(:Skill)` |
| `s_jobrole` | 5,640 | `(:JobRole)` |
| `s_jobrole_skills` | 172,590 | edge `[:REQUIRES_SKILL]` |
| `s_skill_matrix` | 3 | edge `[:HAS_SKILL]` |
| `s_assessment_library` | 40 | `(:SkillAssessment)` |
| `onet_occupation_data` | 1,016 | `(:Occupation)` |

`(:Concept)-[:BUILDS]->(:Skill)-[:REQUIRED_BY]->(:JobRole)-[:MAPS_TO]->(:Occupation)`

⚠️ `s_skill_map_k_a` (133,143 rows, 79.7 MB) and the 10 `o_net_occupation_detail_*_summeries` tables (~110k rows) are **not referenced anywhere in the codebase**. Do not project them until someone confirms they are live.

### 3.8 Staff / Teaching — **Priority 3**

| Table | Rows | Becomes |
|---|---:|---|
| `tbluser` | 4,506 | `(:Staff)` |
| `tbluserprofilemaster` | 595 | `(:Role)` |
| `hrms_departments` | 108 | `(:Department)` |
| `mapped_teachers` | 11 | edge `[:TEACHES]->(:Subject)` |

Enables "who teaches which concepts" and teacher-effectiveness traversals. `mapped_teachers` has **11 rows** — verify before relying on it.

---

## 4. Modules to exclude — OUT

| Module | Tables touched | Largest tables | Why excluded |
|---|---:|---|---|
| **Fees** | 53 | `fees_breackoff` 180,880 · `fees_receipt_book_master` 9,778 | Money. Needs ACID; a second, eventually-consistent total is a business incident. `fees_payment` has **46 refs and 0 rows** — a mirror would copy nothing. |
| **Inventory** | 20 | `inventory_item_master` 321 | Stock ledger; pure CRUD, no traversal |
| **HRMS** | 10 | `hrms_attendances` 351,908 · `hrms_emp_leaves` 28,203 | Aggregate workload (`SUM`/`GROUP BY`) — Neo4j's worst case |
| **Transportation** | 12 | `transport_map_student` 30,191 · `transport_stop` 2,809 | *Looks* graph-shaped, but this is fixed-sequence assignment, not routing. No pathfinding query justifies it. |
| **Hostel** | 14 | `hostel_room_master` 96 | Occupancy state |
| **Library** | 6 | `library_book_circulations` 67,354 | Transactional lending |
| **Admission** | 21 | `admission_registration_v1` 836 | Funnel/pipeline. Only the `tblstudent` hand-off matters, already covered. |
| **Payroll · Visitor mgmt · Inward/Outward · Front desk** | — | — | Transactional; no traversal value |

Excluded modules keep using MariaDB exactly as today. Nothing about them changes.

---

## 5. Scope summary

| | Count |
|---|---:|
| Tables in database | 484 |
| Tables referenced by module code | 428 |
| **Tables projected into Neo4j** | **~40** |
| Est. nodes (Phase 1) | ~250k |
| Est. relationships (Phase 1) | ~900k |

Roughly **8% of tables** enter the graph. `lms_online_exam_answer` (2.29M) and `result_personalize_marks` (1.3M) contribute **zero nodes** — only aggregated `MASTERS` edges. This is what keeps the graph small enough to reload in minutes.

---

## 6. Suggested build order

| Step | Module | Why |
|---|---|---|
| 0 | Fix blockers §7 of the migration report | `MERGE`-on-name and missing `sub_institute_id` will corrupt the graph |
| 1 | **Curriculum/LMS** | The spine; nothing else traverses without it |
| 2 | **Student** + reconcile with existing Assessment nodes | Completes `Student → Result → Assessment → Chapter` |
| 3 | **Learning Outcome** | Only 7 tables — cleanest pilot for the read/visualisation endpoint |
| 4 | **Result** | After confirming which result tables are live (many are empty) |
| 5 | **PAL** | After the `lms_concept` vs `pal_concepts` split is resolved |
| 6 | **Skill/Career** | Largest strategic payoff, but depends on `(:Concept)` existing |

---

## 7. Open items — status after re-verification (2026-08-03)

| # | Item | Status |
|---|---|---|
| 1 | `lms_mapping_type` schema | ✅ **Resolved** — a taxonomy tree, not an edge table. See §3.1.1. The original assumption was wrong. |
| 2 | `lms_concept` vs `pal_concepts` | ✅ **Resolved** — `lms_concept` is authoritative and well-formed. Repoint PAL V4. See §3.1.2. |
| 3 | Empty-but-coded result tables | ✅ **Confirmed empty** — `result_html`, `result_marks`, `result_activity_marks`, `result_sub_activity` and `fees_payment` all return 0 rows. Still needs a product answer on which reporting path is live. |
| 4 | `PREREQUISITE_OF` source | ⬜ **Open** — no table supplies it in either schema. Authored by curriculum staff, or inferred? This is the critical path. |
| 5 | Unreferenced bulk data | ⬜ **Open** — `s_skill_map_k_a` (133,143 rows) and ten `o_net_occupation_detail_*` tables (~110k rows) referenced nowhere in code. Confirm before projecting. |
| 6 | Database credentials | ✅ **Resolved** — `.env` is back to `vivek_erp` / `vivek_user` and connects (MariaDB 10.11.9). All figures re-verified against it. |
| 7 | Multi-tenant curriculum coverage | 🔴 **New** — only institute 1 has a complete curriculum chain. See §2A. |

### Verification log

All structural claims re-confirmed by exact query on 2026-08-03:

| Claim | Value | Result |
|---|---:|---|
| Tables | 484 | ✅ |
| Rows (est.) | 8,886,606 | ✅ |
| Size | 2,319.8 MB | ✅ |
| Foreign keys | 91 | ✅ |
| Empty tables | 89 | ✅ |
| Triggers | 0 | ✅ |
| `neo4j_sync_queue` | 12,193 (12,185 done / 8 pending) | ✅ |
| `sync_log` | 9,240 (9,232 SUCCESS / 8 PENDING) | ✅ |
| Outbox timeline | created `2026-04-02 13:50:55`, processed `11:49:55` | ✅ |
| `pal_concepts` | 1 | ✅ |
| `content_master` with `concept_id` | 1 of 26,154 | ✅ |

Per-table row counts have been replaced throughout with exact `COUNT(*)`. Earlier figures came from `information_schema.table_rows`, which drifts a few percent on InnoDB; the largest correction was `chapter_master` (74 → **98**). Other notable exact values: `lms_online_exam_answer` **2,418,015**, `result_personalize_marks` **1,308,379**, `lms_question_mapping` **516,016**, `tblstudent` **83,715**. None of these changes alters a recommendation.
