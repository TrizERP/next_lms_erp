<?php

namespace App\Services\Mcp;

use App\Mail\StudentReportNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sending a generated report to the people it is about.
 *
 * The one design decision that matters here: **nobody is sent the report.** A fees
 * arrears report is a single document naming every family that owes money, so mailing
 * it to each of those families would disclose all of their debts to all of them. What
 * each recipient gets is their own row and nothing else, composed for them.
 *
 * That is also the more faithful reading of what sending is for. The consolidated
 * table is a management document; what a parent needs is "you owe 4,200 on two items",
 * which is a different document that happens to come from the same query.
 *
 * Three further properties:
 *
 *   1. **Recipients come from a live re-run of the report's query, not from its HTML.**
 *      The document holds escaped text, not records, and the identifiers needed to
 *      reach anybody are deliberately not printed on the page. Re-running the recorded
 *      query also keeps send and refresh consistent — a notice goes out about the same
 *      figures the report would show if refreshed this moment.
 *   2. **Preview and send are separate calls, and send must name the count it saw.** A
 *      send whose recipient list has changed since it was previewed is refused rather
 *      than delivered, so an operator can never approve one list and dispatch another.
 *   3. **Mail is queued, never sent inline.** Two hundred synchronous sends do not fit
 *      inside a request, and a batch that dies half way through has already told half a
 *      school something it cannot retract.
 */
class ReportSender
{
    /**
     * A ceiling on one send.
     *
     * Not a performance limit — an accident limit. A report resolving to more
     * recipients than this is more plausibly a mis-scoped query than a deliberate
     * school-wide mailing, and the cost of guessing wrong is unrecallable.
     */
    public const MAX_RECIPIENTS = 200;

    /** Row keys that identify a record to the system rather than describing it to a reader. */
    private const INTERNAL_KEYS = ['student_id', 'enquiry_id', 'id', 'sub_institute_id', 'syear'];

    public function __construct(private readonly AiReportGenerator $reports)
    {
    }

    /**
     * Who this report would reach, and what each of them would receive.
     *
     * Nothing is sent. The unreachable are listed rather than quietly dropped, because
     * "sent to 8 of 12" is only an honest sentence if the other four are named.
     *
     * @return array<string, mixed>
     */
    public function preview(McpRequestContext $context, int $reportId): array
    {
        $resolved = $this->resolve($context, $reportId);

        if (empty($resolved['success'])) {
            return $resolved;
        }

        $reachable = array_values(array_filter($resolved['recipients'], fn (array $r) => $r['email'] !== null));
        $unreachable = array_values(array_filter($resolved['recipients'], fn (array $r) => $r['email'] === null));

        return ToolResult::success(
            'ai.reports.send.preview',
            $reachable === []
                ? 'Nobody in this report has an email address on record, so there is nobody to send to.'
                : sprintf(
                    '%d of %d can be emailed. Each receives only their own figures.',
                    count($reachable),
                    count($resolved['recipients'])
                ),
            [
                'template_id' => $reportId,
                'module' => $resolved['module'],
                'subject' => $resolved['subject'],
                'recipient_count' => count($reachable),
                'recipients' => array_map(fn (array $r) => [
                    'name' => $r['name'],
                    'email' => $r['email'],
                ], $reachable),
                'unreachable' => array_map(fn (array $r) => [
                    'name' => $r['name'],
                    'reason' => 'No email address on record.',
                ], $unreachable),
                // The first recipient's real notice, so an operator approves the wording
                // they are about to send rather than a description of it.
                'sample' => $reachable === [] ? null : [
                    'to' => $reachable[0]['name'],
                    'html' => $reachable[0]['html'],
                ],
                'over_limit' => count($reachable) > self::MAX_RECIPIENTS,
                'limit' => self::MAX_RECIPIENTS,
            ]
        );
    }

