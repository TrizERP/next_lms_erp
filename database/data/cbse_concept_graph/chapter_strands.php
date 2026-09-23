<?php

/*
|--------------------------------------------------------------------------
| CBSE concept graph — NCERT chapter → strand assignment, classes 6–10
|--------------------------------------------------------------------------
|
| Loaded by `php artisan cbse:graph-assign-strands`.
|
| WHY CHAPTERS AND NOT CONCEPTS
| Science across 6–10 is roughly 1,000 concepts and Mathematics is comparable.
| Hand-assigning each one to a strand is a week of work that nobody would ever
| re-do after the next extraction adds concepts. But an NCERT chapter is
| strand-coherent BY CONSTRUCTION — the book is organised by exactly the thread
| a strand names — and there are only about 160 chapters across both subjects
| and all five classes. Assigning the chapter and letting its concepts inherit
| is therefore not a shortcut; it is the more faithful model, and it survives
| the concept catalogue changing underneath it.
|
| The handful of concepts a chapter places on the wrong strand are corrected
| individually in `concept_strand_overrides.php`, which the assign command
| applies after this file.
|
| MATCHING IS BY NORMALISED NAME, NOT BY NUMBER
| Chapter numbers differ across NCERT editions and across the estates that
| re-ordered them, so nothing here matches on `sort_order`. Names are compared
| lowercased with punctuation stripped and whitespace collapsed, so
| "Light, Shadows and Reflections" and "Light Shadows & Reflections" are the
| same chapter. `aliases` carries the renamings that normalisation cannot
| reach — including the 2024 Curiosity / Ganita Prakash titles, which several
| estates now carry alongside the older ones.
|
| `also` lists secondary strands. A chapter has exactly one primary — the
| thread it is taught on — and may genuinely belong to others: "Some Natural
| Phenomena" is electrostatics first and environment second.
|
| `grade` is advisory. It documents where NCERT places the chapter so a subject
| expert can spot a mis-filed chapter; the command matches on name and records
| the grade the estate actually reports.
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Class 6
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Food: Where Does It Come From', 'strand' => 'SCI-AGR', 'also' => ['SCI-NUT']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Components of Food', 'strand' => 'SCI-NUT', 'also' => ['SCI-HLT']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Fibre to Fabric', 'strand' => 'SCI-AGR'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Sorting Materials into Groups', 'strand' => 'SCI-MAT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Separation of Substances', 'strand' => 'SCI-MAT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Changes Around Us', 'strand' => 'SCI-MAT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Getting to Know Plants', 'strand' => 'SCI-DIV'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Body Movements', 'strand' => 'SCI-DIV'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'The Living Organisms and Their Surroundings', 'strand' => 'SCI-DIV', 'also' => ['SCI-ENV'],
        'aliases' => ['The Living Organisms Characteristics and Habitats', 'Living Creatures Exploring Their Characteristics and Habitats']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Motion and Measurement of Distances', 'strand' => 'SCI-MOT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Light, Shadows and Reflections', 'strand' => 'SCI-LGT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Electricity and Circuits', 'strand' => 'SCI-ELE'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Fun with Magnets', 'strand' => 'SCI-ELE', 'aliases' => ['Magnets', 'Playing with Magnets']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Water', 'strand' => 'SCI-ENV', 'also' => ['SCI-MAT']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Air Around Us', 'strand' => 'SCI-ENV', 'also' => ['SCI-MAT']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Garbage In, Garbage Out', 'strand' => 'SCI-ENV'],
    // 2024 Curiosity additions that have no pre-2024 counterpart.
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'The Wonderful World of Science', 'strand' => 'SCI-MAT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Diversity in the Living World', 'strand' => 'SCI-DIV'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Mindful Eating: A Path to a Healthy Body', 'strand' => 'SCI-NUT', 'also' => ['SCI-HLT']],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Exploring Magnets', 'strand' => 'SCI-ELE'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Measurement of Length and Motion', 'strand' => 'SCI-MOT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Materials Around Us', 'strand' => 'SCI-MAT'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Temperature and its Measurement', 'strand' => 'SCI-HET'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Nature\'s Treasures', 'strand' => 'SCI-ENV'],
    ['grade' => 6, 'subject' => 'Science', 'chapter' => 'Beyond Earth', 'strand' => 'SCI-AST'],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Class 7
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Nutrition in Plants', 'strand' => 'SCI-NUT'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Nutrition in Animals', 'strand' => 'SCI-NUT'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Fibre to Fabric', 'strand' => 'SCI-AGR'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Heat', 'strand' => 'SCI-HET'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Acids, Bases and Salts', 'strand' => 'SCI-RXN'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Physical and Chemical Changes', 'strand' => 'SCI-MAT', 'also' => ['SCI-RXN']],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Weather, Climate and Adaptations of Animals to Climate', 'strand' => 'SCI-ENV', 'also' => ['SCI-DIV']],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Winds, Storms and Cyclones', 'strand' => 'SCI-ENV', 'also' => ['SCI-HET']],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Soil', 'strand' => 'SCI-ENV', 'also' => ['SCI-AGR']],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Respiration in Organisms', 'strand' => 'SCI-NUT'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Transportation in Animals and Plants', 'strand' => 'SCI-NUT'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Reproduction in Plants', 'strand' => 'SCI-REP'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Motion and Time', 'strand' => 'SCI-MOT'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Electric Current and Its Effects', 'strand' => 'SCI-ELE'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Light', 'strand' => 'SCI-LGT'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Water: A Precious Resource', 'strand' => 'SCI-ENV'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Forests: Our Lifeline', 'strand' => 'SCI-ENV'],
    ['grade' => 7, 'subject' => 'Science', 'chapter' => 'Wastewater Story', 'strand' => 'SCI-ENV'],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Class 8
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Crop Production and Management', 'strand' => 'SCI-AGR'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Microorganisms: Friend and Foe', 'strand' => 'SCI-AGR', 'also' => ['SCI-HLT', 'SCI-DIV']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Synthetic Fibres and Plastics', 'strand' => 'SCI-AGR', 'also' => ['SCI-MAT']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Materials: Metals and Non-Metals', 'strand' => 'SCI-RXN', 'also' => ['SCI-MAT']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Coal and Petroleum', 'strand' => 'SCI-RXN', 'also' => ['SCI-ENV']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Combustion and Flame', 'strand' => 'SCI-RXN'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Conservation of Plants and Animals', 'strand' => 'SCI-ENV'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Cell — Structure and Functions', 'strand' => 'SCI-CEL',
        'aliases' => ['Cell Structure and Functions', 'Cell - Structure and Functions']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Reproduction in Animals', 'strand' => 'SCI-REP'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Reaching the Age of Adolescence', 'strand' => 'SCI-REP', 'also' => ['SCI-HLT']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Force and Pressure', 'strand' => 'SCI-MOT', 'also' => ['SCI-GRV']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Friction', 'strand' => 'SCI-MOT'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Sound', 'strand' => 'SCI-SND'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Chemical Effects of Electric Current', 'strand' => 'SCI-ELE', 'also' => ['SCI-RXN']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Some Natural Phenomena', 'strand' => 'SCI-ELE', 'also' => ['SCI-ENV']],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Light', 'strand' => 'SCI-LGT'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Stars and the Solar System', 'strand' => 'SCI-AST'],
    ['grade' => 8, 'subject' => 'Science', 'chapter' => 'Pollution of Air and Water', 'strand' => 'SCI-ENV'],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Class 9
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Matter in Our Surroundings', 'strand' => 'SCI-MAT', 'also' => ['SCI-HET']],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Is Matter Around Us Pure', 'strand' => 'SCI-MAT'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Atoms and Molecules', 'strand' => 'SCI-ATM'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Structure of the Atom', 'strand' => 'SCI-ATM'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'The Fundamental Unit of Life', 'strand' => 'SCI-CEL'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Tissues', 'strand' => 'SCI-CEL'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Diversity in Living Organisms', 'strand' => 'SCI-DIV'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Motion', 'strand' => 'SCI-MOT'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Force and Laws of Motion', 'strand' => 'SCI-MOT'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Gravitation', 'strand' => 'SCI-GRV'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Work and Energy', 'strand' => 'SCI-ENR'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Sound', 'strand' => 'SCI-SND'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Why Do We Fall Ill', 'strand' => 'SCI-HLT'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Natural Resources', 'strand' => 'SCI-ENV'],
    ['grade' => 9, 'subject' => 'Science', 'chapter' => 'Improvement in Food Resources', 'strand' => 'SCI-AGR'],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — Class 10
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Chemical Reactions and Equations', 'strand' => 'SCI-RXN'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Acids, Bases and Salts', 'strand' => 'SCI-RXN'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Metals and Non-metals', 'strand' => 'SCI-RXN'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Carbon and Its Compounds', 'strand' => 'SCI-RXN'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Periodic Classification of Elements', 'strand' => 'SCI-ATM'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Life Processes', 'strand' => 'SCI-NUT', 'also' => ['SCI-CEL']],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Control and Coordination', 'strand' => 'SCI-CTR'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'How Do Organisms Reproduce', 'strand' => 'SCI-REP',
        'aliases' => ['How do Organisms Reproduce?']],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Heredity and Evolution', 'strand' => 'SCI-REP', 'aliases' => ['Heredity']],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Light — Reflection and Refraction', 'strand' => 'SCI-LGT',
        'aliases' => ['Light Reflection and Refraction', 'Light - Reflection and Refraction']],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'The Human Eye and the Colourful World', 'strand' => 'SCI-LGT'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Electricity', 'strand' => 'SCI-ELE'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Magnetic Effects of Electric Current', 'strand' => 'SCI-ELE'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Sources of Energy', 'strand' => 'SCI-ENR', 'also' => ['SCI-ENV']],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Our Environment', 'strand' => 'SCI-ENV'],
    ['grade' => 10, 'subject' => 'Science', 'chapter' => 'Management of Natural Resources', 'strand' => 'SCI-ENV'],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Class 6
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Knowing Our Numbers', 'strand' => 'MAT-NUM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Whole Numbers', 'strand' => 'MAT-NUM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Playing with Numbers', 'strand' => 'MAT-NUM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Basic Geometrical Ideas', 'strand' => 'MAT-GEO'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Understanding Elementary Shapes', 'strand' => 'MAT-GEO'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Integers', 'strand' => 'MAT-NUM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Fractions', 'strand' => 'MAT-NUM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Decimals', 'strand' => 'MAT-NUM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Data Handling', 'strand' => 'MAT-DAT'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Mensuration', 'strand' => 'MAT-MEN'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Algebra', 'strand' => 'MAT-ALG'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Ratio and Proportion', 'strand' => 'MAT-COM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Symmetry', 'strand' => 'MAT-SYM'],
    ['grade' => 6, 'subject' => 'Mathematics', 'chapter' => 'Practical Geometry', 'strand' => 'MAT-GEO'],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Class 7
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Integers', 'strand' => 'MAT-NUM'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Fractions and Decimals', 'strand' => 'MAT-NUM'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Data Handling', 'strand' => 'MAT-DAT'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Simple Equations', 'strand' => 'MAT-ALG'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Lines and Angles', 'strand' => 'MAT-GEO'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'The Triangle and Its Properties', 'strand' => 'MAT-GEO'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Congruence of Triangles', 'strand' => 'MAT-GEO'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Comparing Quantities', 'strand' => 'MAT-COM'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Rational Numbers', 'strand' => 'MAT-NUM'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Practical Geometry', 'strand' => 'MAT-GEO'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Perimeter and Area', 'strand' => 'MAT-MEN'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Algebraic Expressions', 'strand' => 'MAT-ALG'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Exponents and Powers', 'strand' => 'MAT-NUM'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Symmetry', 'strand' => 'MAT-SYM'],
    ['grade' => 7, 'subject' => 'Mathematics', 'chapter' => 'Visualising Solid Shapes', 'strand' => 'MAT-SYM', 'also' => ['MAT-MEN']],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Class 8
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Rational Numbers', 'strand' => 'MAT-NUM'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Linear Equations in One Variable', 'strand' => 'MAT-ALG'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Understanding Quadrilaterals', 'strand' => 'MAT-GEO'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Practical Geometry', 'strand' => 'MAT-GEO'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Data Handling', 'strand' => 'MAT-DAT', 'also' => ['MAT-PRO']],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Squares and Square Roots', 'strand' => 'MAT-NUM'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Cubes and Cube Roots', 'strand' => 'MAT-NUM'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Comparing Quantities', 'strand' => 'MAT-COM'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Algebraic Expressions and Identities', 'strand' => 'MAT-ALG'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Visualising Solid Shapes', 'strand' => 'MAT-SYM', 'also' => ['MAT-MEN']],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Mensuration', 'strand' => 'MAT-MEN'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Exponents and Powers', 'strand' => 'MAT-NUM'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Direct and Inverse Proportions', 'strand' => 'MAT-COM'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Factorisation', 'strand' => 'MAT-ALG'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Introduction to Graphs', 'strand' => 'MAT-COO'],
    ['grade' => 8, 'subject' => 'Mathematics', 'chapter' => 'Playing with Numbers', 'strand' => 'MAT-NUM'],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Class 9
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Number Systems', 'strand' => 'MAT-NUM'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Polynomials', 'strand' => 'MAT-ALG'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Coordinate Geometry', 'strand' => 'MAT-COO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Linear Equations in Two Variables', 'strand' => 'MAT-ALG', 'also' => ['MAT-COO']],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Introduction to Euclid\'s Geometry', 'strand' => 'MAT-GEO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Lines and Angles', 'strand' => 'MAT-GEO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Triangles', 'strand' => 'MAT-GEO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Quadrilaterals', 'strand' => 'MAT-GEO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Areas of Parallelograms and Triangles', 'strand' => 'MAT-MEN', 'also' => ['MAT-GEO']],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Circles', 'strand' => 'MAT-GEO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Constructions', 'strand' => 'MAT-GEO'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Heron\'s Formula', 'strand' => 'MAT-MEN'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Surface Areas and Volumes', 'strand' => 'MAT-MEN'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Statistics', 'strand' => 'MAT-DAT'],
    ['grade' => 9, 'subject' => 'Mathematics', 'chapter' => 'Probability', 'strand' => 'MAT-PRO'],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Class 10
    // ══════════════════════════════════════════════════════════════════

    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Real Numbers', 'strand' => 'MAT-NUM'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Polynomials', 'strand' => 'MAT-ALG'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Pair of Linear Equations in Two Variables', 'strand' => 'MAT-ALG'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Quadratic Equations', 'strand' => 'MAT-ALG'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Arithmetic Progressions', 'strand' => 'MAT-ALG'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Triangles', 'strand' => 'MAT-GEO'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Coordinate Geometry', 'strand' => 'MAT-COO'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Introduction to Trigonometry', 'strand' => 'MAT-TRI'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Some Applications of Trigonometry', 'strand' => 'MAT-TRI'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Circles', 'strand' => 'MAT-GEO'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Constructions', 'strand' => 'MAT-GEO'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Areas Related to Circles', 'strand' => 'MAT-MEN'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Surface Areas and Volumes', 'strand' => 'MAT-MEN'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Statistics', 'strand' => 'MAT-DAT'],
    ['grade' => 10, 'subject' => 'Mathematics', 'chapter' => 'Probability', 'strand' => 'MAT-PRO'],

];
