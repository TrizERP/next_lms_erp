# Dropdowns API (Result module cascading selects)

Controller: `app/Http/Controllers/api/result/DropdownApiController.php`
Base URI: `/api/result/dropdowns/...` (all endpoints are `GET`)

Auth: every endpoint requires `Authorization: Bearer <user_token>` obtained from
`POST /api/api-login` (email/password). Optional overrides on any request:
`syear`, `term_id`, `sub_institute_id` (query/body) — otherwise the current
academic term of the token's institute is used.

Response envelope (all endpoints):

```json
{ "success": true, "message": "Success", "data": [ { "id": 1, "name": "..." } ], "errors": null }
```

Validation failures return `422 {"success":false,"message":"Validation Failed","errors":{...}}`.

Source of business logic: `app/Http/Controllers/AJAXController.php` (web routes
`routes/result.php` lines 131–148). Pluck-map methods are delegated 1:1 and the
`{id: name}` map is normalized to `[{id, name}]`. Referer-dependent and
broken-SQL methods are replicated — see the per-endpoint notes and the
"Behavioral simplifications" section at the bottom.

---

## 1. GET /api/result/dropdowns/standards

Standards for one or more grades, honouring class-teacher / subject-teacher /
student scoping. Replaces `AJAXController@getStandardList` (which detects the
calling module from `HTTP_REFERER`) — pass `module_name` explicitly instead.

| Param | Type | Required | Notes |
|---|---|---|---|
| grade_id | string/int | yes | csv allowed, e.g. `3` or `3,4` |
| module_name | string | no | one of `student_homework, marks_entry, dicipline, lmsExamwise_progress_report, questionReport, parent_communication, question_paper, co_scholastic_marks_entry, student_homework_submission`. When given (and matching), class-teacher scoping is bypassed in favour of subject-teacher scoping (same list as the web AJAX). |

Scoping (mirrors web intent):
- `module_name` in the list above → restrict to subject-teacher standards (teacher's `allocated_standards`, else timetable standards).
- else, class teacher → restrict to `classTeacherStdArr` (hydrated from `class_teacher` by the middleware).
- else, subject teacher / supervisor with `allocated_standards` → restrict to those standards. A teacher with no timetable and no allocation gets an empty list (same as web).
- Student profile → only the student's own standard.
- Admin / Super Admin → all standards of the grade(s).

Sample: `GET /api/result/dropdowns/standards?grade_id=3`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 12, "name": "Standard 5" }, { "id": 13, "name": "Standard 6" } ],
  "errors": null }
```

## 2. GET /api/result/dropdowns/divisions

Divisions mapped to one or more standards (`std_div_map` join `division`),
honouring class-teacher / subject-teacher / student scoping. Replaces
`AJAXController@getDivisionList` (referer-dependent) — pass `module_name`.

| Param | Type | Required | Notes |
|---|---|---|---|
| standard_id | string/int | yes | csv allowed |
| module_name | string | no | same list/effect as `/standards` |

Scoping: class-teacher divisions (`classTeacherDivArr`) unless `module_name`
bypasses it; subject-teachers without `allocated_standards` requesting a
**single** standard are narrowed to divisions they actually teach for that
standard (timetable subquery, same as web); Student profile → own division.

Sample: `GET /api/result/dropdowns/divisions?standard_id=12`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 3, "name": "A" }, { "id": 4, "name": "B" } ],
  "errors": null }
```

## 3. GET /api/result/dropdowns/subjects

Subjects mapped to standard(s) via `sub_std_map` (returns `display_name`).
Replaces `AJAXController@getSubjectList` (referer-dependent).

| Param | Type | Required | Notes |
|---|---|---|---|
| standard_id | string/int | yes | csv allowed |
| division_id | int | no | used to refine the teacher-timetable branch (single standard, teacher without `allocated_standards`) |
| allow_grades | string | no | `Yes` (default) filters `sub_std_map.allow_grades = 'Yes'` (gradable subjects — what marks-entry/exam screens show); pass `No` to list all mapped subjects |

Teacher with a single `standard_id`: subjects limited to `allocated_standards`
mappings, or (no allocation) to subjects on their timetable for
`standard_id` + `division_id` — exactly the web branches.

Sample: `GET /api/result/dropdowns/subjects?standard_id=12&division_id=3`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 5, "name": "Mathematics" }, { "id": 7, "name": "Science" } ],
  "errors": null }
