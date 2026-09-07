<?php

namespace App\CareerIntelligence\Ingestion;

/**
 * SubjectEnrolmentAdapter — the ONLY code that knows both the ERP schema and the
 * CAI schema. Career Intelligence never queries ERP tables directly; it asks this
 * adapter for a normalised DeclaredPlan.
 *
 * The single hard job of this adapter is HONEST RESOLUTION:
 *   - ERP subject rows are messy (free-text electives, inconsistent naming,
 *     mid-year changes). The adapter maps them to canonical Subject codes.
 *   - When it CANNOT map cleanly, it must set resolved=false and say why.
 *     That flows straight through to CAI => INSUFFICIENT_DATA. It must NEVER
 *     guess a subject to make the plan look complete — a false "green" on a
 *     child's pathway is the worst possible failure of this system.
 *
 * Day-1 task for whoever implements this: look at the ACTUAL enrolment rows before
 * writing the mapper. The real data will be worse than the schema implies.
 *
 * DeclaredPlan and SubjectNormaliser live in their own files (DeclaredPlan.php,
 * SubjectNormaliser.php) in this same directory — three symbols in one file
 * isn't reliably PSR-4-autoloadable (Composer's non-optimized autoloader
 * resolves a class purely from its own filename; bundling them here would
 * make DeclaredPlan/SubjectNormaliser fail to load until something else
 * happened to load this file first).
 */
interface SubjectEnrolmentAdapter
{
    /**
     * @param string $studentId
     * @param string $academicYear  e.g. "2026-2027"
     * @return DeclaredPlan
     */
    public function fetch(string $studentId, string $academicYear): DeclaredPlan;
}
