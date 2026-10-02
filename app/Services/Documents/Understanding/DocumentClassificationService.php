<?php

namespace App\Services\Documents\Understanding;

use App\Models\HrmsDepartment;

class DocumentClassificationService
{
    protected AiClassifierInterface $aiClassifier;
    protected RuleBasedClassifier $ruleClassifier;

    public function __construct()
    {
        $this->aiClassifier = new GeminiClassifier();
        $this->ruleClassifier = new RuleBasedClassifier();
    }

    /**
     * Process classification with AI + Rule-based fallback
     */
    public function classify(string $fullText, string $originalFileName, int $subInstituteId): array
    {
        // 1. Fetch available departments for this institute
        $departments = HrmsDepartment::where('status', 1)
            ->where(function ($q) use ($subInstituteId) {
                $q->where('sub_institute_id', $subInstituteId)
                  ->orWhereNull('sub_institute_id');
            })
            ->pluck('department', 'id')
            ->toArray();

        $deptNames = array_values(array_filter(array_unique($departments)));
        $allowedTypes = config('idms.allowed_document_types', []);

        // 2. Prepare excerpt (~first 6000 and last ~1000 characters)
        $len = mb_strlen($fullText);
        if ($len > 7000) {
            $excerpt = mb_substr($fullText, 0, 6000) . "\n\n[... content truncated ...]\n\n" . mb_substr($fullText, -1000);
        } else {
            $excerpt = $fullText;
        }

        $warnings = [];
        $aiResult = null;

        if (!empty(trim($excerpt))) {
            $aiResult = $this->aiClassifier->classify($excerpt, $deptNames, $allowedTypes);
        }

        $minConfidence = config('idms.confidence_threshold', 0.65);

        if ($aiResult && ($aiResult['confidence'] ?? 0) >= $minConfidence) {
            $result = $aiResult;
        } else {
            // Rule-based fallback
            $result = $this->ruleClassifier->classify($fullText, $originalFileName, $deptNames, $allowedTypes);
            $warnings[] = 'low_confidence';
            if ($aiResult) {
                // Merge AI suggested tags/summary if available
                if (!empty($aiResult['suggested_tags'])) {
                    $result['suggested_tags'] = array_values(array_unique(array_merge($result['suggested_tags'], $aiResult['suggested_tags'])));
                }
                if (!empty($aiResult['summary'])) {
                    $result['summary'] = $aiResult['summary'];
                }
            }
        }

        // Map department name back to department_id
        $deptId = null;
        $targetDeptName = trim($result['department'] ?? '');
        if ($targetDeptName !== '') {
            foreach ($departments as $id => $name) {
                if (strcasecmp($name, $targetDeptName) === 0 || str_contains(mb_strtolower($targetDeptName), mb_strtolower($name))) {
                    $deptId = $id;
                    break;
                }
            }
        }

        /*
         * An unmatched department name is a silent data loss: the document is real, the
         * model picked a plausible name, but nothing maps it to a hrms_departments row, so
         * department_id stays NULL and the file drops into "General / Common" in the browse
         * tree with nothing on the review screen to explain why. Gemini is told the valid
         * names but does not always pick from them, and the same document can classify
         * differently on two runs. Recording the mismatch turns an invisible wrong filing
         * into a one-click correction on the review screen.
         */
        if ($targetDeptName !== '' && $deptId === null) {
            $warnings[] = 'unmatched_department';
            $warnings[] = 'department_not_recognised:' . $targetDeptName;
        }

        return [
            'metadata' => $result,
            'department_id' => $deptId,
            'warnings' => $warnings,
        ];
    }
}
