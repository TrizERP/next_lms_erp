# Content ownership: platform / school / teacher

**Delivers:** Tracker sheet "Content & LMS Architecture" row 4 · "Decisions & Risk Log" #37
**Date:** 2026-09-07 · **Phase:** A1
**Method:** additive migration + derived backfill, verified against live `vivek_erp`

---

## What the tracker asked for

> Every content item tagged by provenance, so a teacher with creation rights sees default content PLUS their
> own additions layered on top — never a fork that replaces the default.
> *Action: add this field now, before more content is authored by more sources.*

The urgency is the right instinct: retro-tagging provenance after several more authoring sources exist is
strictly harder than tagging 96k rows once, today.

## What was built

`lms_content_provenance` — one additive sidecar spanning **all** content estates
(migration `2026_09_07_170000_create_lms_content_provenance_table.php`).

| Column | Purpose |
|---|---|
| `entity_type` + `entity_id` | which item, in which estate (resolved via `config/lms_content.php`, never a hardcoded table name) |
| `owner_sub_institute_id` | explicit tenancy — CONTENT LAW C3, never inferred |
| `ownership` | `platform` \| `school` \| `teacher` |
| `authored_by_user_id`, `authored_by_profile` | who, plus a snapshot of their profile name (profiles get renamed) |
| `authoring_mode`, `generation_source` | how it was made |
| `derived_from_entity_id` | **the overlay pointer** — the platform item this one extends |
| `visibility`, `status` | `global`/`tenant`/`self`; `active`/`archived` |

Unique on `(entity_type, entity_id, owner_sub_institute_id)`, so the backfill is idempotent.

**Three design choices worth recording:**

1. **A sidecar, not a column.** `content_master` (31,385), `lms_teacher_resource` (2,605) and
   `lms_question_master` (62,487) are live across 56 tenants and still read by the legacy Blade UI. Adding a
   column to any of them is the additive-only violation CONTENT LAW C2 exists to prevent.

2. **One table for all estates, not three.** The "3-way split" is three unrelated tables with no shared field
   (`docs/decisions/2026-09-07-chapter-resource-split.md`). Solving ownership per table means solving it three
   times and then reconciling three answers.

3. **Not `pal_content_metadata`.** That is PAL's *pedagogy* overlay and its `quality_status` gates *delivery to
   a learner* (C4). Ownership is an *authoring/governance* fact the Blade content library needs and PAL's
   delivery engine does not. It is also keyed `(content_master_id, sub_institute_id)`, so it structurally
   cannot describe the H5P estate, which has no `content_master` row at all.

## Backfill result

`php artisan lms:backfill-content-provenance --estate=all`

| Estate | Source rows | Written | Platform | School | Teacher | Skipped |
|---|---:|---:|---:|---:|---:|---:|
| `content_master` | 31,385 | 31,385 | 16,377 | 15,006 | **2** | 0 |
| `lms_teacher_resource` | 2,605 | 2,605 | 1,087 | 1,518 | 0 | 0 |
| `lms_question_master` | 62,487 | 62,478 | 25,592 | 36,886 | 0 | **9** |
| `h5p_scenarios` (added in A2) | 11 | 11 | 11 | 0 | 0 | 0 |
| **Total** | | **96,479** | 43,067 | 53,410 | **2** | 9 |

Invariant holds with no exceptions: `platform → global`; `school` and `teacher` → `tenant`.
Visibility follows **ownership**, not tenancy — a teacher-authored item in the platform library is still
tenant-visible, not global.

**The 9 skipped rows** are questions with `sub_institute_id IS NULL`. CONTENT LAW C3 says undecidable tenancy
is *rejected*, not defaulted — so they are logged and left unclassified rather than silently assigned an owner.

## Two findings that change what row 4 means

### 1. ~~There is no teacher-authored content anywhere~~ — CORRECTED 2026-09-08

**This section originally reported "teacher = 0" as a finding about the data. That was wrong: it was an
artifact of a bug in this very command, found by a later audit.**

Two defects, together:

1. `classify()` tested the **platform tenant before the author signal**, so any explicit author marker was
   discarded for every row on tenant 1 — over half the estate.
2. `ContentProvenanceService` enforced a strict XOR (platform tenant **iff** platform ownership), which made
   `ownership='teacher'` **structurally unreachable** for a tenant-1 row even if rule 1 had fired.

