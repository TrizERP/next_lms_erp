# Result Module REST API — Result Template, Student Result Remarks, Approve Mobile Result, Upload Result, All Results, Result Lookups

Base URL: `/api/result` · All endpoints require `Authorization: Bearer <user_token>` obtained
from `POST /api/api-login` (email/password). Optional context overrides on any call:
`syear`, `term_id`, `sub_institute_id` (query or body).

Common envelope:

```json
{ "success": true, "message": "Success", "data": ..., "errors": null, "meta": { "pagination": {...} } }
```

Errors: `422` validation (`errors` filled), `404` not found, `401` bad/missing token, `500` server.

List endpoints additionally accept: `search`, `sort_by`, `sort_dir` (`asc|desc`),
`page`, `per_page` (`per_page=0` disables pagination).

---

## 1. Result Template (`result-template`)

Report-card / certificate HTML templates (table `result_template_master`).

### GET /api/result/result-template — list templates

| Param | Type | Required | Notes |
|---|---|---|---|
| search | string | no | matches module_name, title, user_created |
| page / per_page / sort_by / sort_dir | — | no | standard list params |

Sample request: `GET /api/result/result-template?search=Result&per_page=10`

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "id": 12, "module_name": "Result", "title": "CBSE 1-5 Term Result",
      "html_content": "<div class=\"result-card\">...<< student_name_value >>...</div>",
      "created_by": 101, "created_at": "2025-04-23T10:11:12.000000Z",
      "status": 1, "user_created": "Uma  Patel"
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 } }
}
```

### GET /api/result/result-template/tags — all dynamic template tags

No params. Returns the HTML help text listing every supported tag
(`<< student_name_value >>`, `<< scholastic_marks >>`, `<< total_attendance >>`, ...).

```json
{ "success": true, "message": "Success", "data": "To make the template values dynamic,please use the following table as below.\n <ul>\n <li><b><< result_left_logo >> ...</ul>", "errors": null }
```

### POST /api/result/result-template — create template

| Param | Type | Required |
|---|---|---|
| module_name | string | yes |
| title | string | yes |
| html_content | string (HTML) | yes |

Sample request body:

```json
{ "module_name": "Result", "title": "Primary Progress Card", "html_content": "<div><< student_name_value >></div>" }
```

Response `201`:

```json
{ "success": true, "message": "Result Template Added Successfully", "data": { "status": "1", "message": "Result Template Added Successfully" }, "errors": null }
```

### GET /api/result/result-template/{id} — single template (edit data)

Response:

```json
{
  "success": true, "message": "Success",
  "data": { "template_data": { "id": 12, "module_name": "Result", "title": "CBSE 1-5 Term Result", "html_content": "<div>...</div>", "status": 1, "created_by": 101, "sub_institute_id": 4, "created_at": "...", "updated_at": "..." } },
  "errors": null
}
```

`404` when the id does not exist.

### PUT /api/result/result-template/{id} — update template

Same body as store. Response message: `"Template Updated Successfully"`.

### DELETE /api/result/result-template/{id} — delete template

Response message: `"Template Deleted Successfully"`.

### DELETE /api/result/result-template/bulk — bulk delete

Body: `{ "ids": [12, 13] }` → `{ "success": true, "message": "Template Deleted Successfully", "data": null }`

### GET /api/result/result-template/dropdown — id/name pairs

```json
{ "success": true, "data": [ { "id": 12, "name": "CBSE 1-5 Term Result", "module_name": "Result" } ], "message": "Success", "errors": null }
```

> Caveat: no `GET create` endpoint — the web `create()` returns an empty form with no defaults.

---

## 2. Student Result Remarks (`student-result-remarks`)

Class-teacher remarks per student per term (table `result_remarks`).

### GET /api/result/student-result-remarks — screen context

No params. Returns the hydrated context only:

```json
{ "success": true, "message": "Success", "data": { "sub_institute_id": 4, "syear": 2025 }, "errors": null }
```

### GET /api/result/student-result-remarks/students — students of a class + existing remarks

| Param | Type | Required | Notes |
|---|---|---|---|
| standard | int | yes | standard id |
| division | int | yes | division/section id |
| term | int | yes | term id (existing remarks are matched on it) |
| grade | int | no | echoed back only |
| search | string | no | first/middle/last name, enrollment_no, roll_no, result_remarks |

Sample request: `GET /api/result/student-result-remarks/students?grade=2&standard=15&division=3&term=1`

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "id": 501, "first_name": "Aarav", "middle_name": "R", "last_name": "Shah",
      "enrollment_no": "EN2025001", "mobile": "9876543210",
      "roll_no": 1, "gr_number": 7841,
      "result_remarks": "Excellent performance||Keep it up"
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 32, "last_page": 2 } }
}
```

