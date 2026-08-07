# STEP 1 — Project Map

**Date:** 2026-08-03
**Backend:** `c:\xampp\htdocs\next_lms_erp` — Laravel 9 / PHP 8 — branch `sonika_palv`
**Frontend:** `c:\lms_k12` — Next.js 16 / React 19 — branch `sonika_frontend` — remote `github.com/Vivek99256/lms_k12`

---

## 0. Method & confidence

| Source | Status |
|---|---|
| Migrations, models, controllers, routes (backend) | **Verified** — read from disk |
| App routes, contexts, lib, design system (frontend) | **Verified** — read from disk |
| Live database (`vivek_erp` @ `202.47.117.220`) | **NOT accessed** — connection blocked by the sandbox. Anything that depends on row data is marked *unverified* |

Consequences of no DB access:
- The **runtime** module list lives in `tblmenumaster` (per-tenant rows). I could not read it. The module inventory below is derived from **code artefacts** (route files + controller dirs + model dirs + frontend route segments), which is the honest repo-truth answer but may differ from what any given tenant actually sees enabled.
- Row counts, seeded menu links, and which modules a given `sub_institute_id` has switched on are all **unverified**.

---

## 0a. CORRECTION to the first issue of this document (2026-08-03, second pass)

The first pass of this document claimed there was **no onboarding anywhere in either repo**. That was wrong, and the error was a name-scoped search: I searched `lms_k12` for `onboard*` and the backend `app/` + `routes/` for module directories, but did not sweep `resources/views/`.

**A complete, working onboarding system exists in the Laravel Blade backend.** It is not mock and not static. Full description in **§8**. The corrected headline:

| Layer | Onboarding status |
|---|---|
| Backend (Laravel + Blade) | **Fully functional.** Route `/Onboarding`, ~2,600 lines of Blade, real reads and real writes |
| Frontend (Next.js `lms_k12`) | **Does not exist.** Zero onboarding code. Confirmed again this pass |

The consequence for the brief is unchanged — `/general/onboarding` still has to be built from scratch in Next.js — but the framing changes completely: **this is a port-and-redesign of a working system, not a greenfield build.** And critically, the existing system already implements the "completion is derived from real tables, not a checkbox" rule (§8.3), so STEP 4's key rule has a working precedent in the repo.

Sections 1.1 and 1.3 below are corrected in place.

---

## 1. Conflicts with the brief — read this first

### 1.1 `/general/onboarding` does not exist **in the frontend** — but onboarding exists in the backend
There is no onboarding page in the frontend. Verified: no file, directory, or route matches `onboard*` anywhere in `lms_k12` (only two incidental string hits, in [Institute_profile/page.tsx](../../../lms_k12/app/Institute_Detail/Institute_profile/page.tsx) and a `sops.tsx`, unrelated to a journey UI).

The five pages that exist under `/general` are:

| Route | File |
|---|---|
| `/general/bulk_upload` | `app/general/bulk_upload/page.tsx` |
| `/general/form_builder` | `app/general/form_builder/page.tsx` |
| `/general/implementation_management` | `app/general/implementation_management/page.tsx` |
| `/general/template_management` | `app/general/template_management/page.tsx` |
| `/general/user_profile_masters` | `app/general/user_profile_masters/page.tsx` |

