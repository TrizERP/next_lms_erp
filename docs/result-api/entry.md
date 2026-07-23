# Entry Modules API — Marks Entry, Co-Scholastic Marks Entry, HPC Activity Entry, HPC Entry v1

All endpoints:

- Base URL prefix: `/api/result`
- Auth: `Authorization: Bearer <user_token>` obtained from `POST /api/api-login` (email/password).
- Optional context overrides (query or body): `syear`, `term_id`, `sub_institute_id`.
- Envelope: `{"success": true|false, "message": "...", "data": ..., "errors": ...}`;
  validation failures return HTTP 422 with `errors`, unknown errors HTTP 500.

---

## 1. Marks Entry (`MarksEntryApiController`)

Delegates to `result\marks_entry\marks_entry_controller` (all mark save/update, absent
handling, approval and notification logic reused 1:1).

### GET /api/result/marks-entry

Landing data of the marks entry screen (flash message + empty data list). The actual
entry grid is loaded via `GET /api/result/marks-entry/create`.

Params: none.

Sample response:

```json
{
  "success": true,
  "message": "Success",
  "data": { "data": [] },
  "errors": null
}
```

### GET /api/result/marks-entry/create

Marks entry grid: students of the selected class with already-entered marks, the
subject & exam dropdowns, grade breakups (breakoff ranges) and the approval status of
the selected exam.

| Param | Type | Required | Notes |
|---|---|---|---|
| grade | int | yes | academic section (grade) id |
| standard | int | yes | standard id |
| division | int | yes | division id |
| term | int | yes | term id |
| subject | int | yes | subject id |
| exam_master | int | yes | `result_exam_master.Id` |
| exam | int | yes | `result_create_exam.id` (the created exam) |

Sample request:

```
GET /api/result/marks-entry/create?grade=1&standard=5&division=2&term=1&subject=12&exam_master=3&exam=45
```

Sample response (`data`):

```json
{
  "term_id": "1",
  "standard": "5",
  "grade": "1",
  "division": "2",
  "subject_dd": { "12": "English", "13": "Maths" },
  "subject": "12",
  "exam_master": "3",
  "exam_dd": { "45": "Unit Test 1" },
  "exam": "45",
  "grd_data": { "A": [81, 100], "B": [61, 80], "C": [0, 60] },
  "approve_status": { "id": 9, "status": 1, "created_by": 21 },
  "approved_user": { "id": 21, "first_name": "Asha" },
  "stu_data": [
    {
      "sr.no": 1,
      "name": "Aarav Kumar Shah",
      "roll_no": "7",
      "points": "35",
      "outof": "50",
      "per": "70",
      "grade": "B",
      "comment": "",
      "student_id": 3117
    }
  ]
}
```

### POST /api/result/marks-entry

Save/update marks for many students in one call (upserts `result_marks` via the
legacy store; sends the existing marks notification for client 4/11 installs).

| Param | Type | Required | Notes |
|---|---|---|---|
| values | object | yes | keyed by student_id |
| values.\*.exam_id | int | yes | `result_create_exam.id` |
| values.\*.points | string | no | number, or `AB` / `N.A.` / `EX` for absent-type |
| values.\*.per | string | no | percentage (with or without `%`) |
| values.\*.grade | string | no | grade letter |
| values.\*.comment | string | no | remark |

Sample request:

```json
{
  "values": {
    "3117": { "exam_id": 45, "points": "35", "per": "70", "grade": "B", "comment": "" },
    "3118": { "exam_id": 45, "points": "AB", "per": "0", "grade": "-", "comment": "Absent" }
  }
}
```

Sample response (201):

```json
{
  "success": true,
  "message": "Data Saved",
  "data": { "status": "1", "message": "Data Saved", "class": "success" },
  "errors": null
}
```

### POST /api/result/marks-entry/approve

Approve/un-approve marks of one exam+class+subject (`result_exam_approve`,
module `result_mark`).

| Param | Type | Required |
|---|---|---|
| term_id | int | yes |
| standard_id | int | yes |
| division_id | int | yes |
| subject_id | int | yes |
| exam_id | int | yes |
| approve | int (1/0) | no (default 0) |

