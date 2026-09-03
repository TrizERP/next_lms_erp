<?php

namespace App\CareerIntelligence;

use App\CareerIntelligence\Evidence\EvidenceEvent;
use Illuminate\Support\Facades\DB;

/**
 * Knowledge-Based Career Recommendation Engine — Steps 1-4.
 *
 * Builds a student's demonstrated-knowledge profile straight from evidence
 * (never fabricated — an item only appears here when a real correctly-
 * answered question's `subconcept` text EXACTLY matches (case-insensitive,
 * trimmed) a knowledge label already authored into that chapter's
 * semantic_intelligence record for the student's own sub_institute), loads
 * an occupation's real O*NET knowledge requirements from the locally cached
 * onet_* tables (no external call, no hardcoded occupation), and compares
 * the two.
 *
 * Deliberately NOT touching lms_concept / lms_online_exam_answer evidence
 * used by the PAL pedagogy engine, and NOT touching AssessmentEvidenceAdapter
 * / EvidenceEvent writes — this class only READS evidence_events (existing
 * pipeline, unmodified) plus the raw answer/question/semantic_intelligence
 * rows those evidence_events point at.
 *
 * Vocabulary note (see class-level design discussion): a chapter-level
 * knowledge statement like "Sign conventions" essentially never shares
 * literal text with a broad O*NET domain like "Mathematics". Rather than a
 * hardcoded crosswalk table (forbidden by CI-GUIDE-DEV-002's rules), matching
 * uses generic token-overlap (Jaccard) against BOTH the knowledge item's own
 * text/concept_name AND the real subject_name it was taught under (from
 * lms_question_master.subject_id -> subject.subject_name — a real column,
 * not an invented mapping). This means matches concentrate where the
 * subject_name and the O*NET domain name genuinely share words (e.g.
 * "Mathematics"/"Mathematics", "English"/"English Language") and correctly
 * stay near-zero elsewhere — that is the honest signal, not a defect.
 */
