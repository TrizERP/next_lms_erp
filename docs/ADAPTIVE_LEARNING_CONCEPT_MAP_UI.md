# Adaptive Learning Concept Map — UI redesign

## 1. Existing UI before this change

Before this work, "See the knowledge map" (from the PAL chapter dashboard's
"Mastery details" modal) had gone through two earlier iterations in the same
session:

1. A single-concept neighborhood view (this concept + its direct
   prerequisites/dependents/related concepts only — 2-4 cards).
2. A whole-chapter graph (all 17 ESO-ready concepts in a chapter), laid out
   in depth-ordered rows with SVG-drawn edges, a legend, a "You are here"
   badge, a text "dependency graph" table, and a stats footer.

Both iterations were already real and dynamic (no static/mock data), backed
by `EsoPolicyService::chapterKnowledgeMap()`. This document covers the third
iteration: combining the whole-chapter overview (iteration 2) with the
dependency-focused, current-concept-centric elements from a reference design
(strong "You are here" emphasis, per-card lock reasons, direct CTAs into the
adaptive flow, contextual "what this rests on / what it opens up" framing).

**Deliberately unrelated and untouched:** the separate "Coherence Map"
feature at `d:\lms_k12\app\pal\new\coherence-map\page.tsx` (BKT/Neo4j-backed)
was never modified by any of this — the user explicitly asked for a new,
ESO-only page instead of changing that existing one.

## 2. New UI structure

Route: `d:\lms_k12\app\pal\eso\knowledge-map\[conceptId]\page.tsx`
(`/pal/eso/knowledge-map/{conceptId}?learnerId=`).

Top to bottom:

1. **Back** link.
2. **"Knowledge map"** eyebrow label + a legend of the statuses actually
   present among this chapter's concepts (never all 5 — only what's real for
   this student right now).
3. **Chapter title**, and the chapter's real `chapter_desc` (from
   `chapter_master`) if one is on file — omitted entirely otherwise, never a
   placeholder.
