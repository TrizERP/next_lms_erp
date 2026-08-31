<?php

namespace App\Domain\AI\Lifecycle\Modules;

use App\Domain\AI\Workspace\RouteMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which module a question belongs to.
 *
 * This runs before the pipeline, because the module decides which tools stage 5 may
 * select and how deep stages 10 to 12 can go — a turn cannot be scoped after it has
 * already chosen its tools.
 *
 * Three sources, most trusted first, because they differ in how much they actually know:
 *
 *   1. **An explicit module.** A caller that names one has more context than any
 *      inference — the workspace panel knows exactly which screen it opened on.
 *   2. **The route.** A person asking "who has low attendance?" while looking at the
 *      attendance screen means attendance, and the route says so without ambiguity.
 *   3. **The words.** Last, and deliberately conservative: it needs a clear winner
 *      before it will claim one, because guessing the module wrong sends the question to
 *      the wrong tools and the wrong agent.
 *
 * When none of the three is decisive the answer is the general module, which is honest
 * about having no depth rather than picking a plausible-looking one.
 */
class ModuleResolver
{
    /**
     * A keyword must beat the runner-up by this much before the words alone decide.
     * Below it, two modules are genuinely plausible and the general module is the
     * truthful answer.
     */
    private const MARGIN = 2.0;

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly RouteMatcher $routes,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{module:ModuleCapability, source:string, considered:array<string, float>}
     */
    public function resolve(string $question, array $options = [], ?int $subInstituteId = null): array
    {
        $modules = $this->registry->all($subInstituteId);

        // 1. Named outright.
        $explicit = $options['module'] ?? null;

        if (is_string($explicit) && isset($modules[$explicit])) {
            return [
                'module' => $modules[$explicit],
                'source' => 'declared_by_caller',
                'considered' => [],
            ];
        }

        // 2. Inferred from the screen the question was asked on.
        $route = $options['route'] ?? null;

        if (is_string($route) && $route !== '') {
            $matched = $this->fromRoute($route, $modules);

            if ($matched !== null) {
                return [
                    'module' => $matched,
                    'source' => 'page_route',
                    'considered' => [],
                ];
            }
        }

        // 3. Inferred from the words, if and only if one module clearly wins.
        $scores = $this->score($question, $modules);
        arsort($scores);

        $best = array_key_first($scores);
        $bestScore = $best !== null ? $scores[$best] : 0.0;
        $runnerUp = count($scores) > 1 ? array_values($scores)[1] : 0.0;

        if ($best !== null && $bestScore > 0 && ($bestScore - $runnerUp) >= self::MARGIN) {
            return [
                'module' => $modules[$best],
                'source' => 'question_keywords',
                'considered' => $scores,
            ];
        }

        // A close call between modules of the *same domain* is not ambiguity.
        //
        // "Which students are at academic risk?" scored `student` and `students`
        // identically and was therefore refused as ambiguous — while both bind the same
        // agent, the same workflow and the same case type, so either answer would have
        // been the same answer. The tie was an artefact of one domain having two module
        // rows, and the cost was the agent never running on the platform's flagship
        // question.
        $sameDomain = $this->sameDomainLeader($scores, $modules);

        if ($sameDomain !== null) {
            return [
                'module' => $modules[$sameDomain],
                'source' => 'question_keywords_same_domain',
                'considered' => $scores,
            ];
        }

        return [
            'module' => ModuleCapability::general(),
            'source' => $scores === [] ? 'no_module_matched' : 'ambiguous_between_modules',
            'considered' => $scores,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, ModuleCapability>  $modules
     */
    private function fromRoute(string $route, array $modules): ?ModuleCapability
    {
        if (! Schema::hasTable('ai_modules')) {
            return null;
        }

        $normalised = $this->routes->normalize($route);
        $best = null;
        $bestSpecificity = -1;

        $rows = DB::table('ai_modules')
            ->select('module_key', 'route_patterns', 'match_priority')
            ->orderBy('match_priority')
            ->get();

        foreach ($rows as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                continue;
            }

            $match = $this->routes->best($patterns, $normalised);

            if ($match['matched'] && $match['specificity'] > $bestSpecificity && isset($modules[$row->module_key])) {
                $best = $modules[$row->module_key];
                $bestSpecificity = $match['specificity'];
            }
        }

        return $best;
    }

    /**
     * Score each module's vocabulary against the question.
     *
     * The vocabulary lives in config beside the module's tool bindings, because the two
     * belong together: the words that mean "fees" and the tools that answer a fees
     * question are one decision, and splitting them across a table and a file is how
     * they drift.
     *
     * @param  array<string, ModuleCapability>  $modules
     * @return array<string, float>
     */
    private function score(string $question, array $modules): array
    {
        $normalised = $this->normalise($question);

        if ($normalised === '') {
            return [];
        }

        $vocabularies = (array) config('ai.lifecycle.module_keywords', []);
        $scores = [];

        foreach ($modules as $key => $module) {
            if ($key === 'general') {
                continue;
            }

            $score = 0.0;

            foreach ((array) ($vocabularies[$key] ?? []) as $term => $weight) {
                if ($this->contains($normalised, (string) $term)) {
                    $score += (float) $weight;
                }
            }

            // The module's own label is always part of its vocabulary — a question that
            // names the module is about the module, and nobody should have to configure
            // that.
            if ($this->contains($normalised, mb_strtolower($module->label))) {
                $score += 2.0;
            }

            if ($score > 0) {
                $scores[$key] = $score;
            }
        }

        return $scores;
    }

    /**
     * The modules tied at or near the top, when they all belong to one domain.
     *
     * "One domain" means they bind the same agent — which is what actually decides
     * whether the answer differs. Modules that bind no agent are never folded this way:
     * two tool-less modules tying really is ambiguous, because nothing downstream would
     * reconcile them.
     *
     * The winner is the richest of the tied set, so the turn keeps the widest tool
     * access, with the module key as a stable tie-break so the same question never
     * routes two ways.
     *
     * @param  array<string, float>  $scores  Already sorted, highest first.
     * @param  array<string, ModuleCapability>  $modules
     */
    private function sameDomainLeader(array $scores, array $modules): ?string
    {
        if ($scores === []) {
            return null;
        }

        $top = (float) reset($scores);

        $leaders = array_keys(array_filter(
            $scores,
            static fn (float $score) => ($top - $score) < self::MARGIN
        ));

        if (count($leaders) < 2) {
            return null;
        }

        $agents = [];

        foreach ($leaders as $key) {
            $agent = $modules[$key]->agentKey ?? null;

            if ($agent === null) {
                return null;
            }

            $agents[$agent] = true;
        }

        if (count($agents) !== 1) {
            return null;
        }

        usort($leaders, static function (string $a, string $b) use ($modules) {
            return [count($modules[$b]->mcpTools), $a] <=> [count($modules[$a]->mcpTools), $b];
        });

        return $leaders[0];
    }

    /**
     * Whole-word containment, tolerating a plural.
     *
     * Without the optional `s`, the keyword "student" failed to match the word
     * "students" — so the platform's own flagship question scored its own module at
     * less than half what it should have. English plurals are not an edge case in a
     * vocabulary about students, fees, exams and departments; they are the common form.
     *
     * Still anchored on both sides, so "no" does not match "enrolment" and "fee" does
     * not match "feedback".
     */
    private function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return (bool) preg_match(
            '/(?<![a-z])' . preg_quote($needle, '/') . 's?(?![a-z])/i',
            $haystack
        );
    }

    private function normalise(string $question): string
    {
        $value = mb_strtolower(trim($question));
        $value = preg_replace('/[^\p{L}\p{N}\'\-\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
