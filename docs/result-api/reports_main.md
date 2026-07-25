# Report APIs — Result Report, Student Result (New Report Card), Classwise Grade Report

All endpoints:

- Base URL prefix: `/api/result`
- Auth: `Authorization: Bearer <user_token>` from `POST /api/api-login` (email/password).
- Optional context overrides on any endpoint (query/body): `syear`, `term_id`, `sub_institute_id`.
- Envelope: `{"success": true|false, "message": "...", "data": ..., "errors": ...}` (+ `meta.pagination` on list endpoints).
- Errors: 401 invalid token, 422 validation, 404 not found, 500 `Something went wrong.`
- IMPORTANT for the Result Report endpoints: the legacy report code reads several
  inputs from PHP's `$_REQUEST` superglobal. The API controller mirrors JSON-body
  inputs into `$_REQUEST`, so both `application/json` and form-encoded bodies work.

---

## 1. Result Report (`ResultReportApiController`)

Delegates to `app/Http/Controllers/result/cbse_result/result_report_controller.php`.

### GET /api/result/result-report

Purpose: filter metadata for the report screen — the institute's exam master list.

Params: none (besides auth/context).

Sample request:

```
GET /api/result/result-report
Authorization: Bearer eyJ...
```

Sample response:

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "exam_master": [
      {
        "Id": 12,
        "SubInstituteId": 4,
        "Code": "PT1",
        "ExamTitle": "Periodic Test 1",
        "SortOrder": 1,
        "term_id": 149,
        "standard_id": 25,
        "weightage": "5"
      }
    ],
    "data": []
  },
  "errors": null
}
```

### POST /api/result/result-report/show

Purpose: generate one of the CBSE result reports as structured JSON (the exact
data array the Blade views receive — calculations 100% reused).

Request params:

| name                | type   | required                                     | notes |
|---------------------|--------|----------------------------------------------|-------|
| report_of           | string | yes — one of `overall_report`, `merit_report`, `subject_progress_report`, `classwise_report`, `classwise_grade_report`, `marks_report`, `weightage_conversion_report`, `created_exam_report` | selects the report |
| grade               | int    | yes                                          | grade id |
| standard            | int    | yes                                          | standard id |
| division            | int    | yes                                          | division id |
| term                | int    | no (recommended for subject/classwise/weightage reports) | term id used as the report's term filter |
| subject             | int    | required for `subject_progress_report`, `weightage_conversion_report` | subject id |
| additional_subjects | array  | required for `marks_report`                  | subject ids |
| exam_type           | int    | required for `created_exam_report`; optional filter elsewhere | result_exam_master Id |
| exam_create         | string | no                                           | created-exam title filter (classwise reports) |
| top_students        | int    | no                                           | merit report row limit |
| roll_no             | string | no                                           | student filter |
| from_date / to_date | date `Y-m-d` | no                                     | exam-date range filter |

Sample request (classwise report):

```json
POST /api/result/result-report/show
{
  "report_of": "classwise_report",
  "grade": 3,
  "standard": 25,
  "division": 71,
  "term": 149
}
```

Sample response (truncated):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "grade_id": "3",
    "standard_id": "25",
    "division_id": "71",
    "date_arr": {
      "ENGLISH": ["ENGLISH(100)", "-"],
      "MATHS": ["MATHS(100)", "-"]
    },
    "WRT_data": {
      "5211": {
        "ENGLISH": {
          "ExamTitle": "Periodic Test 1",
          "total_points": "100",
          "subject_id": 12,
          "subject_name": "ENGLISH",
          "student_id": 5211,
          "obtained_points": "78",
          "is_absent": null,
          "percentage": "78.00",
          "grade": "B1",
          "student_name": "AARAV R PATEL",
          "roll_no": "1"
        }
      },
      "total_student": 34
    },
    "all_student": {
      "5211": {
        "id": 5211,
        "student_id": 5211,
        "first_name": "AARAV",
        "last_name": "PATEL",
        "roll_no": "1",
        "standard_id": 25,
        "division_id": 71
      }
    },
    "attendance": { "5211": "182/200" }
  },
  "errors": null
}
```

Report-specific `data` shapes (keys, all straight from the web views):

