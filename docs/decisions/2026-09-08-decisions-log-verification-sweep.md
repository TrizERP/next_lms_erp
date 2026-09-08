# Decisions & Risk Log — verification sweep

**Answers:** the open / unconfirmed rows of the "Decisions & Risk Log" sheet
**Date:** 2026-09-08 · **Phase:** B1
**Method:** direct code reading + live queries against `vivek_erp`, and one dry-run command

---

## The sheet, measured

**72 rows in the file = 1 title + 1 subtitle + 1 header + 69 numbered decisions** (#1–#69, no gaps).

| Status | Rows |
|---|---:|
| `Frozen` (all flavours) | **53** |
| `Needs Team Decision` | 4 |
| `Open` | 5 |
| `Needs Team Confirmation` | 3 |
| `Decided — migration in progress` | 2 |
| `Not started` | 1 |
| `SUPERSEDED` | 1 |

Unlike every other tab, this sheet has **no "Action Required" column** — it is a decision *register*.
"Completing" it means upholding, verifying or deciding each row, not building a backlog.

---

## #50 — Calibrated Assessment Bank *(flagged URGENT)*

### The sheet's number measures something else entirely

> "Live dashboard shows … Calibrated Assessment Bank at 2.3% (2/86 chapters). This single content type is
> load-bearing for THREE separate PAL loop steps."

There is **no calibrated assessment bank table**. *(Precision note: that grep actually matches 4 migration
files — all HR performance-review "calibration", e.g. `2026_08_18_112000_create_talent_performance_tables.php`.
Restricted to table names, there is no `calibrat`/`item_bank`/`assessment_bank` table. The conclusion holds;
the command as published does not produce a zero-hit output.)* The 2.3% is computed per request at
`ContentModelProjector.php:598` as `count(array_filter($items, fn ($i) => $i['has_answer_key']))` over
`semantic_intelligence.assessment_rubrics` — i.e. **2 of the 86 AI-extraction rows for tenant 1 produced a
rubric payload.** It is an LLM extraction-coverage figure, not psychometric calibration.

### The real numbers

| Measure | Value |
|---|---:|
| Questions in the bank | **62,487** |
| Graded responses available (`lms_online_exam_answer`) | **2,418,164** |
| Questions with **any** response | 43,265 |
| **Questions with ≥30 responses — calibratable today** | **29,234 (46.8%)** |
| Questions actually calibrated right now | **0** |

`pal_question_metadata` holds 2,209 rows, and across all of them `discrimination_index`, `irt_b` and
`response_count` are **entirely unset**. So the true calibration coverage is **0%**, not 2.3%.

### But the fix already exists and has simply never been run

`app/Console/Commands/PAL/DeriveIrtCommand.php` (`pal:derive-irt`) derives Rasch difficulty (`irt_b`),
a classical Kelley discrimination index, average time and first-attempt rate straight from the answer
history. It is honest about its limits: `irt_a` is written as an approximation, and `irt_c` is left NULL
rather than fabricated, because a guessing parameter needs a 3PL fit.

**Dry-run over a 3,000-question sample** (no writes, per the agreed scope):

| | |
|---|---:|
| Processed | 3,000 |
| Would write | 3,000 |
| Skipped (below the 30-response floor) | 0 |
| **Flagged REVISE (discrimination < 0.25)** | **1,708 (57%)** |

Per tenant: `sub_institute_id` 195 → 2,055; 72 → 945.

### What this changes

1. **#50 is not a content-authoring problem.** Nobody needs to write questions. **29,234 items are
   calibratable today** with a command that already exists, from data already collected.
2. **It is an ops gap, not an engineering gap.** `pal:derive-irt` is not in `Kernel::schedule()`. Running it
   and scheduling it is the whole fix — which folds directly into **#5**.
3. **The more serious finding is quality, not coverage.** 57% of the sampled items discriminate below 0.25.
   Calibrating the bank will not make it good; it will make it *measurably* mediocre, which is the necessary
   first step but should not be sold as a win.

**Recommended status:** `Confirmed — the 2.3% figure is misread; true calibration is 0%, and 29,234 items are
calibratable now with an existing command. Escalate as an ops task, not a content backlog.`

---

## #53 — PAL vs Examination item isolation *(same defect, different angle)*

- `pal_question_metadata.assessment_type` **exists** but is non-null on **0 of 2,209** rows. Dead metadata.
- The discriminator actually in use is `question_paper.exam_type = 'PAL'`, consistently, across
  `palController.php:562,828,964,1123,1321,1837,2453,2475`, `PalWorkspaceController.php:282`,
  `PalEvidenceRepository.php:73,148`. Examination excludes it explicitly:
  `LmsResultDashboardApiController.php:55` — `private const EXCLUDED_EXAM_TYPE = 'PAL';`
- **PAL's adaptive selection pool is `palController.php:987-1012` `getRandomPalQuestions()`, and it ends in
  `->inRandomOrder()->take($limit)`.** It reads no `irt_b`, no `difficulty`, no `assessment_type`.

So the exclusion rule #53 asks for has nothing to attach to yet, and PAL is not selecting adaptively in the
first place. **#50 and #53 are one defect seen twice.** Brief in B4.

---

## #2 — Auth bypass: substantially fixed by the platform team, with a residue

**Correcting our own earlier citations.** The `type=API` bypass described in the tracker (and cited in our A4
memo) **no longer exists** — it was removed while this work was in progress.

| Then | Now |
|---|---|
| `checkPermission.php:25` skipped every rights check when `type=API` | Gone. A comment at `:25-29` records the change; the guard is now only `session()->get('user_profile_name') != "Super Admin"` |
| `SessionMiddleware.php:21` let `type=API` through unauthenticated | Now **JWT-gated** — `:24-34` calls `hydrateSessionFromToken()` and returns the error before `$next()` |
| `merge(['type' => 'API'])` in `ApiSessionHydrator.php` | Actually lives in `Concerns/HydratesLegacyApiSession.php:147` |

**What remains open:**

1. **The no-rights rejection is still commented out** — `checkPermission.php:71-73`. A user with no rights row
   still falls through to allowed.
2. **Hardcoded menu-id allowlists** — `:78` `in_array($menu_id,[200])` always permits delete; `:82`
   `in_array($menu_id,[31,82,386])` always permits edit. **This is a live #23 violation** (see B2).
3. **`if ($menu_id != '')` at `:44`** — any route with no `tblmenumaster` row is unguarded by default.
4. **`routes/api.php` still has no auth on most routes.** Reproduced today, with **no token at all**:

   | Request | Result |
   |---|---|
   | `POST /api/lms-chapters` with `user_profile_name=ADMIN` | **200 — 1,484,576 bytes of real data** |
   | same with `TEACHER` | **200 — 1,484,576 bytes** |
   | same with `STUDENT` | 403 |
   | same with the field omitted | 403 |

   The role is read from **request input** (`ApiLmsCourseController.php:698-700`, repeated at `:733, :807,
   :878, :1029`). The caller declares their own role. This is not a session bypass — it is a different door,
   and it is still open.

5. `routes/teacherapi.php` and `routes/adminapi.php` are registered with **no middleware at all**;
   `routes/lms.php:352-361` carries 9 unauthenticated AI-generation routes that spend provider credits.

**Recommended status:** `Partially resolved — the type=API bypass is closed; client-supplied role, the
commented-out rejection, and the menu-id allowlists remain.` Track D's, not ours.

---

## #45 — Framework tab: not a second implementation, and not really an implementation

The decision asks us to verify "whether PAL's current tab isn't already just a data view".

**It is less than a data view — it is a substring search over concatenated text.**
`app/pal/data/pal-content-model.ts:362-370`:

```ts
function findKeywordMatches(values: string[], keywords: string[], limit = 8): PalSourceItem[] {
  const lowered = keywords.map((keyword) => keyword.toLowerCase());
  return take(unique(values)
      .filter((value) => lowered.some((keyword) => value.toLowerCase().includes(keyword)))
      .map((value) => ({ title: decodeText(value) })), limit);
}
```

Called **7 times** to construct the framework modules. There is no id join, no backend query, no taxonomy,
and results are capped at 8 per module. `FRAMEWORK_META` is a static 7-slug list, duplicated again in
`app/pal/content-model-data.ts`.

Route duplication confirmed — **6 files, 2 of them dead stubs**:

| File | Lines | |
|---|---:|---|
| `app/pal/framework/page.tsx` | 28 | redirect stub |
| `app/pal/framework/ulu/page.tsx` | 5 | redirect stub |
| `app/pal/frameworks/page.tsx` | 59 | real |
| `app/pal/frameworks/[slug]/page.tsx` | 67 | real |
| `app/pal/ulu/page.tsx` | 59 | real |
| `app/pal/ulu/[slug]/page.tsx` | 67 | real |

**Recommended status:** `Confirmed — PAL's Framework tab is a keyword projection, not an alignment model.
Nothing to migrate; there is no second implementation to reconcile, only a display to rebuild once
Curriculum owns the real data.` The 2 dead stubs are safe to delete.

---

## #34 — K-12 KASBA vs G2G Capability Intelligence

**45 tables** match competency / capability / kasba / evidence in the live schema. **26 of them are
completely empty.** The populated ones:

| Table | Rows |
|---|---:|
| `pal_competencies` | **24,003** |
| `hpbrain_capabilities` | **2,398** |
| `ai_evidence` | 1,448 |
| `hpbrain_capability_tasks` | 1,224 |
| `ai_case_evidence` | 722 |
| `hpbrain_case_evidence` / `hpbrain_evidence` | 135 each |
| `pal_learning_evidence` | 34 |
| `evidence_events` | 7 |
| `competency` | 3 |
| **`competency_kasba_item`** | **1** |
| **`competency_kasba_rating`** | **0** |
| 8 more `s_competency_*` | 1–9 each |

Three things follow, and they shrink the decision considerably:

1. **KASBA is an empty shell** — 1 item, 0 ratings. There is no K-12 capability graph to reconcile *with*.
   The duplication the decision fears has not been built.
2. **Both estates already live in one schema.** `pal_competencies` and `hpbrain_capabilities` are one `JOIN`
   apart. No cross-repo coordination is needed to *answer* the question.
3. **The real overlap is exactly two tables**, 24,003 rows vs 2,398 — not the sprawling duplication the row
   implies. The other 43 tables are empty or near-empty scaffolding.

**The exact question for whoever owns G2G/EB:** *are `hpbrain_capabilities` (2,398) and `pal_competencies`
(24,003) describing the same vocabulary at different grain, or genuinely different things?* If the former,
one is canonical and the other becomes a view. That is a half-day of SQL, not a migration project.

**Recommended status:** `Confirmed — overlap is real but far smaller than assumed (2 populated tables, not an
engine each), and KASBA is unbuilt. Reconcile at the data layer before building anything.`

---

## #14 — Event Bus

There is no pub/sub today worth the name. `EventServiceProvider.php:19-26` maps exactly **two** events, one
of them framework-supplied. `QUEUE_CONNECTION=sync`, so nothing is asynchronous; the `jobs` /
`failed_jobs` / `job_batches` tables exist with no producer.

**But an at-least-once delivery mechanism already runs in production**, and it is the right thing to attach
to rather than build beside: the `sync_log` outbox — **31,167 rows**, fed by database triggers plus an inline
controller flush, drained every minute by `neo4j:drain` under `withoutOverlapping(5)`, with a
`graph_synced_at` watermark that stamps only what the consumer confirmed.

**Recommended status:** `Open — design pull-based caching against the existing sync_log outbox. Do not build
a second bus.` Design brief in B4.

---

## #29 — no action

`[SUPERSEDED] Populate ~100 subject placeholders early` is explicitly superseded by **#30** (rollup counters
instead of individual empty cards). Nothing to do; recorded so the row is not re-opened.

---

## #11, #16, #21 — bounded by repo access

- **#11 (G2G / EB Phase-0 audits)** and **#21 (does EB's Settings already hold the AI console?)** need the
  Enterprise Brain codebase, which is not in this workspace. Blocked, with the exact ask stated.
- **#16 (Agentic Library migration)** is G2G-side. The G2G repo *is* present locally, but per the agreed
  scope we stay inside the two K-12 repos and read G2G only for comparison.

**What we can say from here:** the K-12 side has no agent-execution engine of its own to compete with G2G's,
so nothing on our side blocks that migration.
