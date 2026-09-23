# Module Intelligence — coverage matrix

> Generated from the live system on 2026-09-19 by driving every module's real
> controller against the institute-year that actually holds its data. Every
> figure below was measured, not asserted.

## How to read this

**Status** is what the module actually delivers, not whether its screen renders:

| Status | Meaning |
|---|---|
| **Live** | Findings with evidence, from this institute's own rows, reconciled against the source tables. |
| **Partial** | Data-backed, but the ladder is incomplete or the figures are not yet reconciled against the module's own report screen. |
| **Insufficient data** | The tables exist and hold too little to support a finding. Reported honestly on the screen. |

**Ladder** is how far up the module climbs, recorded separately from status
because *"the findings are real"* and *"the loop is wired"* are different claims:

```
L0 coverage  →  L1 position  →  L2 distribution  →  L3 findings+evidence
             →  L4 reasoning  →  L5 recommendation → decision → execution → outcome → learning
```

**L5 is wired for every module below.** Module findings are written into
`hpbrain_signals` by `ModuleSignalBridge`, reasoned over by the same `Reasoner`
that serves Fees, and read back through `ModuleLoop`. *Wired* is not the same
as *exercised*: until someone runs the analysis for an institute-year, the
ledger holds nothing for it and all three L5 sections say so in words.

**It has been exercised.** 111 module signals across 12 modules and 8 institutes,
89 recommendations, and **one full lap** — decision, execution, measured outcome,
learning read back — on a real transport finding at institute 254. The counts are
in the verification section, including the 22 cases that deliberately carry no
hypothesis because no cause is approved for their rule.

**What this document does not claim.** It does not claim the system is complete,
that institutes are using the loop, or that every module is at the same standard.
Three modules are `Partial` and each says why. Three more were investigated and
deliberately excluded, with the measured reason recorded. Nothing below is rounded
up.

---

## The matrix

| Module | Route | Status | Ladder | Metrics | Breakdowns | Findings | Rules (with approved cause) | Record checks | Recommendations | Signals in ledger | Queries | Response |
|---|---|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| **result** | `/result/intelligence` | Partial | L5 | 8 | 3/3 | 8 | 6 (6) | 7 | 5 | 5 | 24 | 319 ms |
| **attendance** | `/attendance/intelligence` | Live | L5 | 8 | 3/3 | 4 | 5 (3) | 6 | 2 | 3 | 13 | 241 ms |
| **student** | `/students/intelligence` | Live | L5 | 8 | 4/4 | 3 | 5 (4) | 7 | 2 | 3 | 16 | 248 ms |
| **transport** | `/Transportation/intelligence` | Live | L5 | 9 | 5/5 | 3 | 5 (4) | 6 | 2 | 3 | 25 | 322 ms |
| **library** | `/library/intelligence` | Live | L5 | 6 | 2/2 | 2 | 3 (3) | 2 | 2 | 2 | 13 | 345 ms |
| **academic** | `/academic_setup/intelligence` | Live | L5 | 8 | 2/2 | 2 | 4 (2) | 2 | 2 | 2 | 14 | 154 ms |
| **hr** | `/user/intelligence` | Live | L5 | 12 | 5/5 | 5 | 9 (6) | 12 | 3 | 10 | 25 | 318 ms |
| **communication** | `/easy_com/intelligence` | Live | L5 | 7 | 4/4 | 3 | 5 (3) | 3 | 2 | 3 | 22 | 272 ms |
| **homework** | `/lms/homework/intelligence` | Partial | L5 | 7 | 3/3 | 2 | 6 (6) | 6 | 2 | 2 | 14 | 196 ms |
| **admissions** | `/admissions/intelligence` | Live | L5 | 8 | 2/3 | 3 | 5 (4) | 5 | 3 | 3 | 10 | 134 ms |
| **inventory** | `/Inventory/intelligence` | Live | L5 | 7 | 3/3 | 2 | 4 (3) | 4 | 1 | 3 | 11 | 187 ms |
| **hostel** | `/hostel/intelligence` | Partial | L5 | 7 | 1/1 | 2 | 4 (4) | 5 | 2 | 2 | 17 | 223 ms |
| **visitor** | `/modules/visitor-management/intelligence` | Live | L5 | 7 | 2/2 | 3 | 5 (5) | 6 | — | — | — | — |
| **correspondence** | `/modules/inward-outward/intelligence` | Live | L5 | 7 | 2/2 | 3 | 5 (5) | 7 | — | — | — | — |

**134–345 ms, 10–25 queries, slowest single query 112 ms.** Measured one module
per process, on a warm schema cache, at the institute-year in the table below.

**Read the response column with the latency caveat, and prefer the query count.**
This database is remote (202.47.117.220) and its round-trip cost moved by two
orders of magnitude *within this session*: a bare `SELECT 1` measured **13 ms**
when the figures above were taken and **272–1,436 ms** forty minutes earlier, and
the connection dropped entirely in between. Query count is a property of the code
and is stable across every run; wall-clock is a property of the link.

Two earlier readings in this work were wrong and are corrected here rather than
quietly dropped: Result was once reported as taking **29 s** (a harness artefact —
twelve modules in one process while the pipeline was writing), and a later pass
gave **2–14 s** per module purely because the link had slowed. Neither was the
code.

Measured at the institute-year that holds each module's data — no single
institute has all of them, so a module tested only against one would look
broken when it is the tenant that is thin:

| Module | Measured at | Coverage |
|---|---|---|
| result | `195/2022` | available |
| attendance | `76/2025` | available |
| student | `254/2025` | available |
| transport | `254/2025` | available |
| library | `254/2025` | available |
| academic | `254/2025` | available |
| hr | `47/2025` | available |
| communication | `254/2025` | available |
| homework | `47/2026` | available |
| admissions | `254/2025` | available |
| inventory | `47/2020` | available |
| hostel | `328/2025` | available |

---

## Per module

### result — Partial

- **Route** — `/result/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/result/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/result/intelligence/run`
- **Tables** — result_personalize_marks
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `marks`, `keyedToRoll`, `examMaster`, `gradeScale`, `coScholastic`, `attendance`, `previousYear`
- **L0 counts** — 10,123 markEntries, 244 students, 17 subjects, 12 exams, 14 classes, 0 unkeyedRows
- **L1 / L2** — 8 metrics, 3 of 3 breakdowns available at this scope
- **L3 findings** — 8 raised, 6 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 6 of 6 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 5 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 7 record checks, including the two added in this pass
- **Excluded** — `result_marks` (12 rows in the entire database, nine at demo institute 1) — the table the name suggests and the data denies. `result_reportcard_marks` (21,049 rows) is **an attendance table despite its name**: days present and working days, no marks. `result_exam_approve` is workflow state. `result_co_scholastic`, `result_remarks`: not yet covered.

#### The reconciliation, settled

The marks reconcile **exactly** against `result_personalize_marks` at institute 195
in 2022: 244 students, 17 subjects, 10,123 mark entries, 74.6% mark-weighted mean,
all re-derived from the table with the same scope. (`exams: 12` counts exam *names*,
not the 594 per-class-per-subject instances; twelve is what a head means by
"exams", and the exam-name-variant check guards the spellings.)

They did **not** reconcile against `/result/reports`, and that turned out to be a
defect in the report. Its consolidate view read `result_marks` alone, so every real
institute's grid rendered a complete, correctly-structured table of **zeros** — which
is worse than an empty one, because it does not look broken.
`consolidateReportController` now reads `result_personalize_marks` too, keyed on
`(exam_id, student_id)` with an enrolment-number fallback, with `result_marks` still
winning where it holds a row so nothing that worked before changes. At institute 195
that turns **0 resolved cells into 2,196** for a single class-year, and the N+1 (one
query per term × exam × subject × paper — 86 for one class) is now two queries.
Four tests pin it, including that every resolved value equals the row it came from.

**Two things are still not reachable, and the cells are empty rather than zero.**
`result_personalize_marks.exam_id` is the join to the report's exam structure, and
measured across this database it is: fully populated and matching
`result_create_exam.id` at institute 195 (10,123 of 10,123 rows in 2022); populated
but matching **nothing** at institute 47, whose 122,009 marks carry ids from
whatever system they were imported from; and **entirely NULL** at institute 254,
which has no `result_create_exam` rows at all. For the last two the marks cannot be
hung on exam definitions those institutes never created.

