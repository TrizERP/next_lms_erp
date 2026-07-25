# CBSE / WRT Legacy Report-Card Generator APIs

Base URL prefix: `/api/result` — all endpoints require
`Authorization: Bearer <user_token>` obtained from `POST /api/api-login`
(email/password). Optional overrides on any request: `syear`, `term_id`,
`sub_institute_id` (query or body).

Standard envelope:

```json
{ "success": true, "message": "Success", "data": { }, "errors": null }
```

Validation failure: HTTP 422 `{ "success": false, "message": "Validation Failed", "errors": { } }`.
Unhandled errors: HTTP 500 `{ "success": false, "message": "Something went wrong." }`.

All calculations (marks conversion via con_point, elective-subject filtering,
grade scales, percentages, co-scholastic grades, attendance, rank) are executed
by the existing web controllers in `app/Http/Controllers/result/cbse_result/` —
these APIs only delegate; nothing is re-implemented.

---

## 1. CBSE Std 1-5 Result — `cbse-1t5-result`

Web controller: `result\cbse_result\cbse_1t5_result_controller`.

### GET /api/result/cbse-1t5-result

Filter-screen bootstrap. Returns the legacy index payload (an empty `data`
array plus an optional flash `message`). Use the shared lookup APIs
(`api/get-standard-list`, `api/get-division-list`, …) for the actual filters.

Sample response:

```json
{ "success": true, "message": "Success", "data": { "data": [] }, "errors": null }
```

### POST /api/result/cbse-1t5-result/show

Generate the Std 1-5 report card for every student of the selected class.
Term 1 comes back in `data`, term 2 in `term_2_data` (the legacy method
switches the in-memory session term between the two passes; for
sub_institute_id 72 only the current term is used).

| Param    | Type | Required | Notes               |
|----------|------|----------|---------------------|
| grade    | int  | yes      | academic_section id |
| standard | int  | yes      | standard id         |
| division | int  | yes      | division id         |

Sample request:

```json
{ "grade": 4, "standard": 12, "division": 3 }
```

Sample response (abridged — one student):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "data": {
      "5321": {
        "year": "2025-2026",
        "term": "TERM - 1",
        "total_mark": 100,
        "name": "AARAV R PATEL",
        "roll_no": "7",
        "mother_name": "NITABEN",
        "class": "STD-3",
        "father_name": "RAKESHBHAI",
        "division": "A",
        "date_of_birth": "14-06-2017",
        "gr_no": "10234",
        "image": "student/5321.jpg",
        "height": "121",
        "weight": "22",
        "exam": [
          { "exam_id": 8, "exam": "PT-1", "mark": "20" },
          { "exam_id": 9, "exam": "HALF YEARLY", "mark": "80" },
          { "exam": "Marks Obtained", "mark": 100 }
        ],
        "mark": {
          "ENGLISH": { "PT-1": "18.00", "HALF YEARLY": "65.00", "TOTAL_GAIN": "83.00", "TOTAL_MARKS": "100.00", "GRADE": "A" },
          "MATHS":   { "PT-1": "15.00", "HALF YEARLY": "70.00", "TOTAL_GAIN": "85.00", "TOTAL_MARKS": "100.00", "GRADE": "A" }
        },
        "per": 84,
        "final_grade": "A",
        "co_scholastic_area": {
          "co_area": { "WORK EDUCATION": { "DISCIPLINE": "A" } }
        },
        "att": "92",
        "total_working_day": "104",
        "teacher_remark": "Good",
        "grade_range": {
          "mark_range": {
            "SCHOLASTIC MARKS RANGE": ["91-100", "81-90"],
            "GRADE": ["A+", "A"]
          }
        }
      }
    },
    "term_2_data": { "5321": { "...": "same shape for term 2" } },
    "header_data": { "line1": "SHREE TRUST", "line2": "...", "school_logo": "..." },
    "footer_data": { "teacher_sign": "...", "principal_sign": "...", "director_signatiure": "...", "reopen_date": "..." },
    "standard_id": "12",
    "grade_id": "4",
    "division_id": "3",
    "syear": 2025,
    "term_id": 149
  },
  "errors": null
}
```

Mark values can also be the strings `"AB"`, `"N.A."` or `"EX"` (absent /
not applicable / exempted).

### POST /api/result/cbse-1t5-result/save-html

Persist the rendered report-card HTML per student into `result_html`
(insert or update), same as the web "save result" action.

| Param                   | Type   | Required        | Notes                                         |
|-------------------------|--------|-----------------|-----------------------------------------------|
| student_arr             | string | yes             | comma separated student ids, e.g. "5321,5322" |
| term_id                 | int    | yes             |                                               |
| grade_id                | int    | yes             |                                               |
| standard_id             | int    | yes             |                                               |
| division_id             | int    | yes             |                                               |
| syear                   | int    | no              | defaults to token session year                |
| html_&lt;student_id&gt; | string | yes per student | the rendered card HTML                        |

Sample request:

```json
{
  "student_arr": "5321,5322",
  "term_id": 149,
  "grade_id": 4,
  "standard_id": 12,
  "division_id": 3,
  "syear": 2025,
  "html_5321": "<div class=\"report-card\">...</div>",
  "html_5322": "<div class=\"report-card\">...</div>"
}
```

Sample response:

```json
{ "success": true, "message": "Result HTML Saved", "data": null, "errors": null }
```

### POST /api/result/cbse-1t5-result/pdf

Convert the saved report-card HTML of one student to PDF file(s) on the
server and return their public links (legacy mobile `studentResultPDFAPI`,
including the fee-due gate for sub_institute_id 195 and the special CSS /
renderer for the "Hills" institutes 201, 202, 203, 204, 324, 326, 327).

| Param            | Type | Required | Notes                          |
|------------------|------|----------|--------------------------------|
| student_id       | int  | yes      |                                |
| syear            | int  | no       | defaults to token session year |
| sub_institute_id | int  | no       | defaults to token institute    |
| term_id          | int  | no       | defaults to token session term |

Sample request:

```json
{ "student_id": 5321, "syear": 2025, "sub_institute_id": 61, "term_id": 149 }
```

Sample response:

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "title": "2025 - Regular",
      "result_type": "Regular",
      "term_id": 149,
      "student_id": "5321",
      "pdf_link": "https://erp.example.com/storage/result_pdf/5321_20260722103000_0.pdf"
    }
  ],
  "errors": null
}
```

