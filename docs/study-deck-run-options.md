# Study deck: run options, replay and picture rules

How to generate a Classroom Presentation (the study deck) for a chapter that has many concepts and a question bank to
match, and how to fix a deck without paying for another model run. Nothing here is a second pipeline: these are options
and rules of the existing one (`lms:generate-study-deck` -> `StudyDeckService` -> `lms:store-study-deck`).

## Raising the caps for one run

By default a deck is a lesson with a few optional practice questions and at most ten pictures. A run may raise the caps
(the defaults are unchanged for a run that names none):

| Option | Default | Effect |
|---|---|---|
| `--max-practice=N` | 10 | most bank questions placed in the whole deck. Above the default the planner is asked for practice *spread through the lesson*, after the slide that teaches each concept, mostly self-checking (multiple choice, fill in the blanks, flashcards). |
| `--per-slide=N` | 1 | most bank questions on one slide |
| `--max-visuals=N` | 10 | most pictures or diagrams. Above the default the planner is asked for real photographs first, with the drawn diagram as the fallback. |
| `--max-scenarios=N` | 3 | most branching scenarios |

The values are written to the learning map (`limits`), so the planner, the interaction planner and `DeckValidator` all hold
the deck to the same numbers (`SlidePlanner::limits()`). Chapter 8593 was generated with
`--executor=cli --max-practice=24 --per-slide=2 --max-visuals=18 --max-scenarios=4`.

## Replay: fix the code after the model, not the model run

A run writes a review bundle (`plan.json`, `slides.json`, `images.json`, `interactions.json`, `learning-map.json`...).
When only the code after the model changed (a validator rule, the renderer, a picture rule), rebuild and re-validate the
bundle with **no model call and no image search**:

```
php artisan lms:generate-study-deck <chapter> --replay [--out=<bundle>]
php artisan lms:generate-study-deck <chapter> --replay --recheck-images
```

`--recheck-images` reads each chosen photograph back (from the school's stored copy of the download) and holds it to the
picture rules again; a photograph that fails is replaced by the diagram the plan drew for that slide (diagrams are never
changed, so the interactions written on them stay true). The limits come from the bundle unless named again.

### Redraw the diagrams, swap in chosen pictures (visual upgrade, no model call)

```
php artisan lms:generate-study-deck <chapter> --replay --out=<new bundle> --redraw-diagrams --images-from=<overrides.json>   --max-practice=24 --per-slide=2 --max-visuals=30 --max-scenarios=4
```

Copy the old bundle's JSON files to `<new bundle>` first: the new bundle is a separate folder, so the reviewed one is not
overwritten. Name every limit the deck was planned under, or the learning map is rebuilt with the defaults.

* `--redraw-diagrams` draws the deck's drawn diagrams again with the current `DiagramRenderer` (rounded boxes in the
  lesson's colour families, soft shadows, drawn at 2x and scaled down). Same words in the same places (`anchors()` is
  unchanged), so the hotspots written on a diagram stay true. A diagram whose words changed, and every photograph or
  illustration, is left alone.
* `--images-from` swaps in pictures a person chose (`ImageOverrides`, JSON `{"slides": {"<n>": ...}}`):
  * `photo`: `image_url`, `source_url`, `title`, `creator`, `license`, `provider`, `alt`, `caption`, `shows`, optional `query` and
    `purpose`. It faces the same rules as a searched photograph (accepted licence, a credit where the licence needs one, a real
    download, the detail gate, keyword evidence from the title and the `shows` note) and is recorded as chosen by a reviewer.
    A picture that fails stops the run; it is never skipped.
    Optional keys: `local_file` (the reviewer's saved copy, used only when the download is refused, e.g. Wikimedia's 429 on a large copy it has not cached), `accept_soft` (a reviewer who looked at a soft but correct subject waives the sharpness gate; the record says so), `backdrop: false` (the player must not use it as a texture behind other slides).
  * `illustration`: `file` (a PNG or JPEG, relative to the JSON file), `caption`, `alt`, `texts` (every word drawn on it),
    `anchors` (`label`, `x`, `y`, `w`, `h` as % of the picture). Stored like a drawn diagram: no source, no licence. The hotspots of the slide
    are moved to the anchors by label; a hotspot with no anchor, or whose label is not in `texts`, stops the run.
  * `remove`: the slide goes without a picture, with the reason kept.
  A slide that had no planned visual gets one, so `DeckValidator` holds its picture to the picture rules.

Pictures are stored in `study_deck_images` as usual (unreferenced until the deck is stored; `study-deck:images-prune` removes
the ones nothing links to).

## Chapter context

Besides the chapter text, topics, concepts, prerequisites and bank questions, the planner is given the chapter's
**practical activities** (`lms_prayogshala_activity`, titles and aims only) so a scenario or application slide can lead
into an experiment the school already runs. They are planning context: the deck's facts still come from the chapter text.

