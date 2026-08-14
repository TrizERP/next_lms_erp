# Neo4j Migration — Phase 1: classification of all 488 tables

> **Status: awaiting human review.** This document is the Phase 1 deliverable and its gate is
> *"reviewed by a human"*. Nothing downstream should be built until someone signs off on §3 and §4 —
> the registry in Phase 2 is generated from these decisions, so an error here propagates into every
> load phase.

**Measured:** 2026-08-10 against `vivek_erp` @ `202.47.117.220` (MariaDB 10.11.9), the authoritative
source fixed by decision DB-AUTH. **Read-only — nothing was written to MariaDB or Neo4j.**

**Companion docs:** [`neo4j-migration-status.md`](./neo4j-migration-status.md) ·
[`neo4j-migration-runbook.md`](./neo4j-migration-runbook.md) ·
[`neo4j-full-erp-graph-master-prompt.md`](./neo4j-full-erp-graph-master-prompt.md) ·
[`neo4j-graph-audit-and-rebuild-prompt.md`](./neo4j-graph-audit-and-rebuild-prompt.md)

---

## 1. Method — what was actually measured

Every number in this document was queried, not estimated or carried over from the audit docs.

| Measurement | How |
|---|---|
| Row counts | `SELECT COUNT(*)` per table, all 488. **Not** `information_schema.table_rows`, which is an InnoDB estimate |
| Size | `data_length + index_length` from `information_schema` |
| Code references | Regex sweep of 3,302 `.php/.sql/.js/.json` files under `app/ routes/ resources/views/ database/ config/ public/`, counting *quoted* occurrences (`'table'`, `` `table` ``, `FROM table`) separately from bare word hits — because `batch`, `subject`, `standard` and `timetable` are English words |
| Tenancy columns | `information_schema.columns`, matched case- and underscore-insensitively so `sub_institute_id`, `SUB_INSTITUTE_ID` and `SubInstituteId` all resolve |
| FK integrity | `LEFT JOIN` from child to parent, counting unmatched rows |
| Aggregate cardinality | `SELECT COUNT(*) FROM (SELECT ... GROUP BY ...)` — the real edge count each ledger collapses to |

**Discrepancy with the audit docs, resolved:** the audit reports 487 tables / 8,896,258 rows. Live
measurement finds **488 tables / 9,020,219 rows**. The row delta is because the audit used the
`information_schema` estimate; the table delta is one table added since. Both figures below are exact.

---

## 2. Headline result

| | Naive row-per-node | This classification |
|---|---:|---:|
| Nodes | 9,020,219 | **596,825** |
| Relationships | ~9,000,000+ | **1,853,811** |
| Source rows never mirrored as nodes | 0 | **6,949,135** (77%) |

Both figures sit inside the master prompt's budget (< 700,000 nodes / < 4,000,000 relationships).
The reduction comes entirely from PROJECTION LAW L3/L4 — ledgers become aggregated edges.

### Decisions

| Decision | Meaning | Tables |
|---|---|---:|
| `NODE` | Becomes a node label | 156 |
| `EDGE` | Becomes a relationship, one per row | 57 |
| `AGG_EDGE` | `GROUP BY` in SQL, one edge per group (L4) | 33 |
| `PROP` | Folded as properties onto another node or edge | 31 |
| `EXCLUDE` | Not projected, reason recorded per table | 194 |
| `REVIEW` | **Needs a human decision** — see §5 | 17 |
| | **Total** | **488** |

### Tiers

| Tier | Meaning | Sync cadence | Tables |
|---|---|---|---:|
| **A** | Full graph — entities + rich relationships, traversal is the point | Live (queued observers) | 56 |
| **B** | Entity + aggregated edges | Nightly backfill | 188 |
| **C** | Reference / lookup dimension | Manual, on data refresh | 51 |
| **D** | Excluded — logs, caches, rights matrices, framework tables | — | 193 |

---

## 3. Findings that change the plan

These are measured contradictions between the master prompt's assumed schema and the live database.
Each one changes what Phase 2 must generate. **F1–F5 are the ones to read before approving anything.**

---

### F1 — The curriculum spine has two generations, and the older one no longer connects

`chapter_master` holds **99 rows**, with ids spread across the range **1012–8677**. Ninety-eight of
them carry a distinct `extraction_id`, so what survives is the output of the current
document-extraction pipeline — one chapter per extracted document, and almost all of it tenant 1.

Meanwhile the bulk content tables reference a chapter id space that spans 1–8560 and mostly does not
resolve:

| Child table | Rows checked | `chapter_id` that does **not** resolve | % dangling |
|---|---:|---:|---:|
| `lms_question_master` | 62,206 | 59,314 | **95.3%** |
| `topic_master` | 13,561 | 13,479 | **99.4%** |
| `content_master` | 31,362 | 31,037 | **99.0%** |
| `lms_lesson_plan` | 1,803 | 1,791 | **99.3%** |
| `lms_concept` | 1,372 | **0** | **0%** |

`lms_concept` is the single exception — every one of its 1,372 rows resolves, and all 1,372 belong to
`sub_institute_id = 1`.

**Why this matters.** Wave 2's gate is *"0 orphan Concept / Chapter / Topic / Content"*. As the data
stands that gate can only pass for tenant 1's 98 chapters and 1,372 concepts. Project the other
tables as specified and roughly 105,000 nodes attach to nothing — which is defect **D9 and D4
recreated at ten times the original scale**, and D9 was measured at only 7,674 dangling refs.

The audit recorded D9 as 7,674 dangling `Question→Chapter` references. The real figure is **59,314**.

**Two readings, same consequence.** Either the old chapter rows were deleted and the children were
left behind, or the legacy content was imported from a prior system whose chapter ids were never
carried across. Nothing in the schema distinguishes these. Either way, 95% of the question bank
cannot currently reach a chapter, and no loader can invent the link.

**This is decision CHAPTER-SOURCE (§4).**

---

### F2 — `(:Question)-[:ASSESSES_CONCEPT]->(:Concept)` cannot be built from the question table

`lms_question_master.concept_id` is empty on **62,162 of 62,209 rows (99.92%)**. Forty-seven questions
have one. `topic_id` is empty on 39,859 (64.1%).

Wave 2 lists `ASSESSES_CONCEPT` as a core edge and Wave 4 depends on it for
`(:Student)-[:MASTERS]->(:Concept)`. Neither can be derived from this column. See F3 for why the
mapping table does not supply it either.

---

### F3 — `lms_question_mapping` is taxonomy tagging, not chapter/concept mapping

The master prompt maps its 516,022 rows to `ASSESSES_CHAPTER` and `ASSESSES_CONCEPT`. It does not
hold either. `mapping_type_id` points into `lms_mapping_type`, which is a vocabulary tree:

```
  9  Depth of Knowledge (Easy, Medium, Hard)     82  Blooms Taxonomy (Creating, Evaluate, Analyse…)
 10  └ Easy      11 └ Medium      12 └ Hard      83  └ Creating   84 └ Evaluate   85 └ Analyse
```

Six mapping types account for **515,391 of 516,022 rows (99.88%)**: ids 82, 9, 123, 112, 155, 105.
These are Bloom's level, difficulty, and four sibling dimensions — question *attributes*, not
curriculum placement.

**Correct projection:** `(:Question)-[:TAGGED_AS {value}]->(:MappingType)`. That is genuinely useful
— it enables "give me Analyse-level questions on this chapter" — but it is not the curriculum link
the plan assumed, and it does not rescue F2.

---

### F4 — `answer_master` holds question options, not student answers

Wave 4 instructs: *"`answer_master` 458,522 → fold into the MASTERS aggregate"*. The table is the
multiple-choice option list:

```
 id | question_id | answer | correct_answer
  1 |           1 | house  | 1
  2 |           1 | big    | 0
  3 |           1 | has    | 0
```

444,838 rows across 114,656 distinct questions — **3.88 options per question**. It contains no
student column. `lms_online_exam_answer.answer_id` points *into* it (13 unmatched in a 20,000-row
sample), which is how a student's chosen option is recorded.

Folding it into student mastery would fabricate mastery data. **Correct projection:** `option_count`
and `correct_answer_id` as properties on `:Question`.

---

### F5 — `fees_breackoff` has no student column; it is a fee schedule, not a ledger

Wave 6 instructs: *"aggregate per (student, syear, head) → `(:Student)-[:LIABLE_FOR]->(:FeeHead)`"*.
The table has no `student_id`:

```
id, syear, admission_year, fee_type_id, quota, grade_id, standard_id,
section_id, month_id, amount, sub_institute_id, created_at, updated_at
```

All 182,333 rows are keyed by **(grade, standard, section, quota, month)** — the fee *structure* a
school publishes, not what any individual owes.

Student-attached fee tables do exist, and they are much smaller:

| Table | Rows | Has `student_id` |
|---|---:|---|
| `fees_breakoff_other` | 46,861 | yes |
| `fees_collect` | **13** | yes |
| `fees_other_collection` | 2,098 | yes |
| `fees_payment` | **0** | yes (column exists, table empty) |

So `LIABLE_FOR` must either be built from `fees_breakoff_other` only, or **derived** by joining a
student's enrolment (standard + quota + section) to the matching schedule rows. The second is a real
computation, not a projection, and it is the kind of derived financial figure the master prompt's own
"graph never answers *how much*" rule exists to prevent.

**This is decision FEES-MODEL (§4).**

---

### F6 — The fee collection ledger is effectively empty in this database

`fees_collect` has **13 rows** against **112 code references**. `fees_payment` has **0 rows** against
**60 code references**. Whatever collects money in production, it is not leaving rows in these tables
in `vivek_erp`.

The master prompt's headline justification for including Fees — *"students with unpaid dues who are
also on transport route 12 and in hostel block A"* — needs a paid/unpaid signal per student. On this
data there is essentially none. The set-intersection query will run and return nothing useful.

---

### F7 — 26 tables are referenced in code but hold zero rows

Not a projection question — a "which reporting path is actually live" question. The master prompt
flags four of these; there are 26.

| Table | Code refs | Rows |
|---|---:|---:|
| `wk_main` | 76 | 0 |
| `fees_payment` | 60 | 0 |
| `result_html` | 39 | 0 |
| `result_activity_marks` | 21 | 0 |
| `result_marks` | 16 | 0 |
| `result_sub_activity` | 16 | 0 |
| `biomatrix` | 15 | 0 |
| `admission_form` | 14 | 0 |
| `photo_video_gallary` | 14 | 0 |
| `recommendations` | 11 | 0 |
| `result_co_scholastic_marks_entries` | 11 | 0 |
| `pal_subjects` | 10 | 0 |

…plus `wk_condition` (10), `wk_module` (8), `gamma_ppt` (7), `implementation_master` (7),
`wk_execute_schedule` (7), `fees_axis` (6), `fees_online_split` (6),
`inventory_item_defective_details` (6), `inventory_item_return_details` (6),
`tbluser_past_education` (6), `wk_mail` (6), `wk_sms` (6), `wk_updatequery` (6),
`master_compliance` (5).

All are classified `EXCLUDE`. The whole `wk_*` workflow engine (8 tables, 0 rows, 76 refs on
`wk_main` alone) appears to be dormant code. **Nothing is silently projected from an empty table.**

---

### F8 — `s_jobrole_skills` joins on name strings, not ids

176,460 rows, and `jobrole` / `skill` are free text:

