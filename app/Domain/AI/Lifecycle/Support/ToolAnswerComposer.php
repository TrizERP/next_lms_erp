<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\StageContext;

/**
 * Turns what the tools returned into something a person can read.
 *
 * Without this the tool path stopped one step short of being useful: a question would
 * resolve to the right module, select the right tool, read six hundred real rows — and
 * then fall through to a reply about not knowing which student was meant, because the
 * only thing that knew how to compose an answer was the case-based path.
 *
 * Everything here is rendered from the payload and nothing is inferred. No model writes
 * this prose. That is a deliberate trade: a generated summary would read better and
 * could quietly assert something the rows do not support, and a list of real records
 * with real counts is worth more than a fluent paragraph nobody can check.
 *
 * The renderer is generic on purpose. Tool payloads share a shape — a count and a list
 * of records — and teaching this class about individual tools would mean every new tool
 * needing a matching renderer before it could answer anything.
 */
class ToolAnswerComposer
{
    /** Items shown per list before it is truncated with a note. */
    private const MAX_ITEMS = 10;

    /** Keys that make a good heading for a record, most identifying first. */
    private const TITLE_KEYS = [
        'student_name', 'teacher_name', 'full_name', 'name', 'title', 'label',
        'department', 'subject_name', 'standard_name', 'exam_title', 'display_name',
    ];

    /** Keys that are noise in a summary — ids the reader did not ask for. */
    private const HIDDEN_KEYS = [
        'parent_id', 'description', 'code', 'is_overdue', 'judgeable',
    ];

    /**
     * Lists that describe the query rather than answer it.
     *
     * A payload often carries both — `students.directory` returns `students` and
     * `unresolved_filters` side by side. Picking the wrong one answered "which students
     * are in class 5?" with "No unresolved filters matched", which is true, useless, and
     * reads like a malfunction.
     */
    private const META_LISTS = [
        'unresolved_filters', 'blind_detectors', 'dropped', 'sources', 'calls',
        'planned_tools', 'selected_tools', 'candidate_tools',
    ];

    public function __construct(private readonly AnswerComposer $compose)
    {
    }

    /**
     * True when there is tool output worth turning into an answer.
     */
    public function hasResults(StageContext $context): bool
    {
        return $this->lists($context) !== [];
    }

    /**
     * Write the answer for a turn that was served by tools.
     */
    public function compose(StageContext $context): void
    {
        $lists = $this->lists($context);

        if ($lists === []) {
            return;
        }

        // The answer is the *last* list, not the first.
        //
        // A model plan builds toward its answer: asked "who has low attendance?" it
        // resolves the class structure and then queries attendance. Headlining the first
        // list answered an attendance question with "5 grades" — the working-out, not
        // the result. Earlier lists are context and stay out of the answer.
        $answer = $this->answerList($lists);

        if ($answer === null) {
            return;
        }

        if ($answer['items'] === []) {
            $this->composeEmpty($context, $answer, $lists);

            return;
        }

        $context->setHeadline($this->headline($answer));

        $shown = array_slice($answer['items'], 0, self::MAX_ITEMS);

        $context->addSection($this->compose->records(
            $this->sectionTitle($answer),
            array_map(fn (array $item) => $this->record($item), $shown)
        ));

        if (count($answer['items']) > count($shown)) {
            $context->addSection($this->compose->text(
                'Not all shown',
                sprintf(
                    '%d of %d shown. Narrow the question — by class, subject or date — to see the rest.',
                    count($shown),
                    count($answer['items'])
                )
            ));
        }

        $facts = $this->facts([$answer]);

        if ($facts !== []) {
            $context->addSection($this->compose->keyValues('Figures', $facts));
        }

        $this->provenance($context, $lists);
    }

