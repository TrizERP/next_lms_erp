# Neo4j module scripts — the rest of the ERP in the graph

How the eight module scripts under [`database/neo4j/cypher/`](../database/neo4j/cypher/) work, what
they load, and the rules they follow. Written 2026-09-04, after loading all eight against
`bolt://dev.triz.co.in:7688`.

```
470,776 nodes / 931,049 rels     ->     881,734 nodes / 2,912,957 rels
125 labels / 55 rel types                170 labels / 169 rel types
```

---

## 1. The one rule everything else follows

The graph already held a working PAL/K12 layer built by `k12_cypher.txt` and `reference_code.txt`
(both now kept verbatim as `00_k12_reference.cypher` and `01_graph_repair_reference.cypher`). Those
nodes and their **24 relationship types must not change**:

    HAS_STUDENT · ENROLLED_IN · HAS_SUBJECT · HAS_ASSESSMENT · HAS_RESULT · HAS_QUESTION ·
    BELONGS_TO · HAS_CHAPTER · ASSESSES_CHAPTER · FOR_ASSESSMENT · INCLUDES ·
    BELONGS_TO_CURRICULUM · HAS_LESSON · COVERS · ASSESSES · OCCURS_IN · TEACHES · REMEDIATES ·
    ATTEMPTED · ATTENDED · MASTERS · HAS_MISCONCEPTION · HAS_UNIT · PREREQUISITE_OF

So every module script is **additive**: `MERGE` + `ON CREATE SET`, never a bare `SET` on a node that
might already exist, and no `DELETE`, `REMOVE`, `DROP` or `DETACH` anywhere. Two guards enforce it
rather than trusting the author:

1. `neo4j:cypher` scans the whole file for those four verbs **before** connecting, and refuses the
   file if it finds one. A destructive verb in a module script is a bug, not an instruction.
2. It counts all 24 protected types before and after the run. Any that moves — except the ones the
   module's manifest entry declares in `extends` — is reported red.

Only `people` declares anything: `HAS_STUDENT` and `ENROLLED_IN`, because those two statements are
the reference script's own, re-run over the full 176,458-row table instead of the 5,409-row CSV
subset it was originally given. After the full load, 22 of the 24 were byte-identical and no
pre-existing label had lost a single node.

---

## 2. How a script runs from here without uploading anything

The scripts are written in the k12 dialect, so they can be pasted into Neo4j Browser or fed to
`cypher-shell` on the server unchanged:

```cypher
LOAD CSV WITH HEADERS FROM 'file:///timetable_agg.csv' AS row
WITH row WHERE row.teacher_id IS NOT NULL
MATCH ...
```

But the MySQL host (`202.47.117.220`) and the Neo4j host (`202.47.117.61`) are different machines, so
`INTO OUTFILE` can never land a file in Neo4j's import directory without a human copying it. Rather
than require that, `neo4j:cypher` rewrites the prefix:

```
LOAD CSV WITH HEADERS FROM 'file:///x.csv' AS row   ->   UNWIND $rows AS row
```

and streams `storage/app/neo4j-csv/x.csv` over Bolt in batches. Values are bound as **strings**,
which is exactly what `LOAD CSV` hands Cypher, so `toInteger(trim(row.x))` means the same thing on
both paths. `:auto` and `USING PERIODIC COMMIT` are stripped — they are server directives with no
meaning over Bolt.

```bash
php artisan neo4j:csv-export --module=hr          # MariaDB -> storage/app/neo4j-csv/*.csv
php artisan neo4j:csv-export --module=hr --sql    # ... and the INTO OUTFILE script, for the server
php artisan neo4j:cypher --module=hr --dry-run    # parse, rewrite, print; connect for nothing
php artisan neo4j:cypher --module=hr              # run it
php artisan neo4j:cypher --module=hr --verify     # re-run only the verify section
php artisan neo4j:cypher --baseline               # fingerprint every label and rel type
```

A run that dies mid-way resumes: `--from=N` picks up at statement N, and every statement is a MERGE,
so replaying one is free. Re-running a whole module creates nothing — verified on `finance`:
`+0 nodes / +0 rels`.

