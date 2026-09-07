<?php

namespace App\CareerIntelligence\Ingestion;

/**
 * The governed canonical Subject vocabulary. One list, not scattered string
 * literals — anything Career Intelligence compares a subject against (career
 * requirements in the graph, alignment checks) compares against these codes,
 * never against raw ERP subject_name strings.
 *
 * Scope: CBSE secondary/senior-secondary academic subjects actually observed
 * in the ERP's sub_std_map/subject tables (see ErpSubjectNormaliser). This is
 * deliberately not exhaustive — primary-grade subjects (rhymes, dictation,
 * drawing…), sports/co-curricular activities, and school-internal coaching
 * labels (e.g. "PHY-JEE-D") are out of scope for career alignment and are
 * left unmapped on purpose.
 */
final class CanonicalSubject
{
    // Languages
    public const ENGLISH = 'ENGLISH';
    public const HINDI = 'HINDI';
    public const SANSKRIT = 'SANSKRIT';
    public const FRENCH = 'FRENCH';
    public const MARATHI = 'MARATHI';
    public const GUJARATI = 'GUJARATI';

    // Grade 9-10 compound subjects
    public const SCIENCE = 'SCIENCE';
    public const SOCIAL_SCIENCE = 'SOCIAL_SCIENCE';

    // Sciences (grade 11-12, split out of SCIENCE)
    public const PHYSICS = 'PHYSICS';
    public const CHEMISTRY = 'CHEMISTRY';
    public const BIOLOGY = 'BIOLOGY';

    // Mathematics
    public const MATHEMATICS = 'MATHEMATICS';
    public const APPLIED_MATHEMATICS = 'APPLIED_MATHEMATICS';

    // Commerce
    public const ACCOUNTANCY = 'ACCOUNTANCY';
    public const BUSINESS_STUDIES = 'BUSINESS_STUDIES';
    public const ECONOMICS = 'ECONOMICS';
    public const ENTREPRENEURSHIP = 'ENTREPRENEURSHIP';
    public const BANKING_AND_INSURANCE = 'BANKING_AND_INSURANCE';
    public const STATISTICS = 'STATISTICS';

    // Humanities
    public const HISTORY = 'HISTORY';
    public const POLITICAL_SCIENCE = 'POLITICAL_SCIENCE';
    public const GEOGRAPHY = 'GEOGRAPHY';
    public const SOCIOLOGY = 'SOCIOLOGY';
    public const PSYCHOLOGY = 'PSYCHOLOGY';
    public const PHILOSOPHY = 'PHILOSOPHY';
    public const HOME_SCIENCE = 'HOME_SCIENCE';

    // Computing / skill subjects
    public const COMPUTER_SCIENCE = 'COMPUTER_SCIENCE';
    public const INFORMATICS_PRACTICES = 'INFORMATICS_PRACTICES';
    public const INFORMATION_TECHNOLOGY = 'INFORMATION_TECHNOLOGY';
    public const ARTIFICIAL_INTELLIGENCE = 'ARTIFICIAL_INTELLIGENCE';
    public const ROBOTICS = 'ROBOTICS';

    // Other gradeable academic subjects
    public const PHYSICAL_EDUCATION = 'PHYSICAL_EDUCATION';
    public const ENVIRONMENTAL_STUDIES = 'ENVIRONMENTAL_STUDIES';

    /** @return string[] every canonical code this class defines */
    public static function all(): array
    {
        return (new \ReflectionClass(self::class))->getConstants();
    }
}
