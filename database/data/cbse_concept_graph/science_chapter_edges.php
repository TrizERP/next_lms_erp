<?php

/*
|--------------------------------------------------------------------------
| CBSE concept graph — Science chapter prerequisites, classes 6–10
|--------------------------------------------------------------------------
|
| Loaded by `php artisan cbse:graph-import --level=chapter`.
| Written into `pal_learning_relations` (chapter -> chapter), which already
| exists for exactly this: prerequisite edges whose endpoints are not concepts.
|
| WHY THE CHAPTER BACKBONE IS AUTHORED FIRST
| Concept names in `lms_concept` were produced by the AI extraction pipeline
| from uploaded textbooks, so they vary by estate and by extraction run. NCERT
| chapter names do not. The chapter graph therefore resolves on any CBSE estate
| on the day it is imported, and it is the frame the concept-level edges are
| authored inside: a concept edge that crosses a chapter pair with no chapter
| edge is a signal to re-check one or the other.
|
| It is also what the coherence map actually draws at its coarsest level, so
| this file alone fixes chapter sequencing — the thing a teacher sees first.
|
| DIRECTION
| `prerequisite` is taught first. `dependent` needs it. Read each entry as
| "dependent requires prerequisite".
|
| EVERY ENTRY CARRIES A RATIONALE, AND THE RULE FOR WRITING ONE IS STRICT:
| say what the learner LITERALLY CANNOT DO without the prerequisite. Not "it is
| related to", not "it is foundational" — a specific act that fails. An entry
| whose rationale could be pasted onto any other edge is not a rationale and
| the importer rejects it.
|
| RELATION TYPES
|   requires     — hard gate; the dependent is not teachable without it
|   builds_on    — strongly assumed, but a teacher can recover it in the lesson
|   spirals_from — the same idea at an earlier class; the CBSE spiral
|   applies      — cross-subject transfer (the Mathematics anchors at the end)
|
| NOT DERIVED FROM `semantic_intelligence.prerequisites`, and not from the 1,601
| AI-proposed rows in `pal_concept_relations`. Those are unreviewed chapter-local
| drafts containing 41 reciprocal pairs; `cbse:graph-report --compare-ai` reports
| where this file and they disagree, after the fact, as a signal about both.
|
| Chapter names are the NCERT titles; the importer matches them normalised
| (case, punctuation and any chapter-number prefix are ignored).
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // SCI-MAT — Matter and its nature
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Sorting Materials into Groups'],
        'dependent' => ['grade' => 6, 'chapter' => 'Separation of Substances'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Choosing a separation technique means choosing the property to separate ON — solubility, size, density. A learner who cannot yet sort materials by property has no basis on which to pick winnowing over sieving.',
        'evidence' => 'NCERT Science 6, Ch. Sorting Materials into Groups → Ch. Separation of Substances',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Sorting Materials into Groups'],
        'dependent' => ['grade' => 6, 'chapter' => 'Changes Around Us'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'A change is described as a change IN a property. Without the vocabulary of properties, a learner can only say something "looks different" and cannot say what changed.',
        'evidence' => 'NCERT Science 6, Ch. Changes Around Us',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Changes Around Us'],
        'dependent' => ['grade' => 7, 'chapter' => 'Physical and Chemical Changes'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Class 7 asks the learner to CLASSIFY a change as physical or chemical. That question is unaskable until reversible and irreversible change have been met informally in Class 6.',
        'evidence' => 'NCERT Science 6 Ch. Changes Around Us → Science 7 Ch. Physical and Chemical Changes',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Heat'],
        'dependent' => ['grade' => 9, 'chapter' => 'Matter in Our Surroundings'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Change of state is stated as a response to temperature, and latent heat is defined as heat absorbed with NO temperature change. Both sentences are meaningless to a learner who has not separated heat from temperature.',
        'evidence' => 'NCERT Science 7 Ch. Heat → Science 9 Ch. Matter in Our Surroundings (latent heat)',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Sorting Materials into Groups'],
        'dependent' => ['grade' => 9, 'chapter' => 'Matter in Our Surroundings'],
        'type' => 'spirals_from', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Class 9 explains the observable properties sorted in Class 6 — hardness, compressibility, flow — in terms of particle spacing. The explanation lands only if the observations are already familiar.',
        'evidence' => 'NCERT Science 6 Ch. Sorting Materials into Groups → Science 9 Ch. Matter in Our Surroundings',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Matter in Our Surroundings'],
        'dependent' => ['grade' => 9, 'chapter' => 'Is Matter Around Us Pure'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'A mixture is defined as two or more substances whose PARTICLES are intermixed without combining. The whole chapter is stated in the particle model the previous chapter establishes.',
        'evidence' => 'NCERT Science 9, Ch. Matter in Our Surroundings → Ch. Is Matter Around Us Pure',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Separation of Substances'],
        'dependent' => ['grade' => 9, 'chapter' => 'Is Matter Around Us Pure'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Class 9 re-teaches the same techniques — filtration, evaporation, distillation, chromatography — and now asks WHY each works. The procedure has to be known before its justification can be the lesson.',
        'evidence' => 'NCERT Science 6 Ch. Separation of Substances → Science 9 Ch. Is Matter Around Us Pure',
    ],
    [
        'strand' => 'SCI-MAT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Physical and Chemical Changes'],
        'dependent' => ['grade' => 9, 'chapter' => 'Is Matter Around Us Pure'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.7,
        'rationale' => 'Separating a mixture must leave its components unchanged; separating a compound must not. The learner needs the physical/chemical distinction to see why one is a separation and the other a reaction.',
        'evidence' => 'NCERT Science 7 Ch. Physical and Chemical Changes → Science 9 Ch. Is Matter Around Us Pure',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-ATM — Atomic structure and periodicity
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Is Matter Around Us Pure'],
        'dependent' => ['grade' => 9, 'chapter' => 'Atoms and Molecules'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'The laws of chemical combination are stated about ELEMENTS and COMPOUNDS. A learner who cannot yet tell a compound from a mixture cannot see what the law of constant proportions is even claiming.',
        'evidence' => 'NCERT Science 9, Ch. Is Matter Around Us Pure → Ch. Atoms and Molecules',
    ],
    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Atoms and Molecules'],
        'dependent' => ['grade' => 9, 'chapter' => 'Structure of the Atom'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'This chapter opens by contradicting Dalton — the atom is divisible after all. There is nothing to contradict, and no reason to care, unless Dalton\'s indivisible atom has been taught first.',
        'evidence' => 'NCERT Science 9, Ch. Atoms and Molecules → Ch. Structure of the Atom',
    ],
    [
        'strand' => 'SCI-ATM',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Structure of the Atom'],
        'dependent' => ['grade' => 10, 'chapter' => 'Periodic Classification of Elements'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'The modern periodic law orders elements by atomic number, and a period is a shell while a group shares valence electrons. Every organising idea in the chapter is a fact about electronic configuration.',
        'evidence' => 'NCERT Science 9 Ch. Structure of the Atom → Science 10 Ch. Periodic Classification of Elements',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-RXN — Chemical reactions and substances
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Atoms and Molecules'],
        'dependent' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Balancing an equation IS applying conservation of mass to a formula. Both halves — writing the formula from valency, and the conservation law being balanced against — are taught in Class 9.',
        'evidence' => 'NCERT Science 9 Ch. Atoms and Molecules → Science 10 Ch. Chemical Reactions and Equations',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Physical and Chemical Changes'],
        'dependent' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations'],
        'type' => 'spirals_from', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.7,
        'rationale' => 'The observable signs a reaction has occurred — gas, precipitate, colour, temperature change — are introduced in Class 7 and assumed without restatement in Class 10.',
        'evidence' => 'NCERT Science 7 Ch. Physical and Chemical Changes → Science 10 Ch. Chemical Reactions and Equations',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations'],
        'dependent' => ['grade' => 10, 'chapter' => 'Acids, Bases and Salts'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Neutralisation, the action of acids on metals, and salt preparation are every one of them stated as balanced equations. A learner who cannot balance cannot follow a single worked example.',
        'evidence' => 'NCERT Science 10, Ch. Chemical Reactions and Equations → Ch. Acids, Bases and Salts',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Acids, Bases and Salts'],
        'dependent' => ['grade' => 10, 'chapter' => 'Acids, Bases and Salts'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Class 7 gives the indicator test and the idea of neutralisation as observation. Class 10 explains both by hydrogen and hydroxide ions — the explanation needs the observation it is explaining.',
        'evidence' => 'NCERT Science 7 Ch. Acids, Bases and Salts → Science 10 Ch. Acids, Bases and Salts',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations'],
        'dependent' => ['grade' => 10, 'chapter' => 'Metals and Non-metals'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'The reactivity series is built by comparing displacement reactions, and every extraction step is a reduction. Both are the reaction types classified in the opening chapter.',
        'evidence' => 'NCERT Science 10, Ch. Chemical Reactions and Equations → Ch. Metals and Non-metals',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Structure of the Atom'],
        'dependent' => ['grade' => 10, 'chapter' => 'Metals and Non-metals'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Ionic bonding is taught as the transfer of valence electrons to complete an octet. Without electronic configuration there is nothing to transfer and no octet to complete.',
        'evidence' => 'NCERT Science 9 Ch. Structure of the Atom → Science 10 Ch. Metals and Non-metals (ionic compounds)',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Materials: Metals and Non-Metals'],
        'dependent' => ['grade' => 10, 'chapter' => 'Metals and Non-metals'],
        'type' => 'spirals_from', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.7,
        'rationale' => 'The physical properties that separate the two families — lustre, malleability, conductivity — are established in Class 8 and used in Class 10 as known, while the chemistry becomes the lesson.',
        'evidence' => 'NCERT Science 8 Ch. Materials: Metals and Non-Metals → Science 10 Ch. Metals and Non-metals',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Structure of the Atom'],
        'dependent' => ['grade' => 10, 'chapter' => 'Carbon and Its Compounds'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Catenation and tetravalency are consequences of carbon having four valence electrons. The entire chapter is a deduction from one electronic configuration.',
        'evidence' => 'NCERT Science 9 Ch. Structure of the Atom → Science 10 Ch. Carbon and Its Compounds',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Chemical Reactions and Equations'],
        'dependent' => ['grade' => 10, 'chapter' => 'Carbon and Its Compounds'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Combustion, oxidation, addition and substitution are presented as named reaction types with equations. The classification and the balancing both come from the first chapter.',
        'evidence' => 'NCERT Science 10, Ch. Chemical Reactions and Equations → Ch. Carbon and Its Compounds',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Combustion and Flame'],
        'dependent' => ['grade' => 10, 'chapter' => 'Carbon and Its Compounds'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Complete versus incomplete combustion, and why a flame is sooty, are met in Class 8 and re-used in Class 10 to distinguish saturated from unsaturated hydrocarbons by their flame.',
        'evidence' => 'NCERT Science 8 Ch. Combustion and Flame → Science 10 Ch. Carbon and Its Compounds',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Coal and Petroleum'],
        'dependent' => ['grade' => 10, 'chapter' => 'Carbon and Its Compounds'],
        'type' => 'builds_on', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.4,
        'rationale' => 'Class 8 gives hydrocarbons as fuels and as a finite resource; Class 10 gives them a structure. The earlier chapter supplies the examples the later one explains, but the explanation stands without it.',
        'evidence' => 'NCERT Science 8 Ch. Coal and Petroleum → Science 10 Ch. Carbon and Its Compounds',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Acids, Bases and Salts'],
        'dependent' => ['grade' => 10, 'chapter' => 'Metals and Non-metals'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Metal-oxide basicity and non-metal-oxide acidity are the organising contrast of the chapter, and both are stated in the acid/base vocabulary just established.',
        'evidence' => 'NCERT Science 10, Ch. Acids, Bases and Salts → Ch. Metals and Non-metals',
    ],
    [
        'strand' => 'SCI-RXN',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Chemical Effects of Electric Current'],
        'dependent' => ['grade' => 10, 'chapter' => 'Metals and Non-metals'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Electrolytic refining and electroplating in Class 10 are the Class 8 electrolysis demonstration done for an industrial purpose; the apparatus and the observation are assumed.',
        'evidence' => 'NCERT Science 8 Ch. Chemical Effects of Electric Current → Science 10 Ch. Metals and Non-metals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-MOT — Motion, force and measurement
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Motion and Measurement of Distances'],
        'dependent' => ['grade' => 7, 'chapter' => 'Motion and Time'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Speed is distance divided by time. The learner must already be able to measure a distance and choose a unit before a rate built on one means anything.',
        'evidence' => 'NCERT Science 6 Ch. Motion and Measurement of Distances → Science 7 Ch. Motion and Time',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Motion and Time'],
        'dependent' => ['grade' => 9, 'chapter' => 'Motion'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Class 9 re-states speed as velocity by adding direction, and builds acceleration on top. Both moves are modifications of the Class 7 definition and are unintelligible without it.',
        'evidence' => 'NCERT Science 7 Ch. Motion and Time → Science 9 Ch. Motion',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Force and Pressure'],
        'dependent' => ['grade' => 9, 'chapter' => 'Force and Laws of Motion'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Class 8 gives force as a push or pull that changes the state of motion, and the effect of two forces acting together. Newton\'s first law is the formal version of exactly that sentence.',
        'evidence' => 'NCERT Science 8 Ch. Force and Pressure → Science 9 Ch. Force and Laws of Motion',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion'],
        'dependent' => ['grade' => 9, 'chapter' => 'Force and Laws of Motion'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'The second law is F = ma. Acceleration is defined in the previous chapter, so without it the central equation of the chapter has an undefined term in it.',
        'evidence' => 'NCERT Science 9, Ch. Motion → Ch. Force and Laws of Motion',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Friction'],
        'dependent' => ['grade' => 9, 'chapter' => 'Force and Laws of Motion'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'The first law is taught against the everyday intuition that motion needs a continuous push. Friction is the reason that intuition exists, and naming it is how the law is made believable.',
        'evidence' => 'NCERT Science 8 Ch. Friction → Science 9 Ch. Force and Laws of Motion',
    ],
    [
        'strand' => 'SCI-MOT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Motion and Time'],
        'dependent' => ['grade' => 8, 'chapter' => 'Force and Pressure'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'A force is introduced by what it does to motion — starts it, stops it, changes its speed or direction. The learner needs a working account of motion before a cause of change in it can be described.',
        'evidence' => 'NCERT Science 7 Ch. Motion and Time → Science 8 Ch. Force and Pressure',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-GRV — Gravitation, pressure and floatation
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-GRV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Force and Laws of Motion'],
        'dependent' => ['grade' => 9, 'chapter' => 'Gravitation'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Weight is defined as mass times g — a force. Free fall is motion under a single unbalanced force. Both statements are in the language of the laws of motion.',
        'evidence' => 'NCERT Science 9, Ch. Force and Laws of Motion → Ch. Gravitation',
    ],
    [
        'strand' => 'SCI-GRV',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Force and Pressure'],
        'dependent' => ['grade' => 9, 'chapter' => 'Gravitation'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'The second half of the Class 9 chapter is thrust, pressure and buoyancy. Pressure as force per unit area is defined in Class 8 and is used here without being re-derived.',
        'evidence' => 'NCERT Science 8 Ch. Force and Pressure → Science 9 Ch. Gravitation (thrust and pressure)',
    ],
    [
        'strand' => 'SCI-GRV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion'],
        'dependent' => ['grade' => 9, 'chapter' => 'Gravitation'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Free-fall problems are the equations of motion with a = g substituted in. Without those three equations the numerical half of the chapter cannot be attempted.',
        'evidence' => 'NCERT Science 9, Ch. Motion → Ch. Gravitation (free fall)',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-ENR — Work, energy and power
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-ENR',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Force and Laws of Motion'],
        'dependent' => ['grade' => 9, 'chapter' => 'Work and Energy'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Work is defined as force times displacement in the direction of the force. The definition opens with a term the previous chapter supplies.',
        'evidence' => 'NCERT Science 9, Ch. Force and Laws of Motion → Ch. Work and Energy',
    ],
    [
        'strand' => 'SCI-ENR',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion'],
        'dependent' => ['grade' => 9, 'chapter' => 'Work and Energy'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Kinetic energy is derived by substituting an equation of motion into the work expression, and the derivation is the lesson. Displacement and velocity must both already be secure.',
        'evidence' => 'NCERT Science 9, Ch. Motion → Ch. Work and Energy (derivation of ½mv²)',
    ],
    [
        'strand' => 'SCI-ENR',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Gravitation'],
        'dependent' => ['grade' => 9, 'chapter' => 'Work and Energy'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Potential energy is mgh — work done against gravity. The value of g and the fact that weight is a force both come from the gravitation chapter.',
        'evidence' => 'NCERT Science 9, Ch. Gravitation → Ch. Work and Energy (potential energy)',
    ],
    [
        'strand' => 'SCI-ENR',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Work and Energy'],
        'dependent' => ['grade' => 10, 'chapter' => 'Sources of Energy'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Every source in the chapter is judged by how much energy it delivers and how efficiently it converts one form to another. Both judgements need energy, its forms and its conservation already defined.',
        'evidence' => 'NCERT Science 9 Ch. Work and Energy → Science 10 Ch. Sources of Energy',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-LGT — Light and optics
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Light, Shadows and Reflections'],
        'dependent' => ['grade' => 7, 'chapter' => 'Light'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Class 7 states the law of reflection as an equality of angles. Rectilinear propagation — light travelling in straight lines — is what makes a ray diagram legitimate, and it is established in Class 6.',
        'evidence' => 'NCERT Science 6 Ch. Light, Shadows and Reflections → Science 7 Ch. Light',
    ],
    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Light'],
        'dependent' => ['grade' => 8, 'chapter' => 'Light'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Class 8 adds multiple reflection, dispersion and the structure of the eye on top of the reflection law. It restates none of it.',
        'evidence' => 'NCERT Science 7 Ch. Light → Science 8 Ch. Light',
    ],
    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Light'],
        'dependent' => ['grade' => 10, 'chapter' => 'Light — Reflection and Refraction'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Class 10 makes the same phenomena quantitative — mirror and lens formulae, sign conventions, magnification. Every formula is a numerical statement of a diagram the learner should already be able to draw.',
        'evidence' => 'NCERT Science 8 Ch. Light → Science 10 Ch. Light — Reflection and Refraction',
    ],
    [
        'strand' => 'SCI-LGT',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Light — Reflection and Refraction'],
        'dependent' => ['grade' => 10, 'chapter' => 'The Human Eye and the Colourful World'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Correcting myopia is choosing a lens of the right power, and a rainbow is dispersion by refraction. Both the lens formula and refraction itself come from the preceding chapter.',
        'evidence' => 'NCERT Science 10, Ch. Light — Reflection and Refraction → Ch. The Human Eye and the Colourful World',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-SND — Sound
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-SND',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Sound'],
        'dependent' => ['grade' => 9, 'chapter' => 'Sound'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Class 8 gives vibration, frequency, amplitude and the need for a medium as observations. Class 9 turns them into a wave with a speed and an equation, and assumes every one of them.',
        'evidence' => 'NCERT Science 8 Ch. Sound → Science 9 Ch. Sound',
    ],
    [
        'strand' => 'SCI-SND',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Matter in Our Surroundings'],
        'dependent' => ['grade' => 9, 'chapter' => 'Sound'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Sound is taught as a compression wave — particles crowding and spreading. Why it travels fastest in solids and not at all in vacuum is a statement about particle spacing, so the particle model has to be in place.',
        'evidence' => 'NCERT Science 9, Ch. Matter in Our Surroundings → Ch. Sound',
    ],
    [
        'strand' => 'SCI-SND',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Motion'],
        'dependent' => ['grade' => 9, 'chapter' => 'Sound'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Every echo and SONAR problem in the chapter is a speed-distance-time calculation with a there-and-back doubling. The doubling is the new idea; the calculation is not.',
        'evidence' => 'NCERT Science 9, Ch. Motion → Ch. Sound (echo and SONAR numericals)',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-ELE — Electricity and magnetism
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Electricity and Circuits'],
        'dependent' => ['grade' => 7, 'chapter' => 'Electric Current and Its Effects'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'The effects of a current are demonstrated in circuits the learner has to build and read. Closed circuits, conductors and the circuit symbols are all Class 6.',
        'evidence' => 'NCERT Science 6 Ch. Electricity and Circuits → Science 7 Ch. Electric Current and Its Effects',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Electric Current and Its Effects'],
        'dependent' => ['grade' => 8, 'chapter' => 'Chemical Effects of Electric Current'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'The chemical effect is presented as a third effect alongside the heating and magnetic ones. It is defined by contrast with two things taught the year before.',
        'evidence' => 'NCERT Science 7 Ch. Electric Current and Its Effects → Science 8 Ch. Chemical Effects of Electric Current',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Electric Current and Its Effects'],
        'dependent' => ['grade' => 10, 'chapter' => 'Electricity'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Class 10 quantifies what Class 7 demonstrated: the heating effect becomes I²Rt. The qualitative behaviour has to be familiar before the formula describing it is anything but symbols.',
        'evidence' => 'NCERT Science 7 Ch. Electric Current and Its Effects → Science 10 Ch. Electricity',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Structure of the Atom'],
        'dependent' => ['grade' => 10, 'chapter' => 'Electricity'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.7,
        'rationale' => 'Current is defined as a flow of charge, and in a metal the charge carriers are electrons. That identification is borrowed from the atom chapter and never justified in the electricity one.',
        'evidence' => 'NCERT Science 9 Ch. Structure of the Atom → Science 10 Ch. Electricity',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Electricity'],
        'dependent' => ['grade' => 10, 'chapter' => 'Magnetic Effects of Electric Current'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'The field of a solenoid depends on the current through it, and the motor and generator are explained with circuits carrying a known current. Current, potential difference and the circuit have to be secure first.',
        'evidence' => 'NCERT Science 10, Ch. Electricity → Ch. Magnetic Effects of Electric Current',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Fun with Magnets'],
        'dependent' => ['grade' => 10, 'chapter' => 'Magnetic Effects of Electric Current'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Field lines are drawn from pole to pole and never cross — a rule that only makes sense to a learner who already knows magnets have two poles that attract and repel.',
        'evidence' => 'NCERT Science 6 Ch. Fun with Magnets → Science 10 Ch. Magnetic Effects of Electric Current',
    ],
    [
        'strand' => 'SCI-ELE',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Electricity and Circuits'],
        'dependent' => ['grade' => 8, 'chapter' => 'Some Natural Phenomena'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.55,
        'rationale' => 'Lightning is explained as an electric discharge between accumulated charges. The learner needs charge and conduction to have been met, even informally, for that explanation to be more than a label.',
        'evidence' => 'NCERT Science 6 Ch. Electricity and Circuits → Science 8 Ch. Some Natural Phenomena',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-CEL — Cell and tissue organisation
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-CEL',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Cell — Structure and Functions'],
        'dependent' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Class 9 adds the organelles and the plasma membrane\'s behaviour to a cell the learner is expected to be able to draw and label already.',
        'evidence' => 'NCERT Science 8 Ch. Cell — Structure and Functions → Science 9 Ch. The Fundamental Unit of Life',
    ],
    [
        'strand' => 'SCI-CEL',
        'prerequisite' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life'],
        'dependent' => ['grade' => 9, 'chapter' => 'Tissues'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'A tissue is defined as a group of cells of similar structure performing one function. The definition is unreadable to a learner who cannot yet say what a cell\'s structure consists of.',
        'evidence' => 'NCERT Science 9, Ch. The Fundamental Unit of Life → Ch. Tissues',
    ],
    [
        'strand' => 'SCI-CEL',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Tissues'],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Every organ in the chapter — the alveolus, the nephron, the xylem — is named as a tissue arrangement. Class 10 never re-teaches what epithelial or vascular tissue is.',
        'evidence' => 'NCERT Science 9 Ch. Tissues → Science 10 Ch. Life Processes',
    ],
    [
        'strand' => 'SCI-CEL',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Tissues'],
        'dependent' => ['grade' => 10, 'chapter' => 'Control and Coordination'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'The neuron is taught in Class 9 as a type of nervous tissue. Class 10 opens with the reflex arc and assumes the neuron as known anatomy.',
        'evidence' => 'NCERT Science 9 Ch. Tissues → Science 10 Ch. Control and Coordination',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-DIV — Diversity and classification
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-DIV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'The Living Organisms and Their Surroundings'],
        'dependent' => ['grade' => 9, 'chapter' => 'Diversity in Living Organisms'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Classification begins from the characteristics that mark something as living, and from habitat and adaptation. All three are established in Class 6 and used in Class 9 as the basis for grouping.',
        'evidence' => 'NCERT Science 6 Ch. The Living Organisms and Their Surroundings → Science 9 Ch. Diversity in Living Organisms',
    ],
    [
        'strand' => 'SCI-DIV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life'],
        'dependent' => ['grade' => 9, 'chapter' => 'Diversity in Living Organisms'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'The first and deepest split in the hierarchy is prokaryote against eukaryote — a fact about whether the nucleus is membrane-bound. Without the cell there is no first split.',
        'evidence' => 'NCERT Science 9, Ch. The Fundamental Unit of Life → Ch. Diversity in Living Organisms',
    ],
    [
        'strand' => 'SCI-DIV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Getting to Know Plants'],
        'dependent' => ['grade' => 9, 'chapter' => 'Diversity in Living Organisms'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'The plant kingdom is divided on the presence of vascular tissue, seeds and flowers. Root, stem, leaf and flower are named in Class 6 and used as known parts in Class 9.',
        'evidence' => 'NCERT Science 6 Ch. Getting to Know Plants → Science 9 Ch. Diversity in Living Organisms',
    ],
    [
        'strand' => 'SCI-DIV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Body Movements'],
        'dependent' => ['grade' => 9, 'chapter' => 'Diversity in Living Organisms'],
        'type' => 'builds_on', 'necessity' => 'helpful', 'gates' => false, 'strength' => 0.4,
        'rationale' => 'The vertebrate/invertebrate division rests on the backbone, which Class 6 introduces through movement and the skeleton. Useful grounding, but Class 9 does re-state the criterion.',
        'evidence' => 'NCERT Science 6 Ch. Body Movements → Science 9 Ch. Diversity in Living Organisms',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-NUT — Nutrition and life processes
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Components of Food'],
        'dependent' => ['grade' => 7, 'chapter' => 'Nutrition in Animals'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Digestion is taught as the breakdown of specific nutrients by specific enzymes. Carbohydrate, protein and fat have to be nameable before the enzyme acting on each can be.',
        'evidence' => 'NCERT Science 6 Ch. Components of Food → Science 7 Ch. Nutrition in Animals',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Getting to Know Plants'],
        'dependent' => ['grade' => 7, 'chapter' => 'Nutrition in Plants'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Photosynthesis happens in the leaf, water arrives through the root and the stem carries it. The chapter is stated entirely in plant parts named the year before.',
        'evidence' => 'NCERT Science 6 Ch. Getting to Know Plants → Science 7 Ch. Nutrition in Plants',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Nutrition in Plants'],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Autotrophic nutrition in Class 10 is the Class 7 photosynthesis with the stomata, chlorophyll and the equation made precise. The process itself is assumed known.',
        'evidence' => 'NCERT Science 7 Ch. Nutrition in Plants → Science 10 Ch. Life Processes',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Nutrition in Animals'],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'The human alimentary canal is taught in Class 7 and revisited in Class 10 with the enzymes and their pH. The route the food takes is not re-taught.',
        'evidence' => 'NCERT Science 7 Ch. Nutrition in Animals → Science 10 Ch. Life Processes',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Respiration in Organisms'],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Class 10 distinguishes aerobic from anaerobic respiration by their products and energy yield. The distinction, and the fact that respiration is not breathing, are established in Class 7.',
        'evidence' => 'NCERT Science 7 Ch. Respiration in Organisms → Science 10 Ch. Life Processes',
    ],
    [
        'strand' => 'SCI-NUT',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Transportation in Animals and Plants'],
        'dependent' => ['grade' => 10, 'chapter' => 'Life Processes'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Double circulation and transpiration pull in Class 10 are refinements of the heart, blood vessels, xylem and phloem introduced in Class 7 — none of which is redefined.',
        'evidence' => 'NCERT Science 7 Ch. Transportation in Animals and Plants → Science 10 Ch. Life Processes',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-CTR — Control and coordination
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-CTR',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Life Processes'],
        'dependent' => ['grade' => 10, 'chapter' => 'Control and Coordination'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'Hormones are transported in the blood and act on organs whose function was just established. The chapter reads as coordination OF the life processes, so it is far clearer after them.',
        'evidence' => 'NCERT Science 10, Ch. Life Processes → Ch. Control and Coordination',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-REP — Reproduction and heredity
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Getting to Know Plants'],
        'dependent' => ['grade' => 7, 'chapter' => 'Reproduction in Plants'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Pollination is described as pollen moving from anther to stigma. The parts of a flower are dissected and named in Class 6 and used as vocabulary in Class 7.',
        'evidence' => 'NCERT Science 6 Ch. Getting to Know Plants → Science 7 Ch. Reproduction in Plants',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Reproduction in Plants'],
        'dependent' => ['grade' => 10, 'chapter' => 'How Do Organisms Reproduce'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Class 10 repeats vegetative propagation and flower structure and then adds the fate of the ovule after fertilisation. The earlier account is the thing being extended.',
        'evidence' => 'NCERT Science 7 Ch. Reproduction in Plants → Science 10 Ch. How Do Organisms Reproduce',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Reproduction in Animals'],
        'dependent' => ['grade' => 10, 'chapter' => 'How Do Organisms Reproduce'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Gametes, fertilisation, and the internal/external distinction are taught in Class 8. Class 10 builds the human reproductive system on top of all three without restating them.',
        'evidence' => 'NCERT Science 8 Ch. Reproduction in Animals → Science 10 Ch. How Do Organisms Reproduce',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Reaching the Age of Adolescence'],
        'dependent' => ['grade' => 10, 'chapter' => 'How Do Organisms Reproduce'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'The hormonal control of puberty and the menstrual cycle are Class 8. Class 10 uses the cycle to explain when fertilisation can occur and what contraception interrupts.',
        'evidence' => 'NCERT Science 8 Ch. Reaching the Age of Adolescence → Science 10 Ch. How Do Organisms Reproduce',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 9, 'chapter' => 'The Fundamental Unit of Life'],
        'dependent' => ['grade' => 10, 'chapter' => 'How Do Organisms Reproduce'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Binary fission, budding and fragmentation are all described as what the cell and its nucleus do. Chromosomes and the nucleus must already be known structures.',
        'evidence' => 'NCERT Science 9 Ch. The Fundamental Unit of Life → Science 10 Ch. How Do Organisms Reproduce',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 10, 'chapter' => 'How Do Organisms Reproduce'],
        'dependent' => ['grade' => 10, 'chapter' => 'Heredity and Evolution'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 1.0,
        'rationale' => 'Inheritance is the passing of traits through gametes, and Mendel\'s crosses are sexual reproduction done deliberately. Neither sentence is available without the previous chapter.',
        'evidence' => 'NCERT Science 10, Ch. How Do Organisms Reproduce → Ch. Heredity and Evolution',
    ],
    [
        'strand' => 'SCI-REP',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Diversity in Living Organisms'],
        'dependent' => ['grade' => 10, 'chapter' => 'Heredity and Evolution'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'Evolutionary relationships are read off the classification hierarchy, and speciation is explained as the origin of the groups that hierarchy names.',
        'evidence' => 'NCERT Science 9 Ch. Diversity in Living Organisms → Science 10 Ch. Heredity and Evolution',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-ENV — Environment and ecology
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Water'],
        'dependent' => ['grade' => 7, 'chapter' => 'Water: A Precious Resource'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Scarcity and management are argued from the water cycle and from where fresh water actually sits. Both are established in Class 6.',
        'evidence' => 'NCERT Science 6 Ch. Water → Science 7 Ch. Water: A Precious Resource',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Air Around Us'],
        'dependent' => ['grade' => 8, 'chapter' => 'Pollution of Air and Water'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'A pollutant is defined as a substance present in air where it should not be, or in the wrong proportion. The normal composition of air is the baseline, and it comes from Class 6.',
        'evidence' => 'NCERT Science 6 Ch. Air Around Us → Science 8 Ch. Pollution of Air and Water',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Garbage In, Garbage Out'],
        'dependent' => ['grade' => 8, 'chapter' => 'Pollution of Air and Water'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Biodegradable and non-biodegradable waste is the Class 6 idea that makes water pollution by plastics and sewage explicable rather than merely deplorable.',
        'evidence' => 'NCERT Science 6 Ch. Garbage In, Garbage Out → Science 8 Ch. Pollution of Air and Water',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Wastewater Story'],
        'dependent' => ['grade' => 8, 'chapter' => 'Pollution of Air and Water'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Sewage treatment is the remedy Class 8 cites for water pollution, and it is described in full in Class 7. Class 8 names it without re-describing it.',
        'evidence' => 'NCERT Science 7 Ch. Wastewater Story → Science 8 Ch. Pollution of Air and Water',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Forests: Our Lifeline'],
        'dependent' => ['grade' => 8, 'chapter' => 'Conservation of Plants and Animals'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Deforestation is presented as the loss of the functions a forest performs. Those functions — habitat, water cycle, oxygen — are what Class 7 establishes.',
        'evidence' => 'NCERT Science 7 Ch. Forests: Our Lifeline → Science 8 Ch. Conservation of Plants and Animals',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Air Around Us'],
        'dependent' => ['grade' => 9, 'chapter' => 'Natural Resources'],
        'type' => 'spirals_from', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'The biogeochemical cycles are stated over the atmosphere\'s actual composition. Class 9 assumes the learner knows what air is made of.',
        'evidence' => 'NCERT Science 6 Ch. Air Around Us → Science 9 Ch. Natural Resources',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Nutrition in Plants'],
        'dependent' => ['grade' => 10, 'chapter' => 'Our Environment'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'A food chain begins with a producer, and a trophic level is defined by how energy entered the chain. Photosynthesis is what makes the first level a level at all.',
        'evidence' => 'NCERT Science 7 Ch. Nutrition in Plants → Science 10 Ch. Our Environment',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Natural Resources'],
        'dependent' => ['grade' => 10, 'chapter' => 'Our Environment'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Ozone depletion and the cycling of matter in Class 10 continue the atmosphere and the nitrogen and carbon cycles set up in Class 9.',
        'evidence' => 'NCERT Science 9 Ch. Natural Resources → Science 10 Ch. Our Environment',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 9, 'chapter' => 'Natural Resources'],
        'dependent' => ['grade' => 10, 'chapter' => 'Management of Natural Resources'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Sustainable management is argued about resources whose formation and limits were taught the year before. The argument needs the resource to be understood as finite.',
        'evidence' => 'NCERT Science 9 Ch. Natural Resources → Science 10 Ch. Management of Natural Resources',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Conservation of Plants and Animals'],
        'dependent' => ['grade' => 10, 'chapter' => 'Management of Natural Resources'],
        'type' => 'spirals_from', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.7,
        'rationale' => 'Forest management and stakeholder conflict in Class 10 extend the reserves, endangered species and Red Data Book introduced in Class 8.',
        'evidence' => 'NCERT Science 8 Ch. Conservation of Plants and Animals → Science 10 Ch. Management of Natural Resources',
    ],
    [
        'strand' => 'SCI-ENV',
        'prerequisite' => ['grade' => 10, 'chapter' => 'Our Environment'],
        'dependent' => ['grade' => 10, 'chapter' => 'Management of Natural Resources'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'The case for management is made from ecosystem damage — biomagnification, waste that does not degrade — which is exactly what the preceding chapter documents.',
        'evidence' => 'NCERT Science 10, Ch. Our Environment → Ch. Management of Natural Resources',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-HLT — Health and disease
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-HLT',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Components of Food'],
        'dependent' => ['grade' => 9, 'chapter' => 'Why Do We Fall Ill'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Deficiency diseases are taught as the consequence of a missing nutrient. Naming scurvy or rickets as a cause-and-effect needs the nutrient it lacks to be nameable.',
        'evidence' => 'NCERT Science 6 Ch. Components of Food → Science 9 Ch. Why Do We Fall Ill',
    ],
    [
        'strand' => 'SCI-HLT',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Microorganisms: Friend and Foe'],
        'dependent' => ['grade' => 9, 'chapter' => 'Why Do We Fall Ill'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Infectious disease is defined by its agent — bacterium, virus, protozoan, fungus. The four groups and what distinguishes them are Class 8 content.',
        'evidence' => 'NCERT Science 8 Ch. Microorganisms: Friend and Foe → Science 9 Ch. Why Do We Fall Ill',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-AGR — Food production and materials
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-AGR',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Food: Where Does It Come From'],
        'dependent' => ['grade' => 8, 'chapter' => 'Crop Production and Management'],
        'type' => 'spirals_from', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'Class 8 manages the production of food whose plant and animal sources Class 6 established. The earlier chapter supplies what is being produced.',
        'evidence' => 'NCERT Science 6 Ch. Food: Where Does It Come From → Science 8 Ch. Crop Production and Management',
    ],
    [
        'strand' => 'SCI-AGR',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Nutrition in Plants'],
        'dependent' => ['grade' => 8, 'chapter' => 'Crop Production and Management'],
        'type' => 'requires', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Manure and fertiliser are justified as replacing the nutrients a crop takes from the soil. The justification needs mineral nutrition to have been taught.',
        'evidence' => 'NCERT Science 7 Ch. Nutrition in Plants → Science 8 Ch. Crop Production and Management',
    ],
    [
        'strand' => 'SCI-AGR',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Crop Production and Management'],
        'dependent' => ['grade' => 9, 'chapter' => 'Improvement in Food Resources'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Crop variety improvement and cropping patterns in Class 9 assume the practices — irrigation, sowing, harvesting, storage — taught in full in Class 8.',
        'evidence' => 'NCERT Science 8 Ch. Crop Production and Management → Science 9 Ch. Improvement in Food Resources',
    ],
    [
        'strand' => 'SCI-AGR',
        'prerequisite' => ['grade' => 8, 'chapter' => 'Microorganisms: Friend and Foe'],
        'dependent' => ['grade' => 9, 'chapter' => 'Improvement in Food Resources'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Nitrogen fixation by Rhizobium, and crop disease caused by pathogens, are both taken from Class 8 and applied to yield in Class 9.',
        'evidence' => 'NCERT Science 8 Ch. Microorganisms: Friend and Foe → Science 9 Ch. Improvement in Food Resources',
    ],
    [
        'strand' => 'SCI-AGR',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Fibre to Fabric'],
        'dependent' => ['grade' => 7, 'chapter' => 'Fibre to Fabric'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Class 7 takes up wool and silk having had cotton and jute — and the spinning and weaving steps — established in Class 6.',
        'evidence' => 'NCERT Science 6 Ch. Fibre to Fabric → Science 7 Ch. Fibre to Fabric',
    ],
    [
        'strand' => 'SCI-AGR',
        'prerequisite' => ['grade' => 7, 'chapter' => 'Fibre to Fabric'],
        'dependent' => ['grade' => 8, 'chapter' => 'Synthetic Fibres and Plastics'],
        'type' => 'spirals_from', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'A synthetic fibre is defined by contrast with a natural one, and its advantages are stated as comparisons. The natural fibres are the term of comparison.',
        'evidence' => 'NCERT Science 7 Ch. Fibre to Fabric → Science 8 Ch. Synthetic Fibres and Plastics',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCI-AST — Earth, sky and the solar system
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => 'SCI-AST',
        'prerequisite' => ['grade' => 6, 'chapter' => 'Motion and Measurement of Distances'],
        'dependent' => ['grade' => 8, 'chapter' => 'Stars and the Solar System'],
        'type' => 'builds_on', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.55,
        'rationale' => 'The light year is introduced as a unit of distance, which only reads as a distance to a learner who has already chosen units for measuring one.',
        'evidence' => 'NCERT Science 6 Ch. Motion and Measurement of Distances → Science 8 Ch. Stars and the Solar System',
    ],

    // ══════════════════════════════════════════════════════════════════
    // CROSS-SUBJECT ANCHORS — Mathematics into Science
    //
    // Few in number and disproportionately load-bearing. Every one of these is
    // a place where a Science chapter silently assumes a Mathematics technique
    // and a learner who lacks it fails the Science for a reason no Science
    // remediation will find.
    // ══════════════════════════════════════════════════════════════════

    [
        'strand' => null,
        'prerequisite' => ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Ratio and Proportion'],
        'dependent' => ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Motion and Time'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Speed is a ratio of two unlike quantities. A learner who cannot form or compare a ratio cannot compute a speed, and will read 60 km/h as a number rather than as a rate.',
        'evidence' => 'NCERT Mathematics 6 Ch. Ratio and Proportion → Science 7 Ch. Motion and Time',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Ratio and Proportion'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Atoms and Molecules'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'The mole is a fixed ratio between mass and particle count, and the law of constant proportions is a proportion by name. Molar-mass problems are unitary-method problems in chemical clothing.',
        'evidence' => 'NCERT Mathematics 6 Ch. Ratio and Proportion → Science 9 Ch. Atoms and Molecules (mole concept)',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Comparing Quantities'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Is Matter Around Us Pure'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Concentration is expressed as mass percentage and volume percentage. Every worked example is a percentage calculation, so a learner weak on percentage fails the chemistry arithmetic, not the chemistry.',
        'evidence' => 'NCERT Mathematics 7 Ch. Comparing Quantities → Science 9 Ch. Is Matter Around Us Pure (concentration)',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Introduction to Graphs'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Motion'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.95,
        'rationale' => 'Distance-time and velocity-time graphs are the chapter\'s main representation, and acceleration is read off as a slope while displacement is read off as an area. All three skills are Mathematics, not Science.',
        'evidence' => 'NCERT Mathematics 8 Ch. Introduction to Graphs → Science 9 Ch. Motion',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Linear Equations in One Variable'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Motion'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Every equation-of-motion numerical is solved by rearranging for an unknown. A learner who cannot transpose a term across an equals sign cannot finish a single one.',
        'evidence' => 'NCERT Mathematics 8 Ch. Linear Equations in One Variable → Science 9 Ch. Motion',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Squares and Square Roots'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Motion'],
        'type' => 'applies', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'The third equation of motion is v² = u² + 2as, and solving for v means taking a square root. Without it the learner stops one step short of the answer.',
        'evidence' => 'NCERT Mathematics 8 Ch. Squares and Square Roots → Science 9 Ch. Motion',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Direct and Inverse Proportions'],
        'dependent' => ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Electricity'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.9,
        'rationale' => 'Ohm\'s law IS a statement of direct proportion, and resistance in parallel is an inverse one. A learner who cannot recognise either relationship has to memorise the formulae instead of understanding them.',
        'evidence' => 'NCERT Mathematics 8 Ch. Direct and Inverse Proportions → Science 10 Ch. Electricity',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Direct and Inverse Proportions'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Gravitation'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'The universal law is an inverse-square relationship. "Quadruple the distance and the force falls to a sixteenth" is a proportional-reasoning step, and it is where most learners lose the law.',
        'evidence' => 'NCERT Mathematics 8 Ch. Direct and Inverse Proportions → Science 9 Ch. Gravitation',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Direct and Inverse Proportions'],
        'dependent' => ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Force and Pressure'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Pressure is inversely proportional to area for a fixed force — which is the whole explanation of why a sharp knife cuts. Without inverse proportion the explanation is an assertion.',
        'evidence' => 'NCERT Mathematics 8 Ch. Direct and Inverse Proportions → Science 8 Ch. Force and Pressure',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Mensuration'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Gravitation'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Density and relative density are mass over volume, and every floatation problem needs the volume of a regular solid. Those volume formulae are Mathematics.',
        'evidence' => 'NCERT Mathematics 8 Ch. Mensuration → Science 9 Ch. Gravitation (density and floatation)',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Mensuration'],
        'dependent' => ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Force and Pressure'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.75,
        'rationale' => 'Pressure is force per unit AREA. A learner who cannot compute the area a force acts over cannot compute a pressure, whatever they understand about force.',
        'evidence' => 'NCERT Mathematics 6 Ch. Mensuration → Science 8 Ch. Force and Pressure',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Understanding Elementary Shapes'],
        'dependent' => ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Light'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'The law of reflection is an equality between two measured angles. Measuring an angle with a protractor is a Class 6 Mathematics skill and is never taught in Science.',
        'evidence' => 'NCERT Mathematics 6 Ch. Understanding Elementary Shapes → Science 8 Ch. Light',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Lines and Angles'],
        'dependent' => ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Light — Reflection and Refraction'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.8,
        'rationale' => 'Ray diagrams depend on the normal being perpendicular to the surface and on angles measured from it. Perpendicularity and angle pairs are Mathematics content the Science chapter assumes.',
        'evidence' => 'NCERT Mathematics 7 Ch. Lines and Angles → Science 10 Ch. Light — Reflection and Refraction',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Exponents and Powers'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Atoms and Molecules'],
        'type' => 'applies', 'necessity' => 'mandatory', 'gates' => true, 'strength' => 0.85,
        'rationale' => 'Avogadro\'s number is 6.022 × 10²³ and every mole calculation multiplies or divides numbers in standard form. Without index laws the arithmetic is simply not performable.',
        'evidence' => 'NCERT Mathematics 8 Ch. Exponents and Powers → Science 9 Ch. Atoms and Molecules',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Exponents and Powers'],
        'dependent' => ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Structure of the Atom'],
        'type' => 'applies', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.65,
        'rationale' => 'Atomic radii and particle masses are quoted in negative powers of ten. A learner who reads 10⁻¹⁰ m as a large number has no sense of the scale the chapter is about.',
        'evidence' => 'NCERT Mathematics 8 Ch. Exponents and Powers → Science 9 Ch. Structure of the Atom',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Comparing Quantities'],
        'dependent' => ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Sources of Energy'],
        'type' => 'applies', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.6,
        'rationale' => 'Sources are compared by percentage efficiency and by proportion of demand met. The comparison is the point of the chapter, and it is arithmetic.',
        'evidence' => 'NCERT Mathematics 8 Ch. Comparing Quantities → Science 10 Ch. Sources of Energy',
    ],
    [
        'strand' => null,
        'prerequisite' => ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Statistics'],
        'dependent' => ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Heredity and Evolution'],
        'type' => 'applies', 'necessity' => 'recommended', 'gates' => false, 'strength' => 0.55,
        'rationale' => 'Mendel\'s 3:1 and 9:3:3:1 results are ratios read off counted offspring, and the claim they support is statistical. A learner who cannot read a ratio from a tally sees the numbers as arbitrary.',
        'evidence' => 'NCERT Mathematics 9 Ch. Statistics → Science 10 Ch. Heredity and Evolution',
    ],

];
