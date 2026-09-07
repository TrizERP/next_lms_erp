# Chapter 1014 ("Metals and Non-metals") — Node Content Health Report

**Purpose:** for every K/A/S node across all 17 concepts of Chapter 1014, how many of its tagged
questions are actually *servable* to a student today — i.e. MCQ (`question_type_id = 1`, with
`answer_master` options) and `quality_status = 'approved'` (the two gates `QuestionMetadata::
scopeServable()` and `EsoPolicyService::hydrateQuestion()` apply). A node with real tagged
content can still be functionally dead if none of that content is MCQ.

No database change is made by this report. No node's content was modified. This is inspection
only, produced while investigating the Node 195 (S114) pilot blocker reported separately.

**Method:** every question tagged to every node in `pal_concept_nodes` for chapter 1014's 17
concepts, cross-referenced against `lms_question_master.question_type_id` and
`pal_question_metadata.quality_status`. All tagged rows in this chapter are `quality_status =
'approved'`, so "servable MCQ count" here equals the raw MCQ count — there is no additional
approval-queue backlog hiding behind these numbers.

## Risk levels used below

| Risk | Servable MCQ count | Diagnostic (D1) | Practice/Retrieval (D3-D5) |
|---|---|---|---|
| **CRITICAL** | 0 | `diagnosticItems()` yields nothing for this node — it is silently skipped or, if every node in the concept is CRITICAL, the concept's diagnostic renders zero questions | `practiceItem()`/retrieval return `null` — structural dead end, matches the Node 195 finding exactly |
| **HIGH** | 1 | Works, but only one data point (double-weighted) decides skip/no-skip | Every practice/retrieval attempt re-serves the *same* single question — no variety, trivially memorizable, D5's expected 2-3 item retrieval check cannot be met |
| **MEDIUM** | 2-3 | Works | Workable but thin; retrieval checks will often repeat |
| **LOW (healthy)** | 4+ | Works | Adequate rotation |

## Summary

