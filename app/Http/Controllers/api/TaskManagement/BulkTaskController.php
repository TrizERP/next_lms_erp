<?php

namespace App\Http\Controllers\api\TaskManagement;

use App\Http\Controllers\api\TaskManagement\Concerns\ResolvesTaskManagementContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskManagement\BulkTaskImportRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from hp_erp's `App\Http\Controllers\front_desk\BulkTaskController`
 * (CSV/JSON bulk task import), moved into the TaskManagement namespace and
 * routed through the session context instead of `ResolvesApiIdentity`.
 *
 * The source also fired an FCM push notification per created task via
 * `kreait/firebase-php`, a package this target does not have installed
 * (`composer.json` carries no `kreait/firebase-php` dependency). That push
 * is replaced with the module's own in-app notification
 * (`NotificationController::notify`), the same substitution
 * `LegacyTaskController::store` already makes for a single-task create -
 * the business rule ("tell the assignee") is kept; only the delivery
 * channel changes to one this target actually has.
 */
class BulkTaskController extends Controller
{
    use ResolvesTaskManagementContext;

    public function import(BulkTaskImportRequest $request)
    {
        $context = $this->taskManagementContext($request);

        try {
            if ($request->input('formType') !== 'BulkTask') {
                return $this->taskManagementError('Invalid formType. Use BulkTask', 400);
            }

            $insertCount = 0;
            $taskDetails = [];
            $skippedTasks = [];

            if ($request->hasFile('csv_file')) {
                $file = $request->file('csv_file');
                if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                    $headers = fgetcsv($handle, 1000, ',');
                    if ($headers && count($headers) > 0) {
                        $headers = array_map(function ($header) {
                            $header = (string) $header;
                            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
                            return trim($header);
                        }, $headers);
                    }
                    $rowNum = 2;
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        if (count($headers) === count($row)) {
                            $rowAssoc = array_combine($headers, $row);
                            $rowAssoc['_row_num'] = $rowNum;
                            $taskDetails[] = $rowAssoc;
                        } else {
                            $skippedTasks[] = ['row' => $rowNum, 'reason' => 'Row column count does not match header column count'];
                        }
                        $rowNum++;
                    }
                    fclose($handle);
                }
            } elseif ($request->has('task_details')) {
                $taskDetails = json_decode($request->input('task_details'), true) ?: [];
            }

            if (empty($taskDetails)) {
                return $this->taskManagementError('No task data provided', 400);
            }

            foreach ($taskDetails as $index => $taskValue) {
                $rowNum = $taskValue['_row_num'] ?? ($index + 1);
                $taskValue = $this->normalizeTaskRow($taskValue);

                $assignedName = trim((string) $this->getTaskFieldValue($taskValue, [
                    'assigned_to', 'employee_name_assigned_to', 'employee_name',
                    'calendar_assigned_to', 'calendar_assignedto',
                ], ''));
                $departmentName = trim((string) $this->getTaskFieldValue($taskValue, ['department'], ''));
                $jobRoleName = trim((string) $this->getTaskFieldValue($taskValue, ['jobrole', 'job_role'], ''));
                $observerName = trim((string) $this->getTaskFieldValue($taskValue, ['observer', 'observation_point'], ''));
                $taskTitle = trim((string) $this->getTaskFieldValue($taskValue, ['task_title', 'calendar_subject'], ''));
                $taskDesc = trim((string) $this->getTaskFieldValue($taskValue, ['task_description', 'calendar_description'], ''));
                $completionRemarks = trim((string) $this->getTaskFieldValue($taskValue, ['taskcompletation_remarks', 'calendar_event_completion_remarks'], ''));

                if ($taskTitle === '') {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Task title is missing', ['assigned_to' => $assignedName]);
                    continue;
                }
                if ($assignedName === '') {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Assigned employee name is missing', ['task_title' => $taskTitle]);
                    continue;
                }

                $resolvedUser = $this->resolveTaskUser($context['sub_institute_id'], $assignedName, $departmentName, $jobRoleName);
                $matchedUser = $resolvedUser['user'];

                if (!$matchedUser) {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, $resolvedUser['reason'], [
                        'task_title' => $taskTitle, 'assigned_to' => $assignedName,
                        'department' => $departmentName, 'job_role' => $jobRoleName,
                    ]);
                    continue;
                }

                $allocatedUserId = $matchedUser->id;
                $task_type = $this->normalizeTaskType($this->getTaskFieldValue($taskValue, ['task_type', 'task_priority'], 'Medium'));

