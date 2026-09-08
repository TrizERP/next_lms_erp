# Frozen decisions, audited against the code

**Covers:** the 53 `Frozen` rows of the "Decisions & Risk Log" sheet
**Date:** 2026-09-08 · **Phase:** B2

A `Frozen` status means *decided, do not re-litigate* — not *implemented*. About 21 of the 53 make a claim
these two repos can be checked against. This is that check: **compliant**, **violated**, or **gap**, with
evidence.

---

## Summary

| Verdict | Rows |
|---|---|
| ✅ **Compliant** | #7, #9, #13, #35, #36, #37, #57, #59, #61, #62 |
| ❌ **Violated** | **#23** |
| ⚠️ **Gap — decided but not built** | #52, #55, #67, #68 |
| 🔄 **The row's own facts have drifted** | **#54**, #56 |
| 📋 **Applies to our own work** | #58 |

---

## ❌ #23 — Configuration can never grant a permission · **VIOLATED**

> "Configuration UI must never be capable of bypassing or expanding what RBAC allows."

`app/Http/Middleware/checkPermission.php`:

```php
:78   if ((str_contains(request()->path(), 'delete') || ...) && $can_delete != 1 && !in_array($menu_id,[200]))
:82   elseif ((str_contains(request()->path(), 'update')) && $can_edit != 1 && !in_array($menu_id,[31,82,386]))
```

Menu **200** may always delete. Menus **31, 82, 386** may always edit — **regardless of what
`tblgroupwise_rights` says**. That is a hardcoded configuration value granting a permission RBAC withheld,
which is the literal thing this decision forbids.

Two compounding problems:
- **Menu ids are environment-specific.** The same literal points at different menus in different databases.
- **`:44` `if ($menu_id != '')`** — any route with no `tblmenumaster` row skips the permission block entirely.
- **`:71-73`** — the "no rights row at all" rejection is still commented out, so an ungranted user falls
  through to *allowed*.

**Report, do not fix.** This middleware is load-bearing for every Blade module across 56 tenants; removing the
allowlists could lock real users out of screens they use daily. Track D's, tied to #2.

*Our own work complies:* `config/rbac_modules.php` names modules only, resolves by `tblmenumaster.link`
(never a hardcoded id), and every grant lives in the rights tables. Pinned by
`test_the_registry_names_modules_but_grants_nothing`.

---

## 🔄 #54 — "Live repo audit conducted" · **one of its six claims is now false**

This row asserts a code audit upgraded six items "from hypothesis to confirmed fact". Re-checking it is the
cheapest way to judge how much of the sheet still holds.

| Claim | Verdict |
|---|---|
| KASBA duplication **with dead code** — "endpoints exist but NOTHING CALLED THEM — measured, zero callers" | ❌ **REFUTED** |
| Framework routes duplicated across 4 folders | ✅ confirmed (6 files, 2 dead stubs) |
| Leaderboard duplicated: LMS vs PAL Gamification | ✅ confirmed |
| ESO exists but lacks nav visibility | ✅ confirmed |
| Learning Path is a genuine gap | ✅ confirmed |
| Conversational AI already correctly centralised | ✅ confirmed |

**The refutation:** `app/talent-management/_components/kasba-rating-panel.tsx` is **live, not dead**:

```
:85    import { KasbaRatingPanel } from '../../_components/kasba-rating-panel'
:440   <KasbaRatingPanel userId={Number(effectiveUserId)} />
```

— in `app/talent-management/employee-profiles/components/employee-profiles-center.tsx`, imported and
**rendered with a real prop**. Either it was wired up after that audit, or the original measurement was wrong.
Either way, **"remove it, nothing calls it" is now the wrong action** and would break a live screen.

The other five hold:
- **Leaderboard duplication:** `app/lms/leader-board`, `app/lms/leader-board-master`,
  `app/lms/social-collaborative` **and** `app/pal/new/gamification/{challenge-mode,team-challenges}`.
- **Conversational AI centralised:** 24 files import `@shared/conversational-ai-core`, and there is exactly
  **one** `ChatbotPanel.tsx`. This remains the reference pattern.
- **Learning Path:** no dedicated route anywhere. Genuine gap.

---

## ✅ Compliant

**#7 — audit logging and transaction-safe writes shipped together.**
`ContentAuthoringService.php:97` wraps content insert + provenance write in one `DB::transaction`, and the
same class writes 3 audit-row updates. Neither shipped before the other.

**#9 — new engine gate (passing tests AND wired into a real flow).**
10 new service classes, 4 dedicated unit test files (34 tests among them), 5 routes registered. Nothing was
started before the previous piece was tested and wired.

**#13 — RBAC cache 0-30s or none.** `config/rbac_modules.php:94` → `'cache_seconds' => 0`. Per-request
memoisation only; no cross-request cache. A stale "yes you're allowed" is impossible.

