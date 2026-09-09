# Is the Classroom / Teacher / Question Bank resource split "correct as designed"?

**Answers:** Tracker sheet "Content & LMS Architecture" row 1
**Date:** 2026-09-07 · **Method:** direct code reading + read-only queries against live `vivek_erp`
**Status of the answer:** the tracker's "no change needed" is **half right**. One question needs the team.

---

## The question as the tracker asked it

> Chapter resource split: Classroom Resource / Teacher Resource / Question Bank — three genuinely different
> audience/purpose categories per chapter. **Confirmed correct as designed — no change needed.**
> Status: `Live (per screenshot)`

## The answer

**Correct as a concept. Not correct as an implementation.** The three-way audience split is the right model
and should stay. But it is not implemented as three audiences over one content model — it is three unrelated
storage estates, one of which the new UI does not read at all.

"No change needed" is safe to say about the *taxonomy*. It is not safe to say about the *data layer*, and
rows 2, 3, 4 and 5 of the same sheet all land on that data layer.

---

## Evidence

### 1. Three unrelated tables, no shared model

| Surface | Table | Live rows | Own upload path | Own controller |
|---|---|---:|---|---|
| Classroom Resource | `content_master` | **31,385** | `putFileAs('public/lms_content_file/')` | `lms/contentController` |
| Teacher Resource | `lms_teacher_resource` | **2,605** | `putFileAs('public/lms_teacher_resource/')` | `TeacherResourceApiController` |
| Question Bank | `lms_question_master` | **62,487** | n/a | `questionmasterController` |

Different column sets, different controllers, different storage paths. There is **no shared field for
ownership, quality status, format, or provenance** across the three — which is exactly what sheet rows 2, 3
and 4 need. Each of those rows currently has to be solved three times, or once in a sidecar that spans all
three. (The plan chooses the sidecar.)

### 2. The Next.js Teacher Resource never reads `lms_teacher_resource`

This is the finding that changes row 1's status.

`app/Http/Controllers/api/ApiLmsCourseController.php:540-604` — `getChapterContentCategories()`, the sole
source for the chapter content screen — queries **only `content_master`** (left-joined to `lms_concept`
purely to resolve a concept name) **and `lms_flashcard`**. There is no
join, subquery or union to `lms_teacher_resource` anywhere in the method.

So what does the new Teacher Resource screen actually show? `content_master` rows filtered by a **hardcoded
string predicate on the frontend** — `isTeacherTrainingContent()` at
`lms_k12/app/course-master/[courseId]/chapters/page.tsx:544-546`:

```ts
return `${item.contentCategory ?? ''} ${item.type}`.toLowerCase().includes('teacher');
```

**There are two Teacher Resource estates:**

| Estate | Rows | Who sees it |
|---|---:|---|
| `lms_teacher_resource` | 2,605 | the legacy Blade UI only |
| `content_master` rows whose category or type contains `"teacher"` — `Teacher Training` (4,898) + `Teacher training presentation` (14) | **4,912** | the Next.js UI only |

They do not overlap and neither knows about the other. A teacher using the new UI cannot see 2,605 resources
that exist; a teacher using Blade cannot see 4,912 that do.

Verified: `grep -c "lms_teacher_resource" app/Http/Controllers/api/ApiLmsCourseController.php` → **0**.

### 3. The audience split is a string match, not data

```
// lms_k12/app/course-master/[courseId]/chapters/page.tsx:1240-1243
const matchesResourceType =
  contentResourceType === 'teacher' ? isTeacherTraining : !isTeacherTraining;
```

Classroom vs Teacher is decided client-side by matching `"teacher"` against text. Nothing in the schema records
audience. Any content whose category string is worded differently lands on the wrong surface, silently.

### 4. The tab strip on Teacher Resource filters nothing

```
// page.tsx:1242-1252
const matchesTab =
  contentResourceType === 'teacher'
    ? true                       // <- every tab shows every item
    : contentLibraryTab === 'All content' || ...
```

`All content` and `Presentations` render as tabs on Teacher Resource and both return the full list. This
matters for row 2: adding an `H5P Interactive` tab there is a **real behaviour change**, not a cosmetic one,
because the filter it joins does not currently work.

### 5. `content_master` carries three competing discriminators

