# Classroom Resource Grounding & Intelligence Data
## Chapter 41675: Global water resources: Distribution and historical impact

> **Source Page URL**: [https://lms-k12.vercel.app/course-master/48385-4258/chapters?view=content&resourceType=classroom&chapterId=41675&expandedChapterId=41675](https://lms-k12.vercel.app/course-master/48385-4258/chapters?view=content&resourceType=classroom&chapterId=41675&expandedChapterId=41675)  
> **Tenant ID (`sub_institute_id`)**: `341`  
> **Target System Role**: Canonical input artifact for Classroom Presentation generation (`interactive_slides` & `study_deck` pipeline).

---

## 1. System Metadata & Source Inventory

| Parameter | Value | Database Provenance |
|---|---|---|
| **Chapter ID** | `41675` | `chapter_master.id` |
| **Chapter Name** | Global water resources: Distribution and historical impact | `chapter_master.chapter_name` |
| **Subject** | EVS (ID: `48385`) | `subject.subject_name` (`chapter_master.subject_id`) |
| **Standard / Grade** | Class 4 (Standard ID: `4258`, Grade ID: `1087`) | `standard.name`, `chapter_master.grade_id` |
| **Tenant (Sub-Institute ID)** | `341` | `chapter_master.sub_institute_id` |
| **Academic Year (`syear`)** | `2026` | `chapter_master.syear` |
| **Board** | CAMBRIDGE | `document_extractions.board` |
| **Extraction Confidence** | `0.946` | `chapter_master.extraction_confidence` |
| **Document Extraction ID** | `858` | `document_extractions.id` (linked via `chapter_master.extraction_id`) |
| **Document Type & Title** | `Chapter` — Global water resources: Distribution and historical impact | `document_extractions.document_type`, `document_tittle` |
| **Textbook Source File** | `C:\Users\MILAN\Downloads\g4_water_split\g4water_ch01.pdf` | `document_extractions.pdf_url` |
| **Textbook Page Count** | `7` pages | `document_extractions.page_count` |
| **Textbook Character Count** | 8656 characters | `document_extractions.md_content` |
| **Content SHA-256** | `9733e70e815a67ca520cebb8f64f8c5d4f8f6f63f52295c87cdb91e30ffb7129` | `document_extractions.content_sha256` |
| **Semantic Intelligence ID** | `1082` | `semantic_intelligence.id` |
| **Semantic Intelligence Quality**| `good` | `semantic_intelligence.qulity_flag` |
| **LLM Extraction Model** | `claude-sonnet-5-5 (supplied in-session)` | `semantic_intelligence.llm_model` |
| **Total Verified Concepts** | `12` | `semantic_intelligence.total_concepts` / `lms_concept` count |
| **Total Topics** | 4 | `topic_master` count |

### Data Availability Audit
- [x] **`chapter_master`**: Verified row exists, strictly scoped to `sub_institute_id = 341`.
- [x] **`topic_master`**: 4 topics verified, scoped to `sub_institute_id = 341` and `chapter_id = 41675`.
- [x] **`lms_concept`**: 12 concepts verified, scoped to `sub_institute_id = 341` and `chapter_id = 41675`.
- [x] **`document_extractions`**: Row `id = 858` verified, scoped to `sub_institute_id = 341`, containing full markdown text and asset manifest.
- [x] **`semantic_intelligence`**: Row `id = 1082` verified, scoped to `sub_institute_id = 341`, containing 316 KB structured JSON intelligence.
- [!] **`lms_learning_outcomes` table**: 0 rows in relational table; learning outcomes and objectives are fully captured within `semantic_intelligence.learning_objective`, `learning_objectives`, and `learning_outcomes`.
- [!] **`concept_prerequisite` table**: 0 direct relational edges; concept prerequisites and relational networks are fully structured within `semantic_intelligence.prerequisites` and `concept_relationships`.
- [x] **`content_master`**: 10 auxiliary resources verified under `sub_institute_id = 341` (posters, student task cards, teacher training rubrics).
- [x] **`lms_prayogshala_activity`**: 1 active hands-on activity verified (`id = 755`).

---

## 2. Chapter Overview & Master Learning Objectives

### Chapter Summary
'Global water resources: Distribution and historical impact' teaches the following topics in order:
1. Water on Earth: A global view - Learners find out how Earth's water is shared between salt water and fresh water, and where the small share of usable fresh water is found. They see why water is spread unevenly across the planet.
2. How water shaped civilisations - Learners explore how the great ancient civilisations grew beside rivers for water, farming, trade and travel. They also see how floods and poor water management caused problems and decline.
3. Water challenges today - Learners look at today's water problems: uneven sharing, pollution, overuse, melting glaciers and people without clean water or sanitation. They meet the United Nations goal for clean water and sanitation.
4. Learning from the past, acting for the future - Learners connect lessons from ancient water management to action today, such as rainwater harvesting and smart irrigation. They learn how individuals and larger policies can protect water for the future.

### Master Learning Objectives (Semantic Intelligence)
1. Name the two kinds of water on Earth, salt water and fresh water.
2. Compare the share of salt water with the share of fresh water on Earth.
3. Explain why sea water cannot be used directly for drinking.
4. Name three kinds of places where fresh water is found.
5. Explain why most fresh water is hard for people to use.
6. State that less than 1% of Earth's water is available for people to use.
7. Explain what it means to say that water is unevenly shared across the planet.
8. Compare a region with abundant water with a region that faces chronic shortages.
9. Describe one way the amount of water in a place affects how people live.
10. Name the oceans, lakes and rivers that the book lists as major water bodies.
11. Classify named water bodies as oceans, lakes, rivers or places of frozen water.
12. Locate the major water bodies on a world map.
13. Name three things that rivers offered to early settlements.
14. Explain why early settlements were often built near rivers.
15. Describe how the yearly floods of the Nile helped farming in Egypt.
16. Match each river civilisation with the river it grew along.
17. Describe how people in Mesopotamia, the Indus Valley and China managed water.
18. Explain why different civilisations managed water in different ways.
19. Name three threats that rivers brought to ancient people.
20. Explain how over-irrigation harmed farming in Mesopotamia.
21. Relate the failure to maintain water systems to the decline of civilisations.
22. Name countries that are rich in fresh water and regions that struggle with water scarcity.
23. Compare one water-rich and one water-poor country using a world map and simple data.
24. Explain how rainfall, water sources and population affect water availability.
25. Name three things that make water shortages worse.
26. Explain why wells run dry when groundwater is pumped out too fast.
27. Describe how melting glaciers affect the stores of fresh water.
28. Explain why contaminated rivers and lakes are unsafe to drink from.
29. State what can happen when people lack clean water and safe sanitation.
30. Describe in simple words what Sustainable Development Goal 6 aims to do.
31. Explain what happened to societies that protected their water and to those that ignored water problems.
32. Name two ancient water practices that we can learn from today.
33. Justify why water should be treated as a shared, limited resource.
34. Name three ways to use water wisely, reduce waste or keep water sources clean.
35. Explain why policies and technologies are also needed to protect water.
36. Plan one action that helps to secure water for future generations.

---

## 3. Extracted Visual Assets & Image Manifest

The following visual figures were extracted from the source textbook (`document_extractions.asset_manifest`) and cross-referenced with the curated presentation image manifest (`evs-std4-water.json`):

### Figure 1: g4-water-ch1-water-stress-map-2040
- **Caption**: *"World map shaded from low to extremely high water stress by country for 2040, with North Africa, the Middle East and Central Asia darkest."*
- **Book Page**: 5 (PDF page index: 4)
- **File Name**: `3c5a9501e02afc9d2cabce57e8a2cdf2f2c4814cfe47c51b5031ce31af279951.jpg`
- **Dimensions**: 1539 × 852 px (`368610` bytes)
- **Asset URL**: `http://127.0.0.1:8001/api/assets/g4water_ch01/pass_auto/g4water_ch01/auto/images/3c5a9501e02afc9d2cabce57e8a2cdf2f2c4814cfe47c51b5031ce31af279951.jpg`
- **Alt Text / Description**: World map shaded from low to extremely high water stress by country for 2040, with North Africa, the Middle East and Central Asia darkest.
- **OCR Text Extracted from Figure**:
  ```text
  1250
  2500 mi
  2000
  4000 km
  2040 WATER STRESS
  PROJECTIONS
  BY COUNTRY
  Low
  Low to medium
  Medium to high
  High
  Extremely high
  ```

### Figure 2: g4-water-ch1-earth-water-distribution-chart
- **Caption**: *"Yangtze, China - the yellow river"*
- **Book Page**: 2 (PDF page index: 1)
- **File Name**: `79074ef9c9796e5dffc16836b2ad846634de18930062a77534f7991a0313cd8b.jpg`
- **Dimensions**: 735 × 1203 px (`289444` bytes)
- **Asset URL**: `http://127.0.0.1:8001/api/assets/g4water_ch01/pass_auto/g4water_ch01/auto/images/79074ef9c9796e5dffc16836b2ad846634de18930062a77534f7991a0313cd8b.jpg`
- **Alt Text / Description**: Stacked pie charts of Earth's water: 96.5 percent salt water, then fresh water in ice and ground water, then lakes, soil and rivers, above a photo of a river gorge.
- **OCR Text Extracted from Figure**:
  ```text
  Water distribution on Earth
  Oceans & Seas
  (salt water)
  %96.5
  Fresh Water
  Ice caps & Glaciers
  %69
  Surface water and others
  → Ground Water
  %30
  Soil
  Lakes
  %38
  %52
  Rivers
  Water (Vapour) in atmosphere
  Living Organisms
  ```

### Figure 3: g4-water-ch1-early-civilisations-world-map
- **Caption**: *"Ancient river valley civilisations"*
- **Book Page**: 3 (PDF page index: 2)
- **File Name**: `eb86c856d7cc262b2093fde7b02b19c71c30742cefe96589de99efc24c623f2a.jpg`
- **Dimensions**: 1545 × 768 px (`414879` bytes)
- **Asset URL**: `http://127.0.0.1:8001/api/assets/g4water_ch01/pass_auto/g4water_ch01/auto/images/eb86c856d7cc262b2093fde7b02b19c71c30742cefe96589de99efc24c623f2a.jpg`
- **Alt Text / Description**: World map marking where early farming and the first civilisations began, including Sumer, Egypt, the Indus Valley, China, the Olmecs and Chavin, with dates in years BCE.
- **OCR Text Extracted from Figure**:
  ```text
  EUROPE
  ORTH
  MESOPOTAMIA
  PACIFIC
  SUMER
  OCEAN
  CHINA
  ATLANTIC
  SHANG
  NDUS VALLEY
  OCEAN
  NILE VALLEY
  INDUS VALLEY
  East Chin:
  EGYPT
  MESOAMERICA
  PACIFIC
  OCEAN
  OLMECS
  AFRICA
  Mesopotamia early agriculture ~8000 BCE
  Sumer ~3500 BCE
  Nile valley early agriculture ~5000 BCE
  SOUTH
  Egypt ~3100 BCE
  ANDES
  AMERICA
  Mesoamerica early agriculture ~5000 BCE
  Olmec civilization ~1200 BCE
  AUSTRALIA
  China early agriculture ~4000 BCE
  Shang dynasty ~1800 BCE
  INDIAN
  Andes early agriculture ~4000 BCE
  OCEAN
  Chavin culture ~900 BCE
  Indus valley early agriculture ~3000 BCE
  Indus valley civilization ~2600 BCE
  ASIA
  CHAVIN
  J
  ```

### Curated Presentation Visuals Registry (`evs-std4-water.json`)
| Image File | Page | Caption | Content Description | Keywords |
|---|---|---|---|---|
| `page_09_image_02.png` | 9 | Water distribution on Earth | A layered diagram of where Earth's water is: oceans and seas (salt water) at the top, a thin stream of fresh water below it, then ice caps and glaciers, ground water, surface water, lakes, rivers, soil moisture, water vapour in the atmosphere and living organisms. The percentages printed on the diagram are its own and differ slightly from the chapter text (97% salt, 3% fresh). | `water distribution, salt water, fresh water, ice caps, glaciers, ground water, lakes, rivers, oceans, atmosphere, living organisms` |
| `page_09_image_04.png` | 9 | Yangtze, China | A wide green river winding through steep forested gorge walls, with small boats on the water: the Yangtze river in China. | `yangtze river, river gorge, china, large river, boats` |
| `page_10_image_03.png` | 10 | Ancient river valley civilisations | A coloured world map marking where early civilisations and early farming began along river valleys, with a colour key listing the regions. | `ancient civilisations, river valley, world map, early farming, nile, tigris, indus, yellow river` |
| `page_12_image_02.png` | 12 | Water scarcity prediction | A world map shaded by projected water stress in 2040 by country, from low to extremely high, with a legend. The darkest areas are North Africa and the Middle East. | `water scarcity, water stress, world map, 2040, north africa, middle east, drought` |
| `page_13_image_01.png` | 13 | Grey water recycling | A flow diagram: water from the shower, laundry, hand washing and dish washing goes into a grey water recycler, and the recycled water goes to flushing toilets and watering the garden. | `grey water, recycling water, reuse water, flushing, gardening, saving water` |
| `page_13_image_03.png` | 13 | Smart irrigation | A farm field of green crop rows being watered by sprinklers, with fine sprays of water over the plants. | `smart irrigation, sprinkler irrigation, farm field, crops, watering crops, irrigation` |
| `page_13_image_05.png` | 13 | Smart irrigation | A phone screen of an irrigation app showing soil moisture 72%, temperature and a motor switch that is on. | `smart irrigation, soil moisture, irrigation app, phone, sensor, motor` |

---

## 4. Chapter-wise, Topic-wise & Concept-wise Curriculum Hierarchy

# Topic 1: Water on Earth: A global view
- **Topic Master ID**: `26313`
- **Sort Order**: 1
- **Description**: Learners find out how Earth's water is shared between salt water and fresh water, and where the small share of usable fresh water is found. They see why water is spread unevenly across the planet.
- **Estimated Duration**: 20 minutes

## Concept 1.1: Salt water and fresh water
- **Concept ID (`lms_concept.id`)**: `46120`
- **Topic Reference**: Topic #26313 (Water on Earth: A global view)
- **Concept Type**: `Fact`
- **Difficulty Level**: `Easy`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Water covers about 71% of Earth's surface, but about 97% of it is salt water in oceans and seas that people cannot use directly. Only about 3% of Earth's water is fresh.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"covering about 71% of the planet’s surface"*
- **[Textbook]**: *"around 97% is salt water found in oceans and seas"*
- **[Textbook]**: *"making it unsuitable for direct human use"*
- **[Textbook]**: *"Only 3% of the Earth's water is fresh"*
- **[Textbook]**: *"covering about 71% of the planet’s surface"*
- **[Inferred]**: *"Students think that because water covers most of the Earth, there is plenty of water for people to drink and use."*
- **[Inferred]**: *"Students think there are roughly equal amounts of salt water and fresh water on Earth."*
- **[Textbook]**: *"covering about 71% of the planet’s surface"*

### 3. Knowledge Statements & Key Terms
- **Water covers about 71% of the surface**: Water covers about 71% of the surface of the Earth.
- **About 97% of water is salt water**: About 97% of Earth's water is salt water, found in the oceans and seas.
- **Salt water is unsuitable for direct use**: Salt water is unsuitable for direct human use.
- **Only 3% of water is fresh**: Only about 3% of the water on Earth is fresh water.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name the two kinds of water on Earth, salt water and fresh water.
- [Ability] Compare the share of salt water with the share of fresh water on Earth.
- [Ability] Explain why sea water cannot be used directly for drinking.

**Demonstrable Outcomes:**
- The student says that about 97% of Earth's water is salt water and only about 3% is fresh.
- The student explains in simple words why people cannot use most of Earth's water directly.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 50% |
| Bloom's Taxonomy | **Understand** | Coverage score: 50% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Earth has plenty of drinkable water"
  - *Observed Flawed Statement*: "Students think that because water covers most of the Earth, there is plenty of water for people to drink and use."
  - *Instructional Correction*: About 97% of Earth's water is salt water in oceans and seas, which people cannot use directly, so only a small part of all the water is useful for drinking.
- **Misconception**: "Salt and fresh water are about equal"
  - *Observed Flawed Statement*: "Students think there are roughly equal amounts of salt water and fresh water on Earth."
  - *Instructional Correction*: Salt water is the huge majority, about 97%, and fresh water is only about 3%.

### 7. Real-World Applications & Daily Context
- **Daily Life**: A family living by the sea still cannot drink the sea water, so they use water from taps, wells or rivers instead.
- **Environment**: Ships carry drinking water in tanks because the sea water all around them is salty.
- **Society**: Towns plan carefully how to share fresh water because only a small part of Earth's water is fresh.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Activity Based Teaching
  - *Why Effective*: Counting 100 beads, with 97 for salt water and 3 for fresh water, turns the percentages into a picture learners can touch and count.
  - *Characteristics*: Concrete model of parts out of 100, Hands-on counting
- **Strategy**: Inquiry Based Teaching
  - *Why Effective*: Asking 'There is so much water in the sea, so why can we not drink it?' starts from what learners already know about salty sea water and leads to the numbers.
  - *Characteristics*: Starts from the learners' own question, Links to everyday knowledge of salty sea water

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Salt water and fresh water` ──[related_to]──> `Where fresh water is found`
- `Salt water and fresh water` ──[related_to]──> `Clean water access and SDG 6`

**Prerequisites:**
- **Oceans and seas as very large bodies of water** (Knowledge, Necessity: Recommended)
- **Understanding a percentage as a part out of 100** (Knowledge, Necessity: Recommended)
- **Water is used for drinking, washing and farming** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 1.1.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: About how much of the water on Earth is salt water?

**Options**:
- **(A)** About 3%
  - *Rationale*: A learner may swap the two numbers and think the small share is salt water.
  - *Misconception Tested*: Students think that because water covers most of the Earth, there is plenty of water for people to drink and use.
- **(B)** About 97% **[CORRECT ANSWER]**
  - *Rationale*: The book says the majority of Earth's water, around 97%, is salt water found in oceans and seas.
- **(C)** About 50%
  - *Rationale*: A learner may guess half and half because both kinds of water are easy to picture.
  - *Misconception Tested*: Students think there are roughly equal amounts of salt water and fresh water on Earth.
- **(D)** About 71%
  - *Rationale*: 71% is the share of Earth's surface covered by water, not the share of the water that is salty.

**Anticipated Common Student Errors**:
- Mixing up 71% (surface covered by water) with 97% (salt water).
- Swapping 97% and 3%.

#### Question 1.1.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Water covers about 71% of Earth's surface. Which sentence about this water is true?

**Options**:
- **(A)** People can drink almost all of it.
  - *Rationale*: A learner may think that so much water must be drinkable.
  - *Misconception Tested*: Students think that because water covers most of the Earth, there is plenty of water for people to drink and use.
- **(B)** About half of it is salt water and half is fresh.
  - *Rationale*: A learner may guess an equal split.
  - *Misconception Tested*: Students think there are roughly equal amounts of salt water and fresh water on Earth.
- **(C)** All of it is fresh water in rivers and lakes.
  - *Rationale*: A learner may only picture the rivers and lakes they know.
- **(D)** Most of it is salt water, which people cannot use directly. **[CORRECT ANSWER]**
  - *Rationale*: The book says most of it is salt water, unsuitable for direct human use.

**Anticipated Common Student Errors**:
- Choosing A because there is so much water on the globe.

#### Question 1.1.3 (Short Answer · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Imagine all the water on Earth fills 100 cups. About how many cups are salt water and about how many are fresh water? Say why we cannot use the salt-water cups directly.

**Acceptable Marking Points**:
- About 97 cups are salt water and about 3 cups are fresh water.
  - *Alternative Acceptable Phrasings*: Salt water is almost all of it (about 97) and fresh water is a few (about 3).
- Salt water from oceans and seas is unsuitable for direct human use.
  - *Alternative Acceptable Phrasings*: We cannot drink sea water or use it straight away.

**Anticipated Common Student Errors**:
- Swapping 97 and 3.
- Giving the numbers without saying why salt water cannot be used.

#### Question 1.1.4 (Competency Based Question · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Ravi lives by the sea. He says, 'There is so much water around us that we will never run short of water to drink.' Use what you know about Earth's water to answer Ravi kindly.

**Indicative Content (Expected Key Points)**:
- Water covers about 71% of Earth's surface, so Ravi is right that there is a lot of water.
- But about 97% of Earth's water is salt water in oceans and seas, which is unsuitable for direct human use.
- Only about 3% of Earth's water is fresh, so the amount that suits drinking is small.
- A lot of water is not the same as a lot of usable water.

**Threshold Conditions for High Score**: For the highest levels, the answer must separate 'a lot of water' from 'a lot of fresh water that people can use'.

**Anticipated Common Student Errors**:
- Agreeing with Ravi because the sea is huge.
- Saying sea water is bad but not giving the 97% / 3% idea.

#### Question 1.1.5 (Practical · Difficulty: Easy · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Put 100 beads (or small pieces of paper) in a bowl to stand for all the water on Earth. Take out 3 beads and put them in a small cup. Say which pile stands for salt water and which for fresh water, and tell what the small cup shows you.

**Evaluation Rubric Criteria**:
- **Conceptual Understanding**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates conceptual understanding
  - *Level 2 (Progressing)*: Demonstrates conceptual understanding with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate conceptual understanding; needs regular support
- **Scientific Method / Process**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates scientific method / process
  - *Level 2 (Progressing)*: Demonstrates scientific method / process with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate scientific method / process; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support

**Anticipated Common Student Errors**:
- Counting out the wrong number of beads.
- Saying the small cup is the salt water.

---

## Concept 1.2: Where fresh water is found
- **Concept ID (`lms_concept.id`)**: `46121`
- **Topic Reference**: Topic #26313 (Water on Earth: A global view)
- **Concept Type**: `Fact`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Fresh water is found in rivers, lakes and underground aquifers, but most of it is locked away in glaciers and ice caps, so less than 1% of Earth's water is available for people to use.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"Fresh water is found in rivers, lakes, and underground aquifers"*
- **[Textbook]**: *"is locked away in glaciers and ice caps"*
- **[Textbook]**: *"less than 1% of Earth's water is available for people to use"*
- **[Textbook]**: *"for drinking, farming, washing, and other daily needs"*
- **[Textbook]**: *"Fresh water is found in rivers, lakes, and underground aquifers"*
- **[Inferred]**: *"Students think rivers and lakes hold all the fresh water on Earth."*
- **[Inferred]**: *"Students think all fresh water can be used by people because it is fresh."*
- **[Inferred]**: *"Students think an aquifer is a large lake or river hidden under the ground."*
- **[Textbook]**: *"Fresh water is found in rivers, lakes, and underground aquifers"*

### 3. Knowledge Statements & Key Terms
- **Fresh water is in rivers, lakes and aquifers**: Fresh water is found in rivers, lakes and underground aquifers.
- **Most fresh water is locked in ice**: Most fresh water is locked away in glaciers and ice caps.
- **Less than 1% of water is available to use**: Less than 1% of Earth's water is available for people to use.
- **Available water meets daily needs**: People use the fresh water they can reach for drinking, farming, washing and other daily needs.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name three kinds of places where fresh water is found.
- [Ability] Explain why most fresh water is hard for people to use.
- [Knowledge] State that less than 1% of Earth's water is available for people to use.

**Demonstrable Outcomes:**
- The student names rivers, lakes and underground aquifers as places where fresh water is found.
- The student explains that most fresh water is locked away in glaciers and ice caps, so very little is available to use.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 40% |
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Apply** | Coverage score: 20% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Fresh water is only in rivers and lakes"
  - *Observed Flawed Statement*: "Students think rivers and lakes hold all the fresh water on Earth."
  - *Instructional Correction*: Fresh water is also found in underground aquifers, and most of it is locked away in glaciers and ice caps.
- **Misconception**: "All fresh water is ready for people to use"
  - *Observed Flawed Statement*: "Students think all fresh water can be used by people because it is fresh."
  - *Instructional Correction*: Most fresh water is locked away in ice, so less than 1% of Earth's water is available for people to use.
- **Misconception**: "Aquifers are big underground lakes"
  - *Observed Flawed Statement*: "Students think an aquifer is a large lake or river hidden under the ground."
  - *Instructional Correction*: An aquifer is a layer of underground rock, sand or gravel that holds water in its tiny spaces, a little like a wet sponge.

### 7. Real-World Applications & Daily Context
- **Daily Life**: Water for drinking and washing comes from rivers, from lakes, or from wells that reach water under the ground.
- **Society**: Water planners find out how much fresh water is in rivers, lakes and under the ground before deciding how much a town can use.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Activity Based Teaching
  - *Why Effective*: Drawing a landscape with a river, a lake, a glacier and an aquifer lets learners place each store of fresh water and see which are locked away.
  - *Characteristics*: Visual sorting, Labelled diagrams
- **Strategy**: Experiential Based Teaching
  - *Why Effective*: Soaking a sponge or a jar of sand with water shows that water can hide in the tiny spaces of the ground, which corrects the idea of an underground lake.
  - *Characteristics*: Concrete model, Corrects a common misconception

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Where fresh water is found` ──[extends]──> `Salt water and fresh water`
- `Where fresh water is found` ──[related_to]──> `Major water bodies of the world`

**Prerequisites:**
- **The difference between fresh water and salt water** (Knowledge, Necessity: Recommended)
- **Rivers and lakes as sources of water** (Knowledge, Necessity: Recommended)
- **Ice is frozen water** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 1.2.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Which list names places where fresh water is found?

**Options**:
- **(A)** Oceans, seas and salty lakes
  - *Rationale*: A learner may mix up fresh water with the salt water of oceans and seas.
- **(B)** Only rivers and lakes
  - *Rationale*: A learner may name only the fresh water they can see and forget the water under the ground.
  - *Misconception Tested*: Students think rivers and lakes hold all the fresh water on Earth.
- **(C)** Rivers, lakes and underground aquifers **[CORRECT ANSWER]**
  - *Rationale*: The book names rivers, lakes and underground aquifers as places where fresh water is found.
- **(D)** Only deserts and dry plains
  - *Rationale*: The book says deserts and dry plains face shortages of water.

**Anticipated Common Student Errors**:
- Choosing B because rivers and lakes are the water learners can see.

#### Question 1.2.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Only a very small part of Earth's water is available for people to use. What is the main reason?

**Options**:
- **(A)** Most fresh water is locked away in glaciers and ice caps. **[CORRECT ANSWER]**
  - *Rationale*: The book says most fresh water is locked away in glaciers and ice caps, so little is available to use.
- **(B)** There are only a few rivers and lakes in the world.
  - *Rationale*: A learner who thinks fresh water is only in rivers and lakes may blame the number of rivers and lakes.
  - *Misconception Tested*: Students think rivers and lakes hold all the fresh water on Earth.
- **(C)** Aquifers are big underground lakes that nobody can reach.
  - *Rationale*: A learner may picture an aquifer as a hidden pool that cannot be used.
  - *Misconception Tested*: Students think an aquifer is a large lake or river hidden under the ground.
- **(D)** All fresh water is ready to use, but people have already used most of it.
  - *Rationale*: A learner may think fresh water is always ready to use and that the shortage comes from using it up.
  - *Misconception Tested*: Students think all fresh water can be used by people because it is fresh.

**Anticipated Common Student Errors**:
- Choosing B or D without thinking about water locked in ice.

#### Question 1.2.3 (Short Answer · Difficulty: Medium · DOK 1 · Bloom: Understand)
**Prompt / Stem**: Name three kinds of places where fresh water is found. Then say where most fresh water is locked away.

**Acceptable Marking Points**:
- Fresh water is found in rivers, lakes and underground aquifers (all three named).
  - *Alternative Acceptable Phrasings*: Rivers; lakes; water under the ground (aquifers).; Glaciers and ice caps also count as places where fresh water is found; accept any three of the places the book names.
- Most fresh water is locked away in glaciers and ice caps.
  - *Alternative Acceptable Phrasings*: It is frozen in the ice of glaciers and ice caps.

**Anticipated Common Student Errors**:
- Naming the ocean as a place for fresh water.
- Forgetting the underground aquifers.

#### Question 1.2.4 (Competency Based Question · Difficulty: Hard · DOK 3 · Bloom: Analyze)
**Prompt / Stem**: Sara says, 'Only 3% of Earth's water is fresh, so we can use all of that 3%.' Is Sara right? Use what you have learned about where fresh water is found to explain.

**Indicative Content (Expected Key Points)**:
- Sara is not right: only a small part of the fresh water can be used.
- Most fresh water is locked away in glaciers and ice caps, where people cannot reach it.
- Less than 1% of Earth's water is available for people to use.
- This small amount must supply drinking, farming, washing and other daily needs, so it is precious.

**Threshold Conditions for High Score**: For the highest levels, the answer must say that most fresh water is locked away in ice.

**Anticipated Common Student Errors**:
- Agreeing with Sara because 3% sounds like a fair amount.
- Saying the ice is salt water.

#### Question 1.2.5 (Practical · Difficulty: Easy · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Draw a landscape with a mountain, a river, a lake and the ground below. Show and label a glacier or ice cap, a river, a lake and an underground aquifer. Colour the place where most fresh water is locked away.

**Evaluation Rubric Criteria**:
- **Conceptual Understanding**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates conceptual understanding
  - *Level 2 (Progressing)*: Demonstrates conceptual understanding with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate conceptual understanding; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support
- **Record / Documentation**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates record / documentation
  - *Level 2 (Progressing)*: Demonstrates record / documentation with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate record / documentation; needs regular support

**Anticipated Common Student Errors**:
- Drawing the aquifer as a lake with a roof.
- Colouring the river as the place where most fresh water is locked away.

---

## Concept 1.3: Uneven distribution of water
- **Concept ID (`lms_concept.id`)**: `46122`
- **Topic Reference**: Topic #26313 (Water on Earth: A global view)
- **Concept Type**: `Principle`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Water is not shared equally across the planet: regions around large rivers and lakes have plenty, while deserts and dry plains face chronic shortages. This shapes where people live, how they grow food and how societies develop.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"its distribution across the planet is unequal"*
- **[Textbook]**: *"those around large rivers and lakes have abundant water"*
- **[Textbook]**: *"such as deserts or dry plains, face chronic shortages"*
- **[Textbook]**: *"where people can live, how they grow food, and how societies develop"*
- **[Textbook]**: *"its distribution across the planet is unequal"*
- **[Inferred]**: *"Students think every place on Earth gets about the same amount of water."*
- **[Inferred]**: *"Students think a desert has no water at all."*
- **[Inferred]**: *"Students think people can live and farm anywhere, whatever the water supply."*
- **[Textbook]**: *"its distribution across the planet is unequal"*

### 3. Knowledge Statements & Key Terms
- **Water is not spread equally**: Fresh water is not spread equally across the planet.
- **Rivers and lakes bring abundant water**: Regions around large rivers and lakes have abundant water.
- **Deserts and dry plains have shortages**: Deserts and dry plains face chronic shortages of water.
- **Water shapes where people live and grow food**: How water is spread across the planet affects where people can live, how they grow food and how societies develop.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Ability] Explain what it means to say that water is unevenly shared across the planet.
- [Ability] Compare a region with abundant water with a region that faces chronic shortages.
- [Knowledge] Describe one way the amount of water in a place affects how people live.