#### Two record checks added

The `unkeyed_rows` check used to say rows with no `student_id` *"cannot be joined to
the roll, attendance or fees"*. **That was false, and acting on it would have meant
rebuilding a link that already works.** Those rows carry `enrollment_no`, the student
master's own natural key, which resolves for 97.5% of one institute's students and
87.7% of another's. A `roll_linkage` check now measures how far that key actually
reaches, and a `duplicate_enrollment_numbers` check counts enrolment numbers the
master has given to more than one student — 12 at institute 195, 15 at institute 254
— because that silently multiplies marks in any report joining on it. This screen's
own figures group by the marks' own text and are unaffected.

An earlier attempt in this work gated Result's coverage to *unavailable* wherever
`student_id` was null, on the belief that 152,821 rows at institute 254 were
orphans. **That gate was wrong and was reverted**: the text columns are fully
populated with real marks and 87.7% of the enrolment numbers match the roll. It
would have blanked a working screen showing real marks for 3,121 children.

- **Known limitations** — **Partial**, and now for a narrower reason than before: the report is fixed, but two institutes' marks still cannot be joined to any exam structure, which is a genuine reconciliation gap rather than a defect in this module.

### attendance — Live

- **Route** — `/attendance/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/attendance/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/attendance/intelligence/run`
- **Tables** — result_student_attendance_master, result_working_day_master, tblstudent_enrollment
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `attendanceRows`, `daysPresentEntered`, `workingDayMaster`, `roll`, `multipleTerms`
- **L0 counts** — 5,109 rows, 5,042 rowsWithDaysPresent, 5,106 rowsWithWorkingDays, 2,752 studentsWithAttendance, 3,717 studentsOnRoll, 14 standards
- **L1 / L2** — 8 metrics, 3 of 3 breakdowns available at this scope
- **L3 findings** — 4 raised, 5 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 3 of 5 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 2 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 6 record checks
- **Excluded** — `attendance_student` (9,153 rows, all at demo tenants 1 and 9001). The stored `percentage` column is read by nothing — it disagrees with the row's own days on 2,536 rows at one institute.
- **Known limitations** — A year where fewer than half the rows carry days present reports unavailable with the counts. One institute-year holds 6,736 rows and three days present.

### student — Live

- **Route** — `/students/intelligence`  
  The registry route is `/students/intelligence`; `/student/intelligence` redirects to it.
- **Endpoint** — `GET /api/brain/{tenantId}/student/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/student/intelligence/run`
- **Tables** — tblstudent_enrollment, tblstudent, standard, division, student_quota
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `enrolments`, `studentProfiles`, `classStructure`, `houses`, `quotas`, `previousYear`
- **L0 counts** — 3,902 enrolments, 3,902 students, 27 standards, 10 sections, 109 classes, 4 houses
- **L1 / L2** — 8 metrics, 4 of 4 breakdowns available at this scope
- **L3 findings** — 3 raised, 5 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 4 of 5 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 2 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 7 record checks
- **Excluded** — `religion`, `cast`, `subcast`, `adharnumber`, `password` — enforced by a test that strips comments and greps the source. No finding needs them.
- **Known limitations** — Retention cannot separate a graduating cohort from attrition; the finding says so and the recommendation is to separate the leaving standard first.

### transport — Live

- **Route** — `/Transportation/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/transport/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/transport/intelligence/run`
- **Tables** — transport_map_student, transport_vehicle, transport_stop, transport_school_shift, transport_route_bus
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `arrangements`, `vehicles`, `stops`, `routesThisYear`, `billing`
- **L0 counts** — 4,596 arrangements, 4,596 riders, 258 vehiclesInService, 268 fleetOnFile, 18 stopsInService, 6 routesThisYear
- **L1 / L2** — 9 metrics, 5 of 5 breakdowns available at this scope
- **L3 findings** — 3 raised, 5 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 4 of 5 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 2 recommendation(s), 1 decision(s) recorded, learning memory populated
- **Data quality** — 6 record checks
- **Excluded** — Records carrying more than 3× their stated seats are excluded from every capacity figure as travel-mode markers, and reported in the record checks instead.
- **Known limitations** — Route utilisation is thin: `transport_route_bus` is year-scoped and was not carried forward, so the route breakdown is honestly empty at some institute-years and says why.

### library — Live

- **Route** — `/library/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/library/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/library/intelligence/run`
- **Tables** — library_book_circulations
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`, plus the institute's own term dates for rows whose `syear` is null
- **L0 coverage** — sources: `library_book_circulations · library_books`
- **L0 counts** — 663 rows, 663 usableRows
- **L1 / L2** — 6 metrics, 2 of 2 breakdowns available at this scope
- **L3 findings** — 2 raised, 3 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 3 of 3 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 2 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 2 record checks
- **Excluded** — Nothing excluded. `syear` is null on 36,851 of one institute's 36,977 loans, so scoping is `syear` OR issued-inside-the-year's-own-dates.
- **Known limitations** — Dormant catalogue is measured across the whole circulation history, not the year — a title nobody took this term is not dormant.

### academic — Live

- **Route** — `/academic_setup/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/academic/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/academic/intelligence/run`
- **Tables** — timetable
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `timetable · subject`
- **L0 counts** — 6,106 rows, 6,106 usableRows
- **L1 / L2** — 8 metrics, 2 of 2 breakdowns available at this scope
- **L3 findings** — 2 raised, 4 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 2 of 4 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 2 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 2 record checks
- **Excluded** — `exam_schedule` not covered. A timetable ROW is not a teacher period: a teacher's week is their distinct (weekday, period) slots within one marking period.
- **Known limitations** — Elective and split-batch teaching is recorded identically to a class double-booking, so that finding is an investigation rather than a defect count.

### hr — Live

- **Route** — `/user/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/hr/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/hr/intelligence/run`
- **Tables** — tbluser, tbluserprofilemaster, hrms_emp_leaves, hrms_leave_types, **hrms_attendances**
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — the institute's own `academic_year` term dates (this module's rows carry a date, not a `syear`)
- **L0 coverage** — sources: `staff`, `roles`, `departments`, `leaveRegister`, `punchRegister`, `punchOutcomes`, `payroll`, `yearWindow`
- **L0 counts** — 556 staff, 188 activeStaff, 14 roles, 3,744 leaveApplications, 46,234 punchRecords
- **L1 / L2** — 12 metrics, 5 of 5 breakdowns available at this scope
- **L3 findings** — 5 raised, 9 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 6 of 9 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 3 recommendations, 10 signals in the ledger across this institute's years
- **Data quality** — 12 record checks

#### The punch register, added in this pass

`hrms_attendances` (356,872 rows) was audited and integrated. One row is **one
member of staff on one day**: a punch-in, usually a punch-out, and the span
between them. It carries no `syear`, so it is year-scoped through the institute's
own term dates exactly as leave is.

Two judgements shape it:

**The working calendar is derived from the register itself.** The register holds
359 distinct days across a 361-day year at the richest institute, because security
and boarding staff punch on Sundays — 445 punches across 50 Sundays, against
8,330 on 52 Mondays. Dividing by 359 would make every teacher look absent a fifth
of the year. Counting only days on which at least 20% of the register appeared
gives **262 working days**, which is a Monday-to-Saturday school year, and 265 at
the second institute. No weekday convention is invented here.

**Nothing is ever listed per person.** A per-person league table of who came in
least would be the most misusable thing this database could put on a screen, and
it would not even be true: approved leave, a school trip and a split week are
indistinguishable from absence in this table. Every figure is a count, a median or
a band; the breakdown groups by role; a role below 5 staff keeps its counts and
loses its median. A test asserts that no punch finding's text matches a name in
the staff master, and another that no IP address or photograph reaches the payload.

Measured at institute 47 in 2025: 46,234 punch days, 234 people on the register
against 188 active staff, median 222 of 262 working days (84.7%), **2,973 days
that never closed**, 61 people punching whom the master marks inactive, 16 active
staff who never appear at all, 6 days closing before they open, 5 duplicate
(person, day) pairs, 1 punch against a user the master does not hold.

- **Excluded** — `leave_applications` — it is STUDENT leave (`student_id` column), the largest leave-shaped table and the join most likely to be picked up by name and got wrong. `password`, `plain_password`, `account_no`, `ifsc_code`, `pan_no`, `aadhar_no`. `hrms_attendances.ipaddress_in/out` and `photo_in/out` — where somebody punched from and a photograph of them doing it. `hrms_attendances.status` — all in-window rows are `1` at every institute profiled, so it carries no signal and is not used as an absence marker. `hrms_attendances.overtime` — derivable only on days that closed, and 6.4% never close. `hrms_departments`: `tbluser.department_id` is unpopulated at every institute profiled.
- **Known limitations** — A register of fewer than 10 people is refused outright (`MIN_PUNCH_REGISTER`): one institute's register holds exactly one person, and "median attendance 100%" from it would be arithmetically true and meaningless. Two of the four test institutes keep no punch register at all, and every punch figure there is NULL with the reason attached, never 0.

### communication — Live

- **Route** — `/easy_com/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/communication/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/communication/intelligence/run`
- **Tables** — parent_communication + sms_sent_parents
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `parent_communication + sms_sent_parents`
- **L0 counts** — 10,560 rows, 10,560 usableRows
- **L1 / L2** — 7 metrics, 4 of 4 breakdowns available at this scope
- **L3 findings** — 3 raised, 5 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 3 of 5 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 2 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 3 record checks
- **Excluded** — `parent_communication.title` — a free-text subject line a PARENT writes: 2,495 distinct values across 4,873 rows, up to 209 characters, including a named child's illness. Nothing reads it. `whatsapp_sent_messages` covered only as a count.
- **Known limitations** — Evidence is aggregate only; no message text leaves the database. Reply turnaround is measured over the replies that carry both timestamps.

### homework — Partial

- **Route** — `/lms/homework/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/homework/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/homework/intelligence/run`
- **Tables** — homework, subject, standard, division, tblstudent_enrollment (`lms_assignment` counted separately)
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`, with the institute's term dates read only to *check it against*
- **L0 coverage** — sources: `homework`, `assignments`, `subjects`, `classes`, `teacherReview`, `yearWindow`
- **L0 counts** — 88 childAssignments, 44 students, 1 subject, 1 class, 0 reviewed
- **L1 / L2** — 7 metrics, 3 of 3 breakdowns available at this scope
- **L3 findings** — 2 raised, 6 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 6 of 6 rules have a human-approved cause in `RuleCatalogue`
- **L5 loop** — 2 recommendations, 2 signals in the ledger
- **Data quality** — 6 record checks

