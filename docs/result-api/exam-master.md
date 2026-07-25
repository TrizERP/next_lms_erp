# Exam Master API

Wraps `result\ExamMaster\ExamMasterController`. Base URI: `/api/result/exam-master`.
All requests need `Authorization: Bearer <user_token>`.

| Method | URI | Purpose |
|---|---|---|
| GET | `/api/result/exam-master` | List exams (search/sort/pagination, filter `standard_id`) |
| GET | `/api/result/exam-master/create` | Form defaults (next Code/SortOrder, term + standard dropdowns) |
| POST | `/api/result/exam-master` | Create exam rows (one per standard × term) |
| GET | `/api/result/exam-master/{id}` | Single exam + edit dropdown data |
| PUT | `/api/result/exam-master/{id}` | Update exam |
| DELETE | `/api/result/exam-master/{id}` | Delete exam |
| DELETE | `/api/result/exam-master/bulk` | Bulk delete, body `{"ids":[1,2]}` |
| GET | `/api/result/exam-master/dropdown` | `[{id, name, standard_id, term_id}]` for selects |

## GET /api/result/exam-master

Query params: `search` (matches ExamTitle/standard/term/Code), `standard_id`,
`sort_by`, `sort_dir`, `page`, `per_page`, `syear`, `term_id`.

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "Id": 12, "Code": 3, "ExamType": 14, "ExamTitle": "Term 1 Final",
      "SortOrder": 3, "SubInstituteId": 1, "standard_id": 5, "term_id": 2,
      "weightage": "40", "std_name": "Std 5", "term": "Term 1",
      "total_count": 4, "SrNo": 1
    }
  ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 18, "last_page": 1 } }
}
```

## POST /api/result/exam-master

```json
{
  "Code": 4,
  "ExamTitle": "Unit Test 2",
  "SortOrder": 4,
  "weightage": "20",
  "all_standard": [5, 6],
  "all_term": [1, 2]
}
```

Creates one `result_exam_master` row per standard × term (existing behavior).
Response `201`: `{ "success": true, "message": "Data Saved", ... }`

Validation (422 when missing): `Code`, `ExamTitle`, `SortOrder`,
`all_standard[]`, `all_term[]`.

## PUT /api/result/exam-master/{id}

Same body as POST (single `all_standard[0]` / `all_term[0]` are applied, as in
the existing web update).

## GET /api/result/exam-master/{id}

Returns the exam row plus `ddValue` (exam types), `all_term`, `all_standard`
dropdown data — identical to the web edit screen's data.