**Demonstrable Outcomes:**
- The student explains that some regions have plenty of water while deserts and dry plains face shortages.
- The student gives one example of how the amount of water affects where people live or how they grow food.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Understand** | Coverage score: 50% |
| Bloom's Taxonomy | **Apply** | Coverage score: 30% |
| Bloom's Taxonomy | **Analyze** | Coverage score: 20% |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |
| Depth of Knowledge (DOK) | **Level 3** | Strategic Thinking |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Water is shared equally everywhere"
  - *Observed Flawed Statement*: "Students think every place on Earth gets about the same amount of water."
  - *Instructional Correction*: Water is not shared equally: some regions have abundant water and others face chronic shortages.
- **Misconception**: "Deserts have no water at all"
  - *Observed Flawed Statement*: "Students think a desert has no water at all."
  - *Instructional Correction*: Deserts and dry plains face chronic shortages, which means very little water, not none.
- **Misconception**: "Water does not decide where people live"
  - *Observed Flawed Statement*: "Students think people can live and farm anywhere, whatever the water supply."
  - *Instructional Correction*: The way water is spread across the planet plays a crucial role in where people can live, how they grow food and how societies develop.

### 7. Real-World Applications & Daily Context
- **Daily Life**: In a dry region a family may have to walk far or wait for a tanker to get water, while a family beside a river can fetch it close to home.
- **Environment**: Farmers on a plain beside a large river can grow crops more easily than farmers on a dry plain far from any river.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Game Based Teaching
  - *Why Effective*: In a water-sharing game some groups get a full cup and others only a few spoonfuls, so learners feel what uneven sharing means and talk about what is fair.
  - *Characteristics*: Role play, Feeling the difference