- `overall_report`: `data` (term-1 per-student array: year, term, total_mark, name, roll_no, mother_name, class, father_name, division, date_of_birth, gr_no, exam[], mark[], per, final_grade, co_scholastic_area, att, grade_range), `term_2_data`, `term_3_data`, `term_4_data`, `header_data`, `footer_data`, `all_subject`, `std_div`, `standard_id`, `grade_id`, `division_id`, `syear`, `term_id`.
- `merit_report`: `students_data` (per-student: student_id, roll_no, student_name, obtainedMarks, totalMarks, percentage, failed, rank), `grade_id`, `standard_id`, `division_id`.
- `subject_progress_report`: `date_arr` (exam headers), `WRT_data` (per student per exam: marks/percentage/grade), `all_student`.
- `classwise_report`: as sample above (+ `attendance`).
- `classwise_grade_report`: same as classwise_report without `attendance`.
- `marks_report`: `date_arr`, `WRT_data` (subject-wise totals), `all_student`.
- `weightage_conversion_report`: `date_arr`, `mark_arr` (converted best-of weighted marks), `all_student`, `get_exam_masters`, `get_exam_master_heading`.
- `created_exam_report`: `term_id`, `examType`, `exam_create`, `totalField`, `termData`, `createdExams` (subject-wise created exams), `studentData` (per student per subject per exam `{outof, marks}`).

### GET /api/result/result-report/standardwise-subjects

Purpose: subjects mapped to a standard (`sub_std_map`) for the report filters.
Supports `search`, `sort_by`/`sort_dir`, `page`/`per_page`.

| name   | type | required |
|--------|------|----------|
| std_id | int  | yes      |

Sample request: `GET /api/result/result-report/standardwise-subjects?std_id=25`

Sample response:

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 310,
      "sub_institute_id": 4,
      "standard_id": 25,
      "subject_id": 12,
      "display_name": "ENGLISH",
      "elective_subject": null,
      "optional_type": null,
      "sort_order": 1
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 8, "last_page": 1 } }
}
```

### GET /api/result/result-report/download-overall-excel

Purpose: Overall report Excel export. Streams `OverallReport.xls`
(`Content-Type: application/excel`) — NOT the JSON envelope. The overall report
is regenerated in-memory first (the web flow relied on a session value that a
stateless API does not keep).

| name     | type | required |
|----------|------|----------|
| grade    | int  | yes      |
| standard | int  | yes      |
| division | int  | yes      |

Sample request:
`GET /api/result/result-report/download-overall-excel?grade=3&standard=25&division=71`

Sample response: binary `.xls` attachment (`Content-Disposition: attachment; filename=OverallReport.xls`).

---

## 2. Student Result / New Report Card (`StudentResultApiController`)

Delegates to `app/Http/Controllers/result/new_result/studentResultController.php`
(only `index`, `create`, `store` exist on the resource route, plus
`save_result_html` = web route `save_result_html_new`).

### GET /api/result/student-result

Purpose: search-panel metadata — active report-card templates of the institute
(falls back to global templates), academic terms of the year, result types.

Params: none.

Sample response:

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "data": [
      {
        "id": 12,
        "sub_institute_id": 4,
        "template_name": "CBSE 1-5 Term Report",
        "html_content": "<div>...template html...</div>",
        "status": 1,
        "sort_order": 1
      }
    ],
    "terms": [
      { "id": 300, "sub_institute_id": 4, "syear": 2025, "term_id": 149, "title": "Term 1", "start_date": "2025-06-01", "end_date": "2025-10-31" }
    ],
    "result_types": ["Regular", "HPC"]
  },
  "errors": null
}
```

### GET /api/result/student-result/create

Purpose: step 1 of report-card generation — the students of the selected class
plus the selected template/format context.

| name        | type   | required | notes |
|-------------|--------|----------|-------|
| standard    | int    | yes      | standard id |
| grade       | int    | no       | grade id |
| division    | int    | no       | division id |
| template    | int    | no       | result_template id |
| format      | string | no       | e.g. term id or `yearly` |
| result_type | string | no       | `Regular` / `HPC` |
| term_id     | int    | no       | |

Sample request:
`GET /api/result/student-result/create?grade=3&standard=25&division=71&template=12&format=149&result_type=Regular`

