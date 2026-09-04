<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AdmissionMcpService;
use App\Services\Mcp\McpRequestContext;

/**
 * Fill in what an admission is missing.
 *
 * This is the piece that was only ever in the frontend, and without it the admissions
 * conversation could report that four fields were missing and then do nothing about it.
 *
 * It is a write tool but deliberately not a confirmable one. The consequential act in
 * this flow is `admissions.confirm`, which creates a student enrolment and carries its
 * own admin gate and confirmation token. Filling in an enrolment number is data entry:
 * making the user confirm each field would turn a two-turn exchange into six and teach
 * them to click through confirmations without reading, which is how a real confirmation
 * stops meaning anything.
 *
 * `read_only: false` keeps it out of the model planner's catalogue regardless, so it is
 * reachable only from the deterministic admissions route.
 */
class AdmissionsUpdateEnquiryTool extends AbstractMcpTool
{
    public function __construct(private readonly AdmissionMcpService $service)
    {
    }

    protected function name(): string
    {
        return 'admissions.updateEnquiry';
    }

    protected function description(): string
    {
        return 'Set the fields an admission needs before it can be confirmed — first name, last '
            . 'name, standard, division, quota, admission date, enrollment number. Only those '
            . 'fields can be written; anything else is rejected. Use it after '
            . 'admissions.validateConfirmation reports missing fields.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enquiry_id' => ['type' => 'integer', 'minimum' => 1],
                'updates' => [
                    'type' => 'object',
                    'description' => 'Field name to value. Only the seven confirmation-required fields are accepted.',
                    'properties' => [
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string'],
                        'admission_standard' => ['type' => 'string', 'description' => 'Standard id.'],
                        'admission_division' => ['type' => 'string', 'description' => 'Division id.'],
                        'student_quota' => ['type' => 'string'],
                        'admission_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                        'enrollment_no' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['enquiry_id', 'updates'],
            'additionalProperties' => false,
        ];
    }

    protected function isReadOnly(): bool
    {
        return false;
    }

    protected function annotations(): array
    {
        return [
            'risk' => 'write',
            'required_permission' => 'admission.confirm',
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->updateEnquiry($context, $arguments);
    }
}
