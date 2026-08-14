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
