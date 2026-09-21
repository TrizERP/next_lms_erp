<?php

/**
 * Std-10 legacy chapter -> current chapter crosswalk.
 *
 * AUTHORED, NOT GENERATED. This pipeline uses no LLM API. Each entry
 * below was decided by reading the evidence that `php artisan
 * remap:evidence` prints for that group: per-item vote tallies, the
 * distinctive terms driving each match, actual question text and actual
 * content filenames.
 *
 * Entry shape:
 *   subject   legacy subject_id
 *   chapter   legacy chapter_id
 *   to        target chapter_master.id (null for out_of_syllabus / needs_review)
 *   decision  map | nearest_surviving | out_of_syllabus | needs_review
 *   conf      0.0-1.0, the author's confidence
 *   why       the evidence this rests on, in words
 *   classes   optional; restricts the entry to some entity classes.
 *             Omitted means it applies to questions, content and
 *             teacher resources alike. Needed because a legacy chapter
 *             id can mean different things in different tables.
 *
 * Anything not listed here is left untouched and reported as
 * unmapped -- absence is never treated as permission to guess.
 */

return [

    // =================================================================
    // 4470  History (India and the Contemporary World-II)
    // 5 legacy groups -> 5 current chapters, a clean 1:1 run.
    // Every one confirmed by a decisive question vote.
    // =================================================================
    ['subject' => 4470, 'chapter' => 6243, 'to' => 23705, 'decision' => 'map', 'conf' => 0.98,
     'why' => 'question vote 28/35 for 23705; terms germany, italy, unification, prussia, balkan = Rise of Nationalism in Europe'],
    ['subject' => 4470, 'chapter' => 6244, 'to' => 23706, 'decision' => 'map', 'conf' => 0.99,
     'why' => 'question vote 12/12 unanimous; terms swaraj, khilafat, jallianwala, rowlatt, boycott = Nationalism in India'],
    ['subject' => 4470, 'chapter' => 6245, 'to' => 23707, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'question vote 4/5; terms bretton, imf, monetary = The Making of a Global World'],
    ['subject' => 4470, 'chapter' => 6246, 'to' => 23708, 'decision' => 'map', 'conf' => 0.99,
     'why' => 'question vote 9/9 unanimous; terms jenny, manchester, gomastha, victorian = The Age of Industrialisation'],
    ['subject' => 4470, 'chapter' => 6247, 'to' => 23709, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'terms manuscript, vernacular, hickey, handwritten = Print Culture and the Modern World'],

    // =================================================================
    // 4469  Geography (Contemporary India-II)
    // 7 legacy -> 7 current. Content filenames are self-identifying
    // here ("cbse_ppt_chapter_2_forest_and_wildlife"), which settled
    // 6237 against a misleading pooled-BM25 reading.
    // =================================================================
    ['subject' => 4469, 'chapter' => 6236, 'to' => 23693, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'question vote 5/6; content title "NCERT Class 10 Geography Chapter 1- Resources and Development"'],
    ['subject' => 4469, 'chapter' => 6237, 'to' => 23694, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content title "cbse_ppt_chapter_2_forest_and_wildlife"; 18 of 41 questions are species/extinction/biodiversity. Pooled BM25 said Water Resources on 7 minority water questions; per-item vote correctly gives 23694'],
    ['subject' => 4469, 'chapter' => 6238, 'to' => 23695, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'no questions, but content titles are explicit: "Water Resources - Chapter 3 Geography NCERT Class 10"'],
    ['subject' => 4469, 'chapter' => 6239, 'to' => 23696, 'decision' => 'map', 'conf' => 0.99,
     'why' => 'question vote 36/40; terms rabi, kharif, subsistence, jowar; content "Chapter 4- Agriculture"'],
    ['subject' => 4469, 'chapter' => 6240, 'to' => 23697, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'terms lode, metamorphic, tidal, harness = Minerals and Energy Resources; content "Ch-5"'],
    ['subject' => 4469, 'chapter' => 6241, 'to' => 23698, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'terms smelt, limestone, coking; content "Manufacturing Industries Class 10 notes"'],
    ['subject' => 4469, 'chapter' => 6242, 'to' => 23699, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'terms highway, waterway, pipeline, postal, navigable = Lifelines of National Economy'],

    // =================================================================
    // 4472  Economics (Understanding Economic Development)
    // 5 legacy -> 5 current.
    // =================================================================
    ['subject' => 4472, 'chapter' => 6248, 'to' => 23700, 'decision' => 'map', 'conf' => 0.98,
     'why' => 'terms hdi, per capita, infant mortality, kerala = Development'],
    ['subject' => 4472, 'chapter' => 6249, 'to' => 23701, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'terms gdp, disguised unemployment, tertiary = Sectors of the Indian Economy'],
    ['subject' => 4472, 'chapter' => 6250, 'to' => 23702, 'decision' => 'map', 'conf' => 0.98,
     'why' => 'terms borrower, lender, collateral, moneylender, informal = Money and Credit'],
    ['subject' => 4472, 'chapter' => 6251, 'to' => 23703, 'decision' => 'map', 'conf' => 0.96,
     'why' => 'terms multinational, corporation = Globalisation and the Indian Economy'],
    ['subject' => 4472, 'chapter' => 7970, 'to' => 23704, 'decision' => 'map', 'conf' => 0.70,
     'why' => 'no usable text (titles are generic "Classroom Presentation", "Remedial Class"). Assigned by elimination: the other four legacy groups map confidently to 4 of the 5 chapters, leaving Consumer Rights. Inference from set structure, not from content'],

    // =================================================================
    // 4064  Political Science (Democratic Politics-II)
    // 8 legacy -> 5 current. The 2023 rationalisation dropped three
    // chapters: Democracy and Diversity, Popular Struggles and
    // Movements, and Challenges to Democracy. Per instruction those go
    // to the nearest surviving chapter rather than being deleted,
    // because each is topically adjacent to one that survived.
    // =================================================================
    ['subject' => 4064, 'chapter' => 1039, 'to' => 23710, 'decision' => 'map', 'conf' => 0.96,
     'why' => 'question vote 24/38 for 23710; terms belgium, sinhala, tamil, brussels, majoritarianism = Power Sharing. Content titles are literally "power sharing". 5 minority votes for 23705 are a handful of misfiled History questions'],
    ['subject' => 4064, 'chapter' => 1040, 'to' => 23711, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'terms federalism, decentralisation, concurrent, panchayati raj = Federalism'],
    ['subject' => 4064, 'chapter' => 1041, 'to' => 23712, 'decision' => 'nearest_surviving', 'conf' => 0.72,
     'why' => 'legacy chapter is "Democracy and Diversity" (terms ireland, overlapping, exclusive), dropped in rationalisation. 23712 Gender/Religion/Caste is the surviving chapter that teaches social divisions'],
    ['subject' => 4064, 'chapter' => 1042, 'to' => 23712, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'term feminist plus caste/communal question text = Gender, Religion and Caste'],
    ['subject' => 4064, 'chapter' => 1043, 'to' => 23713, 'decision' => 'nearest_surviving', 'conf' => 0.60,
     'why' => 'legacy chapter is "Popular Struggles and Movements" (pressure groups, FEDECOR, Nepal), dropped in rationalisation. Zero distinctive terms survive in the corpus. 23713 Political Parties is the nearest surviving chapter on political organisation. Low confidence, deliberately'],
    ['subject' => 4064, 'chapter' => 1044, 'to' => 23713, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'terms alliance, welfare, coalition plus party-system question text = Political Parties'],
    ['subject' => 4064, 'chapter' => 1045, 'to' => 23714, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'terms inequality, accountable, dictatorship, assess = Outcomes of Democracy'],
    ['subject' => 4064, 'chapter' => 1046, 'to' => 23714, 'decision' => 'nearest_surviving', 'conf' => 0.65,
     'why' => 'legacy chapter is "Challenges to Democracy" (foundational/expansion challenge), dropped in rationalisation. 23714 Outcomes of Democracy is the surviving chapter that assesses democracy'],

    // =================================================================
    // 3978  English (First Flight)
    // 23 legacy -> 9 current, the most tangled subject.
    //
    // Three distinct situations:
    //   1. prose lessons        -> a clean 1:1 ordinal run, verified
    //   2. The Hundred Dresses  -> removed from the syllabus entirely
    //   3. the eleven poems     -> NOT out of syllabus, but the current
    //      chapter set has NO poem topics at all (verified against
    //      topic_master). Each poem is mapped to the prose chapter it
    //      shares in First Flight, and flagged so the missing poem
    //      topics can be added later.
    // =================================================================
    ['subject' => 3978, 'chapter' => 1028, 'to' => 23715, 'decision' => 'map', 'conf' => 0.96,
     'why' => 'question vote for 23715; A Letter to God / Lencho'],
    ['subject' => 3978, 'chapter' => 1029, 'to' => 23716, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'question text is Nelson Mandela, apartheid, South African flag, inauguration. Pooled vote drifted to the English Grammar workbook because the group also holds generic tense drills; the lesson-specific questions are decisive'],
    ['subject' => 3978, 'chapter' => 1030, 'to' => 23717, 'decision' => 'map', 'conf' => 0.96,
     'why' => 'question text names "His First Flight" and "Black Aeroplane" = Two Stories about Flying'],
    ['subject' => 3978, 'chapter' => 1031, 'to' => 23718, 'decision' => 'map', 'conf' => 0.96,
     'why' => 'question text is Anne Frank diary, Mr Keesing = From the Diary of Anne Frank'],
    ['subject' => 3978, 'chapter' => 1032, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.90,
     'why' => 'The Hundred Dresses - I. Removed from First Flight by the 2023 rationalisation; no surviving chapter teaches it and no topic in the std-10 set mentions it'],
    ['subject' => 3978, 'chapter' => 1033, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.90,
     'why' => 'The Hundred Dresses - II (Miss Mason, Wanda). Removed by the 2023 rationalisation'],
    ['subject' => 3978, 'chapter' => 1034, 'to' => 23719, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'question vote for 23719; Glimpses of India (Goa baker, Coorg, Assam tea)'],
    ['subject' => 3978, 'chapter' => 1035, 'to' => 23720, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'question vote 0.74 share for 23720; Mijbil the Otter'],
    ['subject' => 3978, 'chapter' => 1036, 'to' => 23721, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'question vote 0.83 share for 23721; Madam Rides the Bus / Valli'],
    ['subject' => 3978, 'chapter' => 1037, 'to' => 23722, 'decision' => 'map', 'conf' => 0.98,
     'why' => 'question vote 0.90 share for 23722; The Sermon at Benares / Kisa Gotami'],
    ['subject' => 3978, 'chapter' => 1038, 'to' => 23723, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'no questions, but content filenames are explicit: "THE PROPOSAL.mp4", "The Proposal i.mp4" = The Proposal'],

    // --- 3978 First Flight poems -------------------------------------
    // Verified against topic_master: NOT ONE of the nine current
    // English chapters carries a poem topic. The poems are genuinely
    // part of the CBSE Class 10 syllabus, so deleting them would lose
    // real content; instead each is mapped to the prose chapter it
    // shares in First Flight. This is inference from the textbook's
    // structure, not from anything in the database, hence the flag and
    // the moderate confidence.
    ['subject' => 3978, 'chapter' => 3629, 'to' => 23715, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "Dust of Snow", paired with A Letter to God in First Flight ch1. No poem topics exist in the current chapter set'],
    ['subject' => 3978, 'chapter' => 3630, 'to' => 23715, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "Fire and Ice", paired with A Letter to God in First Flight ch1'],
    ['subject' => 3978, 'chapter' => 3631, 'to' => 23716, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "A Tiger in the Zoo" (question text: "in his quiet rage", "stalks in his vivid stripes"), paired with Nelson Mandela in ch2'],
    ['subject' => 3978, 'chapter' => 3632, 'to' => 23717, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "How to Tell Wild Animals", paired with Two Stories about Flying in ch3'],
    ['subject' => 3978, 'chapter' => 3633, 'to' => 23717, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "The Ball Poem", paired with Two Stories about Flying in ch3'],
    ['subject' => 3978, 'chapter' => 3634, 'to' => 23718, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "Amanda!", paired with From the Diary of Anne Frank in ch4'],
    ['subject' => 3978, 'chapter' => 3635, 'to' => 23719, 'decision' => 'nearest_surviving', 'conf' => 0.72,
     'why' => 'poem "Animals" (Walt Whitman), paired with Glimpses of India in ch5'],
    ['subject' => 3978, 'chapter' => 3636, 'to' => 23719, 'decision' => 'nearest_surviving', 'conf' => 0.72,
     'why' => 'poem "The Trees", paired with Glimpses of India in ch5'],
    ['subject' => 3978, 'chapter' => 3637, 'to' => 23720, 'decision' => 'nearest_surviving', 'conf' => 0.75,
     'why' => 'poem "Fog", paired with Mijbil the Otter in ch6'],
    ['subject' => 3978, 'chapter' => 3638, 'to' => 23721, 'decision' => 'nearest_surviving', 'conf' => 0.78,
     'why' => 'poem "The Tale of Custard the Dragon" (content filename "the_tale_of_custard_the_dragon"), paired with Madam Rides the Bus in ch7'],
    ['subject' => 3978, 'chapter' => 3639, 'to' => 23722, 'decision' => 'nearest_surviving', 'conf' => 0.78,
     'why' => 'poem "For Anne Gregory" (content filename "For anne Gregory (Notes).pdf"), paired with The Sermon at Benares in ch8'],

    ['subject' => 3978, 'chapter' => 8507, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'only generic titles ("Class-10-English-Classroom-Presentation"). No lesson identity in any of the 5 rows'],

    // =================================================================
    // 4435  English-2 (Footprints Without Feet)
    // 10 legacy -> 9 current. Content filenames name the lesson
    // outright, so this is the best-evidenced subject in the set.
    // =================================================================
    ['subject' => 4435, 'chapter' => 6226, 'to' => 23724, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'content "a-triumph-of-surgery_.pdf" = A Triumph of Surgery'],
    ['subject' => 4435, 'chapter' => 6227, 'to' => 23725, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'content "READING MATERIAL-A THIEF STORY.pdf" = The Thief\'s Story'],
    ['subject' => 4435, 'chapter' => 6228, 'to' => 23726, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'content "READING MATERIALS FOR THE MIDNIGHT VISITOR.pdf"'],
    ['subject' => 4435, 'chapter' => 6229, 'to' => 23727, 'decision' => 'map', 'conf' => 0.98,
     'why' => 'content "A Question of Trust Class 10 English Chapter 4 explanation"'],
    ['subject' => 4435, 'chapter' => 6230, 'to' => 23728, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'content "READING MATERIALS ON FOOTPRINTS WITHOUT FEET"'],
    ['subject' => 4435, 'chapter' => 6231, 'to' => 23729, 'decision' => 'map', 'conf' => 0.82,
     'why' => 'content is images "butterfly.jpg", "chemical structure.jpg" = The Making of a Scientist (Richard Ebright and his butterfly collection). Weaker evidence than its neighbours but consistent with the 1:1 run'],
    ['subject' => 4435, 'chapter' => 6232, 'to' => 23730, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'content "thenecklace-compressed.mp4" = The Necklace'],
    ['subject' => 4435, 'chapter' => 6233, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.88,
     'why' => 'content title "The Hack Driver". Removed from Footprints Without Feet by the 2023 rationalisation; no surviving chapter and no topic mentions it'],
    ['subject' => 4435, 'chapter' => 6234, 'to' => 23731, 'decision' => 'map', 'conf' => 0.96,
     'why' => 'content "BHOLI.mp4" = Bholi'],
    ['subject' => 4435, 'chapter' => 6235, 'to' => 23732, 'decision' => 'map', 'conf' => 0.97,
     'why' => 'content "THE BOOK THAT SAVED THE EARTH PART 1-3.mp4"'],

    // =================================================================
    // 3975  Science -- the 60-row CR residue
    //
    // Science itself is already correctly mapped; these three legacy
    // groups are old Science chapters 14, 15 and 16. The std-10 Science
    // chapter set (ids 1012-1024, created 2021) is the PRE-
    // rationalisation set of 13 chapters and simply has no rows for
    // them -- note it still contains Periodic Classification (1016),
    // which rationalisation removed.
    //
    // Sources of Energy and Management of Natural Resources were
    // genuinely dropped. Our Environment was NOT; it is chapter 13 of
    // the rationalised book and its absence here is a gap in
    // chapter_master, so its content is held for review rather than
    // deleted.
    // =================================================================
    ['subject' => 3975, 'chapter' => 1025, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.85,
     'why' => 'content "Ch-14 Sources of energy", solar cooker, biogas. Sources of Energy was removed by the 2023 rationalisation and no chapter row exists'],
    ['subject' => 3975, 'chapter' => 1026, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'content "Ch-15 Our Environment". Our Environment IS retained in the rationalised syllabus but no chapter row exists in the std-10 Science set. Missing chapter, not retired content -- do not delete'],
    ['subject' => 3975, 'chapter' => 1027, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.85,
     'why' => 'content "CHAPTER 16 MANAGEMENT OF NATURAL RESOURCES.pdf". Removed by the 2023 rationalisation and no chapter row exists'],

    // =================================================================
    // 3976  Mathematics
    // 15 legacy -> 14 current. The old book had 15 chapters; the 2023
    // rationalisation removed Constructions (ch11), which is exactly
    // legacy 1007. Eleven positions are confirmed by content titles or
    // question votes, so the two unidentifiable groups (999, 1008) sit
    // on a well-corroborated ordinal run rather than a bare guess.
    // Note many titles here are raw YouTube ids and carry no signal.
    // =================================================================
    ['subject' => 3976, 'chapter' => 997,  'to' => 22905, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "REAL NUMBERS - PART 1 - CBSE Class 10 Maths - Chapter 1.mp4"'],
    ['subject' => 3976, 'chapter' => 998,  'to' => 22907, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "CBSE_Class_10_Maths_-_2_--_Polynomials_--_Full_Chapter"'],
    ['subject' => 3976, 'chapter' => 999,  'to' => 22910, 'decision' => 'map', 'conf' => 0.72,
     'why' => 'no identifying title (only "EXPLANATION VIDEO.mp4"). Position 3 on an ordinal run confirmed at positions 1, 2, 4, 5, 6, 7, 8, 9, 11, 12, 13, 14 = Pair of Linear Equations'],
    ['subject' => 3976, 'chapter' => 1000, 'to' => 23126, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "2-Quadratic Equations.mp4"'],
    ['subject' => 3976, 'chapter' => 1001, 'to' => 23197, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "Class 10 exercise 5.2 NCERT solutions chapter 5 - Arithmetic progression"'],
    ['subject' => 3976, 'chapter' => 1002, 'to' => 23513, 'decision' => 'map', 'conf' => 0.90,
     'why' => 'lexical match on proportionality, converse, thales = Triangles; consistent with ordinal position 6'],
    ['subject' => 3976, 'chapter' => 1003, 'to' => 23512, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "Distance formula", "Ratio in which a point divides a line segment - Coordinate geometry"'],
    ['subject' => 3976, 'chapter' => 1004, 'to' => 23511, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "Introduction_to_Trigonometry_Explanation.mp4", trigonometric ratios'],
    ['subject' => 3976, 'chapter' => 1005, 'to' => 23199, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "CBSE X Maths Some Applications of Trigonometry - Heights and Distances"'],
    ['subject' => 3976, 'chapter' => 1006, 'to' => 23198, 'decision' => 'map', 'conf' => 0.70,
     'why' => 'content is mixed: 3 tangent/circle titles and 4 "Areas Related to circles" titles. Ordinal position 10 = Circles, and legacy 1008 takes Areas Related to Circles. Lower confidence because of the mixture'],
    ['subject' => 3976, 'chapter' => 1007, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.92,
     'why' => 'content "Class 10 Maths Construction Online - Construction Methods", "Construction of tangents". Constructions was removed by the 2023 rationalisation, which is exactly why 15 legacy chapters map onto 14 current ones'],
    ['subject' => 3976, 'chapter' => 1008, 'to' => 23196, 'decision' => 'map', 'conf' => 0.62,
     'why' => 'no identifying content at all (pure boilerplate). Ordinal position 11 on a run confirmed either side = Areas Related to Circles. Positional inference, flagged'],
    ['subject' => 3976, 'chapter' => 1009, 'to' => 22909, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "Class 10 Surface Area And Volume"'],
    ['subject' => 3976, 'chapter' => 1010, 'to' => 22908, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "statistics-_compressed.mp4"'],
    ['subject' => 3976, 'chapter' => 1011, 'to' => 22906, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "Explanation Theoretical Probability.mp4" etc; lexical specificity 0.85 on probability, theoretical'],

    // =================================================================
    // 5333  Health and Physical Education
    // 13 legacy (8025-8037, contiguous) -> 13 current (sort 1-13).
    // Counts match exactly. Content is almost entirely boilerplate
    // ("Revision Notes", "Classroom Activity"); the only identifying
    // rows are three "Healthy Community Living" titles at legacy 8035,
    // which lands on sort 11 "Community Life in Three Settings" under
    // the ordinal alignment and corroborates it.
    //
    // This is positional inference with one semantic anchor. Confidence
    // is set accordingly and every row is separately revertible.
    // =================================================================
    ['subject' => 5333, 'chapter' => 8025, 'to' => 23768, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 1 of 13; counts match exactly; anchored by 8035'],
    ['subject' => 5333, 'chapter' => 8026, 'to' => 23769, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 2 of 13'],
    ['subject' => 5333, 'chapter' => 8027, 'to' => 23770, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 3 of 13'],
    ['subject' => 5333, 'chapter' => 8028, 'to' => 23771, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 4 of 13'],
    ['subject' => 5333, 'chapter' => 8029, 'to' => 23772, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 5 of 13'],
    ['subject' => 5333, 'chapter' => 8030, 'to' => 23773, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 6 of 13'],
    ['subject' => 5333, 'chapter' => 8031, 'to' => 23774, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 7 of 13'],
    ['subject' => 5333, 'chapter' => 8032, 'to' => 23775, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 8 of 13'],
    ['subject' => 5333, 'chapter' => 8033, 'to' => 23776, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 9 of 13'],
    ['subject' => 5333, 'chapter' => 8034, 'to' => 23777, 'decision' => 'map', 'conf' => 0.62, 'why' => 'ordinal position 10 of 13'],
    ['subject' => 5333, 'chapter' => 8035, 'to' => 23778, 'decision' => 'map', 'conf' => 0.80, 'why' => 'content title "Healthy Community Living" matches sort 11 "Community Life in Three Settings / What Makes a Community Healthy". This is the anchor for the whole ordinal run'],
    ['subject' => 5333, 'chapter' => 8036, 'to' => 23779, 'decision' => 'map', 'conf' => 0.60, 'why' => 'ordinal position 12 of 13; title "Healthy Community Living" is a continuation and only weakly consistent with sort 12 Social Health'],
    ['subject' => 5333, 'chapter' => 8037, 'to' => 23780, 'decision' => 'map', 'conf' => 0.58, 'why' => 'ordinal position 13 of 13; title is a continuation label and does not independently confirm'],

    // =================================================================
    // 3979  English Grammar (Words and Expressions 2)
    // STRUCTURAL MISMATCH, not a mapping problem.
    //
    // The legacy groups are grammar TOPICS (Tenses, Subject-Verb
    // Agreement, Reported Speech, Clauses, Determiners). The nine
    // current chapters are workbook units tied to First Flight lessons
    // ("Recalling A Letter to God", "Recalling Nelson Mandela"). There
    // is no defensible mapping from a grammar topic to a lesson unit:
    // tenses are practised across all of them.
    //
    // Held for review. The right fix is chapter rows for the grammar
    // topics, which is a content decision, not a remap.
    // =================================================================
    ['subject' => 3979, 'chapter' => 6210, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'legacy topic "10th English Tenses". Current 3979 chapters are lesson-based workbook units; no grammar-topic chapter exists'],
    ['subject' => 3979, 'chapter' => 6211, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'generic titles only; grammar workbook topic, no matching chapter'],
    ['subject' => 3979, 'chapter' => 6212, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'generic titles ("VSP=ENGLISH.pdf"); no matching chapter'],
    ['subject' => 3979, 'chapter' => 6213, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'legacy topic "Subject-Verb Agreement Rules"; no grammar-topic chapter exists'],
    ['subject' => 3979, 'chapter' => 6214, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'legacy topic "Reported speech / Indirect speech"; no grammar-topic chapter exists'],
    ['subject' => 3979, 'chapter' => 6215, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'legacy topic "Clauses"; no grammar-topic chapter exists'],
    ['subject' => 3979, 'chapter' => 6216, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'legacy topic "Determiners"; no grammar-topic chapter exists'],
    ['subject' => 3979, 'chapter' => 6217, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'generic titles only; no matching chapter'],

    // =================================================================
    // 3977  Hindi-A (Kshitij-2)
    // 16 legacy -> 12 current. Content filenames name the lesson or the
    // poet, so most of this is well evidenced.
    //
    // Two separate gaps:
    //   - legacy 6194-6196 are KRITIKA, the supplementary reader. The
    //     current subject only holds Kshitij-2, so the whole book is
    //     missing from chapter_master. Held for review, not deleted.
    //   - legacy 6202 is कन्यादान (Rituraj), a poem the current
    //     12-chapter set does not carry.
    // =================================================================
    ['subject' => 3977, 'chapter' => 6194, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'Kritika lesson "माता का अंचल" (content "Std-10 Hindi-A Cha-1 माता का अंचल"). Kritika is a separate supplementary book with no chapters in the std-10 set'],
    ['subject' => 3977, 'chapter' => 6195, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'Kritika lesson "जॉर्ज पंचम की नाक" (content "कृतिका-पाठ-2"). Book missing from chapter_master'],
    ['subject' => 3977, 'chapter' => 6196, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'Kritika lesson "सन्ना सन्ना हाथ जोड़ि". Book missing from chapter_master'],
    ['subject' => 3977, 'chapter' => 6197, 'to' => 23742, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "SURDAS MATERIAL IN PDF" = सूरदास, chapter sort 1'],
    ['subject' => 3977, 'chapter' => 6198, 'to' => 23743, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "Std-10 Hindi-A Cha-5 राम लक्ष्मण, परशुराम संवाद" = तुलसीदास, chapter sort 2'],
    ['subject' => 3977, 'chapter' => 6199, 'to' => 23745, 'decision' => 'map', 'conf' => 0.90,
     'why' => 'content "utsah - Nirala" = निराला, chapter sort 4 (उत्साह and अट नहीं रही are one chapter)'],
    ['subject' => 3977, 'chapter' => 6200, 'to' => 23745, 'decision' => 'map', 'conf' => 0.90,
     'why' => 'content "At nahi rahi hai - Nirala" = निराला, chapter sort 4. Second legacy group for the same current chapter'],
    ['subject' => 3977, 'chapter' => 6201, 'to' => 23746, 'decision' => 'map', 'conf' => 0.92,
     'why' => 'content "DANTURIT MUSKAN" and "FASAL" = नागार्जुन, chapter sort 5'],
    ['subject' => 3977, 'chapter' => 6202, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.80,
     'why' => 'content "Std-10 Hindi-A कन्यादान(कविता)". No chapter or topic in the current 12-chapter Kshitij-2 set covers कन्यादान'],
    ['subject' => 3977, 'chapter' => 6203, 'to' => 23747, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "Sangatkar Kavita" = मंगलेश डबराल संगतकार, chapter sort 6'],
    ['subject' => 3977, 'chapter' => 6204, 'to' => 23748, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "Netaji ka Chashma.pdf" = स्वयं प्रकाश नेताजी का चश्मा, chapter sort 7'],
    ['subject' => 3977, 'chapter' => 6205, 'to' => 23749, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "Std-10 Hindi-A Cha-12 बालगोबिन भगत" = रामवृक्ष बेनीपुरी, chapter sort 8'],
    ['subject' => 3977, 'chapter' => 6206, 'to' => 23750, 'decision' => 'map', 'conf' => 0.92,
     'why' => 'content "लखनवी अंदाज़" = यशपाल, chapter sort 9'],
    ['subject' => 3977, 'chapter' => 6207, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'content is only "WhatsApp Image ...jpeg" files. No lesson identity'],
    ['subject' => 3977, 'chapter' => 6208, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'generic titles only ("READING MATERIAL.jpg"). No lesson identity'],
    ['subject' => 3977, 'chapter' => 6209, 'to' => 23752, 'decision' => 'map', 'conf' => 0.60,
     'why' => 'content is मोहर्रम images; Muharram and the shehnai belong to यतींद्र मिश्र "नौबतखाने में इबादत", chapter sort 11. Thin evidence, flagged'],

    // =================================================================
    // 4512  Hindi-B (Sparsh-2)
    // 16 legacy -> 14 current. Content filenames name the lesson, so
    // this subject is well evidenced despite having no questions.
    //
    //   - legacy 6252-6253 are SANCHAYAN, the supplementary reader,
    //     absent from chapter_master entirely -> held for review
    //   - legacy 6257, 6263, 6266 are individual lessons the current
    //     14-chapter Sparsh set does not carry
    // =================================================================
    ['subject' => 4512, 'chapter' => 6252, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'Sanchayan lesson "सपनों के-से दिन". Sanchayan is a separate supplementary book with no chapters in the std-10 set'],
    ['subject' => 4512, 'chapter' => 6253, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0,
     'why' => 'Sanchayan lesson "टोपी शुक्ला" (content "Class 10 Topi Shukla FULL ANIMATION.mp4"). Book missing from chapter_master'],
    ['subject' => 4512, 'chapter' => 6254, 'to' => 23761, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "Reading material of bade bhai sahab" = प्रेमचंद बड़े भाई साहब, chapter sort 8'],
    ['subject' => 4512, 'chapter' => 6255, 'to' => 23762, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "dairy ka ek panna" = सीताराम सेकसरिया डायरी का एक पन्ना, chapter sort 9'],
    ['subject' => 4512, 'chapter' => 6256, 'to' => 23763, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "Tantara Vamiro Katha" = लीलाधर मंडलोई ततांरा-वामीरो कथा, chapter sort 10'],
    ['subject' => 4512, 'chapter' => 6257, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.78,
     'why' => 'content "GIRGIT.pdf" = गिरगिट (Chekhov). No chapter or topic in the current 14-chapter Sparsh set covers it'],
    ['subject' => 4512, 'chapter' => 6258, 'to' => 23765, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "अब कहाँ दूसरे के दुख से दुखी होने वाले" = निदा फ़ाज़ली, chapter sort 12'],
    ['subject' => 4512, 'chapter' => 6259, 'to' => 23767, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "Kaartoos Class 10 Hindi Sparsh Book Chapter 17 Explanation" = हबीब तनवीर कारतूस, chapter sort 14'],
    ['subject' => 4512, 'chapter' => 6260, 'to' => 23766, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "Patjhad ki Tooti Pattiyaan (Ginni Ka Sona)" = रवींद्र केलेकर, chapter sort 13'],
    ['subject' => 4512, 'chapter' => 6261, 'to' => 23755, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "PAD (MEERA) PPT.pdf" = मीराबाई पद, chapter sort 2'],
    ['subject' => 4512, 'chapter' => 6262, 'to' => 23754, 'decision' => 'map', 'conf' => 0.95,
     'why' => 'content "SAAKHI (KABIR)" and "Std-10 Hindi-B Cha-4 साखी" = कबीर साखी, chapter sort 1'],
    ['subject' => 4512, 'chapter' => 6263, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.78,
     'why' => 'content "बिहारी के दोहे (Bihari ke Dohe)". No chapter or topic in the current Sparsh set covers it'],
    ['subject' => 4512, 'chapter' => 6264, 'to' => 23756, 'decision' => 'map', 'conf' => 0.93,
     'why' => 'content "MANUSYATA.pdf" = मैथिलीशरण गुप्त मनुष्यता, chapter sort 3'],
    ['subject' => 4512, 'chapter' => 6265, 'to' => 23757, 'decision' => 'map', 'conf' => 0.94,
     'why' => 'content "PPT-PARVAT PRADESH ME PAVAS.pdf" = सुमित्रानंदन पंत, chapter sort 4'],
    ['subject' => 4512, 'chapter' => 6266, 'to' => null, 'decision' => 'out_of_syllabus', 'conf' => 0.75,
     'why' => 'content "मधुर मधुर मेरे दीपक जल" (महादेवी वर्मा). No chapter or topic in the current Sparsh set covers it'],
    ['subject' => 4512, 'chapter' => 6267, 'to' => 23760, 'decision' => 'map', 'conf' => 0.92,
     'why' => 'content "aatmtran.mp4" / "atmatran-ppt.ppt" = रवींद्रनाथ ठाकुर आत्मत्राण, chapter sort 7'],

    // =================================================================
    // 3980  Hindi Grammar
    // STRUCTURAL GAP: the subject has ZERO chapters in the std-10 set.
    // Every legacy group is a Hindi writing or grammar skill, and
    // neither Hindi-A (Kshitij) nor Hindi-B (Sparsh) contains grammar
    // chapters to move them to. Nothing can be mapped without inventing
    // a destination, so all eight are held for review.
    // =================================================================
    ['subject' => 3980, 'chapter' => 6218, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic पत्र लेखन (letter writing); subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6219, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic अपठित गद्यांश (unseen passage); subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6220, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'generic titles only; subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6221, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic मुहावरे (idioms); subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6222, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic अनुच्छेद लेखन (paragraph writing); subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6223, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic पदबंध (phrase); subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6224, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic सूचना लेखन (notice writing); subject 3980 has no chapters at all'],
    ['subject' => 3980, 'chapter' => 6225, 'to' => null, 'decision' => 'needs_review', 'conf' => 0.0, 'why' => 'topic विज्ञापन लेखन (advertisement writing); subject 3980 has no chapters at all'],
];