class KnowledgeMatchService
{
    /**
     * @return array<int, array{
     *   knowledge: string, statement: string, subject_name: string,
     *   concept_name: string|null, confidence: float, source_count: int,
     *   semantic_intelligence_id: int, chapter_id: int,
     * }>
     */
    public function buildStudentKnowledgeProfile(string $studentId): array
    {
        $activeEvidence = EvidenceEvent::where('student_id', $studentId)
            ->where('contested', false)
            ->get();

        if ($activeEvidence->isEmpty()) {
            return [];
        }

        // Evidence Events -> Assessment: assessment_id/source_id both carry
        // the question_paper_id AssessmentEvidenceAdapter rolled up from —
        // only these already-evidenced assessments are in scope, never the
        // student's full raw answer history independent of active evidence.
        $questionPaperIds = $activeEvidence
            ->pluck('assessment_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($questionPaperIds)) {
            return [];
        }

        $subInstituteId = $this->resolveSubInstituteId($studentId);
        if ($subInstituteId === null) {
            return []; // cannot scope semantic_intelligence to a tenant — never guess
        }

        // Assessment -> Question: only questions the student actually
        // answered correctly, only within the assessments evidence_events
        // already vouches for.
        $answers = DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
            ->join('subject as sub', 'sub.id', '=', 'q.subject_id')
            ->where('a.student_id', $studentId)
            ->where('a.ans_status', 'right')
            ->whereIn('a.question_paper_id', $questionPaperIds)
            ->select(
                'a.question_id',
                'q.chapter_id',
                'q.concept_id',
                'q.concept',
                'q.subconcept',
                'sub.subject_name'
            )
            ->get()
            ->unique('question_id'); // Rule: remove duplicates (repeat attempts of the same question)

        if ($answers->isEmpty()) {
            return [];
        }

        $answersByChapter = $answers->groupBy('chapter_id');

        // Question -> Chapter -> semantic_intelligence: one lookup per
        // distinct chapter, scoped to this student's own tenant.
        $chapterIds = $answersByChapter->keys()->all();
        $semanticRows = DB::table('semantic_intelligence')
            ->whereIn('chapter_id', $chapterIds)
            ->where('sub_institute_id', $subInstituteId)
            ->select('id', 'chapter_id', 'knowledge')
            ->get()
            ->keyBy('chapter_id');

        // concept_id -> concept name, for the answers that carry one.
        $conceptIds = $answers->pluck('concept_id')->filter()->unique()->values()->all();
        $conceptNames = empty($conceptIds)
            ? collect()
            : DB::table('lms_concept')->whereIn('id', $conceptIds)->pluck('name', 'id');

        // knowledge -> Knowledge Concepts: exact-match aggregation, keyed so
        // duplicate knowledge items (e.g. answered via more than one
        // question) collapse into a single profile entry per Rule
        // "Remove duplicates" / "Store confidence and evidence count".
        $profile = [];

        foreach ($answersByChapter as $chapterId => $chapterAnswers) {
            $semantic = $semanticRows->get($chapterId);
            if ($semantic === null) {
                // No semantic_intelligence record for this chapter under this
                // tenant — never fabricate a knowledge item for it.
                continue;
            }

            $knowledgeItems = json_decode($semantic->knowledge ?? '[]', true);
            if (! is_array($knowledgeItems)) {
                continue;
            }

            foreach ($chapterAnswers as $answer) {
                $conceptName = $answer->concept_id
                    ? ($conceptNames[$answer->concept_id] ?? null)
                    : ($answer->concept ?: null);

                $subconcept = trim((string) $answer->subconcept);
                if ($subconcept === '') {
                    continue; // nothing to exact-match against — skip, don't guess
                }

                foreach ($knowledgeItems as $item) {
                    $itemKnowledge = trim((string) ($item['knowledge'] ?? ''));
                    if ($itemKnowledge === '' || strcasecmp($itemKnowledge, $subconcept) !== 0) {
                        continue;
                    }

                    // If the question resolved a concept name, require it to
                    // match the knowledge item's own concept_name too (when
                    // the item has one) — an extra guard against a subconcept
                    // string coincidentally matching a knowledge label under
                    // an unrelated concept.
                    $itemConceptName = trim((string) ($item['concept_name'] ?? ''));
                    if ($conceptName && $itemConceptName !== '' && strcasecmp($itemConceptName, $conceptName) !== 0) {
                        continue;
                    }

                    $key = $semantic->id.'|'.$itemKnowledge;
                    if (! isset($profile[$key])) {
                        $profile[$key] = [
                            'knowledge' => $itemKnowledge,
                            'statement' => (string) ($item['statement'] ?? ''),
                            'subject_name' => $answer->subject_name,
                            'concept_name' => $itemConceptName !== '' ? $itemConceptName : $conceptName,
                            'confidence' => isset($item['confidence']) ? (float) $item['confidence'] : 0.0,
                            'source_count' => 0,
                            'semantic_intelligence_id' => (int) $semantic->id,
                            'chapter_id' => (int) $chapterId,
                            '_question_ids' => [],
                        ];
                    }

                    if (! in_array($answer->question_id, $profile[$key]['_question_ids'], true)) {
                        $profile[$key]['_question_ids'][] = $answer->question_id;
                        $profile[$key]['source_count']++;
                    }
                }
            }
        }

        return array_values(array_map(function (array $entry) {
            unset($entry['_question_ids']);

            return $entry;
        }, $profile));
    }

    /**
     * @return array<int, array{knowledge: string, importance: float, level: float}>
     */
    public function getOccupationKnowledge(string $onetsocCode): array
    {
        $rows = DB::table('onet_knowledge as k')
            ->join('onet_content_model_reference as r', 'r.element_id', '=', 'k.element_id')
            ->where('k.onetsoc_code', $onetsocCode)
            ->select('r.element_name', 'k.scale_id', 'k.data_value')
            ->get();

        $byElement = [];
        foreach ($rows as $row) {
            $byElement[$row->element_name]['importance'] ??= 0.0;
            $byElement[$row->element_name]['level'] ??= 0.0;
            if ($row->scale_id === 'IM') {
                $byElement[$row->element_name]['importance'] = (float) $row->data_value;
            } elseif ($row->scale_id === 'LV') {
                $byElement[$row->element_name]['level'] = (float) $row->data_value;
            }
        }

        $result = [];
        foreach ($byElement as $elementName => $scores) {
            $result[] = [
                'knowledge' => $elementName,
                'importance' => $scores['importance'],
                'level' => $scores['level'],
            ];
        }

        return $result;
    }

