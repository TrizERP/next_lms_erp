# Security review remediation - 28 Oct 2026

Companion to `Security-Review-K12-ERP.pdf`. Everything below is **uncommitted** at the time of writing. The code
changes were tested with scripts and the PHPUnit / node / Flutter / Jest suites; none were exercised against a
running production stack. Treat this as a staging checklist.

## 1. Do these before (or with) the deploy - code cannot do them

1. **Rotate every secret that was ever in git**: `JWT_SECRET`, `APP_KEY`, DB passwords (including the `root` one),
   mail, Pusher, DigitalOcean Spaces, every AI provider key, the CAPTCHA bypass code, the live-chat DB password.
   Rotating without removing them from history leaves them readable.
2. **Remove them from git history** (`git filter-repo` / BFG) for `.env.bak-before-ai-provider`, the old `public/*.php`
   scripts and `lib/livechat/db.ts` (commit `0d3465dc`). This rewrites history and needs a force-push: coordinate.
3. **`public/library/`** (264 tracked files) still holds hard-coded DB credentials. Not touched.
4. Frontend: rename `NEXT_PUBLIC_SUPABASE_SERVICE_ROLE_KEY` to `SUPABASE_SERVICE_ROLE_KEY` (`.env.local` and Vercel).

## 2. Environment variables added or changed

| Variable | Where | Notes |
|---|---|---|
| `LEGACY_DB_HOST/USER/PASSWORD/NAME`, `LEGACY_DB_NAME_MAIN`, `LEGACY_LOCAL_DB_*`, `LEGACY_API_SECRET_KEY` | Laravel | The `public/*.php` scripts now read these (`bootstrap/legacy_env.php`). Unset = those scripts cannot connect. |
| `CRM_WHATSAPP_SECRET` | Laravel | CRM sends it as `X-CRM-Key`. Unset = `/api/crm-whatsapp*` refuse every call. |
| `WHATSAPP_WEBHOOK_SECRET` | Laravel | Provider callback gets `?secret=<value>`. **Unset = webhooks stay open** (and log a warning). |
| `OTP_REVIEW_NUMBERS`, `OTP_REVIEW_CODE` | Laravel | App-store review logins. Both empty = feature off. Set them if the stores' reviewers rely on the old fixed OTP. |
| `JWT_TTL_MINUTES` | Laravel | Default is now 43200 (30 days). Old tokens without `exp` still work until users log in again. |
| `LMS_API_AUTH_ENFORCE` | Laravel | Default now `true`. `false` is the emergency rollback. |
| `SESSION_ENCRYPT` | Laravel | Default now `true`. Everyone must log in again after the deploy. |
| `TRUSTED_PROXIES` | Laravel | Load balancer address(es) or `*`. Without it per-IP limits see one address. |
| `LIVE_CHAT_MEET_HOSTS` | Laravel | Default Meet, Zoom, Teams, Webex. |
| `MCP_SCHOOL_RATE_PER_MINUTE` / `_PER_DAY` | Laravel | School-wide AI caps, default 600 / 20000. |
| `CAPTCHA` | Laravel | Leave **empty** in production (it is a bypass code). |
| `APP_ENV=production`, `APP_DEBUG=false` | Laravel | `/api/testkey` only exists when `APP_ENV` is `local` or `testing`. |
| `LARAVEL_JWT_SECRET` | Vercel | **Required.** Without it, production now rejects every Next.js session token. |
| `LIVE_CHAT_DB_*` | Next.js | No longer needed: the Next.js live-chat routes proxy to Laravel. |
| `API_BASE_URL` (`--dart-define`) | Flutter / RN builds | There is still a dev-host default. Make sure the release script always sets it. |

## 3. One-off commands

```
# AI provider keys are now encrypted at rest. Back up the database first.
php artisan ai:encrypt-keys --dry-run
php artisan ai:encrypt-keys
# Before ever changing APP_KEY:
php artisan ai:encrypt-keys --decrypt
```

## 4. Things that behave differently after the deploy (test these with real logins)

- Anonymous `/chat`, `/geminiAI`, import, CKEditor and similar web routes now need a login. `/superAdmin*`,
  `/transferDocs`, `/convertDoc`, `/download-folder`, `/python_timetable` need a Super Admin.