```

## 4. GET /api/result/dropdowns/all-subjects

Every subject mapped to a standard — no teacher scoping, no allow_grades
filter. Delegates to `AJAXController@getAllSubjectList`.

| Param | Type | Required |
|---|---|---|
| standard_id | int | yes |

Sample: `GET /api/result/dropdowns/all-subjects?standard_id=12` → same shape as /subjects.

## 5. GET /api/result/dropdowns/exam-names

Online-exam question papers attempted by students of the given class + subject
(joins `question_paper`, `lms_question_master`, `lms_online_exam_answer`).
Replicates `AJAXController@getExamsList` with a SQL fix (see caveats).

| Param | Type | Required |
|---|---|---|
| grade_id | int | yes |
| standard_id | int | yes |
| division_id | int | yes |
| subject_id | int | yes |

Sample: `GET /api/result/dropdowns/exam-names?grade_id=3&standard_id=12&division_id=3&subject_id=5`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 45, "name": "Unit Test 1 - Maths", "online_exam_ids": 118, "question_paper_name": "Unit Test 1 - Maths" } ],
  "errors": null }
```

## 6. GET /api/result/dropdowns/exam-masters

Exam-master titles (`result_exam_master.ExamTitle`) for a term + standard.
Delegates to `AJAXController@getExamsMasterList`.

| Param | Type | Required |
|---|---|---|
| term_id | int | yes |
| standard_id | int | yes |

Sample: `GET /api/result/dropdowns/exam-masters?term_id=2&standard_id=12`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 8, "name": "First Terminal Exam" }, { "id": 9, "name": "Unit Test 1" } ],
  "errors": null }
```

## 7. GET /api/result/dropdowns/subjects-by-create-exam

Subjects that already have created exams (`result_create_exam`) for the class.
Delegates to `AJAXController@getSubjectByCreateExam`; `sub_institute_id` and
`syear` are forced from the authenticated session (the web AJAX trusts request
params for these).

| Param | Type | Required |
|---|---|---|
| grade_id | int | yes |
| standard_id | int | yes |
| division_id | int | yes |

Sample: `GET /api/result/dropdowns/subjects-by-create-exam?grade_id=3&standard_id=12&division_id=3`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 5, "name": "Mathematics", "subject_id": 5, "subject_name": "Mathematics" } ],
  "errors": null }
```

## 8. GET /api/result/dropdowns/exams-by-create-exam

Exam-master entries that have `result_create_exam` rows for the class +
subject. Replicated (web SQL is broken — see caveats); returns the exam id and
`result_exam_master.ExamTitle` plus the subject fields the web select had.

| Param | Type | Required |
|---|---|---|
| grade_id | int | yes |
| standard_id | int | yes |
| division_id | int | yes |
| subject_id | int | yes |

Sample: `GET /api/result/dropdowns/exams-by-create-exam?grade_id=3&standard_id=12&division_id=3&subject_id=5`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 8, "name": "First Terminal Exam", "subject_id": 5, "subject_name": "Mathematics" } ],
  "errors": null }
```

## 9. GET /api/result/dropdowns/chapters

Chapters (`chapter_master.chapter_name`) for a standard + subject(s).
Delegates to `AJAXController@getChapterList`.

| Param | Type | Required | Notes |
|---|---|---|---|
| subject_id | string/int | yes | csv allowed |
| standard_id | int | yes | |

Sample: `GET /api/result/dropdowns/chapters?standard_id=12&subject_id=5`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 101, "name": "Algebra" }, { "id": 102, "name": "Geometry" } ],
  "errors": null }
```

## 10. GET /api/result/dropdowns/topics

Topics (`topic_master.name`) for one or more chapters.
Delegates to `AJAXController@getTopicList`.

| Param | Type | Required | Notes |
|---|---|---|---|
| chapter_id | string/int | yes | csv allowed |

Sample: `GET /api/result/dropdowns/topics?chapter_id=101`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 501, "name": "Linear Equations" } ],
  "errors": null }
```

## 11. GET /api/result/dropdowns/exams

Created exams (`result_create_exam.title`) for the institute + current syear.
Delegates to `AJAXController@getExamList`. Filter combinations (same as web):

- `standard_id` + `term_id` + `subject_id` → exams of that subject/term.
- `standard_id` + `exam_id` → all created exams under that exam master (grouped by title).
- `standard_id` + `exam_id` + `term_id` + `subject_id` → fully filtered, grouped by title.

| Param | Type | Required |
|---|---|---|
| standard_id | int | yes |
| term_id | int | required unless exam_id given |
| subject_id | int | required unless exam_id given |
| exam_id | int | no |

Sample: `GET /api/result/dropdowns/exams?standard_id=12&term_id=2&subject_id=5`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 301, "name": "Unit Test 1 - Mathematics" } ],
  "errors": null }
```

