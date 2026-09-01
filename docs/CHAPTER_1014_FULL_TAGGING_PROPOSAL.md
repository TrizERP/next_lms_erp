# Chapter 1014 ("Metals and Non-metals") — Full Content-Tagging Proposal

**Status: PROPOSAL ONLY — nothing in this document has been written to the database.**
Scope: the **205 questions** in `lms_question_master` for `chapter_id=1014` that were **not** covered by
the prior human-reviewed pass (question ids listed in the Background section of the task; verified by
`SELECT question_id FROM pal_question_metadata WHERE chapter_ref_id=1014 AND node_id IS NOT NULL`, which
returns exactly the 15 ids 104556, 104557, 104558, 104559, 104560, 104562, 104563, 104564, 104565, 104566,
104567, 104568, 104570, 104571, 104572).

Chapter 1014 has 220 questions total: 50 MCQ (`question_type_id=1`) and 170 narrative/free-text
(`question_type_id=2`). Of the 205 remaining, 35 are MCQ and 170 are narrative.

Every question below was read individually (title text, and for MCQs every `answer_master` option text)
and matched against the 17 real concepts (`lms_concept.id` 114–130) for this chapter, and against the
current 46-row `pal_misconception_library` catalog for this chapter (ids 140–183 minus gaps, plus
3670–3672). No new misconceptions are proposed in this pass — only reuse of the existing catalog, per
instructions.

---

## 1. Summary

| Metric | Count |
|---|---|
| Questions already tagged (prior pass) | 15 |
| Remaining questions in scope for this proposal | 205 |
| **Confidently tagged in this proposal** | **182** |
| Left "uncertain — needs human review" | 23 |
| **Total chapter coverage if this proposal is applied** | **197 / 220 (89.5%)** |

Confident tags by concept (this proposal only; excludes the 15 already done):

| concept_id | Concept name | Narrative confident | MCQ confident | Total confident |
|---|---|---|---|---|
| 114 | Physical Properties of Metals | 14 | 3 | 17 |
| 115 | Physical Properties of Non-metals | 7 | 2 | 9 |
| 116 | Malleability and Ductility | 3 | 0 | 3 |
| 117 | Chemical Reactivity of Metals | 19 | 3 | 22 |
| 118 | Amphoteric Oxides | 8 | 3 | 11 |
| 119 | Reactivity Series of Metals | 20 | 15 | 35 |
| 120 | Ionic Compound Formation | 9 | 1 | 10 |
| 121 | Properties of Ionic Compounds | 7 | 0 | 7 |
| 122 | Minerals, Ores, and Gangue | 11 | 3 | 14 |
| 123 | Metallurgy | 2 | 0 | 2 |
| 124 | Extraction of Metals | 7 | 0 | 7 |
| 125 | Roasting and Calcination | 6 | 0 | 6 |
| 126 | Electrolytic Refining | 9 | 0 | 9 |
| 127 | Corrosion | 12 | 0 | 12 |
| 128 | Rusting of Iron | 2 | 0 | 2 |
| 129 | Prevention of Corrosion | 4 | 0 | 4 |
| 130 | Alloys | 12 | 0 | 12 |
| **Total** | | **152** | **30** | **182** |

Uncertain: 18 narrative + 5 MCQ = 23 (see Section 4).

Reactivity Series of Metals (119) dominates because the majority of this chapter's MCQ bank and many
narrative items are lab-observation questions built around the displacement-reaction activities (Zn/Fe/Cu/Al
in various salt solutions) — all of which concept 119's description ("helps predict displacement
reactions") covers directly.