Failure cases: HTTP 404 `"No Record"` when no saved HTML / uploaded result
exists; HTTP 404 with message `"Please paid reamaining fees for view report
card."` for institute 195 with dues; HTTP 401 if the token fails the legacy
JWT re-check. When the result comes from the `upload_result` table instead of
saved HTML, `data` is a single object (not an array) with the uploaded
`file_name` as `pdf_link`.

---

## 2. CBSE Std 1-5 Term 2 Result — `cbse-1t5-t2-result`

Web controller: `result\cbse_result\cbse_1t5_t2_result_controller`.

### GET /api/result/cbse-1t5-t2-result

Filter-screen bootstrap (same shape as module 1's index).

### POST /api/result/cbse-1t5-t2-result/show

Same request and response shape as `POST cbse-1t5-result/show` (term 1 block
in `data`, term 2 block in `term_2_data`, `header_data`, `footer_data`,
`standard_id`, `grade_id`, `division_id`, `syear`, `term_id`). Differences:
student objects have no `height`/`weight`, and exams are fetched per term
(`getAllExam(standard, term)`).

| Param    | Type | Required |
|----------|------|----------|
| grade    | int  | yes      |
| standard | int  | yes      |
| division | int  | yes      |

---

## 3. CBSE Std 11 Term 2 Result — `cbse-11-t2-result`

Web controller: `result\cbse_result\cbse_11_t2_result_controller`.

### GET /api/result/cbse-11-t2-result

Filter-screen bootstrap (same shape as module 1's index).

### POST /api/result/cbse-11-t2-result/show

| Param    | Type | Required |
|----------|------|----------|
| grade    | int  | yes      |
| standard | int  | yes      |
| division | int  | yes      |

Sample response (abridged — one student):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "data": {
      "6810": {
        "year": "2025-2026",
        "term-1": 149,
        "term-2": 150,
        "total_mark": 100,
        "name": "ISHA M SHAH",
        "roll_no": "12",
        "mother_name": "HETAL",
        "class": "STD-11 SCI",
        "medium": "English",
        "father_name": "MEHUL",
        "division": "B",
        "date_of_birth": "02-03-2009",
        "gr_no": "8912",
        "image": "student/6810.jpg",
        "exam": [ { "ExamTitle": "UNIT TEST", "exam_title": "UT-1", "points": "25", "Id": 3, "term_id": 149, "title": "TERM - 1" } ],
        "exam_subject_wise": { "...": "exam breakup per subject" },
        "mark": { "PHYSICS": { "UT-1": "21.00", "TOTAL_GAIN": "78.00", "TOTAL_MARKS": "100.00", "GRADE": "B+" } },
        "per": 76.4,
        "final_grade": "B+",
        "co_scholastic_area": { "co_area": { } },
        "att": "88",
        "total_working_day": "110",
        "teacher_remark": "-"
      }
    },
    "header_data": { "line1": "...", "line2": "..." },
    "footer_data": { "teacher_sign": "...", "principal_sign": "...", "director_signatiure": "...", "reopen_date": "..." }
  },
  "errors": null
}
```

---

## 4. WRT Report — `wrt-report`

Web controller: `result\cbse_result\WRT_report_controller`.

### GET /api/result/wrt-report

Filter-screen bootstrap: `{ "data": [] }`.

### POST /api/result/wrt-report/show

| Param     | Type         | Required | Notes                 |
|-----------|--------------|----------|-----------------------|
| grade     | int          | yes      |                       |
| standard  | int          | yes      |                       |
| division  | int          | yes      |                       |
| from_date | date (Y-m-d) | yes      | exam_date range start |
| to_date   | date (Y-m-d) | yes      | exam_date range end   |

Sample request:

```json
{ "grade": 4, "standard": 12, "division": 3, "from_date": "2025-06-01", "to_date": "2025-07-31" }
```

Sample response (abridged):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "WRT_data": {
      "5321": {
        "WRT-1": [
          {
            "ExamTitle": "WRT-1",
            "total_points": "20",
            "subject_id": 5,
            "subject_name": "ENGLISH",
            "exam_date": "12-06-2025",
            "exam_day": "Thursday",
            "student_id": 5321,
            "obtained_points": "17",
            "is_absent": null,
            "percentage": "85.00"
          }
        ]
      }
    },
    "WRT_exam_master": [
      { "id": 45, "title": "WRT-1", "ExamTitle": "WRT-1", "exam_date": "2025-06-12", "points": "20", "standard_id": 12 }
    ],
    "all_student": { "5321": { "id": 5321, "student_id": 5321, "first_name": "AARAV", "roll_no": "7", "standard_name": "STD-3", "division_name": "A" } },
    "result_year": "2025-2026",
    "header_data": { "line1": "...", "school_logo": "..." },
    "standard_id": "12",
    "grade_id": "4",
    "division_id": "3",
    "syear": 2025,
    "term_id": 149
  },
  "errors": null
}
```