- **Strategy**: Inquiry Based Teaching
  - *Why Effective*: Looking at a map that shows rivers and deserts, learners ask why people live where they do, which builds the link between water and where people settle.
  - *Characteristics*: Map reading, Asking why

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Uneven distribution of water` ──[depends_on]──> `Where fresh water is found`
- `Uneven distribution of water` ──[causes]──> `Water-rich and water-poor countries`

**Prerequisites:**
- **People need water to live and to grow food** (Knowledge, Necessity: Mandatory)
- **Deserts are very dry places** (Knowledge, Necessity: Recommended)
- **Reading a simple map or globe** (Skill, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 1.3.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: What does it mean to say that water is unevenly distributed on Earth?

**Options**:
- **(A)** Every region gets the same amount of water.
  - *Rationale*: A learner may think water is the same everywhere because it is the same at home.
  - *Misconception Tested*: Students think every place on Earth gets about the same amount of water.
- **(B)** Deserts have no water at all.
  - *Rationale*: A learner may picture a desert as having zero water; the book says deserts face chronic shortages.
  - *Misconception Tested*: Students think a desert has no water at all.
- **(C)** People can live anywhere, whatever the water supply.
  - *Rationale*: A learner may think the water supply does not matter when choosing where to live.
  - *Misconception Tested*: Students think people can live and farm anywhere, whatever the water supply.
- **(D)** Some regions have plenty of water while others face shortages. **[CORRECT ANSWER]**
  - *Rationale*: This is what unequal distribution means in the book.

**Anticipated Common Student Errors**:
- Choosing A because water seems the same everywhere at home.

#### Question 1.3.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: One region has many large rivers and lakes. Another region is mostly desert. Which statement fits the book?

**Options**:
- **(A)** Both regions have the same amount of water.
  - *Rationale*: A learner may think every region has an equal share of water.
  - *Misconception Tested*: Students think every place on Earth gets about the same amount of water.
- **(B)** The first has abundant water and the desert faces chronic shortages. **[CORRECT ANSWER]**
  - *Rationale*: The book says regions around large rivers and lakes have abundant water, while deserts face chronic shortages.
- **(C)** The desert has no water, so nobody has ever lived there.
  - *Rationale*: A learner may think a desert has zero water; the book says it faces shortages.
  - *Misconception Tested*: Students think a desert has no water at all.
- **(D)** The desert region has more water because it is so large.
  - *Rationale*: A learner may think a bigger region must hold more water.

**Anticipated Common Student Errors**:
- Choosing C because deserts look completely dry.

#### Question 1.3.3 (Short Answer · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Give two ways in which the amount of water in a place can affect people.

**Acceptable Marking Points**:
- It affects where people can live.
  - *Alternative Acceptable Phrasings*: Few people can live where there is very little water.
- It affects how people grow food.
  - *Alternative Acceptable Phrasings*: Farmers find it hard to grow crops where there is little water.
- It affects how societies develop.
  - *Alternative Acceptable Phrasings*: Towns and countries with plenty of water can grow more easily.

**Anticipated Common Student Errors**:
- Giving only one way.
- Writing about the colour or taste of the water instead of the amount.

#### Question 1.3.4 (Competency Based Question · Difficulty: Medium · DOK 3 · Bloom: Analyze)
**Prompt / Stem**: Village A is beside a large river. Village B is on a dry plain far from any river. Compare the two villages. How may life be different for their farmers and families?

**Indicative Content (Expected Key Points)**:
- Village A has abundant water from the river, while Village B faces a chronic shortage.
- Farmers in Village A can grow food more easily; farmers in Village B find it hard to grow crops without water.
- Families in Village B may have to fetch water from far away or manage with less.
- Where water is found affects where people live, how they grow food and how societies develop.

**Threshold Conditions for High Score**: For the highest levels, the answer must link the amount of water to both farming and daily life.

**Anticipated Common Student Errors**:
- Saying the villages are the same.
- Describing only one village.

#### Question 1.3.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Water-sharing game: give each group a different amount of water in a cup (a full cup, half a cup, a few spoonfuls, none). Each group tries to water a small plant and rinse a spoon. Watch what each group can and cannot do, then tell the class how this is like water being unevenly shared on Earth.

**Evaluation Rubric Criteria**:
- **Conceptual Understanding**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates conceptual understanding
  - *Level 2 (Progressing)*: Demonstrates conceptual understanding with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate conceptual understanding; needs regular support
- **Application & Relevance**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates application & relevance
  - *Level 2 (Progressing)*: Demonstrates application & relevance with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate application & relevance; needs regular support
- **Collaboration & Teamwork**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates collaboration & teamwork
  - *Level 2 (Progressing)*: Demonstrates collaboration & teamwork with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate collaboration & teamwork; needs regular support

**Anticipated Common Student Errors**:
- Talking only about the game and not about water on Earth.
- Giving every group the same amount of water.

---

## Concept 1.4: Major water bodies of the world
- **Concept ID (`lms_concept.id`)**: `46123`
- **Topic Reference**: Topic #26313 (Water on Earth: A global view)
- **Concept Type**: `Fact`
- **Difficulty Level**: `Easy`
- **Importance Rating**: `Important`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Major water bodies include oceans such as the Pacific and Atlantic, lakes such as Lake Baikal and Lake Victoria, and rivers such as the Nile, Amazon, Ganges and Yangtze. Frozen water is found in places such as Antarctica, Greenland and Iceland.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"oceans like the Pacific and Atlantic"*
- **[Textbook]**: *"lakes such as Lake Baikal and Lake Victoria"*
- **[Textbook]**: *"the Nile (the longest river in the world), Amazon, Ganges, and Yangtze"*
- **[Textbook]**: *"Frozen water is concentrated in Antarctica and Greenland and Iceland"*
- **[Textbook]**: *"oceans like the Pacific and Atlantic"*
- **[Inferred]**: *"Students think oceans, lakes and rivers are all the same kind of water body."*
- **[Inferred]**: *"Students think the only big place of frozen water is the North Pole."*
- **[Textbook]**: *"oceans like the Pacific and Atlantic"*

### 3. Knowledge Statements & Key Terms
- **The Pacific and Atlantic are major oceans**: The Pacific and the Atlantic are major oceans.
- **Lake Baikal and Lake Victoria are major lakes**: Lake Baikal and Lake Victoria are major lakes.
- **The Nile, Amazon, Ganges and Yangtze are major rivers**: The Nile, the Amazon, the Ganges and the Yangtze are major rivers.
- **Frozen water is found in Antarctica, Greenland and Iceland**: Frozen water is found in places such as Antarctica, Greenland and Iceland.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name the oceans, lakes and rivers that the book lists as major water bodies.
- [Ability] Classify named water bodies as oceans, lakes, rivers or places of frozen water.
- [Skill] Locate the major water bodies on a world map.

**Demonstrable Outcomes:**
- The student names at least one ocean, one lake and one river from the book and one place where frozen water is found.
- The student marks and labels major water bodies on a world map.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 60% |
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Oceans, lakes and rivers are all the same"
  - *Observed Flawed Statement*: "Students think oceans, lakes and rivers are all the same kind of water body."
  - *Instructional Correction*: An ocean is a huge body of salt water, a lake is water held in a hollow of the land, and a river is water flowing along a channel.
- **Misconception**: "Frozen water is found only at the North Pole"
  - *Observed Flawed Statement*: "Students think the only big place of frozen water is the North Pole."
  - *Instructional Correction*: The book names Antarctica, Greenland and Iceland as places with frozen water. Antarctica lies around the South Pole.

### 7. Real-World Applications & Daily Context
- **Daily Life**: A learner uses a map or globe to find the river, lake or ocean named in a news story or a lesson.
- **Research**: Scientists study the ice of Antarctica and Greenland to learn about the frozen water of our planet.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Activity Based Teaching
  - *Why Effective*: Marking each named water body on an outline world map, with one colour for each kind, helps the names stick and shows where they are.
  - *Characteristics*: Map work, Colour coding
- **Strategy**: Flashcard Based / Spaced Repetition Teaching
  - *Why Effective*: Cards with a name on one side and 'ocean', 'lake', 'river' or 'frozen water' on the back let learners practise the names little and often.
  - *Characteristics*: Short repeated practice, Matching a name to its kind

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Major water bodies of the world` ──[related_to]──> `Where fresh water is found`
- `Major water bodies of the world` ──[related_to]──> `River valley civilisations`

**Prerequisites:**
- **Oceans, lakes and rivers as kinds of water bodies** (Knowledge, Necessity: Recommended)
- **Finding places on a world map or globe** (Skill, Necessity: Recommended)
- **Ice and snow are frozen water** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 1.4.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Which of these is an ocean?

**Options**:
- **(A)** The Pacific **[CORRECT ANSWER]**
  - *Rationale*: The book names the Pacific and the Atlantic as major oceans.
- **(B)** Lake Victoria
  - *Rationale*: A learner may think any very big water body is an ocean; Lake Victoria is a lake.
  - *Misconception Tested*: Students think oceans, lakes and rivers are all the same kind of water body.
- **(C)** The Nile
  - *Rationale*: A learner may think any famous water body is an ocean; the Nile is a river.
  - *Misconception Tested*: Students think oceans, lakes and rivers are all the same kind of water body.
- **(D)** Antarctica
  - *Rationale*: Antarctica is land covered with ice, not an ocean.

**Anticipated Common Student Errors**:
- Choosing a lake or a river because it is large.

#### Question 1.4.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Which of these places does the book name as a place where frozen water is found?

**Options**:
- **(A)** The North Pole
  - *Rationale*: A learner may think ice is found only at the North Pole; the book does not name it.
  - *Misconception Tested*: Students think the only big place of frozen water is the North Pole.
- **(B)** The Amazon
  - *Rationale*: The Amazon is a river that flows through a warm land.
- **(C)** Greenland **[CORRECT ANSWER]**
  - *Rationale*: The book names Antarctica, Greenland and Iceland as places with frozen water.
- **(D)** Lake Victoria
  - *Rationale*: Lake Victoria is a lake in a warm part of the world.

**Anticipated Common Student Errors**:
- Choosing the North Pole because ice is linked with it in stories.

#### Question 1.4.3 (Short Answer · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: From the book, name one ocean, one lake, one river and one place where frozen water is found.

**Acceptable Marking Points**:
- An ocean: the Pacific or the Atlantic.
  - *Alternative Acceptable Phrasings*: Pacific Ocean; Atlantic Ocean
- A lake: Lake Baikal or Lake Victoria.
  - *Alternative Acceptable Phrasings*: Baikal; Victoria
- A river named in the book, for example the Nile, the Amazon, the Ganges or the Yangtze.
  - *Alternative Acceptable Phrasings*: Any river the book names, including the Tigris, the Euphrates, the Indus or the Yellow River.
- A place of frozen water: Antarctica, Greenland or Iceland.
  - *Alternative Acceptable Phrasings*: Any one of the three places named in the book.

**Anticipated Common Student Errors**:
- Naming a river as a lake.
- Naming an ocean twice and leaving out a kind.

#### Question 1.4.4 (Competency Based Question · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Your teacher gives you eight name cards: Pacific, Lake Baikal, Nile, Antarctica, Atlantic, Lake Victoria, Amazon, Greenland. Sort them into four groups: oceans, lakes, rivers and places of frozen water.

**Indicative Content (Expected Key Points)**:
- Oceans: the Pacific and the Atlantic.
- Lakes: Lake Baikal and Lake Victoria.
- Rivers: the Nile and the Amazon.
- Places of frozen water: Antarctica and Greenland.

**Threshold Conditions for High Score**: For the highest levels, all eight names must be sorted correctly and the difference between a lake and a river must be given.

**Anticipated Common Student Errors**:
- Putting Antarctica or Greenland in the ocean group.
- Saying a lake and a river are the same.

#### Question 1.4.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: On an outline map of the world, mark and label the Pacific and Atlantic oceans, Lake Baikal, Lake Victoria, the Nile, Amazon, Ganges and Yangtze, and the places of frozen water in Antarctica, Greenland and Iceland. Use one colour for each kind of water.

