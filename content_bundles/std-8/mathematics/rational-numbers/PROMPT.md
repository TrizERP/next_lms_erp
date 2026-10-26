# Build a classroom presentation — Rational Numbers

**Class 8 · Mathematics · CBSE board**

Attached in this folder:

| File | What it is |
|---|---|
| `chapter.md` | The **exact textbook text** (22270 characters). Every fact must come from here. |
| `concepts.json` | The concepts this chapter teaches — cover **all** of them |
| `topics.json` | How those concepts group, with suggested timings |
| `intelligence.json` | Per concept: misconceptions, real-world uses, suggested pedagogy, Bloom/DOK, and ready-made assessment questions |
| `manifest.json` | Board profile and counts |

Already generated for this chapter: nothing yet.

---

## What to make

A **classroom presentation** a teacher projects while teaching this chapter. Deliver it as an editable **.pptx** file, 16:9.

Roughly 1–2 slides per concept, plus a cover and a closing summary.

## The one rule that matters most

**Every slide ends with a question, and the answer must NOT be visible until the teacher clicks.**

Put the answer on its own animation step. A slide that shows the question and answer together is useless in a classroom — the class reads the answer before being asked anything. This is the single most important requirement here.

## Content rules

- **Ground truth only.** Facts, definitions, examples, numbers and names come from `chapter.md`. Use the textbook's exact wording for definitions. Do not invent examples.
- **Cover every concept** in `concepts.json`. Use `topics.json` to group and section them.
- **Use the misconceptions** in `intelligence.json` — they are real, per concept, and they are where students actually fail. Give the important ones their own slide.
- **Use the assessment questions** already in `intelligence.json` rather than writing new ones where they fit.
- Question forms for this board: Multiple choice, Assertion-Reason, Very short answer, Short answer, Long answer, Case based / source based, Higher order thinking skills, Competency based.
- Indian context and examples where the textbook offers them.

## Design language

- **Brand indigo `#4F46E5`**, deep indigo `#1E1B4B` for dark slides, slate neutrals (`#1E293B` text, `#475569` secondary).
- Semantic colour, used consistently: **amber `#B45309`** = misconception · **emerald `#047857`** = worked example · **blue `#0369A1`** = try this / check · **indigo** = key idea.
- **Inter** (or a close sans) throughout. Strong size contrast — the biggest and second-biggest text on a slide should differ by at least 2×.
- **Vary the layout.** Do not repeat one template. Use a mix: hero cover, section dividers, big-number stat tiles, split text/diagram, two-column comparison, full-bleed diagram, quote, question card, summary grid.
- **Sentence case** everywhere. Not Title Case.
- **No emoji, no decorative borders, no clip-art flourishes.** Visual interest comes from real diagrams, layout variety, colour blocking and type contrast — not ornament. Decoration that carries no meaning measurably reduces learning.
- Generous whitespace. Aim for **under 60 words** of body text per slide.
- Accessible contrast (4.5:1 body text), and never carry meaning by colour alone — always label a coloured box.

## Diagrams

Draw diagrams where they help — cell structures, force arrows, process cycles, comparison tables, labelled parts. Clean vector style matching the palette above. If you cannot draw something accurately, use a labelled layout instead rather than a decorative stock picture.

---

## Chapter structure

- **Introduction: Why Rational Numbers** (5 concepts, ~45 min): Natural numbers are not enough, Zero and the whole numbers, Negatives and the integers, Fractions and the rationals, From solving to exploring
- **Closure** (5 concepts, ~55 min): What a rational number is, Whole numbers and integers compared, Rational numbers are closed under addition, Closed under subtraction and multiplication too, Division is the exception
- **Commutativity** (4 concepts, ~50 min): Addition is commutative, Subtraction is not commutative, Why the failure carries over, Testing each operation in turn
- **Associativity** (4 concepts, ~55 min): Addition is associative, Subtraction is not associative, Multiplication is associative, Division is not
- **The Role of Zero and One** (2 concepts, ~55 min): Zero is the additive identity, One is the multiplicative identity
- **Distributivity** (2 concepts, ~55 min): The rule, Using it to simplify

## Concepts to cover (Rational Numbers)

- Natural numbers are not enough
- Zero and the whole numbers
- Negatives and the integers
- Fractions and the rationals
- From solving to exploring
- What a rational number is
- Whole numbers and integers compared
- Rational numbers are closed under addition
- Closed under subtraction and multiplication too
- Division is the exception
- Addition is commutative
- Subtraction is not commutative
- Why the failure carries over
- Testing each operation in turn
- Addition is associative
- Subtraction is not associative
- Multiplication is associative
- Division is not
- Zero is the additive identity
- One is the multiplicative identity
- The rule
- Using it to simplify

---

*Generated by `lms:export-chapter-bundle`. When the .pptx is ready, download it and import with:*
`php artisan lms:import-deck 25850 "Presentation" <file.pptx>`