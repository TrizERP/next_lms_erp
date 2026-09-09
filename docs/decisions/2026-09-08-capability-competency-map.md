# Capability / competency estates — the map before any consolidation

**Covers:** "Decisions & Risk Log" #34, and the request to bring G2G's capability/competency into K-12
**Date:** 2026-09-08 · **Phase:** B6a *(read-only — no code changed, nothing copied)*

---

## The headline

**A partial port already exists, in a shape that does not match this repo's own porting convention.**

`app/capability-intelligence/capability-library/page.tsx` says so in its own header:

> *"Ported from G2G's `components/domain/competency/cm-libraries-taxonomy.tsx`"*

So the question is not "should we copy G2G's competency module here". **Someone already started.**
The real question is how to finish it without ending up with two competency codebases.

---

## What is actually in each place

### G2G (`g2gv0`) — the source

`components/domain/competency/` — **35 `.tsx` files**, plus an `audit/` subfolder; ~26,600 lines total.

### K-12 (`lms_k12`) — four estates

| Estate | Files | Lines | Routes | What it really is |
|---|---:|---:|---:|---|
| `app/talent-management` | 66 | 37,840 | 11 | **G2G port** — routeMapper.ts:414 says "Migrated as-is from G2G (g2gv0)" |
| `app/capability-intelligence` | 32 | 11,882 | 5 | **Partial G2G competency port**, inlined into the route tree with its own `_lib/` API layer |
| `app/people-competency` | 12 | 689 | 9 | **Not competency at all** — 5-line wrapper pages for *LMS* screens (my-learning, course-builder, assessments) |
| `app/enterprise-brain/capabilities` | 2 | 546 | 2 | EB capability viewer. **Not referenced from `routeMapper.ts`** |

`app/people-competency` is misfiled, not duplicated. Its routes are learning screens; the folder name is
misleading and that is the whole of its problem.

### The convention this repo already uses for G2G ports

| Ported domain | Where the code lives | Route pages |
|---|---|---|
| LMS | `components/domain/lms/` — 58 files, 22,622 lines | 5–16 line wrappers |
| Organization | `components/domain/organization/` — 5 files, 1,107 lines | thin wrappers |
| **Competency** | **`components/domain/competency/` — does not exist** | — |

That is the inconsistency. LMS and Organization were ported as `components/domain/<domain>/` plus thin route
pages — exactly mirroring G2G's own layout. Competency was ported instead by **inlining** screens into
`app/capability-intelligence/**` with a bespoke `_lib/` API layer.

---

## Exactly what is ported, and what is not

**11 of G2G's 35 competency files** are referenced as ported into K-12:

`cm-certifications`, `cm-command-center`, `cm-competency-library`, `cm-development-career`,
`cm-employee-profiles`, `cm-framework-mapping`, `cm-libraries-taxonomy`, `cm-taxonomy-ontology`,
`competency-focus-banner`, `kasba-rating-panel`, `task-competency-inline-panel`

**24 are not.** The substantial ones:

| File | Lines | |
|---|---:|---|
| `cm-audit.tsx` | 1,215 | governance / approval trail |
| `cm-candidate-assessments.tsx` | 1,214 | |
| `cm-assessment-workspace.tsx` | 853 | |
| `cm-assessment-console.tsx` | 728 | |
| `competency-form.tsx` | 591 | |
| `cm-assessment-generator.tsx` | 555 | |
| `course-competencies-panel.tsx` | 354 | |
| `cm-skill-taxonomy.tsx` | 321 | |
| `role-requirements-panel.tsx` | 322 | |
| *(15 more, 23–309 lines each)* | ~2,900 | |

**The missing 24 are overwhelmingly the assessment and audit half** — assessment console, generator,
workspace, result, candidate assessments, self-rating, my-capability, and the audit trail. What was ported is
the **library / taxonomy / profile** half.

That is a coherent split, not a random one — and it means the two halves were probably separated
deliberately, then never finished.

---

## What the database says

45 tables match competency / capability / kasba / evidence. **26 are completely empty.** The populated ones:

| Table | Rows | Side |
|---|---:|---|
| `pal_competencies` | **24,003** | K-12 / PAL |
| `hpbrain_capabilities` | **2,398** | EB |
| `hpbrain_capability_tasks` | 1,224 | EB |
| `competency` | 3 | shared |
| **`competency_kasba_item`** | **1** | — |
| **`competency_kasba_rating`** | **0** | — |
| 8 × `s_competency_*` | 1–9 each | — |

Three consequences:

1. **KASBA is an empty shell.** 1 item, 0 ratings — yet its UI panel is live and rendered
   (`employee-profiles-center.tsx:440`). Classic "built but inert" (#56).
2. **Only two vocabularies are populated**, and they are **already in one schema**, one `JOIN` apart. No
   cross-repo work is needed to reconcile them.
3. **26 empty tables** are abandoned scaffolding. Deleting them is a bigger cleanup win than any UI work.

---

## What this means for the request

Copying the remaining 24 files as-is would:

- add ~10,700 lines in a **third** shape (G2G's, alongside `components/domain/*` and
  `app/capability-intelligence/**`),
- give this repo **two competency codebases** with overlapping concerns,
- and do precisely what **#34** warns against — *"a second competency engine that later has to be reconciled"*.

**Recommended sequence (B6b → B6d), each step independently reviewable:**

1. **B6b — decide the target shape first.** Either move `app/capability-intelligence/**` into
   `components/domain/competency/` + thin pages (matching LMS and Organization), or accept the inlined shape
   and stop pretending there is a convention. **This decision costs nothing now and everything later.**
   *Recommendation: match the existing convention* — two domains already prove it, and it makes the remaining
   24 files a mechanical copy rather than a redesign.
2. **B6c — rename `app/people-competency` → `app/lms-learning`** (or fold into the existing LMS routes).
   It contains no competency code; the name is the only problem. Cheap, low risk, removes one "estate" from
   the count outright.
3. **B6d — port the missing 24 files into the agreed shape**, assessment half first since it is coherent on
   its own, and only what has a real consumer.

**Do NOT delete `kasba-rating-panel.tsx`** despite the tracker calling it dead code — it is imported and
rendered live. That claim is stale (see the B2 audit).

---

## The one question that needs G2G's owner

Both populated vocabularies sit in one database:

> Are `hpbrain_capabilities` (2,398 rows) and `pal_competencies` (24,003 rows) the same vocabulary at
> different grain, or genuinely different things?

If the same → one is canonical, the other becomes a view, and #34 closes at the data layer without any UI
work at all. If different → both stay, and the decision is only about UI shape.

**This is a half-day of SQL, not a migration project** — and it should be answered before B6d ports anything,
because the answer decides whether the assessment half has one vocabulary to write against or two.
