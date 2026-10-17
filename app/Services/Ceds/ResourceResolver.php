<?php

namespace App\Services\Ceds;

use App\Services\StudyDeck\Documents\DocumentKind;

/**
 * Which of the six resource formats (D1-D6) already exist for a chapter, from rows
 * the caller has already read.
 *
 * READ-ONLY BY CONSTRUCTION. This class never touches the database: it is handed
 * `content_master` rows and `lms_prayogshala_activity` rows and sorts them. It cannot
 * overwrite, hide, delete or duplicate anything, and it only ever points at stored
 * rows by id. The only existing code it calls is the pure `DocumentKind` name/category
 * matcher, so a row counts as a study document by exactly the rule the library and
 * StudyDeckApiController already use.
 *
 * The rules mirror StudyDeckApiController::resolveContent:
 *   - chapter match, the school rule (caller passes the tenants it may read),
 *   - visible = show_hide is null or not 0 (a hidden row is an older version the
 *     replacing store path hid; it is counted but never offered),
 *   - a study deck is category 'Classroom Presentation' + pptx + `study_deck_*`,
 *   - a study document is its strict `study_*` file name AND the matching category.
 *
 * Statuses per format: 'available', 'pending_review' (only Prayogshala labs that are
 * still in review), 'partial' (only D1, when just one of its two resources exists) or
 * 'missing'. A lab in review is NEVER reported as approved.
 */
class ResourceResolver
{
    public const AVAILABLE = 'available';
    public const PENDING_REVIEW = 'pending_review';
    public const MISSING = 'missing';

    public const PENDING_REVIEW_LABEL = 'Pending Review';

    private const LEGACY_PRESENTATION_CATEGORIES = ['classroom presentation', 'presentation'];

    /** @param string $sourceLabel content_master.source of generated rows (config claude.source_label) */
    public function __construct(private string $sourceLabel = 'Claude AI')
    {
    }

    /**
     * @param list<array<string,mixed>> $contentRows content_master rows of the chapter
     * @param list<array<string,mixed>> $labRows lms_prayogshala_activity rows of the chapter
     * @param list<int> $tenants sub_institute_ids the caller may read
     * @return array<string,array<string,mixed>> D1..D6 => result
     */
    public function resolve(int $chapterId, array $contentRows, array $labRows, array $tenants, ?array $formatLabels = null): array
    {
        $labels = $formatLabels ?? (array) array_map(fn ($f) => $f['label'], (array) config('ceds_fit_matrix.formats', []));
        $content = array_values(array_filter($contentRows, fn (array $r) => (int) ($r['chapter_id'] ?? 0) === $chapterId
            && in_array((int) ($r['sub_institute_id'] ?? 0), $tenants, true)));

        $presentation = $this->presentations($content);
        $revision = $this->documents(DocumentKind::RevisionNotes, $content);

        $out = [
            // D1 reuses the two stored resources below; it adds nothing of its own.
            'D1' => $this->classroomContent($presentation, $revision),
            'D2' => $presentation,
            'D3' => $this->documents(DocumentKind::Activities, $content),
            'D4' => $this->labs($chapterId, $labRows, $tenants),
            'D5' => $revision,
            'D6' => $this->documents(DocumentKind::Remedial, $content),
        ];
        foreach ($out as $code => &$result) {
            $result = ['format' => $code, 'label' => $labels[$code] ?? $code] + $result;
        }
        unset($result);

        return $out;
    }

