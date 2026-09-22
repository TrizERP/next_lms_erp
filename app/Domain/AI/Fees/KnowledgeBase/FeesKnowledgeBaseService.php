<?php

namespace App\Domain\AI\Fees\KnowledgeBase;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeesKnowledgeBaseService
{
    public function findPolicies(int $subInstituteId, array $categories = ['fees'], ?int $limit = 50): array
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            return [];
        }

        if (! Schema::hasColumn('knowledge_base_detail', 'category')) {
            return [];
        }

        return DB::table('knowledge_base_detail')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('category', $categories)
            ->where('status', 1)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'content' => (string) $row->content,
                'category' => (string) $row->category,
                'tags' => $row->tags ?? '',
            ])
            ->all();
    }

    public function findByTags(int $subInstituteId, array $tags, ?int $limit = 20): array
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            return [];
        }

        if (! Schema::hasColumn('knowledge_base_detail', 'tags')) {
            return [];
        }

        return DB::table('knowledge_base_detail')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->where(function ($query) use ($tags) {
                foreach ($tags as $tag) {
                    $query->orWhere('tags', 'like', "%{$tag}%");
                }
            })
            ->limit($limit)
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'content' => (string) $row->content,
                'category' => (string) $row->category,
                'tags' => $row->tags ?? '',
            ])
            ->all();
    }

    public function policyFor(string $topic, int $subInstituteId): ?array
    {
        $policies = $this->findPolicies($subInstituteId, ['fees']);

        foreach ($policies as $policy) {
            if (stripos($policy['title'] . ' ' . $policy['content'], $topic) !== false) {
                return $policy;
            }
        }

        return null;
    }

    public function listCategories(int $subInstituteId): array
    {
        if (! Schema::hasTable('knowledge_base_detail')) {
            return [];
        }

        if (! Schema::hasColumn('knowledge_base_detail', 'category')) {
            return [];
        }

        return DB::table('knowledge_base_detail')
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->distinct()
            ->pluck('category')
            ->all();
    }

    public function summaryForStudent(int $studentId, int $subInstituteId, ?int $syear): string
    {
        $policies = $this->findPolicies($subInstituteId, ['fees']);

        if ($policies === []) {
            return 'No fee policies are configured for this institute.';
        }

        $lines = array_map(static fn ($p) => "{$p['title']}: {$p['content']}", $policies);

        return implode("\n\n", $lines);
    }
}