# Result Module REST API

REST APIs exposing the existing Laravel Result module for the Next.js frontend.
All endpoints are **thin wrappers** around the existing web controllers in
`app/Http/Controllers/result/` — every calculation (marks, grades, GPA,
percentage, attendance, ranking, promotion, remarks) is executed by the same
code the Blade screens use. **No existing file was modified**; the web
application continues to work unchanged.

## Architecture

| Piece | File |
|---|---|
| Routes (prefix `api/result`) | `routes/resultapi.php` (registered in `RouteServiceProvider::mapResultApiRoutes`) |
| Auth + legacy session bridge | `app/Http/Middleware/ApiSessionHydrator.php` (alias `api.session`) |
| Base controller (envelope, delegation, pagination) | `app/Http/Controllers/api/result/BaseResultApiController.php` |
| API controllers | `app/Http/Controllers/api/result/*ApiController.php` |

How it works per request:

1. `api.session` middleware validates the ERP's existing **JWT** (GenTux — the
   same token issued by `POST /api/api-login`).
2. It hydrates the legacy session keys (`sub_institute_id`, `syear`, `term_id`,
   `user_id`, `user_profile_id`, `user_profile_name`, `academicTerms`,
   `classTeacher*Arr`, …) from the token payload + DB, exactly the way
   `loginController` does at web login. The session store is never persisted —
   the API stays stateless.
3. It merges `type=API` into the request, so the existing controllers take the
   same JSON branches they already use for the mobile apps
   (`is_mobile()` helper, `SessionMiddleware`, `checkPermission`).
4. The API controller delegates to the existing web controller method and
   normalizes the result into the standard envelope.

## Authentication

```
POST /api/api-login
Content-Type: application/json

{ "email": "user@school.com", "password": "secret" }
```

The response contains `data.user_token`. Send it on every Result API call:

```
Authorization: Bearer <user_token>
```

Menu/permission rights for the logged-in user are available from the existing
endpoints `POST /api/menu-rights` and `GET /api/master-menu-rights`
(`data.erp_rights` in the login response lists allowed menu ids). Server-side
permission checks behave exactly as they do today for API-type requests.

### Academic year / term switching

By default the current academic term (today's date within
`academic_year.start_date..end_date`) is used, same as web login. To operate on
a different year/term, pass `syear` and/or `term_id` as query or body
parameters on any endpoint.

## Response envelope

Success (HTTP 200/201):

```json
{ "success": true, "message": "Success", "data": {}, "errors": null }
```

List endpoints add pagination metadata:

```json
{
  "success": true,
  "message": "Success",
  "data": [ ... ],
  "errors": null,
  "meta": { "pagination": { "current_page": 1, "per_page": 25, "total": 92, "last_page": 4 } }
}
```

Validation error (HTTP 422):

```json
{ "success": false, "message": "Validation Failed", "errors": { "field": ["msg"] } }
```

Exception (HTTP 500): `{ "success": false, "message": "Something went wrong." }`
Unauthorized (HTTP 401), Forbidden (403), Not found (404) use the same shape.

## List conventions (all list endpoints)

| Param | Meaning |
|---|---|
| `search` | free-text search across the module's display columns |
| `sort_by` / `sort_dir` | sort by any returned field, `asc`/`desc` |
| `page` / `per_page` | pagination (`per_page=0` returns everything) |
| module filters | e.g. `standard_id`, `term_id` — documented per module |

## Module documentation

See the per-module docs in this folder:

- [masters1.md](masters1.md) — Exam Type, Grade Master, Standard-Grade Mapping, Working Day, Result Remark
- [masters2.md](masters2.md) — Result Master, Exam Creation, Result Book, Student Attendance, Consolidate Report
- [exam-master.md](exam-master.md) — Exam Master
- [coscholastic_hpc.md](coscholastic_hpc.md) — Co-Scholastic masters, HPC Skillset, HPC Activity master
- [entry.md](entry.md) — Marks Entry, Co-Scholastic Marks Entry, HPC Activity Entry, HPC Entry v1
- [template_misc.md](template_misc.md) — Result Template, Student Result Remarks, Approve Mobile Result, Upload Result, All Results, Personalize/PAL lookups
- [reports_main.md](reports_main.md) — Result Report, New Report Card (Student Result), Classwise Grade Report
- [reports_cbse.md](reports_cbse.md) — CBSE 1-5 / 1-5 T2 / 11 T2, WRT, WRT Progress, Overall Mark reports
- [dropdowns.md](dropdowns.md) — dropdown / lookup APIs for cascading selects

A ready-to-import Postman collection is at
[postman/Result-API.postman_collection.json](postman/Result-API.postman_collection.json).
