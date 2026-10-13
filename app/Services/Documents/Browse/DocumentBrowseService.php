<?php

namespace App\Services\Documents\Browse;

use App\Models\Documents\DocumentMaster;
use App\Models\HrmsDepartment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DocumentBrowseService
{
    /**
     * Build Virtual Directory Tree: Department > Document Type > Academic Year
     */
    public function getTree($user, int $subInstituteId): array
    {
        $userId = is_object($user) ? $user->id : (int)$user;
        $cacheKey = "idms_tree_{$subInstituteId}_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user, $subInstituteId) {
            $records = DocumentMaster::query()
                ->visibleTo($user, $subInstituteId)
                ->where('processing_status', 'done')
                ->select([
                    'department_id',
                    DB::raw("COALESCE(document_type, 'Unclassified') as doc_type"),
                    DB::raw("COALESCE(academic_year, 'General') as acad_year"),
                    DB::raw('COUNT(*) as total_count'),
                ])
                ->groupBy(['department_id', 'doc_type', 'acad_year'])
                ->get();

            $departments = HrmsDepartment::where('status', 1)
                ->pluck('department', 'id')
                ->toArray();

            $tree = [];

            foreach ($records as $row) {
                $deptId = $row->department_id ?: 0;
                $deptName = $departments[$deptId] ?? 'General / Common';

                if (!isset($tree[$deptId])) {
                    $tree[$deptId] = [
                        'id' => $deptId,
                        'name' => $deptName,
                        'count' => 0,
                        'types' => [],
                    ];
                }

                $tree[$deptId]['count'] += $row->total_count;

                $typeKey = $row->doc_type;
                if (!isset($tree[$deptId]['types'][$typeKey])) {
                    $tree[$deptId]['types'][$typeKey] = [
                        'name' => $typeKey,
                        'count' => 0,
                        'years' => [],
                    ];
                }

                $tree[$deptId]['types'][$typeKey]['count'] += $row->total_count;
                $tree[$deptId]['types'][$typeKey]['years'][] = [
                    'year' => $row->acad_year,
                    'count' => (int)$row->total_count,
                ];
            }

            // Normalize associative arrays to index lists for JSON API
            $result = [];
            foreach ($tree as $dept) {
                $dept['types'] = array_values($dept['types']);
                $result[] = $dept;
            }

            return $result;
        });
    }

    /**
     * Build Tag Cloud with counts under visibleTo scope
     */
    public function getTagCloud($user, int $subInstituteId): array
    {
        $userId = is_object($user) ? $user->id : (int)$user;
        $cacheKey = "idms_tags_{$subInstituteId}_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user, $subInstituteId) {
            $tagJsonLists = DocumentMaster::query()
                ->visibleTo($user, $subInstituteId)
                ->where('processing_status', 'done')
                ->whereNotNull('tag_names')
                ->pluck('tag_names');

            $counts = [];
            foreach ($tagJsonLists as $tagNames) {
                if (is_array($tagNames)) {
                    foreach ($tagNames as $tag) {
                        $normalized = trim($tag);
                        if ($normalized !== '') {
                            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
                        }
                    }
                }
            }

            arsort($counts);

            $tagList = [];
            foreach (array_slice($counts, 0, 50, true) as $name => $count) {
                $tagList[] = [
                    'name' => $name,
                    'count' => $count,
                ];
            }

            return $tagList;
        });
    }
}
