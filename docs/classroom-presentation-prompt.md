# Reusable prompt: generate Classroom Presentations (study decks)

Paste the block below into Claude Code (working directory `next_lms_erp`, with `lms_k12` as an additional directory) and fill in the four
`<<...>>` values. It drives the existing pipeline that produced Std 9 Science chapters 8592 (Chapter 1, content_master 61401) and
8593 (Chapter 2, content_master 61406). It adds no pipeline: it is the checklist and guard rails around these commands.

    lms:generate-study-deck  ->  (--replay --recheck-images)  ->  lms:store-study-deck

Facts it relies on (verified in code on 2026-10-16, see `docs/study-deck-run-options.md`):

* One run = one chapter. Topics and concepts are inside the deck (planner works through the chapter's topics and concepts);
  the pictures are searched/drawn per slide.
* `lms:store-study-deck` writes ONE `content_master` row per chapter (Classroom Presentation, `file_type=pptx`, `source=Claude AI`,
  `standard_id`/`subject_id`/`chapter_id` from the chapter row). `topic_id` and `concept_id` are NULL by design: the topic and concept
  structure lives in the sidecar `.deck.json`, not in columns. Storing a *changed* deck is a new row and hides the old one.
* Pictures live in `study_deck_images`, not as files.

---

## The prompt

```
Generate Classroom Presentations for the Classroom Resource module using the EXISTING study-deck pipeline
(lms:generate-study-deck -> lms:store-study-deck). Work in next_lms_erp. Do not build a new pipeline, do not fork or duplicate
logic, and do not change code unless you hit a genuine bug (stop and tell me first if you do).

SCOPE
  Tenant (sub_institute_id): <<1>>
  Standard:  <<e.g. 9>>
  Subject:   <<e.g. Science>>
  Chapters:  <<chapter_master ids, or "sort_order 3-5", or "all">>

REFERENCE: read docs/study-deck-run-options.md and the doc comments of GenerateStudyDeckCommand and StoreStudyDeckCommand first.
Std 9 Science chapters 8592 and 8593 are the reference for what a good result looks like.

1. RESOLVE (read-only SQL, no writes)
   - From chapter_master (standard_id, subject_id, sort_order, syear, sub_institute_id) resolve my scope to concrete chapter ids.
     "Chapter N" means chapter_master.sort_order = N. Show me the table: id, sort_order, name, #topics, #concepts, #bank questions.
   - For each chapter list any existing visible Classroom Presentation in content_master (content_category='Classroom Presentation',
     show_hide<>0). Storing a changed deck HIDES that row, so list it and skip chapters that already have one unless I said to replace.
   - Skip, and report, any chapter with no concepts or no chapter text.

2. PREFLIGHT
   - Executor: api needs an Anthropic key for the tenant; otherwise use --executor=cli (CLAUDE_CLI_BINARY = the VS Code
     extension's claude.exe). Say which you use.
   - Run chapters ONE AT A TIME (a run is ~25-65 min; redirect output to storage/app/study-deck/gen<id>.log, run in background).
     Never edit PHP files while a run is in progress.

3. GENERATE (per chapter)
   php artisan lms:generate-study-deck <id> --tenant=<T> --executor=<api|cli> [limits]
   Limits: defaults for a small chapter (<~30 concepts). For a large one use what 8593 used (47 concepts):
   --max-practice=24 --per-slide=2 --max-visuals=18 --max-scenarios=4. Scale proportionally; do not exceed what the concept count justifies.
   Then: php artisan lms:generate-study-deck <id> --replay --recheck-images (same limits again), so soft/blurred photographs fall back to
   the planned diagram.

4. VERIFY before storing (all must hold; otherwise fix by replay or report, do not store)
   - report.json ok=true, no errors; read the warnings.
   - Slides within 30-35; EVERY concept in concepts.json is taught by some slide; topics appear in the chapter's own order.
   - Every learning slide has an interaction; pictures are relevant. LOOK at the photographs (relevance is judged on titles/tags
     only; ones like a mislabelled micrograph or a screenshot have slipped through before) and replace bad ones via --images-from.
   - Photographs are CC0/PDM/BY/BY-SA with credit on the slide; nothing else.
   - Facts come from the chapter text and the school's own concept data, not from outside knowledge.

5. STORE AUTOMATICALLY once step 4 passes (this is my standing authorisation for chapters in the scope above, nothing wider)
   php artisan lms:store-study-deck <id> --tenant=<T> --dry-run   -> expect "would_create" and 0 missing pictures
   php artisan lms:store-study-deck <id> --tenant=<T>
   Then verify with read-only SQL that the content_master row has: content_category='Classroom Presentation', file_type='pptx',
   show_hide=1, correct standard_id / subject_id / chapter_id / sub_institute_id / syear, and that its .deck.json and PDF exist.
   Writes only to content_master, the deck/PDF files and study_deck_images. No h5p_* rows, no question-bank rows, no new tables.
   If a chapter fails validation or the store, do NOT hand-edit rows: report it and continue to the next chapter.

6. NEVER
   - run study-deck:images-prune (it can delete other people's pictures), unstore anything, or touch rows that are not this run's
   - run migrations, commit, or push
   - fabricate a picture source, licence, or fact to get past a gate

7. REPORT (plain, per chapter): chapter id/name, slides, concepts covered, #photos vs #drawn diagrams, interactions, practice
   questions placed, the content_master id, the player URL /student/study-deck/<id>?content=<content_id>, the undo command
   (lms:unstore-study-deck <content_id> --images), and everything weak or unverified (see "known weak" below). Do not claim a
   check you did not run.
```

---

## Notes for you (not part of the prompt)

* **Topic/concept association.** Your message was cut off at "...chapter, topic,". With the existing mechanism the row is
  associated with standard, subject and chapter; topics and concepts are in the deck file, and `topic_id`/`concept_id` stay NULL.
  If you want one `content_master` row per topic or concept, that is a change to `publishStudyDeck` and the Classroom Resource
  list, not a reuse of the pipeline. Say so and I will scope it separately.
* **Auto-store is a real write** to the shared `vivek_erp` database and the object store, which is why step 5 names its authority
  and limits it to the scope you fill in. For a first run on a new subject, set Chapters to one chapter and review before widening.
* **Other subjects are untested.** Only Std 9 Science has been run. Maths, languages and social science may get fewer usable
  photographs (the planner falls back to drawn diagrams) and fewer diagram-friendly concepts; do a one-chapter pilot first.
* **Known weak, inherited from 8592/8593** (the report should repeat these where they apply): placed bank questions are
  "extracted" rows with an unconfirmed licence and `pending` validation; semantic-intelligence matching of concepts is thin, so
  per-concept misconceptions often do not reach the generator; photograph relevance needs a human look; this is the deck's own
  player, not the official H5P runtime; unreferenced pictures from discarded runs stay in `study_deck_images`.
* No batch command exists, so the prompt loops chapter by chapter. A wrapper command would be new code; not added.
