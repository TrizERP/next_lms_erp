# Result Module API — Masters (batch 1)

Modules: Exam Type Master, Grade Master, Standard-Grade Mapping, Working Day Master,
Student Result Remark Master.

**Auth (all endpoints):** `Authorization: Bearer <user_token>` obtained from
`POST /api/api-login` (email/password). Optional overrides on any request:
`syear`, `term_id`, `sub_institute_id` (query or body).

**Common list params (every GET list endpoint):**

| Param | Type | Required | Notes |
|---|---|---|---|
| search | string | no | case-insensitive, applied across the module's search keys |
| sort_by | string | no | any field of the row |
| sort_dir | string | no | `asc` (default) / `desc` |
| page | int | no | default 1 |
| per_page | int | no | default 25; `0` disables pagination |

**Envelope:** success `{"success":true,"message":"...","data":...,"errors":null}`
(+`meta.pagination` on lists); validation 422 `{"success":false,"message":"Validation Failed","errors":{...}}`;
error 500 `{"success":false,"message":"Something went wrong."}`; missing record 404; bad token 401.

---

## 1. Exam Type Master  (`/api/result/exam-type-master`)

Table: `result_exam_type_master` (`Id`, `Code`, `ExamType`, `ShortName`, `SortOrder`, `SubInstituteId`).
Search keys: `Code`, `ExamType`, `ShortName`.

### GET /api/result/exam-type-master — list exam types

```
GET /api/result/exam-type-master?search=unit&per_page=10
```

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "Id": 3, "Code": 3, "ExamType": "Unit Test", "ShortName": "UT",
      "SortOrder": 3, "SubInstituteId": 4,
      "created_at": "2024-05-01T05:10:11.000000Z", "updated_at": "2024-05-01T05:10:11.000000Z",
      "SrNo": 3
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 } }
}
```

### GET /api/result/exam-type-master/create — form defaults (next Code / SortOrder)

```json
{ "success": true, "message": "Success", "data": { "Code": 7, "SortOrder": 7 }, "errors": null }
```

### POST /api/result/exam-type-master — create

| Param | Type | Required |
|---|---|---|
| Code | int | yes |
| ExamType | string | yes |
| ShortName | string | no |
| SortOrder | int | yes |

```json
{ "Code": 7, "ExamType": "Semester Exam", "ShortName": "SEM", "SortOrder": 7 }
```

201:

```json
{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }
```

### GET /api/result/exam-type-master/{id} — single record (edit data)

```json
{
  "success": true, "message": "Success",
  "data": { "Id": 7, "Code": 7, "ExamType": "Semester Exam", "ShortName": "SEM", "SortOrder": 7, "SubInstituteId": 4,
            "created_at": "2026-07-22T08:00:00.000000Z", "updated_at": "2026-07-22T08:00:00.000000Z" },
  "errors": null
}
```

### PUT /api/result/exam-type-master/{id} — update
Same body/validation as POST. Returns `"message": "Data Saved"`.

### DELETE /api/result/exam-type-master/{id} — delete one
```json
{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }
```

### DELETE /api/result/exam-type-master (bulk) — body `{ "ids": [5,6] }`
```json
{ "success": true, "message": "Data Deleted", "data": null, "errors": null }
```

### GET /api/result/exam-type-master/dropdown — id/name pairs
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 1, "name": "Term Exam", "code": 1 }, { "id": 3, "name": "Unit Test", "code": 3 } ],
  "errors": null }
```

---

## 2. Grade Master  (`/api/result/grade-master`)

Tables: `grade_master` (`id`, `grade_name`, `sort_order`, `sub_institute_id`) and
`grade_master_data` (`id`, `grade_id`, `title`, `breakoff`, `gp`, `sort_order`, `comment`, `syear`, `sub_institute_id`).
Search keys: `grade_name`.

The web controller has **no edit or update** methods, so no `GET {id}` / `PUT {id}` is exposed.
`DELETE {id}` removes a **grade_master_data row** (grade row inside a scale), exactly like the web action.

