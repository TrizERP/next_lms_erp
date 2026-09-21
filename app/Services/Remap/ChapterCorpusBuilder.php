<?php

namespace App\Services\Remap;

use App\Services\Remap\Support\TextNormalizer as T;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds the candidate side of the match: one profile per current
 * std-10 chapter.
 *
 * The critical domain fact encoded here is that chapter_name is a
 * GENERIC PLACEHOLDER for every subject except Mathematics
 * ("Democratic Politics - Chapter 2", "Sparsh - Chapter 3"). Matching
 * on it would be matching on noise, so it carries weight 1 everywhere
 * and weight 3 only for Maths, where the titles are real
 * ("REAL NUMBERS", "TRIANGLES").
 *
 * All real semantic signal comes from chapter_master.key_concepts
 * (JSON) and lms_concept.
 */
class ChapterCorpusBuilder
{
    /** Field weights for the candidate document. */
    private const W_CHAPTER_NAME      = 1.0;
    private const W_CHAPTER_NAME_MATH = 3.0;
    private const W_KEY_CONCEPT_NAME  = 3.0;
    private const W_KEY_CONCEPT_DESC  = 1.0;
    private const W_CONCEPT_NAME      = 3.0;
    private const W_CONCEPT_DESC      = 1.0;
    private const W_TOPIC_NAME        = 3.0;

    private array $scope;
    private int $malformedKeyConcepts = 0;

    public function __construct(?array $scope = null)
    {
        $this->scope = $scope ?? config('remap.scope');
    }

    /**
     * @return array<int, array> chapter_id => profile
     */
    public function build(): array
    {
        $chapters = DB::table('chapter_master')
            ->where('standard_id', $this->scope['standard_id'])
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->get([
                'id', 'subject_id', 'standard_id', 'chapter_name',
                'key_concepts', 'sort_order', 'sub_institute_id',
            ]);

        $concepts = $this->conceptsByChapter();
        $topics   = $this->topicsByChapter();
        $subjects = DB::table('subject')->pluck('subject_name', 'id');
        $mathsId  = (int) ($this->scope['maths_subject_id'] ?? 0);
        $minLen   = (int) config('remap.lexical.min_token_len', 3);

        $corpus = [];

        foreach ($chapters as $chapter) {
            $chapterId = (int) $chapter->id;
            $subjectId = (int) $chapter->subject_id;

            $keyConcepts  = $this->decodeKeyConcepts($chapter->key_concepts, $chapterId);
            $rowConcepts  = $concepts[$chapterId] ?? [];
            $rowTopics    = $topics[$chapterId] ?? [];
            $nameWeight   = $subjectId === $mathsId ? self::W_CHAPTER_NAME_MATH : self::W_CHAPTER_NAME;

            $fields = [
                ['terms' => T::tokens($chapter->chapter_name, $minLen), 'weight' => $nameWeight],
            ];

            foreach ($keyConcepts as $kc) {
                $fields[] = ['terms' => T::tokens($kc['name'] ?? '', $minLen), 'weight' => self::W_KEY_CONCEPT_NAME];
                $fields[] = ['terms' => T::tokens($kc['description'] ?? '', $minLen), 'weight' => self::W_KEY_CONCEPT_DESC];
            }

            foreach ($rowTopics as $t) {
                $fields[] = ['terms' => T::tokens($t['name'], $minLen), 'weight' => self::W_TOPIC_NAME];
            }

            foreach ($rowConcepts as $c) {
                $fields[] = ['terms' => T::tokens($c['name'], $minLen), 'weight' => self::W_CONCEPT_NAME];
                $fields[] = ['terms' => T::tokens(trim(($c['description'] ?? '') . ' ' . ($c['definition'] ?? '')), $minLen), 'weight' => self::W_CONCEPT_DESC];
            }

            // The BM25 index needs a flat term list; repetition is the
            // weighting mechanism, so a weight of 3 means the term is
            // contributed three times.
            $terms = [];
            $blob  = [];

            foreach ($fields as $field) {
                $repeat = max(1, (int) round($field['weight']));
                for ($i = 0; $i < $repeat; $i++) {
                    foreach ($field['terms'] as $term) {
                        $terms[] = $term;
                    }
                }
                $blob[] = implode(' ', $field['terms']);
            }

            // Concept labels the adjudicator is allowed to cite. Anything
            // outside this list, cited by a model, is a hallucination.
            $labels = [];
            foreach ($rowConcepts as $c) {
                $labels[] = ['id' => $c['id'], 'name' => $c['name'], 'description' => $c['description'] ?? ''];
            }
            foreach ($keyConcepts as $kc) {
                if (!empty($kc['name'])) {
                    $labels[] = ['id' => null, 'name' => $kc['name'], 'description' => $kc['description'] ?? ''];
                }
            }

            $corpus[$chapterId] = [
                'chapter_id'       => $chapterId,
                'subject_id'       => $subjectId,
                'standard_id'      => (int) $chapter->standard_id,
                'sub_institute_id' => (int) $chapter->sub_institute_id,
                'chapter_name'     => (string) $chapter->chapter_name,
                'subject_name'     => (string) ($subjects[$subjectId] ?? ('subject ' . $subjectId)),
                'sort_order'       => (int) $chapter->sort_order,
                'is_placeholder'   => $this->looksLikePlaceholder($chapter->chapter_name),
                'terms'            => $terms,
                'concept_rows'     => $rowConcepts,
                'concept_labels'   => $labels,
                'concept_count'    => count($rowConcepts),
                'topic_rows'       => $rowTopics,
                'topic_count'      => count($rowTopics),
                'devanagari'       => T::devanagariRatio(implode(' ', $blob)),
            ];
        }

        if ($this->malformedKeyConcepts > 0) {
            Log::channel('remap')->warning(
                "key_concepts JSON unparseable for {$this->malformedKeyConcepts} chapter(s); " .
                'those chapters ranked on lms_concept rows alone.'
            );
        }

        return $corpus;
    }