Sample request:

```json
{ "term_id": 1, "standard_id": 5, "division_id": 2, "subject_id": 12, "exam_id": 45, "approve": 1 }
```

Sample response:

```json
{
  "success": true,
  "message": "Data Saved",
  "data": { "status": "1", "message": "Data Saved", "class": "success" },
  "errors": null
}
```

### POST /api/result/marks-entry/marks-approval

Marks approval report for one class/term: scholastic exams grouped by exam type,
subject heads, exam types, grade types and co-scholastic entries.

| Param | Type | Required |
|---|---|---|
| term | int | yes |
| standard | int | yes |
| grade | int | yes |
| division | int | yes |

Sample request:

```json
{ "term": 1, "standard": 5, "grade": 1, "division": 2 }
```

Sample response (`data`, trimmed):

```json
{
  "term": 1,
  "standard": 5,
  "grade": 1,
  "division": 2,
  "subject_dd": { "12": "English" },
  "scholastic": [
    {
      "exam_name": "Unit Test",
      "exam_type_id": "2",
      "exam_title": "Unit Test 1,Unit Test 2",
      "create_exam": "3",
      "exam_id": "45,46",
      "subject_name": "English",
      "standard_id": 5,
      "exam_type": "UT",
      "subject_id": 12
    }
  ],
  "subject_head": [ { "sub_id": 12, "subject_name": "English" } ],
  "exam_type": [ { "Id": 2, "ExamType": "UT" } ],
  "grade_type": [ { "title": "Work Education", "mark_type": "GRADE", "grade_id": "7" } ],
  "co_scholastic": [
    { "create_id": 4, "standard_id": 5, "term_id": 1, "co_scholastic_id": 7, "main_id": 7, "exam_name": "Work Education" }
  ]
}
```

### POST /api/result/marks-entry/marks-dd

Exam-type/exam-title dropdown data derived from one student's entered marks
(legacy mobile helper `get_marks_dd`).

| Param | Type | Required | Notes |
|---|---|---|---|
| student_id | int | yes | |
| sub_institute_id | int | no | defaults to session |
| syear | int | no | defaults to session |

Sample request:

```json
{ "student_id": 3117 }
```

Sample response:

```json
{
  "success": true,
  "message": "Sucsess",
  "data": [ { "ExamType": "UT", "ExamTitle": "Unit Test", "title": "Unit Test 1" } ],
  "errors": null
}
```

### POST /api/result/marks-entry/co-scholastic-marks-dd

Teacher-wise co-scholastic dropdown data (class/term/item combinations + grade
scales) — legacy mobile helper `get_co_scholastic_marks_dd`.

| Param | Type | Required | Notes |
|---|---|---|---|
| teacher_id | int | yes | |
| sub_institute_id | int | no | defaults to session |

Sample request:

```json
{ "teacher_id": 21 }
```

Sample response (intended legacy shape — see caveat below):

```json
{
  "success": true,
  "message": "Sucsess",
  "data": [
    {
      "resp_data": "5 / A / Term 1 / Co-Curricular / Work Education",
      "standard_id": 5,
      "division_id": 2,
      "mark_type": "GRADE",
      "term_id": 1,
      "co_scholastic_id": 7,
      "acdemic_section_id": 1,
      "co_grade": 3,
      "max_mark": 10,
      "grades": [ { "id": 1, "map_id": 3, "title": "A" } ]
    }
  ],
  "errors": null
}
```

### POST /api/result/marks-entry/get-result

Exam-wise result (per-subject marks, totals, average) of one student — legacy
mobile helper `get_result`.

| Param | Type | Required | Notes |
|---|---|---|---|
| student_id | int | yes | |
| sub_institute_id | int | no | defaults to session |
| syear | int | no | defaults to session |

Sample request:

```json
{ "student_id": 3117 }
```

Sample response (intended legacy shape — see caveat below):

