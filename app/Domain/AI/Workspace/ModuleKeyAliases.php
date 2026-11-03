<?php

namespace App\Domain\AI\Workspace;

/**
 * Canonicalizes a module-bar slug (`fees_menu_categories.module_name`, e.g.
 * `'new-pal'`, `'teach_learn'`) to the `ai_ontology_views.module_key` its
 * views are actually seeded under.
 *
 * Why this exists: `WorkspaceController::moduleViews()` used to pass the
 * calling page's `module_name` straight through as `module_key` with no
 * translation — a mismatched key just silently returned zero views. That
 * never surfaced because Student and Fees are the only modules with real
 * views today, and both slugs happen to equal their module_key verbatim.
 * It becomes a real bug the moment one concept spans multiple module bars —
 * e.g. "LMS + PAL" is `lms`/`new-pal`/`teach_learn`/`lms-report` as real
 * `module_name` slugs, but one shared set of ontology views. `canonicalize()`
 * is the single place that collapses such slugs onto one `module_key`; an
 * unmapped slug passes through unchanged, so this can never make an
 * already-working lookup (Student, Fees) stop resolving.
 *
 * Precedent for this exact kind of slug-aliasing already exists inline in
 * `lms_k12/app/_components/module-category-page.tsx` for other tool-strip
 * tabs (`admission`/`admissions`, `user`/`users`/`user-i-card`) — this class
 * is the backend equivalent, scoped to ontology view resolution.
 */
class ModuleKeyAliases
{
    /**
     * module_name slug => ai_ontology_views.module_key.
     *
     * Deliberately empty until a stage actually verifies and seeds a shared
     * view set for a multi-slug module — add entries there, not here, so
     * every mapping in this file has been checked against real data first.
     */
    private const MAP = [];

    public static function canonicalize(string $moduleKey): string
    {
        return self::MAP[$moduleKey] ?? $moduleKey;
    }
}