(rows contain all `tblstudent` columns plus `roll_no`, `gr_number`, `result_remarks`;
`result_remarks` format is `<selected remark>||<free text>`.)

### POST /api/result/student-result-remarks — save/update remarks

| Param | Type | Required | Notes |
|---|---|---|---|
| student_id | int[] | yes | students to save |
| term_id | int | yes | |
| result_remarks | map<student_id,string> | no | remark chosen from master |
| result_remarks_input | map<student_id,string> | no | free-text remark |
| grade_id / standard_id / division_id | int | no | context (not persisted) |

Sample body:

```json
{
  "student_id": [501, 502],
  "term_id": 1,
  "grade_id": 2, "standard_id": 15, "division_id": 3,
  "result_remarks": { "501": "Excellent performance", "502": "Needs improvement" },
  "result_remarks_input": { "501": "Keep it up" }
}
```

Response `201`:

```json
{ "success": true, "message": "Added Successfully !", "data": { "status": "1", "message": "Added Successfully !" }, "errors": null }
```

---

## 3. Approve Mobile Result (`approve-mobile-result`)

Approve/deny generated result cards for the mobile app (`result_html.is_allowed`).

### GET /api/result/approve-mobile-result — screen defaults

No params. Returns the academic terms of the year + context:

```json
{
  "success": true, "message": "Success",
  "data": {
    "terms": [ { "id": 9, "sub_institute_id": 4, "syear": 2025, "term_id": 1, "title": "Term 1", "start_date": "2025-06-01", "end_date": "2025-10-31", "sort_order": 1 } ],
    "status": "1", "message": "Success", "syear": 2025, "sub_institute_id": 4, "term_id": null
  },
  "errors": null
}
```

### GET /api/result/approve-mobile-result/create — generated result cards of a class

| Param | Type | Required |
|---|---|---|
| grade | int | yes |
| standard | int | yes |
| division | int | yes |
| term_id | int | yes |
| search | string | no (student_name, enrollment_no, standard, division) |

Sample request: `GET /api/result/approve-mobile-result/create?grade=2&standard=15&division=3&term_id=1`

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "id": 501, "student_id": 501, "grade_id": 2, "standard_id": 15, "division_id": 3,
      "term_id": 1, "syear": 2025, "sub_institute_id": 4,
      "html": "<div class=\"result-card\">...</div>", "is_allowed": "N",
      "enrollment_no": "EN2025001", "student_name": "Aarav Shah",
      "standard": "5", "division": "A"
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 30, "last_page": 2 } }
}
```

### POST /api/result/approve-mobile-result — approve / un-approve

| Param | Type | Required | Notes |
|---|---|---|---|
| students | int[] | no (default []) | student ids to set `is_allowed = 'Y'` |
| student_id | int[] | no (default []) | student ids to set `is_allowed = 'N'` |
| grade_id / standard_id / division_id / term_id | int | yes | which result_html rows to flag |

Sample body:

```json
{ "students": [501, 502], "student_id": [503], "grade_id": 2, "standard_id": 15, "division_id": 3, "term_id": 1 }
```

Response: `{ "success": true, "message": "Success", "data": { "status": "1", "message": "Success" }, "errors": null }`

---

## 4. Upload Result (`upload-result`)

Attach an externally generated result file (image/PDF) per student per term
(table `upload_result`, files stored under `storage/upload_result/`).

### GET /api/result/upload-result — screen context

No params. `data`: `{ "status": "1", "message": "Success" }` (web index carries no data).

### GET /api/result/upload-result/create — students of a class + uploaded file per term

| Param | Type | Required | Notes |
|---|---|---|---|
| term | int | yes | term whose uploads are shown |
| grade | int | no | filter |
| standard | int | no | filter |
| division | int | no | filter |
| search | string | no | student_name, enrollment_no, uniqueid, standard_name, division_name, file_name |

Sample request: `GET /api/result/upload-result/create?grade=2&standard=15&division=3&term=1`

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "CHECKBOX": 501, "student_name": "Aarav R Shah", "grade": "Primary",
      "standard_name": "5", "division_name": "A",
      "enrollment_no": "EN2025001", "mobile": "9876543210", "uniqueid": "U1001",
      "file_name": "upload_result-20250715101112-48213.pdf", "term_name": "Term 1"
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 32, "last_page": 2 } }
}
```

