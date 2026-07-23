# Result Module API — Masters (Batch 2)

Modules: **Result Master**, **Exam Creation**, **Result Book Master**,
**Student Attendance Master**, **Consolidate Report**.

## Authentication (all endpoints)

- Header: `Authorization: Bearer <user_token>` (token from `POST /api/api-login` with email/password).
- Optional context overrides (query or body): `syear`, `term_id`, `sub_institute_id`.
- Common list params (where noted): `search`, `sort_by`, `sort_dir` (`asc|desc`), `page`, `per_page` (`per_page=0` disables pagination).

Envelope: success `{"success": true, "message": "...", "data": ..., "errors": null}` (+ optional `meta.pagination`); validation failure `422 {"success": false, "message": "Validation Failed", "errors": {...}}`; server error `500 {"success": false, "message": "Something went wrong."}`; missing record `404`.

---

## 1. Result Master (`result_master_confrigration`)

Result configuration per standard: result date, reopen date, vacation window, signatures, remark mode.

### GET /api/result/result-master
List configurations for the current institute + academic year.

Params: `search` (string, optional — matches term_name/grade_name/standard_name/result_date), `sort_by`, `sort_dir`, `page`, `per_page`.

Sample request:
```
GET /api/result/result-master?search=Std 5&per_page=10
```

Sample response:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 12,
      "term_name": "Term 1",
      "grade_name": "Primary",
      "standard_name": "Std 5",
      "result_date": "25-04-2026",
      "reopen_date": "08-06-2026",
      "vaction_start_date": "01-05-2026",
      "vaction_end_date": "07-06-2026",
      "teacher_sign": "20260420101530.png",
      "principal_sign": "20260420101531.png",
      "director_signatiure": "",
      "result_remark": "Grade Master",
      "optional_subject_display": "Yes",
      "remove_fail_per": "No"
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 } }
}
```

### POST /api/result/result-master
Create a configuration for one or more standards. Send as `multipart/form-data` when uploading signature images.

| Param | Type | Required |
|---|---|---|
| term | int | yes |
| standard | array of int | yes (one row is created per standard) |
| result_date | date (d-m-Y or Y-m-d) | yes |
| reopen_date | date | yes |
| vaction_start_date | date | yes |
| vaction_end_date | date | yes |
| result_remark | string (`grade_master` or `student_wise`) | no |
| optional_subject_display | string (`y`/`n`) | no |
| remove_fail_per | string (`y`/`n`) | no |
| teacher_sign | file (image) | no |
| principal_sign | file (image) | no |
| director_signatiure | file (image) | no |

Sample request (form fields): `term=1&standard[]=5&standard[]=6&result_date=25-04-2026&reopen_date=08-06-2026&vaction_start_date=01-05-2026&vaction_end_date=07-06-2026&result_remark=grade_master&optional_subject_display=y&remove_fail_per=n`

Sample response (201):
```json
{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }
```
If any selected standard already has a configuration, returns `422` with message `"Given Standard Have Settings."` (nothing is saved).

### GET /api/result/result-master/{id}
Single configuration (raw dates, same data as web edit screen).

Sample response:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 12, "term": 1, "standard_id": 5, "grade": 2,
    "result_date": "2026-04-25", "reopen_date": "2026-06-08",
    "vaction_start_date": "2026-05-01", "vaction_end_date": "2026-06-07",
    "teacher_sign": "20260420101530.png", "principal_sign": "20260420101531.png",
    "director_signatiure": "", "result_remark": "grade_master",
    "optional_subject_display": "y", "remove_fail_per": "n"
  },
  "errors": null
}
```

### PUT /api/result/result-master/{id}
Same params as store except `standard` is a **single int** (not an array). Signature files optional; existing files kept when omitted.

Sample response: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### DELETE /api/result/result-master/{id}
Sample response: `{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }`

### DELETE /api/result/result-master/bulk
Body: `{ "ids": [12, 13] }` → `{ "success": true, "message": "Data Deleted", "data": null, "errors": null }`

### GET /api/result/result-master/dropdown
`data`: `[{ "id": 12, "name": "Std 5", "grade": "Primary", "term_name": "Term 1" }]`

---

## 2. Exam Creation (`result_create_exam`)

### GET /api/result/exam-creation
List created exams for the current institute + academic year.

Params: `search` (matches exam_name/exam_type/sub_name/std_name/term_name/display_name), `sort_by`, `sort_dir`, `page`, `per_page`.