### GET /api/result/grade-master — list grade scales with nested grade rows

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "id": 2, "grade_name": "A-E Scale", "sub_institute_id": 4, "sort_order": 1,
      "created_at": "2024-04-20T04:00:00.000000Z", "updated_at": "2024-04-20T04:00:00.000000Z",
      "grade_data": [
        { "id": 11, "syear": 2026, "grade_id": 2, "title": "A", "breakoff": 91, "gp": 10,
          "sort_order": 1, "comment": "Outstanding", "sub_institute_id": 4,
          "created_at": "2024-04-20T04:05:00.000000Z", "updated_at": "2024-04-20T04:05:00.000000Z" }
      ]
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 } }
}
```

### GET /api/result/grade-master/create-data/{id} — defaults for adding grade rows to scale {id}
(wraps web `grade_master/createData/{id}` → `AddAllData`)

```json
{ "success": true, "message": "Success", "data": { "grade_id": "2" }, "errors": null }
```

### POST /api/result/grade-master — create (two modes, like the web form)

Mode A — create a grade **scale** (default):

| Param | Type | Required |
|---|---|---|
| grade_name | string | yes |
| sort_order | int | no |

```json
{ "grade_name": "1-10 GPA Scale", "sort_order": 2 }
```

Mode B — create a grade **row** (`add_type=add_grade_data`):

| Param | Type | Required |
|---|---|---|
| add_type | string | yes (`add_grade_data`) |
| grade_id | int | yes |
| title | string | yes |
| breakoff | number | no |
| gp | number | no |
| sort_order | int | no |
| comment | string | no |

```json
{ "add_type": "add_grade_data", "grade_id": 2, "title": "B", "breakoff": 81, "gp": 9,
  "sort_order": 2, "comment": "Excellent" }
```

201:

```json
{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }
```

### DELETE /api/result/grade-master/{id} — delete a grade DATA row (`grade_master_data.id`)
```json
{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }
```

### DELETE /api/result/grade-master (bulk) — body `{ "ids": [11,12] }` (grade_master_data ids)
```json
{ "success": true, "message": "Data Deleted", "data": null, "errors": null }
```

### GET /api/result/grade-master/dropdown — grade scale id/name pairs
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 2, "name": "A-E Scale" }, { "id": 3, "name": "1-10 GPA Scale" } ],
  "errors": null }
```

---

## 3. Standard - Grade Mapping  (`/api/result/standard-grade-mapping`)

Table: `result_std_grd_maping` (`id`, `standard`, `grade_scale`, `sub_institute_id`), joined to
`grade_master`, `standard`, `academic_section`. Rows are grouped per grade scale.
Search keys: `scale_name`, `grade_name`, `standard_name`.

### GET /api/result/standard-grade-mapping — list mappings (one row per grade scale)

```json
{
  "success": true, "message": "Success",
  "data": [
    {
      "id": 5, "scale_name": "A-E Scale",
      "grade_name": "Primary,Secondary", "standard_name": "STD 1,STD 2,STD 3",
      "standard_id": ["1", "2", "3"], "grade_id": ["1", "2"], "grade_scale": 2
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 } }
}
```

### GET /api/result/standard-grade-mapping/create — form defaults
`ddValue` = grade scales NOT yet mapped.

```json
{ "success": true, "message": "Success",
  "data": { "ddValue": [ { "id": 3, "grade_name": "1-10 GPA Scale", "sub_institute_id": 4, "sort_order": 2,
                           "created_at": "2024-04-20T04:00:00.000000Z", "updated_at": "2024-04-20T04:00:00.000000Z" } ] },
  "errors": null }
```

### POST /api/result/standard-grade-mapping — create (one row per standard)

| Param | Type | Required |
|---|---|---|
| grade_scale | int | yes |
| standard | int[] | yes (min 1) |

```json
{ "grade_scale": 3, "standard": [4, 5, 6] }
```

201: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### GET /api/result/standard-grade-mapping/{id} — single mapping + edit dropdown
`ddValue` here = ALL grade scales (edit dropdown).

```json
{
  "success": true, "message": "Success",
  "data": {
    "id": 5, "scale_name": "A-E Scale", "grade_name": "Primary,Secondary",
    "standard_name": "STD 1,STD 2,STD 3", "standard_id": ["1", "2", "3"], "grade_id": ["1", "2"],
    "grade_scale": 2,
    "ddValue": [ { "id": 2, "grade_name": "A-E Scale", "sub_institute_id": 4, "sort_order": 1,
                   "created_at": "2024-04-20T04:00:00.000000Z", "updated_at": "2024-04-20T04:00:00.000000Z" } ]
  },
  "errors": null
}
```

### PUT /api/result/standard-grade-mapping/{id} — update
Same body/validation as POST. The web logic deletes every mapping row of the
record's grade scale and recreates one row per submitted standard.

### DELETE /api/result/standard-grade-mapping/{id} — delete
Deletes ALL mapping rows of the record's grade scale (web behaviour).
`{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }`

### DELETE /api/result/standard-grade-mapping (bulk) — body `{ "ids": [5,8] }`
`{ "success": true, "message": "Data Deleted", "data": null, "errors": null }`

### GET /api/result/standard-grade-mapping/dropdown
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 5, "name": "A-E Scale", "grade_scale": 2 } ],
  "errors": null }