**Evaluation Rubric Criteria**:
- **Data Handling & Accuracy**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates data handling & accuracy
  - *Level 2 (Progressing)*: Demonstrates data handling & accuracy with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate data handling & accuracy; needs regular support
- **Record / Documentation**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates record / documentation
  - *Level 2 (Progressing)*: Demonstrates record / documentation with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate record / documentation; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support

**Anticipated Common Student Errors**:
- Placing a name on the wrong continent.
- Using the same colour for lakes and rivers.

---

# Topic 2: How water shaped civilisations
- **Topic Master ID**: `26314`
- **Sort Order**: 2
- **Description**: Learners explore how the great ancient civilisations grew beside rivers for water, farming, trade and travel. They also see how floods and poor water management caused problems and decline.
- **Estimated Duration**: 25 minutes

## Concept 2.1: Rivers supported early settlements
- **Concept ID (`lms_concept.id`)**: `46124`
- **Topic Reference**: Topic #26314 (How water shaped civilisations)
- **Concept Type**: `Concept`
- **Difficulty Level**: `Easy`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Rivers gave early people fresh water, fertile soil from seasonal floods and easy transportation, so settlements built near rivers could farm and their communities could grow.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"they offered fresh water, fertile soil from seasonal floods, and easy transportation"*
- **[Textbook]**: *"Early human settlements were often built near rivers"*
- **[Textbook]**: *"which allowed agriculture to flourish and communities to expand"*
- **[Textbook]**: *"provided drinking water, irrigation for crops, fish, and a means of trade and communication"*
- **[Textbook]**: *"annual floods enriched Egyptian soil, making farming productive"*
- **[Textbook]**: *"they offered fresh water, fertile soil from seasonal floods, and easy transportation"*
- **[Inferred]**: *"Students think early people lived near rivers only to get water to drink."*
- **[Inferred]**: *"Students think a river flood can only damage fields and homes."*
- **[Textbook]**: *"they offered fresh water, fertile soil from seasonal floods, and easy transportation"*

### 3. Knowledge Statements & Key Terms
- **Rivers offered water, soil and transport**: Rivers offered fresh water, fertile soil from seasonal floods and easy transportation.
- **Early settlements were built near rivers**: Early human settlements were often built near rivers.
- **Rivers helped farming and communities grow**: Living near rivers allowed agriculture to flourish and communities to expand.
- **Rivers gave water for crops, fish and trade**: Rivers provided drinking water, water for crops, fish, and a way to trade and communicate.
- **Nile floods made Egyptian soil rich**: The yearly floods of the Nile enriched Egyptian soil and made farming productive.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name three things that rivers offered to early settlements.
- [Ability] Explain why early settlements were often built near rivers.
- [Knowledge] Describe how the yearly floods of the Nile helped farming in Egypt.

**Demonstrable Outcomes:**
- The student names fresh water, fertile soil and easy transportation as things rivers offered.
- The student explains why a settlement beside a river could farm and grow.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 30% |
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Apply** | Coverage score: 30% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Early people settled by rivers only to drink"
  - *Observed Flawed Statement*: "Students think early people lived near rivers only to get water to drink."
  - *Instructional Correction*: Rivers also gave fertile soil from seasonal floods, water for crops, fish, and easy transportation for trade and travel.
- **Misconception**: "Floods only ever harm farmers"
  - *Observed Flawed Statement*: "Students think a river flood can only damage fields and homes."
  - *Instructional Correction*: Regular seasonal floods spread fertile soil over the land, and the yearly Nile floods made Egyptian farming productive.

### 7. Real-World Applications & Daily Context
- **Society**: Many old towns and cities grew beside rivers, where boats carried people and goods from place to place.
- **Daily Life**: Fishing families and boat travellers today still depend on rivers for food and a way to move about.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Project Based Teaching
  - *Why Effective*: Building a clay or sand-tray model of a river village shows how water, fields, houses and boats fit together.
  - *Characteristics*: Making a model, Seeing connections
- **Strategy**: Scenario Based Teaching
  - *Why Effective*: Asking learners to choose a village site beside a river or on a dry hill makes them use the reasons rivers give to explain their choice.
  - *Characteristics*: Choosing and justifying, Role play as early settlers

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Rivers supported early settlements` ──[causes]──> `River valley civilisations`
- `Rivers supported early settlements` ──[related_to]──> `Water threats and decline`

**Prerequisites:**
- **Farming needs water and good soil** (Knowledge, Necessity: Mandatory)
- **Rivers carry water across the land** (Knowledge, Necessity: Recommended)
- **A settlement is a place where people live together** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 2.1.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Why were early human settlements often built near rivers?

**Options**:
- **(A)** Rivers kept all floods away from the settlement.
  - *Rationale*: A learner may think floods are always harmful, so rivers must keep them away.
  - *Misconception Tested*: Students think a river flood can only damage fields and homes.
- **(B)** Rivers were only needed for drinking water.
  - *Rationale*: A learner may think of drinking first and forget the other gifts of a river.
  - *Misconception Tested*: Students think early people lived near rivers only to get water to drink.
- **(C)** Rivers offered fresh water, fertile soil and easy transportation. **[CORRECT ANSWER]**
  - *Rationale*: The book gives these three reasons for settling near rivers.
- **(D)** Rivers were the only place where food could be found.
  - *Rationale*: The book does not say this; rivers helped farming but food came from other places too.

**Anticipated Common Student Errors**:
- Choosing B and naming only drinking water.

#### Question 2.1.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: How did the yearly floods of the Nile help farmers in Egypt?

**Options**:
- **(A)** They washed the soil away so the fields stayed clean.
  - *Rationale*: A learner may think any flood only takes things away.
  - *Misconception Tested*: Students think a river flood can only damage fields and homes.
- **(B)** They enriched the soil, so farming became productive. **[CORRECT ANSWER]**
  - *Rationale*: The book says the annual floods enriched Egyptian soil and made farming productive.
- **(C)** They dried up the fields so crops grew faster.
  - *Rationale*: A learner may mix up floods and droughts; floods bring water and soil, not dryness.
- **(D)** They stopped boats from using the river.
  - *Rationale*: The book says rivers gave easy transportation; it does not say floods stopped boats.

**Anticipated Common Student Errors**:
- Choosing A because floods are linked with damage.

#### Question 2.1.3 (Short Answer · Difficulty: Easy · DOK 1 · Bloom: Understand)
**Prompt / Stem**: Name two things that a river gave to people who settled near it.

**Acceptable Marking Points**:
- Fresh water, for drinking and for crops.
  - *Alternative Acceptable Phrasings*: Water to drink; Water for irrigation
- Fertile soil from seasonal floods.
  - *Alternative Acceptable Phrasings*: Rich soil for farming
- Easy transportation, for travel and trade.
  - *Alternative Acceptable Phrasings*: A way to move by boat; Fish and trade (also accepted)

**Anticipated Common Student Errors**:
- Giving the same thing twice, such as 'water to drink' and 'drinking water'.

#### Question 2.1.4 (Competency Based Question · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Imagine you are a villager long ago. You can build your village beside a river or on a dry hill far from any water. Which would you choose? Give three reasons.

**Indicative Content (Expected Key Points)**:
- Beside the river: fresh water for drinking.
- Fertile soil from seasonal floods and water for crops, so farming can flourish.
- Easy transportation, fish and trade, so the community can expand.
- On a dry hill far from water, farming and daily life would be hard.

**Threshold Conditions for High Score**: For the highest levels, the answer must give at least three different reasons linked to what rivers offer.

**Anticipated Common Student Errors**:
- Choosing the hill without a reason.
- Giving three reasons that all say 'water'.

#### Question 2.1.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Make a small model of a river village in a sand tray or with clay. Show the river, fields, houses and a boat. Point to each part and say how the river helps the people who live there.

**Evaluation Rubric Criteria**:
- **Application & Relevance**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates application & relevance
  - *Level 2 (Progressing)*: Demonstrates application & relevance with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate application & relevance; needs regular support
- **Originality & Creativity**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates originality & creativity
  - *Level 2 (Progressing)*: Demonstrates originality & creativity with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate originality & creativity; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support

**Anticipated Common Student Errors**:
- Making the model without saying how the river helps.
- Placing the fields far from the river with no water reaching them.

---

## Concept 2.2: River valley civilisations
- **Concept ID (`lms_concept.id`)**: `46125`
- **Topic Reference**: Topic #26314 (How water shaped civilisations)
- **Concept Type**: `Fact`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Ancient Egypt grew along the Nile, Mesopotamia along the Tigris and Euphrates, the Indus Valley along the Indus and Ancient China along the Yellow River. Each managed water in its own way: canals, wells and drains, or dams.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"Ancient Egypt by the Nile, Mesopotamia by the Tigris and Euphrates"*
- **[Textbook]**: *"the Indus Valley by the Indus River, and Ancient China along the Yellow River"*
- **[Textbook]**: *"people used irrigation canals to farm even in a dry climate"*
- **[Textbook]**: *"cities like Mohenjo-Daro show evidence of sophisticated water management with wells and drainage systems"*
- **[Textbook]**: *"forcing people to build protective dams"*
- **[Textbook]**: *"Ancient Egypt by the Nile, Mesopotamia by the Tigris and Euphrates"*
- **[Inferred]**: *"Students think all the great ancient civilisations grew beside the Nile."*
- **[Inferred]**: *"Students think every river civilisation used the same way of managing water."*
- **[Inferred]**: *"Students think wells, drains, canals and dams were not known in ancient times."*
- **[Textbook]**: *"Ancient Egypt by the Nile, Mesopotamia by the Tigris and Euphrates"*

### 3. Knowledge Statements & Key Terms
- **Egypt and Mesopotamia grew along rivers**: Ancient Egypt grew beside the Nile, and Mesopotamia beside the Tigris and Euphrates.
- **Indus Valley and China grew along rivers**: The Indus Valley grew along the Indus River, and Ancient China along the Yellow River.
- **Mesopotamia used irrigation canals**: In Mesopotamia people used irrigation canals to farm even in a dry climate.
- **Indus Valley cities had wells and drainage**: Indus Valley cities such as Mohenjo-Daro had wells and drainage systems to manage water.
- **Yellow River people built protective dams**: People along the Yellow River built protective dams.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Match each river civilisation with the river it grew along.
- [Knowledge] Describe how people in Mesopotamia, the Indus Valley and China managed water.
- [Ability] Explain why different civilisations managed water in different ways.

**Demonstrable Outcomes:**
- The student names the river beside which Egypt, Mesopotamia, the Indus Valley and Ancient China grew.
- The student explains why a river civilisation chose canals, wells and drains, or dams.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 40% |
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Analyze** | Coverage score: 20% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "All ancient civilisations grew along the Nile"
  - *Observed Flawed Statement*: "Students think all the great ancient civilisations grew beside the Nile."
  - *Instructional Correction*: Different civilisations grew beside different rivers: the Nile, the Tigris and Euphrates, the Indus and the Yellow River.
- **Misconception**: "All ancient people managed water the same way"
  - *Observed Flawed Statement*: "Students think every river civilisation used the same way of managing water."
  - *Instructional Correction*: Mesopotamia used irrigation canals, Indus Valley cities used wells and drainage, and people on the Yellow River built protective dams.
- **Misconception**: "Water management is a modern invention"
  - *Observed Flawed Statement*: "Students think wells, drains, canals and dams were not known in ancient times."
  - *Instructional Correction*: Ancient cities such as Mohenjo-Daro already had wells and drainage systems, and ancient people built canals and dams.

### 7. Real-World Applications & Daily Context
- **Daily Life**: The drains in our streets and the canals that bring water to farms use ideas that ancient river civilisations also used.
- **Research**: Archaeologists study the wells and drains of Mohenjo-Daro to learn how people lived with water long ago.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Art Integrated Teaching
  - *Why Effective*: Drawing a poster with four rivers and each civilisation's water method lets learners join a place, a river and a method in one picture.
  - *Characteristics*: Visual summary, Linking place and method
- **Strategy**: Inquiry Based Teaching
  - *Why Effective*: Asking 'Why dams on the Yellow River but canals in Mesopotamia?' makes learners connect each method to the water needs of its place.
  - *Characteristics*: Why questions, Cause and effect

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `River valley civilisations` ──[depends_on]──> `Rivers supported early settlements`
- `River valley civilisations` ──[related_to]──> `Water threats and decline`

**Prerequisites:**
- **Ancient times and early civilisations** (Knowledge, Necessity: Recommended)
- **Canals, wells and dams as ways to move or hold water** (Knowledge, Necessity: Recommended)
- **Reading a simple map to find rivers** (Skill, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 2.2.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Which civilisation is matched with the correct river?

**Options**:
- **(A)** Ancient Egypt and the Yellow River
  - *Rationale*: A learner may mix up two famous rivers; the Yellow River belongs to Ancient China.
- **(B)** Ancient China and the Nile
  - *Rationale*: A learner may link the Nile with every ancient civilisation.
  - *Misconception Tested*: Students think all the great ancient civilisations grew beside the Nile.
- **(C)** The Indus Valley and the Yellow River
  - *Rationale*: A learner may mix up two Asian rivers; the Indus Valley grew along the Indus River.
- **(D)** Mesopotamia and the Tigris and Euphrates **[CORRECT ANSWER]**
  - *Rationale*: The book says Mesopotamia grew by the Tigris and Euphrates.

**Anticipated Common Student Errors**:
- Choosing B because the Nile is the best-known river.

#### Question 2.2.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Which way of managing water did people use in Indus Valley cities such as Mohenjo-Daro?

**Options**:
- **(A)** Wells and drainage systems **[CORRECT ANSWER]**
  - *Rationale*: The book says Mohenjo-Daro shows wells and drainage systems.
- **(B)** Irrigation canals only
  - *Rationale*: A learner may give the method of Mesopotamia for every civilisation.
  - *Misconception Tested*: Students think every river civilisation used the same way of managing water.
- **(C)** Protective dams on the river
  - *Rationale*: A learner may give the method of the Yellow River for every civilisation.
  - *Misconception Tested*: Students think every river civilisation used the same way of managing water.
- **(D)** They did not manage water at all
  - *Rationale*: A learner may think ancient cities had no water systems.
  - *Misconception Tested*: Students think wells, drains, canals and dams were not known in ancient times.

**Anticipated Common Student Errors**:
- Choosing D because drains seem modern.

#### Question 2.2.3 (Short Answer · Difficulty: Medium · DOK 1 · Bloom: Understand)
**Prompt / Stem**: Say how people managed water in Mesopotamia, in Indus Valley cities such as Mohenjo-Daro, and on the Yellow River in China.

**Acceptable Marking Points**:
- Mesopotamia: irrigation canals to farm in a dry climate.
  - *Alternative Acceptable Phrasings*: They dug canals to bring river water to the fields.
- Indus Valley cities: wells and drainage systems.
  - *Alternative Acceptable Phrasings*: Wells for water and drains to carry it away.
- Yellow River (China): protective dams.
  - *Alternative Acceptable Phrasings*: They built dams to protect against floods.

**Anticipated Common Student Errors**:
- Giving the same method for all three places.

#### Question 2.2.4 (HOTS · Difficulty: Hard · DOK 3 · Bloom: Analyze)
**Prompt / Stem**: The Yellow River brought frequent floods, but Mesopotamia had a dry climate. Why do you think people in China built protective dams while people in Mesopotamia dug irrigation canals?

**Indicative Content (Expected Key Points)**:
- The Yellow River brought frequent floods, so dams were built to protect people and fields.
- Mesopotamia had a dry climate, so canals carried river water to the farms.
- Each people solved the water problem it faced.
- How water is managed depends on the place and its needs.

**Threshold Conditions for High Score**: For the highest levels, the answer must link each method to the problem of its place (floods or dry climate).

**Anticipated Common Student Errors**:
- Saying the people copied each other.
- Mixing up which method belongs to which place.

#### Question 2.2.5 (Viva · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Tell your teacher which four river civilisations you learned about and which river belongs to each. Then choose one and say how its people managed water.

**Evaluation Rubric Criteria**:
- **Viva / Oral Defence**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates viva / oral defence
  - *Level 2 (Progressing)*: Demonstrates viva / oral defence with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate viva / oral defence; needs regular support
- **Conceptual Understanding**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates conceptual understanding
  - *Level 2 (Progressing)*: Demonstrates conceptual understanding with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate conceptual understanding; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support

**Anticipated Common Student Errors**:
- Naming the rivers but not the civilisations.
- Choosing a civilisation and giving no water method.

---

## Concept 2.3: Water threats and decline
- **Concept ID (`lms_concept.id`)**: `46126`
- **Topic Reference**: Topic #26314 (How water shaped civilisations)
- **Concept Type**: `Concept`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Important`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Rivers also brought threats: unpredictable floods that damaged settlements, salt build-up in the soil from over-irrigation in Mesopotamia, and droughts that led to hunger and migration. Civilisations that failed to maintain their water systems often declined.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"while rivers provided life, they also posed threats"*
- **[Textbook]**: *"also flooded unpredictably, damaging settlements"*
- **[Textbook]**: *"over-irrigation caused salt buildup in the soil, reducing crop productivity"*
- **[Textbook]**: *"dried up led to hunger and migration"*
- **[Textbook]**: *"When civilisations failed to maintain their water systems, they often declined"*
- **[Textbook]**: *"while rivers provided life, they also posed threats"*
- **[Inferred]**: *"Students think rivers gave ancient people only benefits and no problems."*
- **[Inferred]**: *"Students think the more water farmers put on their fields, the better the crops grow."*
- **[Inferred]**: *"Students think ancient civilisations ended only because enemies attacked them."*
- **[Textbook]**: *"while rivers provided life, they also posed threats"*

