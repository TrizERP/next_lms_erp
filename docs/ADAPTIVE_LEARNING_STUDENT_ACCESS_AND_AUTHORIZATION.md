# Adaptive Learning — Student Access & Authorization

Business rule implemented this pass: **Adaptive Learning is a learner-facing feature. Only the
enrolled student's own authenticated session may start or advance their Adaptive Learning
session.** A teacher/staff/admin may continue to *view* a student's progress where existing
permissions already allow it, but can never act as the learner. Enforced on both the frontend
(the entry point is not offered) and the backend (every start/advance endpoint independently
rejects any non-student caller, and any learner id that isn't the caller's own) — the backend is
the real boundary; the frontend change alone would not have been sufficient.

## 1. Who can access Adaptive Learning

| Caller | Chapter/concept discovery | Start/advance a session (diagnostic, practice, attempt, retrieval, Pal render) | Read decision-log / progress reporting |
|---|---|---|---|
| The enrolled student, their own session | Yes | Yes, own learner id only | Yes, own record |
| A different student | Yes | No (403) | No, unless their own record (403 otherwise) |
| Teacher / staff (plain, non-admin-profile) | Yes | **No (403), even for a student in their assigned classes** | Yes, for students in their assigned classes (existing scope, unchanged) |
| Institute admin | Yes | **No (403), even within their own institute** | Yes, for students in their institute (existing scope, unchanged) |
| Client-level / multi-client super admin | Yes | **No (403), unconditionally** | Yes, per existing client/global scope (unchanged) |

## 2. Student journey

`/login` (real credentials) → `/dashboard` → **LMS + PAL → Test → PAL** (`/pal`) → the student
sees their own PAL directly (no student picker — that only appears for staff) → expand a subject
→ find the chapter → click **"Adaptive learning"** → **"Start Adaptive Learning"** modal → pick a
concept → `/pal/eso?conceptId=114` (no `learnerId` in the URL at all) → the real D1-D5 flow runs,
scoped to the authenticated student throughout.

## 3. Teacher behavior

- The **"Adaptive learning"** button no longer renders on any chapter row for a staff/admin
  session — `app/pal/page.tsx`'s `ChapterRow` only mounts `AdaptiveLearningButton` when
  `!isStaff` (the same `isStudentSession()`-derived flag that already gates the existing
  `StudentPicker`/`ViewAsBanner`).
- If a teacher opens `/pal/eso?conceptId=...` directly (typed, bookmarked, or via a stale link),
  the page loads and immediately shows the backend's denial message — it never reaches the
  diagnostic or any other learner-state screen.
- Every other existing teacher-facing control on the same row (**Take diagnostic**, **Start
  quiz**, **Suggested content**, **Misconception**, Practice review) is untouched and continues
  to work exactly as before — this task only ever removed/gated the one Adaptive Learning entry
  point.

## 4. Admin/staff behavior

Identical to teacher behavior — institute admins and the multi-client super admin are blocked
from starting/advancing a session exactly the same as plain staff. There is currently no separate
"read-only monitoring" execution mode; if one is wanted in the future it would need to be a
distinct, explicitly-named feature/endpoint, not a side door on the student execution routes.

## 5. Learner identity source

The learner is **always** derived from the authenticated JWT (`pal_auth.user_id`, set by
`PalApiAuth` from the token's `id` claim — which for a student login is `tblstudent.id`, the
exact id space every ESO route's `{learnerId}` already uses). The real student entry point
(`AdaptiveLearningButton`) never puts a learner id in the URL it builds. A `?learnerId=` query
parameter is read by `app/pal/eso/page.tsx` only for backward compatibility with old links — it
is never authoritative; the backend independently validates or rejects it regardless of what the
client supplies.

## 6. URL behavior

- **Student-facing URL:** `/pal/eso?conceptId=114` — no learner id.
- An old-style `?conceptId=114&learnerId=283919` link still works for the student who actually
  owns learner id 283919, and is rejected for anyone else, exactly as if the parameter had never
  been there — because the backend derives the real learner from the JWT and only uses a supplied
  `learnerId`/`learner_id` as a secondary equality check.

## 7. Backend authorization

Two layers, both required:

1. **`pal.auth` (`App\Http\Middleware\PalApiAuth`, unchanged this pass)** — central JWT
   authentication for every `api/pal/*` route, plus general per-learner tenant/ownership scoping
   (a student may only ever touch their own record; staff/admins are scoped to their
   institute/class). This is shared by all PAL V4 features and was **not modified**, so every
   existing reporting feature (Misconceptions, Pedagogy Engine, etc.) is unaffected.
2. **`eso.student` (`App\Http\Middleware\EsoStudentOnlyAuth`, new this pass)** — applied only to
   the ESO *start/advance* route group. Reads the `pal_auth` attribute `pal.auth` already
   populated (never trusts the client) and:
   - Denies (403) any caller whose `role` is not `student` — regardless of institute/class scope,
     admin tier, or how legitimately that caller could otherwise read the same student's data.
   - Re-asserts, defensively, that a student caller's own id matches the route/body learner id
     (already guaranteed by `pal.auth`, but this is exactly the boundary under scrutiny, so it is
     asserted explicitly rather than assumed).

## 8. API ownership checks — full endpoint audit

| Endpoint | Learner-scoped? | Group | Notes |
|---|---|---|---|
| `GET /chapter-concepts/{chapterId}` | No | — | Chapter-level content list, not learner state; `pal.auth` only |
| `GET /diagnostic/{learnerId}/{conceptId}` | Yes | **eso.student** | Serves diagnostic items |
| `POST /diagnostic/{learnerId}/{conceptId}/submit` | Yes | **eso.student** | Writes `learner_node_state` |
| `GET /next-action/{learnerId}/{conceptId}` | Yes | **eso.student** | The resolver — decides what the student sees next |
| `GET /practice-item/{learnerId}/{nodeId}` | Yes | **eso.student** | Serves a practice question |
| `POST /practice/{learnerId}/{nodeId}/attempt` | Yes | **eso.student** | Writes `learner_node_state`, may flag a misconception |
| `GET /retrieval-items/{learnerId}/{nodeId}` | Yes | **eso.student** | Serves a D5 retention check |
| `POST /retrieval/{learnerId}/{nodeId}/check` | Yes | **eso.student** | Writes retained/reloop state |
| `GET /due-for-retrieval/{learnerId}` | Yes | **eso.student** | Lists a learner's due nodes |
| `POST /render` (`learner_id` in body) | Yes | **eso.student** | The one LLM call — Pal phrasing for the active session |
| `GET /decision-log/{learnerId}/{conceptId}` | Yes | `pal.auth` only | Explicitly "the plain-language audit trace for parents/teachers" — read-only reporting, preserved |
| `GET /pilot/metrics` | No (aggregate) | `pal.auth` only | Existing staff/admin-only aggregate reporting endpoint, unaffected |

Every route that reads or writes `learner_node_state`, `eso_decision_log`, mastery, attempts,
misconception state, or retrieval state is in the `eso.student` group. A student can never modify
another student's data (blocked by `pal.auth`'s existing ownership check, confirmed by test); a
teacher/staff/admin can never modify *any* student's data through these routes (blocked by
`eso.student`, new this pass).