    /**
     * D1 Classroom Content is not a stored category of its own. In this system it is met by two
     * existing resources of the chapter: the Classroom Presentation (the study deck, which carries
     * the hook, the explanation of every concept, worked examples and discussion questions) and
     * the Revision Notes. D1 is therefore made of THOSE rows, by id, and nothing else:
     *
     *   available  both are stored, visible and readable by this school
     *   partial    only one is; the other is named in the note
     *   missing    neither is (the only case in which anything could ever be offered for generation)
     *
     * It is computed from the same results as D2 and D5, so it can never disagree with them, and
     * a hidden or other-school row can never make D1 look available.
     *
     * @param array<string,mixed> $presentation the D2 result
     * @param array<string,mixed> $revision the D5 result
     * @return array<string,mixed>
     */
    private function classroomContent(array $presentation, array $revision): array
    {
        $parts = ['presentation' => ['Classroom Presentation', $presentation], 'revision_notes' => ['Revision Notes', $revision]];

        $items = [];
        $components = [];
        foreach ($parts as $key => [$label, $result]) {
            $ok = $result['items'] !== [];
            $components[$key] = ['label' => $label, 'status' => $ok ? self::AVAILABLE : self::MISSING, 'item_ids' => array_column($result['items'], 'id')];
            foreach ($result['items'] as $item) {
                $items[] = $item + ['component' => $key];
            }
        }

        $have = array_keys(array_filter($components, fn (array $c) => $c['status'] === self::AVAILABLE));
        $status = count($have) === count($parts) ? self::AVAILABLE : ($have === [] ? self::MISSING : 'partial');
        $absent = array_map(fn (string $k) => $components[$k]['label'], array_keys(array_diff_key($components, array_flip($have))));
        $note = match ($status) {
            self::AVAILABLE => 'Met by the existing Classroom Presentation and Revision Notes of this chapter.',
            'partial' => 'Only part of it exists. Missing: ' . implode(' and ', $absent) . '.',
            default => 'Neither the Classroom Presentation nor the Revision Notes exists for this chapter.',
        };

        return [
            'status' => $status,
            'items' => $items,
            'components' => $components,
            'hidden_ignored' => $presentation['hidden_ignored'] + $revision['hidden_ignored'],
            'unavailable_ignored' => 0,
            // Nothing is generated for D1 from here; this only says whether any part of it exists.
            'can_generate' => $status === self::MISSING,
            'note' => $note,
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private function presentations(array $rows): array
    {
        $items = [];
        $hidden = 0;
        foreach ($rows as $r) {
            $category = $this->category($r);
            $isDeck = $category === 'classroom presentation'
                && strtolower((string) ($r['file_type'] ?? '')) === 'pptx'
                && str_starts_with((string) ($r['filename'] ?? ''), 'study_deck_')
                && ($r['source'] ?? null) === $this->sourceLabel;
            $isLegacy = !$isDeck && in_array($category, self::LEGACY_PRESENTATION_CATEGORIES, true);
            if (!$isDeck && !$isLegacy) {
                continue;
            }
            if (!$this->visible($r)) {
                $hidden++;
                continue;
            }
            $items[] = $this->contentItem($r, $isDeck ? 'study_deck' : 'legacy', 'chapter');
        }

        return $this->result($this->newestFirst($items, 'study_deck'), $hidden);
    }

    /** @param list<array<string,mixed>> $rows */
    private function documents(DocumentKind $kind, array $rows): array
    {
        $items = [];
        $hidden = 0;
        foreach ($rows as $r) {
            if (DocumentKind::fromCategory((string) ($r['content_category'] ?? '')) !== $kind) {
                continue;
            }
            $filename = (string) ($r['filename'] ?? '');
            $isStudy = DocumentKind::fromFilename($filename) === $kind
                && strtolower((string) ($r['file_type'] ?? '')) === 'pdf'
                && ($r['source'] ?? null) === $this->sourceLabel;
            if (!$this->visible($r)) {
                $hidden++;
                continue;
            }
            $items[] = $this->contentItem($r, $isStudy ? 'study_document' : 'legacy', $isStudy ? $this->documentScope($filename) : 'chapter');
        }

        return $this->result($this->newestFirst($items, 'study_document'), $hidden);
    }

    /**
     * @param list<array<string,mixed>> $labs
     * @param list<int> $tenants
     */
    private function labs(int $chapterId, array $labs, array $tenants): array
    {
        $items = [];
        $unavailable = 0;
        foreach ($labs as $r) {
            if ((int) ($r['chapter_id'] ?? 0) !== $chapterId || !in_array((int) ($r['sub_institute_id'] ?? 0), $tenants, true)) {
                continue;
            }
            // A placeholder that is still generating, failed or has no lab is not a lab yet.
            $generation = (string) ($r['generation_status'] ?? '');
            if (in_array($generation, ['generating', 'failed', 'needs_content'], true) && empty($r['lab_config'])) {
                $unavailable++;
                continue;
            }
            $status = (string) ($r['status'] ?? '');
            if ($status !== 'published' && $status !== 'review') {
                $unavailable++;
                continue;
            }
            $items[] = [
                'id' => (int) $r['id'],
                'source' => 'lms_prayogshala_activity',
                'title' => (string) ($r['title'] ?? ''),
                'kind' => 'prayogshala_lab',
                'scope' => !empty($r['concept_id']) ? 'concept' : 'topic',
                'concept_id' => isset($r['concept_id']) ? (int) $r['concept_id'] : null,
                'topic_id' => isset($r['topic_id']) ? (int) $r['topic_id'] : null,
                'status' => $status === 'published' ? self::AVAILABLE : self::PENDING_REVIEW,
                'review_label' => $status === 'published' ? null : self::PENDING_REVIEW_LABEL,
            ];
        }
        usort($items, fn (array $a, array $b) => [$a['status'] === self::AVAILABLE ? 0 : 1, $a['id']] <=> [$b['status'] === self::AVAILABLE ? 0 : 1, $b['id']]);

        return $this->result($items, 0, null, $unavailable);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function result(array $items, int $hiddenIgnored, ?string $note = null, int $unavailable = 0): array
    {
        $anyAvailable = (bool) array_filter($items, fn (array $i) => $i['status'] === self::AVAILABLE);
        $status = $items === [] ? self::MISSING : ($anyAvailable ? self::AVAILABLE : self::PENDING_REVIEW);

        return [
            'status' => $status,
            'items' => $items,
            'hidden_ignored' => $hiddenIgnored,
            'unavailable_ignored' => $unavailable,
            // Offered for generation only when nothing usable exists; never to replace a stored item.
            'can_generate' => $status === self::MISSING,
            'note' => $note,
        ];
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function contentItem(array $r, string $kind, string $scope): array
    {
        return [
            'id' => (int) $r['id'],
            'source' => 'content_master',
            'title' => (string) ($r['title'] ?? ''),
            'kind' => $kind,
            'file_type' => (string) ($r['file_type'] ?? ''),
            'scope' => $scope,
            'concept_id' => isset($r['concept_id']) ? (int) $r['concept_id'] : null,
            'created_at' => $r['created_at'] ?? null,
            'status' => self::AVAILABLE,
            'review_label' => null,
        ];
    }

    /** Study-generated items before legacy ones, newest id first inside each group. @param list<array<string,mixed>> $items */
    private function newestFirst(array $items, string $preferredKind): array
    {
        usort($items, fn (array $a, array $b) => [$a['kind'] === $preferredKind ? 0 : 1, -$a['id']] <=> [$b['kind'] === $preferredKind ? 0 : 1, -$b['id']]);

        return $items;
    }

    private function documentScope(string $filename): string
    {
        return preg_match('/_all_[0-9a-f]{12}\.pdf$/', $filename) ? 'chapter' : 'concepts';
    }

    /** @param array<string,mixed> $r */
    private function visible(array $r): bool
    {
        return !isset($r['show_hide']) || (int) $r['show_hide'] !== 0;
    }

    /** @param array<string,mixed> $r */
    private function category(array $r): string
    {
        return trim((string) preg_replace('/[\s_\-]+/', ' ', strtolower((string) ($r['content_category'] ?? ''))));
    }
}
