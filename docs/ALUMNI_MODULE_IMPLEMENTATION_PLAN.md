# Alumni Management Module — Implementation Plan & Checklist

Spec: `Alumni_Module_newK12.pdf` (50 sections). Repos: Laravel `d:\next_lms_erp` (API), Next.js `d:\lms_k12` (UI).
Branch: `student_workflow` (both). Status legend: [ ] todo · [~] in progress · [x] done and verified.

## 1. Audit findings that shape the design

| Concern | Finding | Design decision |
|---|---|---|
| Tenancy | Single DB, `sub_institute_id` column on every table; no global scope. JWT claims hydrate a fake session (`api.session`). | Every alumni table carries `sub_institute_id` (indexed). Tenant is read only from `session('sub_institute_id')`, never from the request body. Every query is explicitly scoped. |
| Academic year | `syear` is client-overridable. | Alumni master is not year-scoped; `syear` is only used when reading enrollment history. |
| Routes | `routes/api.php` + per-module files registered in `RouteServiceProvider` with `Route::prefix('api')->middleware('api')`. Writes are POST. | New `routes/alumni.php`, new `mapAlumniRoutes()`. Group: `api.session` + `staff.only`. Writes via POST like sibling modules. |
| Envelope | `{status_code:1\|0, message, data}`; 422 with `errors`. | Same envelope. |
| Permissions | Menus in `tblmenumaster`, rights in `tblgroupwise_rights`; `perm:` middleware is warn-only by default; `check_permissions` is route-name based. No Alumni role exists. | Menu + rights migrations copy the Talent Management pair. Server-side role gate in the controller (`AlumniAccess`), because `perm:` is warn-only and `staff.only` only blocks Student/Parent. |
| Alumni login | JWT login knows only `tbluser` and `tblstudent`. | **Dependency / open decision**: alumni self-service login needs a new login path. Phase B builds staff-side screens first; alumni-facing portal is gated on that decision. |
| Student data | `tblstudent`, `tblstudent_enrollment` (one row per syear), `tblstudent_tc_details`, `student_inactive`, `lc_number`, `drop_code`. **No "passed out" status exists.** | Conversion eligibility is a *configurable rule set* (`alumni_settings`), default = Leaving Certificate issued OR final-standard enrollment ended. Always dry-run first; conversion is explicit and idempotent (unique `tenant+student_id`). |
| Events | Only `calendar_events` (no registration). | New `alumni_events*` tables (Phase D). |
| Communication | `NotificationSender::dispatch` really sends email + in-app; SMS/WhatsApp/push log `skipped` until an integration config exists. | Use it. Surface `skipped` honestly; never report "sent". |
| Payments | Razorpay SDK + `FeePaymentGatewayResolver` for fees. | Reuse resolver in Phase E; server-side verification; do not enable without gateway config. |
| Audit | `AuditTrail::record()` → `platform_audit_log`. | All alumni writes call it. |
| Import/Export | `maatwebsite/excel` present. | Reuse for import/export (Phase F). |
| Tests | Real DB is the live DB. Prayogshala test forces in-memory sqlite. | Same pattern; tests refuse to run unless sqlite. |
| Frontend | App Router; menu is backend-driven (DB `link` == route); `fetchLaravelJson` via `/api/proxy?path=`; shadcn kit + recharts; no SWR; tests are `node:test`. | Pages under `app/alumni/<screen>/page.tsx`; module `app/alumni/_lib/alumni-api.ts` (reads Laravel `errors` for field validation); `components/ui/*`; design-system tokens per CLAUDE.md. |

## 2. Data model (all tables `alumni_*`, all with `sub_institute_id`)

Phase B: `alumni` (master; unique `(sub_institute_id, student_id)` and `(sub_institute_id, alumni_code)`), `alumni_id_sequence`, `alumni_settings`, `alumni_education`, `alumni_career`, `alumni_business`, `alumni_skills`, `alumni_achievements`, `alumni_documents`, `alumni_privacy`, `alumni_activity` (timeline + engagement).
Phase C/D/E (designed later, one migration per phase): events/registrations/feedback, mentorship (profiles/requests/sessions), job opportunities, campaigns/donations/receipts/refunds, surveys/questions/responses, comm campaigns/templates (delivery rows come from `platform_notification_log`), import batches/rows.
Academic history is **not copied**: it is read from `tblstudent_enrollment` + `result_marks` via `student_id`.

## 3. API surface (prefix `/api/alumni`, middleware `api.session`, `staff.only`)