---

## 5. WRT Progress Report — `wrt-progress-report`

Web controller: `result\cbse_result\WRT_progress_report_controller`.

### GET /api/result/wrt-progress-report

Filter-screen bootstrap. Returns the institute's exam master list for the
`exam_type` filter:

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "exam_master": [
      { "Id": 3, "ExamTitle": "WRT", "Code": "WRT", "SubInstituteId": 61, "SortOrder": 1 }
    ],
    "data": []
  },
  "errors": null
}
```

### POST /api/result/wrt-progress-report/show

| Param     | Type         | Required | Notes                                |
|-----------|--------------|----------|--------------------------------------|
| grade     | int          | yes      |                                      |
| standard  | int          | yes      |                                      |
| division  | int          | yes      |                                      |
| from_date | date (Y-m-d) | yes      | exam_date range start                |
| to_date   | date (Y-m-d) | yes      | exam_date range end                  |
| exam_type | int          | no       | result_exam_master Id to filter with |

Sample request:

```json
{ "grade": 4, "standard": 12, "division": 3, "from_date": "2025-06-01", "to_date": "2025-07-31", "exam_type": 3 }
```

Sample response (abridged):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "WRT_data": {
      "5321": [
        {
          "ExamTitle": "WRT-1",
          "total_points": "20",
          "subject_id": 5,
          "subject_name": "ENGLISH",
          "exam_date": "12-06-2025",
          "exam_day": "Thursday",
          "student_id": 5321,
          "obtained_points": "17",
          "is_absent": null,
          "percentage": "85.00",
          "grade": "A",
          "rank": 2
        }
      ],
      "total_student": 34
    },
    "WRT_exam_master": [ { "id": 45, "title": "WRT-1", "ExamTitle": "WRT-1", "exam_date": "2025-06-12" } ],
    "all_student": { "5321": { "id": 5321, "student_id": 5321, "first_name": "AARAV", "roll_no": "7" } },
    "result_year": "2025-2026",
    "header_data": { "line1": "..." },
    "standard_id": "12",
    "grade_id": "4",
    "division_id": "3",
    "syear": 2025,
    "term_id": 149
  },
  "errors": null
}
```