Sample response `data[]` row:
```json
{
  "id": 101, "term_name": "Term 1", "medium": "English",
  "exam_type": "First Unit Test", "app_disp_status": "Y",
  "std_name": "Std 5", "display_name": "Mathematics",
  "sub_name": "Maths", "exam_name": "Unit Test 1",
  "points": 25, "marks_type": "marks", "report_card_status": "Y",
  "sort_order": 1, "exam_date": "15-07-2026"
}
```

### GET /api/result/exam-creation/create
Exam-master dropdown for the add form.

Sample response:
```json
{ "success": true, "message": "Success", "data": { "exams": [ { "id": 3, "name": "First Unit Test" } ] }, "errors": null }
```

### POST /api/result/exam-creation
Creates one row per subject x title combination.

| Param | Type | Required |
|---|---|---|
| term | int | yes |
| exam | int (result_exam_master id) | yes |
| standard | int | yes |
| subject | array of int | yes (web validation) |
| title | array of string | yes (web validation) |
| app_disp_status | array of `Y`/`N` | yes (web validation) |
| points | array of number | no |
| exam_date | array of date | no |
| sort_order | array of int | no |
| medium | string | no |
| con_point | number | no |
| marks_type | string (`marks`/`grade`) | no |
| report_card_status | `Y`/`N` | no |

Sample request (form fields): `term=1&exam=3&standard=5&medium=English&marks_type=marks&report_card_status=Y&subject[]=7&title[]=Unit Test 1&points[]=25&app_disp_status[]=Y&sort_order[]=1&exam_date[]=15-07-2026`

Sample response (201): `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`
Duplicate (same term/exam/standard/subject/title) → `422` `"Given Standard Already Has Exams."`

### GET /api/result/exam-creation/{id}
Single created exam + edit dropdowns.

Sample response `data`:
```json
{
  "id": 101, "term_id": 1, "medium": "English", "exam_id": 3,
  "app_disp_status": "Y", "grade": 2, "standard_id": 5, "subject_id": 7,
  "title": "Unit Test 1", "points": 25, "marks_type": "marks",
  "report_card_status": "Y", "sort_order": 1, "exam_date": "2026-07-15",
  "con_point": null,
  "exams": { "3": "First Unit Test" },
  "report_card_status_arr": { "Y": "Yes", "N": "No" },
  "app_disp_status_arr": { "Y": "Yes", "N": "No" }
}
```

### PUT /api/result/exam-creation/{id}
Scalar fields (NOT arrays). Note the exam master id field is **`exam_id`** here (store uses `exam`).

Required: `term`, `exam_id`, `standard`, `subject`, `title`. Optional: `medium`, `points`, `con_point`, `marks_type`, `report_card_status`, `app_disp_status`, `sort_order`, `exam_date`.

Sample response: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### DELETE /api/result/exam-creation/{id} • DELETE /api/result/exam-creation/bulk (`{ "ids": [...] }`)
Same envelope as Result Master delete/bulk.

### GET /api/result/exam-creation/dropdown
`data`: `[{ "id": 101, "name": "Unit Test 1", "exam_type": "First Unit Test", "subject": "Maths", "standard": "Std 5", "term_name": "Term 1" }]`

---

## 3. Result Book Master (`result_trust_master` + `result_book_master`)

Report-card header (4 trust lines + left/right logos) mapped to standards.

### GET /api/result/result-book-master
Params: `search` (matches line1..line4/grade_name/standard_name), `sort_by`, `sort_dir`, `page`, `per_page`.

Sample response `data[]` row:
```json
{
  "id": 4,
  "line1": "Shree Vidya Trust", "line2": "Managed by ...",
  "line3": "Affiliation No 123", "line4": "City, State",
  "left_logo": "20260101101010.png", "right_logo": "20260101101011.png",
  "status": 1,
  "standard": [5, 6], "grade": [2],
  "grade_name": "Primary", "standard_name": "Std 5,Std 6"
}
```

### POST /api/result/result-book-master
`multipart/form-data` when uploading logos.

| Param | Type | Required |
|---|---|---|
| standard | array of int | yes |
| line1 / line2 / line3 / line4 | string | no |
| status | int (1/0) | no |
| left_logo / right_logo | file (image) | no |