    public function malformedCount(): int
    {
        return $this->malformedKeyConcepts;
    }

    /**
     * Concepts grouped by chapter, restricted to the std-10 tenant and
     * to concepts that are not hidden.
     *
     * @return array<int, array<int, array>>
     */
    private function conceptsByChapter(): array
    {
        $rows = DB::table('lms_concept')
            ->where('standard_id', $this->scope['standard_id'])
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->where('concept_show_hide', 1)
            ->orderBy('chapter_id')
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'definition', 'chapter_id', 'subject_id', 'standard_id', 'sub_institute_id']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->chapter_id][] = [
                'id'               => (int) $row->id,
                'name'             => (string) $row->name,
                'description'      => (string) ($row->description ?? ''),
                'definition'       => (string) ($row->definition ?? ''),
                'chapter_id'       => (int) $row->chapter_id,
                'subject_id'       => (int) $row->subject_id,
                'standard_id'      => (int) $row->standard_id,
                'sub_institute_id' => (int) $row->sub_institute_id,
            ];
        }

        return $out;
    }

    /**
     * Decode chapter_master.key_concepts defensively.
     *
     * Accepts both [{name,description}] and {concepts:[...]} shapes. A
     * chapter whose JSON will not parse must still be usable via its
     * lms_concept rows, so this never throws.
     *
     * @return array<int, array{name: string, description: string}>
     */
    private function decodeKeyConcepts(?string $json, int $chapterId): array
    {
        if ($json === null || trim($json) === '' || trim($json) === '[]') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            $this->malformedKeyConcepts++;
            Log::channel('remap')->debug("chapter {$chapterId}: key_concepts not valid JSON");
            return [];
        }

        if (isset($decoded['concepts']) && is_array($decoded['concepts'])) {
            $decoded = $decoded['concepts'];
        }

        $out = [];

        foreach ($decoded as $item) {
            if (is_string($item)) {
                $out[] = ['name' => $item, 'description' => ''];
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $name = $item['name'] ?? $item['concept'] ?? $item['title'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            $desc = $item['description'] ?? $item['desc'] ?? $item['definition'] ?? '';

            $out[] = [
                'name'        => trim($name),
                'description' => is_string($desc) ? trim($desc) : '',
            ];
        }

        return $out;
    }

    /**
     * Detect the generic "<Book> - Chapter N" naming so prompts can flag
     * it and humans reading the report are not misled by it.
     */
    private function looksLikePlaceholder(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return true;
        }

        return (bool) preg_match('/-\s*chapter\s*\d+\s*$/i', trim($name));
    }

    /**
     * Topics grouped by chapter.
     *
     * topic_master carries real, human-written names ("Chemical
     * Equations", "pH scale") for all 115 std-10 chapters, unlike
     * chapter_master.chapter_name which is a placeholder for every
     * subject but Maths. It is therefore a first-class matching signal,
     * and it supplies the topic_id that mapped questions receive.
     *
     * @return array<int, array<int, array>>
     */
    private function topicsByChapter(): array
    {
        $chapterIds = DB::table('chapter_master')
            ->where('standard_id', $this->scope['standard_id'])
            ->where('sub_institute_id', $this->scope['sub_institute_id'])
            ->pluck('id');

        if ($chapterIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('topic_master')
            ->whereIn('chapter_id', $chapterIds)
            ->orderBy('chapter_id')
            ->orderBy('topic_sort_order')
            ->orderBy('id')
            ->get(['id', 'chapter_id', 'name', 'description', 'main_topic_id', 'topic_show_hide']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->chapter_id][] = [
                'id'            => (int) $row->id,
                'chapter_id'    => (int) $row->chapter_id,
                'name'          => (string) $row->name,
                'description'   => (string) ($row->description ?? ''),
                'main_topic_id' => $row->main_topic_id !== null ? (int) $row->main_topic_id : null,
            ];
        }

        return $out;
    }
}
