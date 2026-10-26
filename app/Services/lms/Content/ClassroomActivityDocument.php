<?php

namespace App\Services\lms\Content;

/**
 * An interactive Classroom Activity: one hands-on task card (steps, a worksheet to fill in, a discussion prompt), stored
 * as ONE content_master row.
 *
 * Same idea as the Vocabulary Class: the activity is a single JSON document in `content_master.description`, so there is
 * no activity table, no row per step and no sidecar file. The row is recognised by its file name
 * (`classroom_activity__<key>.json`, "the name is the identity") and filed under the EXISTING 'Classroom Activity'
 * category, next to the PDF task cards that are already there. Nothing is replaced: the PDF and the interactive activity
 * are two resources.
 *
 * What a document holds is the textbook's own activity: its steps in the printed order and wording, the worksheet the task
 * card asks learners to fill in, and the discussion prompt. It holds no results. A learner's measurements and answers are
 * theirs; the player keeps them in the browser and never sends them anywhere.
 *
 * Pure validation lives here so the store command, the read API and the tests share one definition of "an activity".
 */
class ClassroomActivityDocument
{
    public const TYPE = 'classroom_activity';

    public const VERSION = 1;

    /** The existing Classroom Resource category and tab: "Classroom activity". */
    public const CATEGORY = 'Classroom Activity';

    public const FILE_PREFIX = 'classroom_activity__';

    /** Not a file format a viewer would open: the card shows it as "Interactive". */
    public const FILE_TYPE = 'interactive';

    /** content_master.source: written by people from a supplied document, not generated. */
    public const SOURCE = 'Authored';

    private const BLOCK_KINDS = ['table', 'compare', 'notes', 'report'];

    public static function isActivityFilename(?string $filename): bool
    {
        return str_starts_with((string) $filename, self::FILE_PREFIX) && str_ends_with((string) $filename, '.json');
    }

