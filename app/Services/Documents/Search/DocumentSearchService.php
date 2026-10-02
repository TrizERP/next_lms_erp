<?php

namespace App\Services\Documents\Search;

use App\Models\Documents\DocumentMaster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DocumentSearchService
{
    /**
     * Search documents with Hybrid ranking: filters + FULLTEXT + highlights.
     * Note: Never selects extracted_text or embedding directly into list.
     */
    public function search(array $params, $user, int $subInstituteId): LengthAwarePaginator
    {
        $perPage = min(max((int)($params['per_page'] ?? 20), 1), 100);
        $page = (int)($params['page'] ?? 1);

        $query = DocumentMaster::query()
            ->visibleTo($user, $subInstituteId)
            ->where('processing_status', 'done')
            ->select([
                'id',
                'sub_institute_id',
                'title',
                'original_file_name',
                'mime_type',
                'size',
                'storage_path',
                'preview_path',
                'current_version',
                'document_type',
                'category',
                'department_id',
                'subject',
                'document_date',
                'academic_year',
                'organization',
                'project',
                'lifecycle_status',
                'summary',
                'confidence',
                'tags',
                'tag_names',
                'owner_id',
                'visibility',
                'created_by',
                'created_at',
                'updated_at',
                /*
                 * The columns below are read by DocumentResource and rendered by the
                 * library and the review screen. They were missing from this list, and a
                 * column that is not selected comes back as NULL rather than as absent:
                 * processing_status therefore arrived as null and the result row crashed
                 * the list. Adding a field to DocumentResource without adding it here is
                 * the trap — this select is the contract, not the model.
                 *
                 * extracted_text and embedding stay out on purpose: they are fetched only
                 * for the current page of snippets, below.
                 */
                'processing_status',
                'processing_error',
                'warnings',
                'people',
                'keywords',
                'permissions',
            ]);

        // Exact column filters
        if (!empty($params['department_id'])) {
            $query->where('department_id', (int)$params['department_id']);
        }
        if (!empty($params['document_type'])) {
            $query->where('document_type', $params['document_type']);
        }
        if (!empty($params['academic_year'])) {
            $query->where('academic_year', 'LIKE', '%' . $params['academic_year'] . '%');
        }
        if (!empty($params['lifecycle_status'])) {
            $query->where('lifecycle_status', $params['lifecycle_status']);
        }
        if (!empty($params['tag'])) {
            $tagLower = mb_strtolower(trim($params['tag']));
            $query->whereRaw('JSON_CONTAINS(tag_names, ?)', [json_encode($tagLower)]);
        }

        // FULLTEXT Search
        $searchTerm = trim($params['q'] ?? $params['search'] ?? '');
        if ($searchTerm !== '') {
            $cleanTerm = addslashes($searchTerm);
            $query->where(function ($sub) use ($cleanTerm, $searchTerm) {
                // Match on metadata fields
                $sub->whereRaw("MATCH(title, original_file_name, tags_text, subject, organization) AGAINST(? IN BOOLEAN MODE)", [$cleanTerm . '*'])
                    ->orWhereRaw("MATCH(extracted_text) AGAINST(? IN BOOLEAN MODE)", [$cleanTerm . '*'])
                    ->orWhere('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('original_file_name', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Sorting
        $sort = $params['sort'] ?? 'newest';
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'title') {
            $query->orderBy('title', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Fetch snippets in a targeted second query for the paginated page only
        if ($searchTerm !== '' && $paginator->isNotEmpty()) {
            $ids = $paginator->pluck('id')->toArray();
            $texts = DB::table('document_master')
                ->whereIn('id', $ids)
                ->pluck('extracted_text', 'id')
                ->toArray();

            foreach ($paginator->items() as $item) {
                $full = $texts[$item->id] ?? '';
                $item->snippet = $this->buildSnippet($full, $searchTerm);
            }
        }

        return $paginator;
    }

    /**
     * Build highlighted snippet around search terms
     */
    protected function buildSnippet(string $text, string $term, int $radius = 120): string
    {
        if (empty($text) || empty($term)) {
            return mb_substr($text, 0, 160) . '...';
        }

        $pos = mb_stripos($text, $term);
        if ($pos === false) {
            return mb_substr($text, 0, 160) . '...';
        }

        $start = max(0, $pos - $radius);
        $length = mb_strlen($term) + ($radius * 2);
        $snippet = mb_substr($text, $start, $length);

        if ($start > 0) $snippet = '...' . $snippet;
        if ($start + $length < mb_strlen($text)) $snippet .= '...';

        // Add highlight tag
        return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark class="bg-amber-200 font-semibold px-0.5 rounded">$1</mark>', htmlspecialchars($snippet));
    }
}