Phase B: `GET|POST alumni/list`, `GET alumni/{id}`, `POST alumni/store`, `POST alumni/{id}/update`, `POST alumni/{id}/status`, `POST alumni/{id}/verify`, `GET alumni/{id}/academic-history`, `GET alumni/conversion/preview`, `POST alumni/conversion/run`, `GET alumni/dashboard`, `GET alumni/meta` (filters/options), `GET alumni/settings`, `POST alumni/settings`. Child records: `GET|POST alumni/{id}/education|career|skills|achievements` + `.../{rid}/update|delete`.
Later phases are listed in the checklist and documented per API as built.

## 4. Menu (tblmenumaster, link == Next.js route)
Module `alumni_management.index` → level-2 items with links `alumni/dashboard`, `alumni/list`, `alumni/add`, `alumni/pending_verification`, `alumni/directory`, `alumni/batches`, `alumni/career`, `alumni/higher_education`, `alumni/communication`, `alumni/events`, `alumni/engagement`, `alumni/donations`, `alumni/surveys`, `alumni/import_export`, `alumni/reports` (spec §46 flattened to the ERP's 2-level convention; sub-screens become tabs). Profile route `alumni/profile/[id]` is reached from lists, not the menu.

## 5. Phases & checklist

**A — Foundation**
- [x] Audit (this doc)
- [x] Plan, schema, menu + rights migrations (written; sqlite-tested; NOT yet run on the live DB)

**B — Core alumni** (backend verified by 16 sqlite tests; UI type-checked and linted, not yet browser-tested)
- [x] Master/settings/child migration `2026_10_10_100000` (run on live DB: pending, use `--path`)
- [x] ID generation (configurable pattern), duplicate detection, profile completion
- [x] CRUD, status, verification, audit (`AuditTrail`)
- [x] Conversion preview/run (rule-based, idempotent)
- [x] Academic history read-through
- [x] Child records API: education, career, business, skills, achievements
- [x] Next.js: dashboard, list, add/edit, pending verification, conversion, 360 profile (5 tabs)
- [x] Browser E2E: 16/16 steps pass (`lms_k12/scripts/e2e/alumni-e2e.cjs`; menu, dashboard, list search/filter/sort/paging, add + validation + duplicate, profile child records, status, edit, verification approve/reject, conversion preview, 390px layout, 403 for a non-alumni profile). Bug found and fixed: adding a skill with no level returned HTTP 500 (regression test added).
- [x] Batch statistics API + screen (`alumni/batches`; group by batch, passing year, admission year, course, department, campus; rows link into the filtered list). Browser-verified. Menu entry is in migration `2026_10_10_100500` (NOT yet applied to the live DB).
- [x] Directory with backend-enforced privacy (`alumni/directory`, `alumni/directory/options`): hidden fields are blanked AND cannot be searched on; filter dropdowns only list visible values; only verified/active alumni; 18 backend tests + browser-verified.
- [x] Documents: private-disk upload (type/size whitelist, content sniffing, random names), authorised download, verify/reject, delete; achievement verification with evidence. Privacy & consent editor (profile tab). Browser-verified incl. a PHP file disguised as a PDF being refused.
- [ ] Alumni self-registration + alumni login (blocked on open decision 2)
- [x] Export CSV/XLSX (privacy-masked, formula-injection-safe, 20,000-row cap, audited) and bulk status change (<=500, tenant-scoped). Browser-verified.

**C - Career, mentorship, jobs, engagement** (code-complete, 54 backend tests pass, browser-verified 14/14 + Phase B 24/24 on an isolated SQLite stack)
- [x] Mentor profiles (verified/active alumni only, one per alumnus, capacity, days, mode)
- [x] Mentorship workflow: request (staff on behalf of a student, or the student) -> explainable matching -> mentor accepts/declines (recorded by staff until alumni can sign in) -> sessions -> feedback -> complete; capacity and decline rules; max 3 open requests per student
- [x] Opportunities: job/internship/apprenticeship/project/freelance, approval setting, derived expiry (a stopped scheduler cannot leave a dead listing), http(s)-only links, alumni credit
- [x] Engagement ledger + configurable weights/window/levels + score (capped 100), idempotent automatic credit, leaderboard, profile tab, dashboard tiles
- [x] Student-facing API + page (`/alumni/student`): own data only, no alumni contact details
- [x] Education, career, business, skills, achievements, documents: done in Phase B
- [ ] Menu entries for Batches, Directory, Engagement, Mentorship, Jobs, Student page: migrations `2026_10_10_100500` and `2026_10_11_100100` written and tested, NOT applied to the live DB (needs your go-ahead; student page placement needs a product decision)
- [ ] Phase C tables (`2026_10_11_100000_create_alumni_engagement_tables`) NOT applied to the live DB

**Defect found by the browser run (not visible to unit tests):** `api.session` sets `type=API` on every request, silently replacing any request field named `type`. The opportunity form and the student board broke. Fix: request fields are `opportunity_type` / `engagement_type`; every alumni test helper now merges `type=API` like the middleware does.
**Defect found earlier:** bare `throttle:N,1` shares one IP-wide counter with all API traffic; alumni routes use named per-user, per-action limiters.

**D - Communication, events, surveys** (code-complete; 102 alumni backend tests + 23 security tests pass; browser-verified 12/12, with Phase B 24/24 and Phase C 14/14 re-run afterwards, all on the isolated SQLite stack)
- [x] Templates (built-in defaults, placeholders, CRUD), campaigns (audience filters, preview, send now / schedule / draft / cancel / retry), message log with per-recipient status and reason, history screen and profile tab
- [x] Consent and eligibility: marketing-type messages only reach registered/verified/active alumni who consented to that channel; opted-out, blocked and deceased never; transactional notices (verification, event confirmations) are separate and say so
- [x] Honest delivery: in-app inbox is real; email counts as sent only when a real mail transport accepted it; SMS/WhatsApp/mobile are recorded `skipped` with the reason ("no driver" / "not configured") and never reported as delivered. The compose screen shows each channel's real state before sending
- [x] Idempotency: a campaign cannot message the same alumnus on the same channel twice; automatic notices carry a dedupe key (birthday, anniversary, event reminders)
- [x] Events: draft/publish/complete/cancel, capacity enforced under a row lock, FIFO waiting list with automatic promotion, confirm/cancel, manual payment status (no gateway involved), check-in by QR token / mobile / registration no. / alumni id / manual (opens the day before), no-show on completion, feedback only from attendees, files, invitations, per-alumnus event history, engagement credit for attendance
- [x] Surveys: six question types, anonymous mode (answers stored without the alumnus id), one response per person, questions locked once responses exist, results with counts/averages/distribution, participation list, record-on-behalf, send request
- [x] Scheduler + commands: `alumni:run {task}` entries (campaign dispatch, milestone notices, event reminders, opportunity expiry) in `Kernel.php`
- [ ] Menu entries (Communication, Events, Surveys): migration `2026_10_12_100300_add_alumni_phase_d_menu` written and sqlite-tested, NOT applied to the live DB
- [ ] Phase D tables (`2026_10_12_100000/100100/100200`) NOT applied to the live DB
- [ ] Real email delivery needs a configured mail transport; SMS/WhatsApp/push need provider configs (decision 4). Until then those messages are `skipped`, which is visible in the UI

**Defects found by the browser run, fixed:** (1) after sending, the "Done: N sent, N skipped..." summary vanished with the form - it now lives in the page and shows above Campaigns; (2) the check-in screen only explained "opens the day before" after a refusal and showed a wrong registration-number example - it now says so up front. Test-side: the sample alumni have not consented to email, so email is reported as "no consent" (correct), not "skipped".
**Known weak assertion:** the E2E feedback step accepts either "saved" or a refusal, because which alumnus attended is not controlled there; the rule itself is covered by `AlumniEventsTest`.
**E - Donations** (offline-recorded part code-complete; **online payment NOT built - blocked on decision 3**; 16 donation tests; browser-verified 9/9)
- [x] Campaigns (goal, dates, status; "raised" = verified money net of refunds, never pledges or unverified entries)
- [x] Donations recorded by finance staff: pledge, or money received (cash/cheque/transfer/UPI/card, entered by hand). Pledged -> awaiting verification -> **verified** (a person confirms) -> partly refunded / refunded; reject and cancel need a reason
- [x] Receipt number (`DON-YYYY-NNNNN`, per institute, unique index + retry) is issued ONLY at verification, exactly once, and never for a pledge/rejected/cancelled gift. Cheque/transfer/UPI/card need a reference before verification. Verified gifts are locked (edit refused); refunds are separate rows bounded by the remaining amount and the receipt stays as the record of the original gift
- [x] Verification credits engagement once and sends a receipt notice (a transactional notice; email counts as sent only through a real mail transport)
- [x] `finance` role gate (Admin-types + Accountant) on every donation endpoint; Accountant gets a minimal donor lookup (id/name/code/batch, no contact data). Anonymous donors are masked in lists, summary and reports. Printable acknowledgement receipt ("not a tax-exemption certificate")
- [ ] Online payment, gateway webhooks, payment links, automatic refunds, 80G/tax certificates: **not built** (no gateway or institute registration details were chosen). Nothing in the code can mark a payment successful on its own
- [ ] Menu: `2026_10_13_100100_add_alumni_donations_menu` (Admin-types + Accountant only) written and sqlite-tested, NOT applied live. Tables `2026_10_13_100000` NOT applied live
- Not covered by a test: two simultaneous verifications picking the same receipt number (the unique index plus bounded retry handle it, but a race cannot be forced here)

**F - Reports, import, audit, review** (code-complete; 152 alumni tests + 23 security tests; browser-verified Phase F 11/11 after the chunked-import change - 11/11 steps)
- [x] Nine reports (master, batch-wise, career, higher education, location, company/industry, engagement, events, donations), filters, paging, summary tiles; CSV and XLSX export of exactly what the screen shows (up to 20,000 rows), formula-safe, privacy-masked, audited. Per-report gate: donations need `finance`
- [x] Privacy in aggregates: location / employer / higher-education counts leave out alumni who made that field private; row reports blank it; salary is never reported
- [x] Bulk import (CSV/XLSX, 2,000 rows / 5 MB): upload -> auto column match -> mapping -> **preview writes nothing** -> confirm. Content-sniffed file type; row validation with reasons; duplicates in the file and against existing alumni (bulk lookup, constant queries); formula cells refused; day-first dates, Excel serial dates and "2018.0" years understood; problems file to fix and re-upload; file deleted after confirm/cancel; scheduled purge of abandoned uploads (2 days) and row data (14 days)
- [x] Import rules (decisions, see 8-10 below): existing alumni never overwritten; **no consent imported**; status never imported (everyone starts `registered`); a row needs a first name and an identifier
- [x] Confirm runs in resumable chunks of 100 rows (about 16 queries per row measured, so one request is bounded) with progress in the UI, "Finish import" for unfinished batches, atomic row claims, a stale-row recovery, and a purge that finishes forgotten imports without importing the rest
- [x] Audit-log viewer (Admin-types only, read-only, filters, before/after detail truncated at 4 KB)
- [x] Security review as an executable test (`AlumniRouteSecurityTest`): every one of the 133 staff routes is behind `api.session` + `staff.only` and returns 403 to a Teacher before doing anything; money/audit routes also refuse an Alumni Coordinator; the 7 student routes refuse staff and unknown students; a tenant in the request body is ignored
- [ ] Menu: `2026_10_13_100300_add_alumni_phase_f_menu` (Reports + Import follow the Alumni List rights; Audit Log Admin-types only, view only) written and sqlite-tested, NOT applied live. Import tables `2026_10_13_100200` NOT applied live. Optional index migration: see "Performance" below

**Defects found while building D-F and fixed**
1. **Every alumni audit entry had no actor** (`AuditTrail` read the user only from the `lms_auth` attribute, which `api.session` routes never set). Fixed centrally in `AuditTrail` (falls back to the verified session user; the name lookup no longer uses MySQL-only `CONCAT_WS` and cannot drop a row). This is a shared-platform change: other `api.session` modules now also get an actor in their entries. Regression tests added
2. Accountant could not search for a donor (the picker used the manage-gated alumni search) - finance donor lookup added
3. `events/{id}/registrations/{rid}/payment` validated input before the role gate - found by the route-security net, fixed
4. Donation summary used an ambiguous `sub_institute_id` in joined queries (would have failed on MySQL) - found by tests
5. Alumni list ignored `?q=` in the URL - fixed
6. Audit viewer kept a stale detail panel after the filters changed - fixed
7. Import confirm was one request of ~16 queries per row (about 32,000 for a full file): redesigned as bulk preview + chunked confirm

**Performance** (measured on SQLite, so counts matter more than times): import preview 306 -> constant (<40) queries for 300-400 rows; import confirm ~16 queries/row, bounded to 100 rows/request; existing alumni indexes already cover the list filters, geography, batch, year, industry and the career/education children. Reports page their results (25/page) and the batch/engagement mappers run per page, not per row. Master export holds up to 20,000 rows in memory (about 20 MB). Not load-tested against the real MySQL.

## 6. Open decisions for the product owner
1. What marks a student "passed out"? (default rule is a proposal)
2. Alumni login: separate `alumni` profile + login path, or token-link access?
3. Payment gateway/credentials and 80G/tax-receipt requirements for donations.
4. SMS/WhatsApp/push provider configs per tenant (otherwise those channels stay `skipped`).
5. Privacy masking policy: staff see everything except fields an alumnus set to `private`; defaults are conservative (contact details institution-only). Confirm whether admins should see private fields.
6. Consent defaults: nobody is messaged on a channel without recorded consent; transactional notices (verification, event confirmations, receipts) go by email + in-app without marketing consent. Confirm this split.
7. Money controls: should the person who recorded a donation be barred from verifying it (two-person rule)? Not enforced today; both are recorded and audited. Should the Accountant profile get the Donations menu (the migration grants it)?
8. Import: existing alumni are never overwritten (an "update existing" mode would be a separate, explicit feature).
9. Import: imported alumni get no consent and start `registered`; consent is captured later.
10. Import limits (2,000 rows, 5 MB, 100 rows per request) and retention (uploads 2 days if abandoned, row data 14 days).
11. Audit log visibility: Admin-types only (it shows donation amounts and who verified what). Should coordinators see their own module's entries?
12. Receipt numbering `DON-YYYY-NNNNN` by calendar year and what a receipt must say (it currently states it is not a tax certificate).
13. Where students reach the alumni page (menu entry placed under Alumni Management with view-only student rights).
14. Whether to apply the pending migrations to the live database (list and commands in the final report).

## 7. Product-owner decisions received (2026-10-12) and what was done

| # | Decision | Status |
|---|---|---|
| 1 | Passed out = Leaving Certificate issued OR final-standard enrollment ended | Done: new default rule `lc_or_final_enrollment_ended` (final standard = the institute's highest `sort_order`; falls back to LC-only if the live tables lack those columns). Tested. |
| 2 | Separate alumni login | **Not built yet.** The choice is made but its details are not (see open questions below). Nothing was invented. |
| 3 | Defer online donation payments | Deferred: nothing to build; offline recording only. |
| 4 | Defer SMS / WhatsApp / push providers | Deferred: those channels stay recorded as `skipped`. |
| 5 | Student alumni page from the student dashboard; Accountant gets the Donations menu | Done: link on the student dashboard; the student menu entry and Student rights were removed from the Phase C menu migration before it was applied; Accountant has Donations only. |
| 6 | Separate recorder and verifier | Done: the user who recorded the money cannot verify it (403, tested); marking a pledge received makes that person the recorder. |
| 7 | Reject duplicates; manual consent | Already how import works (duplicates are never imported; no consent imported). |
| 8 | Apply approved migrations to production now | **Applied 2026-10-12** to `vivek_erp`, one at a time with `--path`: 6 table migrations then 5 menu migrations. Before: 702 menu rows (max id 897), 51,632 rights rows (max id 281,675), 819 migrations. After: 714 / 929, 53,082 / 283,141, 830. |

Open questions for decision 2 (separate alumni login): how an alumnus first gets credentials (invitation link sent by staff, or open self-registration that staff then verify); the login identifier (email or alumni ID); password rules and reset path; whether a login may ever see other alumni's data (it must not by default); and whether alumni sessions use the same JWT service with an `is_alumni` claim. Alumni self-registration (spec) depends on the same answer.

Live menu check after applying: 16 screens under Alumni Management; no Student/Parent/Teacher profile holds any alumni menu right; Donations is held by Admin-type and Accountant profiles only; Audit Log by Admin types only. `SUPER ADMIN` profiles received the copied screens (Reports, Import, Communication...) but not Donations or Audit Log in the menu, because the name-matched grant only covers profiles active in the module's institute list; they still pass the controller gate, so this can be added by hand if wanted.

## 8. Alumni authentication - inspection and design (written before implementation; NOT released, NOT applied to production)

### 8.1 What the existing authentication does (inspected, not changed)
- **Staff / Student login** (`ApiLoginController@login`): looks the user up in `tbluser` (staff) or `tblstudent`, checks the password (Laravel hash, with upgrade from legacy plain/MD5), and issues a JWT through GenTux `JwtToken::createToken` signed with the global `JWT_SECRET`. Claims: `id`, `sub_institute_id`, `is_admin`, `client_id`, `user_profile_id`, `is_student` (+ `iat`/`exp` only if `JWT_TTL_MINUTES` is set; otherwise tokens never expire).
- **`api.session`** (`ApiSessionHydrator` + `HydratesLegacyApiSession`): validates the JWT with the global secret and **trusts `id` as a `tbluser`/`tblstudent` id and `sub_institute_id` as the tenant**, then fills the in-memory session that thousands of legacy controllers read. **`staff.only`** blocks only profiles named Student/Parent (a profile with an empty name passes). **`api.jwt`** (`RequireApiJwt`) validates the same global-secret JWT for the routes listed in `config/api_guard.php`. `ApiGuardTest` requires every `api/*` route to be guarded, behind an auth-named middleware, or listed as deliberately public.
- **Frontend**: every route not behind the staff login renders the staff `LoginPage` (`ConditionalApp`); `installErpAuthFetch` adds the staff bearer to ERP fetches that lack one; the `/api/proxy` relay forwards `Authorization` and hides the browser IP from Laravel.

### 8.2 The danger this design avoids
An alumni token signed with the **same** secret would pass `api.session`, be read as `tbluser` id = (alumni id) in the alumni's tenant, carry no profile name (so `staff.only` lets it through) and could reach staff endpoints as whichever staff user happens to share that number. Therefore: **alumni tokens use a different signing secret (`ALUMNI_JWT_SECRET`), a different audience, different claim names (no `id`, no `sub_institute_id`, no `is_student`), and a different middleware that never hydrates the legacy session.** As defence in depth the legacy hydrator also refuses any payload marked as an alumni token. A staff token is, symmetrically, invalid for the portal (wrong secret).

### 8.3 Tables (migration `2026_10_14_100000_create_alumni_auth_tables`, one migration, additive)
- `alumni_accounts`: `sub_institute_id`, `alumni_id` (unique per institute), `email` (lower-case, unique per institute), `password_hash` (Laravel hash; null until set), `email_verified_at`, `status` (`invited` | `unverified` | `active` | `disabled`), `failed_attempts`, `locked_until`, `last_login_at`, `password_changed_at`, `created_via` (`invite` | `self`), `created_by`, `disabled_by`, `disabled_reason`.
- `alumni_auth_tokens`: single-use tokens for `invite` (7 days), `verify_email` (24 h), `reset_password` (60 min). Only the **SHA-256 of the token is stored**; the token itself exists only in the email/link. `used_at` makes it single use.
- `alumni_auth_sessions`: one row per login: `refresh_hash` + `previous_refresh_hash` (refresh-token rotation with **reuse detection**: presenting an already-rotated token revokes the session), `expires_at` (absolute, 30 days), `last_used_at`, `revoked_at`, `ip`, `user_agent`.

### 8.4 Token lifecycle and claims
- **Access token** (JWT, HS256, `ALUMNI_JWT_SECRET`, min 32 chars, **fail closed if unset**): `iss=alumni-portal`, `aud=alumni`, `typ=alumni_access`, `sub=<account id>`, `aid=<alumni id>`, `tid=<institute id>`, `sid=<session id>`, `iat`, `exp` (30 min), `jti`. On every request the middleware also checks that the session is not revoked/expired, the account is `active`, and the alumnus is not blocked/deceased/opted out - so disabling an account or resetting a password takes effect **immediately**, not after the token expires.
- **Refresh token**: 256-bit random, stored hashed, rotated on every use. Logout, password change/reset, staff disable and reuse detection revoke sessions.
- Identity comes only from the verified token (`tid`/`aid`); a request body can never name another alumnus or institute.

### 8.5 Routes (all under `api/alumni-portal`, registered separately from the staff `alumni/*` routes)
- **Public, rate-limited:** `register`, `login`, `refresh`, `verify-email`, `resend-verification`, `forgot-password`, `reset-password`, `invite/check`, `invite/accept`. They never reveal whether an email has an account (same answer either way; duplicate-email registration and unknown-email resets get the same generic reply).
- **Authenticated (`alumni.auth`):** `me`, `me/update` (limited fields), `me/privacy` (get/save), `change-password`, `logout`, `events` (list), `events/{id}/register`, `events/{id}/cancel`. Every query is scoped by `tid` + `aid`; features need the alumnus to be `registered`/`verified`/`active` (a self-registered `pending_verification` alumnus can log in and see their own status only until staff verify them).
- **Staff (`api.session` + `staff.only`, `manage` gate):** `alumni/{id}/invite` (issues an invitation; the link is shown **once** and emailed), `alumni/accounts` (list), `alumni/accounts/{id}/disable|enable|revoke-sessions|resend-verification`. Every action is audited.

### 8.6 Rules
- Login = **institute code + email + password**. Passwords: min 10 chars, letters and digits, not the email; bcrypt via `Hash::make`. Email verification is required before the first login, **including invited accounts** (an invitation link proves the staff chose to send it, not that the mailbox is theirs).
- Self-registration always creates a **new** alumni record (`source=self`, `pending_verification`) through the normal duplicate check; if the person already exists it creates nothing and tells them (by email) to ask the institute for an invitation. It never links to, or reveals, an existing record.
- Rate limiting + lockout: 5 wrong passwords lock the account for 15 minutes; login/reset/register/resend have named limiters keyed on the email **and** the IP (the proxy hides the browser IP, so the email key is what actually protects an account).
- Reset tokens: single use, 60 min, a new request invalidates earlier ones, success revokes every session.

### 8.7 Migration / release requirements (nothing below has been done on production)
1. Apply `2026_10_14_100000_create_alumni_auth_tables` (needs explicit approval).
2. Set `ALUMNI_JWT_SECRET` (random, >= 32 chars, **different from `JWT_SECRET`**) and `ALUMNI_PORTAL_URL` in the environment. Without the secret the portal answers 503 and nothing else changes.
3. A mail transport must be configured, or verification / invitation / reset emails are recorded as `skipped` (staff can still copy an invitation link).
4. The portal's public routes are added to the reviewed list in `ApiGuardTest`.
5. Frontend: `/alumni-portal/*` is exempted from the staff login shell in `ConditionalApp` (only that path prefix).
6. Optional later: a menu entry for an "Alumni accounts" staff screen (the API exists; v1 puts account controls on the alumnus profile).

## 9. Alumni authentication - what was built and verified (2026-10-12) - NOT released, NOT applied to production

**Status:** code-complete and test-passing on SQLite; browser-verified on the isolated stack. **Nothing has been applied to production and the portal is not live**: it needs the migration below, the environment settings in 8.7, and your explicit approval.

### 9.1 What exists
- **Backend:** `config/alumni_auth.php`; migration `2026_10_14_100000_create_alumni_auth_tables` (NOT applied); `AlumniAuthService` (accounts, registration, invitations, email confirmation, sign-in, lockout, refresh rotation with reuse detection, password change/reset, staff disable/enable); `AlumniAuthenticate` middleware (`alumni.auth`); `AlumniPortalApiController` (public + signed-in portal API, `routes/alumni_portal.php`); `AlumniAccountApiController` (staff: invite, list, show, disable, enable, sign out everywhere, resend); rate limiters `portal-login|register|reset|token|api`.
- **Frontend:** `/alumni-portal` (my details, privacy and contact, events, password), `/alumni-portal/login|register|verify-email|forgot-password|reset-password|invite`; a "Portal access" tab on the staff alumnus profile; the portal is exempted from the staff login shell by an exact path prefix in `ConditionalApp`.
- **Shared code touched (all covered by the existing suites, which still pass):** `HydratesLegacyApiSession` now refuses any token marked as an alumni token; `Kernel` has the `alumni.auth` alias; `AlumniPrivacy` gained `payload()`/`saveChoices()` (the staff controller now calls them, so staff and portal share one implementation); `AlumniEventService::mayAttend` is public; the reviewed public-route list in `ApiGuardTest` has the 9 portal sign-in routes; the test institutes have a `ShortCode`.

### 9.2 Findings from inspecting the existing JWT code (they shaped the design)
1. The staff stack reads a token's `id` as a `tbluser` id and `staff.only` blocks only profiles *named* Student/Parent, so an alumni token signed with the staff secret would have been accepted as staff user #N. **Alumni tokens therefore use a different secret, audience and claim names, and are rejected by the legacy hydrator even if one were ever signed with the staff secret (tested).**
2. The GenTux wrapper silently falls back to the **staff `JWT_SECRET`** when no secret is passed and to an environment-chosen algorithm. The portal therefore uses `Firebase\JWT\JWT` directly (the library GenTux wraps) with HS256 pinned and **refuses to run (503) if `ALUMNI_JWT_SECRET` is missing, under 32 characters, or equal to the staff secret** (tested).
3. `platform_notification_log.body` stores full message bodies, so emailed one-time links would have sat in the database. **The link is scrubbed from that log right after sending, tokens are stored only as SHA-256, and a test proves the live token is in no table.**
4. The `/api/proxy` relay hides the visitor's IP from Laravel, so IP-keyed rate limits would throttle everyone together; limits are keyed on the hashed email as well, with a generous per-IP ceiling.
5. Shared Next.js code adds the **staff** bearer to requests that carry no `Authorization` header; the portal client sends an empty `Bearer` on public calls so a staff token can never ride along.

### 9.3 Verified behaviour (tests)
`AlumniAuthTest` (32): secret missing/weak/equal-to-staff -> 503 everywhere; registration answers identically whether or not the person exists and never links to an existing record; email must be confirmed before first sign-in (also for invited accounts); sign-in failure message identical for wrong password / unknown email / wrong institute; 5 wrong passwords lock the account for 15 minutes (a lock looks like any failure); named per-email rate limit (429); tokens signed with the staff or a wrong secret, wrong audience/type/tenant/alumnus/session, expired, HS512, `alg: none`, garbage -> 401; disabling an account, blocking an alumnus, an expired session, logout and password reset take effect on the next request; refresh rotation and **reuse of an old refresh token revokes the login**; reset/verify/invite tokens are single-use, expire, are replaced by a newer one, and a weak password does not burn them; change password signs out other devices only; staff invite (link shown once, email refused when none on record / blocked statuses / already has account / duplicate email / other institute), staff gate (Teacher/Accountant/Peon refused), staff see only their own institute's accounts; an alumnus can read and change only their own record (ids, tenant and protected columns in the body are ignored, only 10 contact fields are editable), privacy/consent apply only to the caller, events self-service respects audience/capacity/ownership and cannot claim a payment; alumni tokens are refused by staff endpoints and staff tokens by the portal; same email at two institutes = two unrelated accounts; audit entries exist for every security event and contain no password, hash or token; the portal route table is pinned (exactly 9 public routes, 9 behind `alumni.auth`, none behind the staff stack, all with named portal throttles).

### 9.4 Results of this verification run
| Check | Result |
|---|---|
| Alumni backend tests (13 files) | all pass - see the totals recorded in section 10 |
| Security tests (`tests/Feature/Security`, includes the route-guard test) | 23 pass |
| Browser, isolated stack: Phase B / C / D / E / F | 24/24, 14/14, 12/12, 9/9 (donation recorder/verifier rule exercised), 11/11 |
| Browser, isolated stack: alumni portal | 11/11 |
| Frontend type-check and lint on every touched file | clean (see section 10) |

### 9.5 Defects found by the browser/tests during this work and fixed
- The email-confirmation page showed the generic "Please correct the highlighted fields" instead of "invalid or expired" after a link was used (the specific reason arrives as a field error).
- The portal route test and two older route-listing tests matched `api/alumni-portal` through the `api/alumni` prefix; they now scope to `api/alumni/` and a separate test pins the portal's routes.
- Phase E script helper had a mangled regex (test-side only).

### 9.6 Super Admin menu check (read-only on production; nothing was changed)
The 6 `SUPER ADMIN` profiles are platform-level (`sub_institute_id = 0`), none belongs to the module's institute list, and `tbluser` has **no** `is_admin = 2` users. Each holds the module row and 14 of the 16 alumni screens; **missing: Donations and Audit Log.** Access is not affected: the role gate accepts the `SUPER ADMIN` profile name and `is_admin = 2`. Not added, per your instruction to report first. If you want them, it is 12 rights rows (6 profiles x 2 screens) for institute 0.

### 9.7 Outstanding
- **Needs your approval:** (1) apply `2026_10_14_100000_create_alumni_auth_tables` to production; (2) set `ALUMNI_JWT_SECRET` (random, >= 32 chars, different from `JWT_SECRET`) and `ALUMNI_PORTAL_URL` in the production environment; (3) release the frontend portal pages; (4) decide whether to add the Donations and Audit Log menu rows for Super Admin profiles.
- **Before release:** a working mail transport (otherwise confirmation/reset emails are `skipped`, staff can still copy invitation links; the `log` mail driver would write links to `laravel.log`, so do not use it in production); a manual smoke test against a staging copy with real email.
- **Not built / deferred:** changing the sign-in email (needs re-verification), a staff "Alumni accounts" list screen and menu entry (API exists), alumni-facing mentorship/opportunities/directory in the portal, social login, two-factor sign-in, portal notifications.
- **Known limits:** the 30-day absolute session and 30-minute access token are defaults in `config/alumni_auth.php`; refresh-token reuse detection treats two tabs refreshing at the very same instant as theft (the user signs in again); account lockout is per account, so someone can lock a known alumnus out for 15 minutes (the usual trade-off - the email-keyed rate limit and lockout both apply).
- **Unchanged and still open:** online donation payments (deferred), SMS/WhatsApp/push providers (deferred), the two items above for Super Admin.

## 10. Verified totals (2026-10-12, final run of this session)
- Backend (SQLite): **195 alumni tests, 2,140 assertions, all pass** (13 files, including `AlumniAuthTest` 32, `AlumniRouteSecurityTest` 5, `AlumniDonationsTest` 18); **23 security tests pass**.
- Browser (isolated SQLite stack, each suite on a freshly built DB, run one at a time): Phase B 24/24, C 14/14, D 12/12, E 9/9, F 11/11, alumni portal 11/11 = **81 steps, all pass**.
- Frontend: `tsc` (filtered to the touched areas; the repo-wide `framer-motion` error is pre-existing) and `eslint` on `app/alumni`, `app/alumni-portal`, `StudentDashboard.tsx`, `ConditionalApp.tsx`: no findings.
- Not verified: anything on real MySQL beyond the 11 migrations already applied; real email delivery; production load.

## 11. Menu organization and navigation guide (2026-10-12)
- Guide: `docs/ALUMNI_MANAGEMENT_NAVIGATION_GUIDE.docx` (menu hierarchy, role matrix, per-role steps, workflow, sample data, test steps).
- Built: migration `2026_10_15_100000_reorder_alumni_menu_and_add_conversion` (workflow order for 17 screens, adds the missing Convert Passed-Out Students entry with Alumni List rights; **not applied to production**); `alumni:sample-data` now fills mentorship, jobs, events, surveys, messaging and donations (no receipts, nothing "sent") with `--remove`.
- Verified (SQLite): 204 alumni tests pass (201 + sample-data 2 + reorder 1), 23 security tests pass.
- Open: Alumni Coordinator profile does not exist in production; a browser suite for the sidebar as seen by each role has not been run.
