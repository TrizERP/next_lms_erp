<?php

namespace App\Services\Mcp;

use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Http\Request;

class FeesPendingService
{
    public function getPending(McpRequestContext $context, array $arguments): array
    {
        $studentId = (int) ($arguments['student_id'] ?? 0);
        if ($studentId <= 0) {
            return ToolResult::failure('fees.getPending', 'A valid student is required.', 'MISSING_STUDENT_ID');
        }

        $controller = app(fees_collect_controller::class);

        // `type` is deliberately not sent, and that single parameter was the whole bug.
        //
        // `studentFeesDetailAPI` branches on it: with a `type` (or a `month_id`) it
        // answers with the *paid receipts* array and nothing else, and only without one
        // does it return the {STU_DATA, PENDING, PAID} structure this service reads. So
        // sending `type => JSON` meant every call came back holding receipts, `PENDING`
        // was never present, and the filter below found nothing to report.
        //
        // The result was a tool that could not fail and could not be right: every student
        // in every institute came back "no pending fees were found", and `fees.arrears`,
        // which asks this service once per student, therefore swept whole cohorts and
        // announced zero defaulters. Verified against a live tenant, one of the students
        // it had just cleared was carrying ₹50,700 outstanding.
        $request = Request::create('/studentFeesDetailAPI', 'POST', [
            'student_id' => $studentId,
            'sub_institute_id' => $context->selectedInstituteId,
            'syear' => $context->academicYear,
        ]);

        // `studentFeesDetailAPI` writes the tenant and year into the session, and the
        // arrears engine underneath it (`getBk`) reads them back from there rather than
        // from its arguments. A request built with `Request::create()` carries no session
        // store at all, so that write threw "Session store not set on request." and the
        // whole call failed — in every context, not just the console.
        //
        // Binding the container's own store keeps one session instance, so what the
        // controller puts is what the global `session()` helper inside `getBk` reads.
        $request->setLaravelSession(app('session.store'));

        $payload = json_decode((string) $controller->studentFeesDetailAPI($request), true) ?: [];
        $data = $payload['data'] ?? [];
        $student = $data['STU_DATA'] ?? [];
        $pending = array_values(array_filter($data['PENDING'] ?? [], static fn ($row) => !empty($row['remain']) || !empty($row->remain)));

        return ToolResult::success(
            'fees.getPending',
            count($pending) > 0 ? 'Pending fees loaded successfully.' : 'No pending fees were found for this student.',
            [
                'student' => [
                    'student_id' => (int) ($student['student_id'] ?? $studentId),
                    'student_name' => $student['name'] ?? null,
                    'enrollment_no' => $student['enrollment'] ?? null,
                    'standard_name' => $student['stddiv'] ?? null,
                ],
                'pending_items' => $pending,
                'pending_count' => count($pending),
            ],
            [
                'uiAction' => [
                    'type' => 'navigate',
                    'path' => '/fees/collect/' . $studentId,
                    'params' => ['student_id' => $studentId],
                ],
                'conversationPatch' => [
                    'workflow' => 'fees_collection',
                    'currentStep' => 'review_pending_fees',
                    'selectedEntityType' => 'student',
                    'selectedEntityId' => $studentId,
                    'workflowCompleted' => false,
                ],
            ]
        );
    }
}
