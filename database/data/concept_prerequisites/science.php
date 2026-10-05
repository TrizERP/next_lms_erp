<?php

/*
|--------------------------------------------------------------------------
| Science — concept prerequisites, classes 6–10
|--------------------------------------------------------------------------
|
| Loaded by:  php artisan concept:prereq-import --file=science.php
|
| EVERY ENTRY NAMES A REAL lms_concept.id, read from vivek_erp. The concept
| name in the comment is for the person reading the file; the id is what is
| stored. Concept names on this estate come out of the AI extraction run and
| vary between estates, so ids are the only stable handle.
|
| DIRECTION
|   'prerequisite' is learned FIRST.  'concept' is the one that needs it.
|   Read each entry as "concept requires prerequisite".
|
| LINK TYPES
|   requires      — hard gate; the learner cannot start without it
|   builds_on     — assumed, but a teacher can recover it inside the lesson
|   spiral        — the same idea at an earlier class (the CBSE spiral)
|   cross_subject — from another subject, almost always Mathematics
|
| 'gate' => true means the learner is BLOCKED, not merely helped. Kept
| deliberately narrow: over-gating locks students out for gaps that a two-minute
| recap would close.
|
| EVERY ENTRY CARRIES A REASON, and the rule is strict — say what the learner
| LITERALLY CANNOT DO without the prerequisite. Not "is related to", not "is
| foundational". A reason that could be pasted onto any other link is not a
| reason, and the importer rejects entries under 40 characters.
|
| NOTE ON THIS ESTATE'S BOOKS: classes 6, 7 and 9 are on the newer NCERT
| editions ("Journey Inside the Atom", "Atomic Foundations of Matter") while
| 8 and 10 are on the older ones. Chapter names in `source` are this estate's,
| not the textbook's, so they can be checked against the data.
|
| BATCH 1 — Chemistry and atomic structure: C9 → C10.
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // C9 · Journey Inside the Atom (8607) — the internal chain
    // Discovery → model → shells → configuration → valency.
    // This ladder is what every Class 10 chemistry chapter stands on.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7417,       // Thomson's plum pudding model
        'prerequisite' => 7416,  // Discovery of electrons
        'type' => 'requires', 'gate' => true,
        'reason' => 'The plum pudding model exists to explain where the negatively charged electrons sit. Without the electron having been discovered there is nothing for the model to place.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7418,       // Rutherford's gold foil experiment
        'prerequisite' => 7417,  // Thomson's plum pudding model
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The experiment is taught as a test of the plum pudding model, and its result is only surprising if the learner expected charge to be spread evenly.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7419,       // Rutherford's nuclear model
        'prerequisite' => 7418,  // Rutherford's gold foil experiment
        'type' => 'requires', 'gate' => true,
        'reason' => 'The nuclear model is an inference from the alpha-particle deflections. A learner who has not seen the observation has no evidence from which the nucleus follows.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7420,       // Limitation of Rutherford's model
        'prerequisite' => 7419,  // Rutherford's nuclear model
        'type' => 'requires', 'gate' => true,
        'reason' => 'The limitation is that orbiting electrons should spiral into the nucleus. There is no limitation to state until the orbiting model itself has been taught.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7422,       // Bohr's model of atom
        'prerequisite' => 7420,  // Limitation of Rutherford's model
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bohr\'s fixed energy shells are introduced precisely to repair the instability Rutherford could not explain. Without the problem, the fix looks arbitrary.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7424,       // Chadwick's discovery of neutron
        'prerequisite' => 7423,  // Mass puzzle of helium
        'type' => 'requires', 'gate' => true,
        'reason' => 'The neutron is proposed to account for the missing mass in helium. The puzzle is the reason the particle is looked for at all.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7429,       // Neutral atom has equal protons and electrons
        'prerequisite' => 7428,  // Atomic number definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Neutrality is stated as electrons equalling the atomic number. The learner must already know that the atomic number counts protons.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7430,       // Mass number and nucleons
        'prerequisite' => 7428,  // Atomic number definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mass number is protons plus neutrons, so it cannot be computed or interpreted without the proton count the atomic number supplies.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7430,       // Mass number and nucleons
        'prerequisite' => 7424,  // Chadwick's discovery of neutron
        'type' => 'requires', 'gate' => true,
        'reason' => 'Half of the mass number is the neutron count. A learner for whom the neutron does not yet exist can only memorise the number, not derive it.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7431,       // Standard atomic notation
        'prerequisite' => 7430,  // Mass number and nucleons
        'type' => 'requires', 'gate' => true,
        'reason' => 'The notation writes mass number above and atomic number below the symbol. Both numbers must mean something before the notation can be read or written.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7433,       // Bohr-Bury 2n^2 rule
        'prerequisite' => 7422,  // Bohr's model of atom
        'type' => 'requires', 'gate' => true,
        'reason' => 'The rule states how many electrons each shell holds. There are no shells to fill until Bohr\'s shell model has been established.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7438,       // Electronic configuration definition
        'prerequisite' => 7433,  // Bohr-Bury 2n^2 rule
        'type' => 'requires', 'gate' => true,
        'reason' => 'A configuration is produced by applying the shell capacity rule outwards. Without the capacities the learner cannot distribute a single element\'s electrons.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7439,       // First 18 elements shell distribution
        'prerequisite' => 7438,  // Electronic configuration definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'This is the configuration procedure applied element by element. It is practice of the definition and is unattemptable without it.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7441,       // Valence shell and valence electrons
        'prerequisite' => 7438,  // Electronic configuration definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The valence shell is the outermost occupied shell, which can only be identified by writing the configuration first.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7442,       // Complete octet confers stability
        'prerequisite' => 7441,  // Valence shell and valence electrons
        'type' => 'requires', 'gate' => true,
        'reason' => 'An octet is eight electrons in the valence shell. The learner must be able to count valence electrons before completeness means anything.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7443,       // Valency as electrons lost/gained/shared
        'prerequisite' => 7442,  // Complete octet confers stability
        'type' => 'requires', 'gate' => true,
        'reason' => 'Valency is defined as how many electrons an atom must lose, gain or share to reach an octet. The octet is the target the whole definition is measured against.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7444,       // Fewer than four valence electrons
        'prerequisite' => 7443,  // Valency as electrons lost/gained/shared
        'type' => 'requires', 'gate' => false,
        'reason' => 'This is the rule for deciding whether an atom loses rather than gains electrons, which presupposes that valency is already understood as a choice between the two.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7447,       // Isotopes definition
        'prerequisite' => 7430,  // Mass number and nucleons
        'type' => 'requires', 'gate' => true,
        'reason' => 'Isotopes are atoms with the same atomic number and different mass numbers. Both quantities must be separable before the definition can distinguish anything.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7449,       // Isotopes have similar chemical properties
        'prerequisite' => 7441,  // Valence shell and valence electrons
        'type' => 'requires', 'gate' => false,
        'reason' => 'The reason isotopes behave alike chemically is that they share a valence electron count. Without valence electrons the claim is an unexplained fact.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7452,       // Simple average atomic mass
        'prerequisite' => 7447,  // Isotopes definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The average is taken over an element\'s isotopes weighted by abundance. There is nothing to average until isotopes exist for the learner.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7454,       // Isobars definition
        'prerequisite' => 7430,  // Mass number and nucleons
        'type' => 'requires', 'gate' => true,
        'reason' => 'Isobars share a mass number while differing in atomic number — the mirror image of isotopes, and unreadable without both numbers.',
        'source' => 'C9 Journey Inside the Atom',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Atomic Foundations of Matter (8614)
    // Conservation → constant proportions → atoms → bonds → formulae → mass.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7493,       // Law of Constant Proportions
        'prerequisite' => 7489,  // Law of Conservation of Mass
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Both laws are established from the same mass measurements on the same reactions, and the second is taught as the next question asked of that data.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7491,       // Mass balance verification calculations
        'prerequisite' => 7489,  // Law of Conservation of Mass
        'type' => 'requires', 'gate' => true,
        'reason' => 'The calculation checks that total reactant mass equals total product mass. It is the law being applied, so it cannot precede the law.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7496,       // Using mass ratio to calculate amounts
        'prerequisite' => 7493,  // Law of Constant Proportions
        'type' => 'requires', 'gate' => true,
        'reason' => 'The calculation only works because the ratio is fixed. A learner who does not hold constant proportions has no justification for scaling the numbers.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7501,       // Atoms combine in whole-number ratios
        'prerequisite' => 7493,  // Law of Constant Proportions
        'type' => 'requires', 'gate' => true,
        'reason' => 'Whole-number combining ratios are Dalton\'s explanation OF constant proportions. The observation must be in place before the explanation is offered.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7504,       // Atoms combine to attain stability
        'prerequisite' => 7442,  // Complete octet confers stability  (8607)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Stability here means a completed octet. The bonding chapter borrows that criterion from the atom chapter and never restates it.',
        'source' => 'C9 Atomic Foundations of Matter ← C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7505,       // Chemical bond
        'prerequisite' => 7504,  // Atoms combine to attain stability
        'type' => 'requires', 'gate' => true,
        'reason' => 'A bond is defined as what holds atoms together once they have combined for stability. The motive comes first, the mechanism second.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7506,       // Covalent bond
        'prerequisite' => 7505,  // Chemical bond
        'type' => 'requires', 'gate' => true,
        'reason' => 'Covalent bonding is one kind of chemical bond, distinguished by sharing. The general category must exist before it can be subdivided.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7510,       // Ions and ionic bond formation
        'prerequisite' => 7443,  // Valency as electrons lost/gained/shared  (8607)
        'type' => 'requires', 'gate' => true,
        'reason' => 'An ion is what an atom becomes after losing or gaining the number of electrons its valency specifies. Without valency there is no predicting the charge.',
        'source' => 'C9 Atomic Foundations of Matter ← C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7511,       // Ionic compounds form crystal lattice
        'prerequisite' => 7510,  // Ions and ionic bond formation
        'type' => 'requires', 'gate' => true,
        'reason' => 'The lattice is the arrangement oppositely charged ions take up. There are no ions to arrange until ionic bonding has been taught.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7513,       // Chemical formula uses symbols and valencies
        'prerequisite' => 7443,  // Valency as electrons lost/gained/shared  (8607)
        'type' => 'requires', 'gate' => true,
        'reason' => 'A formula is built by combining symbols in the ratio their valencies dictate. A learner who cannot state an element\'s valency cannot write the formula of any compound it forms.',
        'source' => 'C9 Atomic Foundations of Matter ← C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7514,       // Criss-cross method for covalent compounds
        'prerequisite' => 7513,  // Chemical formula uses symbols and valencies
        'type' => 'requires', 'gate' => true,
        'reason' => 'Criss-crossing is the procedure for turning valencies into subscripts, so it is meaningless before the formula convention is known.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7516,       // Criss-cross method for ionic compounds
        'prerequisite' => 7513,  // Chemical formula uses symbols and valencies
        'type' => 'requires', 'gate' => true,
        'reason' => 'The same procedure applied to charges instead of shared pairs; it still writes a formula and still needs the formula convention first.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7516,       // Criss-cross method for ionic compounds
        'prerequisite' => 7510,  // Ions and ionic bond formation
        'type' => 'requires', 'gate' => true,
        'reason' => 'The numbers being crossed are ionic charges. Without ions the learner has no charges to cross over.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7517,       // Ionic compounds are electrically neutral
        'prerequisite' => 7510,  // Ions and ionic bond formation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Neutrality is the statement that positive and negative ionic charges cancel, which needs both kinds of ion to be established first.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7519,       // Brackets with multiple polyatomic ions
        'prerequisite' => 7516,  // Criss-cross method for ionic compounds
        'type' => 'requires', 'gate' => false,
        'reason' => 'Brackets are a notation fix applied when the criss-cross produces more than one polyatomic ion, so it is a refinement of that procedure.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7521,       // Conduction by dissolved ionic compounds
        'prerequisite' => 7510,  // Ions and ionic bond formation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Conduction is explained by free ions carrying charge through the solution. Without ions there is no carrier and the fact is unexplainable.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7524,       // Molecular mass definition
        'prerequisite' => 7503,  // Molecule definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Molecular mass is the mass of one molecule. The thing being massed has to exist for the learner before its mass can be defined.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7525,       // Adding atomic masses
        'prerequisite' => 7524,  // Molecular mass definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding the constituent atomic masses is the procedure for obtaining molecular mass, so the quantity must be defined before the method computes it.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7525,       // Adding atomic masses
        'prerequisite' => 7513,  // Chemical formula uses symbols and valencies
        'type' => 'requires', 'gate' => true,
        'reason' => 'You add one atomic mass per atom in the formula, so the formula must be writable before the sum has terms.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7528,       // Formula unit mass definition
        'prerequisite' => 7527,  // Formula unit representation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Formula unit mass is the mass of one formula unit, which is the concept introduced immediately before it for ionic compounds that have no molecules.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7527,       // Formula unit representation
        'prerequisite' => 7511,  // Ionic compounds form crystal lattice
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formula unit exists because a lattice has no discrete molecule to weigh. The lattice is the reason the alternative representation is needed.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7529,       // Calculating formula unit mass
        'prerequisite' => 7528,  // Formula unit mass definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The calculation is the definition carried out on a particular compound, so it cannot be performed before the quantity is defined.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Exploring Mixtures and their Separation (8604)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7358,       // Solution is homogeneous
        'prerequisite' => 7356,  // Homogeneous mixture definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Classifying a solution as homogeneous is applying the definition of homogeneity, which must therefore already be available.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7362,       // Concentration of a solution
        'prerequisite' => 7361,  // Solute and solvent in a solution
        'type' => 'requires', 'gate' => true,
        'reason' => 'Concentration is a comparison of solute to solvent or solution. Both roles have to be identifiable before the comparison has terms.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7363,       // Mass by mass percentage
        'prerequisite' => 7362,  // Concentration of a solution
        'type' => 'requires', 'gate' => true,
        'reason' => 'This is one of the ways of expressing concentration, so the quantity has to be understood before a method of stating it is chosen.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7364,       // Volume by volume percentage
        'prerequisite' => 7362,  // Concentration of a solution
        'type' => 'requires', 'gate' => true,
        'reason' => 'The second way of expressing the same quantity; it is a choice of method and is meaningless without the quantity.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7365,       // Choosing the concentration method
        'prerequisite' => 7363,  // Mass by mass percentage
        'type' => 'requires', 'gate' => false,
        'reason' => 'Choosing between methods requires both methods to be known; this is the decision that follows having been taught them.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7367,       // Saturated solution
        'prerequisite' => 7366,  // Solubility definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'A saturated solution is one holding the maximum the solubility allows at that temperature, so solubility is the limit being reached.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7368,       // Temperature effect on solubility
        'prerequisite' => 7366,  // Solubility definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The effect is a change IN solubility, so the quantity must be defined before a variable can be said to move it.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7369,       // Solubility curve interpretation
        'prerequisite' => 7368,  // Temperature effect on solubility
        'type' => 'requires', 'gate' => true,
        'reason' => 'The curve plots solubility against temperature; reading it means reading the relationship the previous concept establishes.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7371,       // Crystallization definition
        'prerequisite' => 7367,  // Saturated solution
        'type' => 'requires', 'gate' => true,
        'reason' => 'Crystals form when a saturated solution is cooled and can no longer hold its solute. Saturation is the state the whole technique depends on.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7373,       // Slow cooling produces large crystals
        'prerequisite' => 7372,  // Principle of crystallization
        'type' => 'requires', 'gate' => false,
        'reason' => 'Cooling rate is a control applied to the crystallization principle, and is only meaningful once that principle is understood.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7375,       // Boiling point difference requirement
        'prerequisite' => 7374,  // Distillation definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The requirement states when distillation will work. It is a condition on the technique and needs the technique to have been described.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7377,       // Fractional distillation definition
        'prerequisite' => 7375,  // Boiling point difference requirement
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fractional distillation is what is used when the boiling points are too close for simple distillation, so the limitation is its entire justification.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7391,       // Colloids do not settle
        'prerequisite' => 7390,  // Particle size distinguishes mixtures
        'type' => 'requires', 'gate' => true,
        'reason' => 'Colloid particles stay suspended because of their size. The size criterion is what separates a colloid from a suspension in the first place.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7392,       // Tyndall effect definition
        'prerequisite' => 7391,  // Colloids do not settle
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Scattering happens because dispersed particles remain suspended in the path of the beam; the effect is a consequence of the property just established.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7387,       // Limitation of filtration
        'prerequisite' => 7386,  // Suspension definition
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Filtration works on suspensions and fails on colloids, so the limitation is stated by contrast with the suspension case.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Chemical Reactions and Equations (1012)
    // Where the Class 9 chemistry ladder is cashed in.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 2726,       // Chemical equations using formulae
        'prerequisite' => 7513,  // Chemical formula uses symbols and valencies  (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Every term in the equation is a chemical formula. A learner who cannot write a formula cannot write the equation, whatever they understand about the reaction.',
        'source' => 'C10 Chemical Reactions and Equations ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 2726,       // Chemical equations using formulae
        'prerequisite' => 2725,  // Reactants and products
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formula equation places reactants on the left and products on the right, so the two roles must already be distinguishable.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2728,       // Law of conservation of mass
        'prerequisite' => 7489,  // Law of Conservation of Mass  (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The same law, met in Class 9 as a measurement result and used in Class 10 as the rule that balancing must satisfy. The Class 10 chapter states it in one line and assumes the rest.',
        'source' => 'C10 Chemical Reactions and Equations ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 2727,       // Skeletal chemical equations
        'prerequisite' => 2726,  // Chemical equations using formulae
        'type' => 'requires', 'gate' => true,
        'reason' => 'A skeletal equation is the formula equation before balancing. It is that object, named at the stage prior to the next step.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2729,       // Balancing starts with maximum atoms
        'prerequisite' => 2728,  // Law of conservation of mass
        'type' => 'requires', 'gate' => true,
        'reason' => 'Balancing exists only because mass is conserved. Without the law the learner is rearranging numbers for no stated reason.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2729,       // Balancing starts with maximum atoms
        'prerequisite' => 2727,  // Skeletal chemical equations
        'type' => 'requires', 'gate' => true,
        'reason' => 'The thing being balanced is the skeletal equation, so it must be on the page before the procedure can begin.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2730,       // Hit-and-trial balancing method
        'prerequisite' => 2729,  // Balancing starts with maximum atoms
        'type' => 'requires', 'gate' => true,
        'reason' => 'The method is the systematic version of the starting rule; attempting it without the rule produces the endless trial-and-error learners get stuck in.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2731,       // Symbols of physical states
        'prerequisite' => 2730,  // Hit-and-trial balancing method
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'State symbols are added to an already balanced equation as the final layer of information, so they are taught after balancing rather than alongside it.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2733,       // Combination reaction
        'prerequisite' => 2730,  // Hit-and-trial balancing method
        'type' => 'requires', 'gate' => true,
        'reason' => 'A reaction type is recognised from the SHAPE of its balanced equation — here, two reactants giving one product. An unbalanced equation has no recognisable shape.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2735,       // Decomposition reaction
        'prerequisite' => 2730,  // Hit-and-trial balancing method
        'type' => 'requires', 'gate' => true,
        'reason' => 'Identified by one reactant giving several products, which again is read off the balanced equation rather than from the description.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2735,       // Decomposition reaction
        'prerequisite' => 2733,  // Combination reaction
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Decomposition is taught as the reverse of combination and is defined by that contrast, so the first type gives the second its meaning.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2736,       // Thermal decomposition
        'prerequisite' => 2735,  // Decomposition reaction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Thermal decomposition is the subtype driven by heat. The general type must exist before it is split by the energy source.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2737,       // Photolytic decomposition
        'prerequisite' => 2735,  // Decomposition reaction
        'type' => 'requires', 'gate' => true,
        'reason' => 'The light-driven subtype of the same general type, and equally dependent on the general type being in place.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2738,       // Endothermic reactions
        'prerequisite' => 2734,  // Exothermic chemical reactions
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Endothermic is defined as the opposite of exothermic — absorbing rather than releasing energy — so the first term is the reference point for the second.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2739,       // Displacement reaction
        'prerequisite' => 2730,  // Hit-and-trial balancing method
        'type' => 'requires', 'gate' => true,
        'reason' => 'Displacement is recognised by one element taking another\'s place in a balanced equation; the substitution is invisible in an unbalanced one.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2740,       // Precipitation reaction
        'prerequisite' => 7521,  // Conduction by dissolved ionic compounds  (C9)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'A precipitate forms when ions in solution meet and produce an insoluble compound. The learner needs ionic compounds to dissociate in water, which Class 9 establishes.',
        'source' => 'C10 Chemical Reactions and Equations ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 2743,       // Oxidation-reduction (redox) reactions
        'prerequisite' => 2741,  // Oxidation
        'type' => 'requires', 'gate' => true,
        'reason' => 'A redox reaction is one where oxidation and reduction occur together. Half the definition is missing without oxidation.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2743,       // Oxidation-reduction (redox) reactions
        'prerequisite' => 2742,  // Reduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other half of the same definition; a learner holding only one of the two cannot identify a redox reaction.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2744,       // Corrosion
        'prerequisite' => 2743,  // Oxidation-reduction (redox) reactions
        'type' => 'requires', 'gate' => true,
        'reason' => 'Corrosion is presented as metals being oxidised by their surroundings, so it is an application of redox and unexplainable without it.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2745,       // Rusting of iron
        'prerequisite' => 2744,  // Corrosion
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rusting is the worked example of corrosion; the general process is what the example is an instance of.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2746,       // Corrosion of silver and copper
        'prerequisite' => 2744,  // Corrosion
        'type' => 'requires', 'gate' => false,
        'reason' => 'Further instances of the same process, taught to show corrosion is not confined to iron — which only lands once corrosion itself is understood.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2749,       // Prevention of rancidity
        'prerequisite' => 2748,  // Rancidity
        'type' => 'requires', 'gate' => true,
        'reason' => 'Prevention methods are each aimed at interrupting the oxidation that causes rancidity. There is nothing to prevent until the process is known.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2748,       // Rancidity
        'prerequisite' => 2741,  // Oxidation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rancidity is the oxidation of fats and oils. Without oxidation the learner has a food-spoilage fact with no chemistry attached.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Metals and Non-metals (1014)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 116,        // Malleability and Ductility
        'prerequisite' => 114,   // Physical Properties of Metals
        'type' => 'requires', 'gate' => false,
        'reason' => 'Malleability and ductility are two of the physical properties being surveyed, so they are a detail of the category introduced first.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 118,        // Amphoteric Oxides
        'prerequisite' => 117,   // Chemical Reactivity of Metals
        'type' => 'requires', 'gate' => true,
        'reason' => 'An amphoteric oxide is one that reacts with both acids and bases — an exception inside the pattern of metal reactivity, and meaningless without the pattern.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 119,        // Reactivity Series of Metals
        'prerequisite' => 2739,  // Displacement reaction  (C10 Ch.1)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The series is built by observing which metal displaces which from solution. Displacement is the experiment the whole ordering is read from.',
        'source' => 'C10 Metals and Non-metals ← C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 119,        // Reactivity Series of Metals
        'prerequisite' => 117,   // Chemical Reactivity of Metals
        'type' => 'requires', 'gate' => true,
        'reason' => 'The series orders metals by the reactivity just established; without that property there is no dimension along which to order them.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 120,        // Ionic Compound Formation
        'prerequisite' => 7510,  // Ions and ionic bond formation  (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 10 repeats electron transfer between a metal and a non-metal having had ions and ionic bonding taught in full in Class 9, and does not re-derive them.',
        'source' => 'C10 Metals and Non-metals ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 121,        // Properties of Ionic Compounds
        'prerequisite' => 120,   // Ionic Compound Formation
        'type' => 'requires', 'gate' => true,
        'reason' => 'High melting point and conduction in solution are explained by the lattice of ions the formation step produces, so formation has to come first.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 123,        // Metallurgy
        'prerequisite' => 122,   // Minerals, Ores, and Gangue
        'type' => 'requires', 'gate' => true,
        'reason' => 'Metallurgy is the process of getting a metal out of its ore. Ore and gangue are the starting material the whole process operates on.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 124,        // Extraction of Metals
        'prerequisite' => 119,   // Reactivity Series of Metals
        'type' => 'requires', 'gate' => true,
        'reason' => 'Which extraction method an ore needs is decided by where its metal sits in the series — electrolysis at the top, carbon reduction in the middle, heat alone at the bottom. The series IS the decision rule.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 124,        // Extraction of Metals
        'prerequisite' => 123,   // Metallurgy
        'type' => 'requires', 'gate' => true,
        'reason' => 'Extraction is the central stage of the metallurgical sequence, so the sequence has to be in view before one of its stages is detailed.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 125,        // Roasting and Calcination
        'prerequisite' => 124,   // Extraction of Metals
        'type' => 'requires', 'gate' => true,
        'reason' => 'Both are preparatory steps that convert an ore to its oxide before reduction, and only make sense inside the extraction sequence.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 126,        // Electrolytic Refining
        'prerequisite' => 124,   // Extraction of Metals
        'type' => 'requires', 'gate' => true,
        'reason' => 'Refining is what happens to the impure metal extraction produces, so it is the step after and cannot be taught before it.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 126,        // Electrolytic Refining
        'prerequisite' => 7521,  // Conduction by dissolved ionic compounds  (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Refining passes current through a solution of the metal\'s salt and relies on ions carrying that current — the Class 9 fact, used without restatement.',
        'source' => 'C10 Metals and Non-metals ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 128,        // Rusting of Iron
        'prerequisite' => 127,   // Corrosion
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rusting is the specific case of the general corrosion process this chapter has just introduced.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 129,        // Prevention of Corrosion
        'prerequisite' => 127,   // Corrosion
        'type' => 'requires', 'gate' => true,
        'reason' => 'Every prevention method works by blocking air or moisture from reaching the metal, which presupposes knowing what corrosion needs in order to happen.',
        'source' => 'C10 Metals and Non-metals',
    ],
    [
        'concept' => 130,        // Alloys
        'prerequisite' => 7385,  // Alloy definition  (C9)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Class 9 defines an alloy as a homogeneous mixture of metals; Class 10 takes that definition as given and moves to why alloying changes properties.',
        'source' => 'C10 Metals and Non-metals ← C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 130,        // Alloys
        'prerequisite' => 114,   // Physical Properties of Metals
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Alloys are justified by the property improvements they give — hardness, resistance to corrosion — which can only be appreciated against the pure metal\'s properties.',
        'source' => 'C10 Metals and Non-metals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Measurement of Length and Motion (29838)
    // The root of the whole motion ladder. Class 6 names are narrative on
    // this estate ("Why Five Handspans Disagree"), so the ids matter more
    // than the titles.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32700,      // A Length Is a Number and a Unit
        'prerequisite' => 32699, // Why Five Handspans Disagree
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The handspan disagreement is the problem a standard unit solves. Without having seen measurements disagree, a learner has no reason why a number alone is not enough.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32701,      // Why the World Agreed on SI
        'prerequisite' => 32700, // A Length Is a Number and a Unit
        'type' => 'requires', 'gate' => true,
        'reason' => 'Agreeing on SI is agreeing on which unit to attach to the number. The number-and-unit idea is what is being standardised.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32702,      // Metre, Centimetre, Millimetre, Kilometre
        'prerequisite' => 32701, // Why the World Agreed on SI
        'type' => 'requires', 'gate' => true,
        'reason' => 'These are the SI length units being named. A learner meets them as members of the system, so the system has to exist first.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32711,      // Matching Lengths to Units
        'prerequisite' => 32702, // Metre, Centimetre, Millimetre, Kilometre
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing millimetres for a pencil and kilometres for a road means knowing what size each unit describes.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32704,      // Writing a Measurement Properly
        'prerequisite' => 32700, // A Length Is a Number and a Unit
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing it properly means writing both parts. The rule is the number-and-unit idea turned into a convention.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32703,      // Where to Put the Scale and the Eye
        'prerequisite' => 32702, // Metre, Centimetre, Millimetre, Kilometre
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Reading a scale correctly assumes the learner knows what the marks on it stand for, which the units chapter has just supplied.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32713,      // The Thickness of One Page
        'prerequisite' => 32703, // Where to Put the Scale and the Eye
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Measuring a stack and dividing is an indirect technique built on being able to take a direct reading accurately first.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32707,      // In Motion or at Rest
        'prerequisite' => 32705, // Distance Needs a Reference Point
        'type' => 'requires', 'gate' => true,
        'reason' => 'Whether something is moving is only answerable relative to something else. The reference point is what the judgement is made against.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32708,      // The Passengers in Deepa's Bus
        'prerequisite' => 32707, // In Motion or at Rest
        'type' => 'requires', 'gate' => true,
        'reason' => 'The passengers are at rest relative to the bus and moving relative to the road. The example only makes its point once rest and motion are known to depend on the reference.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32709,      // Linear and Circular Motion
        'prerequisite' => 32707, // In Motion or at Rest
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sorting motion into kinds presupposes being able to say that something is moving at all.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32710,      // Oscillatory and Periodic Motion
        'prerequisite' => 32709, // Linear and Circular Motion
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Oscillatory motion is introduced as a third kind alongside the first two, and is defined by contrast with them.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32714,      // A Bicycle That Measures Distance
        'prerequisite' => 32706, // Kilometre Stones on the Way to Delhi
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Both are ways of accumulating distance along a route; the milestone example is what makes counting wheel rotations read as a distance.',
        'source' => 'C6 Measurement of Length and Motion',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Measurement of Time and Motion (25920)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28145,      // What a simple pendulum is
        'prerequisite' => 32710, // Oscillatory and Periodic Motion  (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'A pendulum is the worked example of oscillatory motion. Class 7 uses it as a timekeeper without re-teaching what an oscillation is.',
        'source' => 'C7 Measurement of Time and Motion ← C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 28146,      // Time period of a pendulum
        'prerequisite' => 28145, // What a simple pendulum is
        'type' => 'requires', 'gate' => true,
        'reason' => 'The time period is the duration of one complete swing, so the swing itself has to be defined before it can be timed.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28147,      // Time period depends on length
        'prerequisite' => 28146, // Time period of a pendulum
        'type' => 'requires', 'gate' => true,
        'reason' => 'This is a statement about how the time period changes, so the quantity must exist before a variable can be said to affect it.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28148,      // The second
        'prerequisite' => 28142, // Repeating events as the basis of time
        'type' => 'requires', 'gate' => true,
        'reason' => 'A second is defined by counting a repeating event. The idea that time is measured by repetition is what makes any unit of time possible.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28149,      // Minutes and hours
        'prerequisite' => 28148, // The second
        'type' => 'requires', 'gate' => true,
        'reason' => 'Minutes and hours are multiples of the second, so the base unit has to be in place before the larger ones are built from it.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28152,      // The fastest covers most distance per unit time
        'prerequisite' => 28150, // What fast and slow mean
        'type' => 'requires', 'gate' => true,
        'reason' => 'This turns the everyday words fast and slow into a comparison that can be measured. The informal idea is what is being made precise.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28153,      // Average speed
        'prerequisite' => 28152, // The fastest covers most distance per unit time
        'type' => 'requires', 'gate' => true,
        'reason' => 'Speed is that comparison written as distance divided by time. The comparison is the definition, stated one step earlier in words.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28153,      // Average speed
        'prerequisite' => 28148, // The second
        'type' => 'requires', 'gate' => true,
        'reason' => 'Speed divides a distance by a time, so a learner with no unit of time has nothing to divide by.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28153,      // Average speed
        'prerequisite' => 32702, // Metre, Centimetre, Millimetre, Kilometre  (C6)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other half of the ratio is a length, so the length units from Class 6 are the numerator of every speed the learner will ever compute.',
        'source' => 'C7 Measurement of Time and Motion ← C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 28154,      // Calculating speed from a journey
        'prerequisite' => 28153, // Average speed
        'type' => 'requires', 'gate' => true,
        'reason' => 'The calculation is the definition applied to real numbers, so it cannot be attempted before the definition exists.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28155,      // Converting between m/s and km/h
        'prerequisite' => 28153, // Average speed
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting the unit of a speed presupposes knowing what the unit is a unit OF.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28156,      // Finding distance and time from speed
        'prerequisite' => 28154, // Calculating speed from a journey
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rearranging for distance or time is the same relationship solved the other way round, so the forward calculation has to be secure first.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28157,      // Using a railway timetable
        'prerequisite' => 28149, // Minutes and hours
        'type' => 'requires', 'gate' => false,
        'reason' => 'A timetable is read in hours and minutes and differences between them, which is the unit arithmetic taught just before it.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28158,      // The speedometer
        'prerequisite' => 28153, // Average speed
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The instrument is introduced as a device that reads out the quantity just defined; without speed it is a dial showing a number.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28159,      // The odometer
        'prerequisite' => 32714, // A Bicycle That Measures Distance  (C6)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The odometer is the Class 6 rotation-counting bicycle made into an instrument, and Class 7 presents it as already familiar.',
        'source' => 'C7 Measurement of Time and Motion ← C6 Measurement of Length and Motion',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Describing Motion Around Us (8600)
    // Where motion stops being descriptive and becomes quantitative.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7268,       // Reference point for position
        'prerequisite' => 32705, // Distance Needs a Reference Point  (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The same idea, formalised. Class 9 assumes the learner already accepts that a position is only stated relative to something.',
        'source' => 'C9 Describing Motion Around Us ← C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 7269,       // Position depends on distance and direction
        'prerequisite' => 7268,  // Reference point for position
        'type' => 'requires', 'gate' => true,
        'reason' => 'Distance and direction are both measured FROM the reference point, so it has to be fixed before either can be stated.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7270,       // Motion as change in position
        'prerequisite' => 7269,  // Position depends on distance and direction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Motion is defined as position changing, so position must be a well-defined quantity before change in it means anything.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7271,       // Rest as no change in position
        'prerequisite' => 7270,  // Motion as change in position
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rest is the negation of motion and is defined by contrast with it.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7275,       // Displacement definition
        'prerequisite' => 7269,  // Position depends on distance and direction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Displacement is the change in position including direction, so both components of position must already be established.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7276,       // Magnitude and direction of displacement
        'prerequisite' => 7275,  // Displacement definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting displacement into magnitude and direction is analysing the quantity, which must exist to be analysed.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7277,       // Distance-displacement equality condition
        'prerequisite' => 7275,  // Displacement definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The condition says when the two quantities coincide, so both have to be separately defined before they can be compared.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7277,       // Distance-displacement equality condition
        'prerequisite' => 7274,  // Total distance travelled
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other half of the comparison. Without path length there is nothing for displacement to be equal to or shorter than.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7278,       // Displacement magnitude never exceeds distance
        'prerequisite' => 7277,  // Distance-displacement equality condition
        'type' => 'requires', 'gate' => true,
        'reason' => 'This is the general inequality of which the equality case is the boundary, so it follows the comparison being set up.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7280,       // Average speed definition
        'prerequisite' => 28153, // Average speed  (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 restates the Class 7 ratio with symbols and SI units and then builds velocity on top of it. The ratio itself is assumed, not re-derived.',
        'source' => 'C9 Describing Motion Around Us ← C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 7282,       // Average velocity definition
        'prerequisite' => 7280,  // Average speed definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Velocity is speed with a direction attached. The scalar has to be secure before direction is added to it.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7282,       // Average velocity definition
        'prerequisite' => 7275,  // Displacement definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Average velocity divides displacement, not distance, by time. Using the wrong numerator is the most common error here, and it is impossible to avoid without displacement.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7283,       // Round trip average velocity zero
        'prerequisite' => 7282,  // Average velocity definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The zero result follows from displacement being zero over a round trip, which only surprises a learner who holds the velocity definition.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7284,       // Average acceleration definition
        'prerequisite' => 7282,  // Average velocity definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Acceleration is the rate of change of velocity. Velocity is literally a term in the definition, so it cannot come after.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7285,       // SI unit of acceleration
        'prerequisite' => 7284,  // Average acceleration definition
        'type' => 'requires', 'gate' => false,
        'reason' => 'The unit metres per second squared is read off the defining ratio, so the definition supplies it.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7286,       // Acceleration direction when speed increases
        'prerequisite' => 7284,  // Average acceleration definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The direction of the acceleration is the direction of the velocity CHANGE, which the definition is what supplies.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7287,       // Acceleration from direction change
        'prerequisite' => 7284,  // Average acceleration definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'That turning alone counts as accelerating follows from velocity being a vector; it is the definition\'s least intuitive consequence.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7288,       // Condition for constant acceleration
        'prerequisite' => 7284,  // Average acceleration definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Constancy is a property of the quantity, so the quantity must be defined before it can be said to hold steady.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7290,       // Gravitational acceleration in free fall
        'prerequisite' => 7288,  // Condition for constant acceleration
        'type' => 'requires', 'gate' => true,
        'reason' => 'Free fall is presented as the standard example of uniform acceleration, so the general case has to be available for it to be an example of.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7292,       // Plotting position-time graph
        'prerequisite' => 7291,  // Purpose of motion graphs
        'type' => 'requires', 'gate' => false,
        'reason' => 'The purpose section says what the graphs are for; plotting one without it is drawing axes for no stated reason.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7293,       // Constant velocity position-time graph
        'prerequisite' => 7292,  // Plotting position-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recognising the straight line for constant velocity means being able to read a position-time plot at all.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7294,       // Position-time graph at rest
        'prerequisite' => 7292,  // Plotting position-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'The horizontal line is another shape read off the same plot, so the plot has to be readable first.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7295,       // Slope of position-time graph
        'prerequisite' => 7293,  // Constant velocity position-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the slope IS the velocity is the point of the whole graph section, and it is established from the constant-velocity case first.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7296,       // Horizontal velocity-time graph
        'prerequisite' => 7282,  // Average velocity definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The vertical axis is velocity, so the quantity plotted must be understood before the plot can be read.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7297,       // Slope of velocity-time graph
        'prerequisite' => 7296,  // Horizontal velocity-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'The slope of this graph is the acceleration, which can only be extracted once the learner can read the graph it is the slope of.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7297,       // Slope of velocity-time graph
        'prerequisite' => 7284,  // Average acceleration definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading the slope as an acceleration requires knowing what acceleration is; otherwise the learner has computed a gradient with no physical meaning.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7298,       // Area under velocity-time graph
        'prerequisite' => 7296,  // Horizontal velocity-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'The area gives the displacement, and it is computed under the curve the learner must already be able to read.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7299,       // Kinematic equations set
        'prerequisite' => 7288,  // Condition for constant acceleration
        'type' => 'requires', 'gate' => true,
        'reason' => 'All three equations hold only for uniform acceleration. Applying them without that condition is the single biggest source of wrong answers in this chapter.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7300,       // First kinematic equation
        'prerequisite' => 7297,  // Slope of velocity-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'NCERT derives v = u + at from the slope of the velocity-time graph. A learner who cannot read that slope can only memorise the result.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7301,       // Second kinematic equation
        'prerequisite' => 7298,  // Area under velocity-time graph
        'type' => 'requires', 'gate' => true,
        'reason' => 'The displacement equation is derived as the area under the same graph, so the area interpretation is the derivation itself.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7302,       // Third kinematic equation
        'prerequisite' => 7300,  // First kinematic equation
        'type' => 'requires', 'gate' => true,
        'reason' => 'The third equation is obtained by eliminating time between the first two, so the first is an input to its derivation.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7302,       // Third kinematic equation
        'prerequisite' => 7301,  // Second kinematic equation
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other input to the same elimination; without it there is nothing to eliminate time from.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7303,       // Kinematic equations sign convention
        'prerequisite' => 7272,  // Straight-line direction sign convention
        'type' => 'requires', 'gate' => true,
        'reason' => 'Signs in the equations mean directions along the line, which is the convention set up at the start of the chapter.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7303,       // Kinematic equations sign convention
        'prerequisite' => 7299,  // Kinematic equations set
        'type' => 'requires', 'gate' => true,
        'reason' => 'There is no convention to apply until there are equations to apply it to.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7304,       // Stopping distance dependence
        'prerequisite' => 7302,  // Third kinematic equation
        'type' => 'requires', 'gate' => true,
        'reason' => 'That stopping distance goes as the square of the speed is read straight off v squared equals u squared plus 2as, and is invisible without it.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7306,       // One revolution distance
        'prerequisite' => 7305,  // Definition of circular motion
        'type' => 'requires', 'gate' => true,
        'reason' => 'A revolution is a lap of the circular path, so the motion has to be defined before one of its laps can be measured.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7307,       // One revolution displacement
        'prerequisite' => 7306,  // One revolution distance
        'type' => 'requires', 'gate' => true,
        'reason' => 'The point is that the lap covers a circumference of distance but returns to the start, so the distance has to be established for the contrast to land.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7308,       // Speed constant direction changes
        'prerequisite' => 7287,  // Acceleration from direction change
        'type' => 'requires', 'gate' => true,
        'reason' => 'That steady circular motion is accelerated motion depends entirely on a direction change counting as acceleration.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7308,       // Speed constant direction changes
        'prerequisite' => 7305,  // Definition of circular motion
        'type' => 'requires', 'gate' => true,
        'reason' => 'The claim is about a body in circular motion, so the motion must be identifiable for the claim to have a subject.',
        'source' => 'C9 Describing Motion Around Us',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · How Forces Affect Motion (8605)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7397,       // Overcoming friction to start motion
        'prerequisite' => 7396,  // Friction as opposing force
        'type' => 'requires', 'gate' => true,
        'reason' => 'What has to be overcome is the opposing force, so the force must be named before overcoming it is a described action.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7398,       // Friction depends on surfaces in contact
        'prerequisite' => 7396,  // Friction as opposing force
        'type' => 'requires', 'gate' => true,
        'reason' => 'This states what the size of friction depends on, so the quantity must exist before its dependence can be given.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7400,       // Friction brings moving objects to rest
        'prerequisite' => 7396,  // Friction as opposing force
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bringing a body to rest is what an opposing force does over time; the effect follows from the definition.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7401,       // Frictionless motion continues indefinitely
        'prerequisite' => 7400,  // Friction brings moving objects to rest
        'type' => 'requires', 'gate' => true,
        'reason' => 'The thought experiment removes the cause of stopping. It only reads as a conclusion if friction was the identified cause.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7402,       // First law and inertia
        'prerequisite' => 7401,  // Frictionless motion continues indefinitely
        'type' => 'requires', 'gate' => true,
        'reason' => 'The first law is that thought experiment stated as a law. Taught without it, the law contradicts everyday experience and learners reject it.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7403,       // Newton's second law (F = ma)
        'prerequisite' => 7402,  // First law and inertia
        'type' => 'requires', 'gate' => true,
        'reason' => 'The second law quantifies what the first law says qualitatively about unbalanced force, so the qualitative statement comes first.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7403,       // Newton's second law (F = ma)
        'prerequisite' => 7284,  // Average acceleration definition  (8600)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Acceleration is one of the two terms in F = ma. A learner who cannot compute an acceleration cannot compute a force, whatever they understand about the law.',
        'source' => 'C9 How Forces Affect Motion ← C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7404,       // Definition of the newton
        'prerequisite' => 7403,  // Newton's second law (F = ma)
        'type' => 'requires', 'gate' => true,
        'reason' => 'One newton is defined as the force giving one kilogram one metre per second squared, which is the second law read as a unit definition.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7405,       // Weight equals mass times g
        'prerequisite' => 7403,  // Newton's second law (F = ma)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Weight is F = ma with the acceleration set to g. It is a special case of the law and unreachable without it.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7405,       // Weight equals mass times g
        'prerequisite' => 7290,  // Gravitational acceleration in free fall  (8600)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The g being multiplied is the free-fall acceleration established in the motion chapter; without it the formula has an unexplained constant.',
        'source' => 'C9 How Forces Affect Motion ← C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7406,       // Longer impact time lowers stopping force
        'prerequisite' => 7403,  // Newton's second law (F = ma)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Spreading a velocity change over a longer time lowers the acceleration and therefore the force. The reasoning is the second law applied twice.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7407,       // Finding force from velocity-time graph
        'prerequisite' => 7297,  // Slope of velocity-time graph  (8600)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The force is obtained by reading the acceleration off the slope and multiplying by mass. The graph-reading step is Mathematics the physics chapter assumes.',
        'source' => 'C9 How Forces Affect Motion ← C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7407,       // Finding force from velocity-time graph
        'prerequisite' => 7403,  // Newton's second law (F = ma)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The multiplication by mass is the second law; the graph alone gives an acceleration and no force.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7408,       // Net force from opposing forces
        'prerequisite' => 7396,  // Friction as opposing force
        'type' => 'requires', 'gate' => true,
        'reason' => 'The opposing force being subtracted is friction in every worked example, so it must already be identified as acting against the motion.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7409,       // Connected objects as a single system
        'prerequisite' => 7408,  // Net force from opposing forces
        'type' => 'requires', 'gate' => true,
        'reason' => 'Treating bodies as one system means adding their forces into a single net force, so combining forces has to come first.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7410,       // Internal versus external forces
        'prerequisite' => 7409,  // Connected objects as a single system
        'type' => 'requires', 'gate' => true,
        'reason' => 'Internal and external are defined relative to the boundary of the system, so the system has to be drawn before a force can be inside or outside it.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7411,       // System acceleration uses total mass
        'prerequisite' => 7410,  // Internal versus external forces
        'type' => 'requires', 'gate' => true,
        'reason' => 'Only external forces drive the system, and the reason internal ones cancel is what justifies using the total mass.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7412,       // Vertical forces balance in system
        'prerequisite' => 7408,  // Net force from opposing forces
        'type' => 'requires', 'gate' => true,
        'reason' => 'Balance means the net vertical force is zero, which is the net-force idea applied to one direction.',
        'source' => 'C9 How Forces Affect Motion',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Cell: The Building Block of Life (8593)
    // The foundation every Class 10 physiology chapter assumes.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7310, 'prerequisite' => 7309,   // Cells are microscopic ← Resolution limit of human eye
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cells being invisible is stated as being below the eye\'s resolving power, so that limit is the measure the claim is made against.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7313, 'prerequisite' => 7312,   // Electron microscope advantage ← Total magnification calculation
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The advantage is expressed as far higher magnification and resolution, which only means something to a learner who can already compute magnification.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7318, 'prerequisite' => 7316,   // Osmosis ← Selective permeability of cell membrane
        'type' => 'requires', 'gate' => true,
        'reason' => 'Osmosis is defined as movement across a SELECTIVELY permeable membrane. The selectivity is the property that makes it osmosis rather than plain diffusion.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7320, 'prerequisite' => 7316,   // Fluid-mosaic model ← Selective permeability
        'type' => 'requires', 'gate' => true,
        'reason' => 'The model is offered as the structure that explains how a membrane can let some things through and not others.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7321, 'prerequisite' => 7320,   // Role of membrane proteins ← Fluid-mosaic model
        'type' => 'requires', 'gate' => true,
        'reason' => 'The proteins are the mosaic part of the model, so their role cannot be described before the arrangement they sit in.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7323, 'prerequisite' => 7322,   // Cell wall maintains shape ← Functions of cell wall
        'type' => 'requires', 'gate' => true,
        'reason' => 'Maintaining shape is one of the functions being enumerated, so it is a detail of the category introduced first.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7324, 'prerequisite' => 7322,   // Absence of cell wall in animals ← Functions of cell wall
        'type' => 'requires', 'gate' => true,
        'reason' => 'The absence is significant only against what the wall does; a learner without its functions cannot say what animal cells lack.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7325, 'prerequisite' => 7322,   // Cell wall is made of cellulose ← Functions of cell wall
        'type' => 'requires', 'gate' => false,
        'reason' => 'The composition is taught as the reason the wall is rigid, so it follows the function it explains.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7327, 'prerequisite' => 7326,   // Prokaryotic nucleoid ← Prokaryotic and eukaryotic cells
        'type' => 'requires', 'gate' => true,
        'reason' => 'The nucleoid is what a prokaryote has instead of a nucleus, so the prokaryote/eukaryote split has to be in place first.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7329, 'prerequisite' => 7326,   // Nucleus and nuclear membrane ← Prokaryotic and eukaryotic cells
        'type' => 'requires', 'gate' => true,
        'reason' => 'A membrane-bound nucleus is the defining feature of a eukaryote, which is the category this concept is describing.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7330, 'prerequisite' => 7329,   // Chromatin, chromosomes and genes ← Nucleus and nuclear membrane
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chromatin is the material inside the nucleus. Without the nucleus there is no compartment for it to be in, and every later genetics concept rests on this one.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7328, 'prerequisite' => 7326,   // Division of labour in organelles ← Prokaryotic and eukaryotic cells
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Division of labour is what distinguishes the compartmented eukaryotic cell from the prokaryotic one, and is introduced by that contrast.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7331, 'prerequisite' => 7328,   // Ribosomes and protein synthesis ← Division of labour
        'type' => 'requires', 'gate' => false,
        'reason' => 'Each organelle is introduced as one worker in the division of labour, so the organising idea comes before the individual jobs.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7332, 'prerequisite' => 7328,   // Types of endoplasmic reticulum ← Division of labour
        'type' => 'requires', 'gate' => false,
        'reason' => 'The ER is presented as the transport and synthesis compartment within the shared division of labour.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7333, 'prerequisite' => 7332,   // Golgi apparatus ← Types of endoplasmic reticulum
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The Golgi is taught as receiving and packaging what the ER produces, so the ER has to exist for the handover to be described.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7334, 'prerequisite' => 7333,   // Lysosomes digest waste ← Golgi apparatus
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Lysosomes are described as vesicles budded from the Golgi, so their origin is the preceding organelle.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7335, 'prerequisite' => 7328,   // Mitochondria ← Division of labour
        'type' => 'requires', 'gate' => false,
        'reason' => 'The powerhouse role is one station in the division of labour the chapter has just set up.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7336, 'prerequisite' => 7328,   // Chloroplasts ← Division of labour
        'type' => 'requires', 'gate' => false,
        'reason' => 'Chloroplasts are the food-making station, and are introduced alongside the other organelles as part of the same scheme.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7337, 'prerequisite' => 7336,   // Chromoplasts ← Chloroplasts
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Chromoplasts are taught as a second kind of plastid alongside the chloroplast, and are defined by the comparison.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7338, 'prerequisite' => 7336,   // Leucoplasts ← Chloroplasts
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The third plastid type, introduced in the same group and distinguished from the pigmented ones.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7341, 'prerequisite' => 7335,   // DNA in mitochondria and plastids ← Mitochondria
        'type' => 'requires', 'gate' => false,
        'reason' => 'The surprising fact is that THESE organelles carry their own DNA, so the organelles must be known before the exception is notable.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7341, 'prerequisite' => 7330,   // DNA in mitochondria and plastids ← Chromatin, chromosomes and genes
        'type' => 'requires', 'gate' => true,
        'reason' => 'The point is that DNA is found outside the nucleus as well. Without knowing DNA belongs in the nucleus, there is no exception to notice.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7343, 'prerequisite' => 7342,   // Regulation by cell cycle ← Purpose of cell division
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cycle regulates a process, so the process and its purpose have to be established before its control is described.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7344, 'prerequisite' => 7343,   // Role of mitosis ← Regulation by cell cycle
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mitosis is a stage of the cell cycle, so the cycle is the frame the stage sits in.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7344, 'prerequisite' => 7330,   // Role of mitosis ← Chromatin, chromosomes and genes
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mitosis is described as chromosomes duplicating and separating equally. Without chromosomes there is nothing being divided.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7345, 'prerequisite' => 7344,   // Role of meiosis ← Role of mitosis
        'type' => 'requires', 'gate' => true,
        'reason' => 'Meiosis is taught by contrast with mitosis — halving rather than preserving the chromosome number — so the first is the reference for the second.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7346, 'prerequisite' => 7344,   // Result of mitosis ← Role of mitosis
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two identical daughter cells is the outcome of the process, so the process has to be described before its result.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7347, 'prerequisite' => 7345,   // Result of meiosis ← Role of meiosis
        'type' => 'requires', 'gate' => true,
        'reason' => 'Four cells with half the chromosome number is what meiosis produces, and is meaningless without the process.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7348, 'prerequisite' => 7343,   // Consequences of faulty division ← Regulation by cell cycle
        'type' => 'requires', 'gate' => true,
        'reason' => 'A fault is a failure of the regulation, so the normal control has to be known before its breakdown can be discussed.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7352, 'prerequisite' => 7343,   // Contact inhibition ← Regulation by cell cycle
        'type' => 'requires', 'gate' => false,
        'reason' => 'Contact inhibition is one of the mechanisms by which division is stopped, so it belongs inside the regulation topic.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7353, 'prerequisite' => 7352,   // Cancer ← Contact inhibition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cancer is explained as cells that have lost contact inhibition, so that control is the thing whose failure defines the disease.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7353, 'prerequisite' => 7348,   // Cancer ← Consequences of faulty cell division
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cancer is the headline consequence of division going wrong, so the general consequence topic frames it.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7354, 'prerequisite' => 7351,   // Programmed cell death ← Definite life span of cells
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Programmed death is the mechanism behind a cell having a fixed life span, so the observation precedes its explanation.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Tissues in Action (8599)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7216, 'prerequisite' => 7322,   // Plant cell wall provides support ← Functions of cell wall (8593)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The tissue chapter explains plant rigidity by the wall each cell carries, which the cell chapter supplies and this one does not restate.',
        'source' => 'C9 Tissues in Action ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7221, 'prerequisite' => 7220,   // Meristematic tissue ← Tissue growth patterns differ
        'type' => 'requires', 'gate' => true,
        'reason' => 'Meristem is introduced as the answer to why plants grow only at certain points, which is the pattern just observed.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7222, 'prerequisite' => 7221,   // Apical meristem ← Meristematic tissue
        'type' => 'requires', 'gate' => true,
        'reason' => 'Apical is one location of the meristem, so the tissue has to exist before its positions are distinguished.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7223, 'prerequisite' => 7221,   // Lateral meristem ← Meristematic tissue
        'type' => 'requires', 'gate' => true,
        'reason' => 'Lateral meristem is the second location, and is defined by contrast with the apical one within the same tissue type.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7224, 'prerequisite' => 7221,   // Intercalary meristem ← Meristematic tissue
        'type' => 'requires', 'gate' => false,
        'reason' => 'The third location of the same tissue, meaningful only once meristematic tissue itself is understood.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7225, 'prerequisite' => 7223,   // Annual rings ← Lateral meristem
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rings are the record of girth added by the lateral meristem season by season, so that tissue is what produces them.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7226, 'prerequisite' => 7344,   // Meristematic cells suited for division ← Role of mitosis (8593)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Being suited for division means being suited for mitosis — thin walls, dense cytoplasm, large nucleus. The process is what the features are suited to.',
        'source' => 'C9 Tissues in Action ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7227, 'prerequisite' => 7221,   // Differentiation forms permanent tissues ← Meristematic tissue
        'type' => 'requires', 'gate' => true,
        'reason' => 'Differentiation is what meristematic cells do when they stop dividing, so the dividing tissue is the starting material.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7228, 'prerequisite' => 7227,   // Permanent tissues simple or complex ← Differentiation
        'type' => 'requires', 'gate' => true,
        'reason' => 'The classification applies to permanent tissues, which only exist once differentiation has produced them.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7229, 'prerequisite' => 7228,   // Epidermis ← Permanent tissues simple or complex
        'type' => 'requires', 'gate' => false,
        'reason' => 'Epidermis is presented as one of the simple permanent tissues in the classification just given.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7230, 'prerequisite' => 7229,   // Root hairs ← Epidermis
        'type' => 'requires', 'gate' => true,
        'reason' => 'Root hairs are outgrowths of epidermal cells, so the layer has to be known before its extensions are described.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7231, 'prerequisite' => 7229,   // Stomata ← Epidermis
        'type' => 'requires', 'gate' => true,
        'reason' => 'Stomata are pores in the epidermis bounded by guard cells, so the layer they interrupt must already be established.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7232, 'prerequisite' => 7228,   // Parenchyma ← Permanent tissues
        'type' => 'requires', 'gate' => false,
        'reason' => 'Parenchyma is named as a simple permanent tissue within the classification scheme.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7233, 'prerequisite' => 7232,   // Collenchyma ← Parenchyma
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Collenchyma is taught as parenchyma with thickened corners, so it is defined against the simpler tissue.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7234, 'prerequisite' => 7233,   // Sclerenchyma ← Collenchyma
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The third support tissue, introduced as the most thickened of the series and understood by its place in it.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7235, 'prerequisite' => 7228,   // Xylem ← Permanent tissues simple or complex
        'type' => 'requires', 'gate' => true,
        'reason' => 'Xylem is the worked example of a COMPLEX permanent tissue, so the simple/complex distinction is what makes it one.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7236, 'prerequisite' => 7235,   // Phloem ← Xylem
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Phloem is introduced as the second conducting tissue and is described throughout by contrast with xylem.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7239, 'prerequisite' => 7238,   // Thin epithelium ← Epithelial lining
        'type' => 'requires', 'gate' => true,
        'reason' => 'Thinness is a variation of the epithelial layer, so the layer type must be established before its forms are separated.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7240, 'prerequisite' => 7238,   // Multilayered epithelium ← Epithelial lining
        'type' => 'requires', 'gate' => false,
        'reason' => 'Another form of the same tissue, distinguished by layering and only describable once the tissue is known.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7241, 'prerequisite' => 7238,   // Secretory epithelium ← Epithelial lining
        'type' => 'requires', 'gate' => false,
        'reason' => 'Glandular epithelium is a functional variant of the lining tissue introduced first.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7242, 'prerequisite' => 7238,   // Sensory epithelium ← Epithelial lining
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sensory epithelium is the stimulus-detecting variant, and is one of the set being enumerated.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7243, 'prerequisite' => 7238,   // Tall epithelium ← Epithelial lining
        'type' => 'requires', 'gate' => false,
        'reason' => 'Columnar epithelium is the absorbing variant, described within the same classification of the lining tissue.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7245, 'prerequisite' => 7244,   // Matrix decides consistency ← Connective tissue binds
        'type' => 'requires', 'gate' => true,
        'reason' => 'The matrix is the defining feature of connective tissue, so the tissue must be introduced before its matrix explains the varieties.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7246, 'prerequisite' => 7245,   // Blood components ← Matrix decides consistency
        'type' => 'requires', 'gate' => true,
        'reason' => 'Blood is presented as connective tissue with a fluid matrix, which is exactly the idea the previous concept supplies.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7247, 'prerequisite' => 7245,   // Bone matrix ← Matrix decides consistency
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bone is the hard-matrix case in the same series, and its hardness is explained by the matrix idea.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7248, 'prerequisite' => 7245,   // Cartilage ← Matrix decides consistency
        'type' => 'requires', 'gate' => false,
        'reason' => 'Cartilage sits between blood and bone on the matrix-consistency scale the previous concept establishes.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7249, 'prerequisite' => 7244,   // Tendons and ligaments ← Connective tissue binds
        'type' => 'requires', 'gate' => false,
        'reason' => 'Both are named as connective tissues that join structures, which is the binding role the category was defined by.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7251, 'prerequisite' => 7250,   // Skeletal muscle ← Voluntary and involuntary movements
        'type' => 'requires', 'gate' => true,
        'reason' => 'Skeletal muscle is defined as the voluntary type, so the voluntary/involuntary split is what classifies it.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7252, 'prerequisite' => 7250,   // Smooth muscle ← Voluntary and involuntary movements
        'type' => 'requires', 'gate' => true,
        'reason' => 'Smooth muscle is the involuntary type, and is identified by the same distinction.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7253, 'prerequisite' => 7250,   // Cardiac muscle ← Voluntary and involuntary movements
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cardiac muscle is the involuntary muscle that never tires, placed by the same voluntary/involuntary criterion.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7255, 'prerequisite' => 7254,   // Neurons transmit messages ← Nervous tissue coordinates
        'type' => 'requires', 'gate' => true,
        'reason' => 'The neuron is the cell that makes up nervous tissue, so the tissue is the context its function is described in.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7256, 'prerequisite' => 7255,   // Neuron parts ← Neurons transmit messages
        'type' => 'requires', 'gate' => true,
        'reason' => 'Each part is explained by the job it does in transmitting a message, so the function has to precede the anatomy.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7257, 'prerequisite' => 7254,   // Muscles depend on nervous instructions ← Nervous tissue
        'type' => 'requires', 'gate' => true,
        'reason' => 'The instruction comes from nervous tissue, so that tissue must exist for the dependency to be stated.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7259, 'prerequisite' => 7251,   // Muscle contraction moves bones ← Skeletal muscle structure
        'type' => 'requires', 'gate' => true,
        'reason' => 'It is skeletal muscle that pulls on bone, so its structure and attachment have to be known for the movement to be explained.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7260, 'prerequisite' => 7259,   // Joints allow movement ← Muscle contraction moves bones
        'type' => 'requires', 'gate' => true,
        'reason' => 'A joint is where the bone movement happens, and the chapter\'s point is that joints permit but do not cause it.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7261, 'prerequisite' => 7260,   // Ball and socket joint ← Joints allow movement
        'type' => 'requires', 'gate' => false,
        'reason' => 'One joint type within the classification, describable only once joints as a category exist.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7262, 'prerequisite' => 7260,   // Hinge joint ← Joints allow movement
        'type' => 'requires', 'gate' => false,
        'reason' => 'A second joint type in the same series, defined by the single plane of movement it allows.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7263, 'prerequisite' => 7260,   // Pivot joint ← Joints allow movement
        'type' => 'requires', 'gate' => false,
        'reason' => 'The rotating joint type, placed alongside the others in the same classification.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7264, 'prerequisite' => 7260,   // Fixed joints ← Joints allow movement
        'type' => 'requires', 'gate' => false,
        'reason' => 'Immovable joints are the limiting case of the category and are only notable against joints that do move.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7258, 'prerequisite' => 7259,   // Musculoskeletal system ← Muscle contraction moves bones
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The system is named once muscle and bone have been shown to work together, which is the mechanism just described.',
        'source' => 'C9 Tissues in Action',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Life Processes (1016) — the Class 9 tissue work cashed in
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 152, 'prerequisite' => 151,     // Autotrophic Nutrition ← Life Processes Definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nutrition is presented as one of the life processes just enumerated, so the list frames the mode.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 153, 'prerequisite' => 152,     // Photosynthesis Process ← Autotrophic Nutrition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Photosynthesis is the mechanism by which autotrophs make food, so the mode of nutrition is what it is the mechanism for.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 153, 'prerequisite' => 7336,    // Photosynthesis Process ← Chloroplasts and photosynthesis (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 places photosynthesis in the chloroplast; Class 10 gives its equation and conditions and never restates where it happens.',
        'source' => 'C10 Life Processes ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 154, 'prerequisite' => 7231,    // Stomata and Guard Cells ← Stomata perform exchange (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 introduces stomata as epidermal pores with guard cells; Class 10 uses them for gas exchange and transpiration without re-describing the structure.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 155, 'prerequisite' => 151,     // Heterotrophic Nutrition ← Life Processes Definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The second mode of nutrition inside the same set of life processes, and defined against the first.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 156, 'prerequisite' => 155,     // Human Digestive System ← Heterotrophic Nutrition
        'type' => 'requires', 'gate' => true,
        'reason' => 'The human gut is the worked example of heterotrophic nutrition, so the mode has to be defined for the anatomy to illustrate it.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 157, 'prerequisite' => 156,     // Enzymes in Digestion ← Human Digestive System
        'type' => 'requires', 'gate' => true,
        'reason' => 'Each enzyme is named by where it acts along the canal, so the canal must be mapped before the enzymes are placed on it.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 158, 'prerequisite' => 7252,    // Peristaltic Movements ← Smooth muscle (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Peristalsis is smooth muscle contracting in waves along the gut wall. Class 10 names the movement and assumes the muscle type from Class 9.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 158, 'prerequisite' => 156,     // Peristaltic Movements ← Human Digestive System
        'type' => 'requires', 'gate' => true,
        'reason' => 'The waves move food along the alimentary canal, so the canal has to be in place for the movement to have a path.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 159, 'prerequisite' => 151,     // Respiration Pathways ← Life Processes Definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Respiration is one of the life processes the opening section lists, which is the frame the pathways sit in.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 160, 'prerequisite' => 159,     // ATP Energy Currency ← Respiration Pathways
        'type' => 'requires', 'gate' => true,
        'reason' => 'ATP is what the respiration pathways produce, so the pathways are where it comes from.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 160, 'prerequisite' => 7335,    // ATP Energy Currency ← Mitochondria supply cellular energy (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 names the mitochondrion the powerhouse; Class 10 says what the power actually is. The organelle is assumed known.',
        'source' => 'C10 Life Processes ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 161, 'prerequisite' => 159,     // Human Respiratory System ← Respiration Pathways
        'type' => 'requires', 'gate' => true,
        'reason' => 'The organ system is presented as how a human supplies the oxygen the aerobic pathway needs.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 161, 'prerequisite' => 7239,    // Human Respiratory System ← Thin epithelium allows rapid exchange (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The alveolus works because its wall is one cell thick — the Class 9 fact about thin epithelium, used in Class 10 without restatement.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 162, 'prerequisite' => 151,     // Human Circulatory System ← Life Processes Definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'Transport is one of the listed life processes, and the circulatory system is introduced as how animals carry it out.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 163, 'prerequisite' => 162,     // Heart Structure ← Human Circulatory System
        'type' => 'requires', 'gate' => true,
        'reason' => 'The heart is described as the pump inside the circulatory system, so the system is the context for the organ.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 163, 'prerequisite' => 7253,    // Heart Structure ← Cardiac muscle (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the heart beats without tiring is a property of cardiac muscle, taught in Class 9 and used here as given.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 164, 'prerequisite' => 163,     // Double Circulation ← Heart Structure and Function
        'type' => 'requires', 'gate' => true,
        'reason' => 'Double circulation is blood passing through the heart twice per circuit, which cannot be traced without the chambers.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 165, 'prerequisite' => 7246,    // Blood Vessels Types ← Blood components (C9)
        'type' => 'requires', 'gate' => false,
        'reason' => 'The vessels are described by what they carry and at what pressure, which assumes blood and its components are already familiar.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 165, 'prerequisite' => 162,     // Blood Vessels Types ← Human Circulatory System
        'type' => 'requires', 'gate' => true,
        'reason' => 'Arteries, veins and capillaries are the plumbing of the system, so the system has to be introduced before its parts.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 166, 'prerequisite' => 7235,    // Transportation in Plants ← Xylem (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Water rises through xylem. Class 10 names the tissue as known and describes only the mechanism, so a learner without it has an unexplained word.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 166, 'prerequisite' => 7236,    // Transportation in Plants ← Phloem (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Food moves through phloem, and translocation is described entirely in terms of that tissue without redefining it.',
        'source' => 'C10 Life Processes ← C9 Tissues in Action',
    ],
    [
        'concept' => 167, 'prerequisite' => 166,     // Transpiration Pull ← Transportation in Plants
        'type' => 'requires', 'gate' => true,
        'reason' => 'The pull is the mechanism that drives the transport just introduced, so the transport is what it explains.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 167, 'prerequisite' => 154,     // Transpiration Pull ← Stomata and Guard Cells
        'type' => 'requires', 'gate' => true,
        'reason' => 'Water is lost through the stomata and that loss is what creates the pull, so the pore has to be established as the exit.',
        'source' => 'C10 Life Processes',
    ],
    [
        'concept' => 168, 'prerequisite' => 162,     // Human Excretory System ← Human Circulatory System
        'type' => 'requires', 'gate' => true,
        'reason' => 'The kidney filters blood arriving through the circulation, so the delivery system has to exist before filtration is described.',
        'source' => 'C10 Life Processes',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Control and Coordination (1017)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 170, 'prerequisite' => 169,     // Nervous System in Animals ← Control and Coordination
        'type' => 'requires', 'gate' => true,
        'reason' => 'The nervous system is presented as one of the two coordinating systems the chapter opens by naming.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 171, 'prerequisite' => 7256,    // Neuron Structure and Function ← Neuron parts (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The neuron is taught in Class 9 as a nervous tissue cell. Class 10 opens with the reflex arc and assumes the dendrite, axon and synapse are known anatomy.',
        'source' => 'C10 Control and Coordination ← C9 Tissues in Action',
    ],
    [
        'concept' => 172, 'prerequisite' => 171,     // Nerve Impulse Transmission ← Neuron Structure and Function
        'type' => 'requires', 'gate' => true,
        'reason' => 'The impulse travels along the structures just described, so the anatomy is the route the signal takes.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 173, 'prerequisite' => 7242,    // Receptors ← Sensory epithelium detects stimuli (C9)
        'type' => 'requires', 'gate' => false,
        'reason' => 'Receptors are the sensory cells Class 9 introduced as sensory epithelium, now given a role at the start of the arc.',
        'source' => 'C10 Control and Coordination ← C9 Tissues in Action',
    ],
    [
        'concept' => 174, 'prerequisite' => 172,     // Reflex Action ← Nerve Impulse Transmission
        'type' => 'requires', 'gate' => true,
        'reason' => 'A reflex is a signal travelling a short route, so impulse transmission is the process the reflex is a special case of.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 175, 'prerequisite' => 174,     // Reflex Arc ← Reflex Action
        'type' => 'requires', 'gate' => true,
        'reason' => 'The arc is the pathway the reflex takes, so the response must be known before its route is traced.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 175, 'prerequisite' => 173,     // Reflex Arc ← Receptors
        'type' => 'requires', 'gate' => true,
        'reason' => 'The arc begins at a receptor. Without it the pathway has no starting point.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 176, 'prerequisite' => 172,     // Human Brain ← Nerve Impulse Transmission
        'type' => 'requires', 'gate' => true,
        'reason' => 'The brain is described as where impulses are processed, so the impulse has to exist for the processing to act on something.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 177, 'prerequisite' => 176,     // Central Nervous System ← Human Brain
        'type' => 'requires', 'gate' => true,
        'reason' => 'The CNS is defined as brain plus spinal cord, so the brain is half the definition.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 178, 'prerequisite' => 177,     // Peripheral Nervous System ← Central Nervous System
        'type' => 'requires', 'gate' => true,
        'reason' => 'The PNS is everything outside the CNS, so it is defined entirely by contrast with it.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 179, 'prerequisite' => 176,     // Brain Divisions and Functions ← Human Brain
        'type' => 'requires', 'gate' => true,
        'reason' => 'The divisions are parts of the organ, so the organ must be introduced before it is subdivided.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 180, 'prerequisite' => 169,     // Coordination in Plants ← Control and Coordination
        'type' => 'requires', 'gate' => true,
        'reason' => 'Plant coordination is presented as the same problem solved without a nervous system, so the general topic frames it.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 181, 'prerequisite' => 180,     // Plant Movements ← Coordination in Plants
        'type' => 'requires', 'gate' => true,
        'reason' => 'Movements are the observable evidence that plants coordinate, so the coordination claim comes first.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 182, 'prerequisite' => 181,     // Tropism ← Plant Movements
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tropism is directional plant movement, a subtype of the movements just introduced.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 183, 'prerequisite' => 182,     // Plant Hormones ← Tropism
        'type' => 'requires', 'gate' => true,
        'reason' => 'Hormones are given as the mechanism producing tropic bending, so the phenomenon is what they explain.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 184, 'prerequisite' => 169,     // Endocrine System ← Control and Coordination
        'type' => 'requires', 'gate' => true,
        'reason' => 'The endocrine system is the second animal coordinating system named in the chapter opening.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 185, 'prerequisite' => 184,     // Animal Hormones ← Endocrine System in Animals
        'type' => 'requires', 'gate' => true,
        'reason' => 'Hormones are what endocrine glands secrete, so the system has to be introduced before its products.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 185, 'prerequisite' => 162,     // Animal Hormones ← Human Circulatory System (1016)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Hormones reach their target organs through the bloodstream. Without circulation there is no delivery route and the slowness of hormonal control is unexplainable.',
        'source' => 'C10 Control and Coordination ← C10 Life Processes',
    ],
    [
        'concept' => 186, 'prerequisite' => 185,     // Adrenaline ← Animal Hormones
        'type' => 'requires', 'gate' => false,
        'reason' => 'Adrenaline is one of the named hormones, so it is an instance of the category taught first.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 187, 'prerequisite' => 185,     // Insulin and Blood Sugar ← Animal Hormones
        'type' => 'requires', 'gate' => false,
        'reason' => 'Insulin is a second worked example within the same set of animal hormones.',
        'source' => 'C10 Control and Coordination',
    ],
    [
        'concept' => 188, 'prerequisite' => 187,     // Feedback Mechanisms ← Insulin and Blood Sugar
        'type' => 'requires', 'gate' => true,
        'reason' => 'Blood sugar control is the worked example the feedback idea is drawn from, so the example precedes the generalisation.',
        'source' => 'C10 Control and Coordination',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · How do Organisms Reproduce (1018)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 190, 'prerequisite' => 7330,    // DNA Copying and Variation ← Chromatin, chromosomes and genes (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reproduction is presented as DNA copying. A learner for whom DNA and genes are not yet located in the cell has nothing being copied.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 190, 'prerequisite' => 189,     // DNA Copying and Variation ← Importance of Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'The chapter argues that reproduction matters because DNA copying is never perfect, so the importance frames the mechanism.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 191, 'prerequisite' => 190,     // Asexual Reproduction ← DNA Copying and Variation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asexual reproduction is defined by producing near-identical copies, which is a statement about the DNA copying just described.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 192, 'prerequisite' => 191,     // Fission ← Asexual Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fission is one of the asexual modes being enumerated, so the category has to exist for it to belong to.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 192, 'prerequisite' => 7344,    // Fission ← Role of mitosis (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Binary fission is mitosis in a single-celled organism. Class 10 shows the splitting and assumes the division mechanism from Class 9.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 193, 'prerequisite' => 191,     // Budding and Fragmentation ← Asexual Reproduction
        'type' => 'requires', 'gate' => false,
        'reason' => 'Two further asexual modes in the same enumeration, distinguished from fission by how the parent divides.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 194, 'prerequisite' => 191,     // Regeneration ← Asexual Reproduction
        'type' => 'requires', 'gate' => false,
        'reason' => 'Regeneration is presented within the asexual group, with the caution that it is not reproduction in every case.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 195, 'prerequisite' => 7355,    // Vegetative Propagation ← Totipotency of plant cells (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'A cutting grows into a whole plant because plant cells are totipotent. Class 9 supplies that property and Class 10 relies on it without naming it again.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 195, 'prerequisite' => 191,     // Vegetative Propagation ← Asexual Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vegetative propagation is the plant case of asexual reproduction, so it sits inside that category.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 196, 'prerequisite' => 191,     // Spore Formation ← Asexual Reproduction
        'type' => 'requires', 'gate' => false,
        'reason' => 'Spore formation completes the list of asexual modes and is classified by the same criterion.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 197, 'prerequisite' => 7345,    // Sexual Reproduction ← Role of meiosis (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gametes carry half the chromosome number because they are made by meiosis. Without meiosis the halving is an unexplained rule.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 197, 'prerequisite' => 190,     // Sexual Reproduction ← DNA Copying and Variation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sexual reproduction is justified by the variation two parents produce, which is the copying-and-variation idea applied.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 198, 'prerequisite' => 197,     // Sexual Reproduction in Flowers ← Sexual Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'The flower is the plant organ for the process just defined, so the process frames the anatomy.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 199, 'prerequisite' => 198,     // Pollination and Fertilisation ← Sexual Reproduction in Flowers
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pollen moving from anther to stigma is described using the flower parts named immediately before.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 200, 'prerequisite' => 197,     // Puberty and Sexual Maturation ← Sexual Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Puberty is presented as the point at which the body becomes capable of the process, so the process has to be defined first.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 201, 'prerequisite' => 200,     // Human Male Reproductive System ← Puberty
        'type' => 'requires', 'gate' => false,
        'reason' => 'The organs are described in terms of the maturation that makes them functional, which the puberty section supplies.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 202, 'prerequisite' => 200,     // Human Female Reproductive System ← Puberty
        'type' => 'requires', 'gate' => false,
        'reason' => 'Same for the female system: the anatomy is presented alongside the changes puberty brings about in it.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 203, 'prerequisite' => 202,     // Menstruation ← Human Female Reproductive System
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cycle is described as changes in the uterine lining and ovary, so those organs must be known for the account to have a subject.',
        'source' => 'C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 204, 'prerequisite' => 203,     // Reproductive Health and Contraception ← Menstruation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Contraceptive methods are explained by which stage of the cycle or which organ they interrupt, so the cycle is the thing being interrupted.',
        'source' => 'C10 How do Organisms Reproduce',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Heredity (1019) — the deepest chain in Science
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 205, 'prerequisite' => 190,     // Variation during Reproduction ← DNA Copying and Variation
        'type' => 'requires', 'gate' => true,
        'reason' => 'Variation arises from imperfect DNA copying, which the reproduction chapter establishes and this one takes as given.',
        'source' => 'C10 Heredity ← C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 206, 'prerequisite' => 205,     // Accumulation of Variation ← Variation during Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Accumulation is variation building up across generations, so single-generation variation is the unit being accumulated.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 207, 'prerequisite' => 197,     // Heredity Definition ← Sexual Reproduction
        'type' => 'requires', 'gate' => true,
        'reason' => 'Heredity is the passing of traits from parents to offspring through gametes, so sexual reproduction is the vehicle the definition names.',
        'source' => 'C10 Heredity ← C10 How do Organisms Reproduce',
    ],
    [
        'concept' => 208, 'prerequisite' => 207,     // Inherited Traits ← Heredity Definition
        'type' => 'requires', 'gate' => true,
        'reason' => 'An inherited trait is one transmitted by heredity, so the process defines the category of trait.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 209, 'prerequisite' => 208,     // Mendel's Contributions ← Inherited Traits
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mendel\'s experiments track particular traits across generations, so the idea of an inherited trait is what he is working with.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 210, 'prerequisite' => 209,     // Dominant Traits ← Mendel's Contributions
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dominance is the conclusion Mendel draws from the first generation of his crosses, so the experiments come first.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 211, 'prerequisite' => 210,     // Recessive Traits ← Dominant Traits
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recessive is defined as the trait that dominance hides, so it has no meaning without its counterpart.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 212, 'prerequisite' => 210,     // Monohybrid Cross ← Dominant Traits
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 3:1 ratio is interpreted through dominance, so the concept is needed to read the result rather than just count it.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 212, 'prerequisite' => 211,     // Monohybrid Cross ← Recessive Traits
        'type' => 'requires', 'gate' => true,
        'reason' => 'The one-quarter that reappears in the second generation is the recessive trait, so both terms are needed for the cross to be explicable.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 213, 'prerequisite' => 212,     // Genes as Factors ← Monohybrid Cross
        'type' => 'requires', 'gate' => true,
        'reason' => 'The factor is inferred as the thing that must be passed on to produce the observed ratio, so the ratio is the evidence for it.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 214, 'prerequisite' => 212,     // Law of Segregation ← Monohybrid Cross
        'type' => 'requires', 'gate' => true,
        'reason' => 'Segregation is the rule that explains the monohybrid ratio, so the ratio is the observation the law accounts for.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 215, 'prerequisite' => 212,     // Dihybrid Cross ← Monohybrid Cross
        'type' => 'requires', 'gate' => true,
        'reason' => 'The dihybrid cross tracks two traits at once and is set up by analogy with the single-trait case.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 216, 'prerequisite' => 215,     // Independent Assortment ← Dihybrid Cross
        'type' => 'requires', 'gate' => true,
        'reason' => 'The law explains the 9:3:3:1 result of the dihybrid cross, so the cross is the observation it is drawn from.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 218, 'prerequisite' => 7330,    // Chromosomes and Genes ← Chromatin, chromosomes and genes (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 places genes on chromosomes inside the nucleus; Class 10 connects them to Mendel\'s factors and assumes the location is already known.',
        'source' => 'C10 Heredity ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 218, 'prerequisite' => 213,     // Chromosomes and Genes ← Genes as Factors
        'type' => 'requires', 'gate' => true,
        'reason' => 'The chapter\'s pivot is identifying Mendel\'s abstract factor with a physical gene on a chromosome, so the factor must exist to be identified.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 217, 'prerequisite' => 213,     // Gene Expression Mechanism ← Genes as Factors
        'type' => 'requires', 'gate' => true,
        'reason' => 'Expression explains how a factor becomes a visible trait, so the factor is what is being expressed.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 219, 'prerequisite' => 218,     // Germ Cells and Inheritance ← Chromosomes and Genes
        'type' => 'requires', 'gate' => true,
        'reason' => 'Germ cells carry one chromosome of each pair, so the chromosome-gene link is what makes the carriage meaningful.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 219, 'prerequisite' => 7347,    // Germ Cells and Inheritance ← Result of meiosis (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gametes carry half the chromosome number because meiosis halved it. The Class 9 outcome is the mechanism this concept depends on.',
        'source' => 'C10 Heredity ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 220, 'prerequisite' => 219,     // Sex Determination ← Germ Cells and Inheritance
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sex is determined by which sex chromosome the gamete carries, so gamete inheritance is the mechanism.',
        'source' => 'C10 Heredity',
    ],
    [
        'concept' => 221, 'prerequisite' => 220,     // Role of Paternal Chromosome ← Sex Determination
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the father\'s gamete decides the sex is the conclusion of the determination mechanism, so it cannot precede it.',
        'source' => 'C10 Heredity',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Work, Energy and Simple Machines (8606)
    // Almost entirely downstream: every definition is stated in terms of
    // force and motion from the two chapters before it.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7139, 'prerequisite' => 7403,   // Work ← Newton's second law (8605)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Work is force times displacement, so force must be a quantity the learner can state and compute before the product means anything.',
        'source' => 'C9 Work, Energy and Simple Machines ← C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7139, 'prerequisite' => 7275,   // Work ← Displacement definition (8600)
        'type' => 'requires', 'gate' => true,
        'reason' => 'It is displacement and not distance that appears in the definition, and using the wrong one is the commonest error in the chapter.',
        'source' => 'C9 Work, Energy and Simple Machines ← C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7141, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'No displacement means the product is zero. The result follows directly from the definition and cannot precede it.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7142, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A perpendicular force contributes nothing along the displacement, which is a reading of the definition rather than a separate fact.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7143, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Positive work is the case where force and displacement point the same way, so both quantities must be in the definition first.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7144, 'prerequisite' => 7143,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Negative work is defined by contrast with the positive case, so it is the second half of a pair.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7146, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The joule is a newton-metre, which is the defining product read as a unit.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7147, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The area under a force-displacement graph is the work, so the quantity has to be defined before a graph can represent it.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7148, 'prerequisite' => 7139,   // Energy as capacity to do work ← Work
        'type' => 'requires', 'gate' => true,
        'reason' => 'Energy is defined as the capacity to do work. Work is literally the term the definition is built on, so it cannot come second.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7149, 'prerequisite' => 7148,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The work-energy theorem relates work done to the change in energy, so both quantities have to exist before they can be related.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7150, 'prerequisite' => 7149,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Extending the theorem to systems and varying forces presupposes the simple statement it generalises.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7152, 'prerequisite' => 7148,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Listing forms of energy assumes energy itself has been defined as one quantity that takes different forms.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7153, 'prerequisite' => 7152,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Conversion is movement between forms, so the forms have to be enumerated before one can become another.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7154, 'prerequisite' => 7153,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The everyday examples are instances of the conversion principle and illustrate it rather than establish it.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7155, 'prerequisite' => 7152,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mechanical energy is picked out as one of the forms just listed, so the list is what it is selected from.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7156, 'prerequisite' => 7155,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Kinetic energy is one of the two components of mechanical energy, so the category has to be introduced before it is split.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7158, 'prerequisite' => 7156,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The half-m-v-squared expression quantifies the kinetic energy just described qualitatively as energy of motion.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7159, 'prerequisite' => 7302,   // Deriving KE ← Third kinematic equation (8600)
        'type' => 'requires', 'gate' => true,
        'reason' => 'NCERT derives the kinetic energy expression by substituting v squared equals u squared plus 2as into the work formula. Without that equation the derivation has no starting point.',
        'source' => 'C9 Work, Energy and Simple Machines ← C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7159, 'prerequisite' => 7158,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The derivation arrives at the formula, so the target expression is what the working is aiming to justify.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7160, 'prerequisite' => 7158,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That doubling the speed quadruples the energy is read off the square in the formula, so the formula must be in hand.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7161, 'prerequisite' => 7149,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That positive work raises and negative work lowers kinetic energy is the work-energy theorem applied with signs.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7161, 'prerequisite' => 7158,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The quantity being changed is the kinetic energy, so its formula has to be available for the change to be computed.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7164, 'prerequisite' => 7155,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Energy stored by position is the second component of mechanical energy and belongs to that category.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7165, 'prerequisite' => 7164,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal definition of potential energy generalises the stored-by-position idea introduced just before it.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7166, 'prerequisite' => 7405,   // Near-surface limit of mgh ← Weight = mg (8605)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mgh expression is work done against weight, so weight as mass times g is the force being worked against.',
        'source' => 'C9 Work, Energy and Simple Machines ← C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7166, 'prerequisite' => 7165,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The near-surface caveat limits the potential energy formula, so the formula must exist before its range of validity is stated.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7167, 'prerequisite' => 7158,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Conservation is stated as kinetic plus potential staying constant, so the kinetic term is half of the statement.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7167, 'prerequisite' => 7165,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other half of the same sum; without potential energy there is nothing for kinetic energy to trade against.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7167, 'prerequisite' => 7290,   // Free-fall conservation ← Gravitational acceleration (8600)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The worked case is a body in free fall, whose acceleration is the g established in the motion chapter.',
        'source' => 'C9 Work, Energy and Simple Machines ← C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7168, 'prerequisite' => 7167,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The condition says when conservation holds, so the conserved quantity has to be established before its limits are drawn.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7169, 'prerequisite' => 7168,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Friction is presented as the reason the conservation condition fails in practice, so the condition frames the exception.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7169, 'prerequisite' => 7396,   // Friction dissipates ← Friction as opposing force (8605)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The energy is lost because friction opposes the motion over a distance, which is the opposing-force idea doing negative work.',
        'source' => 'C9 Work, Energy and Simple Machines ← C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7170, 'prerequisite' => 7169,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Total energy conservation is the repair to mechanical conservation once dissipation is accounted for, so the loss comes first.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7171, 'prerequisite' => 7167,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The slide problem is solved by equating potential energy at the top to kinetic energy at the bottom, which is the conservation statement applied.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7172, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Power is the rate of doing work, so work is the quantity whose rate is being taken.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7173, 'prerequisite' => 7172,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formula computes the rate just defined, so it follows the definition.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7174, 'prerequisite' => 7173,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Rearranging for work or time is the same relation solved differently, so the forward formula comes first.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7175, 'prerequisite' => 7172,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The watt is one joule per second, read straight off the definition of power.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7176, 'prerequisite' => 7139,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'A machine is introduced as something that changes how work is applied rather than how much is done, so work frames the whole section.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7177, 'prerequisite' => 7176,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Effort and load are the two forces a machine mediates between, and are defined inside the machine idea.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7178, 'prerequisite' => 7177,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mechanical advantage is the ratio of load to effort, so both terms must be named before the ratio can be formed.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7179, 'prerequisite' => 7178,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The fixed pulley is analysed by its mechanical advantage of one, so the quantity is what the analysis reports.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7180, 'prerequisite' => 7179,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The movable pulley is contrasted with the fixed one to show where the advantage comes from, so the simpler case comes first.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7181, 'prerequisite' => 7178,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The incline is another device judged by the same mechanical advantage measure.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7182, 'prerequisite' => 7177,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A lever is described by where its effort, load and fulcrum sit, so effort and load must already be named.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 7183, 'prerequisite' => 7182,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The classes are distinguished by the order of the three parts along the lever, so the parts have to be identified first.',
        'source' => 'C9 Work, Energy and Simple Machines',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Light — Reflection and Refraction (1020)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 223, 'prerequisite' => 222,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The laws state the angle relationship for the bouncing just described, so the phenomenon must be named before it is governed.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 224, 'prerequisite' => 223,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Every ray drawn at a curved mirror obeys the same two laws, so the laws are what make the diagrams predictable.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 225, 'prerequisite' => 224,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pole, centre of curvature and focus are points on the mirror, so the mirror has to exist before they can be located on it.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 228, 'prerequisite' => 225,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sign convention measures distances from the pole along the principal axis, so those reference features must already be defined.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 226, 'prerequisite' => 228,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mirror formula gives wrong answers unless distances carry the right signs, so the convention is part of using it at all.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 227, 'prerequisite' => 226,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Magnification is computed from the image and object distances the mirror formula supplies.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 238, 'prerequisite' => 225,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ray diagrams are drawn through the focus and centre of curvature, so those points have to be placed before any image can be constructed.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 239, 'prerequisite' => 238,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The convex case uses the same construction rules with the focus behind the mirror, and is taught by contrast with the concave one.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 229, 'prerequisite' => 222,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Refraction is introduced as what light does when it is not simply bounced back, so reflection is the contrast it is set against.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 230, 'prerequisite' => 229,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Snell\'s law quantifies the bending just described, so the bending has to be observed before it is given a law.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 231, 'prerequisite' => 230,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The refractive index is the constant ratio Snell\'s law asserts, so the law is where the quantity comes from.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 232, 'prerequisite' => 231,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Optical density is defined by comparing refractive indices, so the index is the quantity being compared.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 233, 'prerequisite' => 229,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A lens works by refracting light at its two curved surfaces, so refraction is the mechanism the device relies on.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 234, 'prerequisite' => 233,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Optical centre and focal length are features of the lens, so the lens must be introduced before they are located.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 235, 'prerequisite' => 234,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The lens formula relates object and image distance to focal length, which is one of the terms just defined.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 235, 'prerequisite' => 228,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The same sign convention governs lens distances, and applying the formula without it produces confidently wrong answers.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 236, 'prerequisite' => 235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Lens magnification is computed from the distances the lens formula produces.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 237, 'prerequisite' => 235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Power is the reciprocal of focal length in metres, so the focal length has to be obtainable before power can be stated.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 240, 'prerequisite' => 234,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Convex-lens ray diagrams are drawn through the optical centre and focus, so both must be placed before construction.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 241, 'prerequisite' => 240,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The concave lens uses the same rules with a virtual focus and is introduced by contrast with the convex case.',
        'source' => 'C10 Light — Reflection and Refraction',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · The Human Eye and the Colourful World (1021)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 242, 'prerequisite' => 233,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The eye is explained as a converging lens system, so lenses must be understood before the organ can be described as one.',
        'source' => 'C10 The Human Eye ← C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 243, 'prerequisite' => 242,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Accommodation is the ciliary muscle changing the lens curvature, so the structures have to be named before their action.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 244, 'prerequisite' => 243,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Near and far points are the limits of accommodation, so the process defines where its limits lie.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 245, 'prerequisite' => 244,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Myopia is described as the far point moving closer than infinity, which is a statement about the limits just defined.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 245, 'prerequisite' => 237,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Correcting myopia means computing the power of the concave lens needed. Every correction question is a lens-power question.',
        'source' => 'C10 The Human Eye ← C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 246, 'prerequisite' => 244,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Hypermetropia is the near point receding, again a statement about the accommodation limits.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 246, 'prerequisite' => 237,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The correction is a convex lens of a computed power, so lens power is the tool the remedy is expressed in.',
        'source' => 'C10 The Human Eye ← C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 247, 'prerequisite' => 243,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Presbyopia is the weakening of accommodation with age, so the faculty must be understood before its decline.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 248, 'prerequisite' => 230,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Light bends twice inside a prism, each time according to Snell\'s law, so the law governs the whole path.',
        'source' => 'C10 The Human Eye ← C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 249, 'prerequisite' => 248,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The deviation is the total turning produced by the two refractions, so the prism path has to be traced first.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 250, 'prerequisite' => 231,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dispersion happens because the refractive index differs with colour, so red bends least and violet most. Without the index there is no quantity that can differ.',
        'source' => 'C10 The Human Eye ← C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 250, 'prerequisite' => 249,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The spread of colours is a spread of deviations, so deviation is the quantity that varies across the spectrum.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 251, 'prerequisite' => 250,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The spectrum is the band dispersion produces, so the process must be described before its result is named.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 252, 'prerequisite' => 250,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A rainbow is dispersion by water droplets, so the phenomenon is an application of the process.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 253, 'prerequisite' => 229,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Atmospheric effects are refraction through air layers of changing density, so refraction is the mechanism throughout.',
        'source' => 'C10 The Human Eye ← C10 Light — Reflection and Refraction',
    ],
    [
        'concept' => 254, 'prerequisite' => 253,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Twinkling is explained by the starlight refracting through a restless atmosphere, so the general effect precedes the example.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 255, 'prerequisite' => 254,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Planets not twinkling is explained by contrast with stars that do, so the twinkling case has to come first.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 256, 'prerequisite' => 253,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Seeing the Sun before it rises is another consequence of atmospheric refraction, and rests on the same mechanism.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 259, 'prerequisite' => 257,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The blue sky is explained by short wavelengths scattering most, so scattering is the process the explanation uses.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 260, 'prerequisite' => 257,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Red is chosen for danger signals because it scatters least and travels furthest, which is the same scattering rule applied.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 257, 'prerequisite' => 251,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Scattering is described as depending on wavelength, so the learner needs the spectrum to have separate colours with different wavelengths.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],
    [
        'concept' => 258, 'prerequisite' => 7392,    // Tyndall Effect ← Tyndall effect definition (C9)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Class 9 introduces the Tyndall effect as evidence for colloids; Class 10 re-uses it as an instance of scattering and does not redefine it.',
        'source' => 'C10 The Human Eye ← C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 258, 'prerequisite' => 257,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The visible beam is scattered light, so scattering is what makes the effect observable.',
        'source' => 'C10 The Human Eye and the Colourful World',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Electricity (1022)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 261, 'prerequisite' => 7416,    // Electric Current ← Discovery of electrons (C9)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Current is a flow of charge, and in a metal the carriers are electrons. That identification is borrowed from the atom chapter and never justified here.',
        'source' => 'C10 Electricity ← C9 Journey Inside the Atom',
    ],
    [
        'concept' => 262, 'prerequisite' => 261,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A circuit is defined as a closed path for the current, so current is the thing the path carries.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 263, 'prerequisite' => 261,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Potential difference is the work done per unit charge moved, so charge in motion has to be established first.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 264, 'prerequisite' => 263,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ohm\'s law states that current is proportional to potential difference, so one of the two related quantities is supplied here.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 265, 'prerequisite' => 264,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Resistance is defined as the constant of proportionality in Ohm\'s law, so the law is where the quantity comes from.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 266, 'prerequisite' => 265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Resistivity is resistance normalised by length and area, so resistance must be defined before it is normalised.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 267, 'prerequisite' => 265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The factors are things that change the resistance, so the quantity has to exist before its dependencies are listed.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 268, 'prerequisite' => 264,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The series result is derived by applying V = IR to each resistor and adding the potential differences, so the law is the derivation.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 269, 'prerequisite' => 268,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Parallel is taught by contrast with series — currents add instead of voltages — and learners apply the wrong rule far more often than neither.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 270, 'prerequisite' => 265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Joule heating goes as I squared R, so resistance is a term in the expression being taught.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 271, 'prerequisite' => 270,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Power is the heating rate, obtained directly from Joule\'s law by dividing by time.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 272, 'prerequisite' => 263,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The voltmeter measures potential difference and the ammeter current, so both quantities must be known for the instruments to be meaningful.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 273, 'prerequisite' => 262,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The symbols stand for components in a circuit diagram, so the circuit has to be introduced before it is drawn in shorthand.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 274, 'prerequisite' => 270,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A fuse works by melting from the heat an excessive current produces, which is Joule heating applied as a safety device.',
        'source' => 'C10 Electricity',
    ],
    [
        'concept' => 275, 'prerequisite' => 271,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The kilowatt-hour is a power multiplied by a time, so electric power has to be defined before the commercial unit built from it.',
        'source' => 'C10 Electricity',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Magnetic Effects of Electric Current (1023)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 276, 'prerequisite' => 261,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The whole chapter is about what a CURRENT does magnetically, so current is its subject and cannot follow it.',
        'source' => 'C10 Magnetic Effects ← C10 Electricity',
    ],
    [
        'concept' => 277, 'prerequisite' => 276,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The field is introduced as the region in which the magnetic effect is felt, so the effect comes first.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 278, 'prerequisite' => 277,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Field lines are a way of drawing the field, so the field must exist before it is represented.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 279, 'prerequisite' => 278,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That lines never cross and run north to south are rules about the representation just introduced.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 280, 'prerequisite' => 278,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The concentric circles around a wire are field lines, so the representation has to be available to describe the pattern.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 281, 'prerequisite' => 280,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The thumb rule gives the direction of the circles around a straight conductor, so that pattern is what the rule orients.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 282, 'prerequisite' => 281,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The loop field is built by applying the thumb rule to each element of the loop, so the rule is the tool used.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 283, 'prerequisite' => 282,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A solenoid is a stack of circular loops, so the single-loop field is what is being added up.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 284, 'prerequisite' => 283,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An electromagnet is a solenoid with a soft-iron core, so the solenoid is the device being modified.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 285, 'prerequisite' => 277,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The force arises on a conductor placed in a magnetic field, so the field is one of the two ingredients.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 286, 'prerequisite' => 285,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fleming\'s rule gives the direction of the force just established, so the force must exist before it is oriented.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 287, 'prerequisite' => 269,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Household wiring is explained as a parallel circuit so each appliance gets the full voltage, which only makes sense with the parallel rule in hand.',
        'source' => 'C10 Magnetic Effects ← C10 Electricity',
    ],
    [
        'concept' => 288, 'prerequisite' => 287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fuse is a component of the domestic circuit, so the circuit has to be described before its protection is placed in it.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 289, 'prerequisite' => 287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Overloading is too many appliances drawing current in the same domestic circuit, so that circuit is the context.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 290, 'prerequisite' => 287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A short circuit is live and neutral touching within the household wiring, which requires knowing what those wires are.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],
    [
        'concept' => 291, 'prerequisite' => 287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The earth wire is the third conductor in the domestic supply, so the supply arrangement has to be known first.',
        'source' => 'C10 Magnetic Effects of Electric Current',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Force and Pressure (25844)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31138, 'prerequisite' => 31137,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The definition generalises the everyday pushes and pulls the chapter opens with, so the examples are what the definition abstracts from.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31139, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a force needs two objects is a condition on the definition, so the definition has to be stated before it is qualified.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31141, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding forces presupposes that a force is a quantity with a size, which the definition supplies.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31142, 'prerequisite' => 31141,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Subtraction is introduced by contrast with the same-direction case, so addition comes first.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31143, 'prerequisite' => 31142,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Net force is the single force left after combining, so both combining rules have to be available.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31144, 'prerequisite' => 31143,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Tug of war is the worked example of a net force deciding the outcome, so the quantity precedes the illustration.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31145, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Changing speed is described as what a force does, so the force has to be defined before its effects are listed.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31146, 'prerequisite' => 31145,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Changing direction is the second effect in the same list and is introduced alongside the first.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31147, 'prerequisite' => 31146,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Deforming an object is the third effect, and is notable because it happens where motion cannot change.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31148, 'prerequisite' => 31145,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That nothing changes without a force is the converse of the effects just listed, and is the seed of the first law of motion.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31150, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Friction is named as one kind of force, so it belongs to the category introduced first.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31151, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Magnetic force is another member of the same list of force types.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31152, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Gravitational force completes the survey of force types the chapter enumerates.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31153, 'prerequisite' => 31138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pressure is force divided by area, so force is a term in its definition and must come first.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31154, 'prerequisite' => 31153,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fluid pressure extends the quantity to liquids and gases, so the quantity has to exist before it is extended.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31155, 'prerequisite' => 31154,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Atmospheric pressure is the gas case of fluid pressure, so the general fluid result frames it.',
        'source' => 'C8 Force and Pressure',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Friction (25845)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31157, 'prerequisite' => 31156,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That friction opposes the push is the conclusion drawn from the book stopping whichever way it is pushed.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31157, 'prerequisite' => 31150,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Friction was named as a force type in the previous chapter; this chapter takes that and says which way it acts.',
        'source' => 'C8 Friction ← C8 Force and Pressure',
    ],
    [
        'concept' => 31158, 'prerequisite' => 31157,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming the two surfaces friction acts between presupposes that friction has been identified as a force.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31159, 'prerequisite' => 31158,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Interlocking irregularities is the explanation of what happens at the contact, so the contact has to be located first.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31160, 'prerequisite' => 31159,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rougher surfaces having more friction follows from the interlocking picture, which is what explains it.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31161, 'prerequisite' => 31159,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pressing harder pushes the irregularities together, so the mechanism is what the dependence is read from.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31162, 'prerequisite' => 31157,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Static and sliding are two states of the same opposing force, distinguished by whether motion has begun.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31163, 'prerequisite' => 31157,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Walking and gripping are presented as things friction makes possible, so friction has to be established as the cause.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31164, 'prerequisite' => 31159,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Wear is the irregularities grinding each other down, which is the same contact mechanism seen over time.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31165, 'prerequisite' => 31157,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Heat is produced because the opposing force does work against the motion, so friction must first be the opposing force.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31166, 'prerequisite' => 31160,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Treads and grooves increase grip by increasing roughness, which is the dependence established just before.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31167, 'prerequisite' => 31163,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Brakes are the deliberate use of friction, so the useful-friction idea is what the device applies.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31168, 'prerequisite' => 31159,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A lubricant works by filling the irregularities so they no longer interlock, which only explains anything once the interlocking picture is held.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31169, 'prerequisite' => 31162,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rolling friction is introduced as a third kind beside static and sliding, so those two are the reference.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31170, 'prerequisite' => 31169,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The comparison is between rolling and sliding friction, so rolling has to be defined for it to be the smaller one.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31171, 'prerequisite' => 31170,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ball bearings exist to convert sliding into rolling, so the advantage of rolling is their entire justification.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31172, 'prerequisite' => 31157,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drag is friction from a fluid, so the opposing-force idea is extended rather than newly introduced.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31173, 'prerequisite' => 31172,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Listing what drag depends on presupposes that drag has been named as a quantity.',
        'source' => 'C8 Friction',
    ],
    [
        'concept' => 31174, 'prerequisite' => 31173,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Streamlining is chosen because shape is one of the things drag depends on, so the dependence justifies the design.',
        'source' => 'C8 Friction',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Sound (25846)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31176, 'prerequisite' => 31175,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vibration is named as the cause after the observation that stopping it stops the sound, so the observation motivates the term.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31177, 'prerequisite' => 31176,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That the whole instrument vibrates is an extension of the vibration idea to a larger body.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31178, 'prerequisite' => 31176,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The voice box is presented as the human vibrating structure, so vibration is what it is an instance of.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31179, 'prerequisite' => 31178,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cords and the slit are parts of the voice box, so the organ has to be introduced before its anatomy.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31180, 'prerequisite' => 31179,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Air from the lungs is what sets the cords vibrating, so the cords must be in place for the airflow to act on them.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31182, 'prerequisite' => 31181,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sound fading as air is pumped out is the result of the tumbler experiment just set up.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31183, 'prerequisite' => 31182,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Having shown a medium is needed, the chapter then shows other media also work, so the necessity comes first.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31184, 'prerequisite' => 31176,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The eardrum is described as vibrating in response to arriving sound, so vibration is the mechanism it uses.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31186, 'prerequisite' => 31184,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The path to the brain begins at the eardrum, so the starting structure has to be established.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31187, 'prerequisite' => 31176,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Frequency counts vibrations per second, so the vibration is the event being counted.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31188, 'prerequisite' => 31187,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Distinguishing sounds by pitch and loudness needs frequency to be a defined quantity rather than a word.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31189, 'prerequisite' => 31187,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The audible range is stated as a span of frequencies, so frequency has to be measurable for the range to have limits.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31190, 'prerequisite' => 31189,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ultrasound and infrasound are defined as lying outside the human range, so the range is the reference.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31191, 'prerequisite' => 31188,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Noise pollution is defined by excessive loudness, which is one of the qualities the previous concept establishes.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31193, 'prerequisite' => 31191,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The harms are consequences of the pollution just defined, so the definition frames them.',
        'source' => 'C8 Sound',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Light (25849)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31233, 'prerequisite' => 31232,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A dark room hides things because no light reaches the eye, which is the condition stated immediately before.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31234, 'prerequisite' => 31232,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Objects are seen by light they reflect into the eye, so the eye-reaching condition is half the explanation.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31235, 'prerequisite' => 31234,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The law of reflection governs the bouncing just introduced, so the phenomenon precedes the rule.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31236, 'prerequisite' => 31235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The coplanarity statement is the second law, completing the pair begun with the angle equality.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31237, 'prerequisite' => 31235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Lateral inversion is worked out by applying the reflection law to each point of the object.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31238, 'prerequisite' => 31235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Regular reflection is the case where parallel rays stay parallel, which is read from the law applied to a smooth surface.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31239, 'prerequisite' => 31238,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Diffused reflection is defined by contrast with the regular case, so that case has to be described first.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31240, 'prerequisite' => 31239,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The point is that the laws hold even on a rough surface, which only matters once diffusion has been shown.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31241, 'prerequisite' => 31235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiple reflection is the law applied a second time to the already-reflected ray.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31242, 'prerequisite' => 31241,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Many images arise from light bouncing repeatedly between two mirrors, so repeated reflection is the mechanism.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31243, 'prerequisite' => 31242,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The kaleidoscope is the device built from the multiple-image effect just explained.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31245, 'prerequisite' => 31244,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dispersion is the splitting of white light into the seven colours it was just shown to contain.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31247, 'prerequisite' => 31246,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Retina, rods and cones are parts of the eye, so the organ has to be laid out before its detail.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 31249, 'prerequisite' => 31248,
        'type' => 'requires', 'gate' => true,
        'reason' => 'How the code works describes the Braille system, so the system has to be introduced before its rules.',
        'source' => 'C8 Light',
    ],

    // ══════════════════════════════════════════════════════════════════
    // CLASS 8 INTO CLASS 9 AND 10
    // The vertical joins. Note that 8 and 10 are on the older NCERT
    // editions while 9 is on the newer one, so these were checked against
    // both rather than assumed.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7396, 'prerequisite' => 31157,  // Friction as opposing force (C9) ← Friction opposes the applied force (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 opens by taking friction as a known opposing force and reasoning from it to the first law. The opposing character is established in Class 8 and not re-derived.',
        'source' => 'C9 How Forces Affect Motion ← C8 Friction',
    ],
    [
        'concept' => 7402, 'prerequisite' => 31148,  // First law and inertia (C9) ← Nothing changes without a force (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The first law is the Class 8 statement that nothing changes without a force, made formal. A learner without it meets the law as a bare assertion.',
        'source' => 'C9 How Forces Affect Motion ← C8 Force and Pressure',
    ],
    [
        'concept' => 7408, 'prerequisite' => 31143,  // Net force from opposing forces (C9) ← Net force (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Combining forces into one net force is taught in Class 8 and used in Class 9 as the routine first step of every problem.',
        'source' => 'C9 How Forces Affect Motion ← C8 Force and Pressure',
    ],
    [
        'concept' => 222, 'prerequisite' => 31234,   // Reflection of Light (C10) ← Reflection makes things visible (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 10 begins with reflection already familiar and moves straight to curved mirrors, so the Class 8 account is the assumed starting point.',
        'source' => 'C10 Light — Reflection and Refraction ← C8 Light',
    ],
    [
        'concept' => 223, 'prerequisite' => 31235,   // Laws of Reflection (C10) ← Angle equality (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The same two laws, stated in Class 8 and re-used in Class 10 as the basis for every ray diagram without being re-justified.',
        'source' => 'C10 Light — Reflection and Refraction ← C8 Light',
    ],
    [
        'concept' => 250, 'prerequisite' => 31245,   // Dispersion of Light (C10) ← Dispersion (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 shows white light splitting; Class 10 explains it by the refractive index varying with colour. The phenomenon is assumed known.',
        'source' => 'C10 The Human Eye ← C8 Light',
    ],
    [
        'concept' => 242, 'prerequisite' => 31246,   // Human Eye Structure (C10) ← The parts of the eye (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The anatomy is taught in Class 8 and Class 10 proceeds directly to accommodation and defects, restating none of it.',
        'source' => 'C10 The Human Eye ← C8 Light',
    ],
    [
        'concept' => 31153, 'prerequisite' => 31141, // Pressure (C8) ← Forces in the same direction add
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The force in the pressure ratio is the total force on the area, so combining forces is assumed before it is divided by anything.',
        'source' => 'C8 Force and Pressure',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Sound Waves: Characteristics and Applications (8615)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7548, 'prerequisite' => 31176,  // Sound produced by vibrations ← Vibration (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 establishes that sound comes from vibration; Class 9 takes that and builds the wave picture on it without re-demonstrating the cause.',
        'source' => 'C9 Sound Waves ← C8 Sound',
    ],
    [
        'concept' => 7549, 'prerequisite' => 7548,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal definition of a vibration follows the observation that vibrating things make sound, which is what it is a definition of.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7550, 'prerequisite' => 7549,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Identifying a source as vibrating requires the vibration to be a defined motion rather than a loose word.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7551, 'prerequisite' => 7550,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Instruments are worked examples of vibrating sources, so the general claim precedes the cases.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7552, 'prerequisite' => 7550,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The voice is presented as another vibrating source, classified by the same criterion.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7554, 'prerequisite' => 7550,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The tuning fork is the laboratory example of a vibrating source, used because its vibration is visible.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7556, 'prerequisite' => 31182,  // Sound needs a medium ← Fainter as air is removed (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 shows sound fading as air is pumped out; Class 9 states the requirement as a principle and cites the same demonstration.',
        'source' => 'C9 Sound Waves ← C8 Sound',
    ],
    [
        'concept' => 7556, 'prerequisite' => 7555,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a medium is needed is the conclusion drawn from sound travelling through solids, liquids and gases alike.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7557, 'prerequisite' => 7556,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The medium is defined once the need for one has been established, so the requirement motivates the term.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7558, 'prerequisite' => 7556,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A vacuum carries no sound precisely because it is the absence of a medium, so the requirement is what makes the vacuum case follow.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7559, 'prerequisite' => 7558,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The bell jar is the experiment that evidences the vacuum claim, so the claim frames what the experiment shows.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7560, 'prerequisite' => 7558,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Silence in space is the vacuum result applied outside the laboratory.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7561, 'prerequisite' => 7560,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Radios are needed because space is silent, so the silence is the reason for the equipment.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7562, 'prerequisite' => 7497,   // Compressions and rarefactions ← Particulate nature of matter (C9 8614)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Compression and rarefaction are regions where particles crowd and spread. Without matter being made of particles there is nothing to crowd.',
        'source' => 'C9 Sound Waves ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7562, 'prerequisite' => 7549,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The wave is produced by the source vibrating back and forth, so the vibration is what generates the alternating regions.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7563, 'prerequisite' => 7562,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Particles oscillating about fixed positions is the detail of the compression picture just introduced.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7565, 'prerequisite' => 7563,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Longitudinal means the particle motion is along the direction of travel, which needs the particle motion to be described first.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7566, 'prerequisite' => 7563,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That energy moves while particles stay put is the key consequence of particles only oscillating about a mean position.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7567, 'prerequisite' => 7562,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The crest and trough graph is a way of drawing the compressions and rarefactions, so the physical picture comes before the plot.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7568, 'prerequisite' => 7567,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Wavelength is measured between successive crests on that graph, so the graph has to be readable.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7569, 'prerequisite' => 31187,  // Frequency definition ← Frequency in hertz (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Frequency is counted in hertz in Class 8 and re-used in Class 9 as a wave property without being redefined.',
        'source' => 'C9 Sound Waves ← C8 Sound',
    ],
    [
        'concept' => 7570, 'prerequisite' => 7569,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The time period is the time for one oscillation, defined against the frequency that counts them.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7571, 'prerequisite' => 7570,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The reciprocal relation connects the two quantities, so both must be separately defined before it is stated.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7572, 'prerequisite' => 7567,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Amplitude is the height of the crest on the wave graph, so the graph supplies the measurement.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7573, 'prerequisite' => 7572,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The energy claim is about amplitude, so the quantity has to be defined before energy can depend on it.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7574, 'prerequisite' => 7573,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Intensity is energy per unit area per unit time, so the amplitude-energy link is what it quantifies.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7575, 'prerequisite' => 7574,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fall with distance is a statement about intensity, so intensity must exist to decrease.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7576, 'prerequisite' => 7568,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The wave equation multiplies wavelength by frequency, so wavelength is one of its two terms.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7576, 'prerequisite' => 7569,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Frequency is the other term in the same product and is equally indispensable.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7577, 'prerequisite' => 7557,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Speed varying with the medium is a claim about media, so the medium has to be a defined thing for the claim to have a subject.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7578, 'prerequisite' => 7569,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pitch is how the ear perceives frequency, so the physical quantity is what the perception tracks.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7579, 'prerequisite' => 7572,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Loudness is the perception of amplitude, so amplitude has to be defined before perception is mapped onto it.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7580, 'prerequisite' => 7564,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reflection is what happens when the spreading wave meets a surface, so its propagation must be established.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7581, 'prerequisite' => 31235,  // Sound obeys laws of reflection ← Angle equality (C8 Light)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The angle law for sound is stated as the same rule already met for light, so the optics version is the pattern being reused.',
        'source' => 'C9 Sound Waves ← C8 Light',
    ],
    [
        'concept' => 7581, 'prerequisite' => 7580,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The laws govern the reflection just introduced, so the phenomenon precedes the rule.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7582, 'prerequisite' => 7580,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An echo is reflected sound heard separately, so reflection is what it is a case of.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7583, 'prerequisite' => 7582,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The tenth-of-a-second rule says when a reflection counts as an echo, so the echo must be defined for the condition to qualify it.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7584, 'prerequisite' => 7583,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 17-metre figure is computed from the 0.1-second gap and the speed of sound, so the time condition is one input.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7584, 'prerequisite' => 7576,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other input is the wave speed, so the speed relation has to be available for the distance to be derived rather than memorised.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7586, 'prerequisite' => 7580,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reverberation is repeated reflection, so a single reflection is the unit being repeated.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7587, 'prerequisite' => 7586,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Acoustic treatment is designed to reduce reverberation, so the problem must be understood before the remedy.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7588, 'prerequisite' => 31189,  // Human audible range ← The human range (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The 20 Hz to 20 kHz range is given in Class 8 and re-used in Class 9 as the reference for defining infrasound and ultrasound.',
        'source' => 'C9 Sound Waves ← C8 Sound',
    ],
    [
        'concept' => 7589, 'prerequisite' => 7588,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Infrasonic and ultrasonic are defined as below and above the audible range, so the range is the boundary.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7590, 'prerequisite' => 7589,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Animal hearing is described by which of those bands it extends into, so the bands have to be named first.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7591, 'prerequisite' => 7589,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Earthquake precursors are infrasonic, so the band has to exist for the application to be placed in it.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7592, 'prerequisite' => 7589,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Medical imaging uses ultrasound, which is one of the two out-of-range bands just defined.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7593, 'prerequisite' => 7589,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Flaw detection is a second ultrasonic application, classified by the same frequency band.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7594, 'prerequisite' => 7582,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Echolocation is an animal using echoes to judge distance, so the echo is the mechanism it exploits.',
        'source' => 'C9 Sound Waves',
    ],
    [
        'concept' => 7595, 'prerequisite' => 7584,
        'type' => 'requires', 'gate' => true,
        'reason' => 'SONAR computes range from the echo delay and the speed of sound, which is exactly the calculation behind the minimum echo distance.',
        'source' => 'C9 Sound Waves',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Our Environment (1024)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 293, 'prerequisite' => 292,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Producers are named as one component of the ecosystem, so the components have to be enumerated first.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 293, 'prerequisite' => 153,     // Producers ← Photosynthesis Process (1016)
        'type' => 'requires', 'gate' => true,
        'reason' => 'A producer is an organism that makes its own food by photosynthesis. Without the process there is nothing that distinguishes a producer from anything else.',
        'source' => 'C10 Our Environment ← C10 Life Processes',
    ],
    [
        'concept' => 294, 'prerequisite' => 293,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Consumers are defined by eating producers or other consumers, so the producer level has to exist for them to consume.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 295, 'prerequisite' => 292,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Decomposers complete the set of ecosystem components introduced at the start of the chapter.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 296, 'prerequisite' => 294,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A food chain is a sequence of who eats whom, so producers and consumers must both be identifiable for the sequence to be built.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 297, 'prerequisite' => 296,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A trophic level is a position in the food chain, so the chain has to exist before positions on it can be numbered.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 298, 'prerequisite' => 297,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Energy flow is described as passing from one trophic level to the next, so the levels are what it flows between.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 298, 'prerequisite' => 7153,    // Energy Flow ← Energy conversion between forms (C9 8606)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Energy entering as sunlight and leaving as heat is a conversion between forms, which Class 9 establishes and this chapter assumes.',
        'source' => 'C10 Our Environment ← C9 Work, Energy and Simple Machines',
    ],
    [
        'concept' => 299, 'prerequisite' => 298,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ten percent law quantifies how much energy passes between levels, so the flow has to be established before it is measured.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 300, 'prerequisite' => 296,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A food web is many interconnected food chains, so the single chain is the unit it is built from.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 301, 'prerequisite' => 297,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Magnification is the concentration of a pollutant rising at each successive trophic level. Without levels there is nothing for it to rise through.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 303, 'prerequisite' => 302,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Depletion matters because of what the layer does, so its importance has to be established before its loss is a problem.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 305, 'prerequisite' => 304,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Non-biodegradable is defined by contrast with biodegradable, so the first term gives the second its meaning.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 301, 'prerequisite' => 305,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Only substances that do not break down accumulate up the chain, so non-biodegradability is what makes magnification possible.',
        'source' => 'C10 Our Environment',
    ],
    [
        'concept' => 306, 'prerequisite' => 305,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Waste strategies are chosen according to whether the waste degrades, so the distinction is the basis of the choice.',
        'source' => 'C10 Our Environment',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Reproduction: How Life Continues (8616)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7660, 'prerequisite' => 7659,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vegetative propagation is the plant case of the single-parent mode, so the mode has to be defined for it to be an instance.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7661, 'prerequisite' => 7660,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Cutting is one of the propagation methods being enumerated under the general technique.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7662, 'prerequisite' => 7660,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Grafting is a second method within the same technique and is compared with the first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7663, 'prerequisite' => 7660,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Layering completes the set of traditional propagation methods.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7664, 'prerequisite' => 7355,   // Tissue culture ← Totipotency of plant cells (8593)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tissue culture works because a single plant cell can regenerate a whole plant. Totipotency is the property the entire technique depends on.',
        'source' => 'C9 Reproduction ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7665, 'prerequisite' => 7659,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Budding is an animal and fungal case of single-parent reproduction, classified by the same criterion.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7666, 'prerequisite' => 7659,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Spore formation is another single-parent mode, listed alongside budding and fission.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7667, 'prerequisite' => 7344,   // Mitosis is basis of asexual ← Role of mitosis (8593)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asexual offspring are identical because mitosis preserves the chromosome number. Without mitosis the identity has no mechanism.',
        'source' => 'C9 Reproduction ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7668, 'prerequisite' => 7667,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Meiosis is motivated by the problem that fusing two mitotic cells would double the chromosome number each generation.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7669, 'prerequisite' => 7345,   // Meiosis halves chromosome number ← Role of meiosis (8593)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The halving is taught in the cell chapter and applied here to gametes, so Class 9 reproduction assumes the process rather than deriving it.',
        'source' => 'C9 Reproduction ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7670, 'prerequisite' => 7669,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gametes are named as the haploid cells meiosis produces, so the halving defines what a gamete is.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7671, 'prerequisite' => 7669,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Unique combinations arise because meiosis shuffles which chromosome of each pair enters a gamete.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7672, 'prerequisite' => 7671,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adaptation depends on there being variation to select from, which the unique combinations supply.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7674, 'prerequisite' => 7673,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The whorls are parts of the flower, so the flower has to be introduced as the reproductive organ before it is dissected.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7675, 'prerequisite' => 7674,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sepals and petals are two of the four whorls, so the structure precedes their functions.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7676, 'prerequisite' => 7674,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The stamen is identified as the third whorl, so the whorl scheme is what places it.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7677, 'prerequisite' => 7674,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The pistil is the innermost whorl and is located by the same scheme.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7678, 'prerequisite' => 7676,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pollination moves pollen from the anther, which is part of the stamen, so the male whorl must be identified first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7678, 'prerequisite' => 7677,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pollen arrives at the stigma, which is part of the pistil, so the female whorl is the destination the process needs.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7679, 'prerequisite' => 7678,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Self and cross pollination are two routes the same transfer can take, so the transfer must be defined first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7680, 'prerequisite' => 7679,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Wind and water are agents of cross pollination, classified under the routes just distinguished.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7681, 'prerequisite' => 7679,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Animal pollinators are the other agent group within the same classification.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7682, 'prerequisite' => 7678,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The tube grows from a pollen grain that pollination has delivered, so delivery has to happen first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7683, 'prerequisite' => 7682,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fertilisation happens when the tube delivers the male gamete to the ovule, so tube growth is the step immediately before.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7684, 'prerequisite' => 7683,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fruit and seed formation follows fertilisation, so the zygote is what the ovule becomes after it.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7685, 'prerequisite' => 7684,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What is dispersed is the seed that fertilisation produced, so seed formation has to come first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7687, 'prerequisite' => 7686,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Internal fertilisation is defined by contrast with the external case, which the chapter presents first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7688, 'prerequisite' => 7686,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Large egg numbers are explained by the losses external fertilisation incurs, so that mode is the reason.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7690, 'prerequisite' => 7687,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Yolk-rich eggs are an adaptation of internally fertilising land animals, so that mode frames the adaptation.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7691, 'prerequisite' => 7688,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Parental care is presented as the alternative strategy to producing very many offspring, so the numbers argument sets it up.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7692, 'prerequisite' => 7670,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The male organs are described as producing and transporting sperm, so the gamete has to be named before its factory.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7693, 'prerequisite' => 7670,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The female organs are described by their role in producing and receiving gametes, which the gamete concept supplies.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7694, 'prerequisite' => 7692,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Pubertal changes are described organ by organ, so the organs must be known for the changes to be located.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7695, 'prerequisite' => 7669,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gametogenesis is meiosis happening in the gonads, so the halving process is the mechanism being placed.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7696, 'prerequisite' => 7695,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing sperm and egg presupposes both have been produced by the gametogenesis just described.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7697, 'prerequisite' => 7692,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The scrotum is one of the male organs, and its cooling role only matters once sperm production is located in the testes.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7698, 'prerequisite' => 7693,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ovulation is the ovary releasing an egg, so the ovary has to be placed in the female system first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7699, 'prerequisite' => 7698,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fertilisation can only occur after an egg has been released, so ovulation is the step before.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7700, 'prerequisite' => 7699,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Menstruation is what happens when the fertilisation just described does not occur, so it is defined against that case.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7701, 'prerequisite' => 7700,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cycle is the recurring pattern of which menstruation is one phase, so the phase has to be known first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7702, 'prerequisite' => 7699,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pregnancy begins at implantation, so implantation is the event the trimesters are counted from.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7703, 'prerequisite' => 7702,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Childbirth ends the pregnancy whose stages have just been described.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7704, 'prerequisite' => 7703,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Newborn care follows the birth, so the birth has to be described before what comes after it.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7705, 'prerequisite' => 7702,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Maternal health advice is organised by stage of pregnancy, so the stages frame it.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7706, 'prerequisite' => 7694,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The distinction is drawn between physical maturity, which puberty brings, and emotional maturity, which it does not.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7707, 'prerequisite' => 7706,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Responsible decision-making is argued from the gap between physical and emotional maturity just established.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7709, 'prerequisite' => 7708,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A barrier method is explained by what infection it blocks, so the infections have to be introduced first.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7710, 'prerequisite' => 7701,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Oral contraceptives work by altering the hormonal cycle, so the cycle is what they act on.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7711, 'prerequisite' => 7699,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An intra-uterine device prevents implantation, so implantation has to be understood as the step being blocked.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7712, 'prerequisite' => 7692,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sterilisation cuts a named duct in the male or female tract, so the anatomy is what the procedure acts on.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Patterns in Life: Diversity and Classification (8617)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7600, 'prerequisite' => 7599,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ecological argument is about biodiversity, so the term has to be defined before its importance is argued.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7602, 'prerequisite' => 7601,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Endemism is defined as being restricted to one habitat or region, so habitat variety is the frame.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7603, 'prerequisite' => 7602,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A hotspot is defined partly by its number of endemic species, so endemism is a term in the definition.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7605, 'prerequisite' => 7599,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Classification exists to make the diversity manageable, so the diversity is the problem it answers.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7606, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Each criterion is a possible basis for the classification whose purpose was just stated.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7609, 'prerequisite' => 7326,   // Cellular Structure Criterion ← Prokaryotic and eukaryotic cells (8593)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Classifying by cell structure means sorting on whether the nucleus is membrane-bound, which is the prokaryote/eukaryote distinction from the cell chapter.',
        'source' => 'C9 Patterns in Life ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7609, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Cell structure is one of the criteria offered for the classification just motivated.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7614, 'prerequisite' => 7613,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The two-kingdom system is presented as the successor to Aristotle\'s habitat scheme, so the earlier attempt frames it.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7615, 'prerequisite' => 7614,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Protista is added because two kingdoms could not hold the single-celled organisms, so the two-kingdom system is the thing being repaired.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7616, 'prerequisite' => 7615,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Monera is the next addition in the same historical sequence of repairs.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7617, 'prerequisite' => 7616,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The five-kingdom system is the end point of the sequence, so each earlier stage is what it improves on.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7618, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That classification keeps changing is the lesson drawn from the sequence ending in five kingdoms.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7619, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Monera is described as one of the five kingdoms, so the scheme has to be in place before its members are detailed.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7620, 'prerequisite' => 7619,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The ecological roles follow from the characteristics just described for the kingdom.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7621, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Protista is a second kingdom within the same five-kingdom scheme.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7622, 'prerequisite' => 7621,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The food-chain role follows from protist characteristics, particularly their photosynthetic members.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7623, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fungi is the third kingdom detailed under the same scheme.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7624, 'prerequisite' => 7623,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saprophytic nutrition and hyphal structure are the defining characteristics being elaborated.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7625, 'prerequisite' => 7666,   // Fungal Reproduction and Spores ← Spore formation in fungi (8616)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fungal spores are introduced in the reproduction chapter as a single-parent mode, and this chapter uses them as a classification feature without redefining them.',
        'source' => 'C9 Patterns in Life ← C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7626, 'prerequisite' => 7624,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Fungi matter ecologically because they decompose, which is the saprophytic nutrition just described.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7627, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Plantae is the fourth kingdom in the scheme, so the scheme places it.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7628, 'prerequisite' => 7627,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Thallophyta is the first division within Plantae, so the kingdom has to be introduced before it is divided.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7629, 'prerequisite' => 7623,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A lichen is an alga and a fungus living together, so the fungal kingdom is half of what it is made of.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7630, 'prerequisite' => 7628,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bryophyta is presented as the next division up from Thallophyta, defined by the land adaptations it adds.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7631, 'prerequisite' => 7630,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pteridophyta is the next division, distinguished by having the vascular tissue bryophytes lack.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7631, 'prerequisite' => 7235,   // Pteridophyta ← Xylem (8599)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vascular plants are defined by having xylem and phloem, which the tissue chapter establishes and this one uses as a sorting criterion.',
        'source' => 'C9 Patterns in Life ← C9 Tissues in Action',
    ],
    [
        'concept' => 7632, 'prerequisite' => 7631,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gymnosperms are the next division, adding seeds to the vascular tissue pteridophytes already have.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7633, 'prerequisite' => 7632,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Angiosperms are distinguished from gymnosperms by enclosing their seeds, so the naked-seed case is the reference.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7633, 'prerequisite' => 7673,   // Angiosperm ← Flowers as reproductive organs (8616)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Angiosperms are the flowering plants, so the flower as a reproductive organ is the feature the whole division is named for.',
        'source' => 'C9 Patterns in Life ← C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7634, 'prerequisite' => 7630,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The land adaptations are the theme running through the plant divisions from bryophytes upward.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7635, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Animalia is the fifth kingdom, so the scheme places it as it did the others.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7637, 'prerequisite' => 7635,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Porifera is the simplest animal phylum, introduced once the kingdom is opened.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7638, 'prerequisite' => 7637,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cnidaria is distinguished from Porifera by having true tissues, so the simpler phylum is the comparison.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7639, 'prerequisite' => 7638,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Platyhelminthes adds bilateral symmetry to the tissue organisation cnidarians introduced.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7640, 'prerequisite' => 7639,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nematoda is the next step in the same series of increasing body complexity.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7641, 'prerequisite' => 7640,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Annelida adds segmentation to the body plan the previous phylum established.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7642, 'prerequisite' => 7641,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Arthropods are segmented animals with jointed appendages, so segmentation is the feature being built on.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7643, 'prerequisite' => 7642,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Mollusca continues the survey of invertebrate phyla in the same order of complexity.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7644, 'prerequisite' => 7643,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Echinodermata completes the invertebrate survey before chordates are taken up.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7636, 'prerequisite' => 7635,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chordates are a division of Animalia defined by the notochord, so the kingdom frames the criterion.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7646, 'prerequisite' => 7636,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vertebrate features are described within the chordate group, so chordates have to be introduced first.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7647, 'prerequisite' => 7646,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Mammary glands are one of the vertebrate adaptations being enumerated.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7648, 'prerequisite' => 7617,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The hierarchy from kingdom down to species extends the kingdom scheme, so kingdoms are its top level.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7649, 'prerequisite' => 7648,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That shared features increase further down is a statement about the hierarchy, so the levels must exist first.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7651, 'prerequisite' => 7650,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The two-part form is the convention that answers the need for a universal name.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7652, 'prerequisite' => 7651,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Genus and species are the two parts of the name, so the naming form has to be given before its components are defined.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7653, 'prerequisite' => 7651,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The capitalisation and italic rules apply to the two-part name, so the name form precedes the rules.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7655, 'prerequisite' => 7654,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading change from the layers presupposes that fossils are preserved remains laid down over time.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7604, 'prerequisite' => 7655,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The evolutionary claim is evidenced by what the fossil layers show, so the evidence precedes the conclusion.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7604, 'prerequisite' => 7672,   // Evolution ← Variation promotes adaptation (8616)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Accumulated differences are accumulated variations, and variation as the raw material of adaptation is established in the reproduction chapter.',
        'source' => 'C9 Patterns in Life ← C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7657, 'prerequisite' => 7656,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cascade is the consequence of the losses human activity causes, so the threat has to be stated first.',
        'source' => 'C9 Patterns in Life',
    ],
    [
        'concept' => 7658, 'prerequisite' => 7602,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The Sangai is presented as an endemic species dependent on one habitat, so endemism is what makes it the example.',
        'source' => 'C9 Patterns in Life',
    ],

    // ══════════════════════════════════════════════════════════════════
    // CLASS 9 BIOLOGY INTO CLASS 10
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 191, 'prerequisite' => 7659,    // Asexual Reproduction (C10) ← one parent (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 defines the single-parent mode and enumerates its methods; Class 10 revisits them briefly and moves to DNA copying, assuming the modes are known.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 195, 'prerequisite' => 7660,    // Vegetative Propagation (C10) ← (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Cutting, grafting and layering are taught in full in Class 9 and named as familiar in Class 10.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 197, 'prerequisite' => 7669,    // Sexual Reproduction (C10) ← Meiosis halves (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The halving of chromosome number in gametes is established in Class 9 and is the fact Class 10 builds inheritance on.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 198, 'prerequisite' => 7674,    // Sexual Reproduction in Flowers (C10) ← Four whorls (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The flower is dissected whorl by whorl in Class 9; Class 10 uses anther, stigma and ovary as known vocabulary.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 199, 'prerequisite' => 7678,    // Pollination and Fertilisation (C10) ← Pollination (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 covers pollination and the pollen tube in detail; Class 10 compresses both into one step and assumes the mechanism.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 201, 'prerequisite' => 7692,    // Human Male Reproductive System (C10) ← (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The male anatomy is taught in Class 9 and revisited in Class 10 at the same level of detail, so the earlier treatment is the foundation.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 202, 'prerequisite' => 7693,    // Human Female Reproductive System (C10) ← (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Same for the female system: Class 9 establishes the organs and Class 10 proceeds to the cycle and contraception.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 203, 'prerequisite' => 7700,    // Menstruation (C10) ← Menstruation when egg unfertilised (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The cycle and its cause are taught in Class 9; Class 10 refers to it as known when explaining fertility and contraception.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 204, 'prerequisite' => 7708,    // Reproductive Health (C10) ← STIs (C9)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Sexually transmitted infections are introduced in Class 9 and are part of the health argument Class 10 continues.',
        'source' => 'C10 How do Organisms Reproduce ← C9 Reproduction',
    ],
    [
        'concept' => 205, 'prerequisite' => 7671,    // Variation during Reproduction (C10) ← Unique combinations (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That each offspring gets a unique chromosome combination is the Class 9 source of the variation Class 10 reasons from.',
        'source' => 'C10 Heredity ← C9 Reproduction',
    ],
    [
        'concept' => 206, 'prerequisite' => 7604,    // Accumulation of Variation (C10) ← Evolution (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Evolution through accumulated differences is stated in Class 9; Class 10 gives it a mechanism in inheritance and selection.',
        'source' => 'C10 Heredity ← C9 Patterns in Life',
    ],
    [
        'concept' => 295, 'prerequisite' => 7626,    // Decomposers Role (C10) ← Ecological Importance of Fungi (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Decomposers are the fungi and bacteria whose saprophytic role Class 9 established, so the ecosystem component is already named there.',
        'source' => 'C10 Our Environment ← C9 Patterns in Life',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Acids, Bases and Salts (1013)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 48, 'prerequisite' => 47,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Olfactory indicators are a second kind of indicator, defined against the colour-change ones taught first.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 49, 'prerequisite' => 2739,     // Acid-Metal Reaction ← Displacement reaction (1012)
        'type' => 'requires', 'gate' => true,
        'reason' => 'A metal displacing hydrogen from an acid is a displacement reaction, so the reaction type has to be classified before this instance is read.',
        'source' => 'C10 Acids, Bases and Salts ← C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 50, 'prerequisite' => 2730,     // Acid-Carbonate ← Hit-and-trial balancing (1012)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The carbonate reaction is presented as a balanced equation producing salt, water and carbon dioxide, so balancing is needed to follow it.',
        'source' => 'C10 Acids, Bases and Salts ← C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 51, 'prerequisite' => 47,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Neutralisation is demonstrated by the indicator changing back, so the indicator is the evidence the process is complete.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 52, 'prerequisite' => 51,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A metal oxide is called basic because it neutralises an acid, so neutralisation is the test being applied.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 53, 'prerequisite' => 52,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Non-metal oxides are acidic by the mirror-image test, so the basic-oxide case is the pattern being reversed.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 54, 'prerequisite' => 7510,     // Ionic Nature of Acids ← Ions and ionic bond formation (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'An acid is defined as producing hydrogen ions in solution. Without ions having been taught there is no particle for the definition to name.',
        'source' => 'C10 Acids, Bases and Salts ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 55, 'prerequisite' => 54,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bases are defined by hydroxide ions in the same framework, so the acid case establishes the pattern.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 56, 'prerequisite' => 7362,     // Dilution ← Concentration of a solution (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dilution is lowering the concentration by adding water, so concentration must be a quantity the learner can already state.',
        'source' => 'C10 Acids, Bases and Salts ← C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 56, 'prerequisite' => 54,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What is being diluted is the concentration of hydrogen ions, so the ionic account is what dilution changes.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 57, 'prerequisite' => 54,
        'type' => 'requires', 'gate' => true,
        'reason' => 'pH measures hydrogen ion concentration, so the ion has to exist for the scale to measure anything.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 57, 'prerequisite' => 55,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The alkaline half of the scale is read in terms of hydroxide ions, so both ionic accounts are needed for the full range.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 58, 'prerequisite' => 57,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Strength is defined by how completely an acid ionises, which is read off its pH, so the scale comes first.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 59, 'prerequisite' => 57,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Soil, stomach and rain examples are all stated as pH values, so the scale is the language they are given in.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 60, 'prerequisite' => 51,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A salt is the product of neutralisation, so the reaction is where the whole family comes from.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 61, 'prerequisite' => 60,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Whether a salt solution is acidic or basic depends on which acid and base made it, so the family scheme has to be in place.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 61, 'prerequisite' => 58,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The rule is stated in terms of strong and weak parents, so that distinction is what decides the answer.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 62, 'prerequisite' => 7521,     // Chlor-alkali ← Conduction by dissolved ionic compounds (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The process passes current through brine, which only conducts because dissolved ionic compounds free their ions — the Class 9 fact.',
        'source' => 'C10 Acids, Bases and Salts ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 63, 'prerequisite' => 62,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bleaching powder is made from the chlorine the chlor-alkali process produces, so the process is its source.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 64, 'prerequisite' => 62,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Baking soda is produced from the sodium chloride route the same process opens, so the process frames its preparation.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 65, 'prerequisite' => 64,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Washing soda is made by heating and recrystallising baking soda, so the earlier compound is the starting material.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 66, 'prerequisite' => 65,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Washing soda is the worked example of a hydrated salt, so the compound introduces the idea of water held in a crystal.',
        'source' => 'C10 Acids, Bases and Salts',
    ],
    [
        'concept' => 67, 'prerequisite' => 66,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Plaster of Paris is defined by how much water of crystallisation it retains, so that idea is needed to state what it is.',
        'source' => 'C10 Acids, Bases and Salts',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · Carbon and its Compounds (1015)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 131, 'prerequisite' => 7506,    // Covalent Bonding ← Covalent bond (C9)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 9 introduces the shared-pair bond; Class 10 applies it to carbon and does not re-derive why sharing produces stability.',
        'source' => 'C10 Carbon and its Compounds ← C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 134, 'prerequisite' => 7443,    // Tetravalency ← Valency (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'That carbon has a valency of four is the single fact the whole chapter is deduced from, and valency itself is Class 9 content.',
        'source' => 'C10 Carbon and its Compounds ← C9 Journey Inside the Atom',
    ],
    [
        'concept' => 134, 'prerequisite' => 131,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tetravalency is expressed as carbon forming four covalent bonds, so covalent bonding is the mechanism it uses.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 133, 'prerequisite' => 134,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Catenation is carbon using its four bonds to link to further carbons, so tetravalency is what makes chains possible.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 132, 'prerequisite' => 133,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Diamond and graphite differ in how carbon atoms link to each other, which is catenation arranged two ways.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 135, 'prerequisite' => 133,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A saturated hydrocarbon is a carbon chain with only single bonds, so chain formation has to be established first.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 136, 'prerequisite' => 135,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Unsaturated is defined by contrast — a chain carrying a double or triple bond — so the saturated case is the reference.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 137, 'prerequisite' => 135,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Isomers are different arrangements of the same chain, so the chain has to be drawable before it can be rearranged.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 138, 'prerequisite' => 136,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A functional group replaces a hydrogen on the hydrocarbon skeleton, so the skeleton must exist for the substitution to be described.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 139, 'prerequisite' => 138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A homologous series is compounds sharing one functional group with a growing chain, so the group is half the definition.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 140, 'prerequisite' => 139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming works by counting chain carbons and appending the group suffix, which is exactly what the homologous series systematises.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 141, 'prerequisite' => 135,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Combustion is described for hydrocarbons specifically, so the hydrocarbon has to be defined before it is burned.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 142, 'prerequisite' => 2741,    // Oxidation Reactions ← Oxidation (1012)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Organic oxidation is the same electron-loss idea from the reactions chapter, applied to alcohols becoming acids.',
        'source' => 'C10 Carbon and its Compounds ← C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 143, 'prerequisite' => 136,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Addition happens at a double bond, so unsaturation is the structural feature the reaction requires.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 144, 'prerequisite' => 135,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Substitution replaces a hydrogen on a saturated chain, so saturation is the condition that makes substitution rather than addition the route.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 145, 'prerequisite' => 138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ethanol is presented as the worked example of the alcohol functional group, so the group frames the compound.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 146, 'prerequisite' => 138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ethanoic acid is the worked example of the carboxylic acid group, classified by the same scheme.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 147, 'prerequisite' => 145,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An ester is formed from an alcohol and an acid, so the alcohol is one of the two reactants.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 147, 'prerequisite' => 146,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The carboxylic acid is the other reactant, and the reaction cannot be written without both.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 148, 'prerequisite' => 147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saponification is the ester being hydrolysed back by alkali, so ester formation is the reaction being reversed.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 149, 'prerequisite' => 148,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A micelle is formed by the soap molecule saponification produces, so the product has to exist before its behaviour in water.',
        'source' => 'C10 Carbon and its Compounds',
    ],
    [
        'concept' => 150, 'prerequisite' => 149,
        'type' => 'requires', 'gate' => true,
        'reason' => 'How soaps clean, and why detergents work in hard water, are both explained by the micelle, so the structure comes first.',
        'source' => 'C10 Carbon and its Compounds',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Exploration: Entering the World of Secondary Science (8592)
    // A nature-of-science chapter. Its concepts are mostly independent
    // ideas, so this is deliberately SPARSE - only where one idea is
    // genuinely unstatable without another.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7186, 'prerequisite' => 7185,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Giving exploration a purpose is presented as what turns careful looking into investigation, so observation is what is being directed.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7188, 'prerequisite' => 7187,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Leaving detail out is what makes a model simplified, so the model idea is what the omission is a property of.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7189, 'prerequisite' => 7188,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Which details may be dropped depends on the question, so deliberate omission has to be established before it is made selective.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7190, 'prerequisite' => 7189,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding detail back is the reverse of dropping it for a purpose, so the selection principle frames the trade-off.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7192, 'prerequisite' => 7191,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A symbol is a precise shorthand for a term, so the demand for precision is what motivates symbols at all.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7193, 'prerequisite' => 32701,  // International standard units ← Why the World Agreed on SI (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 argues why a shared system is necessary; Class 9 takes SI as given and uses it to write quantities formally.',
        'source' => 'C9 Exploration ← C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 7193, 'prerequisite' => 7192,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A standard unit is a symbol everyone agrees on, so the symbol convention comes before the agreement about it.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7195, 'prerequisite' => 7192,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An equation is built from the symbols for quantities, so the symbols have to mean something before they are related.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7197, 'prerequisite' => 7196,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A theory explains the pattern a law describes, so the law is the thing being explained.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7198, 'prerequisite' => 7197,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A principle is distinguished from a theory by its breadth, so the theory is the reference point.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7199, 'prerequisite' => 7197,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Revisability is a property of theories, so theories must exist before they can be said to change.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7201, 'prerequisite' => 7200,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling a prediction a reasoned expectation qualifies the prediction, so the prediction has to be introduced first.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7202, 'prerequisite' => 7201,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A prediction succeeding is a fact about predictions, so the account of what they are comes first.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7203, 'prerequisite' => 7202,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mismatch case is taught by contrast with the success case, so the successful one sets up the comparison.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7204, 'prerequisite' => 7201,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A testable question is one whose prediction can be checked, so prediction is what testability is defined against.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7205, 'prerequisite' => 7204,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Complex systems are presented as the limit of testability, so the standard case has to be described first.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7207, 'prerequisite' => 7206,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using an estimate as a reasonableness check presupposes that estimating has been introduced as a method.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7208, 'prerequisite' => 7207,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Estimating from rates and assumptions is the technique that produces the approximate value used as a check.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7209, 'prerequisite' => 7208,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cross-checking compares two independent estimates, so the method of making one has to be available.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7211, 'prerequisite' => 7210,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That real problems cross branches only means something once the branches have been called artificial divisions.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7212, 'prerequisite' => 7211,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Links to non-science domains extend the cross-branch argument one step further out.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7214, 'prerequisite' => 7213,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Collective effort is a claim about science as a human activity, so that framing has to be in place.',
        'source' => 'C9 Exploration',
    ],
    [
        'concept' => 7215, 'prerequisite' => 7212,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Applying scientific thinking outside science is the practical conclusion of science linking to other domains.',
        'source' => 'C9 Exploration',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Earth as a System: Energy, Matter, and Life (8618)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 8057, 'prerequisite' => 8056,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Each sphere is introduced as one component of the single interacting system, so the system frames them all.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8058, 'prerequisite' => 8056,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The hydrosphere is a second component of the same system, named within the same scheme.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8059, 'prerequisite' => 8058,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cryosphere is the frozen part of the hydrosphere, so the water sphere has to be defined first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8060, 'prerequisite' => 8056,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The atmosphere completes the non-living components of the interacting system.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8061, 'prerequisite' => 8056,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The biosphere is the living component of the same system, and is the one every later cycle runs through.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8062, 'prerequisite' => 8061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That disturbing one sphere changes the others needs all the spheres to have been named for the interaction to have parties.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8063, 'prerequisite' => 8056,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Solar radiation is introduced as the energy input driving the whole system, so the system is what it drives.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8064, 'prerequisite' => 8063,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting the radiation into UV, visible and infrared presupposes it has been described as electromagnetic waves.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8065, 'prerequisite' => 8063,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Insolation is the solar radiation actually received per unit area, so the radiation has to be introduced first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8066, 'prerequisite' => 8065,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The solar constant is the insolation at the top of the atmosphere, so insolation is the quantity being fixed.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8067, 'prerequisite' => 8066,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The calculation multiplies the solar constant by an area and a time, so the constant is its starting value.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8068, 'prerequisite' => 8065,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Albedo is the fraction of incoming insolation reflected, so insolation is what the fraction is taken of.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8069, 'prerequisite' => 8065,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Uneven heating is uneven insolation caused by the angle of incidence, so insolation is the quantity that varies.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8071, 'prerequisite' => 8070,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The layers are distinguished by composition and temperature, so the composition has to be given first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8072, 'prerequisite' => 8064,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The greenhouse effect is gases letting visible light in and trapping outgoing infrared, so the two bands must be distinguished.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8073, 'prerequisite' => 8069,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Winds arise because uneven heating creates pressure differences, so the uneven heating is the cause.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8073, 'prerequisite' => 31155,  // Winds ← Atmospheric pressure (C8)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Air pressure as a quantity is taught in Class 8; Class 9 reasons about differences in it and never redefines the quantity itself.',
        'source' => 'C9 Earth as a System ← C8 Force and Pressure',
    ],
    [
        'concept' => 8074, 'prerequisite' => 8073,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A valley breeze is a local wind driven by a local pressure difference, so the general mechanism is what it instantiates.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8075, 'prerequisite' => 8074,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mountain breeze is the night-time reversal of the valley breeze, so the daytime case is the reference.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8076, 'prerequisite' => 8073,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pressure belts are the planetary-scale version of the same pressure-difference mechanism.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8077, 'prerequisite' => 8076,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The polar belts complete the set begun with the equatorial and subtropical ones.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8078, 'prerequisite' => 8073,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Coriolis deflects an already-moving wind, so wind formation has to precede its deflection.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8079, 'prerequisite' => 8078,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Surface currents are driven by winds and deflected by Coriolis, so both are inputs to the current pattern.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8080, 'prerequisite' => 8079,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Density-driven circulation is contrasted with the wind-driven surface currents introduced first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8081, 'prerequisite' => 8079,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gyres are the closed loops the surface currents form, so the currents are what loops.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8082, 'prerequisite' => 8079,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The climate role follows from currents carrying heat, so the currents have to be established as moving water.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8083, 'prerequisite' => 8062,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A biogeochemical cycle is matter moving between the spheres, so sphere interaction is the framework it needs.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8084, 'prerequisite' => 8083,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The water cycle is the first worked example of a biogeochemical cycle, so the general idea frames it.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8085, 'prerequisite' => 8084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Changes to the cycle are changes to the pathways just described, so the pathways come first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8086, 'prerequisite' => 8083,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carbon reservoirs are the stores the carbon cycle moves between, so the cycle idea places them.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8087, 'prerequisite' => 8086,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fast cycle moves carbon between the reservoirs just named, so the reservoirs must be defined.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8087, 'prerequisite' => 7336,   // Fast carbon cycle ← Chloroplasts and photosynthesis (8593)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fast cycle runs through photosynthesis and respiration. Without photosynthesis there is no route by which carbon enters life.',
        'source' => 'C9 Earth as a System ← C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 8088, 'prerequisite' => 8087,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The slow cycle is defined by contrast with the fast one, over geological rather than biological time.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8089, 'prerequisite' => 8086,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ocean is one of the carbon reservoirs, so its absorbing role sits inside the reservoir scheme.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8090, 'prerequisite' => 8088,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Burning fossil fuels moves slow-cycle carbon into the fast cycle, so both cycles must be distinguished for the point to land.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8092, 'prerequisite' => 8091,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fixation exists because atmospheric nitrogen is unusable by most life, which is the importance argument made first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8093, 'prerequisite' => 8092,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nitrification acts on the ammonia fixation produces, so fixation is the step before it in the cycle.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8094, 'prerequisite' => 8093,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Assimilation takes up the nitrates nitrification makes, so the sequence order is the dependency.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8095, 'prerequisite' => 8094,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ammonification returns nitrogen from dead tissue that assimilation had built, so assimilation precedes it.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8096, 'prerequisite' => 8095,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Denitrification closes the cycle by returning nitrogen to the air, so it is the last step of the sequence.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8097, 'prerequisite' => 8083,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The oxygen cycle is a third biogeochemical cycle, framed by the same general idea.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8098, 'prerequisite' => 8089,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Acidification is the chemical consequence of the ocean absorbing carbon dioxide, so absorption is the cause.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8099, 'prerequisite' => 8089,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Warming reducing absorption is a statement about the absorption process, which must be established first.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8100, 'prerequisite' => 8099,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saturation is the end state of absorption declining, so the decline has to be described before its limit.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8101, 'prerequisite' => 8094,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Eutrophication is excess nitrate being assimilated by algae, so the assimilation step is the mechanism it exploits.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8102, 'prerequisite' => 8084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Deforestation lowers rainfall by removing transpiration from the water cycle, so the cycle pathways are what is being cut.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8103, 'prerequisite' => 8102,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Erosion and biodiversity loss are further consequences of the same clearance, presented alongside the rainfall effect.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8104, 'prerequisite' => 8071,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That ozone is protective high up and harmful at the surface only makes sense once the atmosphere has layers.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8105, 'prerequisite' => 8062,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cooperation is argued for because a disturbance in one sphere spreads to all, which is the interaction principle.',
        'source' => 'C9 Earth as a System',
    ],
    [
        'concept' => 8106, 'prerequisite' => 8105,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Individual sustainable action is presented as the local counterpart of the global cooperation just argued for.',
        'source' => 'C9 Earth as a System',
    ],

    // ── Class 9 Earth system into Class 10 environment ──────────────────

    [
        'concept' => 292, 'prerequisite' => 8061,    // Ecosystem Components (C10) ← Biosphere definition (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'An ecosystem is a unit of the biosphere interacting with its non-living surroundings, which is exactly the sphere framework Class 9 builds.',
        'source' => 'C10 Our Environment ← C9 Earth as a System',
    ],
    [
        'concept' => 302, 'prerequisite' => 8071,    // Ozone Layer Importance (C10) ← Atmospheric layers (C9)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The protective ozone sits in the stratosphere, so the learner needs the atmosphere to have distinct layers before the layer can be located.',
        'source' => 'C10 Our Environment ← C9 Earth as a System',
    ],
    [
        'concept' => 306, 'prerequisite' => 8106,    // Waste Management (C10) ← Sustainable actions (C9)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Class 9 makes the general sustainability case; Class 10 turns it into specific waste-handling strategies.',
        'source' => 'C10 Our Environment ← C9 Earth as a System',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Coal and Petroleum (25839)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31043, 'prerequisite' => 31042,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Inexhaustible is one half of a split applied to natural resources, so the category has to exist before it is divided.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31044, 'prerequisite' => 31043,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Exhaustible is defined by contrast with the inexhaustible case presented first.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31045, 'prerequisite' => 31044,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fossil fuels are the worked example of an exhaustible resource, so the category frames why their formation time matters.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31046, 'prerequisite' => 31045,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carbonisation is the process that turned buried plants into coal, so the fossil origin is what it describes.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31047, 'prerequisite' => 31046,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Coke is a product of processing coal, so coal formation has to be covered before its products.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31048, 'prerequisite' => 31046,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Coal tar is a second product of the same processing.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31049, 'prerequisite' => 31046,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Coal gas completes the set of products obtained from processing coal.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31051, 'prerequisite' => 31045,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Petroleum formation is the marine counterpart of the same fossil process, so the fossil-fuel idea frames it.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31050, 'prerequisite' => 31051,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Where petroleum is found follows from how it formed, so formation explains the location.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31052, 'prerequisite' => 31051,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Refining separates the mixture petroleum formation produced, so the crude material has to exist first.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31053, 'prerequisite' => 31052,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Petrochemicals are the fractions refining yields, so refining is where they come from.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31054, 'prerequisite' => 31052,
        'type' => 'requires', 'gate' => false,
        'reason' => 'CNG is presented alongside the refined fractions as an alternative fuel from the same source.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31055, 'prerequisite' => 31054,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That CNG burns cleaner is a property of the fuel just introduced.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31057, 'prerequisite' => 31045,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mismatch between formation time and burning time is the whole conservation argument, and needs the fossil origin.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31059, 'prerequisite' => 31057,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That they cannot be manufactured reinforces the slow-formation point, which has to be made first.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31060, 'prerequisite' => 31059,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The conservation advice is the practical response to fuels being irreplaceable.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31058, 'prerequisite' => 31052,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The pollution discussed is from burning the refined fuels, so the fuels have to be introduced before their emissions.',
        'source' => 'C8 Coal and Petroleum',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Combustion and Flame (25840)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31062, 'prerequisite' => 31061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Air being necessary is one of the conditions for the process just defined, so the definition comes first.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31063, 'prerequisite' => 31061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ignition temperature is the second condition for combustion, defined as the temperature at which it starts.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31064, 'prerequisite' => 31063,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An inflammable substance is one with a very low ignition temperature, so the quantity has to be defined to be low.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31065, 'prerequisite' => 31062,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Extinguishing works by removing fuel, air or heat, so the necessity of air is one of the three legs being kicked away.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31065, 'prerequisite' => 31063,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cooling below the ignition temperature is the third way to stop combustion, so that temperature is one of the conditions.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31066, 'prerequisite' => 31065,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Water works by cooling and fails on oil and electrical fires, which is the remove-one-condition principle applied.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31067, 'prerequisite' => 31066,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carbon dioxide is introduced where water fails, so the failure case is the reason for the alternative.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31068, 'prerequisite' => 31061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rapid combustion is one type within the process just defined, classified by speed.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31069, 'prerequisite' => 31068,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Spontaneous combustion is distinguished from the rapid case by needing no external ignition.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31070, 'prerequisite' => 31069,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Coal dust and forest fires are the worked examples of spontaneous ignition.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31071, 'prerequisite' => 31068,
        'type' => 'requires', 'gate' => false,
        'reason' => 'An explosion is the extreme of rapid combustion, so the rapid case is the scale it extends.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31072, 'prerequisite' => 31061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That only vapours burn with a flame is a fact about how combustion proceeds, so combustion has to be defined first.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31073, 'prerequisite' => 31072,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three zones are regions of a flame, and the flame exists only where vapours burn.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31074, 'prerequisite' => 31061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fuel quality is judged by how it burns, so combustion is the behaviour being assessed.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31075, 'prerequisite' => 31074,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That no fuel meets every criterion presupposes the criteria have been listed.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31076, 'prerequisite' => 31074,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calorific value is the measurable version of one of the quality criteria, so the criteria come first.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31077, 'prerequisite' => 31076,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fuels are compared by their calorific values, so the quantity has to be defined before the table means anything.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31077, 'prerequisite' => 31053,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The fuels being compared are the refined fractions introduced in the previous chapter, so those supply the list.',
        'source' => 'C8 Combustion and Flame ← C8 Coal and Petroleum',
    ],
    [
        'concept' => 31078, 'prerequisite' => 31061,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carbon monoxide comes from incomplete combustion, so the process is what is being incompletely performed.',
        'source' => 'C8 Combustion and Flame',
    ],
    [
        'concept' => 31079, 'prerequisite' => 31078,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Acid rain and warming are further consequences of the combustion products just named.',
        'source' => 'C8 Combustion and Flame',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Chemical Effects of Electric Current (25847)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31196, 'prerequisite' => 31194,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Whether the bulb glows is the result of the test being performed, so the test has to be set up first.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31197, 'prerequisite' => 31196,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The caution is that a dark bulb may still carry a weak current, which only corrects a conclusion the learner has already drawn.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31198, 'prerequisite' => 31197,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The bulb fails because it needs enough current to heat the filament, which is the explanation of the caution.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31199, 'prerequisite' => 31198,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The LED is introduced because it detects weaker currents than a filament bulb, so the bulb\'s limitation motivates it.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31200, 'prerequisite' => 31199,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The magnetic tester is a third detector in the same sequence of increasingly sensitive methods.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31201, 'prerequisite' => 31194,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing distilled water with salt water is the test being applied to two liquids, so the test procedure comes first.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31202, 'prerequisite' => 31201,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tap water conducts because of dissolved salts, which is exactly the comparison the distilled-water experiment sets up.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31203, 'prerequisite' => 31202,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Acids and bases are added to the list of conducting solutions once dissolved solutes have been shown to be the cause.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31204, 'prerequisite' => 31201,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The bubbles appear in the conducting solution the experiment has just established, so the setup precedes the observation.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31206, 'prerequisite' => 31204,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What the effects depend on is a question about the observed chemical effect, which must be seen first.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31208, 'prerequisite' => 31206,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Which electrode the deposit forms on is one of the dependencies being investigated.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31210, 'prerequisite' => 31204,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Copper moving from one electrode to the other is the chemical effect made visible, so the effect has to be established.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31211, 'prerequisite' => 31210,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chromium plating is the industrial use of the copper-transfer mechanism just demonstrated.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31212, 'prerequisite' => 31211,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Tin and zinc plating are further applications, and the waste problem follows from the industry they support.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Some Natural Phenomena (25848)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31216, 'prerequisite' => 31215,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Attraction is the evidence that rubbing has produced charge, so the charging has to be introduced first.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31217, 'prerequisite' => 31215,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That both rubbed objects are charged is a refinement of the rubbing observation.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31219, 'prerequisite' => 31216,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Testing many materials applies the attraction test systematically, so the test has to exist.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31220, 'prerequisite' => 31219,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two kinds of charge is the conclusion drawn from some pairs attracting and others repelling across many materials.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31221, 'prerequisite' => 31220,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming them positive and negative labels the two kinds the experiments distinguished.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31222, 'prerequisite' => 31221,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The electroscope detects and compares charge, so charge has to be a named quantity with a sign.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31223, 'prerequisite' => 31221,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Earthing is charge draining away to the ground, so charge must be established as something that can move.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31224, 'prerequisite' => 31221,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A thundercloud separating positive from negative is the same two-charge picture at enormous scale.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31225, 'prerequisite' => 31224,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The discharge happens once separation has built enough potential, so separation is the state that precedes it.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31226, 'prerequisite' => 31225,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Safety advice is about where the discharge will and will not reach, so the discharge mechanism frames it.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31227, 'prerequisite' => 31226,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The unsafe places are the complement of the safe ones, presented as the same guidance.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31229, 'prerequisite' => 31228,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Seismographs record the plate movement just described, so the mechanism precedes its measurement.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31230, 'prerequisite' => 31229,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Building standards are set against recorded earthquake magnitudes, so measurement comes first.',
        'source' => 'C8 Some Natural Phenomena',
    ],

    // ── Class 8 chemistry and electrostatics into Class 9 and 10 ────────

    [
        'concept' => 7521, 'prerequisite' => 31202,  // Conduction by dissolved ionic compounds (C9) ← Why tap water conducts (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 shows that dissolved salts make water conduct; Class 9 explains it by free ions. The observation is assumed and only the explanation is new.',
        'source' => 'C9 Atomic Foundations of Matter ← C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 261, 'prerequisite' => 31221,   // Electric Current (C10) ← Positive, negative and static (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Current is a flow of charge, and charge with a sign is established in Class 8 electrostatics. Class 10 assumes it entirely.',
        'source' => 'C10 Electricity ← C8 Some Natural Phenomena',
    ],
    [
        'concept' => 126, 'prerequisite' => 31211,   // Electrolytic Refining (C10) ← Chromium plating (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Electroplating is demonstrated in Class 8; Class 10 applies the same cell to purifying a metal and does not re-describe the apparatus.',
        'source' => 'C10 Metals and Non-metals ← C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 62, 'prerequisite' => 31204,    // Chlor-alkali (C10) ← Gas bubbles at the electrodes (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Gases appearing at electrodes is the Class 8 observation that the chlor-alkali process industrialises.',
        'source' => 'C10 Acids, Bases and Salts ← C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 141, 'prerequisite' => 31061,   // Combustion of Carbon Compounds (C10) ← Combustion defined (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Combustion and its conditions are established in Class 8; Class 10 applies it to hydrocarbons and only adds the sooty-flame distinction.',
        'source' => 'C10 Carbon and its Compounds ← C8 Combustion and Flame',
    ],
    [
        'concept' => 2734, 'prerequisite' => 31061,  // Exothermic reactions (C10) ← Combustion defined (C8)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Burning is the everyday exothermic reaction learners already have from Class 8, and it is the example Class 10 opens with.',
        'source' => 'C10 Chemical Reactions and Equations ← C8 Combustion and Flame',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Materials Around Us (29839)
    // The root of the whole matter strand. Class 9's particle model is
    // built on the mass-and-volume definition established here.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32717, 'prerequisite' => 32715,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sorting materials means sorting by a chosen property, so the learner must first accept that a material is a thing with properties.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32719, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Lustre is one of the properties the classification scheme offers, so the scheme frames it.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32720, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Hardness is a second sorting property, and its relativity is a caution about using it.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32721, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => false,
        'reason' => 'How a material handles light is a third property in the same classification.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32722, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Solubility is the property Class 9 will build mixtures and solutions on, and it enters here as one of the sorting criteria.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32724, 'prerequisite' => 32723,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Volume is introduced after mass as the second thing a material has, so the comparison of heaviness comes first.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32725, 'prerequisite' => 32724,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Matter is defined as anything having mass and volume. Both quantities have to be established for the definition to have terms.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32718, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Why a ball is made of what it is applies the property scheme to a design decision.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32729, 'prerequisite' => 32718,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Choosing a material for a job generalises the single worked example into a method.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32728, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sorting a kitchen is the classification scheme exercised on an everyday collection.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32730, 'prerequisite' => 32719,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Recyclers sort by exactly the observable properties the chapter has been teaching, lustre among them.',
        'source' => 'C6 Materials Around Us',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Temperature and its Measurement (29840)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 33276, 'prerequisite' => 33275,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Naming the scales after Celsius and Fahrenheit follows the observation that more than one scale is in use.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33277, 'prerequisite' => 33276,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting to kelvin adds a fixed number to a Celsius reading, so the Celsius scale has to be established first.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33279, 'prerequisite' => 33278,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting the divisions is done within the range the thermometer covers, so the range has to be read first.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33280, 'prerequisite' => 33279,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Working out what one division is worth requires having counted the divisions between two marked values.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33285, 'prerequisite' => 33280,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing a thermometer means matching its range and smallest division to the job, so both have to be readable.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33282, 'prerequisite' => 33281,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Recording daily maxima and minima is what the wall thermometer is for, so the instrument comes first.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33283, 'prerequisite' => 33282,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Explaining why the figures vary presupposes a record of varying figures.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33284, 'prerequisite' => 33283,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Reading a record as a history is the interpretive step after the variation has been explained.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33273, 'prerequisite' => 33272,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Non-contact thermometers are presented among the replacements for mercury, so the reason for replacing it frames them.',
        'source' => 'C6 Temperature and its Measurement',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · A Journey through States of Water (29841)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32737, 'prerequisite' => 32731,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Three states of one substance only makes sense once ice and water have been accepted as the same substance.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32738, 'prerequisite' => 32737,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Shape, flow and spreading are the behaviours that distinguish the three states, so the states must be named first.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32740, 'prerequisite' => 32737,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Melting and freezing are changes BETWEEN states, so the states have to exist for a change between them to be described.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32735, 'prerequisite' => 32733,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Condensation is named as the explanation of the drops on the cold glass, so the observation motivates the term.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32736, 'prerequisite' => 32735,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Humidity is the water in the air that condensation draws on, so condensation has to be established as the process.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32739, 'prerequisite' => 32736,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Harvesting water from humid air applies the humidity idea deliberately.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32741, 'prerequisite' => 32732,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The rate of evaporation is a property of the disappearance the plate experiment shows, so the phenomenon precedes its controls.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32742, 'prerequisite' => 32741,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sun, wind and humidity are further factors in the same rate, so the idea that the rate can vary comes first.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32743, 'prerequisite' => 32742,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The earthen pot cools because evaporation is fast through its porous wall, which is the rate argument applied.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32744, 'prerequisite' => 32743,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The pot-in-pot cooler is the earthen-pot principle built into a device.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32745, 'prerequisite' => 32735,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Clouds form by condensation onto dust, so condensation is the process the dust provides a surface for.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32746, 'prerequisite' => 32735,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cycle is built from evaporation and condensation, so condensation is one of its two engines.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32746, 'prerequisite' => 32741,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Evaporation is the other engine, and the cycle cannot be traced without it.',
        'source' => 'C6 A Journey through States of Water',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Methods of Separation in Everyday Life (29842)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32751, 'prerequisite' => 32750,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Winnowing removes the husk that threshing has loosened, so the two steps are sequential in the process.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32752, 'prerequisite' => 32751,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sieving is introduced as the next method when size rather than weight is the difference to exploit.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32753, 'prerequisite' => 32722,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Getting salt back from seawater assumes the salt is dissolved, which is the solubility property established in the materials chapter.',
        'source' => 'C6 Methods of Separation ← C6 Materials Around Us',
    ],
    [
        'concept' => 32753, 'prerequisite' => 32741,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The water leaves by evaporation, so the rate and conditions of evaporation are what make the method work in the sun.',
        'source' => 'C6 Methods of Separation ← C6 A Journey through States of Water',
    ],
    [
        'concept' => 32754, 'prerequisite' => 32753,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Boiling is the faster version of the same separation, so the sun-drying case is what it speeds up.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32756, 'prerequisite' => 32755,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sedimentation and decantation name what was observed when the tea leaves settled and the liquid was poured off.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32757, 'prerequisite' => 32756,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Residue and filtrate name the two parts filtration produces, and filtration is introduced where decanting is not clean enough.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32758, 'prerequisite' => 32757,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Nets and cloth are everyday filters, classified by the residue-and-filtrate scheme just named.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32759, 'prerequisite' => 32755,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Butter rising is separation by density, presented alongside settling as the opposite direction of the same idea.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32761, 'prerequisite' => 32757,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The summary list collects the methods, so filtration has to be among those already taught.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32761, 'prerequisite' => 32754,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Evaporation and boiling are also on the list being collected, so they precede the summary.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32762, 'prerequisite' => 32761,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testing which method suits which condition requires the full set of methods to choose between.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32763, 'prerequisite' => 32762,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ordering steps for a multi-part mixture builds on knowing which single method each condition calls for.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],

    // ── Class 6 into Class 9 — the matter strand's long jump ────────────

    [
        'concept' => 7497, 'prerequisite' => 32725,  // Particulate nature of matter (C9) ← Anything with Mass and Volume (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 defines matter as anything with mass and volume; Class 9 asks what that anything is made of. The definition is the thing being explained.',
        'source' => 'C9 Atomic Foundations of Matter ← C6 Materials Around Us',
    ],
    [
        'concept' => 7356, 'prerequisite' => 32722,  // Homogeneous mixture definition (C9) ← Soluble and Insoluble (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'A solution is the homogeneous case, and dissolving is the Class 6 property that makes it one. Class 9 assumes solubility is familiar.',
        'source' => 'C9 Exploring Mixtures ← C6 Materials Around Us',
    ],
    [
        'concept' => 7371, 'prerequisite' => 32754,  // Crystallization definition (C9) ← Boiling the Salt Back (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 recovers salt by boiling the water off; Class 9 refines that into crystallisation and explains why slow cooling gives better crystals.',
        'source' => 'C9 Exploring Mixtures ← C6 Methods of Separation',
    ],
    [
        'concept' => 7387, 'prerequisite' => 32757,  // Limitation of filtration (C9) ← Residue and Filtrate (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Saying where filtration fails needs the learner to know what it does, which Class 6 establishes as splitting residue from filtrate.',
        'source' => 'C9 Exploring Mixtures ← C6 Methods of Separation',
    ],
    [
        'concept' => 7555, 'prerequisite' => 32737,  // Sound propagates in all states (C9) ← Three States (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The claim is that sound travels through solids, liquids and gases alike, which assumes the three states are already a familiar division.',
        'source' => 'C9 Sound Waves ← C6 A Journey through States of Water',
    ],
    [
        'concept' => 8084, 'prerequisite' => 32746,  // Water cycle pathways (C9) ← The Water Cycle (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 traces the cycle; Class 9 places it inside the biogeochemical framework and adds the climate-change effects. The cycle itself is assumed.',
        'source' => 'C9 Earth as a System ← C6 A Journey through States of Water',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Diversity in the Living World (29835)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32649, 'prerequisite' => 32648,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The distinguishing features are read out of the table the walk produced, so the recording comes before the comparison.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32651, 'prerequisite' => 32649,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That different bases give different groups only means something once several distinguishing features are in hand.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32652, 'prerequisite' => 32651,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Stem and height are one chosen basis, so the idea of choosing a basis has to come first.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32653, 'prerequisite' => 32652,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The five plant categories are the result of grouping by stem and height, so the basis produces the names.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32654, 'prerequisite' => 32651,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Leaf venation and root type are a second basis, offered as an alternative to the first.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32655, 'prerequisite' => 32654,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The correlation between reticulate veins and taproots can only be noticed once both features are being recorded.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32656, 'prerequisite' => 32655,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cotyledon count is the third member of the same correlated set, and completes the monocot/dicot picture.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32657, 'prerequisite' => 32651,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Movement is offered as a basis for grouping animals, parallel to the plant bases.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32659, 'prerequisite' => 32650,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking why regions differ presupposes biodiversity as something that varies from place to place.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32660, 'prerequisite' => 32659,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The two camels are the worked case of regional difference, so the general question frames them.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32661, 'prerequisite' => 32660,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adaptation is named as the explanation of why the two camels differ, so the example motivates the term.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32662, 'prerequisite' => 32661,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Terrestrial and aquatic adaptations are classified by habitat, which needs adaptation to be defined first.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32663, 'prerequisite' => 32650,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sacred groves are presented as local biodiversity protection, so biodiversity is what is being protected.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32665, 'prerequisite' => 32651,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Inventing your own grouping applies the chosen-basis idea independently.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32666, 'prerequisite' => 32648,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A biodiversity register is the recording table extended to a whole locality.',
        'source' => 'C6 Diversity in the Living World',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Mindful Eating: A Path to a Healthy Body (29836)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32670, 'prerequisite' => 32669,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Proteins are introduced as a second job food does, so the energy-giving role has to be established first.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32673, 'prerequisite' => 32670,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The word nutrient generalises the specific roles just described, so the examples come before the category.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32671, 'prerequisite' => 32673,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Scurvy is explained as a missing nutrient, so the nutrient category has to exist for one to be missing.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32672, 'prerequisite' => 32671,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Goitre is the second deficiency case, taught by the same pattern the sailors established.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32674, 'prerequisite' => 32673,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Roughage and water complete the list of what a balanced diet must contain beyond the energy nutrients.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32675, 'prerequisite' => 32669,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The iodine test detects starch, so the learner must know starch is a carbohydrate worth testing for.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32676, 'prerequisite' => 32675,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fat and protein tests are taught as the next two in the same series of food tests.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32677, 'prerequisite' => 32676,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing wafers with chana applies the tests to make a dietary judgement.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32678, 'prerequisite' => 32677,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The junk food discussion generalises the wafers-versus-chana comparison into food policy.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32679, 'prerequisite' => 32673,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Millets are recommended on the basis of the nutrients they carry, so the nutrient idea underpins the case.',
        'source' => 'C6 Mindful Eating',
    ],
    [
        'concept' => 32680, 'prerequisite' => 32668,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Food miles extend the local-crops discussion to where food actually travels from.',
        'source' => 'C6 Mindful Eating',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Living Creatures: Exploring their Characteristics (29843)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32766, 'prerequisite' => 32765,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Plant movement is noticed by comparing plants with yourself, which is the method the chapter opens with.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32767, 'prerequisite' => 32765,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Breathing and excreting are further life characteristics found by the same self-comparison.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32768, 'prerequisite' => 32767,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reproduction and death complete the list of characteristics, so the earlier ones set the pattern.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32769, 'prerequisite' => 32766,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The four-pot experiment tests whether plants respond, which is the movement claim being investigated.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32770, 'prerequisite' => 32769,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The result about light comes out of the controlled four-pot setup, so the setup precedes the finding.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32771, 'prerequisite' => 32769,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The beaker experiment repeats the one-variable design on a different question.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32772, 'prerequisite' => 32766,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The crescograph is introduced as the instrument that made plant movement measurable.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32773, 'prerequisite' => 32768,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Watching a plant through its life stages is observing the growth and reproduction characteristics just named.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32774, 'prerequisite' => 32768,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mosquito life cycle is the animal counterpart of the plant one, and both illustrate reproduction and growth.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32775, 'prerequisite' => 32774,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Designing a test for the order of the stages presupposes the stages have been identified.',
        'source' => 'C6 Living Creatures',
    ],
    [
        'concept' => 32776, 'prerequisite' => 32774,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Noticing that some accounts club stages together is a comparison against the four-stage version taught.',
        'source' => 'C6 Living Creatures',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Nature's Treasures (29844)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32782, 'prerequisite' => 32781,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Air is presented as the first of the natural treasures, so the framing category comes first.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32783, 'prerequisite' => 32782,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The spinning firki shows moving air has effect, which extends air from something breathed to something with force.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32784, 'prerequisite' => 32781,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Water is the second treasure surveyed, placed by the same organising idea.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32785, 'prerequisite' => 32784,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Conservation is argued from how little usable water there is, which the counting exercise establishes.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32786, 'prerequisite' => 32785,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Stepwells are presented as historical water conservation, so the conservation case frames them.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32788, 'prerequisite' => 32781,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Forests are the third treasure in the survey, introduced by the same framing.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32789, 'prerequisite' => 32788,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chipko and slow regeneration are arguments about protecting the forest just introduced.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32790, 'prerequisite' => 32781,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Soil is surveyed as another natural treasure within the same scheme.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32791, 'prerequisite' => 32790,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rocks are what soil forms from and what we build with, so soil is the context they are introduced in.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32792, 'prerequisite' => 32791,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Minerals are extracted from rocks, so the rock has to be established as their source.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32793, 'prerequisite' => 32792,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Fossil fuels are surveyed alongside minerals as the other thing taken out of the ground.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32794, 'prerequisite' => 32793,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The renewable split is drawn once both the living and the fossil resources have been surveyed, and fossil fuels are the clearest non-renewable case.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32795, 'prerequisite' => 32782,
        'type' => 'requires', 'gate' => true,
        'reason' => 'City air smelling different is a change in the air whose composition and importance were set out at the start.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32796, 'prerequisite' => 32794,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The need-not-greed argument rests on some resources being non-renewable, which the split establishes.',
        'source' => 'C6 Nature\'s Treasures',
    ],

    // ── Class 6 into Class 8 and 9 ─────────────────────────────────────

    [
        'concept' => 31042, 'prerequisite' => 32781, // What counts as a natural resource (C8) ← Treasures of Nature (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 surveys air, water, forest, soil and minerals as nature\'s treasures; Class 8 opens by calling the same set natural resources and classifying them.',
        'source' => 'C8 Coal and Petroleum ← C6 Nature\'s Treasures',
    ],
    [
        'concept' => 31044, 'prerequisite' => 32794, // Exhaustible resources (C8) ← Renewable or Non-renewable (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The renewable and non-renewable split is made in Class 6; Class 8 renames it inexhaustible and exhaustible and reasons from it about fossil fuels.',
        'source' => 'C8 Coal and Petroleum ← C6 Nature\'s Treasures',
    ],
    [
        'concept' => 7599, 'prerequisite' => 32650,  // Biodiversity Definition (C9) ← Biodiversity and Who Depends on Whom (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Biodiversity and interdependence are introduced in Class 6 through a nature walk; Class 9 formalises the term and never re-establishes the idea.',
        'source' => 'C9 Patterns in Life ← C6 Diversity in the Living World',
    ],
    [
        'concept' => 7605, 'prerequisite' => 32651,  // Purpose of Biological Classification (C9) ← Different Groups, Different Bases (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That a grouping depends on the basis chosen is the Class 6 insight the whole Class 9 classification chapter is built on.',
        'source' => 'C9 Patterns in Life ← C6 Diversity in the Living World',
    ],
    [
        'concept' => 7601, 'prerequisite' => 32659,  // India's Habitat Diversity (C9) ← Why One Region Differs (C6)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Class 6 asks why regions differ and answers with adaptation; Class 9 catalogues India\'s habitats on that basis.',
        'source' => 'C9 Patterns in Life ← C6 Diversity in the Living World',
    ],
    [
        'concept' => 7627, 'prerequisite' => 32653,  // Kingdom Plantae Features (C9) ← Herbs, Shrubs, Trees (C6)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The Class 6 habit-based plant groups are the learner\'s first plant classification, and Class 9 replaces rather than repeats them.',
        'source' => 'C9 Patterns in Life ← C6 Diversity in the Living World',
    ],
    [
        'concept' => 8091, 'prerequisite' => 32670,  // Nitrogen's biological importance (C9) ← Proteins Build and Repair (C6)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nitrogen matters because proteins are built from it. Without knowing what proteins do, the importance of the nitrogen cycle is an unexplained assertion.',
        'source' => 'C9 Earth as a System ← C6 Mindful Eating',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · The Wonderful World of Science (29834)
    // A nature-of-science opener. Sparse by design, like its Class 9 twin.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32641, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Guess-test-guess is presented as what the scientific way of thinking actually looks like in practice.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32642, 'prerequisite' => 32641,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The five steps formalise the informal guess-and-test cycle just described.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32643, 'prerequisite' => 32642,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cook and the mechanic are shown following the same five steps, so the steps have to be named first.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32644, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Curiosity is named as the first of the scientific skills, so the idea of science as thinking frames it.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32645, 'prerequisite' => 32644,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing your own question is curiosity turned into an activity.',
        'source' => 'C6 The Wonderful World of Science',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Exploring Magnets (29837)
    // The root of the magnetism strand, cashed in at Class 10.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32686, 'prerequisite' => 32685,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Magnetic and non-magnetic are read out of the predict-then-test table, so the experiment produces the definitions.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32687, 'prerequisite' => 32686,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Filings crowding at the ends is an observation about magnetic materials, so the magnetic category has to exist first.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32688, 'prerequisite' => 32687,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming the two poles follows from seeing that the effect concentrates at two ends.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32689, 'prerequisite' => 32688,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A compass works because a freely suspended magnet turns north, which is the north-seeking property just established.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32690, 'prerequisite' => 32689,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Building a compass applies the principle the previous concept explains.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32691, 'prerequisite' => 32688,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The attraction and repulsion rule is stated about poles, so the poles have to be identified and labelled.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32692, 'prerequisite' => 32691,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the needle is itself a magnet is deduced from how it responds to another magnet\'s poles.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32693, 'prerequisite' => 32686,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Acting through wood and glass is a further property of magnetic attraction, so attraction has to be established.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32694, 'prerequisite' => 32691,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The toys work by like poles repelling, so the rule is what the activity demonstrates.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32695, 'prerequisite' => 32688,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Marking N and S on a magnet presupposes the poles have been distinguished.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32696, 'prerequisite' => 32691,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Storing magnets with unlike poles facing preserves them, which is the attraction rule applied to care.',
        'source' => 'C6 Exploring Magnets',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Beyond Earth (29845)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32801, 'prerequisite' => 32800,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Navigation by star patterns needs the learner to have looked for and drawn a pattern first.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32802, 'prerequisite' => 32801,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding north is the worked case of navigating by a pattern, so the general use comes first.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32804, 'prerequisite' => 32802,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The five-times rule is the method for locating the Pole Star, which is what finding north depends on.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32805, 'prerequisite' => 32801,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Following Orion to Sirius is a second navigation-by-pattern technique.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32803, 'prerequisite' => 32799,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Light pollution is the contrast to the clear Nubra sky the chapter opens with.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32807, 'prerequisite' => 32806,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the morning star is not a star only lands once the learner knows what a star is — and that the Sun is one.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32808, 'prerequisite' => 32807,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Planets not twinkling is the observational test that distinguishes them from stars, which the morning-star case raises.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32811, 'prerequisite' => 32808,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Assembling the solar system needs planets to have been distinguished from stars first.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32810, 'prerequisite' => 32809,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Missions and asteroids are introduced after the Moon\'s cratered surface has raised the question of what strikes it.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32812, 'prerequisite' => 32811,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Galaxies and exoplanets are the scale beyond the solar system, so the solar system has to be assembled first.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32815, 'prerequisite' => 32803,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Dark sky reserves such as Hanle are the response to the light pollution just described.',
        'source' => 'C6 Beyond Earth',
    ],

    // ── Class 6 magnetism and astronomy into Class 9 and 10 ────────────

    [
        'concept' => 278, 'prerequisite' => 32687,   // Magnetic Field Lines (C10) ← Iron Filings Crowd the Ends (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 shows filings tracing a pattern that crowds at the poles; Class 10 calls that pattern field lines and formalises it. The picture is assumed known.',
        'source' => 'C10 Magnetic Effects ← C6 Exploring Magnets',
    ],
    [
        'concept' => 279, 'prerequisite' => 32691,   // Properties of Field Lines (C10) ← Unlike Poles Attract (C6)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Field lines run from north to south precisely because of the attraction and repulsion rule Class 6 establishes.',
        'source' => 'C10 Magnetic Effects ← C6 Exploring Magnets',
    ],
    [
        'concept' => 284, 'prerequisite' => 32684,   // Electromagnet (C10) ← Lodestones and Artificial Magnets (C6)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'An electromagnet is a magnet made deliberately, which extends the Class 6 distinction between natural lodestones and artificial magnets.',
        'source' => 'C10 Magnetic Effects ← C6 Exploring Magnets',
    ],
    [
        'concept' => 255, 'prerequisite' => 32808,   // Planets Do Not Twinkle (C10) ← Planets Do Not Twinkle (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The same observation, four classes apart: Class 6 uses it to tell a planet from a star, Class 10 explains it by the planet being an extended source. The observation is assumed.',
        'source' => 'C10 The Human Eye ← C6 Beyond Earth',
    ],
    [
        'concept' => 8063, 'prerequisite' => 32806,  // Solar radiation as EM waves (C9) ← The Sun Is the Closest Star (C6)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Treating the Sun as the energy source driving the Earth system assumes it has been established as a star rather than a special object.',
        'source' => 'C9 Earth as a System ← C6 Beyond Earth',
    ],
    [
        'concept' => 7184, 'prerequisite' => 32633,  // Science asks what and how (C9) ← Science as a Way of Thinking (C6)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Class 6 opens with science as a way of thinking; Class 9 opens the secondary course by sharpening the same idea into the kinds of question science asks.',
        'source' => 'C9 Exploration ← C6 The Wonderful World of Science',
    ],
    [
        'concept' => 7185, 'prerequisite' => 32642,  // Careful observation (C9) ← The Five Steps of Finding Out (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The Class 6 five-step method is the learner\'s first account of how investigation proceeds, and Class 9 refines its observation stage without restating the method.',
        'source' => 'C9 Exploration ← C6 The Wonderful World of Science',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Exploring Substances: Acidic, Basic, and Neutral (25914)
    // The root of the acid/base strand that Class 10 formalises with ions.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28029, 'prerequisite' => 28028,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Basic substances are introduced as the opposite of acidic ones, so the acid case is the reference the contrast is drawn against.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28030, 'prerequisite' => 28029,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Neutral means neither acidic nor basic, so both categories have to exist for the third to be defined by exclusion.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28031, 'prerequisite' => 28029,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Lime water is prepared as a working example of a base, so the category frames the preparation.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28032, 'prerequisite' => 28028,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An indicator is useful because it distinguishes acids from bases, so both categories must exist for it to have a job.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28033, 'prerequisite' => 28032,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The rose-extract colours are the readings of the indicator just made, so the indicator comes first.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28034, 'prerequisite' => 28029,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Turmeric turning red is a base test, so the base category has to be available for the result to mean something.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28035, 'prerequisite' => 28034,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The limitation is that turmeric cannot separate acids from neutrals, which only matters once its base test is known.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28036, 'prerequisite' => 28034,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The curry stain turning red with soap is the turmeric base test happening accidentally.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28038, 'prerequisite' => 28037,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Onion strips are the worked example of an olfactory indicator, so the category comes first.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28039, 'prerequisite' => 28030,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Neutralisation is acid and base producing a neutral result, so the neutral category is what the reaction is defined as reaching.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28040, 'prerequisite' => 28039,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Salt and water are the products OF neutralisation, so the reaction has to be established before its products are named.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28041, 'prerequisite' => 28040,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The demonstration shows the products forming, so knowing what to expect is what makes it a demonstration rather than a mess.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28042, 'prerequisite' => 28039,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Treating an ant bite with a base neutralises the acid injected, which is the reaction applied.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28043, 'prerequisite' => 28039,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Liming acidic soil is the same neutralisation at agricultural scale.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28044, 'prerequisite' => 28043,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Treating factory effluent extends the soil-correction case to industry.',
        'source' => 'C7 Exploring Substances',
    ],
    [
        'concept' => 28045, 'prerequisite' => 28043,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Correcting alkaline soil is the mirror-image problem to correcting acidic soil.',
        'source' => 'C7 Exploring Substances',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Electricity: Circuits and their Components (25915)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28047, 'prerequisite' => 28046,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The lamp and switch are parts of the torch the chapter opens by taking apart.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28048, 'prerequisite' => 28047,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why the switch has two positions is a question about the switch just identified.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28051, 'prerequisite' => 28049,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal account of a cell and its terminals follows the cells found inside the torch.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28052, 'prerequisite' => 28047,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The filament is the working part of the lamp, so the lamp has to be identified before it is opened up.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28053, 'prerequisite' => 28051,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That an LED only conducts one way is stated relative to the cell\'s terminals, so the terminals must be distinguishable.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28054, 'prerequisite' => 28048,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Completing or breaking the circuit is the explanation of the two switch positions.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28055, 'prerequisite' => 28054,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Symbols stand for the components — cell, lamp, switch — that have now all been introduced.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28056, 'prerequisite' => 28055,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Why symbols are standardised is a point about the symbols just introduced.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28057, 'prerequisite' => 28055,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A circuit diagram is written in the symbols, so the symbol set is the alphabet the diagram uses.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28058, 'prerequisite' => 28057,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The tester circuit is built from a diagram, so reading and drawing one has to come first.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28059, 'prerequisite' => 28058,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A conductor is defined as a material that makes the tester glow, so the test defines the category.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28060, 'prerequisite' => 28059,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An insulator is what a conductor is not, so the positive case is the reference.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28061, 'prerequisite' => 28060,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Safe handling rests on knowing which materials conduct, so the conductor/insulator split is the basis of every rule.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · The World of Metals and Non-metals (25916)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28063, 'prerequisite' => 28062,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Malleability is the second metallic property surveyed after lustre, and brittleness is the non-metal contrast.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28064, 'prerequisite' => 28063,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ductility is drawing into wire, taught alongside and by contrast with beating into sheets.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28065, 'prerequisite' => 28063,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Sonority continues the survey of physical properties that separate metals from non-metals.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28066, 'prerequisite' => 28062,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Conduction is another member of the same property list begun with lustre.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28067, 'prerequisite' => 28062,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rusting is presented as what happens to a metal, so the metal category has to be established first.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28068, 'prerequisite' => 28067,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The Iron Pillar is remarkable because it has not rusted, which needs rusting to be the expected outcome.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28073, 'prerequisite' => 28067,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Cleaning before testing removes the oxide layer rusting creates, so corrosion has to be known as the reason.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28070, 'prerequisite' => 28069,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Magnesium burning produces the oxide whose basic character is then tested, so the burning comes first.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28070, 'prerequisite' => 28029,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling the oxide basic uses the base category from the previous chapter, which this one does not redefine.',
        'source' => 'C7 The World of Metals and Non-metals ← C7 Exploring Substances',
    ],
    [
        'concept' => 28071, 'prerequisite' => 28069,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Sodium is stored in kerosene because it reacts even faster than magnesium, so the reactivity example frames it.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28074, 'prerequisite' => 28070,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The acidic non-metal oxide is taught as the mirror image of the basic metal oxide, so that case is the reference.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28074, 'prerequisite' => 28028,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling the oxide acidic uses the acid category established in the previous chapter.',
        'source' => 'C7 The World of Metals and Non-metals ← C7 Exploring Substances',
    ],
    [
        'concept' => 28075, 'prerequisite' => 28074,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That sulfur does not react with water is a further property of the non-metal just examined.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28077, 'prerequisite' => 28074,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The definition of a non-metal is assembled from the properties sulfur and phosphorus have just displayed.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28079, 'prerequisite' => 28077,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Oxygen is named as a non-metal essential to life, so the category places it.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28080, 'prerequisite' => 28077,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Carbon is a second biologically essential non-metal in the same survey.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28081, 'prerequisite' => 28077,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Nitrogen completes the set of non-metals introduced through their uses.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Changes Around Us: Physical and Chemical (25917)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28082, 'prerequisite' => 32740,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Melting and freezing are named in Class 6 as changes of state; Class 7 classifies exactly those as physical changes and assumes them known.',
        'source' => 'C7 Changes Around Us ← C6 A Journey through States of Water',
    ],
    [
        'concept' => 28083, 'prerequisite' => 28082,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Folding paper and inflating a balloon are worked examples of the physical change just defined.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28084, 'prerequisite' => 28082,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A chemical change is defined by contrast — a NEW substance forms — so the physical case is the reference.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28085, 'prerequisite' => 28031,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The lime water test needs lime water, whose preparation is taught in the acids and bases chapter.',
        'source' => 'C7 Changes Around Us ← C7 Exploring Substances',
    ],
    [
        'concept' => 28085, 'prerequisite' => 28084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Milky lime water is evidence that a new gas has formed, which is only significant once new substances mark a chemical change.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28086, 'prerequisite' => 28084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An equation records reactants becoming products, so the chemical change is what the notation describes.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28087, 'prerequisite' => 28067,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That rust needs both air and water is taught in the metals chapter; here rusting is re-used as the standard chemical change.',
        'source' => 'C7 Changes Around Us ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28087, 'prerequisite' => 28084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling rust a new substance applies the chemical-change criterion.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28088, 'prerequisite' => 28084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Combustion is presented as a chemical change with heat and light, so the category frames it.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28089, 'prerequisite' => 28088,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ignition temperature is a condition for combustion, so combustion has to be defined before its conditions are stated.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28090, 'prerequisite' => 28084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Noticing both kinds in one process requires both kinds to be separately identifiable.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28091, 'prerequisite' => 28090,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The candle is the worked example of a process containing both a physical and a chemical change.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28092, 'prerequisite' => 28084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Deciding which kind a change is applies the two definitions as a test.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28093, 'prerequisite' => 28082,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reversibility is a second way of sorting the changes already classified as physical or chemical.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28094, 'prerequisite' => 28093,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Irreversible is defined against the reversible case presented first.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28095, 'prerequisite' => 28094,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sorting by reversibility needs both categories available.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28096, 'prerequisite' => 28094,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Undesirable changes are mostly the irreversible ones, so that category frames the judgement.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28097, 'prerequisite' => 28096,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That the same change can be judged differently qualifies the undesirable label just applied.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28098, 'prerequisite' => 28094,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Composting is an irreversible change put to use, so the category places it.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28099, 'prerequisite' => 28094,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Weathering is an irreversible change acting on rock, and its irreversibility is why soil accumulates.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28100, 'prerequisite' => 28099,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Erosion moves the fragments weathering produced, so weathering is the step before.',
        'source' => 'C7 Changes Around Us',
    ],
    [
        'concept' => 28101, 'prerequisite' => 28093,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Water freezing and boiling is the clearest reversible change, used to anchor the category.',
        'source' => 'C7 Changes Around Us',
    ],

    // ── Class 6 into Class 7 ───────────────────────────────────────────

    [
        'concept' => 28062, 'prerequisite' => 32719, // Metallic lustre (C7) ← Lustre, and What Glitters (C6)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 teaches lustre as a sorting property and warns that not all that glitters is gold; Class 7 uses it as the first metal test without re-explaining it.',
        'source' => 'C7 The World of Metals and Non-metals ← C6 Materials Around Us',
    ],
    [
        'concept' => 28066, 'prerequisite' => 32717, // Conduction of heat and electricity (C7) ← Classification Is a Choice of Property (C6)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Conduction joins the Class 6 property list as a further basis for sorting materials, and is introduced in that frame.',
        'source' => 'C7 The World of Metals and Non-metals ← C6 Materials Around Us',
    ],

    // ── Class 7 into Class 8, 9 and 10 ─────────────────────────────────

    [
        'concept' => 31194, 'prerequisite' => 28058, // Testing a liquid (C8) ← Testing materials in a circuit (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 builds the tester and uses it on solids; Class 8 takes the same tester to liquids. The circuit is assumed, only the sample changes.',
        'source' => 'C8 Chemical Effects ← C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 31061, 'prerequisite' => 28088, // Combustion defined (C8) ← Combustible substances (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Combustion enters in Class 7 as a chemical change giving heat and light; Class 8 opens by formalising exactly that and adding the conditions.',
        'source' => 'C8 Combustion and Flame ← C7 Changes Around Us',
    ],
    [
        'concept' => 31063, 'prerequisite' => 28089, // Ignition temperature (C8) ← Ignition temperature (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The same quantity and the same three requirements, introduced in Class 7 and used in Class 8 as the basis of extinguishing.',
        'source' => 'C8 Combustion and Flame ← C7 Changes Around Us',
    ],
    [
        'concept' => 2723, 'prerequisite' => 28084,  // Observations indicating a chemical reaction (C10) ← Chemical change (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The signs that a reaction has occurred are taught in Class 7 as what marks a chemical change; Class 10 lists them in one line and assumes the rest.',
        'source' => 'C10 Chemical Reactions and Equations ← C7 Changes Around Us',
    ],
    [
        'concept' => 47, 'prerequisite' => 28032,    // Acid-Base Indicators (C10) ← Making an indicator from flowers (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 makes an indicator and reads its colours; Class 10 uses litmus and phenolphthalein as already-understood tools.',
        'source' => 'C10 Acids, Bases and Salts ← C7 Exploring Substances',
    ],
    [
        'concept' => 51, 'prerequisite' => 28039,    // Neutralisation Reaction (C10) ← Acid and base cancel each other (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Neutralisation is taught as an observation in Class 7 and explained by ions in Class 10; the observation is the thing being explained.',
        'source' => 'C10 Acids, Bases and Salts ← C7 Exploring Substances',
    ],
    [
        'concept' => 52, 'prerequisite' => 28070,    // Metallic Oxides as Bases (C10) ← Metal oxides are basic (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The same claim, demonstrated in Class 7 with magnesium and restated in Class 10 as a general rule.',
        'source' => 'C10 Acids, Bases and Salts ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 53, 'prerequisite' => 28074,    // Non-metallic Oxides as Acids (C10) ← Burning sulfur (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The sulfur demonstration is Class 7; Class 10 generalises it to all non-metal oxides without repeating the experiment.',
        'source' => 'C10 Acids, Bases and Salts ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 114, 'prerequisite' => 28062,   // Physical Properties of Metals (C10) ← Metallic lustre (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Lustre, malleability, ductility, sonority and conduction are surveyed in Class 7; Class 10 lists them as known and moves to the chemistry.',
        'source' => 'C10 Metals and Non-metals ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 115, 'prerequisite' => 28077,   // Physical Properties of Non-metals (C10) ← Defining a non-metal (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The non-metal category is assembled in Class 7 from sulfur and phosphorus; Class 10 treats it as established.',
        'source' => 'C10 Metals and Non-metals ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 127, 'prerequisite' => 28067,   // Corrosion (C10) ← Both air and water are needed for rust (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That rusting needs both air and moisture is the Class 7 experimental result Class 10 generalises into corrosion.',
        'source' => 'C10 Metals and Non-metals ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 262, 'prerequisite' => 28054,   // Electric Circuit (C10) ← A switch completes or breaks the circuit (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The closed-path idea is established in Class 7 with a torch; Class 10 assumes it and moves straight to current and potential difference.',
        'source' => 'C10 Electricity ← C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 273, 'prerequisite' => 28055,   // Electrical Circuit Symbols (C10) ← Symbols for circuit components (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The symbol set is taught in Class 7 and reused unchanged in Class 10, which adds only the resistor and ammeter.',
        'source' => 'C10 Electricity ← C7 Electricity: Circuits and their Components',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Heat Transfer in Nature (25919)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28123, 'prerequisite' => 28122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A good conductor is one that conducts well, so conduction has to be defined before materials are ranked by it.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28123, 'prerequisite' => 28066,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That metals conduct heat is established in the metals chapter, and this one takes it as the starting list of good conductors.',
        'source' => 'C7 Heat Transfer ← C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28124, 'prerequisite' => 28123,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An insulator is defined against the good conductor, so the positive case has to come first.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28125, 'prerequisite' => 28122,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That solids transfer heat mainly by conduction places the process among the states of matter.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28126, 'prerequisite' => 28122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming three ways presupposes the first has been taught, so conduction anchors the classification.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28127, 'prerequisite' => 28126,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Convection is the second of the three named modes, so the scheme places it.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28128, 'prerequisite' => 28127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The coloured streak makes the convection current visible, so the process has to be named for the experiment to demonstrate it.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28129, 'prerequisite' => 28127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That convection needs a fluid to carry the heat is a condition on the process just defined.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28130, 'prerequisite' => 28127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sea breeze is convection on a coastal scale, so the process is what the phenomenon instantiates.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28131, 'prerequisite' => 28130,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The land breeze is the night-time reversal of the sea breeze, so the daytime case is the reference.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28132, 'prerequisite' => 28129,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Radiation is introduced precisely because convection and conduction both need a medium and sunlight reaches us anyway.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28133, 'prerequisite' => 28132,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That every object radiates generalises the process from the Sun to everything else.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28134, 'prerequisite' => 28124,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Hollow bricks work because trapped air is a poor conductor, which is the insulator idea applied to building.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28135, 'prerequisite' => 28126,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Designing for heat means choosing which of the three modes to encourage or block, so all three have to be known.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28137, 'prerequisite' => 32746,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 traces the water cycle; Class 7 revisits it with precipitation and infiltration named, and does not re-derive the cycle.',
        'source' => 'C7 Heat Transfer ← C6 A Journey through States of Water',
    ],
    [
        'concept' => 28138, 'prerequisite' => 28137,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Infiltration is the stage of the cycle where water enters the ground, so the cycle frames it.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28139, 'prerequisite' => 28138,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Groundwater is what infiltration produces, so the process has to precede the store it fills.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28140, 'prerequisite' => 28139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Conservation is argued from groundwater being slow to replenish, so its limits are the premise.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28141, 'prerequisite' => 28140,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Ice stupas are a conservation technique, so the case for conserving water frames them.',
        'source' => 'C7 Heat Transfer in Nature',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Life Processes in Animals (25921)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28161, 'prerequisite' => 28160,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Digestion is introduced as one of the life processes just enumerated, so the list frames it.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28162, 'prerequisite' => 32675,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saliva acting on starch is shown by the iodine test, which Class 6 teaches. Without that test the change is invisible.',
        'source' => 'C7 Life Processes in Animals ← C6 Mindful Eating',
    ],
    [
        'concept' => 28162, 'prerequisite' => 28161,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saliva breaking starch down is the first worked instance of why food must be broken down.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28163, 'prerequisite' => 28162,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mouth-to-stomach pathway continues from the saliva stage, so that stage comes first.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28164, 'prerequisite' => 28163,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Absorption happens after the food has travelled the canal just traced.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28165, 'prerequisite' => 28164,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The large intestine handles what the small intestine did not absorb, so absorption is the prior step.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28166, 'prerequisite' => 28164,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Ruminant digestion is presented as a variation on the human pathway just described.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28167, 'prerequisite' => 28166,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chewing the cud is the distinctive ruminant behaviour, so ruminants have to be introduced first.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28168, 'prerequisite' => 28167,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why grass needs a second chewing explains the behaviour just described.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28169, 'prerequisite' => 28160,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Respiration is a second life process from the opening list, and the air pathway is how animals carry it out.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28170, 'prerequisite' => 28169,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ribs and diaphragm move air along the pathway just traced, so the pathway comes first.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28171, 'prerequisite' => 28170,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The bell-jar model represents the diaphragm and ribs, so their action has to be understood for the model to model it.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28172, 'prerequisite' => 28169,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing inhaled and exhaled air tests what happened along the pathway.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28173, 'prerequisite' => 28172,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The breathing-versus-respiration distinction is drawn from the gas difference just measured, and it is the single most common confusion in the chapter.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28174, 'prerequisite' => 28160,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Transport is a third life process from the opening list.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28175, 'prerequisite' => 28174,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The heart is the pump of the system just introduced, so the system frames the organ.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28176, 'prerequisite' => 28174,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Waste removal is a job of the transport system, so the system has to exist to carry it.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28177, 'prerequisite' => 28169,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Gills and skin are alternatives to the lung pathway, so the lung case is the reference.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28178, 'prerequisite' => 28175,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Coordination between systems needs at least two systems to have been described, of which circulation is one.',
        'source' => 'C7 Life Processes in Animals',
    ],
    [
        'concept' => 28179, 'prerequisite' => 28172,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That exhaled air still contains oxygen is read straight off the inhaled-versus-exhaled comparison.',
        'source' => 'C7 Life Processes in Animals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Life Processes in Plants (25922)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28181, 'prerequisite' => 28180,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The need for food is inferred from the growth just observed, so the observation motivates the claim.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28182, 'prerequisite' => 28181,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That plants make their own food answers the question of where their food comes from, which the growth need raises.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28183, 'prerequisite' => 28182,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chlorophyll is introduced as what makes the food-making possible, so the process comes first.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28184, 'prerequisite' => 28183,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Other pigments are notable because they mask the chlorophyll that must still be there.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28185, 'prerequisite' => 28182,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The starch test proves food was made in the leaf, so the food-making claim is what it tests.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28186, 'prerequisite' => 28183,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chlorophyll is one of the four requirements, so it has to be introduced before the list is assembled.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28187, 'prerequisite' => 28186,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The products follow the requirements in the same equation, so the inputs come before the outputs.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28188, 'prerequisite' => 28186,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The leaf is called the food factory because it holds all four requirements, so the requirements justify the name.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28189, 'prerequisite' => 28188,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Stomata are pores in the leaf, so the leaf has to be established as the site before its pores are located.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28190, 'prerequisite' => 28189,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gas exchange happens through the stomata, so the pore has to be introduced as the opening.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28191, 'prerequisite' => 28187,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Gas exchange is needed because photosynthesis consumes carbon dioxide and releases oxygen, which the products establish.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28192, 'prerequisite' => 28186,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Water has to reach the leaf because it is one of the requirements, so the requirement motivates the transport question.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28193, 'prerequisite' => 28192,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Xylem is named as the tissue the dye traced, so the experiment comes before the name.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28194, 'prerequisite' => 28193,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Phloem is introduced as the second conducting tissue, defined by contrast with xylem.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28195, 'prerequisite' => 28187,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That plants also respire is surprising only against the photosynthesis products just established.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28196, 'prerequisite' => 28195,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The account of plant respiration follows the claim that plants respire at all.',
        'source' => 'C7 Life Processes in Plants',
    ],
    [
        'concept' => 28197, 'prerequisite' => 28196,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing the two processes needs respiration to have been described as well as photosynthesis.',
        'source' => 'C7 Life Processes in Plants',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Light: Shadows and Reflections (25923)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 28199, 'prerequisite' => 28198,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Other natural sources are surveyed after the Sun has been established as the principal one.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28203, 'prerequisite' => 28198,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rectilinear propagation is a claim about how light from a source travels, so a source has to be in play.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28205, 'prerequisite' => 32721,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Transparent, translucent and opaque are taught as material properties in Class 6; Class 7 uses them to explain shadows without redefining them.',
        'source' => 'C7 Light ← C6 Materials Around Us',
    ],
    [
        'concept' => 28206, 'prerequisite' => 28205,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Translucent is the intermediate case between transparent and opaque, so the clear case is the reference.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28208, 'prerequisite' => 28203,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A shadow forms because light cannot bend around the object, which is rectilinear propagation doing the work.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28208, 'prerequisite' => 28206,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The object has to be opaque, so the transparent/translucent/opaque scheme is what qualifies it.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28209, 'prerequisite' => 28208,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Varying the shadow means varying the three ingredients the previous concept names.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28211, 'prerequisite' => 28209,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Shadow puppetry controls shadow size and sharpness deliberately, which is the previous concept applied.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28210, 'prerequisite' => 28203,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Light being sent back is a change of direction of a straight-line ray, so the straight path is the baseline.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28212, 'prerequisite' => 28210,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A mirror image is what a shiny surface sending light back produces, so the property precedes the phenomenon.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28213, 'prerequisite' => 28212,
        'type' => 'requires', 'gate' => false,
        'reason' => 'How mirrors are made follows from what a mirror has to do optically.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28214, 'prerequisite' => 28203,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The pinhole camera works because rays travel straight through the hole, so rectilinear propagation is the whole explanation.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28215, 'prerequisite' => 28214,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The inverted image is a consequence of the ray paths through the pinhole, so the device has to be understood.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28216, 'prerequisite' => 28212,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A periscope is two mirrors reflecting in turn, so mirror reflection is the component it is built from.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28217, 'prerequisite' => 28216,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Seeing round obstacles is what the periscope is for, so the device precedes its purpose.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],

    // ── Class 7 into Class 8, 9 and 10 ─────────────────────────────────

    [
        'concept' => 31232, 'prerequisite' => 28203, // Light must reach the eye (C8) ← Light travels in a straight line (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 establishes that light travels in straight lines from a source; Class 8 adds that it must reach the eye and builds reflection on both.',
        'source' => 'C8 Light ← C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 31234, 'prerequisite' => 28212, // Reflection makes things visible (C8) ← Reflection in a mirror (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Mirror reflection is taught in Class 7; Class 8 generalises it to every visible object and states the angle law.',
        'source' => 'C8 Light ← C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 8063, 'prerequisite' => 28132,  // Solar radiation as EM waves (C9) ← Radiation needs no medium (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That heat crosses empty space by radiation is the Class 7 result; Class 9 names the carrier as electromagnetic waves and builds the energy budget on it.',
        'source' => 'C9 Earth as a System ← C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 8074, 'prerequisite' => 28130,  // Valley breeze formation (C9) ← The sea breeze by day (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The sea breeze is the worked convection case in Class 7; Class 9 repeats the reasoning for valleys and mountains and assumes the pattern.',
        'source' => 'C9 Earth as a System ← C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 152, 'prerequisite' => 28182,   // Autotrophic Nutrition (C10) ← Plants make their own food (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 establishes that plants make their own food; Class 10 gives that mode a name and contrasts it with heterotrophy.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Plants',
    ],
    [
        'concept' => 153, 'prerequisite' => 28186,   // Photosynthesis Process (C10) ← The requirements (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The four requirements and the products are taught in Class 7; Class 10 writes the equation and assumes the learner knows what goes in and out.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Plants',
    ],
    [
        'concept' => 154, 'prerequisite' => 28189,   // Stomata and Guard Cells (C10) ← Stomata (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Stomata as leaf pores for gas exchange is Class 7 content; Class 10 adds the guard cells and the transpiration role.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Plants',
    ],
    [
        'concept' => 156, 'prerequisite' => 28163,   // Human Digestive System (C10) ← Chewing, tongue, oesophagus (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The alimentary canal is traced in Class 7; Class 10 revisits it to place the enzymes and never re-describes the route.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Animals',
    ],
    [
        'concept' => 157, 'prerequisite' => 28162,   // Enzymes in Digestion (C10) ← Saliva acts on starch (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Salivary action on starch is the Class 7 demonstration that food is chemically broken down; Class 10 names the enzyme and adds the rest.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Animals',
    ],
    [
        'concept' => 159, 'prerequisite' => 28173,   // Respiration Pathways (C10) ← Breathing and respiration are different (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 separates breathing from respiration; Class 10 goes straight to the aerobic and anaerobic pathways and assumes the distinction holds.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Animals',
    ],
    [
        'concept' => 161, 'prerequisite' => 28169,   // Human Respiratory System (C10) ← The pathway of air to the alveoli (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The nostril-to-alveolus route is taught in Class 7; Class 10 uses it to place gas exchange without re-tracing it.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Animals',
    ],
    [
        'concept' => 162, 'prerequisite' => 28174,   // Human Circulatory System (C10) ← What the circulatory system is (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 introduces blood, heart and vessels as a transport system; Class 10 adds double circulation on top of that account.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Animals',
    ],
    [
        'concept' => 166, 'prerequisite' => 28193,   // Transportation in Plants (C10) ← Xylem carries water (C7)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Xylem and phloem are identified by experiment in Class 7; Class 10 explains the driving mechanism and takes the tissues as given.',
        'source' => 'C10 Life Processes ← C7 Life Processes in Plants',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Crop Production and Management (25837)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30433, 'prerequisite' => 30432,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Kharif and rabi are two kinds of crop, so the word crop has to be defined before it is subdivided by season.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30434, 'prerequisite' => 30432,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Preparing the soil is the first of the crop-production practices, so the crop has to be the subject before the practices are listed.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30436, 'prerequisite' => 30434,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That only the topsoil feeds the plant is the reason loosening matters, so the loosening step frames it.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30435, 'prerequisite' => 30434,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Earthworms and microbes are presented as doing naturally what ploughing does mechanically.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30437, 'prerequisite' => 30434,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tilling, crumbling and levelling are the named stages of the loosening just introduced.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30438, 'prerequisite' => 30437,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sowing follows soil preparation in the sequence, so the prepared field is what the seed goes into.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30439, 'prerequisite' => 30438,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The floating test is the method for selecting the good seed the previous step calls for.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30440, 'prerequisite' => 30436,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Exhaustion is the topsoil running out of nutrients, so knowing the topsoil feeds the plant is what makes exhaustion a problem.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30441, 'prerequisite' => 30440,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Manure and fertiliser are the remedy for exhaustion, so the problem has to be stated before the fix.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30442, 'prerequisite' => 28193,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nutrients reach the plant dissolved in water moving through xylem, which Class 7 establishes and this chapter relies on without restating.',
        'source' => 'C8 Crop Production ← C7 Life Processes in Plants',
    ],
    [
        'concept' => 30443, 'prerequisite' => 30442,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Irrigation exists to supply the water that carries nutrients, so the transport role justifies the practice.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30444, 'prerequisite' => 30432,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A weed is defined by growing where the crop is, so the crop has to be identified first.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30445, 'prerequisite' => 30444,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Timing weed removal before seeding is a tactic against the competition just described.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30446, 'prerequisite' => 32750,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Threshing is demonstrated in Class 6 as beating the stalks to free the grain; Class 8 names it as a stage of harvesting.',
        'source' => 'C8 Crop Production ← C6 Methods of Separation',
    ],
    [
        'concept' => 30447, 'prerequisite' => 30446,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drying is what happens to the grain harvesting produced, so harvesting precedes storage.',
        'source' => 'C8 Crop Production and Management',
    ],
    [
        'concept' => 30449, 'prerequisite' => 30448,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Animal husbandry is the management of the animal food source just introduced.',
        'source' => 'C8 Crop Production and Management',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Microorganisms: Friend and Foe (25838)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31024, 'prerequisite' => 31023,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The four groups divide the organisms too small to see, so the category has to be established before it is split.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31025, 'prerequisite' => 31024,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Viruses are set apart from the four groups, so the groups have to exist for something to be apart from them.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31026, 'prerequisite' => 31024,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Whether a microbe is single-celled or not is a property of the groups just named.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31027, 'prerequisite' => 31023,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Their presence everywhere is a claim about the organisms just introduced.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31028, 'prerequisite' => 31027,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Living in other organisms is a particular case of living everywhere, and the one that leads to disease.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31029, 'prerequisite' => 31024,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Fermentation is attributed to specific groups — yeasts and bacteria — so the groups have to be named.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31030, 'prerequisite' => 31024,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Antibiotics come from microbes and act on them, so the organisms have to be classified first.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31031, 'prerequisite' => 31028,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A pathogen is a microbe living in a host and causing harm, so living in other organisms is the precondition.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31032, 'prerequisite' => 31031,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A communicable disease is one a pathogen spreads, so the pathogen has to be defined.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31033, 'prerequisite' => 31032,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A carrier transmits a communicable disease, so the transmission idea comes first.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31034, 'prerequisite' => 31031,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Preservatives work by stopping the microbes that spoil food, so harmful microbes are what they target.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31035, 'prerequisite' => 31034,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Heat and cold are the physical counterparts of the chemical preservatives just described.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31039, 'prerequisite' => 32670,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nitrogen matters because proteins are built from it, which Class 6 establishes. Without that the nitrogen cycle has no purpose.',
        'source' => 'C8 Microorganisms ← C6 Mindful Eating',
    ],
    [
        'concept' => 31036, 'prerequisite' => 31039,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rhizobium matters because it makes atmospheric nitrogen usable, so the need for nitrogen is the reason to care.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31037, 'prerequisite' => 31036,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Lightning is presented as a second route to fixation alongside the bacterial one.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31038, 'prerequisite' => 31036,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Free-living fixers are a third route, contrasted with the root-nodule case.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31040, 'prerequisite' => 31036,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Decomposers return the nitrogen that fixation brought in, so fixation is the step they complete.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],
    [
        'concept' => 31041, 'prerequisite' => 31040,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Closing the cycle means returning nitrogen to the air after decomposition, so decomposition is the prior stage.',
        'source' => 'C8 Microorganisms: Friend and Foe',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Conservation of Plants and Animals (25841)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31081, 'prerequisite' => 31080,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The causes are causes OF deforestation, so the phenomenon has to be defined before they are listed.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31082, 'prerequisite' => 31081,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Natural causes are set alongside the human ones already given.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31083, 'prerequisite' => 28186,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fewer trees means less carbon dioxide taken up, which needs photosynthesis and its requirements to be known from Class 7.',
        'source' => 'C8 Conservation ← C7 Life Processes in Plants',
    ],
    [
        'concept' => 31083, 'prerequisite' => 31080,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The warming effect is a consequence of deforestation, so the cause has to be established.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31084, 'prerequisite' => 28100,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Erosion moving soil fragments is taught in Class 7; Class 8 uses it to explain why cleared land degrades and does not re-derive it.',
        'source' => 'C8 Conservation ← C7 Changes Around Us',
    ],
    [
        'concept' => 31085, 'prerequisite' => 31084,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Floods follow from soil that no longer holds water, which the erosion account supplies.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31087, 'prerequisite' => 31086,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A national park is distinguished from a sanctuary by what it protects and how, so the sanctuary is the reference.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31088, 'prerequisite' => 31087,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A biosphere reserve contains both, so the two smaller categories have to be defined first.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31090, 'prerequisite' => 31089,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Endemic species are a subset of an area\'s flora and fauna, so those terms have to be introduced.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31091, 'prerequisite' => 31090,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What endangers them is a question about the endemic species just defined.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31092, 'prerequisite' => 31091,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Endangered and extinct are the outcome categories of the threats just described.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31093, 'prerequisite' => 31092,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The Red Data Book records which species are endangered, so the category has to exist to be recorded.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31094, 'prerequisite' => 31092,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That small animals are at greater risk is a refinement of the endangered category.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31096, 'prerequisite' => 31095,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Defining a migratory bird follows from having explained why birds migrate.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31098, 'prerequisite' => 31080,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reforestation is the remedy for deforestation, so the problem defines the response.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 31097, 'prerequisite' => 31080,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Saving paper is argued as reducing the demand that drives forest clearance.',
        'source' => 'C8 Conservation of Plants and Animals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Reproduction in Animals (25842)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31100, 'prerequisite' => 31099,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Continuation across generations is the answer to why reproduction matters, so the question frames it.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31101, 'prerequisite' => 31100,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The two modes are two ways of achieving the continuation just established.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31102, 'prerequisite' => 31101,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The male organs belong to the sexual mode, so the modes have to be distinguished before the anatomy is placed.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31103, 'prerequisite' => 31102,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a sperm is one cell is a detail about the gamete the male organs produce.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31104, 'prerequisite' => 31101,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The female organs and ovum are the other half of the sexual mode.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31105, 'prerequisite' => 31103,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fertilisation is sperm and egg fusing, so the sperm has to be introduced as a cell that can fuse.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31105, 'prerequisite' => 31104,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ovum is the other fusing cell, and the event cannot be described with only one of the two.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31106, 'prerequisite' => 31105,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Internal fertilisation is a place where the fusion happens, so the fusion has to be defined.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31107, 'prerequisite' => 31106,
        'type' => 'requires', 'gate' => true,
        'reason' => 'External fertilisation is defined by contrast with the internal case, and the numbers argument depends on that contrast.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31108, 'prerequisite' => 31105,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The zygote is what fertilisation produces, so development starts where fusion ends.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31109, 'prerequisite' => 31108,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The foetus is a later stage of the embryo, so the earlier stage comes first.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31110, 'prerequisite' => 31108,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The hen\'s egg shows the embryo developing outside the body, which needs the embryo to be a known stage.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31111, 'prerequisite' => 31109,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Viviparous animals are those whose foetus develops inside, so the foetal stage has to be defined.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31112, 'prerequisite' => 31111,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Oviparous is defined against viviparous, so the first category is the reference.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31113, 'prerequisite' => 31112,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Telling them apart requires both categories to have been defined.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31114, 'prerequisite' => 31109,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Metamorphosis is a pattern of post-embryonic development, so the developmental sequence has to be established.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31115, 'prerequisite' => 31114,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why humans do not metamorphose is a contrast with the animals that do.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31116, 'prerequisite' => 31101,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Budding and fission are the asexual mode worked out, so the two-mode split has to be in place.',
        'source' => 'C8 Reproduction in Animals',
    ],
    [
        'concept' => 31117, 'prerequisite' => 31116,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Cloning is presented as asexual reproduction performed deliberately in a laboratory.',
        'source' => 'C8 Reproduction in Animals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Reaching the Age of Adolescence (25843)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31119, 'prerequisite' => 31118,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Puberty is the biological process within the period called adolescence, so the period frames it.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31120, 'prerequisite' => 31119,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The growth spurt is one of the changes puberty brings, so puberty has to be defined first.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31121, 'prerequisite' => 31120,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Voice and skin changes continue the list of pubertal changes begun with growth.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31122, 'prerequisite' => 31121,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Secondary sexual characters are the collective name for the changes just enumerated.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31123, 'prerequisite' => 31122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Explaining the word secondary presupposes the characters have been named.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31124, 'prerequisite' => 31122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Hormones are introduced as the cause of the secondary characters, so the effects motivate the cause.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31125, 'prerequisite' => 31124,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An endocrine gland is defined as one that secretes a hormone into the blood, so the hormone comes first.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31126, 'prerequisite' => 31125,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The target is where the secreted hormone acts, so secretion has to be established.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31127, 'prerequisite' => 31126,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testosterone and estrogen are named with their targets, so the gland-to-target scheme has to be available.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31128, 'prerequisite' => 31127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The pituitary is described as controlling the sex hormones, so those hormones have to be introduced first.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31129, 'prerequisite' => 31127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The menstrual cycle is driven by the hormones just named, so they are its mechanism.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31130, 'prerequisite' => 31129,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Menarche and menopause are the start and end of the cycle, so the cycle has to be described.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31131, 'prerequisite' => 31119,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sex chromosomes are introduced within the account of how sex and its development are determined.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31132, 'prerequisite' => 31131,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the father\'s chromosome decides follows from knowing which chromosomes each parent contributes.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31133, 'prerequisite' => 31125,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The other glands are surveyed once the endocrine principle has been established with the sex hormones.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31134, 'prerequisite' => 31114,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saying hormones drive metamorphosis needs metamorphosis to have been described, which the reproduction chapter does.',
        'source' => 'C8 Reaching the Age of Adolescence ← C8 Reproduction in Animals',
    ],
    [
        'concept' => 31135, 'prerequisite' => 31120,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Diet and exercise advice is given for the growth spurt, so the spurt is what it responds to.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],

    // ── Class 8 biology into Class 9 and 10 ────────────────────────────

    [
        'concept' => 7659, 'prerequisite' => 31101, // Asexual reproduction uses one parent (C9) ← Sexual and asexual (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 splits reproduction into the two modes; Class 9 opens with the asexual one already named and goes straight to its methods.',
        'source' => 'C9 Reproduction ← C8 Reproduction in Animals',
    ],
    [
        'concept' => 7670, 'prerequisite' => 31103, // Gamete names (C9) ← A sperm is a single cell (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'That a sperm is a single cell is Class 8; Class 9 uses sperm and ovum as gamete vocabulary without redefining either.',
        'source' => 'C9 Reproduction ← C8 Reproduction in Animals',
    ],
    [
        'concept' => 7686, 'prerequisite' => 31107, // External fertilisation (C9) ← External fertilisation and numbers (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 establishes external fertilisation and why it needs many eggs; Class 9 repeats the comparison as known ground.',
        'source' => 'C9 Reproduction ← C8 Reproduction in Animals',
    ],
    [
        'concept' => 7692, 'prerequisite' => 31102, // Male reproductive organs (C9) ← Male organs and sperms (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The male anatomy is introduced in Class 8 and revisited in Class 9 with the transport route added.',
        'source' => 'C9 Reproduction ← C8 Reproduction in Animals',
    ],
    [
        'concept' => 7694, 'prerequisite' => 31124, // Hormones cause pubertal changes (C9) ← What a hormone is (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The hormone concept is built in Class 8 from the secondary sexual characters; Class 9 uses it as an established mechanism.',
        'source' => 'C9 Reproduction ← C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 7700, 'prerequisite' => 31129, // Menstruation (C9) ← The menstrual cycle (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The cycle and its hormonal control are Class 8; Class 9 connects it to ovulation and fertilisation and assumes the cycle.',
        'source' => 'C9 Reproduction ← C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 8092, 'prerequisite' => 31036, // Nitrogen fixation (C9) ← Rhizobium in root nodules (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Rhizobium fixing nitrogen in root nodules is taught in Class 8; Class 9 places it as one stage of the nitrogen cycle.',
        'source' => 'C9 Earth as a System ← C8 Microorganisms',
    ],
    [
        'concept' => 8095, 'prerequisite' => 31040, // Ammonification (C9) ← Decomposers return nitrogen (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Decomposers returning nitrogen is Class 8; Class 9 names the step ammonification and slots it into the cycle.',
        'source' => 'C9 Earth as a System ← C8 Microorganisms',
    ],
    [
        'concept' => 7602, 'prerequisite' => 31090, // Endemic Species Definition (C9) ← Endemic species (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Endemism is defined in Class 8 within conservation; Class 9 reuses it to define biodiversity hotspots.',
        'source' => 'C9 Patterns in Life ← C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 7656, 'prerequisite' => 31080, // Human Activities Threaten Biodiversity (C9) ← What deforestation is (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Deforestation and its consequences are the Class 8 case study; Class 9 generalises it into the threat to biodiversity.',
        'source' => 'C9 Patterns in Life ← C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 8102, 'prerequisite' => 31080, // Deforestation lowers rainfall (C9) ← What deforestation is (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Class 8 defines deforestation and its effects; Class 9 explains the rainfall effect through the water cycle.',
        'source' => 'C9 Earth as a System ← C8 Conservation of Plants and Animals',
    ],
    [
        'concept' => 184, 'prerequisite' => 31125,  // Endocrine System (C10) ← Endocrine glands are ductless (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Ductless glands secreting into the blood is Class 8 content; Class 10 builds chemical coordination on it without restating it.',
        'source' => 'C10 Control and Coordination ← C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 200, 'prerequisite' => 31119,  // Puberty and Sexual Maturation (C10) ← Puberty (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Puberty and its changes are taught in Class 8; Class 10 refers to sexual maturation as the point reproduction becomes possible.',
        'source' => 'C10 How do Organisms Reproduce ← C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 220, 'prerequisite' => 31131,  // Sex Determination (C10) ← The sex chromosomes (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The X and Y chromosomes are introduced in Class 8; Class 10 works the inheritance out from them and assumes they are known.',
        'source' => 'C10 Heredity ← C8 Reaching the Age of Adolescence',
    ],

    // ══════════════════════════════════════════════════════════════════
    // ACROSS THE DISCIPLINES
    //
    // Science is ONE subject here, and these are the links that only appear
    // when the physics, chemistry and biology chapters are read together.
    // A pass that works through one thread at a time misses every one of
    // them, which is exactly why this section exists as its own step rather
    // than being folded into the chapter batches above.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7317,       // Diffusion along concentration gradient   (C9 Cell — biology)
        'prerequisite' => 7362,  // Concentration of a solution              (C9 Mixtures — chemistry)
        'type' => 'requires', 'gate' => true,
        'reason' => 'A concentration gradient is a difference in concentration between two places. A learner who cannot say what the concentration of a solution is has no quantity to form a gradient from.',
        'source' => 'C9 Cell: The Building Block of Life ← C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7318,       // Osmosis across selectively permeable membrane  (biology)
        'prerequisite' => 7362,  // Concentration of a solution                     (chemistry)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Osmosis is water moving towards the more concentrated solution. The direction of movement is stated entirely in terms of concentration, so without it the rule has no meaning.',
        'source' => 'C9 Cell: The Building Block of Life ← C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7318,       // Osmosis across selectively permeable membrane
        'prerequisite' => 7317,  // Diffusion along concentration gradient
        'type' => 'requires', 'gate' => true,
        'reason' => 'Osmosis is taught as diffusion of water restricted by a selectively permeable membrane, so it is the general process plus one constraint.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7319,       // Isotonic, hypotonic and hypertonic solutions   (biology)
        'prerequisite' => 7362,  // Concentration of a solution                     (chemistry)
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three terms are comparisons of concentration between a cell and its surroundings. Every one of them is unreadable without the quantity being compared.',
        'source' => 'C9 Cell: The Building Block of Life ← C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7319,       // Isotonic, hypotonic and hypertonic solutions
        'prerequisite' => 7318,  // Osmosis across selectively permeable membrane
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three cases are classified by which way osmosis carries water, so the process has to be understood before its outcomes can be named.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 126,        // Electrolytic Refining     (C10 Metals — chemistry)
        'prerequisite' => 261,   // Electric Current          (C10 Electricity — physics)
        'type' => 'requires', 'gate' => true,
        'reason' => 'Refining works by passing a current through a solution so that metal ions deposit on the cathode. A learner with no working idea of current cannot follow why the metal moves at all.',
        'source' => 'C10 Metals and Non-metals ← C10 Electricity',
    ],
    [
        'concept' => 7521,       // Conduction by dissolved ionic compounds  (C9 chemistry)
        'prerequisite' => 7362,  // Concentration of a solution              (C9 chemistry)
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Conductivity is demonstrated by dissolving a salt and testing the solution, so the learner needs solutions and what is dissolved in them to be familiar first.',
        'source' => 'C9 Atomic Foundations of Matter ← C9 Exploring Mixtures and their Separation',
    ],


    // ==================================================================
    // Science classes 6, 8 and 10 - the last unlinked beats
    // ==================================================================

    [
        'concept' => 32634, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why the stars shine is the first question put to show what science as a way of thinking looks like.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32635, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Discoveries from unexpected places is a second illustration of the same habit of asking.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32636, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The puzzle piece in the wrong place shows the same thinking applied to a misfit observation.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32637, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Why Earth alone supports life is another of the opening questions science is shown asking.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32638, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What our food is made of is the same question asked of something entirely ordinary.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32639, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Hot, cold and why it matters extends the opening questions to everyday sensation.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32640, 'prerequisite' => 32633,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Different things, different materials is the last of the opening questions in the set.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32646, 'prerequisite' => 32645,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Calling a wise person a whys person closes the chapter on the learner\'s own why question.',
        'source' => 'C6 The Wonderful World of Science',
    ],
    [
        'concept' => 32648, 'prerequisite' => 32647,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The table records what the undisturbed walk turned up, so the walk has to be taken first.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32658, 'prerequisite' => 32650,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Silent Valley was saved because of what lives there and depends on what, which is biodiversity.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32664, 'prerequisite' => 32663,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What the chapter established is summarised once the protected groves close the argument.',
        'source' => 'C6 Diversity in the Living World',
    ],
    [
        'concept' => 32668, 'prerequisite' => 32667,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Local crops and dishes are read out of the week of meals already tabulated.',
        'source' => 'C6 Mindful Eating: A Path to a Healthy Body',
    ],
    [
        'concept' => 32681, 'prerequisite' => 32680,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The reasoning questions are set once food miles complete the chapter\'s argument.',
        'source' => 'C6 Mindful Eating: A Path to a Healthy Body',
    ],
    [
        'concept' => 32682, 'prerequisite' => 32681,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The take-home task follows the questions that ask for reasons.',
        'source' => 'C6 Mindful Eating: A Path to a Healthy Body',
    ],
    [
        'concept' => 32684, 'prerequisite' => 32683,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Lodestones and artificial magnets answer why Reshma needed a compass at all.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32697, 'prerequisite' => 32696,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The method exercises are set once the chapter has reached storing magnets properly.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32698, 'prerequisite' => 32684,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Hopping frogs and maglev trains are artificial magnets made strong enough to lift, which needs the artificial magnet.',
        'source' => 'C6 Exploring Magnets',
    ],
    [
        'concept' => 32712, 'prerequisite' => 32711,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Measuring what you cannot reach still needs the length matched to a sensible unit.',
        'source' => 'C6 Measurement of Length and Motion',
    ],
    [
        'concept' => 32716, 'prerequisite' => 32715,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Terracotta is offered as an example once it is settled what counts as a material.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32726, 'prerequisite' => 32717,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The twenty gunas are an older classification of materials by chosen properties.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 32727, 'prerequisite' => 32725,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What the chapter grouped is summarised once matter has been defined by mass and volume.',
        'source' => 'C6 Materials Around Us',
    ],
    [
        'concept' => 33271, 'prerequisite' => 33270,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Washing the tip is a rule for using the instrument the failed touch test sends the learner to.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 33274, 'prerequisite' => 33273,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Consent is asked before pointing a non-contact thermometer at a person, so that instrument comes first.',
        'source' => 'C6 Temperature and its Measurement',
    ],
    [
        'concept' => 32734, 'prerequisite' => 32733,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The class argued about where the drops on the outside of the glass came from.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32747, 'prerequisite' => 32746,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The everyday observations are explained once the water cycle is in place.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32748, 'prerequisite' => 32747,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The making and acting tasks follow the explanations they dramatise.',
        'source' => 'C6 A Journey through States of Water',
    ],
    [
        'concept' => 32750, 'prerequisite' => 32749,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Beating the stalks is the second method in the survey handpicking opens.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32760, 'prerequisite' => 32749,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A magnet in the sawdust is handpicking done by a force instead of by fingers.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32764, 'prerequisite' => 32763,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Thinking like a scientist follows choosing an order of steps for yourself.',
        'source' => 'C6 Methods of Separation in Everyday Life',
    ],
    [
        'concept' => 32777, 'prerequisite' => 32776,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The seven-line summary is written once the life stages have been settled.',
        'source' => 'C6 Living Creatures: Exploring their Characteristics',
    ],
    [
        'concept' => 32778, 'prerequisite' => 32777,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The grain and wooden log questions test the summary just given.',
        'source' => 'C6 Living Creatures: Exploring their Characteristics',
    ],
    [
        'concept' => 32779, 'prerequisite' => 32778,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Looking for living things yourself follows the questions that ask you to judge cases.',
        'source' => 'C6 Living Creatures: Exploring their Characteristics',
    ],
    [
        'concept' => 32780, 'prerequisite' => 32774,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking what a world without mosquitoes would be like presupposes the mosquito\'s life cycle.',
        'source' => 'C6 Living Creatures: Exploring their Characteristics',
    ],
    [
        'concept' => 32787, 'prerequisite' => 32781,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sunlight drying the chillies is one of the treasures of nature the chapter sets out to survey.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32797, 'prerequisite' => 32796,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The whole-chapter questions are set once need and greed close the argument.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32798, 'prerequisite' => 32797,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The local investigations follow the questions that need the whole chapter.',
        'source' => 'C6 Nature\'s Treasures',
    ],
    [
        'concept' => 32813, 'prerequisite' => 32811,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The riddles and missing planets are exercises on what makes up the solar system.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32814, 'prerequisite' => 32813,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Drawing and watching the sky follows the riddles as the chapter\'s own observation task.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 32816, 'prerequisite' => 32815,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Saying it is not the end closes the chapter on dark skies and the observatories.',
        'source' => 'C6 Beyond Earth',
    ],
    [
        'concept' => 31056, 'prerequisite' => 31053,
        'type' => 'requires', 'gate' => true,
        'reason' => 'India\'s reserves are reserves of the petroleum and its products just described.',
        'source' => 'C8 Coal and Petroleum',
    ],
    [
        'concept' => 31136, 'prerequisite' => 31135,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Myths, drugs and HIV extend the section on looking after the adolescent body.',
        'source' => 'C8 Reaching the Age of Adolescence',
    ],
    [
        'concept' => 31140, 'prerequisite' => 31139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The man pushing the car is the worked case of a force needing two objects.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31149, 'prerequisite' => 31148,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Muscular force is the first named force, listed once nothing changes without one.',
        'source' => 'C8 Force and Pressure',
    ],
    [
        'concept' => 31185, 'prerequisite' => 31184,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The tin-can model is built to show how the eardrum responds, so the eardrum comes first.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31192, 'prerequisite' => 31191,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Where noise comes from presupposes noise pollution being defined.',
        'source' => 'C8 Sound',
    ],
    [
        'concept' => 31195, 'prerequisite' => 31194,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Checking the tester first is a precaution for the liquid test just set up.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31205, 'prerequisite' => 31204,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Nicholson is credited with the gas bubbles at the electrodes just observed.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31207, 'prerequisite' => 31206,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The overnight potato is a slow demonstration of what the effects depend on.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31209, 'prerequisite' => 31208,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Finding something else while looking for one thing is told of the positive-wire result.',
        'source' => 'C8 Chemical Effects of Electric Current',
    ],
    [
        'concept' => 31215, 'prerequisite' => 31213,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rubbing is done in order to produce, in miniature, the spark lightning makes on a huge scale.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31214, 'prerequisite' => 31213,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The history from the wrath of gods to Franklin is the history of explaining lightning.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31218, 'prerequisite' => 31217,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Handling a charged object safely presupposes both objects carrying charge.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31231, 'prerequisite' => 31230,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What to do when an earthquake strikes follows the section on building for them.',
        'source' => 'C8 Some Natural Phenomena',
    ],
    [
        'concept' => 31250, 'prerequisite' => 31249,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Other aids for the visually impaired are surveyed once the Braille code has been explained.',
        'source' => 'C8 Light',
    ],
    [
        'concept' => 2724, 'prerequisite' => 2723,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A word equation records the reaction the observations have just identified.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2732, 'prerequisite' => 2731,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reaction conditions are written above the arrow alongside the physical state symbols.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],
    [
        'concept' => 2747, 'prerequisite' => 2745,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The damage corrosion causes is the damage rusting does, costed out.',
        'source' => 'C10 Chemical Reactions and Equations',
    ],


    // ==================================================================
    // Science class 7 - the remaining chapters
    // ==================================================================

    [
        'concept' => 28010, 'prerequisite' => 28009,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The deeper questions of the year are put as questions the process of science will answer.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28011, 'prerequisite' => 28010,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Learning beyond the textbook is urged because the year\'s questions run past it.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28012, 'prerequisite' => 28011,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Responsibility follows from science being something the learner does, not only reads.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28013, 'prerequisite' => 28010,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Properties of everyday materials is the first of the year\'s questions previewed.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28014, 'prerequisite' => 28013,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sorting into metals and non-metals is a sorting by the properties just previewed.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28015, 'prerequisite' => 28013,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Reversible and irreversible changes are changes in the materials just previewed.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28016, 'prerequisite' => 28010,
        'type' => 'requires', 'gate' => false,
        'reason' => 'How heat flows is the next of the year\'s questions previewed.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28017, 'prerequisite' => 28016,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The journey of water is previewed as heat flow driving a cycle.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28018, 'prerequisite' => 28010,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Our changing bodies is the biology strand of the year previewed.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28019, 'prerequisite' => 28018,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Life processes for survival extends the preview from growth to the body\'s work.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28020, 'prerequisite' => 28019,
        'type' => 'requires', 'gate' => false,
        'reason' => 'How plants get their food is previewed as a life process of a different kind of organism.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28021, 'prerequisite' => 28010,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Measuring time is the physics strand of the year previewed.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28022, 'prerequisite' => 28021,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Shadows as early clocks previews where the measurement of time began.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28023, 'prerequisite' => 28022,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Light and seeing is previewed through the shadow that a clock is read from.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28024, 'prerequisite' => 28023,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Eclipses are previewed as the largest shadows there are.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28025, 'prerequisite' => 28024,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Rotation and revolution are previewed as what puts the Earth and Moon in line.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28026, 'prerequisite' => 28009,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Questioning the answer is the process of science turned on a statement.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28027, 'prerequisite' => 28026,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking non-obvious questions is what the question-the-answer activity trains.',
        'source' => 'C7 The Ever-Evolving World of Science',
    ],
    [
        'concept' => 28009, 'prerequisite' => 32633,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Science as a way of thinking is Class 6 content; Class 7 restates it as a process to be carried out.',
        'source' => 'C7 The Ever-Evolving World of Science <- C6 The Wonderful World of Science',
    ],
    [
        'concept' => 28014, 'prerequisite' => 32717,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'That classification is a choice of property is Class 6 work; metals and non-metals is one such choice.',
        'source' => 'C7 The Ever-Evolving World of Science <- C6 Materials Around Us',
    ],
    [
        'concept' => 28017, 'prerequisite' => 32746,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The water cycle is established in Class 6; Class 7 previews it as a journey driven by heat.',
        'source' => 'C7 The Ever-Evolving World of Science <- C6 A Journey through States of Water',
    ],
    [
        'concept' => 28025, 'prerequisite' => 32811,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'What makes up the solar system is Class 6 work; Class 7 previews the motions within it.',
        'source' => 'C7 The Ever-Evolving World of Science <- C6 Beyond Earth',
    ],
    [
        'concept' => 28026, 'prerequisite' => 32645,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Choosing your own why question is Class 6 work; Class 7 turns the same habit on a given answer.',
        'source' => 'C7 The Ever-Evolving World of Science <- C6 The Wonderful World of Science',
    ],
    [
        'concept' => 28050, 'prerequisite' => 28049,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The torch and its cells are the first of the everyday uses the chapter then surveys.',
        'source' => 'C7 Electricity: Circuits and their Components',
    ],
    [
        'concept' => 28072, 'prerequisite' => 28071,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sodium kept under kerosene is the case that shows a metal can be soft enough to cut.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28076, 'prerequisite' => 28071,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Phosphorus under water is the non-metal counterpart of sodium under kerosene.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28078, 'prerequisite' => 28074,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Handling burning sulfur safely is a precaution for the reaction just carried out.',
        'source' => 'C7 The World of Metals and Non-metals',
    ],
    [
        'concept' => 28103, 'prerequisite' => 28102,
        'type' => 'requires', 'gate' => true,
        'reason' => 'When adolescence begins and ends places it among the stages of life just listed.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28104, 'prerequisite' => 28103,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The growth spurt is one of the changes of the period just delimited.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28105, 'prerequisite' => 28103,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The change of voice is a second change of the same period.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28106, 'prerequisite' => 28105,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Secondary sexual characteristics is the name for the changes the voice is one of.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28106, 'prerequisite' => 28104,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The growth spurt is counted among the same set of changes.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28107, 'prerequisite' => 28106,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Puberty names the stage at which those characteristics appear.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28108, 'prerequisite' => 28107,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Stronger emotions are attributed to the hormonal change puberty names.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28109, 'prerequisite' => 28108,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Turning mood swings into growth presupposes the swings being expected.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28110, 'prerequisite' => 28104,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A balanced diet is argued from the body growing faster than at any time since infancy.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28111, 'prerequisite' => 28106,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Personal hygiene is a response to the bodily changes just described.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28112, 'prerequisite' => 28111,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Menstrual hygiene is the particular case of the personal hygiene just set out.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28113, 'prerequisite' => 28112,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The schemes exist to make menstrual hygiene possible, so the need comes first.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28114, 'prerequisite' => 28108,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Respectful interaction is asked for because feelings run stronger at this age.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28115, 'prerequisite' => 28114,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Responsible use of online platforms is respectful interaction carried online.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28116, 'prerequisite' => 28115,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Handling cyberbullying presupposes knowing what responsible use looks like.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28117, 'prerequisite' => 28114,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Peer pressure is the pressure that respectful interaction has to survive.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28118, 'prerequisite' => 28117,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tobacco is the first of the substances peer pressure pushes towards.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28119, 'prerequisite' => 28118,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Alcohol and illegal drugs continue the list tobacco opens.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28120, 'prerequisite' => 28119,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Learning to say no is the answer to the substances just named.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28121, 'prerequisite' => 28120,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why adolescence carries this risk explains why saying no is hardest now.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28121, 'prerequisite' => 28108,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The risk is tied to the stronger feelings of the period.',
        'source' => 'C7 Adolescence: A Stage of Growth and Change',
    ],
    [
        'concept' => 28136, 'prerequisite' => 32746,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The water cycle is met in Class 6; Class 7 names precipitation as one of its steps and explains it by heat.',
        'source' => 'C7 Heat Transfer in Nature <- C6 A Journey through States of Water',
    ],
    [
        'concept' => 28137, 'prerequisite' => 28136,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cycle is assembled once precipitation has been named as one of its steps.',
        'source' => 'C7 Heat Transfer in Nature',
    ],
    [
        'concept' => 28143, 'prerequisite' => 28142,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sundial measures time by a repeating event, which is the basis just established.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28144, 'prerequisite' => 28143,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Water clocks follow the sundial as the next early timekeeper.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28151, 'prerequisite' => 28150,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing runners is the worked case of what fast and slow mean.',
        'source' => 'C7 Measurement of Time and Motion',
    ],
    [
        'concept' => 28200, 'prerequisite' => 28199,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Artificial lighting is surveyed once the natural sources have been listed.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28201, 'prerequisite' => 28198,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That light takes time to travel is shown by how long sunlight takes to reach us.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28202, 'prerequisite' => 28199,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Fireflies are a further natural source, of a living kind.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28204, 'prerequisite' => 28203,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saying light can bend around corners is a qualification of travelling in a straight line.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28207, 'prerequisite' => 28206,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Predicting before observing is the habit the transparent and translucent tests train.',
        'source' => 'C7 Light: Shadows and Reflections',
    ],
    [
        'concept' => 28219, 'prerequisite' => 28218,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The Sun rising in the east is the first consequence drawn from the Earth turning.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28220, 'prerequisite' => 28219,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The merry-go-round is offered to explain why the turning looks like the sky moving.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28221, 'prerequisite' => 28218,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Star trails are photographic evidence of the same turning.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28222, 'prerequisite' => 28219,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Aryabhata\'s account is the historical statement of the daily motion just explained.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28223, 'prerequisite' => 28220,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The direction of rotation is fixed by the merry-go-round reasoning.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28224, 'prerequisite' => 28223,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sunrise at different times follows from the Earth turning west to east.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28225, 'prerequisite' => 28224,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Six months of Sun at the poles is the extreme case of sunrise differing by place.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28226, 'prerequisite' => 28218,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Revolution is the second motion, named against the rotation already described.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28227, 'prerequisite' => 28226,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The orbit and the year are the path and period of the revolution just named.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28228, 'prerequisite' => 28227,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The tilt is a property of the axis as the Earth travels the orbit just described.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28229, 'prerequisite' => 28228,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Seasons are explained by the tilt, so the tilt has to be established first.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28229, 'prerequisite' => 28227,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The seasons repeat once per orbit, so the year has to be in place.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28230, 'prerequisite' => 28229,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reversed seasons in the south follow from the explanation just given.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28231, 'prerequisite' => 28229,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Identifying two wrong explanations presupposes the right one.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28232, 'prerequisite' => 28226,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A solar eclipse needs the Moon between Earth and Sun, which is a fact about their motions.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28233, 'prerequisite' => 28232,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why a small Moon covers the Sun answers the objection the solar eclipse raises.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28234, 'prerequisite' => 28232,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The lunar eclipse is the same alignment with the Earth in the middle.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28235, 'prerequisite' => 28234,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The red Moon is explained by what happens during the lunar eclipse just described.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28236, 'prerequisite' => 28232,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Kodaikanal is introduced as where solar events of this kind are studied in India.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 28237, 'prerequisite' => 28236,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Vainu Bappu\'s work continues the Indian astronomy the observatory stands for.',
        'source' => 'C7 Earth, Moon, and the Sun',
    ],
    [
        'concept' => 31135, 'prerequisite' => 28110,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'A balanced diet in adolescence is Class 7 content; Class 8 adds hygiene and exercise to it.',
        'source' => 'C8 Reaching the Age of Adolescence <- C7 Adolescence',
    ],
    [
        'concept' => 31136, 'prerequisite' => 28119,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Alcohol and illegal drugs are covered in Class 7; Class 8 adds the myths and the HIV risk.',
        'source' => 'C8 Reaching the Age of Adolescence <- C7 Adolescence',
    ],


    // ==================================================================
    // Science class 9 - the remaining leaf concepts
    // ==================================================================

    [
        'concept' => 7194, 'prerequisite' => 7193,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That symbols come from history and agreement is said of the standard units just adopted.',
        'source' => 'C9 Exploration: Entering the World of Secondary Science',
    ],
    [
        'concept' => 7311, 'prerequisite' => 7310,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cell was discovered once instruments could reach below what the eye resolves.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7314, 'prerequisite' => 7313,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Judging a microscope by resolution and contrast follows the comparison with the electron microscope.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7315, 'prerequisite' => 7314,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Estimating a cell\'s size depends on knowing what the instrument can resolve.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7339, 'prerequisite' => 7338,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Vacuoles continue the survey of storage structures that the leucoplasts open.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7340, 'prerequisite' => 7339,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cytoskeleton is the support counterpart of the vacuole\'s turgor support.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7349, 'prerequisite' => 7342,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cells grown in culture are cells dividing outside the body, so division has to be understood first.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7350, 'prerequisite' => 7311,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cell theory is the generalisation drawn from the discoveries that named the cell.',
        'source' => 'C9 Cell: The Building Block of Life',
    ],
    [
        'concept' => 7217, 'prerequisite' => 7216,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Animal cells being flexible is stated by contrast with the rigid plant cell wall.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7218, 'prerequisite' => 7217,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Differing by mode of nutrition is a second contrast between plant and animal tissue.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7219, 'prerequisite' => 7218,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Differing transport tissues continues the same comparison.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7237, 'prerequisite' => 7236,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three tissue systems group the conducting tissues xylem and phloem with the rest.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7265, 'prerequisite' => 7264,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The axial skeleton is named once the skull and its fixed joints have been described.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7266, 'prerequisite' => 7265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The vertebral column is the part of the axial skeleton that must bend as well as bear.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7267, 'prerequisite' => 7265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The rib cage is the part of the axial skeleton built around the breathing organs.',
        'source' => 'C9 Tissues in Action',
    ],
    [
        'concept' => 7273, 'prerequisite' => 7271,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling an object at rest is a claim about an interval, not an instant, so rest comes first.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7279, 'prerequisite' => 7275,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The metre is the unit of both quantities, so displacement has to be defined alongside distance.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7281, 'prerequisite' => 7280,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Uniform motion is defined as equal distances in equal times, which average speed measures.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7289, 'prerequisite' => 7287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That acceleration follows any velocity change generalises the case of a direction change alone.',
        'source' => 'C9 Describing Motion Around Us',
    ],
    [
        'concept' => 7357, 'prerequisite' => 7356,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Heterogeneous is defined by contrast with the homogeneous case just given.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7359, 'prerequisite' => 7357,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Telling the two apart by eye presupposes both being defined.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7360, 'prerequisite' => 7359,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The examples are sorted by the observations just described.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7370, 'prerequisite' => 7368,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A crystal forms when a hot saturated solution cools, so the temperature effect comes first.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7376, 'prerequisite' => 7375,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Distilling a dissolved solid off works because the two boiling points differ widely.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7378, 'prerequisite' => 7361,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Chromatography separates solutes carried at different rates by one solvent.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7379, 'prerequisite' => 7378,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing the solvent is a decision about the chromatography just explained.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7380, 'prerequisite' => 7357,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two immiscible liquids are a heterogeneous mixture, which is why they can be run off separately.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7381, 'prerequisite' => 7360,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Mixtures containing a gas extend the list of everyday examples.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7382, 'prerequisite' => 7374,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sublimation is the second state-change separation the chapter surveys, after distillation.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7383, 'prerequisite' => 7382,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Deposition is defined as sublimation run backwards.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7384, 'prerequisite' => 7382,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Separating by sublimation applies the state change just defined.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7388, 'prerequisite' => 7387,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Centrifuging is used where the particles are too fine for filtration to hold them.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7389, 'prerequisite' => 7388,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Coagulation makes particles large enough to settle, where spinning them down is impractical.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7393, 'prerequisite' => 7391,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The dispersed phase is the part of a colloid that does not settle out.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7394, 'prerequisite' => 7393,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The dispersion medium is what the dispersed phase is spread through.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7395, 'prerequisite' => 7394,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An emulsion is the case where both phase and medium are liquids.',
        'source' => 'C9 Exploring Mixtures and their Separation',
    ],
    [
        'concept' => 7399, 'prerequisite' => 7398,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Measuring friction with a spring balance tests how it varies with the surfaces in contact.',
        'source' => 'C9 How Forces Affect Motion',
    ],
    [
        'concept' => 7140, 'prerequisite' => 7139,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saying which force on which object presupposes work being force times displacement.',
        'source' => 'C9 Work, Energy, and Simple Machines',
    ],
    [
        'concept' => 7145, 'prerequisite' => 7144,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That work is scalar although it carries a sign is pressing once negative work is met.',
        'source' => 'C9 Work, Energy, and Simple Machines',
    ],
    [
        'concept' => 7151, 'prerequisite' => 7150,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A collision is the system the work-energy theorem is applied to next.',
        'source' => 'C9 Work, Energy, and Simple Machines',
    ],
    [
        'concept' => 7157, 'prerequisite' => 7156,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Zero kinetic energy at rest is the limiting case of kinetic energy coming from motion.',
        'source' => 'C9 Work, Energy, and Simple Machines',
    ],
    [
        'concept' => 7162, 'prerequisite' => 7161,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That kinetic energy is scalar matters once work has been seen to change it by sign.',
        'source' => 'C9 Work, Energy, and Simple Machines',
    ],
    [
        'concept' => 7163, 'prerequisite' => 7155,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Energy stored by deformation is a second form of mechanical energy alongside motion.',
        'source' => 'C9 Work, Energy, and Simple Machines',
    ],
    [
        'concept' => 7414, 'prerequisite' => 7413,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The Greek atomos is placed beside Kanada\'s parmanu as the same idea reached independently.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7415, 'prerequisite' => 7414,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dalton turns the old philosophical atom into a theory that can be tested.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7421, 'prerequisite' => 7419,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The proton is identified as the positive particle the nucleus is made of.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7425, 'prerequisite' => 7424,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Attributing the mass to protons and neutrons presupposes the neutron being discovered.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7426, 'prerequisite' => 7194,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Latin-derived symbols are the clearest case of symbols arising from history rather than logic.',
        'source' => 'C9 Journey Inside the Atom <- C9 Exploration',
    ],
    [
        'concept' => 7427, 'prerequisite' => 7426,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The IUPAC rules regularise the inherited symbols just described.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7432, 'prerequisite' => 7425,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the electron adds no appreciable mass is the counterpart of the nucleons carrying it all.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7434, 'prerequisite' => 7433,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The two-electron first shell is the 2n squared rule at n equal to one.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7435, 'prerequisite' => 7433,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The octet limit on the outer shell is a restriction on the same filling rule.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7436, 'prerequisite' => 7435,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Filling inwards first presupposes the limit each shell can hold.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7437, 'prerequisite' => 7436,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The K, L, M, N order names the shells the filling works through.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7440, 'prerequisite' => 7439,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Combining capacity is read off the shell distributions of the first eighteen elements.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7445, 'prerequisite' => 7444,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carbon sharing four electrons is the case that does not fit losing or gaining a few.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7446, 'prerequisite' => 7445,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The exceptions are introduced once carbon has shown the simple rule straining.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7448, 'prerequisite' => 7447,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three hydrogens are the worked example of the isotope definition.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7450, 'prerequisite' => 7425,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The unified mass unit is defined against the mass the nucleons carry.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7451, 'prerequisite' => 7448,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The applications use particular isotopes of the kind hydrogen illustrates.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7453, 'prerequisite' => 7452,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the average applies to no single atom is a caution about the average just computed.',
        'source' => 'C9 Journey Inside the Atom',
    ],
    [
        'concept' => 7489, 'prerequisite' => 7488,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The law generalises the conservation already observed in physical changes.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7490, 'prerequisite' => 7489,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A closed system is needed to test the law where a gas is given off.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7492, 'prerequisite' => 7491,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Predicting a product mass extends the balance calculations just carried out.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7494, 'prerequisite' => 7493,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The one-to-eight ratio in water is the worked instance of constant proportions.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7495, 'prerequisite' => 7493,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That mixtures are exempt marks the boundary of the law just stated.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7498, 'prerequisite' => 7497,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Atoms being indivisible is the first postulate about the particles matter is made of.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7499, 'prerequisite' => 7498,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That an element\'s atoms are identical is the next postulate in the same list.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7500, 'prerequisite' => 7499,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Atoms of different elements differing completes the pair of identity postulates.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7502, 'prerequisite' => 7501,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A constant relative number follows from atoms combining in whole-number ratios.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7507, 'prerequisite' => 7506,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Single and double bonds are counts of the shared pairs a covalent bond is made of.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7508, 'prerequisite' => 7507,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Compounds of different atoms are built from the bonds just counted.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7509, 'prerequisite' => 7508,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The naming prefixes count the atoms in the covalent compounds just formed.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7512, 'prerequisite' => 7510,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming an ionic compound names the ions it was formed from.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7515, 'prerequisite' => 7514,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Omitting a subscript of one is a convention of the criss-cross method just used.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7518, 'prerequisite' => 7516,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Simplifying to the lowest ratio finishes the ionic criss-cross.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7520, 'prerequisite' => 7511,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Solubility differences are explained by the lattice an ionic compound forms.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7522, 'prerequisite' => 7521,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That covalent compounds do not conduct is stated against the ionic case that does.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7523, 'prerequisite' => 7511,
        'type' => 'requires', 'gate' => true,
        'reason' => 'High melting points are explained by the lattice the ionic compound forms.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7526, 'prerequisite' => 7525,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using atomic masses in calculations applies the addition just demonstrated.',
        'source' => 'C9 Atomic Foundations of Matter',
    ],
    [
        'concept' => 7553, 'prerequisite' => 7552,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Animal sound production is compared against the human voice just described.',
        'source' => 'C9 Sound Waves: Characteristics and Applications',
    ],
    [
        'concept' => 7585, 'prerequisite' => 7583,
        'type' => 'requires', 'gate' => true,
        'reason' => 'How strongly a surface reflects matters once an echo needs a clear separation to be heard.',
        'source' => 'C9 Sound Waves: Characteristics and Applications',
    ],
    [
        'concept' => 7689, 'prerequisite' => 7688,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Yolk and a larval stage are the other answers to the losses external fertilisation brings.',
        'source' => 'C9 Reproduction: How Life Continues',
    ],
    [
        'concept' => 7607, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Mode of nutrition is one of the criteria that serve the purpose of classifying.',
        'source' => 'C9 Patterns in Life: Diversity and Classification',
    ],
    [
        'concept' => 7608, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Internal structure is a second criterion serving the same purpose.',
        'source' => 'C9 Patterns in Life: Diversity and Classification',
    ],
    [
        'concept' => 7610, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ecological role is a further criterion by which organisms can be grouped.',
        'source' => 'C9 Patterns in Life: Diversity and Classification',
    ],
    [
        'concept' => 7611, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reproduction is another criterion offered for the same purpose.',
        'source' => 'C9 Patterns in Life: Diversity and Classification',
    ],
    [
        'concept' => 7612, 'prerequisite' => 7605,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Genetic similarity is the modern criterion for the same classifying purpose.',
        'source' => 'C9 Patterns in Life: Diversity and Classification',
    ],
    [
        'concept' => 7645, 'prerequisite' => 7644,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That structure reflects change is drawn once the invertebrate phyla have been surveyed.',
        'source' => 'C9 Patterns in Life: Diversity and Classification',
    ],

];