### POST /api/result/upload-result — upload result files (multipart/form-data)

| Field | Type | Required | Notes |
|---|---|---|---|
| students[] | int[] | yes | selected student ids |
| grade_id | int | yes | |
| standard_id | int | yes | |
| term_id | int | yes | |
| division_id | int | no | context only |
| image[<student_id>] | file | conditional | one file keyed by student id; **if any `image` file is sent, one must be present for EVERY id in `students[]`** (legacy code indexes `image[student_id]` for each student when any file exists) |

Sample (curl):

```bash
curl -X POST /api/result/upload-result \
  -H "Authorization: Bearer <token>" \
  -F "students[]=501" -F "students[]=502" \
  -F "grade_id=2" -F "standard_id=15" -F "division_id=3" -F "term_id=1" \
  -F "image[501]=@aarav_result.pdf" -F "image[502]=@diya_result.pdf"
```

Response `201`:

```json
{ "success": true, "message": "Result Uploaded Successfully", "data": { "status": "1", "message": "Result Uploaded Successfully" }, "errors": null }
```

> **Existing endpoint (not duplicated):** `POST /uploadResultAPI` (routes/result.php, outside
> the api group; JWT validated inside the method). Params: `student_id`, `sub_institute_id`,
> `syear`. Returns the student's uploaded results per term with a public
> `https://<host>/storage/upload_result/<file>` link. Mobile apps already consume it — the new
> `GET upload-result/create` covers the staff-side view instead.

---

## 5. All Results (`all-results`) — student token

A student's generated result cards across years/terms (table `result_html`).
The web controller resolves the student from the session `user_id`, so call these
with a **student login token**.

### GET /api/result/all-results — list my results (one row per year/term)

| Param | Type | Required |
|---|---|---|
| search | string | no (standard name, term title, syear) |

```json
{
  "success": true, "message": "Success",
  "data": [
    { "id": 8841, "standard_id": 15, "term_id": 1, "name": "5", "title": "Term 1", "syear": 2025 },
    { "id": 7210, "standard_id": 14, "term_id": 2, "name": "4", "title": "Term 2", "syear": 2024 }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 2, "last_page": 1 } }
}
```

### GET /api/result/all-results/{id} — render one result card to PDF

`{id}` = `result_html.id` from the list above.

| Param | Type | Required | Notes |
|---|---|---|---|
| student_id | int | no | only used in the generated PDF file name |

Sample request: `GET /api/result/all-results/8841?student_id=501`

```json
{
  "success": true, "message": "Success",
  "data": {
    "result_data": { "id": 8841, "student_id": 501, "standard_id": 15, "term_id": 1, "syear": 2025, "html": "<div>...</div>", "is_allowed": "Y" },
    "pdf_link": "http://erp.example.com/storage/result_pdf/501_20260722103015.pdf"
  },
  "errors": null
}
```

`404` when the result_html id does not exist.

> Caveats (legacy behavior, unchanged): every call **deletes all files in
> `storage/result_pdf/`** before generating the new PDF (links are single-use, fetch the PDF
> immediately); the link is built with `http://` + server name.

---

## 6. Result personalize / PAL lookups

Read-only analytics endpoints wrapping `result\result_api\resultAPIController`.
All of them default `sub_institute_id` / `syear` from the token session, overridable per call.

### GET /api/result/personalize-marks — previous-years personalized marks

| Param | Type | Required | Notes |
|---|---|---|---|
| enrollment_no | string | no | filter one student |
| standard | string | no | standard NAME as stored in result_personalize_marks |
| sub_institute_id | int | no | default from token |

Sample request: `GET /api/result/personalize-marks?enrollment_no=EN2025001`