Sample response (truncated):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "data": [ { "id": 12, "template_name": "CBSE 1-5 Term Report" } ],
    "terms": [ { "term_id": 149, "title": "Term 1" } ],
    "status_code": 1,
    "message": "Success",
    "template": "12",
    "format": "149",
    "student_data": [
      {
        "id": 5211,
        "student_id": 5211,
        "first_name": "AARAV",
        "middle_name": "R",
        "last_name": "PATEL",
        "roll_no": "1",
        "enrollment_no": "GR1024",
        "standard_id": 25,
        "division_id": 71
      }
    ],
    "grade_id": "3",
    "standard_id": "25",
    "division_id": "71",
    "result_type": "Regular",
    "result_types": ["Regular", "HPC"]
  },
  "errors": null
}
```

When no student matches, `status_code` is `0` and `message` is
`"No student found please check your search panel"` (still HTTP 200, like the
legacy mobile API).

### POST /api/result/student-result

Purpose: generate the report cards for the selected students with the selected
template. Returns the fully rendered per-student report-card HTML plus context —
this HTML is the actual report artifact (it is what `save-html` persists), so it
is returned as-is; all marks/grade/attendance values inside it are computed by
the legacy `create_html_content()` pipeline.

| name        | type         | required | notes |
|-------------|--------------|----------|-------|
| template_id | int          | yes      | result_template id |
| students    | array/string | yes      | selected student ids (as the web form posts them) |
| format      | string       | yes      | term id or `yearly` |
| result_type | string       | no       | `Regular` / `HPC` |
| standard_id | int          | no       | used for result-book/trust lookup + saved context |
| grade_id    | int          | no       | saved context |
| division_id | int          | no       | saved context |

Sample request:

```json
POST /api/result/student-result
{
  "template_id": 12,
  "students": ["5211", "5212"],
  "format": "149",
  "result_type": "Regular",
  "grade_id": 3,
  "standard_id": 25,
  "division_id": 71
}
```

Sample response (truncated — `data` also still contains the numeric student rows
from the legacy array):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "0": { "id": 5211, "first_name": "AARAV", "roll_no": "1" },
    "html": "<div id=\"5211\"><div class=\"report-card-bg\" style=\"...\">...rendered report card...</div></div>",
    "standard_id": 25,
    "grade_id": 3,
    "division_id": 71,
    "term_id": "149",
    "syear": 2025,
    "result_type": "Regular",
    "all_stud_html": {
      "5211": "<div id=\"5211\">...</div>",
      "5212": "<div id=\"5212\">...</div>"
    },
    "students_ids": ["5211", "5212"],
    "template": 12
  },
  "errors": null
}
```

### POST /api/result/student-result/save-html

Purpose: persist the generated report-card HTML into `result_html` (insert or
update) and the attendance summary into `result_reportcard_marks` — identical to
web route `save_result_html_new`.

| name                | type   | required | notes |
|---------------------|--------|----------|-------|
| student_arr         | string | yes      | comma-separated student ids, e.g. `"5211,5212"` |
| term_id             | int    | yes      | |
| grade_id            | int    | yes      | |
| standard_id         | int    | yes      | |
| division_id         | int    | yes      | |
| result_type         | string | no       | `Regular` / `HPC` |
| html_{student_id}   | string | one per student | the rendered HTML from POST /student-result (`all_stud_html`) |
| total_working_day   | int    | no       | attendance summary |
| present_working_day | int    | no       | attendance summary |
| student_percentage  | number | no       | attendance summary |

Sample request:

```json
POST /api/result/student-result/save-html
{
  "student_arr": "5211,5212",
  "term_id": 149,
  "grade_id": 3,
  "standard_id": 25,
  "division_id": 71,
  "result_type": "Regular",
  "html_5211": "<div id=\"5211\">...</div>",
  "html_5212": "<div id=\"5212\">...</div>",
  "total_working_day": 200,
  "present_working_day": 182,
  "student_percentage": 91
}
```

Sample response:

```json
{ "success": true, "message": "Result HTML saved.", "data": null, "errors": null }
```

---

## 3. Classwise Grade Report (`ClasswiseGradeReportApiController`)

Delegates to `app/Http/Controllers/result/classwiseGradeReportController.php`.

### GET /api/result/classwise-grade-report

Purpose: parity with the web resource index. The web index only re-displays the
last generated report from a session flash; the API is stateless, so `data` is
`null`. Use `GET /classwise-grade-report/create` to generate the report.

Sample response:

```json
{ "success": true, "message": "Success", "data": null, "errors": null }
```

### GET /api/result/classwise-grade-report/create

Purpose: generate the classwise grade report — per-student exam x subject marks
(with elective-subject grade conversion, and Grand Total / Average rows when no
single exam is selected), class rank per exam, appreciation + remark texts, and
the exam/subject header matrix.