**Where things live.** `database/neo4j/modules.php` is the manifest: per module, the script file, the
`extends` list, and one SQL query per CSV the script reads. The `.cypher` files never contain SQL and
the manifest never contains Cypher.

---

## 3. Rules the scripts share

**Keys.** New labels use the k12 shape — an integer native key named `<label>Id`: `staffId`,
`holidayId`, `examinationId`, `bookId`, `routeId`, `skillId`, `jobroleId`. Natural string keys keep
their own name (`onetsocCode`, `elementId`, `commodityCode`). Each gets
`CREATE CONSTRAINT <label>_<key>_unique IF NOT EXISTS`.

**Every node** carries `displayLabel`, `sub_institute_id` and `src` (the source table, which is what
makes a per-table verification possible).

**Casting.** `toInteger(trim(x))` for ids and counts, `toFloat(trim(x))` for amounts and rates, and
for text `CASE WHEN trim(coalesce(row.x,'')) = '' THEN null ELSE trim(row.x) END`. Neo4j 4.4 has no
`nullIf`; setting a property to null simply does not create it, which keeps empty strings out.

**Aggregates are grouped in SQL, not in Cypher**, so the CSV is already one row per edge. This is
what keeps the graph small and the export off the wire:

| source | rows | edges |
|---|---:|---:|
| `lms_online_exam_answer` (+2 variants) | 2,418,947 | 24,061 |
| `result_personalize_marks` | 1,308,379 | 54,710 |
| `hrms_attendances` | 356,872 | 15,962 |
| `onet_work_context` | 289,173 | 49,761 |
| `fees_breackoff` | 182,379 | 10,128 |
| `timetable` | 102,686 | 35,457 |
| `library_book_circulations` | 67,487 | 66,352 |

**The endpoint resolver.** Some parents exist twice: once keyed the k12 way (`Standard.stId`, an
integer) and once keyed `uid` by the earlier batch pipeline (`Standard:<tenant>:0:<id>`). Matching one
convention silently drops every edge whose parent lives under the other, so each such lookup is:

```cypher
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH row, coalesce(n1, n2) AS st WHERE st IS NOT NULL
```

It prefers the k12 node, falls back to the uid twin, creates neither, and yields one edge per row.
`T` is always `toString(toInteger(trim(row.sub_institute_id)))`.

Labels that exist **only** under uid — Institute (`Institute:<t>:0:<t>`), Division, Department, Role,
AcademicYear (`AcademicYear:<t>:<syear>:<id>`), Content, MappingType (`MappingType:0:0:<id>`,
tenant-global), GradeScheme, Batch — are matched on uid directly.

**The person resolver.** `tbluser` holds 4,771 people and the graph already had 118 of them as
`:Teacher`. Creating `:Staff` for all of them would put those 118 in the graph twice, so the `:Staff`
statement skips ids that are already `:Teacher` (4,653 created) and every HR/inventory/task edge
resolves:

```cypher
OPTIONAL MATCH (t:Teacher {teacherId: <id>})
OPTIONAL MATCH (s:Staff   {staffId:   <id>})
WITH ..., coalesce(t, s) AS person WHERE person IS NOT NULL
```

One person, one node, either way. Note for Neo4j 4.4: `(p:Teacher|Staff)` inside a pattern is Cypher 5
syntax and does **not** work here — the verify blocks use `WHERE p:Teacher OR p:Staff`.

**Student-keyed tables attach to `:StuDetail {sdId}`**, not `:Student {stuId}`. Measured 2026-09-03:
every `student_id` column in these modules matches `tblstudent.id` at 97–100% and
`tblstudent_enrollment.id` at 0–37%. In this schema a column called `student_id` is the master id.

**New semantics get new names.** Where a fact resembles a protected type but is not the same
measurement, it gets its own type rather than overloading one — `MASTERS_CHAPTER` (per-answer accuracy
against a chapter) beside `MASTERS` (score-based, against a concept); `TEACHES_SUBJECT` beside the
reference layer's `TEACHES` (Content→Concept); `ASSIGNED_EXAM` beside `HAS_RESULT`.

