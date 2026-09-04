# LMS Leader Board & Social Collaborative — migration to LMS K12

Rebuild of the two legacy Blade modules

- `127.0.0.1:8000/lms/lmsLeaderboard`
- `127.0.0.1:8000/lms/lmsSocialCollabrotive`

as stateless REST APIs plus new Next.js pages in the K12 frontend.

**The old ERP is untouched.** No legacy controller, route, model, Blade view or
table definition was modified. The legacy screens keep working exactly as they
did; the new code lives beside them.

Repositories:

- Backend — `d:\next_lms_erp` (Laravel)
- Frontend — `d:\lms_k12` (Next.js App Router)

---

## 1. Old ERP analysis

### 1.1 Leader Board

| Concern | Legacy implementation |
| --- | --- |
| Route | `routes/lms.php:185` → `Route::resource('lmsLeaderboard', lmsLeaderboardController::class)` |
| Controller | `app/Http/Controllers/lms/lmsLeaderboardController.php` — only `index()` + `getData()` are implemented; `create/store/show/edit/update/destroy` are empty stubs |
| View | `resources/views/lms/show_lmsLeaderboard.blade.php` (Bootstrap cards: My Points, Medals, Class Rank, Points Details, Class Toppers) |
| Config module | `app/Http/Controllers/lms/leaderboard/lbMasterController.php` + `resources/views/lms/leaderboard/{add,show}_lbmaster.blade.php` |
| Models | `app/Models/lms/leaderboard/lb_pointsModel.php`, `lb_masterModel.php` |
| Tables | `lb_points` (earned ledger), `lb_master` (points config), joined to `tblstudent`, `tblstudent_enrollment`, `standard`, `academic_section` |
| Auth | Laravel web session (`sub_institute_id`, `user_id`, `user_profile_id`, `syear`), with request-parameter fallbacks added later for headless `type=API` calls |
| Filters / paging | None. Fixed top-5, current session year only |
| Writes | **None anywhere in the ERP.** `lb_points` is only ever read; no controller, job, observer or command inserts into it |

Business logic, as encoded:

1. Load the learner's `lb_points` rows for `(sub_institute_id, user_id, user_profile_id, syear)`, joined to `lb_master` on `module_name` + the learner's `standard_id` to pick up the display icon.
2. `total_points` = the sum of those rows. `modulewise_points[module][date]` = the per-day series.
3. Class ranking = `SUM(points)` grouped by `user_id` over everyone enrolled in the same `standard_id` for that `syear`, ordered DESC, `LIMIT 5`.
4. `student_rank` = `array_search(user_id, top5) + 1`.
5. The medal is the literal string "Bronze" hard-coded in the Blade — there is no tier logic anywhere.

### 1.2 Social & Collaborative

The feature is spread across three controllers:

| Concern | Legacy implementation |
| --- | --- |
| Feed route | `routes/lms.php:182` → `lmsSocialCollabrotiveController@index` |
| Feed view | `resources/views/lms/show_lmsSocialCollabrotivenew.blade.php` (comment-thread markup + a "Reply" modal) |
| Create doubt | `lmsDoubtController@create` (form) / `@store` (insert + upload to DigitalOcean Spaces `public/lms_doubts/`), view `resources/views/lms/add_doubt.blade.php` |
| Create comment | `lmsDoubtConversationController@store` |
| Compose lookups | `sub_std_map` (subjects for the learner's standard), `ajax_LMS_SubjectwiseChapter`, `ajax_LMS_ChapterwiseTopic` (both on `lmsPortfolioController`) |
| Models | `app/Models/lms/doubtModel.php`, `doubtConversationModel.php` |
| Tables | `lms_doubt`, `lms_doubt_conversation`, joined to `tblstudent`, `tblstudent_enrollment`, `standard`, `division`, `tbluser` |
| Update / delete | **Do not exist** — those resource methods are empty stubs |

Business logic, as encoded:

1. Everything is scoped to `(sub_institute_id, syear)`.
2. A **student** sees public doubts plus doubts raised inside their own class; **staff** see every doubt in the institute for that year.
3. A doubt carries subject / chapter / topic, a title auto-composed by the form as `"Subject / Chapter / Topic"`, an HTML description (Summernote), an optional attachment and `visibility` = `public|private`.
4. A conversation entry's author is either a student (shown with `standard/division`) or a staff user — the legacy code expressed that as a `UNION` of two near-identical queries.
5. Both students and staff may comment.

### 1.3 Defects found in the legacy code

These were reproduced faithfully in *intent* but fixed in the new implementation. Each is called out in the source docblocks.

| # | Where | Defect | New behaviour |
| --- | --- | --- | --- |
| L1 | Leader Board | `array_search($user, $top5) + 1` returns `false + 1 = 1`, so **every learner outside the top five is reported as rank #1** | true competition rank computed over the whole class; ties share a rank |
| L2 | Leader Board | the class query filters the enrollment year but not `lb_points.syear`, so it sums **all years** while "my points" sums one — the two figures can never agree | both pinned to the same `syear` |
| L3 | Leader Board | the enrollment join carries no year predicate, so a learner enrolled in several years multiplies their own ledger rows | enrollment de-duplicated per year |
| L4 | Leader Board | `lb_master` is INNER JOINed, silently voiding points whose module has no master row — even though the class ranking counts those same points | LEFT JOIN; a missing config row no longer erases earned points |
| S1 | Social | `->where(institute, syear)->where(visibility)->orWhere(standard)` — Laravel's flat precedence lets the trailing `orWhere` escape the tenant scope, **leaking other schools' doubts into the feed** | the visibility test is a grouped sub-clause nested inside the tenant scope |
| S2 | Social | the conversation join compares `se.standard_id` to the *string* `'l.standard_id'`, a column that does not exist on `lms_doubt_conversation`, so the student half of the UNION matches nothing and **student comments are invisible** | authors resolved from both tables in bulk |
| S3 | Social | the feed INNER JOINs `tblstudent`, so a doubt raised by a staff member disappears from the list | both author sources resolved |
| S4 | Social | the conversation is fetched with one query per doubt (N+1) | one grouped count per page + bulk author resolution |

---

## 2. Old → new mapping

```
lms/lmsLeaderboard  (Blade, session)          lms/lmsSocialCollabrotive (Blade, session)
        │                                              │
        ▼                                              ▼
LmsLeaderboardService                        LmsSocialCollaborativeService
        │                                              │
        ▼                                              ▼
LmsLeaderboardApiController                  LmsSocialCollaborativeApiController
        │            (api.session — JWT)               │
        ▼                                              ▼
GET /api/lms/leaderboard*                    GET|POST /api/lms/social-collaborative*
        │                                              │
        ▼                                              ▼
app/lms/data/leaderBoard.ts                  app/lms/data/socialCollaborative.ts
        │                                              │
        ▼                                              ▼
/lms/leader-board                            /lms/social-collaborative
```

The legacy controllers are **not** called, extended, wrapped or imported.

---

## 3. Database

Every table already existed. **No table was created, no column added, no data
migrated or rewritten.**

| Table | Role | Key columns | Rows found |
| --- | --- | --- | --- |
| `lb_master` | points configuration per (grade, standard, module) | `grade_id`, `standard_id`, `module_name`, `per_value`, `points`, `icon`, `status`, `sub_institute_id` | 5 |
| `lb_points` | earned-points ledger | `user_id`, `user_profile_id`, `sub_institute_id`, `syear`, `inserted_date`, `module_name`, `points` | 2 |
| `lms_doubt` | the post | `subject_id`, `chapter_id`, `topic_id`, `title`, `description`, `file_name`, `visibility`, `user_id`, `sub_institute_id`, `syear` | 3 |
| `lms_doubt_conversation` | the replies | `doubt_id`, `message`, `user_id`, `user_profile_id`, `sub_institute_id`, `syear` | 24 |

Supporting (read-only): `tblstudent`, `tblstudent_enrollment`, `standard`,
`division`, `tbluser`, `sub_std_map`, `chapter_master`, `topic_master`,
`school_setup`, `academic_year`, `tblmenumaster`, `tblgroupwise_rights`.

### 3.1 Migration (indexes only)

`database/migrations/2026_09_04_100000_add_lms_engagement_indexes.php`

Purely additive: these four tables have carried no index beyond their primary
key since 2021, while every new query filters on `(sub_institute_id, syear)`.
Both `up()` and `down()` consult `information_schema` first, so re-running is a
no-op rather than an error.

**Not executed** — it is left for the team to run against the production
database:

```bash
php artisan migrate --path=database/migrations/2026_09_04_100000_add_lms_engagement_indexes.php
```

---

## 4. Data initialization

`php artisan lms:engagement-init --institute=<id> [--standard=<id>…] [--grant-menu-rights] [--dry-run]`
(`app/Console/Commands/LMS/LmsEngagementInitCommand.php`)

1. **Leader-board configuration.** The board can only attribute points to a
   module that has an `lb_master` row for the learner's standard, so an
   institute with no configuration shows an empty board. The command inserts
   the four supported modules — `login` (10), `exampass` (20, `per_value` 50),
   `examfail` (−10, `per_value` 50), `homework` (10) — for every standard of
   the institute that is missing them, using the same values the reference
   institute has carried since 2021.
2. **`--grant-menu-rights`** (opt-in) grants `can_view` on the two menu rows
   that already exist in `tblmenumaster` — 290 "Leader Board" and 463
   "Social & Collabrotive" — to exactly the profiles that already hold rights
   on that institute's LMS root (menu 230/277). No role gains access it did not
   already have to the LMS; if no profile holds LMS rights, the command refuses
   rather than over-granting.

Idempotence: every insert is guarded by an existence check on its natural key,
so re-running creates nothing. Verified — a dry run against standard 39 (already
configured) reports `0 rows`, while the institute's other 12 standards report 44.

**No points were fabricated.** `lb_points` is left exactly as found: nothing in
the ERP has ever written to it (see §8), and inventing ledger rows would put
fictional scores in front of real learners.

---

## 5. API

All endpoints sit behind the project's existing `api.session` middleware: it
validates the ERP bearer JWT and hydrates the request session from the verified
payload. **Tenant, user and academic year are read from that session only, never
from request parameters**, so a caller cannot act as another user, institute or
year. `syear` / `term_id` may be passed to switch academic year, exactly like the
existing header year switcher.

Envelope (matches `api\result\BaseResultApiController`):

```json
{ "success": true, "message": "…", "data": …, "errors": null, "meta": { … } }
```

### 5.1 Leader Board

| Method | Endpoint | Notes |
| --- | --- | --- |
| GET | `/api/lms/leaderboard` | my summary; `top_limit` (default 5, max 25) |
| GET | `/api/lms/leaderboard/filters` | years / classes / modules that actually have data |
| GET | `/api/lms/leaderboard/rankings` | full class ranking; `standard_id`, `section_id`, `module_name`, `from`, `to`, `page`, `per_page`. A student is always pinned to their own class; staff who send no `standard_id` get the institute-wide board for the year rather than an empty screen |
| GET | `/api/lms/leaderboard/{userId}` | one learner (self, or a learner inside the caller's institute) |

```jsonc
// GET /api/lms/leaderboard?syear=2021&term_id=1
{
  "success": true,
  "message": "Leader board fetched successfully.",
  "data": {
    "has_data": true,
    "syear": 2021,
    "learner": {
      "user_id": 97382, "standard_id": 39, "standard_name": "6",
      "section_id": 11, "section_name": "C",
      "total_points": 20, "rank": 1, "class_size": 1, "medal": "Bronze"
    },
    "modules": [
      { "module_name": "login", "label": "Login", "icon": "xf005",
        "description": "Login Points", "points": 20,
        "entries": [ { "date": "2021-06-10", "points": 10 },
                     { "date": "2021-09-03", "points": 10 } ] }
    ],
    "toppers": [
      { "rank": 1, "user_id": 97382, "student_name": "EVAAN RAJESH RAFALIYA",
        "total_points": 20, "avatar_url": "…/storage/student/97382_1.jpg",
        "is_current_user": true }
    ]
  },
  "errors": null
}
```

### 5.2 Social & Collaborative

| Method | Endpoint | Notes |
| --- | --- | --- |
| GET | `/api/lms/social-collaborative` | feed; `search`, `subject_id`, `chapter_id`, `visibility`, `mine`, `page`, `per_page` |
| GET | `/api/lms/social-collaborative/{id}` | one thread + every reply |
| POST | `/api/lms/social-collaborative` | `title`*, `visibility`* (`public|private`), `description`, `subject_id`, `chapter_id`, `topic_id`, `file` (≤10 MB) |
| POST | `/api/lms/social-collaborative/{id}/comments` | `message`* |
| GET | `/api/lms/social-collaborative/lookups/subjects` | `standard_id` (staff); a student always gets their own class |
| GET | `/api/lms/social-collaborative/lookups/chapters` | `subject_id`*, `standard_id` |
| GET | `/api/lms/social-collaborative/lookups/topics` | `chapter_id`* |

```jsonc
// GET /api/lms/social-collaborative?syear=2021&per_page=2
{
  "success": true,
  "message": "Discussion feed fetched successfully.",
  "data": [
    {
      "id": 3,
      "title": "Science / Food: Where Does It Come From? / Food Variety",
      "description": "What to learn from Food Chapter<p><br></p>",
      "visibility": "public",
      "subject_id": 3975, "chapter_id": 26, "topic_id": 674,
      "attachment_url": null,
      "created_at": "2021-06-01 15:34:24",
      "total_days": 1921,
      "comment_count": 5,
      "author": { "user_id": 97382, "name": "EVAAN RAJESH RAFALIYA",
                  "type": "student", "class": "6/C", "avatar_url": "…" }
    }
  ],
  "errors": null,
  "meta": { "current_page": 1, "per_page": 2, "total": 3, "last_page": 2 }
}
```

Error shape:

```json
{ "success": false, "message": "Validation failed", "data": null,
  "errors": { "title": ["The title field is required."] } }
```

**There is deliberately no PUT or DELETE.** The legacy module has no edit or
delete path for a doubt or a comment, and adding one would introduce ownership
and moderation rules the ERP has never had.

---

## 6. Authentication & authorization

- Mechanism: the ERP's existing JWT (`POST /api/api-login` → `user_token`), sent as `Authorization: Bearer …`. No new auth system.
- `api.session` (`App\Http\Middleware\ApiSessionHydrator`) verifies the token and hydrates the session; a missing or invalid token returns `401`.
- **Leader Board**: a student may only read their own summary (`403` otherwise) and only their own class's ranking — a `standard_id`/`section_id` sent by a student is discarded server-side. Staff and admins are confined to their institute; `{userId}` must resolve to a learner of that institute, else `404`.
- **Social**: reads are institute + year scoped for everyone. A student additionally sees only public doubts, their own, and doubts raised by learners of their own class. Commenting requires that the doubt is visible to the caller (`404` otherwise). Author identity on every write comes from the token, never the body.

---

## 7. Frontend

| Route | File |
| --- | --- |
| `/lms/leader-board` | `app/lms/leader-board/page.tsx` (rewritten — it previously scraped the legacy Blade endpoint) |
| `/lms/social-collaborative` | `app/lms/social-collaborative/page.tsx` (new) |

Data layers: `app/lms/data/leaderBoard.ts` (rewritten to the new API) and
`app/lms/data/socialCollaborative.ts` (new). Both use the project's existing
`lib/erp-client` session/auth helpers — `buildSessionContext()` +
`createAuthHeaders()` — with no new HTTP client and no hard-coded data.

Sidebar: `app/data/routeMapper.ts` maps the `tblmenumaster` links
`lmsSocialCollabrotive.index` (the legacy misspelling stored in the database)
and `lms/lmsSocialCollabrotive` — plus the corrected spellings — to
`/lms/social-collaborative`. The Leader Board entries were already mapped.

UI (new design; nothing from the Bootstrap original was reused):

- **Leader board** — stat cards (points / rank / medal), a three-place podium, a "how I earned my points" breakdown with per-module share bars, and a paginated class ranking with class and activity filters plus an academic-year switcher. All filters are backed by real columns; none are decorative.
- **Social & collaborative** — a feed of discussion cards with author, class chip, visibility badge, relative time, attachment link and an inline expandable reply thread with a composer; debounced search, visibility filter, "only mine" toggle, "load more" paging, and a compose dialog whose subject → chapter → topic cascade auto-composes the title exactly as the ERP form does.
- Every surface has a skeleton loading state, a designed empty state ("No leader board data available" / "No collaborative activity yet") and a retryable error banner. Raw backend errors are never shown — a `401` is rewritten to "Your session has expired."
- Responsive (single column on mobile, grid from `sm`/`lg`), keyboard-reachable controls, `aria-label`s on icon-only buttons, `role="alert"` on error regions.

TypeScript: full interfaces for every payload (`LeaderBoard`, `LbModule`,
`LbRanking`, `LbRankingPage`, `LbFilterOptions`, `ScPost`, `ScComment`,
`ScAuthor`, `ScFeedPage`, `ScOption`, `ScNewPost`). No `any`.

---

## 8. Known limitations / assumptions

1. **Nothing awards points.** Verified across the whole repository: `lb_points`
   is only ever read — no controller, command, job or observer inserts into it,
   and the `access_log` table that could have back-filled login points is empty.
   The board therefore reflects whatever an external process has written. Award
   logic is a separate product decision and was not invented here.
2. **The medal is a constant.** The legacy Blade prints a literal "Bronze"; no
   tier thresholds exist in the ERP. The field is preserved as-is rather than
   inventing a tiering rule.
3. **The only real data is from 2021** (institute 1, `syear` 2021). The new UI
   ships an academic-year switcher so that data is reachable; under the current
   year both modules correctly show their empty state.
4. **No edit/delete** for posts or comments — see §5.2.
5. Comment authors whose user record no longer exists render as "Unknown user"
   rather than being dropped (one such row exists: user 18009).
6. The Social module has no year switcher: the legacy module is inherently
   "current academic year", and that behaviour is kept.

---

## 9. Testing performed

Backend — an end-to-end probe booted the real HTTP kernel, minted real JWTs for
a student (97382) and a staff user (6956), and dispatched requests through the
full middleware stack against the live database. Every write ran inside a
transaction that was rolled back; row counts before and after were identical
(`lms_doubt` 3, `lms_doubt_conversation` 24).

| Case | Result |
| --- | --- |
| No token / garbage token | `401` with the standard envelope |
| Student summary | `200` — 20 points, module `login`, class 6/C, rank 1 (matches the legacy figures) |
| Filters | `200` — years `[2021]`, class 6, four modules |
| Rankings + pagination meta | `200` |
| Student reading another learner | `403` "You can only view your own leader board." |
| Staff reading a learner | `200` |
| Year with no data (2022) | `200`, `has_data: false` (empty state, not an error) |
| Invalid `standard_id=abc` | `422` with a field-level error |
| Student feed / staff feed / page 2 / search | `200`, correct rows |
| Single thread + conversation | `200` — student **and** staff comment authors both resolve (legacy bug S2) |
| Missing doubt id | `404` |
| Subjects lookup | `200` |
| Chapters lookup without `subject_id` | `422` |
| Create with empty body | `422` (`title`, `visibility`) |
| Create doubt | `201`, author resolved from the token |
| Comment as staff on that doubt | `201` |
| Re-read the new thread | `200`, `comment_count: 1` |

Command — `lms:engagement-init --institute=1 --dry-run` reports 44 missing rows
across 12 standards and **0** for standard 39, which is already configured
(idempotence).

Frontend — `tsc --noEmit` clean across the repo; `eslint` clean on all changed
files; both routes compile and serve `200` from the dev server with no compile
errors in the Turbopack log.

Not covered: browser-level interaction testing of the two pages against a live
signed-in session (no automated browser harness is set up in this repo).

---

## 10. Files changed

### Backend (`d:\next_lms_erp`) — created

- `app/Services/lms/LmsLeaderboardService.php`
- `app/Services/lms/LmsSocialCollaborativeService.php`
- `app/Http/Controllers/api/lms/BaseLmsEngagementApiController.php`
- `app/Http/Controllers/api/lms/LmsLeaderboardApiController.php`
- `app/Http/Controllers/api/lms/LmsSocialCollaborativeApiController.php`
- `app/Console/Commands/LMS/LmsEngagementInitCommand.php`
- `database/migrations/2026_09_04_100000_add_lms_engagement_indexes.php`
- `docs/LMS_LEADERBOARD_SOCIAL_COLLABORATIVE_MIGRATION.md` (this file)

### Backend — modified

- `routes/api.php` — one new `api.session` group (`api/lms/leaderboard*`, `api/lms/social-collaborative*`). Nothing existing was moved or renamed.

### Frontend (`d:\lms_k12`) — created

- `app/lms/social-collaborative/page.tsx`
- `app/lms/data/socialCollaborative.ts`

### Frontend — modified

- `app/lms/leader-board/page.tsx` — rewritten against the new API
- `app/lms/data/leaderBoard.ts` — rewritten against the new API
- `app/data/routeMapper.ts` — four Social & Collaborative link variants added