## 12. GET /api/result/dropdowns/co-scholastic-parents

Co-scholastic parent (skill group) titles (`result_co_scholastic_parent.title`)
for the institute. Delegates to `AJAXController@getCoScholasticParentList`.
No params.

Sample: `GET /api/result/dropdowns/co-scholastic-parents`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 1, "name": "Work Education" }, { "id": 2, "name": "Art Education" } ],
  "errors": null }
```

## 13. GET /api/result/dropdowns/co-scholastics

Co-scholastic items (`result_co_scholastic.title`) under a parent for a term +
standard. Delegates to `AJAXController@getCoScholasticList`.

| Param | Type | Required |
|---|---|---|
| co_scholastic_parent_id | int | yes |
| term_id | int | yes |
| standard_id | int | yes |

Sample: `GET /api/result/dropdowns/co-scholastics?co_scholastic_parent_id=1&term_id=2&standard_id=12`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 21, "name": "Gardening" }, { "id": 22, "name": "Cooking" } ],
  "errors": null }
```

## 14. GET /api/result/dropdowns/activity-masters

Activity-master titles (`result_activity_master.title`) for a skill +
standard. Delegates to `AJAXController@getActivityMasterList`.

| Param | Type | Required | Notes |
|---|---|---|---|
| skillset_id | int | yes | matched against `result_activity_master.skill_id` |
| standard | int | yes | web param name; `standard_id` is accepted as an alias |
| term_id | int | no | applied only when non-empty |

Sample: `GET /api/result/dropdowns/activity-masters?skillset_id=4&standard=12&term_id=2`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 71, "name": "Group Discussion" } ],
  "errors": null }
```

---

## Behavioral simplifications / caveats (vs. the web AJAX)

1. **module detection**: `getStandardList` / `getDivisionList` / `getSubjectList`
   detect the calling module by parsing `HTTP_REFERER` (and contain an
   always-true string check that forces `module_name = "student_homework"` on
   most requests). The API replaces this with an explicit `module_name` query
   param; when absent, normal class-teacher scoping applies.
2. **subjectTeacher\*Arr session keys** are populated on the web by the
   `SearchChain` HTML helper (`app/Helpers/Helper.php` lines 228–311), which the
   API middleware does not run. The API recomputes the same arrays per request
   (teacher `allocated_standards` → standards/divisions; else the teacher's
   `timetable` rows; supervisor profiles only via `allocated_standards`).
3. **Institute-195 special case dropped**: the web code restricts teachers to
   their single class-teacher class for certain `right_menu_id` values when
   `sub_institute_id = 195`. Not replicated.
4. **Multi-standard division scoping tightened**: web uses `orWhereIn` for
   subject-teacher divisions on multi-standard requests (which can leak
   divisions of other standards); the API uses `whereIn`.
5. **`/subjects` allow_grades default**: on the web the `allow_grades='Yes'`
   filter is applied whenever an HTTP referer exists (an `||` that is
   effectively always true), i.e. practically always. The API defaults to
   `allow_grades=Yes` to match; pass `allow_grades=No` for the unfiltered list.
   The teacher-timetable branch ignores the filter (same as web).
6. **`/exam-names` (getExamsList) SQL fixed**: the web join clause
   `"...lqm.id in and lqm.status = 1 (SELECT ...)"` is invalid SQL — the web
   endpoint always errors. The API uses the evident intended form
   `lqm.status = 1 AND lqm.id IN (SELECT ...)` with the inner alias
   de-conflicted (`lqm2`) and `lqm2.status` qualified.
7. **`/exams-by-create-exam` (getExamByCreateExam) fixed**: the web query joins
   `result_exam_master` without the `rem` alias it references (always a SQL
   error) and selected only `subject_name`/`subject_id` while grouping by
   `rce.exam_id`. The API adds the missing alias and additionally selects
   `rce.exam_id` + `rem.ExamTitle` so `{id, name}` is the exam, with the
   original subject fields kept as extras.
8. **Empty teacher scope = empty list**: a Teacher profile with no timetable
   rows and no `allocated_standards` receives an empty standards/divisions
   list (matches the web behaviour of `whereIn` on an empty session array).
9. **Student scoping**: a Student profile only ever sees its own
   standard/division (enrollment lookup, same as web).
10. `/exam-names` and the create-exam lookups filter `sub_institute_id`/`syear`
    from the authenticated session, not from client params (the web AJAX
    trusted request params for these).
