<?php

namespace Tests\Unit;

use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\NotificationSender;
use App\Services\Platform\SchedulerDispatcher;
use App\Services\Platform\SchedulerNotifier;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Synthetic tenant -1 and faked collaborators only: nothing here writes to the database. The recipient lookup
 * and the "last stamp" lookup are overridden in a subclass; the one query-shape test runs under DB::pretend.
 */
class SchedulerNotifierTest extends TestCase
{
    private function task(string $key = 'library.circulation.overdue_scan'): PlatformScheduledTask
    {
        $task = new PlatformScheduledTask();
        $task->sub_institute_id = -1;
        $task->task_key = $key;

        return $task;
    }

    private function finding(int $count = 3, string $fp = '3|2026-01-01'): array
    {
        return ['key' => 'overdue_books', 'count' => $count, 'severity' => 'warning', 'fingerprint' => $fp, 'message' => "{$count} overdue"];
    }

    /** @return array{0:SchedulerNotifier,1:object} notifier and a log of dispatch calls */
    private function notifier(array $users, array &$lastStamps = [], bool $senderThrows = false): array
    {
        $log = new \stdClass();
        $log->calls = [];
        $sender = new class($log, $senderThrows) extends NotificationSender {
            public function __construct(private object $log, private bool $throws) {}

            public function dispatch(int $tenantId, string $eventKey, array $recipients, string $subject, string $body, bool $isSample = false, ?string $stamp = null): array
            {
                if ($this->throws) {
                    throw new \RuntimeException('boom');
                }
                $this->log->calls[] = compact('tenantId', 'eventKey', 'recipients', 'subject', 'body', 'stamp');

                return [];
            }
        };
        $notifier = new class($sender, $users, $lastStamps) extends SchedulerNotifier {
            public function __construct(NotificationSender $s, private array $users, private array &$stamps)
            {
                parent::__construct($s);
            }

            public function recipientUserIds(int $tenant, array $lowerNames): array
            {
                return $this->users;
            }

            public function lastStamp(int $tenant, string $recipient, string $eventKey): ?string
            {
                return $this->stamps[$recipient.'|'.$eventKey] ?? null;
            }
        };

        return [$notifier, $log];
    }

    public function test_dispatcher_accepts_string_and_structured_results(): void
    {
        $this->assertSame(['summary' => 'All fine.', 'findings' => []], SchedulerDispatcher::normalise('All fine.'));

        $n = SchedulerDispatcher::normalise(['summary' => 'S', 'findings' => [$this->finding(), ['nokey' => 1]]]);
        $this->assertSame('S', $n['summary']);
        $this->assertCount(1, $n['findings']);
        $this->assertSame(3, $n['findings'][0]['count']);
        $this->assertSame('warning', $n['findings'][0]['severity']);
    }

    public function test_count_zero_sends_nothing(): void
    {
        [$notifier, $log] = $this->notifier([7]);
        $this->assertSame(0, $notifier->notify($this->task(), [$this->finding(0, '0')]));
        $this->assertSame([], $log->calls);
    }

    public function test_one_web_notice_per_recipient_with_user_recipient_and_stamp(): void
    {
        [$notifier, $log] = $this->notifier([7, 8]);
        $this->assertSame(2, $notifier->notify($this->task(), [$this->finding()]));
        $this->assertSame([['channel' => 'web', 'recipient' => 'user:7']], $log->calls[0]['recipients']);
        $this->assertSame('library.circulation.overdue_scan.finding.overdue_books', $log->calls[0]['eventKey']);
        $this->assertSame('fp:overdue_books|3|2026-01-01', $log->calls[0]['stamp']);
        $this->assertSame(-1, $log->calls[0]['tenantId']);
    }

    public function test_same_fingerprint_is_skipped_and_a_change_is_sent(): void
    {
        $stamps = ['user:7|library.circulation.overdue_scan.finding.overdue_books' => 'fp:overdue_books|3|2026-01-01'];
        [$notifier, $log] = $this->notifier([7], $stamps);

        $this->assertSame(0, $notifier->notify($this->task(), [$this->finding(3, '3|2026-01-01')]));
        $this->assertSame([], $log->calls);

        $this->assertSame(1, $notifier->notify($this->task(), [$this->finding(4, '4|2026-01-01')]));
        $this->assertCount(1, $log->calls);
    }

    public function test_notifier_failure_never_throws(): void
    {
        $none = [];
        [$notifier] = $this->notifier([7], $none, true);
        $this->assertSame(0, $notifier->notify($this->task(), [$this->finding()]));
    }

    public function test_dispatcher_notify_wrapper_swallows_a_broken_notifier(): void
    {
        $this->app->bind(SchedulerNotifier::class, fn () => throw new \RuntimeException('cannot build'));
        $this->assertSame(0, (new SchedulerDispatcher())->notifyFindings($this->task(), [$this->finding()]));
    }

    public function test_audience_is_staff_profile_names_and_never_students_or_parents(): void
    {
        $notifier = new SchedulerNotifier();
        $this->assertSame(['librarian', 'school admin'], $notifier->audienceFor('library.circulation.overdue_scan'));
        $this->assertSame([], $notifier->audienceFor('some.unlisted.task'));

        config(['platform_services.scheduler_audiences' => ['x.y.z' => ['Admin', 'Student', ' Parent ', 'Guardian']]]);
        $this->assertSame(['admin'], $notifier->audienceFor('x.y.z'));
    }

    public function test_recipients_are_resolved_by_profile_name_not_role_key(): void
    {
        $queries = DB::pretend(fn () => (new SchedulerNotifier())->recipientUserIds(-1, ['librarian']));
        $sql = strtolower($queries[0]['query'] ?? '');

        $this->assertStringContainsString('tbluserprofilemaster', $sql);
        $this->assertStringContainsString('user_profile_id', $sql);
        $this->assertStringContainsString('lower(trim(p.name))', str_replace('`', '', $sql));
        $this->assertStringNotContainsString('role_key', $sql);
        $this->assertSame([-1, 1, 'librarian'], $queries[0]['bindings']);
    }

    public function test_pilot_jobs_are_audience_mapped(): void
    {
        foreach (['library.circulation.overdue_scan', 'attendance.student.unmarked_check', 'fees.defaulter.overdue_scan'] as $key) {
            $this->assertNotSame([], (new SchedulerNotifier())->audienceFor($key), $key);
        }
    }
}
