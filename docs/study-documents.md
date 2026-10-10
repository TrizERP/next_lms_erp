# Study documents: revision notes, remedial classes and classroom activities

Three kinds of content a teacher already asks the content drawer for, written the way a study deck is: from the chapter's
own data, checked before anything is stored, stored as one `content_master` row with a PDF, and opened in place by the
student and teacher interface with an online practice beside the PDF.

| Kind (`DocumentKind`) | Library category (`content_category`) | File name starts with | Cover | Stored copy | Other copy (stored beside it) |
|---|---|---|---|---|---|
| `revision_notes` | Revision Notes | `study_revision_notes_` | a sheet for each topic (a line for every concept, a comparison table, what not to confuse, facts to recall), the key terms, a few exam-style bank questions. **Teaches nothing.** See *Purpose-based design* | answers shown | answers hidden |
| `remedial` | Remedial Class | `study_remedial_class_` | a short check, a table of potential difficulties, lessons for only the concepts that may be hard (worked example, hinted practice), the common mix-ups, practice on your own, an exit check, a teacher's guide. **Reteaches; does not summarise.** See *Purpose-based design* | answers shown (with the teacher's guide) | answers hidden |
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
`<name>.pdf.doc.json`, the structured source the online practice and the PDF are both drawn from, and `<name>.pdf.practice.pdf`,
the OTHER copy (answers hidden / student handout), drawn once when the document is stored so that opening it is a read and
never a render. `description` holds the design-system markup, so the document is readable straight out of the database.
A document stored before the other copy was kept is brought up to date by storing the same bundle again (`repaired`); until
then the API draws its other copy on request, as it used to.

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
other copy read from storage (drawn on request only for a document stored before it was kept). `disposition=inline` asks for it to be shown where the reader already is
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

## Purpose-based design: revision notes and a remedial class are different documents

**The defect this replaced.** Both documents were "one part for every concept", written from the same learning-map record
(definition, knowledge, objectives, misconceptions, real-world uses) with the same questions and the same diagrams. A revision
note and a remedial unit for the same concept restated the same three examples in the same order in different boxes; for the
chapter in the pilot, 10 of 10 bank questions and both diagrams of the two compact packs were identical and ~65% of the
vocabulary was shared. A remedial class that revisits all 32 concepts equally cannot prioritise, and a "revision note" with a
worked example is a lesson. Titles and layouts were never the problem: the **granularity, the inputs and the schema** were.

The default profile of `revision_notes` and `remedial` is now `purpose` (`profile: "purpose"` in the document). The old
one-card-per-concept design is only reachable with the `standard` profile of `StudyDocumentService::generate()` and is kept for
reading and regenerating old documents. `--compact` (the exact-N-page grid) is unchanged. Activities have no purpose design.

