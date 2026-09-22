# H5P text-passage types — implementation and test report

Drag the Words (`H5P.DragText`), Fill in the Blanks (`H5P.Blanks`) and Mark the
Words (`H5P.MarkTheWords`), added to the LMS across `next_lms_erp` (Laravel) and
`lms_k12` (Next.js).

Date of run: 2026-09-21 · Branch: `zeel` · Database: `vivek_erp` (`APP_ENV=local`)

---

## 1. Read this first: what "official H5P library" means here

This platform **does not embed the H5P PHP framework or the H5P JS player.**
Neither `h5p/h5p-core` nor an H5P player package is in `composer.json` or
`package.json`, and there are no `h5p_libraries` / `h5p_contents` tables. Every
H5P type the product ships — image hotspot, interactive video, flash cards,
multiple choice, drag and drop — is a **native implementation over its own
tables, rendered by the product's own player.** This is stated at the top of
`config/h5p_libraries.php` and predates this work.

The three new types follow that same architecture, which is what the brief asked
for ("follow the same architecture already used by existing H5P content types").
So two requirements from the brief need restating in terms of what was actually
built:

| Brief said | What exists |
|---|---|
| "Register and support the official libraries" | The official **machine names, versions and full dependency closures** are registered in `config/h5p_libraries.php` and written into every exported package's manifest. The library **code** is not hosted here. |
| "Auto-load all required dependencies" | An exported `.h5p` declares its dependency closure so an importing host (Moodle, Drupal, Lumi, h5p.com) resolves and loads the libraries from its own store. Nothing is auto-loaded *into this app*, because this app has no H5P runtime to load them into. |
| "Support content upgrades through H5P library updates" | Each row records the library and version its params were built for (`library` column), so rows written against an older minor can be found and rebuilt. Bumping a version is a one-line edit in `config/h5p_libraries.php`. There is no H5P upgrade-script runner, because there is no H5P framework. |

**Version numbers need one confirmation from you.** The registered versions —
`H5P.DragText 1.10`, `H5P.Blanks 1.14`, `H5P.MarkTheWords 1.11` — and their
dependency closures were written from the libraries' published `library.json`
declarations. Please confirm them against h5p.org before this goes to a host
that resolves versions strictly. Everything else in this report was executed.

---

## 2. What was built

### One storage family for three types

Drag and Drop got three tables because it is genuinely its own shape (a canvas,
positioned zones, positioned draggables). These three are not: **H5P itself
stores all three as one string** — a passage carrying inline `*answer*` markup.

```
H5P.Blanks        "Oslo is the capital of *Norway/Noreg:It is Nordic*."
H5P.DragText      "Oslo is the capital of *Norway*."   (+ distractors)
H5P.MarkTheWords  "The *dog* ran after the *cat*."
```

Same grammar, same answer semantics, three renderers. So they share one table
family with a `content_type` discriminator, and each gets its own PAL registry
row pointing at that table with a `where` filter — exactly how `multiple_choice`
already shares `lms_question_master`.

| Table | Holds |
|---|---|
| `h5p_text_activity` | passage, behaviour, feedback bands, scoring, status, media |
| `h5p_text_activity_blanks` | one row per answer slot, **derived from the passage on every write** |

The child table exists so the answer key is queryable (scoring, max score,
publish validation). It is re-parsed on every save and never edited
independently, so it cannot drift from the passage the learner is shown.

### Files

**Backend (`next_lms_erp`)**

| File | |
|---|---|
| `config/h5p_libraries.php` | + 3 library registrations with dependency closures |
| `config/pal_h5p.php` | + `drag_text` type; `fill_in_the_blanks` and `mark_the_words` promoted `planned` → `native`; `drag_text` mapped to the `activity_based` pedagogy |
| `database/migrations/2026_09_19_160000_create_h5p_text_activity_tables.php` | new |
| `database/migrations/2026_09_19_170000_sync_h5p_text_activity_registry.php` | new — re-publishes the registry (the DB registry is the runtime source of truth, so a config edit alone would be invisible) |
| `app/Models/lms/h5p/H5pTextActivity.php`, `H5pTextActivityBlank.php` | new |
| `app/Services/lms/H5P/H5PTextActivityBuilder.php` | new — the markup grammar, params for all three libraries, import parsing |
| `app/Services/lms/H5P/H5PPackageArchive.php` | new — the `.h5p` format, extracted from `H5PPackageService` |
| `app/Services/lms/H5P/H5PTextPackageService.php` | new — export/import for the three types |
| `app/Services/lms/H5P/H5PPackageService.php` | refactored onto the shared base; public API unchanged |
| `app/Http/Controllers/lms/h5p/H5PTextActivityController.php` | new — full lifecycle |
| `…/H5PDragTextController.php`, `H5PBlanksController.php`, `H5PMarkTheWordsController.php` | new — ~10 lines each |
| `app/Services/lms/Content/H5PContentAdapter.php` | + 3 sources with discriminator support |
| `app/Services/PAL/H5P/H5PModelRegistry.php` | `typeForTable()` made discriminator-aware |
| `app/Services/PAL/H5P/H5PRegistrySeeder.php` | `drag_text` added to owned types |
| `database/seeders/H5PTextActivitySampleSeeder.php` | new — 1 sample per type |
| `tests/Unit/H5PTextActivityBuilderTest.php` | new — 23 tests |
| `routes/lms.php` | + 36 routes (12 per type) |

