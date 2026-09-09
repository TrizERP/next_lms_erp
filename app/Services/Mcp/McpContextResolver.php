<?php

namespace App\Services\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McpContextResolver
{
    public function resolve(Request $request, array $auth): McpRequestContext
    {
        [$selectedInstituteId, $allowedInstituteIds] = $this->resolveInstituteScope($request, $auth);
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
     * The institute this request acts on, and the full set it may read.
     *
     * Returned together because they have to agree. Every scope filter downstream pins a
     * query to the selection and then intersects it with the allowed set — StudentScope,
     * EntityResolver and GraphQueryService all do exactly that — so a selection missing
     * from the set makes the two clauses contradict and the query returns nothing, while
     * the MCP tool services, which read the selection alone, return the institute's data
     * quite happily. One request, two answers, and no error to say which is right.
     *
     * The only way the two can diverge is the super-admin bypass in resolveInstituteId(),
     * and a super admin permitted to *select* an institute is by definition permitted to
     * *read* it. Folding the selection in therefore grants no authority the bypass has
     * not already granted; it only stops the request contradicting itself.
     *
     * @param  array<string, mixed>  $auth
     * @return array{0:int, 1:array<int, int>}
     */
    private function resolveInstituteScope(Request $request, array $auth): array
    {
        $allowedInstituteIds = $this->parseInstituteIds($auth['sub_institute_id'] ?? '');
        $selectedInstituteId = $this->resolveInstituteId($request, $allowedInstituteIds, $auth);

        if (! in_array($selectedInstituteId, $allowedInstituteIds, true)) {
            $allowedInstituteIds[] = $selectedInstituteId;
        }

        return [$selectedInstituteId, $allowedInstituteIds];
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
        $meta = $this->meta($request);
        $requestedInstitute = (int) ($request->header('X-MCP-Institute-Id') ?: ($meta['institute_id'] ?? 0));

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
        $meta = $this->meta($request);
        $requestedYear = $meta['academic_year'] ?? null;
        $requestedTerm = $meta['term_id'] ?? null;

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

    /**
     * Legacy HTTP calls put scope under meta. Official MCP JSON-RPC calls put it in
     * params._meta, as defined by the protocol. Neither location is trusted until the
     * authenticated institute check above accepts it.
     *
     * @return array<string, mixed>
     */
    private function meta(Request $request): array
    {
        $legacy = $request->input('meta');

        if (is_array($legacy)) {
            return $legacy;
        }

        $official = $request->input('params._meta');

        return is_array($official) ? $official : [];
    }
}
