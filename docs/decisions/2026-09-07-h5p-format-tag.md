# H5P becomes a format tag, not a fourth destination

**Delivers:** Tracker sheet "Content & LMS Architecture" row 2 · "Decisions & Risk Log" #35
**Date:** 2026-09-07 · **Phase:** A2
**Method:** direct code reading + read-only queries against live `vivek_erp`

---

## What the tracker asked for

> Fold H5P into the existing content-type filter already visible in Teacher Resource
> ("All content / Presentations" + "All sources" dropdown) — add "H5P Interactive" as a filter value
> inside Classroom Resource and Teacher Resource, remove it as a top-level button.

The reasoning behind it is sound and is worth restating, because it is the part that generalises:
**format and audience are different classification axes.** "Is this interactive?" is a format question;
"is this for students or for teacher prep?" is an audience question. An H5P item can legitimately be either
audience, so making it a peer of Classroom / Teacher / Question Bank forces a false choice.

## What was actually required

The sheet describes this as folding H5P into an existing filter, which reads like a UI tweak. It is not.
**There was nothing in the content list to filter for.**

| Measured on live, 2026-09-07 | |
|---|---:|
| H5P rows in `content_master` | **0** |
| `content_master.file_type` values | pdf 15,460 · link 13,683 · mp4 1,921 · pptx 183 · jpg 99 · docx 9 · … **no `h5p`** |
| `h5p_scenarios` | 11 (10 live, 1 soft-deleted) |
| `h5p_interactive_video` | 0 |
| `h5p_flashcard` | 0 |

So H5P had to be *joined into* the content list before it could be filtered within it.

## What was built

`app/Services/lms/Content/H5PContentAdapter.php` reads the three `h5p_*` tables and maps them into the same
asset shape the chapter content list already uses, with `format = 'h5p'` under an `H5P Interactive` bucket.

Three options were considered:

| Option | Verdict |
|---|---|
| INSERT the items into `content_master` | **Rejected.** Violates additive-only on a table 56 tenants and the Blade UI read (CONTENT LAW C2). An H5P item also has no file — its render target is a route, not a `file_type` any existing viewer can open. |
| Backfill `pal_content_metadata.format = 'h5p'` | **Rejected as insufficient.** That table is uniquely keyed `(content_master_id, sub_institute_id)`, so it structurally cannot describe an item with no `content_master` row. It stays correct for H5P authored *as* content_master rows later. |
| **Adapter that reads the `h5p_*` tables in place** | **Chosen.** Nothing is copied, so there is no second source of truth and no sync problem. |

There is precedent for exactly this in the method it plugs into: `Flash Cards` are already merged in from
`lms_flashcard`, and `Mindmap` / `Virtual Lab` are already synthesised as empty buckets
(`ApiLmsCourseController::getChapterContentCategories`). H5P follows a pattern that already ships.

Notable details:

- **Ids are namespaced** — `h5p:scenario:7`, never a bare `7` — so an H5P item can never collide with a
  `content_master.id` or be mistaken for one by a write path. The real key travels separately as
  `h5p_source_id`, and `ContentOwnershipDecorator::provenanceKey()` resolves it explicitly rather than
  coercing the namespaced string with `(int)` (which would silently yield 0).
- **Audience is `both`.** No `h5p_*` table records an audience, so H5P surfaces on Classroom Resource *and*
  Teacher Workspace. That is what the tracker asks for, and it avoids inventing a classification the data
  cannot support.
- **Soft deletes are honoured** — 11 rows exist, 10 are served.
- **Reversible without a deploy**: `config('lms_content.h5p.surface_in_content_list')`, and
  `SHOW_LEGACY_H5P_BUTTON` on the frontend restores the old button in one line.

## The finding that matters more than the change

**Every H5P item in the estate is attached to a chapter that does not exist in `chapter_master`.**

| `h5p_scenarios.chapter_id` | items | in `chapter_master`? | rows in `content_master` |
|---:|---:|---|---:|
| 8104 | 1 | **no** | 13 |
| 8506 | 7 | **no** | 13 |
| 8507 | 1 | **no** | 4 |
| 8508 | 2 | **no** | 13 |

`chapter_master` holds 120 rows. None of those four ids is among them.

Two consequences:

1. **`chapterContent()` returns 404 for those chapters**, because it requires a `chapter_master` row before it
   reads any content. So the H5P bucket is correct but currently unreachable through the UI — and so are the
   43 `content_master` rows on the same four chapters.

2. **The button being removed was already dead.** `H5PScenarioController@index` filters on
   `chapter_id` + `standard_id` + `subject_id` + `sub_institute_id`. The chapter id it receives comes from the
   chapter list, which is built from `chapter_master`. Since no H5P item shares a chapter with `chapter_master`,
   the old "H5P Content" button returned an **empty list for every one of the 120 chapters the catalogue can
   display**. Removing it loses no reach whatsoever.

This is the "built but inert" pattern the Session Audit tab names — real UI, real CRUD, real routes, and no
reachable data — showing up in a fifth place beyond the four already logged (Assessment Bank, Pedagogy Engine,
KASBA, Leaderboard).

## What this means for row 2's status

The code change is complete and correct, and it is strictly better than what it replaces. But marking row 2
`Done` would overstate it, because **no user can see an H5P item either before or after** until the chapter
linkage is repaired.

Recommended status: **`Built — blocked on chapter linkage, not on code`**, with the linkage gap raised as its
own item. That is the honest reading under Decision #58's Definition of Done, which requires "a CONFIRMED real
module consuming the engine and producing auditable, user-visible value".

## Recommendation

1. **Reconcile the chapter estate.** This is the same `chapter_master` = 120 vs thousands-of-chapter-ids
   discrepancy already recorded as R5 in `docs/lms-pal-content-intelligence-master-prompt.md` (there measured
   as `chapter_master` 110 vs 5,521 `:Chapter` nodes in the graph). It is now blocking a second deliverable,
   which raises its priority.
2. **Do not "fix" it by relaxing the 404.** Serving content for a chapter with no `chapter_master` row would
   produce cards with no chapter name and no way back — the linkage needs repairing at the data layer.
3. **Re-verify row 2 end-to-end once linkage lands.** The adapter is unit-tested and was verified directly
   against chapters 8506 and 8508 (6 and 2 assets returned respectively, correct deep links, correct
   `format='h5p'`); only the route into it is missing.

## How to reproduce

```sql
SELECT chapter_id, COUNT(*) total, SUM(deleted_at IS NOT NULL) soft_deleted
  FROM h5p_scenarios GROUP BY chapter_id;
SELECT id FROM chapter_master WHERE id IN (8104, 8506, 8507, 8508);   -- returns nothing
SELECT COUNT(*) FROM chapter_master;                                   -- 120
SELECT file_type, COUNT(*) FROM content_master GROUP BY file_type;     -- no h5p
```

Adapter, directly (bypasses the `chapter_master` gate):

```php
app(App\Services\lms\Content\H5PContentAdapter::class)->forChapter(8506, 1);   // 6 assets
```
