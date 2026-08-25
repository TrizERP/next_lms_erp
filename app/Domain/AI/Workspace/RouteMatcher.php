<?php

namespace App\Domain\AI\Workspace;

/**
 * Matches a frontend route against the patterns configured in `ai_modules`.
 *
 * Patterns are the same shape the Next.js app uses, with `:param` where the app has
 * `[param]` — `/fees/collect/:studentId`. Two extras earn their keep:
 *
 *   `*`  matches one segment loosely (`/lms/*​/report`)
 *   `**` at the end matches the rest (`/settings/**`)
 *
 * Patterns are compiled to anchored regexes with the literal parts quoted, so a
 * configured pattern cannot become a regex injection when an administrator edits it.
 * Query strings and trailing slashes are stripped before matching, because a route
 * means the same thing with or without them.
 */
class RouteMatcher
{
    /** @var array<string, array{regex:string, params:array<int,string>}> */
    private array $compiled = [];

    /**
     * @return array{matched:bool, params:array<string,string>, specificity:int}
     */
    public function match(string $pattern, string $route): array
    {
        $normalizedRoute = $this->normalize($route);
        $compiled = $this->compile($pattern);

        if (! preg_match($compiled['regex'], $normalizedRoute, $matches)) {
            return ['matched' => false, 'params' => [], 'specificity' => 0];
        }

        $params = [];

        foreach ($compiled['params'] as $index => $name) {
            $value = $matches[$index + 1] ?? null;

            if ($value !== null && $value !== '') {
                $params[$name] = $value;
            }
        }

        return [
            'matched' => true,
            'params' => $params,
            'specificity' => $this->specificity($pattern),
        ];
    }

    /**
     * The best match among many patterns.
     *
     * "Best" is the most specific: more literal segments wins, so
     * `/fees/collect/:studentId` beats `/fees/**` for the same route and the
     * workspace resolves the student rather than the module's landing page.
     *
     * @param  array<int, string>  $patterns
     * @return array{matched:bool, pattern:string|null, params:array<string,string>, specificity:int}
     */
    public function best(array $patterns, string $route): array
    {
        $best = ['matched' => false, 'pattern' => null, 'params' => [], 'specificity' => -1];

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            $result = $this->match($pattern, $route);

            if ($result['matched'] && $result['specificity'] > $best['specificity']) {
                $best = [
                    'matched' => true,
                    'pattern' => $pattern,
                    'params' => $result['params'],
                    'specificity' => $result['specificity'],
                ];
            }
        }

        return $best;
    }

    public function normalize(string $route): string
    {
        $path = parse_url(trim($route), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = trim($route);
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @return array{regex:string, params:array<int,string>}
     */
    private function compile(string $pattern): array
    {
        if (isset($this->compiled[$pattern])) {
            return $this->compiled[$pattern];
        }

        $normalized = $this->normalize($pattern);
        $segments = $normalized === '/' ? [] : explode('/', ltrim($normalized, '/'));

        $regex = '';
        $params = [];

        foreach ($segments as $segment) {
            if ($segment === '**') {
                // Everything that remains, including nothing.
                $regex .= '(?:/.*)?';

                continue;
            }

            if ($segment === '*') {
                $regex .= '/[^/]+';

                continue;
            }

            if (str_starts_with($segment, ':')) {
                $params[] = substr($segment, 1);
                $regex .= '/([^/]+)';

                continue;
            }

            // Literal. Quoted so an edited pattern cannot inject regex syntax.
            $regex .= '/' . preg_quote($segment, '#');
        }

        if ($regex === '') {
            $regex = '/';
        }

        $compiled = [
            'regex' => '#^' . $regex . '$#i',
            'params' => $params,
        ];

        return $this->compiled[$pattern] = $compiled;
    }

    /**
     * Literal segments are worth more than captures, and captures more than
     * wildcards, so the most concrete pattern wins.
     */
    private function specificity(string $pattern): int
    {
        $normalized = $this->normalize($pattern);

        if ($normalized === '/') {
            return 1;
        }

        $score = 0;

        foreach (explode('/', ltrim($normalized, '/')) as $segment) {
            $score += match (true) {
                $segment === '**' => 1,
                $segment === '*' => 2,
                str_starts_with($segment, ':') => 5,
                default => 10,
            };
        }

        return $score;
    }
}