    /**
     * Queue one notice per reachable recipient.
     *
     * @param  int  $expected  The reachable count the caller previewed.
     * @return array<string, mixed>
     */
    public function send(McpRequestContext $context, int $reportId, int $expected): array
    {
        $resolved = $this->resolve($context, $reportId);

        if (empty($resolved['success'])) {
            return $resolved;
        }

        $reachable = array_values(array_filter($resolved['recipients'], fn (array $r) => $r['email'] !== null));

        if ($reachable === []) {
            return ToolResult::failure(
                'ai.reports.send',
                'Nobody in this report has an email address on record, so nothing was sent.',
                'no_recipients',
                ['template_id' => $reportId]
            );
        }

        if (count($reachable) > self::MAX_RECIPIENTS) {
            return ToolResult::failure(
                'ai.reports.send',
                sprintf(
                    'This report resolves to %d recipients, above the %d that may be sent at once. '
                    . 'Narrow the report — by class, or by amount — and send again.',
                    count($reachable),
                    self::MAX_RECIPIENTS
                ),
                'over_limit',
                ['template_id' => $reportId, 'recipient_count' => count($reachable)]
            );
        }

        // The list moved between preview and send: somebody paid, a record changed, or a
        // different report was previewed. Refusing is the only safe answer — an operator
        // approved a list, and this is no longer that list.
        if ($expected !== count($reachable)) {
            return ToolResult::failure(
                'ai.reports.send',
                sprintf(
                    'The recipient list changed since it was previewed — %d now, %d then. Nothing was '
                    . 'sent. Review the list and confirm again.',
                    count($reachable),
                    $expected
                ),
                'recipients_changed',
                ['template_id' => $reportId, 'recipient_count' => count($reachable)]
            );
        }

        $sent = [];
        $failed = [];

        foreach ($reachable as $recipient) {
            try {
                Mail::to($recipient['email'])->queue(
                    new StudentReportNotice($resolved['subject'], $recipient['html'])
                );

                $sent[] = ['name' => $recipient['name'], 'email' => $recipient['email']];
            } catch (Throwable $exception) {
                // One bad address must not abandon the rest of the batch, and must not
                // be reported as delivered either.
                report($exception);
                $failed[] = ['name' => $recipient['name'], 'email' => $recipient['email']];
            }
        }

        Log::info('AI report sent', [
            'template_id' => $reportId,
            'module' => $resolved['module'],
            'sub_institute_id' => $context->selectedInstituteId,
            'sent_by' => $context->userId,
            'queued' => count($sent),
            'failed' => count($failed),
        ]);

        return ToolResult::success(
            'ai.reports.send',
            sprintf(
                '%d notice%s queued, each carrying only that recipient\'s own figures.%s',
                count($sent),
                count($sent) === 1 ? '' : 's',
                $failed === [] ? '' : sprintf(' %d could not be queued.', count($failed))
            ),
            [
                'template_id' => $reportId,
                'queued' => count($sent),
                'failed_count' => count($failed),
                'sent' => $sent,
                'failed' => $failed,
            ]
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * The report, its live rows, and one composed notice per person in them.
     *
     * @return array<string, mixed>
     */
    private function resolve(McpRequestContext $context, int $reportId): array
    {
        $report = DB::table('template_master')
            ->where('id', $reportId)
            ->where('module_name', AiTemplateService::AI_MODULE)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if (! $report) {
            return ToolResult::failure('ai.reports.send', 'That report could not be found.', 'report_not_found');
        }

        $figures = $this->reports->rowsForReport($context, (string) $report->html_content);

        if ($figures === null) {
            return ToolResult::failure(
                'ai.reports.send',
                'This document no longer contains a generated table, so there is nobody it is about. '
                    . 'Generate a new report to send one.',
                'figures_not_found',
                ['template_id' => $reportId]
            );
        }

        if ($figures['rows'] === []) {
            return ToolResult::failure(
                'ai.reports.send',
                sprintf(
                    'This report\'s query matches no %s records now, so there is nobody to send to.',
                    $figures['module']
                ),
                'no_rows',
                ['template_id' => $reportId]
            );
        }

        $title = (string) $report->title;
        $contacts = $this->contactsFor($context, $figures['rows']);
        $recipients = [];

        foreach ($figures['rows'] as $row) {
            $key = $this->identityOf($row);

            if ($key === null) {
                continue;
            }

            $contact = $contacts[$key] ?? null;
            $name = trim((string) ($row['student_name'] ?? $contact['name'] ?? ''));

            $recipients[] = [
                'name' => $name === '' ? 'Unnamed record' : $name,
                'email' => $this->validEmail($contact['email'] ?? null),
                'html' => $this->notice($figures, $row, $name),
            ];
        }

        return [
            'success' => true,
            'module' => $figures['module'],
            'subject' => $this->subjectFor($figures['module'], $title),
            'recipients' => $recipients,
        ];
    }

    /**
     * The identity a row can be reached by.
     *
     * Fees and attendance rows carry an enrolled `student_id`; an admissions enquiry is
     * not a student yet and carries `enquiry_id`. Reading whichever is present keeps
     * this generic rather than a switch the next module has to be added to.
     *
     * @param  array<string, mixed>  $row
     */
    private function identityOf(array $row): ?string
    {
        foreach (['student_id', 'enquiry_id'] as $field) {
            if (! empty($row[$field])) {
                return $field . ':' . (int) $row[$field];
            }
        }

        return null;
    }

    /**
     * Email addresses for the rows, read from the table that owns each identity.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{name: string, email: string|null}>
     */
    private function contactsFor(McpRequestContext $context, array $rows): array
    {
        $studentIds = [];
        $enquiryIds = [];

        foreach ($rows as $row) {
            if (! empty($row['student_id'])) {
                $studentIds[] = (int) $row['student_id'];
            } elseif (! empty($row['enquiry_id'])) {
                $enquiryIds[] = (int) $row['enquiry_id'];
            }
        }

        $contacts = [];

        if ($studentIds !== []) {
            $students = DB::table('tblstudent')
                ->whereIn('id', array_unique($studentIds))
                // Scoped even though the ids came from an already-scoped query: a
                // contact lookup is the last place to take an id on trust.
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->get(['id', 'email', 'first_name', 'middle_name', 'last_name']);

            foreach ($students as $student) {
                $contacts['student_id:' . (int) $student->id] = [
                    'name' => trim(implode(' ', array_filter([
                        $student->first_name, $student->middle_name, $student->last_name,
                    ]))),
                    'email' => $student->email,
                ];
            }
        }

        if ($enquiryIds !== []) {
            $enquiries = DB::table('admission_enquiry')
                ->whereIn('id', array_unique($enquiryIds))
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->get(['id', 'email', 'first_name', 'middle_name', 'last_name']);

            foreach ($enquiries as $enquiry) {
                $contacts['enquiry_id:' . (int) $enquiry->id] = [
                    'name' => trim(implode(' ', array_filter([
                        $enquiry->first_name, $enquiry->middle_name, $enquiry->last_name,
                    ]))),
                    'email' => $enquiry->email,
                ];
            }
        }

        return $contacts;
    }

    private function validEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function subjectFor(string $module, string $title): string
    {
        return match ($module) {
            'fees' => 'Your outstanding school fees',
            'attendance' => 'Your attendance record',
            'admissions' => 'Your admission enquiry',
            default => $title,
        };
    }

    /**
     * One recipient's notice: their own figures, and nothing about anybody else.
     *
     * Escaped on the same principle the report itself follows — a name that arrives as
     * markup must not leave as markup, and an email client is one more renderer that
     * would honour it.
     *
     * @param  array{module: string, source: string, columns: array<int, string>}  $figures
     * @param  array<string, mixed>  $row
     */
    private function notice(array $figures, array $row, string $name): string
    {
        $rows = '';

        foreach ($figures['columns'] as $column) {
            if (in_array($column, self::INTERNAL_KEYS, true) || $column === 'student_name') {
                continue;
            }

            $value = $row[$column] ?? '';

            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $rows .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #eee;color:#475569;">'
                . e(ucfirst(str_replace('_', ' ', $column)))
                . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee;font-weight:600;">'
                . e((string) $value)
                . '</td></tr>';
        }

        return '<div style="font:14px/1.6 Arial,sans-serif;color:#0f172a;">'
            . '<p>Dear ' . e($name === '' ? 'Parent or guardian' : $name) . ',</p>'
            . '<p>' . e($this->openingFor($figures['module'])) . '</p>'
            . '<table style="border-collapse:collapse;margin:12px 0;">' . $rows . '</table>'
            . '<p><small style="color:#64748b;">These figures were read from the school\'s records on '
            . e(now()->format('j M Y'))
            . '. If you believe they are wrong, or you have already settled this, please contact the '
            . 'school office.</small></p>'
            . '</div>';
    }

    private function openingFor(string $module): string
    {
        return match ($module) {
            'fees' => 'Our records show the following fees are still outstanding for you.',
            'attendance' => 'This is a summary of your attendance as currently recorded.',
            'admissions' => 'This is the current status of your admission enquiry with us.',
            default => 'This is the information the school currently holds for you.',
        };
    }
}
