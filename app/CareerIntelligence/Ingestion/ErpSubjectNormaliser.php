<?php

namespace App\CareerIntelligence\Ingestion;

/**
 * Maps a raw ERP subject label (from `subject.subject_name`) to a canonical
 * CanonicalSubject code.
 *
 * The lookup table below was built by reading every distinct subject_name in
 * this ERP's `sub_std_map`/`subject` tables that is actually gradeable
 * (sub_std_map.allow_grades = 'Yes') — 346 distinct labels across all
 * tenants. It is worse than the schema implies: typos ("CHEMESTRY",
 * "MATHEMETICS"), per-institute display variants ("English Core" vs
 * "Communicative English"), coaching/exam-prep compounds ("PHY-JEE-D",
 * "BIO-NEET"), primary-grade subjects (rhymes, dictation, drawing), and
 * garbled non-Latin-script labels.
 *
 * Only variants a human can confidently attribute to a single canonical
 * subject are listed here. Everything else — including plausible-looking
 * abbreviations like "H.P.E" or compound labels like "GEOGRAPHY/P. T" —
 * deliberately returns null. Guessing a subject here produces a confident
 * lie downstream (a false ALIGNED/MISALIGNED for a real child), so an
 * unmapped label must surface as "we don't know", never a silent best guess.
 */
