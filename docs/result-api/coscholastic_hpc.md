# Result API — Co-Scholastic Master, Co-Scholastic, HPC Skillset, HPC Activity Master

Base URL: `/api/result` · All endpoints require `Authorization: Bearer <user_token>`
(token from `POST /api/api-login` with email/password). Optional context overrides on any
request: `syear`, `term_id`, `sub_institute_id` (query or body).

Common list parameters (every GET list endpoint): `search` (string, optional),
`sort_by` (string, optional), `sort_dir` (`asc`|`desc`, default `asc`),
`page` (int, default 1), `per_page` (int, default 25, `0` = no pagination).

Envelope: `{"success": true, "message": "...", "data": ..., "errors": null}`
(+ `meta.pagination` on lists). Errors: 422 validation, 404 not found, 401 auth, 500 other.

---

## 1. Co-Scholastic Master (`co-scholastic-master`)
Parent categories for co-scholastic areas. Table `result_co_scholastic_parent`
(columns: `id`, `title`, `sort_order`, `sub_institute_id`, `created_at`, `updated_at`).

### GET /api/result/co-scholastic-master — list categories
Params: common list parameters (search across `title`).

Sample response:
```json
{
  "success": true, "message": "Success",
  "data": [
    { "id": 3, "title": "Art Education", "sort_order": 1, "sub_institute_id": 4,
      "created_at": "2024-04-01 10:00:00", "updated_at": null }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 6, "last_page": 1 } }
}
```

### GET /api/result/co-scholastic-master/create — form defaults
No params. Returns `SortOrder` (next sort order) and `ddValue` (existing categories).
```json
{ "success": true, "message": "Success",
  "data": { "SortOrder": 7, "ddValue": [ { "id": 3, "title": "Art Education", "sort_order": 1, "sub_institute_id": 4 } ] },
  "errors": null }
```

### POST /api/result/co-scholastic-master — create category
| Param | Type | Required |
|---|---|---|
| title | string | yes |
| sort_order | int | yes |

Request: `{ "title": "Health & Physical Education", "sort_order": 7 }`
Response 201: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### GET /api/result/co-scholastic-master/{id} — single category (edit data)
Returns the row's columns plus `ddValue` (all categories). 404 when the id does not exist.
```json
{ "success": true, "message": "Success",
  "data": { "id": 3, "title": "Art Education", "sort_order": 1, "sub_institute_id": 4,
            "ddValue": [ { "id": 3, "title": "Art Education", "sort_order": 1 } ] },
  "errors": null }
```

### PUT /api/result/co-scholastic-master/{id} — update category
Same body as POST. Response: `"message": "Data Saved"`.

### DELETE /api/result/co-scholastic-master/{id} — delete category
Response: `{ "success": true, "message": "Data Deleted", "data": { "status": "1", "message": "Data Deleted" }, "errors": null }`

### DELETE /api/result/co-scholastic-master/bulk — bulk delete
Body: `{ "ids": [3, 4] }` → `"message": "Data Deleted"`.

### GET /api/result/co-scholastic-master/dropdown — id/name pairs
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 3, "name": "Art Education", "sort_order": 1 } ], "errors": null }
```

---

## 2. Co-Scholastic mapping (`co-scholastic`)
Co-scholastic areas mapped to standard + term with MARK or GRADE evaluation.
Tables `result_co_scholastic` (columns: `id`, `term_id`, `title`, `sort_order`, `parent_id`,
`mark_type`, `max_mark`, `co_grade` [grade map id], `standard_id`, `sub_institute_id`) and
`result_co_scholastic_grade` (columns: `id`, `map_id`, `title`, `break_off`, `sub_institute_id`).

### GET /api/result/co-scholastic — list areas
Params: common list parameters (search across `title`, `parent_name`, `term_name`, `standard`).
```json
{ "success": true, "message": "Success",
  "data": [
    { "id": 12, "term_id": 2, "title": "Drawing", "sort_order": 1, "parent_id": 3,
      "mark_type": "GRADE", "max_mark": null, "co_grade": 5, "standard_id": 8,
      "sub_institute_id": 4, "parent_name": "Art Education", "term_name": "Term 1",
      "standard": "STD 5" }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 14, "last_page": 1 } } }
