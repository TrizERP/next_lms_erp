<?php

namespace App\Services\Remap;

use App\Services\Remap\Support\TextNormalizer as T;
use Illuminate\Support\Facades\DB;

/**
 * Reconstructs the identity of a DELETED chapter from the content that
 * still points at it.
 *
 * This is the crux of the whole job: the 2026-09-17 re-seed removed the
 * old chapter_master rows, so there is no legacy chapter_name to match
 * on. The only evidence about legacy chapter X is the Q&A, content and
 * teacher-resource rows that still carry chapter_id = X.
 *
 * lms_question_master.concept / .subconcept are weighted highest
 * because they are human-authored topic labels that SURVIVED the
 * deletion -- the single most reliable signal available.
 */
class LegacyProfileBuilder
{
    private const W_Q_CONCEPT   = 4.0;
    private const W_Q_TITLE     = 2.0;
    private const W_Q_ANSWER    = 1.0;
    private const W_C_TITLE     = 3.0;
    private const W_C_CATEGORY  = 2.0;
    private const W_C_DESC      = 1.0;
    private const W_T_TITLE     = 3.0;
    private const W_T_ACTIVITY  = 2.0;
    private const W_T_MAPPING   = 2.0;
    private const W_T_DESC      = 1.0;

    private array $scope;

    public function __construct(?array $scope = null)
    {
        $this->scope = $scope ?? config('remap.scope');
    }

    /**
     * Every legacy (subject_id, chapter_id) pair that still has content
     * pointing at a chapter_master row which no longer exists.
     *
     * Scoped to the tenant that owns the new chapter set. Tenant 1000's
     * rows are deliberately excluded: mapping them onto tenant 1
     * chapters would be a cross-tenant write.
     *
     * @return array<int, array{subject_id:int, chapter_id:int, questions:int, content:int, teacher:int}>
     */
    public function groups(): array
    {
        $tenant   = $this->scope['sub_institute_id'];
        $standard = $this->scope['standard_id'];
        $groups   = [];

        $orphanClause = function ($query, string $alias) {
            $query->whereNotExists(function ($sub) use ($alias) {
                $sub->select(DB::raw(1))
                    ->from('chapter_master as cm')
                    ->whereColumn('cm.id', "{$alias}.chapter_id");
            });
        };

        $sources = [
            'questions' => ['lms_question_master as q', 'q', true],
            'content'   => ['content_master as c', 'c', false],
            'teacher'   => ['lms_teacher_resource as t', 't', false],
        ];

        foreach ($sources as $key => [$from, $alias, $softDeletable]) {
            $query = DB::table($from)
                ->where("{$alias}.sub_institute_id", $tenant)
                ->where("{$alias}.standard_id", $standard)
                ->whereNotNull("{$alias}.chapter_id")
                ->where("{$alias}.chapter_id", '>', 0);

            if ($softDeletable) {
                $query->whereNull("{$alias}.deleted_at");
            }

            $orphanClause($query, $alias);

            $rows = $query->groupBy("{$alias}.subject_id", "{$alias}.chapter_id")
                ->get([
                    DB::raw("{$alias}.subject_id as subject_id"),
                    DB::raw("{$alias}.chapter_id as chapter_id"),
                    DB::raw('COUNT(*) as n'),
                ]);

            foreach ($rows as $row) {
                $gk = ((int) $row->subject_id) . ':' . ((int) $row->chapter_id);

                $groups[$gk] ??= [
                    'subject_id' => (int) $row->subject_id,
                    'chapter_id' => (int) $row->chapter_id,
                    'questions'  => 0,
                    'content'    => 0,
                    'teacher'    => 0,
                ];

                $groups[$gk][$key] = (int) $row->n;
            }
        }

        $groups = array_values($groups);

        usort($groups, fn ($a, $b) => [$a['subject_id'], $a['chapter_id']] <=> [$b['subject_id'], $b['chapter_id']]);

        return $groups;
    }

