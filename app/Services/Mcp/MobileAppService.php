<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile apps, as `mobile_homescreen` and `teacher_mobile_homescreen` configure them.
 *
 * WHAT THIS MODULE'S RECORDS ACTUALLY ARE
 *
 * Not app users and not devices — this estate stores neither. What it stores is the app's
 * own navigation, configured per user profile: a row is one tile on a home screen, with
 * the section it sits under (`main_title`), its label (`sub_title_of_main`), the API the
 * tile calls (`sub_title_api`), the screen it opens (`screen_name`), its sort order and
 * whether it is switched on. Two tables hold it, one for the parent/student app and one
 * for the teacher app, with identical columns.
 *
 * So the questions this module can answer are configuration questions: which tiles a
 * profile sees, what is switched off, which sections exist, whether the teacher app and
 * the parent app have drifted apart. It cannot answer "how many people use the app",
 * because nothing here records a session, a device or a login.
 *
 * THAT LIMIT IS REPORTED, NOT WORKED AROUND
 *
 * Every read returns `records` describing what the table holds, and the published prompts
 * for this module carry a rule forbidding any claim about usage, adoption or downloads.
 * A school told "68% of parents use the app" would be reading a number nobody measured.
 *
 * SCOPING
 *
 * `sub_institute_id` on both tables, matched against the caller's token. Neither table
 * carries an academic year — a home screen is configuration, not a year's records — so no
 * year filter is applied, and that is stated rather than left to be inferred.
 */
class MobileAppService
{
    /** The two tables, and the app each configures. */
    private const SURFACES = [
        'parent' => ['table' => 'mobile_homescreen', 'label' => 'Parent and student app'],
        'teacher' => ['table' => 'teacher_mobile_homescreen', 'label' => 'Teacher app'],
    ];

    /**
     * The configured tiles, newest section first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function homescreen(McpRequestContext $context, array $filters): array
    {
        $surface = $this->surface($filters['app'] ?? null);
        $table = self::SURFACES[$surface]['table'];

        if (! Schema::hasTable($table)) {
            return [
                'count' => 0,
                'tiles' => [],
                'note' => 'The '.self::SURFACES[$surface]['label'].' is not configured in this estate.',
            ];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context, $table);

        if (! empty($filters['user_profile_name'])) {
            $query->where('user_profile_name', 'like', '%'.$filters['user_profile_name'].'%');
        }

        if (! empty($filters['section'])) {
            $query->where('main_title', 'like', '%'.$filters['section'].'%');
        }

        // 'on' | 'off' — the real two states. Anything else means both, which is the
        // default and the honest answer to a question that did not ask.
        $status = strtolower(trim((string) ($filters['status'] ?? '')));

        if ($status === 'on') {
            $query->where('status', 1);
        } elseif ($status === 'off') {
            $query->where('status', '!=', 1);
        }

        // Counted before the limit, so a page is never read as the whole app.
        $total = (clone $query)->count();
        $enabled = (clone $query)->where('status', 1)->count();

        $rows = $query
            ->orderBy('main_sort_order')
            ->orderBy('sub_title_sort_order')
            ->limit($limit)
            ->get([
                'id', 'user_profile_id', 'user_profile_name', 'main_title', 'menu_type',
                'sub_title_of_main', 'sub_title_icon', 'sub_title_api', 'screen_name',
                'main_sort_order', 'sub_title_sort_order', 'status', 'updated_on',
            ]);

        return [
            'app' => $surface,
            'app_label' => self::SURFACES[$surface]['label'],
            'count' => $total,
            'row_count' => $rows->count(),
            'tiles_enabled' => $enabled,
            'tiles_disabled' => max($total - $enabled, 0),
            'tiles' => $rows->map(static fn ($row) => [
                'tile_id' => (int) $row->id,
                'user_profile_id' => $row->user_profile_id === null ? null : (int) $row->user_profile_id,
                'user_profile' => $row->user_profile_name,
                'section' => $row->main_title,
                'menu_type' => $row->menu_type,
                'label' => $row->sub_title_of_main,
                'icon' => $row->sub_title_icon,
                'opens_screen' => $row->screen_name,
                'calls_api' => $row->sub_title_api,
                'section_order' => $row->main_sort_order === null ? null : (int) $row->main_sort_order,
                'tile_order' => $row->sub_title_sort_order === null ? null : (int) $row->sub_title_sort_order,
                'enabled' => (int) $row->status === 1,
                'updated_on' => $row->updated_on,
            ])->all(),
            'rule' => 'These rows are the app\'s configured navigation, not its usage. Nothing in this '
                .'estate records a session, a device or a login, so no figure here describes how many '
                .'people use the app.',
        ];
    }

    /**
     * The sections each app is built from, with how many tiles sit under each.
     *
     * Reported per app rather than merged, because the parent app and the teacher app are
     * separately configured and a section present in one is routinely absent from the
     * other — which is exactly the drift worth seeing.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function sections(McpRequestContext $context, array $filters): array
    {
        $apps = [];

        foreach (self::SURFACES as $key => $surface) {
            if (! Schema::hasTable($surface['table'])) {
                continue;
            }

            $rows = $this->query($context, $surface['table'])
                ->selectRaw(
                    'main_title, user_profile_name,
                     COUNT(*) AS tiles,
                     SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS enabled,
                     MIN(main_sort_order) AS section_order'
                )
                ->groupBy('main_title', 'user_profile_name')
                ->orderBy('section_order')
                ->limit(min(max((int) ($filters['limit'] ?? 100), 1), 200))
                ->get();

            $apps[] = [
                'app' => $key,
                'app_label' => $surface['label'],
                'section_count' => $rows->count(),
                'sections' => $rows->map(static fn ($row) => [
                    'section' => $row->main_title,
                    'user_profile' => $row->user_profile_name,
                    'tiles' => (int) $row->tiles,
                    'tiles_enabled' => (int) $row->enabled,
                    'tiles_disabled' => (int) $row->tiles - (int) $row->enabled,
                    'section_order' => $row->section_order === null ? null : (int) $row->section_order,
                ])->all(),
            ];
        }

        return [
            'count' => count($apps),
            'apps' => $apps,
            'rule' => 'The two apps are configured separately. A section listed for one and not the other '
                .'is a real difference in what those users see, not a gap in this report.',
        ];
    }

    /** One app surface key, defaulting to the parent app. */
    private function surface(mixed $value): string
    {
        $given = strtolower(trim((string) ($value ?? '')));

        return isset(self::SURFACES[$given]) ? $given : 'parent';
    }

    /**
     * The tenant-scoped query for one table.
     *
     * No academic year filter: neither table carries `syear`, because a home screen is
     * configuration rather than a year's records. Adding one would silently return nothing.
     */
    private function query(McpRequestContext $context, string $table): Builder
    {
        return DB::table($table)->where('sub_institute_id', $context->selectedInstituteId);
    }
}
