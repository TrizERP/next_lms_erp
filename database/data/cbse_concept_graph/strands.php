<?php

/*
|--------------------------------------------------------------------------
| CBSE concept graph — strand catalogue, classes 6–10
|--------------------------------------------------------------------------
|
| Loaded by `php artisan cbse:graph-assign-strands`.
|
| A STRAND is one idea followed vertically across classes 6 to 10. CBSE is a
| spiral curriculum: the same thread is revisited at greater depth each year,
| so the real prerequisite chains run ACROSS grades while `lms_concept` is
| organised across chapters WITHIN a grade. Nothing in the estate recorded the
| vertical thread, which is why the 1,601 AI-proposed edges are chapter-local
| and contain 41 reciprocal pairs — a proposer that only ever sees one chapter
| cannot see the chain that chapter sits on.
|
| These seed as scope='global' / sub_institute_id=0 — board-level curriculum
| vocabulary, the documented exception to per-tenant scoping (CONTENT LAW C3).
| Every CBSE sub-institute reads the same rows.
|
| `code` IS PERMANENT. The Science and Mathematics edge files quote it verbatim
| and `pal_concept_strand_map` stores its resolved id. Rename one and every
| edge batch keyed to it stops resolving. Deprecate via `status` instead.
|
| `anchors` is documentation, not data: the NCERT chapters a strand is expected
| to draw from, written so a subject expert can check the assignment pass caught
| the right concepts and notice when a chapter is missing entirely. The class
| numbers are where each chapter sits in the NCERT sequence for 6–10.
|
| SMEs extend this file. Adding a strand is cheap; splitting one after edges
| reference it is not, so prefer a strand that is slightly too broad over two
| that overlap.
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Chemistry track
    // ══════════════════════════════════════════════════════════════════

    [
        'code' => 'SCI-MAT',
        'name' => 'Matter and its nature',
        'subject_scope' => 'Science',
        'discipline' => 'chemistry',
        'sort_order' => 10,
        'description' => 'From sorting everyday materials by observable property, through the '
            . 'particle model, to the formal distinction between a pure substance and a mixture. '
            . 'This is the strand every other chemistry strand stands on: a learner who does not '
            . 'hold the particle model cannot be taught what an atom is.',
        'anchors' => [
            'Sorting materials into groups (6)',
            'Separation of substances (6)',
            'Physical and chemical changes (7)',
            'Matter in our surroundings (9)',
            'Is matter around us pure (9)',
        ],
    ],

    [
        'code' => 'SCI-ATM',
        'name' => 'Atomic structure and periodicity',
        'subject_scope' => 'Science',
        'discipline' => 'chemistry',
        'sort_order' => 20,
        'description' => 'Atoms, molecules, the mole, sub-atomic structure and the periodic '
            . 'organisation that follows from it. Wholly a Class 9–10 strand, but it is the one '
            . 'that most depends on Mathematics — the mole is a ratio and molar mass is a '
            . 'proportion, so its cross-subject anchors matter more than its internal ones.',
        'anchors' => [
            'Atoms and molecules (9)',
            'Structure of the atom (9)',
            'Periodic classification of elements (10)',
        ],
    ],

    [
        'code' => 'SCI-RXN',
        'name' => 'Chemical reactions and substances',
        'subject_scope' => 'Science',
        'discipline' => 'chemistry',
        'sort_order' => 30,
        'description' => 'Reaction types and equations, then the four substance families CBSE '
            . 'treats in Class 10 — acids/bases/salts, metals and non-metals, and carbon '
            . 'compounds. Balancing an equation gates everything downstream of it, because every '
            . 'later chapter states its chemistry as equations.',
        'anchors' => [
            'Acids, bases and salts (7)',
            'Combustion and flame (8)',
            'Coal and petroleum (8)',
            'Materials: metals and non-metals (8)',
            'Chemical reactions and equations (10)',
            'Acids, bases and salts (10)',
            'Metals and non-metals (10)',
            'Carbon and its compounds (10)',
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Physics track
    // ══════════════════════════════════════════════════════════════════

    [
        'code' => 'SCI-MOT',
        'name' => 'Motion, force and measurement',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 40,
        'description' => 'Measuring distance, describing motion, and then explaining it with '
            . 'force. The Class 9 treatment is where physics stops being descriptive and starts '
            . 'being quantitative, so it is the single densest cross-subject junction in the '
            . 'whole graph: it needs ratio, graphs and linear equations from Mathematics at once.',
        'anchors' => [
            'Motion and measurement of distances (6)',
            'Motion and time (7)',
            'Force and pressure (8)',
            'Friction (8)',
            'Motion (9)',
            'Force and laws of motion (9)',
        ],
    ],

    [
        'code' => 'SCI-GRV',
        'name' => 'Gravitation, pressure and floatation',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 50,
        'description' => 'Universal gravitation, weight as a force, thrust and pressure, and '
            . 'Archimedes\' principle. Sits directly on top of force and cannot be taught before '
            . 'it; also the home of the mass/weight confusion, which is the most common '
            . 'misconception in Class 9 physics.',
        'anchors' => [
            'Force and pressure (8)',
            'Gravitation (9)',
            'Thrust and pressure, Archimedes\' principle, relative density (9)',
        ],
    ],

    [
        'code' => 'SCI-ENR',
        'name' => 'Work, energy and power',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 60,
        'description' => 'Work as force through distance, the two forms of mechanical energy, '
            . 'conservation, power, and the survey of energy sources in Class 10. Every '
            . 'definition in the strand is stated in terms of force and motion, so it is a '
            . 'downstream strand with almost no roots of its own.',
        'anchors' => [
            'Work and energy (9)',
            'Sources of energy (10)',
        ],
    ],

    [
        'code' => 'SCI-LGT',
        'name' => 'Light and optics',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 70,
        'description' => 'Rectilinear propagation and shadows, then reflection at plane and '
            . 'curved surfaces, refraction, lenses, and the human eye. The clearest example in '
            . 'the estate of the CBSE spiral: reflection appears in 6, 8 and 10, three times, '
            . 'each time with more formalism — which only a vertical strand makes visible.',
        'anchors' => [
            'Light, shadows and reflections (6)',
            'Light (8)',
            'Light: reflection and refraction (10)',
            'The human eye and the colourful world (10)',
        ],
    ],

    [
        'code' => 'SCI-SND',
        'name' => 'Sound',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 80,
        'description' => 'Production, propagation, the characteristics of a sound wave, '
            . 'reflection of sound and the range of hearing. Short and self-contained, but it '
            . 'needs the particle model from SCI-MAT: sound needing a medium is only explicable '
            . 'once matter is made of particles.',
        'anchors' => [
            'Sound (8)',
            'Sound (9)',
        ],
    ],

    [
        'code' => 'SCI-ELE',
        'name' => 'Electricity and magnetism',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 90,
        'description' => 'Circuits and conductors from Class 6, the heating and chemical effects '
            . 'of current, then the quantitative Class 10 treatment — Ohm\'s law, resistance in '
            . 'series and parallel, and the magnetic effect. Ohm\'s law is where direct '
            . 'proportion from Mathematics becomes physics, and series/parallel is where '
            . 'learners most often confuse two things the graph should hold apart.',
        'anchors' => [
            'Electricity and circuits (6)',
            'Fun with magnets (6)',
            'Electric current and its effects (7)',
            'Chemical effects of electric current (8)',
            'Some natural phenomena (8)',
            'Electricity (10)',
            'Magnetic effects of electric current (10)',
        ],
    ],

    [
        'code' => 'SCI-HET',
        'name' => 'Heat and temperature',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 100,
        'description' => 'Measuring temperature, modes of transfer, and the heat/temperature '
            . 'distinction. Small as a strand, but load-bearing: change of state in SCI-MAT and '
            . 'latent heat are both taught in its vocabulary.',
        'anchors' => [
            'Heat (7)',
        ],
    ],

    [
        'code' => 'SCI-AST',
        'name' => 'Earth, sky and the solar system',
        'subject_scope' => 'Science',
        'discipline' => 'physics',
        'sort_order' => 105,
        'description' => 'The moon and its phases, stars and constellations, the planets and '
            . 'artificial satellites. One NCERT chapter across the whole of 6–10, but it needs '
            . 'a strand of its own rather than being folded into gravitation: the Class 8 '
            . 'treatment is observational and precedes the Class 9 force law it is often '
            . 'assumed to depend on.',
        'anchors' => [
            'Stars and the solar system (8)',
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Biology track
    // ══════════════════════════════════════════════════════════════════

    [
        'code' => 'SCI-CEL',
        'name' => 'Cell and tissue organisation',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 110,
        'description' => 'The cell as the unit of life, its organelles, and the organisation of '
            . 'cells into tissues. The strictest vertical chain in biology — cell (8) then '
            . 'fundamental unit of life (9) then tissues (9) — and the foundation every Class 10 '
            . 'physiology chapter assumes without restating.',
        'anchors' => [
            'Cell: structure and functions (8)',
            'The fundamental unit of life (9)',
            'Tissues (9)',
        ],
    ],

    [
        'code' => 'SCI-DIV',
        'name' => 'Diversity and classification',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 120,
        'description' => 'Observing living things, the characteristics that separate them from '
            . 'the non-living, habitat and adaptation, and finally the formal hierarchy of '
            . 'classification. The Class 9 chapter is unteachable without the cell, because the '
            . 'first split in the hierarchy is prokaryote versus eukaryote.',
        'anchors' => [
            'The living organisms and their surroundings (6)',
            'Getting to know plants (6)',
            'Body movements (6)',
            'Diversity in living organisms (9)',
        ],
    ],

    [
        'code' => 'SCI-NUT',
        'name' => 'Nutrition and life processes',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 130,
        'description' => 'Nutrition, respiration, transport and excretion — first organism by '
            . 'organism in Classes 6–7, then unified as "life processes" in Class 10. The Class '
            . '10 chapter is a synthesis node: it legitimately has more prerequisites than any '
            . 'other concept in Science, and the validator should expect that.',
        'anchors' => [
            'Components of food (6)',
            'Nutrition in plants (7)',
            'Nutrition in animals (7)',
            'Respiration in organisms (7)',
            'Transportation in animals and plants (7)',
            'Life processes (10)',
        ],
    ],

    [
        'code' => 'SCI-CTR',
        'name' => 'Control and coordination',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 140,
        'description' => 'Nervous coordination, reflex action, the brain, and chemical '
            . 'coordination by hormones in animals and plants. Almost wholly Class 10, resting '
            . 'on tissue types from SCI-CEL — nervous tissue is taught in Class 9 and simply '
            . 'assumed a year later.',
        'anchors' => [
            'Control and coordination (10)',
        ],
    ],

    [
        'code' => 'SCI-REP',
        'name' => 'Reproduction and heredity',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 150,
        'description' => 'Asexual and sexual reproduction in plants and animals, adolescence, '
            . 'and then inheritance and evolution. Heredity is the deepest chain in Science: it '
            . 'needs reproduction, which needs the cell, and its whole vocabulary is cellular.',
        'anchors' => [
            'Reproduction in plants (7)',
            'Reproduction in animals (8)',
            'Reaching the age of adolescence (8)',
            'How do organisms reproduce (10)',
            'Heredity and evolution (10)',
        ],
    ],

    [
        'code' => 'SCI-ENV',
        'name' => 'Environment and ecology',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 160,
        'description' => 'Waste, water, air, forests, pollution, the biosphere\'s cycles, food '
            . 'chains and resource management. The broadest and least gated strand in Science — '
            . 'most of its edges are `builds_on` rather than `requires`, because a learner can '
            . 'meet a food chain without having met a cell.',
        'anchors' => [
            'Garbage in, garbage out (6)',
            'Water (6)',
            'Air around us (6)',
            'Forests: our lifeline (7)',
            'Wastewater story (7)',
            'Pollution of air and water (8)',
            'Conservation of plants and animals (8)',
            'Natural resources (9)',
            'Our environment (10)',
            'Management of natural resources (10)',
        ],
    ],

    [
        'code' => 'SCI-HLT',
        'name' => 'Health and disease',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 170,
        'description' => 'What health is, what causes disease, the distinction between infectious '
            . 'and non-infectious causes, and prevention. Depends on microorganisms from '
            . 'SCI-AGR and on nutrition from SCI-NUT, which makes it one of the few genuinely '
            . 'cross-strand biology chapters.',
        'anchors' => [
            'Components of food (6)',
            'Why do we fall ill (9)',
        ],
    ],

    [
        'code' => 'SCI-AGR',
        'name' => 'Food production and materials',
        'subject_scope' => 'Science',
        'discipline' => 'biology',
        'sort_order' => 180,
        'description' => 'Crop production, microorganisms as friend and foe, improvement in food '
            . 'resources, and the fibre/plastic material chapters that sit beside them in the '
            . 'NCERT sequence. Applied rather than foundational: it draws on nutrition and on '
            . 'the matter strand and is a prerequisite for very little.',
        'anchors' => [
            'Fibre to fabric (6)',
            'Fibre to fabric (7)',
            'Crop production and management (8)',
            'Microorganisms: friend and foe (8)',
            'Synthetic fibres and plastics (8)',
            'Improvement in food resources (9)',
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS
    //
    // Authored now rather than with the Mathematics batch, because the Science
    // edge file carries ~40 `applies` anchors that point INTO these strands
    // (ratio -> the mole, graphs -> velocity-time, direct proportion -> Ohm's
    // law). An anchor whose target strand does not exist cannot be imported.
    // ══════════════════════════════════════════════════════════════════

    [
        'code' => 'MAT-NUM',
        'name' => 'Number system',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 210,
        'description' => 'Whole numbers, factors and multiples, integers, fractions and decimals, '
            . 'rational numbers, exponents, squares and cubes, and finally the real numbers. The '
            . 'longest unbroken vertical chain in the entire 6–10 curriculum.',
        'anchors' => [
            'Knowing our numbers (6)', 'Whole numbers (6)', 'Playing with numbers (6)',
            'Integers (6)', 'Fractions (6)', 'Decimals (6)',
            'Integers (7)', 'Fractions and decimals (7)', 'Rational numbers (7, 8)',
            'Exponents and powers (7, 8)', 'Squares and square roots (8)', 'Cubes and cube roots (8)',
            'Real numbers (10)',
        ],
    ],

    [
        'code' => 'MAT-ALG',
        'name' => 'Algebra',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 220,
        'description' => 'Letters for numbers, expressions, identities and factorisation, '
            . 'equations in one and two variables, polynomials, quadratics and progressions. '
            . 'Supplies the manipulation every quantitative science chapter assumes.',
        'anchors' => [
            'Algebra (6)', 'Algebraic expressions (7)', 'Simple equations (7)',
            'Linear equations in one variable (8)', 'Algebraic expressions and identities (8)',
            'Factorisation (8)', 'Polynomials (9, 10)', 'Linear equations in two variables (9)',
            'Pair of linear equations in two variables (10)', 'Quadratic equations (10)',
            'Arithmetic progressions (10)',
        ],
    ],

    [
        'code' => 'MAT-COM',
        'name' => 'Ratio, proportion and commercial arithmetic',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 230,
        'description' => 'Ratio and proportion, unitary method, percentage, profit and loss, '
            . 'interest, and direct and inverse variation. The highest-value strand in the whole '
            . 'graph for cross-subject purposes: speed, density, molar mass, concentration and '
            . 'Ohm\'s law are all proportions wearing a science label.',
        'anchors' => [
            'Ratio and proportion (6)', 'Comparing quantities (7)', 'Comparing quantities (8)',
            'Direct and inverse proportions (8)',
        ],
    ],

    [
        'code' => 'MAT-GEO',
        'name' => 'Geometry',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 240,
        'description' => 'Points, lines and angles, triangles and their properties, congruence, '
            . 'quadrilaterals, circles, constructions and similarity. Angle measurement here is '
            . 'what makes the laws of reflection and refraction statable in Science.',
        'anchors' => [
            'Basic geometrical ideas (6)', 'Understanding elementary shapes (6)',
            'Lines and angles (7, 9)', 'The triangle and its properties (7)',
            'Congruence of triangles (7)', 'Understanding quadrilaterals (8)',
            'Triangles (9, 10)', 'Quadrilaterals (9)', 'Circles (9, 10)',
            'Constructions (8, 9, 10)',
        ],
    ],

    [
        'code' => 'MAT-MEN',
        'name' => 'Mensuration',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 250,
        'description' => 'Perimeter, area, surface area and volume, from rectangles in Class 6 '
            . 'to cones, spheres and frusta in Class 10. Volume from this strand is what makes '
            . 'density computable in Science, and area is what makes pressure computable.',
        'anchors' => [
            'Mensuration (6, 8)', 'Perimeter and area (7)', 'Heron\'s formula (9)',
            'Surface areas and volumes (9, 10)', 'Areas related to circles (10)',
        ],
    ],

    [
        'code' => 'MAT-COO',
        'name' => 'Coordinate geometry and graphs',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 260,
        'description' => 'Reading and plotting on axes, the Cartesian plane, and distance and '
            . 'section formulae. Graph reading from Class 8 is the unstated prerequisite for '
            . 'every distance-time and velocity-time graph in Class 9 physics.',
        'anchors' => [
            'Introduction to graphs (8)', 'Coordinate geometry (9, 10)',
        ],
    ],

    [
        'code' => 'MAT-TRI',
        'name' => 'Trigonometry',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 270,
        'description' => 'Ratios of the sides of a right triangle, identities, and heights and '
            . 'distances. Entirely Class 10 and entirely downstream: it needs similarity of '
            . 'triangles and the ratio idea before a single definition makes sense.',
        'anchors' => [
            'Introduction to trigonometry (10)', 'Some applications of trigonometry (10)',
        ],
    ],

    [
        'code' => 'MAT-DAT',
        'name' => 'Data handling and statistics',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 280,
        'description' => 'Collecting, organising and representing data, then the measures of '
            . 'central tendency for raw and for grouped data. Home of the mean/median/mode '
            . 'confusion, which is worth recording as a contrast rather than a prerequisite.',
        'anchors' => [
            'Data handling (6, 7, 8)', 'Statistics (9, 10)',
        ],
    ],

    [
        'code' => 'MAT-PRO',
        'name' => 'Probability',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 290,
        'description' => 'Empirical probability in Class 9 and the classical definition in Class '
            . '10. Short, and almost entirely dependent on fractions and on data handling.',
        'anchors' => [
            'Probability (9, 10)',
        ],
    ],

    [
        'code' => 'MAT-SYM',
        'name' => 'Symmetry and spatial visualisation',
        'subject_scope' => 'Mathematics',
        'discipline' => null,
        'sort_order' => 300,
        'description' => 'Lines of symmetry, rotational symmetry, and reading solid shapes '
            . 'through their nets and views. Feeds mensuration — a learner who cannot see a net '
            . 'cannot derive a surface area.',
        'anchors' => [
            'Symmetry (6, 7)', 'Visualising solid shapes (7, 8)',
        ],
    ],

];