`/general/implementation_management` is a 3-line config-driven page (`<GeneralPage config={implementationConfig} />`) whose config is a **student headcount form** — `total_boys`, `total_girls`, `total_strenght`, `standard_totals` ([configs.ts:44-61](../../../lms_k12/app/general/configs.ts#L44-L61)). Not a journey.

**Corrected conclusion:** STEP 2 is not blocked. The audit target is the **live Blade onboarding at `/Onboarding`** (§8) — that is "the current onboarding", and it is what the Next.js page must replace.

### 1.2 `implementation_master` is not an onboarding table
Despite the name, `implementation_master` ([migration](../../database/migrations/2023_03_05_115658_create_implementation_master_table.php), [model](../../app/Models/implementation/implementation_MasterModel.php)) stores **student headcount by standard** — `total_male`, `total_female`, `total_boys`, `total_girls`, `standard_id`, `std_wise_total`, `final_std_total`. It has no step, status, module, or completion concept. It cannot be reused as the journey store.

### 1.3 `erptour` is **a** progress tracker — and it is the anti-pattern (but it is *not* what drives the onboarding screen)

**Corrected:** the first pass called `erptour` "the only existing progress tracker". It is not. The `/Onboarding` screen's completion ticks are **derived from real table rows** (§8.3); `erptour` drives only the separate legacy pop-up *tour*. Both exist, side by side, and they are unrelated mechanisms. Everything below about `erptour` still stands — it is still a self-reported flag store and should still not be extended.

`erptour` ([migration](../../database/migrations/2023_03_05_115658_create_erptour_table.php), [tourModel.php](../../app/Models/tourModel.php)) is the closest existing analogue to onboarding progress:

```
erptour: id, dashboard, school_sidebar, student_quota, fees_title,
         fees_structure, fees_receipt, fees_map, fees_collect,
         user_id, sub_institute_id
```

Three properties matter for STEP 4:
1. **Self-reported flags.** Columns are set to `1` from inside [checkPermission.php:104-118](../../app/Http/Middleware/checkPermission.php#L104-L118) when a matching route is POSTed. The flag records *"a POST happened"*, not *"the data exists"*. It is never re-derived and never reset if the row is later deleted.
2. **Hardcoded, Fees-only.** One column per step, all Fees; adding a module means an `ALTER TABLE`.
3. **Scoped per-user, not per-tenant.** Key is `(user_id, sub_institute_id)` — so progress resets per user rather than being a school-level fact.

This is precisely the "done because someone ticked a box" pattern the brief rules out. **Recommendation: do not extend `erptour`.** Design a new derived-completion store in STEP 3/4 and leave `erptour` alone for the legacy Blade tour.

### 1.4 The design-system brand is indigo, the reference design is violet
[tokens/colors.css](../../../lms_k12/K-12%20ERP%20Design%20System/tokens/colors.css) defines brand as indigo — `--color-brand-500: #6366f1`, `--color-brand-600: #4f46e5`. The serpentine ribbon in the reference screenshot is a more saturated violet. Whether indigo-600/700 is an acceptable token match, or whether the Figma introduces a violet ramp the token set does not have, is a **STEP 3 question** — flagged now because "tokens only, never hardcode" and "copy the design exactly" may conflict here. I have not opened the Figma yet.

### 1.5 The Figma file was not read
The brief supplies an embed URL (`figma.com/design/ouEtvbgxPFObOeWiePXerj/Onboarding---AI?node-id=2674-118`). I have not fetched it — STEP 1 is repo mapping, and exact colour/geometry extraction is STEP 3 work. Flagging that reading it may need a Figma token or an export, since the embed is a rendered iframe rather than an API surface.

---

## 2. Backend architecture

### 2.1 Stack
Laravel 9, PHP ^8.0. Notable deps: `laravel/sanctum`, `generationtux/jwt-artisan` (JWT for API), `laudis/neo4j-php-client` (graph sync), `maatwebsite/excel` (bulk import/export), `livewire/livewire`, `razorpay/razorpay`, `openai-php/client`.

Scale: **566 migrations**, **456 distinct tables created**, **307 models**, **526 controllers**, **30 route files** (3,551 route lines).

### 2.2 Routing
Route files are the primary module boundary. Sizes:

```
web.php 746   lms.php 369   resultapi.php 311   fees.php 297   api.php 282
student.php 276   result.php 233   hrms.php 138   pal_api.php 95  adminapi.php 95
teacherapi.php 91   admission.php 73   inventory.php 66   frontdesk.php 60
user.php 58   tranceport.php 46   settings.php 42   hostel_management.php 39
custom_module.php 39   cal.php 30   visitor_management.php 25   ptm.php 19
inward_outward.php 19   skill.php 18   implementation.php 16   consent.php 14
api_hostel.php 9   driver_management.php 8
```

`web.php` is the catch-all and still carries a large amount of un-modularised routing.

### 2.3 Tenancy — `sub_institute_id`, applied manually

**Model:** single shared database, discriminator column. **No** Laravel global scopes, **no** tenant middleware, **no** per-tenant connections.

- Tenant key: **`sub_institute_id`** = `school_setup.Id`.
- Hierarchy: `tblclient` (client / group) → `school_setup` (the school = the tenant) → all module data.
- Spread: **300 of 566 migrations** reference `sub_institute_id`. So ~53% of migrations carry the tenant column; the remainder are global/reference tables (`tblstate`, `tblcity`, `caste`, `religion`, ONet reference data) or **gaps**.
- Secondary discriminators seen alongside it: `client_id`, and `syear` / `academic_year` for the academic-year dimension.

**Enforcement is per-query and by hand.** Every controller adds `->where('sub_institute_id', $sub_institute_id)` itself. There is no central guarantee. This is the single most important fact for the STEP 4 rule "tenant-scoped everywhere" — the derived-completion queries must add the scope explicitly, because nothing will add it for them.

### 2.4 Auth — two independent paths

**Web (session):** [SessionMiddleware.php](../../app/Http/Middleware/SessionMiddleware.php). Reads `session('user_id')`; redirects to `home` if absent. Session also carries `sub_institute_id`, `user_profile_id`, `user_profile_name`, `client_id`, `syear`.

**API (JWT):** [ApiSessionHydrator.php](../../app/Http/Middleware/ApiSessionHydrator.php), alias `api.session`. Validates a JWT, then **hydrates an in-memory session** from the token payload (`id`, `sub_institute_id`, `user_profile_id`, `client_id`, `is_admin`, `is_student`) so legacy session-reading controllers work unchanged. Nothing is persisted. Resolves current term from `academic_year` by date when `syear`/`term_id` are absent. There is also `pal.auth` ([PalApiAuth.php](../../app/Http/Middleware/PalApiAuth.php)) for the PAL surface.

**⚠ Finding — the `type=API` bypass.** Both [SessionMiddleware.php:20-28](../../app/Http/Middleware/SessionMiddleware.php#L20-L28) and [checkPermission.php:25](../../app/Http/Middleware/checkPermission.php#L25) short-circuit when the request carries `type=API` or `type=JSON`:

```php
// SessionMiddleware
$type = $request->input("type");
if($type !== "API" && $type != "JSON"){
    $user_id = $request->session()->get('user_id');
    if(empty($user_id)){ return redirect(route('home')); }
}
return $next($request);   // <-- no auth check at all when type=API
```

`type` is caller-supplied input. On any route protected only by `session` + `check_permissions` (which is most of the `implementation`, `fees`, `student` groups), appending `?type=API` skips **both** the auth check and the permission check. `api.session` (real JWT validation) is a *separate* alias and is not applied to those groups.

I have **not** exploited this to confirm end-to-end, so treat the impact as *unverified* — but the control-flow reading is unambiguous. This belongs in the STEP 2 audit and constrains STEP 4: **the onboarding API must use `api.session` (JWT), not the `session` + `type=API` convention**, or it will inherit the bypass.

### 2.5 Permissions — menu-driven RBAC

[checkPermission.php](../../app/Http/Middleware/checkPermission.php) resolves rights per route:

1. Look up `tblmenumaster.id` where `link` = current **route name** and `status = 1`.
2. Look for a row in `tblindividual_rights` matching `(menu_id, profile_id, user_id, sub_institute_id)`.
3. Fall back to `tblgroupwise_rights` matching `(menu_id, profile_id, sub_institute_id)`.
4. Apply `can_view` / `can_add` / `can_edit` / `can_delete`, inferred from URL substrings (`delete`, `update`, `store|add|save`) and HTTP verb.
5. `user_profile_name === "Super Admin"` bypasses everything.

Roles live in **`tbluserprofilemaster`** (`id`, `parent_id`, `name`, `description`, `sort_order`, `status`, `sub_institute_id`, `client_id`) — so roles are themselves tenant-scoped and hierarchical.

Note the hardcoded menu-id escape hatches in the middleware (`!in_array($menu_id,[200])`, `[31,82,386]`) — magic numbers tying logic to seeded rows I cannot verify without DB access.

**Relevance to the brief's "Triz User vs School User" owner markers:** there is no existing `Triz User` / internal-staff role concept in `tbluserprofilemaster` that I can verify from code. Whether such a profile is seeded per tenant is **unverified**. This is a real design question for STEP 3 — the two owner markers on every chevron need a role source, and it may not exist yet.

### 2.6 Menu registry — `tblmenumaster`
```
id, name, menu_title, menu_sortorder, description, parent_menu_id, level,
status, sort_order, link, icon, sub_institute_id, client_id,
menu_type, site_map_name, youtube_link, pdf_link, menu_path,
quick_menu, dashboard_menu, created_at, updated_at
```
Hierarchical (`parent_menu_id` + `level`), tenant-scoped, `link` holds a **Laravel route name**. `youtube_link` and `pdf_link` already exist per menu item — directly reusable for the **Training & Documentation** step of the journey spine.

`wk_module` (`modulename`, `fieldname`, `displayname`, `tablename`, `tablealias`, `status`) is a separate, thinner module/table registry used by the dynamic report builder. Row contents **unverified**.

---

## 3. Frontend architecture

### 3.1 Stack
Next.js **16.2.6** (App Router), React **19.2.4**, TypeScript 5, Tailwind CSS **v4** (`@tailwindcss/postcss`), shadcn-style components on `@base-ui/react` + `@radix-ui/react-radio-group`, `lucide-react` icons, `react-hook-form` + `zod`, `recharts` + `chart.js` for charts, `date-fns`.

**240 routes** (`page.tsx` files) across 38 top-level sections.

### 3.2 State layer
There is **no** Redux / Zustand / TanStack Query. The state layer is:

- **[contexts/AuthContext.tsx](../../../lms_k12/contexts/AuthContext.tsx)** — the only React context. Holds `isAuthenticated`, `user`, `menuContext` (`sub_institute_id`, `user_id`, `user_profile_name`, `user_profile_id`, `client_id`), `academicTerms`, `academicYears`. Persists to **localStorage** under keys `auth`, `menuContext`, `userData`, `sessionDate`. 30-minute inactivity timeout.
- **[lib/erp-client.ts](../../../lms_k12/lib/erp-client.ts)** — `buildSessionContext()` assembles `{baseUrl, token, subInstituteId, syear, userId, termId}` from localStorage and passes it into fetches. Plus normalisers (`readString`, `readNumber`, `normalizeAcademicYear`, `normalizeNumericId`, `normalizeApiStatus`).
- **[lib/erp-legacy.ts](../../../lms_k12/lib/erp-legacy.ts)**, `lib/class-options.ts`, `lib/table-export.ts`, `lib/utils.ts`.
- Per-page `app/**/_lib/` and `app/**/_components/` folders (e.g. `app/admin-services/_lib`, `app/general/api.ts`).

Implication for STEP 4: server-side data fetching, cache invalidation and optimistic updates all have to be written by hand per page — there is no query cache to hang "recompute journey progress" off.

### 3.3 Design system
`c:\lms_k12\K-12 ERP Design System\` — a self-contained kit with `SKILL.md`, `_ds_manifest.json`, `_ds_bundle.js`, an oxlint adherence config, plus:

**Tokens** (`tokens/`): `base.css`, `colors.css`, `fonts.css`, `layout.css`, `motion.css`, `shape.css`, `spacing.css`, `typography.css`.
`colors.css` is structured as **primitives → semantic roles**, themed via `[data-theme]`. Header comment is explicit: *"UI consumes ONLY the semantic roles."* Primitive ramps present: brand (indigo), neutral (slate), success (emerald), warning (amber), and further ramps below the read window.

**Components** (`components/`), grouped: `auth`, `buttons`, `cards`, `charts`, `communication`, `data-display`, `feedback`, `inputs`, `navigation`, `overlays`, `selection`, `tables`, `uploads`, `utilities`, **`workflow`**.

**`components/workflow/` is directly relevant to STEP 3** — it already ships `ActivityFeed`, `ApprovalCard`, `AuditEntry`, `TimelineItem`, each with a `.jsx`, a `.d.ts` and a `.prompt.md`. `TimelineItem` is the closest existing primitive to a journey step. Whether the serpentine ribbon can be composed from these or needs a new component is a STEP 3 decision.

**Guidelines** (`guidelines/`): 24 `.card.html` reference cards covering colour roles (action, border, brand, content, error, feedback, info, neutral, success, surface, warning), elevation, motion, radius, spacing (scale/semantic/density) and type (scale, weights, headings, body, mono).

**App-level components** (`c:\lms_k12\components\`): `ui/` (badge, button, calendar, card, dropdown-menu, input, label, pagination, popover, radio-group, select, table, textarea), `erp/`, `search-dropdown/`.

---

## 4. Module inventory

Every module below is evidenced by at least two of: a dedicated route file, a controller directory, a model directory, a frontend route section. Nothing here is inferred from naming alone.

Legend — **FE**: frontend route section exists. `—` means no frontend section found (backend/Blade only).

| # | Module | Route file | Controllers | Models | FE section |
|---|---|---|---|---|---|
| 1 | School / Academic Setup | `web.php` | `school_setup/` | `school_setup/` (22) | `/academic_setup`, `/subjects`, `/chapters` |
| 2 | Student | `student.php` | `student/` | `student/` (24) | `/student`, `/students` |
| 3 | Admission | `admission.php` | `admission/` | `admission/` (4) | `/admissions`, `/admission-Enquiry` |
| 4 | Fees | `fees.php` | `fees/` | `fees/` (18) + `fees_new/` (2) | `/fees` |
| 5 | LMS | `lms.php` | `lms/` | `lms/` (55) | `/lms`, `/course-master`, `/quiz` |
| 6 | Result / Exam | `result.php`, `resultapi.php` | `result/`, `template_result/` | `result/` (21) | `/exam` |
| 7 | HRMS & Payroll | `hrms.php` | `HRMS/`, `Payroll/`, `leave/` | `HRMS/` (2) + root Hrms* (8) | — |
| 8 | Inventory | `inventory.php` | `inventory/` | `inventory/` (19) | `/Inventory` |
| 9 | Transportation | `tranceport.php`, `driver_management.php` | — | `transportation/` (11) | `/Transportation` |
| 10 | Hostel Management | `hostel_management.php`, `api_hostel.php` | `hostel_management/` | `hostel_management/` (9) | `/hostel` |
| 11 | Library | `web.php`, `skill.php` | `library/` | `library/` (2) + root Library* (3) | `/library` |
| 12 | Front Desk | `frontdesk.php` | `front_desk/`, `frontdesk/` | `front_desk/` (8) + `frontdesk/` (5) | `/admin-services` |
| 13 | Visitor Management | `visitor_management.php` | — | `visitor_management/` (2) | `/admin-services/add-visitor` |
| 14 | Inward / Outward | `inward_outward.php` | `inward_outward/` | `inward_outward/` (4) | — |
| 15 | PTM | `ptm.php` | `ptm/` | `ptm/` (2) | `/admin-services/ptm-*` |
| 16 | Consent | `consent.php` | `consent/` | `consent/` (1) | `/admin-services/consent-*` |
| 17 | Settings / Master Fields | `settings.php` | `settings/` | `settings/` (7) | `/general/*` |
| 18 | User & Rights | `user.php` | — | `user/` (5) | `/user`, `/user_log` |
| 19 | Custom Module | `custom_module.php` | `custom_module/` | `custom_module/` (1) | `/Utility/custom-module` |
| 20 | Skill | `skill.php` | `skill/` | `skill/` (3) | — |
| 21 | SQAA | `resultapi.php`, `web.php` | `sqaa/` | `sqaa/` (3) | — |
| 22 | PAL (adaptive learning) | `pal_api.php` | `agenticAI/`, `MIS/` | `PAL/` (4 files, 27 tables) | `/pal` |
| 23 | H5P interactive content | `lms.php`, `pal_api.php` | — | `h5p/` (2) + `lms/h5p/` (3) | `/h5p` |
| 24 | Easy Com (SMS/WhatsApp) | `web.php` | `easy_com/` | `easy_com/` (2) | — |
| 25 | Calendar | `cal.php` | `calendar/` | `calendar/` (1) | — |
| 26 | Implementation | `implementation.php` | `implementation/` | `implementation/` (1) | `/general/implementation_management` |
| 27 | Counselling / Career (ONet) | `lms.php` | `lms/` | `lms/counselling/` | — |
| 28 | Form Builder | `web.php` | `UserFormbuilderController.php` | `FormTable`, `FormSubmitData` | `/general/form_builder` |
| 29 | Neo4j / Graph sync | `web.php`, `api.php` | `neo4J/`, `neo4jGraph/`, `GraphController*.php` | `LmsDataContentNeo4j` | — |
| 30 | Attendance | `student.php`, `web.php` | — | `studentCaptureAttendanceModel`, `attendanceJsonResultModel` | `/attendance`, `/student/*attendance*` |
| 31 | Petty Cash | `frontdesk.php` | `frontdesk/` | `PettyCashMasterModel`, `PettyCashModel` | `/admin-services/petty-cash*` |
| 32 | Proxy / Substitution | `web.php` | `school_setup/` | `proxyModel` | `/proxy_master`, `/todays_proxy_report` |

**Frontend-only sections with no dedicated backend module dir:** `/Institute_Detail`, `/Utility` (rollover, student-transfer, breakoff-rollover), `/classteacher`, `/teachertransfer`, `/teacher_daily_report`, `/dashboard`, `/student_homework`.

**Backend modules with no frontend yet:** HRMS & Payroll, Inward/Outward, Skill, SQAA, Easy Com, Calendar, Counselling, Neo4j. These matter for STEP 3 — the "one serpentine per module" project-map screen has to decide whether to show modules that have no UI.

---

## 5. Tables owned per module

Ownership taken from `protected $table` declarations in the module's model directory, cross-checked against `Schema::create` in migrations. Dependencies are tables the module reads but does not own.

### Core / shared (owned by no single module)
`school_setup`, `tblclient`, `tblapplications`, `tbluser`, `tbluserprofilemaster`, `tbluser_contact_details`, `tbluser_past_education`, `tblmenumaster`, `tblmenumaster_new`, `tblmenumaster_old`, `rightside_menumaster`, `tblindividual_rights`, `tblgroupwise_rights`, `new_client_rights`, `academic_year`, `academic_section`, `standard`, `division`, `std_div_map`, `subject`, `sub_std_map`, `batch`, `period`, `timetable`, `class_teacher`, `caste`, `religion`, `blood_group`, `tblstate`, `tblcity`, `master_fields`, `master_fields_institute`, `master_fields_table`, `tblcustom_fields`, `tblfields_data`, `general_data`, `wk_module`, `erptour`, `requirement_gathering`, `access_log_route`, `err_log`, `user_activities`.

Almost every module depends on: **`school_setup`** (tenant), **`academic_year`** (`syear`), **`standard`** / **`division`** / **`std_div_map`** (class structure), **`tblstudent`** (learner), **`tbluser`** (staff).

### Per module

| Module | Owns | Depends on |
|---|---|---|
| **School / Academic Setup** | `standard`, `division`, `std_div_map`, `division_capacity_master`, `subject`, `sub_std_map`, `student_optional_subject`, `batch`, `period`, `timetable`, `class_teacher`, `academic_year`, `academic_section`, `chapter_master`, `topic_master`, `lessonplan`, `lessonplan_execution`, `proxy_master`, `caste`, `religion`, `blood_group`, `school_setup` | `tblclient`, `tbluser` |
| **Student** | `tblstudent`, `tblstudent_enrollment`, `tblstudent_document`, `tblstudent_doc_std_mapping`, `tblstudent_family_history`, `tblstudent_siblings`, `tblstudent_past_education`, `tblstudent_parent_feedback`, `tblstudent_tc_details`, `tblstudent_bank_detail`, `tblstudent_bank_detail_log`, `tblstudent_payment_method_mapping`, `student_health`, `student_height_weight`, `student_infirmary`, `student_vaccination`, `student_anacdotal`, `student_quota`, `student_document_type`, `student_change_request`, `student_change_req_type`, `student_capture_attendance`, `student_capture_photos`, `house_master`, `app_notification` | `standard`, `division`, `std_div_map`, `academic_year`, `caste`, `religion`, `blood_group`, `tblcity`, `tblstate`, `school_setup` |
| **Admission** | `admission_enquiry`, `admission_form`, `admission_registration`, `admission_registration_v1`, `admission_age_validation`, `admission_category_master`, `follow_up` | `standard`, `academic_year`, `tblstudent`, `school_setup` |
| **Fees** | `fees_title`, `fees_title_master`, `fees_head_master`, `fees_breackoff`, `fees_breackoff_logs`, `fees_breakoff_other`, `fees_collect`, `fees_receipt`, `fees_receipt_book_master`, `fees_receipt_css`, `fees_config_master`, `fees_map_years`, `fees_late_master`, `fees_month_header`, `fees_cancel`, `fees_cancel_type`, `fees_refund`, `fees_other_head`, `fees_paid_other`, `fees_other_collection`, `fees_other_cancel`, `fees_circular_master`, `fees_circular_log`, `fees_payment`, `fees_reconciliation`, `fees_online_maping`, `fees_online_split`, `fees_aggre_pay`, `fees_razorpay`, `fees_icici`, `fees_hdffc`, `fees_axis`, `fees_payphi`, `imprest_fees_cancel`, `tblstudent_fees_failure`, `bank_master`, `NACH_ac_type`, `donation_collection` | `tblstudent`, `standard`, `division`, `academic_year`, `student_quota`, `school_setup` |
| **LMS** | 46 `lms_*` tables (assignment, lesson_plan, lessonplan_dayswise, online_exam + answers + student, offline_exam + answer, question_master, question_mapping, doubt, doubt_conversation, flashcard, portfolio, teacher_resource, virtual_classroom, curriculum, syllabus, units, knowledge_graph, concept_mastery, misconceptions, forgetting_curve, student_profile, student_engagement, teacher_interventions, class_insights, …), plus `content_master`, `contents`, `content_mapping_type`, `question_master`, `question_paper`, `question_type_master`, `question_level_master`, `question_category_master`, `answer_master`, `lo_master`, `lo_category`, `lo_indicator`, `master_skills`, `gamma_ppt`, `lb_master`, `lb_points`, `homework` | `standard`, `division`, `subject`, `chapter_master`, `topic_master`, `tblstudent`, `tbluser`, `academic_year`, `school_setup` |
| **Result / Exam** | 25 `result_*` tables (exam_master, exam_type_master, create_exam, exam_approve, marks, co_scholastic + grades + parent + marks_entries + range, std_grd_maping, student_attendance_master, working_day_master, book_master, trust_master, template_master, remark_masters, remarks, activity_master/group/marks, sub_activity, skillset, html, master_confrigration), plus `grade_master`, `grade_master_data`, `upload_result` | `tblstudent`, `standard`, `division`, `subject`, `academic_year`, `school_setup` |
| **HRMS & Payroll** | `hrms_attendances`, `hrms_departments`, `hrms_departments_mapping`, `hrms_emp_leaves`, `hrms_emp_payroll_deduction`, `hrms_holidays`, `hrms_in_out_times`, `hrms_job_titles`, `hrms_leave_allocation`, `hrms_leave_types`, `hrms_salary_certificate`, `hrms_weekdays`, `tbluser_shift_master`, `tbluser_shift_records`, `employee_salary_structures`, `employee_monthly_salary_data`, `payroll_type` | `tbluser`, `tbluserprofilemaster`, `school_setup` |
| **Inventory** | 18 `inventory_*` tables (item_master, item_category_master, item_sub_category_master, item_type, master_setup, vendor_master, tax_master, requisition_details, requisition_status_master, generate_po_details, negotiate_po_details, item_quotation_details, item_receivable_details, item_return_details, item_defective_details, item_lost_details, item_direct_purchase, allocation_details) | `tbluser`, `school_setup` |
| **Transportation** | `transport_route`, `transport_stop`, `transport_route_stop`, `transport_route_bus`, `transport_vehicle`, `transport_vehicle_type`, `transport_driver_detail`, `transport_kilometer_rate`, `transport_map_student`, `transport_school_shift` | `tblstudent`, `school_setup` |
| **Hostel** | `hostel_master`, `hostel_type_master`, `hostel_building_master`, `hostel_floor_master`, `hostel_room_master`, `hostel_room_allocation`, `hostel_visitor_master`, `room_type_master`, `admission_category_master` | `tblstudent`, `school_setup` |
| **Library** | `library_books`, `library_items`, `library_book_circulations`, `item_scan_details`, `mst_item_status` | `tblstudent`, `tbluser`, `school_setup` |
| **Front Desk** | `front_desk`, `task`, `complaint`, `circular`, `classwork_attachment`, `create_timetable`, `leave_applications`, `parent_communication`, `dicipline`, `dicipline_dd`, `dicipline_master`, `petty_cash`, `petty_cash_master` | `tblstudent`, `tbluser`, `standard`, `division`, `school_setup` |
| **Visitor Management** | `visitor_master`, `visitor_master_settings`, `visitor_type` | `school_setup` |
| **Inward / Outward** | `inward`, `outward`, `physical_file_location`, `place_master` | `school_setup` |
| **PTM** | `ptm_booking_master`, `ptm_time_slots_master` | `tblstudent`, `tbluser`, `school_setup` |
| **Consent** | `consent_master` | `tblstudent`, `school_setup` |
| **Settings** | `master_fields`, `master_fields_institute`, `master_fields_table`, `tblcustom_fields`, `tblfields_data`, `template_master`, `institute_detail` | `school_setup`, `tblmenumaster` |
| **User & Rights** | `tbluser`, `tbluserprofilemaster`, `tblindividual_rights`, `tblgroupwise_rights`, `tbluser_past_education`, `tbluser_contact_details`, `user_experience_details`, `user_activities` | `tblmenumaster`, `school_setup` |
| **Custom Module** | `custom_module_tables`, `custom_module_table_columns`, `donation_collection` | `school_setup` |
| **Skill** | `s_assessment_library`, `s_skill_matrix`, `s_jobrole`, `s_jobrole_skills`, `s_jobrole_task`, `master_skills` | `tbluser`, `school_setup` |
| **SQAA** | `sqaa_master`, `sqaa_documents`, `sqaa_documant_master`, `sqaa_marks` | `school_setup` |
| **PAL** | 27 `pal_*` tables (learner_states, learning_sessions, session_events, assessment_results, misconceptions, learner_misconceptions, remediations, remediation_sessions, contents, content_recommendations, competencies, concepts, subjects, telemetry_events, reflections, pedagogy_effectiveness, learner_preferences, collaboration_activities, classroom_activities, discussions, group_activities, self_corrections, learning_plans, strategy_selections, learning_journals, learning_events, learning_patterns) | `tblstudent`, `subject`, `lms_*`, `school_setup` |
| **H5P** | `h5p_scenarios`, `h5p_scenario_points`, `h5p_flashcard`, `h5p_interactive_video`, `h5p_video_interactions` | `lms_*`, `school_setup` |
| **Easy Com** | `sms_api_details`, `sms_sent_parents`, `whatapp_user_details`, `whatsapp_sent_messages`, `incoming_messages` | `tblstudent`, `tbluser`, `school_setup` |
| **Calendar** | `calendar_events` | `academic_year`, `school_setup` |
| **Implementation** | `implementation_master` (headcount only — see §1.2) | `standard`, `academic_year`, `school_setup` |
| **Counselling / ONet** | `counselling_course`, `counselling_question_master`, `counselling_answer_master`, `counselling_question_mapping`, `counselling_online_exam`, `counselling_online_exam_answer`, `onet_*` (employer, expert_advice, explore_sector, institute_courses, institute_data, career_cluster, content_model_reference, occupation_data) + the `ONet*` root models | `tblstudent`, `school_setup` |
| **Form Builder** | `form_builder`, `form_submit_data` | `school_setup`, `tblmenumaster` |
| **Neo4j / Graph** | `lms_data_content_neo4j`, `lms_data` | `lms_*`, `content_master` |
| **Reports (dynamic)** | `report_dynamic`, `report_module`, `report_module_data`, `report_module_fields` | `wk_module`, all module tables |

---

## 6. Assets already in the repo that STEP 3/4 can build on

| Asset | Where | Use |
|---|---|---|
| `tblmenumaster.youtube_link`, `.pdf_link` | backend | **Training & Documentation** step content, per module, per tenant |
| `tblmenumaster` hierarchy + `link` (route name) | backend | Mapping a journey step to the actual screen the user must visit |
| `requirement_gathering` (`requirements`, `menu_id`, `menu_name`, `created_by_*`, `sub_institute_id`) | backend | Existing per-menu, per-tenant notes surface — adjacent to a journey "notes" field |
| `maatwebsite/excel` + `/general/bulk_upload` + `Import/` controllers | both | **Upload Existing Data** step — bulk import already exists |
| `components/workflow/TimelineItem` | design system | Closest primitive to a journey step plate |
| `guidelines/*.card.html` (24 cards) | design system | Authoritative token semantics for STEP 3 mapping |
| `academic_year` + `syear` | backend | The journey almost certainly needs an academic-year dimension — flagged for STEP 3 |

---

## 7. Open questions for you

Nothing below blocks me from starting STEP 2 in some form, but the first one changes what STEP 2 *is*.

1. **`/general/onboarding` doesn't exist.** Options: (a) audit `/general/implementation_management` instead as the nearest existing screen; (b) audit the legacy Blade implementation/tour flow in the backend; (c) treat STEP 2 as a greenfield readiness audit — audit the *infrastructure* the new page will sit on (design-system adherence in comparable `/general` pages, the `erptour` progress model, tenancy/role handling) rather than an existing page. **I'd recommend (c) with (a) folded in** — it produces the gap list STEP 3 actually needs, and (a) alone is a 3-line page that will yield very little.

2. **"Triz User" role.** I can't verify that an internal/implementation-partner profile exists in `tbluserprofilemaster`. Is it a real seeded role, or a new concept the journey introduces? Affects the two-owner-marker design directly.

3. **Module scope for the project-map screen.** 32 modules exist; 8 have no frontend at all. Do all 32 get a serpentine, or only the ones with a live UI?

4. **Journey granularity.** Is a journey per `(module, sub_institute_id)`, or per `(module, sub_institute_id, syear)`? Fees setup is year-specific (`fees_map_years`), so this matters.

5. **DB access.** If you can allow a read-only MySQL connection, I can replace several *unverified* markers with facts — especially the real per-tenant module list from `tblmenumaster` and whether a Triz-User profile is seeded.

---

## 8. How the current onboarding actually works

Everything in this section is **verified by reading source**. Row-level data (which menus a tenant has, what `database_table` holds) is still *unverified* — no DB access.

### 8.1 Entry points

| Route | Name | Controller | View | Registered |
|---|---|---|---|---|
| `GET /Onboarding` | `Onboarding` | [tourController@Onboarding](../../app/Http/Controllers/tourController.php#L133) | `setup_institute_details.blade.php` (471 ln) | [web.php:342](../../routes/web.php#L342) |
| `GET setup-institute-details` | `setup-institute-details` | [dashboardController@setup_details:2653](../../app/Http/Controllers/dashboardController.php#L2653) | same view | [web.php:269](../../routes/web.php#L269) **and** [web.php:574](../../routes/web.php#L574) |
| `GET transport_Onboarding` | `transportOnboarding` | [tourController@transportOnboarding](../../app/Http/Controllers/tourController.php#L286) | `onboard_module/transportOnboarding.blade.php` (490 ln) | [web.php:602](../../routes/web.php#L602) |
| `GET /implementation`, `/implementation_1`, `/implementation_2`, `/skip_implementation` | — | `tourController` | `implementation*.blade.php` | [web.php:341-345](../../routes/web.php#L341-L345) |
| `ANY /tourUpdate` | `tourUpdate` | `tourController@index` | — (writes `erptour`) | [web.php:339](../../routes/web.php#L339) |

Supporting views: `onboard_module/feesOnboardingModal.blade.php` (**970 ln**), `onboard_module/onboarding_model.blade.php` (154 ln). Total onboarding Blade ≈ **2,600 lines**.

Note `setup_details` in `dashboardController` is a **near-verbatim copy** of `tourController@Onboarding` — the derivation logic exists twice, and its route is registered twice.

### 8.2 The screen — a 3-step accordion per module

`setup_institute_details.blade.php` renders, for every menu group the tenant has:

```
[ Module card ]  ← one per tblmenumaster.menu_title (level 1)
   └ expands to:
      1  <Module> Master   → menu rows where menu_type = 'MASTER'
      2  <Module> Entry    → menu rows where menu_type = 'ENTRY'
      3  <Module> Report   → menu rows where menu_type = 'REPORT'
```

Each leaf row is a link to the real screen (`route($master['link'])`, opened in a new tab) plus a status glyph — `square-check.svg` or `close-square-icon.svg` — and an `<i>` info tooltip fed by `tblmenumaster.text`. Empty groups render *"There is no required for Master Setup"*.

So the **current spine is 3 steps (Master → Entry → Report)**, driven by `menu_type`. The brief's 8-step spine (Training & Documentation → Master Setup → Configuration → Integrations → Validation & Testing → Upload Existing Data → Verify Data → Communication) does not exist. Mapping one onto the other is a STEP 3 decision.

### 8.3 ⭐ Completion is DERIVED — the rule STEP 4 demands is already implemented

[tourController.php:179-206](../../app/Http/Controllers/tourController.php#L179-L206):

```php
$databaseTables = tblmenumasterModel::select('database_table')
    ->whereRaw("find_in_set('$sub_institute_id', sub_institute_id)")
    ->where('status', 1)->pluck('database_table')->toArray();

foreach ($databaseTables as $tableName) {
    if (Schema::hasTable($tableName)) {
        if (Schema::hasColumn($tableName, 'sub_institute_id')) {
            $exists = DB::table($tableName)
                ->where('sub_institute_id', $sub_institute_id)   // tenant-scoped
                ->exists();
        } else { $exists = false; }
    } else { $exists = false; }
    $subInstituteExists[$tableName] = $exists;
}
```

The view then ticks a step by looking up `$data['table_name'][$row['database_table']]`.

**This is a genuine derived-completion engine**, and it is tenant-scoped. It is the strongest existing asset for STEP 4. The mapping mechanism is a column on the menu table: **`tblmenumaster.database_table`** names the table that proves the step was done.

**Three material weaknesses:**

1. **⚠ `tblmenumaster.database_table` has no migration.** Grepped all of `database/` — zero hits; the only references are in `tourController`, `dashboardController` and the Blade views. The column was added straight to the database. So `database/migrations` is **not** a faithful description of `tblmenumaster`, and a fresh `migrate:fresh` produces a schema on which `/Onboarding` fatals. Treat the migration set as incomplete for any table the onboarding touches.
2. **The proof is "≥ 1 row exists for this tenant" — nothing more.** Not year-aware (no `syear`/`academic_year` filter, though Fees setup is year-specific), no per-step granularity (one table per menu row, so two steps sharing a table can never differ), no dependency checking, no notion of *correct* or *complete* data. One stray row marks a step done forever.
3. **Silent false negatives.** A table that lacks a `sub_institute_id` column is hardcoded to `false` — it can never be completed. Same for a missing/misspelled table name. Nothing surfaces the difference between "not done" and "misconfigured".

### 8.4 Module coverage — 2 modules are deep, the rest are generic

| Module | Treatment | Trigger |
|---|---|---|
| **Fees** | Full multi-step wizard, 970 ln | `menu_id == 6` → `#exampleModal_fees` |
| **Transportation** | Full wizard, 490 ln + own page | `menu_id == 48` → `#transportModel` |
| **Everything else** | Generic 3-step accordion + `onboarding_model.blade.php` | `else` branch |

Both branch points are **hardcoded numeric menu IDs** in the view's jQuery ([setup_institute_details.blade.php:299-302](../../resources/views/setup_institute_details.blade.php#L299-L302)). Adding a third deep module means editing Blade + JS.

The generic path builds links by **string-munging route names in JavaScript**, against hardcoded lookup maps:

```js
var hrmslink = {"Payroll Type": "payroll-type/create", "Leave Type Master": "leave-type", ...};
var prefix1  = {"Transportation": "transportation/", "Hostel": "hostel_management/",
                "Inventory": "inventory/", "LMS": "lms/", "Communication": "easy_com/", ...};
var no_create = ["Map Optional Subjects", "Student Request Type", "Transfer Student", ...];
// then: linkValue1.replace('.index', '/create')
```

Plus a hardcoded `$extra_menu` in PHP injecting **Library** and **LMS Preloaded** cards that are not in `tblmenumaster` at all. This is the most brittle part of the whole feature and should not be ported.

### 8.5 The Fees wizard is fully functional — real reads, real writes

**Reads** — `tourController@Onboarding` instantiates five real controllers server-side and injects their output ([tourController.php:246-268](../../app/Http/Controllers/tourController.php#L246-L268)):

```php
$request->merge(['type'=>"API", 'sub_institute_id'=>$sub_institute_id, 'syear'=>$syear, ...]);
$res['map_year']         = json_decode((new map_year_controller)->create($request));
$res['feesTitle']        = json_decode((new fees_title_controller)->create($request));
$res['feesMonthHeader']  = json_decode((new feesMonthHeadercontroller)->index($request));
$res['feesReceiptBook']  = json_decode((new feesReceiptBookMasterController)->create($request));
$res['feesBreakoff']     = json_decode((new fees_breackoff_controller)->create($request));   // set_time_limit(300)
```

**Writes** — the modal AJAXes to the same production store routes the normal Fees screens use: `map_year.store`, `fees_title.store`, `fees_config_master.store`, `fees_month_header.store`, `fees_receipt_book_master.store`, `fees_breackoff.store`, `requirements.store`, and `import_parse` for CSV upload.

So a school genuinely configures Fees from inside onboarding. **Nothing here is mock.** Transport is the same pattern against `add_driver.store`, `add_vehicle.store`, `add_route.store`, `add_stop.store`.

Two notes: `set_time_limit(300)` before the breakoff call is an admission that this page is slow — it is five synchronous controller round-trips before first paint. And `$request->merge(['type' => "API"])` is the **§2.4 bypass convention** used deliberately for internal dispatch.

### 8.6 Owner / role markers — the source already exists, but it is global

`role_responsibility` ([migration](../../database/migrations/2023_12_13_151321_create_role_responsibility_table.php)):

```
id, module_name, profile_name, text, created_at, updated_at
```

Loaded in [tourController.php:238-244](../../app/Http/Controllers/tourController.php#L238-L244) as `$rolesRes[module_name][profile_name]`, and rendered in the Fees modal ([feesOnboardingModal.blade.php:615-624](../../resources/views/onboard_module/feesOnboardingModal.blade.php#L615-L624)) as a per-module *"who is responsible for what"* table.

**This is the existing analogue of the brief's two owner markers (Triz User / School User)** — it already stores responsibility per `(module, profile)`. It answers open question §7.2 partially.

**⚠ But `role_responsibility` has no `sub_institute_id`**, and it is queried with `DB::table('role_responsibility')->get()` — **no tenant filter at all**. Every school sees the same responsibility matrix. If the new journey uses it for owner markers, it needs a tenant column, or it breaks the "tenant-scoped everywhere" rule. Whether the seeded `profile_name` values include a Triz/internal role is still **unverified** (needs DB).

### 8.7 Other real data the screen already assembles

| Feature | Source | Notes |
|---|---|---|
| Per-module requirement notes | `requirement_gathering` grouped by `menu_name`, `whereRaw('sub_institute_Id in (0,'.$sub_institute_id.')')` | `0` = global default, tenant row overrides. Editable via `requirements.store` |
| Bulk-import field registry | `import_table_fields` grouped by `table_name` | Feeds the CSV import step (`import_parse`) — the **Upload Existing Data** step already has a backing registry |
| Menu rights filtering | `tblindividual_rights` / `tblgroupwise_rights` joined to `tblmenumaster` | `$rightsMenusIds` is computed... |
| School + user detail | `school_setup`, `tbluser` (`status = 1`) | header/context |

**⚠ Bug — the rights filter is computed but never applied.** In `tourController@Onboarding`, `$rightsMenusIds` is built with a full rights join (lines 138-172) and then **never used**. The three menu queries that follow filter only on `sub_institute_id`, `status` and `menu_type`. Compare [MasterSetupMenuMiddleware.php:63-66](../../app/Http/Middleware/MasterSetupMenuMiddleware.php#L63-L66), which *does* apply it (`and id in (".$rightsMenusIds.")`). Net effect: **the onboarding screen shows every module for the tenant regardless of the user's permissions.** Tenant isolation holds; per-user permission filtering does not.

### 8.8 Front-end / design state of the current screen

Standalone Blade page, not part of the Next.js app. Bootstrap 5 + jQuery from CDN (`jsdelivr`, `code.jquery.com`, `ajax.googleapis.com`). **jQuery is loaded four times** across the page and its includes, including `jquery.slim.min.js` immediately followed by full jQuery.

Styling is inline `<style>` + inline `style=` attributes with `!important`, hardcoded hex throughout — the brand purple is **`#5C4AC7`**, repeated literally in the view and both modals. Icons are static SVG assets (`square-check.svg`, `close-square-icon.svg`) plus Font Awesome.

**`#5C4AC7` is the closest thing to a verified brand purple in the repo** and is much nearer the reference screenshot's ribbon than the design system's indigo `--color-brand-600: #4f46e5`. That sharpens §1.4: the design system's brand ramp does not currently contain the product's actual purple. STEP 3 has to resolve this — most likely by checking whether `colors.css` has a violet/secondary ramp I have not yet read, or raising a token gap.

No responsive handling beyond Bootstrap's grid; layout uses viewport units for padding (`padding: 2vh 5vw`). No loading, empty or error states — a failed controller call renders a blank or broken accordion.

### 8.9 Verdict

**Functional, not mock — with real caveats.**

| Dimension | Verdict |
|---|---|
| Backend data flow | **Real.** Live queries, five real controllers, real store routes |
| Completion status | **Real and derived** — but a crude "≥1 row exists" proxy |
| Writes | **Real.** Fees and Transport genuinely configure the school |
| Module coverage | **Uneven.** 2 of ~32 modules deep; rest generic 3-step |
| Step spine | 3 steps (Master/Entry/Report), not the brief's 8 |
| Tenant safety | Menu + completion scoped; **`role_responsibility` is not** |
| Permission safety | **Broken** — rights computed, never applied (§8.7) |
| Schema integrity | **`database_table` missing from migrations** (§8.3) |
| Frontend | Legacy Blade/Bootstrap/jQuery, hardcoded hex, no design system |
| Next.js app | **Nothing exists** |

### 8.10 What this changes for STEP 3 / STEP 4

**Keep and build on:**
- The derived-completion engine (§8.3) — the pattern is right, the predicate needs to be stronger than `exists()`
- `tblmenumaster.database_table` as the step→proof mapping — but give it a migration
- `role_responsibility` as the owner-marker source — but add `sub_institute_id`
- `requirement_gathering` (per-module notes, with the `0` = global-default fallback) and `import_table_fields` (bulk import)
- `tblmenumaster.youtube_link` / `.pdf_link` for Training & Documentation

**Replace:**
- Hardcoded menu IDs `6` / `48`, the JS prefix/link maps, `$extra_menu` — make the journey definition data-driven (this is what STEP 4's "seeders for the journey definition" should hold)
- The 3-step Master/Entry/Report spine → the brief's 8-step spine
- Five synchronous controller calls at page load → a real API returning progress

**Fix:**
- Unapplied rights filter (§8.7)
- `role_responsibility` tenancy
- Missing `database_table` migration
- Duplicate `setup_details` copy + duplicate route registration

---

*End of STEP 1 (revised).*