    /**
     * Build the evidence profile for one legacy group.
     *
     * Sampling is seeded by the legacy chapter id, so repeated runs
     * produce byte-identical prompts -- which is what makes the LLM
     * cache effective and the decisions reproducible.
     */
    public function build(int $subjectId, int $chapterId, ?array $sources = null): array
    {
        $minLen = (int) config('remap.lexical.min_token_len', 3);
        $items  = array_merge(
            $this->questionItems($subjectId, $chapterId),
            $this->contentItems($subjectId, $chapterId),
            $this->teacherItems($subjectId, $chapterId)
        );

        // Entity-type scoping. A legacy chapter id can mean different
        // topics in different tables (see group 4064/1039), so the
        // pipeline must be able to profile each entity type on its own.
        if ($sources !== null) {
            $items = array_values(array_filter($items, fn ($i) => in_array($i['source'], $sources, true)));
        }

        $labels = [];
        foreach ($items as $item) {
            foreach ($item['labels'] ?? [] as $label) {
                $flat = T::flatten($label);
                if ($flat !== '') {
                    $labels[$flat] = $label;
                }
            }
        }

        // Display sample only. The query vector below is built from
        // ALL items: sampling by length biases the profile toward long
        // answer text, which mis-ranked Geography group 6237 (Forest and
        // Wildlife) as Water Resources.
        $sampled = $this->sample($items, $chapterId);

        $fields    = [];
        $blob      = [];
        $charCount = 0;

        foreach ($items as $item) {
            $fields[] = ['terms' => T::tokens($item['text'], $minLen), 'weight' => $item['weight']];
            $blob[]   = $item['text'];
            $charCount += mb_strlen(T::flatten($item['text']), 'UTF-8');
        }

        // Distinct legacy concept labels are the strongest surviving
        // signal, so they enter the query vector regardless of sampling.
        foreach ($labels as $label) {
            $fields[] = ['terms' => T::tokens($label, $minLen), 'weight' => self::W_Q_CONCEPT];
            $blob[]   = $label;
        }

        $query  = \App\Services\Remap\Support\Bm25::weightedQuery($fields);
        $allTxt = implode(' ', $blob);
        $tokens = T::tokens($allTxt, $minLen);

        return [
            'subject_id'   => $subjectId,
            'chapter_id'   => $chapterId,
            'query'        => $query,
            'keyphrases'   => $this->keyphrases($tokens, $query),
            'bigrams'      => array_values(array_unique(T::bigrams($tokens))),
            'labels'       => array_values($labels),
            'items'        => $items,
            'sampled'      => $sampled,
            'item_count'   => count($items),
            'char_count'   => $charCount,
            'devanagari'   => T::devanagariRatio($allTxt),
            'counts'       => [
                'questions' => count(array_filter($items, fn ($i) => $i['source'] === 'questions')),
                'content'   => count(array_filter($items, fn ($i) => $i['source'] === 'content')),
                'teacher'   => count(array_filter($items, fn ($i) => $i['source'] === 'teacher')),
            ],
            'is_thin'      => count($items) < (int) config('remap.thresholds.thin_profile_items', 3)
                              || $charCount < (int) config('remap.thresholds.thin_profile_chars', 200),
        ];
    }

    /** @return array<int, array> */
    private function questionItems(int $subjectId, int $chapterId): array
    {
        $rows = DB::table('lms_question_master')
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->where('standard_id', $this->scope['standard_id'])
            ->where('subject_id', $subjectId)
            ->where('chapter_id', $chapterId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'question_title', 'answer', 'concept', 'subconcept']);

        $out = [];

        foreach ($rows as $row) {
            $labels = array_values(array_filter([
                trim((string) ($row->concept ?? '')),
                trim((string) ($row->subconcept ?? '')),
            ], fn ($v) => $v !== ''));

            $out[] = [
                'source' => 'questions',
                'id'     => (int) $row->id,
                'text'   => (string) $row->question_title,
                'weight' => self::W_Q_TITLE,
                'labels' => $labels,
                'title'  => (string) $row->question_title,
            ];

            if (!empty($row->answer)) {
                $out[] = [
                    'source' => 'questions',
                    'id'     => (int) $row->id,
                    'text'   => (string) $row->answer,
                    'weight' => self::W_Q_ANSWER,
                    'labels' => [],
                    'title'  => null,
                ];
            }
        }

