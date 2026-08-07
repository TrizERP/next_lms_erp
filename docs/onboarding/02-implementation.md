# Module-wise onboarding — analysis & implementation

**Date:** 2026-08-03
**Backend:** `c:\xampp\htdocs\next_lms_erp` — Laravel 9 / PHP 8 — branch `sonika_palv`
**Frontend:** `c:\lms_k12` — Next.js 16 / React 19
**Database:** `vivek_erp` — **read/write access confirmed this pass**, so everything marked *unverified* in [01-project-map.md](01-project-map.md) is now resolved.

---

## 1. Corrections to the prior analysis

Live DB access resolved the open questions from `01-project-map.md §7`:

| Prior open question | Resolved answer |
|---|---|
| §7.2 — does a "Triz User" role exist? | **No.** `role_responsibility` holds 7 rows across 4 profiles: Admin, Teacher, Student, Clerk. `tbluserprofilemaster` has 30 distinct names, none internal/vendor. **However `sub_institute_id = 1` is "Triz International School"** — Triz is the vendor, and its own tenant is row 1. So the Triz/School split is *organisational*, not a seeded role. It had to be modelled as new data. |
| §7.3 — how many modules? | 40 distinct `menu_title` groups exist, but 99 rows have a NULL group and many groups are 1–3 report links. **20 groups are real, onboarding-able modules**; those are what shipped. |
| §7.4 — journey granularity | Per `(module, sub_institute_id, syear)` — but only for steps whose proof table actually has a year column. Verified per table; `proof_scope` is set accordingly. |
| §7.5 — DB access | Granted. Every proof table below was verified to exist **and** to carry a tenant column. |

Two prior findings **confirmed**:

- **`tblmenumaster.database_table` has no migration.** Grep of `database/` returns zero hits; the column exists live (`varchar(50)`, after `menu_type`). Fixed — see §5.
- **`role_responsibility` has no `sub_institute_id`** and is queried unfiltered. Not changed (out of scope, and it is read-only here), but the new journey does **not** depend on it for ownership — it carries its own per-step owners. `role_responsibility` is surfaced as supplementary "who is responsible" content only.

One correction to the brief's premise about the design system:

> **The `K-12 ERP Design System/` folder is not the live design system.** It has **zero imports** anywhere in `lms_k12` — verified across `app`, `components`, `lib`, `contexts`, `next.config.ts`, `package.json` and all CSS. It is a reference kit of `.jsx` files, never wired into the build.
>
> The **live** design system is: `components/ui/*` (shadcn, `base-nova` style, on `@base-ui/react`), `components/erp/erp-ui.tsx` (`ErpPageHeader`, `ErpAlert`, `ErpLoading`, `ErpEmpty`, `ErpSection`), `components/search-dropdown/`, `lucide-react` icons, and Tailwind v4 tokens in `app/globals.css`. **That is what this work reuses.** Building against the `.jsx` kit would have introduced a second, dead design language — the opposite of the constraint.

---

## 2. Architecture decision

The legacy Blade onboarding (`/Onboarding`, ~2,600 lines) has the right core idea — completion derived from real rows — wrapped in an unmaintainable shell: deep flows exist only for **hardcoded menu ids 6 (Fees) and 48 (Transportation)**, branched on inside a Blade view's jQuery, with route links built by string-munging against hardcoded JS lookup maps.

**This implementation keeps the idea and makes it data-driven.** One engine, one UI, 20 modules described as rows. Adding a module is a seeder entry, not a view edit.

```
onboarding_module  (journey definition: which modules)
   └── onboarding_step  (journey definition: the 8-step spine + how each step proves itself)
          └── onboarding_progress  (only what CANNOT be derived: sign-off, skip, notes)
```

**Completion is derived, never self-reported.** A step backed by a table is complete because the rows exist; the API *rejects* a manual `completed` on such a step (verified — returns 422). This is what stops the store degrading into the `erptour` anti-pattern (`01-project-map.md §1.3`), where a column is set to `1` because a POST happened and is never re-derived.