## 9. E2E tests

- **PHPUnit** (`tests/Feature/Eso/EsoAuthorizationTest.php`, real HTTP + real middleware stack, no mocks):
  13/13 pass, including new coverage — staff denied on `next-action`/diagnostic-submit/attempt
  even within their own institute, a client-level super-admin (`is_admin=2`) denied unconditionally,
  and `decision-log` explicitly confirmed to still work for staff (both allowed in-institute and
  denied cross-institute) to prove reporting was preserved.
- **Playwright** (`student_only_auth_e2e.mjs`, real Next.js UI + real Laravel API + real DB, no
  mocks): 13/13 pass —
  1. A genuine student session sees the Adaptive Learning entry point and starts their own
     session; the resulting URL carries only `conceptId`.
  2. A second authenticated student cannot reach learner id 283919's session by editing the URL
     (UI shows the rejection; API returns 403).
  3. A staff/admin session has zero "Adaptive learning" buttons anywhere on the chapter list.
  4. The same staff/admin session opening the ESO URL directly is denied by both the UI and the
     real API (403).
  5. Existing, unrelated teacher functionality (the "Take diagnostic" button) still renders for
     staff, and staff can still read a student's decision-log (200) — reporting preserved.

## 10. Expected 403 behavior

- Missing/invalid token → 401 (unchanged, `pal.auth`).
- Authenticated student, wrong learner id → 403, `"You can only access your own PAL data."`
  (`pal.auth`) or `"You can only access your own Adaptive Learning session."` (`eso.student`,
  redundant defense-in-depth on the same routes).
- Authenticated staff/admin/super-admin on any start/advance route → 403, `"Adaptive Learning
  sessions can only be started by the enrolled student's own account."` (`eso.student`).
- Authenticated staff/admin on `decision-log`/`chapter-concepts`/`pilot/metrics` → unaffected,
  governed only by `pal.auth`'s existing institute/class scoping as before.

## 11. Existing reporting exceptions

`decision-log`, `chapter-concepts`, and `pilot/metrics` are the three ESO endpoints deliberately
left outside `eso.student` — they are chapter-level content listing or explicitly-designed
teacher/parent-facing reporting, not a way to act as the learner. Every other PAL V4 feature
(Misconceptions modal, Pedagogy Engine, Practice review, quiz attempts review) is unrelated to
this route file entirely and was not touched.

## Files changed

- `app/Http/Middleware/EsoStudentOnlyAuth.php` — new middleware.
- `app/Http/Kernel.php` — registers the `eso.student` alias.
- `routes/pal_eso_api.php` — splits the ESO routes into a reporting group (`pal.auth` only) and a
  start/advance group (`pal.auth` + `eso.student`).
- `tests/Feature/Eso/EsoAuthorizationTest.php` — one test flipped (staff no longer succeeds on
  `next-action` within their own institute), five new tests added.
- `d:/lms_k12/app/pal/page.tsx` — `AdaptiveLearningButton` is only rendered when `!isStaff`.
- `d:/lms_k12/app/pal/_components/AdaptiveLearningButton.tsx` — drops the `studentId` prop and the
  `learnerId` query parameter entirely; the student URL is `/pal/eso?conceptId={id}` only.
- `d:/lms_k12/app/pal/eso/page.tsx` — comment updated to reflect that `?learnerId=` is now
  backward-compatibility-only, never authoritative.

D1-D5 policy, mastery thresholds, misconception rules, question tagging, Chapter 1014 content,
and pilot metrics logic were **not modified**.