```
sector       | track     | jobrole                            | skill
Accountancy  | Assurance | Audit Associate / Audit Assistant… | Accounting Standards
```

There is no `jobrole_id` or `skill_id`. Building `(:JobRole)-[:REQUIRES_SKILL]->(:Skill)` requires
matching those strings against `s_jobrole.name` and `master_skills.name` — which is exactly the
name-based MERGE that PROJECTION LAW **L1** forbids and that caused the original graph's tenant
collapse. `sub_institute_id` is also empty on the sampled rows.

**This is decision JOBROLE-KEY (§4).**

---

### F9 — Near-duplicate and case-variant tables

Phase 2 must pick one of each pair, or the registry will produce two labels for one concept.

| Pair | Rows | Note |
|---|---|---|
| `cast` / `caste` | 15 / 16 | Both live, both referenced (q73 / q15) |
| `student_change_req_type` / `STUDENT_CHANGE_REQ_TYPE` | 1 / 6 | Case-variant tables in the same schema |
| `lms_syllabus` / `syllabus` | 2 / 18 | Both referenced |
| `lms_lesson_plan` / `lessonplan` | 1,803 / 39 | q10 vs q41 — the *less* populated one is referenced more |
| `dicipline_master` / `dicipline_dd` | 62 / 42 | Two discipline category tables |
| `tblmenumaster` / `_new` / `_old` / `hp_tblmenumaster` | 501 / 0 / 0 / 153 | All Tier D, no action needed |

---

### F10 — Orphaned sync infrastructure from a previous attempt

| Table | Rows | Code refs |
|---|---:|---:|
| `neo4j_sync_queue` | 12,193 | **0** |
| `sync_log` | 9,240 | **0** |
| `lms_data_content_neo4j` | 13,693 | 2 |
| `lms_data_neo4j` | 4,131 | **0** |
| `lms_data_question_neo4j` | 0 | 0 |

A queue table holding 12,193 undrained rows that no code reads is worth knowing about before Phase 15
builds a new outbox. All are `EXCLUDE`; none should be re-imported. Whether the 12,193 queued items
represent lost writes is a question for the owner — it does not block the rebuild.

---

### F11 — The assessment and result data is pilot-scale, not production-scale

The row counts imply a large working system. The distinct-entity counts do not.

| Ledger | Rows | Distinct students | Tenants |
|---|---:|---:|---:|
| `lms_online_exam_answer` | 2,418,015 | **1,326** | n/a (no tenant column) |
| `lms_online_exam` | 147,875 | **1,326** | n/a |
| `result_personalize_marks` | 1,308,379 | **443** | **4** |

Against 83,715 students in 48 tenants. `result_personalize_marks` averages ~2,950 rows per student.

This is good news for graph size and important for expectations: the mastery and scoring layer will
cover **~1.6% of the student body**. Any demo of "student mastery" runs on 1,326 students, and the
learning-outcome module (11/15/14 rows) is a structural pilot, as the master prompt already says.

---

### F12 — Tenancy has to be derived for 125 non-empty tables

334 of 488 tables carry a `sub_institute_id` variant; 165 carry both it and a year column. **125
non-empty tables carry neither**, and they include the highest-volume ones in the plan:

| Table | Rows | Derivation path | Orphans |
|---|---:|---|---:|
| `lms_online_exam_answer` | 2,418,015 | → `online_exam_id` → `question_paper` | — |
| `lms_question_mapping` | 516,022 | → `questionmaster_id` → `lms_question_master` | — |
| `lms_online_exam` | 147,875 | → `question_paper_id` → `question_paper` | **21** |
| `lms_online_exam` | 147,875 | → `student_id` → `tblstudent` | **17** |
| `lms_units` | 60 | → `curriculum_id` → `lms_curriculum` | — |
| `content_mapping_type` | 1,909 | → `content_id` → `content_master` | — |
| `lms_learning_outcomes` | 259 | → `chapter_id` / `subject_id` | — |

`question_paper` is the right anchor for the assessment chain: it carries **both** `sub_institute_id`
and `syear`, and only 21 of 147,875 exams fail to reach one. The registry must record a derivation
path per table, and the loader must reject rows that cannot resolve a tenant rather than defaulting
them — L2 says *no node without a tenant*.

**Three spelling variants exist** and the loader must handle all of them:

| Spelling | Tables | Examples |
|---|---:|---|
| `sub_institute_id` | 314 | the norm |
| `SUB_INSTITUTE_ID` | 18 | `complaint`, `front_desk`, `fees_receipt`, `inventory_*` |
| `SubInstituteId` | 2 | `result_exam_master`, `result_exam_type_master` (PK is also `Id`) |

Year columns vary too: `syear` (167), `SYEAR` (21), `academic_year` (`library_books`),
`academic_year_id` (`period`).

---

## 4. New blocking decisions

Added to STATUS §3. Four are new; one existing decision now has evidence.

| ID | Question | Blocks | Recommendation |
|---|---|---|---|
| **CHAPTER-SOURCE** | 95–99% of questions, topics, content and lesson plans reference chapter ids absent from `chapter_master` (F1). Restore the missing chapters, re-map the children, or load only what resolves? | **Phase 5, 6** | Load only what resolves, and record the dropped counts per table in the load log. Do not invent chapters. Restoring them is a data-recovery project, not a migration step |
| **CONCEPT-LINK** | `Question→Concept` has no source: `concept_id` is 99.92% empty (F2) and the mapping table is taxonomy (F3). How does `MASTERS` reach `:Concept`? | **Phase 8** | Aggregate `MASTERS` to `:Chapter` for now (24,040 edges, buildable today) and treat `Question→Concept` as authored intelligence alongside `PREREQUISITE_OF` and `BUILDS` |
| **FEES-MODEL** | `fees_breackoff` is a schedule, not a student ledger (F5), and the collection tables are near-empty (F6). What does `LIABLE_FOR` mean? | **Phase 11** | Project `fees_breakoff_other` only (46,861 rows, has `student_id`). Do **not** derive per-student liability by joining schedule to enrolment — that computes a financial figure in the graph, which L7 forbids |
| **JOBROLE-KEY** | `s_jobrole_skills` joins on name strings (F8). Name-based MERGE violates L1. | **Phase 13** | Resolve names to ids in SQL during export, drop rows that do not resolve, and log the count. If the match rate is poor, defer the whole skills module |
| **TENANT-SCOPE** | *(existing, still open)* All 56 tenants or institute 1 only? | Phase 5 | **Now evidenced:** all 1,372 `lms_concept` rows and 98 of 99 chapters are tenant 1. The curriculum spine *is* single-tenant. Student, fees, transport and HR are genuinely multi-tenant (48 tenants). Recommend: full tenancy for people/ops, tenant 1 only for the curriculum spine, and say so explicitly in the model doc |

---

## 5. Tables needing a human call

17 tables are classified `REVIEW` — I can describe them but not decide them.

| Table | Rows | Refs | Question |
|---|---:|---:|---|
| `onet_institute_data` | 65,738 | 5 | Indian AICTE college listings. Not occupation data and not K12. Does the careers module need it? |
| `chapter_topic_master` | 330 | **0** | Chapter ids sit in the dead id space (F1). Gen-1 residue, or still wanted? |
| `general_data` | 189 | 40 | Generic name, heavily referenced. What does it hold? |
| `semantic_intelligence` | 88 | 16 | Unclear role in the curriculum pipeline |
| `contents` | 48 | 31 | Distinct from `content_master` — which is authoritative? |
| `lessonplan` | 39 | 41 | More referenced than `lms_lesson_plan` but 46× smaller (F9) |
| `attendance_student` | 18 | 26 | 18 rows, well referenced — live feature or abandoned? |
| `lessonplan_execution` | 5 | 5 | Follows whatever `lessonplan` resolves to |
| `pal_*` (9 tables) | 1–18 each | 4–12 | PAL stubs holding 1–18 rows. Schema exists, feature is not in production. Project the shape or wait? |

The remaining 17 `pal_*` tables are empty and classified `EXCLUDE`; if PAL ships, they move to Tier B
together.

---
## 6. Per-module summary

| Module | Phase | Tables | Source rows | NODE | EDGE | AGG | PROP | EXCL | REVIEW |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Assessment | 6 | 16 | 3,595,241 | 6 | 2 | 3 | 2 | 3 | 0 |
| Result | 9 | 30 | 1,399,568 | 11 | 2 | 2 | 4 | 11 | 0 |
| ONET | 13 | 43 | 1,266,180 | 15 | 7 | 6 | 0 | 14 | 1 |
| Student | 7 | 35 | 789,863 | 6 | 6 | 4 | 5 | 13 | 1 |
| Staff | 10 | 21 | 407,038 | 4 | 6 | 1 | 4 | 6 | 0 |
| Skill | 13 | 9 | 376,692 | 4 | 4 | 0 | 0 | 1 | 0 |
| Fees | 11 | 41 | 265,022 | 12 | 1 | 7 | 3 | 18 | 0 |
| Curriculum | 5 | 45 | 153,527 | 26 | 5 | 0 | 3 | 6 | 5 |
| Platform | 14 | 59 | 142,301 | 1 | 1 | 0 | 0 | 57 | 0 |
| Library | 12 | 3 | 139,331 | 1 | 1 | 1 | 0 | 0 | 0 |
| NotK12 | — | 8 | 121,141 | 0 | 0 | 0 | 0 | 8 | 0 |
| Communication | 14 | 12 | 116,643 | 0 | 0 | 5 | 0 | 7 | 0 |
| Timetable | 10 | 3 | 102,706 | 0 | 1 | 0 | 0 | 2 | 0 |
| Transport | 12 | 10 | 39,949 | 6 | 3 | 0 | 1 | 0 | 0 |
| Inventory | 12 | 19 | 33,707 | 5 | 5 | 0 | 1 | 8 | 0 |
| Foundation | 4 | 38 | 22,204 | 23 | 3 | 0 | 5 | 6 | 1 |
| Leave | 10 | 1 | 20,396 | 0 | 1 | 0 | 0 | 0 | 0 |
| Payroll | 10 | 5 | 13,011 | 2 | 1 | 2 | 0 | 0 | 0 |
| Operations | 12 | 5 | 6,129 | 4 | 0 | 0 | 1 | 0 | 0 |
| Calendar | 14 | 1 | 3,941 | 1 | 0 | 0 | 0 | 0 | 0 |
| SQAA | 13 | 4 | 1,873 | 2 | 2 | 0 | 0 | 0 | 0 |
| Admission | 7 | 7 | 1,221 | 5 | 0 | 0 | 0 | 2 | 0 |
| PTM | 14 | 2 | 941 | 1 | 1 | 0 | 0 | 0 | 0 |
| Visitor | 12 | 3 | 897 | 2 | 0 | 0 | 0 | 1 | 0 |
| LearningOutcome | 9 | 8 | 220 | 5 | 2 | 0 | 0 | 1 | 0 |
| Counselling | 6 | 6 | 149 | 3 | 1 | 1 | 1 | 0 | 0 |
| Hostel | 12 | 7 | 129 | 5 | 2 | 0 | 0 | 0 | 0 |
| Report | — | 4 | 80 | 0 | 0 | 0 | 0 | 4 | 0 |
| FrontDesk | 12 | 7 | 79 | 5 | 0 | 0 | 1 | 1 | 0 |
| PAL | 14 | 26 | 33 | 0 | 0 | 0 | 0 | 17 | 9 |
| Leaderboard | 14 | 2 | 7 | 1 | 0 | 1 | 0 | 0 | 0 |
| Workflow | — | 8 | 0 | 0 | 0 | 0 | 0 | 8 | 0 |