---

## 3. Existing vs. new backend components

### 3.1 Reused — no duplication

| Asset | Kind | How it is reused |
|---|---|---|
| `tblmenumaster` | table | Module grouping (`menu_title`), the real screen link (`link` → route name), and `database_table` as the step→screen mapping |
| `tblmenumaster.youtube_link` / `.pdf_link` | columns | Training material on the *Training & documentation* step — no new storage |
| `requirement_gathering` | table | Per-module "what we need from you" notes, with the existing `sub_institute_id = 0` global-default / tenant-override convention |
| `import_table_fields` (492 rows, 39 tables) | table | Bulk-upload column templates on the *Upload existing data* step |
| `role_responsibility` | table | Supplementary "who is responsible" content per module |
| `ApiSessionHydrator` (`api.session`) | middleware | Auth. Real JWT validation + session hydration |
| `App\Services\*` | convention | Service layer location |
| `GeneralSetupApiController` | convention | API controller shape, `status_code` envelope, validator usage |
| `mapApiLinkToRoute` (`app/data/routeMapper.ts`) | frontend | Laravel route name → Next.js path for every step's action button |
| `buildSessionContext` / `createAuthHeaders` (`lib/erp-client.ts`) | frontend | Session + auth headers |
| `components/ui/*`, `components/erp/erp-ui.tsx` | frontend | All UI primitives |

**No existing table could serve as the journey store.** Checked and rejected:
- `implementation_master` — despite the name, stores student headcount by standard (`total_boys`, `total_girls`, `std_wise_total`). No step/status/module concept.
- `erptour` — 9 hardcoded Fees-only boolean columns, scoped per *user*, set from middleware when a POST happens, never re-derived. Extending it means `ALTER TABLE` per step. Left untouched for the legacy tour.

### 3.2 New

| Component | Path | Why it was necessary |
|---|---|---|
| `onboarding_module` | migration `2026_08_03_100100` | No journey-definition store existed |
| `onboarding_step` | migration `2026_08_03_100200` | No per-step proof definition existed |
| `onboarding_progress` | migration `2026_08_03_100300` | No non-derivable state store existed (sign-off, skip, notes) |
| `tblmenumaster.database_table` | migration `2026_08_03_100000` | **Reconciliation, not a new column** — the column exists live but had no migration, so `migrate:fresh` produced a schema on which `/Onboarding` fatals. Guarded with `hasColumn`; `down()` is intentionally a no-op so rollback cannot destroy live data |
| `OnboardingModuleModel` / `OnboardingStepModel` / `OnboardingProgressModel` | `app/Models/onboarding/` | — |
| `OnboardingProgressService` | `app/Services/Onboarding/` | The derived-completion engine |
| `OnboardingApiController` | `app/Http/Controllers/api/` | 3 endpoints |
| `OnboardingJourneySeeder` | `database/seeders/` | The 20-module × 8-step template |
| `onboarding:install` | `app/Console/Commands/` | `db:seed` is hard-blocked by `App\Console\Kernel::bootstrap()` |

**No existing controller, model, service or route was modified.** The only edits to existing files are two additive ones: the route group appended to `routes/api.php`, and the `OnboardingJourneySeeder` call added to `DatabaseSeeder`.

---

## 4. Improvements over the legacy engine

