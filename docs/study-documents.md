# Study documents: revision notes, remedial classes and classroom activities

Three kinds of content a teacher already asks the content drawer for, written the way a study deck is: from the chapter's
own data, checked before anything is stored, stored as one `content_master` row with a PDF, and opened in place by the
student and teacher interface with an online practice beside the PDF.

| Kind (`DocumentKind`) | Library category (`content_category`) | File name starts with | Cover | Stored copy | Other copy (drawn on request) |
|---|---|---|---|---|---|
| `revision_notes` | Revision Notes | `study_revision_notes_` | one card per concept, key terms, important questions with answers, a checklist | answers shown | answers hidden |
| `remedial` | Remedial Class | `study_remedial_class_` | one unit per concept: what you need first, plain words, small steps, a worked example, mistakes, practice that gets harder, what to revisit | answers shown | answers hidden |
| `activities` | Classroom Activity | `study_classroom_activity_` | a run sheet, then each activity: objectives, materials, teacher steps with times, student steps, quiz, discussion, assessment | teacher edition | student handout |

Code: `app/Services/StudyDeck/Documents/` (the pipeline), `ContentGenerationService` (dispatch, storage, the PDF), the three
`lms:*-study-document` commands, and `StudyDeckApiController` (reading). Frontend: `lms_k12/docs/study-document-viewer.md`.

## What is reused and what is new

Nothing here is a second pipeline. A study document is the study deck's pipeline with the middle swapped.

| Reused unchanged | New |
|---|---|
| `ConceptContextBuilder` (chapter, topics, concepts, concept intelligence, prerequisites, bank questions) | `Writers/*`: the model's wording for each kind |
| `QuestionSelector` (only self-contained bank questions) | `QuestionPlacement`: which bank question goes where |
| `LearningPlanBuilder` (teaching order, prerequisites first) | `DocumentAssembler`, `DocumentHtmlRenderer`, `DocumentValidator` |
| `Completer` (API or dev CLI), `ExtractsJson` | `StudyDocumentPdfRenderer` (extends the deck's) |
| `ImagePlanner` + `DiagramRenderer` (diagrams drawn by code) | `DocumentKind`, `StudyDocumentService` |
| `InteractionPlanner::check()` (hotspots, matching and ordering rules) | the three commands, the API reads |
| `DeckValidator` markup and grounding checks | |
| `StudyDeckPublisher`, `StudyDeckImages` (pictures live in the database), `StudyDeckImageUrls` | |
| `StudyDeckPdfRenderer`, Dompdf, `generatedContentCss()`, the running header and footer | |
| `POST /api/lms-study-deck` and `/pdf`, tenant rule, `content_master`, `study_deck_image_links` | |

**No new table, no new column, no new route.** The kind is the row's file name (nothing else is named
`study_<kind>_<chapter>_<scope>_<hash>.pdf`) and it must agree with the row's category, so it is never guessed from a title.
Shared classes got small, behaviour-preserving hooks only (visibility, a `unitName()` seam, a `$lead` parameter, two public
validator checks); the deck's own tests are unchanged and pass.

## The pipeline

```
chapter rows (read only) -> self-contained bank questions -> teaching order
  -> QuestionPlacement (which bank question where)         deterministic
  -> Writer (the model's wording, in chunks, one repair)   the only model calls
  -> DocumentAssembler (numbering, diagrams, hotspots)     code
  -> DocumentHtmlRenderer (design-system markup)           code
  -> DocumentValidator                                     code
  -> report {ok, errors, warnings, stats}
```

* Questions are **never written by the model**. Every question in a document is a row of the chapter's question bank,
  chosen by `QuestionPlacement` (revision: at least one per concept, a second for harder concepts; remedial: up to three,
  easiest first, so practice rises through level 1 "with a hint" to level 3 "on your own"; activities: each question used
  once across the document). The model writes only what surrounds them (a hint, why a wrong choice is wrong, when in the
  activity to use them), and is never shown a question's database id.
* Diagrams are **drawn by code** from a spec the model proposes; every word in a picture is a word the spec named, and its
  alt text is true by construction. A diagram's parts become hotspots only when every label has an explanation that passes
  the deck's own rules.
* **Grounding.** Numbers in the wording must appear in the chapter text (an error); capitalised names that do not are a
  warning to review. Procedure numbers in an activity (group sizes, minutes) are checked separately. Misconceptions must be
  ones the concept data lists. Bloom and DOK come from the concept intelligence, not the model's guess, so the range a
  document spans is the range the chapter's data describes.
* **All concepts, including the first.** Validation fails when a concept has no part, when the number of parts is wrong, or
  when a part's numbering is not its position (part 0 is the overview).
* A document that fails validation is **not stored**. `lms:generate-study-document` still writes the bundle so a person can
  see why.

## Storage

One `content_master` row per document, filed under its category, `file_type = pdf`, `show_hide = 1`, `meta_tags = null`
(the concept tagger and the video scorer read that column as text). The row's own file is the PDF. Beside it, named after it:
`<name>.pdf.doc.json`, the structured source the online practice and the PDF are both drawn from. `description` holds the
design-system markup, so the document is readable straight out of the database.

* The name carries a hash of the content and a scope (`all`, or `p<hash>` for a document limited to some concepts). Storing
  the same document again finds its row and changes nothing; a changed one is a new row and hides the one it replaces
  (same kind, chapter, school and scope). Nothing is deleted; `show_hide = 0` is reversible.
* Everything that can fail happens before a row exists (PDF drawn and checked, source stored and read back); a failure removes
  what this run stored. The row, the hiding of the old one and the picture links land in one transaction.
* Pictures are rows of `study_deck_images` (shared by checksum, one per school), linked to the document in
  `study_deck_image_links` so `study-deck:images-prune` never removes a picture a stored document uses.