- `/table_data` and `/lms_data` accept bearer tokens only, four tables only, own school only.
- `adminapi.php` / `teacherapi.php` need a staff token; the school comes from the token, not the request.
- Mobile student/parent APIs take the student/teacher id and school from the token. A parent with several children
  sending a sibling's id will now be pinned to the token's own student.
- MCP/AI: write tools and Fees/Payroll/Leave/StaffAttendance/StudentMedical tools are administrator-only.
- Recommendation approve/reject/defer are administrator-only.
- Student/parent tokens cannot use the staff APIs or Brain.
- Cookie-session writes to `api/*` must come from this site (Origin/Referer check).
- Uploads: any file with a server-executable extension or a `<?php` header is refused everywhere.
- Mobile apps need a new release to stop sending the token in GET query strings (old versions keep working).
- `Content-Security-Policy-Report-Only` is on; reports go to `/api/csp-report`. Tighten from them, then enforce.

## 5. Known gaps (not fixed, and why)

| Item | Why |
|---|---|
| **B2** passwords still stored plain text (staff) / MD5 (students) | The sidebar passes the stored password in the URL to the external Library, LMS and dailyLMS apps, which have no SSO token. Hashing the column breaks them. The web and JSON logins already accept all three formats. |
| **G2** token in `localStorage` | Needs an httpOnly cookie design across both repos. |
| **E4** write routes without a menu row are not rights-checked | Now logged hourly (`perm: write route has no menu row`). Use the log to map them, then fail closed. |
| **E2** `destroy()` guard covers routes where the table can be derived | Others have no destroy method, are empty stubs, or use tables without a school column. |
| **H3** certificate pinning, **H4** dev-host default | Depend on the release process. |
| **B7, B10, B13 logout verb, E22** | Design decisions, see the review notes in the conversation. |
| **I6** | Practice evidence reliability is capped at 0.6; it is still counted. |

## 6. Known test baselines (failing before these changes)

`McpSecurityStackTest::test_no_tool_accepts_a_tenant_argument`, `BrainRoleBridgeTest::test_a_teaching_profile_stays_read_only`,
ExamEvaluation `GeminiDocumentClientTest` / `QueueAndGeminiConfigTest`, AI `ModuleModelBindingIsolationTest`,
`AiConfigIntegrityTest`, `GeminiDailyQuotaFallbackTest`, Eso (4), CoursePresentation (14), and most of `tests/Unit`
(missing test-support classes).

## 7. Round 2 (after PR #966)

- `requirements` CRUD and `/task` now need a login; the school comes from the session. Platform-wide process
  documents (school 0, used by the add-process screen) can only be written by a Super Admin. The all-schools report
  `customers_requirement` is Super Admin only.
- Uploaded SVG files are cleaned in `BlockExecutableUploads` (scripts, event handlers, `javascript:` links,
  foreignObject, animation elements, DOCTYPE/entities removed); an SVG that cannot be parsed is refused with 422.
  HTML uploads are still allowed (LMS and H5P content needs them).
- Found with `php artisan routes:unmapped-writes` plus a read of the controller source: write routes with neither
  a declared guard nor any auth in the controller. Now login-gated: the `ai/*` generators and `generate-gamma-pdf`,
  `lms/api/teacher_resource/*`, `lms_chat`, `preview_distribution`, `get_search_url`, `lms/track-content-view`,
  `lms/update-forgetting-curve`, `neo4j/assessment`, blog create/update/delete (blog reading stays public),
  `map_student/bulk-delete`, and the Next.js `student/api/{student_certificate,student_icard,teacher_icard}/*`
  routes (staff only, bearer token). The public sign-up / onboarding posts (`signup`, `NewLMS_*`, `add-institute`,
  `preload-institute`, `admission_enquiry/payment_proof`) stay open on purpose and are limited to 20 per minute.
- `php artisan routes:unmapped-writes [--json] [--only=web|api]` is read only. It lists every write route whose name
  has no `tblmenumaster` row, grouped by what protects it. Map those routes, then make `checkPermission` fail closed
  (E4). Its "no login and no rights check" group reads route middleware only, so `api/*` entries there are usually
  covered by the `api` group guard or by the controller.