**Money is not projected.** Fee, payroll and purchase nodes and edges carry `authoritative: false`
(12,559 nodes). Structure and counts are in the graph; the ledger stays in MariaDB. No per-student
liability is derived from the fee schedule. Credentials, bank and identity columns
(`password`, `plain_password`, `otp`, `login_ip`, `account_no`, `ifsc_code`, `pan_no`, `aadhar_no`)
are not exported at all — this Neo4j is Community edition with one shared credential and no RBAC.

**Label collisions avoided.** `:Examination` (not `:Exam`) and `:OnetOccupation` (not `:Occupation`)
because the career-intelligence seed owns `:Exam {exam_id}`, `:Occupation {occupation_id}` and
`:Subject {code}` with curated string vocabularies that `CaiCoreService::CAI_CORE_QUERY` matches on.
Also `:StaffShift` vs `:TransportShift`, `:CoScholasticArea` vs `:CoScholasticParent`,
`:CounsellingQuestion`/`:CounsellingResult`/`:CounsellingCourse`, `:JobTask`, `:BookCopy`.

---

## 4. What each module loaded

| # | Module | Script | Loaded |
|---|---|---|---|
| 1 | people | `10_people.cypher` | `:Student` 176,499 (was 5,589) · HAS_STUDENT 176,495 · ENROLLED_IN 171,086 · IN_DIVISION 173,748 · STUDIES 103,404 · ATTENDANCE 52,672 · HAS_INCIDENT 4,810 · GUARDIAN_OF 10,008 |
| 2 | hr | `20_hr.cypher` | `:Staff` 4,653 · Holiday 143 · LeaveType 17 · PayrollType 47 · SalaryStructure 1,131 · TOOK_LEAVE 22,706 · TEACHES_SUBJECT 22,308 · TEACHES_CLASS 22,224 · SCHEDULED 7,686 · CLASS_TEACHER_OF 2,733 · ATTENDANCE_MONTH 15,953 · APPLIED_FOR_LEAVE 6,996 |
| 3 | result | `30_result.cypher` | `:Examination` 33,760 · ExamType 1,229 · Grade 394 · CoScholasticArea 1,356 · Activity 4,664 · ExamSchedule 5,216 · SCORED 54,701 · REPORTCARD 15,458 |
| 4 | assessment | `40_assessment.cypher` | `:QuestionType` 8 · counselling bank · Question→MappingType TAGGED_AS 178,079 · OF_QUESTION_TYPE 68,807 · MASTERS_CHAPTER 9,323 |
| 5 | operations | `50_operations.cypher` | Book 35,663 · BookCopy 36,132 · BORROWED 66,214 · Stop 2,812 · BOARDS_AT 60,597 · Vehicle 691 · Driver 739 · hostel · inventory · Visitor 869 · InwardDocument 5,773 |
| 6 | finance | `60_finance.cypher` | FeeTitle 824 · FeeHead 66 · ReceiptBook 9,736 · Bank 176 · APPLIES_TO 9,935 · LIABLE_FOR 7,405 · PAID 1,586 · PETTY_CASH 43 (all `authoritative:false`) |
| 7 | skills | `70_skills.cypher` | Skill 16,239 · JobRole 5,805 · JobTask 33,457 · REQUIRES_SKILL 173,395 · SQAA · O\*NET: OnetOccupation 1,016, OnetElement 627, OnetTask 19,281, UnspscCategory 4,262, ~250,000 rating edges |
| 8 | platform | `80_platform.cypher` | CalendarEvent 3,944 · Task 888 · TimeSlot 153 · COMMUNICATION 23,252 · ASSIGNED_TASK 884 |

Learners now reach: attendance 18,518 · electives 15,768 · report cards 15,361 · messages 15,385 ·
transport 16,964 · fees 4,668 · library 4,621 · exam marks 442 · chapter mastery 341.

### Two coverage ceilings, both upstream of the graph

- **`MASTERS_CHAPTER` is 9,323 from 24,061 candidate rows.** The rest reference chapter ids that are
  not in the graph. `chapter_master` holds ~114 rows while the question bank spans 582 chapters; this
  is a curriculum-tagging gap, not an ingest defect.