* A school sees its own documents and, when it has the LMS, the platform library's (school 1): the same rule as the
  Classroom Resource list.

## Reading and opening

`POST /api/lms-study-deck` with `chapter_id` + `content_id` returns the structured source (`kind`, `data`), pictures already
turned into signed addresses. `POST /api/lms-study-deck/pdf` returns the PDF: the stored copy, or with `variant=practice` the
other copy drawn on request (not kept anywhere). `disposition=inline` asks for it to be shown where the reader already is
(anything else is a download). A document is found **only by its content id**, never as "the latest", and only when the
file name and the category agree on the kind; otherwise the answer is 404, exactly as for a missing deck.

The chapter-content list adds `study_doc_kind` and `pdf_url` to a row that really is a study document, and nothing else.

## PDF and online: what runs where

A PDF cannot run H5P, flip a card or mark an answer, and this does not pretend it can. The PDF writes every interactive part
out in full in print form and says on the cover where the online version is; the interface opens that version from the same
stored document, using the study deck's own interaction views and question players (so no H5P row is created either).

| Element | In the PDF | In the app (Try it online) |
|---|---|---|
| Multiple choice, true/false, fill the blank, drag text, mark the words, flashcard, essay | the question, its options, and (answers-shown copy) the answer and the reason | the shared question player: answered, marked, explained |
| Matching | a two-column exercise: solved for the teacher, lettered with blanks for the student | the deck's matching screen |
| Ordering | the correct order (teacher) or scrambled with blanks (student) | the deck's ordering screen |
| Diagram with explained parts | the picture with numbered parts and what each one says | hotspots: select a part, read it |
| Steps, worked example, mistakes, cards | written out in full | opened one at a time |
| Key terms | a table | flashcards, "I know this" |
| Checklist | boxes to tick on paper | tickable, remembered in this browser |
| Hints, why the other choices fail | printed (answers-shown copy only) | hint on request; reasons after answering |
| Discussion | prompt, and the possible answer for the teacher | prompt, answer on request |
| Reflection | lines to write on | a text box, kept in this browser only |

Layout rules: a title block, a contents page, running header and footer with "Page n of N" (not on the cover), no part starts
a new page, headings keep their first content with them, a table's header never ends a page alone, a question is kept whole
with its answer and its reason, and each kind has its own accent colour on the same design system.

## Operating it

```
php artisan lms:generate-study-document 8592 revision_notes            # writes a review bundle, both PDFs; stores nothing
php artisan lms:generate-study-document 8592 remedial --concepts=101,102
php artisan lms:store-study-document 8592 revision_notes --dry-run     # what would happen
php artisan lms:store-study-document 8592 revision_notes               # store exactly what was reviewed
php artisan lms:unstore-study-document <content_id> [--images] [--dry-run]
```

Bundle: `storage/app/study-deck/chapter-<id>/<kind>/` (`document.json`, `draft.json`, `report.json`, `learning-map.json`,
`questions-excluded.json`, `out/<kind>.html` and the two PDFs). **Generating writes the diagrams into `study_deck_images`**
(pictures are database rows, never files; the deck's command does the same); the ones of a run that is never stored are
removed by `study-deck:images-prune`. `--executor=cli` is the dev-only path with the tools disabled.

From the content drawer: `POST lms/gamma-content-master` and `POST api/intelligence/content/generate` hand "Revision Notes",
"Remedial Class" and "Classroom Activity" to this pipeline when `claude.chapter_ids` serves the chapter. Set
`CLAUDE_STUDY_DOCUMENTS=false` (`config/claude.php`: `study_documents`) to go back to the earlier single-prompt documents for
every chapter at once, with no deploy.

## Limits, decisions and honest caveats

* **Generation is slow and synchronous.** A whole 32-concept chapter is 8 to 12 model calls per kind: about 14, 19 and 19
  minutes with the dev CLI executor (revision notes, remedial, activities). Through the drawer that exceeds the drawer's
  10-minute client timeout and many proxies; `ignore_user_abort(true)` lets the server finish and store the document, which
  then appears in the list. For a large chapter use the artisan command. A queue would be the proper fix and is a deliberate
  non-goal here.
* **Teacher editions are not secret.** The content endpoints are not authenticated per learner (as for every other content
  type), so the student interface only chooses defaults and hides the teacher edition from its own menu. It does not enforce.
* **Diagrams are drawn, not photographs.** A drawn diagram has labelled boxes and arrows. Documents never search for a
  photograph (only the deck's slides do), so a part with nothing worth drawing has no picture rather than a loosely related one.
* **Wording is the model's.** The checks catch numbers, structure, coverage, listed misconceptions and unsupported names; they
  cannot prove every sentence. Read the PDFs of a new chapter before storing (that is what the review bundle is for).
* A narrowed document (some concepts) is written by the same pipeline and filed under its own scope; the drawer does not
  offer narrowing yet, the API (`concept_names` on the gamma endpoint, `concept_ids` on the intelligence endpoint) does.

## Tests

`tests/Unit/StudyDeck/Documents/` (no live database: SQLite in memory for the picture tables, `Storage::fake`, the model replaced
by a script of replies): pipeline for all three kinds, PDF markup and drawing, publishing and every failure path, the API
(tenant rule, kind/category agreement, inline and download, the other copy), the three commands end to end, storage rules (the
pipeline writes no database row, no H5P row, no object), `DocumentKind`, and the golden contract
`tests/Fixtures/study-documents-golden.json` that the frontend loads too (`UPDATE_GOLDEN=1` regenerates it; copy it to
`lms_k12/lib/study-document/fixtures/`).

```
vendor/bin/phpunit tests/Unit/StudyDeck
```
