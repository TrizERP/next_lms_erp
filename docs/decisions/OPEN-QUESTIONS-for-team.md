# Open questions for the team — Content & LMS Architecture

Raised while executing the tracker's "Content & LMS Architecture" sheet. None of these are engineering
judgement calls, which is why they are here rather than decided in code. Each says what is blocked and what
happens if it stays unanswered.

Last updated 2026-09-07 (after Phase A2).

---

## 1. Which schools get the platform content layer switched on? — **commercial**

`school_setup.is_Lms = 'Y'` gates the additive "platform content PLUS your own" read
(`ApiLmsCourseController.php:553-560`). Live: **1 of 56 schools** has it (institute 328, which owns no content
of its own). The other 55 take the tenant-only branch and cannot see the platform tenant's **16,379** content
rows at all.

**Blocks:** the user-visible half of tracker row 4. The ownership field is built and backfilled; the layering
it enables is off almost everywhere.

**Why not just turn it on:** it would change what every teacher in 55 schools sees, overnight. That is a
decision about what each school has paid for.

**Needed:** a list of which `sub_institute_id`s should have `is_Lms = 'Y'`, or a rule for deriving it.

---

## 2. Which Teacher Resource estate wins? — **product, touches the legacy Blade UI**

There are two, and they do not overlap:

| Estate | Rows | Visible to |
|---|---:|---|
| `lms_teacher_resource` | 2,605 | the legacy Blade UI only |
| `content_master` rows whose category contains "teacher" | 4,912 | the Next.js UI only |

The Next.js Teacher Workspace never reads `lms_teacher_resource` — verified:
`grep -c "lms_teacher_resource" app/Http/Controllers/api/ApiLmsCourseController.php` → **0**.

**Blocks:** closing tracker row 1 honestly, and any future "one content model" work.

**Our recommendation if pressed:** retire `lms_teacher_resource` into `content_master`. The new UI, the PAL
sidecars (`pal_content_metadata` is keyed on `content_master_id`) and the new provenance sidecar all already
key on `content_master`. Migrating 2,605 rows once is cheaper than teaching four subsystems about a second
table. But this changes what Blade users see, so it is not ours to decide.

---

## 3. The chapter catalogue is broken, and it now blocks a delivered feature — **data**

`chapter_master` holds **120** rows. Content and H5P reference chapter ids far outside it.

- All 10 live H5P items sit on chapters **8104 / 8506 / 8507 / 8508** — none in `chapter_master`.
- Those same four chapters carry **43** `content_master` rows.
- `chapterContent()` returns 404 for any chapter without a `chapter_master` row, so none of it is reachable.

**Blocks:** the user-visible half of tracker row 2. The H5P filter is built and unit-tested, and was verified
directly against chapters 8506 and 8508 — only the route into it is missing.

**Worth knowing:** this is not a regression we introduced. `H5PScenarioController@index` filters by
`chapter_id`, so the "H5P Content" button we removed was already returning an empty list for **all 120**
chapters the catalogue can display.

Same root cause as R5 in `docs/lms-pal-content-intelligence-master-prompt.md` (`chapter_master` 110 vs 5,521
`:Chapter` nodes in the graph). It has now blocked a second deliverable, which should raise its priority.

**Do not** "fix" this by relaxing the 404 — serving content for a chapter with no catalogue row produces cards
with no chapter name and no way back.

---

## 4. Correcting two numbers the tracker reports — **for the record, no action needed from you**

Both were verified against the live database and are wrong in the workbook. Flagging them because decisions
are being sequenced off them.

**"Calibrated Assessment Bank at 2.3% — blocks 3 PAL loop steps."** There is no assessment-bank table
anywhere (`grep -riE "calibrat|item_bank|assessment_bank" database/migrations/` → zero hits). The 2.3% is
computed per request at `ContentModelProjector.php:598` as *"2 of the 86 `semantic_intelligence` rows for
tenant 1 whose `assessment_rubrics` JSON column is non-empty"*. It measures **LLM extraction coverage**, not
psychometric calibration.

**"Concept Learning / Practice / Misconceptions all at 100% (86/86)."** That is 100% of an 86-chapter
denominator which is itself ~6.6% of the estate — `semantic_intelligence` covers 89 chapters; the question
bank spans **1,351**.

**A larger gap nobody has logged:** of **62,487** questions, only **282** have a non-empty `answer`
(0.45%). If assessment integrity is the concern, this is the number to escalate.

`app/Console/Commands/PAL/DeriveIrtCommand.php` already exists and can compute real IRT calibration from the
~2.4M graded responses in `lms_online_exam_answer` — so the *real* calibration figure is measurable now, not
merely confirmable.

---

## 5. Teacher-authored content does not exist yet — **planning input, not a blocker**

All 96,479 provenance rows are `platform` or `school`. The `teacher` bucket is **empty** in every estate.

The tracker plans around "70-80% use ready-made content, 20-30% create their own". Today that 20-30% is 0%.
Row 5's RBAC gate is still worth building — you gate before enabling, not after — but it cannot be validated
against real usage, and a future reader should not mistake "0 teacher rows" for a defect.
