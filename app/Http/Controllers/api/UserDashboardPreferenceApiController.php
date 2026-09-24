<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-user dashboard customisation: which widgets (KPI cards, charts,
 * panels) the signed-in user has hidden on a given dashboard.
 *
 * Runs behind `api.session` (App\Http\Middleware\ApiSessionHydrator). The
 * owner of every read and write is taken ONLY from session() -- never from
 * the request -- so a user can only ever see or change their own
 * preferences, and hiding a widget never affects any other user.
 *
 * Hiding is presentation only. It is not a permission: the dashboard APIs
 * still decide what data a user may see.
 */
class UserDashboardPreferenceApiController extends Controller
{
    private const TABLE = 'user_dashboard_preferences';

    // Dashboard keys and widget ids share one shape: "home.admin", "kpi.total_staff".
    public const KEY_PATTERN = '[a-z0-9][a-z0-9._-]{0,99}';

    private const MAX_HIDDEN_WIDGETS = 100;

    public function show(string $dashboardKey): JsonResponse
    {
        $row = DB::table(self::TABLE)->where($this->owner($dashboardKey))->first();

        return response()->json([
            'status' => '1',
            'dashboard_key' => $dashboardKey,
            'hidden_widgets' => $row ? $this->decodeWidgets($row->hidden_widgets) : [],
            'updated_at' => $row->updated_at ?? null,
        ]);
    }

    public function update(Request $request, string $dashboardKey): JsonResponse
    {
        $validated = $request->validate([
            'hidden_widgets' => ['present', 'array', 'max:' . self::MAX_HIDDEN_WIDGETS],
            'hidden_widgets.*' => ['string', 'regex:/^' . self::KEY_PATTERN . '$/'],
        ]);

        $hidden = array_values(array_unique($validated['hidden_widgets']));
        $now = now();

        // Keyed on the unique owner index, so a double-submit can never
        // produce two rows for the same user and dashboard.
        DB::table(self::TABLE)->upsert(
            [array_merge($this->owner($dashboardKey), [
                'hidden_widgets' => json_encode($hidden),
                'created_at' => $now,
                'updated_at' => $now,
            ])],
            ['sub_institute_id', 'user_id', 'user_type', 'dashboard_key'],
            ['hidden_widgets', 'updated_at']
        );

        return response()->json([
            'status' => '1',
            'message' => 'Dashboard preferences saved.',
            'dashboard_key' => $dashboardKey,
            'hidden_widgets' => $hidden,
            'updated_at' => $now->toDateTimeString(),
        ]);
    }

    /**
     * @return array{sub_institute_id:int,user_id:int,user_type:string,dashboard_key:string}
     */
    private function owner(string $dashboardKey): array
    {
        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id' => (int) session()->get('user_id'),
            // tbluser and tblstudent ids overlap; is_student tells them apart.
            'user_type' => session()->get('is_student') ? 'student' : 'staff',
            'dashboard_key' => $dashboardKey,
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeWidgets(?string $json): array
    {
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