        return $out;
    }

    /** @return array<int, array> */
    private function contentItems(int $subjectId, int $chapterId): array
    {
        $rows = DB::table('content_master')
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->where('standard_id', $this->scope['standard_id'])
            ->where('subject_id', $subjectId)
            ->where('chapter_id', $chapterId)
            ->orderBy('id')
            ->get(['id', 'title', 'description', 'content_category']);

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'source' => 'content',
                'id'     => (int) $row->id,
                'text'   => (string) $row->title,
                'weight' => self::W_C_TITLE,
                'labels' => [],
                'title'  => (string) $row->title,
            ];

            // content_category is a coarse but real signal ("Revision
            // Notes", "Classroom Activity"); weighted below the title.
            if (!empty($row->content_category)) {
                $out[] = [
                    'source' => 'content', 'id' => (int) $row->id,
                    'text' => (string) $row->content_category,
                    'weight' => self::W_C_CATEGORY, 'labels' => [], 'title' => null,
                ];
            }

            if (!empty($row->description)) {
                $out[] = [
                    'source' => 'content', 'id' => (int) $row->id,
                    'text' => (string) $row->description,
                    'weight' => self::W_C_DESC, 'labels' => [], 'title' => null,
                ];
            }
        }

        return $out;
    }

    /** @return array<int, array> */
    private function teacherItems(int $subjectId, int $chapterId): array
    {
        $rows = DB::table('lms_teacher_resource')
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->where('standard_id', $this->scope['standard_id'])
            ->where('subject_id', $subjectId)
            ->where('chapter_id', $chapterId)
            ->orderBy('id')
            ->get(['id', 'title', 'description', 'activity', 'mapping_value']);

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'source' => 'teacher', 'id' => (int) $row->id,
                'text' => (string) $row->title, 'weight' => self::W_T_TITLE,
                'labels' => [], 'title' => (string) $row->title,
            ];

            foreach ([
                ['activity', self::W_T_ACTIVITY],
                ['mapping_value', self::W_T_MAPPING],
                ['description', self::W_T_DESC],
            ] as [$column, $weight]) {
                if (!empty($row->{$column})) {
                    $out[] = [
                        'source' => 'teacher', 'id' => (int) $row->id,
                        'text' => (string) $row->{$column}, 'weight' => $weight,
                        'labels' => [], 'title' => null,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Deterministic sample: the longest items (most topic-bearing) plus
     * a spread selected by a hash seeded on the legacy chapter id.
     *
     * Seeding on the chapter id rather than a random source is what
     * makes a re-run produce the same prompt, and therefore the same
     * prompt_hash and a free cache hit.
     *
     * @param  array<int, array> $items
     * @return array<int, array>
     */
    private function sample(array $items, int $seed): array
    {
        $max = (int) config('remap.batch.profile_sample', 40);

        if (count($items) <= $max) {
            return $items;
        }

        $half = (int) floor($max / 2);

        $byLength = $items;
        usort($byLength, function ($a, $b) {
            $cmp = mb_strlen($b['text'], 'UTF-8') <=> mb_strlen($a['text'], 'UTF-8');
            // Tie-break on id so the ordering is total and stable.
            return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']);
        });

        $picked = [];
        foreach (array_slice($byLength, 0, $half) as $item) {
            $picked[$item['source'] . ':' . $item['id'] . ':' . mb_substr($item['text'], 0, 16)] = $item;
        }

        $spread = $items;
        usort($spread, function ($a, $b) use ($seed) {
            $ha = crc32($seed . '|' . $a['source'] . '|' . $a['id'] . '|' . mb_substr($a['text'], 0, 32));
            $hb = crc32($seed . '|' . $b['source'] . '|' . $b['id'] . '|' . mb_substr($b['text'], 0, 32));
            return $ha <=> $hb;
        });

        foreach ($spread as $item) {
            if (count($picked) >= $max) {
                break;
            }
            $picked[$item['source'] . ':' . $item['id'] . ':' . mb_substr($item['text'], 0, 16)] = $item;
        }

        return array_values($picked);
    }

    /**
     * Top weighted unigrams and bigrams. Used for the out-of-syllabus
     * coverage probe, which asks whether this vocabulary appears
     * ANYWHERE in the surviving corpus.
     *
     * @param  string[]            $tokens
     * @param  array<string,float> $query
     * @return string[]
     */
    private function keyphrases(array $tokens, array $query, int $limit = 20): array
    {
        arsort($query);
        $top = array_slice(array_keys($query), 0, $limit);

        $bigramFreq = [];
        foreach (T::bigrams($tokens) as $bigram) {
            $bigramFreq[$bigram] = ($bigramFreq[$bigram] ?? 0) + 1;
        }
        arsort($bigramFreq);

        // Only bigrams seen more than once are worth treating as phrases.
        $phrases = array_keys(array_filter($bigramFreq, fn ($n) => $n > 1));

        return array_values(array_unique(array_merge($top, array_slice($phrases, 0, 10))));
    }
}
