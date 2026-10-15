<?php

namespace Tests\Feature;

use App\Services\Mcp\Concerns\DeliversWhatsapp;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Meta's delivery receipts: who may send them, what they change, and what the status read then says.
 *
 * Runs on a throwaway in-memory SQLite database. The real `whatsapp_sent_messages` table is never touched.
 */
class WhatsappWebhookTest extends TestCase
{
    private const SECRET = 'app-secret-for-tests';

    private const MESSAGE_ID = 'wamid.TESTMESSAGE1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'services.whatsapp.verify_token' => 'verify-me',
            'services.whatsapp.app_secret' => self::SECRET,
        ]);
        DB::purge('sqlite');

        Schema::create('whatsapp_sent_messages', function ($table) {
            $table->id();
            $table->string('uri')->nullable();
            $table->string('message_status')->nullable();
            $table->string('message_error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('whatsapp_sent_messages')->insert([
            'uri' => self::MESSAGE_ID, 'message_status' => 'accepted', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);
    }

    protected function tearDown(): void
    {
        // The in-memory database goes with its connection. (The application deliberately blocks DROP TABLE, so none is issued.)
        DB::purge('sqlite');

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $status */
    private function metaCallback(array $status, ?string $secret = self::SECRET)
    {
        $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [$status + ['id' => self::MESSAGE_ID]]]]]]]]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret ?? 'wrong')];

        return $this->call('POST', '/api/whatsapp/webhook', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    private function stored(): array
    {
        $row = DB::table('whatsapp_sent_messages')->where('uri', self::MESSAGE_ID)->first();

        return [$row->message_status, $row->message_error];
    }

    public function test_meta_verifies_the_url_only_with_the_configured_token(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')->assertOk()->assertSee('12345');
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();

        config(['services.whatsapp.verify_token' => '']);
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=&hub.challenge=12345')->assertForbidden();
    }

    public function test_a_callback_without_a_valid_signature_changes_nothing(): void
    {
        $this->metaCallback(['status' => 'delivered'], 'not-the-secret')->assertForbidden();
        $this->assertSame(['accepted', null], $this->stored());

        // And a server with no app secret configured accepts nothing at all.
        config(['services.whatsapp.app_secret' => '']);
        $this->metaCallback(['status' => 'delivered'], '')->assertForbidden();
        $this->assertSame(['accepted', null], $this->stored());
    }

    public function test_delivered_then_read_are_recorded_and_a_late_sent_never_undoes_them(): void
    {
        $this->metaCallback(['status' => 'delivered'])->assertOk()->assertJson(['updated' => 1]);
        $this->assertSame('delivered', $this->stored()[0]);

        $this->metaCallback(['status' => 'read'])->assertOk();
        $this->assertSame('read', $this->stored()[0]);

        $this->metaCallback(['status' => 'sent'])->assertOk()->assertJson(['updated' => 0]);
        $this->metaCallback(['status' => 'delivered'])->assertOk()->assertJson(['updated' => 0]);
        $this->assertSame('read', $this->stored()[0]);
    }

    public function test_a_failure_records_metas_own_code_and_reason(): void
    {
        $this->metaCallback(['status' => 'failed', 'errors' => [[
            'code' => 131026, 'title' => 'Message undeliverable', 'message' => 'Message undeliverable',
            'error_data' => ['details' => 'Unable to deliver the message. Recipient may not be a valid WhatsApp user.'],
        ]]])->assertOk();

        [$status, $error] = $this->stored();

        $this->assertSame('failed', $status);
        $this->assertStringContainsString('131026', $error);
        $this->assertStringContainsString('not be a valid WhatsApp user', $error);
    }

    public function test_an_unknown_message_or_status_is_ignored_and_still_answered_ok(): void
    {
        $this->metaCallback(['status' => 'delivered', 'id' => 'wamid.SOMEONE-ELSES'])->assertOk()->assertJson(['updated' => 0]);
        $this->metaCallback(['status' => 'telepathy'])->assertOk()->assertJson(['updated' => 0]);
        $this->assertSame('accepted', $this->stored()[0]);
    }

    // ---- what the status read says, with and without receipts reaching this system

    private function reader(): object
    {
        return new class
        {
            use DeliversWhatsapp {
                whatsappReceipt as public receipt;
            }
        };
    }

    public function test_an_accepted_message_with_no_receipt_is_called_not_confirmed_and_says_why(): void
    {
        $sentAt = now()->subMinutes(40)->toDateTimeString();

        // No message here has ever had a receipt: this system is not hearing from Meta.
        [$state, $note] = $this->reader()->receipt(self::MESSAGE_ID, $sentAt);
        $this->assertSame('not_confirmed', $state);
        $this->assertStringContainsString('not receiving Meta', $note);
        $this->assertStringContainsString('/api/whatsapp/webhook', $note);

        // Once receipts do arrive (some other message was delivered through the webhook), silence means undelivered.
        DB::table('whatsapp_sent_messages')->insert([
            'uri' => 'wamid.OTHER', 'message_status' => 'delivered', 'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(2),
        ]);
        [$state, $note] = $this->reader()->receipt(self::MESSAGE_ID, $sentAt);
        $this->assertSame('not_confirmed', $state);
        $this->assertStringContainsString('likely undelivered', $note);
    }

    public function test_a_recent_message_is_only_pending_and_a_reported_one_shows_what_was_reported(): void
    {
        $this->assertSame('pending', $this->reader()->receipt(self::MESSAGE_ID, now()->subMinutes(3)->toDateTimeString())[0]);

        $this->metaCallback(['status' => 'delivered'])->assertOk();
        $this->assertSame('delivered', $this->reader()->receipt(self::MESSAGE_ID, now()->subHour()->toDateTimeString())[0]);

        $this->metaCallback(['status' => 'read'])->assertOk();
        $this->assertSame('read', $this->reader()->receipt(self::MESSAGE_ID, now()->subHour()->toDateTimeString())[0]);
    }

    public function test_a_failed_receipt_reports_metas_reason(): void
    {
        $this->metaCallback(['status' => 'failed', 'errors' => [['code' => 131049, 'title' => 'Healthy ecosystem engagement']]])->assertOk();

        [$state, $note] = $this->reader()->receipt(self::MESSAGE_ID, now()->subHour()->toDateTimeString());

        $this->assertSame('failed', $state);
        $this->assertStringContainsString('131049', $note);
    }
}