---

## 7. The full register — all 488 tables

Grouped by module, ordered by load phase. **T** = tenancy columns present: `S` = a
`sub_institute_id` variant, `Y` = a `syear`/academic-year variant, `—` = neither (tenancy must be
derived through a foreign key). **Refs** = quoted code references.

### Foundation  ·  phase 4  ·  38 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `sub_std_map` | 6,656 | 109 | S | A | `EDGE` | (:Standard)-[:HAS_SUBJECT]->(:Subject) |  |
| `batch` | 3,000 | 79 | SY | A | `NODE` | :Batch |  |
| `period_details` | 2,034 | 16 | S | B | `PROP` | on :Period |  |
| `subject` | 2,025 | 807 | S | A | `NODE` | :Subject |  |
| `std_div_map` | 1,966 | 28 | S | A | `EDGE` | (:Standard)-[:HAS_DIVISION]->(:Division) | NOT in the master prompt — this is how Standard reaches Division |
| `standard` | 1,000 | 1454 | S | A | `NODE` | :Standard |  |
| `student_quota` | 873 | 125 | S | B | `NODE` | :Quota | q125 — heavily used; also the join key for the fee schedule |
| `division` | 655 | 583 | S | A | `NODE` | :Division | `section_id` in enrollment refers here |
| `tblcity` | 609 | 3 | — | C | `NODE` | :City | Geography dimension |
| `tbluserprofilemaster` | 596 | 109 | S | A | `NODE` | :Role |  |
| `academic_year` | 522 | 227 | SY | A | `NODE` | :AcademicYear | Referenced 105× in code; every time-scoped edge hangs off it |
| `division_capacity_master` | 496 | 19 | SY | B | `PROP` | capacity on :Division |  |
| `period` | 307 | 66 | SY | B | `NODE` | :Period | Uses `academic_year_id`, not syear |
| `academic_section` | 237 | 142 | S | A | `NODE` | :AcademicSection |  |
| `general_data` | 189 | 40 | S | C | `REVIEW` | — | q40, 189 rows, generic name — needs a human to say what it holds |
| `tblclient` | 148 | 34 | — | B | `NODE` | :Client | Parent org above Institute |
| `hrms_departments` | 113 | 41 | S | A | `NODE` | :Department |  |
| `place_master` | 112 | 14 | S | C | `NODE` | :Place |  |
| `school_detail` | 95 | 9 | S | B | `PROP` | on :Institute |  |
| `subject_elective` | 74 | 16 | SY | B | `EDGE` | (:Standard)-[:HAS_ELECTIVE]->(:Subject) |  |
| `template_master` | 72 | 18 | S | D | `EXCLUDE` | — | Print templates |
| `school_setup` | 56 | 78 | Y | A | `NODE` | :Institute | PK `Id`; no sub_institute_id — the PK *is* the tenant. uid Institute:{Id}:0:{Id} |
| `master_setup_select` | 54 | 18 | S | C | `PROP` | lookup |  |
| `subject_optional_type` | 49 | 9 | SY | B | `NODE` | :OptionalSubjectType |  |
| `house_master` | 43 | 22 | SY | B | `NODE` | :House |  |
| `tblstate` | 36 | 3 | — | C | `NODE` | :State | Geography dimension |
| `grade_master` | 32 | 31 | S | B | `NODE` | :GradeScheme |  |
| `master_fields_institute` | 28 | 4 | S | D | `EXCLUDE` | — | Form-builder metadata |
| `master_fields` | 26 | 10 | — | D | `EXCLUDE` | — | Form-builder metadata |
| `erp_status` | 19 | 11 | — | D | `EXCLUDE` | — | Status lookup |
| `caste` | 16 | 15 | — | C | `NODE` | :Caste | Duplicate of `cast`; pick one at Phase 2 |
| `cast` | 15 | 73 | S | C | `NODE` | :Caste | `cast` and `caste` are two live tables — see finding F9 |
| `religion` | 14 | 72 | — | C | `NODE` | :Religion | Demographic dimension |
| `institute_detail` | 10 | 6 | S | B | `PROP` | on :Institute |  |
| `school_sections` | 10 | 14 | SY | A | `NODE` | :SchoolSection | Tenant is `school_id`, not the NULL sub_institute_id column (SCHOOL-SECTION-TENANCY 2026-08-11) |
| `blood_group` | 9 | 45 | — | C | `NODE` | :BloodGroup |  |
| `enrollment_prefix_master` | 6 | 16 | S | D | `EXCLUDE` | — | Numbering config, no traversal value |
| `master_fields_table` | 2 | 5 | S | D | `EXCLUDE` | — | Form-builder metadata |

### Curriculum  ·  phase 5  ·  45 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `lms_mapping_type` | 71,532 | 154 | — | A | `NODE` | :MappingType + [:PARENT_OF] | Self-referencing taxonomy tree (Blooms, DoK). `type` empty on 99.8% |
| `content_master` | 31,362 | 104 | SY | A | `NODE` | :Content | 99.0% of chapter_id values dangle — see F1 |
| `lms_data_content_neo4j` | 13,693 | 2 | S | D | `EXCLUDE` | — | **Old migration staging table** (13,693 rows, q2). Do not re-import |
| `topic_master` | 13,561 | 31 | SY | A | `NODE` | :Topic | 99.4% of chapter_id values dangle — see F1 |
| `classwork_attachment` | 5,401 | 3 | SY | B | `PROP` | attachment_count on :Lesson | 5,401 rows — file metadata only |
| `lms_data_neo4j` | 4,131 | 0 | S | D | `EXCLUDE` | — | Old migration staging (4,131 rows, q0) |
| `lms_lessonplan_dayswise` | 3,337 | 3 | S | B | `EDGE` | (:Lesson)-[:SCHEDULED_ON]->(:AcademicYear) |  |
| `lms_teacher_resource` | 2,606 | 19 | SY | B | `NODE` | :TeacherResource |  |
| `content_mapping_type` | 1,909 | 11 | — | A | `EDGE` | (:Content)-[:TAGGED_AS]->(:MappingType) | No tenancy — derive via content_id |
| `lms_lesson_plan` | 1,803 | 10 | SY | A | `NODE` | :Lesson | 1,803 rows; 99.3% chapter_id dangling |
| `lms_concept` | 1,372 | 45 | SY | A | `NODE` | :Concept | 1,372 rows, **100% tenant 1**, 0 dangling chapter_id. The one clean spine |
| `book_list` | 559 | 11 | SY | B | `NODE` | :TextBook |  |
| `lms_lesson_plan_concepts` | 537 | 6 | — | A | `EDGE` | (:Lesson)-[:COVERS]->(:Concept) | Joins via lms_lesson_plan_periods_id, not lesson_id |
| `chapter_topic_master` | 330 | 0 | — | B | `REVIEW` | — | 330 rows, **0 code references**, chapter_ids in the dead id space. Probably Gen-1 residue |
| `lms_lesson_plan_periods` | 329 | 9 | — | B | `NODE` | :LessonPeriod |  |
| `lms_learning_outcomes` | 259 | 7 | — | A | `NODE` | :LearningObjective | No tenancy column — derive via subject_id (LO-TENANCY 2026-08-11; chapter_id path kept 0 of 259) |
| `document_extractions` | 108 | 4 | SY | A | `NODE` | :Extraction | The provenance of the Gen-2 chapters — 108 rows, 98 used by chapter_master |
| `chapter_master` | 99 | 94 | SY | A | `NODE` | :Chapter | **99 rows only, ids 1012-8677.** Gen-2 extraction pipeline. See finding F1 |
| `homework` | 94 | 98 | SY | B | `NODE` | :Homework | q98 |
| `semantic_intelligence` | 88 | 16 | S | B | `REVIEW` | — | 88 rows, q16 — unclear role |
| `h5p_scenario_points` | 60 | 3 | S | B | `PROP` | on :H5PScenario |  |
| `lms_units` | 60 | 9 | — | A | `NODE` | :Unit | No tenancy column — derive via curriculum_id |
| `contents` | 48 | 31 | S | B | `REVIEW` | — | 48 rows, q31 — distinct from content_master; needs a human call |
| `lms_assignment` | 44 | 6 | SY | B | `NODE` | :Assignment |  |
| `lessonplan` | 39 | 41 | SY | B | `REVIEW` | — | 39 rows, q41 — legacy sibling of lms_lesson_plan |
| `lms_virtual_classroom` | 25 | 10 | SY | B | `NODE` | :VirtualClassroom |  |
| `lms_doubt_conversation` | 24 | 8 | SY | B | `EDGE` | (:Doubt)-[:HAS_REPLY]->(:Doubt) |  |
| `lms_content_category` | 21 | 7 | S | B | `NODE` | :ContentCategory | Global reference data — all 21 rows carry sub_institute_id 0 (CATEGORY-SCOPE 2026-08-11) |
| `syllabus` | 18 | 14 | SY | B | `NODE` | :Syllabus | Second syllabus table — reconcile with lms_syllabus at Phase 2 |
| `lms_flashcard` | 12 | 10 | SY | B | `NODE` | :Flashcard |  |
| `h5p_scenarios` | 11 | 3 | S | B | `NODE` | :H5PScenario |  |
| `lms_curriculum` | 10 | 25 | SY | A | `NODE` | :Curriculum |  |
| `suggested_content` | 10 | 59 | SY | B | `EDGE` | (:Content)-[:SUGGESTED_FOR]->(:Concept) |  |
| `lms_portfolio` | 9 | 3 | SY | B | `NODE` | :Portfolio |  |
| `knowledge_base` | 8 | 10 | — | B | `NODE` | :KnowledgeBase |  |
| `lessonplan_execution` | 5 | 5 | SY | B | `REVIEW` | — | 5 rows |
| `lms_intelligence_lesson_plans` | 4 | 5 | SY | B | `NODE` | :Lesson | AI-generated variant; 4 rows |
| `lms_doubt` | 3 | 6 | SY | B | `NODE` | :Doubt |  |
| `knowledge_base_detail` | 2 | 5 | — | B | `PROP` | on :KnowledgeBase |  |
| `lms_syllabus` | 2 | 6 | S | B | `NODE` | :Syllabus |  |
| `pal_misconceptions` | 2 | 10 | — | A | `NODE` | :Misconception | **2 rows.** The named source for (:Misconception) in Wave 2 — the shape is buildable but carries almost no data |
| `gamma_ppt` | 0 | 7 | SY | D | `EXCLUDE` | — | Empty |
| `h5p_interactive_video` | 0 | 4 | SY | D | `EXCLUDE` | — | Empty |
| `h5p_video_interactions` | 0 | 3 | — | D | `EXCLUDE` | — | Empty |
| `lms_data_question_neo4j` | 0 | 0 | S | D | `EXCLUDE` | — | Old migration staging, empty |