**20 of this chapter's 32 nodes (62.5%) have zero servable MCQ content today — not just Node
195.** Nine of the seventeen concepts (121, 123, 124, 125, 126, 127, 128, 129, 130) have **zero
servable MCQs on every one of their nodes**, meaning D1 diagnostic renders no questions at all
for those concepts, independent of the Node 195 issue. This is a materially bigger gap than the
89.5%-tagged / 23-untagged-questions figure in the existing readiness checklist suggests — that
figure measures whether a question got *any* concept/node tag, not whether the tagged content is
*answerable* by the current MCQ-only engine. Concept 114 (this pilot's target concept) is, by
comparison, one of the **best-covered** concepts in the chapter: both its K and A nodes are LOW
risk; only its S node (195) is CRITICAL.

## Full node table

| Node ID | Concept ID | Concept name | Node type | Question count | MCQ count | Application count | Transfer count | Diagnostic suitability | Practice suitability | Risk | Recommended action |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 91 | 114 | Physical Properties of Metals | K | 6 | 3 | 0 | 0 | Workable | Workable (3-way rotation) | LOW | None — healthy |
| 92 | 114 | Physical Properties of Metals | A | 16 | 6 | 6 | 0 | Workable | Workable | LOW | None — healthy |
| **195** | **114** | **Physical Properties of Metals** | **S** | **1** | **0** | **0** | **0** | **Broken (falls through to K/A only)** | **Broken — `practiceItem()` returns null** | **CRITICAL** | **See separate S114 recommendation below — content-authoring decision needed** |
| 93 | 115 | Physical Properties of Non-metals | K | 3 | 2 | 0 | 0 | Workable | Workable (thin) | MEDIUM | Monitor; a 3rd MCQ would help |
| 94 | 115 | Physical Properties of Non-metals | A | 10 | 4 | 4 | 0 | Workable | Workable | LOW | None — healthy |
| 95 | 116 | Malleability and Ductility | K (only node) | 5 | 2 | 0 | 0 | Workable | Workable (thin) | MEDIUM | Concept has no A node at all — separate scope question, not addressed here |
| 96 | 117 | Chemical Reactivity of Metals | K | 17 | 6 | 0 | 0 | Workable | Workable | LOW | None — healthy |
| 196 | 117 | Chemical Reactivity of Metals | A | 8 | 0 | 0 | 0 | Broken for this node | Broken — `practiceItem()` returns null | CRITICAL | Same pattern as S114: 8 narrative items exist, zero are MCQ |
| 197 | 118 | Amphoteric Oxides | K | 6 | 2 | 0 | 0 | Workable | Workable (thin) | MEDIUM | Monitor |
| 198 | 118 | Amphoteric Oxides | A | 5 | 1 | 1 | 0 | Workable | Single-item repeat | HIGH | A 2nd/3rd MCQ would remove the repeat-item risk |
| 199 | 119 | Reactivity Series of Metals | K | 12 | 9 | 0 | 0 | Workable | Workable | LOW | None — healthy |
| 200 | 119 | Reactivity Series of Metals | A | 23 | 6 | 6 | 0 | Workable | Workable | LOW | None — healthy |
| 201 | 120 | Ionic Compound Formation | K | 5 | 1 | 0 | 0 | Workable | Single-item repeat | HIGH | A 2nd/3rd MCQ would remove the repeat-item risk |
| 202 | 120 | Ionic Compound Formation | A | 5 | 0 | 0 | 0 | Broken for this node | Broken | CRITICAL | 5 narrative items exist, zero MCQ |
| 203 | 121 | Properties of Ionic Compounds | K | 2 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 204 | 121 | Properties of Ionic Compounds | A | 5 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 205 | 122 | Minerals, Ores, and Gangue | K | 11 | 3 | 0 | 0 | Workable | Workable (thin) | MEDIUM | Monitor |
| 206 | 122 | Minerals, Ores, and Gangue | A | 3 | 0 | 0 | 0 | Broken for this node | Broken | CRITICAL | 3 narrative items exist, zero MCQ |
| 207 | 123 | Metallurgy | K (only node) | 2 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Only node in the concept; zero MCQ |
| 208 | 124 | Extraction of Metals | K | 2 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 209 | 124 | Extraction of Metals | A | 5 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 210 | 125 | Roasting and Calcination | K | 2 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 211 | 125 | Roasting and Calcination | A | 4 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 212 | 126 | Electrolytic Refining | K | 8 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 213 | 126 | Electrolytic Refining | A | 1 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 214 | 127 | Corrosion | K | 7 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 215 | 127 | Corrosion | A | 5 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 216 | 128 | Rusting of Iron | K | 1 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 217 | 128 | Rusting of Iron | A | 1 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 218 | 129 | Prevention of Corrosion | K (only node) | 4 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Only node in the concept; zero MCQ |
| 219 | 130 | Alloys | K | 9 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |
| 220 | 130 | Alloys | A | 3 | 0 | 0 | 0 | **Concept-wide: broken** | Broken | CRITICAL | Whole concept (both nodes) has zero MCQ |

*("Application count" / "Transfer count" columns are 0 for K-type and most A/S-type rows because
each node's tagged items are homogeneous by design — a K node holds `item_type = recall` items,
an A node holds `application` items, the one S node holds `transfer` items. The columns are kept
per the requested template but are not independently informative given the current one-item-type-
per-node tagging convention.)*

## Concepts with zero usable diagnostic content (every node CRITICAL)

121 (Properties of Ionic Compounds), 123 (Metallurgy), 124 (Extraction of Metals), 125 (Roasting
and Calcination), 126 (Electrolytic Refining), 127 (Corrosion), 128 (Rusting of Iron), 129
(Prevention of Corrosion), 130 (Alloys) — **9 of 17 concepts**. For these, `nextAction()`'s D1
entry point would call `diagnosticItems()`, which would return an empty array; a student entering
any of these concepts today would see a diagnostic step render zero questions.

## Scope note

This report only covers Chapter 1014. It does not assess whether the same MCQ-availability
pattern exists in other chapters — that would require separately auditing each chapter's own
question bank and is outside what this investigation was asked to cover.