    /**
     * Step 3. Purely mechanical token-overlap matching — no per-name rules.
     *
     * @param array $studentKnowledge   buildStudentKnowledgeProfile() output
     * @param array $occupationKnowledge getOccupationKnowledge() output
     * @return array{
     *   matchedKnowledge: array, missingKnowledge: array, extraKnowledge: array,
     *   matchPercentage: float,
     * }
     */
    public function matchKnowledge(array $studentKnowledge, array $occupationKnowledge): array
    {
        $minScore = (float) config('career_recommendation.min_match_score');
        $minTokenLength = (int) config('career_recommendation.min_token_length');

        $studentTokenSets = array_map(function (array $item) use ($minTokenLength) {
            $item['_tokens'] = $this->tokenize(
                trim(($item['subject_name'] ?? '').' '.($item['concept_name'] ?? '').' '.($item['knowledge'] ?? '')),
                $minTokenLength
            );

            return $item;
        }, $studentKnowledge);

        $matched = [];
        $missing = [];
        $matchedOccupationImportance = 0.0;
        $totalOccupationImportance = 0.0;
        $usedStudentKnowledgeKeys = [];

        foreach ($occupationKnowledge as $occItem) {
            $totalOccupationImportance += max($occItem['importance'], 0.0);

            $occTokens = $this->tokenize($occItem['knowledge'], $minTokenLength);

            $best = null;
            $bestScore = 0.0;
            foreach ($studentTokenSets as $studentItem) {
                $score = $this->jaccard($occTokens, $studentItem['_tokens']);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $studentItem;
                }
            }

            if ($best !== null && $bestScore >= $minScore) {
                $matchedOccupationImportance += max($occItem['importance'], 0.0);
                $usedStudentKnowledgeKeys[$best['knowledge']] = true;
                $matched[] = [
                    'occupation_knowledge' => $occItem['knowledge'],
                    'importance' => $occItem['importance'],
                    'level' => $occItem['level'],
                    'matched_student_knowledge' => $best['knowledge'],
                    'confidence' => $best['confidence'],
                    'source_count' => $best['source_count'],
                    'overlap_score' => round($bestScore, 4),
                ];
            } else {
                $missing[] = [
                    'knowledge' => $occItem['knowledge'],
                    'importance' => $occItem['importance'],
                    'level' => $occItem['level'],
                ];
            }
        }

        $extra = [];
        foreach ($studentKnowledge as $item) {
            if (! isset($usedStudentKnowledgeKeys[$item['knowledge']])) {
                $extra[] = $item;
            }
        }

        $matchPercentage = $totalOccupationImportance > 0
            ? round(($matchedOccupationImportance / $totalOccupationImportance) * 100, 2)
            : 0.0;

        return [
            'matchedKnowledge' => $matched,
            'missingKnowledge' => $missing,
            'extraKnowledge' => $extra,
            'matchPercentage' => $matchPercentage,
        ];
    }

    /**
     * Step 4. Threshold comes entirely from config/career_recommendation.php.
     *
     * @param array|null $studentKnowledge pass a precomputed profile to avoid
     *                                     rebuilding it when the caller
     *                                     already has one (e.g. the
     *                                     recommender flow); null builds it.
     */
    public function evaluateAlignment(string $studentId, string $onetsocCode, ?array $studentKnowledge = null): array
    {
        $studentKnowledge ??= $this->buildStudentKnowledgeProfile($studentId);
        $occupationKnowledge = $this->getOccupationKnowledge($onetsocCode);
        $match = $this->matchKnowledge($studentKnowledge, $occupationKnowledge);

        $threshold = (float) config('career_recommendation.alignment_threshold');
        $alignment = ($match['matchPercentage'] / 100) >= $threshold ? 'ALIGNED' : 'MISALIGNED';

        return array_merge($match, [
            'alignment' => $alignment,
            'threshold' => $threshold,
            'occupation_code' => $onetsocCode,
            'studentKnowledge' => $studentKnowledge,
            'occupationKnowledge' => $occupationKnowledge,
        ]);
    }

    /** @return array<string, bool> a token set (keys only, for O(1) membership) */
    public function tokenize(string $text, int $minTokenLength): array
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        static $stopwords = [
            'and', 'or', 'of', 'the', 'a', 'an', 'in', 'on', 'to', 'for',
            'with', 'is', 'are', 'as', 'by', 'at', 'this', 'that', 'its',
        ];

        $tokens = [];
        foreach ($words as $word) {
            if (strlen($word) < $minTokenLength || in_array($word, $stopwords, true)) {
                continue;
            }
            $tokens[$word] = true;
        }

        return $tokens;
    }

    /** Generic Jaccard similarity between two token sets — no domain logic. */
    public function jaccard(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $keysA = array_keys($tokensA);
        $keysB = array_keys($tokensB);
        $intersection = count(array_intersect($keysA, $keysB));
        $union = count(array_unique(array_merge($keysA, $keysB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /** Most recent enrolment row's sub_institute_id — same source table resolveStudentGrade() uses. */
    private function resolveSubInstituteId(string $studentId): ?int
    {
        $value = DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->orderByDesc('id')
            ->value('sub_institute_id');

        return $value !== null ? (int) $value : null;
    }
}
