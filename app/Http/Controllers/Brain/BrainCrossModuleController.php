<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\AttendanceIntelligence;
use App\Brain\Intelligence\CrossModuleInsights;
use App\Brain\Intelligence\ResultIntelligence;
use App\Brain\Support\AcademicYear;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-module insights for the Enterprise Brain overview.
 *
 * Reuses the module services as they are - Attendance's rate and Result's percentage keep their one
 * definition each - and relates them in CrossModuleInsights. The tenant comes from the verified
 * session (`brain.tenant` middleware), never from the request body, and both services scope every
 * query by that tenant and year.
 */
class BrainCrossModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (string) $request->attributes->get('tenantId', $request->attributes->get('auth.tenantId'));
        $syear = AcademicYear::resolve($tenantId, $request->query('syear'));

        $attendance = new AttendanceIntelligence($tenantId, $syear);
        $results = new ResultIntelligence($tenantId, $syear);

        $attendanceCoverage = $attendance->coverage();
        $resultsCoverage = $results->coverage();

        $attendanceAvailable = (bool) ($attendanceCoverage['available'] ?? false);
        $resultsAvailable = (bool) ($resultsCoverage['available'] ?? false);

        $insights = [
            CrossModuleInsights::attendanceVsResults(
                $attendanceAvailable ? $attendance->byStandard() : [],
                $resultsAvailable ? $results->byClass() : [],
                $attendanceAvailable ? ($attendance->position()['attendanceRate'] ?? null) : null,
                $resultsAvailable ? ($results->position()['meanPercentage'] ?? null) : null,
                $syear
            ),
        ];

        return response()->json([
            'tenantId' => $tenantId,
            'academicYear' => ['syear' => $syear],
            'generatedAt' => now()->toIso8601String(),
            'insights' => $insights,
            'modules' => [
                ['module' => 'attendance', 'label' => 'Attendance', 'available' => $attendanceAvailable,
                    'reason' => $attendanceAvailable ? null : ($attendanceCoverage['reason'] ?? 'No attendance records for this year.')],
                ['module' => 'result', 'label' => 'Results', 'available' => $resultsAvailable,
                    'reason' => $resultsAvailable ? null : ($resultsCoverage['reason'] ?? 'No marks for this year.')],
            ],
            // What is NOT implemented, said plainly, so absence is never mistaken for "nothing found".
            'notImplemented' => [
                'Fees against attendance or results: the fee tables are not keyed to the same class labels, so no verified link exists yet.',
                'Teacher competency against learning needs: not computed here; see Talent and Teach/Learn Intelligence.',
            ],
        ]);
    }
}