```json
{
  "success": true,
  "message": "Sucsess",
  "data": [
    {
      "exam_data": {
        "exam_name": "UT - Unit Test - Unit Test 1",
        "etmid": 2, "emid": 3, "rceid": 45, "title": "Unit Test 1",
        "result": [
          { "subject_name": "English", "f_marks": "50", "g_marks": "35", "total_marks": "50", "totalk_get_marks": "35", "avge": "70.0" }
        ]
      }
    }
  ],
  "errors": null
}
```

---

## 2. Co-Scholastic Marks Entry (`CoScholasticMarksEntryApiController`)

Delegates to `result\co_scholastic_marks_entry\co_scholastic_marks_entry_controller`.

### GET /api/result/co-scholastic-marks-entry

Landing data (flash message + empty data list). Grid loads via `.../create`.

Sample response: `{"success": true, "message": "Success", "data": {"data": []}, "errors": null}`

### GET /api/result/co-scholastic-marks-entry/create

Co-scholastic entry grid: students with existing points/grades, parent & item
dropdowns, grade scale (for GRADE mark type) and approval status.

| Param | Type | Required | Notes |
|---|---|---|---|
| grade | int | yes | academic section id |
| standard | int | yes | |
| division | int | yes | |
| term | int | yes | |
| co_scholastic_parent | int | yes | `result_co_scholastic_parent.id` |
| co_scholastic | int | yes | `result_co_scholastic.id` |

Sample request:

```
GET /api/result/co-scholastic-marks-entry/create?grade=1&standard=5&division=2&term=1&co_scholastic_parent=2&co_scholastic=7
```

Sample response (`data`):

```json
{
  "mark_type": "GRADE",
  "term_id": "1",
  "standard": "5",
  "grade": "1",
  "division": "2",
  "co_scholastic_parent_dd": { "2": "Co-Curricular" },
  "co_scholastic_parent": "2",
  "co_scholastic_dd": { "7": "Work Education" },
  "co_scholastic": "7",
  "co_scholastic_grade_dd": { "1": "A", "2": "B" },
  "approve_status": { "id": 11, "status": 0 },
  "approved_user": "",
  "stu_data": [
    {
      "sr.no": 1,
      "roll_no": "7",
      "name": "Aarav Kumar Shah",
      "points": "8",
      "outof": 10,
      "grade": "A",
      "student_id": 3117
    }
  ]
}
```

### POST /api/result/co-scholastic-marks-entry

Save/update co-scholastic points/grades for many students
(upserts `result_co_scholastic_marks_entries`).

| Param | Type | Required | Notes |
|---|---|---|---|
| values | object | yes | keyed by student_id |
| values.\*.grade_id | int | yes | academic section id |
| values.\*.standard_id | int | yes | |
| values.\*.term_id | int | yes | |
| values.\*.co_scholastic | int | yes | `result_co_scholastic.id` |
| values.\*.grade_opt | string | no | grade (GRADE mark type) |
| values.\*.points | string | no | points (MARKS mark type) |

Sample request:

```json
{
  "values": {
    "3117": { "grade_id": 1, "standard_id": 5, "term_id": 1, "co_scholastic": 7, "grade_opt": "A" },
    "3118": { "grade_id": 1, "standard_id": 5, "term_id": 1, "co_scholastic": 7, "grade_opt": "B" }
  }
}
```

Sample response (201):

```json
{
  "success": true,
  "message": "Data Saved",
  "data": { "status": "1", "message": "Data Saved", "class": "success" },
  "errors": null
}
```

### POST /api/result/co-scholastic-marks-entry/approve

Approve/un-approve a co-scholastic item for one class/term
(`result_exam_approve`, module `co_scholastic`).

| Param | Type | Required | Notes |
|---|---|---|---|
| term_id | int | yes | |
| standard_id | int | yes | |
| division_id | int | yes | |
| exam_id | int | yes | the `result_co_scholastic.id` |
| subject_id | int | no | send `0` (co-scholastic has no subject) |
| approve | int (1/0) | no | default 0 |

Sample request:

```json
{ "term_id": 1, "standard_id": 5, "division_id": 2, "exam_id": 7, "subject_id": 0, "approve": 1 }
```