The only two rows in `content_master` carrying the documented teacher marker
(`user_profile_name='LMS Teache'`, ids **53869** and **53870**) sit on tenant 1. So the teacher rule could
never fire on the exact rows it was written for.

**Root cause: ownership and tenancy are different axes and this code conflated them.**

| | |
|---|---|
| `owner_sub_institute_id` | **who owns it** — whose library it sits in |
| `ownership` | **who authored it** — platform / school / teacher |

A teacher can perfectly well author content that lives in the platform library. What must remain impossible
is the reverse claim — content owned by an ordinary school tenant is not *platform-authored* — and that one
direction is still enforced.

**After the fix**, re-running the backfill:

| ownership | visibility | rows |
|---|---|---:|
| platform | global | 43,067 |
| school | tenant | 53,410 |
| **teacher** | tenant | **2** |
| | | **96,479** |

Both teacher rows are `tenant=1, profile='LMS Teache', user=6956` — exactly the two the classifier was
written for.

**What is still true, and matters for row 5:** teacher-authored content is *vanishingly rare* — 2 rows out of
96,479. The tracker plans around "70-80% use ready-made content, 20-30% create their own". The observed
figure is 0.002%. The RBAC gate is still worth building (you gate before enabling), but it cannot be
validated against real usage, and nobody should read the near-zero as a defect.

### 2. The "layered on top" behaviour is switched off for 55 of 56 schools

The additive *platform-plus-own* read is gated on `school_setup.is_Lms = 'Y'`
(`ApiLmsCourseController.php:553-560`). Measured live: **1** school has `Y` (institute 328, which owns 0
content), **55** have `N`.

So 55 of 56 schools take the tenant-only branch and never see the platform tenant's 16,379 content rows at
all. **The exact behaviour row 4 describes is not happening anywhere in production.**

The ownership field is a genuine prerequisite for it — but it is not sufficient. Someone has to decide which
schools get `is_Lms = 'Y'`, and that is a commercial decision about what a school has bought, not an
engineering one. Phase A1 deliberately did **not** change it: flipping it would alter what every teacher in
55 schools sees, overnight, with no announcement.

## How "never a fork" is actually enforced

Three mechanisms, weakest to strongest:

1. **The read predicate is additive.** `platform tenant OR this tenant` — an `OR`, never an `AND`. No future
   filter may narrow it.
2. **`ContentOwnershipDecorator` adds fields and never removes rows.** A derived item is *pointed at* from its
   parent via `overlay_entity_ids`; the parent is always emitted.
3. **A unit test holds the invariant** — `tests/Unit/ContentOwnershipDecoratorTest.php` asserts the platform
   item count is identical before and after overlaying, on a fixture where a teacher item derives from a
   platform item. Convention and comments cannot enforce a property of the output; a test can.

`derived_from_entity_id` is **never** written by the backfill. Whether a school item was historically created
as an extension of a platform item is not reconstructible after the fact, and guessing would corrupt the very
overlay it exists to express. It is only written forward, by the authoring service in Phase A3.

## Status recommendation

Row 4: **`Built — field live and fully backfilled; the behaviour it enables is gated on a commercial switch`**.

Marking it plainly `Done` would imply teachers now see layered content. They do not, for a reason outside
engineering's control.

## How to reproduce

```sql
SELECT entity_type, ownership, COUNT(*) FROM lms_content_provenance GROUP BY entity_type, ownership;
SELECT ownership, visibility, COUNT(*) FROM lms_content_provenance GROUP BY ownership, visibility;
SELECT COUNT(*) FROM lms_question_master WHERE sub_institute_id IS NULL;   -- the 9 skipped
SELECT is_Lms, COUNT(*) FROM school_setup GROUP BY is_Lms;                 -- 1 Y, 55 N
SELECT user_profile_name, sub_institute_id, COUNT(*) FROM content_master
 WHERE user_profile_name <> '' GROUP BY user_profile_name, sub_institute_id;
```

```bash
php artisan lms:backfill-content-provenance --estate=all --dry-run   # re-run safely, writes nothing
./vendor/bin/phpunit --filter ContentOwnershipDecoratorTest           # the never-a-fork invariant
```
