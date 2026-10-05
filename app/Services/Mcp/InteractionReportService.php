<?php

namespace App\Services\Mcp;

use App\Models\InteractionLog;
use Illuminate\Support\Carbon;

/**
 * Reads for the Interactions AI Stack — backed by `interaction_logs`, which starts
 * genuinely empty until a staff member logs a call, meeting or note. No seed rows.
 */
class InteractionReportService
{
    public function list(McpRequestContext $context, array $args): array
    {
        $tenant = $context->selectedInstituteId;
        $limit = (int) ($args['limit'] ?? 50);

        $rows = InteractionLog::query()
            ->where('sub_institute_id', $tenant)
            ->when(! empty($args['related_type']), fn ($q) => $q->where('related_type', $args['related_type']))
            ->when(! empty($args['related_id']), fn ($q) => $q->where('related_id', $args['related_id']))
            ->when(! empty($args['status']), fn ($q) => $q->where('status', $args['status']))
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get([
                'id', 'staff_id', 'related_type', 'related_id', 'interaction_type',
                'subject', 'notes', 'occurred_at', 'follow_up_date', 'status',
            ]);

        return [
            'sub_institute_id' => $tenant,
            'interactions' => $rows,
            'count' => $rows->count(),
        ];
    }

    public function summary(McpRequestContext $context, array $args): array
    {
        $tenant = $context->selectedInstituteId;
        $daysBack = (int) ($args['days_back'] ?? 30);
        $since = Carbon::now()->subDays($daysBack);

        $base = InteractionLog::query()->where('sub_institute_id', $tenant)->where('occurred_at', '>=', $since);

        $byType = (clone $base)->selectRaw('interaction_type, COUNT(*) as total')
            ->groupBy('interaction_type')->pluck('total', 'interaction_type');

        $openFollowUps = InteractionLog::query()
            ->where('sub_institute_id', $tenant)
            ->where('status', 'open')
            ->whereNotNull('follow_up_date')
            ->orderBy('follow_up_date')
            ->limit(50)
            ->get(['id', 'related_type', 'related_id', 'subject', 'follow_up_date']);

        return [
            'sub_institute_id' => $tenant,
            'window_days' => $daysBack,
            'total' => (clone $base)->count(),
            'by_type' => $byType,
            'open_follow_ups' => $openFollowUps,
            'open_follow_up_count' => $openFollowUps->count(),
        ];
    }
}