### Assessment  ·  phase 6  ·  16 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `lms_online_exam_answer` | 2,418,015 | 28 | — | A | `AGG_EDGE` | (:Student)-[:MASTERS]->(:Chapter) | **2,418,015 rows → ~24k-58k edges.** Cannot target :Concept — see F2 |
| `lms_question_mapping` | 516,022 | 15 | — | A | `EDGE` | (:Question)-[:TAGGED_AS]->(:MappingType) | **NOT chapter/concept mapping** — it is Blooms + Depth-of-Knowledge tagging. See F3 |
| `answer_master` | 444,838 | 29 | S | A | `PROP` | option_count + correct_answer_id on :Question | **Question OPTIONS, not student answers** (3.88 per question). Master prompt misread this. See F4 |
| `lms_online_exam` | 147,875 | 28 | — | A | `NODE` | :Result | 147,875 rows but only **1,326 distinct students**. No tenancy column — derive via question_paper_id (21 orphans) |
| `lms_question_master` | 62,209 | 76 | S | A | `NODE` | :Question | **concept_id empty on 99.92%** (62,162/62,209) — ASSESSES_CONCEPT cannot be built from here. See F2 |
| `question_paper` | 5,431 | 56 | SY | A | `NODE` | :Assessment | Has both sub_institute_id and syear — the tenancy anchor for lms_online_exam |
| `lms_online_exam_answer_student` | 723 | 5 | — | B | `AGG_EDGE` | (:Student)-[:MASTERS]->(:Chapter) | 723 rows. Aggregates to :Chapter per CONCEPT-LINK |
| `lms_online_exam_student` | 78 | 4 | — | B | `EDGE` | (:Student)-[:HAS_RESULT]->(:Result) | 78 rows |
| `MBTI_answer` | 16 | 3 | — | B | `PROP` | answer key on :Assessment | **16 rows; columns are only (id, ans_key, answer_html)** — no student and no FK to MBTI_paper, so it cannot be a mastery edge |
| `10_05_question_master` | 9 | 0 | S | D | `EXCLUDE` | — | 9 rows, 0 code refs — dated backup table |
| `question_type_master` | 8 | 13 | SY | B | `NODE` | :QuestionType |  |
| `old_question_category_master` | 6 | 2 | — | D | `EXCLUDE` | — | Legacy, superseded by lms_mapping_type |
| `lms_offline_exam_answer` | 5 | 7 | — | B | `AGG_EDGE` | (:Student)-[:MASTERS]->(:Chapter) | 5 rows. Aggregates to :Chapter per CONCEPT-LINK |
| `old_question_level_master` | 3 | 2 | — | D | `EXCLUDE` | — | Legacy |
| `lms_offline_exam` | 2 | 6 | SY | B | `NODE` | :Result | 2 rows |
| `MBTI_paper` | 1 | 3 | S | B | `NODE` | :Assessment | 1 row — MBTI pilot |

### Counselling  ·  phase 6  ·  6 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `counselling_online_exam_answer` | 45 | 6 | — | B | `AGG_EDGE` | (:Student)-[:MASTERS]->(:Chapter) | 45 rows. Aggregates to :Chapter per CONCEPT-LINK |
| `counselling_answer_master` | 40 | 7 | S | B | `PROP` | on :Question |  |
| `counselling_online_exam` | 35 | 6 | S | B | `NODE` | :Result | 35 rows |
| `counselling_question_mapping` | 13 | 3 | — | B | `EDGE` | (:Question)-[:TAGGED_AS]->(:MappingType) |  |
| `counselling_question_master` | 11 | 6 | S | B | `NODE` | :Question | Separate counselling question bank |
| `counselling_course` | 5 | 7 | S | B | `NODE` | :Course |  |

### Admission  ·  phase 7  ·  7 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `admission_registration_v1` | 836 | 8 | S | B | `NODE` | :Applicant | 836 rows |
| `new_admission_inquiry_registration` | 356 | 21 | SY | B | `NODE` | :Enquiry | 356 rows |
| `admission_enquiry` | 12 | 82 | SY | B | `NODE` | :Enquiry | 12 rows, q82 |
| `admission_age_validation` | 6 | 6 | SY | D | `EXCLUDE` | — | Validation config |
| `admission_category_master` | 6 | 12 | S | B | `NODE` | :AdmissionCategory |  |
| `admission_registration` | 5 | 25 | S | B | `NODE` | :Applicant | 5 rows — legacy v0 |
| `admission_form` | 0 | 14 | S | D | `EXCLUDE` | — | Empty |

### Student  ·  phase 7  ·  35 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `tblstudent_enrollment` | 176,305 | 178 | SY | A | `EDGE` | (:Student)-[:ENROLLED_IN]->(:Standard) + [:IN_DIVISION]->(:Division) | `section_id` is the division FK |
| `tblstudent_payment_method_mapping` | 175,268 | 5 | SY | D | `EXCLUDE` | — | 175,268-row ledger, q5. Payment-method history — MariaDB answers this |
| `student_optional_subject` | 103,678 | 48 | SY | A | `EDGE` | (:Student)-[:STUDIES]->(:Subject) |  |
| `tblstudent` | 83,715 | 228 | S | A | `NODE` | :Student | 83,715 rows across 48 tenants. **PII** — fold all demographics here; do NOT create :StuDetail (defect D5) |
| `tblstudent_document` | 66,492 | 11 | S | B | `PROP` | document_count on :Student | 66,492 rows of file metadata — projecting as nodes duplicates PII for zero traversal value |
| `result_student_attendance_master` | 53,032 | 11 | SY | A | `AGG_EDGE` | (:Student)-[:ATTENDANCE]->(:AcademicYear) | Aggregate per (student, syear, **term_id**) — there is no month column |
| `student_infirmary` | 21,373 | 13 | SY | B | `AGG_EDGE` | (:Student)-[:INFIRMARY_VISITS]->(:AcademicYear) | 21,373-row ledger. **Sensitive PII** |
| `tblstudent_doc_std_mapping` | 19,645 | 10 | S | D | `EXCLUDE` | — | Document/standard join, metadata only |
| `dicipline` | 19,642 | 25 | SY | B | `EDGE` | (:Student)-[:HAS_INCIDENT]->(:DisciplineCategory) | 19,642 rows. Master prompt said Student→Student — that is wrong, the target is a category |
| `tblstudent_fees_failure` | 16,786 | 4 | SY | D | `EXCLUDE` | — | 16,786-row failure log |
| `student_anacdotal` | 11,749 | 2 | SY | B | `AGG_EDGE` | (:Student)-[:ANECDOTAL_NOTES]->(:AcademicYear) | 11,749-row ledger |
| `tblstudent_family_history` | 10,279 | 10 | S | B | `NODE` | :Guardian | 10,279 rows — parent/guardian records. **PII** |
| `student_height_weight` | 9,415 | 21 | SY | B | `AGG_EDGE` | (:Student)-[:BIOMETRIC]->(:AcademicYear) | 9,415-row ledger — aggregate latest per year |
| `tblstudent_tc_details` | 7,470 | 6 | SY | B | `PROP` | transfer-certificate fields on :Student |  |
| `tblstudent_past_education` | 5,448 | 10 | S | B | `PROP` | on :Student |  |
| `tblstudent_bank_detail` | 4,933 | 14 | S | D | `EXCLUDE` | — | Bank PII, no traversal value |
| `class_teacher` | 2,924 | 35 | SY | A | `EDGE` | (:Staff)-[:CLASS_TEACHER_OF]->(:Division) | 2,924 rows |
| `temp_signup` | 612 | 5 | Y | D | `EXCLUDE` | — | Signup staging |
| `tblstudent_bank_detail_log` | 417 | 3 | S | D | `EXCLUDE` | — | Audit log |
| `student_capture_photos` | 190 | 13 | SY | D | `EXCLUDE` | — | Photo metadata |
| `student_capture_attendance` | 130 | 8 | SY | B | `EXCLUDE` | — | 130 rows, photo-capture log |
| `student_health` | 94 | 19 | SY | B | `PROP` | on :Student | **Sensitive PII** |
| `dicipline_master` | 62 | 3 | S | B | `NODE` | :DisciplineCategory |  |
| `student_document_type` | 62 | 6 | — | B | `NODE` | :DocumentType |  |
| `dicipline_dd` | 42 | 7 | S | B | `NODE` | :DisciplineDropdown | 42 rows. **Split from :DisciplineCategory 2026-08-10** — 16 (tenant,pk) pairs collide with dicipline_master, so sharing the label silently lost rows |
| `attendance_json_result` | 30 | 3 | SY | D | `EXCLUDE` | — | JSON cache |
| `attendance_student` | 18 | 26 | SY | B | `REVIEW` | — | 18 rows, q26 |
| `consent_master` | 18 | 8 | SY | B | `NODE` | :ConsentType |  |
| `student_change_request` | 10 | 4 | SY | D | `EXCLUDE` | — | Workflow log |
| `student_vaccination` | 8 | 13 | SY | B | `PROP` | on :Student | **Sensitive PII**, 8 rows |
| `STUDENT_CHANGE_REQ_TYPE` | 6 | 3 | SY | D | `EXCLUDE` | — | Case-variant duplicate of student_change_req_type — see F9 |
| `tblstudent_parent_feedback` | 6 | 3 | S | B | `EDGE` | (:Guardian)-[:GAVE_FEEDBACK]->(:Institute) | 6 rows |
| `tblstudent_siblings` | 2 | 13 | S | A | `EDGE` | (:Student)-[:SIBLING_OF]->(:Student) | 2 rows |
| `certificate_history` | 1 | 10 | SY | D | `EXCLUDE` | — | Issue log |
| `student_change_req_type` | 1 | 3 | SY | D | `EXCLUDE` | — | Lookup |

### LearningOutcome  ·  phase 9  ·  8 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `learning_outcome_pdf` | 151 | 37 | — | D | `EXCLUDE` | — | Generated PDFs |
| `lo_master` | 23 | 6 | SY | A | `NODE` | :LOCategory |  |
| `learning_outcome_question_master` | 15 | 12 | Y | A | `EDGE` | (:LearningOutcome)-[:ASSESSED_BY]->(:Question) | 15 rows |
| `learning_outcome_student_marks` | 14 | 25 | SY | A | `EDGE` | (:Student)-[:ACHIEVED]->(:LearningOutcome) | 14 rows |
| `learning_outcome_indicator` | 11 | 46 | — | C | `NODE` | :LOIndicatorRef | 11 rows — structural pilot. ORPHAN-LABELS 2026-08-11: split from :LearningOutcome and demoted to C. Its STANDARD/SUBJECT columns hold names, not ids, so no parent FK is resolvable; as Tier A it hard-failed G8 with no available remedy |
| `learning_outcome_exam_type_master` | 4 | 7 | — | C | `NODE` | :ExamTypeRef | 4 rows, columns are ID + EXAM_TYPE only. ORPHAN-LABELS 2026-08-11: split from :ExamType — that label is Tier A via result_exam_master, so these 4 parentless reference rows hard-failed G8 despite having no FK to attach to |
| `lo_category` | 1 | 6 | SY | A | `NODE` | :LOCategoryAlt | 1 row. **Split from :LOCategory 2026-08-10** — its single (tenant,pk) collides with lo_master |
| `lo_indicator` | 1 | 4 | SY | A | `NODE` | :LearningOutcome | 1 row |

