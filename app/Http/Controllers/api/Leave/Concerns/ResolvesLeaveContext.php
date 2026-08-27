<?php

namespace App\Http\Controllers\api\Leave\Concerns;

use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared request context resolution for the Leave Management API.
 *
 * Every endpoint under /api/leave is token authenticated with the same JWT
 * already used by this app's own login/session (see
 * App\Http\Middleware\ApiSessionHydrator for the established precedent of
 * validating this exact token type on a stateless API route) - NOT a Laravel
 * Sanctum personal access token, which is a different token format this
 * codebase's real tokens never match - and every query is scoped by
 * sub_institute_id + the leave year (see resolveLeaveYearBounds()).
 */
trait ResolvesLeaveContext
{
    use GetsJwtToken;

    /**
     * @return array{sub_institute_id:int, user_id:int|null, year:int, from:string, to:string}|\Illuminate\Http\JsonResponse
     */
    protected function leaveContext(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['status' => 0, 'message' => 'Token not provided'], 401);
        }

        try {
            if (!$this->jwtToken($request)->validate()) {
                return response()->json(['status' => 0, 'message' => 'Invalid token'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Invalid token'], 401);
        }

        // G-SEC-29: the tenant comes from the verified JWT payload, never the
        // caller-suppliable request body/header.
        $subInstituteId = $this->jwtPayload('sub_institute_id', $request);

        if (!$subInstituteId || !is_numeric($subInstituteId)) {
            return response()->json(['status' => 0, 'message' => 'sub_institute_id is required'], 400);
        }

        $subInstituteId = (int) $subInstituteId;
        $year = $this->normaliseLeaveYear($request->input('syear') ?? $request->input('year'));
        [$from, $to] = $this->resolveLeaveYearBounds($subInstituteId, $year);

        return [
            'sub_institute_id' => $subInstituteId,
            'user_id'          => is_numeric($request->input('user_id')) ? (int) $request->input('user_id') : null,
            'year'             => $year,
            'from'             => $from,
            'to'               => $to,
        ];
    }

    /**
     * See App\Services\Leave\LeaveAnalyticsService::leaveYearBounds() for the
     * full rationale - same logic, duplicated here since this trait resolves
     * `from`/`to` itself rather than going through that service. Prefers the
     * institute's real academic_year date range for this syear over a
     * hardcoded April-March calendar window.
     */
    protected function resolveLeaveYearBounds(int $subInstituteId, int $year): array
    {
        $bounds = DB::table('academic_year')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $year)
            ->selectRaw('MIN(start_date) as from_date, MAX(end_date) as to_date')
            ->first();

        if ($bounds && $bounds->from_date && $bounds->to_date) {
            return [$bounds->from_date, $bounds->to_date];
        }

        return [$year . '-04-01', ($year + 1) . '-03-31'];
    }

    /**
     * The frontend stores syear in several shapes ("2024", "2024-25", "2024-2025").
     * Everything downstream builds date strings from it, so collapse to the
     * 4 digit opening year of the April-March window.
     */
    protected function normaliseLeaveYear($value): int
    {
        if (is_numeric($value) && (int) $value > 1900 && (int) $value < 2200) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/(\d{4})/', $value, $matches)) {
            return (int) $matches[1];
        }

        // Before April the current leave year is still the previous calendar year.
        return (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    protected function activeFilter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = array_values(array_filter($value, fn ($item) => $item !== null && $item !== '' && $item !== '0' && $item !== 'all'));
            return empty($value) ? null : implode(',', $value);
        }

        $value = trim((string) $value);

        return ($value === '' || $value === '0' || strtolower($value) === 'all') ? null : $value;
    }

    /** Normalise a repeatable filter into a clean list. */
    protected function filterList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_array($value)) {
            $value = explode(',', (string) $value);
        }

        return array_values(array_filter(array_map('trim', $value), function ($item) {
            return $item !== '' && $item !== '0' && strtolower($item) !== 'all';
        }));
    }
}
