# Version 1 — scope, structure and launch notes

Branch: `v1-launch` in both repos (`next_lms_erp` backend, `lms_k12` frontend).
Decisions taken for V1: roles are **Admin, Teacher, Student**; **PAL, H5P, AI/Brain and the other
experimental modules are hidden** (code untouched); **security hardening came first**.

## 1. Product overview

A multi-tenant K-12 school ERP with a learning layer. One deployment serves many schools: a school is a
row in `school_setup` (its id is `sub_institute_id`, the tenant key on almost every table) and schools
are grouped by `tblclient`. School staff run admissions, students, attendance, fees, exams/results and
teaching; students use the learning screens.

## 2. V1 scope

The smallest loop a school can run on: **people in → school runs daily → results and money out.**

In: login and role-based menus · school setup · admissions · student records · attendance · fees ·
exams, marks and report cards · teaching (courses, homework, assignments, exams in LMS) · circulars ·
role dashboards · reports for the above.

Out of V1 (hidden from menus, not deleted): PAL / adaptive learning, H5P authoring, AI workspace and
agents, Enterprise Brain, talent / people / HRIT, career modules, capability intelligence, SQAA, platform
roadmap and services, migration modules, bazar, mobile page builder screens. Also out: parent portal,
HRMS payroll/leave screens (API exists, no screens), LMS Message (placeholder), Learning-Outcome masters.

Hiding is a visibility measure. Pages stay reachable by URL; the backend is what protects data.
Re-enable for a pilot build with `NEXT_PUBLIC_SHOW_DEFERRED_MODULES=true` (frontend).

## 3. Modules and features

| Module | Key features (V1) | Main frontend routes |
|---|---|---|
| Access | Email login, forgot password, role menus from `tblmenumaster`, group/individual rights | `/login`, `/general/groupwise_rights`, `/general/individual_rights` |
| School setup | Standards, divisions, subjects, periods, timetable, users, profiles, onboarding journey | `/academic_setup/*`, `/user`, `/general/onboarding` |
| Admissions | Enquiry, registration, follow-up, confirmation, reports, dashboard | `/admissions/*`, `/admission-Enquiry` |
| Students | Add/search/bulk update, documents, health, certificates, ID cards, houses | `/student/*`, `/students/*` |
| Attendance | Daily marking, dashboards, day/month/year reports | `/attendance/*`, `/student/*attendance*` |
| Fees | Masters, collect, receipts, cancel/refund, defaulters, reports, online payment settings | `/fees/*` |
| Exam & result | Exam/grade masters, marks entry, co-scholastic, report cards, consolidated and grade reports | `/exam/*`, `/result/*` |
| Teaching | Course catalogue, chapters, lesson plans, homework, assignments, LMS exams, question papers | `/course-master/*`, `/lms/*`, `/subjects` |
| Communication | Circulars, SMS/email/WhatsApp send | `/front_desk/circular`, `/easy_com/*` |
| Dashboards | Admin, Teacher, Student landing pages | `/dashboard` |
| Optional add-ons | Hostel, transport, inventory, library, front desk, visitors | `/hostel`, `/Transportation`, `/Inventory`, `/library`, `/front_desk` |

## 4. User roles

Roles are **`tbluserprofilemaster.name`** per school (free text), not a fixed enum.

| Role | Identity in the token | Sees |
|---|---|---|
| Super Admin / client admin | `is_admin` 2 (all schools) or 1 (schools of own `client_id`) | Everything; may name another school in a request |
| Admin, School Admin, Principal | `is_admin` 0, profile name | Admin dashboard, setup, admissions, fees, results |
| Teacher (incl. "Class Teacher", "HOD", "Senior Teacher") | profile name contains teacher/hod/faculty | Teacher dashboard, attendance, homework, marks |
| Student | `is_student` true | Student dashboard, own learning screens |

What each role can open is decided by `tblindividual_rights` / `tblgroupwise_rights`; the dashboard only
picks the landing page. **Parent** has no role, menu or screens in V1.

## 5. Main workflows

1. **Admission → student:** Enquiry → follow-up → registration → confirmation → student record and enrolment (`tblstudent`, `tblstudent_enrollment`).
2. **Daily attendance:** teacher opens class → marks present/absent → dashboard and reports update.
3. **Fee collection:** set heads and breakoff → collect per student → receipt → defaulter and collection reports; cancel/refund with audit.
4. **Exam → report card:** exam master and creation → marks entry → approval → report card / consolidated report.
5. **Homework:** teacher assigns → student submits → teacher reviews.
6. **Circular:** staff posts → parents/students notified by SMS/email/WhatsApp.

## 6. Technical architecture