### Result  ·  phase 9  ·  30 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `result_personalize_marks` | 1,308,379 | 14 | SY | A | `AGG_EDGE` | (:Student)-[:SCORED]->(:Exam) | **1,308,379 rows → 54,712 edges** (measured). Only 4 tenants / 443 students |
| `result_create_exam` | 33,759 | 28 | SY | A | `NODE` | :Exam | 33,759 rows |
| `result_reportcard_marks` | 20,865 | 9 | SY | B | `AGG_EDGE` | (:Student)-[:REPORTCARD]->(:Exam) | 20,865 rows |
| `result_remarks` | 10,936 | 44 | SY | A | `PROP` | remark on the :SCORED edge |  |
| `result_exam_approve` | 6,125 | 12 | S | B | `PROP` | approval status on :Exam | 6,125 rows — workflow state |
| `exam_schedule` | 5,215 | 16 | SY | B | `NODE` | :ExamSchedule | 5,215 rows |
| `result_activity_master` | 4,664 | 19 | S | B | `NODE` | :Activity | 4,664 rows |
| `upload_result` | 1,948 | 11 | SY | D | `EXCLUDE` | — | Import staging log |
| `result_co_scholastic_grades` | 1,880 | 14 | S | B | `EDGE` | (:Student)-[:CO_SCHOLASTIC_GRADE]->(:CoScholasticArea) |  |
| `result_co_scholastic` | 1,353 | 31 | S | B | `NODE` | :CoScholasticArea |  |
| `result_exam_master` | 1,227 | 26 | S | A | `NODE` | :ExamType | Uses **`SubInstituteId`** (PascalCase) and PK `Id` — loader must special-case |
| `result_working_day_master` | 507 | 8 | SY | B | `PROP` | working days on :AcademicYear |  |
| `result_book_master` | 479 | 11 | S | D | `EXCLUDE` | — | Report-book config |
| `result_skillset` | 460 | 41 | S | B | `NODE` | :Skillset | q41 |
| `result_remark_masters` | 398 | 4 | SY | C | `NODE` | :RemarkTemplate |  |
| `grade_master_data` | 394 | 20 | SY | A | `NODE` | :Grade |  |
| `result_master_confrigration` | 392 | 13 | SY | D | `EXCLUDE` | — | Report config (note the typo) |
| `result_std_grd_maping` | 286 | 9 | S | B | `EDGE` | (:Standard)-[:USES_GRADE_SCHEME]->(:GradeScheme) |  |
| `result_trust_master` | 88 | 8 | SY | D | `EXCLUDE` | — | Print header config |
| `result_template_master` | 62 | 5 | S | D | `EXCLUDE` | — | Print templates |
| `result_exam_type_master` | 56 | 4 | S | B | `NODE` | :ExamTypeCategory | Also PascalCase `SubInstituteId` |
| `result_activity_group` | 38 | 6 | S | B | `NODE` | :ActivityGroup |  |
| `result_co_scholatic_range` | 34 | 14 | SY | B | `PROP` | grade band | Note the typo in the table name |
| `result_co_scholastic_parent` | 23 | 12 | S | B | `NODE` | :CoScholasticArea | Parent category |
| `result_activity_marks` | 0 | 21 | SY | D | `EXCLUDE` | — | **0 rows but 21 code refs** — see F7 |
| `result_co_scholastic_marks_entries` | 0 | 11 | SY | D | `EXCLUDE` | — | **0 rows but 11 code refs** — see F7 |
| `result_html` | 0 | 39 | SY | D | `EXCLUDE` | — | **0 rows but 39 code refs** — rendered HTML cache. See F7 |
| `result_marks` | 0 | 16 | S | D | `EXCLUDE` | — | **0 rows but 16 code refs** — see F7 |
| `result_oldyear_marks` | 0 | 0 | S | D | `EXCLUDE` | — | 0 rows, 0 refs |
| `result_sub_activity` | 0 | 16 | S | D | `EXCLUDE` | — | **0 rows but 16 code refs** — see F7 |

### Leave  ·  phase 10  ·  1 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `leave_applications` | 20,396 | 20 | SY | B | `EDGE` | (:Staff)-[:APPLIED_FOR_LEAVE]->(:LeaveType) | **20,396 rows — not in the master prompt.** Separate leave module |

### Payroll  ·  phase 10  ·  5 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `hrms_emp_payroll_deduction` | 7,562 | 9 | S | B | `AGG_EDGE` | (:Staff)-[:DEDUCTION]->(:PayrollType) | **authoritative=false.** 7,562 rows — no amounts as node properties |
| `employee_monthly_salary_data` | 4,263 | 6 | S | B | `AGG_EDGE` | (:Staff)-[:PAYROLL_MONTH]->(:AcademicYear) | **authoritative=false.** 4,263 rows |
| `employee_salary_structures` | 1,128 | 6 | S | B | `EDGE` | (:Staff)-[:HAS_SALARY_STRUCTURE]->(:PayrollType) | **authoritative=false.** Structure/eligibility only |
| `payroll_types` | 46 | 24 | S | B | `NODE` | :PayrollType | **authoritative=false** |
| `hrms_salary_certificate` | 12 | 6 | S | B | `NODE` | :SalaryCertificate | **authoritative=false.** 12 rows |

### Staff  ·  phase 10  ·  21 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `hrms_attendances` | 354,874 | 21 | S | B | `AGG_EDGE` | (:Staff)-[:ATTENDANCE]->(:AcademicYear) | **354,874 rows → ~monthly aggregate.** Date column is `day`; no syear — derive |
| `hrms_emp_leaves` | 28,406 | 13 | S | B | `EDGE` | (:Staff)-[:TOOK_LEAVE]->(:LeaveType) | 28,406 rows |
| `staff_document` | 11,282 | 12 | S | B | `PROP` | document_count on :Staff | 11,282 rows of file metadata |
| `proxy_master` | 6,474 | 7 | SY | B | `EDGE` | (:Staff)-[:SUBSTITUTED_FOR]->(:Staff) | 6,474 rows — substitute teaching |
| `tbluser` | 4,763 | 184 | S | A | `NODE` | :Staff | 4,763 rows. **PII** |
| `hrms_leave_allocation` | 877 | 24 | S | B | `EDGE` | (:Staff)-[:ALLOCATED]->(:LeaveType) |  |
| `hrms_holidays` | 147 | 10 | S | B | `NODE` | :Holiday |  |
| `tblemp_skills` | 96 | 6 | S | A | `EDGE` | (:Staff)-[:HAS_SKILL]->(:Skill) | 96 rows — the only staff→skill link |
| `hrms_departments_mapping` | 53 | 2 | S | B | `EDGE` | (:Staff)-[:IN_DEPARTMENT]->(:Department) |  |
| `hrms_leave_types` | 19 | 11 | S | B | `NODE` | :LeaveType |  |
| `hrms_in_out_times` | 18 | 2 | S | D | `EXCLUDE` | — | 18 rows, shift config |
| `mapped_teachers` | 11 | 7 | SY | B | `EDGE` | (:Staff)-[:MAPPED_TO]->(:Subject) | **Only 11 rows** — verify before relying on it |
| `hrms_weekdays` | 7 | 2 | — | C | `PROP` | lookup |  |
| `role_responsibility` | 7 | 4 | — | B | `PROP` | on :Role |  |
| `tbluser_shift_master` | 3 | 3 | S | B | `NODE` | :Shift | 3 rows |
| `tbluser_contact_details` | 1 | 6 | S | B | `PROP` | on :Staff |  |
| `biomatrix` | 0 | 15 | S | D | `EXCLUDE` | — | Empty biometric config |
| `hrms_job_titles` | 0 | 3 | S | D | `EXCLUDE` | — | Empty |
| `tbluser_past_education` | 0 | 6 | S | D | `EXCLUDE` | — | Empty |
| `tbluser_shift_records` | 0 | 3 | S | D | `EXCLUDE` | — | Empty |
| `user_experience_details` | 0 | 4 | S | D | `EXCLUDE` | — | Empty prior-employment table |

### Timetable  ·  phase 10  ·  3 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `timetable` | 102,652 | 56 | SY | B | `EDGE` | (:Staff)-[:TEACHES]->(:Subject) + (:Division)-[:SCHEDULED]->(:Subject) | 102,652 rows. Full tenancy + teacher_id/subject_id/period_id/division_id |
| `create_timetable` | 54 | 4 | SY | D | `EXCLUDE` | — | 54 rows, generator config |
| `college_timetable` | 0 | 4 | SY | D | `EXCLUDE` | — | Empty |

### Fees  ·  phase 11  ·  41 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `fees_breackoff` | 182,333 | 53 | SY | B | `AGG_EDGE` | (:FeeSchedule)-[:APPLIES_TO]->(:Standard) | **182,333 rows and NO student_id.** It is a fee *schedule* per (grade,standard,quota,month), not a student ledger. Master prompt was wrong — see F5 |
| `fees_breakoff_other` | 46,861 | 18 | SY | B | `AGG_EDGE` | (:Student)-[:LIABLE_FOR]->(:FeeHead) | 46,861 rows — **this one does have student_id**. authoritative=false |
| `fees_receipt_book_master` | 9,734 | 41 | SY | B | `NODE` | :ReceiptBook | 9,734 rows — receipt *stationery* config, not receipts. No student_id |
| `fees_cancel` | 7,837 | 13 | SY | B | `PROP` | status on the LIABLE_FOR edge | 7,837 rows |
| `fees_reconciliation` | 6,721 | 8 | S | D | `EXCLUDE` | — | **Never project.** Reconciliation must not have a second copy (6,721 rows) |
| `fees_breackoff_logs` | 4,731 | 4 | SY | D | `EXCLUDE` | — | Audit log |
| `fees_circular_log` | 2,454 | 6 | SY | D | `EXCLUDE` | — | Audit log |
| `fees_other_collection` | 2,098 | 13 | SY | B | `AGG_EDGE` | (:Student)-[:PAID]->(:FeeHead) | 2,098 rows. authoritative=false |
| `fees_title` | 823 | 210 | SY | B | `NODE` | :FeeTitle | **authoritative=false.** q210 |
| `petty_cash` | 214 | 16 | S | B | `AGG_EDGE` | (:Staff)-[:PETTY_CASH]->(:AcademicYear) | **authoritative=false** |
| `bank_master` | 176 | 4 | — | B | `NODE` | :Bank | **authoritative=false** |
| `fees_map_years` | 176 | 24 | SY | B | `EDGE` | (:FeeConfig)-[:FOR_YEAR]->(:AcademicYear) |  |
| `fees_config_master` | 150 | 21 | SY | B | `NODE` | :FeeConfig | **authoritative=false** |
| `fees_circular_master` | 134 | 6 | SY | B | `NODE` | :FeeCircular | **authoritative=false** |
| `imprest_fees_cancel` | 107 | 9 | SY | D | `EXCLUDE` | — | 107 rows, cancellation log |
| `fees_late_master` | 86 | 12 | SY | B | `NODE` | :LateFeeRule | **authoritative=false** |
| `fees_head_master` | 66 | 4 | SY | B | `NODE` | :FeeHead | **authoritative=false** |
| `fees_month_header` | 62 | 12 | S | B | `NODE` | :FeeMonth | **authoritative=false** |
| `petty_cash_master` | 48 | 11 | S | B | `NODE` | :PettyCashHead | **authoritative=false** |
| `fees_other_head` | 36 | 11 | SY | B | `NODE` | :FeeOtherHead | **authoritative=false.** **Split from :FeeHead 2026-08-10** — 2 (tenant,pk) pairs collide with fees_head_master |
| `donation_collection` | 26 | 9 | S | B | `AGG_EDGE` | (:Institute)-[:RECEIVED_DONATION]->(:AcademicYear) | **authoritative=false** |
| `fees_title_master` | 24 | 8 | — | B | `NODE` | :FeeTitleMaster | **authoritative=false** |
| `fees_receipt` | 22 | 20 | SY | D | `EXCLUDE` | — | 22 rows, print config |
| `fees_paid_other` | 21 | 50 | SY | B | `AGG_EDGE` | (:Student)-[:PAID]->(:FeeHead) | 21 rows |
| `fees_other_cancel` | 15 | 5 | SY | B | `PROP` | status |  |
| `fees_collect` | 13 | 112 | SY | B | `AGG_EDGE` | (:Student)-[:PAID]->(:FeeTitle) | **Only 13 rows** despite q112. The collection ledger is effectively empty here — see F6 |
| `fees_online_maping` | 13 | 28 | SY | D | `EXCLUDE` | — | Gateway config |
| `NACH_ac_type` | 9 | 3 | S | D | `EXCLUDE` | — | Lookup |
| `fees_icici` | 6 | 10 | SY | D | `EXCLUDE` | — | Gateway config |
| `fees_refund` | 6 | 11 | SY | B | `PROP` | refund flag | 6 rows |
| `fees_cancel_type` | 4 | 13 | — | B | `NODE` | :FeeCancelType | **authoritative=false** |
| `fees_razorpay` | 4 | 5 | SY | D | `EXCLUDE` | — | Gateway config |
| `fees_receipt_css` | 4 | 15 | — | D | `EXCLUDE` | — | Stylesheet config |
| `NACH_MASTER` | 3 | 5 | S | D | `EXCLUDE` | — | Mandate config |
| `fees_hdffc` | 2 | 9 | SY | D | `EXCLUDE` | — | Gateway config |
| `fees_aggre_pay` | 1 | 6 | SY | D | `EXCLUDE` | — | Gateway config |
| `fees_hdfcrazorpay` | 1 | 3 | SY | D | `EXCLUDE` | — | Gateway config |
| `fees_payphi` | 1 | 6 | SY | D | `EXCLUDE` | — | Gateway config |
| `fees_axis` | 0 | 6 | SY | D | `EXCLUDE` | — | Gateway config, empty |
| `fees_online_split` | 0 | 6 | S | D | `EXCLUDE` | — | Empty |
| `fees_payment` | 0 | 60 | SY | D | `EXCLUDE` | — | **0 rows but 60 code refs** — see F7 |

