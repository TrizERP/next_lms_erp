<?php

/*
|--------------------------------------------------------------------------
| CBSE concept graph — Science concept-level edges, classes 6–10
|--------------------------------------------------------------------------
|
| Loaded by `php artisan cbse:graph-import --level=concept`.
| Written into `pal_concept_graph_edge`.
|
| HOW THIS DIFFERS FROM science_chapter_edges.php
| The chapter file is the backbone and resolves on any CBSE estate, because
| NCERT chapter names are stable. This file is the precision layer: it names
| individual concepts, and concept names come out of the AI extraction pipeline
| so they vary between estates and between extraction runs. Expect misses on a
| first import. THAT IS THE DESIGNED BEHAVIOUR — the importer prints every
| unresolved name beside the closest real spellings, and an SME either corrects
| the `concept` key or adds a `concept_aliases` entry. A miss is reported, never
| silently dropped; ~4,600 unresolved prerequisite strings already sit in
| `lms_concept_intelligence_index` because the pipeline chose the other way.
|
| Each endpoint is (grade, chapter, concept). The chapter narrows the search, so
| a concept name as ordinary as "Reflection" is unambiguous.
|
| TWO KINDS OF ENTRY LIVE HERE
|
| 1. GATING EDGES — the Class 9–10 spine, where a named concept genuinely stops
|    a learner. These are deliberately few. A concept edge is only worth
|    authoring where the chapter edge is too coarse to act on: "Motion gates
|    Force and Laws of Motion" is true but unhelpful, whereas "a learner who
|    cannot read a velocity–time graph cannot derive the third equation of
|    motion" names the thing to remediate.
|
| 2. CONTRASTS — `contrasts_with` pairs. These are NOT prerequisites and have no
|    direction; they record the pairs learners reliably confuse. Nothing in the
|    estate held this before, and it is the highest-value new signal in the
|    graph: it feeds the diagnostic layer and `pal_misconception_library`
|    directly. A learner failing "weight" is usually not missing weight — they
|    are conflating it with mass, and a remediation that re-teaches weight will
|    not land. Symmetric edges are stored canonically (lower concept id first),
|    are excluded from the acyclicity check, and are NOT projected into
|    `pal_concept_relations`, which six services read as directed.
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // GATING EDGES — the Class 9–10 spine
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Velocity-time graph',
            'concept_aliases' => ['Velocity time graph', 'Graphical representation of motion', 'Velocity–time graph']],
        'dependent' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Equations of motion',
            'concept_aliases' => ['Equations of motion by graphical method', 'Three equations of motion']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'NCERT derives all three equations by reading a velocity-time graph: the first from its slope, the second from the area under it. A learner who cannot read the graph cannot follow the derivation, only memorise the result.',
        'evidence' => 'NCERT Science 9, Ch. Motion §8.5 (equations of motion by graphical method)',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Acceleration'],
        'dependent' => ['grade' => 9, 'chapter' => 'Force and Laws of Motion', 'concept' => 'Second law of motion',
            'concept_aliases' => ['Newton\'s second law of motion', 'Second law']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'F = ma has acceleration as one of its two terms. A learner who cannot compute an acceleration cannot compute a force, and will read the law as a formula rather than as a statement about how motion changes.',
        'evidence' => 'NCERT Science 9, Ch. Force and Laws of Motion §9.4',
    ],
    [
        'strand' => 'SCI-GRV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Gravitation', 'concept' => 'Universal law of gravitation',
            'concept_aliases' => ['Universal law of gravitation', 'Newton\'s universal law of gravitation']],
        'dependent' => ['grade' => 9, 'chapter' => 'Gravitation', 'concept' => 'Acceleration due to gravity',
            'concept_aliases' => ['Free fall', 'Value of g']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'g is derived by equating the gravitational force on a body to ma and cancelling the mass. Without the universal law there is nothing to equate, and g becomes an unexplained constant of 9.8.',
        'evidence' => 'NCERT Science 9, Ch. Gravitation §10.2 → §10.3',
    ],
    [
        'strand' => 'SCI-ENR',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Work and Energy', 'concept' => 'Work',
            'concept_aliases' => ['Work done by a constant force', 'Scientific conception of work']],
        'dependent' => ['grade' => 9, 'chapter' => 'Work and Energy', 'concept' => 'Kinetic energy'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Kinetic energy is defined as the work needed to bring a body to its speed, and ½mv² is obtained by substituting an equation of motion into the work expression. The definition is a work calculation.',
        'evidence' => 'NCERT Science 9, Ch. Work and Energy §11.2 → §11.3',
    ],
    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Atoms and Molecules', 'concept' => 'Valency'],
        'dependent' => ['grade' => 9, 'chapter' => 'Atoms and Molecules', 'concept' => 'Chemical formula',
            'concept_aliases' => ['Writing chemical formulae', 'Chemical formulae']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'A formula is written by crossing over valencies. A learner who cannot state the valency of an element cannot write the formula of any compound it forms, and every equation afterwards is unreachable.',
        'evidence' => 'NCERT Science 9, Ch. Atoms and Molecules §3.4',
    ],
    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Atoms and Molecules', 'concept' => 'Mole concept',
            'concept_aliases' => ['Mole', 'Mole concept and molar mass']],
        'dependent' => ['grade' => 9, 'chapter' => 'Atoms and Molecules', 'concept' => 'Molar mass',
            'concept_aliases' => ['Molar mass', 'Gram atomic mass']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Molar mass is the mass of one mole. The quantity being massed has to be defined before its mass means anything, and learners who skip this treat molar mass as a lookup rather than a ratio.',
        'evidence' => 'NCERT Science 9, Ch. Atoms and Molecules §3.5',
    ],
    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Structure of the Atom', 'concept' => 'Electronic configuration',
            'concept_aliases' => ['Distribution of electrons in different orbits', 'Electron distribution', 'Bohr Bury rules']],
        'dependent' => ['grade' => 10, 'chapter' => 'Periodic Classification of Elements', 'concept' => 'Modern periodic table',
            'concept_aliases' => ['Position of elements in the modern periodic table', 'Modern periodic law']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'A period is a shell and a group shares a valence-electron count. Placing an element in the table IS writing its electronic configuration, so a learner without it can only memorise positions.',
        'evidence' => 'NCERT Science 9 Ch. Structure of the Atom §4.4 → Science 10 Ch. Periodic Classification',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations', 'concept' => 'Balanced chemical equation',
            'concept_aliases' => ['Balancing chemical equations', 'Balanced equation', 'Writing a chemical equation']],
        'dependent' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations', 'concept' => 'Types of chemical reactions',
            'concept_aliases' => ['Types of reactions', 'Combination reaction', 'Decomposition reaction']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Each reaction type is recognised by the SHAPE of its balanced equation — one product, two products, an exchange of ions. An unbalanced equation has no recognisable shape.',
        'evidence' => 'NCERT Science 10, Ch. Chemical Reactions and Equations §1.1 → §1.2',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Metals and Non-metals', 'concept' => 'Reactivity series',
            'concept_aliases' => ['Activity series', 'Reactivity series of metals']],
        'dependent' => ['grade' => 10, 'chapter' => 'Metals and Non-metals', 'concept' => 'Extraction of metals',
            'concept_aliases' => ['Occurrence of metals', 'Metallurgy', 'Extraction of metals from ores']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Which extraction method an ore needs is decided by where its metal sits in the series — electrolysis at the top, reduction by carbon in the middle, heating alone at the bottom. The series is the decision rule.',
        'evidence' => 'NCERT Science 10, Ch. Metals and Non-metals §3.3 → §3.4',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Ohm\'s law',
            'concept_aliases' => ['Ohms law', 'Ohm\'s Law']],
        'dependent' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Resistors in series',
            'concept_aliases' => ['Resistance of a system of resistors', 'Series combination of resistors']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'The series result is derived by applying V = IR to each resistor and summing the potential differences. The derivation is three applications of the law.',
        'evidence' => 'NCERT Science 10, Ch. Electricity §12.4 → §12.5',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Electric current',
            'concept_aliases' => ['Electric current and circuit', 'Current']],
        'dependent' => ['grade' => 10, 'chapter' => 'Magnetic Effects of Electric Current', 'concept' => 'Magnetic field due to a current-carrying conductor',
            'concept_aliases' => ['Magnetic field due to a current carrying conductor', 'Magnetic field due to a current-carrying straight conductor']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'The field strength is stated as proportional to the current and inversely proportional to distance. A learner with no working idea of current has no variable to vary.',
        'evidence' => 'NCERT Science 10 Ch. Electricity §12.2 → Ch. Magnetic Effects §13.2',
    ],
    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Light — Reflection and Refraction', 'concept' => 'Refractive index',
            'concept_aliases' => ['Refractive index', 'Absolute refractive index', 'Laws of refraction']],
        'dependent' => ['grade' => 10, 'chapter' => 'The Human Eye and the Colourful World', 'concept' => 'Dispersion of light',
            'concept_aliases' => ['Dispersion of white light by a glass prism', 'Dispersion']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Dispersion is explained by the refractive index differing with colour, so red bends least and violet most. Without the index there is no quantity that differs and the spectrum is unexplained.',
        'evidence' => 'NCERT Science 10, Ch. Light — Reflection and Refraction §10.3 → Ch. The Human Eye §11.3',
    ],
    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Light — Reflection and Refraction', 'concept' => 'Lens formula',
            'concept_aliases' => ['Lens formula and magnification', 'Lens formula']],
        'dependent' => ['grade' => 10, 'chapter' => 'The Human Eye and the Colourful World', 'concept' => 'Defects of vision',
            'concept_aliases' => ['Defects of vision and their correction', 'Myopia', 'Hypermetropia']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Correcting myopia means computing the power of the lens that moves the far point to infinity. Every correction question is a lens-formula question with a sign convention.',
        'evidence' => 'NCERT Science 10, Ch. Light — Reflection and Refraction §10.3 → Ch. The Human Eye §11.2',
    ],
    [
        'strand' => 'SCI-CEL',
        'prerequisite' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life', 'concept' => 'Plasma membrane',
            'concept_aliases' => ['Plasma membrane or cell membrane', 'Cell membrane']],
        'dependent' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life', 'concept' => 'Osmosis',
            'concept_aliases' => ['Osmosis', 'Diffusion and osmosis']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Osmosis is defined as movement across a SELECTIVELY PERMEABLE membrane. The selectivity is a property of the plasma membrane, so the definition assumes it.',
        'evidence' => 'NCERT Science 9, Ch. The Fundamental Unit of Life §5.1',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Tissues', 'concept' => 'Xylem',
            'concept_aliases' => ['Complex permanent tissue', 'Xylem and phloem', 'Complex tissue']],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes', 'concept' => 'Transportation in plants',
            'concept_aliases' => ['Transport in plants', 'Transportation in plants', 'Transport of water']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Water rises through xylem and food moves through phloem. Class 10 names both as known tissues and never describes their structure, so a learner without Class 9 tissues has two unexplained words.',
        'evidence' => 'NCERT Science 9 Ch. Tissues §6.2 → Science 10 Ch. Life Processes §6.4',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 10, 'chapter' => 'How Do Organisms Reproduce', 'concept' => 'Sexual reproduction',
            'concept_aliases' => ['Sexual reproduction in flowering plants', 'Sexual mode of reproduction']],
        'dependent' => ['grade' => 10, 'chapter' => 'Heredity and Evolution', 'concept' => 'Mendel\'s laws of inheritance',
            'concept_aliases' => ['Mendels contribution', 'Rules for the inheritance of traits', 'Inheritance of traits']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'A monohybrid cross is sexual reproduction performed deliberately, and the 3:1 ratio counts the offspring it produces. Gametes and fertilisation have to be understood before a cross is interpretable.',
        'evidence' => 'NCERT Science 10, Ch. How Do Organisms Reproduce §8.3 → Ch. Heredity and Evolution §9.1',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Our Environment', 'concept' => 'Food chain',
            'concept_aliases' => ['Food chains and webs', 'Food chain and food web']],
        'dependent' => ['grade' => 10, 'chapter' => 'Our Environment', 'concept' => 'Biological magnification',
            'concept_aliases' => ['Biomagnification', 'Biological magnification']],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Magnification is the concentration of a pollutant rising AT EACH TROPHIC LEVEL. Without the chain there are no levels for it to rise through, and the idea collapses into "pollution is bad".',
        'evidence' => 'NCERT Science 10, Ch. Our Environment §15.1 → §15.2',
    ],

    // ══════════════════════════════════════════════════════════════════
    // CONTRASTS — pairs learners reliably conflate
    //
    // Symmetric: no direction, no gate, never projected into
    // pal_concept_relations. `rationale` states the confusion and what
    // distinguishes the pair, because that sentence is what a corrective has to
    // teach. A learner failing the second term of one of these pairs is usually
    // not missing it — they are using the first term's definition for it.
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-GRV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Gravitation', 'concept' => 'Mass'],
        'dependent' => ['grade' => 9, 'chapter' => 'Gravitation', 'concept' => 'Weight'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.9,
        'rationale' => 'The most common confusion in Class 9 physics. Mass is the quantity of matter and is the same everywhere; weight is the gravitational force on that mass and changes with g. Learners who use them interchangeably answer "a body weighs less on the Moon because it has less matter".',
        'evidence' => 'NCERT Science 9, Ch. Gravitation §10.3 (mass and weight)',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Distance',
            'concept_aliases' => ['Distance travelled', 'Distance and displacement']],
        'dependent' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Displacement'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.9,
        'rationale' => 'Distance is path length and is never negative; displacement is the straight line from start to finish and carries direction. A learner conflating them reports a non-zero displacement for a round trip.',
        'evidence' => 'NCERT Science 9, Ch. Motion §8.1',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Speed'],
        'dependent' => ['grade' => 9, 'chapter' => 'Motion', 'concept' => 'Velocity'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.85,
        'rationale' => 'Speed is a magnitude, velocity carries direction. The consequence learners miss is that a body moving in a circle at a steady rate has constant speed and changing velocity — and is therefore accelerating.',
        'evidence' => 'NCERT Science 9, Ch. Motion §8.2',
    ],
    [
        'strand' => 'SCI-HET',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Heat', 'concept' => 'Heat'],
        'dependent' => ['grade' => 7, 'chapter' => 'Heat', 'concept' => 'Temperature'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.85,
        'rationale' => 'Heat is energy in transit; temperature is how hot something is. Conflating them makes latent heat impossible to accept, because the learner cannot allow heat to be absorbed while temperature does not move.',
        'evidence' => 'NCERT Science 7, Ch. Heat',
    ],
    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Atoms and Molecules', 'concept' => 'Atom'],
        'dependent' => ['grade' => 9, 'chapter' => 'Atoms and Molecules', 'concept' => 'Molecule'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.8,
        'rationale' => 'An atom is the smallest particle of an element; a molecule is two or more atoms bonded together. Learners who blur them write O for oxygen gas and cannot balance a single equation involving it.',
        'evidence' => 'NCERT Science 9, Ch. Atoms and Molecules §3.2 → §3.3',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Is Matter Around Us Pure', 'concept' => 'Compound'],
        'dependent' => ['grade' => 9, 'chapter' => 'Is Matter Around Us Pure', 'concept' => 'Mixture'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.9,
        'rationale' => 'A compound has a fixed ratio and new properties; a mixture keeps its components\' properties and can be separated physically. This is the single distinction the whole chapter is organised around, and it is where most marks are lost.',
        'evidence' => 'NCERT Science 9, Ch. Is Matter Around Us Pure §2.3',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Physical and Chemical Changes', 'concept' => 'Physical change'],
        'dependent' => ['grade' => 7, 'chapter' => 'Physical and Chemical Changes', 'concept' => 'Chemical change'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.85,
        'rationale' => 'A physical change makes no new substance; a chemical change does. Learners misclassify dissolving as chemical and burning as physical, and the error propagates into every reaction they later meet.',
        'evidence' => 'NCERT Science 7, Ch. Physical and Chemical Changes',
    ],
    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Light — Reflection and Refraction', 'concept' => 'Reflection'],
        'dependent' => ['grade' => 10, 'chapter' => 'Light — Reflection and Refraction', 'concept' => 'Refraction'],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.8,
        'rationale' => 'Reflection bounces light off a surface; refraction bends it passing through. The chapter title names both, and learners apply the mirror formula to lens problems because they never separated the two.',
        'evidence' => 'NCERT Science 10, Ch. Light — Reflection and Refraction',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Resistors in series',
            'concept_aliases' => ['Series combination of resistors', 'Resistance of a system of resistors']],
        'dependent' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Resistors in parallel',
            'concept_aliases' => ['Parallel combination of resistors']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.85,
        'rationale' => 'Series resistances add; parallel reciprocals add. Learners apply the wrong rule far more often than they fail to apply either, so the remediation is telling the two apart, not re-teaching resistance.',
        'evidence' => 'NCERT Science 10, Ch. Electricity §12.5',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Respiration in Organisms', 'concept' => 'Breathing'],
        'dependent' => ['grade' => 7, 'chapter' => 'Respiration in Organisms', 'concept' => 'Respiration',
            'concept_aliases' => ['Cellular respiration', 'Why do we respire']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.9,
        'rationale' => 'Breathing is the mechanical exchange of gases; respiration is the cellular release of energy from glucose. Learners answer "respiration happens in the lungs", which blocks Class 10 Life Processes outright.',
        'evidence' => 'NCERT Science 7, Ch. Respiration in Organisms',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Life Processes', 'concept' => 'Photosynthesis',
            'concept_aliases' => ['Autotrophic nutrition']],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes', 'concept' => 'Respiration',
            'concept_aliases' => ['Cellular respiration', 'Aerobic respiration']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.85,
        'rationale' => 'The two are near-inverse: one stores energy and releases oxygen, the other releases energy and consumes it. Learners assert that plants respire only at night, because they treat the pair as alternatives rather than as concurrent processes.',
        'evidence' => 'NCERT Science 10, Ch. Life Processes §6.1, §6.2',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Reproduction in Plants', 'concept' => 'Pollination'],
        'dependent' => ['grade' => 7, 'chapter' => 'Reproduction in Plants', 'concept' => 'Fertilisation',
            'concept_aliases' => ['Fertilization']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.8,
        'rationale' => 'Pollination is pollen arriving at the stigma; fertilisation is the gametes fusing afterwards. Treating them as one event makes the Class 10 account of fruit and seed formation unreadable.',
        'evidence' => 'NCERT Science 7, Ch. Reproduction in Plants',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Matter in Our Surroundings', 'concept' => 'Evaporation'],
        'dependent' => ['grade' => 9, 'chapter' => 'Matter in Our Surroundings', 'concept' => 'Boiling',
            'concept_aliases' => ['Boiling point', 'Vaporisation']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.75,
        'rationale' => 'Evaporation is a surface phenomenon at any temperature; boiling is a bulk one at a fixed temperature. Learners explain drying clothes by boiling, which misses that evaporation causes cooling.',
        'evidence' => 'NCERT Science 9, Ch. Matter in Our Surroundings §1.4',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Potential difference',
            'concept_aliases' => ['Electric potential and potential difference', 'Voltage']],
        'dependent' => ['grade' => 10, 'chapter' => 'Electricity', 'concept' => 'Electric current',
            'concept_aliases' => ['Electric current and circuit', 'Current']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.8,
        'rationale' => 'Potential difference is what drives charge; current is the rate at which charge flows. Learners say a battery "supplies current", which makes Ohm\'s law read as a definition rather than a relationship.',
        'evidence' => 'NCERT Science 10, Ch. Electricity §12.2, §12.3',
    ],
    [
        'strand' => 'SCI-DIV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life', 'concept' => 'Prokaryotic cell',
            'concept_aliases' => ['Prokaryotes', 'Prokaryotic cells']],
        'dependent' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life', 'concept' => 'Eukaryotic cell',
            'concept_aliases' => ['Eukaryotes', 'Eukaryotic cells']],
        'type' => 'contrasts_with', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.8,
        'rationale' => 'The distinction is whether the nucleus is membrane-bound. It is the first split in the whole classification hierarchy, so a learner who blurs it cannot place any organism correctly.',
        'evidence' => 'NCERT Science 9, Ch. The Fundamental Unit of Life §5.2',
    ],

];