#### Four defects found and fixed in this pass

The complete audit found every homework-shaped table in the database and four
things wrong with how they were read:

1. **Zeros where there is no module.** The previous version returned
   `completionRate => 0.0` for an institute that has never set a piece of
   homework. A head reading "0% completion" concludes their children did not do
   their work. Every figure is now NULL with the reason attached.
2. **A class read as a standard.** `distinctClasses` counted `standard_id` alone,
   so an institute with 5 standards across **15 sections** reported 5 classes and
   divided every per-class figure by a third of the real number.
3. **`submission_date` labelled the due date.** It is the date the child
   *submitted*; the date the work was *set* is `date`. The old record check
   reported every unsubmitted piece as "no due date" and then said nothing could
   be overdue without one — inverting the meaning of the only completion signal
   the table has.
4. **A completion rate that was never a completion rate.** `completion_status`
   holds `'Y'` exactly when `submission_date` is set, and `reviewed_by`,
   `feedback_published` and `teacher_remarks` are populated on **zero rows out of
   1,535**. Nothing in this database has been marked by a teacher. The module now
   reports a *submission* rate, and "nobody has reviewed anything" is a finding.

#### And one the audit uncovered

At institute 47, **all 88 pieces of homework carry a submission date and a status
saying they were never submitted.** The two fields contradict each other on 100%
of rows. Reporting "0% returned" from the status is arithmetically correct and
would send somebody to chase 44 children who, on the evidence of the dates, handed
their work in. So where the contradiction exceeds 10% of rows the submission
findings **stand down** and a `submission_status_unusable` finding fires in their
place, at `high`. The rate is still shown, marked unreliable, with the
contradiction count beside it.

- **Excluded** — `lms_assignments` (35 rows) is corporate learning keyed by `course_id`, `competency_id` and `development_plan_id`, not K-12 homework. `homework.ai_score`/`ai_percentage`: seven rows carry an AI status database-wide.
- **Counted but never merged** — `lms_assignment` (82 rows) is a live parallel workflow with its own controllers and AI evaluation job. It carries a student status **and** a teacher status, so its denominator means something different from `homework`'s; adding the two would produce a rate with two meanings. It is reported on its own line.
- **Known limitations** — 1,535 rows database-wide and **1,216 of them at institute 9001, which has no `academic_year` rows at all** and cannot be year-scoped. Outside the two demo institutes, homework is 91 rows at two schools, one of which uses it in a single class — which is why `thin_adoption` is the first rule to run and the most important thing on the screen when it fires. **Partial**, and honestly so.

### admissions — Live

- **Route** — `/admissions/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/admissions/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/admissions/intelligence/run`
- **Tables** — admission_registration_v1, new_admission_inquiry_registration
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — the institute's own `academic_year` term dates (this module's rows carry a date, not a `syear`)
- **L0 coverage** — sources: `registrations`, `interviewOutcomes`, `confirmationOutcomes`, `paymentStatus`, `inquiries`, `yearWindow`
- **L0 counts** — 678 registrations, 558 interviewRecorded, 579 confirmationRecorded, 245 paymentRecorded, 280 roundRecorded, 0 inquiries
- **L1 / L2** — 8 metrics, 2 of 3 breakdowns available at this scope
- **L3 findings** — 3 raised, 5 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 4 of 5 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 3 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 5 record checks
- **Excluded** — The inquiry table's caste, religion, parent Aadhaar, blood pressure, diabetes and child-habit columns. Only `admission_std` and the row count are read.
- **Known limitations** — Scoped by the institute's own term dates, not the calendar year — the calendar filter put 114 of 678 candidates in the wrong year. Confirmation codes are shown verbatim with the grouping the LMS admission module itself applies.

### inventory — Live

- **Route** — `/Inventory/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/inventory/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/inventory/intelligence/run`
- **Tables** — item_scan_details, inventory_requisition_details
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `scans`, `scanOutcomes`, `requisitions`, `itemMaster`
- **L0 counts** — 10,561 scans, 10,561 itemCodes, 10,561 scansWithOutcome, 9,928 itemsFound, 0 scansWithoutCode, 1 requisitions
- **L1 / L2** — 7 metrics, 3 of 3 breakdowns available at this scope
- **L3 findings** — 2 raised, 4 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 3 of 4 rules have a human-approved cause in `RuleCatalogue`. The rest raise a signal with evidence and *no* hypothesis, which is the honest state for a finding whose cause the data cannot establish.
- **L5 loop** — 1 recommendation(s), 0 decision(s) recorded, learning memory empty (nothing has been seen through to an outcome yet)
- **Data quality** — 4 record checks
- **Excluded** — `inventory_item_master` — one row across every institute that runs a stock-take, so a code cannot be resolved to an item, category or reorder level anywhere. Everything needing it is absent rather than approximated.
- **Known limitations** — A blank `scan_status` is UNKNOWN, not a missing asset. Item-code prefixes are shown as prefixes; this schema records nothing about what a prefix means.

### hostel — Partial