```
Browser ──► Next.js 16 (app/)  ──bearer JWT──►  Laravel 12 (PHP 8.2)  ──►  MySQL / MariaDB
            React 19, Tailwind 4                routes/*.php → controllers → services/models
            menu built from API                 Blade UI (legacy) uses PHP sessions
                                                optional: S3, Redis, queue, Neo4j, AI providers
```

- Login: `POST /api/api-login` returns a JWT (`id, sub_institute_id, user_profile_id, is_admin, client_id, is_student`).
  The frontend stores it in `localStorage` and sends it as `Authorization: Bearer`.
- Menu: `POST /api/menu-rights` returns level 1/2/3 rows; `app/data/menuMappers.ts` builds the tree and
  `app/data/routeMapper.ts` maps legacy links to Next.js routes.
- Tenant scoping is **manual**: queries filter on `sub_institute_id` themselves. There is no global scope.
- Legacy Blade screens (web session login) still run beside the Next.js frontend.

## 7. Database

914 tables on the development server, most of them legacy or AI. The V1 core:

| Domain | Key tables |
|---|---|
| Identity and rights | `tbluser`, `tbluserprofilemaster`, `tblmenumaster`, `tblindividual_rights`, `tblgroupwise_rights`, `tblprofilewise_menu` |
| Tenancy | `school_setup` (tenant), `tblclient`, `academic_year` (one row per school / year / term) |
| Academic setup | `standard`, `division`, `std_div_map`, `subject`, `sub_std_map`, `class_teacher`, `timetable`, `period` |
| Students | `tblstudent`, `tblstudent_enrollment` (class lives here, not on `tblstudent`), `tblstudent_document`, `tblstudent_tc_details` |
| Admissions | `admission_enquiry`, `admission_registration`, `admission_form`, `admission_category_master` |
| Attendance | `attendance_student`, `result_student_attendance_master` |
| Fees | `fees_title`, `fees_breackoff`, `fees_collect`, `fees_receipt`, `fees_payment`, `fees_cancel`, `fees_refund`, `fees_head_master` |
| Exam / result | `result_create_exam`, `result_marks`, `result_exam_master`, `result_reportcard_marks`, `grade_master`, `result_co_scholastic*`, `result_template_master` |
| Teaching | `homework`, `lms_assignment`, `lms_online_exam*`, `lms_question_master`, `lms_lesson_plan*`, `chapter_master`, `content_master`, `lms_curriculum` |
| Communication | `circular`, `sms_sent_parents`, `email_sent_parents`, `whatsapp_sent_messages` |

Relationships: everything keys on `sub_institute_id`; a student row joins `tblstudent_enrollment` on
`student_id` and `syear`; staff roles come from `tbluser.user_profile_id → tbluserprofilemaster`;
teacher to class linkage is through `timetable` and `class_teacher`.

## 8. API structure

41 route files, about 5,300 routes. V1 uses the `api/*` group mounted in `routes/api.php` (plus
`resultapi.php` for results, `easycomapi.php` for communication). Legacy Blade routes stay in
`web.php`, `student.php`, `fees.php`, `result.php`, etc. AI/experimental files (`brain.php`, `ai.php`,
`mcp.php`, `pal_*.php`, `talent_management.php`, `g2g_lms.php`) are outside V1.

Every `api/*` route is exactly one of: **guarded** by `config/api_guard.php`, behind an auth middleware,
served by a controller that validates the JWT itself, or **deliberately public** (next section).
`ApiGuardTest::test_every_api_route_is_guarded_authenticated_or_deliberately_public` fails the build if
a new route is none of these.

## 9. Security

**Done in V1**

- `api.jwt` middleware (`app/Http/Middleware/RequireApiJwt.php`, config in `config/api_guard.php`):
  239 route/method pairs that were previously anonymous (`api/*`) now require a verified JWT, or a web
  login session for the Blade pages that call them. It is registered in the `web` and `api` groups and matches on the real
  request path (URL-decoded, as the router sees it), so it holds whichever route file declares the route
  and cannot be slipped past with `%2D`-style encoding (a first version could; covered by a test).
- **Tenant rule:** a caller may only name its own school in `sub_institute_id`, unless `is_admin=2`, or
  `is_admin=1` and the school belongs to the caller's client. Previously any caller could read any school.
- **Role rule:** teacher/admin-only URLs (`staff` list) refuse students and parents with 403, using the
  same rule as the existing `staff.only` middleware. Student-facing reads stay open to any logged-in user.
- Closed a **token-minting route**: `GET api/testkey` returned a validly signed JWT with no credentials.
  It is now registered only in `local`/`testing`.
- Found and closed: document templates (read, edit, delete, student merge data) and three LMS routes
  trusted the school and role from the request body, and one decoded the JWT **without verifying its
  signature**.