### 3. Knowledge Statements & Key Terms
- **Rivers also posed threats**: While rivers provided life, they also posed threats to ancient people.
- **Unpredictable floods damaged settlements**: The Yellow River also flooded unpredictably, damaging settlements.
- **Over-irrigation caused salt buildup**: In Mesopotamia, over-irrigation made salt build up in the soil, which reduced crop productivity.
- **Droughts led to hunger and migration**: When rivers dried up, droughts led to hunger and migration.
- **Poorly kept water systems led to decline**: Civilisations that failed to maintain their water systems often declined.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name three threats that rivers brought to ancient people.
- [Ability] Explain how over-irrigation harmed farming in Mesopotamia.
- [Ability] Relate the failure to maintain water systems to the decline of civilisations.

**Demonstrable Outcomes:**
- The student names floods, salty soil and droughts as threats that rivers brought.
- The student explains why a civilisation that did not look after its water systems could decline.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Apply** | Coverage score: 20% |
| Bloom's Taxonomy | **Analyze** | Coverage score: 40% |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |
| Depth of Knowledge (DOK) | **Level 3** | Strategic Thinking |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Rivers only brought good things"
  - *Observed Flawed Statement*: "Students think rivers gave ancient people only benefits and no problems."
  - *Instructional Correction*: Rivers also brought unpredictable floods, salty soil from over-irrigation, and droughts when rivers dried up.
- **Misconception**: "More irrigation is always better for crops"
  - *Observed Flawed Statement*: "Students think the more water farmers put on their fields, the better the crops grow."
  - *Instructional Correction*: In Mesopotamia, over-irrigation made salt build up in the soil and reduced crop productivity.
- **Misconception**: "Civilisations fell only because of enemies"
  - *Observed Flawed Statement*: "Students think ancient civilisations ended only because enemies attacked them."
  - *Instructional Correction*: The book says civilisations that failed to maintain their water systems often declined, so poor care of water could bring a civilisation down.

### 7. Real-World Applications & Daily Context
- **Environment**: Careful farmers today water their fields only as much as the crops need, because too much irrigation can make the soil salty.
- **Society**: Places hit by drought can face hunger and people leaving to find water, as happened to ancient river civilisations.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Scenario Based Teaching
  - *Why Effective*: A short story about a river city whose canals were never cleaned lets learners trace cause and effect from neglected water systems to hunger and people leaving.
  - *Characteristics*: Story and cause-effect chain, Links the past to today
- **Strategy**: Activity Based Teaching
  - *Why Effective*: Ordering cause-and-effect cards, such as 'river dries up', 'crops fail', 'hunger' and 'people move away', shows how one water problem leads to the next.
  - *Characteristics*: Sequencing cards, A visible chain of causes

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Water threats and decline` ──[depends_on]──> `River valley civilisations`
- `Water threats and decline` ──[related_to]──> `Learning from ancient practices`

**Prerequisites:**
- **Floods and droughts are too much and too little water** (Knowledge, Necessity: Recommended)
- **Crops need the right amount of water** (Knowledge, Necessity: Recommended)
- **Thinking about cause and effect** (Skill, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 2.3.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: What happened in Mesopotamia when farmers used too much water to irrigate their fields?

**Options**:
- **(A)** Crops grew better every year.
  - *Rationale*: A learner may think that more water always helps crops.
  - *Misconception Tested*: Students think the more water farmers put on their fields, the better the crops grow.
- **(B)** Salt built up in the soil and crops grew less. **[CORRECT ANSWER]**
  - *Rationale*: The book says over-irrigation caused salt buildup in the soil, reducing crop productivity.
- **(C)** The river became wider and slower.
  - *Rationale*: The book does not say this; it describes a problem in the soil.
- **(D)** The fields were flooded and houses were damaged.
  - *Rationale*: A learner may mix this up with the flooding of the Yellow River, which is a different problem.

**Anticipated Common Student Errors**:
- Choosing A because plants need water.

#### Question 2.3.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: What often happened to a civilisation that failed to maintain its water systems?

**Options**:
- **(A)** It grew stronger, because rivers only bring good things.
  - *Rationale*: A learner may forget the threats that rivers also brought.
  - *Misconception Tested*: Students think rivers gave ancient people only benefits and no problems.
- **(B)** Nothing changed, because rivers never dry up.
  - *Rationale*: The book says droughts happened when rivers dried up.
- **(C)** It often declined. **[CORRECT ANSWER]**
  - *Rationale*: The book says civilisations that failed to maintain their water systems often declined.
- **(D)** It fell only because enemies attacked it.
  - *Rationale*: A learner may link the end of a civilisation only with wars.
  - *Misconception Tested*: Students think ancient civilisations ended only because enemies attacked them.

**Anticipated Common Student Errors**:
- Choosing D because history stories talk about wars.

#### Question 2.3.3 (Short Answer · Difficulty: Medium · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Name two threats that rivers brought to people in ancient times.

**Acceptable Marking Points**:
- Unpredictable floods that damaged settlements.
  - *Alternative Acceptable Phrasings*: Floods on the Yellow River
- Salt build-up in the soil from over-irrigation (Mesopotamia).
  - *Alternative Acceptable Phrasings*: Too much irrigation made the soil salty
- Droughts when rivers dried up, leading to hunger and migration.
  - *Alternative Acceptable Phrasings*: Rivers dried up and people went hungry or moved away

**Anticipated Common Student Errors**:
- Naming a benefit of rivers, such as fresh water.
- Naming the same threat twice.

#### Question 2.3.4 (Competency Based Question · Difficulty: Hard · DOK 3 · Bloom: Analyze)
**Prompt / Stem**: A river city had canals that carried water to its farms. Over many years the people stopped cleaning and mending the canals. Fields became dry and people began to leave. Using the book's ideas, explain what may have happened to the city and why.

**Indicative Content (Expected Key Points)**:
- Water systems that are not maintained stop working, so the farms lose their water.
- Dry fields and droughts lead to hunger and migration.
- Civilisations that failed to maintain their water systems often declined.
- Even advanced societies are vulnerable to water mismanagement.

**Threshold Conditions for High Score**: For the highest levels, the answer must connect the failed canals to hunger or migration and then to decline.

**Anticipated Common Student Errors**:
- Blaming only enemies or bad luck.
- Saying the city grew stronger.

#### Question 2.3.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Make cause-and-effect cards such as 'the river dries up', 'crops fail', 'people go hungry' and 'people move away'. Put them in order and add arrows. Then make a second chain that starts with 'too much irrigation'. Explain each chain to a partner.

**Evaluation Rubric Criteria**:
- **Conceptual Understanding**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates conceptual understanding
  - *Level 2 (Progressing)*: Demonstrates conceptual understanding with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate conceptual understanding; needs regular support
- **Analysis & Interpretation**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates analysis & interpretation
  - *Level 2 (Progressing)*: Demonstrates analysis & interpretation with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate analysis & interpretation; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support

**Anticipated Common Student Errors**:
- Putting the cards in the wrong order.
- Making arrows without saying why one thing causes the next.

---

# Topic 3: Water challenges today
- **Topic Master ID**: `26315`
- **Sort Order**: 3
- **Description**: Learners look at today's water problems: uneven sharing, pollution, overuse, melting glaciers and people without clean water or sanitation. They meet the United Nations goal for clean water and sanitation.
- **Estimated Duration**: 25 minutes

## Concept 3.1: Water-rich and water-poor countries
- **Concept ID (`lms_concept.id`)**: `46127`
- **Topic Reference**: Topic #26315 (Water challenges today)
- **Concept Type**: `Fact`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Important`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Water is unevenly shared across the globe. Countries like Canada, Brazil and Russia are rich in fresh water, while North Africa, the Middle East and parts of India struggle with water scarcity.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"Countries like Canada, Brazil, and Russia are rich in freshwater resources"*
- **[Textbook]**: *"North Africa, the Middle East, and parts of India struggle with water scarcity"*
- **[Textbook]**: *"Discuss how these factors affect water availability"*
- **[Textbook]**: *"Countries like Canada, Brazil, and Russia are rich in freshwater resources"*
- **[Inferred]**: *"Students think a country that is rich in fresh water never has any water problems."*
- **[Inferred]**: *"Students think a big country always has more water than a small one."*
- **[Inferred]**: *"Students think every part of India has plenty of water because India has big rivers such as the Ganges."*
- **[Textbook]**: *"Countries like Canada, Brazil, and Russia are rich in freshwater resources"*

### 3. Knowledge Statements & Key Terms
- **Canada, Brazil and Russia are rich in fresh water**: Canada, Brazil and Russia are rich in fresh water resources.
- **North Africa, the Middle East and parts of India face scarcity**: North Africa, the Middle East and parts of India struggle with water scarcity.
- **Rainfall, water sources and population affect water**: Rainfall, water sources and population are factors that affect how much water a place has.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name countries that are rich in fresh water and regions that struggle with water scarcity.
- [Ability] Compare one water-rich and one water-poor country using a world map and simple data.
- [Ability] Explain how rainfall, water sources and population affect water availability.

**Demonstrable Outcomes:**
- The student names two water-rich countries and two water-scarce regions from the book.
- The student compares a water-rich and a water-poor country using rainfall, water sources and population.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 30% |
| Bloom's Taxonomy | **Understand** | Coverage score: 30% |
| Bloom's Taxonomy | **Analyze** | Coverage score: 40% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Water-rich countries never have water problems"
  - *Observed Flawed Statement*: "Students think a country that is rich in fresh water never has any water problems."
  - *Instructional Correction*: Being rich in fresh water means having plenty of it, but even such countries must look after their water, because water supplies everywhere can be threatened.
- **Misconception**: "A big country always has more water"
  - *Observed Flawed Statement*: "Students think a big country always has more water than a small one."
  - *Instructional Correction*: Rainfall, water sources and population all affect how much water a place has, not just its size.
- **Misconception**: "All of India has plenty of water"
  - *Observed Flawed Statement*: "Students think every part of India has plenty of water because India has big rivers such as the Ganges."
  - *Instructional Correction*: The book says parts of India struggle with water scarcity, so water is not equally available everywhere, even inside one country.

### 7. Real-World Applications & Daily Context
- **Society**: Countries that are short of water depend on careful planning and on trade to meet their needs.
- **Research**: Geographers use rainfall and population facts to find which regions may face water shortages.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Inquiry Based Teaching
  - *Why Effective*: Comparing two countries on a map with a data card lets learners find out for themselves what makes one water-rich and the other water-poor.
  - *Characteristics*: Using data, Comparing two cases
