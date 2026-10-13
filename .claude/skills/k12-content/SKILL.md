---
name: k12-content
description: Generate K-12 chapter learning content (presentations, revision notes, classroom activities, remedial classes, teacher training) that conforms to the platform's Content Design System. Use whenever authoring or regenerating content for a chapter bundle under content/std-*/, or when asked to produce CR (classroom resource) or TR (teacher training) material for a chapter.
user-invocable: true
---

# K-12 content generation

You are authoring learning content for an Indian K-12 platform (CBSE/NCERT, NEP 2020). Output is **design-system-classed semantic HTML**, stored in `content_master` and rendered to HTML, PDF and PPTX by machinery that already exists.

Read `docs/content-design-system/README.md` for the full standard. This file is the operational summary — follow it exactly.

## The one thing that matters

Polished content that does not teach is a failure. The [SLATE benchmark](https://arxiv.org/abs/2609.06212) measured AI-generated slides against real learner knowledge gain and found polish has only a *weak* association with learning while **pedagogical design has a robust positive one** — frontier models produced beautiful decks with *negative* learning gains.

So: every slide earns its place by making the student do something. Prefer one clear idea with a check over three ideas with none.

## Inputs — a chapter bundle

```
content/std-9/science/ch-03-tissues-in-action/
  chapter.md         ground-truth textbook text — THE ONLY SOURCE OF FACTS
  intelligence.json  per-concept semantic intelligence
  concepts.json      concept list
  manifest.json      ids, names, what is already generated
  out/               write generated HTML here
```

Read `chapter.md` fully before writing. **Every fact, definition, example, number and name must come from it.** Inventing an example is a defect, not a flourish. Use the textbook's exact wording for definitions.

`intelligence.json` gives you, per concept: `knowledge_items`, `abilities`, `skills`, `competencies`, `learning_objectives`, `learning_outcomes`, `blooms`, `dok`, `prerequisites`, `misconceptions`, `real_world_applications`, `pedagogy_recommendations`, `assessment_blueprint`, `assessment_rubrics`.

> **Name warning.** The `assessment_blueprint` *key inside this JSON* is a list of suggested questions for one concept. It has nothing to do with the `assessment_blueprint` *database table*, which holds board paper designs (marks by chapter, question counts) that schools clone. Same word, unrelated things, no reference between them. You want the JSON key. See `docs/content-design-system/vocabulary.md` collision #21.

## Write to the board, not to CBSE

`manifest.json` tells you which board this chapter is taught under:

```json
"board": "cbse", "board_label": "CBSE", "board_profile_complete": true,
"question_types": { "assertion_reason": "Assertion-Reason", "hots": "...", ... },
"cognitive_bands": ["remember", "understand", ...],
"difficulty_labels": ["easy", "medium", "hard"]
```

**Use only the `question_types` in the manifest.** They differ by board — Assertion-Reason and HOTS are CBSE paper furniture and have no place in a board-neutral or Cambridge deck.

**The per-concept `assessment_blueprint` in `intelligence.json` is CBSE-shaped**, whatever the manifest says: its `assessment_type` values are `MCQ`, `Assertion Reason`, `HOTS`, `Case Study`, and it carries no board field. Treat its *questions* as sound raw material — they are grounded in the textbook — but **re-label the question type to the manifest's vocabulary**. Do not copy `"HOTS"` into a deck for a board that has no such category.

**If `board_profile_complete` is `false`**, that board's question forms are not yet known. Stop and say so rather than guessing, and never fall back to CBSE forms. Generating Assertion-Reason questions for an IB school is worse than generating nothing.

**Use `misconceptions[]` and `assessment_rubrics` — they are the highest-value fields and were previously ignored.** `assessment_rubrics.items[]` carries real `answer_key[]` (with `misconception_tested`), `level_descriptors[]` with mark bands, and `common_errors[]`. Ground your `misconception` and `assess` blocks in them rather than inventing.

## The ten blocks

Every piece of content is a sequence of blocks. Each block is one pedagogical move.

| `data-block` | Purpose | Markup |
|---|---|---|
| `intro` | Hook + big idea | `<section class="cover">` with `.eyebrow`, `<h2>`, `.lede` |
| `explain` | Core explanation | `<h3>` + `<p>` / `<ul>` |
| `visual` | Meaning-carrying diagram | `<figure class="fig-md">` + `<figcaption>` |
| `example` | Worked example | `<section class="callout callout-example">` |
| `real-world` | Where it shows up in life | `<section class="callout callout-example">` |
| `misconception` | Wrong idea, named + corrected | `<section class="callout callout-warn">` |
| `check` | Question with answer | `<section class="callout callout-try">` |
| `activity` | Something students do | `<section class="callout callout-try">` |
| `summary` | Recap | `<table class="tiles">` or `<section class="callout callout-key">` |
| `assess` | Marked questions | `<table>` or `<ol>` |

Every callout opens with `<span class="callout-label">` — a short sentence-case label ("Common misconception", "Try this", "Worked example"). This is how meaning survives without colour.

## Required metadata

```html
<section class="callout callout-warn" data-block="misconception"
         data-concept="Osmosis" data-bloom="understand" data-dok="2" data-minutes="4">
  <span class="callout-label">Common misconception</span>
  <p>...</p>
</section>
```

- `data-concept` — **exactly** as spelled in `concepts.json`. A typo breaks coverage validation.
- `data-bloom` — lowercase: `remember understand apply analyze evaluate create`
- `data-dok` — `1`-`4` (Webb scale, not difficulty)
- `data-minutes` — integer teaching time

## Allowed markup

Tags: `h1`-`h6`, `p`, `br`, `hr`, `strong`, `b`, `em`, `i`, `sub`, `sup`, `ul`, `ol`, `li`, `blockquote`, `table`, `thead`, `tbody`, `tr`, `th`, `td`, `div`, `span`, `section`, `figure`, `figcaption`, `img`.

Attributes: `class`, the five `data-` attributes above, `src`/`alt` on `img`, `colspan`/`rowspan` on cells.

**Anything else is silently stripped by the sanitiser** — no `style`, no `href`, no `id`. Never write inline styles or raw hex colours; the stylesheet already handles appearance.

Available classes: `cover` `eyebrow` `lede` · `callout` + `callout-key|warn|example|try` · `callout-label` · `tiles` `tile-num` `tile-label` · `slide` `slide-head` `slide-num` · `meta` `pill` · `fig-sm|md|lg`.

## The five content types

**Presentation** — blocks grouped into `<section class="slide">`, each opening with `<div class="slide-head"><span class="slide-num">Slide 3</span><h3>Title</h3></div>`. Roughly 2 slides per concept. Every slide needs a `check`.

**Revision Notes** — `explain` `example` `misconception` `summary` `assess`, unwrapped. Dense is fine; this is for revising.

**Classroom Activity** — inquiry-led. `activity` + `check`. Give the teacher materials, steps, expected observations, assessment criteria, and the misconception to surface deliberately.

**Remedial Class** — diagnostic first, then alternative routes in. Do **not** re-teach the lesson: identify the gap, rebuild through a different representation (analogy, physical model, worked arithmetic), attack the specific misconception, end with a guaranteed win.

**Teacher Training** — about *how to teach* this chapter, not the content itself. Pedagogy fit per concept, differentiation, assessment design, lesson sequence, reflection.

## Validation — check before you finish

Your output must pass `php artisan lms:validate-content`. Check these yourself first:

**Coverage** — every concept in `concepts.json` named by at least one `data-concept`; no invented concept names.

**Pedagogy** — every `.slide` has a `check`; every `misconception` traces to a real `misconceptions[]` entry; Bloom spans ≥3 distinct levels; every `assess` carries marks.

**Cognitive load** (Mayer, made countable) — ≤60 words of body text per slide; one `explain` per slide; ≤7 bullets per list; sentences averaging <25 words.

**Accessibility** — every `img` has real `alt`; every callout has a `.callout-label`; no emoji; no colour-only meaning.

**Grounding** — no claim absent from `chapter.md`.

## Style

Sentence case everywhere. Plain language — aim below the grade level you are writing for. Address the student as *you*. Professional and warm, never chirpy. No emoji, no exclamation marks, no "Let's dive in!". Indian context and examples where the textbook offers them.

## Writing output

Write each content type to `out/<type>.html` as a **body fragment** — no `<!doctype>`, `<html>`, `<head>` or `<body>`. Then store it:

```
php artisan lms:store-authored-content <chapter_id> "<Content Category>" out/<type>.html --author=claude-opus-5
```

Categories: `Presentation`, `Revision Notes`, `Classroom Activity`, `Remedial Class`, `Teacher training presentation`.