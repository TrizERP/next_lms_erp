# RBAC gate on content creation (CONTENT_CREATE)

**Delivers:** Tracker sheet "Content & LMS Architecture" row 5 · "Decisions & Risk Log" #37, #23
**Date:** 2026-09-08 · **Phase:** A4
**Method:** built on the existing rights tables; verified against live `vivek_erp`

---

## What the tracker asked for

> Gate Generate/Upload buttons behind a standard RBAC permission, not a separate feature-flag system.
> *Action: use the same `permission_check(user, module, action)` mechanism as every other gated action in
> this tracker — do not build a parallel content-permissions system.*

## What we found first, and why it changes the shape of the work

### The content API had no authentication at all

`routes/api.php` is registered with only the `api` middleware group, which is `throttle:1000,1` +
`SubstituteBindings` (`app/Http/Kernel.php:45-50`; Sanctum is commented out). Nothing else. So
`POST /api/lms-chapter-content/upload` was reachable anonymously.

### The "role check" that existed was a client-supplied string — demonstrated, not inferred

Several content endpoints carry a guard shaped like this (`ApiLmsCourseController.php:698-704`):

```php
$user_profile_name = $request->input('user_profile_name') ?? session()->get('user_profile_name');
if (!in_array(strtoupper($user_profile_name), ['TEACHER', 'ADMIN'])) { return 403; }
```

The role is read from **request input**. Verified live, with no token of any kind:

| Request | Result |
|---|---|
| `POST /api/lms-chapters` with `user_profile_name=ADMIN` | **200** — returned 13 real chapters |
| `POST /api/lms-chapters` with `user_profile_name=STUDENT` | 403 |

The check works exactly as designed and provides no security: the caller declares their own role. This is the
same failure class as Decisions & Risk Log #2, reachable without touching the `type=API` bypass at all.

**Consequence for row 5:** gating a button behind a permission while the endpoint behind it accepts a
self-declared role would be decoration. Authentication had to come first.

## What was built

| File | Role |
|---|---|
| `config/rbac_modules.php` | Module registry: maps `lms.content` → the `tblmenumaster` rows that already hold its rights. Grants nothing. |
| `app/Services/Rbac/PermissionService.php` | `check(user, profile, tenant, module, action)` + `actionsFor(...)`. Reads `tblindividual_rights` then `tblgroupwise_rights`. |
| `app/Http/Middleware/LmsApiAuth.php` (`lms.auth`) | Requires a valid GenTux JWT. Section 1 of `PalApiAuth`, without its learner scoping. |
| `app/Http/Middleware/RequirePermission.php` (`perm`) | `->middleware('perm:lms.content,create')`. Tenant comes from the **token**, never request input. |
| `app/Http/Controllers/api/PermissionsController.php` | `GET /api/permissions?modules=` — per-action flags for the UI. |
| `lms_k12/app/hooks/usePermission.ts` | `usePermission('lms.content','create')`. |

**No new storage.** `tblgroupwise_rights` already carries this data — measured on menu 236
(`content_master.index`, "Add Content"): **89 profiles, can_view 89, can_add 87, can_edit 85, can_delete 80**.
The mechanism Decision #37 points at was already populated; it had simply never been read by a content screen.

Verified against those real rows:

```
lms.content -> menu ids [236, 270]
profile 2091 (inst 204) can_add=1 -> check(create) = true
profile 3203 (inst 245) can_add=0 -> check(create) = false
unknown profile 999999   -> check(create) = false   (fail-closed)
```

## Three things this fixes that `checkPermission` gets wrong