Sample response (201): `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### GET /api/result/result-book-master/{id}
Same row shape as the list endpoint; `404` if the id does not exist.

### PUT /api/result/result-book-master/{id}
Same params as store. **Caveat (legacy behaviour):** the web logic deletes the trust row and its standard mappings and re-inserts them, so the record receives a **new id** after update.

### DELETE /api/result/result-book-master/{id} • DELETE /api/result/result-book-master/bulk (`{ "ids": [...] }`)
Deletes the trust row and its standard mappings.

### GET /api/result/result-book-master/dropdown
`data`: `[{ "id": 4, "name": "Shree Vidya Trust", "grade_name": "Primary", "standard_name": "Std 5,Std 6", "status": 1 }]`

---

## 4. Student Attendance Master (`student_attendance_master`)

**Transport caveat:** the legacy controller reads `$_REQUEST` directly, so parameters MUST be sent as query string (GET) or `application/x-www-form-urlencoded` / `multipart/form-data` (POST). A raw JSON body will not work.

### GET /api/result/student-attendance-master/create
Attendance entry sheet for a class.

| Param | Type | Required |
|---|---|---|
| term | int | yes |
| grade | int | yes |
| standard | int | yes |
| division | int | yes |

Sample request:
```
GET /api/result/student-attendance-master/create?term=1&grade=2&standard=5&division=3
```

Sample response:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "term_id": "1", "standard": "5", "grade": "2", "division": "3",
    "remark_data": { "1": "Excellent", "2": "Good", "3": "Needs Improvement" },
    "stu_data": [
      {
        "sr.no": 1,
        "name": "Aarav Kumar Shah",
        "att": 108, "day": 113, "per": "95.58",
        "remark": 1, "teacher_remark": "Regular",
        "att_out": 113, "student_id": 501
      }
    ]
  },
  "errors": null
}
```
Precondition failures return `422` with the legacy message: `"Please Add Total Working Days For This Standard."` or `"Please Add Remarks From Remark Master For This Standard."`.

### POST /api/result/student-attendance-master
Upsert attendance rows (rows with all fields empty are skipped by the web logic).

Form fields, one block per student id:

| Param | Type | Required |
|---|---|---|
| values[{student_id}][term_id] | int | yes |
| values[{student_id}][standard] | int | yes |
| values[{student_id}][attendance] | int | yes (at least one of the 4 value fields) |
| values[{student_id}][working_day] | int | see above |
| values[{student_id}][remark_id] | int | see above |
| values[{student_id}][teacher_remark] | string | see above |

Sample request (form-encoded): `values[501][term_id]=1&values[501][standard]=5&values[501][attendance]=108&values[501][working_day]=113&values[501][remark_id]=1&values[501][teacher_remark]=Regular`

Sample response (201):
```json
{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved", "class": "success" }, "errors": null }
```

No list / show / update / delete endpoints: the web controller's index renders an empty filter shell and show/edit/update/destroy are empty stubs.

---

## 5. Consolidate Report

### GET /api/result/consolidate-report
Consolidated marks grid: every report-card exam of every subject across all terms, per student, for one class.

| Param | Type | Required |
|---|---|---|
| grade | int | yes |
| standard | int | yes |
| division | int | yes |

Sample request:
```
GET /api/result/consolidate-report?grade=2&standard=5&division=3
```

Sample response (abridged):
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "totalExams": 24,
    "termList": { "1": "Term 1", "2": "Term 2" },
    "examMasterWise": {
      "1": {
        "First Unit Test": {
          "Mathematics": {
            "Unit Test 1": {
              "id": 101, "title": "Unit Test 1", "term_id": 1, "standard_id": 5,
              "weightage": 20, "ExamTitle": "First Unit Test", "subject_id": 7,
              "points": 25, "con_point": null, "ExamId": 3, "exam_id": 3,
              "display_name": "Mathematics", "elective_subject": "No",
              "allow_grades": "Yes", "optional_type": null
            }
          }
        }
      }
    },
    "studentMarks": {
      "501": {
        "id": 501, "first_name": "Aarav", "middle_name": "Kumar", "last_name": "Shah",
        "enrollment_no": "EN2026001", "roll_no": 1,
        "terms": {
          "1": {
            "title": "Term 1",
            "exams": {
              "First Unit Test": {
                "Mathematics": {
                  "Unit Test 1": { "exam_details": { "id": 101, "points": 25 }, "ob_marks": "22" }
                }
              }
            }
          }
        }
      }
    },
    "grade_id": "2", "standard_id": "5", "division_id": "3"
  },
  "errors": null
}
```

Notes:
- `ob_marks` is the obtained marks, or an absence flag (`AB`, `N.A.`, `EX`) when the student was marked absent/exempt, or `0` when no marks entered.
- The legacy placeholder keys `status` / `message` ("Failed to get Data" — the web view never used them) are stripped from the API response.
- Student scoping (class-teacher restrictions) follows the same `SearchStudent()` helper rules as the web screen.