4. **Summary paragraph** — which concepts are locked and why, built from real
   per-student prerequisite data ("Right now X, Y stay closed, because each
   needs concepts you have not mastered yet — A, B.").
5. **Current-concept panel** ("You are here") — name, status, response
   count, a CTA button, and (new in this iteration) "What this rests on" /
   "What it opens up" — the current concept's direct prerequisite and direct
   dependent names, in plain language, derived from the same edge list the
   diagram below draws (no new backend field needed for this part).
6. **"How to read it"** legend (solid arrow = direct prerequisite, dashed =
   related).
7. **The graph** — every ESO-ready concept in the chapter, laid out in rows
   by prerequisite depth (shallowest at top), connected by real SVG lines
   measured from the rendered cards' own DOM positions.
8. **"The dependency graph"** — the same edges as a plain text table
   (Direct prerequisite / Related concept), for accessibility and at-a-glance
   scanning without parsing the diagram.
9. **Stats footer** — real counts only (concepts, direct prerequisites,
   related links, misconceptions). No fabricated "model issues flagged by
   audit" metric — nothing like that exists per-concept anywhere in this
   codebase (confirmed by investigation before either iteration was built).

## 3. Student journey

Student Login → Student Dashboard (`/dashboard`, PAL chapter dashboard) →
"See mastery details" → "See the knowledge map" → this page → click an
unlocked concept card → `/pal/eso?conceptId=...&learnerId=...` (the existing,
unmodified adaptive-learning flow) → D1-D5 resolves the next step exactly as
it already did.

This page is a **navigation and context layer only**. It never decides what
a student should do next — clicking any unlocked card routes straight into
the existing `EsoConceptFlowPage`/`nextAction()` resolver, which makes that
call exactly as it already did for every other entry point in the app.

## 4. Concept card states

| Spec term | This app's real status (`eso_learner_node_state`-derived) | Label shown | Clickable | CTA |
|---|---|---|---|---|
| CURRENT / YOU ARE HERE | any status, `is_current: true` | badge + status label | yes (unless locked/no content) | per status below |
| AVAILABLE / START NOW | `not_started` | "Start now" | yes | "Start now" |
| IN PROGRESS | `in_progress` | "In progress" | yes | "Continue" |
| COMPLETED / MASTERED | `mastered` | "Got it" | yes | "Review" |
| RETAINED | `mastered` **and** every node has independently survived a D5 spaced-retrieval check | "Retained" | yes | "Review" |
| WAITING ON EARLIER WORK / LOCKED | `locked` | "Waiting on earlier work" | **no** | none — shows "Complete: {prerequisite names}" instead |
| (no adaptive content yet) | `not_ready` | "No content yet" | no | none |

"Retained" is a genuinely new distinction in this iteration — previously
"mastered" absorbed both plain-mastered and D5-retained concepts. It reads
`eso_learner_node_state.status = 'retained'` (already written by the
existing D5 logic in `EsoPolicyService::retrievalCheck()` — this UI change
only *reads* that value, it does not write to it or alter D5's own rules).
See `EsoPolicyService::isConceptRetained()`.

Each card also shows real response count and misconception count (from
`pal_misconception_library.concept_ref_id`, the same source
`MisconceptionLibraryService`/`PalContentIntelligenceController` already
use) — never node IDs or any other technical identifier.

## 5. Dependency visualization

Edges come straight from `pal_concept_relations`, scoped to pairs where both
concepts are in the same chapter (the same "no link crosses a chapter
boundary" convention the existing Coherence Map already applies to this same
table):

- `relation_type = 'requires'` → **direct prerequisite** — solid indigo
  line with an arrowhead, drawn prerequisite → dependent (reads downward in
  the depth-ordered layout).
- `relation_type = 'cross_curricular'` → **related** — dashed grey line, no
  direction implied, deduplicated regardless of which row recorded which
  direction.

No third "supporting" category exists in real data (confirmed: only
`requires` and `cross_curricular` are used anywhere in this table, in either
repo) — it is not shown, rather than invented.

## 6. Current concept behavior

Strong visual emphasis: an indigo-bordered panel above the graph (badge +
name + status + CTA + rests-on/opens-up context), **and** the matching card
inside the graph itself carries its own "You are here" badge and a
thicker indigo border — so the student can find themselves both in the
summary and in the full picture.

## 7. Locked concept behavior

- Not clickable (`<div>`, not `<button>` — cannot be accidentally activated).
- Visually muted (slate background, reduced opacity, no CTA row).
- Shows its own real reason: `Complete: {name(s) of the specific unmet
  prerequisite(s) for THIS concept}` — computed per concept
  (`EsoPolicyService::chapterKnowledgeMap()`'s per-concept
  `blocking_prerequisite_names`, not just the page-level aggregate), so two
  different locked concepts blocked by different prerequisites show
  different reasons. No node IDs or technical terms are ever surfaced.

## 8. Mastered/retained concept behavior

Both remain fully clickable (clicking routes into the existing adaptive
flow) — per the spec, a mastered/retained concept should still allow a
scheduled review to fire when due, and that decision belongs entirely to
`nextAction()`'s existing D4/D5 logic. This page does not special-case
"already mastered" beyond showing "Review" as the CTA label; whether the
next screen is a mastery summary or a due retrieval check is decided by the
unmodified engine, not by this page.

## 9. Adaptive Learning CTA

Every actionable card (and the current-concept panel) routes to
`/pal/eso?conceptId={id}&learnerId={learnerId}` — the exact same route,
component (`EsoConceptFlowPage`), and `fetchNextAction()` call every other
PAL entry point already uses. No second learning engine, decision path, or
API surface was created.

## 10. Student authorization

`learnerId` is resolved the same way every other page in `app/pal/eso/*`
already does: `searchParams.get('learnerId') || viewAsStudent?.studentId ||
defaultLearnerId()` — `defaultLearnerId()` reads the authenticated session's
own id (`d:\lms_k12\lib\erp-client.ts`'s `buildSessionContext()`), so a
student's own visit never needs (or trusts) a URL-supplied id.

Enforcement is server-side and was **not weakened or bypassed** by this
work: `GET /api/pal/eso/knowledge-map/{learnerId}/{conceptId}` sits inside
the `eso.student` middleware group (`routes/pal_eso_api.php`), which the
existing `EsoStudentOnlyAuth` middleware already gates — a teacher/staff/
admin caller is rejected (403) even for a student genuinely within their own
institute, and a student cannot pass another student's id. This is the same
guarantee every other route in this file already had; verified (not just
assumed) by a new test,
`EsoAuthorizationTest::test_staff_cannot_open_a_students_knowledge_map_even_within_their_own_institute`
— see §13.

## 11. Responsive behavior

- The graph sits in its own `overflow-x-auto` container, so a wide chapter
  (many concepts at the same depth) scrolls horizontally *within that one
  box* — the page itself never scrolls sideways.
- Cards are a fixed `w-44` (176px) and wrap onto new lines inside their row
  (`flex-wrap`) rather than shrinking to illegibility at narrower widths.
- The page content is capped at `max-w-5xl` and uses the same responsive
  padding (`px-4 sm:px-6`) as every other page in `app/pal/eso/*`.

## 12. Files changed

Backend (`d:\next_lms_erp`):
- `app/Services/Eso/EsoPolicyService.php` — `chapterKnowledgeMap()` extended
  (chapter description, per-concept `blocking_prerequisite_names`,
  `isConceptRetained()` + retained-status surfacing). `conceptStatusFor()`
  and every D1-D5 method are **unchanged**.
- `tests/Feature/Eso/EsoKnowledgeMapTest.php` — extended (11 tests).
- `tests/Feature/Eso/EsoAuthorizationTest.php` — one new test (teacher
  blocked from the knowledge-map route).
- `docs/ADAPTIVE_LEARNING_CONCEPT_MAP_UI.md` — this file.

Frontend (`d:\lms_k12`):
- `app/pal/eso/knowledge-map/[conceptId]/page.tsx` — rewritten: current-
  concept context panel, direct-launch card interaction, per-card lock
  reasons, retained status, CTA labels.
- `app/pal/data/pal-eso.ts` — `KnowledgeMap`/`KnowledgeMapConcept` types and
  `fetchKnowledgeMap()` extended for the new fields (`chapterDescription`,
  per-concept `blockingPrerequisiteNames`, `'retained'` status).

Not modified: `app/pal/eso/_components/ChapterDashboardView.tsx` (the
"See the knowledge map" link's URL shape was already correct from the prior
iteration), `app/pal/new/coherence-map/**` (explicitly out of scope), any
D1-D5 file, mastery thresholds, or question-selection logic.

## 13. APIs reused / backend changes

Reused, unchanged: `GET /api/pal/eso/next-action/{learnerId}/{conceptId}`
(via the existing `/pal/eso` flow), `eso.student` / `EsoStudentOnlyAuth`
middleware, `EsoPolicyService::conceptStatusFor()`,
`unmetPrerequisiteConceptIds()`, `nodesForConcept()`,
`esoReadyConceptsForChapters()`.

Backend changes (additive only, no existing contract altered):
- `EsoPolicyService::chapterKnowledgeMap()` — added `chapter_description`,
  per-concept `blocking_prerequisite_names`, and the `retained` status value.
- `EsoPolicyService::isConceptRetained()` — new, read-only (queries
  `eso_learner_node_state.status`, writes nothing).
- Route `GET /api/pal/eso/knowledge-map/{learnerId}/{conceptId}` — already
  existed from the prior iteration; response shape extended, not replaced.

## 14. Validation performed

- `./vendor/bin/phpunit --filter Eso` — 82/82 passing (77 before this
  iteration's additions, +4 new EsoKnowledgeMapTest cases, +1 new
  EsoAuthorizationTest case = 82).
- `npx tsc --noEmit` (`d:\lms_k12`) — clean.
- `npx eslint` on every changed file — clean.
- `npm run build` (`d:\lms_k12`) — clean, `/pal/eso/knowledge-map/[conceptId]`
  present in the route list.
- Live API check, real student (id 97926, mastered concept 114): correct
  status, per-card lock reasons, real misconception/response counts.
- Live API check, synthetic pilot student 283919 (chapter 1014, concept
  114) — fresh/untouched state, correct `not_started`/`locked` classification
  and per-card `blocking_prerequisite_names`. This student has no real
  `/login` credentials (confirmed in `docs/CHAPTER_1014_CONCEPT_114_USER_JOURNEY.md`
  §3/§18), so this was verified via direct API call with a minted JWT rather
  than a browser session — the same limitation and workaround that document
  already establishes for this specific synthetic account.
- Live browser walkthrough (Playwright), real student session
  (`student@triz.co.in`): full journey from `/dashboard` through "See
  mastery details" → "See the knowledge map" → the redesigned page →
  clicking an available card → confirmed landing on
  `/pal/eso?conceptId=116&learnerId=97926` (the existing, unmodified
  adaptive flow).
- Teacher/staff restriction verified two ways: (1) the new PHPUnit
  authorization test (403), (2) architecturally unchanged — the route was
  already inside `eso.student` before this iteration and nothing in this
  change touched that middleware group or its ordering.

## 15. Dependency Graph Interaction (follow-up iteration)

The graph/connection layer got a focused redesign after feedback that the
straight lines were hard to read as an actual learning path — cards crossed
by lines, direction wasn't obvious, and there was no way to trace "what does
this depend on / what does it unlock" without reading the whole chapter at
once. Scope was intentionally narrow: **only** `DepthGraph` and `GraphNode`
in `d:\lms_k12\app\pal\eso\knowledge-map\[conceptId]\page.tsx` changed — the
card layout, `KnowledgeMapView`'s header/current-concept panel, the
dependency table, and every backend D1-D5/mastery/misconception file are
untouched.

**Curved edges.** Every edge is now an SVG cubic/quadratic `<path>`, not a
straight `<line>`:
- `direct_prerequisite` edges (`prerequisiteCurve()`) draw a vertical
  S-curve — leaves the prerequisite card straight down, arrives at the
  dependent card straight up — so the "flows downward" reading holds at any
  horizontal offset between the two cards.
- `related` edges (`relatedCurve()`) draw a shallow bow, offset to one side
  or the other by a deterministic seed (`edgeIndex % 2`, not random), so two
  related edges touching the same card don't visually overlap.

**Arrow direction** is unchanged in meaning — `direct_prerequisite` edges
still read prerequisite → dependent, straight from
`pal_concept_relations.relation_type = 'requires'`, same as before. Only the
rendering changed: an active-state marker (`#km-arrow-active`, darker/larger)
distinguishes an emphasized edge's arrowhead from a muted one's.

**Solid vs. dashed** is unchanged — solid indigo for `direct_prerequisite`,
dashed grey for `related` — the existing, real semantic distinction from
`pal_concept_relations.relation_type`; nothing new was invented.

**Hover and keyboard-focus path highlighting** (the main change). A single
`activeConceptId` state, set by both `onMouseEnter`/`onFocus` and cleared by
`onMouseLeave`/`onBlur` on every card — including previously-inert locked
cards, which are now `tabIndex={0} role="button" aria-disabled="true"` with a
visible `focus-visible:ring-2` style, so keyboard users get the exact same
highlighting mouse users do ("hover cannot be the only way"). A memoized
`buildPathIndex()` (`useMemo` keyed on `concepts`/`edges`) precomputes, per
concept, its direct prerequisites/dependents and full transitive closures
once per graph load — hover/focus interaction is then an O(1) map lookup
(`nodeEmphasis()`/`edgeEmphasis()`), not a recomputation on every mouse
move, so a 15-20 concept chapter stays responsive.

Four-tier visual hierarchy, applied to both cards and edges:
1. **Active** — the hovered/focused concept itself: full opacity, indigo
   ring, darkest edge color/thickest stroke.
2. **Direct** — its immediate prerequisites/dependents: full opacity, a
   lighter indigo ring.
3. **Indirect** — everything else on the same upstream/downstream chain
   (transitive, via the closure sets): slightly reduced opacity, medium
   stroke weight.
4. **Muted** — everything unrelated to the active concept: heavily faded
   (12% edge opacity, 40% card opacity) rather than hidden, so the rest of
   the chapter's shape stays visible for context without competing for
   attention.

This deliberately does **not** highlight the whole chapter indiscriminately
— only the active concept's own real upstream/downstream paths — and
locked concepts hovered/focused this way show their real blocking
prerequisite highlighted rather than looking like a dead end (see the
locked-card screenshot in the validation section below).

**Edge tooltip.** Each visible (thin) edge stroke has an invisible, wider
(`strokeWidth={14}`) sibling `<path>` layered on top purely for hit-testing
(`pointer-events: stroke`), since a 1-3px visible line is too thin to
reliably hover. Hovering it shows a small dark tooltip near the edge's
midpoint: `"Prerequisite for: {dependent's real name}"` for a
`direct_prerequisite` edge, `"Related to: {other concept's real name}"` for
a `related` edge — both read from the already-fetched
`KnowledgeMapConcept[]` data; no concept IDs or internal names are ever
shown.

**Per-node hover-details readout.** A small panel above the graph (inside
the same bordered container, so it reserves its height and nothing jumps)
shows the active concept's name plus its real direct `Prerequisites:` /
`Leads to:` lists, reusing the same `directUp`/`directDown` sets the
highlighting itself uses — generalizing the existing current-concept-only
"what this rests on / what it opens up" framing (§5/§6, unchanged) to
whichever concept is currently hovered or focused, not just the current one.

**Row ordering.** `orderRowsByBarycenter()` reorders each depth-row's cards
by the average horizontal position of their already-placed direct
prerequisites in the row(s) above (the standard one-pass barycenter
heuristic for layered graph drawing) — a small, dependency-free reduction in
avoidable edge crossings; no graph-layout library was added.

### Files changed (this iteration)

- `d:\lms_k12\app\pal\eso\knowledge-map\[conceptId]\page.tsx` — `DepthGraph`
  and `GraphNode` rewritten as described above; every other function in the
  file (`KnowledgeMapView`, `DependencyTable`, the data-fetching page shell)
  is unchanged. No backend file changed.

### Validation performed (this iteration)

- `./vendor/bin/phpunit --filter Eso` — 82/82 passing, unchanged (confirms
  no backend file was touched by this iteration).
- `npx tsc --noEmit` (`d:\lms_k12`) — clean.
- `npx eslint` on the changed file — clean.
- `npm run build` (`d:\lms_k12`) — clean.
- Live browser walkthrough (Playwright), real student session
  (`student@triz.co.in`), chapter 1014 / concept 114 ("Physical Properties of
  Metals"):
  - Confirmed 0 legacy `<line>` elements and curved `<path>` elements
    rendering instead.
  - Hovering the current concept's card highlighted it plus its two direct
    dependents and the two connecting curves, muting every unrelated
    card/edge; the hover-details readout updated to show
    `Leads to: Physical Properties of Non-metals, Chemical Reactivity of
    Metals`.
  - Tabbing keyboard focus onto the same card produced byte-for-byte the
    same highlighting as the mouse-hover case.
  - Hovering a locked card (`Electrolytic Refining`) highlighted it and its
    real blocking prerequisite (`Reactivity Series of Metals`) with the
    connecting edge emphasized, and the readout showed
    `Prerequisites: Reactivity Series of Metals` — confirming a locked
    concept's relationship traces cleanly rather than looking broken.
  - Hovering an edge's invisible hit-path showed a tooltip reading
    `Related to: Physical Properties of Metals` with the real concept name.
  - Confirmed the locked card carries `tabindex="0" role="button"
    aria-disabled="true"`, so it is keyboard-reachable despite not being
    clickable.