- **Strategy**: Activity Based Teaching
  - *Why Effective*: A sorting game with country cards, water-rich on one side and water-scarce on the other, builds the facts about the named places quickly.
  - *Characteristics*: Sorting cards, Quick recall of named places

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Water-rich and water-poor countries` ──[extends]──> `Uneven distribution of water`
- `Water-rich and water-poor countries` ──[related_to]──> `Causes of water scarcity`

**Prerequisites:**
- **Reading a world map** (Skill, Necessity: Recommended)
- **Countries and continents** (Knowledge, Necessity: Recommended)
- **Rain is a source of fresh water** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 3.1.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Which group of countries does the book describe as rich in fresh water?

**Options**:
- **(A)** Canada, Brazil and Russia **[CORRECT ANSWER]**
  - *Rationale*: The book names Canada, Brazil and Russia as rich in freshwater resources.
- **(B)** North Africa and the Middle East
  - *Rationale*: The book says these regions struggle with water scarcity.
- **(C)** India, because of the Ganges
  - *Rationale*: A learner may think India has plenty of water everywhere; the book says parts of India struggle with scarcity.
  - *Misconception Tested*: Students think every part of India has plenty of water because India has big rivers such as the Ganges.
- **(D)** Only the biggest countries
  - *Rationale*: A learner may think size decides how much water a country has.
  - *Misconception Tested*: Students think a big country always has more water than a small one.

**Anticipated Common Student Errors**:
- Choosing C because India has famous rivers.

#### Question 3.1.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Which statement is true, according to the book?

**Options**:
- **(A)** Big rivers bring plenty of water to every part of India.
  - *Rationale*: A learner may link India's big rivers with plenty of water everywhere.
  - *Misconception Tested*: Students think every part of India has plenty of water because India has big rivers such as the Ganges.
- **(B)** Countries rich in fresh water never have any water problems.
  - *Rationale*: A learner may think 'rich' means no problems; the book says water supplies are threatened in many places.
  - *Misconception Tested*: Students think a country that is rich in fresh water never has any water problems.
- **(C)** A country's size decides how much water it has.
  - *Rationale*: A learner may think a bigger country must have more water.
  - *Misconception Tested*: Students think a big country always has more water than a small one.
- **(D)** Parts of India struggle with water scarcity. **[CORRECT ANSWER]**
  - *Rationale*: The book names North Africa, the Middle East and parts of India as struggling with water scarcity.

**Anticipated Common Student Errors**:
- Choosing A because the Ganges is famous.

#### Question 3.1.3 (Short Answer · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Name two countries that are rich in fresh water and two regions that struggle with water scarcity.

**Acceptable Marking Points**:
- Water-rich: any two of Canada, Brazil and Russia.
  - *Alternative Acceptable Phrasings*: Canada and Brazil; Brazil and Russia; Canada and Russia
- Water scarcity: any two of North Africa, the Middle East and parts of India.
  - *Alternative Acceptable Phrasings*: North Africa and the Middle East; The Middle East and parts of India; North Africa and parts of India

**Anticipated Common Student Errors**:
- Swapping the two lists.
- Naming only one of each.

#### Question 3.1.4 (Competency Based Question · Difficulty: Hard · DOK 3 · Bloom: Analyze)
**Prompt / Stem**: Look at two countries on a map. Country X has heavy rainfall, many rivers and lakes, and few people. Country Y has little rain, few water sources and many people. Which country is more likely to be water-rich? Use rainfall, water sources and population to explain.

**Indicative Content (Expected Key Points)**:
- Country X is more likely to be water-rich.
- Heavy rainfall and many rivers and lakes mean plenty of water sources.
- Few people share the water in Country X, while many people in Country Y share very little.
- Rainfall, water sources and population all affect water availability.

**Threshold Conditions for High Score**: For the highest levels, the answer must use all three factors.

**Anticipated Common Student Errors**:
- Choosing Country Y because it has more people.
- Using only one factor.

#### Question 3.1.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Choose one water-rich country and one water-poor region from the book and find them on a world map. With the data card from your teacher, compare their rainfall, water sources and population, and tell a partner why one has more water.

**Evaluation Rubric Criteria**:
- **Data Handling & Accuracy**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates data handling & accuracy
  - *Level 2 (Progressing)*: Demonstrates data handling & accuracy with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate data handling & accuracy; needs regular support
- **Analysis & Interpretation**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates analysis & interpretation
  - *Level 2 (Progressing)*: Demonstrates analysis & interpretation with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate analysis & interpretation; needs regular support
- **Presentation & Communication**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates presentation & communication
  - *Level 2 (Progressing)*: Demonstrates presentation & communication with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate presentation & communication; needs regular support

**Anticipated Common Student Errors**:
- Comparing rainfall with population.
- Naming the places but giving no reason for the difference.

---

## Concept 3.2: Causes of water scarcity
- **Concept ID (`lms_concept.id`)**: `46128`
- **Topic Reference**: Topic #26315 (Water challenges today)
- **Concept Type**: `Concept`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Water shortages are worsened by pollution, overuse and climate change. For example, groundwater is being pumped faster than it can be refilled, so wells run dry, and glaciers that store fresh water are melting due to global warming.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"These shortages are worsened by pollution, overuse, and climate change"*
- **[Textbook]**: *"pumped faster than it can be naturally refilled, causing wells to run dry"*
- **[Textbook]**: *"Glaciers, which store vast amounts of fresh water, are melting due to global warming"*
- **[Textbook]**: *"These shortages are worsened by pollution, overuse, and climate change"*
- **[Inferred]**: *"Students think water shortages happen only when there is not enough rain."*
- **[Inferred]**: *"Students think water under the ground refills straight away, so wells can never run dry."*
- **[Inferred]**: *"Students think melting glaciers are good news because they add water for people."*
- **[Textbook]**: *"These shortages are worsened by pollution, overuse, and climate change"*

### 3. Knowledge Statements & Key Terms
- **Pollution, overuse and climate change worsen shortages**: Water shortages are made worse by pollution, overuse and climate change.
- **Groundwater is pumped faster than it refills**: Groundwater is being pumped out faster than nature can refill it, so wells run dry.
- **Melting glaciers shrink stores of fresh water**: Glaciers store vast amounts of fresh water and are melting because of global warming.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name three things that make water shortages worse.
- [Ability] Explain why wells run dry when groundwater is pumped out too fast.
- [Knowledge] Describe how melting glaciers affect the stores of fresh water.

**Demonstrable Outcomes:**
- The student names pollution, overuse and climate change as causes that worsen water shortages.
- The student explains why a well can run dry when too much groundwater is pumped out.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Apply** | Coverage score: 30% |
| Bloom's Taxonomy | **Analyze** | Coverage score: 30% |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |
| Depth of Knowledge (DOK) | **Level 3** | Strategic Thinking |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Only too little rain causes water shortages"
  - *Observed Flawed Statement*: "Students think water shortages happen only when there is not enough rain."
  - *Instructional Correction*: Shortages are also made worse by pollution, overuse and climate change, which are linked to what people do.
- **Misconception**: "Groundwater never runs out"
  - *Observed Flawed Statement*: "Students think water under the ground refills straight away, so wells can never run dry."
  - *Instructional Correction*: Groundwater is being pumped faster than it can be naturally refilled, and this makes wells run dry.
- **Misconception**: "Melting glaciers give us extra water for ever"
  - *Observed Flawed Statement*: "Students think melting glaciers are good news because they add water for people."
  - *Instructional Correction*: Glaciers store vast amounts of fresh water. When they melt because of global warming, the store gets smaller and much of the water flows away to the sea.

### 7. Real-World Applications & Daily Context
- **Daily Life**: A village well that gives less water every year warns the people that too much is being pumped out.
- **Environment**: Towns in mountain regions that depend on glacier water plan ahead as the glaciers shrink.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Experiential Based Teaching
  - *Why Effective*: A sand-and-water tray model of groundwater lets learners take water out faster than they add it and watch the level in the well fall.
  - *Characteristics*: Model of groundwater, Seeing the cause at work
- **Strategy**: Inquiry Based Teaching
  - *Why Effective*: Asking 'What could make a well that gave water last year give none this year?' leads learners to find the causes for themselves.
  - *Characteristics*: Open question, Finding causes

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Causes of water scarcity` ──[related_to]──> `Water-rich and water-poor countries`
- `Causes of water scarcity` ──[related_to]──> `Clean water access and SDG 6`

**Prerequisites:**
- **Groundwater is water under the ground** (Knowledge, Necessity: Recommended)
- **Wells are dug to reach water** (Knowledge, Necessity: Recommended)
- **Heat from the sun melts ice** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 3.2.1 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Why do some wells run dry?

**Options**:
- **(A)** Rain always fills them up again at once.
  - *Rationale*: A learner may think underground water is always topped up, so a dry well seems impossible.
  - *Misconception Tested*: Students think water under the ground refills straight away, so wells can never run dry.
- **(B)** The water under the ground has turned to salt.
  - *Rationale*: The book does not say this; it gives a different reason.
- **(C)** Wells are only meant to give water for a short time.
  - *Rationale*: The book does not say this; a well can last if its water is not overused.
- **(D)** Groundwater is pumped out faster than it is naturally refilled. **[CORRECT ANSWER]**
  - *Rationale*: The book says groundwater is being pumped faster than it can be naturally refilled, causing wells to run dry.

**Anticipated Common Student Errors**:
- Choosing A because the underground water cannot be seen.

#### Question 3.2.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Glaciers store vast amounts of fresh water. What is happening to many glaciers today?

**Options**:
- **(A)** They are growing bigger, so there will be more fresh water.
  - *Rationale*: The book says glaciers are melting, not growing.
- **(B)** They are melting, which gives people extra fresh water for ever.
  - *Rationale*: A learner may think melting ice only adds water for people to use.
  - *Misconception Tested*: Students think melting glaciers are good news because they add water for people.
- **(C)** They are melting because of global warming, so a store of fresh water is shrinking. **[CORRECT ANSWER]**
  - *Rationale*: The book says glaciers, which store vast amounts of fresh water, are melting due to global warming.
- **(D)** They are staying the same because ice never changes.
  - *Rationale*: A learner may think frozen things stay frozen; the book says glaciers are melting.

**Anticipated Common Student Errors**:
- Choosing B because melting makes water.

#### Question 3.2.3 (Short Answer · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: Name three things that make water shortages worse. Then say what happens to wells when groundwater is pumped out too fast.

**Acceptable Marking Points**:
- Pollution, overuse and climate change (all three named).
  - *Alternative Acceptable Phrasings*: Dirty water, using too much water, and a changing climate
- Wells run dry when groundwater is pumped out faster than it is refilled.
  - *Alternative Acceptable Phrasings*: The wells have no water left.

**Anticipated Common Student Errors**:
- Naming only one or two causes.
- Saying wells fill up.

#### Question 3.2.4 (Competency Based Question · Difficulty: Hard · DOK 3 · Bloom: Analyze)
**Prompt / Stem**: Rani's village depends on one well. Every year the well gives less water. Many new pumps have been fitted on farms nearby. A neighbour says, 'It is just bad luck with the rain.' Explain what else may be happening.

**Indicative Content (Expected Key Points)**:
- Groundwater may be pumped out faster than it can be naturally refilled.
- When this happens, wells run dry; this is a kind of overuse.
- Water shortages are worsened by pollution, overuse and climate change, not only by rain.
- So the problem may come from how people use water as well as from the weather.

**Threshold Conditions for High Score**: For the highest levels, the answer must link the new pumps to groundwater being used faster than it refills.

**Anticipated Common Student Errors**:
- Agreeing that it is only the rain.
- Not mentioning the pumps.

#### Question 3.2.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Half fill a clear plastic box with sand. Pour in water slowly until the sand is wet all the way down. Press a small cup into the sand to make a 'well'. Use a spoon to take water out of the well quickly and pour it back slowly with a dropper. Watch the water level in the well and tell what it shows about real wells.

**Evaluation Rubric Criteria**:
- **Scientific Method / Process**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates scientific method / process
  - *Level 2 (Progressing)*: Demonstrates scientific method / process with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate scientific method / process; needs regular support
- **Data Handling & Accuracy**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates data handling & accuracy
  - *Level 2 (Progressing)*: Demonstrates data handling & accuracy with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate data handling & accuracy; needs regular support
- **Analysis & Interpretation**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates analysis & interpretation
  - *Level 2 (Progressing)*: Demonstrates analysis & interpretation with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate analysis & interpretation; needs regular support

**Anticipated Common Student Errors**:
- Adding water as fast as taking it out, so nothing changes.
- Not noting the water level before and after.

---

## Concept 3.3: Clean water access and SDG 6
- **Concept ID (`lms_concept.id`)**: `46129`
- **Topic Reference**: Topic #26315 (Water challenges today)
- **Concept Type**: `Concept`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Many rivers and lakes are contaminated by chemicals and waste, and billions of people lack clean water and safe sanitation, which can lead to disease and inequality. The United Nations created Sustainable Development Goal 6 to ensure clean water and sanitation for all by 2030.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"Many rivers and lakes are contaminated by chemicals and waste"*
- **[Textbook]**: *"Billions of people still lack access to clean water and safe sanitation"*
- **[Textbook]**: *"which can lead to disease and social inequality"*
- **[Textbook]**: *"the United Nations created Sustainable Development Goal 6"*
- **[Textbook]**: *"ensuring access to clean water and sanitation for all by 2030"*
- **[Textbook]**: *"Many rivers and lakes are contaminated by chemicals and waste"*
- **[Inferred]**: *"Students think water that looks clear is always safe to drink."*
- **[Inferred]**: *"Students think everyone in the world already has clean water and a safe toilet."*
- **[Inferred]**: *"Students think sanitation just means having water to wash with."*
- **[Textbook]**: *"Many rivers and lakes are contaminated by chemicals and waste"*

### 3. Knowledge Statements & Key Terms
- **Contaminated rivers and lakes are unsafe to drink**: Many rivers and lakes are contaminated by chemicals and waste, which makes the water unsafe to drink.
- **Billions lack clean water and safe sanitation**: Billions of people still lack access to clean water and safe sanitation.
- **Lack of clean water can lead to disease and inequality**: A lack of clean water and safe sanitation can lead to disease and social inequality.
- **The UN created Sustainable Development Goal 6**: The United Nations created Sustainable Development Goal 6 to address these water issues.
- **SDG 6 aims at clean water and sanitation for all**: SDG 6 aims to ensure access to clean water and sanitation for all by 2030.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Ability] Explain why contaminated rivers and lakes are unsafe to drink from.
- [Knowledge] State what can happen when people lack clean water and safe sanitation.
- [Knowledge] Describe in simple words what Sustainable Development Goal 6 aims to do.

**Demonstrable Outcomes:**
- The student says that chemicals and waste can make river and lake water unsafe to drink.
- The student explains how a lack of clean water can lead to disease and unfairness, and tells what SDG 6 aims for.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Remember** | Coverage score: 30% |
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Analyze** | Coverage score: 30% |
| Depth of Knowledge (DOK) | **Level 1** | Recall and Reproduction |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Clear-looking water is always safe"
  - *Observed Flawed Statement*: "Students think water that looks clear is always safe to drink."
  - *Instructional Correction*: Water that has chemicals or waste in it can be unsafe to drink even if it looks clear.
- **Misconception**: "Everyone already has clean water"
  - *Observed Flawed Statement*: "Students think everyone in the world already has clean water and a safe toilet."
  - *Instructional Correction*: Billions of people still lack access to clean water and safe sanitation.
- **Misconception**: "Sanitation only means water for washing"
  - *Observed Flawed Statement*: "Students think sanitation just means having water to wash with."
  - *Instructional Correction*: Safe sanitation also means safe toilets and ways to take waste away so that it does not pollute water.

### 7. Real-World Applications & Daily Context
- **Daily Life**: Towns treat and test the water in their pipes so that it is safe to drink.
- **Society**: Schools with clean water and safe toilets help children stay healthy and keep coming to school.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Scenario Based Teaching
  - *Why Effective*: A short story about a village whose river is polluted lets learners find the problems for themselves and see what Goal 6 asks for.
  - *Characteristics*: Story-based, Links the problem to the goal