```

### GET /api/result/co-scholastic/create — form defaults
Returns `standard` (standards of the institute), `SortOrder` (next sort order),
`ddValue` (parent categories from `result_co_scholastic_parent`).

### POST /api/result/co-scholastic — create area (one row per standard)
| Param | Type | Required |
|---|---|---|
| title | string | yes |
| term | int (term_id) | yes |
| parent_id | int | yes |
| mark_type | string `MARK`\|`GRADE` | yes |
| sort_order | int | yes (auto-incremented per created standard row) |
| standard | array of standard ids | yes |
| max_mark | number | no (used for MARK) |
| co_grade | array of `{title, break_off}` | required when mark_type=GRADE |

Request:
```json
{ "title": "Drawing", "term": 2, "parent_id": 3, "mark_type": "GRADE", "sort_order": 5,
  "standard": [8, 9],
  "co_grade": [ { "title": "A", "break_off": 90 }, { "title": "B", "break_off": 75 } ] }
```
Response 201: `{ "success": true, "message": "Data Saved", "data": { "status": "1", "message": "Data Saved" }, "errors": null }`

### GET /api/result/co-scholastic/{id} — single area (edit data)
Returns the row's columns plus `grd_data` (its grade rows), `ddValue` (parent categories)
and `standard` (standards list). 404 when the id does not exist.
```json
{ "success": true, "message": "Success",
  "data": { "id": 12, "term_id": 2, "title": "Drawing", "sort_order": 1, "parent_id": 3,
            "mark_type": "GRADE", "max_mark": null, "co_grade": 5, "standard_id": 8,
            "grd_data": [ { "id": 31, "map_id": 5, "title": "A", "break_off": 90, "sub_institute_id": 4 } ],
            "ddValue": [ { "id": 3, "title": "Art Education" } ],
            "standard": [ { "id": 8, "name": "STD 5" } ] },
  "errors": null }
```

### PUT /api/result/co-scholastic/{id} — update area
Same fields as POST but `standard` is a **single id** (stored to `standard_id`). In
`co_grade`, rows containing an existing `id` are updated; rows without `id` are inserted.
Request:
```json
{ "title": "Drawing", "term": 2, "parent_id": 3, "mark_type": "GRADE", "sort_order": 1,
  "standard": 8,
  "co_grade": [ { "id": 31, "title": "A+", "break_off": 92 }, { "title": "C", "break_off": 50 } ] }
```
Response: `"message": "Data Saved"`.

### DELETE /api/result/co-scholastic/{id} — delete area
Response: `"message": "Data Deleted"` (grade rows are kept — same as web).

### DELETE /api/result/co-scholastic/bulk — bulk delete
Body: `{ "ids": [12, 13] }`.

### GET /api/result/co-scholastic/dropdown — id/name pairs
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 12, "name": "Drawing", "parent_id": 3, "parent_name": "Art Education",
              "standard_id": 8, "term_id": 2, "mark_type": "GRADE" } ], "errors": null }
```

---

## 3. HPC Skillset (`hpc-skillset`)
Holistic Progress Card skillsets. Table `result_skillset` (columns: `id`, `main_title`,
`title`, `standard` [standard id], `group`, `sort_order`, `sub_institute_id`, `created_by`,
`created_at`, `updated_at`).

### GET /api/result/hpc-skillset — list skillsets
Params: common list parameters (search across `main_title`, `title`, `standard_name`, `group`).
```json
{ "success": true, "message": "Success",
  "data": [
    { "id": 5, "main_title": "Physical Development", "title": "Gross Motor Skills",
      "standard": 2, "group": 1, "sort_order": 1, "sub_institute_id": 4,
      "created_by": 21, "created_at": "2025-05-02 09:12:33", "updated_at": null,
      "standard_name": "Nursery" }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 9, "last_page": 1 } } }
```

### GET /api/result/hpc-skillset/create — form defaults
Returns `standardLists` (standards) and `get_result_activity_groups`
(rows of `result_activity_group`).

