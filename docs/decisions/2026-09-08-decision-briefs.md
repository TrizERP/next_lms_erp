# Decision briefs — the rows that need a human call

**Covers:** the 4 `Needs Team Decision` rows, the legal-review row, and the 2 design-only deliverables
**Date:** 2026-09-08 · **Phase:** B4

Each brief: what the decision is, what the code actually shows, a recommendation, and what it blocks.
**No code was written for any of these.**

---

## #20 — Fields Configuration: module-local, or a 10th engine?

**The decision:** Fields Configuration (schema / custom-field management) matches none of the 9 platform
engines. Keep it module-local, or add a 10th engine once a second module confirms the same need.

**What the code shows:** it already exists and is already module-local —
`app/Http/Controllers/api/CustomFieldApiController.php`, consumed by the admission module
(`admissionEnquiryController`, `admissionFormController`, `admissionRegistrationController`,
`admissionRegistrationAPIController`). **One consumer family. No second module has asked for it.**

**Recommendation: keep it module-local. Do not create a 10th engine.**

The tracker's own rule #8 — *"don't extract a universal engine until 2+ modules genuinely need the same
behaviour"* — is not satisfied by one module. Extracting now would repeat the pattern that turned a 60-day
estimate into 90-100 days.

What *is* worth doing cheaply: give it a **closed, config-declared vocabulary** the way
`config/pal_content.php` + `PalVocabulary` does, so that if a second module ever adopts it, the field-type
list is already a contract rather than scattered strings. That is a day's work and makes the eventual
extraction mechanical.

**Blocks:** nothing today. Revisit when a second module asks.

---

## #42 — the "Enrichment" naming collision

**The decision:** two concepts share the name. A per-concept extension activity, and a subject-category tier
overlapping "Future Capabilities".

**What the code shows:** the name is **already taken by the first meaning** —
`app/Services/PAL/ContentModel/ContentModelEnrichmentService.php` and the `pal_cm_enrichment` table both mean
*per-concept enrichment*.

**Recommendation: adopt the sheet's own proposal — "Enrichment" = the per-concept activity; "Future
Capabilities" = the subject-category tier.** The code has already voted, and renaming a live service plus a
table to free up a word for a tier that has not been built would be pure cost.

Add one rule to prevent the next collision: **any service whose noun is shared across modules carries a
module prefix.** `ContentModelEnrichmentService` already follows it; the catalog tier should be
`CatalogFutureCapabilities…`, never `EnrichmentService`.