### FrontDesk  ·  phase 12  ·  7 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `complaint` | 34 | 35 | SY | B | `NODE` | :Complaint | Uses UPPERCASE SUB_INSTITUTE_ID |
| `announcement` | 33 | 16 | SY | B | `NODE` | :Announcement |  |
| `complaint_status` | 4 | 10 | — | C | `PROP` | status lookup |  |
| `follow_up` | 4 | 7 | S | D | `EXCLUDE` | — | 4 rows |
| `circular_type` | 2 | 10 | — | C | `NODE` | :CircularType |  |
| `circular` | 1 | 34 | SY | B | `NODE` | :Circular | 1 row, q34 |
| `front_desk` | 1 | 16 | SY | B | `NODE` | :FrontDeskEntry | 1 row. Uses UPPERCASE SUB_INSTITUTE_ID |

### Hostel  ·  phase 12  ·  7 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `hostel_room_master` | 97 | 9 | S | B | `NODE` | :HostelRoom |  |
| `hostel_room_allocation` | 9 | 23 | SY | B | `EDGE` | (:Student)-[:ALLOCATED_ROOM]->(:HostelRoom) | Only 9 rows |
| `hostel_floor_master` | 6 | 12 | S | B | `NODE` | :HostelFloor |  |
| `hostel_type_master` | 5 | 15 | S | C | `NODE` | :HostelType |  |
| `hostel_visitor_master` | 5 | 3 | S | B | `EDGE` | (:Visitor)-[:VISITED]->(:Student) | 5 rows |
| `hostel_master` | 4 | 22 | S | B | `NODE` | :Hostel |  |
| `hostel_building_master` | 3 | 16 | S | B | `NODE` | :HostelBuilding |  |

### Inventory  ·  phase 12  ·  19 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `item_scan_details` | 32,569 | 17 | SY | D | `EXCLUDE` | — | **32,569-row scan log.** Aggregate a scan_count property only |
| `inventory_item_sub_category_master` | 476 | 12 | SY | B | `NODE` | :ItemSubCategory |  |
| `inventory_item_master` | 321 | 38 | SY | B | `NODE` | :InventoryItem |  |
| `inventory_requisition_details` | 139 | 14 | SY | B | `EDGE` | (:Staff)-[:REQUISITIONED]->(:InventoryItem) |  |
| `inventory_item_category_master` | 62 | 12 | SY | B | `NODE` | :ItemCategory |  |
| `inventory_item_direct_purchase` | 48 | 8 | SY | B | `EDGE` | (:Vendor)-[:SUPPLIED]->(:InventoryItem) |  |
| `inventory_vendor_master` | 32 | 12 | SY | B | `NODE` | :Vendor |  |
| `inventory_master_setup` | 15 | 8 | SY | D | `EXCLUDE` | — | Config |
| `inventory_allocation_details` | 12 | 6 | SY | B | `EDGE` | (:Staff)-[:ALLOCATED_ITEM]->(:InventoryItem) | Uses UPPERCASE SUB_INSTITUTE_ID |
| `inventory_item_quotation_details` | 8 | 21 | SY | B | `EDGE` | (:Vendor)-[:QUOTED]->(:InventoryItem) |  |
| `inventory_tax_master` | 5 | 5 | SY | D | `EXCLUDE` | — | Tax config |
| `inventory_generate_po_details` | 4 | 23 | SY | B | `EDGE` | (:Vendor)-[:PURCHASE_ORDER]->(:InventoryItem) |  |
| `inventory_item_receivable_details` | 4 | 6 | SY | D | `EXCLUDE` | — | 4 rows |
| `inventory_item_type` | 4 | 5 | S | C | `NODE` | :ItemType |  |
| `inventory_negotiate_po_details` | 4 | 9 | SY | D | `EXCLUDE` | — | 4 rows, workflow detail |
| `inventory_requisition_status_master` | 4 | 10 | — | C | `PROP` | status lookup |  |
| `inventory_item_defective_details` | 0 | 6 | SY | D | `EXCLUDE` | — | Empty |
| `inventory_item_lost_details` | 0 | 3 | SY | D | `EXCLUDE` | — | Empty |
| `inventory_item_return_details` | 0 | 6 | SY | D | `EXCLUDE` | — | Empty |

### Library  ·  phase 12  ·  3 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `library_book_circulations` | 67,487 | 12 | SY | B | `AGG_EDGE` | (:Student)-[:BORROWED]->(:Book) | **67,487 rows → aggregate per (student, book).** Enables "students who read X also read Y" |
| `library_items` | 36,181 | 12 | S | B | `EDGE` | (:BookCopy)-[:COPY_OF]->(:Book) | 36,181 physical copies |
| `library_books` | 35,663 | 13 | SY | B | `NODE` | :Book | 35,663 rows. Uses `academic_year`, not syear |

### Operations  ·  phase 12  ·  5 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `inward` | 5,773 | 21 | SY | B | `NODE` | :InwardDocument | 5,773 rows |
| `physical_file_location` | 195 | 14 | S | B | `NODE` | :FileLocation |  |
| `outward` | 149 | 15 | SY | B | `NODE` | :OutwardDocument | 149 rows |
| `mst_item_status` | 7 | 60 | S | C | `PROP` | status lookup |  |
| `room_type_master` | 5 | 9 | S | C | `NODE` | :RoomType |  |

### Transport  ·  phase 12  ·  10 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `transport_map_student` | 30,300 | 27 | SY | B | `EDGE` | (:Student)-[:BOARDS_AT]->(:Stop) | 30,300 rows. **Two edges per row** — from_stop and to_stop |
| `transport_stop` | 2,810 | 17 | SY | B | `NODE` | :Stop |  |
| `transport_route_stop` | 2,794 | 5 | SY | B | `EDGE` | (:Route)-[:HAS_STOP {sequence}]->(:Stop) |  |
| `transport_route_bus` | 1,822 | 5 | SY | B | `EDGE` | (:Vehicle)-[:SERVES]->(:Route) |  |
| `transport_driver_detail` | 738 | 24 | S | B | `NODE` | :Driver | **PII** |
| `transport_vehicle` | 690 | 27 | S | B | `NODE` | :Vehicle |  |
| `transport_kilometer_rate` | 388 | 14 | SY | B | `PROP` | rate on :Route |  |
| `transport_route` | 379 | 22 | SY | B | `NODE` | :Route |  |
| `transport_school_shift` | 25 | 31 | S | B | `NODE` | :Shift |  |
| `transport_vehicle_type` | 3 | 5 | — | C | `NODE` | :VehicleType |  |

### Visitor  ·  phase 12  ·  3 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `visitor_master` | 867 | 11 | S | B | `NODE` | :Visitor | **PII** |
| `visitor_type` | 29 | 28 | S | B | `NODE` | :VisitorType |  |
| `visitor_master_settings` | 1 | 3 | S | D | `EXCLUDE` | — | Config |