### POST /api/result/hpc-skillset — create skillset
| Param | Type | Required |
|---|---|---|
| main_title | string | yes |
| title | string | yes |
| standard | int (standard id) | yes |
| group | int (activity group id) | no |
| sort_order | int | no |

Request: `{ "main_title": "Physical Development", "title": "Fine Motor Skills", "standard": 2, "group": 1, "sort_order": 2 }`
Response 201: `{ "success": true, "message": "Skillset added successfully.", "data": { "status": "1", "message": "Skillset added successfully." }, "errors": null }`

### GET /api/result/hpc-skillset/{id} — single skillset (edit data)
Returns `result_skillset` (the row), `standardLists`, `get_result_activity_groups`.
404 when the id does not exist.

### PUT /api/result/hpc-skillset/{id} — update skillset
Same body as POST (only `main_title`, `title`, `standard`, `group`, `sort_order` are
persisted; anything else is ignored). Also updates `standard` on all
`result_activity_master` rows with `skill_id = {id}` (existing web behaviour).
Response: `"message": "Skillset updated successfully."`

### DELETE /api/result/hpc-skillset/{id} — delete skillset
Response: `"message": "Skillset deleted successfully"`.

### DELETE /api/result/hpc-skillset/bulk — bulk delete
Body: `{ "ids": [5, 6] }`.

### GET /api/result/hpc-skillset/dropdown — id/name pairs (for skill_id pickers)
```json
{ "success": true, "message": "Success",
  "data": [ { "id": 5, "name": "Gross Motor Skills", "main_title": "Physical Development",
              "standard_id": 2, "standard_name": "Nursery" } ], "errors": null }
```

---

## 4. HPC Activity Master (`hpc-activity-master`)
Level-3 activities and level-4 sub-activities of the HPC hierarchy. Tables
`result_activity_master` (columns: `id`, `title`, `skill_id`, `standard`, `term_id`,
`sort_order`, `sub_institute_id`, `created_by`, `created_at`, `updated_at`) and
`result_sub_activity` (columns: `id`, `title`, `skill_id`, `sub_skill_id`, `sort_order`,
`sub_institute_id`, `created_by`, `created_at`, `updated_at`).

### GET /api/result/hpc-activity-master — list activities
Params: common list parameters (search across `title`, `result_main_title`, `result_title`,
`standard`, `term_name`, `sub_activity`). `meta.termwise_hpc` (`Yes`/`No`) carries the
institute's termwise-HPC setting.
```json
{ "success": true, "message": "Success",
  "data": [
    { "id": 17, "title": "Running & Jumping", "skill_id": 5, "standard": "Nursery",
      "term_id": 2, "sort_order": 1, "sub_institute_id": 4, "created_by": 21,
      "created_at": "2025-05-02 10:00:00", "updated_at": null,
      "result_main_title": "Physical Development", "result_title": "Gross Motor Skills",
      "sub_activity": "Sprint|||Long jump", "term_name": "Term 1" }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 12, "last_page": 1 },
            "termwise_hpc": "No" } }
```

### GET /api/result/hpc-activity-master/create — form defaults
Returns `standardLists`, `result_skillsets`, `levelLayers` (`[3,4]`) and `termwise_hpc`.

### POST /api/result/hpc-activity-master — create activity or sub-activity
| Param | Type | Required |
|---|---|---|
| title | string | yes |
| skill_id | int (skillset id) | yes |
| levels | int (3 or 4) | no (send 4 with sub_skill_id for a level-4 sub-activity) |
| sub_skill_id | int (parent activity id) | required when levels=4 |
| standard | int (standard id) | required unless levels=4 |
| term | int (term_id) | no (level-3 rows only) |
| sort_order | int | no (auto max+1 when omitted) |

Level-3 request: `{ "title": "Running & Jumping", "skill_id": 5, "standard": 2, "term": 2 }`
Level-4 request: `{ "title": "Sprint", "skill_id": 5, "levels": 4, "sub_skill_id": 17 }`
Response 201: `{ "success": true, "message": "Result activity master added successfully.", "data": { "status": "1", "message": "Result activity master added successfully." }, "errors": null }`
(A failed insert returns 500 with `"Failed to add data"` — web behaviour.)