- **Strategy**: Inquiry Based Teaching
  - *Why Effective*: Asking 'Can you tell by looking whether water is safe?' leads learners to see that clear water may still be unsafe.
  - *Characteristics*: Surprising question, Challenges 'clear means clean'

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Clean water access and SDG 6` ──[related_to]──> `Causes of water scarcity`
- `Clean water access and SDG 6` ──[related_to]──> `Using water wisely and supporting policies`

**Prerequisites:**
- **Dirty water can make people ill** (Knowledge, Necessity: Recommended)
- **Safe toilets and clean surroundings keep people healthy** (Knowledge, Necessity: Helpful)
- **What the United Nations is** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 3.3.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: What is Sustainable Development Goal 6 about?

**Options**:
- **(A)** Protecting the animals that live in the oceans
  - *Rationale*: A learner may mix up SDG 6 with another global goal about water and life.
- **(B)** Clean water and sanitation for all by 2030 **[CORRECT ANSWER]**
  - *Rationale*: The book says SDG 6 ensures access to clean water and sanitation for all by 2030.
- **(C)** Making sure everyone has enough food
  - *Rationale*: A learner may mix up SDG 6 with a goal about food.
- **(D)** Stopping all the glaciers from melting
  - *Rationale*: A learner may link water goals with glaciers; the book says SDG 6 is about clean water and sanitation.

**Anticipated Common Student Errors**:
- Mixing SDG 6 with another global goal.

#### Question 3.3.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Which statement about clean water and sanitation is correct?

**Options**:
- **(A)** Water with chemicals or waste in it can be unsafe to drink, even if it looks clear. **[CORRECT ANSWER]**
  - *Rationale*: The book says contaminated rivers and lakes make water unsafe to drink; looking clear does not make it safe.
- **(B)** If water looks clear, it is always safe to drink.
  - *Rationale*: A learner may judge water only by how it looks.
  - *Misconception Tested*: Students think water that looks clear is always safe to drink.
- **(C)** Everyone in the world already has clean water and a safe toilet.
  - *Rationale*: A learner may think everyone has what they have; the book says billions still lack it.
  - *Misconception Tested*: Students think everyone in the world already has clean water and a safe toilet.
- **(D)** Sanitation only means having water to wash with.
  - *Rationale*: A learner may think sanitation is only about washing; it also means safe toilets and taking waste away.
  - *Misconception Tested*: Students think sanitation just means having water to wash with.

**Anticipated Common Student Errors**:
- Choosing B because dirt is easy to see but chemicals are not.

#### Question 3.3.3 (Short Answer · Difficulty: Medium · DOK 1 · Bloom: Understand)
**Prompt / Stem**: The book says billions of people lack clean water and safe sanitation. Name two things this can lead to.

**Acceptable Marking Points**:
- Disease.
  - *Alternative Acceptable Phrasings*: People can become ill.
- Social inequality.
  - *Alternative Acceptable Phrasings*: Unfairness between people; Some people are left behind

**Anticipated Common Student Errors**:
- Giving 'thirst' only.
- Naming one thing twice.

#### Question 3.3.4 (Competency Based Question · Difficulty: Medium · DOK 3 · Bloom: Apply)
**Prompt / Stem**: A factory near a town lets chemicals and waste run into the river. Many families downstream drink the river water. What problems may happen? What does SDG 6 want the world to do about problems like this?

**Indicative Content (Expected Key Points)**:
- The river is contaminated by chemicals and waste, so the water is unsafe to drink.
- People who drink it may fall ill with disease.
- Families with no other source of clean water are treated unfairly compared with others (social inequality).
- SDG 6 aims to ensure access to clean water and sanitation for all by 2030.

**Threshold Conditions for High Score**: For the highest levels, the answer must name a problem for people and say what SDG 6 aims for.

**Anticipated Common Student Errors**:
- Saying the water is safe because the river keeps flowing.
- Not mentioning SDG 6.

#### Question 3.3.5 (Viva · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Tell your teacher: What is SDG 6? Who created it? What does it want for all people, and by when? Why is it needed?

**Evaluation Rubric Criteria**:
- **Viva / Oral Defence**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates viva / oral defence
  - *Level 2 (Progressing)*: Demonstrates viva / oral defence with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate viva / oral defence; needs regular support
- **Conceptual Understanding**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates conceptual understanding
  - *Level 2 (Progressing)*: Demonstrates conceptual understanding with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate conceptual understanding; needs regular support
- **Application & Relevance**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates application & relevance
  - *Level 2 (Progressing)*: Demonstrates application & relevance with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate application & relevance; needs regular support

**Anticipated Common Student Errors**:
- Forgetting the year 2030.
- Saying why it is needed without mentioning billions of people.

---

# Topic 4: Learning from the past, acting for the future
- **Topic Master ID**: `26316`
- **Sort Order**: 4
- **Description**: Learners connect lessons from ancient water management to action today, such as rainwater harvesting and smart irrigation. They learn how individuals and larger policies can protect water for the future.
- **Estimated Duration**: 20 minutes

## Concept 4.1: Learning from ancient practices
- **Concept ID (`lms_concept.id`)**: `46130`
- **Topic Reference**: Topic #26316 (Learning from the past, acting for the future)
- **Concept Type**: `Principle`
- **Difficulty Level**: `Medium`
- **Importance Rating**: `Core`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> History shows that societies that protected their water sources thrived, while those that ignored water problems struggled or disappeared. We should treat water as a shared, limited resource and learn from ancient practices such as rainwater harvesting and smart irrigation.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"water management can determine the success or collapse of civilisations"*
- **[Textbook]**: *"Those that protected their water sources thrived"*
- **[Textbook]**: *"others that ignored water problems struggled or disappeared"*
- **[Textbook]**: *"We must treat water as a shared, limited resource"*
- **[Textbook]**: *"learn from ancient practices such as rainwater harvesting and smart irrigation"*
- **[Textbook]**: *"water management can determine the success or collapse of civilisations"*
- **[Inferred]**: *"Students think ideas from ancient times are old-fashioned and cannot help with water problems today."*
- **[Inferred]**: *"Students think water cannot run out because rain falls again and again."*
- **[Textbook]**: *"water management can determine the success or collapse of civilisations"*

### 3. Knowledge Statements & Key Terms
- **Water management can decide success or collapse**: Water management can decide whether a civilisation succeeds or collapses.
- **Protecting water sources helped societies thrive**: Societies that protected their water sources thrived.
- **Ignoring water problems led to struggle or loss**: Societies that ignored water problems struggled or disappeared.
- **Water is a shared, limited resource**: Water should be treated as a shared, limited resource.
- **We can learn from rainwater harvesting and smart irrigation**: We can learn from ancient practices such as rainwater harvesting and smart irrigation.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Ability] Explain what happened to societies that protected their water and to those that ignored water problems.
- [Knowledge] Name two ancient water practices that we can learn from today.
- [Ability] Justify why water should be treated as a shared, limited resource.

**Demonstrable Outcomes:**
- The student tells what happened to societies that protected their water and to those that ignored water problems.
- The student names rainwater harvesting and smart irrigation and says how one of them could help today.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Understand** | Coverage score: 40% |
| Bloom's Taxonomy | **Apply** | Coverage score: 30% |
| Bloom's Taxonomy | **Evaluate** | Coverage score: 30% |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |
| Depth of Knowledge (DOK) | **Level 3** | Strategic Thinking |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Ancient practices are too old to help today"
  - *Observed Flawed Statement*: "Students think ideas from ancient times are old-fashioned and cannot help with water problems today."
  - *Instructional Correction*: The book says we should learn from ancient practices such as rainwater harvesting and smart irrigation, which still help us save and use water well.
- **Misconception**: "Water never runs out because rain keeps coming"
  - *Observed Flawed Statement*: "Students think water cannot run out because rain falls again and again."
  - *Instructional Correction*: Water must be treated as a shared, limited resource, because only a small part of Earth's water is available for people to use and everyone has to share it.

### 7. Real-World Applications & Daily Context
- **Daily Life**: A home or school can collect rainwater from its roof in a tank or barrel and use it for plants and cleaning.
- **Technology**: Drip pipes that give water drop by drop at the roots of plants are a modern kind of smart irrigation.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Project Based Teaching
  - *Why Effective*: Designing a model roof that collects rainwater lets learners test an ancient idea and see how much water it can save.
  - *Characteristics*: Design and test, Real link to the school
- **Strategy**: Scenario Based Teaching
  - *Why Effective*: Telling the story of two societies, one that guarded its water and one that did not, lets learners see the lesson of history for themselves.
  - *Characteristics*: Compare two stories, Cause and effect

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Learning from ancient practices` ──[depends_on]──> `Water threats and decline`
- `Learning from ancient practices` ──[related_to]──> `Using water wisely and supporting policies`

**Prerequisites:**
- **Rain can be collected in containers** (Knowledge, Necessity: Recommended)
- **Watering crops is called irrigation** (Knowledge, Necessity: Recommended)
- **Learning lessons from history** (Skill, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 4.1.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Understand)
**Prompt / Stem**: What does history show about water management?

**Options**:
- **(A)** Only modern machines can manage water well.
  - *Rationale*: A learner may think old methods never worked and only new machines do.
  - *Misconception Tested*: Students think ideas from ancient times are old-fashioned and cannot help with water problems today.
- **(B)** Water never runs out, so planning is not needed.
  - *Rationale*: A learner may think rain keeps water endless; the book says water is a shared, limited resource.
  - *Misconception Tested*: Students think water cannot run out because rain falls again and again.
- **(C)** It can decide whether a civilisation thrives or collapses. **[CORRECT ANSWER]**
  - *Rationale*: The book says water management can determine the success or collapse of civilisations.
- **(D)** It matters little for a civilisation's survival.
  - *Rationale*: The book says the opposite: water management can decide success or collapse.

**Anticipated Common Student Errors**:
- Choosing D without thinking about what happened to past societies.

#### Question 4.1.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: Which ancient practices does the book say we can learn from today?

**Options**:
- **(A)** None, because ancient ideas are too old to help.
  - *Rationale*: A learner may think only new ideas can solve water problems.
  - *Misconception Tested*: Students think ideas from ancient times are old-fashioned and cannot help with water problems today.
- **(B)** Pumping water from wells as fast as possible.
  - *Rationale*: The book says groundwater pumped too fast makes wells run dry, so this is not a practice to learn from.
- **(C)** Moving whole towns away from rivers.
  - *Rationale*: The book does not suggest this; it suggests looking after water.
- **(D)** Rainwater harvesting and smart irrigation. **[CORRECT ANSWER]**
  - *Rationale*: These are the two ancient practices named in the book.

**Anticipated Common Student Errors**:
- Choosing A because new machines seem better.

#### Question 4.1.3 (Short Answer · Difficulty: Easy · DOK 1 · Bloom: Remember)
**Prompt / Stem**: What happened to societies that protected their water sources? What happened to societies that ignored water problems?

**Acceptable Marking Points**:
- Societies that protected their water sources thrived.
  - *Alternative Acceptable Phrasings*: They did well and grew strong.
- Societies that ignored water problems struggled or disappeared.
  - *Alternative Acceptable Phrasings*: They had a hard time or came to an end.

**Anticipated Common Student Errors**:
- Swapping the two results.
- Giving only one half.

#### Question 4.1.4 (HOTS · Difficulty: Hard · DOK 3 · Bloom: Evaluate)
**Prompt / Stem**: The book says we must treat water as a shared, limited resource. Explain what this means, and give one reason from history to support it.

**Indicative Content (Expected Key Points)**:
- Shared means many people and places depend on the same water.
- Limited means there is only so much water to go round.
- History shows that societies that protected their water sources thrived.
- Societies that ignored water problems struggled or disappeared, so we should look after water.

**Threshold Conditions for High Score**: For the highest levels, the answer must explain both 'shared' and 'limited' and give a reason from history.

**Anticipated Common Student Errors**:
- Explaining only one of the two words.
- Giving a reason that is not from history.

#### Question 4.1.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Make a model roof from a tilted plastic sheet. Pour 'rain' from a watering can onto it so that the water runs into a container. Measure the water you collected and tell two ways it could be used.

**Evaluation Rubric Criteria**:
- **Scientific Method / Process**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates scientific method / process
  - *Level 2 (Progressing)*: Demonstrates scientific method / process with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate scientific method / process; needs regular support
- **Data Handling & Accuracy**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates data handling & accuracy
  - *Level 2 (Progressing)*: Demonstrates data handling & accuracy with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate data handling & accuracy; needs regular support
- **Application & Relevance**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates application & relevance
  - *Level 2 (Progressing)*: Demonstrates application & relevance with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate application & relevance; needs regular support

**Anticipated Common Student Errors**:
- Pouring a different amount of 'rain' each time.
- Not measuring the collected water.

---

## Concept 4.2: Using water wisely and supporting policies
- **Concept ID (`lms_concept.id`)**: `46131`
- **Topic Reference**: Topic #26316 (Learning from the past, acting for the future)
- **Concept Type**: `Concept`
- **Difficulty Level**: `Easy`
- **Importance Rating**: `Important`
- **Extraction Confidence**: `0.9`

### 1. Definition & Core Meaning
> Individuals can help by using water wisely, reducing waste and keeping water sources clean. On a larger scale, supporting policies and technologies that protect water and give access for all is essential.

### 2. Textbook Grounding Evidence
- **[Textbook]**: *"Individuals can contribute by using water wisely, reducing waste, and keeping water sources clean"*
- **[Textbook]**: *"supporting policies and technologies that protect water and provide access for all is essential"*
- **[Textbook]**: *"it remains a source of life, not conflict or crisis"*
- **[Textbook]**: *"We must act responsibly to secure water for future generations"*
- **[Textbook]**: *"Individuals can contribute by using water wisely, reducing waste, and keeping water sources clean"*
- **[Inferred]**: *"Students think looking after water is only the job of the government or of grown-ups in charge."*
- **[Inferred]**: *"Students think one child saving a little water makes no difference."*
- **[Inferred]**: *"Students think using water wisely means not using water at all, even for drinking."*
- **[Textbook]**: *"Individuals can contribute by using water wisely, reducing waste, and keeping water sources clean"*

### 3. Knowledge Statements & Key Terms
- **Individuals can use water wisely and keep it clean**: Individuals can contribute by using water wisely, reducing waste and keeping water sources clean.
- **Policies and technologies that protect water are essential**: On a larger scale, supporting policies and technologies that protect water and give access for all is essential.
- **Respecting water keeps it a source of life**: By respecting water and planning for the future, we can keep water a source of life, not conflict or crisis.
- **We must act responsibly for future generations**: We must act responsibly to secure water for future generations.

### 4. Learning Objectives & Outcomes
**Specific Objectives:**
- [Knowledge] Name three ways to use water wisely, reduce waste or keep water sources clean.
- [Ability] Explain why policies and technologies are also needed to protect water.
- [Skill] Plan one action that helps to secure water for future generations.

**Demonstrable Outcomes:**
- The student gives two ways to use water wisely or keep water clean at home or school.
- The student explains why both what people do each day and larger policies are needed to protect water.

### 5. Pedagogical Framework: Bloom's Taxonomy & DOK
| Domain | Level | Details / Score |
|---|---|---|
| Bloom's Taxonomy | **Understand** | Coverage score: 30% |
| Bloom's Taxonomy | **Apply** | Coverage score: 40% |
| Bloom's Taxonomy | **Evaluate** | Coverage score: 30% |
| Depth of Knowledge (DOK) | **Level 2** | Skills and Concepts |
| Depth of Knowledge (DOK) | **Level 3** | Strategic Thinking |

### 6. Student Misconceptions & Instructional Corrections
- **Misconception**: "Protecting water is only the government's job"
  - *Observed Flawed Statement*: "Students think looking after water is only the job of the government or of grown-ups in charge."
  - *Instructional Correction*: Individuals can contribute by using water wisely, reducing waste and keeping water sources clean, while larger policies and technologies also matter.
- **Misconception**: "One person's small actions do not matter"
  - *Observed Flawed Statement*: "Students think one child saving a little water makes no difference."
  - *Instructional Correction*: Many small actions add up, and the book says individuals can contribute; wise habits also help others to follow.
