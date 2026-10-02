<?php

namespace App\Services\Documents\Search;

use App\Models\Documents\DocumentMaster;
use App\Models\HrmsDepartment;
use Illuminate\Support\Facades\Http;
use Throwable;

class NaturalLanguageSearchParser
{
    /**
     * Parse natural language search into structured query filters and remainder keywords
     */
    public function parse(string $query, int $subInstituteId): array
    {
        $filters = [];
        $chips = [];
        $remainder = $query;

        // 1. Detect Academic Year pattern (e.g. 2026-27 or 2026)
        if (preg_match('/\b(20\d{2})[-–](20\d{2}|\d{2})\b/', $remainder, $m)) {
            $filters['academic_year'] = $m[0];
            $chips[] = ['field' => 'academic_year', 'label' => 'Year: ' . $m[0], 'value' => $m[0]];
            $remainder = str_replace($m[0], ' ', $remainder);
        } elseif (preg_match('/\b(20\d{2})\b/', $remainder, $m)) {
            $filters['year'] = $m[1];
            $chips[] = ['field' => 'year', 'label' => 'Year: ' . $m[1], 'value' => $m[1]];
            $remainder = str_replace($m[0], ' ', $remainder);
        }

        // 2. Detect Department from database
        $departments = HrmsDepartment::where('status', 1)
            ->where(function ($q) use ($subInstituteId) {
                $q->where('sub_institute_id', $subInstituteId)
                  ->orWhereNull('sub_institute_id');
            })
            ->pluck('department', 'id')
            ->toArray();

        foreach ($departments as $id => $deptName) {
            $cleanName = trim($deptName);
            if (mb_strlen($cleanName) > 2 && preg_match('/\b' . preg_quote($cleanName, '/') . '\b/i', $remainder)) {
                $filters['department_id'] = $id;
                $chips[] = ['field' => 'department', 'label' => 'Dept: ' . $cleanName, 'value' => $id];
                $remainder = preg_replace('/\b' . preg_quote($cleanName, '/') . '\b/i', ' ', $remainder);
                break;
            }
        }

        // 3. Detect Status words
        $statusWords = ['expired', 'archived', 'active', 'filed'];
        foreach ($statusWords as $st) {
            if (preg_match('/\b' . $st . '\b/i', $remainder)) {
                $filters['lifecycle_status'] = $st;
                $chips[] = ['field' => 'lifecycle_status', 'label' => 'Status: ' . ucfirst($st), 'value' => $st];
                $remainder = preg_replace('/\b' . $st . '\b/i', ' ', $remainder);
            }
        }

        // 4. Detect Document Types
        $types = config('idms.allowed_document_types', []);
        foreach ($types as $docType) {
            if (preg_match('/\b' . preg_quote($docType, '/') . '\b/i', $remainder)) {
                $filters['document_type'] = $docType;
                $chips[] = ['field' => 'document_type', 'label' => 'Type: ' . $docType, 'value' => $docType];
                $remainder = preg_replace('/\b' . preg_quote($docType, '/') . '\b/i', ' ', $remainder);
                break;
            }
        }

        // Clean up common query filler phrases (e.g. "Find all", "Show me", "Search for")
        $filler = ['/find all/i', '/find/i', '/show me/i', '/search for/i', '/get/i', '/list of/i', '/list/i', '/for/i'];
        $cleanKeywords = trim(preg_replace('/\s+/', ' ', preg_replace($filler, ' ', $remainder)));

        return [
            'raw_query' => $query,
            'keywords' => $cleanKeywords,
            'filters' => $filters,
            'chips' => $chips,
        ];
    }
}
