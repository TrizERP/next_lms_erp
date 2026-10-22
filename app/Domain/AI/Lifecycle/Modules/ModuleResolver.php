<?php

namespace App\Domain\AI\Lifecycle\Modules;

use App\Domain\AI\Lifecycle\Flows\ExamQuestionHelpFlow;
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
 * Four sources, most trusted first, because they differ in how much they actually know:
 *
 *   1. **An explicit module.** A caller that names one knows which screen it opened on,
 *      and for most questions that is the best evidence there is.
 *   2. **The thread.** A follow-up like "approve it" inherits the governed module the
 *      conversation already belongs to.
 *   3. **The route.** A person asking "who has low attendance?" while looking at the
 *      attendance screen means attendance, and the route says so without ambiguity.
 *   4. **The words.** Last, and deliberately conservative: it needs a clear winner
 *      before it will claim one, because guessing the module wrong sends the question
 *      to the wrong tools and the wrong agent.
 *
 * The first three are *context*, and context is a hint about a question, never a
 * description of it. So each of them can be stood down by the words — but only when the
 * question has no claim on the context module at all and an unmistakable claim
 * somewhere else. `withOverride()` holds that rule and the reasoning behind it.
 *
 * The ordering has one deliberate exception. A question that scores for nothing —
 * "which one is worst?", "why?" — is elliptical: it is about the answer above it, not
 * about the screen it was typed on, so the thread beats the declared module for those.
 * A panel that stays mounted across a whole conversation would otherwise re-assert its
 * own page on every follow-up and strand the thread on turn two.
 *
 * When nothing is decisive the answer is the general module, which is honest about
 * having no depth rather than picking a plausible-looking one.
 */
class ModuleResolver
{
    /**
     * A keyword must beat the runner-up by this much before the words alone decide.
     * Below it, two modules are genuinely plausible and the general module is the
     * truthful answer.
     */
    private const MARGIN = 2.0;

    /**
     * How loudly the words must name another module before a context module is stood
     * down.
     *
     * Set at the score a question earns by naming a module outright — its keyword plus
     * its label, which is what "students" scores for the student module. One incidental
     * word scores less than this and cannot move a question off the screen it was asked
     * on; naming a domain scores at least this and can.
     */
    private const ESCALATION_FLOOR = 4.0;

    /**
     * How loudly the words must name another strict page before a question is sent there.
     * One genuine domain word ("absent", "timetable", "hostel") reaches it; an incidental word
     * does not.
     */
    private const OTHER_PAGE_FLOOR = 3.0;

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

        // Scored once, up front, because the context signals below are now checked
        // against the words rather than trusted blindly. A page cannot tell you that the
        // question asked on it is about something else; only the question can.
        $scores = $this->score($question, $modules);
        arsort($scores);

        $explicit = $this->canonicalModuleKey($options['module'] ?? null, $modules);
        $thread = $this->canonicalModuleKey($options['conversation_module'] ?? null, $modules);
        $thread = $thread !== null && $thread !== 'general' ? $thread : null;

        // A question that selects a row of the previous answer is about that answer, and
        // the module that produced it keeps the turn outright.
        //
        // Stronger than the elliptical rule below, because it does not need the sentence
        // to be silent — only to resolve. "Show the details of the first student" names
        // a row of a fees list while scoring 4.0 for the student module on the bare word
        // "student", which is exactly the margin that stands a context module down; the
        // selection would have been answered from memory either way, but under the wrong
        // module, bound to the wrong detail tool and offering the wrong hand-off.
        $pointsBack = ($options['points_at_previous_answer'] ?? false) === true;

        // 0. A question button on the exam screen. It pins the paper and the question the student is on,
        // and the route confirms they are on that paper's attempt screen, so the words ("explain it in
        // simpler words") cannot be allowed to stand the turn down onto a module they happen to resemble.
        if (isset($modules['exam_assessment']) && ExamQuestionHelpFlow::isPinned($options)) {
            return $this->answer($modules['exam_assessment'], 'exam_question_pinned', $scores);
        }

        // 1. Named outright — unless the words plainly belong somewhere else.
        if (is_string($explicit) && isset($modules[$explicit])) {
            // An elliptical follow-up carries no domain signal of its own, so the screen
            // is the weakest thing in the room: "which one is worst?" is about the answer
            // above it, not about the page it was typed on. The thread wins those.
            if ($thread !== null && ($scores === [] || $pointsBack)) {
                return $this->answer($modules[$thread], 'conversation_thread_over_declared', $scores, $explicit);
            }

            return $this->withOverride($modules[$explicit], 'declared_by_caller', $scores, $modules);
        }

        // 2. Inherited from the conversation the follow-up belongs to.
        if ($thread !== null) {
            return $pointsBack
                ? $this->answer($modules[$thread], 'conversation_thread', $scores)
                : $this->withOverride($modules[$thread], 'conversation_thread', $scores, $modules);
        }