**Frontend (`lms_k12`)**

| File | |
|---|---|
| `lib/h5p/text-activity-markup.ts` | the grammar, tokenising, word bank, author-facing problems |
| `lib/h5p/text-activity-scoring.ts` | scoring for all three, feedback bands |
| `lib/h5p/*.test.ts` | 37 tests |
| `app/h5p/data/h5p.ts` | + types, API surface, route map entries |
| `app/h5p/text_activity/components/editor.tsx` | shared authoring UI |
| `app/h5p/text_activity/components/player.tsx` | shared learner UI, all three renderers |
| `app/h5p/text_activity/components/screens.tsx` | shared list / create / edit / view |
| `app/h5p/h5p_drag_text/`, `h5p_blanks/`, `h5p_mark_the_words/` | 12 route files, ~5 lines each |
| `app/h5p/html_contents/page.tsx` | + 3 card icons |

---

## 3. Test results

### 3.1 Library registration — verified

```
$ php artisan pal:h5p-registry-sync --verify

  source: database
  H5P types: 22 (8 natively implemented)

  Native type → source table
    image_hotspot        h5p_scenarios
    interactive_video    h5p_interactive_video
    multiple_choice      lms_question_master
    flash_cards          h5p_flashcard
    drag_and_drop        h5p_drag_drop
    drag_text            h5p_text_activity
    fill_in_the_blanks   h5p_text_activity
    mark_the_words       h5p_text_activity

  Every natively implemented type has its table present.
```

The first run of this reported **`drag_text` — no pedagogy is authored against
it**, meaning its xAPI events would derive a null `pedagogy_tag` and the type
would drop out of the §9 coverage matrix. Fixed by adding `drag_text` to the
`activity_based` pedagogy, alongside `drag_and_drop` and `mark_the_words`. Re-run
is clean (only `summary` remains, a pre-existing non-native type).

### 3.2 Migrations — run

Both ran against `vivek_erp` with `--path`, so the four unrelated pending
migrations on this database were left alone:

```
2026_09_19_160000_create_h5p_text_activity_tables .......... DONE
2026_09_19_170000_sync_h5p_text_activity_registry .......... DONE
```

### 3.3 Full lifecycle — 45/45 passed

Executed against the real database through the real models and services, then
every row removed. Per type: create → derive answer key → build params →
publish → export `.h5p` → inspect the archive → re-import → refuse a
wrong-type package.

```
FILL IN THE BLANKS (H5P.Blanks)          14 checks, all passed
  export: verify-fill-in-the-blanks-1.h5p, 1202 bytes, 6 declared libraries
  alternatives stored: Norway|Noreg      tip stored: "It is a Nordic country"

DRAG TEXT (H5P.DragText)                 14 checks, all passed
  export: verify-drag-text-2.h5p, 1127 bytes, 7 declared libraries
  2 answers + 2 distractors; distractors do not raise the max score

MARK THE WORDS (H5P.MarkTheWords)        13 checks, all passed
  export: verify-mark-the-words-3.h5p, 1070 bytes, 6 declared libraries

GUARDS                                    4 checks, all passed
  an unmarked passage yields no answer key, and is worth nothing
  an id is invisible under the wrong type (404, not the wrong editor)
  verification rows removed (4 created, 0 left)

RESULT: 45 passed, 0 failed
```

Wrong-type import is refused with a message that names where the package *does*
belong: *"That is a Mark the Words package. Import it from the Mark the Words
list instead."*

### 3.4 Unit tests

| Suite | Result |
|---|---|
| `tests/Unit/H5PTextActivityBuilderTest.php` (new) | **23 passed** |
| `tests/Unit/H5PDragQuestionBuilderTest.php` (existing, covers the refactored package service) | **8 passed** — no regression |
| `lib/h5p/text-activity-markup.test.ts` (new) | **17 passed** |
| `lib/h5p/text-activity-scoring.test.ts` (new) | **20 passed** |
| `npm test` (whole frontend suite) | 324 tests, 322 passed, **2 failed** |

The 2 failures are in `lib/ai/ai-capabilities.test.ts` and **pre-exist on a
clean tree** — confirmed by stashing all of this work and re-running (8 passed /
2 failed, unchanged). They are unrelated to H5P.

### 3.5 Routes — 36 registered

