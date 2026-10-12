<?php

namespace App\Services\lms\H5P;

/**
 * The save payload of a Branching Scenario, read as a graph and as content.
 *
 * A PAYLOAD NODE is what the editor sends and what the generator produces:
 *
 *   ref, node_type, purpose, title, show_title, body, media_path, media_alt,
 *   proceed_button_text, next_ref, score, concept_id, source_question_id,
 *   options: [{text, next_ref, quality, feedback_title, feedback_text,
 *              feedback_image, feedback_image_alt}]
 *
 * It addresses other nodes by `ref`, never by database id, so a brand new node
 * can lead to another brand new node in one save. `fromRows()` produces the
 * same shape from saved rows (the id becomes the ref), which is what lets the
 * publish check and the generator's validator be one piece of code.
 *
 * PURE, like BranchingGraph: no framework, no database.
 */
final class BranchingPayload
{
    /** Quality values a choice may carry. Mirrors H5pBranchingNode::QUALITIES. */
    public const QUALITIES = ['best', 'partial', 'misstep'];

    /**
     * Saved rows -> payload nodes (the id is the ref).
     *
     * @param  iterable<object|array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    public static function fromRows(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $r = is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : (array) $row);

            $options = [];
            foreach ((array) ($r['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $options[] = [
                    'text' => $option['text'] ?? '',
                    'next_ref' => isset($option['next_node_id']) && $option['next_node_id'] !== null ? (string) $option['next_node_id'] : null,
                    'quality' => $option['quality'] ?? null,
                    'feedback_title' => $option['feedback_title'] ?? '',
                    'feedback_text' => $option['feedback_text'] ?? '',
                    'feedback_image' => $option['feedback_image'] ?? '',
                    'feedback_image_alt' => $option['feedback_image_alt'] ?? '',
                ];
            }

            $out[] = [
                'ref' => (string) $r['id'],
                'node_type' => $r['node_type'] ?? 'text',
                'purpose' => $r['purpose'] ?? null,
                'title' => $r['title'] ?? '',
                'show_title' => (bool) ($r['show_title'] ?? true),
                'body' => $r['body'] ?? '',
                'media_path' => $r['media_path'] ?? '',
                'media_alt' => $r['media_alt'] ?? '',
                'proceed_button_text' => $r['proceed_button_text'] ?? '',
                'next_ref' => isset($r['next_node_id']) && $r['next_node_id'] !== null ? (string) $r['next_node_id'] : null,
                'score' => (int) ($r['score'] ?? 0),
                'concept_id' => $r['concept_id'] ?? null,
                'source_question_id' => $r['source_question_id'] ?? null,
                'options' => $options,
            ];
        }

        return $out;
    }

    /**
     * Payload nodes -> BranchingGraph nodes.
     *
     * @param  list<array<string,mixed>>  $nodes
     * @return list<array<string,mixed>>
     */
    public static function graphNodes(array $nodes): array
    {
        $out = [];
        foreach (array_values($nodes) as $i => $node) {
            $type = (string) ($node['node_type'] ?? 'text');
            $out[] = [
                'key' => (string) $node['ref'],
                'type' => $type,
                'label' => self::label($node, $i),
                'next' => $type === 'text' ? self::ref($node['next_ref'] ?? null) : null,
                'targets' => $type === 'branching_question'
                    ? array_map(fn ($o) => self::ref($o['next_ref'] ?? null), array_values((array) ($node['options'] ?? [])))
                    : [],
                'check' => ! empty($node['source_question_id']),
            ];
        }

        return $out;
    }