**Notable gap found:** none of the 17 defined concepts covers *non-metal chemical reactivity* (e.g. "non-metal
oxides are acidic", sulphur burning in air, nitrogen/carbon oxide chemistry). Concept 117 is scoped
explicitly to metals ("Metals react with oxygen, water, and acids…"), and 115 is *physical* properties of
non-metals only. Roughly 8 of the 23 uncertain items exist purely because of this taxonomy gap — see
Section 4.

---

## 2. Confidently-tagged questions, by concept

Node column shows either an **existing numeric id** (verified live against `pal_concept_nodes`) or a
**new placeholder key** (e.g. `K118`, `A119`) to be resolved to a real id only when inserted. Item type is
`recall` / `application` / `transfer`.

### Concept 114 — Physical Properties of Metals (17)
Existing nodes reused: **K114 = id 91**, **A114 = id 92**. New: **S114** (placeholder, transfer).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85120 | Give example of a metal which (a) is a liquid at room temperature (b) can be easily cut with a knife (c) is the best conductor of heat (d) is the poorest conductor of heat. | A114 (92) | application |
| 85139 | You are given a hammer, a battery, a bulb, wires and a switch: (a) How could you use them to distinguish between samples of metals and non-metals? (b) Assess the usefulness of these tests to distinguish between metals and non-metals. | **S114** (new) | transfer |
| 85157 | Which of the following properties is generally not shown by metals? (a) Electrical conduction (b) Sonorous nature (c) Dullness (d) Ductility. | K114 (91) | recall |
| 85159 | Aluminium is used for making cooking utensils. Which of the following properties of the metal are responsible for the same? (i) Good thermal conductivity (ii) Good electrical conductivity (iii) Ductility (iv) High melting point … | A114 (92) | application |
| 85173 | Generally, metals are solid in nature. Which one of the following metals is found in liquid state at room temperature? (a) Na (b) Fe (c) Al (d) Hg | A114 (92) | application |
| 85207 | Give two examples each of the metals that are good conductors and poor conductors of heat respectively. | K114 (91) | recall |
| 85208 | Name one metal and one non-metal that exist in liquid state at room temperature. Also name two metals having melting point less than 310 K (37°C). | A114 (92) | application |
| 85233 | Write one example each of: a metal having low melting point and a metal having high melting point; a metal which is a poor conductor of electricity and a non-metal which is a good conductor of electricity. | A114 (92) | application |
| 85240 | Which metal is the best conductor of electricity? (CBSE 2010) | K114 (91) | recall |
| 85242 | Why are metals conducting in nature? | A114 (92) | application |
| 85253 | Name one metal and one non-metal which exist in liquid state at room temperature? | A114 (92) | application |
| 85258 | Why are metals good conductors of electricity while non-metals are not? (CBSE 2010, 2012) | A114 (92) | application |
| 85259 | Which important properties of aluminium are responsible for its great demand in industry? | A114 (92) | application |
| 85263 | Why is titanium called a strategic metal? Mention two of its properties which make it so special. | A114 (92) | application |
| 104539 | 4 Strips labeled as A, B, C and D have colours reddish brown, dark grey, blackish grey and silvery white respectively. Which of this could be aluminium? | K114 (91) | recall |
| 104545 | Name the metal which can be cut with a knife. (options: Calcium/Iron/**Sodium**/Aluminium) | A114 (92) | application |
| 104546 | Give an example of a metal which is a liquid at room temperature. (options: **Mercury**/Magnesium/lead/tin) | A114 (92) | application |

### Concept 115 — Physical Properties of Non-metals (9)
Existing nodes reused: **K115 = id 93**, **A115 = id 94**.

| id | Question text | Node | Item type |
|---|---|---|---|
| 85175 | Generally non-metals are not lustrous. Which of the following non-metals is lustrous? (a) Sulphur (b) Phosphorus (c) Nitrogen (d) Iodine. | A115 (94) | application |
| 85188 | Generally, non-metals are not conductors of electricity. Which of the following is a good conductor of electricity? (a) Diamond (b) Graphite (c) Phosphorus (d) Iodine | A115 (94) | application |
| 85190 | Which of the following non-metals is a liquid? (a) Carbon (b) Bromine (c) Iodine (d) Sulphur | A115 (94) | application |
| 85199 | A non-metal X exists in two different forms Y and Z. Y is the hardest natural substance, whereas Z is a good conductor of electricity. Identify X, Y and Z. | A115 (94) | application |
| 85230 | Name the non-metal which can conduct electricity. | A115 (94) | application |
| 85244 | Name two non-metals which exist in the solid state and two non-metals which exist in the gaseous state. | K115 (93) | recall |
| 85256 | Name a non-metal which is lustrous and a metal which is non-lustrous. | A115 (94) | application |
| 104547 | Name a non-metal which is a liquid. (options: **Bromine**/Iodine/Chlorine/Sulphur) | A115 (94) | application |
| 104555 | Non-metals which are lustrous and good conductor of electricity respectively are ___ and ___ (options: **iodine, graphite**/iron, sulphur/sulphur, graphite/iodine, phosphorous) | A115 (94) | application |

### Concept 116 — Malleability and Ductility (3)
Existing node reused: **K116 = id 95**.

| id | Question text | Node | Item type |
|---|---|---|---|
| 85121 | Explain the meaning of malleable and ductile. | K116 (95) | recall |
| 85158 | The ability of metals to be drawn into thin sheets is known as (a) ductility (b) malleability (c) sonorosity (d) conductivity. | K116 (95) | recall |
| 85245 | Name the metal whose foils are used for the packing of food materials. | K116 (95) | recall |

### Concept 117 — Chemical Reactivity of Metals (22)
Existing node reused: **K117 = id 96**. New: **A117** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85122 | Why is sodium kept immersed in kerosene oil? (CBSE 2011) | A117 (new) | application |
| 85123 | Write the equations for the reactions of (a) iron with steam (b) calcium with water (c) potassium with water. | K117 (96) | recall |
| 85125 | Which gas is produced when a reactive metal reacts with dilute hydrochloric acid? Write the chemical reaction when iron reacts with dilute H2SO4. (CBSE 2010) | K117 (96) | recall |
| 85154 | An element reacts with oxygen to form an oxide which dissolves in dilute hydrochloric acid. The oxide formed also turns a solution of red litmus blue. Is the element a metal or non-metal? Explain with a suitable example. | A117 (new) | application |
| 85156 | An element A catches fire in water and burns with golden yellow flame in air. It reacts with another element B, present in group 17, to give a product C. An aqueous solution of C on electrolysis gives a compound D and liberates hydrogen. Identify A, B, C and D. | A117 (new) | application |
| 85161 | Which of the following oxide(s) of iron would be obtained on prolonged reaction of iron with steam? (a) FeO (b) Fe2O3 (c) Fe3O4 (d) Fe2O3 and Fe3O4 | K117 (96) | recall |
| 85162 | What happens when magnesium is treated with water? … | K117 (96) | recall |
| 85163 | Generally metals react with acids to give salt and hydrogen gas. Which of the following acids does not give hydrogen gas on reacting with metals (except Mn and Mg)? | K117 (96) | recall |
| 85164 | Composition of aqua-regia by volume is: … | K117 (96) | recall |
| 85177 | 5 mL each of concentrated HCl, HNO3 and a mixture of concentrated HCl(15 mL)+HNO3(5 mL) were taken … the metal got dissolved in test tube C. The metal could be (a) Al (b) Au (c) Cu (d) Ag | K117 (96) | recall |
| 85181 | An element X is soft and can be cut with a knife. This is very reactive to air and cannot be kept in open air. It reacts vigorously with water. Identify the element. | A117 (new) | application |
| 85183 | Which of the following statements is not correct for magnesium metal? … | K117 (96) | recall |
| 85196 | Generally, when metals are treated with mineral acids, hydrogen gas is liberated but when metals (except Mn and Mg) are treated with HNO3, hydrogen is not liberated, why? | K117 (96) | recall |
| 85198 | When a metal X is treated with cold water, it gives a basic salt Y with molecular formula XOH (Molecular mass = 56) and liberates a gas Z which easily catches fire. Identify X, Y and Z and write the reaction. | A117 (new) | application |
| 85209 | An element A reacts with water to form a compound B used in white washing. B on heating forms an oxide C which on treatment with water gives back B. Identify A, B and C. | A117 (new) | application |
| 85211 | A metal M does not liberate hydrogen from acids but reacts with oxygen to give a black coloured product. Identify M and the black product; explain the reaction of M with oxygen. | K117 (96) | recall |
| 85248 | A shining metal X on heating in air becomes black in colour. Name the black coloured compound formed and identify X. | K117 (96) | recall |
| 85268 | Give reasons for the following: Metals replace hydrogen from dilute acids whereas non-metals do not. (CBSE 2011) | A117 (new) | application |
| 85278 | When the powder of a common metal is heated in an open china dish, its colour turns black. When hydrogen gas is passed over the hot black substance, it regains its original colour. What type of reaction takes place in each step? Name the metal. | A117 (new) | application |
| 104552 | ___ reacts with nitric acid and forms hydrogen gas. (options: **Manganese**/Iron/Sodium/Calcium) | K117 (96) | recall |
| 104554 | Magnesium reacts with ___ water (options: Cold/**Hot**/Steam/none) | K117 (96) | recall |
| 104573 | Copper is heated in air…… (options: white oxide/blue oxide/**black oxide**/green oxide) | K117 (96) | recall |

### Concept 118 — Amphoteric Oxides (11)
New nodes: **K118** (recall), **A118** (application/transfer).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85140 | What are amphoteric oxides? Give examples of two amphoteric oxides. | K118 (new) | recall |
| 85187 | Which of the following metals form an amphoteric oxide? (a) Na (b) Ca (c) Zn (d) Cu. | K118 (new) | recall |
| 85193 | Iqbal treated a lustrous, divalent element M with sodium hydroxide — bubbles formed; same when treated with HCl. Suggest how to identify the gas; write equations for both. | A118 (new) | application |
| 85202 | A metal M, used in the thermite process, when heated with oxygen gives an amphoteric oxide. Identify M and its oxide; write reactions with HCl and NaOH. | A118 (new) | application |
| 85210 | An alkali metal A gives compound B (mw=56) on reacting with water. B gives a soluble compound C on treatment with aluminium oxide. Identify A, B, C. | A118 (new) | application |
| 85255 | What are amphoteric oxides? Give an example. | K118 (new) | recall |
| 85269 | (a) Why is ZnO called an amphoteric oxide? Name another. (b) What are alkalies? Give one example. | K118 (new) | recall |
| 85288 | (a) Most metals do not react with bases but zinc does — suggest a reason; write the Zn+NaOH equation. (b) Metal X + cold water → base Y (XOH, mw=40) + gas Z. Identify X, Y, Z. | A118 (new) | application |
| 104548 | ___ is an amphoteric oxide. (options: calcium oxide/**Zinc oxide**/sodium oxide/potassium oxide) | K118 (new) | recall |
| 104574 | Formula of aluminium oxide. (options: Al2O2/**Al2O3**/AlO/AlO6) | K118 (new) | recall |
| 104575 | Water is ___ in nature (options: Basic/Acidic/**Amphoteric**/Salty) | A118 (new) | application |

### Concept 119 — Reactivity Series of Metals (35)
New nodes: **K119** (recall — standard displacement/reactivity facts), **A119** (application — predicting/explaining displacement or reactivity comparisons).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85124 | Samples of four metals A, B, C, D added to FeSO4/CuSO4/ZnSO4/AgNO3 — tabulated results. (a) most reactive metal (b) observe B+CuSO4 (c) arrange A–D by increasing reactivity. (CBSE 2011) | A119 (new) | application |
| 85126 | What would you observe when zinc is added to a solution of iron(II) sulphate? Write the reaction. (CBSE 2010) | A119 (new) | application |
| 85132 | Metallic oxides of zinc, magnesium and copper heated with metals Zn/Mg/Cu — in which cases will displacement occur, given Mg,Zn,Cu reactivity order? | A119 (new) | application |
| 85135 | Which of the following will give displacement reactions? (a) NaCl+Cu (b) MgCl2+Al (c) FeSO4+Ag (d) AgNO3+Cu | A119 (new) | application |
| 85138 | Food cans are coated with tin and not with zinc because (a) Zinc costlier (b) Zinc higher mp (c) Zinc more reactive than tin (d) Zinc less reactive than tin. | A119 (new) | application |
| 85141 | Name two metals which can displace hydrogen from dilute acids and two metals which cannot. | K119 (new) | recall |
| 85152 | You are given Na, Mg, Cu; using only water as reactant, how will you identify them? | A119 (new) | application |
| 85160 | Which one of the following metals does not react with cold as well as hot water? (a) K (b) Ca (c) Mg (d) Fe | A119 (new) | application |
| 85176 | Which one of the following four metals would be displaced from the solution of its salts by the other three? (a) Mg (b) Cu (c) Zn (d) Fe | A119 (new) | application |
| 85191 | Which of the following can undergo a chemical reaction? (a) MgSO4+Zn (b) ZnSO4+Fe (c) CaSO4+Pb (d) CuSO4+Al | A119 (new) | application |
| 85213 | A solution of CuSO4 was kept in an iron pot. After a few days the pot had holes. Explain in terms of reactivity; write the equation. | A119 (new) | application |
| 85218 | Of three metals X, Y, Z: X reacts with cold water, Y with hot water, Z with steam only. Identify and arrange in increasing reactivity. | A119 (new) | application |
| 85226 | Out of zinc and iron, which evolves hydrogen more readily on reacting with dilute HCl? | A119 (new) | application |
| 85254 | What changes do you observe in the iron nails and colour of copper sulphate solution if the nails are dipped in CuSO4 solution for 15 minutes? | K119 (new) | recall |
| 85264 | A copper plate was dipped into a solution of AgNO3. After sometime a black layer deposited. State the reason; write the equation. | K119 (new) | recall |
| 85265 | Zinc in mercuric chloride solution acquires a silvery surface; in magnesium sulphate solution, no change. State the reason. | A119 (new) | application |
| 85270 | (a) What is the activity series? Arrange Zn, Mg, Al, Cu, Fe in decreasing reactivity. (b) Observe Zn in CuSO4 / Cu in FeSO4. (c) Name a metal combining with hydrogen gas; name the compound. | A119 (new) | application |
| 85273 | (a) Using a simple experiment, how can you prove that magnesium is above zinc in reactivity? (b) Why can't copper liberate hydrogen with dil. HCl? | A119 (new) | application |
| 85274 | The way metals like sodium, magnesium and iron react with air and water indicates their relative positions in the reactivity series. True? Justify with examples. (CBSE 2014) | A119 (new) | application |
| 85279 | (a) Which of the following metals would give hydrogen with dilute HCl? iron/copper/magnesium (b) Explain why some metal surfaces acquire a dull appearance on long exposure to air. | A119 (new) | application |
| 104526 | The colour of coating developed on a zinc rod on dipping it in aqueous copper sulphate solution will be | K119 (new) | recall |
| 104527 | When you place an iron nail in copper sulphate solution, the reddish brown coating formed on the nail is | K119 (new) | recall |
| 104528 | Fe2O3 + Al → Al2O3 + Fe. The reaction is an example of ___ | A119 (new) | application |
| 104529 | To show experimentally that zinc is more reactive than copper, the correct procedure is | A119 (new) | application |
| 104531 | Reddish brown deposits observed on iron nails kept in aqueous CuSO4 is that of | K119 (new) | recall |
| 104532 | The blue colour of copper sulphate solution can be changed to pale green by immersing which rod in it. | K119 (new) | recall |
| 104534 | An iron nail kept immersed in aluminium sulphate solution — after an hour it was observed that | A119 (new) | application |
| 104535 | 10ml ferrous sulphate solution; strips of Cu/Fe/Zn/Al introduced; black residue in two of them — the right pair is | A119 (new) | application |
| 104536 | Two beakers A,B contain iron sulphate solution. Cu in A, Zn in B; grey deposit forms on zinc but not copper. Concluded that | A119 (new) | application |
| 104538 | On adding zinc granules to freshly prepared ferrous sulphate solution, a student observes that | K119 (new) | recall |
| 104540 | Which of the following statement is correct? (Zn vs Cu reactivity) | K119 (new) | recall |
| 104541 | A student puts an iron nail in four salt solutions; reddish brown coating observed only in | K119 (new) | recall |
| 104542 | When an aluminium strip is kept immersed in freshly prepared ferrous sulphate solution, the change observed is | A119 (new) | application |
| 104544 | Which is the most reactive metal in the reactivity series? | K119 (new) | recall |
| 104553 | Which of the following is correct? (Mg>Al>Zn>Fe reactivity order) | K119 (new) | recall |

### Concept 120 — Ionic Compound Formation (10)
New nodes: **K120** (recall), **A120** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85127 | (i) Write electron-dot structures for sodium, magnesium and oxygen. (ii) Show formation of Na2O and MgO by electron transfer. (iii) What ions are present? | A120 (new) | application |
| 85165 | Which of the following are not ionic compounds? (i) CaCl2 (ii) HCl (iii) CCl4 (iv) NaCl | A120 (new) | application |
| 85186 | Electronic configurations X(2,8), Y(2,8,6), Z(2,8,1). Which is correct? (metal/non-metal identification) | A120 (new) | application |
| 85204 | Give the formulae of stable binary compounds formed by (a) Ca and N2 (b) Li and O2 (c) Ca and Cl2 (d) K and … | A120 (new) | application |
| 85235 | What is the name of the bond formed when a metal atom combines with the atom of a non-metal? (CBSE 2010) | K120 (new) | recall |
| 85246 | Element E (Z=16, config 2,8,6): will it lose six electrons or gain two electrons? | A120 (new) | application |
| 85251 | What is the common feature in the electronic configuration of metal atoms? | K120 (new) | recall |
| 85252 | What are ionic compounds? | K120 (new) | recall |
| 85257 | Give the electronic configuration of an element having atomic number 11. (CBSE 2010) | K120 (new) | recall |
| 104551 | Sodium chloride is an ___ compound. (options: **Ionic**/Covalent/coordinate/none) | K120 (new) | recall |

### Concept 121 — Properties of Ionic Compounds (7)
New nodes: **K121** (recall), **A121** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85128 | Why do ionic compounds have high melting points? (CBSE 2014) | A121 (new) | application |
| 85137 | An element reacts with oxygen to give a compound with high melting point, also water-soluble. The element is likely to be (a) Calcium (b) Carbon (c) Silicon (d) Iron | A121 (new) | application |
| 85155 | Element E combines with oxygen to form E2O, a good conductor of electricity. How many valence electrons in E? Formula of E with chlorine? | A121 (new) | application |
| 85166 | Which property is not generally exhibited by ionic compounds? (a) Solubility in water (b) Electrical conductivity in solid state (c) High mp/bp (d) Electrical conductivity in molten state. | K121 (new) | recall |
| 85185 | Reaction between X and Y (electron transfer) forms compound Z. Which property is NOT shown by Z? (a) high mp (b) low mp (c) conducts in molten state (d) occurs as solid | K121 (new) | recall |
| 85236 | How will you account for the high melting points of salts? | A121 (new) | application |
| 85280 | How will you demonstrate that ionic compounds do not conduct electricity in the solid state and can do so in solution? | A121 (new) | application |

### Concept 122 — Minerals, Ores, and Gangue (14)
New nodes: **K122** (recall), **A122** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85129 | Define the following terms: Minerals, Ores, Gangue. | K122 (new) | recall |
| 85130 | Name two metals which are formed in nature in free state. | K122 (new) | recall |
| 85153 | Element E among Cu, Zn, Al, Fe: ore rich in E2O3, E2O3 not attacked by water, forms ECl2 and ECl3. Name and justify. | A122 (new) | application |
| 85167 | Which of the following metals exist in their native state in nature? (i) Cu (ii) Au (iii) Zn (iv) Ag | K122 (new) | recall |
| 85222 | Name the metal which is most abundant in earth's crust. | K122 (new) | recall |
| 85225 | Name the process used for the enrichment of sulphide ore. | K122 (new) | recall |
| 85229 | Write the chemical formulae of the main ores of iron and aluminium. | K122 (new) | recall |
| 85232 | Name the chemical formula of zinc blende and galena. | K122 (new) | recall |
| 85237 | Name two metals which exist in the native or free state. | K122 (new) | recall |
| 85261 | All ores are minerals but all minerals are not ores. Justify. | A122 (new) | application |
| 85266 | Which method of concentration of ore is preferred: (i) high-density ore + low-density gangue bulk (ii) copper sulphide intermixed with clay; give example of amalgam. | A122 (new) | application |
| 104543 | Name the metal which is abundant in nature (options: **Aluminium**/Iron/Zinc/Carbon) | K122 (new) | recall |
| 104549 | The elements and compounds present in the earth's crust is called ___ (options: Ore/Gangue/anode mud/**minerals**) | K122 (new) | recall |
| 104550 | ___ is found in the free state (options: **Gold**/Sodium/Potassium/Calcium) | K122 (new) | recall |

### Concept 123 — Metallurgy (2)
New node: **K123** (recall).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85215 | Give the steps involved in the extraction of metals of low and medium reactivity from their respective sulphide ores. | K123 (new) | recall |
| 85217 | (i) Steps for extraction of copper from its ore — roasting of copper(I) sulphide, reduction of copper(I) oxide with copper(I) sulphide, electrolytic refining. (ii) Draw a labelled diagram of electrolytic refining of copper. | K123 (new) | recall |

### Concept 124 — Extraction of Metals (7)
New nodes: **K124** (recall), **A124** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85131 | Which chemical process is used for obtaining a metal from its oxide? | K124 (new) | recall |
| 85174 | Which of the following metals are obtained by electrolysis of their chlorides in molten state? (i) Na (ii) Ca (iii) Fe (iv) Cu | K124 (new) | recall |
| 85197 | Compound A and aluminium are used to join railway tracks. (a) Identify compound A (b) Name the reaction (c) Write its reaction with aluminium. | A124 (new) | application |
| 85200 | Aluminium powder heated with MnO2. (a) Is aluminium getting reduced? (b) Is MnO2 getting oxidised? | A124 (new) | application |
| 85203 | A metal that exists as a liquid at room temperature is obtained by heating its sulphide in the presence of air. Identify the metal and its ore; give the reaction. | A124 (new) | application |
| 85238 | What reaction takes place when manganese dioxide is heated with aluminium powder? | A124 (new) | application |
| 85287 | (a) Which method to reduce oxides of less/moderately/highly reactive metals — with examples. (b) Reaction between metal X and Fe2O3 is highly exothermic, used to join railway tracks. Identify X, name the reaction, write the equation. | A124 (new) | application |

### Concept 125 — Roasting and Calcination (6)
New nodes: **K125** (recall), **A125** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85195 | Why should metal sulphides and carbonates be converted to metal oxides in the process of extraction? | A125 (new) | application |
| 85205 | What happens when (a) ZnCO3 is heated in the absence of oxygen? (b) a mixture of Cu2O and Cu2S is heated? | A125 (new) | application |
| 85219 | Two ores X and Y: on heating, X gives CO2, Y gives SO2. What steps will you take to convert them into respective metals? | A125 (new) | application |
| 85223 | What is the difference between calcination and roasting? | K125 (new) | recall |
| 85267 | Name an ore of zinc other than zinc oxide. By which process can this ore be converted into zinc oxide? | A125 (new) | application |
| 85272 | What is the main ore of mercury? How is mercury obtained from this ore? | K125 (new) | recall |

### Concept 126 — Electrolytic Refining (9)
New nodes: **K126** (recall), **A126** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85142 | In the electrolytic refining of metal M, name anode, cathode and electrolyte. | K126 (new) | recall |
| 85168 | Metals are refined by different methods. Which of the following metals are refined by electrolytic refining? (i) Ag (ii) Cu (iii) Na (iv) Mg | K126 (new) | recall |
| 85179 | An electrolytic cell consists of: (i) positively charged cathode (ii) negatively charged anode (iii) positively charged anode (iv) negatively charged cathode | K126 (new) | recall |
| 85180 | During electrolytic refining of copper, impurity gets (a) deposited on cathode (b) deposited on anode (c) both (d) remains in solution. | K126 (new) | recall |
| 85192 | Which one of the following figures correctly describes the process of electrolytic refining? | K126 (new) | recall |
| 85194 | During extraction of metals, electrolytic refining is used to obtain pure metals. (a) anode/cathode material for copper refining (b) suitable electrolyte (c) where do we get pure copper? | K126 (new) | recall |
| 85221 | A student wired impure copper as cathode and pure copper as anode in CuSO4 electrolyte — nothing happened. He reversed the electrodes and succeeded. What was his mistake? How did he fix it? Write the equation. | A126 (new) | application |
| 85234 | Which acts as anode in the electro-refining of metals? | K126 (new) | recall |
| 85289 | With reference to electrorefining of impure copper: (a) diagram (b) electrolyte used (c) name cathode and anode (d) what happens at cathode and anode? | K126 (new) | recall |

### Concept 127 — Corrosion (12)
New nodes: **K127** (recall), **A127** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85133 | Which metals do not corrode easily? | K127 (new) | recall |
| 85147 | You must have seen tarnished copper vessels being cleaned with lemon or tamarind juice. Explain why these sour substances are effective. (CBSE 2014) | A127 (new) | application |
| 85148 | A man posing as a goldsmith dipped gold bangles in a solution; they sparkled but weight reduced drastically. Predict the nature of the solution. | A127 (new) | application |
| 85149 | Give reason as to why copper is used to make hot water tanks and not steel (an alloy of iron). | A127 (new) | application |
| 85151 | A student observed a black coating on silver coins and a green coating on copper coins. Which chemical phenomenon is responsible? Name the black and green coatings. | K127 (new) | recall |
| 85169 | Silver articles become black on prolonged exposure to air. This is due to the formation of (a) AgCN (b) Ag2O (c) Ag2S (d) Ag2S and AgCN | K127 (new) | recall |
| 85172 | If copper is kept open in air, it loses its shining brown surface and gains a green coating. Due to formation of (a) CuSO4 (b) CuCO3Cu(OH)2 (c) Cu(NO3)2 (d) CuO. | K127 (new) | recall |
| 85243 | Why do metals generally appear to be dull? | A127 (new) | application |
| 85249 | Why do silver articles become black after some time? | K127 (new) | recall |
| 85262 | (a) An iron knife kept in blue copper sulphate solution turns the blue solution light green. Explain. (b) A bronze medal lost lustre due to a greenish layer — name the metals present and the reason for the layer. | A127 (new) | application |
| 85277 | Name the chemical compounds formed on the surface of silver, copper and iron metals when exposed for sometime to atmosphere. | K127 (new) | recall |
| 85282 | (a) Define corrosion; what is corrosion of iron called? (b) Colour of coating on silver and copper on exposure to air. (c) Two damages caused by corrosion and how they can be prevented. | K127 (new) | recall |

### Concept 128 — Rusting of Iron (2)
New nodes: **K128** (recall), **A128** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85224 | What is the chemical formula of rust? | K128 (new) | recall |
| 85239 | Can rusting of iron nail occur in distilled water? | A128 (new) | application |

### Concept 129 — Prevention of Corrosion (4)
New node: **K129** (recall).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85136 | Which of the following methods is suitable for preventing an iron frying pan from rusting? (a) applying grease (b) applying paint (c) applying a coating of zinc (d) all the above. | K129 (new) | recall |
| 85144 | State two ways to prevent the rusting of iron. | K129 (new) | recall |
| 85170 | Galvanisation is a method of protecting iron from rusting by coating with a thin layer of (a) Chromium (b) Copper (c) Zinc (d) Tin | K129 (new) | recall |
| 85250 | Name a metal other than aluminium that is covered with a layer of oxide film. | K129 (new) | recall |

### Concept 130 — Alloys (12)
New nodes: **K130** (recall), **A130** (application).

| id | Question text | Node | Item type |
|---|---|---|---|
| 85134 | What are alloys? (CBSE 2011) | K130 (new) | recall |
| 85171 | Stainless steel is a very useful material. In stainless steel, iron is mixed with (a) Ni and Cr (b) Cu and Cr (c) Ni and Cu (d) Cu and Au. | K130 (new) | recall |
| 85178 | An alloy is: (a) an element (b) a compound (c) a homogeneous mixture (d) a heterogeneous mixture | K130 (new) | recall |
| 85182 | Alloys are homogeneous mixtures of a metal with a metal or non-metal. Which of the following alloys contains a non-metal as one of its constituents? (a) Brass (b) Gun metal (c) Amalgam (d) Steel. | A130 (new) | application |
| 85184 | Which among the following alloys contain mercury as one of its constituents? (a) Stainless steel (b) German silver (c) Solder (d) Zinc amalgam. | K130 (new) | recall |
| 85201 | What are the constituents of solder alloy? Which property of solder makes it suitable for welding electrical wires? | K130 (new) | recall |
| 85220 | Goldsmith story — 24-carat gold too soft to make ornaments; 22-carat gold works. What's the difference; what was wrong with 24-carat gold; how did the trained goldsmith help? | A130 (new) | application |
| 85227 | How do alloys brass and bronze differ in composition? (CBSE 2010) | K130 (new) | recall |
| 85228 | Does german silver contain silver in it? | K130 (new) | recall |
| 85241 | Which metal is used in amalgams? | K130 (new) | recall |
| 85247 | Alloys are used in electrically heating devices rather than pure metals. Give one reason. (CBSE All India 2009) | A130 (new) | application |
| 85260 | Name an alloy of Aluminium used in construction of aircraft, Lead in electric welding joints, Copper used in household vessels. | K130 (new) | recall |

---

## 3. MCQ distractor → misconception review (all 35 remaining MCQs)

Every wrong option's actual text was read from `answer_master` and checked against the live 46-row
`pal_misconception_library` catalog for chapter 1014. Confident reuse is marked with the misconception's
numeric id + tag; everything else is `generic error` or `uncertain`, exactly as instructed — **no new
misconceptions were invented in this pass.**

| Q id | Concept | Correct answer | Wrong option → verdict |
|---|---|---|---|
| 104526 | 119 | Brown | Blue → generic error; White → generic error; Green → generic error |
| 104527 | 119 | hard and flaky | soft and dull → generic error; smooth and shining → generic error; rough and granular → generic error |
| 104528 | 119 | displacement reaction | combination reaction → generic error; decomposition reaction → generic error; double displacement reaction → generic error |
| 104529 | 119 | prepare CuSO4 & dip zinc strip | prepare ZnSO4 & dip copper strip → **uncertain** (plausible: reverses which metal must be added to which salt, but no library entry names this specific error) — treated as tagged; heat zinc and copper strips → generic error; add dilute nitric acid on both strips → generic error |
| 104531 | 119 | Cu | Cu2O → generic error; CuO → generic error; CuS → generic error |
| 104532 | 119 | Iron | Zinc → generic error; Aluminium → generic error; Silver → **uncertain** (plausibly reflects not knowing Ag is less reactive than Cu, but no library entry names this) |
| 104534 | 119 | solution remains colourless, no deposition | colourless solution changes to light green → **uncertain** (plausibly assumes Fe can displace the more-reactive Al, no library match); solution becomes warm → generic error; grey metal deposited on nail → **uncertain** (same reasoning as above) |
| 104535 | 119 | zinc and aluminium | copper and zinc → generic error; aluminium and copper → generic error; iron and aluminium → generic error |
| 104536 | 119 | zinc most active, then iron, then copper | zinc most active, then copper, then iron → generic error; iron most active, then zinc and copper → **uncertain** (misreads which metal received the deposit, no library match); iron most active, then copper then zinc → **uncertain** (same) |
| 104538 | 119 | a dull brown coating | a black coating → generic error; a greyish coating → generic error; no coating → **uncertain** (plausibly doesn't recognise Zn is more reactive than Fe, no library match) |
| 104539 | 114 | D (silvery white) | A → generic error; B → generic error; C → generic error |
| 104540 | 119 | zinc is more reactive than copper | Copper is more reactive than zinc → generic error; copper and zinc equally reactive → generic error; zinc is less reactive than copper → generic error |
| 104541 | 119 | copper sulphate | zinc sulphate → generic error; iron sulphate → generic error; aluminium sulphate → generic error |
| 104542 | 119 | lower end of test tube becomes slightly warm | green solution slowly turns brown → generic error; colourless gas with smell of burning sulphur → generic error; light green solution changes to blue → generic error |
| 104543 | 122 | Aluminium | Iron → generic error; Zinc → generic error; Carbon → generic error |
| 104544 | 119 | Potassium | Sodium → generic error; Iron → generic error; Calcium → generic error |
| 104545 | 114 | Sodium | Calcium → generic error; Iron → generic error; Aluminium → generic error |
| 104546 | 114 | Mercury | Magnesium → generic error; **lead → misconception 3671** ("confuses liquid metal with low melting metal") — confident reuse; **tin → misconception 3671** — confident reuse |
| 104547 | 115 | Bromine | **Iodine → misconception 140** ("all non-metals are soft solids or gases") — confident reuse; **Chlorine → misconception 140** — confident reuse; **Sulphur → misconception 140** — confident reuse (sulphur is the literal example named in 140's description) |
| 104548 | 118 | Zinc oxide | **calcium oxide → misconception 148** ("students think metal oxide reacts only with acid and all metal oxides are basic") — confident reuse; **sodium oxide → misconception 148** — confident reuse; **potassium oxide → misconception 148** — confident reuse |
| 104549 | 122 | minerals | Ore → **uncertain** (plausibly related to #161 "all minerals are ores", but #161 is the reverse-direction claim, not a precise match); Gangue → generic error; anode mud → generic error |
| 104550 | 122 | Gold | Sodium → generic error; Potassium → generic error; Calcium → generic error |
| 104551 | 120 | Ionic | Covalent → generic error; coordinate → generic error; none → generic error |
| 104552 | 117 | Manganese | **Iron → misconception 150** ("students believe any metal + acid produces H2") — confident reuse; **Sodium → misconception 150** — confident reuse; **Calcium → misconception 150** — confident reuse |
| 104553 | 119 | Mg>Al>Zn>Fe | Mg>Al>Fe>Zn → generic error; Al>Mg>Zn>Fe → generic error; Mg>Zn>Al>Fe → generic error |
| 104554 | 117 | Hot | Cold → generic error; Steam → generic error; none → generic error |
| 104555 | 115 | iodine, graphite | iron, sulphur → generic error; sulphur, graphite → generic error; iodine, phosphorous → generic error |
| 104573 | 117 | black oxide | white oxide → generic error; blue oxide → generic error; green oxide → **uncertain** (plausibly confuses the black CuO formed on heating with copper's green corrosion patina, but no library entry names this) |
| 104574 | 118 | Al2O3 | Al2O2 → generic error; AlO → generic error; AlO6 → generic error |
| 104575 | 118 | Amphoteric | Basic → generic error; Acidic → generic error; Salty → generic error |
| 104530 | *concept uncertain* | Aluminium sulphate | *(no misconception review — see Section 4; not a reactivity-series fact, just salt-colour trivia)* |
| 104533 | *concept uncertain* | Zn granules, Cu turnings | *(no misconception review — see Section 4)* |
| 104537 | *concept uncertain* | Blue | *(no misconception review — see Section 4)* |
| 104561 | *concept uncertain* | PVC | *(no misconception review — see Section 4)* |
| 104569 | *concept uncertain* | Produces acidic oxides | *(no misconception review — see Section 4)* |

**Misconception reuse count: 4 distinct misconceptions (140, 148, 150, 3671), applied across 11
option-level mappings.** No new misconceptions proposed, consistent with instructions.

**Flag for reviewer:** misconception **150** is filed in the library under `concept_ref_id=119`
(Reactivity Series), but is applied here to Q104552, which this proposal tags to concept **117**
(Chemical Reactivity of Metals — reaction with acids). Both concepts concern metal reactivity and the
misconception itself ("any metal + any acid → H2") is squarely about 117's territory (acid reactions), so
the reuse is semantically sound, but the concept_ref_id on the library row and the question's tagged
concept differ. Flagging rather than silently normalizing — reviewer's call whether to retag the question
to 119 or leave as-is.

---

## 4. Uncertain / needs human review (23 items)

### MCQ (5)
| id | Question | Reason left uncertain |
|---|---|---|
| 104530 | A colourless solution kept in a test tube could be (Ferrous sulphate/Copper sulphate/**Aluminium sulphate**/potassium permanganate) | Tests recall of common salt-solution colours, not reactivity-series content itself (no comparison/prediction of displacement) — doesn't cleanly match any of the 17 concept descriptions. |
| 104533 | Zinc and copper metal used in the laboratory is available respectively in the form of (**Zn granules, Cu turnings**, etc.) | Lab-supply-form trivia, not a chemistry concept covered by any of the 17. |
| 104537 | Some crystals of copper sulphate were dissolved in water. The colour of the solution would be | Same as 104530 — salt-colour recall, not reactivity-series content. |
| 104561 | Wires which used in homes that are coated with…… (PVC) | Electrical-insulation materials fact, unrelated to any of the 17 metallurgy/reactivity concepts. |
| 104569 | When nonmetals dissolves in water. (Produces acidic oxides) | Non-metal chemical reactivity — no concept among the 17 covers this (117 is scoped to metals only; 115 is non-metal *physical* properties only). See taxonomy gap note below. |

### Narrative (18)
| id | Question | Reason left uncertain |
|---|---|---|
| 85143 | Pratyush heated sulphur powder, collected the gas — action on dry/moist litmus, balanced equation. | Non-metal (sulphur) reacting with oxygen — taxonomy gap (no non-metal-reactivity concept). |
| 85145 | What types of oxides are formed when non-metals combine with oxygen? | Same taxonomy gap. |
| 85146 | Composite "give reasons" question: (a) Pt/Au/Ag jewellery (b) Na/K/Li under oil (c) Al reactive but used for utensils (d) carbonate/sulphide ores converted to oxides. | Spans at least 4 distinct concepts (127, 117, 129, 125) with no single dominant one — genuinely composite. |
| 85150 | Large table differentiating metals/non-metals by chemical properties (oxide nature, electrochemical behaviour, acid reaction, compound type, oxidising/reducing nature). | Spans essentially all chemical-property concepts plus the non-metal-reactivity gap at once; too broad for one concept_id. |
| 85189 | Electrical wires have a coating of insulating material — which one? (PVC) | Same as MCQ 104561 — not covered by any of the 17 concepts. |
| 85206 | Non-metal A, important in food, forms two oxides B (toxic) and C (causes global warming) — identify A, B, C and periodic group. | Non-metal (carbon) oxide chemistry — taxonomy gap. |
| 85212 | An element forms oxide A2O3, acidic in nature. Is A a metal or non-metal? | Tests acidic-vs-basic oxide nature generally — taxonomy gap (this is the "flip side" of concept 117 that isn't captured by any of the 17). |
| 85214 | Non-metal A (largest constituent of air) + H2 → B; + O2 → C; C + H2O + air → acid D. Identify A, B, C, D and periodic group. | Non-metal (nitrogen) reactivity chain — taxonomy gap. |
| 85216 | Composite "explain" question: (a) Al reactivity decreases in conc. HNO3 (b) carbon can't reduce Na/Mg oxides (c) NaCl conductivity by state (d) galvanising (e) Na/K/Ca/Mg never found free. | Five sub-parts spanning passivation, extraction limits, ionic conductivity, galvanising, and native occurrence — no single dominant concept. |
| 85231 | Write the names of two neutral oxides. (CBSE 2010) | Oxide-nature classification (acidic/basic/neutral) — taxonomy gap, same family as 85143/85145/85206/85212/85214. |
| 85271 | Are all pure liquids bad conductors of electricity? Name one that's a good conductor but doesn't electrolyse; why doesn't pure water electrolyse? | General electrolyte/conductivity theory; closest concept (126, Electrolytic Refining) doesn't actually cover this content (that concept is specifically about refining impure metal, not general electrolyte behaviour). |
| 85275 | Metallic compound A + dilute HCl → effervescence + gas B; B extinguishes a candle and turns limewater milky. Identify A and B. | Generic carbonate + acid → CO2 identification; not squarely matched by Roasting/Calcination (125, which is specifically about heating in air, not acid treatment) or any other of the 17. |
| 85276 | Identify the oxides that turn blue litmus red among CO2, Na2O, CaO, SO2, NO2; what is their nature? | Acidic-oxide-of-non-metal identification — taxonomy gap. |
| 85281 | With a suitable activity, show that sulphur burns in air to form a compound which is acidic in nature. | Same taxonomy gap as 85143. |
| 85283 | Composite "give reasons": (a) Zn displaces Cu from CuSO4 (b) Ag blackens over time (c) metal sulphide converted to oxide to extract the metal. | Three sub-parts spanning displacement (119), tarnishing (127), and roasting (125) — no single concept. |
| 85284 | Composite: (a) distinguish roasting/calcination, which for sulphide ores and why (b) thermite equation for railway tracks (c) anode/cathode/electrolyte for Cu electrorefining. | Three sub-parts spanning roasting-calcination (125), thermite/extraction (124), and electrolytic refining (126) — no single concept. |
| 85285 | Composite: (a) examples — liquid metal, kerosene-stored metal, malleable+ductile metal, best heat conductor (b) electrolytic refining of copper with diagram, anode/cathode/electrolyte. | Two unrelated sub-parts spanning physical-property exceptions (114) and electrolytic refining (126). |
| 85286 | Composite: (a) equations for Cu extraction, reducing agent (b) reducing agent/process for top-of-series metals (c) green coating substance on Cu. | Three sub-parts spanning extraction (124/125), extraction method (124), and corrosion (127) — no single concept. |

**Recurring pattern worth flagging to the content team:** 8 of the 23 uncertain items (104569, 85143,
85145, 85206, 85212, 85214, 85231, 85276, 85281 — actually 9) exist purely because the 17-concept
taxonomy for this chapter has no concept covering *non-metal chemical reactivity / acidic oxide
formation*, even though it's clearly taught content in this chapter (NCERT Class 10 Ch.3 covers it
explicitly). If a concept like "Chemical Reactivity of Non-metals" or "Acidic vs Basic Oxides" were added
to `lms_concept` for chapter 1014, most of these 9 items could be confidently tagged. The remaining 9
uncertain items are genuinely composite multi-part questions that don't reduce to one concept, and the
final ~5 (104533, 104561, 85189, 104530/104537, 85271) are either lab-procedure/materials trivia or
content that doesn't belong to this chapter's taxonomy at all.

---

## 5. New K/A/S nodes to create (only nodes actually populated by ≥1 question)

| Placeholder | concept_id | node_type | Label | Questions using it |
|---|---|---|---|---|
| S114 | 114 | S | Skill/transfer: designing and evaluating an experimental protocol to differentiate metals from non-metals | 1 |
| A117 | 117 | A | Application: explaining/predicting metal reactions with oxygen, water, and acids in specific scenarios | 8 |
| K118 | 118 | K | Recall: definitions and canonical examples of amphoteric oxides | 6 |
| A118 | 118 | A | Application/transfer: recognising amphoteric behaviour beyond canonical oxide examples (water, metal + base reactions) | 5 |
| K119 | 119 | K | Recall: reactivity order and standard displacement-reaction observations from lab activities | 9 |
| A119 | 119 | A | Application: predicting/explaining displacement outcomes and reactivity comparisons in specific or novel scenarios | 23 |
| K120 | 120 | K | Recall: definitions of ionic bond formation, cations/anions, electron transfer | 5 |
| A120 | 120 | A | Application: constructing electron-dot structures / predicting ionic formulae / identifying metal vs non-metal from electron configuration | 5 |
| K121 | 121 | K | Recall: properties of ionic compounds (mp/bp, solubility, conductivity by state) | 2 |
| A121 | 121 | A | Application: explaining why ionic compounds show these properties in a given scenario | 5 |
| K122 | 122 | K | Recall: definitions of mineral/ore/gangue; occurrence and abundance facts | 11 |
| A122 | 122 | A | Application: distinguishing ore vs mineral / identifying an element from ore-composition clues | 3 |
| K123 | 123 | K | Recall: overall steps of the metallurgical process for a class of metals | 2 |
| K124 | 124 | K | Recall: which extraction method (carbon reduction / self-reduction / electrolysis) applies to which reactivity tier | 2 |
| A124 | 124 | A | Application: identifying/justifying the correct extraction or reduction method in a specific scenario (incl. thermite) | 5 |
| K125 | 125 | K | Recall: definitions/differences of roasting and calcination | 2 |
| A125 | 125 | A | Application: choosing/explaining the roasting-vs-calcination step, or ore-to-metal conversion, for a specific ore | 4 |
| K126 | 126 | K | Recall: anode/cathode/electrolyte identification in electrolytic refining | 8 |
| A126 | 126 | A | Application: diagnosing/explaining an electrolytic refining setup or error | 1 |
| K127 | 127 | K | Recall: definition of corrosion and standard corrosion-product facts (tarnish colours etc.) | 7 |
| A127 | 127 | A | Application: explaining/predicting a specific corrosion scenario | 5 |
| K128 | 128 | K | Recall: chemical composition/formula of rust | 1 |
| A128 | 128 | A | Application: predicting whether rusting occurs under given conditions | 1 |
| K129 | 129 | K | Recall: standard methods to prevent corrosion/rusting | 4 |
| K130 | 130 | K | Recall: definition of alloys and standard alloy compositions | 9 |
| A130 | 130 | A | Application: reasoning about why an alloy (vs a pure metal) is used for a given purpose | 3 |

No new nodes are proposed for concepts 115 or 116 — all questions tagged to those concepts reuse the
existing K115/A115/K116 nodes (ids 93, 94, 95).

---

## 6. Validation

All checks below were run live against the production DB (read-only) before finalizing this document.

1. **Every concept_id referenced is one of the real 17 (114–130).** ✅ Confirmed — cross-checked every
   concept_id used above against `SELECT id,name FROM lms_concept WHERE chapter_id=1014` (17 rows,
   ids 114–130 inclusive, verified above in Section "Background"). No concept_id outside that range was
   used anywhere in this proposal.

2. **Every node placeholder maps to exactly one concept.** ✅ Each placeholder key (`K118`, `A119`, etc.)
   is scoped to a single concept_id as shown in the Section 5 table; no placeholder is reused across two
   different concepts.

3. **Every existing node id reused is verified to actually exist and belong to the stated concept.**
   ✅ Re-queried live: `SELECT id,concept_id,node_type,label FROM pal_concept_nodes WHERE concept_id
   BETWEEN 114 AND 130` returns exactly 6 rows — id 91 (concept 114, K), id 92 (concept 114, A), id 93
   (concept 115, K), id 94 (concept 115, A), id 95 (concept 116, K), id 96 (concept 117, K). Every
   existing-node reuse in this document (K114=91, A114=92, K115=93, A115=94, K116=95, K117=96) matches
   this table exactly.

4. **Every misconception_id reused is verified to exist in `pal_misconception_library` for this chapter.**
   ✅ Re-queried live: ids 140, 148, 150, 3671 all exist in `pal_misconception_library` with
   `chapter_ref_id=1014` — 140 (concept_ref_id 115, "all non-metals are soft solids or gases"), 148
   (concept_ref_id 118, "metal oxide reacts only with acid / all metal oxides basic"), 150 (concept_ref_id
   119, "any metal+acid→H2"), 3671 (concept_ref_id 114, "confuses liquid metal with low-melting metal").
   No misconception_id was invented; the four reused are the only ones referenced in this proposal (see
   flag in Section 3 re: 150's concept_ref_id vs. Q104552's tagged concept).

5. **No question is tagged twice.** ✅ Verified: the 205 remaining question ids were partitioned into
   exactly two disjoint sets — 182 confidently tagged (Section 2) and 23 uncertain (Section 4) — and every
   id from 85120–85289 (170 consecutive narrative ids) and the 35 remaining MCQ ids (104526–104555,
   104561, 104569, 104573–104575) appears in exactly one of those two sets, cross-checked by walking the
   full consecutive narrative id range and the explicit MCQ id list one by one.

6. **Total question coverage if applied.** 15 (already tagged) + 182 (this proposal, confident) =
   **197 / 220 questions (89.5%)** of chapter 1014 would carry a concept/node/item_type tag. The remaining
   23 questions (10.5%) are explicitly left untagged pending either human judgment call (composite
   multi-part questions) or a taxonomy extension (non-metal-reactivity concept gap, see Section 4).

**No database writes were performed.** This document is the complete deliverable for Phase 0 review.