- **`REQUIRES_SKILL` is 173,395 from 174,268 pairs (99.5%)**, matching the measured name-resolution
  rate exactly — 4,594 of 4,596 job-role names and 15,420 of 15,474 skill names resolve.

### Things the source data does not support, recorded rather than guessed

- `dicipline.dicipline` is free text (`Check`, `Bad`, `good`, empty), not a FK — 0 of 19,642 rows
  resolve to `dicipline_master`. The category is an edge property, and the incident attaches to the
  academic year.
- `leave_applications` is named like an HR table but keys on `tblstudent.id` (18,925 of 20,396). It is
  the **pupil** leave module and hangs off `:StuDetail`.
- `result_co_scholastic_grades` has no student column — those are grade bands, so they are reference
  nodes, not marks.
- `hrms_departments_mapping` is a department master at tenant 0, and `tblemp_skills` has no user
  column. Both excluded.
- `s_jobrole_skills` joins on name strings with `sub_institute_id` NULL on every row, so the join
  ignores tenant; and `master_skills.name` is one constant across all 16,239 rows while `.title` is
  the real name.

---

## 5. Out of scope for this pass

255 tables exist in `vivek_erp` that are absent from the Aug-10 classification, of which 99 hold any
rows. They are mostly AI/PAL runtime scaffolding rather than ERP master data and were **not** loaded:

`hpbrain_*` (50 tables), `ai_*` (24), `talent_*` (37), `s_competency_*` (15), the PAL gamification and
concept-map stack (41), `task_management_*` (8), `workflow_*` (6), `org_*` (4), `eso_*`, `mcp_*`,
`ontology_*`, and the second-generation `lms_*` intelligence tables (14, nearly all empty).

`neo4j:registry-check` reports these as E1 errors ("exists in MariaDB but is not in the registry").
That is a pre-existing condition of the Aug-10 registry, not a fault introduced here.

---

## 5a. Live sync — the modules stay current on their own

Added 2026-09-04. Before this, the eight modules were a one-time load: a new staff member, a
book issue or an exam mark reached MariaDB and the graph never heard about it. The existing
live-sync pipeline covered 12 tables; it now covers **44**.

```
you use a feature  →  row saved in MariaDB  →  [DB trigger]  →  sync_log
                                                                    ↓
                                              projection re-reads MariaDB
                                                                    ↓
                                      node  → sync_log          (table_name = label)
                                      edge  → neo4j_sync_queue
                                                                    ↓
                                          neo4j:drain (every minute)
                                                                    ↓
                                                                 Neo4j
```

**132 triggers on 44 tables**, up from 36 on 12. Adding another is still a spec in
`config/neo4j.php` plus a name in `triggered`, then re-run the trigger migration.

### Four things this needed that the pipeline did not have

**1. Edge-only specs.** `hrms_emp_leaves`, `class_teacher`, `lms_question_mapping` and their kin
are links, not things. `'edges_only' => true` makes the projection emit the edge and no node —
otherwise 28,408 leave rows would each have MERGEd a node keyed on a row id that means nothing
in the graph.

**2. `:Staff` falls back to `:Teacher`.** `tbluser` is one table but two labels: the reference
ingest claimed 118 of those rows as `:Teacher` before `:Staff` existed. `StaffGraphProjection`
asks the graph which label already holds a person and keeps them there; `GraphSchema::siblingOf()`
lets an HR edge carrying a `tbluser.id` reach whichever one it is. Without it every edge for
those 118 people would have been silently dropped.

**3. An edge key — and the bug that proved it was needed.** `neo4j_sync_queue` described an edge
as (source, type, target) and nothing else, so the drain could only write
`MERGE (s)-[:TOOK_LEAVE]->(t)` — one edge per (person, leave type). The module scripts write
`MERGE (person)-[:TOOK_LEAVE {leaveId: N}]->(lt)` — one per leave. Testing found the consequence:
a single test leave was inserted and deleted, the property-less MERGE matched an **existing** edge
from the bulk load, and the DELETE removed a real leave that had nothing to do with the test.
One row of genuine history, lost to a shape mismatch.