```

---

## 4. Working Day Master  (`/api/result/working-day-master`)

Table: `result_working_day_master` (`id`, `standard`, `term_id`, `total_working_day`, `syear`, `sub_institute_id`),
joined to `academic_year`, `standard`, `academic_section`.
Search keys: `term_name`, `grade_name`, `standard_name`.

The web `create()` returns an empty Blade form with no data and no dropdown source exists,
so no GET create / dropdown endpoints are exposed.

### GET /api/result/working-day-master — list

```json
{
  "success": true, "message": "Success",
  "data": [
    { "id": 9, "term_name": "Term 1", "term_id": 1, "grade_name": "Primary", "grade_id": 1,
      "standard_name": "STD 1", "standard_id": 1, "total_working_day": 110 }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 } }
}
```

### POST /api/result/working-day-master — create (one row per standard)

| Param | Type | Required |
|---|---|---|
| term | int | yes (term_id) |
| total_working_day | int | yes |
| standard | int[] | yes (min 1) |

```json
{ "term": 1, "total_working_day": 110, "standard": [1, 2, 3] }
```

201: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### GET /api/result/working-day-master/{id} — single record (edit data)

```json
{ "success": true, "message": "Success",
  "data": { "id": 9, "term_name": "Term 1", "term_id": 1, "grade_name": "Primary", "grade_id": 1,
            "standard_name": "STD 1", "standard_id": 1, "total_working_day": 110 },
  "errors": null }
```

### PUT /api/result/working-day-master/{id} — update (single standard)

| Param | Type | Required |
|---|---|---|
| term | int | yes |
| total_working_day | int | yes |
| standard | int | yes (single standard id) |

```json
{ "term": 1, "total_working_day": 115, "standard": 1 }
```

### DELETE /api/result/working-day-master/{id}
`{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }`

### DELETE /api/result/working-day-master (bulk) — body `{ "ids": [9,10] }`
`{ "success": true, "message": "Data Deleted", "data": null, "errors": null }`

---

## 5. Student Result Remark Master  (`/api/result/result-remark-master`)

Table: `result_remark_masters` (`id`, `title`, `remark_status`, `sort_order`, `marking_period_id`, `syear`, `sub_institute_id`).
Search keys: `title`, `remark_status`.

The web `create()` returns an empty Blade form with no data, so no GET create endpoint is exposed.

### GET /api/result/result-remark-master — list

```json
{
  "success": true, "message": "Success",
  "data": [
    { "id": 4, "syear": 2026, "sub_institute_id": 4, "marking_period_id": null,
      "title": "PASS", "remark_status": "PASS", "sort_order": 1,
      "created_at": "2024-04-25 05:00:00", "updated_at": "2024-04-25 05:00:00" }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 } }
}
```

### POST /api/result/result-remark-master — create

| Param | Type | Required |
|---|---|---|
| title | string | yes |
| result_status | string | no (stored as `remark_status`) |
| sort_order | int | no |

```json
{ "title": "PROMOTED", "result_status": "PASS", "sort_order": 2 }
```

201: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### GET /api/result/result-remark-master/{id} — single record (edit data)

```json
{ "success": true, "message": "Success",
  "data": { "id": 4, "syear": 2026, "sub_institute_id": 4, "marking_period_id": null,
            "title": "PASS", "remark_status": "PASS", "sort_order": 1,
            "created_at": "2024-04-25T05:00:00.000000Z", "updated_at": "2024-04-25T05:00:00.000000Z" },
  "errors": null }
```

### PUT /api/result/result-remark-master/{id} — update

| Param | Type | Required |
|---|---|---|
| title | string | yes |
| result_status | string | no |
| sort_order | int | no |
| term | int | no (stored as `marking_period_id`) |

```json
{ "title": "PASS", "result_status": "PASS", "sort_order": 1, "term": 1 }
```

### DELETE /api/result/result-remark-master/{id}
`{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }`

### DELETE /api/result/result-remark-master (bulk) — body `{ "ids": [4,5] }`
`{ "success": true, "message": "Data Deleted", "data": null, "errors": null }`

### GET /api/result/result-remark-master/dropdown
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 4, "name": "PASS", "remark_status": "PASS" } ],
  "errors": null }
```

---

## Caveats

1. **Grade Master** has no edit/update in the web controller — no `GET {id}` / `PUT {id}`.
   Its `DELETE {id}` targets `grade_master_data.id` rows (grade rows inside a scale),
   never `grade_master` scales — identical to the web behaviour.
2. **Working Day Master** and **Result Remark Master** web `create()` return empty Blade
   forms — no `GET create` endpoints. Working Day Master has no natural dropdown, so none
   is exposed.
3. The legacy Grade Master / Std-Grade Mapping / Working Day Master controllers read
   `$_REQUEST['add_type']` / `$_REQUEST['standard']` (PHP superglobals). The API wrappers
   bridge these from the parsed request so JSON bodies work; form-encoded bodies work
   either way.
4. **Std-Grade Mapping** `update`/`destroy` in the web controller assume the id exists
   (undefined-index otherwise); the API pre-checks via the same legacy `getData()` and
   returns a clean 404.
5. Delete responses echo the legacy `status: "1"` payload; bulk delete loops the existing
   `destroy` per id (no new transactions added).
