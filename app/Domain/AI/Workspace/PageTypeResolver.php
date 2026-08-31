<?php

namespace App\Domain\AI\Workspace;

/**
 * Works out what *kind* of page the user is on.
 *
 * This is the piece that lets one implementation serve the whole estate. Which AI
 * capabilities are useful is a property of the page's shape, not its module: a list
 * wants analysis of the rows, a form wants drafting help, a dashboard wants insight
 * into its figures. Resolving the shape once, here, is what keeps the rest of the
 * system free of `if ($module === 'exam')`.
 *
 * Four sources, most trusted first:
 *
 *   1. **The page's own declaration.** A page knows what it is, and knows things a URL
 *      cannot — that `/students/requests` is a list but becomes a form once a drawer
 *      is open. Always wins.
 *   2. **A configured route pattern.** Covers the conventions this estate actually
 *      uses, so most of the 56 route folders classify correctly with no page changes.
 *   3. **A resolved entity.** A route that resolved to one record is a detail page,
 *      whatever its path looks like.
 *   4. **The configured default.**
 *
 * Rules live in config/ai.php, so reclassifying a route is a config edit rather than a
 * code change — which matters because the alternative is this class slowly accumulating
 * one branch per screen.
 */
class PageTypeResolver
{
    public const TYPES = ['dashboard', 'report', 'list', 'detail', 'form', 'settings'];

    /**
     * @param  array  $rules  The `ai.page_types` config block, injected rather than
     *                        read through the `config()` helper so this stays a plain
     *                        object — testable without booting the framework, and with
     *                        no container lookup on a path that runs per panel open.
     */
    public function __construct(
        private readonly RouteMatcher $matcher,
        private readonly array $rules = [],
    ) {
    }

    /**
     * @param  string|null  $declared  What the page said it is, if anything.
     */
    public function resolve(string $route, ?string $declared = null, bool $hasEntity = false): string
    {
        if ($declared !== null && in_array($declared, self::TYPES, true)) {
            return $declared;
        }

        $matched = $this->matchPattern($route);

        if ($matched !== null) {
            return $matched;
        }

        // A route that resolved to a single record is a detail page even when its path
        // gives no hint — `/fees/collect/42` looks like a list route and is not one.
        if ($hasEntity) {
            return 'detail';
        }

        $default = (string) ($this->rules['default_type'] ?? 'list');

        return in_array($default, self::TYPES, true) ? $default : 'list';
    }

    /**
     * Capabilities implied by a page type.
     *
     * A floor, not a ceiling — the caller unions this with the module's own
     * declaration. Over-enabling is safe because a capability that resolves to no
     * suggestions is hidden rather than shown as an empty tab.
     *
     * @return array<string, bool>
     */
    public function capabilitiesFor(string $pageType): array
    {
        $configured = $this->rules['capabilities'][$pageType] ?? [];

        if (! is_array($configured)) {
            return [];
        }

        return array_map(static fn ($enabled) => (bool) $enabled, $configured);
    }

    /**
     * The analysis action for this page type, if one is configured.
     *
     * @return array{template:string, label:string}|null
     */
    public function analysisFor(string $pageType): ?array
    {
        return $this->action('analysis', $pageType);
    }

    /**
     * The Create action for this page type, if one is configured.
     *
     * @return array{template:string, label:string}|null
     */
    public function generationFor(string $pageType): ?array
    {
        return $this->action('generation', $pageType);
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array{template:string, label:string}|null
     */
    private function action(string $kind, string $pageType): ?array
    {
        $configured = $this->rules[$kind][$pageType] ?? null;

        if (! is_array($configured) || empty($configured['template']) || empty($configured['label'])) {
            return null;
        }

        return [
            'template' => (string) $configured['template'],
            'label' => (string) $configured['label'],
        ];
    }

    private function matchPattern(string $route): ?string
    {
        $patterns = $this->rules['patterns'] ?? [];

        if (! is_array($patterns)) {
            return null;
        }

        $best = null;
        $bestSpecificity = -1;

        foreach ($patterns as $type => $routePatterns) {
            if (! in_array($type, self::TYPES, true) || ! is_array($routePatterns)) {
                continue;
            }

            $result = $this->matcher->best($routePatterns, $route);

            // Most specific wins, so `/lms/dashboard` beats a broad `/**` rule rather
            // than depending on which type happens to be declared first.
            if ($result['matched'] && $result['specificity'] > $bestSpecificity) {
                $best = $type;
                $bestSpecificity = $result['specificity'];
            }
        }

        return $best;
    }
}
