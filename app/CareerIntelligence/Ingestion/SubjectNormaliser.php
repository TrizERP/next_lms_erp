<?php

namespace App\CareerIntelligence\Ingestion;

/**
 * The companion the mapper implements. Keeping it explicit means the
 * canonical-subject vocabulary is one governed list, not scattered string literals.
 */
interface SubjectNormaliser
{
    /**
     * Map one raw ERP subject label to a canonical Subject code.
     * Return null if it cannot be mapped with confidence — the caller then marks
     * the DeclaredPlan unresolved rather than dropping the subject silently.
     */
    public function toCanonical(string $rawSubjectLabel): ?string;
}
