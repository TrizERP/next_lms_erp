<?php

/*
|--------------------------------------------------------------------------
| Mathematics — concept prerequisites, classes 6–10
|--------------------------------------------------------------------------
|
| Loaded by:  php artisan concept:prereq-import --file=mathematics.php
|
| EVERY ENTRY NAMES A REAL lms_concept.id, read from vivek_erp.
| Direction: 'prerequisite' is learned FIRST, 'concept' needs it.
|
| THIS ESTATE'S BOOKS ARE NOT THE ONES THE OLD SYLLABUS NAMES
| Classes 6, 7 and 9 carry the 2024 NCERT "Ganita Prakash" titles, and the
| mapping from the older chapter names is many-to-many, not one-to-one:
|   - "Knowing Our Numbers" and "Whole Numbers" have no Class 6 chapter; that
|     content is spread across "Patterns in Mathematics" and "Number Play".
|   - "Playing with Numbers" is now "Prime Time".
|   - "Fractions and Decimals" (Class 7) is split three ways: "Working with
|     Fractions", "A Peek Beyond the Point" and "Another Peek Beyond the Point".
|   - "Simple Equations" is now "Finding the Unknown"; "Algebraic Expressions"
|     is "Expressions Using Letter-Numbers".
|   - Ratio and Proportion has NO Class 6 or 7 chapter at all on this estate;
|     the first proportional-reasoning chapter is Class 8 "Comparing Quantities".
| So the chains below follow the books that are actually here, not the ones a
| textbook index would predict. Chapter ids are in the comments for checking.
|
| KNOWN HOLE: Class 9 has only 8 chapters and 115 concepts — the later Ganita
| Prakash Class 9 chapters (circles, surface area and volume, statistics) have
| no chapter row on this estate. Chains running 8 -> 9 -> 10 are broken there
| through no fault of the authoring, and are left unlinked rather than faked.
|
| BATCH 1 — Classes 6 and 7, and the 6 -> 7 joins.
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // C6 · Lines and Angles (29825)
    // The angle ladder. Everything geometric in 7 and beyond stands on it.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 32368, 'prerequisite' => 32367,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A segment is the shortest route between two points, so the point has to be a located thing with no size before a route between two of them is defined.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32369, 'prerequisite' => 32368,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Extending presupposes a segment to extend, so the segment comes first.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32370, 'prerequisite' => 32369,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A ray is a segment extended one way only, so it is defined by contrast with extending both ways.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32371, 'prerequisite' => 32370,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An angle is made by two rays sharing an end point, so the ray is the object the whole definition is built from.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32372, 'prerequisite' => 32371,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vertex and arms name the shared point and the two rays, so the configuration has to exist before its parts are named.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32374, 'prerequisite' => 32373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Superimposing is the method that settles which opening is wider, so the question precedes the technique.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32375, 'prerequisite' => 32374,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That arm length is irrelevant is discovered by superimposing angles with different arms, so the comparison method is what reveals it.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32377, 'prerequisite' => 32375,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Seeing an angle as an amount of turn is exactly what makes arm length irrelevant, so the earlier observation is what motivates the rotation model.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32378, 'prerequisite' => 32377,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A straight angle is half a full turn, so the turn model has to be in place for the fraction to mean anything.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32379, 'prerequisite' => 32378,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A right angle is half a straight angle, so it is defined by halving the case just established.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32380, 'prerequisite' => 32377,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 360 convention assigns a number to one complete turn, which only means something once an angle IS a turn.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32381, 'prerequisite' => 32380,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The protractor scale is a division of the 360 turn, so the degree convention is what the instrument is marked in.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32382, 'prerequisite' => 32381,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drawing a given angle is reading the protractor in reverse, so reading has to be secure first.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32383, 'prerequisite' => 32381,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing between the inner and outer scale is the commonest protractor error, and it is a refinement of reading it.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32384, 'prerequisite' => 32379,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Acute and obtuse are defined as less than and more than a right angle, so the right angle is the reference.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32385, 'prerequisite' => 32384,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A reflex angle is bigger than a straight angle, completing the classification the acute and obtuse cases begin.',
        'source' => 'C6 Lines and Angles',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Prime Time (29828) — factors, primes, factorisation
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 33122, 'prerequisite' => 33121,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A prime is found to be a number with only one rectangular arrangement, so the arrangement activity is what the definition is read off.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33123, 'prerequisite' => 33122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sieve crosses out everything that is not prime, so the learner must be able to say what a prime is before running it.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33124, 'prerequisite' => 33123,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Explaining why only primes survive is explaining the procedure just carried out.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33125, 'prerequisite' => 33122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Co-primality is defined through shared factors, and the factor idea is established in defining a prime.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33127, 'prerequisite' => 33122,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Prime factorisation writes a composite as a product of primes, so the prime has to be identifiable first.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33128, 'prerequisite' => 33127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Uniqueness is a property of the factorisation, so the factorisation has to be produced before it can be shown to be the only one.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33129, 'prerequisite' => 33128,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testing co-primality by comparing factorisations needs the factorisation to be unique, or the comparison proves nothing.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33130, 'prerequisite' => 33128,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Divisibility is read off by comparing how many times each prime appears, which presupposes a settled factorisation.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33132, 'prerequisite' => 33131,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The last-two-digits tests extend the last-digit ones, so the simpler rule is the pattern being generalised.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33138, 'prerequisite' => 33125,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the first common multiple of two co-primes is their product is a statement about co-primality.',
        'source' => 'C6 Prime Time',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Fractions (29830)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 33164, 'prerequisite' => 33163,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming halves and thirds formalises the observation that more sharers give a smaller share.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33166, 'prerequisite' => 33165,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Numerator and denominator name how many parts are taken from how many the strip was folded into, so the folding supplies both numbers.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33167, 'prerequisite' => 33166,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Placing a fraction on the number line needs the two parts of the symbol to mean something.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33169, 'prerequisite' => 33167,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fraction wall lays the number-line lengths side by side, so the line representation comes first.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33171, 'prerequisite' => 33169,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Equivalence is SEEN on the wall as two different fractions occupying the same length, so the wall is the evidence.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33172, 'prerequisite' => 33171,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing by a common unit is rewriting both as equivalent fractions with the same denominator, so equivalence is the tool.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33173, 'prerequisite' => 33171,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Lowest terms is the simplest member of an equivalence family, so the family has to exist first.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33173, 'prerequisite' => 33127,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reducing needs the common factor of numerator and denominator, which prime factorisation in the previous chapter supplies.',
        'source' => 'C6 Fractions ← C6 Prime Time',
    ],
    [
        'concept' => 33174, 'prerequisite' => 33172,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bringing two fractions to one unit is the comparison technique reused for arithmetic.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33175, 'prerequisite' => 33174,
        'type' => 'requires', 'gate' => true,
        'reason' => 'You can only add numerators once both fractions count the same unit, so the common unit is a precondition of the rule.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33178, 'prerequisite' => 33175,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why the denominator does not add is the explanation of the addition rule, and is the single most common fraction error.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33177, 'prerequisite' => 33175,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Subtraction with a common unit is the addition rule run backwards, so addition is taught first.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33176, 'prerequisite' => 33173,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing both parts is the procedure that produces lowest terms, so the target form is defined first.',
        'source' => 'C6 Fractions',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · Perimeter and Area (29829)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 33142, 'prerequisite' => 33141,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Measuring tape around a frame is the perimeter idea applied, so the idea precedes the measurement.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33143, 'prerequisite' => 33141,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding three sides is the perimeter of a triangle, computed from the same definition.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33148, 'prerequisite' => 33147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting grid squares is what area means before any formula, and the chapter insists on it as the foundation.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33150, 'prerequisite' => 33147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Overlaying to show equal areas compares two grid counts, so the grid method has to be available.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33151, 'prerequisite' => 33147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why squares tile without gaps is the justification for using a square grid to measure area at all.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33152, 'prerequisite' => 33148,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a diagonal halves a rectangle is established by counting the squares on each side of it.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33153, 'prerequisite' => 33147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rearranging nine squares to change the perimeter needs area as a count that stays fixed while perimeter varies.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33153, 'prerequisite' => 33141,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The perimeter is the quantity being varied, so it must be computable for the variation to be observed.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33154, 'prerequisite' => 33153,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tracking whether the perimeter rises or falls generalises the nine-square experiment.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33156, 'prerequisite' => 33153,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Separating what area measures from what perimeter measures is the lesson the same-area-different-perimeter case teaches.',
        'source' => 'C6 Perimeter and Area',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C6 · The Other Side of Zero (29833) — integers
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 33252, 'prerequisite' => 33251,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Floors below ground are introduced precisely because the number ray stops at zero, so the limitation motivates the extension.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33254, 'prerequisite' => 33252,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing floors is ordering the negative positions the building model has just introduced.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33253, 'prerequisite' => 33252,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The undo button is movement between the floors, so the floors must be numbered first.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33255, 'prerequisite' => 33253,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Starting level plus movement is the addition model, built directly on the journey idea.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33256, 'prerequisite' => 33254,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming the integers formalises the ordered positive and negative positions the floor model supplies.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33257, 'prerequisite' => 33256,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sketching a sum without marked numbers requires the integers to be a known ordered set.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33258, 'prerequisite' => 33257,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rewriting subtraction as addition of the opposite needs the addition model to be secure first.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33259, 'prerequisite' => 33258,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Zero pairs are the device that makes the rewritten subtraction performable, so the rewriting comes first.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33260, 'prerequisite' => 33256,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Credits and debits are a second interpretation of the same signed numbers.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33263, 'prerequisite' => 33259,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sign rules are generalised from the zero-pair work, so that concrete method precedes the rules.',
        'source' => 'C6 The Other Side of Zero',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Arithmetic Expressions (25941)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30308, 'prerequisite' => 30307,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That several expressions share one value is a statement about expressions, so the object has to be defined.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30310, 'prerequisite' => 30307,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing two expressions requires each to have a value, which the definition supplies.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30313, 'prerequisite' => 30312,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The need for an agreed order is argued from two readers getting different answers, so the disagreement is the motivation.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30314, 'prerequisite' => 30313,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Brackets are the notation that fixes the order, so the need for an order comes first.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30315, 'prerequisite' => 30314,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Step-by-step evaluation follows the order the brackets impose.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30316, 'prerequisite' => 30315,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Breaking into terms is the systematic version of evaluating step by step, and is what the rest of the chapter uses.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30317, 'prerequisite' => 30316,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing subtraction as adding a negative term is what makes every expression a sum of terms.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30318, 'prerequisite' => 30316,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That terms settle the order is the point of having identified them.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30319, 'prerequisite' => 30318,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Swapping is legitimate because terms are independent units, which the previous concept establishes.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30320, 'prerequisite' => 30319,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Regrouping extends swapping from pairs to any arrangement of terms.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30321, 'prerequisite' => 30320,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Adding faster is the practical payoff of being allowed to swap and regroup.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30322, 'prerequisite' => 30317,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A bracket after a minus flips every sign inside, which is only explicable once subtraction has been recast as adding negatives.',
        'source' => 'C7 Arithmetic Expressions',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · A Peek Beyond the Point (25942) — decimals
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30330, 'prerequisite' => 30329,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting the unit is the response to whole units being too coarse, so the problem motivates the move.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30331, 'prerequisite' => 30330,
        'type' => 'requires', 'gate' => true,
        'reason' => 'One tenth is the particular split into ten equal parts, so the general idea of splitting comes first.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30331, 'prerequisite' => 33170,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 extends the fraction wall to tenths; Class 7 gives that same tenth a decimal notation and does not re-derive the quantity.',
        'source' => 'C7 A Peek Beyond the Point ← C6 Fractions',
    ],
    [
        'concept' => 30333, 'prerequisite' => 30331,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A hundredth is a tenth of a tenth, so the first split has to be understood before it is repeated.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30335, 'prerequisite' => 30333,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The places to the right of the point are tenths, hundredths and so on, so those units must exist before the columns are named.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30336, 'prerequisite' => 30335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a decimal is reading its place columns, so the columns have to be established.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30339, 'prerequisite' => 30336,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Placing a decimal on the number line needs its value, which comes from reading its places.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30342, 'prerequisite' => 30341,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The trailing-zero rule resolves the zero dilemma the chapter has just raised.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30343, 'prerequisite' => 30342,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing two decimals of different lengths depends on knowing trailing zeros change nothing.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30345, 'prerequisite' => 30336,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding by place value means adding tenths to tenths and hundredths to hundredths, so the place reading is the method.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30346, 'prerequisite' => 30345,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The column algorithm is the compact form of the place-value addition just performed longhand.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30347, 'prerequisite' => 30346,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Subtraction uses the same column alignment, so the addition algorithm is the pattern being reused.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30348, 'prerequisite' => 30342,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding decimals of different lengths works by padding with trailing zeros, which is exactly the rule established earlier.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30337, 'prerequisite' => 30333,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting metres to centimetres is multiplying by a hundred, so the hundredth has to be a familiar unit.',
        'source' => 'C7 A Peek Beyond the Point',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Expressions Using Letter-Numbers (25943) — first algebra
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30353, 'prerequisite' => 30307,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A letter-number expression is an arithmetic expression with a letter in place of a number, so the arithmetic version is what is being generalised.',
        'source' => 'C7 Expressions Using Letter-Numbers ← C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30355, 'prerequisite' => 30353,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An expression takes a value once the letter is given one, so the letter-stands-for-a-number idea is the precondition.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30356, 'prerequisite' => 30355,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Substituting is the procedure that produces the value, so the idea that there is a value comes first.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30357, 'prerequisite' => 30316,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Terms in an algebraic expression are the same terms from arithmetic, so the arithmetic decomposition transfers directly.',
        'source' => 'C7 Expressions Using Letter-Numbers ← C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30359, 'prerequisite' => 30319,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Swapping and grouping still holding is a claim that the arithmetic rules carry over, so those rules have to be known.',
        'source' => 'C7 Expressions Using Letter-Numbers ← C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30360, 'prerequisite' => 30353,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 4n shorthand omits the multiplication sign between a number and a letter, so the letter convention has to exist.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30361, 'prerequisite' => 30360,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The nth term is written in the shorthand just introduced, so the notation is the language the rule is stated in.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30364, 'prerequisite' => 30360,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing a perimeter as 4s uses the same shorthand, applied to a geometric quantity.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30366, 'prerequisite' => 30357,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding like terms requires the expression to have been split into terms in the first place.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30367, 'prerequisite' => 30366,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Simplest form is what collecting like terms produces, so the operation defines the target.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30365, 'prerequisite' => 30322,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Removing brackets in algebra uses the sign rule established for arithmetic brackets after a minus.',
        'source' => 'C7 Expressions Using Letter-Numbers ← C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30371, 'prerequisite' => 30370,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Checking a formula on every input presupposes a formula has been written.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30373, 'prerequisite' => 30372,
        'type' => 'requires', 'gate' => true,
        'reason' => 'You cannot write a pattern as an expression until the pattern itself has been found.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30373, 'prerequisite' => 30361,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The expression produced is an nth-term rule, so that form is what the pattern is written into.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30374, 'prerequisite' => 30373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Predicting a distant stage is substituting a large n into the rule just written.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Parallel and Intersecting Lines (25944)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30378, 'prerequisite' => 30377,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The four angles exist at the crossing point, so the crossing has to be established first.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30379, 'prerequisite' => 30378,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A linear pair is two of the four angles that share an arm, so the four have to be identified.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30380, 'prerequisite' => 30379,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Vertically opposite angles are shown equal by subtracting a shared linear pair, so that result is the proof step.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30382, 'prerequisite' => 30380,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Four equal angles forcing right angles follows from the vertical and linear-pair relations together.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30383, 'prerequisite' => 30382,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Perpendicularity is named once the four-right-angle case has been produced.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30385, 'prerequisite' => 30377,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Parallel is defined by contrast — lines that do NOT meet — so the intersecting case is the reference.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30388, 'prerequisite' => 30385,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A transversal is a third line cutting two others, and the whole point is what happens when those two are parallel.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30389, 'prerequisite' => 30388,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The eight angles are formed at the two crossings the transversal makes, so the transversal has to be drawn.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30390, 'prerequisite' => 30389,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Corresponding angles are picked out from the eight by their matching positions, so the eight must be identified.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30391, 'prerequisite' => 30390,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The parallelism test is stated about corresponding angles, so they have to be locatable for the test to be applied.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30392, 'prerequisite' => 30391,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The set-square construction works by copying a corresponding angle, so the test is what justifies the method.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30394, 'prerequisite' => 30390,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An alternate angle is located from a corresponding one by a vertical step, so corresponding angles come first.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30395, 'prerequisite' => 30394,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That alternate angles are equal follows once the angle has been located and the corresponding result applied.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · A Tale of Three Intersecting Lines (25946) — triangles
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30417, 'prerequisite' => 30416,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Arcs are introduced because the ruler alone leaves the third vertex to guesswork, so the failure motivates the method.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30418, 'prerequisite' => 30417,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The base-then-vertex procedure is the arc method organised into steps.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30420, 'prerequisite' => 30418,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The triangle inequality is discovered when the two arcs fail to meet, so the construction is the evidence for it.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30421, 'prerequisite' => 30420,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testing a set of lengths applies the inequality, so the rule has to be stated first.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30423, 'prerequisite' => 32382,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Building a given angle at a vertex is the Class 6 protractor construction, reused here as a step in a larger one.',
        'source' => 'C7 A Tale of Three Intersecting Lines ← C6 Lines and Angles',
    ],
    [
        'concept' => 30424, 'prerequisite' => 30423,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The second side is measured along the arm the angle construction produced, so that arm has to exist.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30425, 'prerequisite' => 30383,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An altitude is a perpendicular from a vertex to the opposite side, so perpendicularity is a term in its definition.',
        'source' => 'C7 A Tale of Three Intersecting Lines ← C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30426, 'prerequisite' => 30425,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That there are three altitudes counts the vertices, which requires the altitude to be defined for one.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30427, 'prerequisite' => 30426,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Extending the base is the special case that arises when drawing one of the three altitudes on an obtuse triangle.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30429, 'prerequisite' => 30428,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Classifying by angle is presented as a second, independent scheme alongside classifying by side.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30430, 'prerequisite' => 30429,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That all three angles must be acute is a detail of the angle classification just introduced.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30431, 'prerequisite' => 30430,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using both classifications at once needs each to be available separately first.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Geometric Twins (25948) — congruence
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30465, 'prerequisite' => 30464,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding the included angle is the fix for two lengths being insufficient, so the insufficiency is the motivation.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30466, 'prerequisite' => 30465,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Congruence names the relation between figures that the determining conditions produce.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30467, 'prerequisite' => 30466,
        'type' => 'requires', 'gate' => true,
        'reason' => 'SSS is a criterion FOR congruence, so the relation has to be defined before criteria for it are given.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30470, 'prerequisite' => 30466,
        'type' => 'requires', 'gate' => true,
        'reason' => 'SAS is a second congruence criterion within the same scheme.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30472, 'prerequisite' => 30470,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the angle must be included is the condition that makes SAS work and SSA fail, so SAS has to be stated first.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30474, 'prerequisite' => 30466,
        'type' => 'requires', 'gate' => true,
        'reason' => 'ASA is the third criterion, classified under the same congruence relation.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30477, 'prerequisite' => 30476,
        'type' => 'requires', 'gate' => true,
        'reason' => 'RHS is stated in terms of the hypotenuse, so that side has to be named before the criterion uses it.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30478, 'prerequisite' => 30467,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The collected list of sufficient conditions requires each condition to have been established individually.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30478, 'prerequisite' => 30474,
        'type' => 'requires', 'gate' => true,
        'reason' => 'ASA is one of the conditions being collected, so it must precede the summary.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30479, 'prerequisite' => 30478,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using congruence to prove something requires the full set of criteria to choose from.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30480, 'prerequisite' => 30479,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The isosceles base-angle result is the first theorem proved by congruence, so the proving technique comes first.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30481, 'prerequisite' => 30480,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The equilateral 60-degree result follows by applying the isosceles result three times.',
        'source' => 'C7 Geometric Twins',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Working with Fractions (25947)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30451, 'prerequisite' => 30450,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Taking a fraction OF a whole number reverses the previous product, so that product has to be secure.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30453, 'prerequisite' => 30451,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The area model generalises taking a fraction of a quantity to taking a fraction of a fraction.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30454, 'prerequisite' => 30453,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The multiply-across rule is read off the area picture, so the picture is the justification.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30456, 'prerequisite' => 30454,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Restating division as multiplication needs the multiplication rule to restate it into.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30457, 'prerequisite' => 30456,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The reciprocal is defined as what a fraction multiplies by to give one, so multiplication has to be available.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30458, 'prerequisite' => 30457,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing by multiplying by the reciprocal is meaningless without the reciprocal.',
        'source' => 'C7 Working with Fractions',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Operations with Integers (25949)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30486, 'prerequisite' => 33263,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The token model extends Class 6 signed addition into multiplication, and assumes the addition sign rules already work.',
        'source' => 'C7 Operations with Integers ← C6 The Other Side of Zero',
    ],
    [
        'concept' => 30487, 'prerequisite' => 30486,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A positive times a negative is worked out by adding negative tokens repeatedly, so the token model is the method.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30488, 'prerequisite' => 30487,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A negative multiplier means removing rather than adding, defined by contrast with the positive case.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30489, 'prerequisite' => 30488,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Zero pairs are what make removal from an empty bag possible, so the removal idea has to be raised first.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30491, 'prerequisite' => 30490,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fortune-and-debt naming is the vocabulary the like-signs rule is stated in.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30492, 'prerequisite' => 30491,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The unlike-signs rule is the complement of the like-signs one and is taught immediately after it.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30494, 'prerequisite' => 30492,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Division signs are deduced from the multiplication rules, so both multiplication cases have to be settled.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30495, 'prerequisite' => 30494,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a quotient off a known product is the method the restatement licenses.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30496, 'prerequisite' => 30495,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The summary of division sign rules generalises the worked cases that precede it.',
        'source' => 'C7 Operations with Integers',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Finding Common Ground (25950) — HCF and LCM
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30499, 'prerequisite' => 30498,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Common factors are named once the tiling problem has shown that one length must divide both dimensions.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30501, 'prerequisite' => 30499,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The HCF is the largest of the common factors, so the set has to exist before its maximum is taken.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30503, 'prerequisite' => 30502,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Uniqueness of prime factorisation is a statement about primes, so the prime has to be defined.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30504, 'prerequisite' => 30503,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The division method produces the factorisation whose uniqueness has just been argued.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30505, 'prerequisite' => 30503,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That every factor is built from the prime factorisation needs the factorisation to be fixed.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30507, 'prerequisite' => 30506,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A common multiple serves both lengths, so each length being a multiple of its strip has to be established.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30508, 'prerequisite' => 30507,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The LCM is the smallest of the common multiples, so the set comes before its minimum.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30513, 'prerequisite' => 30508,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The bound is a claim about the LCM, so the quantity must exist to be bounded.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30514, 'prerequisite' => 30513,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the LCM divides the product sharpens the inequality just stated.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30515, 'prerequisite' => 30514,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Identifying the leftover multiplier as the HCF depends on the LCM being a factor of the product.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30515, 'prerequisite' => 30501,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The quantity being identified IS the HCF, so it has to be defined for the identification to mean anything.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30516, 'prerequisite' => 30515,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding one of HCF, LCM and product from the other two is the relation just established, solved for a different term.',
        'source' => 'C7 Finding Common Ground',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Finding the Unknown (25954) — equations
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30572, 'prerequisite' => 30569,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Trial and error is applied to the balance equation, so the equation model has to be set up first.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30573, 'prerequisite' => 30572,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The dissatisfaction is with trial and error specifically, so the method has to be tried before it is criticised.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30574, 'prerequisite' => 30573,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Doing the same thing to both sides is introduced as the systematic replacement for guessing, so the failure motivates it.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30575, 'prerequisite' => 30355,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An equation is two expressions set equal, so the learner must already know what an expression with a letter is.',
        'source' => 'C7 Finding the Unknown ← C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30575, 'prerequisite' => 30574,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Turning an expression into an equation is only useful once there is a method for solving one.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30576, 'prerequisite' => 30575,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Translating a word problem produces the equation form just introduced.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30577, 'prerequisite' => 30574,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Removing the constant first is a tactic within the same-operation-both-sides method.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30579, 'prerequisite' => 30574,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding where a solution went wrong means checking each balancing step, so the method has to be known to be audited.',
        'source' => 'C7 Finding the Unknown',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C7 · Connecting the Dots (25952) — statistics
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 30538, 'prerequisite' => 30537,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A statistical question is one a statistical statement answers, so the statement form comes first.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30539, 'prerequisite' => 30538,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Summarising many values into one is the response to a statistical question, so the question frames the need.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30541, 'prerequisite' => 30539,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mean is the first such single number, so the idea of one number standing for many has to exist.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30542, 'prerequisite' => 30541,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fair-share reading interprets the formula, so the formula comes first.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30543, 'prerequisite' => 30542,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That redistribution leaves the total unchanged is the justification of the fair-share interpretation.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30545, 'prerequisite' => 30541,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mean can only misrepresent once the learner knows what it claims to represent.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30546, 'prerequisite' => 30545,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A single extreme value dragging the mean is the mechanism behind the misrepresentation just noted.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30547, 'prerequisite' => 30546,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The word outlier names the dragging value, so the effect is described before it is labelled.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30548, 'prerequisite' => 30547,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The median is introduced because it resists outliers, so the outlier problem is its entire justification.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30550, 'prerequisite' => 30549,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Plotting two series on one graph extends the single-series graph just argued for.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30552, 'prerequisite' => 30551,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a fair-looking display can still mislead is a caution about the good presentation just described.',
        'source' => 'C7 Connecting the Dots',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Rational Numbers (25850)
    // The number system widened one step at a time, then its properties.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31252, 'prerequisite' => 31251,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Zero and the whole numbers are introduced because the naturals cannot express nothing, so the shortfall motivates the extension.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31253, 'prerequisite' => 31252,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Integers extend the whole numbers to the left of zero, so the whole numbers are the set being extended.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31253, 'prerequisite' => 33256,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 names the integers through floors below ground level; Class 8 places them in a formal chain of number systems and assumes them known.',
        'source' => 'C8 Rational Numbers ← C6 The Other Side of Zero',
    ],
    [
        'concept' => 31254, 'prerequisite' => 31253,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rationals extend the integers to allow division, so the integers are the previous stage of the same sequence.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31254, 'prerequisite' => 33166,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Numerator and denominator are established in Class 6; the rational number is that same p-over-q form with the parts allowed to be negative.',
        'source' => 'C8 Rational Numbers ← C6 Fractions',
    ],
    [
        'concept' => 31256, 'prerequisite' => 31254,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal definition follows the informal introduction, and fixes the p-over-q form with q not zero.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31257, 'prerequisite' => 31256,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Comparing the systems presupposes the newest one has been defined.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31258, 'prerequisite' => 31256,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Closure is a property of the set, so the set has to be defined before it can be shown closed.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31259, 'prerequisite' => 31258,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Extending closure to two more operations repeats the argument made for addition.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31260, 'prerequisite' => 31259,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Division failing is notable only against the three operations that succeeded.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31261, 'prerequisite' => 31256,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Commutativity is a property of the rationals, so the set has to exist for the property to be tested on it.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31261, 'prerequisite' => 30319,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 establishes that terms in a sum can be swapped; Class 8 names that commutativity and asks which operations have it.',
        'source' => 'C8 Rational Numbers ← C7 Arithmetic Expressions',
    ],
    [
        'concept' => 31262, 'prerequisite' => 31261,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That subtraction is not commutative is a counterexample to the property just established for addition.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31263, 'prerequisite' => 31262,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Explaining why the failure carries over presupposes the failure has been demonstrated.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31264, 'prerequisite' => 31262,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Testing each operation is the systematic version of the addition-succeeds, subtraction-fails pair.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31265, 'prerequisite' => 31261,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Associativity is introduced as a second property alongside commutativity, and tested the same way.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31266, 'prerequisite' => 31265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The subtraction counterexample mirrors the one already given for commutativity.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31267, 'prerequisite' => 31265,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplication being associative is the next case in the same systematic survey.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31268, 'prerequisite' => 31267,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Division failing completes the associativity survey by contrast with multiplication.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31269, 'prerequisite' => 31256,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An identity is defined relative to an operation on the set, so the set has to be fixed.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31270, 'prerequisite' => 31269,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The multiplicative identity is introduced by analogy with the additive one.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31271, 'prerequisite' => 31267,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The distributive rule links multiplication to addition, so both operations must have been surveyed.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31272, 'prerequisite' => 31271,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using distribution to simplify applies the rule just stated.',
        'source' => 'C8 Rational Numbers',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Linear Equations in One Variable (25851)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31273, 'prerequisite' => 30575,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 turns an expression into an equation; Class 8 opens by sharpening the distinction and assumes both objects are familiar.',
        'source' => 'C8 Linear Equations ← C7 Finding the Unknown',
    ],
    [
        'concept' => 31274, 'prerequisite' => 31273,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Left and right sides only exist once the thing is an equation rather than an expression.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31275, 'prerequisite' => 31274,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A solution makes the two sides equal, so the sides have to be identified for the test to be stated.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31276, 'prerequisite' => 31275,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Restricting to one variable narrows which equations are in scope, so solutions have to be defined first.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31277, 'prerequisite' => 31276,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The degree restriction is the second half of the definition, added after the variable-count restriction.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31278, 'prerequisite' => 31277,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The named class is exactly what the two restrictions together pick out.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31279, 'prerequisite' => 30574,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Doing the same thing to both sides is taught in Class 7 with a balance; Class 8 formalises it as the principle and uses it throughout.',
        'source' => 'C8 Linear Equations ← C7 Finding the Unknown',
    ],
    [
        'concept' => 31280, 'prerequisite' => 31275,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Checking means substituting and comparing sides, which is the definition of a solution applied.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31281, 'prerequisite' => 31279,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Transposing is the shortcut for doing the same operation on both sides, so the principle justifies the shortcut.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31282, 'prerequisite' => 31281,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Collecting variables on one side is transposing applied to a variable term rather than a constant.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31283, 'prerequisite' => 31282,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The caution about subtracting a term rather than a number is a refinement of the variable-on-each-side case.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31284, 'prerequisite' => 31281,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Clearing denominators is another use of the balance principle, applied before transposing.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31285, 'prerequisite' => 30508,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplying through by the LCM of the denominators needs the LCM to be computable, which Class 7 supplies.',
        'source' => 'C8 Linear Equations ← C7 Finding Common Ground',
    ],
    [
        'concept' => 31285, 'prerequisite' => 31284,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The LCM is the specific multiplier the clearing step calls for, so the step comes first.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31286, 'prerequisite' => 31281,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Opening brackets is a preparation step before transposing can be applied.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31287, 'prerequisite' => 31280,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Checking against both sides is the verification step already introduced, insisted on after the harder methods.',
        'source' => 'C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 31288, 'prerequisite' => 31282,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Word problems produce equations with variables on both sides, so that case has to be solvable first.',
        'source' => 'C8 Linear Equations in One Variable',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Squares and Square Roots (25854)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31334, 'prerequisite' => 31333,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The name comes from the area of a square, which is what multiplying a number by itself gives.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31335, 'prerequisite' => 31334,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal definition generalises the multiply-by-itself idea to any number.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31336, 'prerequisite' => 31335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testing whether a number is a perfect square applies the definition as a check.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31337, 'prerequisite' => 31335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The table lists the squares the definition produces, so the definition is what generates it.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31338, 'prerequisite' => 31337,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ending-digit pattern is read off the table, so the table has to be built first.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31341, 'prerequisite' => 31337,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting numbers between consecutive squares needs the squares themselves listed.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31340, 'prerequisite' => 31339,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding two consecutive triangular numbers to get a square presupposes triangular numbers.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31342, 'prerequisite' => 31335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting into tens and units is a technique for squaring, so squaring has to be defined.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31343, 'prerequisite' => 31342,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The numbers-ending-in-5 shortcut is a special case of the tens-and-units split.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31344, 'prerequisite' => 31335,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A Pythagorean triplet is a relation between three squares, so squaring has to be available.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31345, 'prerequisite' => 31335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A square root undoes squaring, so squaring is the operation being inverted.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31346, 'prerequisite' => 31345,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Getting a side from an area is the square root used for its original geometric purpose.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31347, 'prerequisite' => 31335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a square is a sum of consecutive odd numbers is a property of the squares just defined.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31348, 'prerequisite' => 31347,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The repeated-subtraction root method counts how many odd numbers were removed, which is the sum property run backwards.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31349, 'prerequisite' => 30503,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pairing prime factors to take a root needs the prime factorisation to be unique, which Class 7 establishes.',
        'source' => 'C8 Squares and Square Roots ← C7 Finding Common Ground',
    ],
    [
        'concept' => 31349, 'prerequisite' => 31345,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The factorisation method computes a square root, so the root has to be the target.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31350, 'prerequisite' => 31349,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Long division is introduced because factorisation becomes impractical for large numbers, so that method is the one being improved on.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31351, 'prerequisite' => 31350,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting digit pairs is the first step of the long-division method, so the method has to be introduced.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31352, 'prerequisite' => 31351,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The worked example follows the digit-pairing setup.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31353, 'prerequisite' => 31352,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bars on the decimal part extend the integer procedure just worked through.',
        'source' => 'C8 Squares and Square Roots',
    ],
    [
        'concept' => 31354, 'prerequisite' => 31353,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carrying the decimal point up is the final step of the decimal version of the method.',
        'source' => 'C8 Squares and Square Roots',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Exponents and Powers (25859)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31438, 'prerequisite' => 31437,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Power notation is introduced as the answer to numbers too long to write out.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31439, 'prerequisite' => 31438,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the exponent counts repeated multiplications explains the notation just read aloud.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31440, 'prerequisite' => 31439,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Continuing the pattern downwards past zero only makes sense once the pattern upwards is understood as repeated multiplication.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31441, 'prerequisite' => 31440,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The negative exponent is the value the downward pattern forces, so the pattern is the derivation.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31441, 'prerequisite' => 30457,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A negative exponent means the reciprocal, so the learner must already know what a reciprocal is — Class 7 fraction work.',
        'source' => 'C8 Exponents and Powers ← C7 Working with Fractions',
    ],
    [
        'concept' => 31442, 'prerequisite' => 31441,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Generalising to any base extends the rule just established for one.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31443, 'prerequisite' => 31441,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking whether the laws still hold presupposes the exponent set has been widened to include negatives.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31444, 'prerequisite' => 31443,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The product law is the first of the laws whose survival is being checked.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31445, 'prerequisite' => 31444,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The quotient law is the counterpart of the product law and is derived alongside it.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31446, 'prerequisite' => 31445,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rewriting with a positive exponent applies the quotient law together with the reciprocal rule.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31447, 'prerequisite' => 31441,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Numbers awkward at both ends need negative as well as positive exponents, so both have to be available.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31448, 'prerequisite' => 31447,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Standard form for small numbers is the response to the awkwardness just described.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31449, 'prerequisite' => 31448,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting both ways requires the standard form to have been defined.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31450, 'prerequisite' => 31449,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing two standard forms uses the quotient law on numbers already in that form.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31451, 'prerequisite' => 31449,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cell comparison is a worked application of converting to standard form.',
        'source' => 'C8 Exponents and Powers',
    ],
    [
        'concept' => 31452, 'prerequisite' => 31449,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Adding distances in standard form requires the form to be readable and convertible.',
        'source' => 'C8 Exponents and Powers',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Orienting Yourself: The Use of Coordinates (8594)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 21811, 'prerequisite' => 21810,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Row and column addressing is the systematic version of locating a point by reference to something else.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1359, 'prerequisite' => 21811,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The Cartesian system is row-and-column addressing made continuous and signed, so the discrete version motivates it.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1360, 'prerequisite' => 1359,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The origin is the point where the two axes of the system meet, so the system has to exist to have an origin.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1361, 'prerequisite' => 1360,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Quadrants are the four regions the axes cut the plane into around the origin.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1362, 'prerequisite' => 1360,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A coordinate pair is measured from the origin along each axis, so the origin is the reference point.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1363, 'prerequisite' => 1361,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sign pattern is stated quadrant by quadrant, so the quadrants have to be numbered first.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1367, 'prerequisite' => 1362,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A point on an axis is one of its coordinates being zero, which needs the coordinate pair to be readable.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1365, 'prerequisite' => 1363,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reflecting across an axis flips the sign of one coordinate, so the sign convention is what the operation acts on.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1364, 'prerequisite' => 1362,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The distance formula takes differences of coordinates, so the coordinates must be readable.',
        'source' => 'C9 Orienting Yourself',
    ],
    [
        'concept' => 1364, 'prerequisite' => 31344,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The distance formula IS Pythagoras applied to the horizontal and vertical gaps. A learner without the right-triangle relation can only memorise it.',
        'source' => 'C9 Orienting Yourself ← C8 Squares and Square Roots',
    ],
    [
        'concept' => 1364, 'prerequisite' => 31345,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formula ends in a square root, so taking a root has to be a available operation.',
        'source' => 'C9 Orienting Yourself ← C8 Squares and Square Roots',
    ],
    [
        'concept' => 1366, 'prerequisite' => 1364,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The midpoint is derived alongside the distance formula from the same coordinate differences.',
        'source' => 'C9 Orienting Yourself',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Introduction to Linear Polynomials (8595)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1368, 'prerequisite' => 30357,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Expressions as sums of terms is Class 7 work; Class 9 takes that and restricts it to polynomials without re-establishing what a term is.',
        'source' => 'C9 Linear Polynomials ← C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 1369, 'prerequisite' => 1368,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A polynomial is a particular kind of algebraic expression, so the general category comes first.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1370, 'prerequisite' => 1369,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Degree is the highest exponent appearing in the polynomial, so the polynomial has to be defined.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1371, 'prerequisite' => 1370,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Linear means degree one, so degree has to be a defined quantity for the restriction to pick anything out.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1371, 'prerequisite' => 31278,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Class 8 defines a linear equation by its highest power being one; Class 9 applies the same restriction to polynomials.',
        'source' => 'C9 Linear Polynomials ← C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 1372, 'prerequisite' => 1371,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A linear pattern is one a linear polynomial describes, so the polynomial has to be available.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1373, 'prerequisite' => 1372,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Growth is the increasing case of the linear pattern just introduced.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1374, 'prerequisite' => 1373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Decay is the decreasing case, defined by contrast with growth.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1375, 'prerequisite' => 1372,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A linear relationship generalises the pattern from a sequence to two varying quantities.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1376, 'prerequisite' => 1375,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Slope measures the rate in the linear relationship, so the relationship has to exist to be measured.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1377, 'prerequisite' => 1376,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The intercept is the second parameter of the line the slope begins to describe.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1378, 'prerequisite' => 1377,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Graphing needs both the slope and the intercept, so both parameters have to be defined.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 1378, 'prerequisite' => 1362,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Plotting a line means plotting points, so coordinates from the coordinate chapter are the medium the graph is drawn in.',
        'source' => 'C9 Linear Polynomials ← C9 Orienting Yourself',
    ],
    [
        'concept' => 1379, 'prerequisite' => 1376,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Parallel lines are identified by equal slopes, so slope is the criterion.',
        'source' => 'C9 Introduction to Linear Polynomials',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · The World of Numbers (8596)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1382, 'prerequisite' => 1380,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Zero is introduced as what the counting numbers lack, so the naturals are the set being extended.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1381, 'prerequisite' => 1380,
        'type' => 'requires', 'gate' => false,
        'reason' => 'One-to-one correspondence is the account of what counting with the naturals actually does.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1383, 'prerequisite' => 1382,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The integers extend the whole numbers below zero, so zero has to be in place as the pivot.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1384, 'prerequisite' => 1383,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Brahmagupta\'s sign rules are rules for the integers just introduced.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1385, 'prerequisite' => 31256,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The p-over-q definition is Class 8 content; Class 9 uses it to ask what lies between and beyond the rationals.',
        'source' => 'C9 The World of Numbers ← C8 Rational Numbers',
    ],
    [
        'concept' => 1386, 'prerequisite' => 1385,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Density is the claim that between any two rationals there is another, so the rationals have to be defined.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1387, 'prerequisite' => 1386,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Irrationals are surprising precisely because the rationals are dense and still leave gaps, so density sets up the puzzle.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1387, 'prerequisite' => 31345,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The first irrational met is the square root of two, so taking a root has to be an available operation.',
        'source' => 'C9 The World of Numbers ← C8 Squares and Square Roots',
    ],
    [
        'concept' => 1388, 'prerequisite' => 1387,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Proof by contradiction is introduced to show root two is irrational, so the claim is what the method is for.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1389, 'prerequisite' => 1387,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The reals are the rationals and irrationals together, so both halves have to be defined.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1390, 'prerequisite' => 1389,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Terminating, repeating and non-repeating decimals classify the reals, so the reals have to be assembled first.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1391, 'prerequisite' => 1390,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting a repeating decimal back to a fraction needs the repeating type to have been identified.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1393, 'prerequisite' => 1387,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Constructing an irrational length on the number line makes the irrational visible, so it has to be defined first.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1393, 'prerequisite' => 31344,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The construction uses a right triangle whose hypotenuse is the irrational length, so the Pythagorean relation is the tool.',
        'source' => 'C9 The World of Numbers ← C8 Squares and Square Roots',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Exploring Algebraic Identities (8597)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1395, 'prerequisite' => 31271,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Every identity is proved by expanding with the distributive rule, so that rule is the engine behind all of them.',
        'source' => 'C9 Algebraic Identities ← C8 Rational Numbers',
    ],
    [
        'concept' => 1396, 'prerequisite' => 1395,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The square-of-a-sum is the first worked identity, so the idea of an identity has to be defined.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1397, 'prerequisite' => 1396,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The square-of-a-difference is derived by changing one sign in the identity just established.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1398, 'prerequisite' => 1396,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three-term square extends the two-term one, so the simpler case is the pattern.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1399, 'prerequisite' => 1397,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The difference of squares is obtained alongside the sum and difference squares in the same family.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1400, 'prerequisite' => 1396,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The (x+a)(x+b) expansion generalises the square identity to two different constants.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1401, 'prerequisite' => 1399,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Factorising with identities means recognising an expression as one side of an identity, so the identities have to be known.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1402, 'prerequisite' => 1396,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The area picture is a proof of the square identity, so the identity is what it demonstrates.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1403, 'prerequisite' => 1402,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Algebra tiles are the physical version of the area picture, used for factorising.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1404, 'prerequisite' => 1396,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cube identity is derived by multiplying the square identity by another binomial.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1405, 'prerequisite' => 1404,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The difference cube follows from the sum cube by a sign change, as the squares did.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1406, 'prerequisite' => 1404,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum of cubes is obtained by rearranging the binomial cube, so that expansion is the starting point.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1407, 'prerequisite' => 1406,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The difference of cubes mirrors the sum of cubes and is derived the same way.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1408, 'prerequisite' => 1406,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three-cube identity is built from the sum-of-cubes factorisation.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1409, 'prerequisite' => 1401,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Simplifying a rational expression means factorising top and bottom and cancelling, so factorisation is the method.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 21812, 'prerequisite' => 1400,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting the middle term is the reverse of the (x+a)(x+b) expansion, so that expansion is what is being undone.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Circles (8598)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1411, 'prerequisite' => 33185,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 constructs a circle as the set of points equally far from a centre; Class 9 states that as the definition and reasons from it.',
        'source' => 'C9 Circles ← C6 Playing with Constructions',
    ],
    [
        'concept' => 1412, 'prerequisite' => 1411,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Centre and radius are the two elements the definition names, so the definition comes first.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1413, 'prerequisite' => 1412,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A chord joins two points on the circle and a diameter passes through the centre, so both need the centre and radius.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1414, 'prerequisite' => 1412,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Every diameter is a line of symmetry, which is a statement about the centre.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1415, 'prerequisite' => 1412,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking how many circles pass through two points is a question about centres and radii.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1416, 'prerequisite' => 1415,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three-point uniqueness result answers the two-point question by adding one more constraint.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1417, 'prerequisite' => 1416,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The circumcentre is the centre of the unique circle through a triangle\'s three vertices.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1418, 'prerequisite' => 1413,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The theorem is about chords and the angles they subtend, so chords have to be defined.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1418, 'prerequisite' => 30466,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The proof works by showing two triangles congruent, so congruence from Class 7 is the tool every circle theorem here uses.',
        'source' => 'C9 Circles ← C7 Geometric Twins',
    ],
    [
        'concept' => 1419, 'prerequisite' => 1418,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The converse reverses the theorem just proved, so the forward direction comes first.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1420, 'prerequisite' => 1413,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The perpendicular-from-centre result is a statement about a chord, so chords must be defined.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1421, 'prerequisite' => 1420,
        'type' => 'requires', 'gate' => true,
        'reason' => 'This is the converse of the perpendicular result, so that result comes first.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1422, 'prerequisite' => 1420,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Equidistance is measured along the perpendicular from the centre, which the earlier theorem establishes.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1423, 'prerequisite' => 1422,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Relating distance to chord length quantifies the equidistance result just proved.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1424, 'prerequisite' => 1418,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The arc-at-centre theorem extends the chord-angle work from chords to arcs.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1425, 'prerequisite' => 1424,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The right angle in a semicircle is the arc theorem applied to a diameter, so the general result comes first.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1426, 'prerequisite' => 1424,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Angles in the same segment are equal because each is half the same central angle.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1427, 'prerequisite' => 1426,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Concyclicity is tested by the equal-angles-in-the-same-segment property.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1428, 'prerequisite' => 1427,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A cyclic quadrilateral is four concyclic points, so concyclicity is its definition.',
        'source' => 'C9 Circles',
    ],
    [
        'concept' => 1429, 'prerequisite' => 1428,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The converse reverses the cyclic-quadrilateral angle property, so that property comes first.',
        'source' => 'C9 Circles',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Measuring Space: Perimeter and Area (8601)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1430, 'prerequisite' => 33141,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Perimeter as the distance round a boundary is Class 6 content; Class 9 uses it for curved shapes and does not redefine it.',
        'source' => 'C9 Measuring Space ← C6 Perimeter and Area',
    ],
    [
        'concept' => 1431, 'prerequisite' => 1430,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Circumference is the perimeter of a circle, so the general idea has to come first.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1432, 'prerequisite' => 1431,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pi is defined as circumference divided by diameter, so the circumference is one of the two terms.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1433, 'prerequisite' => 1432,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Arc length is a fraction of the circumference, which pi is what computes.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 21814, 'prerequisite' => 33148,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 6 insists on counting grid squares before any formula; Class 9 states the rectangle formula as the shorthand for that count.',
        'source' => 'C9 Measuring Space ← C6 Perimeter and Area',
    ],
    [
        'concept' => 21815, 'prerequisite' => 21814,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The parallelogram area is derived by cutting and rearranging it into a rectangle.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1434, 'prerequisite' => 21815,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A triangle is half a parallelogram, so the parallelogram result is what the halving acts on.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1435, 'prerequisite' => 1434,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Heron\'s formula is the alternative when the height is unknown, so the base-height formula is what it replaces.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1435, 'prerequisite' => 31345,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Heron\'s formula ends in a square root, so taking a root has to be available.',
        'source' => 'C9 Measuring Space ← C8 Squares and Square Roots',
    ],
    [
        'concept' => 1436, 'prerequisite' => 1432,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The circle area formula is stated in terms of pi, so pi has to be defined.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1437, 'prerequisite' => 1436,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A sector area is a fraction of the circle area, so the whole has to be computable first.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1438, 'prerequisite' => 1435,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Brahmagupta\'s formula for a cyclic quadrilateral extends Heron\'s pattern to four sides.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 1439, 'prerequisite' => 1387,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling pi irrational needs the irrational numbers to have been defined in the number chapter.',
        'source' => 'C9 Measuring Space ← C9 The World of Numbers',
    ],
    [
        'concept' => 1440, 'prerequisite' => 1434,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a median halves the area is proved from the base-height formula with equal bases.',
        'source' => 'C9 Measuring Space',
    ],
    [
        'concept' => 21813, 'prerequisite' => 21814,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Showing that equal perimeters give different areas needs both quantities computable for a rectangle.',
        'source' => 'C9 Measuring Space',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · The Mathematics of Maybe: Probability (8602)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1443, 'prerequisite' => 1442,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Randomness is what probability is a measure of, so the subject has to be introduced first.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1444, 'prerequisite' => 1442,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The zero-to-one scale is the range the measure takes, so the measure has to exist.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1447, 'prerequisite' => 1443,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sample space lists all outcomes of the random experiment, so randomness frames it.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1448, 'prerequisite' => 1447,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An event is a subset of the sample space, so the space has to be written down first.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1446, 'prerequisite' => 1448,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Theoretical probability counts favourable outcomes over total outcomes, which are the event and the sample space.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1446, 'prerequisite' => 33166,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A probability is written as a fraction of favourable to total, so numerator and denominator have to mean something.',
        'source' => 'C9 Probability ← C6 Fractions',
    ],
    [
        'concept' => 1445, 'prerequisite' => 1444,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An experimental probability is a number on the same zero-to-one scale, so the scale has to be established.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1449, 'prerequisite' => 1447,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A tree diagram is a way of enumerating the sample space for multi-stage experiments.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1452, 'prerequisite' => 1445,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Relative frequency is what experimental probability computes, so the experimental notion comes first.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1450, 'prerequisite' => 1452,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The law states that relative frequency approaches the theoretical value, so the frequency has to be defined.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1450, 'prerequisite' => 1446,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The value it approaches is the theoretical probability, so that is the other term in the claim.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1451, 'prerequisite' => 1450,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The gambler\'s fallacy is a misreading of the law of large numbers, so the law has to be stated correctly first.',
        'source' => 'C9 Probability',
    ],
    [
        'concept' => 1453, 'prerequisite' => 1446,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Fairness means every outcome has equal theoretical probability, so that notion is the definition.',
        'source' => 'C9 Probability',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C9 · Exploring Sequences and Progressions (8603)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1455, 'prerequisite' => 1454,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An explicit formula gives the nth term of a sequence, so the sequence has to be defined.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1455, 'prerequisite' => 30361,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Writing an nth-term rule is taught in Class 7 as an algebra skill; Class 9 formalises it as the explicit formula of a sequence.',
        'source' => 'C9 Sequences ← C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 1456, 'prerequisite' => 1455,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A recursive formula is contrasted with the explicit one, so the explicit case is the reference.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1457, 'prerequisite' => 1455,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An AP is a sequence with a particular rule, so the sequence has to be defined before it is specialised.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1458, 'prerequisite' => 1457,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The common difference is the constant that makes a sequence arithmetic, so the AP has to be introduced.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1459, 'prerequisite' => 1458,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The nth-term formula is built from the first term and the common difference.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1460, 'prerequisite' => 1457,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum of the first n naturals is the simplest AP sum, so the AP frames it.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1461, 'prerequisite' => 1460,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Triangular numbers are exactly those partial sums, so the sum has to be computed first.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1462, 'prerequisite' => 1457,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A GP is introduced by contrast with an AP — multiplying rather than adding — so the AP is the reference.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1463, 'prerequisite' => 1462,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The common ratio is what makes a sequence geometric, so the GP has to be introduced.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1464, 'prerequisite' => 1463,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The GP nth-term formula is built from the first term and the common ratio.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1465, 'prerequisite' => 1456,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The Fibonacci rule is recursive by nature, so the recursive form has to be available to express it.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1466, 'prerequisite' => 1456,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'A fractal is generated by applying a rule to its own output, which is the recursive idea in a geometric setting.',
        'source' => 'C9 Sequences and Progressions',
    ],
    [
        'concept' => 1467, 'prerequisite' => 1466,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The Sierpinski triangle is the worked example of the fractal construction.',
        'source' => 'C9 Sequences and Progressions',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · REAL NUMBERS (22905)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24670, 'prerequisite' => 24669,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Factorising into primes rests on repeated division, so the division process has to be available.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24670, 'prerequisite' => 30503,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 7 establishes that a number\'s prime factorisation is always the same; Class 10 states it as the Fundamental Theorem and proves consequences from it.',
        'source' => 'C10 Real Numbers ← C7 Finding Common Ground',
    ],
    [
        'concept' => 24672, 'prerequisite' => 24670,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal statement follows the working idea of a unique prime product.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24673, 'prerequisite' => 24672,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ordering the primes is the convention that makes the factorisation literally unique, so the theorem comes first.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24674, 'prerequisite' => 24672,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading HCF and LCM off the factorisations needs those factorisations to be unique, or the answer would not be well defined.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24671, 'prerequisite' => 24672,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Whether a decimal terminates depends on the prime factors of the denominator, so the factorisation is the test.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24675, 'prerequisite' => 1387,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Irrational numbers are introduced in Class 9; Class 10 restates the definition only to prove irrationality formally.',
        'source' => 'C10 Real Numbers ← C9 The World of Numbers',
    ],
    [
        'concept' => 24676, 'prerequisite' => 24672,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The lemma about p dividing a squared is proved from unique factorisation, so the theorem is its basis.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24677, 'prerequisite' => 24676,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The root-two proof uses that lemma at its critical step, so the lemma has to be established first.',
        'source' => 'C10 Real Numbers',
    ],
    [
        'concept' => 24677, 'prerequisite' => 24675,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The proof concludes that root two is irrational, so the definition supplies what is being proved.',
        'source' => 'C10 Real Numbers',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · POLYNOMIALS (22907)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24693, 'prerequisite' => 1370,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Degree is defined in Class 9 for linear polynomials; Class 10 reuses it to classify quadratics and cubics.',
        'source' => 'C10 Polynomials ← C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 24694, 'prerequisite' => 24693,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The three names are degrees one, two and three, so degree has to be readable.',
        'source' => 'C10 Polynomials',
    ],
    [
        'concept' => 24695, 'prerequisite' => 24694,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The general quadratic form makes the degree-two case explicit, so the classification comes first.',
        'source' => 'C10 Polynomials',
    ],
    [
        'concept' => 24696, 'prerequisite' => 24693,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A zero is a value making the polynomial vanish, so the polynomial has to be a defined object.',
        'source' => 'C10 Polynomials',
    ],
    [
        'concept' => 24697, 'prerequisite' => 24696,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Zeroes appear on the graph as x-axis crossings, so the algebraic notion comes before the geometric reading.',
        'source' => 'C10 Polynomials',
    ],
    [
        'concept' => 24697, 'prerequisite' => 1378,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading zeroes off a curve requires being able to graph a polynomial at all, which Class 9 teaches for the linear case.',
        'source' => 'C10 Polynomials ← C9 Introduction to Linear Polynomials',
    ],
    [
        'concept' => 24698, 'prerequisite' => 24696,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The linear zero is the simplest case of the general definition.',
        'source' => 'C10 Polynomials',
    ],
    [
        'concept' => 24699, 'prerequisite' => 21812,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Splitting the middle term is taught in Class 9 as a factorisation technique; Class 10 uses it to find quadratic zeroes.',
        'source' => 'C10 Polynomials ← C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 24699, 'prerequisite' => 24695,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The middle term is the bx of the general quadratic, so that form has to be written before it can be split.',
        'source' => 'C10 Polynomials',
    ],
    [
        'concept' => 24700, 'prerequisite' => 24699,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum and product relations are read off the factorised form that splitting produces.',
        'source' => 'C10 Polynomials',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · PAIR OF LINEAR EQUATIONS IN TWO VARIABLES (22910)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24721, 'prerequisite' => 31278,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Class 8 solves one linear equation in one variable; Class 10 raises it to two equations in two and assumes the single case is secure.',
        'source' => 'C10 Pair of Linear Equations ← C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 24722, 'prerequisite' => 24721,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The solving methods are methods for the paired situation just set up.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24724, 'prerequisite' => 24722,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An inconsistent pair is one the methods cannot solve, so the methods frame the classification.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24725, 'prerequisite' => 24724,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Consistent and dependent are defined against the inconsistent case introduced first.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24726, 'prerequisite' => 24725,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The coefficient-ratio test decides which of the three cases a pair falls into, so the cases have to be named.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24727, 'prerequisite' => 31281,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Expressing one variable in terms of the other is transposing, which Class 8 establishes as a legitimate move.',
        'source' => 'C10 Pair of Linear Equations ← C8 Linear Equations in One Variable',
    ],
    [
        'concept' => 24728, 'prerequisite' => 24727,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Substituting requires an expression for one variable, which the previous step produces.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24729, 'prerequisite' => 24728,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Back substitution recovers the second variable after the first has been found by reduction.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24730, 'prerequisite' => 24722,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Matching coefficients is the preparation step of the elimination method.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24731, 'prerequisite' => 24730,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Eliminating works only once the coefficients have been made equal.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24732, 'prerequisite' => 24731,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing elimination presupposes both methods have been carried out at least once.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · QUADRATIC EQUATIONS (23126)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24733, 'prerequisite' => 24695,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A quadratic equation is a quadratic polynomial set equal to zero, so the polynomial form comes first.',
        'source' => 'C10 Quadratic Equations ← C10 Polynomials',
    ],
    [
        'concept' => 24736, 'prerequisite' => 24733,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Standard form fixes the arrangement of the equation just introduced.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24737, 'prerequisite' => 24736,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recognising a quadratic means matching it against the standard form.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24738, 'prerequisite' => 24737,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Framing an equation from words means producing something in the recognised form.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24735, 'prerequisite' => 24696,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A root of the equation is a zero of the corresponding polynomial, so the zero has to be defined.',
        'source' => 'C10 Quadratic Equations ← C10 Polynomials',
    ],
    [
        'concept' => 24739, 'prerequisite' => 24735,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formal root definition follows the zero-root identification.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24740, 'prerequisite' => 24739,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Checking by substitution applies the definition of a root as a test.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24741, 'prerequisite' => 24739,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting the middle term is a method for finding roots, so the root has to be the target.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24742, 'prerequisite' => 24741,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two distinct roots is the outcome when the factorisation yields two different factors.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24743, 'prerequisite' => 24742,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Equal roots is the boundary case, defined against the distinct one.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24744, 'prerequisite' => 24743,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The discriminant is the quantity that predicts which root case occurs, so the cases have to be known first.',
        'source' => 'C10 Quadratic Equations',
    ],
    [
        'concept' => 24734, 'prerequisite' => 24736,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Everyday quadratics are recognised by fitting the standard form.',
        'source' => 'C10 Quadratic Equations',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · ARITHMETIC PROGRESSIONS (23197)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24806, 'prerequisite' => 24805,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The fixed-addition rule is abstracted from the natural patterns the chapter opens with.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24808, 'prerequisite' => 24806,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Terms of a list are what the fixed-addition rule generates.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24809, 'prerequisite' => 1458,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The common difference is defined in Class 9; Class 10 uses it immediately to build the general form and the nth term.',
        'source' => 'C10 Arithmetic Progressions ← C9 Sequences and Progressions',
    ],
    [
        'concept' => 24810, 'prerequisite' => 24809,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Finite and infinite are properties of the progression the common difference defines.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24811, 'prerequisite' => 24809,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The general form is written from the first term and the common difference.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24812, 'prerequisite' => 24811,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The nth-term formula is read off the general form.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24813, 'prerequisite' => 24812,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Applying the formula to word problems presupposes the formula.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24815, 'prerequisite' => 24814,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pairing terms is the trick the Gauss story demonstrates, so the story sets it up.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24816, 'prerequisite' => 24815,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum formula generalises the pairing trick to any AP.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24816, 'prerequisite' => 24812,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum formula is written in terms of the first and last terms, and the last term comes from the nth-term formula.',
        'source' => 'C10 Arithmetic Progressions',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · TRIANGLES (23513) — similarity
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24793, 'prerequisite' => 30466,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Congruence is established in Class 7 with its criteria; Class 10 recalls it in one line and builds similarity by relaxing it.',
        'source' => 'C10 Triangles ← C7 Geometric Twins',
    ],
    [
        'concept' => 24794, 'prerequisite' => 24793,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Similarity is introduced as congruence with the equal-size condition dropped, so congruence is the starting point.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24795, 'prerequisite' => 24794,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Why similarity matters is argued once the relation has been defined.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24797, 'prerequisite' => 24796,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Figures of different sizes are contrasted with congruent ones, so the congruent case is the reference.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24798, 'prerequisite' => 24797,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The polygon similarity conditions — equal angles and proportional sides — formalise same-shape-different-size.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24799, 'prerequisite' => 24798,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Specialising to triangles narrows the polygon definition just given.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24800, 'prerequisite' => 24799,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The Basic Proportionality Theorem is a statement about a triangle, so the triangle case has to be in focus.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24801, 'prerequisite' => 24800,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The converse reverses the theorem, so the forward direction comes first.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24802, 'prerequisite' => 24800,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The AAA criterion is proved using the proportionality theorem, so that theorem is the tool.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24803, 'prerequisite' => 24802,
        'type' => 'requires', 'gate' => true,
        'reason' => 'SSS similarity is established after AAA and by the same proportional reasoning.',
        'source' => 'C10 Triangles',
    ],
    [
        'concept' => 24804, 'prerequisite' => 24803,
        'type' => 'requires', 'gate' => true,
        'reason' => 'SAS similarity completes the set of criteria begun with AAA and SSS.',
        'source' => 'C10 Triangles',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · COORDINATE GEOMETRY (23512)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24784, 'prerequisite' => 1362,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Coordinates are taught in Class 9; Class 10 names the two parts abscissa and ordinate and proceeds to formulas.',
        'source' => 'C10 Coordinate Geometry ← C9 Orienting Yourself',
    ],
    [
        'concept' => 24785, 'prerequisite' => 24784,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A point on an axis is one of the two named coordinates being zero.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24786, 'prerequisite' => 24784,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The derivation takes differences of abscissae and ordinates, so both have to be named.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24787, 'prerequisite' => 24786,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The formula is the result of the derivation just carried out.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24787, 'prerequisite' => 1364,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The distance formula appears in Class 9; Class 10 restates it and applies it to collinearity and section problems.',
        'source' => 'C10 Coordinate Geometry ← C9 Orienting Yourself',
    ],
    [
        'concept' => 24788, 'prerequisite' => 24787,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Collinearity and shape tests are comparisons of computed distances.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24789, 'prerequisite' => 24787,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing a segment in a ratio presupposes the segment and its length can be handled.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24790, 'prerequisite' => 24789,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The section formula computes the dividing point the previous concept describes.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24791, 'prerequisite' => 24790,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Writing the ratio as k to 1 is a convenience within the section formula.',
        'source' => 'C10 Coordinate Geometry',
    ],
    [
        'concept' => 24792, 'prerequisite' => 24790,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The midpoint is the section formula with the ratio one to one, so the general formula comes first.',
        'source' => 'C10 Coordinate Geometry',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · INTRODUCTION TO TRIGONOMETRY (23511)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24773, 'prerequisite' => 24772,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Trigonometry is introduced as the study of the right triangles just pointed out in the world.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24775, 'prerequisite' => 30476,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The hypotenuse is named in Class 7 when RHS congruence is introduced; Class 10 uses it as one of the three named sides without redefining it.',
        'source' => 'C10 Trigonometry ← C7 Geometric Twins',
    ],
    [
        'concept' => 24775, 'prerequisite' => 24773,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming the sides relative to an angle is the first step of the subject just introduced.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24776, 'prerequisite' => 24775,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Each of the six ratios is a quotient of two named sides, so the naming has to come first.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24776, 'prerequisite' => 24798,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ratios depend only on the angle and not the triangle\'s size, which is true BECAUSE similar triangles have proportional sides. Without similarity the definition is not well founded.',
        'source' => 'C10 Trigonometry ← C10 Triangles',
    ],
    [
        'concept' => 24777, 'prerequisite' => 24776,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That one ratio fixes the others is a relation among the six, so all six have to be defined.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24778, 'prerequisite' => 24776,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 45-degree values are the ratios evaluated on a particular triangle.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24779, 'prerequisite' => 24778,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 30 and 60 values are computed the same way, after the 45 case has shown the method.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24780, 'prerequisite' => 24779,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The limiting values at 0 and 90 complete the standard-angle table.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24781, 'prerequisite' => 1395,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'What an identity is — true for every value — is established in Class 9 algebra, and Class 10 reuses the notion for trigonometric ones.',
        'source' => 'C10 Trigonometry ← C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 24782, 'prerequisite' => 31344,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sin-squared-plus-cos-squared identity is Pythagoras divided through by the hypotenuse squared, so the Pythagorean relation is its proof.',
        'source' => 'C10 Trigonometry ← C8 Squares and Square Roots',
    ],
    [
        'concept' => 24782, 'prerequisite' => 24781,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling it an identity presupposes the word has been defined.',
        'source' => 'C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24783, 'prerequisite' => 24782,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The other two identities are obtained by dividing the first one through, so it is the source.',
        'source' => 'C10 Introduction to Trigonometry',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · SOME APPLICATIONS OF TRIGONOMETRY (23199)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24764, 'prerequisite' => 24763,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The angle of elevation is measured between the line of sight and the horizontal, so the line of sight has to be defined.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24766, 'prerequisite' => 24763,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sight below the horizontal is the same line of sight pointed downwards.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24767, 'prerequisite' => 24766,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The angle of depression names the downward case just described.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24769, 'prerequisite' => 24764,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The right triangle is drawn with the elevation angle at the observer, so that angle has to be identified.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24770, 'prerequisite' => 24776,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing between sine, cosine and tangent requires all six ratios to be available and their side pairings known.',
        'source' => 'C10 Applications of Trigonometry ← C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24770, 'prerequisite' => 24769,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The ratio is chosen to match which sides the drawn triangle gives and asks for.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24771, 'prerequisite' => 24770,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding the observer\'s height corrects the answer the chosen ratio produced.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · CIRCLES (23198) — tangents
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24754, 'prerequisite' => 1412,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Centre and radius are Class 9 content; Class 10 classifies lines by how many points they share with the circle and assumes both.',
        'source' => 'C10 Circles ← C9 Circles',
    ],
    [
        'concept' => 24755, 'prerequisite' => 24754,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A secant is the two-point case, contrasted with the non-intersecting one.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24756, 'prerequisite' => 24755,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A tangent is the one-point case, completing the classification begun with zero and two points.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24757, 'prerequisite' => 24756,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The point of contact is the single shared point the tangent definition names.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24758, 'prerequisite' => 24756,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Seeing a tangent as a secant whose two points merge needs both cases to be available.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24759, 'prerequisite' => 24757,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The perpendicularity theorem is stated at the point of contact, so that point has to be identified.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24760, 'prerequisite' => 24756,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting tangents from a point presupposes knowing what a tangent is.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24761, 'prerequisite' => 24760,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The on-the-circle case continues the count begun with the interior case.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24762, 'prerequisite' => 24761,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The exterior case completes the count, and the equal-length result is proved there.',
        'source' => 'C10 Circles',
    ],
    [
        'concept' => 24762, 'prerequisite' => 24759,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The equal-tangents proof uses the radius-tangent right angle, so that theorem is the tool.',
        'source' => 'C10 Circles',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · AREAS RELATED TO CIRCLES (23196)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24745, 'prerequisite' => 1437,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The sector and its area are introduced in Class 9; Class 10 adds segments and combined figures on top.',
        'source' => 'C10 Areas Related to Circles ← C9 Measuring Space',
    ],
    [
        'concept' => 24746, 'prerequisite' => 24745,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A segment is a sector minus a triangle, so the sector has to be defined first.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24747, 'prerequisite' => 24746,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Minor and major apply to both sectors and segments, so both have to be introduced.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24748, 'prerequisite' => 24745,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sector angle is the parameter that fixes which sector is meant.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24749, 'prerequisite' => 1436,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sector area is a fraction of the whole circle area, so the circle formula has to be available from Class 9.',
        'source' => 'C10 Areas Related to Circles ← C9 Measuring Space',
    ],
    [
        'concept' => 24749, 'prerequisite' => 24748,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The fraction is the sector angle over 360, so the angle has to be identified.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24750, 'prerequisite' => 24748,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Arc length uses the same angle fraction applied to the circumference.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24751, 'prerequisite' => 24749,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The segment area subtracts a triangle from the sector area, so the sector area is one of the two terms.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24752, 'prerequisite' => 24751,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The triangle height is needed to compute the piece being subtracted.',
        'source' => 'C10 Areas Related to Circles',
    ],
    [
        'concept' => 24752, 'prerequisite' => 24779,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The height is found using the standard-angle trigonometric ratios, so those values have to be known.',
        'source' => 'C10 Areas Related to Circles ← C10 Introduction to Trigonometry',
    ],
    [
        'concept' => 24753, 'prerequisite' => 24752,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The chord length is found by the same trigonometric method as the height.',
        'source' => 'C10 Areas Related to Circles',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · SURFACE AREAS AND VOLUMES (22909)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24713, 'prerequisite' => 24712,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A combination is made of the basic solids, so those have to be known individually.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24714, 'prerequisite' => 24713,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recognising the parts of a real object presupposes the idea that objects are combinations.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24715, 'prerequisite' => 24714,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Breaking a solid down is recognising its parts and then separating them for calculation.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24716, 'prerequisite' => 24715,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Which surfaces are exposed can only be judged once the solid has been decomposed.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24717, 'prerequisite' => 24716,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Only the exposed curved surfaces are added, so the exposure rule decides what goes into the sum.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24718, 'prerequisite' => 24717,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The contrast with volume is drawn after the surface-area rule has been stated.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24719, 'prerequisite' => 24718,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That volumes simply add, unlike areas, is the point of the contrast just made.',
        'source' => 'C10 Surface Areas and Volumes',
    ],
    [
        'concept' => 24720, 'prerequisite' => 24719,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The shed problem asks for both a surface area and a volume, so both rules have to be settled.',
        'source' => 'C10 Surface Areas and Volumes',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · STATISTICS (22908)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24702, 'prerequisite' => 24701,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Extending the measures to grouped data presupposes the grouped and ungrouped distinction.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24704, 'prerequisite' => 24702,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The class mark is the device that makes a grouped class behave like a single value.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24705, 'prerequisite' => 30541,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The mean as sum over count is Class 7 content; Class 10 adapts it to grouped data and does not re-derive it.',
        'source' => 'C10 Statistics ← C7 Connecting the Dots',
    ],
    [
        'concept' => 24705, 'prerequisite' => 24704,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The direct method multiplies each class mark by its frequency, so the class mark has to be defined.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24706, 'prerequisite' => 24705,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Assumed mean and step deviation are shortcuts for the direct method, so that method comes first.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24707, 'prerequisite' => 24702,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mode is the second measure being extended to grouped data.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24708, 'prerequisite' => 24707,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The modal class is where the mode falls, so the mode has to be defined first.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24709, 'prerequisite' => 24708,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The mode formula interpolates within the modal class, so that class has to be identified.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24710, 'prerequisite' => 30548,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The median as the middle value is taught in Class 7; Class 10 extends it to grouped data with a formula.',
        'source' => 'C10 Statistics ← C7 Connecting the Dots',
    ],
    [
        'concept' => 24703, 'prerequisite' => 24710,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cumulative frequency and the ogive are the tools for locating the median class.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24711, 'prerequisite' => 24709,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The empirical relation links all three measures, so the mode must be computable.',
        'source' => 'C10 Statistics',
    ],
    [
        'concept' => 24711, 'prerequisite' => 24710,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The median is the second of the three terms in the same relation.',
        'source' => 'C10 Statistics',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C10 · PROBABILITY (22906)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 24679, 'prerequisite' => 24678,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The die extends the equally-likely idea introduced with the coin from two outcomes to six.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24680, 'prerequisite' => 24679,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Unequally likely outcomes are defined by contrast with the fair coin and die.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24681, 'prerequisite' => 1446,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Theoretical probability is defined in Class 9; Class 10 restates it precisely and builds the complement rule on it.',
        'source' => 'C10 Probability ← C9 Probability',
    ],
    [
        'concept' => 24681, 'prerequisite' => 24680,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The classical definition requires outcomes to be equally likely, so that condition has to be understood.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24682, 'prerequisite' => 1445,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Experimental probability is met in Class 9; Class 10 explains why it cannot replace the theoretical definition.',
        'source' => 'C10 Probability ← C9 Probability',
    ],
    [
        'concept' => 24684, 'prerequisite' => 24681,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An elementary event is a single outcome with its probability, so the definition of probability comes first.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24685, 'prerequisite' => 24684,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum-to-one result is about elementary events, so they have to be defined.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24686, 'prerequisite' => 24684,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Listing outcomes is enumerating the elementary events.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24687, 'prerequisite' => 24685,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The complement is defined using the fact that all elementary probabilities sum to one.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24688, 'prerequisite' => 24687,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sum rule states the relation between an event and its complement, so the complement has to be defined.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24689, 'prerequisite' => 24688,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using the complement to shorten a calculation applies the rule just proved.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24690, 'prerequisite' => 24681,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An impossible event has probability zero, which is a value of the measure just defined.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24691, 'prerequisite' => 24690,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The sure event is the opposite extreme to the impossible one.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24692, 'prerequisite' => 24691,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The zero-to-one range is bounded by the impossible and sure events just established.',
        'source' => 'C10 Probability',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Comparing Quantities (25856)
    // The first proportional-reasoning chapter on this estate. Ratio and
    // percentage have NO Class 6 or 7 chapter here, so this is the root.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31371, 'prerequisite' => 33166,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'A ratio is written as one quantity over another, so numerator and denominator from Class 6 are the form it is expressed in.',
        'source' => 'C8 Comparing Quantities ← C6 Fractions',
    ],
    [
        'concept' => 31372, 'prerequisite' => 31371,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Seeing the ratio as a fraction is a reinterpretation of the comparison just introduced.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31373, 'prerequisite' => 31372,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A percentage is a fraction with denominator 100, so the fraction reading has to come first.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31374, 'prerequisite' => 31373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A discount is a percentage of the marked price, so percentage has to be computable.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31375, 'prerequisite' => 31374,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The subtraction definition makes precise the discount just introduced.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31376, 'prerequisite' => 31375,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Identifying the marked price as the base of the percentage is what stops learners taking it off the wrong number.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31377, 'prerequisite' => 31376,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Rounding the bill is a practical step once the base has been identified.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31378, 'prerequisite' => 31373,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The ten-per-cent shortcut is a mental method for the percentage just defined.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31379, 'prerequisite' => 31378,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Five per cent is half of ten, so the ten-per-cent method comes first.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31381, 'prerequisite' => 31373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sales tax is a percentage added to the bill, so percentage has to be computable.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31382, 'prerequisite' => 31381,
        'type' => 'requires', 'gate' => true,
        'reason' => 'VAT differs from sales tax in being already included, so the added case is the reference.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31383, 'prerequisite' => 31382,
        'type' => 'requires', 'gate' => false,
        'reason' => 'GST replaced the earlier taxes, so those have to be described for the replacement to mean anything.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31385, 'prerequisite' => 31384,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Simple interest is one way of charging the interest just introduced.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31386, 'prerequisite' => 31385,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Compound interest is defined by contrast — the interest joins the principal — so the simple case is the reference.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31387, 'prerequisite' => 31386,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Wanting a shortcut presupposes having done the year-by-year calculation the hard way.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31388, 'prerequisite' => 31387,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Building it year by year is the derivation the shortcut is abstracted from.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31389, 'prerequisite' => 31388,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The general formula is the pattern of the year-by-year build written once.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31390, 'prerequisite' => 31389,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Population change is the compound formula applied outside money.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31391, 'prerequisite' => 31389,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Bacterial growth is a second application of the same compound formula.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31392, 'prerequisite' => 31389,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Depreciation is the compound formula with a negative rate.',
        'source' => 'C8 Comparing Quantities',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Algebraic Expressions and Identities (25857)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31393, 'prerequisite' => 30366,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Adding like terms is taught in Class 7; Class 8 lays expressions out in rows to do it systematically.',
        'source' => 'C8 Algebraic Expressions ← C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 31394, 'prerequisite' => 31393,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Aligning like terms in columns presupposes the one-per-row layout.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31395, 'prerequisite' => 31394,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding coefficients works because like terms sit in the same column.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31396, 'prerequisite' => 31395,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Three expressions at once extends the two-expression column addition.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31401, 'prerequisite' => 31395,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A monomial is a single term, which only has meaning once terms have been isolated and combined.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31402, 'prerequisite' => 31401,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting a monomial into coefficient and variable part analyses the object just defined.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31403, 'prerequisite' => 31401,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Binomial and trinomial count terms, so the single-term case is the unit being counted.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31398, 'prerequisite' => 31401,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Writing a rectangle\'s area as a product of monomials is the first use of the term.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31399, 'prerequisite' => 31398,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The box volume extends the rectangle area to three monomial factors.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31404, 'prerequisite' => 31403,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplying a monomial by a binomial needs both kinds of expression to be named.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31407, 'prerequisite' => 31271,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Expanding a product term by term IS the distributive law, which the rational-numbers chapter establishes.',
        'source' => 'C8 Algebraic Expressions ← C8 Rational Numbers',
    ],
    [
        'concept' => 31407, 'prerequisite' => 31404,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The step-by-step method formalises the monomial-times-binomial case just worked.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31405, 'prerequisite' => 31404,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The trinomial case adds one more term to the binomial product just done.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31406, 'prerequisite' => 31405,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Extending to more monomials continues the same widening.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31408, 'prerequisite' => 31407,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two binomials give four products by applying the distributive step twice.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31409, 'prerequisite' => 31408,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Collecting like terms tidies the four products the expansion produced.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31410, 'prerequisite' => 31409,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Checking by substitution verifies the collected result.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31411, 'prerequisite' => 31408,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A binomial times a trinomial gives six products, extending the four-product case.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31412, 'prerequisite' => 31411,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fewer than six surviving happens when like terms cancel, so the six have to be produced first.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31413, 'prerequisite' => 31412,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The longer simplification exercises the cancelling just observed.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31414, 'prerequisite' => 31413,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Generalising the method comes after it has been carried out on the longer case.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Direct and Inverse Proportions (25860)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31454, 'prerequisite' => 31371,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Scaling a recipe keeps the ingredients in the same ratio, so ratio has to be a usable idea.',
        'source' => 'C8 Direct and Inverse Proportions ← C8 Comparing Quantities',
    ],
    [
        'concept' => 31454, 'prerequisite' => 31453,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The scaling is done on the recipe the chapter opens with.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31457, 'prerequisite' => 31456,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Interest rising with deposit is one of the examples motivating the study of variation.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31458, 'prerequisite' => 31457,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Spending and articles bought is a second example in the same series.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31460, 'prerequisite' => 31458,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sugar and its cost is the case the numerical pattern is read from.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31461, 'prerequisite' => 31460,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That rising together is not enough is the caution drawn from examining the sugar table closely.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31462, 'prerequisite' => 31461,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Human growth is the counterexample that makes the caution concrete — age and height rise together but not proportionally.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31463, 'prerequisite' => 31461,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The threefold-for-threefold condition is what the caution demands instead of mere co-rising.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31464, 'prerequisite' => 31463,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming it direct proportion labels the constant-ratio condition just established.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31465, 'prerequisite' => 31464,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The minute hand is a worked instance of the relation just named.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31466, 'prerequisite' => 31464,
        'type' => 'requires', 'gate' => true,
        'reason' => 'More workers taking less time is introduced by contrast with the direct case.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31467, 'prerequisite' => 31466,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Speed and travel time is a second instance of the opposite-direction pattern.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31468, 'prerequisite' => 31466,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The same job with a different workforce repeats the workers-and-time case with numbers.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31470, 'prerequisite' => 31467,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Faster meaning sooner states the speed-time relation qualitatively.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31471, 'prerequisite' => 31470,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Doubling the speed halving the time is the quantitative form of the same relation.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31472, 'prerequisite' => 31471,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The second case confirms the doubling-halving pattern.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31473, 'prerequisite' => 31472,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Price and quantity is a third instance of the same inverse pattern.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31474, 'prerequisite' => 31473,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming inverse proportion labels the constant-product condition the examples have established.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Introduction to Graphs (25862)
    // The chapter Class 9 Science motion silently depends on.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31498, 'prerequisite' => 31497,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A graph is introduced as the picture that makes data quick to grasp.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31499, 'prerequisite' => 31498,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That a graph beats a table for trends is an argument about the representation just introduced.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31500, 'prerequisite' => 33233,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Pictographs and bar graphs are taught in Class 6; Class 8 lists them as already met before adding the line graph.',
        'source' => 'C8 Introduction to Graphs ← C6 Data Handling and Presentation',
    ],
    [
        'concept' => 31501, 'prerequisite' => 31500,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The line graph is introduced as the new type for continuous change, so the earlier types are the contrast.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31503, 'prerequisite' => 31501,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Turning a table into a line graph presupposes knowing which graph type is wanted.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31504, 'prerequisite' => 31503,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Deciding what each axis carries is the first step of turning the table into a picture.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31505, 'prerequisite' => 31504,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Points can only be plotted and joined once both axes carry known quantities.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31510, 'prerequisite' => 31504,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a value off the scale needs the axis to have been labelled and scaled.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31506, 'prerequisite' => 31505,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A gap in the record is a break in the joined line just drawn.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31507, 'prerequisite' => 31505,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two lines on one pair of axes extends the single-line plot.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31508, 'prerequisite' => 31507,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Distinguishing the lines matters only once two of them share the axes.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31509, 'prerequisite' => 31505,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Judging steadiness means reading the shape of the joined line.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31511, 'prerequisite' => 31509,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A flat stretch means he stopped, which is a particular reading of steadiness.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31512, 'prerequisite' => 31511,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fastest means steepest, which extends the flat-means-stopped reading to slope in general. This is the seed of every velocity-time graph in Class 9 Science.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31513, 'prerequisite' => 31505,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That one quantity affects another is the interpretation the plotted line supports.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31514, 'prerequisite' => 31513,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Independent and dependent name the two roles the dependence just described creates.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31515, 'prerequisite' => 31514,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Showing the relation graphically presupposes knowing which quantity depends on which.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31516, 'prerequisite' => 31514,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The convention that the independent variable goes on the horizontal axis follows from naming the roles.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31517, 'prerequisite' => 31464,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A directly proportional graph passes through the origin precisely because zero of one gives zero of the other, which is the proportion condition.',
        'source' => 'C8 Introduction to Graphs ← C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31517, 'prerequisite' => 31515,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The claim is about the graph of the relation, so the graph has to be drawn first.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 31518, 'prerequisite' => 31515,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a value that is not in the table is interpolation on the plotted relation.',
        'source' => 'C8 Introduction to Graphs',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 into Class 9 Mathematics
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1369, 'prerequisite' => 31403,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Binomial, trinomial and polynomial are named in Class 8; Class 9 restricts to one variable and defines degree on top of that vocabulary.',
        'source' => 'C9 Linear Polynomials ← C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 1396, 'prerequisite' => 31408,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The square of a binomial is the four-product expansion with both binomials the same, so that expansion is how the identity is proved.',
        'source' => 'C9 Algebraic Identities ← C8 Algebraic Expressions and Identities',
    ],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS INTO SCIENCE
    //
    // The links a Science teacher cannot see and a Mathematics teacher
    // has no reason to look for. A learner failing any of the Science
    // concepts below may have no difficulty with the Science at all.
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 7291, 'prerequisite' => 31499,  // Purpose of motion graphs (C9 Sci) ← Better than a table for trends (C8 Maths)
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'Class 9 Science opens its graph section by asserting a graph shows motion better than a table. That argument is made and justified in Class 8 Mathematics, not in Science.',
        'source' => 'C9 Science Describing Motion ← C8 Mathematics Introduction to Graphs',
    ],
    [
        'concept' => 7292, 'prerequisite' => 31505,  // Plotting position-time graph ← Joining the points
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'Plotting points from a table and joining them is a Mathematics skill. Science assumes it and teaches only what the resulting line means.',
        'source' => 'C9 Science Describing Motion ← C8 Mathematics Introduction to Graphs',
    ],
    [
        'concept' => 7295, 'prerequisite' => 31512,  // Slope of position-time graph ← Where he rode fastest
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'That the steepest part of a distance-time line is the fastest stretch is established in Class 8 Mathematics with a cyclist. Class 9 Science calls the same thing the slope and equates it to velocity.',
        'source' => 'C9 Science Describing Motion ← C8 Mathematics Introduction to Graphs',
    ],
    [
        'concept' => 7280, 'prerequisite' => 31371,  // Average speed definition (C9 Sci) ← Ratio compares two quantities
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'Speed is a ratio of two unlike quantities. A learner who cannot form or interpret a ratio reads 60 km/h as a number rather than a rate, whatever they understand about motion.',
        'source' => 'C9 Science Describing Motion ← C8 Mathematics Comparing Quantities',
    ],
    [
        'concept' => 7362, 'prerequisite' => 31373,  // Concentration of a solution (C9 Sci) ← Percentages
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'Concentration is expressed as a mass or volume percentage, so every worked example in that Science section is a percentage calculation.',
        'source' => 'C9 Science Exploring Mixtures ← C8 Mathematics Comparing Quantities',
    ],
    [
        'concept' => 7363, 'prerequisite' => 31373,  // Mass by mass percentage ← Percentages
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'The quantity is literally a percentage, so a learner weak on percentage fails the chemistry arithmetic rather than the chemistry.',
        'source' => 'C9 Science Exploring Mixtures ← C8 Mathematics Comparing Quantities',
    ],
    [
        'concept' => 264, 'prerequisite' => 31464,   // Ohm's Law (C10 Sci) ← Named as direct proportion
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'Ohm\'s law IS a statement of direct proportion. Without the proportional-reasoning idea a learner can only memorise V = IR instead of reading it as a relationship.',
        'source' => 'C10 Science Electricity ← C8 Mathematics Direct and Inverse Proportions',
    ],
    [
        'concept' => 269, 'prerequisite' => 31474,   // Resistors in Parallel (C10 Sci) ← Inversely proportional
        'type' => 'cross_subject', 'gate' => false,
        'reason' => 'Parallel resistance adds reciprocals, which is the inverse-proportion pattern. Recognising it is what stops learners applying the series rule instead.',
        'source' => 'C10 Science Electricity ← C8 Mathematics Direct and Inverse Proportions',
    ],
    [
        'concept' => 31153, 'prerequisite' => 31474, // Pressure is force per unit area (C8 Sci) ← Inversely proportional (C8 Maths)
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'That a sharp knife cuts because pressure rises as area falls is an inverse proportion. Without it the explanation is an assertion the learner has to take on trust.',
        'source' => 'C8 Science Force and Pressure ← C8 Mathematics Direct and Inverse Proportions',
    ],
    [
        'concept' => 7302, 'prerequisite' => 31345,  // Third kinematic equation (C9 Sci) ← Inverse of squaring (C8 Maths)
        'type' => 'cross_subject', 'gate' => false,
        'reason' => 'The third equation of motion is solved for v by taking a square root, so a learner without that operation stops one step short of every answer.',
        'source' => 'C9 Science Describing Motion ← C8 Mathematics Squares and Square Roots',
    ],
    [
        'concept' => 7452, 'prerequisite' => 30541,  // Simple average atomic mass (C9 Sci) ← The mean (C7 Maths)
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'Average atomic mass is a weighted mean over isotopes, so the mean has to be a computable quantity before the chemistry can be done.',
        'source' => 'C9 Science Journey Inside the Atom ← C7 Mathematics Connecting the Dots',
    ],
    [
        'concept' => 8067, 'prerequisite' => 31449,  // Solar energy calculation (C9 Sci) ← Converting both ways (C8 Maths)
        'type' => 'cross_subject', 'gate' => true,
        'reason' => 'The solar constant and the areas involved are quoted in standard form, so converting in and out of it is a precondition for the arithmetic.',
        'source' => 'C9 Science Earth as a System ← C8 Mathematics Exponents and Powers',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Understanding Quadrilaterals (25852)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31290, 'prerequisite' => 31289,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Convex and concave classify polygons, so the polygon has to be defined before it is sorted.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31291, 'prerequisite' => 31289,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Regular and irregular is a second classification of the same object.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31292, 'prerequisite' => 30379,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An exterior angle forms a linear pair with its interior angle, so the Class 7 linear-pair result is what makes the exterior angle computable.',
        'source' => 'C8 Understanding Quadrilaterals ← C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 31293, 'prerequisite' => 31292,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Walking the pentagon adds the exterior angles physically, so the angle has to be identified first.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31294, 'prerequisite' => 31293,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The 360 total is the conclusion the walk demonstrates.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31295, 'prerequisite' => 31289,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A trapezium is a quadrilateral with one pair of parallel sides, so the polygon family frames it.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31296, 'prerequisite' => 31295,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The kite is the next special quadrilateral in the same survey.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31297, 'prerequisite' => 31296,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Seeing a square as a kite requires the kite\'s defining property to be known.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31298, 'prerequisite' => 31289,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Opposite and adjacent are positions within a polygon, so the polygon has to be there to have positions.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31299, 'prerequisite' => 31298,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The angle version of opposite and adjacent follows the side version.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31300, 'prerequisite' => 31298,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The parallelogram property is stated about opposite sides, so that relation has to be nameable.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31301, 'prerequisite' => 31299,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The equal-opposite-angles property needs opposite angles to be identifiable.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31302, 'prerequisite' => 30395,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adjacent angles of a parallelogram are co-interior angles on parallel lines, so the Class 7 parallel-line angle work is the proof.',
        'source' => 'C8 Understanding Quadrilaterals ← C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 31302, 'prerequisite' => 31301,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The supplementary result completes the angle properties begun with the opposite-angle one.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31303, 'prerequisite' => 31302,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding unknown angles applies the properties just established.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31304, 'prerequisite' => 31300,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The diagonal properties are surveyed after the side and angle ones.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31305, 'prerequisite' => 31304,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bisection is the positive diagonal property, stated after the negative one about equality.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31306, 'prerequisite' => 31305,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cut-out demonstrates the bisection just claimed.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31307, 'prerequisite' => 31300,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A rhombus is a parallelogram with all sides equal, so the parallelogram properties come first.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31308, 'prerequisite' => 31307,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The perpendicular-diagonal property is specific to the rhombus just defined.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31309, 'prerequisite' => 31301,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A rectangle is a parallelogram with right angles, so the angle properties are what it specialises.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],
    [
        'concept' => 31310, 'prerequisite' => 31309,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A square is a rectangle with equal sides, so the rectangle is the immediate parent.',
        'source' => 'C8 Understanding Quadrilaterals',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Data Handling (25853)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31312, 'prerequisite' => 31311,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Organising serves the purpose the data was collected for, so the purpose comes first.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31313, 'prerequisite' => 31312,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Graphing is the next step after organising, and is justified by it.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31314, 'prerequisite' => 31313,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The pictograph is the first graph type surveyed.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31315, 'prerequisite' => 31314,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The bar graph follows the pictograph in the same survey.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31316, 'prerequisite' => 31315,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A double bar graph extends the single one to two series.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31317, 'prerequisite' => 31313,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The pie chart is introduced as another way of picturing the organised data.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31318, 'prerequisite' => 31317,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Proportionality of the sectors is the rule that makes the divided circle readable.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31319, 'prerequisite' => 31318,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a pie chart is applying the proportionality rule backwards.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31320, 'prerequisite' => 31373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Turning a percentage into a fraction needs percentage to be defined, which the Comparing Quantities chapter supplies.',
        'source' => 'C8 Data Handling ← C8 Comparing Quantities',
    ],
    [
        'concept' => 31321, 'prerequisite' => 32380,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A central angle is a fraction of the 360-degree full turn, which Class 6 establishes as the convention.',
        'source' => 'C8 Data Handling ← C6 Lines and Angles',
    ],
    [
        'concept' => 31321, 'prerequisite' => 31320,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The angle is that fraction of 360, so the fraction has to be obtained first.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31322, 'prerequisite' => 31321,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drawing the sectors needs the angles to have been computed.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31323, 'prerequisite' => 31322,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Starting from raw figures runs the whole per-cent to angle chain from the beginning.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31325, 'prerequisite' => 31324,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That repeated trials even out is an observation about the everyday chance just introduced.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31326, 'prerequisite' => 31325,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Equal likelihood is the condition under which trials even out as described.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31327, 'prerequisite' => 31326,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The coin is the simplest equally-likely case.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31328, 'prerequisite' => 31327,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The die extends the coin case from two outcomes to six.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31329, 'prerequisite' => 31371,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Probability is written as favourable over total, so ratio has to be a usable form.',
        'source' => 'C8 Data Handling ← C8 Comparing Quantities',
    ],
    [
        'concept' => 31329, 'prerequisite' => 31326,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting favourable over total is only valid when the outcomes are equally likely.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31330, 'prerequisite' => 31329,
        'type' => 'requires', 'gate' => true,
        'reason' => 'An event is what a probability is assigned to, so the measure comes first.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31331, 'prerequisite' => 31330,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Grouping several outcomes into one event extends the single-outcome case.',
        'source' => 'C8 Data Handling',
    ],
    [
        'concept' => 31332, 'prerequisite' => 31331,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The bag of balls is the worked example of a grouped event.',
        'source' => 'C8 Data Handling',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Cubes and Cube Roots (25855)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31358, 'prerequisite' => 31335,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Cubing is introduced as the next power after squaring, so the square is the pattern being extended.',
        'source' => 'C8 Cubes and Cube Roots ← C8 Squares and Square Roots',
    ],
    [
        'concept' => 31356, 'prerequisite' => 31355,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The two decompositions are what make 1729 interesting, so the anecdote frames them.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31359, 'prerequisite' => 31358,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testing whether 9 is a cube applies the definition as a check.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31360, 'prerequisite' => 31359,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Counting the cubes below a thousand repeats the test systematically.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31361, 'prerequisite' => 31358,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The odd-number sum is a property of the cubes just defined.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31362, 'prerequisite' => 31361,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The consecutive-difference pattern follows the odd-number sum in the same family of observations.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31363, 'prerequisite' => 30503,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting each prime three times presupposes a settled prime factorisation, which Class 7 establishes as unique.',
        'source' => 'C8 Cubes and Cube Roots ← C7 Finding Common Ground',
    ],
    [
        'concept' => 31363, 'prerequisite' => 31358,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The triple-factor test is the factorisation criterion for being a cube.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31364, 'prerequisite' => 31363,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Testing by factorisation applies the triple-factor rule.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31366, 'prerequisite' => 31364,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplying to complete a triple fixes a factorisation the test has just shown to be incomplete.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31367, 'prerequisite' => 31366,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing out a stray factor is the mirror-image fix to multiplying one in.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31365, 'prerequisite' => 31366,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cuboid problem is the worked application of completing a triple.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31368, 'prerequisite' => 31358,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cube root undoes cubing, so cubing is the operation being inverted.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31369, 'prerequisite' => 31368,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The symbol notates the operation just defined.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31370, 'prerequisite' => 31369,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Grouping factors in threes is the method for computing the root the symbol names.',
        'source' => 'C8 Cubes and Cube Roots',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Mensuration (25858)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31415, 'prerequisite' => 33141,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Perimeter as the distance round a boundary is Class 6 content; Class 8 uses it for composite figures without redefining it.',
        'source' => 'C8 Mensuration ← C6 Perimeter and Area',
    ],
    [
        'concept' => 31416, 'prerequisite' => 33148,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Area as a count of unit squares is established in Class 6; Class 8 builds every formula on that meaning.',
        'source' => 'C8 Mensuration ← C6 Perimeter and Area',
    ],
    [
        'concept' => 31417, 'prerequisite' => 31416,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recalling the figures already handled presupposes area being defined for them.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31418, 'prerequisite' => 31417,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A path or border is the difference of two known areas, so those have to be computable.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31419, 'prerequisite' => 31417,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Splitting a figure works only if the pieces are shapes whose area is already known.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31420, 'prerequisite' => 31419,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cutting a quadrilateral with two diagonals is one way of applying the splitting method.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31421, 'prerequisite' => 31420,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The one-diagonal method is a second way of dividing the same figure.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31422, 'prerequisite' => 31421,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing between the divisions presupposes both being available.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31423, 'prerequisite' => 31416,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Treating faces as plane figures is what lets the area work transfer to solids.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31424, 'prerequisite' => 31423,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Congruent faces is an observation about the faces just identified.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31426, 'prerequisite' => 31423,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Surface area is the sum of the face areas, so faces have to be measurable plane figures.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31427, 'prerequisite' => 31426,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The net lays the faces flat so the sum can be taken, so the sum is what it is for.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31428, 'prerequisite' => 31427,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Three identical pairs is read off the cuboid net.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31429, 'prerequisite' => 31428,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cube is the special case where all six faces coincide, so the cuboid result comes first.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31430, 'prerequisite' => 31427,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The cylinder net is the same unfolding idea applied to a curved surface.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31431, 'prerequisite' => 31426,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting unit cubes is the volume counterpart of counting unit squares for area.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31432, 'prerequisite' => 31431,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That rearranging the cubes leaves the volume unchanged is the invariance the count establishes.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31433, 'prerequisite' => 31432,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cube as a special cuboid follows once the cuboid volume is settled.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31434, 'prerequisite' => 31432,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Base area times height is the shorthand for the cube count just made.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31435, 'prerequisite' => 31434,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Distinguishing volume from surface area needs both to have been computed.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31436, 'prerequisite' => 31434,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Litres and cubic centimetres are units for the volume just defined.',
        'source' => 'C8 Mensuration',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 · Factorisation (25861)
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 31476, 'prerequisite' => 31475,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing an expression as a product generalises terms being products of factors.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31477, 'prerequisite' => 31476,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saying what counts as a factor sharpens the product form just introduced.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31478, 'prerequisite' => 31477,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Visible factors are the easy case of the factor idea just defined.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31479, 'prerequisite' => 31478,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The hidden case is defined by contrast with the visible one.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31480, 'prerequisite' => 31479,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rewriting as a product is the work the hidden case demands.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31481, 'prerequisite' => 31480,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Irreducibility says when the rewriting has gone as far as it can.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31482, 'prerequisite' => 31481,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Taking out the common factor is the first method for reaching irreducible factors.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31483, 'prerequisite' => 31482,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That the answer is a product is a check on the common-factor method.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31484, 'prerequisite' => 31482,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Grouping is the method used when no factor is common to every term, so the simpler method comes first.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31485, 'prerequisite' => 31484,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That any workable grouping gives the same answer is a claim about the grouping method.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31486, 'prerequisite' => 31484,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Observing before applying is the habit the grouping work teaches.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31487, 'prerequisite' => 31408,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recognising an expression as one side of an identity requires having expanded that identity forwards first.',
        'source' => 'C8 Factorisation ← C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31487, 'prerequisite' => 31486,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading factors off an identity is the third method, chosen by the observation habit just built.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31495, 'prerequisite' => 31487,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Applying two identities in turn extends the single-identity method.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31496, 'prerequisite' => 31487,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Handling the sign of the middle term is a refinement of reading factors off an identity.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31489, 'prerequisite' => 31488,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Algebraic division is introduced by following the numerical division the learner already does.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31490, 'prerequisite' => 31489,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Monomial by monomial is the simplest case of the division just introduced.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31491, 'prerequisite' => 31490,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing a polynomial by a monomial repeats the monomial case term by term.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31492, 'prerequisite' => 31491,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why factorising helps is argued once division has been attempted without it.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31493, 'prerequisite' => 31492,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Factorise-then-divide is the method the previous argument recommends.',
        'source' => 'C8 Factorisation',
    ],
    [
        'concept' => 31494, 'prerequisite' => 31493,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Mixed factor types exercise the factorise-then-divide routine.',
        'source' => 'C8 Factorisation',
    ],

    // ══════════════════════════════════════════════════════════════════
    // C8 into Class 9 and 10
    // ══════════════════════════════════════════════════════════════════

    [
        'concept' => 1442, 'prerequisite' => 31329,  // What is Probability? (C9) ← Probability as a ratio (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Probability as favourable over total is defined in Class 8; Class 9 opens by distinguishing experimental from theoretical and assumes the ratio form.',
        'source' => 'C9 Probability ← C8 Data Handling',
    ],
    [
        'concept' => 1448, 'prerequisite' => 31330,  // Events (C9) ← What an event is (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The event is named in Class 8; Class 9 places it formally as a subset of the sample space.',
        'source' => 'C9 Probability ← C8 Data Handling',
    ],
    [
        'concept' => 1401, 'prerequisite' => 31487,  // Factorisation Using Identities (C9) ← Reading factors off the identity (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Factorising by recognising an identity is taught in Class 8; Class 9 adds the cube identities to the same technique.',
        'source' => 'C9 Algebraic Identities ← C8 Factorisation',
    ],
    [
        'concept' => 21812, 'prerequisite' => 31496, // Splitting the Middle Term (C9) ← Signs in the middle term (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Handling the middle-term sign is Class 8 work; Class 9 turns it into the systematic splitting method.',
        'source' => 'C9 Algebraic Identities ← C8 Factorisation',
    ],
    [
        'concept' => 24712, 'prerequisite' => 31423, // The basic solids (C10) ← Faces are plane figures (C8)
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Cuboids, cubes and cylinders and their faces are established in Class 8; Class 10 combines them and assumes each one is understood.',
        'source' => 'C10 Surface Areas and Volumes ← C8 Mensuration',
    ],
    [
        'concept' => 24717, 'prerequisite' => 31430, // Adding the curved surface areas (C10) ← A cylinder unrolls (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'That a cylinder unrolls into a rectangle is the Class 8 derivation of curved surface area, reused without restatement.',
        'source' => 'C10 Surface Areas and Volumes ← C8 Mensuration',
    ],
    [
        'concept' => 24719, 'prerequisite' => 31434, // Volumes simply add (C10) ← Base area times height (C8)
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Volume as base area times height is Class 8; Class 10 adds such volumes for combined solids.',
        'source' => 'C10 Surface Areas and Volumes ← C8 Mensuration',
    ],


    // ==================================================================
    // Class 6 - the remaining chapters
    // ==================================================================

    [
        'concept' => 32350, 'prerequisite' => 32349,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Calling mathematics both an art and a science is a claim about the search for patterns just introduced.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32351, 'prerequisite' => 32350,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Why an explanation carries from one case to every case is the scientific half of the claim just made.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32352, 'prerequisite' => 32349,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Number theory is named as the branch that studies patterns in numbers, so the pattern idea comes first.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32353, 'prerequisite' => 32352,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Table 1 is the working list of number sequences that number theory is introduced through.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32354, 'prerequisite' => 32353,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Stating a rule in your own words presupposes a sequence to state the rule for.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32355, 'prerequisite' => 32354,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Turning to pictures is motivated by rules that are hard to state in words alone.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32356, 'prerequisite' => 32355,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The seven dot pictures are the worked instance of making a pattern visible.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32357, 'prerequisite' => 32356,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drawing the next picture continues a sequence the learner must already be able to read.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32358, 'prerequisite' => 32357,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That consecutive odd numbers total a square is spotted by extending the dot pictures.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32359, 'prerequisite' => 32358,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The explaining picture is a picture of the odd-number result, so the result comes first.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32359, 'prerequisite' => 32351,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A picture counts as an explanation only once the learner accepts that an argument can cover every case.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32360, 'prerequisite' => 32359,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The up-and-down sum is a second pattern explained by the same kind of picture.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32361, 'prerequisite' => 32352,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Patterns in shapes are introduced as the geometric counterpart of the number patterns just studied.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32362, 'prerequisite' => 32361,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Sorting shapes by dimension is the first move once geometry is framed as the study of patterns.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32363, 'prerequisite' => 32362,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Table 3 lists shape sequences, which can be read only once shapes are classified.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32364, 'prerequisite' => 32363,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting sides is the numerical reading of the shape sequences in the table.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32365, 'prerequisite' => 32364,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What regular means is settled after the polygons have been counted and compared.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32366, 'prerequisite' => 32365,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The Koch snowflake is built from regular triangles, so regularity has to be defined first.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32366, 'prerequisite' => 32357,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Counting the snowflake means continuing a picture sequence one more step.',
        'source' => 'C6 Patterns in Mathematics',
    ],
    [
        'concept' => 32376, 'prerequisite' => 32375,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The rotating-arm model is built to show what arm length being irrelevant actually means.',
        'source' => 'C6 Lines and Angles',
    ],
    [
        'concept' => 32387, 'prerequisite' => 32386,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking which sequences are possible presupposes the taller-neighbour count being defined.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32388, 'prerequisite' => 32386,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A supercell is defined by comparison with its neighbours, the comparison the opening activity sets up.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33286, 'prerequisite' => 32388,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting how many supercells are possible applies the definition just given.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32389, 'prerequisite' => 32388,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Designing a grid of supercells presupposes knowing what makes a cell super.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33287, 'prerequisite' => 32389,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Filling alternately for the maximum is the strategy the grid design problem asks for.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32390, 'prerequisite' => 32386,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'The number line is brought in as a second way of placing and comparing the same numbers.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32391, 'prerequisite' => 32390,
        'type' => 'builds_on', 'gate' => false,
        'reason' => 'Digit sums and digit counts look inside the numbers the line has been placing.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33288, 'prerequisite' => 32391,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding numbers whose digits add to fourteen applies the digit sum.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33289, 'prerequisite' => 32391,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The consecutive-digit result is a statement about digit sums.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32392, 'prerequisite' => 32391,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A palindrome is read off a number\'s digits, so digits have to be the unit of attention.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32393, 'prerequisite' => 32392,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reverse-and-add aims at a palindrome, so the target has to be defined first.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32394, 'prerequisite' => 32393,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Kaprekar is introduced as the mathematician behind these digit routines.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32395, 'prerequisite' => 32394,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reaching 6174 is Kaprekar\'s own routine, and is named after him.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33290, 'prerequisite' => 32395,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting the rounds for 5683 runs the 6174 routine on one number.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32396, 'prerequisite' => 32392,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Patterned times are palindromes and repetitions read off a clock.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32397, 'prerequisite' => 32396,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Patterned dates extend the clock patterns to the calendar.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32398, 'prerequisite' => 32391,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Making a target from a fixed set of digits is another problem about digits.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33291, 'prerequisite' => 32398,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking which thousands cannot be made is the impossibility half of the target problem.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32399, 'prerequisite' => 32398,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Always, sometimes or never is asked about the claims the target problem throws up.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32400, 'prerequisite' => 32399,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Looking for a quicker way follows once a claim has been tested case by case.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32401, 'prerequisite' => 32400,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Collatz is offered as a routine nobody has yet found a quicker way through.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32402, 'prerequisite' => 32390,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Paromita\'s estimate is a judgement about where a number sits, which the number line supports.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32403, 'prerequisite' => 32402,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Estimating what cannot be counted generalises the single worked estimate.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33292, 'prerequisite' => 32403,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Judging an estimate reasonable presupposes being able to make one.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33293, 'prerequisite' => 33292,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Checking 13,000 hours against the school calendar is the worked reasonableness test.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32404, 'prerequisite' => 32399,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The game of 21 is won by an always-true strategy of the kind just examined.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 32405, 'prerequisite' => 32404,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Inventing a game of the same kind requires having analysed one.',
        'source' => 'C6 Number Play',
    ],
    [
        'concept' => 33230, 'prerequisite' => 33229,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A table with one row per answer is how the responses just collected are recorded.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33246, 'prerequisite' => 33230,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Data and frequency are named once there is a filled table to name them in.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33231, 'prerequisite' => 33230,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding the commonest tree means reading the frequency table.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33232, 'prerequisite' => 33230,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Counting letters in a passage is a second tally exercise of the same shape.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33234, 'prerequisite' => 33230,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A pictograph pictures the tallied table, so the table has to be filled first.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33235, 'prerequisite' => 33234,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Choosing the symbol value is a decision about the pictograph being drawn.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33236, 'prerequisite' => 33235,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Half symbols are needed when a count does not divide by the chosen symbol value.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33247, 'prerequisite' => 33236,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The Bhopal ticket answers use part-symbols for counts that do not divide evenly.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33237, 'prerequisite' => 33230,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bar length stands for a tallied count, so the table has to be filled first.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33238, 'prerequisite' => 33237,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a value off the scale presupposes knowing what the bar\'s length means.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33250, 'prerequisite' => 33238,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Inferring the scale is reading the axis backwards from values already known.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33239, 'prerequisite' => 33237,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Equal widths and uniform gaps are the drawing rules that keep bar length comparable.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33242, 'prerequisite' => 33239,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the gaps mean separate categories explains the drawing rule just given.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33248, 'prerequisite' => 33238,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The traffic answers are read off the bar graph scale.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33249, 'prerequisite' => 33238,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Totalling the wickets means reading each bar and adding.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33240, 'prerequisite' => 33237,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What a graph answers faster is argued once a graph can be read.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33241, 'prerequisite' => 33240,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing two presentations of one table follows from knowing what each is for.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33241, 'prerequisite' => 33234,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The comparison is between a pictograph and a bar graph, so the pictograph must be available.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33243, 'prerequisite' => 33241,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That appearance changes the reading is the conclusion of comparing two presentations.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33244, 'prerequisite' => 33243,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A realistic but inaccurate picture is the clearest case of appearance misleading.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33245, 'prerequisite' => 33244,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Area growing faster than height explains exactly why the realistic picture misleads.',
        'source' => 'C6 Data Handling and Presentation',
    ],
    [
        'concept' => 33120, 'prerequisite' => 33119,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The treasure jumps are the same multiples game played along a track.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33135, 'prerequisite' => 33119,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The answer counts belong to the circle game just played.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33126, 'prerequisite' => 33125,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The thread art closes early unless the peg counts share no common factor, which is the co-prime test.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33133, 'prerequisite' => 33122,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What makes a number special is asked against the two-factor definition of a prime.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33134, 'prerequisite' => 33123,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Filling a grid with primes needs a way of telling primes apart, which the sieve gives.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33136, 'prerequisite' => 33125,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Shading multiples of three and four shows that numbers sharing no factor first coincide at their product.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33137, 'prerequisite' => 33124,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Judging claims about primes presupposes understanding why only primes survive the sieve.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33139, 'prerequisite' => 33132,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Eight divides a number according to its last three digits, which is the test just given.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33140, 'prerequisite' => 33130,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The always-sometimes-never claims are about divisibility, which the multiplicity rule settles.',
        'source' => 'C6 Prime Time',
    ],
    [
        'concept' => 33144, 'prerequisite' => 33143,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing two tracks means totalling each track\'s sides, which the triangle perimeter teaches.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33145, 'prerequisite' => 33144,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A common finishing line is the fair-comparison version of the two-track problem.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33146, 'prerequisite' => 33141,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Estimating a boundary presupposes knowing what the boundary length is.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33149, 'prerequisite' => 33147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Land left over is a difference of areas, each counted on the grid.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33155, 'prerequisite' => 33147,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Recovering a missing room dimension works back from the area, which the grid count supplies.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33157, 'prerequisite' => 33153,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bending one wire into several shapes is the fixed-perimeter idea the nine-squares activity establishes.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33158, 'prerequisite' => 33142,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Distance over several rounds is the frame perimeter multiplied by the number of rounds.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33159, 'prerequisite' => 33152,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading areas off the figures uses the half-a-rectangle result for the triangular parts.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33160, 'prerequisite' => 33150,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Adding Charan\'s rooms to the plot total rests on areas being addable, which overlaying shows.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33161, 'prerequisite' => 33159,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The area-maze answers continue reading areas off figures.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33162, 'prerequisite' => 33154,
        'type' => 'requires', 'gate' => true,
        'reason' => 'One area with several perimeters is the converse of the rising-falling-steady investigation.',
        'source' => 'C6 Perimeter and Area',
    ],
    [
        'concept' => 33168, 'prerequisite' => 33167,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting fractional units in a quantity reads off the marks the number line supplies.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33179, 'prerequisite' => 33175,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking how one can be made from thirds is an addition question about like fractions.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33180, 'prerequisite' => 33163,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The guava and rice answers are sharing problems of the kind the opening activity sets up.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33181, 'prerequisite' => 33167,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Drawing lines of a given fractional length uses the marked number line.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33182, 'prerequisite' => 33166,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing a share as a division fact needs numerator and denominator to be named.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33183, 'prerequisite' => 33172,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Deciding which group gets more is a comparison, and a comparison needs a common unit.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33184, 'prerequisite' => 33178,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Explaining why a common denominator helps restates why denominators are not added.',
        'source' => 'C6 Fractions',
    ],
    [
        'concept' => 33187, 'prerequisite' => 33186,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Where to place the tip only matters once the compass is set to a radius.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33199, 'prerequisite' => 33187,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two arcs cross only where both centres have been chosen deliberately.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33194, 'prerequisite' => 33186,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Transferring a length without a ruler is what a set compass opening is for.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33195, 'prerequisite' => 33187,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A uniform bulge is the shape an arc takes about a fixed tip.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33200, 'prerequisite' => 33199,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The house is finished at the point where the two arcs cross.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33200, 'prerequisite' => 33195,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The roof arcs are the uniform bulges just drawn.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33188, 'prerequisite' => 32379,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A square is defined by equal sides and right angles, so the right angle has to come from Lines and Angles.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33189, 'prerequisite' => 33188,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That rotation preserves sides and angles is a claim about the two defining properties.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33190, 'prerequisite' => 33188,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A construction is planned from the properties that define the figure.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33190, 'prerequisite' => 33187,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The plan is carried out by placing the compass tip at each chosen centre.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33203, 'prerequisite' => 33190,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Naming and drawing rectangles applies the properties-to-construction routine.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33201, 'prerequisite' => 33190,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A figure with right angles but unequal sides is built by relaxing one of the properties used.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33191, 'prerequisite' => 33190,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sliding two points inside the figure presupposes the figure having been constructed.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33192, 'prerequisite' => 33191,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Predicting before measuring is the habit the sliding-points investigation trains.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33193, 'prerequisite' => 33190,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cutting a rectangle into equal squares starts from a rectangle built by construction.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33204, 'prerequisite' => 33193,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Three squares in a row is the worked case of splitting a rectangle into squares.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33196, 'prerequisite' => 33190,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drawing the diagonals presupposes the figure already being on the page.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33205, 'prerequisite' => 33196,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That equal halves force a square is a conclusion about how the diagonals split the angles.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33197, 'prerequisite' => 33196,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Locating the fourth point uses the diagonal as one of the two routes.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33198, 'prerequisite' => 33197,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Being given a side and a diagonal is the case the two routes were developed for.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33202, 'prerequisite' => 33198,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The same problem in another guise re-presents the side-and-diagonal construction.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33206, 'prerequisite' => 33197,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Finding the centre of the hole is a fourth-point construction in disguise.',
        'source' => 'C6 Playing with Constructions',
    ],
    [
        'concept' => 33208, 'prerequisite' => 33207,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting a square\'s folds presupposes knowing what a fold line of symmetry is.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33209, 'prerequisite' => 33207,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Where a corner goes under reflection is a question about the mirror line.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33210, 'prerequisite' => 33207,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A folded ink blot comes out symmetric because the fold is a mirror line.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33211, 'prerequisite' => 33208,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Cutting a folded sheet extends the folding the square activity uses.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33212, 'prerequisite' => 33208,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Drawing a figure to a stated number of lines reverses the counting just practised.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33222, 'prerequisite' => 33208,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The rangoli and butterfly answers are line counts of the same kind.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33223, 'prerequisite' => 33211,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Predicting a punched hole means predicting where the fold sends it.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33224, 'prerequisite' => 33212,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ruling out a triangle with exactly two lines is a claim about how many lines a figure can have.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33227, 'prerequisite' => 33224,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Four sketches separating the kinds of triangle follow from the two-line result.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33219, 'prerequisite' => 33212,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That every diameter is a line of symmetry is the limiting case of counting lines.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33213, 'prerequisite' => 33207,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The windmill is introduced as a figure with no fold line at all, which needs the fold line defined.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33214, 'prerequisite' => 33213,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A centre of rotation is what the windmill has instead of a mirror line.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33216, 'prerequisite' => 33214,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rotating a cutout to check tests the centre just marked.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33217, 'prerequisite' => 33216,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What the overlap tells you interprets the result of the rotation test.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33215, 'prerequisite' => 33214,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Changing the angles between the arms moves them about the marked centre.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33218, 'prerequisite' => 33217,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the turning angles are all multiples of the smallest is read from the overlaps.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33218, 'prerequisite' => 32380,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The turning angles are fractions of the full 360-degree turn defined in Lines and Angles.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33225, 'prerequisite' => 33218,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A smallest angle that is not whole is the awkward case of the multiples rule.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33226, 'prerequisite' => 33218,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Figures with more than one angle of rotation are explained by the multiples rule.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33221, 'prerequisite' => 33217,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That the two symmetries are independent is argued from what the rotation overlap shows.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33221, 'prerequisite' => 33212,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Independence is a claim about line symmetry too, so line counts have to be available.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33220, 'prerequisite' => 33217,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The tiling game turns pieces about a point, which is what the overlap test establishes.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33228, 'prerequisite' => 33221,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Finding symmetry outside the textbook means recognising either kind on its own.',
        'source' => 'C6 Symmetry',
    ],
    [
        'concept' => 33261, 'prerequisite' => 33256,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A grid whose rows and columns must agree is filled with integers, so the integers have to be defined.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33262, 'prerequisite' => 33261,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That there is more than one filling is an observation about the agreeing grid.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33264, 'prerequisite' => 33258,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Brahmagupta\'s rule is the historical statement of rewriting subtraction as addition.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33265, 'prerequisite' => 33259,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The cut-outs are the tokens the zero-pair activities are carried out with.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33266, 'prerequisite' => 33257,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Leaving an answer as an expression follows from sketching a sum without marking it off.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33267, 'prerequisite' => 33255,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding the movement required is the start-plus-movement rule solved backwards.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33268, 'prerequisite' => 33263,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Accepting other correct methods presupposes the rules they have to agree with.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 33269, 'prerequisite' => 33260,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A closing balance is the total of credits and debits once their signs are fixed.',
        'source' => 'C6 The Other Side of Zero',
    ],
    [
        'concept' => 31314, 'prerequisite' => 33235,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Choosing what one symbol stands for is the Class 6 pictograph decision, reused in Class 8 without restatement.',
        'source' => 'C8 Data Handling <- C6 Data Handling and Presentation',
    ],
    [
        'concept' => 31315, 'prerequisite' => 33239,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Equal bar widths and uniform gaps are settled in Class 6; Class 8 assumes the drawing rules.',
        'source' => 'C8 Data Handling <- C6 Data Handling and Presentation',
    ],
    [
        'concept' => 31313, 'prerequisite' => 33240,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Why data is graphed at all is argued in Class 6; Class 8 takes it as the reason for the whole chapter.',
        'source' => 'C8 Data Handling <- C6 Data Handling and Presentation',
    ],


    // ==================================================================
    // Class 7 - the remaining chapters, and the Class 6 spirals into them
    // ==================================================================

    [
        'concept' => 30288, 'prerequisite' => 30287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing a lakh with a population presupposes knowing how large a lakh is.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30289, 'prerequisite' => 30288,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Whether a lakh is big or small is answered by the comparison just made.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30290, 'prerequisite' => 30287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing in the Indian system is writing numbers of the size just introduced.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30291, 'prerequisite' => 30290,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The one-button calculator is a puzzle about building written numbers.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30292, 'prerequisite' => 30291,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting presses presupposes the restricted calculator being understood.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30293, 'prerequisite' => 30292,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Asking for the fewest clicks is the optimisation of the count just made.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30294, 'prerequisite' => 30293,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the fewest clicks read off the place values is the conclusion the puzzle is for.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30295, 'prerequisite' => 30287,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A crore is introduced as the next step up from a lakh.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30296, 'prerequisite' => 30295,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The comma positions mark off lakhs and crores, so both have to be named.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30296, 'prerequisite' => 30290,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Commas are a convention of the Indian system of writing numbers.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30297, 'prerequisite' => 30296,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The American system is compared against the Indian comma placement.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30298, 'prerequisite' => 30296,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A large number is read aloud group by group, which is what the commas mark.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30299, 'prerequisite' => 30298,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Deciding an estimate is enough follows from having to state an exact figure first.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30300, 'prerequisite' => 30299,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rounding to a chosen place is how the estimate just called for is produced.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30300, 'prerequisite' => 30294,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Rounding to the nearest lakh means knowing which digit holds that place.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30301, 'prerequisite' => 30300,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the chosen place changes the error is a claim about the rounding just done.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30302, 'prerequisite' => 30294,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The multiplication patterns are patterns in place value.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30303, 'prerequisite' => 30302,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Predicting the digit count of a product generalises the multiplication patterns.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30304, 'prerequisite' => 30301,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Checking a quoted fact means calculating it and accepting a rounding error.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30305, 'prerequisite' => 30304,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Which questions are worth calculating follows from having checked one.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30306, 'prerequisite' => 30305,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Large numbers in real contexts is where the worth-calculating questions come from.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30306, 'prerequisite' => 30289,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Using a large number in context needs a sense of whether it is big or small.',
        'source' => 'C7 Large Numbers Around Us',
    ],
    [
        'concept' => 30299, 'prerequisite' => 32403,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Estimating what cannot be counted is Class 6 work; Class 7 asks only when the estimate is enough.',
        'source' => 'C7 Large Numbers Around Us <- C6 Number Play',
    ],
    [
        'concept' => 30309, 'prerequisite' => 30307,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saying an expression describes a situation presupposes knowing what an expression is.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30311, 'prerequisite' => 30310,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That subtraction is not symmetric is a comparison result about two expressions.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30323, 'prerequisite' => 30318,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reasoning instead of memorising is possible once terms, not rules, decide the order.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30324, 'prerequisite' => 30316,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Changing one term presupposes the expression being split into terms.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30325, 'prerequisite' => 30314,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding brackets to reach a value uses brackets to force an order.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30326, 'prerequisite' => 30325,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Engineering an expression to a target is the general form of adding brackets to reach a value.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30327, 'prerequisite' => 30310,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing without computing is still a comparison, so the comparison signs have to be known.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30327, 'prerequisite' => 30319,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Comparing without computing works by recognising the same terms rearranged.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30328, 'prerequisite' => 30322,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Brackets after a plus sign is the easy case, settled after the minus sign case that changes signs.',
        'source' => 'C7 Arithmetic Expressions',
    ],
    [
        'concept' => 30332, 'prerequisite' => 30331,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Measuring a pencil in tenths presupposes the tenth being defined.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30334, 'prerequisite' => 30333,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Measuring a sheet to hundredths presupposes the hundredth being defined.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30338, 'prerequisite' => 30337,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing a measurement as a decimal is the unit conversion just practised, written down.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30340, 'prerequisite' => 30339,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Dividing the gap between 1 and 1.1 is subdividing the number line already marked.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30344, 'prerequisite' => 30336,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Adding decimals in a real situation needs each decimal to be read by its places first.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30349, 'prerequisite' => 30343,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A decimal error causes a disaster because two values that look close are not.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30350, 'prerequisite' => 30335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That decimals continue whole-number place value is the point of the places to the right.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30351, 'prerequisite' => 30343,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Ordering several lengths repeats the comparison of two.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30352, 'prerequisite' => 30337,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Units convert cleanly because the conversions are powers of ten, which the conversion work shows.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30352, 'prerequisite' => 30350,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Clean conversion is a consequence of the decimal system being place value continued.',
        'source' => 'C7 A Peek Beyond the Point',
    ],
    [
        'concept' => 30330, 'prerequisite' => 33165,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Splitting a unit into equal parts is the Class 6 fraction strip, reused as the basis of the decimal.',
        'source' => 'C7 A Peek Beyond the Point <- C6 Fractions',
    ],
    [
        'concept' => 30339, 'prerequisite' => 33167,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Marking lengths on the number line is Class 6 work; Class 7 marks decimals on the same line.',
        'source' => 'C7 A Peek Beyond the Point <- C6 Fractions',
    ],
    [
        'concept' => 30354, 'prerequisite' => 30353,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why letters make a relation concise presupposes a letter standing for a number.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30358, 'prerequisite' => 30357,
        'type' => 'requires', 'gate' => true,
        'reason' => 'What was confusing before is exactly reading an expression as a sum of terms.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30362, 'prerequisite' => 30356,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Evaluating 7k and 5m plus 3 is substitution carried out.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30363, 'prerequisite' => 30361,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using a letter for position is how the nth term is written.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30368, 'prerequisite' => 30357,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming terms and letter-numbers sharpens the sum-of-terms reading.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30369, 'prerequisite' => 30356,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A number machine takes an input and returns a value, which is substitution.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30375, 'prerequisite' => 30373,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That different patterns give different expressions compares expressions already written.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30376, 'prerequisite' => 30359,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carrying arithmetic habits across is justified by swapping and grouping still holding.',
        'source' => 'C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 30361, 'prerequisite' => 32354,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Stating a sequence rule in words is Class 6 work; Class 7 writes the same rule as an nth term.',
        'source' => 'C7 Expressions Using Letter-Numbers <- C6 Patterns in Mathematics',
    ],
    [
        'concept' => 30372, 'prerequisite' => 32357,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Continuing a picture sequence is Class 6 work; Class 7 asks for the rule behind it.',
        'source' => 'C7 Expressions Using Letter-Numbers <- C6 Patterns in Mathematics',
    ],
    [
        'concept' => 30381, 'prerequisite' => 30380,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Proving rather than measuring is argued on the vertically opposite angle result.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30384, 'prerequisite' => 30377,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Describing precisely how segments meet refines the one-point crossing.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30386, 'prerequisite' => 30385,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Folding to make parallels presupposes knowing what parallel means.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30386, 'prerequisite' => 30383,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The same fold produces the right angles, so perpendicularity has to be defined.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30387, 'prerequisite' => 30385,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The notation abbreviates the parallel and perpendicular relations already defined.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30393, 'prerequisite' => 30392,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why the construction is trusted is a question about the construction just carried out.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30393, 'prerequisite' => 30391,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The construction is trusted because equal corresponding angles force parallelism.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30396, 'prerequisite' => 30391,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The angle test that beats the eye is the corresponding-angle test.',
        'source' => 'C7 Parallel and Intersecting Lines',
    ],
    [
        'concept' => 30378, 'prerequisite' => 32372,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Vertex, arms and naming are Class 6 work; Class 7 counts the four angles at a crossing without redefining an angle.',
        'source' => 'C7 Parallel and Intersecting Lines <- C6 Lines and Angles',
    ],
    [
        'concept' => 30379, 'prerequisite' => 32378,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'The straight angle is defined in Class 6; the linear pair result is that definition applied at a crossing.',
        'source' => 'C7 Parallel and Intersecting Lines <- C6 Lines and Angles',
    ],
    [
        'concept' => 30383, 'prerequisite' => 32379,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Right angles and perpendicular arms are Class 6 work; Class 7 names perpendicular lines from them.',
        'source' => 'C7 Parallel and Intersecting Lines <- C6 Lines and Angles',
    ],
    [
        'concept' => 30398, 'prerequisite' => 30397,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rebuilding an arrangement from its numbers presupposes the numbers describing position.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30400, 'prerequisite' => 30399,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The odd-plus-odd result follows the even-plus-even one in the same investigation.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30401, 'prerequisite' => 30400,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Parity is named once both sum results have been observed.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30402, 'prerequisite' => 30401,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Using parity to prove impossibility presupposes parity being named.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30403, 'prerequisite' => 30402,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Bounding what a grid can hold is a first impossibility argument of this kind.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30404, 'prerequisite' => 30403,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the row sums total 45 is the specific bound for this grid.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30405, 'prerequisite' => 30404,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The magic sum is derived from the total the rows must make.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30406, 'prerequisite' => 30405,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That every line totals 15 follows from the magic sum just computed.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30408, 'prerequisite' => 30407,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Long and short syllables are the setting the sequence was first found in.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30409, 'prerequisite' => 30408,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Counting rhythms as sums of ones and twos restates the two syllable lengths.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30410, 'prerequisite' => 30409,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The two-term rule is discovered by counting the rhythms systematically.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30412, 'prerequisite' => 30411,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Pinning down the units digit presupposes letters standing for unknown digits.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30413, 'prerequisite' => 30412,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carrying constrains the next digit only once the units digit is fixed.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30414, 'prerequisite' => 30413,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cryptarithms as a class of puzzle generalises the digit-by-digit deduction.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30411, 'prerequisite' => 30353,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A letter standing for an unknown digit is the letter-number of the algebra chapter, narrowed to one digit.',
        'source' => 'C7 Number Play',
    ],
    [
        'concept' => 30397, 'prerequisite' => 32386,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Reading a number as a position rather than a quantity is the Class 6 taller-neighbour count generalised.',
        'source' => 'C7 Number Play <- C6 Number Play',
    ],
    [
        'concept' => 30410, 'prerequisite' => 32353,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The sequence appears in Class 6 Table 1; Class 7 states the rule that generates it.',
        'source' => 'C7 Number Play <- C6 Patterns in Mathematics',
    ],
    [
        'concept' => 30419, 'prerequisite' => 30417,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why the crossing point is right is a question about the arcs just drawn.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30422, 'prerequisite' => 30418,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two sides with the included angle is a second way of locating the third vertex.',
        'source' => 'C7 A Tale of Three Intersecting Lines',
    ],
    [
        'concept' => 30415, 'prerequisite' => 32365,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'What regular means - equal sides and equal angles - is Class 6 work; the equilateral triangle is its three-sided case.',
        'source' => 'C7 A Tale of Three Intersecting Lines <- C6 Patterns in Mathematics',
    ],
    [
        'concept' => 30417, 'prerequisite' => 33199,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Locating a point where two arcs cross is the Class 6 construction, applied here to a vertex.',
        'source' => 'C7 A Tale of Three Intersecting Lines <- C6 Playing with Constructions',
    ],
    [
        'concept' => 30425, 'prerequisite' => 32379,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The right angle is defined in Class 6; an altitude is that angle dropped from a vertex.',
        'source' => 'C7 A Tale of Three Intersecting Lines <- C6 Playing with Constructions',
    ],
    [
        'concept' => 30452, 'prerequisite' => 30451,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting a mixed fraction first is a refinement of taking a fraction of a whole number.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30455, 'prerequisite' => 30454,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That order does not matter is a property of the general rule just stated.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30459, 'prerequisite' => 30458,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Writing the problem as a multiplication is the reciprocal rule applied.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30460, 'prerequisite' => 30459,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The ancient problem is solved by the same rewriting.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30461, 'prerequisite' => 30453,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Taking the whole as one square unit is the area picture set up deliberately.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30462, 'prerequisite' => 30461,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Each region being a fraction of its container reads the square unit picture.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30463, 'prerequisite' => 30462,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplying the steps together totals the nested fractions just identified.',
        'source' => 'C7 Working with Fractions',
    ],
    [
        'concept' => 30450, 'prerequisite' => 33175,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Adding like fractions is Class 6 work; a whole number times a fraction is that addition repeated.',
        'source' => 'C7 Working with Fractions <- C6 Fractions',
    ],
    [
        'concept' => 30468, 'prerequisite' => 30467,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the angles need not be measured is the content of three sides determining a triangle.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30469, 'prerequisite' => 30467,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The two crossing points are where the third vertex can fall once the sides are fixed.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30471, 'prerequisite' => 30470,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Every classmate building the same triangle is what equal sides round an equal angle guarantee.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30473, 'prerequisite' => 30472,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Constructing shows the failure when the angle is not the included one.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30475, 'prerequisite' => 30474,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The construction settles whether two equal angles on a side are enough.',
        'source' => 'C7 Geometric Twins',
    ],
    [
        'concept' => 30483, 'prerequisite' => 30482,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Guess and adjust is a method for the sum-and-difference problem just posed.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30484, 'prerequisite' => 30483,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That swapping flips the sign is noticed while adjusting the guesses.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30485, 'prerequisite' => 30484,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The carrom coin gives the sign flip a physical reading.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30493, 'prerequisite' => 30492,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The historical rules are the rules for like and unlike signs just derived.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30497, 'prerequisite' => 30496,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Real situations exercise the sign rules for both operations.',
        'source' => 'C7 Operations with Integers',
    ],
    [
        'concept' => 30485, 'prerequisite' => 33253,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'A move that undoes a journey is the Class 6 model; the carrom coin is the same movement with a sign.',
        'source' => 'C7 Operations with Integers <- C6 The Other Side of Zero',
    ],
    [
        'concept' => 30486, 'prerequisite' => 33259,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Zero pairs are introduced in Class 6; the bag of tokens uses them to model multiplication.',
        'source' => 'C7 Operations with Integers <- C6 The Other Side of Zero',
    ],
    [
        'concept' => 30490, 'prerequisite' => 33260,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Credits positive and debits negative is Class 6 work; fortune and debt renames it for multiplication.',
        'source' => 'C7 Operations with Integers <- C6 The Other Side of Zero',
    ],
    [
        'concept' => 30493, 'prerequisite' => 33264,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Brahmagupta\'s subtraction rule is met in Class 6; Class 7 gives his rules for the other operations.',
        'source' => 'C7 Operations with Integers <- C6 The Other Side of Zero',
    ],
    [
        'concept' => 30500, 'prerequisite' => 30499,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The largest tile is the greatest of the common factors just listed.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30509, 'prerequisite' => 30508,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The factor case is a special case of the least common multiple.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30510, 'prerequisite' => 30509,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The general statement generalises the case just observed.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30511, 'prerequisite' => 30510,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Algebra is brought in to prove the statement just made.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30511, 'prerequisite' => 30353,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Proving it algebraically needs a letter to stand for an arbitrary number.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30512, 'prerequisite' => 30511,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Testing across families of numbers is what a general proof licences.',
        'source' => 'C7 Finding Common Ground',
    ],
    [
        'concept' => 30499, 'prerequisite' => 33125,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Sharing no common factor is Class 6 work; Class 7 lists the common factors two numbers do share.',
        'source' => 'C7 Finding Common Ground <- C6 Prime Time',
    ],
    [
        'concept' => 30502, 'prerequisite' => 33122,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'A prime has exactly two factors, established in Class 6 and assumed here.',
        'source' => 'C7 Finding Common Ground <- C6 Prime Time',
    ],
    [
        'concept' => 30503, 'prerequisite' => 33128,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That one factorisation results whatever the order is Class 6 work; Class 7 relies on it throughout.',
        'source' => 'C7 Finding Common Ground <- C6 Prime Time',
    ],
    [
        'concept' => 30507, 'prerequisite' => 33138,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The first common multiple is met in Class 6 for co-primes; Class 7 treats the general case.',
        'source' => 'C7 Finding Common Ground <- C6 Prime Time',
    ],
    [
        'concept' => 30517, 'prerequisite' => 30335,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Saying decimals extend place value presupposes the places to the right of the point.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30518, 'prerequisite' => 30517,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reading a decimal as a sum of place values applies the extension just claimed.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30519, 'prerequisite' => 30518,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That the procedures extend follows from the place values extending.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30520, 'prerequisite' => 30519,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplying by a whole number is the first procedure carried across.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30521, 'prerequisite' => 30520,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Converting to fractions explains where the point lands in the product just formed.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30521, 'prerequisite' => 30454,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The explanation multiplies two fractions, which is the general rule from the fractions chapter.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30522, 'prerequisite' => 30521,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Multiplying two decimals is justified by the fraction conversion.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30523, 'prerequisite' => 30519,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Sharing a length equally is division carried across to decimals.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30524, 'prerequisite' => 30523,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rewriting as a fraction is how the sharing is computed.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30524, 'prerequisite' => 30456,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The rewriting uses division restated as multiplication from the fractions chapter.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30525, 'prerequisite' => 30524,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The one-place shift is read off the fraction form.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30526, 'prerequisite' => 30525,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Long division as sharing by place value generalises the single-place shift.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30527, 'prerequisite' => 30526,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The procedure is named once it has been carried out.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30528, 'prerequisite' => 30527,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Carrying the division past the point continues the named procedure.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30529, 'prerequisite' => 30528,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Average speed divides by a decimal, which the extended procedure makes possible.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30530, 'prerequisite' => 30529,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The journey is the situation the average speed is computed for.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30531, 'prerequisite' => 30528,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That the year is not a whole number of days is a division carried past the point.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30532, 'prerequisite' => 30531,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The accumulating error is the fractional part of the year, year after year.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30533, 'prerequisite' => 30532,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Why the drift matters presupposes the error accumulating.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30534, 'prerequisite' => 30533,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The leap year is the correction for the drift just shown to matter.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30535, 'prerequisite' => 30528,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Scaling both numbers is the trick that removes the decimal from the divisor.',
        'source' => 'C7 Another Peek Beyond the Point',
    ],
    [
        'concept' => 30540, 'prerequisite' => 30539,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Comparing collections through summaries presupposes one number standing for many.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30544, 'prerequisite' => 30542,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Working the mean on real collections applies the fair-share reading.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 30537, 'prerequisite' => 33246,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Data and frequency are named in Class 6; a statistical statement is made about them.',
        'source' => 'C7 Connecting the Dots <- C6 Data Handling and Presentation',
    ],
    [
        'concept' => 30549, 'prerequisite' => 33240,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That a graph answers faster than a list is argued in Class 6 and assumed here.',
        'source' => 'C7 Connecting the Dots <- C6 Data Handling and Presentation',
    ],
    [
        'concept' => 30550, 'prerequisite' => 33241,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Two presentations of one table are compared in Class 6; Class 7 puts two series on one graph.',
        'source' => 'C7 Connecting the Dots <- C6 Data Handling and Presentation',
    ],
    [
        'concept' => 30552, 'prerequisite' => 33243,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'That appearance changes the reading is Class 6 work; Class 7 shows a fair display still misleading.',
        'source' => 'C7 Connecting the Dots <- C6 Data Handling and Presentation',
    ],
    [
        'concept' => 30554, 'prerequisite' => 30553,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A family of results is what the recalled construction turns out to give.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30555, 'prerequisite' => 30554,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Equal distances from both ends is the property the family shares.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30556, 'prerequisite' => 30555,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Fixed-radius arcs are how the equidistant points are located.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30557, 'prerequisite' => 30556,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Two located points determine the line through them.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30558, 'prerequisite' => 30557,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The ruler-and-compass restriction is stated once the construction is complete.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30559, 'prerequisite' => 30558,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Holding the restriction for the chapter presupposes the restriction being stated.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30560, 'prerequisite' => 30559,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Reading a design back into supporting lines is done under the tool restriction.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30561, 'prerequisite' => 30560,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Equal angles around a point is the first design read back this way.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30562, 'prerequisite' => 30561,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Bisecting an angle to reach a new one presupposes the equal angles around a point.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30563, 'prerequisite' => 30560,
        'type' => 'requires', 'gate' => false,
        'reason' => 'A real building is a design to be read back into its supporting lines.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30564, 'prerequisite' => 30563,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Deciding which lines it needs is the work the building problem sets.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30565, 'prerequisite' => 30559,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The tangram pieces are cut from a square under the same tool restriction.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30566, 'prerequisite' => 30565,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Rearranging presupposes the pieces having been cut.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30567, 'prerequisite' => 30566,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Tiling is defined after pieces have been fitted together by hand.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30568, 'prerequisite' => 30567,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Tiling a grid with dominoes is the definition applied to one board.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30568, 'prerequisite' => 30402,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Showing a board cannot be tiled is the parity impossibility argument in use.',
        'source' => 'C7 Constructions and Tilings',
    ],
    [
        'concept' => 30553, 'prerequisite' => 33190,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Going from properties to a construction is the Class 6 method this chapter recalls by name.',
        'source' => 'C7 Constructions and Tilings <- C6 Playing with Constructions',
    ],
    [
        'concept' => 30556, 'prerequisite' => 33186,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Setting the compass to a radius is Class 6 work; the arcs here are drawn at a fixed radius.',
        'source' => 'C7 Constructions and Tilings <- C6 Playing with Constructions',
    ],
    [
        'concept' => 30561, 'prerequisite' => 32380,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Equal angles around a point divide the 360-degree full turn.',
        'source' => 'C7 Constructions and Tilings <- C6 Lines and Angles',
    ],
    [
        'concept' => 30568, 'prerequisite' => 33220,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'The tiling game on a grid is played in Class 6; Class 7 asks which boards can be tiled at all.',
        'source' => 'C7 Constructions and Tilings <- C6 Symmetry',
    ],
    [
        'concept' => 30570, 'prerequisite' => 30569,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Consistent units are a condition on the balance being read as an equation.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30571, 'prerequisite' => 30570,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Reasoning out the weight presupposes the two sides being in the same units.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30578, 'prerequisite' => 30573,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Naming the unknown is offered because trial and error is unsatisfactory.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30578, 'prerequisite' => 30353,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Naming the unknown uses a letter to stand for a number.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30580, 'prerequisite' => 30579,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Describing and repairing the error presupposes locating the step it happened at.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30581, 'prerequisite' => 30578,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Bijaganita is the historical name for reasoning with a named unknown.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30582, 'prerequisite' => 30581,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The seed and the hidden answer is the image the name bijaganita carries.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30583, 'prerequisite' => 30582,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Brahmagupta\'s treatment continues the tradition the seed image names.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 30584, 'prerequisite' => 30583,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The word algebra reaches us along the route that runs through Brahmagupta.',
        'source' => 'C7 Finding the Unknown',
    ],
    [
        'concept' => 31311, 'prerequisite' => 30538,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'A statistical question is what data is collected to answer, established in Class 7.',
        'source' => 'C8 Data Handling <- C7 Connecting the Dots',
    ],
    [
        'concept' => 31316, 'prerequisite' => 30550,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Two series on one graph is Class 7 work; the Class 8 double bar graph is its standard form.',
        'source' => 'C8 Data Handling <- C7 Connecting the Dots',
    ],
    [
        'concept' => 31475, 'prerequisite' => 30368,
        'type' => 'spiral', 'gate' => true,
        'reason' => 'Terms and letter-numbers are named in Class 7; Class 8 reads each term as a product of factors.',
        'source' => 'C8 Factorisation <- C7 Expressions Using Letter-Numbers',
    ],
    [
        'concept' => 31408, 'prerequisite' => 30365,
        'type' => 'spiral', 'gate' => false,
        'reason' => 'Removing brackets is Class 7 work; the four products of two binomials is that skill applied twice over.',
        'source' => 'C8 Algebraic Expressions and Identities <- C7 Expressions Using Letter-Numbers',
    ],


    // ==================================================================
    // Classes 7-10 - the last unlinked beats
    // ==================================================================

    [
        'concept' => 30537, 'prerequisite' => 30536,
        'type' => 'requires', 'gate' => true,
        'reason' => 'A statistical statement names what the everyday inference was doing informally.',
        'source' => 'C7 Connecting the Dots',
    ],
    [
        'concept' => 31255, 'prerequisite' => 31254,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Moving from solving to exploring is the turn the chapter takes once the rationals have been reached.',
        'source' => 'C8 Rational Numbers',
    ],
    [
        'concept' => 31357, 'prerequisite' => 31356,
        'type' => 'requires', 'gate' => false,
        'reason' => 'That infinitely many such numbers exist generalises the two decompositions of 1729.',
        'source' => 'C8 Cubes and Cube Roots',
    ],
    [
        'concept' => 31380, 'prerequisite' => 31379,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Practising the method drills the ten-per-cent-then-halve shortcut just given.',
        'source' => 'C8 Comparing Quantities',
    ],
    [
        'concept' => 31398, 'prerequisite' => 31397,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Area as rows times columns is the dot array made continuous, so the array comes first.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31400, 'prerequisite' => 31399,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Buying things completes the list of everyday situations that call for a product, begun with area and volume.',
        'source' => 'C8 Algebraic Expressions and Identities',
    ],
    [
        'concept' => 31425, 'prerequisite' => 31424,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Packaging is offered as everyday evidence that a solid\'s faces repeat in congruent pairs.',
        'source' => 'C8 Mensuration',
    ],
    [
        'concept' => 31455, 'prerequisite' => 31454,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Five students on a job is a second scaling situation alongside the recipe.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31459, 'prerequisite' => 31458,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Finding your own examples presupposes the worked ones being recognised as variation.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31469, 'prerequisite' => 31468,
        'type' => 'requires', 'gate' => false,
        'reason' => 'Four ways to travel is a further case of one job done by different means.',
        'source' => 'C8 Direct and Inverse Proportions',
    ],
    [
        'concept' => 31502, 'prerequisite' => 31501,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Renu\'s temperature chart is the worked instance of continuous change over time.',
        'source' => 'C8 Introduction to Graphs',
    ],
    [
        'concept' => 1392, 'prerequisite' => 1391,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Cyclic numbers are read off the repeating block of a decimal expansion, so that conversion comes first.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1394, 'prerequisite' => 1389,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Madhava\'s series states a real number as an infinite sum, so the real numbers have to be in place.',
        'source' => 'C9 The World of Numbers',
    ],
    [
        'concept' => 1410, 'prerequisite' => 1399,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The gap between consecutive squares is the difference-of-squares identity read at b equal to one.',
        'source' => 'C9 Exploring Algebraic Identities',
    ],
    [
        'concept' => 1441, 'prerequisite' => 21814,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Squaring a rectangle builds a square of the same area, so the rectangle\'s area has to be computable.',
        'source' => 'C9 Measuring Space: Perimeter and Area',
    ],
    [
        'concept' => 24683, 'prerequisite' => 24681,
        'type' => 'requires', 'gate' => true,
        'reason' => 'The origin of the definition is history offered about the definition just stated.',
        'source' => 'C10 Probability',
    ],
    [
        'concept' => 24723, 'prerequisite' => 24722,
        'type' => 'requires', 'gate' => true,
        'reason' => 'That many methods exist is remarked once two of them have been shown.',
        'source' => 'C10 Pair of Linear Equations in Two Variables',
    ],
    [
        'concept' => 24807, 'prerequisite' => 24806,
        'type' => 'requires', 'gate' => false,
        'reason' => 'What the chapter will find is previewed from the fixed-difference pattern just noticed.',
        'source' => 'C10 Arithmetic Progressions',
    ],
    [
        'concept' => 24765, 'prerequisite' => 24764,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Raising the head to view is the bodily reading of the angle of elevation just defined.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24768, 'prerequisite' => 24767,
        'type' => 'requires', 'gate' => true,
        'reason' => 'Lowering the head to view is the bodily reading of the angle of depression just defined.',
        'source' => 'C10 Some Applications of Trigonometry',
    ],
    [
        'concept' => 24774, 'prerequisite' => 24773,
        'type' => 'requires', 'gate' => false,
        'reason' => 'The preview of the identities says where the study of triangle ratios is going.',
        'source' => 'C10 Introduction to Trigonometry',
    ],

];