The legacy derivation ([`tourController.php:179-206`](../../app/Http/Controllers/tourController.php#L179-L206)) asks one question: *does this menu's `database_table` have ≥ 1 row for this tenant?* Four material weaknesses, all addressed:

| Legacy weakness | Fix |
|---|---|
| **Not year-aware.** Fees setup is year-specific (`fees_map_years`) but the proof ignores `syear`, so last year's data marks this year done. | `proof_scope = tenant_year` adds the year predicate. Set only on tables verified to have a `syear`/`academic_year` column — a missing column degrades to tenant scope rather than making the step unachievable. **Observed live:** tenant 76 has 12 `fees_collect` rows, but 0 in `syear = 2026`, so *Upload existing Fees data* correctly reads `pending`. The legacy engine would have shown it done. |
| **One stray row marks a step done forever.** | `proof_min_rows` threshold (default 1, configurable per step). |
| **Silent false negatives.** A missing table, or one without `sub_institute_id`, is hardcoded to `false` — indistinguishable from "not started", and permanently uncompletable. | Surfaced explicitly as `misconfigured`, with the reason on the wire and an amber "Needs attention" state in the UI. |
| **Per-menu-row proof only** — two steps sharing a table can never differ, and a step with no table cannot exist at all. | Proof is per *step*. Steps with no table (`proof_type = manual`) take an attributed sign-off instead. |

Two further fixes:

- **Performance.** The legacy screen makes five synchronous controller round-trips before first paint and needs `set_time_limit(300)`. The new overview derives all 20 modules in **~1.3 s** (down from 2.6 s) by probing with `SELECT 1 … LIMIT n` instead of `COUNT(*)` — the question is "are there at least n rows?", which does not require a tally. The `at_least` flag on the wire tells the UI not to print a capped count as an exact figure.
- **Auth.** The legacy groups rely on `session` + `check_permissions`, both of which short-circuit on caller-supplied `type=API` ([`SessionMiddleware.php:20-28`](../../app/Http/Middleware/SessionMiddleware.php#L20-L28), [`checkPermission.php:25`](../../app/Http/Middleware/checkPermission.php#L25)). The onboarding group uses `api.session`, which validates the JWT for real and derives the tenant from the **token payload**, never from request input. **Verified:** passing `?sub_institute_id=1` alongside a tenant-232 token still returns tenant 232.

---

## 5. Database changes

### New tables

**`onboarding_module`** — journey definition. `sub_institute_id = 0` is the global template; a tenant row overrides it on `module_key` (same convention as `requirement_gathering`).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `module_key` | varchar(60) | stable slug, used in the API and frontend route |
| `module_name` | varchar(120) | |
| `menu_title` | varchar(120) | joins to `tblmenumaster.menu_title` |
| `description` | varchar(500) | |
| `icon` | varchar(60) | Lucide icon name |
| `sort_order`, `status` | int, tinyint | |
| `sub_institute_id` | bigint, default 0 | 0 = global template |

Unique `(module_key, sub_institute_id)`; index `(sub_institute_id, status)`.

**`onboarding_step`** — the spine plus the proof definition.

| Column | Type | Notes |
|---|---|---|
| `module_id` | bigint FK | |
| `step_key`, `title`, `description` | | unique `(module_id, step_key)` |
| `sort_order`, `is_required`, `status` | | |
| `owner` | enum `TRIZ`\|`SCHOOL` | which marker leads (renders above the chevron) |
| `triz_role`, `school_role` | varchar(80) | the two owner markers from the reference design |
| `proof_type` | enum `table_rows`\|`manual`\|`none` | |
| `proof_table` | varchar(100) | |
| `proof_scope` | enum `tenant`\|`tenant_year`\|`global` | |
| `proof_min_rows` | int, default 1 | |
| `proof_conditions` | json | extra equality/IN predicates |
| `action_route` | varchar(160) | `tblmenumaster.link` route name |
| `action_menu_id` | bigint | |
| `help_youtube_link`, `help_pdf_link` | varchar(255) | copied from `tblmenumaster` at seed time |

**`onboarding_progress`** — non-derivable state only.

| Column | Type | Notes |
|---|---|---|
| `sub_institute_id` | bigint | |
| `syear` | int, default 0 | 0 = the not-year-scoped bucket |
| `module_id`, `step_id` | bigint | |
| `status` | enum `pending`\|`in_progress`\|`completed`\|`skipped`\|`blocked` | |
| `notes` | text | |
| `assigned_to_id/_name`, `updated_by_id/_name` | | attribution |
| `completed_at` | timestamp | |

Unique `(sub_institute_id, syear, step_id)` — **school-level, not per-user**, so progress does not reset when a different staff member signs in (the `erptour` mistake).

### Altered table

`tblmenumaster` — `database_table varchar(50) NULL` added **in migration only** (already present on every live DB; guarded by `hasColumn`, `down()` is a deliberate no-op).

### Data written

`onboarding_module` (20 rows) and `onboarding_step` (160 rows), all at `sub_institute_id = 0`. **No tenant data was modified.** The single `onboarding_progress` row created during end-to-end testing was deleted afterwards; the table is empty.

---

## 6. Module → step → table mapping

All 20 modules share the same 8-step spine. Owners: T = Triz-led, S = School-led (both markers render on every step).

| # | Step | Owner | Proof |
|---|---|---|---|
| 1 | Training & documentation | T | manual |
| 2 | *{Module}* master setup | S | `master` table |
| 3 | *{Module}* configuration | T | `config` table |
| 4 | Integrations | T | manual |
| 5 | Validation & testing | T | manual |
| 6 | Upload existing *{module}* data | S | `data` table |
| 7 | Verify the *{module}* data | S | manual |
| 8 | Communication | S | manual |

Proof tables per module — **every one verified to exist and to carry a tenant column**; `†` marks year-scoped (`syear`/`academic_year` present):

| Module | `menu_title` | Master (step 2) | Configuration (step 3) | Data (step 6) |
|---|---|---|---|---|
| School & academic setup | School | `standard` | `std_div_map` | `class_teacher` † |
| Student | Student | `student_quota` | `house_master` † | `tblstudent` |
| Admission | Admission | `admission_category_master` | `admission_form` | `admission_enquiry` † |
| Fees | Fees | `fees_title` † | `fees_receipt_book_master` † | `fees_collect` † |
| LMS | LMS | `chapter_master` † | `content_master` † | `lms_lesson_plan` † |
| Result & exam | Result | `grade_master` | `result_create_exam` † | `result_marks` |
| HRMS & payroll | HRMS | `hrms_departments` | `hrms_leave_types` | `hrms_attendances` |
| Inventory | Inventory | `inventory_item_category_master` † | `inventory_master_setup` | `inventory_item_master` † |
| Transportation | Transportation | `transport_vehicle` | `transport_route` † | `transport_map_student` † |
| Hostel | Hostel | `hostel_master` | `hostel_room_master` | `hostel_room_allocation` † |
| Library | Library | `library_items` | `library_books` † | `library_book_circulations` † |
| Front desk | Front Desk | `front_desk` | `circular` † | `complaint` |
| Visitor management | Visitor | `visitor_type` | `visitor_master_settings` | `visitor_master` |
| Inward / outward | Inward-Outward | `place_master` | `physical_file_location` | `inward` † |
| Parent-teacher meeting | PTM | `ptm_time_slots_master` † | — (manual) | `ptm_booking_master` |
| Consent | Consent | `consent_master` † | — (manual) | — (manual) |
| Communication | Communication | `sms_api_details` | `whatapp_user_details` | — (manual) |
| Users & rights | User | `tbluserprofilemaster` | `tblgroupwise_rights` | `tbluser` |
| Petty cash | Petty Cash | `petty_cash_master` | — (manual) | `petty_cash` |
| Attendance | Attendance | `period` | `timetable` † | `student_capture_attendance` † |

A module with no table for a slot degrades that step to a manual sign-off rather than dropping it, so the spine stays uniform across modules.

**Deliberately excluded** and why: `payroll_type` and `question_master` do not exist on this database; `student_document_type`, `result_exam_master`, `result_exam_type_master`, `lms_online_exam`, `school_setup`, `master_fields` and `form_builder` exist but have **no tenant column**, so using them as tenant-scoped proof would produce a permanently `misconfigured` step. Alternatives were chosen in each case.

---

## 7. API contracts

Base: `{host}/api/onboarding` · Middleware: `api.session` · Auth: `Authorization: Bearer <user_token>` (**required**).

Tenant comes from the validated token payload. `syear` is the only context value the caller may steer (so the header's year switcher works); an invalid one falls back to the term covering today, per `ApiSessionHydrator`.

Envelope on success: `{ "status": 1, "status_code": 1, "message": string, "data": {...} }`
Envelope on failure: `{ "status": 0, "status_code": 0, "message": string, "errors": any, "data": [] }`

### `GET /api/onboarding/overview`

Query: `syear` (optional), `term_id` (optional).

```jsonc
{ "status": 1, "message": "Onboarding overview loaded.",
  "data": {
    "modules": [{
      "id": 4, "module_key": "fees", "module_name": "Fees",
      "menu_title": "Fees", "description": "...", "icon": "receipt-indian-rupee",
      "sort_order": 40, "is_tenant_override": false,
      "summary": { "total_steps": 8, "required_steps": 8, "completed_steps": 2,
                   "required_completed": 2, "percent_complete": 25,
                   "by_status": { "pending": 6, "in_progress": 0, "completed": 2,
                                  "skipped": 0, "blocked": 0, "misconfigured": 0 },
                   "is_complete": false },
      "next_step": { "id": 25, "step_key": "training",
                     "title": "Training & documentation", "owner": "TRIZ", "status": "pending" }
    }],
    "summary": { /* same shape, across all modules */ },
    "context": { "sub_institute_id": 232, "syear": 2023, "school_name": "GGS Vidhyapith" }
  } }
```

**Status codes:** `200` · `401` no/invalid token · `422` no academic term covers today.

### `GET /api/onboarding/modules/{moduleKey}`

```jsonc
{ "status": 1, "message": "Module journey loaded.",
  "data": {
    "module": { /* as above, without summary/next_step */ },
    "steps": [{
      "id": 26, "step_key": "master_setup", "title": "Fees master setup",
      "description": "...", "sort_order": 20, "is_required": true,
      "owner": "SCHOOL", "triz_role": "Implementation Consultant", "school_role": "Accountant",
      "status": "completed",        // pending|in_progress|completed|skipped|blocked|misconfigured
      "is_complete": true,
      "proof": { "type": "table_rows", "table": "fees_title", "scope": "tenant_year",
                 "min_rows": 1, "row_count": 1, "at_least": true,
                 "satisfied": true, "state": "satisfied", "reason": null },
      "action": { "route": "fees_title.index", "menu_id": 123,
                  "youtube_link": "...", "pdf_link": "..." },
      "state": { "manual_status": null, "notes": null, "assigned_to_id": null,
                 "assigned_to_name": null, "updated_by_name": null,
                 "completed_at": null, "updated_at": null }
    }],
    "summary": { /* … */ },
    "resources": {
      "menus": [{ "id", "name", "link", "menu_type", "icon",
                  "youtube_link", "pdf_link", "database_table" }],
      "requirements": "plain text (HTML stripped server-side)",
      "import_fields": { "fees_collect": [{ "field_name", "display_name", "is_required" }] },
      "responsibilities": [{ "profile_name": "Admin", "text": "..." }]
    },
    "context": { "sub_institute_id": 232, "syear": 2023 }
  } }
```

**Status codes:** `200` · `401` · `404` module not configured for this tenant.

`row_count` with `at_least: true` means *at least* that many rows exist — the probe is capped at `min_rows` and the true total is not measured. Do not render it as an exact figure.

### `POST /api/onboarding/steps/{stepId}`

Body (all optional, at least one meaningful):

| Field | Type | Rule |
|---|---|---|
| `status` | string | one of `pending`, `in_progress`, `completed`, `skipped`, `blocked` |
| `notes` | string | ≤ 5000 chars |
| `assigned_to_id` | int | |
| `assigned_to_name` | string | ≤ 160 chars |

Returns `{ "data": { "step": {...}, "summary": {...} } }` — the recomputed step and module summary.

**Status codes:** `200` · `401` · `404` step not found · `403` step not available to this tenant · `422` validation failure **or `status: "completed"` on a `proof_type = table_rows` step**:

> `"Fees master setup" is completed automatically once the underlying records exist. Add the records on the linked screen, or mark the step as skipped if it does not apply.`

`updated_by_id` / `updated_by_name` are taken from the session, never the request body.

---

## 8. Frontend

Route: **`/general/onboarding`** (index) and **`/general/onboarding/{moduleKey}`** (journey).

```
app/general/onboarding/
├── _lib/onboarding-api.ts              typed client + normalisers
├── _components/onboarding-ui.tsx       status/owner tokens, ProgressMeter, legend, panel
├── _components/JourneyRibbon.tsx       the serpentine ribbon
├── _components/StepDrawer.tsx          step detail + actions
├── page.tsx                            module index
└── [module]/page.tsx                   module journey
```

Follows the established page convention exactly (`"use client"`, `_lib` + `_components` co-location, `buildSessionContext()`, `loading` / `error` / `notice` state) — the same shape as `app/Utility/student-transfer/` and `app/fees/`.

### Design system usage

Every visual element comes from the live system — `Button`, `Input`, `Textarea`, `Label`, `Badge` from `components/ui/*`; `ErpPageHeader`, `ErpAlert`, `ErpLoading`, `ErpEmpty` from `components/erp/erp-ui.tsx`; `lucide-react` icons; the app's Tailwind vocabulary (`rounded-2xl`, `border-slate-200`, `bg-white`, `shadow-sm`). No new dependency, no new CSS file, no new token.

`buttonVariants` is applied to a real `<Link>` where a link must look like a button — this app's `Button` is `@base-ui/react` and does **not** support `asChild`.

### The serpentine ribbon

Steps flow left→right, turn, and continue right→left. Row direction comes from row parity, and the chevron's `clip-path` is **swapped** between right-pointing and left-pointing variants rather than mirrored with `scaleX` — mirroring would reverse the label text. Each chevron carries both owner markers, the leading one solid above the ribbon and the counterpart muted below, matching the reference.

**Responsive:** 3 columns ≥ 1280px, 2 ≥ 640px. Below `sm` the ribbon is unreadable, so it degrades to a vertical stepper — same data, same interactions, no `clip-path`. The journey panel scrolls horizontally on narrow tablets rather than compressing.

**Accessibility:** status is never colour alone — every state pairs a hue with a distinct Lucide icon and a text label, and the chevron carries an `sr-only` status suffix. The ribbon uses real `<button>`s with `aria-current="step"`; the drawer is a labelled `role="dialog"` closable with Escape; the progress meter is a proper `role="progressbar"` with `aria-valuenow`.

**States:** loading (`ErpLoading`), error (`ErpAlert tone="error"`), success (`ErpAlert tone="success"`), empty (`ErpEmpty`, distinguishing "no search match" from "no journeys configured"), and `misconfigured` — which the legacy screen could not express at all.

### Save & resume

Progress is server-side and school-level, so it resumes across sessions, devices and users by construction. Notes and status persist per step; the index surfaces each module's next outstanding step so a returning user lands where they left off.

---

## 9. Install

```bash
# 1. Schema (run per-file: this database's `migrations` table is out of sync with
#    database/migrations, so a bare `php artisan migrate` tries to re-run 2019 files)
php artisan migrate --force --path=database/migrations/2026_08_03_100000_add_database_table_to_tblmenumaster.php
php artisan migrate --force --path=database/migrations/2026_08_03_100100_create_onboarding_module_table.php
php artisan migrate --force --path=database/migrations/2026_08_03_100200_create_onboarding_step_table.php
php artisan migrate --force --path=database/migrations/2026_08_03_100300_create_onboarding_progress_table.php

# 2. Journey template (idempotent; never touches onboarding_progress)
php artisan onboarding:install
php artisan onboarding:install --report        # inspect without writing
```

### Making it reachable from the sidebar — **not done, needs your call**

The Next.js sidebar is built from `tblmenumaster`, which is **shared across every school on this database**. Adding the nav entry is therefore a live, multi-tenant data change, so it is opt-in and per-tenant rather than part of the install:

```bash
php artisan onboarding:install --register-menu=232        # one tenant
php artisan onboarding:install --register-menu=232,338    # several
```

Until then `/general/onboarding` is reachable by direct URL and fully functional.

---

## 10. Verification performed

| Check | Result |
|---|---|
| PHP lint — all new backend files | pass |
| `tsc --noEmit` — all new frontend files | pass, 0 errors |
| Migrations applied | 4/4 |
| Journey installed | 20 modules, 160 steps |
| Derivation vs. live tenant 76 | 16/160 steps satisfied, **0 misconfigured** |
| Derivation performance | 2649 ms → **1308 ms** after the probe change, identical results |
| `GET /overview` over HTTP | `200`, 20 modules, correct summary |
| `GET /modules/fees` over HTTP | `200`, 8 steps, 16 menus, import fields, 4 responsibilities, requirements |
| `POST /steps/25` (manual step) | `200` — completed, attributed to the session user, module 25% → 38% |
| `POST /steps/26` (derived step) | **`422` rejected**, as designed |
| No token | `401` |
| Tenant isolation — injected `?sub_institute_id=1` against a tenant-232 token | **ignored**, returned tenant 232 |
| Test data cleanup | the one `onboarding_progress` row deleted; table empty |

---

## 11. Assumptions & open items

1. **The Triz/School owner split is new data.** No such role exists in `tbluserprofilemaster` or `role_responsibility`. It is modelled as `onboarding_step.owner` + `triz_role` + `school_role`. The seeded role names (Implementation Consultant, QA Consultant, Accountant, Librarian, Store Keeper…) are plausible defaults — **confirm the real ones** and the seeder updates them in place.
2. **The ribbon is indigo/violet, not the screenshot's exact violet.** The live app's brand is `--primary-blue: #0D6EFD` with a slate/indigo palette; the legacy Blade onboarding hardcodes `#5C4AC7`. Introducing a third exact hue would have meant a new token. `violet-600` from the existing Tailwind ramp is the closest match that stays inside the current language. Flagged per the "don't introduce a new design language" constraint — say the word and it moves to the exact reference violet.
3. **The 8-step spine is uniform across all 20 modules**, taken from the reference (which shows Fees). Modules that genuinely need more or fewer steps are a seeder edit, not a code change.
4. **Only 20 of ~32 code-level modules got a journey.** The excluded ones (SQAA, Skill, Neo4j, PAL, H5P, Calendar, Counselling, Form Builder, Custom Module, Proxy, Donation, I-Card) either have no real `menu_title` group, no tenant-scoped master table, or are internal/derived surfaces rather than things a school sets up. Adding any of them is one seeder row.
5. **`role_responsibility` still has no tenant column** and is read unfiltered — every school sees the same responsibility text. Out of scope here (the journey does not depend on it for ownership), but it remains a real defect worth a follow-up.
6. **Per-user permission filtering is still not applied to onboarding.** `tourController@Onboarding` computes `$rightsMenusIds` and never uses it. The new API inherits the same posture — tenant isolation holds, per-user menu rights do not gate the journey. Deliberate for now: onboarding is an admin/implementation surface. If it must respect `tblindividual_rights` / `tblgroupwise_rights`, that is a defined follow-up.
7. **The legacy Blade onboarding is untouched.** `/Onboarding`, `/transport_Onboarding` and the `erptour` tour all still work. Nothing was removed.