Sample response: same envelope/shape as marks-entry approve.

---

## 3. HPC Activity Entry (`ResultActivityMarksApiController`)

Delegates to `result\result_activity_marks\resultActivityMarksController`
(table `result_activity_marks`).

### GET /api/result/hpc-activity-entry

Landing data: all result skillsets of the institute + `termwise_hpc` setting.

Sample response (`data`):

```json
{
  "status": "1",
  "message": "Success",
  "result_skillsets": [
    { "id": 3, "main_title": "Physical Development", "title": "Gross Motor Skills", "standard": 5, "group": 1, "sub_institute_id": 46 }
  ],
  "termwise_hpc": "No"
}
```

### GET /api/result/hpc-activity-entry/create

Activity marks grid for one class/skillset/activity: students, skillsets of the
standard, activity master dropdown, sub-activities, activity groups (the mark
options) and each student's existing mark.

| Param | Type | Required | Notes |
|---|---|---|---|
| grade | int | yes | academic section id |
| standard | int | yes | |
| division | int | yes | |
| skillset_id | int | no | `result_skillset.id` |
| activity_master | int | no | `result_activity_master.id` |
| sub_activity_master | int | no | `result_sub_activity.id` |
| term | int | no | |

Sample request:

```
GET /api/result/hpc-activity-entry/create?grade=1&standard=5&division=2&skillset_id=3&activity_master=4
```

Sample response (`data`, trimmed):

```json
{
  "sub_institute_id": 46,
  "standard": "5",
  "grade": "1",
  "division": "2",
  "skillset_id": "3",
  "term": null,
  "result_skillsets": [ { "id": 3, "title": "Gross Motor Skills" } ],
  "activity_master": { "4": "Running" },
  "sub_activity_master": null,
  "activity_value": "4",
  "student_datas": [ { "id": 3117, "student_id": 3117, "first_name": "Aarav", "roll_no": "7" } ],
  "result_activity_groups": [ { "id": 1, "title": "Stream", "group": 1 } ],
  "get_activity_marks": { "3117": { "id": 55, "student_id": 3117, "activity_id": 4, "group_id": 1, "group_title": "Stream" } },
  "termwise_hpc": "No"
}
```

### POST /api/result/hpc-activity-entry

Save/update the activity-group mark of one activity (or sub-activity) for many
students (updateOrInsert into `result_activity_marks`).

| Param | Type | Required | Notes |
|---|---|---|---|
| activity_id | int | yes | `result_activity_master.id` |
| activity_group | object | yes | `{ "<student_id>": <group_id> }` |
| sub_activity_master | int | no | `result_sub_activity.id` when marking a sub-activity |

Sample request:

```json
{
  "activity_id": 4,
  "activity_group": { "3117": 1, "3118": 2 }
}
```

Sample response (201):

```json
{
  "success": true,
  "message": "Result activity marks added/updated successfully.",
  "data": null,
  "errors": null
}
```

---

## 4. HPC Entry v1 (`ResultActivityMarksV1ApiController`)

Delegates to `result\result_activity_marks\resultActivityMarksV1Controller`
(whole-grid Holistic Progress Card entry).

### GET /api/result/hpc-entry-v1

Landing data: status + `termwise_hpc` setting.

Sample response (`data`):

```json
{ "status": "1", "message": "Success", "termwise_hpc": "Yes" }
```

### GET /api/result/hpc-entry-v1/create

Full HPC grid for one class: student list, skillset tree grouped by
`main_sort_order`, activities per skill, sub-activities per activity, activity
groups (mark options) and every student's existing activity/sub-activity marks.

| Param | Type | Required | Notes |
|---|---|---|---|
| grade | int | yes | academic section id |
| standard | int | yes | |
| division | int | yes | |
| term | int | no | required in practice when `termwise_hpc` = "Yes" |

Sample request:

```
GET /api/result/hpc-entry-v1/create?grade=1&standard=5&division=2&term=1
```

Sample response (`data`, trimmed):