`2026_09_04_140000_add_edge_key_to_neo4j_sync_queue` adds a nullable JSON `edge_key`; a
relationship spec declares it as `'key' => ['leaveId' => 'id']`. NULL keeps the old behaviour
exactly, which is right for an edge that can only exist once between two nodes. Five
relationships declare one today: `TOOK_LEAVE {leaveId}`, `ALLOCATED_LEAVE {year}`,
`MAPPED_TO {syear}`, `CLASS_TEACHER_OF {syear}`, `ASSIGNED_EXAM {assignmentId}`.

**4. A DELETE that matches nothing is success, not failure.** The drain threw whenever a
relationship statement matched zero rows. For a MERGE that means the endpoints are missing and
retrying is right; for a DELETE it means the edge is already gone, which is the desired end
state. It fired on every ordinary re-drain — the first attempt removed the edge, the second
could not find it — and after five retries parked the row as `failed` while the graph was in
exactly the right shape.

### What is NOT trigger-synced, and why

**Aggregate sources.** `hrms_attendances`, `result_personalize_marks`, `lms_online_exam_answer`,
`library_book_circulations`, `transport_map_student`, `fees_breackoff` and the SMS/WhatsApp logs.
One edge there summarises thousands of rows — 2.4M answers become 24k `MASTERS_CHAPTER` edges —
so a per-row trigger cannot produce a correct value without re-aggregating the whole group on
every insert. Refresh them by re-running the module: `neo4j:csv-export` then `neo4j:cypher`,
which is additive and safe at any time.

**The 30 `onet_*` tables.** Static US reference data, replaced by a bulk import once a year,
never edited by a user.

**`tbluser.last_login`.** Deliberately outside the watch list. It is the hottest column in the
schema and a sign-in changes nothing in the graph.

### A gap this surfaced

`tbluser#1` has `department_id = 25`, and `hrms_departments#25` exists in MariaDB — but the graph
holds only 5 of tenant 1's departments out of 113 total. So `IN_DEPARTMENT` cannot be built and
the drain correctly refuses to invent the parent. `:Department`, `:Role`, `:Division` and
`:AcademicYear` are uid-pipeline-owned and only partially loaded; edges into them will miss
wherever the parent was never exported. That is a coverage gap in the uid pipeline, not a sync
fault, and inventing a native-keyed twin to paper over it would be worse.

### Verified end to end

Insert / update / delete were exercised against a live row for both a node table
(`hrms_holidays`) and a join table (`hrms_emp_leaves`):

| | result |
|---|---|
| INSERT node | `:Holiday` created with properties and `displayLabel` |
| uid-only parent | `(:Institute {Institute:1:0:1})-[:HAS_HOLIDAY]->` resolved via the uid fallback |
| INSERT edge-only | `(:Teacher {id 1})-[:TOOK_LEAVE]->(:LeaveType)` — sibling resolution picked `:Teacher`, and 0 spurious nodes were created |
| UPDATE | rename propagated to both `holiday_name` and `displayLabel` |
| DELETE node | node removed; the person node it hung off survived |
| DELETE edge | only its own edge removed (22,706 → 22,705), the rest of the history intact |

After the run: 0 pending, 22 of 24 protected types byte-identical. The two that moved —
`BELONGS_TO` and `ASSESSES`, `+1` each — are question #700530, created in MariaDB by the live
application at 12:12 and correctly carried into the graph. That is the mechanism working.

---

## 6. Where this sits next to the other two Neo4j paths

Three things write to this graph, and they are deliberately separate:

| Path | What it is | Keyed on |
|---|---|---|
| **These module scripts** + `00_`/`01_` | Bulk projection of ERP master data | native integer keys (`sdId`, `stId`, `staffId`, …) |
| `neo4j:export` / `load` / `verify` (unchanged) | The uid batch pipeline; loaded Institute, Division, Topic, Content, MappingType, Guardian and the uid twins | `uid = Label:tenant:syear:id` |
| `App\Services\Graph` + `neo4j:drain` (unchanged) | Live MariaDB→Neo4j sync for 12 triggered tables, scheduled every minute | native keys, per `GraphSchema` |