> **Citations corrected 2026-09-08.** Point 3 below described a bypass that the platform team **removed while
> this work was in progress**. The `type=API` guard is gone from `checkPermission`, and `SessionMiddleware` now
> requires a JWT for `type=API`/`JSON` requests. Points 1 and 2 remain true and were re-verified today. The
> `merge(['type' => 'API'])` call also lives in `Concerns/HydratesLegacyApiSession.php:147`, not
> `ApiSessionHydrator.php`. See `2026-09-08-decisions-log-verification-sweep.md` (#2) for the current state.

1. **It fails closed.** `checkPermission.php:71-73` **still** has its "no rights row at all" rejection
   commented out, so a user with no grant falls through to *allowed*. Here, absent means denied.
2. **No menu-id allowlists.** `checkPermission.php:78,82` **still** hardcodes `in_array($menu_id,[200])` and
   `in_array($menu_id,[31,82,386])` to skip delete/edit checks. Those ids differ per environment, and a config
   value granting a permission is a direct Decision #23 violation. Modules resolve by `tblmenumaster.link`.
3. **It takes an explicit identity, so no request flag can switch it off.** *(The specific `type=API` bypass
   this originally cited has since been fixed upstream — see the note above. The design point stands: this
   class is passed a user/profile/tenant rather than reading a request flag.)*

## A bug worth recording, because it failed silently and safely

`config("rbac_modules.modules.{$module}.links")` **always returns `[]`**. Module names contain a dot
(`lms.content`) and Laravel's `config()` treats dots as path separators, so the lookup resolves to
`modules → lms → content → links`, which does not exist.

Because the service fails closed, the symptom was not an error — it was **"nobody can create content"**, with
nothing in any log. Fixed by fetching the modules array and indexing it directly, and pinned by
`test_module_names_contain_dots_so_config_path_lookup_must_not_be_used`.

## Rollout: warn-only by default

`LMS_API_AUTH_ENFORCE` defaults to **false**. In that mode BOTH middlewares log and pass through — for a
tokenless caller *and* for an authenticated caller who lacks the grant.

> **Corrected 2026-09-08 after an independent audit.** The first version warn-skipped only when there was no
> verified identity, so a caller presenting a **valid JWT** was permission-checked for real while the flag
> still said warn-only. That was a live regression: the three legacy write routes returned **403** to any
> token-bearing client. Measured blast radius: 59 (profile, tenant) pairs hold rights on menu 270 with no row
> on menu 236 at all, and 30 of 148 menu-270 rows have `can_add=0`. The Next.js path was safe only by
> accident (it sends no `Authorization` header); a mobile/JWT client was not.
> Fixed, and pinned by `tests/Unit/RequirePermissionTest.php` (5 tests). Warn-only now also **logs exactly who
> would have been denied**, which is the go/no-go evidence for flipping the switch.

This is not caution for its own sake: **the entire content area of the Next.js frontend calls Laravel with a
bare `fetch()` and no `Authorization` header** (Convention A — `API_BASE_URL` + per-caller fetch). Enforcing on
day one would black out the content screens for 56 tenants.

Sequence to enforce:

1. Migrate the content calls in `chapters.ts` / `sideDrawer.tsx` onto `lib/erp-client.ts`
   (`buildSessionContext` + `createAuthHeaders`), which the PAL, fees and rights modules already use.
2. Deploy; watch the daily log for `lms.auth: unauthenticated content API request`.
3. Flip `LMS_API_AUTH_ENFORCE=true` only once that count is zero.

Verified in both modes:

| Mode | `GET /api/permissions` | `POST /lms-chapter-content/upload` |
|---|---|---|
| warn-only | 200, `authenticated:false` | passes through to the controller |
| enforce | **401** | **401** |

## Scope boundary

Applied **only** to the content write routes (`lms-chapter-content/upload`, `lms-create-content`,
`lms-store-content`) plus the new `/api/permissions`. This is deliberately not a fix for the platform-wide
auth surface, which belongs to Track D (Decisions & Risk Log #2, Central Engines rows 1-2). Widening this
middleware to `routes/api.php` would break ~170 other unauthenticated routes and collide with their work.

*Current state of that surface, re-verified 2026-09-08:* the `type=API` bypass has been fixed upstream, but
`checkPermission.php:71-73` (rejection still commented out), `:78`/`:82` (menu-id allowlists) and `:44`
(`$menu_id != ''` skip) remain — as does the client-supplied `user_profile_name` role on `routes/api.php`,
which still returns **200 with 1.48 MB of real data** to an unauthenticated caller claiming `ADMIN`.

When Track D's RBAC engine lands it should replace the **body** of `PermissionService::check()`. Every caller
goes through `check()` or the `perm:` middleware, and the frontend only ever calls
`usePermission(module, action)` — so that swap needs no caller changes on either side.

## On the 70/30 model

The tracker's premise is "70-80% use ready-made content, 20-30% need creation rights". Two measurements sit
awkwardly against it, and are worth stating before anyone tunes defaults:

- **Teacher-authored content is vanishingly rare: 2 rows of 96,479 (0.002%).** *(Corrected 2026-09-08 — this
  originally read "none exists", which was an artifact of a classifier bug, not a fact about the data. See
  `2026-09-07-content-ownership-provenance.md` §1.)*
- **87 of 89 profiles already hold `can_add`** on the content menu. The current grant distribution is closer to
  98/2 than 30/70.

Neither changes the design — the gate reads whatever the rights tables say, and tightening those is a data
decision, not a code one. But "turn the gate on" and "achieve the 70/30 split" are different tasks, and only
the first is engineering.

## Status recommendation

Row 5: **`Built — enforcement live behind a warn-only flag; blocked on the frontend token migration`**.

## How to reproduce

```bash
./vendor/bin/phpunit --filter PermissionServiceTest      # 11 tests, 27 assertions, no DB
```

```sql
SELECT id, name, link FROM tblmenumaster WHERE link IN ('content_master.index','course-master/') AND status = 1;
SELECT COUNT(*) profiles, SUM(can_view), SUM(can_add), SUM(can_edit), SUM(can_delete)
  FROM tblgroupwise_rights WHERE menu_id = 236;
```
