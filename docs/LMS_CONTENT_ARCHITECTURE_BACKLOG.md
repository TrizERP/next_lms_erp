# LMS Content Architecture — Backlog (frozen for demo week)

> Captured from the architecture review so nothing is lost.
> **NONE of this touches the live navigation before the demo.** See the Readiness Framework rule (item #1).

**Owner track for every item below: Track A.**

---

## Rule #0 — The Freeze

**Item 1 — Freeze live LMS navigation for demo week** · Readiness tier: **Demo Ready** · Status: **🔴 Do NOT touch before demo**

No IA changes to Subject / Chapter / Resource screens until after this week's demo. Every item below is *backlog*, not this-week work.

**Action required:** Confirm with the team explicitly — nothing in this document gets built or changed before the demo.

---

## Needs Team Decision (blockers — resolve before building)

### Item 6 — One shared Question Intelligence Engine (PAL + Classroom + Examination)
- **Readiness tier:** Independent Use Ready
- **Depends on:** Concept Intelligence's existing metadata schema (verify overlap)
- **Description:** A single question bank / metadata engine serving diagnostic, practice, mastery, and formal examination use cases — *not* separate databases per use case.
- **Action required:**
  1. **VERIFY FIRST:** does the current Question Bank + Concept Intelligence panel already share one schema, or are they separate today? This determines whether this is a *consolidation* or a *build*.
  2. **UPDATED:** must implement **DUAL fitness-for-purpose metadata** — `pal_calibration` + `board_compliance` tags per item. "Calibrated for PAL" and "compliant for a board exam" are different criteria over the same question, not one shared score.
  3. See the *PAL & Content Model* tab for the full schema.

### Item 10 — "Enrichment" naming collision (two different meanings in use)
- **Readiness tier:** Independent Use Ready
- **Depends on:** Course Catalog & Taxonomy naming
- **Description:** Document 8 uses "Enrichment" as a per-concept extension activity (inside Learning Experience). Document 9 uses "Enrichment" as a subject-category tier (AI Literacy, Coding, Robotics — same territory as "Future Capabilities" in Course Catalog).
- **Action required:** Resolve the naming **BEFORE either is built**, or the dev team will build the wrong one or duplicate effort.
  - **Recommendation:** keep **"Enrichment"** for the per-concept activity; keep **"Future Capabilities"** for the subject-category tier.

---

## Independent Use Ready — post-demo build queue

### Item 2 — Teacher Resource → Teacher Workspace (rename + reframe)
- **Depends on:** Concept Intelligence, Student Evidence store
- **Description:** Reframe from a resource *library* into a working *environment*: what to teach, how to teach it, what students know, who needs help, what's next.
- **Action required:** Naming + IA change — sequence **after** the demo, bundle with the Learning Experience split (item 3).

### Item 3 — Learning tab → Learning Experience (Understand / Interactive Learning / Practice / Activities / Enrichment split)
- **Depends on:** Content ownership field, Content Authoring capability
- **Description:** Replace the flat Classroom / Student / Interactive resource split with a purpose-based sequence matching the concept learning arc.
- **Action required:**
  - **TERMINOLOGY CORRECTION:** "Student Resources" → **"Learning Resources"**. The same resource is consumed in class, at home, and inside PAL, so "Student" incorrectly implies a single consumption context.
  - Genuine IA redesign — ship as **one coordinated release**, not incrementally, to avoid a half-migrated state.

### Item 4 — Interactive Content → Interactive Learning (terminology)
- **Readiness tier:** Demo Ready (naming only) / Independent Use Ready (full capability)
- **Depends on:** H5P tag fix (already in the Content & LMS Architecture tab)
- **Description:** H5P, simulations, drag-and-drop, virtual labs etc. all fall under one "Interactive Learning" capability — not named after the H5P technology.
- **Action required:** The naming change itself is low-risk and could ship the same week as the H5P tag fix if capacity allows — otherwise bundle with the Learning Experience split (item 3).

### Item 5 — Content governance workflow (Draft → Teacher Preview → Publish → School Review → Master Review)
- **Depends on:** Central Workflow Engine, Content ownership field
- **Description:** Multi-stage approval before teacher/school content becomes visible more broadly.
- **Action required:** Register as a **Workflow Engine flow ("Content Publish Approval")** — do **NOT** build a bespoke approval mechanism. Same rule as the Fees module's refund/discount approvals.

### Item 8 — Academic Operations as a separate top-level area
- **Depends on:** RBAC, Workflow Engine
- **Description:** Academic Calendar, Lesson & Teaching Management, Assignment Management pulled out of "Learning" into their own school-operations area.
- **Action required:** Matches the placement rule *"ScholarClone owns learning experience, school owns academic operations"* — **adopt the rule now, build the area later.**

### Item 11 — Content Learning Resource metadata (Purpose / Audience / Delivery Mode, not location-bound)
- **Depends on:** Content ownership field, Content Authoring capability
- **Description:** Store resources by purpose + audience + delivery rather than duplicating the same resource per consumption context (classroom vs. home vs. PAL).
- **Action required:** Prevents content duplication across Classroom / Student / PAL — adopt this schema shape when the Learning Experience split (item 3) is actually built.

---

## Future Module (separate sizing)

### Item 7 — Lesson Kit (before / during / after class bundle per concept)
- **Depends on:** Learning Experience split, Question Intelligence Engine
- **Description:** Packaged bundle — prep materials, in-class materials, after-class materials, and student evidence — presented as one selling unit per concept.
- **Action required:** Strong sales feature, but net-new — **size and sequence independently**; do not fold into the content architecture cleanup.

### Item 9 — Examination as its own full module (blueprint, moderation, paper generator, marks entry, report card)
- **Depends on:** Question Intelligence Engine, Workflow Engine, RBAC
- **Description:** A ~14-item module comparable in size to Fees — exam calendar, blueprint, question paper generation, moderation, conduct, marks entry, evaluation, result processing, report card.
- **Action required:** Do **NOT** bundle into the "content architecture cleanup" — this needs its own audit-style scoping exercise the way Fees got, given comparable size.

---

## Suggested sequencing (derived from the dependency column)

| Order | Work | Why |
|---|---|---|
| 0 | Hold the freeze (item 1) | Demo week |
| 1 | Decide items 6 & 10 | Both block builds; item 6 needs a verification pass first |
| 2 | Item 4 naming + H5P tag fix | Low-risk, can ship alone if capacity allows |
| 3 | Coordinated release: items 2 + 3 + 11 (+ 4 if not already shipped) | One IA release, no half-migrated state |
| 4 | Item 5 on the Workflow Engine; adopt item 8's placement rule | Depends on the ownership field from step 3 |
| 5 | Size items 7 & 9 separately | Net-new modules, own scoping exercises |

---

## Status summary

| # | Item | Readiness Tier | Status |
|---|---|---|---|
| 1 | Freeze live LMS navigation | Demo Ready | Do NOT touch before demo |
| 2 | Teacher Resource → Teacher Workspace | Independent Use Ready | Not Started |
| 3 | Learning tab → Learning Experience | Independent Use Ready | Not Started |
| 4 | Interactive Content → Interactive Learning | Demo Ready / Ind. Use Ready | Not Started |
| 5 | Content governance workflow | Independent Use Ready | Not Started |
| 6 | One shared Question Intelligence Engine | Independent Use Ready | **Needs Team Decision** |
| 7 | Lesson Kit | Future Module | Not Started |
| 8 | Academic Operations as separate top-level area | Independent Use Ready | Not Started |
| 9 | Examination as its own full module | Future Module | Not Started |
| 10 | "Enrichment" naming collision | Independent Use Ready | **Needs Team Decision** |
| 11 | Content Learning Resource metadata | Independent Use Ready | Not Started |
