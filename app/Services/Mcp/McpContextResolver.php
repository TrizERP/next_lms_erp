<?php

namespace App\Services\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McpContextResolver
{
    public function resolve(Request $request, array $auth): McpRequestContext
    {
        $allowedInstituteIds = $this->parseInstituteIds($auth['sub_institute_id'] ?? '');
        $selectedInstituteId = $this->resolveInstituteId($request, $allowedInstituteIds, $auth);
        [$academicYear, $termId] = $this->resolveAcademicContext($request, $selectedInstituteId);

        return new McpRequestContext(
            userId: (int) $auth['user_id'],
            role: (string) $auth['role'],
            selectedInstituteId: $selectedInstituteId,
            allowedInstituteIds: $allowedInstituteIds,
            userProfileId: isset($auth['user_profile_id']) ? (int) $auth['user_profile_id'] : null,
            clientId: isset($auth['client_id']) ? (int) $auth['client_id'] : null,
            academicYear: $academicYear,
            termId: $termId,
            isAdmin: ((int) ($auth['is_admin'] ?? 0)) >= 1,
            isStudent: (bool) ($auth['is_student'] ?? false)
        );
    }

    /**
     * @return array<int, int>
     */
    private function parseInstituteIds(string $raw): array
    {
        return array_values(array_map(
            'intval',
            array_filter(array_map('trim', explode(',', $raw)), 'strlen')
        ));
    }

    /**
     * @param  array<int, int>  $allowedInstituteIds
     */
    private function resolveInstituteId(Request $request, array $allowedInstituteIds, array $auth): int
    {
        $requestedInstitute = (int) ($request->header('X-MCP-Institute-Id') ?: $request->input('meta.institute_id', 0));

        if ($requestedInstitute > 0) {
            if (! in_array($requestedInstitute, $allowedInstituteIds, true) && (int) ($auth['is_admin'] ?? 0) !== 2) {
                throw ValidationException::withMessages([
                    'meta.institute_id' => ['Requested institute is outside your allowed scope.'],
                ]);
            }

            return $requestedInstitute;
        }

        if (! empty($allowedInstituteIds)) {
            return $allowedInstituteIds[0];
        }

        throw ValidationException::withMessages([
            'institute' => ['No institute scope was resolved for the authenticated user.'],
        ]);
    }

    /**
     * @return array{0:?int,1:?int}
     */
    private function resolveAcademicContext(Request $request, int $selectedInstituteId): array
    {
        $requestedYear = $request->input('meta.academic_year');
        $requestedTerm = $request->input('meta.term_id');

        if ($requestedYear !== null && $requestedTerm !== null) {
            $exists = DB::table('academic_year')
                ->where('sub_institute_id', $selectedInstituteId)
                ->where('syear', $requestedYear)
                ->where('term_id', $requestedTerm)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'meta.term_id' => ['Requested academic year or term is invalid for the selected institute.'],
                ]);
            }

            return [(int) $requestedYear, (int) $requestedTerm];
        }

        $currentTerm = DB::table('academic_year')
            ->where('sub_institute_id', $selectedInstituteId)
            ->whereRaw('"' . now()->toDateString() . '" between start_date and end_date')
            ->orderBy('sort_order')
            ->first();

        if (! $currentTerm) {
            return [null, null];
        }

        return [(int) $currentTerm->syear, (int) $currentTerm->term_id];
    }
}