`DeckValidator`'s grounding rule accepts a number that appears in the chapter text, an attached question or a **concept
definition** (a row of the school's own data, handed to the model with the chapter text).

## Picture rules (`ImagePlanner`)

* Openverse is searched (Wikimedia first, then every source); licences CC0, PDM, BY and BY-SA only; BY and BY-SA carry
  their credit on the slide.
* Downloads are paced (3 s apart). Wikimedia's 960px copy is asked for first (a TIFF as its rendered JPEG); if it is
  refused the original is tried. A host that answers 429 with `Retry-After` over a minute (Wikimedia's "robot policy"
  says 10 minutes) is left alone for that long, so its other candidates fail at once and a picture from another source is
  used instead.
* The relevance review reads a picture's title and tags, not the picture. A **detail gate** therefore refuses a
  photograph that is out of focus or featureless (variance of the Laplacian at 320px under 100; real candidates scored
  800 to 6000, a blur 8). It cannot see what a picture shows: a person still has to look at the photographs.

## Study guide PDF (`StudyDeckGuidePdfRenderer`)

The classroom presentation's PDF (`ContentGenerationService::drawStudyDeckPdf`) is drawn by `StudyDeckGuidePdfRenderer`, a
subclass of `StudyDeckPdfRenderer`. The study documents (`Documents\StudyDocumentPdfRenderer`) still use the plain base
class, which the guide only touches through four small hooks (`slideHead`, `slideClass`, `composeLead`, `markerRgb`), so
their output does not change.

What the reader gets, on 65 pages for chapter 8593 (about 7.6 MB; about 25 s to draw):

* a **cover** built from the chapter's own picture (cropped to the page, blue wash behind the words) with the counts of
  slides, concepts, activities and practice questions, and the photo credit;
* **one page of contents** ("Chapter at a glance"): each topic in its own colour, each concept a link to the slide that
  teaches it; a one-line colour key for the card kinds;
* a **colour per topic** (topic band at its first slide, slide heading, card edges), so a page says where it is at a glance;
* the slide's **photograph under its heading** as a figure card with caption, credit and a "View the source" link; drawn
  diagrams as figure cards (hotspot diagrams carry the numbered markers their legend uses);
* on every slide, a **"Try it in the Study Deck" button** (on a plain slide, "Open in Study Deck") that opens that slide in
  the player; on every page after the cover, a **"Back to contents" link** in the footer;
* a **bookmarks panel** (cover, contents, each topic with its slides) and the document's title, subject and keywords.

Dompdf cannot script a PDF, so nothing in the file *reveals* anything on click: the quizzes, hotspots, matching and branching
run in the Study Deck, and the buttons take the reader there. The practice copy (`variant: practice`) has the same layout and
leaves out every answer.

Bookmarks are added by `PdfOutline`, an **incremental update** of the finished file (the original bytes are kept, a new
cross-reference section is appended). Dompdf already names every element with an `id` as a destination and leaves an empty
outline in the catalog; `PdfOutline` fills it in. If the file is not what it expects it returns it unchanged.

To look at one without storing anything, draw it in process with
`ContentGenerationService::studyDeckPdfBytes($deck, ['variant' => 'revision', 'content_id' => $id])` from a review bundle's
`deck.json` (it reads pictures and questions from the database and writes nothing). Two ways a PDF reaches a reader:
the **practice** copy is drawn when it is asked for (`StudyDeckApiController`), so it takes the new layout as soon as the code
is deployed; the **revision** PDF is drawn when the deck is stored and kept, so a record already stored keeps its old PDF
until `lms:store-study-deck --redraw-pdf` replaces it (the row, presentation and deck file are left as they are).

## Tests

`tests/Unit/StudyDeck/StudyDeckGuidePdfRendererTest.php` (extends the plain renderer's test, so every check on what the PDF
must say runs against the guide too; adds the cover, contents links, topic bands, buttons and bookmark tree) and
`PdfOutlineTest.php` (bookmarks added to a real Dompdf file: original bytes kept, cross-reference offsets right, file still
opens with the same pages, unreadable input returned unchanged). Both are plain PHPUnit: no database, no object store.

`tests/Unit/StudyDeck/StudyDeckPipelineTest.php` (limits, prompts, replay round trip, grounding, practicals),
`ImageDownloadTest.php` (thumbnail and original, TIFF, host back-off, detail gate, recheck), `ImageOverridesTest.php` (chosen
photographs and illustrations, licence and credit rules, hotspots moved by label, redraw), `DiagramRendererTest.php` (every layout
draws, anchors land on the boxes, labels stay inside them). Like the rest of the
directory they use `Tests\TestCase` and so read the database once at boot; nothing is written.