    /**
     * Is there anything wrong with this scenario that a learner would hit?
     *
     * `$strict` is the publish/generate standard: what is merely unfinished in
     * a draft (a choice with no feedback) becomes an error. Messages are
     * written for the person fixing them, and name the step.
     *
     * @param  list<array<string,mixed>>  $nodes
     * @return array{
     *     errors: list<array{code:string,node:?string,message:string}>,
     *     warnings: list<array{code:string,node:?string,message:string}>,
     *     stats: array<string,mixed>
     * }
     */
    public static function analyse(array $nodes, bool $strict = true): array
    {
        $graph = BranchingGraph::analyse(self::graphNodes($nodes));
        $errors = $graph['errors'];
        $warnings = $graph['warnings'];

        foreach (self::contentProblems($nodes) as $problem) {
            // A problem marked `strict` blocks only at the publish standard;
            // in a draft it is reported but does not stop the save.
            $level = $problem['strict'] && ! $strict ? 'warning' : $problem['level'];
            $entry = ['code' => $problem['code'], 'node' => $problem['node'], 'message' => $problem['message']];

            if ($level === 'error') {
                $errors[] = $entry;
            } else {
                $warnings[] = $entry;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'stats' => $graph['stats']];
    }

    /**
     * Content a learner would find missing.
     *
     * `strict` marks the problems that are only blocking at publish time.
     *
     * @param  list<array<string,mixed>>  $nodes
     * @return list<array{code:string,node:?string,message:string,level:string,strict:bool}>
     */
    public static function contentProblems(array $nodes): array
    {
        $problems = [];
        $add = function (string $code, string $ref, string $message, string $level = 'error', bool $strict = false) use (&$problems): void {
            $problems[] = ['code' => $code, 'node' => $ref, 'message' => $message, 'level' => $level, 'strict' => $strict];
        };

        foreach (array_values($nodes) as $i => $node) {
            $ref = (string) $node['ref'];
            $name = self::label($node, $i);
            $type = (string) ($node['node_type'] ?? 'text');
            $hasBody = self::plain($node['body'] ?? '') !== '';
            $hasMedia = trim((string) ($node['media_path'] ?? '')) !== '';

            if ($hasMedia && trim((string) ($node['media_alt'] ?? '')) === '') {
                // A picture a learner using a screen reader cannot perceive is
                // content they do not get. Drafts may be unfinished.
                $add('missing_alt', $ref, $name . ' has a picture with no description. Add alt text so it can be read aloud.', 'error', true);
            }

            if ($type === 'text' && ! $hasBody && ! $hasMedia) {
                $add('empty_screen', $ref, $name . ' has no text and no picture.');
            }

            if ($type === 'branching_question') {
                if (! $hasBody) {
                    $add('empty_question', $ref, $name . ' has no question for the learner to answer.');
                }
                foreach (array_values((array) ($node['options'] ?? [])) as $j => $option) {
                    $n = $j + 1;
                    if (trim((string) ($option['text'] ?? '')) === '') {
                        $add('empty_choice', $ref, 'Choice ' . $n . ' of ' . $name . ' has no text.');
                    }
                    if (self::plain($option['feedback_text'] ?? '') === '' && self::plain($option['feedback_title'] ?? '') === '') {
                        // A choice with no consequence is a red cross and
                        // nothing else. Only a warning: a choice may lead
                        // straight into a screen that explains it. The
                        // generator holds its own output to a stricter rule.
                        $add('no_feedback', $ref, 'Choice ' . $n . ' of ' . $name . ' does not say what happens or why.', 'warning');
                    }
                    $quality = $option['quality'] ?? null;
                    if ($quality !== null && $quality !== '' && ! in_array($quality, self::QUALITIES, true)) {
                        $add('bad_quality', $ref, 'Choice ' . $n . ' of ' . $name . ' has an unknown rating.');
                    }
                    if (trim((string) ($option['feedback_image'] ?? '')) !== '' && trim((string) ($option['feedback_image_alt'] ?? '')) === '') {
                        $add('missing_alt', $ref, 'The picture in the feedback for choice ' . $n . ' of ' . $name . ' has no description.', 'error', true);
                    }
                }
            }

            if ($type === 'ending' && self::plain($node['title'] ?? '') === '' && ! $hasBody) {
                $add('empty_ending', $ref, $name . ' has no title and no closing words.');
            }
        }

        return $problems;
    }

    /** "Step 3: Testing the water" -- or "Step 3" for an untitled one. */
    public static function label(array $node, int $index): string
    {
        $title = trim((string) ($node['title'] ?? ''));

        return $title !== '' ? 'Step ' . ($index + 1) . ' (' . $title . ')' : 'Step ' . ($index + 1);
    }

    /** Visible text of an HTML fragment, whitespace collapsed. */
    public static function plain(mixed $html): string
    {
        $text = strip_tags((string) ($html ?? ''));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function ref(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