```json
{
  "success": true, "message": "Success",
  "data": {
    "previousdata": {
      "overallresult": {
        "5": [ { "subjectname": "Mathematics", "totalmarks": "200", "totalobtain": "176" } ]
      },
      "standarddata": [
        {
          "standardname": "5", "totalmarks": 400, "totalobtain": 342,
          "subjectdata": [
            { "title": "Mathematics", "examdata": [ { "title": "Unit Test 1,Term 1", "marks": "100", "obtain": "88" } ] }
          ]
        }
      ]
    }
  },
  "errors": null
}
```

### GET /api/result/pal-marks — PAL chapter-wise marks of a student

| Param | Type | Required |
|---|---|---|
| standard_id | int | yes |
| student_id | int | yes |
| subject_id | int | no |
| sub_institute_id / syear | int | no (default from token) |

Sample request: `GET /api/result/pal-marks?standard_id=15&student_id=501&subject_id=7`

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "standard_id": 15, "subject_id": 7, "question_paper_id": 91,
      "paper_name": "Fractions Quiz", "student_id": 501,
      "total_marks": "40", "obtain_marks": "31",
      "question_ids": "12,13,14,15", "question_titles": "Q1...,Q2...",
      "question_str": "12,13,14,15", "chapter_name": "Fractions", "chapter_id": 22
    }
  ],
  "errors": null
}
```

### GET /api/result/map-value — question-mapping values for one chapter

| Param | Type | Required | Notes |
|---|---|---|---|
| standard_id | int | yes | |
| subject_id | int | yes | |
| chapter_id | int | yes | |
| student_id | int | yes | |
| exam_type | string | no | `pal` → attempts from lms_online_exam_student, else lms_online_exam |
| sub_institute_id / syear | int | no | default from token |

Sample request: `GET /api/result/map-value?standard_id=15&subject_id=7&chapter_id=22&student_id=501&exam_type=pal`

```json
{
  "success": true, "message": "Success",
  "data": {
    "skill": [
      { "id": 311, "questionmaster_id": 12, "type_name": "Skills", "value_name": "Problem Solving", "total_question": 3, "type": "skill" }
    ],
    "interest": [
      { "id": 320, "questionmaster_id": 14, "type_name": "Interests", "value_name": "Investigative", "total_question": 2, "type": "interest" }
    ]
  },
  "errors": null
}
```

(keys of `data` are the `lms_mapping_type.type` values present; empty object when nothing mapped.)

### GET /api/result/current-result — current-year result + LMS chapter analysis

| Param | Type | Required | Notes |
|---|---|---|---|
| standard | int | yes | standard id |
| student_id | int | yes | |
| subject_id | int | no | narrows chapter analysis |
| sub_institute_id / syear | int | no | default from token |

Sample request: `GET /api/result/current-result?standard=15&student_id=501`

```json
{
  "success": true, "message": "Success",
  "data": {
    "currentdata": {
      "standarddata": {
        "standardname": "5", "totalmarks": 400, "totalobtain": 342,
        "subjectdata": [
          { "subject_id": 7, "title": "Mathematics", "examdata": [ { "title": "Unit Test 1", "total": "100", "obtain": "88" } ] }
        ]
      },
      "subjectdata": {
        "7": {
          "subjectdata": "Mathematics", "totalmarks": 200, "totalobtain": 176,
          "chapterdata": {
            "7": [
              {
                "chapter_id": 22, "title": "Fractions", "totalmarks": "40", "totalobtain": "31",
                "chapterrank": 7.8, "recommendation": [],
                "chapteroutcome": [ { "type": "Skills", "data": [ { "title": "Problem Solving", "progress": 3 } ] } ],
                "chapterprogress": [ { "quiz": "Fractions Quiz-Chapter test", "students": [ { "id": 502, "name": "Diya  Mehta", "photo": "https://erp.triz.co.in/storage/student/diya.jpg", "progress": "28", "total": "40" } ] } ]
              }
            ]
          }
        }
      },
      "student_id": 501, "syear": "2025", "sub_institute_id": "4",
      "photo": "aarav.jpg", "birthdate": "2015-03-14", "address": "12, MG Road",
      "admission_date": "2021-06-01", "house": "Red House", "batch": "Morning",
      "optional_subjects": "Sanskrit,Drawing"
    }
  },
  "errors": null
}
```

> Caveat: this endpoint runs several heavy legacy queries (per-chapter loops); response time
> grows with chapter count. `standard`/`student_id` are validated as integers because the
> legacy SQL concatenates them raw.