| | Revision notes | Remedial class |
|---|---|---|
| For | a learner who has studied the chapter and is preparing for an examination | a learner who did not follow it |
| Unit of work | the **topic** (8 sheets for the pilot chapter) | the **concepts that may be hard** (4 to 7 lessons), not every concept |
| Writer | `TopicRevisionWriter` (one prompt per topic; *condense*, never teach) | `RemedialWriter` in its lesson mode + `RemedialFrameWriter` (objectives, mix-ups, teacher's guide) |
| Parts | overview, `topic` sheets, `glossary`, `check` | overview, `diagnostic`, `gaps`, `unit`s, `clinic`, `independent`, `exit`, `teacher` |
| Every concept appears | in a row of its topic's sheet (one short line) | in the table of potential difficulties (taught, tabled or named) |
| Misconceptions | the **first** listed one, as a "do not confuse" pair | the **last** listed one, as a mix-up to judge and settle |
| Questions (bank only) | 6 to 10, from the harder, exam-like end, a few per topic | diagnostic (one per topic, plainest), guided (1 to 2 per lesson, easiest first, with hints), independent, exit (one per lesson); **none of the revision notes'** |
| Diagrams | at most 4: comparison and "around an idea" (`compare`, `hub`) | at most 2: processes (`flow`) |
| Aim / limit | 5 to 10 pages / 5 to 15 | 8 to 15 pages / 5 to 15 |
| Answers hidden copy | no answers or reasons | no answers, no corrections, no "why the other choices do not fit", **no teacher's guide** |

**Which concepts get a lesson (`GapAnalysis`).** No record of how a class performed exists for the chapter (the learner-response
tables hold a handful of rows for it, which is not a basis for anything). So the ranking is made **only from the chapter's own
data**: how the concept is rated, how much thinking it asks for (DOK and Bloom), how many misconceptions are listed for it,
whether later ideas build on it, whether it builds on an earlier one, and whether its bank questions ask for more than recall.
The reasons printed are those facts, in words with no numbers; every difficulty in the document is a *potential* one, and the
validator fails a class that loses the standing note saying so (`GapAnalysis::NOTE`). The teacher's text for each lesson must say
what *may* be seen (a rule `RemedialFrameWriter` repairs against and the validator applies). About a sixth of the chapter gets
a lesson, never fewer than four nor more than seven, at most two from one topic while another has a candidate.

**Which questions go where (`QuestionPlacement::forCheck`, `forRemedialClass`).** Pure and deterministic. The class is told which
questions are the revision notes' and takes none of them. Only a question that can be printed whole is offered (`printable()`:
stem, options, answer and reason within fixed lengths; the bank's text is never shortened).

**Pages (`StudyDocumentService::fitPurpose`).** Both PDFs are drawn and counted. The limit (5 to 15 in each copy) is a
requirement, an error if missed; each kind's aim is advice, a warning. If a document is over its aim the fit gives it less
*optional* content, never half a part: a revision pack takes fewer "test yourself" questions (10 down to 6); a class takes less
practice on its own, then loses its lowest-ranked lesson (never below four), and only then has its guided practice cut to one
question per lesson (`remedialLadder()`). If nothing fits, the run stops (`rejected-<kind>-purpose-replies.json`) instead of
producing a document that breaks the limit.

**Stored JSON.** Version stays 1, with `profile: "purpose"` and `purpose: {min_pages, max_pages, pages: {revision, practice}}`.
The frontend accepts the legacy `note`/`unit` parts and these. Every part keeps the usual keys (`n`, `type`, `block`, `title`,
`topic_id`, `concept_ids`, `taught_concept_ids`, `content`, `image`, `interaction`, `question_ids`, `activities`).

* revision: `topic.content = {big_idea, rows:[{concept_id,name,essential,terms,bloom,dok}], compare:{title,columns,rows}|null,
  mixups:[{wrong_idea,correct}], recall:[…], checklist:[…], minutes}`; `glossary.content = {terms:[{term,meaning,concept_id,topic_id}]}`;
  `check.content = {intro, note}` (+ `question_ids`).
* remedial: `diagnostic.content = {intro, scoring, items:[{question_id,concept_id,topic_id,if_missed:[{n,title}]}]}`;
  `gaps.content = {note, rows:[{concept_id,name,topic_id,priority,reasons,check_first,covered_in}], others:[names]}`; `unit.content`
  is the unit shape (with `mistakes: []`); `clinic.content = {intro, items:[{concept_id,wrong_idea,why_it_seems_true,correction,check_it,unit}]}`;
  `exit.content = {intro, criteria:[{text}], ready_at, total, revisit:[{concept_id,name,n}]}`; `teacher.content = {purpose, how_to_run,
  pacing, total_minutes, interventions:[{concept_id,name,n,look_for,try_this,if_still_stuck}]}`.
  Only the concepts that get a lesson are in `taught_by`; every concept is in `gaps.concept_ids`.

**Print** (`PurposePdfRenderer`): no cover or contents page, a title band, then the parts; "Page n of N" from page one. A hotspot
diagram is printed as its picture (the numbered legend is for the online version).

## Operating it

```
php artisan lms:generate-study-document 8592 revision_notes            # purpose-based; bundle in <kind>_purpose/; stores nothing
php artisan lms:generate-study-document 8592 remedial [--lessons=5]    # likewise
php artisan lms:generate-study-document 8592 remedial --draft=<draft.json>   # re-fit the pages, no model call
php artisan lms:generate-study-document 8592 remedial --concepts=101,102
php artisan lms:store-study-document 8592 revision_notes --dry-run     # what would happen
php artisan lms:store-study-document 8592 revision_notes               # store exactly what was reviewed
php artisan lms:unstore-study-document <content_id> [--images] [--dry-run]
```

Bundle: `storage/app/study-deck/chapter-<id>/<kind>_purpose/` for revision notes and a remedial class, `<kind>_compact/` with
`--compact`, `<kind>/` for activities (a run never writes into another design's bundle) (`document.json`, `draft.json`, `report.json`, `learning-map.json`,
`questions-excluded.json`, `out/<kind>.html` and the two PDFs). **Generating writes the diagrams into `study_deck_images`**
(pictures are database rows, never files; the deck's command does the same); the ones of a run that is never stored are
removed by `study-deck:images-prune`. `--executor=cli` is the dev-only path with the tools disabled.

From the content drawer: `POST lms/gamma-content-master` and `POST api/intelligence/content/generate` hand "Revision Notes",
"Remedial Class" and "Classroom Activity" to this pipeline when `claude.chapter_ids` serves the chapter. Set
`CLAUDE_STUDY_DOCUMENTS=false` (`config/claude.php`: `study_documents`) to go back to the earlier single-prompt documents for
every chapter at once, with no deploy.

## Compact documents (a pack of a given page count)

The documents above are thorough: for a 32-concept chapter the revision notes run to about 50 printed pages, the remedial
class to about 90 and the activities to about 60. When a chapter's whole revision, remedial class or set of activities has
to fit a few pages, the same pipeline writes a **compact** pack of any of the three kinds instead:

```
php artisan lms:generate-study-document 8592 revision_notes --compact --pages=5 --executor=cli
php artisan lms:generate-study-document 8592 remedial       --compact --pages=5 --executor=cli
php artisan lms:generate-study-document 8592 activities     --compact --pages=5 --executor=cli
```

Each writes to `chapter-<id>/<kind>_compact/` (the full bundle in `<kind>/` is left alone) and stores nothing. The store
command takes it with `--bundle`.

**What every compact pack has in common**

* **Written short, not shrunk.** The model is asked for a different, tighter version of each part, not the long one
  squeezed (`RevisionNotesWriter`, `RemedialWriter` and `ActivityWriter` with `compact`; the limits are their
  `COMPACT_*` constants). The validator applies the same limits to the finished document.
* **Every concept, in order.** The coverage and numbering rules are unchanged: a concept without a part fails validation.
* **Diagrams.** At most two, side by side: the concepts whose diagram has the most parts (at least three).
* **Questions are the bank's own, printed whole or not at all.** `QuestionPlacement::forCompact` offers, best first, only
  questions whose answer and reason fit the fixed answer area (`CompactRevisionPdfRenderer::ANSWER_CHARS`, with a stem of
  at most 240 characters and options of at most 110): one from every topic before a second from any, a concept not yet
  asked about before one that has been. Choice questions print with their options; short-answer questions print with
  the bank's model answer. Nothing is reworded or cut.
* **Fitted by drawing.** After the parts are written (the only model calls), the pack is assembled with *n* questions,
  both copies of its PDF are drawn and counted, and the largest *n* that does not pass the target is kept
  (`StudyDocumentService::compactDocument`). The document records `profile: compact` and a `compact` block (target,
  measured pages of each copy, questions offered and printed), so the source and the PDFs are known to be one run.
* **Both copies are the same length.** A question's answer area is the same fixed height in both copies: the answer and
  the reason in the answers-shown copy, an empty box to write in in the answers-hidden copy. The correct option is tinted
  in the shown copy only; no text that could wrap differently is added. A pack that is not exactly `--pages` pages in
  *both* copies fails validation, and the command checks the written files too.
* **One layout engine, one card per kind.** `CompactRevisionPdfRenderer` is the title band, the two-column grid, the
  diagrams and the questions; `CompactRemedialPdfRenderer` and `CompactActivitiesPdfRenderer` lay out their own cards
  on it (`CompactRevisionPdfRenderer::for()` picks by kind). There is no cover and the page number is on every page. The
  long documents' renderer is untouched.
* **Limits.** A concept whose bank has no question that fits has none in the pack (the validator says how many). If the
  parts alone overrun the target the run stops and says so.

**Revision notes.** A one-sentence gist (6 to 24 words), 2 to 3 points of at most 16 words, the chapter's own definition
when it has one (at most 26 words) and one "I can" line. No rules box, worked example, misconception box or "remember"
line (a formula or value the chapter states goes in a point). The PDF leaves out the checklist (one line per concept stays in
the source for the "Try it online" tab), the key-terms table (a definition is printed with its concept) and the hotspot
legend of a diagram.

**Remedial class.** One card per concept: what to start from (the prerequisites by name), the idea in at most 18 words,
three one-line steps (the PDF prints the step text; the label stays in the source), at most one mistake, and a "you can
now" line of at most 10 words. No worked example, no real-life picture, no refresher per prerequisite and no practice
hints (a hint is the model's wording for one question; the bank's questions are printed after the cards, as the bank has
them, easiest first).

**Classroom activities.** 8 to 10 activities (still at most four concepts each, every concept covered), of the formats that
need no on-screen exercise (no matching or sequencing). A run sheet, then a card per activity with exactly three short
teacher steps, three student steps, two things to look for, one reflection prompt, and the misconception to surface when
the concepts list one. No discussion, differentiation, setup or interaction. The copies are the teacher edition and the
student handout: a card has two boxes of one fixed height in both copies (`HALF_LINES` wrapped lines, clipped, and the
validator checks the text against it); the left box (aim, materials, what students do, reflection) is the same in both,
the right box is the teacher's half in the teacher edition and "your notes" in the student handout. The bank's quiz
questions are fitted after the cards, each joining the first activity that covers its concept.

Tests: `tests/Unit/StudyDeck/Documents/CompactRevisionNotesTest.php` and `CompactStudyDocumentsTest.php` (no database, no
Laravel; the model is a script).

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

The purpose-based design has its own no-database tests: `PurposeDocumentsTest.php` (which concepts get a lesson, which question goes
where and that none is shared, each writer's repair rules, both documents end to end, what the validator rejects, the page limit
and the fit ladder, what the hidden copy leaves out) with `PurposeModel.php`, a scripted stand-in for the model that derives valid
replies from each prompt's own data.

```
vendor/bin/phpunit tests/Unit/StudyDeck
```

Several older classes under `tests/Unit/StudyDeck` extend Laravel's `TestCase` (it boots the app and reads the live shared database
at boot). To run only the no-database ones, and prove it, run them against an unreachable database:
`DB_HOST=127.0.0.1 DB_PORT=1 APP_KEY=base64:… phpunit tests/Unit/StudyDeck/Documents tests/Unit/StudyDeck/QuestionSelectorTest.php`.