class ErpSubjectNormaliser implements SubjectNormaliser
{
    /** @var array<string, string> normalised raw label => canonical code */
    private const MAP = [
        // Languages
        'ENGLISH' => CanonicalSubject::ENGLISH,
        'ENGILSH' => CanonicalSubject::ENGLISH,
        'ENGLISH CORE' => CanonicalSubject::ENGLISH,
        'COMMUNICATIVE ENGLISH' => CanonicalSubject::ENGLISH,
        'ENGLISH LANG. & LIT' => CanonicalSubject::ENGLISH,

        'HINDI' => CanonicalSubject::HINDI,
        'HINDI-A' => CanonicalSubject::HINDI,
        'HINDI-B' => CanonicalSubject::HINDI,

        'SANSKRIT' => CanonicalSubject::SANSKRIT,
        'SANAKRIT' => CanonicalSubject::SANSKRIT,
        'SANSKRUT' => CanonicalSubject::SANSKRIT,

        'FRENCH' => CanonicalSubject::FRENCH,

        'MARATHI' => CanonicalSubject::MARATHI,
        'BASIC MARATHI' => CanonicalSubject::MARATHI,

        'GUJARATI' => CanonicalSubject::GUJARATI,
        'GUJRATI' => CanonicalSubject::GUJARATI,

        // Grade 9-10 compound subjects
        'SCIENCE' => CanonicalSubject::SCIENCE,
        'SCIENCE SEC' => CanonicalSubject::SCIENCE,

        'SOCIAL SCIENCE' => CanonicalSubject::SOCIAL_SCIENCE,
        'SOCIAL STUDIES' => CanonicalSubject::SOCIAL_SCIENCE,
        'SST' => CanonicalSubject::SOCIAL_SCIENCE,
        'SOCIAL SCIENCES' => CanonicalSubject::SOCIAL_SCIENCE,
        'SOCIAL SCIENCE SEC' => CanonicalSubject::SOCIAL_SCIENCE,

        // Sciences (grade 11-12 split-outs)
        'PHYSICS' => CanonicalSubject::PHYSICS,
        'CHEMISTRY' => CanonicalSubject::CHEMISTRY,
        'CHEMESTRY' => CanonicalSubject::CHEMISTRY,
        'BIOLOGY' => CanonicalSubject::BIOLOGY,
        'BIO' => CanonicalSubject::BIOLOGY,

        // Mathematics
        'MATHEMATICS' => CanonicalSubject::MATHEMATICS,
        'MATHEMATICS STANDARD' => CanonicalSubject::MATHEMATICS,
        'MATHEMATICS BASIC' => CanonicalSubject::MATHEMATICS,
        'STANDARD MATHEMATICS' => CanonicalSubject::MATHEMATICS,
        'BASIC MATHEMATICS' => CanonicalSubject::MATHEMATICS,
        'MATHEMETICS' => CanonicalSubject::MATHEMATICS,
        'MATHS' => CanonicalSubject::MATHEMATICS,
        'MATH' => CanonicalSubject::MATHEMATICS,
        'APPLIED MATHEMATICS' => CanonicalSubject::APPLIED_MATHEMATICS,
        'APPLIED MATHS' => CanonicalSubject::APPLIED_MATHEMATICS,

        // Commerce
        'ACCOUNTANCY' => CanonicalSubject::ACCOUNTANCY,
        'ACCOUNTS' => CanonicalSubject::ACCOUNTANCY,
        'BUSINESS STUDIES' => CanonicalSubject::BUSINESS_STUDIES,
        'ECONOMICS' => CanonicalSubject::ECONOMICS,
        'ENTREPRENEURSHIP' => CanonicalSubject::ENTREPRENEURSHIP,
        'BANKING & INSURANCE' => CanonicalSubject::BANKING_AND_INSURANCE,
        'BANKING AND INSURANCE' => CanonicalSubject::BANKING_AND_INSURANCE,
        'STATISTICS' => CanonicalSubject::STATISTICS,
        // Real answer-data audit: CBSE's own Class 11 Commerce paper, taught
        // as part of the Economics subject group (not the same subject as
        // plain "Statistics" above — kept as its own exact-string entry, not
        // folded into a fuzzy match against it). 3 students, 140 real
        // attempts, content confirmed genuine CBSE Economics/Statistics
        // curriculum — approved addition.
        'STATISTICS FOR ECONOMICS' => CanonicalSubject::ECONOMICS,

        // Humanities
        'HISTORY' => CanonicalSubject::HISTORY,
        'HISTORY SR.SEC' => CanonicalSubject::HISTORY,
        'POLITICAL SCIENCE' => CanonicalSubject::POLITICAL_SCIENCE,
        'GEOGRAPHY' => CanonicalSubject::GEOGRAPHY,
        'GEO' => CanonicalSubject::GEOGRAPHY,
        'SOCIOLOGY' => CanonicalSubject::SOCIOLOGY,
        'PSYCHOLOGY' => CanonicalSubject::PSYCHOLOGY,
        'PSYCOLOGY' => CanonicalSubject::PSYCHOLOGY,
        'PHYCOLOGY' => CanonicalSubject::PSYCHOLOGY,
        'PHILOSOPHY' => CanonicalSubject::PHILOSOPHY,
        'PHYLOSOPHY' => CanonicalSubject::PHILOSOPHY,
        'HOME SCIENCE' => CanonicalSubject::HOME_SCIENCE,

        // Computing / skill subjects
        'COMPUTER SCIENCE' => CanonicalSubject::COMPUTER_SCIENCE,
        'COMPUTER' => CanonicalSubject::COMPUTER_SCIENCE,
        'COMPUTERS' => CanonicalSubject::COMPUTER_SCIENCE,
        'INFORMATICS PRACTICES' => CanonicalSubject::INFORMATICS_PRACTICES,
        'INFORMATICS PRACTICS' => CanonicalSubject::INFORMATICS_PRACTICES,
        'INFORMATICS PRAC' => CanonicalSubject::INFORMATICS_PRACTICES,
        'INFORMATION TECHNOLOGY' => CanonicalSubject::INFORMATION_TECHNOLOGY,
        'IT' => CanonicalSubject::INFORMATION_TECHNOLOGY,
        // Real answer-data audit (Phase 6 coverage analysis): 277 students,
        // 208,828 real attempts, content confirmed genuine ICT curriculum
        // (PowerPoint/presentation-software questions) — approved addition.
        'ICT' => CanonicalSubject::INFORMATION_TECHNOLOGY,
        'ARTIFICIAL INTELLIGENCE' => CanonicalSubject::ARTIFICIAL_INTELLIGENCE,
        'ROBOTICS' => CanonicalSubject::ROBOTICS,

        // Other gradeable academic subjects
        'PHYSICAL EDUCATION' => CanonicalSubject::PHYSICAL_EDUCATION,
        'HEALTH AND PHYSICAL EDUCATION' => CanonicalSubject::PHYSICAL_EDUCATION,
        'PHYSICAL EDUCATION SR.SEC' => CanonicalSubject::PHYSICAL_EDUCATION,
        'E.V.S' => CanonicalSubject::ENVIRONMENTAL_STUDIES,
        'EVS' => CanonicalSubject::ENVIRONMENTAL_STUDIES,
        'ENVIRONMENTAL STUDIES' => CanonicalSubject::ENVIRONMENTAL_STUDIES,
        'ENVIRONMENTAL SCIENCE' => CanonicalSubject::ENVIRONMENTAL_STUDIES,
        // Real answer-data audit: 355 students, 24,786 real attempts, content
        // confirmed textbook primary-grade EVS (air/water/plants/animals) —
        // approved addition.
        'ENVIRONMENT' => CanonicalSubject::ENVIRONMENTAL_STUDIES,
    ];

    public function toCanonical(string $rawSubjectLabel): ?string
    {
        $normalised = $this->normaliseLabel($rawSubjectLabel);

        return self::MAP[$normalised] ?? null;
    }

    /**
     * Uppercase + collapse whitespace only — no stemming, no fuzzy matching.
     * A label either exactly matches a known variant after this or it doesn't;
     * fuzzy matching is exactly the kind of "confident guess" this class must
     * never make.
     */
    private function normaliseLabel(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', strtoupper($label)));
    }
}
