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
        $request = Request::create('/studentFeesDetailAPI', 'POST', [
            'student_id' => $studentId,
            'type' => 'JSON',
            'sub_institute_id' => $context->selectedInstituteId,
            'syear' => $context->academicYear,
        ]);

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