```json
{
  "status": "1",
  "message": "Success",
  "grade_id": "1",
  "standard_id": "5",
  "division_id": "2",
  "studentsList": [ { "id": 3117, "student_id": 3117, "first_name": "Aarav", "roll_no": "7" } ],
  "skillData": { "1": [ { "id": 3, "main_title": "Physical Development", "title": "Gross Motor Skills", "main_sort_order": 1 } ] },
  "mainTitlesOrder": [1, 2],
  "activityGroup": { "3": [ { "id": 4, "skill_id": 3, "title": "Running", "sort_order": 1 } ] },
  "subActivityGroup": { "4": [ { "id": 9, "skill_id": 3, "sub_skill_id": 4, "title": "50m sprint" } ] },
  "marksType": [ { "id": 1, "title": "Stream", "group": 1 } ],
  "studentMarks": {
    "activity": { "3117": { "4": 1 } },
    "sub_activity_id": { "3117": { "9": 2 } }
  },
  "termwise_hpc": "Yes"
}
```

### POST /api/result/hpc-entry-v1

Save/update the whole HPC grid in one call (updateOrInsert into
`result_activity_marks` for activities and sub-activities).

| Param | Type | Required | Notes |
|---|---|---|---|
| marksArr | object | yes | keyed by student_id |
| marksArr.\*.activity_id | object | no | `{ "<activity_id>": <group_id> }` |
| marksArr.\*.sub_activity_id | object | no | `{ "<activity_id>": { "<sub_activity_id>": <group_id> } }` |

Sample request:

```json
{
  "marksArr": {
    "3117": {
      "activity_id": { "4": 1 },
      "sub_activity_id": { "4": { "9": 2 } }
    }
  }
}
```

Sample response (201):

```json
{
  "success": true,
  "message": "Result activity master deleted successfully",
  "data": { "status": "1", "message": "Result activity master deleted successfully" },
  "errors": null
}
```

(The odd success message text comes from the legacy controller and is returned
unchanged.)

---

## Caveats

1. **No show/update/delete endpoints anywhere.** The web controllers either have no
   such methods (marks_entry, both HPC controllers) or only empty stubs
   (co_scholastic_marks_entry `show/edit/update/destroy`). `marks_entry@show` renders
   the empty approval-report landing view only, so it is not exposed as `GET {id}`;
   the useful report lives at `POST marks-entry/marks-approval`.
2. **marks-dd / co-scholastic-marks-dd / get-result** delegate to legacy methods that
   `echo` JSON and `exit`, bypassing Laravel's response cycle. An output-buffer
   callback re-wraps the payload into the standard envelope (HTTP status is always
   200 on that path).
3. **Legacy runtime bugs (not fixed, per no-modification rule):**
   `get_co_scholastic_marks_dd` and `get_result` contain `selectRaw('CONCAT_WS(' / ', ...)`
   -style string-division typos that throw a TypeError on PHP 8 (plus a wrong
   `rcm`/`rce` join alias in `get_result`), so those two endpoints currently return
   the standard 500 envelope until the legacy code is repaired. Additionally
   `get_co_scholastic_marks_dd` hard-codes `rcp.sub_institute_id = 46` in its join.
4. **HPC Entry v1 create/store are invoked with `type=JSON`, not `type=API`,** on
   purpose: the legacy `type=API` branch assigns `syear = $request->sub_institute_id`
   (copy-paste bug), which would corrupt year filtering/writes. The JSON branch uses
   the hydrated session values and still returns JSON.
5. **Stores use the legacy upsert semantics** (insert or update per student); there is
   no separate PUT. Both marks stores return 201 even when rows were updated, with the
   legacy message ("Data Saved"/"Data Updated") passed through.
6. **Marks store notifications:** for installs with `client_id` 4 or 11 the legacy
   store sends FCM/app notifications on newly inserted numeric marks — unchanged.
7. **`GET marks-entry` / `GET co-scholastic-marks-entry`** are thin landing endpoints
   (empty `data` array) kept for parity with the web resource; the grids come from the
   `/create` endpoints.