**Blocks:** the Course Catalog tier work (a teammate's sheet). Cheap to settle now, expensive after both are
built.

---

## #53 — PAL vs Examination item isolation

**The decision:** a board-reserved exam item must be explicitly excluded from PAL's adaptive pool. "This must
be a defined rule, not an implicit assumption."

**What the code shows — the premise does not hold yet:**

- `pal_question_metadata.assessment_type` exists but is non-null on **0 of 2,209** rows.
- The discriminator actually in use is `question_paper.exam_type = 'PAL'`, applied consistently across
  ~12 call sites, and Examination already excludes it explicitly
  (`LmsResultDashboardApiController.php:55` — `EXCLUDED_EXAM_TYPE = 'PAL'`).
- **PAL's selection is `palController.php:987-1012` `getRandomPalQuestions()`, ending in
  `->inRandomOrder()->take($limit)`.** It reads no difficulty, no IRT, no `assessment_type`.

So there is no shared "Calibrated Assessment Bank" for the two to collide over, and PAL is not selecting
adaptively in the first place. **The exam-integrity risk the row describes is real but not yet reachable.**

**Recommendation — in this order:**
1. **Populate `assessment_type`** (the column exists; nothing writes it). This is the flag the rule needs.
2. **Add an `exam_reserved` boolean** to `pal_question_metadata`, defaulting false.
3. **Add one `where` clause** to `getRandomPalQuestions()` excluding reserved items — a rule in code, not an
   assumption.
4. Only then is the isolation rule meaningful, because only then is there a shared pool.

**Blocks:** nothing today, precisely because PAL is not adaptive. It becomes urgent the moment #50 is acted
on — calibrating the bank is what creates the shared pool this rule guards.

---

## #66 — within-grade pacing

**The decision:** can a student move faster than their grade cohort? Flagged so it is chosen, not defaulted.

**What the code shows:** the catalog is grade-tiered throughout (`standard_id` on every content, question and
chapter row; `sub_std_map` gating subjects per standard). Nothing implements pace-based progression, and
`pal_learner_states` is empty — so no student has a mastery state to pace against.

**Recommendation: decide "paced within the grade boundary", but do not build it yet.**

The board-compliance constraint is real: an Indian K-12 board expects a grade's syllabus to be covered.
Alpha School's ungraded model is not available to a board-compliant product. But *within* a grade, letting a
student who has demonstrated mastery move ahead costs nothing in compliance terms and is the whole point of
adaptivity.

State it as a decision so it does not default by accident — but the prerequisite is a working mastery signal,
and per #50 there is none (0 calibrated items, `inRandomOrder()` selection). **Deciding is free; building is
blocked behind #50.**

**Blocks:** the Grade Enablement tier on the Course Catalog sheet.

---

## #69 — vision / camera monitoring · **legal review gate**

**The decision:** screen-observation or camera capture requires DPDP Act review before *any* engineering.

**What the code shows:** nothing. No camera, vision, screen-capture or proctoring code exists in either repo.
Correctly untouched.

**Recommendation: no engineering, and no scoping either.**

This is the one row where the right action is to write nothing. The decision explicitly says legal review
comes **before scoping**, and scoping is itself engineering work — estimating it would put the cart first.
Continuous monitoring of a minor is not an ordinary product decision, and India's DPDP Act treats a child's
data as a special category requiring verifiable parental consent.

**Recommended status: unchanged — `Frozen, legal review required`. Do not add it to any sprint.**

---

## #5 — Scheduler idempotency *(design deliverable)*

**Correcting an earlier note of ours: this is NOT greenfield.** A working at-least-once pattern is already in
production.

**What exists:**
- `Kernel::schedule()` runs **4 tasks**, every one using `withoutOverlapping()` — with a comment at `:33-41`
  recording a real 2026-08-21 incident where the default 1440-minute mutex silently disabled a sync for a day.
- **`sync_log` — 31,192 rows**, fed by database triggers plus an inline controller flush, drained every minute.
- A **watermark**: `whereNull('graph_synced_at')->orWhereColumn('graph_synced_at','<','updated_at')`
  (`Kernel.php:91-92`), stamping **only rows the consumer confirmed** (`:104-107`), so failures stay owed.

That watermark *is* the "entity dedup key" #5 asks for. It is just Neo4j-specific.

**What is missing:** a **job-run ledger**. `withoutOverlapping()` is a mutex, not a record — "did pass N
complete?" is unanswerable today. And `QUEUE_CONNECTION=sync`, so `app/Jobs`' two jobs run inline.

**Recommendation — generalise, don't invent:**
1. `job_runs (job_key UNIQUE, entity_dedup_key, status, started_at, finished_at, attempts, payload_hash)`.
2. A small trait supplying `jobRunKey()`, claiming a row before execution and releasing after.
3. **Give it real consumers immediately**, or it fails its own #58 test. The three obvious ones already exist
   and are exactly the jobs you never want running twice:
   `lms:backfill-content-provenance`, `lms:project-concept-intelligence`, and — most valuably —
   **`pal:derive-irt`**, which per #50 has never been scheduled at all.

That last point matters: **#5 and #50 solve each other.** Scheduling `pal:derive-irt` through an idempotent
runner discharges the ops half of #50 and gives #5 its first real consumer.

---

## #14 — Event Bus *(design deliverable)*

**The decision:** design pull-based caching first; add push only for RBAC and AI Policy propagation.

**What exists:** almost no event infrastructure — `EventServiceProvider` maps **2** events, one of them
framework-supplied; `QUEUE_CONNECTION=sync`; the `jobs` tables have no producer.

**But `sync_log` is already an outbox** — 31,192 rows, trigger-fed, drained every minute, with a watermark.
It is the only mechanism in the codebase that reliably captures "something changed" *including* for writes
that never pass through a controller (imports, bulk edits, raw SQL).

**Recommendation:**
1. **Do not build a second bus.** A parallel event system beside a working outbox is the duplication this
   tracker exists to prevent.
2. **Pull-based caching first, as the decision says.** The concrete first cache: the chapter content list,
   keyed `(chapter, tenant, resourceType)`, invalidated on authoring write. Real screen, real benefit,
   measurable.
3. **Event shape, when push is eventually justified:**
   `{event_name, entity_type, entity_id, tenant_id, payload, occurred_at, dedup_key}` — deliberately the same
   shape `sync_log` already carries, so the outbox becomes the transport rather than a competitor.
4. **Push only for RBAC and AI Policy**, per the decision, and only once a second product genuinely consumes
   it. Today K-12 is the only consumer, so the 2+-consumer bar is not met.

**Recommended status: `Open — pull-cache first, attach to sync_log, do not build a second bus.`**