| name      | type   | required | notes |
|-----------|--------|----------|-------|
| standard  | int    | yes      | standard id |
| grade     | int    | no       | grade id (146/147 add grade next to marks; 148/149 use passing ratio 33) |
| division  | int    | no       | division id (used for rank) |
| term      | int    | no       | term id filter |
| exam_type | string | no       | created-exam TITLE (e.g. `"Periodic Test 1"`); empty = all exams + Grand Total/Average |

Sample request:
`GET /api/result/classwise-grade-report/create?grade=3&standard=25&division=71&term=149`

Sample response (truncated):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "grade_id": "3",
    "standard_id": "25",
    "division_id": "71",
    "term_id": "149",
    "exam_type": null,
    "examSubject": [
      { "id": 901, "title": "Periodic Test 1", "subject_name": "ENGLISH", "subject_id": 12 }
    ],
    "studentData": {
      "5211": {
        "id": 5211,
        "student_id": 5211,
        "first_name": "AARAV",
        "roll_no": "1",
        "exams": ["Periodic Test 1", "Half Yearly", "Grand Total", "Average"],
        "examData": {
          "Periodic Test 1": {
            "ENGLISH": {
              "id": 901,
              "ExamTitle": "Periodic Test 1",
              "total_points": "40",
              "subject_name": "ENGLISH",
              "student_id": 5211,
              "obtained_points": "32",
              "is_absent": null,
              "elective_subject": null,
              "optional_type": null,
              "display_points": "32"
            },
            "rank": {
              "student_id": 5211,
              "roll_no": "1",
              "student_name": "AARAV R PATEL",
              "obtainedMarks": "212",
              "totalMarks": "240",
              "percentage": "88.3333",
              "failed": 0,
              "rank": 2
            },
            "applied": "Excellent",
            "remark": "Aim higher"
          },
          "Grand Total": { "ENGLISH": 74 },
          "Average": { "ENGLISH": 140 }
        },
        "attendance": 0
      }
    }
  },
  "errors": null
}
```

### GET /api/result/classwise-grade-report/exam-create-names

Purpose: distinct created-exam titles of a standard (optionally term filtered) —
same as web route `get-exam-create-name`. Supports `search`, `sort_by`/`sort_dir`,
`page`/`per_page`.

| name   | type   | required | notes |
|--------|--------|----------|-------|
| stdId  | int    | yes      | standard id |
| termID | int    | no       | term id |
| title  | string | no       | exact title filter |

Sample request:
`GET /api/result/classwise-grade-report/exam-create-names?stdId=25&termID=149`

Sample response:

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 901,
      "sub_institute_id": 4,
      "syear": 2025,
      "standard_id": 25,
      "term_id": 149,
      "exam_id": 12,
      "subject_id": 12,
      "title": "Periodic Test 1",
      "points": "40",
      "exam_date": "2025-07-20",
      "report_card_status": "Y",
      "sort_order": 1
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 4, "last_page": 1 } }
}
```

---

## Caveats (legacy behavior, intentionally NOT changed)

1. **Hard-coded term 149 in API-mode rank**: both
   `result_report_controller::getRank()` and
   `classwiseGradeReportController::getRank()` set `term_id = 149` when
   `type == 'API'` (legacy mobile branch). Rank/percentage/failed values (and the
   appreciation/remark texts derived from them) in `merit_report`,
   `subject_progress_report`, `classwise_report`, `classwise_grade_report`,
   `marks_report` and the classwise-grade-report `create` endpoint are therefore
   computed against term 149's exams, not the requested term. Same behavior as
   the existing mobile API.
2. **overall_report needs at least 2 academic terms** — the legacy code accesses
   `academicTerms[1]` unconditionally; single-term institutes get a 500.
3. `POST /student-result` (store): the legacy method hard-codes `$type = ""`, so
   it always builds a Blade view; the API returns the view's data array. The
   payload IS rendered report-card HTML per student — that is the report artifact
   the module persists, so it is not stripped.
4. `GET /classwise-grade-report` (index) returns `null` in stateless API mode
   (web shows a session flash); the report itself is on `/create`.
5. `student-result` resource route: only `index`, `create`, `store` exist on the
   web controller — no show/edit/update/destroy, so no GET {id} / PUT / DELETE
   are exposed.
6. `save-html` performs DB writes (updateOrInsert/insert/update) exactly as the
   web method; it returns the legacy scalar `1`, wrapped as
   `message: "Result HTML saved."`.
7. The Excel export endpoint streams via `header()/echo/exit` in legacy code —
   the response bypasses Laravel's response pipeline (passthrough by design).