### GET /api/result/hpc-activity-master/{id} — single activity (edit data)
Returns `result_activity_masters` (the row), `result_sub_activity_masters` (first linked
sub-activity row or null), `result_skillsets` (skillsets of the row's standard),
`standardLists`, `termwise_hpc`. 404 when the id does not exist.

### PUT /api/result/hpc-activity-master/{id} — update activity (+ its sub-activities)
| Param | Type | Required |
|---|---|---|
| title | string | yes |
| skill_id | int | yes |
| standard | int | yes |
| sort_order | int | no |
| term | int (term_id) | no — **omitting it sets term_id to NULL** (web behaviour), so resend it |
| subData | object `{id: [], title: [], sort_order: []}` | no — parallel arrays updating linked `result_sub_activity` rows |

Request:
```json
{ "title": "Running & Jumping", "skill_id": 5, "standard": 2, "sort_order": 1, "term": 2,
  "subData": { "id": [41, 42], "title": ["Sprint", "Long jump"], "sort_order": [1, 2] } }
```
Response: `"message": "Result activity master updated successfully."`
Only the whitelisted columns above are persisted; anything else is ignored.

### DELETE /api/result/hpc-activity-master/{id} — delete activity
Response: `"message": "Result activity master deleted successfully"` (linked
sub-activities are kept — same as web).

### DELETE /api/result/hpc-activity-master/bulk — bulk delete
Body: `{ "ids": [17, 18] }`.

### DELETE /api/result/hpc-activity-master/sub-activity/{id} — delete one sub-activity
`{id}` = `result_sub_activity.id` (web reads it as `sub_id` input; the API merges the URL
segment in). Response: `"message": "Result activity master deleted successfully"`.

### GET /api/result/hpc-activity-master/activity-lists — dependent hierarchy lists
| Param | Type | Required | Effect |
|---|---|---|---|
| skill_id | int | conditional | with `standard`: level-3 activities of that skillset+standard; with `level=4`: parent activity id whose sub-activities are returned |
| standard | int | conditional | with `skill_id` (as above) or with `level=2`: skillsets of that standard |
| level | int (2 or 4) | conditional | selects which table is queried |

Examples:
- `GET .../activity-lists?skill_id=5&standard=2` → rows from `result_activity_master`
- `GET .../activity-lists?level=4&skill_id=17` → rows from `result_sub_activity` (sub_skill_id=17)
- `GET .../activity-lists?level=2&standard=2` → rows from `result_skillset`

```json
{ "success": true, "message": "Success",
  "data": [ { "id": 17, "title": "Running & Jumping", "skill_id": 5, "standard": 2,
              "term_id": 2, "sort_order": 1, "sub_institute_id": 4 } ], "errors": null }
```
When no recognised parameter combination is sent, `data` is `[]` with message `"No Data"`.

---

## Caveats

1. **Co-Scholastic store/update `co_grade`:** the web controller iterates the PHP
   `$_REQUEST` superglobal for `co_grade`. The API controller primes
   `$_REQUEST['co_grade']` from the parsed request body, so both form-encoded and JSON
   clients work.
2. **HPC Skillset PUT and HPC Activity Master PUT do not use `delegate()`:** the web
   `update()` methods write `$request->except(...)` straight into the table, so the
   middleware's `type=API` flag (or `syear`/`term_id`/`sub_institute_id` overrides) would
   become bogus UPDATE columns. The API passes a whitelisted copy of the request without
   `type`; the web method then takes its redirect branch and the flashed payload is read
   back from the (in-memory) session. Consequence: only the documented columns are ever
   persisted through these two endpoints.
3. **HPC Activity Master PUT `term`:** the web code always writes `term_id` from the
   `term` input; omitting `term` sets the column to NULL. Clients should resend it.
4. **`show` endpoints reuse the web `edit()` methods** (web `show()` methods are empty),
   so they return the row plus the edit screen's dropdown datasets.
5. **No status-change endpoints** — none of the four web controllers has a status toggle.
6. **Delete endpoints are hard deletes** without child cleanup (co-scholastic grade rows
   and HPC sub-activities are left behind), mirroring the web controllers exactly.