- **Misconception**: "Using water wisely means using none"
  - *Observed Flawed Statement*: "Students think using water wisely means not using water at all, even for drinking."
  - *Instructional Correction*: Using water wisely means using what we need and avoiding waste, such as turning off a running tap.

### 7. Real-World Applications & Daily Context
- **Daily Life**: Turning off the tap while brushing teeth and getting a leaking tap mended stop water from being wasted.
- **Society**: Governments make rules against dumping waste in rivers and help build tanks and pipes that bring water to people who have none nearby.

### 8. Recommended Pedagogy & Teaching Strategies
- **Strategy**: Project Based Teaching
  - *Why Effective*: A class 'water saver' project, in which learners track and cut water waste at school, shows that small actions add up.
  - *Characteristics*: Real action at school, Visible results
- **Strategy**: Experiential Based Teaching
  - *Why Effective*: Collecting the drips from a leaking tap in a cup lets learners measure the waste for themselves and see why saving matters.
  - *Characteristics*: Measuring waste, Learning by doing

### 9. Concept Network Relationships & Prerequisites
**Relational Edges:**
- `Using water wisely and supporting policies` ──[extends]──> `Learning from ancient practices`
- `Using water wisely and supporting policies` ──[related_to]──> `Clean water access and SDG 6`

**Prerequisites:**
- **Water is used every day at home and school** (Knowledge, Necessity: Recommended)
- **Rubbish and dirt can pollute water** (Knowledge, Necessity: Recommended)
- **Rules help people share and protect things** (Knowledge, Necessity: Helpful)

### 10. Assessment Items & Presentation Questions
#### Question 4.2.1 (MCQ · Difficulty: Easy · DOK 1 · Bloom: Apply)
**Prompt / Stem**: Which action shows using water wisely?

**Options**:
- **(A)** Turning off the tap while brushing teeth **[CORRECT ANSWER]**
  - *Rationale*: This reduces waste, which the book says individuals can do.
- **(B)** Leaving a hose running to keep the yard wet
  - *Rationale*: A learner may think more water is always better; this wastes water.
- **(C)** Throwing rubbish into a stream
  - *Rationale*: This does not keep water sources clean.
- **(D)** Using no water at all, even for drinking
  - *Rationale*: A learner may think wise use means going without; it means using what we need and avoiding waste.
  - *Misconception Tested*: Students think using water wisely means not using water at all, even for drinking.

**Anticipated Common Student Errors**:
- Choosing D because 'saving' sounds like 'going without'.

#### Question 4.2.2 (MCQ · Difficulty: Medium · DOK 2 · Bloom: Understand)
**Prompt / Stem**: A learner says, 'Water is the government's job, so I do not need to do anything.' What does the book say?

**Options**:
- **(A)** She is right: only the government can protect water.
  - *Rationale*: A learner may think big problems belong only to big groups.
  - *Misconception Tested*: Students think looking after water is only the job of the government or of grown-ups in charge.
- **(B)** Individuals can help by using water wisely and keeping water clean. **[CORRECT ANSWER]**
  - *Rationale*: The book says individuals can contribute by using water wisely, reducing waste and keeping water sources clean.
- **(C)** One person's actions are too small to matter.
  - *Rationale*: A learner may think a single tap is tiny; the book says individuals can contribute.
  - *Misconception Tested*: Students think one child saving a little water makes no difference.
- **(D)** Only big machines can protect water.
  - *Rationale*: The book names policies and technologies as important, but it also says individuals can help.

**Anticipated Common Student Errors**:
- Choosing A because governments run big water projects.

#### Question 4.2.3 (Short Answer · Difficulty: Easy · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Give two things you can do at home or school to use water wisely or to keep water clean.

**Acceptable Marking Points**:
- Use water wisely, for example use only what is needed.
  - *Alternative Acceptable Phrasings*: Turn off the tap when not using it.; Use a bucket instead of a running hose.
- Reduce waste, for example mend leaks.
  - *Alternative Acceptable Phrasings*: Do not leave water running.; Reuse water for plants.
- Keep water sources clean, for example do not throw rubbish in drains, ponds or rivers.
  - *Alternative Acceptable Phrasings*: Put waste in the bin.

**Anticipated Common Student Errors**:
- Giving two ways that are the same.
- Naming something only a government can do.

#### Question 4.2.4 (HOTS · Difficulty: Hard · DOK 3 · Bloom: Evaluate)
**Prompt / Stem**: A neighbour says, 'The town is making a new rule and a new machine to clean river water, so I do not need to do anything.' Explain how what people do each day and what towns and governments do can work together to keep water a source of life.

**Indicative Content (Expected Key Points)**:
- Individuals can use water wisely, reduce waste and keep water sources clean.
- On a larger scale, policies and technologies that protect water and give access for all are essential.
- Both are needed: rules and machines cannot work well if people waste or dirty water.
- Respecting water and planning for the future keeps it a source of life, not conflict or crisis.

**Threshold Conditions for High Score**: For the highest levels, the answer must show that both individual actions and policies or technologies are needed.

**Anticipated Common Student Errors**:
- Agreeing with the neighbour.
- Writing only about what the town should do.

#### Question 4.2.5 (Practical · Difficulty: Medium · DOK 2 · Bloom: Apply)
**Prompt / Stem**: Water-use diary: for one day, write down each time you use water at home or school (for example drinking, washing hands, watering plants). Mark one use where water could be saved or kept clean. Share your idea with the class and help make a class poster of the best ideas.

**Evaluation Rubric Criteria**:
- **Record / Documentation**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates record / documentation
  - *Level 2 (Progressing)*: Demonstrates record / documentation with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate record / documentation; needs regular support
- **Application & Relevance**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates application & relevance
  - *Level 2 (Progressing)*: Demonstrates application & relevance with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate application & relevance; needs regular support
- **Data Handling & Accuracy**
  - *Level 3 (Proficient)*: Consistently and independently demonstrates data handling & accuracy
  - *Level 2 (Progressing)*: Demonstrates data handling & accuracy with occasional support
  - *Level 1 (Beginner)*: Beginning to demonstrate data handling & accuracy; needs regular support

**Anticipated Common Student Errors**:
- Listing uses but not choosing one to improve.
- Choosing an idea that needs a government, not a learner.

---

## 5. Complete Extracted Textbook Text (`document_extractions.md_content`)

Below is the complete, unabridged ground-truth textbook content extracted from `document_extractions` (ID `858`), which serves as the factual basis for slide assertions:

```markdown
## Page 1


# CHAPTER 1 Global water resources: Distribution and historical impact What will we learn? 1. Water on Earth: A global view 2. How water shaped civilisations 3. Water challenges today 4. Learning from the past, acting for the future Q Concept videos 1. Let's map out global water distribution! 2. From Nile to Tigris: How rivers shaped the world's oldest cultures Morning circle videos 1. The water of life 2. Are we running out of clean water? 3. Why Egypt's lifeline is drying up! 4. Yangtze river facts! C Sustainable cities project videos 1. What is Sustainability 0 Supporting scheme videos


## Page 2




## 1.1 Water on Earth: A global view

 Water is one of the most essential substances on Earth, covering about 71% of the planet’s surface. However, the majority of it around 97% is salt water found in oceans and seas, making it unsuitable for direct human use. Only 3% of the Earth's water is fresh, and of that, nearly all is locked away in glaciers and ice caps. This means that less than 1% of Earth's water is available for people to use for drinking, farming, washing, and other daily needs.


![Figure](http://127.0.0.1:8001/api/assets/g4water_ch01/pass_auto/g4water_ch01/auto/images/79074ef9c9796e5dffc16836b2ad846634de18930062a77534f7991a0313cd8b.jpg)


Fresh water is found in rivers, lakes, and underground aquifers, but its distribution across the planet is unequal. Some regions, like those around large rivers and lakes have abundant water, while others, such as deserts or dry plains, face chronic shortages. Major water bodies include oceans like the Pacific and Atlantic, lakes such as Lake Baikal and Lake Victoria, and rivers like the Nile (the longest river in the world), Amazon, Ganges, and Yangtze. Frozen water is concentrated in Antarctica and Greenland and Iceland. The global distribution of water resources plays a crucial role in where people can live, how they grow food, and how societies develop. Key points 97% salt water, 3% fresh water. Less than 1% usable for human needs. Uneven distribution creates water-rich and water-scarce regions.


## Page 3


# A1. Heat up and cool down: Comparing land and water Observe how different surfaces soil, sand, grass, and water heat up and cool down at different speeds. Use thermometers to compare how their temperatures change under a lamp or in sunlight. Notice how sand and soil heat up and cool down quickly, while grass and water change temperature more slowly. This difference helps us understand how sea and land breezes occur and why coastal areas have milder weather than inland regions compared to places farther from the sea. Observation sheet: G4_WATER_EO_OBS_1 Student task card: G4_WATER_EO_STC_1

## 1.2 How water shaped civilisations

 Throughout history, civilisations have depended on water for survival and growth. Rivers were especially important because they offered fresh water, fertile soil from seasonal floods, and easy transportation. Early human settlements were often built near rivers, which allowed agriculture to flourish and communities to expand. Major ancient civilisations developed around rivers: Ancient Egypt by the Nile, Mesopotamia by the Tigris and Euphrates, the Indus Valley by the Indus River, and Ancient China along the Yellow River. These rivers provided drinking water, irrigation for crops, fish, and a means of trade and communication. For example, the Nile's annual floods enriched Egyptian soil, making farming productive and allowing Egypt to become a powerful and stable society. Similarly, Mesopotamia is known as the "Cradle of civilisation" because people used irrigation canals to farm even in a dry climate and developed early systems of writing and governance. In the Indus Valley, cities like Mohenjo-Daro show evidence of sophisticated water management with wells and drainage systems. In China, the Yellow River allowed early rice farming but also brought frequent floods, forcing people to build protective dams.


![Figure](http://127.0.0.1:8001/api/assets/g4water_ch01/pass_auto/g4water_ch01/auto/images/eb86c856d7cc262b2093fde7b02b19c71c30742cefe96589de99efc24c623f2a.jpg)


## Page 4


However, while rivers provided life, they also posed threats. The same Yellow River that enabled farming also flooded unpredictably, damaging settlements. In Mesopotamia, over-irrigation caused salt buildup in the soil, reducing crop productivity. Droughts—when rivers dried up led to hunger and migration. When civilisations failed to maintain their water systems, they often declined. The fall of these ancient powers reminds us that even the most advanced societies are vulnerable to water mismanagement. Key points Civilisations grew around rivers due to water availability. Rivers supported agriculture, trade, and daily life. Water management influenced success and survival.

## 1.3 Water challenges today

 Today, just as in the past, water remains unevenly shared across the globe. Countries like Canada, Brazil, and Russia are rich in freshwater resources, while regions such as North Africa, the Middle East, and parts of India struggle with water scarcity. These shortages are worsened by pollution, overuse, and climate change. Many rivers and lakes are contaminated by chemicals and waste, making the water unsafe to drink. Groundwater is being pumped faster than it can be naturally refilled, causing wells to run dry. Glaciers, which store vast amounts of fresh water, are melting due to global warming. Billions of people still lack access to clean water and safe sanitation, which can lead to disease and social inequality. To address these issues, the United Nations created Sustainable Development Goal 6: ensuring access to clean water and sanitation for all by 2030.


## Page 5


![Figure](http://127.0.0.1:8001/api/assets/g4water_ch01/pass_auto/g4water_ch01/auto/images/3c5a9501e02afc9d2cabce57e8a2cdf2f2c4814cfe47c51b5031ce31af279951.jpg)


# Key points Pollution, overuse, and climate change threaten water supplies. Billions lack clean water and sanitation. Global efforts, like SDG 6, aim to improve water access. B1. Mapping water abundance and scarcity Compare one water-rich and one water-poor country using a world map. Analyse the data given for each country, such as rainfall, water sources, and population. Discuss how these factors affect water availability. Understand why some places have plenty of water while others face shortages. Data sheet: G4_WATER_EO_DS_1 Student task card: G3_WATER_EO_STC_2

## 1.4 Learning from the past, acting for the future

 History clearly shows that water management can determine the success or collapse of civilisations. Those that protected their water sources thrived, while others that ignored water problems struggled or disappeared. This lesson remains critical today. We must treat water as a shared, limited resource and learn from ancient practices such as rainwater harvesting and smart irrigation.


## Page 6


Individuals can contribute by using water wisely, reducing waste, and keeping water sources clean. On a larger scale, supporting policies and technologies that protect water and provide access for all is essential. By respecting water and planning for the future, we can ensure that it remains a source of life, not conflict or crisis. Key points Good water management supports long-term survival. We must act responsibly to secure water for future generations. Z Handwash • ……• 72% Raw greywater Grey water Recycled water ON Motor Flushing Gardening A O MTD B2. Investigating heat absorption in different materials Investigate the rate of temperature change in water when stored in metal, glass, and plastic containers. Set each container in either sunlight and refrigerator and record which one alters the water temperature most quickly. Pay attention to how the material type impacts heat absorption and cooling efficiencies. This experiment will enhance our understanding of the varying heat conduction properties of different materials. Observation sheet: G4_WATER_EO_OBS_2 Student task card: G4_WATER_EO_STC_3


## Page 7


# Direct questions 1. Name some important ancient civilisations that developed around rivers. 2. What goal did the UN create to address clean water issues? Indirect questions 1. Why did Mesopotamia face problems even though it had rivers? 2. What can we learn from the fall of ancient civilisations that mismanaged their water? Application question What measures would you take to help a river from drying up? THINK MORE il Sustainable Cities What lessons from ancient civilizations can you include in your sustainable city’s water plan to make it more sustainable?
```

---

## 6. Auxiliary Classroom & Teacher Resources (`content_master` & `prayogshala`)

The following related classroom materials exist for Chapter 41675 under Tenant ID 341:

| ID | Category | Title | Source | Sub-Institute |
|---|---|---|---|---|
| `61551` | Recorded Videos | Chapter 1: supporting scheme video | HES publisher material | 341 |
| `61555` | Classroom Presentation | Chapter 1: poster SS1, Global Water Resources | HES publisher material | 341 |
| `61559` | Classroom Activity | Chapter 1: Student task cards (A1, B1) | HES publisher material | 341 |
| `61560` | Worksheet | Chapter 1: Observation sheets (A1, B2) | HES publisher material | 341 |
| `61566` | Classroom Activity | (whole unit): data collection and STEM sheets | HES publisher material | 341 |
| `61567` | Revision Notes | (whole unit): vocabulary cards for printing | HES publisher material | 341 |
| `61578` | Teacher training presentation | Teacher training presentation | Claude AI | 341 |
| `61579` | Teacher Training | Learning Outcomes | Claude AI | 341 |
| `61580` | Teacher Training | KSA Framework | Claude AI | 341 |
| `61581` | Teacher Training | Assessment Rubrics | Claude AI | 341 |

### Associated Prayogshala (Hands-on Lab Activity)
- **Activity #755**: Water on Earth: A global view (Tenant: `341`)
