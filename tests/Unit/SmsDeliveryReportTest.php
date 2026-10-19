<?php

namespace Tests\Unit;

use App\Services\Mcp\Concerns\ReadsSmsDelivery;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What an SMS is called after the gateway accepted it, according to the gateway's own delivery report.
 *
 * HTTP is faked and nothing is sent. The response shapes are the ones login.smsforyou.biz really returned (2026-10-15):
 * every message sent that day was still "Submitted" with a 1970 delivery date.
 */
class SmsDeliveryReportTest extends TestCase
{
    private const GATEWAY = [
        'url' => 'https://login.smsforyou.biz/V2/http-api.php?',
        'pram' => 'apikey=SECRETKEY&senderid=SMSEDU&format=json',
        'sub_institute_id' => 1,
    ];

    private function reader(?array $gateway = self::GATEWAY): object
    {
        return new class($gateway)
        {
            use ReadsSmsDelivery {
                smsDeliveryOf as public delivery;
                fetchSmsDeliveryReport as public fetch;
                classifySmsReport as public classify;
            }

            public function __construct(private readonly ?array $gateway)
            {
            }

            protected function smsGateway(McpRequestContext $context): ?array
            {
                return $this->gateway;
            }

            public function budget(): int
            {
                return $this->deliveryReportBudget;
            }
        };
    }

    private function context(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
    }

    private function fakeReport(string $description, string $deliveredDate = '1970-01-01 05:30 am'): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.smsforyou.biz/*' => Http::response([
            'status' => 'OK',
            'data' => [['id' => '9918178045825279', 'mobile' => null, 'status' => null, 'status-desc' => $description, 'status_desc' => $description, 'delivered_date' => $deliveredDate]],
            'message' => 'message '.$description.' successfully',
        ], 200)]);
    }

    public function test_the_report_is_read_with_the_institutes_own_key_and_the_epoch_date_is_not_a_delivery_time(): void
    {
        $this->fakeReport('Submitted');

        $report = $this->reader()->fetch(self::GATEWAY, '9918178045825279');

        $this->assertSame(['status' => 'Submitted', 'delivered_at' => null], $report);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/V2/http-dlr.php')
            && $request['msgid'] === '9918178045825279' && $request['apikey'] === 'SECRETKEY');
    }

    public function test_a_gateway_with_no_known_report_call_is_unknown_not_guessed_at(): void
    {
        Http::preventStrayRequests();

        $report = $this->reader()->fetch(['url' => 'https://sms.infisms.co.in/API/SendSMS.aspx?', 'pram' => 'UserID=x'] + self::GATEWAY, '123');

        $this->assertNull($report);
        [$state] = $this->reader(['url' => 'https://sms.infisms.co.in/API/SendSMS.aspx?', 'pram' => 'UserID=x'] + self::GATEWAY)
            ->delivery($this->context(), '123', now()->subHour()->toDateTimeString());
        $this->assertSame('unknown', $state);
    }

    /** @return array<string, array{0:string, 1:int, 2:string}> */
    public static function reports(): array
    {
        return [
            'delivered' => ['Delivered', 90, 'delivered'],
            'still submitted, recent' => ['Submitted', 4, 'pending'],
            'still submitted, an hour on' => ['Submitted', 60, 'not_confirmed'],
            'queued for hours' => ['Queued', 240, 'not_confirmed'],
            'failed' => ['Failed', 30, 'failed'],
            'rejected by the operator' => ['Rejected', 30, 'failed'],
            'undelivered' => ['Undelivered', 30, 'failed'],
            'do not disturb' => ['Failed - DND', 30, 'failed'],
            'something else' => ['Frozen', 30, 'unknown'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reports')]
    public function test_each_gateway_status_maps_to_what_it_actually_proves(string $description, int $minutesAgo, string $expected): void
    {
        $this->fakeReport($description, $expected === 'delivered' ? '2026-10-15 16:10:00' : '1970-01-01 05:30 am');

        [$state, $note] = $this->reader()->delivery($this->context(), '9918178045825279', now()->subMinutes($minutesAgo)->toDateTimeString());

        $this->assertSame($expected, $state, $note);
    }

    public function test_a_message_stuck_at_submitted_says_so_and_what_to_check(): void
    {
        $this->fakeReport('Submitted');

        [$state, $note] = $this->reader()->delivery($this->context(), '9918178045825279', now()->subHours(3)->toDateTimeString());

        $this->assertSame('not_confirmed', $state);
        $this->assertStringContainsString('Still "Submitted"', $note);
        $this->assertStringContainsString('3 hours', $note);
        $this->assertStringContainsString('DLT template', $note);
        $this->assertStringNotContainsString('SECRETKEY', $note);
    }

    public function test_a_gateway_failure_quotes_the_gateways_own_words(): void
    {
        $this->fakeReport('Failed');

        [$state, $note] = $this->reader()->delivery($this->context(), '9918178045825279', now()->subMinutes(30)->toDateTimeString());

        $this->assertSame('failed', $state);
        $this->assertStringContainsString('"Failed"', $note);
    }

    public function test_no_message_id_or_an_unreachable_gateway_is_unknown_and_never_delivered(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 500)]);

        $this->assertSame('unknown', $this->reader()->delivery($this->context(), null, now()->toDateTimeString())[0]);
        $this->assertSame('unknown', $this->reader()->delivery($this->context(), '9918178045825279', now()->subHour()->toDateTimeString())[0]);
    }

    public function test_one_status_read_makes_a_bounded_number_of_gateway_calls(): void
    {
        $this->fakeReport('Submitted');
        $reader = $this->reader();

        for ($i = 0; $i < 25; $i++) {
            $reader->delivery($this->context(), '9918178045825279', now()->subHour()->toDateTimeString());
        }

        Http::assertSentCount(10);
        $this->assertSame(0, $reader->budget());
    }
}
