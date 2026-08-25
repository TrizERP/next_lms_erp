<?php

namespace App\Domain\AI\Workspace;

use App\Services\Mcp\McpRequestContext;

/**
 * What the AI Workspace knows about where the user is standing.
 *
 * Assembled once per panel open and passed down to every capability, so the answer
 * to "why is this student at risk?" does not need the user to say which student.
 *
 * Two halves, deliberately kept apart:
 *
 *   - the *scope* (McpRequestContext) is trusted: it came from the JWT and cannot be
 *     influenced by the page.
 *   - the *place* (module, route, entity) is derived from the route the browser
 *     reported, and is only ever used to choose what to offer and what to look up.
 *     It never widens what the user may see — every lookup still runs through the
 *     scoped resolvers.
 *
 * That separation is why a forged route cannot leak data: at worst it shows the
 * wrong suggestions, and every one of them still reads through the tenant filter.
 */
final class AiContext
{
    public function __construct(
        public readonly McpRequestContext $scope,
        public readonly string $route,
        public readonly ?string $moduleKey = null,
        public readonly ?string $moduleLabel = null,
        public readonly ?string $entityKey = null,
        public readonly int|string|null $entityId = null,
        public readonly ?string $entityLabel = null,
        public readonly array $entityAttributes = [],
        public readonly array $routeParams = [],
        public readonly array $selectedRecords = [],
        public readonly array $pageData = [],
        public readonly array $capabilities = [],
        // What the page reports it is currently showing — filters, search, the KPI
        // tiles, the visible rows. Normalised and capped in PageSnapshot, and, like
        // the route, descriptive only: it steers what is offered, never what is read.
        public readonly PageSnapshot $page = new PageSnapshot(),
        // dashboard | report | list | detail | form | settings — resolved once, and
        // the reason one implementation can serve every module.
        public readonly string $pageType = 'list',
    ) {
    }

    public function hasEntity(): bool
    {
        return $this->entityKey !== null && $this->entityId !== null;
    }

    public function supports(string $capability): bool
    {
        return (bool) ($this->capabilities[$capability] ?? false);
    }

    /** The user id, role and permissions half of the context object. */
    public function actor(): array
    {
        return [
            'user_id' => $this->scope->userId,
            'role' => $this->scope->role,
            'is_admin' => $this->scope->isAdmin,
            'is_student' => $this->scope->isStudent,
            'sub_institute_id' => $this->scope->selectedInstituteId,
            'academic_year' => $this->scope->academicYear,
            'term_id' => $this->scope->termId,
        ];
    }

    /**
     * Fill {{placeholders}} in a configured prompt from the resolved context, so a
     * suggestion reads as a natural sentence about the record on screen.
     */
    public function renderPrompt(?string $template): ?string
    {
        if ($template === null || trim($template) === '') {
            return null;
        }

        $replacements = [
            'entity_label' => $this->entityLabel ?? 'this record',
            'entity_id' => (string) ($this->entityId ?? ''),
            'entity_type' => $this->entityKey ?? '',
            'module' => $this->moduleLabel ?? $this->moduleKey ?? '',
            // Page-level placeholders, so a configured suggestion can read
            // "Summarise these 42 records" rather than "Summarise the records".
            'page_title' => $this->page->title ?? $this->moduleLabel ?? 'this page',
            'record_count' => (string) $this->page->recordCount,
            'selected_count' => (string) count($this->selectedRecords),
            'search_query' => $this->page->searchQuery ?? '',
            'filters' => $this->page->describeFilters() ?? '',
        ];

        foreach ($this->entityAttributes as $key => $value) {
            if (is_scalar($value)) {
                $replacements['entity.' . $key] = (string) $value;
            }
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn ($matches) => $replacements[$matches[1]] ?? $matches[0],
            $template
        ) ?? $template;
    }

    /**
     * The shape the frontend receives. Mirrors the context object in the brief:
     * userId, role, permissions, module, route, entityType, entityId,
     * selectedRecords, pageData, availableActions.
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->scope->userId,
            'role' => $this->scope->role,
            'is_admin' => $this->scope->isAdmin,
            'module' => $this->moduleKey,
            'module_label' => $this->moduleLabel,
            'route' => $this->route,
            'route_params' => $this->routeParams,
            'entity_type' => $this->entityKey,
            'entity_id' => $this->entityId,
            'entity_label' => $this->entityLabel,
            'entity_attributes' => $this->entityAttributes,
            'selected_records' => $this->selectedRecords,
            'page_data' => $this->pageData,
            // The normalised view of the same thing. `page_data` is what the page sent;
            // `page` is what the assistant actually reasons over, capped and typed.
            'page' => $this->page->toArray(),
            'page_type' => $this->pageType,
            'capabilities' => $this->capabilities,
            'academic_year' => $this->scope->academicYear,
            'term_id' => $this->scope->termId,
        ];
    }
}