12 per type: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`,
plus `publish`, `duplicate`, `import`, `media`, `export`. The non-id routes are
declared before each `Route::resource`, or `show` would swallow `import` and
`media` as ids.

### 3.6 Static analysis

| Check | Result |
|---|---|
| `php -l` on every new/changed PHP file | clean |
| `npx tsc --noEmit` | clean (excluding a pre-existing corrupt `.next/dev/types/` build cache) |
| `npx eslint` on all new frontend code | clean |

### 3.7 Not executed

- **Browser testing.** The authoring and player UIs were typechecked and linted
  but not clicked through in a browser. Worth a manual pass, particularly the
  Drag the Words click-to-place path and the Mark the Words token hit areas.
- **Sample seeder.** `H5PTextActivitySampleSeeder` is written and its parsing
  path is covered by §3.3, but it could not be run: `app/Console/Kernel.php:144`
  blocks any artisan command matching `db:seed|schema|fresh|refresh`
  **unconditionally**, despite the message saying "in production" and this
  environment being `local`. To seed:

  ```
  H5P_SAMPLE_TENANT=1 H5P_SAMPLE_STANDARD=43 H5P_SAMPLE_SUBJECT=3975 \
  H5P_SAMPLE_CHAPTER=1012 php artisan db:seed --class=H5PTextActivitySampleSeeder
  ```

  That guard looks like it wants an `app()->environment('production')` check
  around it; it is outside this change's scope, so it was left alone.
- **A package produced by real H5P.** Round-trip was tested against this
  system's own exports. Importing a `.h5p` authored in Lumi or h5p.com is the
  test that proves interoperability end to end and is worth doing before
  release.

---

## 4. Behaviour worth knowing

### Export flattens what a library cannot express, and says so

Only `H5P.Blanks` renders the full grammar. The other two cannot:

| | alternatives (`/`) | tips (`:`) |
|---|---|---|
| `H5P.Blanks` | yes | yes |
| `H5P.DragText` | **no** | yes |
| `H5P.MarkTheWords` | **no** | **no** |

The product's own player honours alternatives on all three, because a teacher
who typed them meant them. But an export has to produce something the real
library will load, so `build()` flattens what the target cannot express and
**reports every flatten as an export warning** rather than shipping a package
that quietly marks answers wrong. The count comes back on the
`X-H5P-Export-Warnings` response header and is shown to the author.

### Mark the Words subtracts for wrong marks

`H5P.MarkTheWords` scores `correct − incorrect`, floored at zero. This is the
library's rule, not a house rule, and without it clicking every word in the
passage scores full marks — the first thing a class discovers. It is pinned by
a test and explained in the player's score line when it costs the learner marks.

### An unmarkable activity is reported, not scored zero

An author can save a passage that marks nothing. That activity is not "worth
zero" — it is unmarkable, and telling a learner `0/0` says they got it wrong
when nothing was ever right. Publish is refused server-side, and the player says
so plainly instead of showing a score.

### Answers are marked in the browser

The answer key ships with the activity, so a determined learner can read it out
of the network tab. **This is true of every H5P type this platform ships and is
inherent to the format** — H5P `content.json` contains its own solutions and the
official player marks client-side too. What protects a graded assessment is the
xAPI record and the teacher's own marking, not the player. Flagged so nobody
reads these types as tamper-proof.

### Dragging is never the only way

Drag the Words supports click-to-place as well as dragging: pick a word, then
pick a blank. Drag-only would exclude keyboard users, most touch users on a
small screen, and anyone with a motor impairment — and the activity is about
vocabulary, not dexterity.

---

## 5. LMS integration and analytics

**Content list.** All three are registered in `H5PContentAdapter::SOURCES` with
`content_type` discriminators, so they surface in the chapter content list under
`format = 'h5p'` / category `H5P Interactive`, reaching every LMS module that
reads that list (courses, lessons, topics, learning activities, homework,
worksheets, assessments, practice). Each is `published_only`, so a draft never
leaks into a student-facing surface through any consumer of that list.

**Discriminators matter here.** Without them all three would surface the same
rows three times over, each under the wrong label and deep-linking to an editor
that would 404 on the id.

**xAPI.** The player posts `attempted` on open, then `answered` and `completed`
on check, with `success`, `score`, and duration. Object ids are
`drag_text:<id>`, `fill_in_the_blanks:<id>`, `mark_the_words:<id>` — the registry
codes, which is what `H5PXapiPipeline` resolves against. Because the three types
are registered natively with source tables and `where` filters,
`H5PContentRepository::node()` resolves them and the pipeline enriches events
with chapter, concept, Bloom level and pedagogy automatically. No pipeline
change was needed. Attempt count, completion, success, score, max score,
percentage, time spent and timestamps all flow through the existing PAL
telemetry path used by the other assessment types.

---

## 6. Follow-ups

1. **Confirm the library versions** against h5p.org (§1).
2. **Browser-test** the authoring and player screens (§3.7).
3. **Import a package authored in real H5P** to prove interoperability (§3.7).
4. **Run the sample seeder** once the `db:seed` guard is sorted, or relax that
   guard to check the environment it names (§3.7).
5. Consider whether `H5PDragDropController` should also get the `duplicate`
   endpoint the three new types have — teachers building a set of variations
   want it there too.