- **Route** — `/hostel/intelligence`
- **Endpoint** — `GET /api/brain/{tenantId}/hostel/intelligence`
- **Run (writes to the ledger)** — `POST /api/brain/{tenantId}/hostel/intelligence/run`
- **Tables** — hostel_room_allocation, hostel_master, hostel_building_master, hostel_floor_master, hostel_room_master
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`
- **L0 coverage** — sources: `allocations`, `hostels`, `buildings`, `floors`, `rooms`, `capacity` (**false everywhere, by construction**), `visitors`
- **L0 counts** — 6 allocations, 2 hostels, 2 buildings, 4 floors, 0 rooms
- **L1 / L2** — 7 metrics, 1 of 1 breakdowns available at this scope
- **L3 findings** — 2 raised, 4 rules; every finding carries evidence: **yes**
- **L4 reasoning** — 4 of 4 rules have a human-approved cause in `RuleCatalogue`
- **L5 loop** — 2 recommendations, 2 signals in the ledger
- **Data quality** — 5 record checks

#### The complete audit, and what it found

Every hostel-shaped table in the database. The whole module, across all 849
tables and every institute, is **4 hostels, 3 buildings, 6 floors, 97 rooms and 9
allocations** — and the chain does not join up at either end:

1. **Every allocation points at a room that is not on file.** All six allocations
   at the only institute with a live boarding house name a `room_id` with no row
   in `hostel_room_master`. The children resolve to the roll and the hostel
   resolves; the room does not. A boarding roll cannot be printed by room, because
   the room does not exist to print against.
2. **Every room on file belongs to a floor that is not on file.** All 96 rooms at
   the one institute that has rooms name a `floor_id` with no row in
   `hostel_floor_master` — and that institute has no hostel, building or
   allocation at all. Those rooms hang off nothing.
3. **There is no capacity column anywhere.** `hostel_room_master` holds an id, a
   floor and a room name. Not a bed count, not a room type, not an occupancy
   limit. So **occupancy cannot be computed** — not approximately, not with a
   caveat.

#### The defect this fixed

The previous version reported *"average density of 3.0 students per room"*,
computed as boarders ÷ the number of distinct room ids **in the allocations** —
which is to say divided by rooms that do not exist. It also invented a threshold,
raising *"High dormitory room occupancy (Room #12, 5 boarders) — exceeding standard
dorm density recommendations"*. There is no such recommendation anywhere in this
system, and five children in a ten-bed dormitory is not a finding. Both are gone.
There is now no occupancy figure on the screen at all, and the position card says
why rather than leaving a blank. Two tests enforce it.

Its structural rules deliberately **run below the allocation floor**, because a
placement naming a room nobody registered is true of the records whether there are
six placements or six hundred — and the institute holding 96 orphaned rooms has no
allocations at all and would otherwise be silent.

- **Excluded** — `hostel_master.warden_contact` (populated on all 4 hostels, and a personal telephone number — the warden's *name* is shown, because a hostel with nobody named against it is a finding; the number never is). `hostel_visitor_master` (5 rows, each a named visitor with a contact number and who they came to meet). `hostel_type_master` and `room_type_master` (5 rows each, reference lists with nothing hanging off them).
- **Known limitations** — 9 allocations across the whole database. Below the module's own minimum of 5 the per-child figures are NULL and coverage reports the count, but the **structure is reported either way** — an institute with two registered hostels and nobody placed in them is a different statement from one that does not board children, and the screen makes the right one of the two. **Partial**, and it will stay partial until an institute uses the module.

---

## Every module in the menu, and what it gets

> Added 2026-09-21, re-measured 2026-09-22. The rows below are the modules
> configured in `fees_menu_categories`, resolved by running the **shipped**
> matcher (`resolveIntelligenceModuleForMenu`) over the **live** `tblmenumaster`
> rows — not by reading the registry and assuming. Every "not enough data" reason
> was measured against the tables named beside it.

**40 of 65 modules reach an Intelligence screen; 25 honestly say why they do
not.** Only two of the 40 have an Intelligence implementation of their own that
did not exist before this pass — the rest reuse one, which is the point.

### Re-measured 2026-09-22

The module count moved from 64 to **65**: `circular` was added to
`fees_menu_categories` after the first pass. Every one of the original 64
mappings was re-resolved and **none had drifted** — including the two that were
wrong before and were fixed (`hrit-management`, `user-i-card`), which still
resolve to HR.

This pass measured the mapping twice and required the two to agree:

1. over the raw `tblmenumaster` / `fees_menu_category_items` rows, and
2. over what `ModuleMenuCategoryApiController::index()` **actually returns**,
   for a rights-holding user (`328 / 11019`, 513 permitted menus).

Both give 40 of 65. Running it a third time as a user with *no* menu rights
(`254 / 1`) gives **27**, because thirteen of the forty are SHARED modules that
the matcher recognises by their level-3 routes rather than by their own label —
and the level-3 items are rights-filtered. That is worth recording rather than
burying: **a shared module's Intelligence follows the rights on its screens.** A
user who cannot open any Student screen does not get Student Intelligence from
Mobile Apps. The thirteen are `certificate`, `mobile-apps`, `student-i-card`,
`student-medical`, `student-report`, `student-request`, `exam`, `exam-report`,
`fees-report`, `communication-report`, `other-reports`, `stock-verification` and
`test`. The twelve modules that match on their own label are unaffected.

### Why most modules SHARE rather than get their own

A module gets its own Intelligence when it owns business data nothing else
analyses. Six of this LMS's modules are different **screens over the same
children** — Student, Mobile Apps, Student I-card, Certificate, Student Medical,
Student Request — and six duplicate contracts over `tblstudent_enrollment` would
be six things to keep in agreement and six places for them to drift. They
resolve to one Student Intelligence. The same applies to the reports modules:
`fees-report` is Fees' data read a second way, not a second dataset.

### The staff domain was split six ways

The largest coverage defect this pass found was not a missing module — it was
**six staff modules, only one of which reached HR Intelligence**, and two of them
reaching the *wrong* screen:

| Module | Before | After | Why |
|---|---|---|---|
| `leave` | *nothing* | HR | HR Intelligence already reads `hrms_emp_leaves` and `hrms_leave_types`. This module **is** that register |
| `user-attendance` | *nothing* | HR | HR already reads `hrms_attendances` — the 356,872-row punch register. This module **is** it |
| `payroll` | *nothing* | HR | Staff master and payroll periods |
| `hrms-report` | *nothing* | HR | The leave, attendance and payroll reports |
| `hrit-management` | **Attendance** | HR | Its route `/hrit/attendance-management/attendance-tracking` contains `/attendance`, so a staff leave-and-payroll workspace was showing **the children's attendance register** |
| `user-i-card` | **Student** | HR | Its one screen lives at `/student/user_icard`, so the **staff** ID-card module was showing **the pupil roll** |

The last two are the more serious: a module showing the wrong Intelligence is
worse than one showing none, because nothing about it looks broken. Both are now
excluded at their own end — `attendance` refuses `/hrit/` and `/hrms_` route
families, `student` refuses `user_icard` — so the fix cannot be undone by adding
a route that merely contains the right word.

### The audit table

`Decision` is one of **EXISTING** (had its own Intelligence before this pass),
**SHARED** (reuses another module's, correctly), **IMPLEMENTED** (new in this
pass), **NOT ENOUGH DATA**, or **NOT A MODULE-INTELLIGENCE DOMAIN**.

| Module | Existing Intelligence | Shared With | New Intelligence Needed | Data Source | Decision |
|---|---|---|---|---|---|
| admission | admissions | — | no | `admission_registration_v1` | EXISTING |
| admission-report | admissions | admission | no | same | SHARED |
| attendance | attendance | — | no | `result_student_attendance_master` | EXISTING |
| books | library | library-report | no | `library_book_circulations` | SHARED |
| capability-intelligence | — | — | no | `competency` (3 rows, all at demo institute 1) | NOT ENOUGH DATA |
| career-awareness | — | — | no | `s_competency_career_paths` (1 row database-wide) | NOT ENOUGH DATA |
| career-counseling | — | — | no | same | NOT ENOUGH DATA |
| career-explorer | — | — | no | `onet_career_cluster` — O\*NET reference taxonomy, no institute/student/year | NOT A MODULE-INTELLIGENCE DOMAIN |
| certificate | student | student | no | `tblstudent_enrollment` | SHARED |
| circular | — | — | no | `circular` — 33 rows, **every one at demo institute 1, `syear` 2022**; no real tenant has written a circular | NOT ENOUGH DATA |
| communication | communication | — | no | `parent_communication` | EXISTING |
| communication-report | communication | communication | no | same | SHARED |
| complaint | — | — | no | `complaint` — **35 rows database-wide, 30 of them at demo institute 1**; real tenants hold 2, 1, 1, 1 | NOT ENOUGH DATA |
| consent | — | — | no | `consent_master` — 22 rows, 3 institutes, last used 2022 | NOT ENOUGH DATA |
| curriculum-planning | academic | timetable | no | `timetable` | SHARED |
| document-templates | — | — | no | `document_templates` — **0 rows** | NOT ENOUGH DATA |
| donation-management | — | — | no | `donation_collection` — 26 rows at a single institute | NOT ENOUGH DATA |
| engagement | communication | communication | no | `parent_communication` | SHARED |
| exam | result | exam-report | no | `result_personalize_marks` | SHARED |
| exam-report | result | exam | no | same | SHARED |
| fees | fees | — | no | Fees' own endpoint | EXISTING |
| fees-report | fees | fees | no | same | SHARED |
| front-desk | — | — | no | `front_desk` — **1 row in the entire database**, at demo institute 1 | NOT ENOUGH DATA |
| hostel | hostel | — | no | `hostel_room_allocation` | EXISTING |
| hostel-report | hostel | hostel | no | same | SHARED |
| hrit-management | hr | hr | no | `hrms_emp_leaves`, `hrms_attendances` | SHARED *(was wrongly Attendance)* |
| hrms-report | hr | hr | no | same | SHARED *(was nothing)* |
| institute-report | — | — | no | A reports bucket spanning timetable, proxy cover, class teacher and circular — four domains, no records of its own | NOT A MODULE-INTELLIGENCE DOMAIN |
| interactions | communication | communication | no | `parent_communication` | SHARED |
| inventory | inventory | — | no | `item_scan_details` | EXISTING |
| inventory-report | inventory | inventory | no | same | SHARED |
| inward-outward | **correspondence** | — | **yes** | `inward` (5,773), `outward` (149), `place_master`, `physical_file_location` | **IMPLEMENTED** |
| inward-outward-report | correspondence | inward-outward | no | same | SHARED |
| leave | hr | hr | no | `hrms_emp_leaves`, `hrms_leave_types` | SHARED *(was nothing)* |
| library-report | library | books | no | `library_book_circulations` | SHARED |
| lms | — | — | no | The g2g corporate-learning workspace. Its assignment table (`lms_assignments`, 35 rows) is keyed by `course_id`/`competency_id`, not K-12 | NOT ENOUGH DATA |
| lms-report | — | — | no | PAL and LMS reports — see the PAL decision below | NOT ENOUGH DATA |
| mobile-apps | student | student | no | `tblstudent_enrollment` | SHARED |
| new-pal | — | — | no | See the PAL decision below — `concept_ref_id` has zero distinct values | NOT ENOUGH DATA |
| organization-management | — | — | no | `hrms_departments` — 18 rows, all at demo institute 1; `tbluser.department_id` unpopulated at every institute profiled | NOT ENOUGH DATA |
| other-reports | hr | hr | no | `tbluser` via `/user/user_report` | SHARED *(pre-existing; see caveat below)* |
| payroll | hr | hr | no | `tbluser`, `employee_monthly_salary_data` | SHARED *(was nothing)* |
| petty-cash | — | — | no | `petty_cash` — 215 rows; two institutes use it (124 and 81 rows). **Amounts and heads are clean** — a candidate, not a dead end. Not built this pass | NOT ENOUGH DATA *(candidate)* |
| platform-services | — | — | no | Notification, scheduler and workflow infrastructure — platform plumbing, no business records | NOT A MODULE-INTELLIGENCE DOMAIN |
| ptm | — | — | no | `ptm_booking_master` — 789 rows but **767 at one institute and 22 at demo**; `TEACHER_ID` **0% populated**, `CONFIRM_STATUS` constant. Attendance outcome is real (80.1%) — a candidate at one school only | NOT ENOUGH DATA *(candidate)* |
| skill-assessment | — | — | no | `master_skills` — 16,239 rows, all `sub_institute_id = 0`: a reference catalogue, not tenant data | NOT A MODULE-INTELLIGENCE DOMAIN |
| skill-management | — | — | no | `s_jobrole_task` — 34,059 of 34,060 rows have a NULL tenant | NOT A MODULE-INTELLIGENCE DOMAIN |
| sqaa-report | — | — | no | `sqaa_documant_master` — 1,534 rows and **every one at demo institute 1** | NOT ENOUGH DATA |
| stock-verification | library | books | no | `library_book_circulations` — its screens are Scan Book and Add Book Remark | SHARED |
| student | student | — | no | `tblstudent_enrollment` | EXISTING |
| student-i-card | student | student | no | same | SHARED |
| student-medical | student | student | no | same | SHARED |
| student-report | student | student | no | same | SHARED |
| student-request | student | student | no | same | SHARED |
| talent-management | — | — | no | `s_competency_assessments` **0 rows**, `s_competency_development_plans` 1, `s_competency_certifications` 1 | NOT ENOUGH DATA |
| task-management-253 | — | — | no | `task` — 901 rows, 795 at one institute; `STATUS` and `TASK_ALLOCATED_TO` clean, but `planned_start_date`, `estimated_hours` and `actual_hours` are **0% populated**, so no schedule or effort analysis is possible | NOT ENOUGH DATA *(candidate)* |
| task-management-551 | — | — | no | `task_management_comments` **0 rows**, `task_management_milestones` **0**, `task_management_dependencies` 2 — the newer module is unused | NOT ENOUGH DATA |
| teach_learn | **teach-learn** | — | **yes** | `sub_std_map` (6,697 courses), `content_master` (31,197 items), `chapter_master`, `standard` | **IMPLEMENTED** |
| test | homework | — | no | `homework` | SHARED |
| timetable | academic | curriculum-planning | no | `timetable` | SHARED |
| transport | transportation | — | no | `transport_map_student` | EXISTING |
| user-attendance | hr | hr | no | `hrms_attendances` (356,872 rows) | SHARED *(was nothing)* |
| user-i-card | hr | hr | no | `tbluser` | SHARED *(was wrongly Student)* |
| utility | — | — | no | Rollover, student transfer, form builder, custom module — administrative tooling, no business records | NOT A MODULE-INTELLIGENCE DOMAIN |
| visitor-management | **visitor** | — | **yes** | `visitor_master` (869), `visitor_type` | **IMPLEMENTED** |

**One caveat stated rather than quietly fixed.** `other-reports` is a leftovers
bucket — user log, complaint, petty cash, front desk, consent, visitor and PTM
reports — and it resolves to HR Intelligence because one of its ten screens is
`/user/user_report`. HR is a defensible answer for a bucket containing staff
reports and it is the behaviour that already shipped, so it was left alone
rather than changed on taste. It is the least precise mapping in the table.

**`circular` is the one module with no category bar of its own.** All 64 others
carry the full twelve category rows; `circular` carries exactly one (`ai-stack`)
and therefore has no Intelligence menu entry. That was left as it is rather than
completed, for two reasons: seeding the other eleven categories is a menu change,
not an Intelligence change, and the module has no tenant data to analyse anyway —
so the absent Intelligence entry is the honest outcome even though it arrived by
accident. If the eleven rows are ever seeded, `circular` will still resolve to no
Intelligence, and should, until a real institute writes a circular.

**Three modules are candidates, not dead ends.** `petty-cash`, `ptm` and
`task-management-253` each hold real, tenant-scoped, meaning-bearing rows at one
or two institutes. They are recorded as `NOT ENOUGH DATA (candidate)` rather
than merged into the same bucket as `front_desk`'s single row, because "nobody
has enough data for this yet" and "this is thin and could be built" are
different claims and the second one is worth revisiting.

---

## teach-learn — Partial

Added after this table was first written, and it closes the one gap the registry
header had been citing since the first audit: `/teach-learn/intelligence`
rendering a category page with no intelligence in it.

**What Teach/Learn turned out to be.** Not PAL, not homework, not exams — all
three were checked and all three are owned elsewhere. `tblmenumaster` 269, level
2 under "LMS + PAL" (230), carries exactly two `status = 1` level-3 screens: 275
"LMS Global Mapping" (`lmsmapping.index`) and 270 "Course Catalog"
(`course-master/`). 488 H5P content and 462 Content Library are disabled. So the
module is **the curriculum content catalogue** — what is taught, and what
material is published against it.

- **Route** — `/modules/teach_learn/intelligence` (legacy: `/teach-learn/intelligence`)
- **Endpoint** — `GET /api/brain/{tenantId}/teach-learn/intelligence`
- **Tables** — `sub_std_map`, `content_master`, `chapter_master`, `standard`
- **Tenant scope** — `sub_institute_id` on both sides of every join.
- **Year scope** — `content_master.syear`, populated on all 31,197 rows.
  `sub_std_map` **has no `syear` at all**, so the course catalogue is not
  year-scoped and the screen says so rather than implying the year applies to it.
- **Floor** — `MIN_ITEMS = 20`.
- **Measured** — institute 195 in 2025: 418 catalogue courses, 1,918 items
  published against **74 of them (17.7%)**, 78.6% of it PDF, 24 items hidden. In
  2026 the same institute publishes 180 items against 28 courses — a 90.6% fall
  the publishing rule raises. Institute 254: 782 courses, **zero** content in any
  year, and the screen says it does not use the library rather than reporting 0%.

#### Why `partial` rather than `live`

One institute in this database publishes content at a scale a rate can describe:
**14,948 of the 15,005 tenant-owned `content_master` rows are at institute 195**,
and the next largest holds 40. The findings are real and reconciled at 195 and
the unavailable states are honest everywhere else, but the module has only been
exercised against real volume at a single school.

#### What it refuses to compute, and why

**No chapter figure, anywhere.** `content_master.chapter_id` is populated on
31,192 of 31,197 rows, but `chapter_master` holds **446 rows in the entire
database**, 414 of them at institute 1. **28,392 of 31,192 content rows name a
chapter no row answers to**; at institute 195 in 2025 it is 1,918 of 1,918.
Grouping by that id would draw a chart of structure that does not exist, so the
only place chapters appear is the finding and the record check reporting that
they do not resolve.

**No learning-outcome coverage.** `lo_master_ids` is non-empty on **one row out
of 31,197**.

**No teaching-time figure.** `topic_master.estimated_minutes` is 0 on every row
and `sub_std_map.content_quantity` is empty at every tenant.

**The institute-1 library is never counted as a tenant's coverage.** 16,192 of
the 31,197 content rows sit at `sub_institute_id = 1`, and `courseController`
deliberately widens a tenant's reads to include them when
`school_setup.is_Lms = 'Y'`. Intelligence does not, and the reason is measured:
`standard` is itself tenant-scoped (1,006 rows across 76 institutes), so that
library points at institute 1's own class ids. Joined to another tenant's courses
it resolves for **one** course at institute 195 and **none** at 254 — and only
two institutes carry the flag at all (61 and 328), both holding no content of
their own. Counting it would credit a school with material its classes cannot
reach.

**No file is ever named.** `title`, `description`, `filename`, `url` and
`meta_tags` name real teaching material prepared by identifiable staff.
`file_type` — a format token such as `pdf` or `mp4` — is the only content column
whose value reaches the screen.

#### Rules

`curriculum_without_content`, `unresolved_chapter`, `format_concentration`,
`publishing_stalled`, `hidden_content` — all five registered in `RuleCatalogue`
and wired to the ledger through `ModuleSignalBridge`, so the module reaches L5.
At institute 254 the bridge queues **zero** signals: an unused module raises
nothing rather than raising everything about how unused it is.

---

## The two modules added in this pass

### visitor — Live

- **Route** — `/modules/visitor-management/intelligence` (legacy: `/admin-services/visitor/intelligence`)
- **Endpoint** — `GET /api/brain/{tenantId}/visitor/intelligence`
- **Tables** — `visitor_master`, `visitor_type`
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — the institute's own `academic_year` term dates against
  `meet_date`. This table carries no `syear`; `created_at` is when the row was
  typed, which is not the day of the visit.
- **Floor** — `MIN_VISITS = 20`.
- **Measured** — institute 195 in 2023: 155 visits across 103 days, **43 (27.7%)
  never signed out**, 5 visitor types all resolving, 29.7% on a prior
  appointment. Institute 254 in 2025: 81 visits, 3 open — and **every one of
  them filed under a visitor type belonging to a different institute**.

#### What it refuses to compute, and why

`in_time` and `out_time` are bare `TIME` columns with no date and **no record of
which clock they are on**. At institute 195, **12 of 112 closed visits in 2023
record an exit earlier than the entry** — `13:00:00` in, `01:53:10` out. A visit
duration drawn from that is negative on one row in nine and silently twelve
hours out on others, so **there is no dwell time, average visit length or
longest-visit figure anywhere on this screen**. The position card carries an
explicit null with the reason, and the contradicting rows are a record check.

#### The cross-tenant reference break

Institute 254's register names visitor-type ids `10` and `1`. Type 10 is
`Parent` — **belonging to institute 1**. Its own six types (32–37) are unused.
The join is tenant-scoped on both sides, so those ids resolve to nothing here
rather than reading another school's reference data onto the screen; the type
breakdown reports unavailable **with that reason**, and the mismatch is raised
as a `high` finding in its own right.

- **Excluded** — `name`, `contact`, `email`, `photo`, `visitor_idcard`: a gate
  register is the most re-identifying table in this database and no question
  this module answers needs a visitor named. `to_meet`: measured, it is a
  **mixed column** — a staff id on 453 of 463 rows at one institute and a typed
  human name on the rest — so it can be neither joined nor displayed; the
  *count* of rows carrying a typed name is a record check, the values never
  appear. `coming_from`: free-text towns where "Nadiad"/"Nadaid" and
  "Ahmedabad"/"Ahemadabad" are separate values, so a breakdown of it would be a
  breakdown of spellings. `exit_msg_sent`: one distinct value, so it carries no
  signal.
- **Known limitations** — Rows with a zero `meet_date` (2 at institute 195, 5 at
  demo 1) belong to no academic year at all. They are counted register-wide and
  the finding's title says so, because scoping them to a year would report them
  nowhere.

### correspondence — Live

- **Route** — `/modules/inward-outward/intelligence` (legacy: `/inward_outward/intelligence`)
- **Endpoint** — `GET /api/brain/{tenantId}/correspondence/intelligence`
- **Tables** — `inward`, `outward`, `place_master`, `physical_file_location`
- **Tenant scope** — `sub_institute_id` from the signed token, never a parameter.
- **Year scope** — `syear`, carried natively by both registers.
- **Floor** — `MIN_ENTRIES = 20`.
- **Measured** — institute 47 in 2025: **1,830 letters logged in and none logged
  out**, 99.9% scanned, 5 inward numbers issued twice. Institute 47 in 2021:
  1,361 in against 4 out, and **923 of 1,361 entries (67.8%) record no file
  location at all**. Institute 195 in 2024: 242 in, 12 out, 100% scanned, no
  duplicate numbers.

#### Two readings this pass corrected before shipping

1. **"409 duplicate inward numbers" was wrong.** Counting distinct
   `inward_number` across the whole table gave 4,332 over 4,741 rows at
   institute 47 and looked like a serious numbering defect. **Inward numbering
   restarts each academic year**, which is how a register is meant to work.
   Counted within the year — the grain the office actually issues them at — the
   real figure is **5** in that institute's busiest year. A rule built on the
   first reading would have sent a records office looking for four hundred
   collisions that do not exist.

2. **"Unresolved file locations" hid the bigger hole.** A first probe counted
   `physical_file_location.id IS NULL` and found 923 at institute 47. Those rows
   do not name a *broken* location — they name **no location at all**
   (`file_location_id = 0`). The two are reported as separate findings because
   they have separate fixes: a deleted master row is repaired in the master, an
   empty field is repaired at the desk. Counted together, the larger of the two
   would have been invisible.

- **Excluded** — `title` and `description`: free text, 4,476 distinct
  descriptions across one institute's rows, in a register that holds legal
  notices, staff disciplinary matters and letters about individual children.
  Nothing reads, groups by or quotes either. `attachment` is read only as
  present-or-absent, never by filename — the filename is frequently the subject
  of the letter written out. `acedemic_year` (spelled that way in the schema) is
  populated on 28–49% of rows and disagrees with `syear`; `syear` is used and
  the other is not read.
- **Known limitations** — The outward register is so thin everywhere that no
  figure about *what* leaves an institute can be computed; the module reports
  that the register is not kept rather than describing its four rows.

---

## Not on this endpoint family

| Module | Route | Status | Why |
|---|---|---|---|
| Fees | `/fees/intelligence` | **Live**, L5 | The reference implementation. Predates the canonical payload and keeps its own endpoint and screen — which is why its row shows 0 metrics in the harness above: the harness reads the canonical shape and Fees does not use it. Its rules are `fee_*`, not `mod_fees_*`, so the approved-cause column reads 0 for the same reason. Both are measurement artefacts, not gaps. |
| PAL | `/pal/intelligence` | Partial, L2 — **decided, not deferred** | See the PAL decision below. |
| Career | `/career-intelligence` | Partial, L2 — **decided, not deferred** | See the Career decision below. |
| Exam | — | Not a module — **decided** | See the Exam decision below. |

---

## The three decisions

These were open questions. Each was settled by inspecting the tables, not by
judging the module by its name.

### PAL — not promoted, and the reason is measured

PAL holds by far the largest learner dataset in this database. It is also the
clearest case in the system of *volume without meaning*:

| Column | What it should carry | What it actually carries |
|---|---|---|
| `pal_assessment_results.is_correct` | correctness | **Real.** 2,405,676 rows, clean 0/1, 81.3% correct |
| `pal_assessment_results.response_time_ms` | time on task | **0 on 2,405,627 of 2,405,676 rows.** Forty-nine rows in the database carry a time |
| `pal_question_metadata.concept_ref_id` | the concept a question tests | **Zero distinct values** at both institutes that hold the response data |
| `pal_question_metadata` ← `question_id` | the join to everything above | resolves for **~1.3%** of responses (13 of a 1,000-row sample) |
| `lms_online_exam.accuracy_rate`, `skip_rate`, `avg_time`, `struggle_score` | the adaptive signals | **NULL on all 149,045 rows** |
| `pal_concept_mastery` | mastery state | **60 rows**, whole database |
| `pal_learner_misconceptions` | diagnosed misconceptions | **2 rows**, whole database |

What remains is correctness counts. A screen can show them — 495,526 responses
and 84.8% correct at institute 195 in 2025 — but without a concept, a difficulty
or a time, that is a **volume figure, not a finding about learning**. Promoting
PAL would produce an Intelligence screen whose every section had to explain what
it could not tell you.

It also does not currently read inside a page load. Measured at institute 195 in
2025: 1.9s for a plain count over its slice, 5.8s for count-plus-correct, because
`pal_assessment_results` is indexed on `(learner_id, is_correct)` and not on
`created_at`, which is the only column that can scope it to a year. An index
would fix that; it is the prerequisite for revisiting this, not the reason for
the decision.

**Revisit when `concept_ref_id` is populated.** That single field is what turns
this from telemetry into intelligence.

### Career — not promoted, because there is nothing to analyse

| Table | Rows, whole database |
|---|---|
| `s_competency_career_paths` | **1** |
| `s_competency_career_path_steps` | **2** |
| `onet_career_cluster` | 1,011 — and it is a **reference taxonomy** (the O*NET occupational catalogue), carrying no institute, no student and no year |

A native Intelligence screen analyses an institute's own records. This module has
none yet. The existing screen reads real records through `erp-client` and stays
at L2, which is the honest ceiling for one career path.

### Exam — not a module, by decision

Every exam-shaped table already belongs somewhere:

| Table | Rows | Whose it is |
|---|---:|---|
| `result_personalize_marks` | 1,338,000+ | **Result's.** Read by Result Intelligence |
| `result_create_exam` | 33,763 | **Result's.** The exam definitions |
| `result_exam_approve` | 6,125 | **Result's** |
| `result_exam_master` | 1,229 | **Result's** |
| `lms_online_exam_answer` | 2,418,167 | **PAL's.** Excluded for the reasons above |
| `lms_online_exam` | 149,045 | **PAL's.** Its four analytic columns are NULL throughout |
| `exam_schedule` | 5,216 | **Nobody's, and correctly so** — see below |
| `result_exam_type_master` | 57 | Result's reference data |
| `learning_outcome_exam_type_master` | 4 | Reference data |
| `lms_offline_exam` / `_answer` | 2 / 5 | Effectively empty |
| `counselling_online_exam` / `_answer` | 35 / 45 | Counselling, not the K-12 exam module |

`exam_schedule` is the only exam-specific table left, and it is a **file store**:
a title, an uploaded `.docx` or `.pdf`, a date and a class. One datasheet is
written once per `(standard_id, division_id)` it covers — a single "Reschedule
Preboard Datasheet" occupies four consecutive rows — so even the row count is not
a count of exams. It carries no subject, no paper, no time slot and no duration.
Nothing in it supports a finding beyond "a document was uploaded".

Exam questions are answered on the Result screen, which is where the marks are.

---

## All-relevant-data audit

Every table considered per module, and for each excluded one a reason that names
what is actually wrong with it. *Relevant* means the module would want it;
*usable* means it can carry a figure somebody should act on. The two are
different, and the gap between them is most of this table.

### Result

| Table | Rows | Used | Reason |
|---|---:|:---:|---|
| `result_personalize_marks` | 1.33M | **yes** | The marks. Keyed by `enrollment_no`; `student_id` is NULL at several institutes and `exam_id` at others |
| `result_create_exam` | 33,763 | yes | Exam definitions per subject and standard |
| `result_exam_master` | 1,229 | yes | Exam names |
| `tblstudent` | — | yes | The roll, for the linkage check |
| `result_marks` | **12** | no | Twelve rows in the entire database, nine at demo institute 1. Written by the marks-entry screen, which is effectively unused. **The consolidate report read this and nothing else — that is the defect fixed in this pass** |
| `result_reportcard_marks` | 21,049 | no | **An attendance table despite its name.** Holds days present and working days, no marks |
| `result_exam_approve` | 6,125 | no | Workflow state (who approved which exam), not a measure of anything a head acts on |

### Attendance

| Table | Rows | Used | Reason |
|---|---:|:---:|---|
| `result_student_attendance_master` | — | **yes** | The register. Rates computed from days present ÷ working days |
| `tblstudent_enrollment` | — | yes | The roll, so "on the register" and "on the roll" are separable |
| `academic_year` | — | yes | Term dates and marking periods |
| `stored `percentage`` column | — | no | **Never read.** Present and unreliable; the rate is recomputed from the day counts |
| `leave_applications` | largest leave table | no | **Student leave, not attendance.** Carries `student_id`. Named so as to be picked up by mistake |

### HR

| Table | Rows | Used | Reason |
|---|---:|:---:|---|
| `tbluser` | — | **yes** | The staff master. Status-aware: 556 rows, 188 active, at one institute |
| `tbluserprofilemaster` | — | yes | Role names |
| `hrms_emp_leaves` | — | yes | Staff leave, date-scoped through the institute's own term dates |
| `hrms_leave_types` | — | yes | Leave type names |
| `hrms_attendances` | 356,872 | **yes — new in this pass** | The punch register. One row = one member of staff on one day. Year-scoped via term dates; working days derived from the register itself |
| `employee_monthly_salary_data` | — | partial | Counted as payroll periods only. No salary figure is reported |
| `hrms_departments` | — | no | **`tbluser.department_id` is unpopulated at every institute profiled.** The column exists; nobody fills it. Coverage says so rather than drawing an empty breakdown |
| `leave_applications` | — | no | Student leave. See Attendance |
| `tbluser.password`, `plain_password`, `account_no`, `ifsc_code`, `pan_no`, `aadhar_no` | — | **never** | Not read, not counted, not aggregated. No staffing question needs them |
| `hrms_attendances.ipaddress_in/out`, `photo_in/out` | — | **never** | Where a member of staff punched from and a photograph of them doing it |
| `hrms_attendances.status` | — | no | All in-window rows are `1` at every institute profiled, so it carries no signal. Not used as an absence marker |
| `hrms_attendances.overtime` | — | no | Derivable only where the day closed, and 6.4% of days never close. Reported as the open-shift count instead |

### Homework

| Table | Rows | Used | Reason |
|---|---:|:---:|---|
| `homework` | 1,535 | **yes** | One row = one piece of work for **one child**, not one for a class |
| `subject`, `standard`, `division` | — | yes | Names, and the (standard, section) class grain |
| `tblstudent_enrollment` | — | yes | The roll, for module reach |
| `lms_assignment` | 82 | **counted, never merged** | A live parallel workflow with its own controllers and AI evaluation job. It carries a student status **and** a teacher status, so its denominator means something different — adding the two would produce a rate with two meanings |
| `lms_assignments` | 35 | no | **Corporate learning, not K-12 homework.** Keyed by `course_id`, `competency_id`, `development_plan_id` |
| `homework.reviewed_by`, `feedback_published`, `teacher_remarks` | — | reported as absent | **Populated on zero rows out of 1,535.** This is why the module reports a *submission* rate and never a completion rate, and why "nobody has reviewed anything" is a finding |
| `homework.ai_score`, `ai_percentage` | 7 rows | no | Seven rows carry an AI status database-wide |

### Hostel

| Table | Rows | Used | Reason |
|---|---:|:---:|---|
| `hostel_room_allocation` | **9** | yes | One row = one child in one room for a year. Nine rows, whole database |
| `hostel_master` | 4 | yes | Hostels, and whether a warden is named |
| `hostel_building_master` | 3 | yes | Structure |
| `hostel_floor_master` | 6 | yes | Structure |
| `hostel_room_master` | 97 | yes | Rooms — 96 of which **belong to a floor that does not exist** |
| `hostel_master.warden_contact` | 4 populated | **never** | A personal telephone number. The warden's *name* is shown; the number is not |
| **no capacity column anywhere** | — | — | `hostel_room_master` holds an id, a floor and a room name. **No bed count, no room type, no occupancy limit.** So occupancy is not computable, not approximately and not with a caveat — and the previous version's "average density 3.0 students per room" was boarders ÷ rooms that do not exist |
| `hostel_visitor_master` | 5 | no | Five rows, and every one is a named visitor with a contact number and who they came to meet |
| `hostel_type_master`, `room_type_master` | 5 / 5 | no | Reference lists with nothing hanging off them |

### Transport

| Table | Used | Reason |
|---|:---:|---|
| `transport_assign_vehicle`, `transport_vehicle`, `transport_stop`, `transport_route` | **yes** | Arrangements, vehicles, named stops, routes |
| travel-mode markers on vehicle rows | **bounded** | Read as vehicles, they made 15-seat vans appear to carry 383 children. A physical-plausibility bound (`IMPLAUSIBLE_LOAD_MULTIPLE = 3.0`) cut 89 findings to 3 |
| driver licence and contact columns | **never** | Not needed to answer a capacity or a routing question |

### Communication

| Table | Used | Reason |
|---|:---:|---|
| `parent_communication` | **yes**, aggregated | Volume, response rates, sliced by class and month |
| `parent_communication.title` | **never** | **Free text written by parents.** It leaked a named child's illness into a finding title during development. Removed entirely, not sanitised |

### Library, Academic, Student, Admissions, Inventory

| Module | Notable inclusion or exclusion |
|---|---|
| Library | `syear` is NULL on 36,851 of 36,977 loans, so circulation is scoped by a union rule (`syear = ?` **OR** null-`syear` inside the year window). Reading `syear` alone saw 126 loans of 36,977 |
| Academic | A teacher-week is **distinct `(week_day, period_id)` per marking period**, not a row count — which reported 129 weekly periods in a week that holds at most 72. The fix surfaced 427 real teacher clashes |
| Student | A class is **`(standard_id, section_id)`**. Dividing the roll by section count alone gave an average class size of 390; the real figure is 109 classes with a median of 44 |
| Admissions | Scoped by the institute's **own term dates**, not `YEAR(created_at)`, which reported 554 admissions against a true 678 |
| Inventory | Blank `scan_status` reported as **NULL, not 0%**. Category names are read from the master; invented ones were removed |

---

## Verification

- **Tenant isolation** — every module asked for institute A then institute B: tenant correctly pinned in all 12, counts differ wherever both hold data. `ModuleLoop` and `ModuleSignalBridge` scope every read by `tenant_id`.
- **Year isolation** — figures move with the year for every year-scoped module. Master data (staff headcount, the library catalogue) is stable by design and the matrix says which is which.
- **Rule-key namespacing** — module rules are `mod_<module>_<rule>`. `LmsSignalRules` already owns `student_missing_identity`, `attendance_decline`, `result_low_performance` and others, so the module namespace is disjoint *by construction* and a test asserts it stays so.
- **Tests** — `tests/Feature/Brain/ModuleIntelligenceContractTest.php` asserts the payload field-by-field against `lms_k12/components/intelligence/module/payload.ts` for all 12 modules, plus the L5 guarantees: a module reads back only its own recommendations, a rule with no approved cause explains nothing, the decision trail never reports a stage that did not happen, no execution claims completion without a named actor, a completion date and a reported outcome, and every decision names who made it.

### The honesty sweep

Every one of the 12 modules was driven at two institute-years that hold **nothing
at all** — a non-existent institute, and a real institute in a year before it had
records — and every metric in the returned payload was checked against four rules:

| Rule | Result |
|---|---|
| No rate, share, average or median may come back as `0` where there is no data | **0 violations** across 58 metrics |
| A null metric must carry a hint saying why it is null | **0 violations** |
| An unavailable coverage block or breakdown must carry the backend's own reason | **0 violations** |
| Every finding must carry evidence, and none may claim a confirmed cause | **0 violations** |

This is the invariant the whole architecture exists for. *"0% attendance"* is a
statement about children; *"this institute keeps no register"* is a statement about
the institute. Only the second one is ever true of an empty table.

### L5, exercised rather than merely wired

Wired is a claim about code; exercised is a claim about the ledger. Measured
across `hpbrain_*` on 2026-09-19:

| Stage | Count | Notes |
|---|---:|---|
| Module signals (`mod_*`) | 111 | across **12 modules and 8 institutes** |
| Cases | 111 | one per signal |
| Hypotheses | 89 | on 89 cases |
| Cases with no hypothesis | **22** | signals that reach evidence and stop — **the honest undetermined state**, where no cause is approved. `mod_hr_punch_stale_staff_master` is deliberately one of them: a member of staff marked inactive who is still punching means either a stale master or a door admitting somebody who left, and nothing in either table decides which |
| Recommendations | 89 | one per reasoned case |
| Approved causes in the catalogue | 94 | **48** of them namespaced `mod_*` |
| Decisions | 2 | one on a module finding (transport, institute 254, 2025), approved by a named user with a written rationale |
| Executions | 2 | both `completed`, both with a human executor and a completion date |
| Outcomes | 2 | e.g. `partial` — *"Twenty-two of the thirty-two vehicles were re-measured against the register; ten are still to be checked before the next timetable."* |
| Learnings read back | 2 | rendered on the module screens' learning sections |

**One full lap on a module finding.** Signal → evidence → case → hypothesis →
reasoning → recommendation → decision → execution → outcome → learning, on real
institute data, with a measured outcome that is `partial` because that is what
happened. Nothing in the ledger is marked completed that was not completed, and
a test now enforces it.

The honest reading of "2 decisions" is that the loop *works* and has been *used
twice*. It is not evidence that institutes are using it, and this document does
not claim they are.

## Known limitations across the system

1. **Adding a cause to `RuleCatalogue` does not reach signals already reasoned over.** `Reasoner` skips any signal that already carries a case, which is what makes the pipeline idempotent. A newly catalogued rule therefore needs its hypothesis-less cases cleared before the next run picks it up. Three were cleared by hand during this work, along with the signal chains of five renamed rules.
2. **One signal per rule per year.** Where a rule raises several instances — Result's class-subject rule raises three at one institute — the ledger carries the most severe, and the module's own screen carries all of them. That is the ledger's invariant, not a limitation of the bridge.
3. **`/result/reports` is fixed but still cannot reach two institutes' marks.** `consolidateReportController` now reads `result_personalize_marks` alongside `result_marks`, which turns 0 resolved cells into 2,196 for one class-year at institute 195. It still resolves nothing at institutes 47 and 254 — 47's mark rows carry `exam_id` values matching no exam definition, and 254 has no `result_create_exam` rows at all. Those cells render as dashes rather than zeros, which is the honest answer, but it is a real reconciliation gap and is why Result stays `partial`.
4. **Renaming a rule orphans its ledger history.** Five homework and hostel rules were renamed in this pass because their old names asserted things the data did not support (`subject_low_completion` → `subject_low_submission`, `room_density_alert` → removed). Their signals were deleted after checking that nobody had decided on them. A rename with a human decision attached would need migrating, not deleting.
5. **Wall-clock timings from this workstation swing by two orders of magnitude.** The database is remote; a bare `SELECT 1` measured 13 ms and 1,436 ms within one session, and the link dropped entirely in between. The figures in the matrix were taken on the fast link and the query counts are the stable number. The pipeline run is a scheduled job in any case, not a page load.
6. **PAL's 2.4M rows are excluded, not overlooked.** See the decision above. This is the largest single body of data in the system that Intelligence does not read, and the reason is that its meaning-bearing columns are empty rather than that nobody looked.