- Login, OTP and `api-login` are throttled per IP (10–20 per minute) on top of the global 1000/minute.
- Dynamic table names in `DROP TABLE` / `SHOW COLUMNS` must be plain identifiers.
- Frontend: the bearer token is attached to every request to the ERP origin (and only that origin) that
  did not set its own; the profile menu's platform setup section is admin-only; AI and roadmap items are
  hidden.
- `.env.example` now defaults to `APP_ENV=production`, `APP_DEBUG=false`, and documents every key the
  code reads.

**Deliberately public** (`config/api_guard.php` → `public`, and the test's list): `api-login`, legacy
mobile `login` / OTP endpoints, WhatsApp gateway webhooks, public certificate verification, the public
online-admission submit, the ticket-redeem endpoint for the mobile WebView, and two dropdowns for the
public discipline form (only with `type=webForm`).

**Must be set at deploy** (not enforced by code):

| Setting | Why |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | Debug pages leak code and config |
| `JWT_SECRET` long and random | It signs every token |
| `CORS_ALLOWED_ORIGINS=https://<frontend>,…` | When unset, CORS falls back to `*` |
| `CAPTCHA` empty | A non-empty value is accepted as a valid captcha |
| `API_GUARD_ENFORCE=true` | `false` switches the guard off (emergency rollback only) |
| HTTPS only; PHP-FPM + nginx, not `artisan serve` | `serve` is single-threaded |
| DB backups, `storage/` and `.env` outside the web root | |

**Known risks NOT fixed in V1** (need a decision or a data migration)

1. **Web login compares passwords in plaintext / md5** (`loginController`). The API login already
   upgrades to hashes on success; the web login does not, and bcrypt rows cannot log in through it.
   Fix needs a password-migration plan for every school.
2. **Tokens live in `localStorage`** and pages are guarded client-side only. Any XSS exposes the token.
3. **WhatsApp webhooks accept any caller.** Add a shared-secret check before using them in production.
4. `ApiLmsCourseController` and a few older controllers still read `user_profile_name` from the request
   body; the `staff` list limits the damage but does not remove it.
5. `lms.auth` is warn-only until `LMS_API_AUTH_ENFORCE=true`.
6. About 38 debug/one-off scripts are tracked in the repo root (`test_*.php`, `debug_*.php`, `tmp_*.php`,
   `check_*.php`, `fix_agent.php`, …), several with hard-coded user/school ids. They are not web-served,
   but they should be removed or moved to `tools/` before launch. Also `composer.lock.bak`.
7. About 1,600 `dd` / `dump` / `print_r` / `exit` calls and 56 `exec` / `eval` hits in `app/` were not audited.
8. CSRF is exempted for `api/*` and `fees/*`.

## 10. Verification performed

| Check | Result |
|---|---|
| Laravel security tests (`tests/Feature/Security`) | 20 pass, including 10 new in `ApiGuardTest` |
| Frontend unit tests (`lib/roadmap`) | pass (V1 menu filter, link mapping, role resolution) |
| `npm run typecheck` | one error, `framer-motion` not installed in this machine's `node_modules` (it is declared in `package.json`; `npm ci` fixes it) |
| **Menu link gate** `npx tsx scripts/check-menu-links.ts <links.json>` | Before: 117 shown links led to a missing page. After: 287 shown, **0 dead** |
| Live HTTP checks | Protected URLs 401 without a token; student 403 on staff URLs; teacher and student 403 on another school; public entry points still respond |
| Browser run, real data (Playwright) | Admin, Teacher and Student dashboards load; no failed ERP call; no deferred module in the sidebar; `/lms/homework`, `/document-templates`, admissions, result and exam screens load with the guard on |

Re-run the menu gate whenever menu rows change: export `id, level, name, link` from `tblmenumaster`
where `status = 1`, save as JSON, and run the script. It exits 1 if a visible link would 404.

## 11. Fixed along the way (frontend)

- Admin dashboard quick actions pointed at `/fees`, `/reports`, `/settings` (404). Now `/fees/collect`,
  `/fees/reports`, `/general/onboarding`.
- 20 of 30 real Result menu links resolved to pages that do not exist (the screens were rebuilt at new
  paths). `routeMapper.ts` now generates every spelling the menu table uses from one table.
- Teachers named "Class Teacher" / "HOD" landed on the admin dashboard and got a 403.
- The sidebar no longer shows links that can only 404 (Blade-only HRMS/payroll screens, AI containers,
  LMS Message placeholder).

## 12. Not done / next (V1.1)

Parent portal · HRMS leave/payroll/attendance screens (APIs exist) · password hashing on web login ·
webhook secrets · removing the root debug scripts · tests for attendance, fees collection UI, marks
entry and login (core school operations have almost none) · an `.env` review on the production host.