        // 3. Inferred from the screen the question was asked on.
        $route = $options['route'] ?? null;

        if (is_string($route) && $route !== '') {
            $matched = $this->fromRoute($route, $modules);

            if ($matched !== null) {
                return $this->withOverride($matched, 'page_route', $scores, $modules);
            }
        }

        // 4. Inferred from the words, if and only if one module clearly wins.
        $decided = $this->decisiveLeader($scores, $modules);

        if ($decided !== null) {
            return $this->answer($modules[$decided['key']], $decided['source'], $scores);
        }

        return $this->answer(
            ModuleCapability::general(),
            $scores === [] ? 'no_module_matched' : 'ambiguous_between_modules',
            $scores
        );
    }

    /**
     * Whether a question belongs to a different module's page than the one it was asked on.
     *
     * Only the pages in `ai.lifecycle.page_scoped_modules` are strict, and only another page from
     * that same list can be the place a question is sent to. That keeps a generic noun such as
     * "students" from bouncing a question off the Attendance page: it names no page of its own.
     *
     * Two ways a question qualifies, both decided by the words and never by the thread:
     *
     *   - the older stand-down rule already moved it (`question_overrode_*`): the words name another
     *     domain outright; or
     *   - the page's own vocabulary has no claim on it and another strict page's clearly does — the
     *     case the stand-down rule's high floor misses, such as "how many students are absent today?"
     *     asked on the Fees page, where "absent" is the only attendance word.
     *
     * A question that names no module — general knowledge, "show me their names" — scores for none,
     * so it never reaches here.
     *
     * @param  array{module:ModuleCapability, source:string, considered?:array<string,float>, stood_down?:?string}  $resolution
     * @return array{page:ModuleCapability, asked:ModuleCapability}|null
     */
    public function outsideThisPage(array $resolution, ?int $subInstituteId = null): ?array
    {
        $strict = (array) config('ai.lifecycle.page_scoped_modules', []);
        $source = (string) ($resolution['source'] ?? '');
        $scores = (array) ($resolution['considered'] ?? []);
        $module = $resolution['module'];

        if (str_starts_with($source, 'question_overrode_')) {
            $pageKey = $resolution['stood_down'] ?? null;
            $askedKey = $module->key;
        } elseif (in_array($source, ['declared_by_caller', 'page_route'], true)) {
            $pageKey = $module->key;
            $askedKey = null;

            // The best-scoring OTHER strict page, if it clearly beats this one.
            foreach ($scores as $key => $score) {
                if ($key === $pageKey || ! in_array($key, $strict, true)) {
                    continue;
                }

                if ($score >= self::OTHER_PAGE_FLOOR && ($score - (float) ($scores[$pageKey] ?? 0.0)) >= self::MARGIN) {
                    $askedKey = $key;
                }

                break;
            }
        } else {
            return null;
        }

        if (! is_string($pageKey) || ! is_string($askedKey) || $askedKey === $pageKey) {
            return null;
        }

        if (! in_array($pageKey, $strict, true) || ! in_array($askedKey, $strict, true)) {
            return null;
        }

        $modules = $this->registry->all($subInstituteId);

        return isset($modules[$pageKey], $modules[$askedKey])
            ? ['page' => $modules[$pageKey], 'asked' => $modules[$askedKey]]
            : null;
    }

    // ------------------------------------------------------- context override

    /**
     * Keep a context module, or stand it down when the question is plainly not about it.
     *
     * This is the rule that stops the panel's own screen from deciding what a question
     * means. A user on the dashboard asking "which Grade 8 students are at academic
     * risk?" was routed to the dashboard module, which binds no agent — so the risk
     * agent never ran, no case opened, no referents were recorded, and the follow-up
     * that depended on them had nothing to resolve. The declared module was not adding
     * context; it was removing the answer.
     *
     * Standing a module down needs the words to name another domain outright *and* to
     * beat this screen by that same margin — see the test inside for why dominance
     * rather than silence.
     *
     * So the fees screen keeps every fees question, and the dashboard keeps "summarise
     * the KPIs": those score for the module they were asked on. Only a question the
     * current screen has no real claim on, and another module unmistakably does, moves.
     *
     * @param  array<string, float>  $scores
     * @param  array<string, ModuleCapability>  $modules
     * @return array<string, mixed>
     */
    private function withOverride(
        ModuleCapability $module,
        string $source,
        array $scores,
        array $modules
    ): array {
        // A module with no vocabulary configured is never stood down. It scores zero for
        // every question ever asked, so zero says nothing about it, and reading that as
        // "no claim" would re-route every question typed on half the estate's screens.
        if (! $this->hasVocabulary($module->key)) {
            return $this->answer($module, $source, $scores);
        }

        $decided = $this->decisiveLeader($scores, $modules);

        if ($decided === null || $decided['key'] === $module->key) {
            return $this->answer($module, $source, $scores);
        }

        $winner = $scores[$decided['key']] ?? 0.0;
        $own = $scores[$module->key] ?? 0.0;

        // Both halves have to hold, and the second is what keeps this narrow.
        //
        // An earlier version asked whether the context module scored *nothing*, which
        // read well and was wrong: "term" sits in the exam vocabulary, so the exams
        // screen scored on a pure academic-risk question and kept it on that alone.
        // Dominance is the honest test: the words have to name the other domain
        // outright, and to out-score this screen by that same margin.
        if ($winner < self::ESCALATION_FLOOR || ($winner - $own) < self::ESCALATION_FLOOR) {
            return $this->answer($module, $source, $scores);
        }

        return $this->answer(
            $modules[$decided['key']],
            'question_overrode_' . $source,
            $scores,
            $module->key
        );
    }

    /**
     * The module the words settle on, or null when they settle nothing.
     *
     * @param  array<string, float>  $scores  Already sorted, highest first.
     * @param  array<string, ModuleCapability>  $modules
     * @return array{key:string, source:string}|null
     */
    private function decisiveLeader(array $scores, array $modules): ?array
    {
        $best = array_key_first($scores);
        $bestScore = $best !== null ? $scores[$best] : 0.0;
        $runnerUp = count($scores) > 1 ? array_values($scores)[1] : 0.0;

        if ($best !== null && $bestScore > 0 && ($bestScore - $runnerUp) >= self::MARGIN) {
            return ['key' => $best, 'source' => 'question_keywords'];
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

        return $sameDomain === null
            ? null
            : ['key' => $sameDomain, 'source' => 'question_keywords_same_domain'];
    }

    /** Whether this module has words of its own to be judged by. */
    private function hasVocabulary(string $key): bool
    {
        $vocabularies = (array) config('ai.lifecycle.module_keywords', []);

        return ($vocabularies[$key] ?? []) !== [];
    }

    /**
     * @param  array<string, float>  $scores
     * @return array<string, mixed>
     */
    private function answer(
        ModuleCapability $module,
        string $source,
        array $scores,
        ?string $stoodDown = null
    ): array {
        return [
            'module' => $module,
            'source' => $source,
            'considered' => $scores,
            // Named rather than implied. A turn that ran somewhere other than the screen
            // it was asked on has to say so, or the trace describes a question nobody
            // asked and the next reader cannot tell routing from a bug.
            'stood_down' => $stoodDown,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * Frontend page context and older conversation rows use `student_profiles`,
     * while the lifecycle registry binds the same capability as `student`.
     * Keep that compatibility at the resolver boundary so an elliptical follow-up
     * such as "Why is Abhi D. Raval at risk?" stays on the academic-risk module.
     *
     * @param  array<string, ModuleCapability>  $modules
     */
    private function canonicalModuleKey(mixed $key, array $modules): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        if (isset($modules[$key])) {
            return $key;
        }

        return match ($key) {
            'student_profiles' => isset($modules['student']) ? 'student' : (isset($modules['students']) ? 'students' : null),
            default => null,
        };
    }

    /**
     * @param  array<string, ModuleCapability>  $modules
     */
    private function fromRoute(string $route, array $modules): ?ModuleCapability
    {
        $normalised = $this->routes->normalize($route);
        $best = null;
        $bestSpecificity = -1;

        $rows = Schema::hasTable('ai_modules')
            ? DB::table('ai_modules')
                ->select('module_key', 'route_patterns', 'match_priority')
                ->orderBy('match_priority')
                ->get()
            : collect();

        // Modules bound in config with their own route patterns and no `ai_modules` row.
        // They take part in the same specificity contest; a real row for the same key
        // is never shadowed by its config twin.
        $known = $rows->pluck('module_key')->all();

        foreach ((array) config('ai.lifecycle.modules', []) as $key => $binding) {
            $patterns = is_array($binding) ? (array) ($binding['route_patterns'] ?? []) : [];

            if ($patterns !== [] && ! in_array($key, $known, true)) {
                $rows->push((object) [
                    'module_key' => (string) $key,
                    'route_patterns' => json_encode(array_values($patterns)),
                    'match_priority' => 80,
                ]);
            }
        }

        foreach ($rows as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                continue;
            }

            // A real row's patterns are widened, never replaced, by the ones config declares for
            // the same key — the row predates screens such as /lms/homework, and the row cannot be
            // edited without a migration. Only pages the row did not already claim are added.
            if (in_array($row->module_key, $known, true)) {
                $extra = (array) (config('ai.lifecycle.modules.' . $row->module_key . '.route_patterns') ?? []);
                $patterns = array_values(array_unique(array_merge($patterns, $extra)));
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
