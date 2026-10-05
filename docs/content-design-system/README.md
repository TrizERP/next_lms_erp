# K-12 Content Design & Generation System

**Version 1.0** · The standard every piece of generated learning content in this platform must follow — whoever or whatever produces it.

This is not a style guide. It defines how content is **structured, generated, validated, rendered and delivered**, so that a chapter written by Claude, by Gamma, or by a human teacher all arrive in the same shape and can be re-rendered, re-skinned and quality-checked without being rewritten.

---

## 1. Why this exists

Before this, every generated resource was a finished artifact. A Gamma deck is a Gamma deck: you cannot restyle it, you cannot check whether it covered every concept, and you cannot re-render it as a PDF. The design lived inside 830 frozen files.

The inversion: **the model writes structured content; we own the rendering.** Change a colour, a slide layout, or an accessibility rule, and every artifact in the platform re-renders for free.

One research finding drives the whole standard. The [SLATE benchmark](https://arxiv.org/abs/2609.06212) evaluated AI-generated teaching slides against *measured learner knowledge gain* and found a **dissociation between artifact quality and instructional effectiveness**: content validity had only a weak association with learning gain, while **pedagogical design had a robust positive association**. Frontier models produced visually polished decks that taught nothing — some with *negative* learning gains.

So polish is not the goal, and polish is exactly what a slide-generation service sells. **Section 5 (Validation) is the part of this system that does the real work.**

---

## 2. The three layers

| Layer | What it owns | Where it lives |
|---|---|---|
| **Design** | Colour, type, spacing, motion, a11y | `lms_k12/K-12 ERP Design System/` (the `eduerp-design` Skill) |
| **Content** | Blocks, content types, metadata | This document + the `k12-content` Skill |
| **Render** | HTML, PDF, PPTX, Gamma | `RendersGeneratedContent.php`, `globals.css`, PHPPresentation |

**The Design layer is inherited, not redefined.** EduERP tokens are the source of truth for every colour and measurement. Content never invents a hex.

One deliberate extension: EduERP is an *enterprise admin* language — "no gradients, no decoration, calm restraint" — which is correct for a fees screen and wrong for a Class 6 science lesson. The Content layer therefore permits more visual variety (callouts, tiles, figures, cover panels) **built entirely from EduERP tokens**. Same palette, same type scale, same contrast floors; richer composition.

---

## 3. Taxonomy — the words we use

Deliberately small and plain. Anyone in the business should be able to read this table.

| Term | Meaning |
|---|---|
| **Chapter** | The unit of generation. Everything is generated per chapter. |
| **Concept** | A teachable idea inside a chapter. Comes from `semantic_intelligence`. |
| **Content type** | What is being made — one of the five in §4. |
| **Block** | One pedagogical move. The atom of this system. See §4. |
| **Slide** | A group of blocks shown together. Only presentations have slides. |
| **Pack** | Everything generated for one chapter — all five content types. |

Everything above already exists in the database except **Block** and **Pack**, which this standard adds.

> **Naming rule:** this system introduces exactly two new words. It does not rename anything. The estate already contains **31 documented vocabulary collisions** — `content_type` means two different things, `assessment_blueprint` names both a database table and an unrelated JSON key, "blueprint" names four things, difficulty has **five** scales, and the platform tenant is encoded as `1`, `0` and `NULL` in three different registries. See `vocabulary.md` for the full table and the canonical term for each. **Reconciliation belongs in `config/pal_content_model.php`'s alias maps — never in a new registry.**

---

## 4. The content model

### 4.1 The ten blocks

Every block is one pedagogical move. A content item is a sequence of blocks. That is the whole model.

| Block | What it does | Renders as |
|---|---|---|
| `intro` | Hook + the big idea of a concept | `.cover` panel |
| `explain` | The core explanation | heading + prose |
| `visual` | A diagram or image that carries meaning | `figure.fig-*` |
| `example` | A worked example | `.callout.callout-example` |
| `real-world` | Where this shows up in life | `.callout.callout-example` |
| `misconception` | A common wrong idea, named and corrected | `.callout.callout-warn` |
| `check` | Check for understanding — a question with an answer | `.callout.callout-try` |
| `activity` | Something students *do* | `.callout.callout-try` |
| `summary` | Recap of what was covered | `.tiles` or `.callout-key` |
| `assess` | Formal questions with marks | `table` or `ol` |

The visual classes on the right **already exist** in both renderers (`RendersGeneratedContent::generatedContentCss()` and `.lms-generated-body` in `globals.css`). This standard does not add CSS — it finally tells the generator what the CSS is for.

### 4.2 Block metadata

Every block carries its pedagogical identity as inert `data-` attributes:

```html
<section class="callout callout-warn" data-block="misconception"
         data-concept="Osmosis" data-bloom="understand" data-dok="2" data-minutes="4">
  <span class="callout-label">Common misconception</span>
  <p>Students often say the salt moved into the potato...</p>
</section>
```

| Attribute | Values |
|---|---|
| `data-block` | one of the ten block names |
| `data-concept` | a concept name exactly as it appears in `semantic_intelligence` |
| `data-bloom` | `remember` `understand` `apply` `analyze` `evaluate` `create` |
| `data-dok` | `1` `2` `3` `4` |
| `data-minutes` | integer — teaching time |

`data-bloom` uses the lowercase spelling the drawer already teaches (`conceptIntelligenceGuide.ts`). The alias maps handle the other two spellings in the estate.

### 4.3 The five content types

| Type | Blocks it must contain | Audience |
|---|---|---|
| **Presentation** | `intro` `explain` `visual` `example` `check` `summary` — grouped into `.slide` sections | Students, in class |
| **Revision Notes** | `explain` `example` `misconception` `summary` `assess` | Students, revising |
| **Classroom Activity** | `activity` `check` — inquiry-led | Teacher running a lesson |
| **Remedial Class** | `misconception` `explain` `example` `check` — diagnostic-first | Students with gaps |
| **Teacher Training** | `explain` `activity` — pedagogy-focused, about *how to teach* | Teachers |

Same ten blocks throughout. A presentation is blocks wrapped in `.slide`; a document is the same blocks unwrapped. **One vocabulary, five types, four render targets.**

---

## 4.4 Boards

The system must produce content for CBSE, ICSE, Cambridge, IB and state boards. Two things in the estate are called "assessment blueprint" and **neither could carry that**:

| | Intelligence blueprint<br>(`full_intelegance_json` key) | Assessment blueprint<br>(database table) |
|---|---|---|
| **What it is** | Suggested questions for one concept | One exam paper's design |
| **Scope** | Concept | Whole paper |
| **Board field** | **None** | `board` + 8 named boards, free text |
| **Board-aware?** | CBSE by construction — `Assertion Reason`, `HOTS`, `Case Study` | Yes, explicitly; competency bands deliberately *not* hardcoded |
| **Grounded in the textbook?** | Yes | No |
| **Populated?** | Every chapter | **Zero rows** |

**Neither is "better" — they answer different questions.** The intelligence blueprint *does* follow CBSE, which is exactly why it cannot be the basis of an all-board system: it is CBSE-locked with no way to declare or vary the board. The table has the right instincts for multi-board but designs *papers*, not chapter content, and is empty.

So the content system gets a third thing, which neither provided: a **board profile** — [`config/lms_content_boards.php`](../../config/lms_content_boards.php). It declares, per board, the question types, cognitive bands and difficulty labels that board actually uses. `lms:export-chapter-bundle` resolves the board from `lms_curriculum.board` (falling back to a board prefix on the standard name) and writes the profile into `manifest.json`, so the generator writes to the board rather than to CBSE.

### How a chapter's board is resolved

In this estate **a content-owning tenant is a board.** `sub_institute_id = 1` is the platform library every CBSE school consumes; `341` is the Cambridge library. A school tenant does not own content — it consumes a library and inherits that library's board.

That mapping exists **nowhere in the database**: `institute_detail` has no board column, `lms_data_neo4j` names tenant 1 "Triz International School" (so the board cannot be read off the name) and does not list 341 at all, and `pal_curriculum_versions` — which does have a board column — is empty. So it is declared in `tenant_boards` in the config.

Resolution order, most specific first:

1. **`lms_curriculum.board`** — a per standard+subject declaration. 39 rows today, all CBSE, tenant 1.
2. **A board prefix on the standard name** (`CBSE-6`), the same fallback `storeGammaContent` uses. Yields nothing for standards 39/40/42, which are named plainly `6`/`7`/`9`.
3. **`tenant_boards`** — the whole-tenant default. Any per-subject declaration beats it.
4. **`generic`** — board-neutral.

Two deliberate safeguards:

- **The default is `generic`, not `cbse`.** A chapter with no declared board gets board-neutral question types. Defaulting to a real board is how CBSE assumptions got baked into fields that never declared them.
- **Only CBSE is filled in**, because it is the only board this estate has evidence for (39 `lms_curriculum` rows, all CBSE). The others are declared with `profile_complete => false` and must not be silently treated as CBSE. Fill each from that board's own published assessment guidance before generating against it.

The intelligence blueprint stays valuable — it is textbook-grounded raw material. Its *questions* are reusable; its *question-type labels* must be re-mapped through the board profile.

---

## 5. Validation — the part that matters

Generation is not finished when the model stops writing. It is finished when it **passes**. A failing item is regenerated, not shipped.

### Coverage
- Every concept in the chapter's `semantic_intelligence` is named by at least one block's `data-concept`.
- No `data-concept` value that is not a real concept in that chapter.

### Pedagogy
- Every `.slide` contains at least one `check` block. *(A slide with nothing to answer teaches nothing — this is the SLATE finding made mechanical.)*
- Every `misconception` block traces to a real entry in that concept's `misconceptions[]`.
- Bloom levels across the item span at least three distinct values — not all `remember`.
- Every `assess` block carries marks.

### Cognitive load
Mayer's coherence and segmenting principles, made countable:
- A slide holds **at most 60 words** of body text and **one** `explain` block.
- At most **7** bullets in any list.
- Prose sentences average **under 25 words**.

### Accessibility
- All colour comes from tokens, so WCAG 2.2 AA contrast (4.5:1 body, 3:1 large) holds by construction.
- Every `img` has a non-empty `alt`.
- Meaning is never carried by colour alone — every callout has a text `.callout-label`.
- No emoji.

### Grounding
- No factual claim absent from the chapter's ground-truth markdown.
- Definitions use the textbook's exact wording.

---

## 6. Rendering

| Target | Mechanism | Status |
|---|---|---|
| **HTML** (in the LMS) | `.lms-generated-body` in `globals.css` | exists |
| **PDF** (print/offline) | Dompdf 3.1.6 via `renderGeneratedContentPdf()` | exists |
| **PPTX** (editable) | PHPPresentation 1.2.0 at `resolvePresentationRenderer()` — one `.slide` per slide | to build; dependency already installed |
| **Gamma** (teacher self-serve) | We hand Gamma our block content as `inputText` | exists |

All four consume the same stored HTML. Nothing is generated twice.

---

## 7. Generating

Content is generated per chapter from a **chapter bundle** — a folder holding everything the generator needs:

```
content/std-9/science/ch-03-tissues-in-action/
  chapter.md         ground-truth textbook text
  intelligence.json  per-concept semantic intelligence
  concepts.json      the concept list
  manifest.json      ids, names, counts, what has been generated
  out/               generated HTML, one file per content type
```

Built by `php artisan lms:export-chapter-bundle`. Imported by `php artisan lms:store-authored-content`.

The bundle makes every generation reproducible and reviewable, and lets a run resume after interruption without regenerating what already succeeded.

> **Trap:** `semantic_intelligence.full_intelegance_json` contains malformed UTF-8 and will fail `json_decode` outright. Always `mb_convert_encoding($s, 'UTF-8', 'UTF-8')` first. The exporter does this.

---

## 8. Rules for any producer

Whether the generator is Claude, Gamma, or a person:

1. **Tokens only.** Never a raw hex or px. The design system lints this.
2. **Ground truth only.** Facts come from `chapter.md`. Invented examples are a defect.
3. **Blocks, not prose.** Every piece of content is a block with a declared pedagogical role.
4. **Sentence case.** Headings, labels, buttons. Not Title Case.
5. **No emoji, no decoration.** Meaning through text, structure and icon.
6. **Name the concept.** Every block declares which concept it teaches.
7. **Validate before storing.** Failing content is regenerated, never shipped.