### ONET  ·  phase 13  ·  43 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `onet_work_context` | 289,173 | 1 | — | C | `AGG_EDGE` | (:Occupation)-[:REQUIRES]->(:WorkContext) | Rating ledger — project top-20 per occupation as weighted edges, log dropped rows |
| `o_net_data_tables` | 209,080 | 9 | — | D | `EXCLUDE` | — | Denormalised summary table, 9 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_task_ratings` | 161,847 | 1 | — | C | `AGG_EDGE` | (:Occupation)-[:REQUIRES]->(:Task) | Rating ledger — project top-20 per occupation as weighted edges, log dropped rows |
| `onet_abilities` | 90,792 | 1 | — | C | `AGG_EDGE` | (:Occupation)-[:REQUIRES]->(:Ability) | Rating ledger — project top-20 per occupation as weighted edges, log dropped rows |
| `onet_work_activities` | 71,586 | 1 | — | C | `AGG_EDGE` | (:Occupation)-[:REQUIRES]->(:WorkActivity) | Rating ledger — project top-20 per occupation as weighted edges, log dropped rows |
| `onet_institute_data` | 65,738 | 5 | — | C | `REVIEW` | — | **65,738 rows of Indian college listings (AICTE).** Not K12 and not occupation data — decide whether the careers module needs it |
| `onet_skills` | 61,110 | 1 | — | C | `AGG_EDGE` | (:Occupation)-[:REQUIRES]->(:Skill) | Rating ledger — project top-20 per occupation as weighted edges, log dropped rows |
| `onet_knowledge` | 57,618 | 1 | — | C | `AGG_EDGE` | (:Occupation)-[:REQUIRES]->(:Knowledge) | Rating ledger — project top-20 per occupation as weighted edges, log dropped rows |
| `onet_tools_used` | 41,650 | 1 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:Tool) | Rating table — project as weighted edges |
| `onet_technology_skills` | 32,470 | 1 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:TechnologySkill) | Rating table — project as weighted edges |
| `o_net_occupation_detail_work_activity_summeries` | 21,580 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_task_statements` | 19,281 | 1 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:Task) | Rating table — project as weighted edges |
| `o_net_occupation_detail_list_summaries` | 17,592 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_detail_abilities_summeries` | 16,424 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_detail_work_style_summeries` | 15,603 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_work_styles` | 13,968 | 1 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:WorkStyle) | Rating table — project as weighted edges |
| `o_net_occupation_detail_lists` | 13,515 | 4 | — | D | `EXCLUDE` | — | Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_detail_skill_summeries` | 12,442 | 3 | — | D | `EXCLUDE` | — | Denormalised summary table, 3 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_detail_tech_skill_summeries` | 12,028 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_interests` | 8,307 | 1 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:Interest) | Rating table — project as weighted edges |
| `onet_work_values` | 7,866 | 1 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:WorkValue) | Rating table — project as weighted edges |
| `o_net_occupation_detail_knowledge_summeries` | 6,668 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_unspsc_reference` | 4,262 | 2 | — | C | `NODE` | :UNSPSCCategory | O*NET dimension — load once, no live sync |
| `o_net_occupation_detail_work_value_summeries` | 2,618 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_detail_education_summeries` | 2,452 | 4 | — | D | `EXCLUDE` | — | Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_detail_interest_summeries` | 2,214 | 2 | — | D | `EXCLUDE` | — | Denormalised summary table, 2 refs — duplicates the dimension+rating tables. Report and ask |
| `o_net_occupation_details` | 1,230 | 4 | — | D | `EXCLUDE` | — | Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_occupation_data` | 1,016 | 1 | — | C | `NODE` | :Occupation | O*NET dimension — load once, no live sync |
| `onet_career_cluster` | 1,011 | 1 | — | C | `NODE` | :CareerCluster | O*NET dimension — load once, no live sync |
| `onet_job_zones` | 923 | 0 | — | C | `EDGE` | (:Occupation)-[:REQUIRES]->(:JobZone) | Rating table — project as weighted edges |
| `o_net_data_table_lists` | 873 | 2 | — | C | `NODE` | :ONetDataTable | O*NET dimension — load once, no live sync |
| `o_net_occupation_detail_job_zone_summeries` | 872 | 4 | — | D | `EXCLUDE` | — | Denormalised summary table, 4 refs — duplicates the dimension+rating tables. Report and ask |
| `onet_content_model_reference` | 627 | 9 | — | C | `NODE` | :ONetElement | O*NET dimension — load once, no live sync |
| `onet_expert_advice` | 430 | 3 | — | C | `NODE` | :ExpertAdvice | O*NET dimension — load once, no live sync |
| `o_net_data_sub_categories` | 358 | 4 | — | C | `NODE` | :ONetDataSubCategory | O*NET dimension — load once, no live sync |
| `onet_explore_sector` | 344 | 3 | — | C | `NODE` | :Sector | O*NET dimension — load once, no live sync |
| `onet_work_context_categories` | 281 | 0 | — | C | `NODE` | :WorkContextCategory | O*NET dimension — load once, no live sync |
| `o_net_data_occupations` | 246 | 2 | — | C | `NODE` | :ONetDataOccupation | O*NET dimension — load once, no live sync |
| `onet_scales_reference` | 29 | 9 | — | C | `NODE` | :ONetScale | O*NET dimension — load once, no live sync |
| `onet_employer` | 23 | 3 | — | C | `NODE` | :Employer | O*NET dimension — load once, no live sync |
| `onet_institute_courses` | 16 | 2 | — | C | `NODE` | :Course | O*NET dimension — load once, no live sync |
| `o_net_data_categories` | 12 | 2 | — | C | `NODE` | :ONetDataCategory | O*NET dimension — load once, no live sync |
| `onet_job_zone_reference` | 5 | 1 | — | C | `NODE` | :JobZone | O*NET dimension — load once, no live sync |

### SQAA  ·  phase 13  ·  4 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `sqaa_documant_master` | 1,534 | 4 | S | B | `NODE` | :SQAADocument | 1,534 rows (note the typo) |
| `sqaa_master` | 247 | 8 | S | B | `NODE` | :SQAAStandard |  |
| `sqaa_documents` | 86 | 5 | S | B | `EDGE` | (:Institute)-[:SUBMITTED]->(:SQAADocument) |  |
| `sqaa_marks` | 6 | 5 | S | B | `EDGE` | (:Institute)-[:SCORED_SQAA]->(:SQAAStandard) | 6 rows |

### Skill  ·  phase 13  ·  9 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `s_jobrole_skills` | 176,460 | 1 | S | A | `EDGE` | (:JobRole)-[:REQUIRES_SKILL]->(:Skill) | **176,460 rows. `jobrole` and `skill` are NAME STRINGS, not ids** — violates L1. See F8 |
| `s_skill_map_k_a` | 138,461 | 0 | — | D | `EXCLUDE` | — | **138,461 rows / 79.7 MB, 0 code references.** Do not project — confirm with owner first |
| `s_jobrole_task` | 34,060 | 2 | S | A | `EDGE` | (:JobRole)-[:INVOLVES_TASK]->(:Task) | 34,060 rows |
| `master_skills` | 16,239 | 5 | S | A | `NODE` | :Skill | 16,239 rows. sub_institute_id = 0 → reference data, not tenant-scoped |
| `s_jobrole` | 5,805 | 7 | S | A | `NODE` | :JobRole | 5,805 rows |
| `s_user_jobrole` | 4,909 | 2 | S | A | `EDGE` | (:Staff)-[:TARGETS_JOBROLE]->(:JobRole) | 4,909 rows |
| `s_industries` | 715 | 0 | — | C | `NODE` | :Industry | 715 rows, 0 code refs |
| `s_assessment_library` | 40 | 3 | — | A | `NODE` | :SkillAssessment | 40 rows |
| `s_skill_matrix` | 3 | 3 | — | A | `EDGE` | (:Staff)-[:HAS_SKILL]->(:Skill) | **Only 3 rows.** `user_id` → tbluser |

### Calendar  ·  phase 14  ·  1 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `calendar_events` | 3,941 | 18 | SY | B | `NODE` | :CalendarEvent | 3,941 rows |

### Communication  ·  phase 14  ·  12 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `sms_sent_parents` | 53,447 | 15 | SY | B | `AGG_EDGE` | (:Student)-[:COMMUNICATION]->(:AcademicYear) | **53,447-row send log** — aggregate counts only |
| `gcm_users` | 26,199 | 23 | S | D | `EXCLUDE` | — | **26,199-row push-token registry** — no traversal value |
| `parent_communication` | 22,729 | 34 | SY | B | `AGG_EDGE` | (:Student)-[:COMMUNICATION]->(:AcademicYear) | 22,729 rows |
| `whatsapp_sent_messages` | 11,153 | 6 | SY | B | `AGG_EDGE` | (:Student)-[:COMMUNICATION]->(:AcademicYear) | 11,153 rows |
| `email_sent_parents` | 2,761 | 10 | SY | B | `AGG_EDGE` | (:Staff)-[:COMMUNICATION]->(:AcademicYear) | 2,761 rows. Keys on USER_ID (staff sender); there is no student_id, so this cannot hang off :Student |
| `app_notification` | 199 | 9 | SY | D | `EXCLUDE` | — | Notification log |
| `incoming_messages` | 104 | 2 | — | D | `EXCLUDE` | — | Inbound webhook log |
| `sms_api_details` | 34 | 7 | S | D | `EXCLUDE` | — | Gateway config |
| `sms_sent_staff` | 10 | 4 | SY | B | `AGG_EDGE` | (:Staff)-[:COMMUNICATION]->(:AcademicYear) | 10 rows |
| `smtp_details` | 6 | 15 | S | D | `EXCLUDE` | — | Mail config |
| `whatapp_user_details` | 1 | 5 | S | D | `EXCLUDE` | — | Gateway config (note the typo) |
| `app_notification_teacher` | 0 | 4 | SY | D | `EXCLUDE` | — | Empty |

### Leaderboard  ·  phase 14  ·  2 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `lb_master` | 5 | 7 | S | B | `NODE` | :LeaderboardRule | 5 rows — points rule per (grade, standard, module) |
| `lb_points` | 2 | 3 | SY | B | `AGG_EDGE` | (:Staff)-[:EARNED_POINTS]->(:LeaderboardRule) | 2 rows. `user_id` → tbluser, not tblstudent |