    /**
     * The tool ran and found nothing.
     *
     * This is a real answer and has to read like one. Falling back to an earlier step's
     * list would present the working-out as the result; saying nothing at all would
     * leave the user unable to tell an empty result from a broken one. So it reports the
     * emptiness, and — where the payload said what it looked at — what was searched.
     *
     * @param  array<string, mixed>  $answer
     * @param  array<int, array<string, mixed>>  $lists
     */
    private function composeEmpty(StageContext $context, array $answer, array $lists): void
    {
        $noun = str_replace('_', ' ', $answer['key']);

        $context->setHeadline(sprintf('No %s matched.', $noun));

        $scope = $this->facts([$answer]);

        if ($scope !== []) {
            $context->addSection($this->compose->keyValues('What was searched', $scope));
        }

        $context->addSection($this->compose->text(
            'What this means',
            sprintf(
                'The %s tool ran against live records and returned nothing for this scope. That is a '
                . 'result, not a failure — widening the date range or removing a filter may find rows.',
                $answer['tool']
            )
        ));

        $this->provenance($context, $lists);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     */
    private function provenance(StageContext $context, array $lists): void
    {
        $context->addSection($this->compose->text(
            'Where this came from',
            sprintf(
                'Read live through %s. Every row above is a record in the school database, not a summary.',
                implode(', ', array_unique(array_column($lists, 'tool')))
            )
        ));
    }

    // ---------------------------------------------------------------- internals

    /**
     * Every list a tool returned, with the tool and payload it came from.
     *
     * @return array<int, array{tool:string, key:string, items:array<int, array<string, mixed>>, payload:array<string, mixed>}>
     */
    private function lists(StageContext $context): array
    {
        $results = $context->get('mcp_step_results', []);
        $calls = $context->toolCalls();
        $lists = [];

        // Step results are keyed by plan step, and the call log holds the tool names in
        // the order they ran. Zipping them keeps each list attributed to the tool that
        // produced it, which is what the provenance line rests on.
        $tools = array_values(array_filter(array_map(
            static fn (array $call) => ($call['status'] ?? null) === 'completed' ? ($call['tool'] ?? null) : null,
            $calls
        )));

        $index = 0;

        foreach ((array) $results as $payload) {
            $tool = $tools[$index] ?? 'a tool';
            $index++;

            if (! is_array($payload)) {
                continue;
            }

            // Two payload shapes exist in this estate and both are legitimate: the newer
            // services return their findings directly, while the older ones wrap them in
            // a ToolResult envelope whose contents live under `data`. Reading only the
            // top level found the list for one and nothing at all for the other — so an
            // admissions query executed its tool, read a real row, and still answered
            // that it did not know which student was meant.
            if (array_key_exists('success', $payload) && is_array($payload['data'] ?? null)) {
                $payload = $payload['data'];
            }

            foreach ($payload as $key => $value) {
                // Empty lists are kept, deliberately. Dropping them meant a final step
                // that found nothing vanished, and an earlier resolution step became
                // "the answer" — which is how an attendance question came back as a
                // list of grades.
                if (! is_array($value) || ! array_is_list($value)) {
                    continue;
                }

                $lists[] = [
                    'tool' => $tool,
                    'key' => (string) $key,
                    'items' => array_values(array_filter($value, 'is_array')),
                    'payload' => $payload,
                ];
            }
        }

        return $lists;
    }

    /**
     * The list that actually answers the question.
     *
     * The last tool call is the answer step, and within its payload the answer is the
     * list carrying rows — falling back, when everything is empty, to the first list
     * that is not describing the query itself. That fallback is what turns a fruitless
     * search into "No students matched" rather than "No unresolved filters matched".
     *
     * @param  array<int, array<string, mixed>>  $lists
     * @return array<string, mixed>|null
     */
    private function answerList(array $lists): ?array
    {
        $lastTool = $lists[array_key_last($lists)]['tool'];

        $candidates = array_values(array_filter(
            $lists,
            static fn (array $list) => $list['tool'] === $lastTool
                && ! in_array($list['key'], self::META_LISTS, true)
        ));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b) => count($b['items']) <=> count($a['items']));

        return $candidates[0];
    }

    /**
     * @param  array{key:string, items:array<int, mixed>, payload:array<string, mixed>}  $list
     */
    private function headline(array $list): string
    {
        // `count` is the tool's own total, which can exceed what it returned when a limit
        // applied. Falling back to the row count keeps the sentence true either way.
        $total = $list['payload']['count'] ?? count($list['items']);
        $noun = str_replace('_', ' ', $list['key']);

        return sprintf('%d %s.', (int) $total, $noun);
    }

    /**
     * @param  array{tool:string, key:string}  $list
     */
    private function sectionTitle(array $list): string
    {
        return ucfirst(str_replace('_', ' ', $list['key']));
    }

    /**
     * One record, rendered as a heading plus the fields that qualify it.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function record(array $item): array
    {
        $title = null;

        foreach (self::TITLE_KEYS as $key) {
            if (! empty($item[$key]) && is_scalar($item[$key])) {
                $title = (string) $item[$key];
                break;
            }
        }

        $meta = [];

        foreach ($item as $key => $value) {
            if (! is_scalar($value) || $value === '' || $value === null) {
                continue;
            }

            if (in_array($key, self::HIDDEN_KEYS, true) || (string) $value === $title) {
                continue;
            }

            // An id is worth keeping only when there is nothing better to call the row.
            if (str_ends_with((string) $key, '_id') && $title !== null) {
                continue;
            }

            $meta[ucfirst(str_replace('_', ' ', (string) $key))] = is_bool($value)
                ? ($value ? 'yes' : 'no')
                : (string) $value;
        }

        return [
            'title' => $title ?? 'Record',
            'lines' => [],
            'meta' => array_slice($meta, 0, 5, true),
        ];
    }

    /**
     * Scalar figures the payloads reported next to their lists.
     *
     * @param  array<int, array<string, mixed>>  $lists
     * @return array<string, string>
     */
    private function facts(array $lists): array
    {
        $facts = [];

        foreach ($lists as $list) {
            foreach ($list['payload'] as $key => $value) {
                if (! is_scalar($value) || $value === null || $value === '') {
                    continue;
                }

                // `count` is already the headline, and echoing it reads as padding.
                if (in_array($key, ['count', 'query', 'note', 'rule'], true)) {
                    continue;
                }

                $facts[ucfirst(str_replace('_', ' ', (string) $key))] = is_bool($value)
                    ? ($value ? 'yes' : 'no')
                    : (string) $value;
            }
        }

        return array_slice($facts, 0, 8, true);
    }
}