`GraphSchema.php` is **not** extended with the new labels: the live sync only needs its 19, and the
new labels are batch-only. If a new label ever needs live sync, add it there and to
`config/neo4j.php`'s `projections`.

---

## 7. Neo4j-related files, after the 2026-09-04 cleanup

**Removed** (all dead — verified no live reference before deleting):

| File | Why |
|---|---|
| `app/Http/Controllers/Neo4jSyncController.php` + `app/Models/LmsDataContentNeo4j.php` | MERGEd on names, collapsing all 56 tenants; route disabled since 2026-08-10, model had no other user |
| `app/Http/Controllers/DataMigrationController.php` | Name-keyed nodes and unparameterised Cypher; route disabled since 2026-08-10 |
| `test_neo4j.php`, `seed_career_graph.php` | Root scripts with hard-coded `neo4j/admin`, no caller; the career seed lives in `SeedCareerGraphCommand` |
| `resources/views/newD3visualsnew.blade.php` | No route rendered it |
| `storage/logs/neo4j-*.log` (10) | Aug-2026 run logs, nothing read them |
| 5 routes in `routes/cal.php` | Bound to `Neo4jAssessmentController`, a class that exists nowhere — every one threw on dispatch |
| `use Graph1Controller` in `routes/web.php` | Class does not exist |
| The "Sync Data from MySQL to Neo4j" button in `welcomenew.blade.php` | Called the removed `/sync-neo4j`; the graph is fed by `neo4j:drain` on the scheduler, so there is nothing to trigger by hand |

**Repaired:** `/graph-data` and `/graph-data-learning-path` resolved to `neo4jGraph\GraphController`,
which has neither method — both returned 500 to the two views that fetch them. They now point at the
root `App\Http\Controllers\GraphController`, whose two queries were rewritten against the live schema
(they still matched the pre-migration `[:OFFERS]` edges) and whose `getLearningPath()` read an
undefined `$student`. They return 16 and 41 nodes respectively.

**Kept:** everything in `app/Services/Graph/`, all eight `neo4j:*` commands, both 2026-08-21
migrations, `config/neo4j.php`, `config/neo4j_graph.php`, the `database/neo4j/` generators and their
JSON snapshots, all `docs/neo4j-*.md`, and `docs/neo4j-backup-2026-08-10/`.

---

## 8. Runbook

```bash
php artisan neo4j:cypher --baseline                    # 1. fingerprint first, always

for m in people hr result assessment operations finance skills platform; do
  php artisan neo4j:csv-export --module=$m             # 2. MariaDB -> local CSV
  php artisan neo4j:cypher --module=$m --dry-run       # 3. look at it
  php artisan neo4j:cypher --module=$m --batch=2000    # 4. run it
done

php artisan neo4j:cypher --module=<m> --verify         # 5. counts, any time
```

Notes from doing it once:

- **`--batch=2000` for the heavy modules.** Bolt drops on long statements at 5,000
  (`StreamSocket::$stream` undefined is what that looks like). The runner retries four times and then
  stops with a resume line; `--from=N` picks up where it stopped.
- **Two exports are slow enough to need care.** `s_jobrole_skills_agg` originally resolved names to
  ids with two joins against derived tables and never finished — `s_jobrole_skills` has no index on
  `jobrole` or `skill`, so MariaDB scanned 176,460 rows against materialised maps over a WAN link. It
  now only deduplicates, and the graph resolves the names using `jobrole_name_idx` / `skill_title_idx`.
- **A `0 rows` export is a finding, not a warning.** `fees_other_collection_agg` and
  `result_reportcard_marks_agg` both came back empty first time — `is_deleted` is `'Y'`/`'N'` rather
  than NULL, and `result_reportcard_marks.term_id` is NULL on most rows so the exact-term join to
  `academic_year` matched nothing. Both were fixed in the manifest, not worked around in Cypher.
- **Neo4j rate-limits authentication.** Many short-lived connections in a row earns
  `AuthenticationRateLimit` for a minute or so. Prefer one artisan command over a loop of small ones.