**#35 / #36 / #37** — discharged by Sheet A (H5P as a format tag; one authoring capability; ownership +
RBAC-gated creation). See the A2/A3/A1/A4 memos.

**#57 — PAL → LearnSense rename NOT executed.** `grep -rn 'LearnSense'` → **0 occurrences**. Correctly on
hold until adaptivity is real, which — given #50 — it is not.

**#59 — Evidence & Context Engine stays Track D's.** Our A5 projection names it as the intended successor in
its own migration docblock and carries no learner linkage, no write API, no cross-module ingestion.

**#61 — ESO stays PAL-only; no Decision Engine built.** `app/Services/Eso/` holds 6 PAL-scoped classes
(`EsoPalRenderer`, `EsoLearningContentResolver`, …). `FeesEso` / `CareerEso` / `TeacherEso` references: **0**.
`class DecisionEngine`: **0**. Nothing was generalised prematurely.

**#62 — "Student Resources" → "Learning Resources".** `'Student Resources'` → **0 occurrences**;
`'Learning Resources'` → 2. Rename complete.

---

## ⚠️ Gaps — decided, not built

**#52 — content versioning by curriculum + academic year. NOT BUILT.**

| Table | `syear` | version / curriculum column |
|---|---|---|
| `content_master` | yes | **none** |
| `lms_question_master` | **no** | **none** |

(`cross_curriculum_grade_topic` is a topic-mapping field, not a version.) The decision's own rationale —
*"a board syllabus revision creates a new version instead of corrupting historical mappings"* — is exactly
what cannot happen today: a revision overwrites. And `lms_question_master` has no academic year at all, so
62,487 questions cannot even be scoped to a year. The row says "added before scale"; the scale already
arrived.

**#55 — AI stack as a callable service. Partly mis-stated, and not done.**
`packages/conversational-ai-core` **is not an npm workspace package** — `lms_k12/package.json` has no
`workspaces` key and the package has no `package.json`. It is wired by a `tsconfig` path alias, i.e.
compile-time source inlining. It *does* already have an HTTP surface (6 Next.js route handlers). So the work
is **"give it a package boundary and cut the lms-k12 adapter coupling"**, not "expose it". Sized, not built.

**#67 — confidence must be 3 scores, not 1.** Our own A5 projection carries a single blended `confidence`
column — precisely what this row says to replace with Intelligence / Evidence / Capability confidence.
Partial compliance; the column is additive so splitting it later is cheap, but it is a known gap and should
not be discovered by someone else.

**#68 — universal 10-field schema for every AI-generated claim.** The projection covers **4 of 10**: Claim
(`item_label`), Source (`source_table`/`semantic_id`), Provenance, Confidence. Missing: Confidence basis,
Evidence gaps, Last validated, Reviewer status, Decision impact.

---

## 🔄 #56 — "built but inert" — add a fifth instance

The row names four: Assessment Bank, Pedagogy Engine, KASBA, Leaderboard. Sheet A found a fifth:

**H5P** — a full route tree with working CRUD, five tables, and **10 live items every one of which hangs off
a chapter absent from `chapter_master`**. So `chapterContent()` 404s for them and the scenario list returns
empty for all 120 reachable chapters. Real UI, real CRUD, zero reachable data.

Two of the original four also need re-grading in light of B1:
- **Assessment Bank** — not "2.3% populated" but **0% calibrated**, with **29,234 items calibratable today**
  from data already collected. Inert because a command was never run, not because content is missing.
- **KASBA** — inert in the *data* sense (`competency_kasba_item` = 1 row, `competency_kasba_rating` = 0), but
  its UI panel is **live and rendered**, contra #54.

---

## 📋 #58 — Definition of Done, applied to our own Sheet A

> "'Done' or 'Live' requires a CONFIRMED real module consuming the engine and producing auditable,
> user-visible value."

Applied honestly, three of our six rows do **not** qualify:

| Row | Status we recommend | Why it is not `Done` |
|---|---|---|
| 1 Chapter resource split | Verified | Taxonomy right, data layer needs reconciliation |
| **2 H5P as format tag** | **Built — blocked on data** | No user can reach an H5P item: every one sits on a chapter outside the catalogue |
| 3 Shared authoring | Built — additional front door | `lms_content_authoring_audit` = **0 rows**; no real traffic yet |
| **4 Ownership field** | **Built — gated on a commercial switch** | `is_Lms='Y'` on 1 of 56 schools, so the layering it enables is off |
| **5 RBAC gate** | **Built — warn-only** | Blocks nothing for tokenless callers. *(Corrected 2026-09-08: it DID block authenticated-but-ungranted callers with 403 regardless of the flag — a live regression, now fixed and covered by `RequirePermissionTest`.)* |
| 6 Concept Intelligence | Answered + projection shipped | Endpoint live and queryable |

Stamping any of rows 2, 4 or 5 plain `Done` would be exactly the overclaiming #58 exists to prevent.
