<?php

namespace App\Services\Documents\Understanding;

class RuleBasedClassifier
{
    /**
     * Rule-based fallback classifier using regex and heuristics
     */
    public function classify(string $text, string $originalFileName, array $availableDepartments, array $allowedTypes): array
    {
        $haystack = mb_strtolower($text . ' ' . $originalFileName);

        // 1. Detect Document Type
        $detectedType = 'General Correspondence';
        $typeKeywords = [
            'Contract' => ['contract', 'agreement', 'service level agreement', 'amc', 'memorandum'],
            'Invoice' => ['invoice', 'bill', 'receipt', 'tax invoice', 'gstin'],
            'Policy' => ['policy', 'guidelines', 'standard operating procedure', 'sop', 'code of conduct'],
            'Circular' => ['circular', 'notification', 'office order', 'advisory'],
            'Minutes of Meeting' => ['minutes of meeting', 'mom', 'meeting agenda', 'proceedings'],
            'Report' => ['annual report', 'audit report', 'evaluation report', 'progress report', 'summary report'],
            'Academic Syllabus' => ['syllabus', 'curriculum', 'course outline', 'lesson plan'],
            'Question Paper' => ['question paper', 'examination', 'test paper', 'midterm', 'final exam', 'marking scheme'],
            'Certificate' => ['certificate of', 'bonafide', 'completion certificate', 'transfer certificate', 'leaving certificate'],
        ];

        foreach ($typeKeywords as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $detectedType = $type;
                    break 2;
                }
            }
        }

        // 2. Academic Year pattern (e.g. 2026-27 or 2025-2026)
        $academicYear = null;
        if (preg_match('/\b(20\d{2})[-–](20\d{2}|\d{2})\b/', $text . ' ' . $originalFileName, $m)) {
            $academicYear = $m[0];
        }

        // 3. Date pattern
        $documentDate = null;
        if (preg_match('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](20\d{2})\b/', $text, $dm)) {
            $documentDate = sprintf('%04d-%02d-%02d', $dm[3], $dm[2], $dm[1]);
        } elseif (preg_match('/\b(20\d{2})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})\b/', $text, $dm)) {
            $documentDate = sprintf('%04d-%02d-%02d', $dm[1], $dm[2], $dm[3]);
        }

        // 4. Department matching
        $matchedDept = '';
        foreach ($availableDepartments as $dept) {
            if ($dept !== '' && str_contains($haystack, mb_strtolower($dept))) {
                $matchedDept = $dept;
                break;
            }
        }
        // Common domain fallback
        if (empty($matchedDept)) {
            if (str_contains($haystack, 'computer') || str_contains($haystack, 'it ') || str_contains($haystack, 'software') || str_contains($haystack, 'network')) {
                $matchedDept = 'IT';
            } elseif (str_contains($haystack, 'teacher') || str_contains($haystack, 'student') || str_contains($haystack, 'academic')) {
                $matchedDept = 'Academics';
            } elseif (str_contains($haystack, 'salary') || str_contains($haystack, 'leave') || str_contains($haystack, 'employee')) {
                $matchedDept = 'HR';
            } elseif (str_contains($haystack, 'fee') || str_contains($haystack, 'account') || str_contains($haystack, 'payment')) {
                $matchedDept = 'Accounts';
            }
        }

        // 5. Generate tags
        $tags = [$detectedType];
        if ($academicYear) $tags[] = $academicYear;
        if ($matchedDept) $tags[] = $matchedDept;
        if (str_contains($haystack, 'maintenance')) $tags[] = 'Maintenance';
        if (str_contains($haystack, 'lab')) $tags[] = 'Computer Lab';
        if (str_contains($haystack, 'amc')) $tags[] = 'AMC';

        return [
            'document_type' => $detectedType,
            'category' => 'Administrative',
            'department' => $matchedDept,
            'subject' => pathinfo($originalFileName, PATHINFO_FILENAME),
            'document_date' => $documentDate,
            'academic_year' => $academicYear,
            'people' => [],
            'organization' => '',
            'project' => null,
            'keywords' => array_values(array_unique($tags)),
            'lifecycle_status' => 'active',
            'suggested_tags' => array_values(array_unique($tags)),
            'confidence' => 0.45,
            'summary' => 'Auto-classified using rule-based keyword extraction.',
        ];
    }
}