### PAL  ·  phase 14  ·  26 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `pal_session_events` | 18 | 4 | — | B | `REVIEW` | — | PAL stub, 18 row(s) — schema exists, feature not in production |
| `pal_telemetry_events` | 5 | 11 | — | B | `REVIEW` | — | PAL stub, 5 row(s) — schema exists, feature not in production |
| `pal_reflections` | 3 | 10 | — | B | `REVIEW` | — | PAL stub, 3 row(s) — schema exists, feature not in production |
| `pal_learner_misconceptions` | 2 | 4 | — | B | `REVIEW` | — | PAL stub, 2 row(s) — schema exists, feature not in production |
| `pal_concepts` | 1 | 12 | — | B | `REVIEW` | — | PAL stub, 1 row(s) — schema exists, feature not in production |
| `pal_contents` | 1 | 6 | — | B | `REVIEW` | — | PAL stub, 1 row(s) — schema exists, feature not in production |
| `pal_learning_sessions` | 1 | 9 | — | B | `REVIEW` | — | PAL stub, 1 row(s) — schema exists, feature not in production |
| `pal_pedagogy_effectiveness` | 1 | 11 | — | B | `REVIEW` | — | PAL stub, 1 row(s) — schema exists, feature not in production |
| `pal_remediations` | 1 | 4 | — | B | `REVIEW` | — | PAL stub, 1 row(s) — schema exists, feature not in production |
| `pal_assessment_results` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_classroom_activities` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_collaboration_activities` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_competencies` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_content_recommendations` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_discussions` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_group_activities` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_learner_preferences` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_learner_states` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_learning_events` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_learning_journals` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_learning_patterns` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_learning_plans` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_remediation_sessions` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_self_corrections` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_strategy_selections` | 0 | 4 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |
| `pal_subjects` | 0 | 10 | — | D | `EXCLUDE` | — | PAL stub, empty — schema exists, feature not in production |

### PTM  ·  phase 14  ·  2 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `ptm_booking_master` | 789 | 5 | S | B | `EDGE` | (:Guardian)-[:BOOKED]->(:Staff) | 789 rows |
| `ptm_time_slots_master` | 152 | 4 | SY | B | `NODE` | :TimeSlot |  |

### Platform  ·  phase 14  ·  59 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `tblprofilewise_menu` | 48,351 | 29 | S | D | `EXCLUDE` | — | 48,351-row menu matrix |
| `tblgroupwise_rights` | 30,557 | 50 | S | D | `EXCLUDE` | — | 30,557-row permission matrix — no traversal value |
| `tblindividual_rights` | 25,060 | 25 | S | D | `EXCLUDE` | — | 25,060-row permission matrix |
| `neo4j_sync_queue` | 12,193 | 0 | — | D | `EXCLUDE` | — | **12,193 rows, 0 code references.** Orphaned queue from a previous sync attempt — see F10 |
| `sync_log` | 9,240 | 0 | — | D | `EXCLUDE` | — | 9,240 rows, 0 code refs |
| `ERR_LOG` | 5,312 | 3 | — | D | `EXCLUDE` | — | Error log |
| `erptour` | 1,973 | 31 | S | D | `EXCLUDE` | — | UI tour state |
| `teacher_mobile_homescreen` | 1,641 | 13 | S | D | `EXCLUDE` | — | UI config |
| `access_log_route` | 1,421 | 6 | S | D | `EXCLUDE` | — | Audit log |
| `mobile_homescreen` | 1,142 | 13 | S | D | `EXCLUDE` | — | UI config |
| `task` | 887 | 51 | SY | B | `NODE` | :Task | 887 rows, q51 |
| `onboarding_step` | 611 | 6 | — | D | `EXCLUDE` | — | Onboarding checklist |
| `tblmenumaster` | 501 | 102 | S | D | `EXCLUDE` | — | Menu definitions |
| `password_resets` | 496 | 6 | — | D | `EXCLUDE` | — | Framework table |
| `import_table_fields` | 492 | 17 | — | D | `EXCLUDE` | — | Import mapping config |
| `S2_LOG` | 378 | 6 | — | D | `EXCLUDE` | — | Integration log |
| `csv_data` | 280 | 12 | — | D | `EXCLUDE` | — | Import staging |
| `migrations` | 278 | 2 | — | D | `EXCLUDE` | — | Laravel framework table |
| `tblcustom_fields` | 277 | 16 | S | D | `EXCLUDE` | — | Custom-field metadata |
| `rightside_menumaster` | 274 | 3 | S | D | `EXCLUDE` | — | Menu definitions |
| `app_language` | 189 | 12 | S | D | `EXCLUDE` | — | i18n strings |
| `dynamic_dashboard` | 181 | 8 | S | D | `EXCLUDE` | — | UI config |
| `hp_tblmenumaster` | 153 | 0 | S | D | `EXCLUDE` | — | Menu definitions, 0 refs |
| `personal_access_tokens` | 64 | 2 | — | D | `EXCLUDE` | — | Framework table |
| `ai_interaction_logs` | 53 | 4 | SY | D | `EXCLUDE` | — | LLM call log |
| `relation_table_fields` | 52 | 3 | — | D | `EXCLUDE` | — | Import mapping config |
| `tblfields_data` | 51 | 7 | — | D | `EXCLUDE` | — | Custom-field metadata |
| `custom_module_table_columns` | 49 | 15 | — | D | `EXCLUDE` | — | Dynamic-table metadata |
| `onboarding_module` | 40 | 6 | S | D | `EXCLUDE` | — | Onboarding checklist |
| `user_activities` | 24 | 5 | S | B | `EDGE` | (:Staff)-[:VIEWED]->(:Content) | 24 rows — content view log |
| `onboarding_progress` | 20 | 5 | SY | D | `EXCLUDE` | — | Onboarding checklist |
| `blogs` | 11 | 4 | — | D | `EXCLUDE` | — | 11 rows, CMS content |
| `table_relation` | 10 | 2 | — | D | `EXCLUDE` | — | Import mapping config |
| `custom_module_tables` | 8 | 8 | S | D | `EXCLUDE` | — | Dynamic-table metadata |
| `form_submit_data` | 8 | 3 | S | D | `EXCLUDE` | — | Form submissions |
| `ai_sops` | 7 | 3 | S | D | `EXCLUDE` | — | Prompt config |
| `form_builder` | 5 | 8 | — | D | `EXCLUDE` | — | Form metadata |
| `requirement_gathering` | 5 | 7 | S | D | `EXCLUDE` | — | 5 rows |
| `ai_api_keys` | 4 | 7 | S | D | `EXCLUDE` | — | Secrets |
| `api_details` | 1 | 2 | S | D | `EXCLUDE` | — | Config |
| `new_client_rights` | 1 | 2 | — | D | `EXCLUDE` | — | Permission config |
| `users` | 1 | 50 | — | D | `EXCLUDE` | — | Laravel auth table, 1 row |
| `access_log` | 0 | 4 | SY | D | `EXCLUDE` | — | Audit log (empty) |
| `activity_log` | 0 | 4 | — | D | `EXCLUDE` | — | Audit log (empty) |
| `ai_daily_used_api` | 0 | 2 | S | D | `EXCLUDE` | — | Usage counter |
| `application_forms` | 0 | 2 | — | D | `EXCLUDE` | — | Empty |
| `dashboard_master` | 0 | 2 | S | D | `EXCLUDE` | — | Empty |
| `document_templates` | 0 | 4 | SY | D | `EXCLUDE` | — | Empty |
| `document_template_versions` | 0 | 3 | S | D | `EXCLUDE` | — | Empty |
| `implementation_master` | 0 | 7 | SY | D | `EXCLUDE` | — | Empty |
| `master_compliance` | 0 | 5 | S | D | `EXCLUDE` | — | Empty compliance tracker |
| `photo_video_gallary` | 0 | 14 | SY | D | `EXCLUDE` | — | Empty (note the typo) |
| `recommendations` | 0 | 11 | — | D | `EXCLUDE` | — | **0 rows but 11 code refs** — see F7 |
| `task_management_comments` | 0 | 0 | — | D | `EXCLUDE` | — | Empty |
| `task_management_dependencies` | 0 | 0 | SY | D | `EXCLUDE` | — | Empty |
| `task_management_milestones` | 0 | 0 | SY | D | `EXCLUDE` | — | Empty |
| `tblapplications` | 0 | 3 | — | D | `EXCLUDE` | — | Empty |
| `tblmenumaster_new` | 0 | 2 | S | D | `EXCLUDE` | — | Empty |
| `tblmenumaster_old` | 0 | 2 | S | D | `EXCLUDE` | — | Empty |

### NotK12  ·  phase —  ·  8 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `sharebazar_position` | 77,305 | 13 | — | D | `EXCLUDE` | — | **77,305 rows.** Stock-trading module — not K12 |
| `sharebazar_pnl` | 24,829 | 8 | — | D | `EXCLUDE` | — | Stock-trading module — not K12 |
| `sharebazar_margin` | 18,985 | 8 | — | D | `EXCLUDE` | — | Stock-trading module — not K12. Confirm before deleting |
| `Z_donarDetails` | 17 | 4 | S | D | `EXCLUDE` | — | Prefixed-Z scratch table |
| `Z_customer_registrations` | 3 | 0 | S | D | `EXCLUDE` | — | Prefixed-Z scratch table, 0 refs |
| `Z_Seminar` | 1 | 0 | S | D | `EXCLUDE` | — | Prefixed-Z scratch table |
| `Z_Student_Details` | 1 | 0 | S | D | `EXCLUDE` | — | Prefixed-Z scratch table |
| `Z_employee_details` | 0 | 0 | S | D | `EXCLUDE` | — | Prefixed-Z scratch table, empty |

### Report  ·  phase —  ·  4 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `report_module_fields` | 26 | 4 | S | D | `EXCLUDE` | — | Report definitions |
| `report_module_data` | 25 | 9 | S | D | `EXCLUDE` | — | Report definitions |
| `report_module` | 22 | 9 | S | D | `EXCLUDE` | — | Report definitions |
| `report_dynamic` | 7 | 1 | Y | D | `EXCLUDE` | — | Report definitions — read path only |

### Workflow  ·  phase —  ·  8 tables

| Table | Rows | Refs | T | Tier | Decision | Target | Note |
|---|---:|---:|:-:|:-:|---|---|---|
| `wk_condition` | 0 | 10 | — | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_execute_schedule` | 0 | 7 | S | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_log` | 0 | 4 | — | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_mail` | 0 | 6 | — | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_main` | 0 | 76 | S | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_module` | 0 | 8 | — | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_sms` | 0 | 6 | — | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |
| `wk_updatequery` | 0 | 6 | — | D | `EXCLUDE` | — | Workflow-engine table, empty (wk_main has 76 code refs but 0 rows) |

---

## 8. What Phase 2 must generate from this

The registry (`config/neo4j_graph.php`) needs one entry per projected table carrying:

1. **Decision** — `NODE` / `EDGE` / `AGG_EDGE` / `PROP`, from the register above.
2. **uid template** — `<Label>:<sub_institute_id>:<syear>:<pk>` (PROJECTION LAW L1). For tables with
   no year column, `syear` is `0`, not null.
3. **Tenancy source** — either the column name (handling all three spellings in F12) or a derivation
   path through a named foreign key. **No default tenant.** A row that cannot resolve one is dropped
   and counted, never assigned `0`.
4. **The `GROUP BY`** for every `AGG_EDGE` — written in SQL, in the export query, not in PHP.
5. **`authoritative=false`** on every Fees and Payroll node and edge (46 tables).
6. **Dropped-row counters** per table, surfaced by `neo4j:verify`. F1 alone will drop ~105,000 child
   rows; that number must appear in a log, not vanish.

Special cases the loader must handle explicitly:

| Case | Tables | Handling |
|---|---|---|
| PascalCase tenancy + PK `Id` | `result_exam_master`, `result_exam_type_master` | Column alias in the export query |
| PK is the tenant | `school_setup` | uid `Institute:{Id}:0:{Id}` |
| Two edges per row | `transport_map_student` | `from_stop` and `to_stop` both become `BOARDS_AT` |
| Aggregate key is a term, not a month | `result_student_attendance_master` | Group by `(student_id, syear, term_id)` |
| Date column is `day`, no year column | `hrms_attendances` | Derive `syear` from `day` |
| Self-referencing tree | `lms_mapping_type` | `PARENT_OF` where `parent_id <> 0`; assert acyclic |
| Name-string joins | `s_jobrole_skills`, `s_jobrole_task` | Blocked on JOBROLE-KEY (§4) |

---

## 9. Phase 1 verification gate

The gate for this phase is **human review**, not a query. These are the mechanical checks that back
the document; all were run against the live source this session.

```
CHECK 1  Every table in the source appears exactly once in the register
         488 tables measured / 488 classified / 0 uncovered ............... PASS

CHECK 2  Every table has a decision from the closed vocabulary
         NODE 156 · EDGE 57 · AGG_EDGE 34 · PROP 30 · EXCLUDE 194 · REVIEW 17
         = 488 ............................................................ PASS

CHECK 3  Every table has a tier
         A 57 · B 189 · C 49 · D 193 = 488 ................................ PASS

CHECK 4  Every EXCLUDE carries a stated reason
         194 / 194 ........................................................ PASS

CHECK 5  Projected size inside the master-prompt budget
         nodes 596,825 < 700,000 .......................................... PASS
         relationships 1,853,827 < 4,000,000 .............................. PASS

CHECK 6  No ledger (>50k rows) is projected as nodes
         Tables >50k rows projected as NODE: lms_online_exam (147,875),
         tblstudent (83,715), lms_mapping_type (71,532), lms_question_master
         (62,209) — all four are entity tables, not ledgers ............... PASS

CHECK 7  Row counts are exact, not estimates
         COUNT(*) run per table, 488/488 ................................. PASS

CHECK 8  Nothing was written to MariaDB or Neo4j
         All queries SELECT-only; no connection opened to Neo4j .......... PASS

GATE     Human review of §3 (findings), §4 (blocking decisions), §5 (review
         items) ........................................................... AWAITING
```

**Phase 1 is not complete until a human signs off.** The four new blocking decisions in §4 change
what Phases 5, 6, 8, 11 and 13 build. Approving the register without settling CHAPTER-SOURCE and
FEES-MODEL means Phase 5 loads a curriculum whose children cannot attach, which is the defect this
migration exists to fix.

---

## 10. Sign-off

| | |
|---|---|
| Reviewed by | _(name)_ |
| Date | |
| CHAPTER-SOURCE decision | |
| CONCEPT-LINK decision | |
| FEES-MODEL decision | |
| JOBROLE-KEY decision | |
| TENANT-SCOPE decision | |
| §5 review items resolved | |