Note: `total_student` is appended as an extra key inside `WRT_data` by the
legacy code (it is not a student id).

---

## 6. Overall Mark Report — `overall-mark-report`

Web controller: `result\cbse_result\overall_mark_report_controller`.

### GET /api/result/overall-mark-report

Filter-screen bootstrap: `{ "data": [] }` (+ optional flash `message`).

### POST /api/result/overall-mark-report/show

| Param    | Type | Required |
|----------|------|----------|
| grade    | int  | yes      |
| standard | int  | yes      |
| division | int  | yes      |

Sample response (abridged — one student):

```json
{
  "success": true,
  "message": "Success",
  "data": {
    "data": {
      "5321": {
        "year": "2025-2026",
        "term-1": 1,
        "term-2": 2,
        "total_mark": 100,
        "name": "AARAV R PATEL",
        "roll_no": "7",
        "mother_name": "NITABEN",
        "class": "STD-3",
        "medium": "English",
        "father_name": "RAKESHBHAI",
        "division": "A",
        "date_of_birth": "2017-06-14",
        "gr_no": "10234",
        "exam": [ { "ExamTitle": "PT-1", "points": "20", "Id": 8, "term_id": 149 } ],
        "exam_subject_wise": { "...": "exam breakup per subject" },
        "mark": { "ENGLISH": { "PT-1": "18.00", "TOTAL_GAIN": "83.00", "TOTAL_MARKS": "100.00", "GRADE": "A" } },
        "per": 84.5,
        "final_grade": "A",
        "co_scholastic_area": { "co_area": { "WORK EDUCATION": { "DISCIPLINE": { "149": "A", "150": "B" } } } },
        "att": "92/104",
        "headings": { "line1": "SHREE TRUST", "line2": "...", "line3": "...", "line4": "..." },
        "exam_master_settig": { "teacher_sign": "...", "principal_sign": "...", "director_signatiure": "...", "reopen_date": "..." }
      }
    }
  },
  "errors": null
}
```

---

## Caveats (legacy behavior preserved on purpose)

1. **`$_REQUEST` superglobal**: the legacy methods read `$_REQUEST` directly;
   the API controllers mirror the validated inputs into `$_REQUEST` before
   delegating so JSON bodies work. Filters may be sent as body or query.
2. **WRT hardcoded term 149**: with `type=API` the WRT helpers
   (`getWRTData`, `getAllExamMaster`, `getRank`, `getHeader`) hardcode
   `term_id = 149` instead of the session term (existing mobile behavior,
   institute-specific). Results for institutes whose exams are not under term
   149 may be empty. Not fixed because calculations must not be re-implemented.
3. **Two academic terms required**: `cbse-1t5-result/show` and
   `cbse-1t5-t2-result/show` read `academicTerms[0]` and `academicTerms[1]`
   (except sub_institute_id 72). Institutes with a single term row in
   `academic_year` will get a 500 — same as the web screen.
4. **Empty class**: `wrt-progress-report/show` reads `all_student[0]` in its
   API branch and indexes `rank[student_id]`; a division with no enrolled
   students (or marks rows without rank data) errors with 500, exactly like
   the legacy mobile call.
5. **`overall-mark-report/show`**: the web app registers only the resource
   route (no explicit `show_result` POST route), but the method exists and is
   the only way to produce this report, so it is exposed per the
   module-action convention. Students with no marks rows can 500 (legacy
   `getPer` indexes `$all_subject_mark[$student_id]` without an isset guard).
6. **`cbse-1t5-result/pdf`** deletes all existing files in
   `storage/result_pdf/` before regenerating (legacy behavior) — links from a
   previous call become stale after each new call.
7. **Report HTML/PDF flow**: `show` returns structured JSON only; the
   frontend renders the card and posts the HTML back via `save-html`; `pdf`
   converts the saved HTML server-side.
8. No update/delete/dropdown endpoints: the web controllers only implement
   `index` + `show_result` (+ `save_result_html` / `studentResultPDFAPI` for
   module 1), so nothing else is exposed.