`content_category` (18 live values), `content_type` (added `2026_07_24_000001`), and `source` (added
`2026_08_07_000001`). Live distribution of `content_category`:

```
My Course 10,984 · Teacher Training 4,898 · Recorded Videos 2,952 · Revision Notes 2,560
Classroom Activity 2,494 · Classroom Presentation 2,390 · Videos 2,305 · Worksheet 1,351
(blank) 781 · Lesson Plan 371 · Remedial Class 257 · … 6 more with <15 rows each
```

These mix **audience** (Teacher Training), **format** (Recorded Videos, Presentation) and **purpose**
(Revision Notes, Remedial Class) on one axis. That is the same category error the tracker correctly identified
for H5P in row 2 — it is simply more widespread than the H5P case.

### 6. The platform content layer is switched off for 55 of 56 schools

Found while building the ownership backfill, and it materially changes what row 4 is asking for.

The additive "platform PLUS your own" read is gated on `school_setup.is_Lms = 'Y'`
(`ApiLmsCourseController.php:553-560`). Measured on live:

| | Count |
|---|---:|
| Schools with `is_Lms = 'Y'` | **1** (institute 328) |
| Schools with `is_Lms = 'N'` | **55** |
| Content rows owned by institute 328 | **0** |

So today, 55 of 56 schools take the `else` branch, which is tenant-only: they never see the platform
tenant's **16,379** content rows at all. The single school that does get the additive read owns no content
of its own, so it sees only platform content.

**The "teacher sees default content PLUS their own additions" behaviour that row 4 describes is therefore
not happening anywhere in production right now.** The ownership field is a genuine prerequisite for it, but
it is not sufficient — someone has to decide which schools get `is_Lms = 'Y'`.

That is a product/commercial decision about what each school has paid for, not an engineering one, so
Phase A1 deliberately did **not** change it. Flagging it rather than flipping it: turning the platform layer
on for 55 schools would change what every teacher in them sees, overnight.

---

---

## What is genuinely correct, and should not be touched

The **three-audience taxonomy itself**: student-facing, teacher-prep-only, and assessment are real, distinct
purposes, and every downstream decision in the tracker (PAL consuming student content, Examination consuming
the question bank, Teacher Workspace consuming teacher content) depends on that distinction holding. Keep it.

---

## Recommendation

1. **Change row 1's status** from `Live (per screenshot)` / "no change needed" to
   **`Live — taxonomy correct, data layer needs reconciliation`**, with findings 2 and 5 attached.
2. **Do not fix the two-estate split unilaterally.** It touches the Blade UI that 56 tenants use. This is the
   one open question for the team:

   > Does the Next.js Teacher Resource migrate onto `lms_teacher_resource` (2,605 rows, purpose-built,
   > currently invisible to the new UI), or does `lms_teacher_resource` retire into `content_master` with
   > `content_category = 'Teacher Training'` (4,912 rows, what the new UI already shows)?

   Recommendation if pressed: **retire `lms_teacher_resource` into `content_master`.** The new UI, the PAL
   sidecars (`pal_content_metadata` is keyed on `content_master_id`), and the planned ownership sidecar all
   already key on `content_master`. Migrating 2,605 rows one way is cheaper than teaching four subsystems
   about a second table. But this is a product call about the Blade UI, not ours to make.
3. **Make audience explicit rather than inferred.** The ownership sidecar (Phase A1) is the natural home for
   an `audience` field, replacing `isTeacherTrainingContent()`'s string match. Cheap to add now, expensive
   once more content is authored.
4. **Fix `matchesTab` for Teacher Resource** as part of Phase A2, since row 2 requires that filter to work.

---

## How to reproduce

```sql
SELECT COUNT(*) FROM content_master;                 -- 31,385
SELECT COUNT(*) FROM lms_teacher_resource;           -- 2,605
SELECT COUNT(*) FROM lms_question_master;            -- 62,487
SELECT content_category, COUNT(*) c FROM content_master GROUP BY content_category ORDER BY c DESC;
SELECT COUNT(*) FROM content_master WHERE content_category = 'Teacher Training';   -- 4,898
```

Then read `app/Http/Controllers/api/ApiLmsCourseController.php:540-604` and confirm `lms_teacher_resource`
does not appear in it.
