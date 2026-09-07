# Chapter 1014 ("Metals and Non-metals") — Content Review Queue

**Status: PENDING CONTENT-TEAM REVIEW — none of these 23 questions has been modified.**
No database write is associated with this document. No new concept, node, or misconception has
been created or proposed here — every recommendation below is a choice for the content team to
make, not an action already taken.

Source: `docs/CHAPTER_1014_FULL_TAGGING_PROPOSAL.md` §4, the 23 of Chapter 1014's 220 questions
left untagged by that proposal because no confident concept/node/item_type assignment could be
made honestly. 197 of 220 questions (89.5%) are already tagged and are not part of this queue.

## How to use this document

Each item below has a **Final decision** field at the end — that is the only field a reviewer
needs to fill in. Pick one of the listed options (or write a different one) and this queue
becomes the direct input to a follow-up tagging pass. Items are grouped into four categories
that share the same kind of problem and the same kind of decision, so similar items can be
approved together rather than one at a time.

| Category | Count | What it means |
|---|---|---|
| A — Taxonomy gap (non-metal reactivity / oxide acid-base nature) | 9 | The question tests real Chapter 3 content, but none of the 17 defined concepts covers it |
| B — Composite question | 7 | The question has multiple sub-parts that span several different concepts |
| C — Out of scope for this chapter's taxonomy | 5 | The question doesn't test any of the 17 concepts' content at all |
| D — Near-miss (closest concept doesn't actually fit) | 2 | A concept exists on the same general topic, but tagging to it would misrepresent what the question tests |

---

## Summary table (all 23, for quick scanning)

| ID | Type | Topic | Category | Closest concept |
|---|---|---|---|---|
| 104569 | MCQ | Non-metals dissolving in water → acidic oxides | A | 115 / 117 (both weak) |
| 85143 | Narrative | Sulphur burns in air, litmus test | A | 115 / 117 (both weak) |
| 85145 | Narrative | Oxides formed when non-metals + oxygen | A | 115 / 117 (both weak) |
| 85206 | Narrative | Non-metal A (carbon) forms oxides B, C | A | 115 / 117 (both weak) |
| 85212 | Narrative | Oxide A₂O₃, acidic — metal or non-metal? | A | 118 (closest, still wrong axis) |
| 85214 | Narrative | Non-metal A (nitrogen) reaction chain | A | 115 / 117 (both weak) |
| 85231 | Narrative | Name two neutral oxides | A | 118 (closest, still wrong axis) |
| 85276 | Narrative | Which oxides turn litmus red (acidic oxide ID) | A | 118 (closest, still wrong axis) |
| 85281 | Narrative | Sulphur burns in air → acidic compound (activity) | A | 115 / 117 (both weak) |
| 85146 | Narrative | 4 sub-parts: noble metals, Na/K storage, Al passivation, ore→oxide | B | 127, 117, 129, 125 |
| 85150 | Narrative | Large metal-vs-non-metal property comparison table | B | 114, 115, 117, 118, 119 (+ gap) |
| 85216 | Narrative | 5 sub-parts: passivation, carbon-reduction limits, NaCl conductivity, galvanising, native metals | B | 129, 124, 120/121, 129, 122 |
| 85283 | Narrative | 3 sub-parts: Zn/Cu displacement, Ag tarnish, sulphide→oxide | B | 119, 127, 125 |
| 85284 | Narrative | 3 sub-parts: roasting/calcination, thermite, electrorefining | B | 125, 124, 126 |
| 85285 | Narrative | 2 sub-parts: physical-property examples, electrolytic refining | B | 114, 126 |
| 85286 | Narrative | 3 sub-parts: Cu extraction, reducing agent, green coating | B | 124/125, 124, 127 |
| 104530 | MCQ | Colour of an unnamed salt solution | C | 119 (superficial only) |
| 104533 | MCQ | Lab supply form of Zn/Cu (granules vs turnings) | C | 119 (superficial only) |
| 104537 | MCQ | Colour of copper sulphate solution | C | 119 (superficial only) |
| 104561 | MCQ | Insulating coating on household wires (PVC) | C | none |
| 85189 | Narrative | Insulating coating on electrical wires (duplicate of 104561) | C | none |
| 85271 | Narrative | Do pure liquids conduct? Why doesn't pure water electrolyse? | D | 126 (near-miss) |
| 85275 | Narrative | Metallic compound + HCl → gas that turns limewater milky | D | 125 (near-miss) |

---

## Category A — Taxonomy gap: non-metal chemical reactivity (9 items)

**Why these are uncertain, as a group:** every item here tests how a *non-metal* behaves
chemically (burning in air, forming an oxide, and whether that oxide is acidic) — real,
explicitly-taught Chapter 3 content (NCERT Class 10 Ch. 3 covers it directly). But of the 17
defined concepts, concept 117 ("Chemical Reactivity of Metals") is scoped to metals only by its
own description, and concept 115 ("Physical Properties of Non-metals") is scoped to *physical*
properties only. Neither is a genuine fit; every item in this group would need a real judgment
call to force onto one of them.

**Closest existing concept/node:** concepts 115 and 117 are the two nearest by subject-matter
proximity, but both fail on a different axis (wrong element class for 117, wrong property type
for 115) — reviewers should not read "closest" as "acceptable."

**Recommended decision for the group:** leave all 9 untagged for the pilot. Chapter 1014's Phase
0 tagging was always partial by design (the Developer Brief does not require 100% question
coverage), and inventing a new concept is explicitly out of scope for this pass. If the content
team later wants this content covered, the clean fix is a new concept (e.g. "Chemical Reactivity
of Non-metals") added to `lms_concept` for chapter 1014 — that is a content-authoring decision,
not something this review queue or its follow-up tagging pass should do unilaterally.

| ID | Type | Question | Current mapping | Problem |
|---|---|---|---|---|
| 104569 | MCQ | "When nonmetals dissolve in water…" (correct: produces acidic oxides) | none | Tests non-metal + water → acidic oxide; no concept covers non-metal chemical behaviour |
| 85143 | Narrative | Pratyush heated sulphur powder, collected the gas — action on dry/moist litmus, balanced equation | none | Same non-metal-reactivity gap |
| 85145 | Narrative | What types of oxides are formed when non-metals combine with oxygen? | none | Same gap, general form of the question |
| 85206 | Narrative | Non-metal A (important in food) forms oxides B (toxic) and C (causes global warming) — identify A, B, C | none | Same gap (carbon oxide chemistry) |
| 85212 | Narrative | An element forms oxide A₂O₃, acidic in nature — is A a metal or non-metal? | none | Tests acidic-vs-basic oxide nature generally; concept 118 is the nearest neighbour but is specifically about *amphoteric* oxides, not simple acidic ones |
| 85214 | Narrative | Non-metal A (largest constituent of air) + H₂ → B; + O₂ → C; C + H₂O + air → acid D | none | Same gap (nitrogen reactivity chain) |
| 85231 | Narrative | Write the names of two neutral oxides (CBSE 2010) | none | Oxide-classification question; concept 118 is the nearest neighbour but covers amphoteric, not neutral, oxides |
| 85276 | Narrative | Identify which oxides (CO₂, Na₂O, CaO, SO₂, NO₂) turn blue litmus red; state their nature | none | Acidic-non-metal-oxide identification; same gap as 85212/85231 |
| 85281 | Narrative | With a suitable activity, show that sulphur burns in air to form an acidic compound | none | Same gap as 85143, activity-based framing |

**Final decision** (repeat per item, or one blanket decision for all 9):
`[ ] Leave untagged for v1 pilot   [ ] Add new concept "___" to lms_concept, then retag   [ ] Force-tag to concept ___ (state rationale)   [ ] Other: ___`

---

## Category B — Composite questions (7 items)

**Why these are uncertain, as a group:** each item has 2–5 distinct sub-parts, and the sub-parts
test *different* concepts. The Adaptive Learning Engine's diagnostic and practice loop works at
single-concept, single-node granularity (`answer_master.correct_answer` is one flag per question)
— even if one of these were force-tagged to a single concept, the engine could never credit or
diagnose the other sub-parts correctly, so a single tag would misrepresent the item regardless of
which concept were chosen.

**Closest existing concept/node:** listed per item below — in every case it is only the
*dominant or first* sub-part's concept, not a fit for the whole question.

**Recommended decision for the group:** leave all 7 untagged for the pilot. The durable fix is
splitting each into separate `lms_question_master` rows (one per sub-part), which is a
question-authoring task outside this review's scope — flagging it to the content team as a
worthwhile follow-up, not doing it here.

| ID | Question (sub-parts) | Sub-part concepts | Problem |
|---|---|---|---|
| 85146 | (a) Pt/Au/Ag jewellery (b) Na/K/Li stored under oil (c) Al reactive but used for utensils (d) carbonate/sulphide ores → oxides | 127, 117, 129, 125 | 4 unrelated sub-parts |
| 85150 | Large table differentiating metals/non-metals across every chemical property axis | 114, 115, 117, 118, 119 + the Category A gap | Too broad for any single concept; also touches the non-metal-reactivity gap |
| 85216 | (a) Al passivation (b) carbon can't reduce Na/Mg oxides (c) NaCl conductivity by state (d) galvanising (e) Na/K/Ca/Mg never found free | 129, 124, 120/121, 129, 122 | 5 unrelated sub-parts |
| 85283 | (a) Zn displaces Cu from CuSO₄ (b) Ag blackens over time (c) sulphide ore → oxide | 119, 127, 125 | 3 unrelated sub-parts |
| 85284 | (a) roasting vs. calcination, which for sulphide ores (b) thermite equation (c) anode/cathode/electrolyte for Cu refining | 125, 124, 126 | 3 unrelated sub-parts |
| 85285 | (a) physical-property examples (liquid metal, kerosene-stored, malleable+ductile, best conductor) (b) electrolytic refining of copper | 114, 126 | 2 unrelated sub-parts |
| 85286 | (a) Cu extraction equations (b) reducing agent for top-of-series metals (c) green coating on copper | 124/125, 124, 127 | 3 unrelated sub-parts |

**Final decision** (per item, or one blanket decision for all 7):
`[ ] Leave untagged for v1 pilot   [ ] Split into separate questions, then tag each part   [ ] Force-tag whole item to its dominant sub-part's concept (accept imprecision)   [ ] Other: ___`

---

## Category C — Out of scope for this chapter's taxonomy (5 items)

**Why these are uncertain, as a group:** these questions don't test conceptual understanding of
any of the 17 defined concepts — they're recall of incidental lab facts (a solution's colour, the
physical form a reagent is supplied in) or a fact about an unrelated topic (wire insulation) that
happens to sit in this chapter's question bank.

**Closest existing concept/node:** for the three salt-colour/lab-trivia items, concept 119
(Reactivity Series) is a *superficial* neighbour only — these questions share vocabulary
("copper sulphate solution," "zinc," "iron nail") with 119's genuine displacement-reaction
content, but test none of the actual reasoning 119 is meant to assess. The two PVC-wire items
have no reasonable concept match at all.

**Recommended decision for the group:** leave all 5 untagged permanently — this is not a gap to
close, the content itself is outside this chapter's adaptive-engine scope. Separately worth
flagging to content QA (not an adaptive-engine decision): whether 104561/85189 (near-duplicate
PVC-insulation questions) belong in the Chapter 1014 question bank at all.

| ID | Type | Question | Problem |
|---|---|---|---|
| 104530 | MCQ | "A colourless solution kept in a test tube could be…" (Ferrous sulphate/Copper sulphate/Aluminium sulphate/potassium permanganate) | Recall of salt-solution colours, not a reactivity-series comparison or prediction |
| 104533 | MCQ | "Zinc and copper metal used in the laboratory is available respectively in the form of…" | Lab-supply-form trivia, not chemistry content |
| 104537 | MCQ | "Some crystals of copper sulphate were dissolved in water. The colour of the solution would be…" | Same as 104530 — salt-colour recall |
| 104561 | MCQ | "Wires which used in homes that are coated with…" (PVC) | Electrical-insulation-material fact, unrelated to metallurgy/reactivity |
| 85189 | Narrative | "Electrical wires have a coating of insulating material — which one?" | Near-duplicate of 104561, same issue |

**Final decision** (per item, or one blanket decision for all 5):
`[ ] Leave untagged permanently (confirmed out of scope)   [ ] Remove from chapter 1014 question bank entirely (separate content-QA action)   [ ] Other: ___`

---

## Category D — Near-miss: an existing concept is topically close but doesn't fit (2 items)

**Why these are uncertain, as a group:** unlike Category A/C, a genuinely relevant concept
*exists* for each of these — but its actual defined scope is narrower than what the question
tests. Force-tagging would let an off-topic question feed that concept's mastery signal, which
risks giving a student credit (or blame) for something the concept isn't really measuring.

**Recommended decision for the group:** leave both untagged for the pilot. Force-tagging is not
recommended; if the content team wants this content covered, it needs either a broadened concept
description or a new sub-concept — a deliberate content decision, not a default.

| ID | Question | Closest concept | Problem |
|---|---|---|---|
| 85271 | "Are all pure liquids bad conductors of electricity? Name one that's a good conductor but doesn't electrolyse; why doesn't pure water electrolyse?" | 126 — Electrolytic Refining | 126 is specifically about refining *impure metal* via electrolysis, not general electrolyte/conductivity theory — this question tests neither refining nor a metal |
| 85275 | "Metallic compound A + dilute HCl → effervescence + gas B; B extinguishes a candle and turns limewater milky. Identify A and B." | 125 — Roasting and Calcination | 125 is specifically about heating ores in air/limited air; this question is a carbonate + acid → CO₂ identification, a different reaction entirely |

**Final decision** (per item):
`[ ] Leave untagged for v1 pilot   [ ] Force-tag to the closest concept anyway (state rationale)   [ ] Other: ___`

---

## After this review

Once the content team fills in the **Final decision** fields above, a follow-up tagging pass can
apply whatever was approved (additional questions tagged, a new concept authored, or specific
items formally marked "out of scope, do not revisit") — through the same review-then-apply
process used for the rest of this chapter. Nothing in this document should be treated as applied
until that follow-up happens.