                $rawTaskDate = $this->getTaskFieldValue($taskValue, ['task_date', 'task_deadline', 'calendar_start_date_time']);
                $parsedTaskDate = $this->parseTaskDate($rawTaskDate);
                if (!empty($rawTaskDate) && $parsedTaskDate === null) {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Invalid task date format', [
                        'task_title' => $taskTitle, 'assigned_to' => $assignedName, 'task_date' => $rawTaskDate,
                    ]);
                    continue;
                }
                $task_date = $parsedTaskDate ?? date('Y-m-d');

                $rawRepeatDays = $this->getTaskFieldValue($taskValue, ['repeat_days', 'repeat_once_in_every_days'], 1);
                if ($rawRepeatDays !== null && $rawRepeatDays !== '' && (!is_numeric($rawRepeatDays) || (int) $rawRepeatDays < 1)) {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Repeat days must be a number greater than 0', [
                        'task_title' => $taskTitle, 'assigned_to' => $assignedName, 'repeat_days' => $rawRepeatDays,
                    ]);
                    continue;
                }
                $repeat_days = max((int) $rawRepeatDays, 1);

                $rawRepeatUntil = $this->getTaskFieldValue($taskValue, ['repeat_until', 'calendar_end_date_time']);
                $repeat_until = $this->parseTaskDate($rawRepeatUntil);
                if (!empty($rawRepeatUntil) && $repeat_until === null) {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Invalid repeat until date format', [
                        'task_title' => $taskTitle, 'assigned_to' => $assignedName, 'repeat_until' => $rawRepeatUntil,
                    ]);
                    continue;
                }
                if (!empty($repeat_until) && $repeat_until < $task_date) {
                    $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Repeat until date cannot be earlier than task date', [
                        'task_title' => $taskTitle, 'assigned_to' => $assignedName,
                        'task_date' => $task_date, 'repeat_until' => $repeat_until,
                    ]);
                    continue;
                }

                $calendarStatus = trim((string) $this->getTaskFieldValue($taskValue, ['calendar_status'], ''));
                $taskStatus = $this->mapTaskStatus($calendarStatus);
                $dates = $this->getDatesWithoutSundays($task_type, $task_date, $repeat_days, $repeat_until);

                $baseTask = [
                    'sub_institute_id' => $context['sub_institute_id'],
                    'SYEAR' => $context['syear'],
                    'TASK_TITLE' => $taskTitle,
                    'TASK_DESCRIPTION' => $taskDesc,
                    'taskcompletation_remarks' => $completionRemarks,
                    'observation_point' => $observerName ?: null,
                    'repeat_days' => $repeat_days,
                    'task_type' => $task_type,
                    'TASK_ALLOCATED' => $context['user_id'],
                    'TASK_ALLOCATED_TO' => $allocatedUserId,
                    'CREATED_BY' => $context['user_id'],
                    'STATUS' => $taskStatus,
                    'CREATED_IP_ADDRESS' => $request->ip(),
                    'CREATED_ON' => now(),
                ];

                $datesToInsert = !empty($dates) ? $dates : [$task_date];

                foreach ($datesToInsert as $date) {
                    $data = $baseTask;
                    $data['TASK_DATE'] = $date;

                    try {
                        $insertId = DB::table('task')->insertGetId($data);
                        if ($insertId) {
                            $insertCount++;
                            $this->notifyAssignee($context['sub_institute_id'], $allocatedUserId, $taskTitle, $context['user_id'], $insertId);
                        } else {
                            $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, 'Task insert failed', [
                                'task_title' => $taskTitle, 'assigned_to' => $assignedName, 'task_date' => $date,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $skippedTasks[] = $this->buildSkippedTaskDetail($rowNum, $e->getMessage(), [
                            'task_title' => $taskTitle, 'assigned_to' => $assignedName, 'task_date' => $date,
                        ]);
                    }
                }
            }

            return response()->json([
                'status_code' => $insertCount > 0 ? 1 : 0,
                'message' => $insertCount > 0 ? "$insertCount tasks imported successfully" : 'No tasks were imported',
                'imported' => $insertCount,
                'skipped_count' => count($skippedTasks),
                'skipped_details' => $skippedTasks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function getDatesWithoutSundays($type = '', $task_date = '', $repeat_days = 1, $repeat_until = null)
    {
        $startDate = $task_date ? Carbon::parse($task_date) : Carbon::now();
        $endDate = $repeat_until
            ? Carbon::parse($repeat_until)
            : ($task_date ? Carbon::parse($task_date) : Carbon::create($startDate->year, $startDate->month)->endOfMonth());

        $dates = [];

        if (in_array($type, ['High', 'Medium', 'Low'])) {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDays($repeat_days)) {
                if (!$date->isSunday()) {
                    $dates[] = $date->format('Y-m-d');
                }
            }
        } else {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                if (!$date->isSunday()) {
                    $dates[] = $date->format('Y-m-d');
                }
            }
        }

        return $dates;
    }

    private function buildSkippedTaskDetail(int $rowNum, string $reason, array $context = []): array
    {
        return array_merge(
            ['row' => $rowNum, 'reason' => $reason],
            array_filter($context, fn ($value) => $value !== null && $value !== '')
        );
    }

    private function normalizeTaskRow(array $row): array
    {
        $normalizedRow = [];
        foreach ($row as $key => $value) {
            if ($key === '_row_num') {
                $normalizedRow[$key] = $value;
                continue;
            }
            $normalizedRow[$this->normalizeFieldKey($key)] = is_string($value) ? trim($value) : $value;
        }
        return $normalizedRow;
    }

    private function normalizeFieldKey($key): string
    {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9]+/', '', $key);
    }

    private function getTaskFieldValue(array $row, array $possibleKeys, $default = null)
    {
        foreach ($possibleKeys as $key) {
            $normalizedKey = $this->normalizeFieldKey($key);
            if (array_key_exists($normalizedKey, $row) && $row[$normalizedKey] !== '' && $row[$normalizedKey] !== null) {
                return $row[$normalizedKey];
            }
        }
        return $default;
    }

    private function normalizeTaskType($taskType): string
    {
        $taskType = ucfirst(strtolower(trim((string) $taskType)));
        return in_array($taskType, ['High', 'Medium', 'Low'], true) ? $taskType : 'Medium';
    }

    private function parseTaskDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'm/d/Y', 'd-m-y', 'd/m/y',
            'Y-m-d H:i:s', 'd-m-Y H:i:s', 'd/m/Y H:i:s', 'd-m-Y h:i A', 'd/m/Y h:i A', 'Y-m-d H:i',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Exception $e) {
            }
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private function mapTaskStatus(string $calendarStatus): string
    {
        if ($calendarStatus === '') {
            return 'PENDING';
        }
        if (strcasecmp($calendarStatus, 'Planned') === 0 || strcasecmp($calendarStatus, 'Pending') === 0) {
            return 'PENDING';
        }
        if (strcasecmp($calendarStatus, 'Held') === 0 || strcasecmp($calendarStatus, 'Completed') === 0) {
            return 'COMPLETED';
        }
        if (strcasecmp($calendarStatus, 'In Progress') === 0) {
            return 'IN-PROGRESS';
        }
        if (strcasecmp($calendarStatus, 'On Hold') === 0) {
            return 'ON HOLD';
        }
        return 'PENDING';
    }

    private function resolveTaskUser($subInstituteId, string $assignedName, string $departmentName = '', string $jobRoleName = ''): array
    {
        $assignedName = trim(preg_replace('/\s+/', ' ', $assignedName));

        $searchAttempts = [];
        if ($departmentName !== '' || $jobRoleName !== '') {
            $searchAttempts[] = [$departmentName, $jobRoleName];
            if ($departmentName !== '') {
                $searchAttempts[] = [$departmentName, ''];
            }
            if ($jobRoleName !== '') {
                $searchAttempts[] = ['', $jobRoleName];
            }
        }
        $searchAttempts[] = ['', ''];

        foreach ($searchAttempts as [$departmentFilter, $jobRoleFilter]) {
            $query = DB::table('tbluser as u')
                ->leftJoin('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
                ->leftJoin('s_user_jobrole as uj', 'uj.id', '=', 'u.allocated_standards')
                ->where('u.sub_institute_id', $subInstituteId)
                ->where('u.status', 1)
                ->select('u.*')
                ->where(function ($nameQuery) use ($assignedName) {
                    $nameQuery
                        ->whereRaw("LOWER(TRIM(CONCAT_WS(' ', COALESCE(u.first_name, ''), COALESCE(u.middle_name, ''), COALESCE(u.last_name, '')))) = ?", [strtolower($assignedName)])
                        ->orWhereRaw("LOWER(TRIM(CONCAT_WS(' ', COALESCE(u.first_name, ''), COALESCE(u.last_name, '')))) = ?", [strtolower($assignedName)])
                        ->orWhereRaw('LOWER(TRIM(u.first_name)) = ?', [strtolower($assignedName)]);
                });

            if ($departmentFilter !== '') {
                $query->whereRaw('LOWER(TRIM(hd.department)) = ?', [strtolower(trim($departmentFilter))]);
            }
            if ($jobRoleFilter !== '') {
                $query->whereRaw('LOWER(TRIM(uj.jobrole)) = ?', [strtolower(trim($jobRoleFilter))]);
            }

            $matches = $query->get();

            if ($matches->count() === 1) {
                return ['user' => $matches->first(), 'reason' => null];
            }
            if ($matches->count() > 1) {
                return ['user' => null, 'reason' => 'Multiple active users matched this employee name. Please make the employee name unique in the CSV.'];
            }
        }

        return ['user' => null, 'reason' => 'User not found in this sub institute with the given employee name, department, and job role'];
    }

    private function notifyAssignee(int $sid, int $assigneeId, string $taskTitle, int $assignerId, int $taskId): void
    {
        NotificationController::notify($sid, $assigneeId, 'task_assigned', 'New task: ' . $taskTitle, null, $taskId);
    }
}