    public static function filenameFor(string $key): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($key)), '_');

        return self::FILE_PREFIX . $slug . '.json';
    }

    /** The student route that plays an activity, addressed by the chapter the learner came from. */
    public static function deepLink(int $chapterId, int $contentId): string
    {
        return '/student/classroom-activity/' . $chapterId . '?content=' . $contentId;
    }

    /**
     * Why a document is not a playable activity. An empty list means it is one.
     *
     * Strict about what the player relies on (numbered steps, resolvable references to blocks, columns, groups and rows),
     * silent about anything extra, so a document with added fields still plays.
     *
     * @return list<string>
     */
    public static function problems(mixed $doc): array
    {
        if (!is_array($doc)) {
            return ['The document is not a JSON object.'];
        }
        $problems = [];
        if (($doc['type'] ?? null) !== self::TYPE) {
            $problems[] = 'type must be "' . self::TYPE . '".';
        }
        if (($doc['version'] ?? null) !== self::VERSION) {
            $problems[] = 'version must be ' . self::VERSION . '.';
        }
        foreach (['key', 'code', 'title'] as $field) {
            if (trim((string) ($doc[$field] ?? '')) === '') {
                $problems[] = $field . ' is required.';
            }
        }
        $scope = (array) ($doc['scope'] ?? []);
        foreach (['sub_institute_id', 'standard_id', 'subject_id', 'chapter_id'] as $field) {
            if ((int) ($scope[$field] ?? 0) < 1) {
                $problems[] = 'scope.' . $field . ' is required.';
            }
        }
        if (trim((string) ($doc['intro']['purpose'] ?? '')) === '') {
            $problems[] = 'intro.purpose is required.';
        }
        foreach (['objectives', 'materials', 'safety'] as $list) {
            foreach ((array) ($doc['intro'][$list] ?? []) as $i => $entry) {
                if (trim((string) ($entry['text'] ?? '')) === '') {
                    $problems[] = 'intro.' . $list . '[' . $i . '] needs text.';
                }
            }
        }
        if (!is_array($doc['intro']['objectives'] ?? null) || $doc['intro']['objectives'] === []) {
            $problems[] = 'intro.objectives needs at least one objective.';
        }

        $blocks = self::indexBlocks($doc, $problems);
        $steps = $doc['steps'] ?? null;
        if (!is_array($steps) || $steps === []) {
            return array_merge($problems, ['steps must be a non-empty list.']);
        }
        $seen = [];
        foreach (array_values($steps) as $i => $step) {
            $at = 'steps[' . $i . ']';
            $n = (int) ($step['n'] ?? 0);
            if ($n !== $i + 1) {
                $problems[] = $at . '.n must be ' . ($i + 1) . ' (steps keep the printed order, numbered from 1).';
            }
            $id = trim((string) ($step['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                $problems[] = $at . ' needs a unique id.';
            }
            $seen[$id] = true;
            if (trim((string) ($step['text'] ?? '')) === '') {
                $problems[] = $at . ' needs text.';
            }
            if (isset($step['timer']) && (int) ($step['timer']['seconds'] ?? 0) < 1) {
                $problems[] = $at . '.timer needs seconds.';
            }
            foreach ((array) ($step['uses'] ?? []) as $u => $use) {
                self::checkUse($blocks, (array) $use, $at . '.uses[' . $u . ']', $problems);
            }
        }
        foreach ((array) ($doc['reflection'] ?? []) as $r => $reflection) {
            $at = 'reflection[' . $r . ']';
            if (trim((string) ($reflection['id'] ?? '')) === '' || trim((string) ($reflection['prompt'] ?? '')) === '') {
                $problems[] = $at . ' needs an id and a prompt.';
            }
            $step = (int) ($reflection['step'] ?? 0);
            if ($step < 1 || $step > count($steps)) {
                $problems[] = $at . '.step must name one of the steps.';
            }
        }
        if (isset($doc['expected'])) {
            $e = (array) $doc['expected'];
            if (trim((string) ($e['text'] ?? '')) === '' || (int) ($e['step'] ?? 0) < 1 || (int) ($e['step'] ?? 0) > count($steps)) {
                $problems[] = 'expected needs text and a step.';
            }
            if (isset($e['requires']) && !isset($blocks[$e['requires']])) {
                $problems[] = 'expected.requires names no block.';
            }
        }

        return $problems;
    }

    /**
     * @param  array<string,mixed>  $doc
     * @param  list<string>  $problems
     * @return array<string,array<string,mixed>> blocks by id
     */
    private static function indexBlocks(array $doc, array &$problems): array
    {
        $blocks = [];
        foreach ((array) ($doc['blocks'] ?? []) as $b => $block) {
            $at = 'blocks[' . $b . ']';
            $id = trim((string) ($block['id'] ?? ''));
            if ($id === '' || isset($blocks[$id])) {
                $problems[] = $at . ' needs a unique id.';
                continue;
            }
            $kind = $block['kind'] ?? null;
            if (!in_array($kind, self::BLOCK_KINDS, true)) {
                $problems[] = $at . '.kind must be one of ' . implode(', ', self::BLOCK_KINDS) . '.';
                continue;
            }
            $blocks[$id] = $block;

            if ($kind === 'table') {
                $rows = self::ids((array) ($block['rows'] ?? []), $at . '.rows', $problems);
                $cols = self::ids((array) ($block['columns'] ?? []), $at . '.columns', $problems);
                if ($rows === [] || $cols === []) {
                    $problems[] = $at . ' needs rows and columns.';
                }
                foreach ((array) ($block['derived'] ?? []) as $d => $derived) {
                    if (!isset($cols[$derived['of'] ?? '']) || !isset($cols[$derived['minus'] ?? '']) || trim((string) ($derived['id'] ?? '')) === '') {
                        $problems[] = $at . '.derived[' . $d . '] must subtract one column from another.';
                    }
                }
            } elseif ($kind === 'compare') {
                $entities = self::ids((array) ($block['entities'] ?? []), $at . '.entities', $problems);
                $rows = self::ids((array) ($block['rows'] ?? []), $at . '.rows', $problems);
                if (count($entities) < 2 || $rows === []) {
                    $problems[] = $at . ' needs at least two entities and some rows.';
                }
            } elseif ($kind === 'notes') {
                if (trim((string) ($block['label'] ?? '')) === '') {
                    $problems[] = $at . ' needs a label.';
                }
            } elseif ($kind === 'report') {
                $sections = self::ids((array) ($block['sections'] ?? []), $at . '.sections', $problems);
                if ($sections === [] || trim((string) ($block['title_template'] ?? '')) === '') {
                    $problems[] = $at . ' needs a title_template and sections.';
                }
                foreach ((array) ($block['sections'] ?? []) as $s => $section) {
                    $min = (int) ($section['min'] ?? 0);
                    $max = (int) ($section['max'] ?? 0);
                    if ($min < 1 || $max < $min) {
                        $problems[] = $at . '.sections[' . $s . '] needs 1 <= min <= max.';
                    }
                }
            }
        }
        // A report fills its names from a comparison; the reference and its tokens must resolve.
        foreach ($blocks as $id => $block) {
            if (($block['kind'] ?? null) !== 'report') {
                continue;
            }
            $from = $blocks[$block['names_from'] ?? ''] ?? null;
            if (!$from || ($from['kind'] ?? null) !== 'compare') {
                $problems[] = 'block "' . $id . '" names_from must name a compare block.';
                continue;
            }
            $entities = self::ids((array) ($from['entities'] ?? []), 'entities', $problems);
            foreach ((array) ($block['tokens'] ?? []) as $token => $entity) {
                if (!isset($entities[$entity])) {
                    $problems[] = 'block "' . $id . '" token ' . $token . ' names no entity.';
                }
            }
        }

        return $blocks;
    }

    /**
     * @param  list<array<string,mixed>>  $list
     * @param  list<string>  $problems
     * @return array<string,true>
     */
    private static function ids(array $list, string $where, array &$problems): array
    {
        $ids = [];
        foreach (array_values($list) as $i => $entry) {
            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '' || isset($ids[$id]) || trim((string) ($entry['label'] ?? '')) === '') {
                $problems[] = $where . '[' . $i . '] needs a unique id and a label.';
                continue;
            }
            $ids[$id] = true;
        }

        return $ids;
    }

    /**
     * A step shows a block (or part of one). What it names must exist.
     *
     * @param  array<string,array<string,mixed>>  $blocks
     * @param  array<string,mixed>  $use
     * @param  list<string>  $problems
     */
    private static function checkUse(array $blocks, array $use, string $at, array &$problems): void
    {
        $block = $blocks[$use['block'] ?? ''] ?? null;
        if (!$block) {
            $problems[] = $at . '.block names no block.';

            return;
        }
        foreach ((array) ($use['columns'] ?? []) as $column) {
            if (($block['kind'] ?? null) !== 'table' || !in_array($column, array_column((array) $block['columns'], 'id'), true)) {
                $problems[] = $at . '.columns names a column the block does not have: ' . $column . '.';
            }
        }
        if (($block['kind'] ?? null) === 'compare') {
            $rows = (array) $block['rows'];
            $groups = array_unique(array_column($rows, 'group'));
            if (isset($use['group']) && $use['group'] !== 'names' && !in_array($use['group'], $groups, true)) {
                $problems[] = $at . '.group names a group the block does not have: ' . $use['group'] . '.';
            }
            foreach ((array) ($use['rows'] ?? []) as $row) {
                if (!in_array($row, array_column($rows, 'id'), true)) {
                    $problems[] = $at . '.rows names a row the block does not have: ' . $row . '.';
                }
            }
        }
    }

    /** The activity stored in a content_master row, or null when the row is not a readable activity. */
    public static function decode(?string $description): ?array
    {
        $doc = json_decode((string) $description, true);

        return is_array($doc) && self::problems($doc) === [] ? $doc : null;
    }

    /**
     * The fields the Classroom Resource list adds to a stored activity's row: where it opens, its code, how many steps it has.
     *
     * @param  array<string,mixed>  $row  a content_master row as an array
     * @return array<string,mixed> nothing when the row is not a readable activity
     */
    public static function listingFields(array $row, int $chapterId): array
    {
        if (!self::isActivityFilename($row['filename'] ?? null) || empty($row['id'])) {
            return [];
        }
        $doc = self::decode($row['description'] ?? null);
        if ($doc === null) {
            return [];
        }
        $link = self::deepLink($chapterId, (int) $row['id']);

        return [
            'deep_link' => $link,
            'url' => $link,
            'activity_code' => (string) $doc['code'],
            'activity_steps' => count($doc['steps']),
        ];
    }
}
