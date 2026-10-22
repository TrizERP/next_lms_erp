<?php

namespace Tests\Feature;

use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use App\Services\Mcp\SmsGatewayTemplate;
use Tests\TestCase;

/**
 * Send SMS Parents and the assistant send the same request.
 *
 * Both call `send_sms_parents_controller::gatewayUrl()`; this pins what that builds. It matters because libcurl
 * refuses a URL with a space in it (curl error 3, "Malformed input to a URL function"): 24 of the 34 institutes
 * that have a gateway saved keep a signature with spaces in `last_var`, and for them the module's own sender
 * never left the server.
 *
 * READ-ONLY: only builds addresses. Nothing is sent.
 */
class SmsGatewayUrlTest extends TestCase
{
    private const ROW = [
        'url' => 'https://sms.example.test/V2/http-api.php?',
        'pram' => 'apikey=KEY&senderid=SCHOOL&format=json',
        'mobile_var' => '&number=',
        'text_var' => '&message=',
        'last_var' => '.(Generated at CMA)',
    ];

    private function controller(): send_sms_parents_controller
    {
        return app(send_sms_parents_controller::class);
    }

    public function test_the_address_is_the_saved_parts_in_order_with_the_message_encoded(): void
    {
        $url = $this->controller()->gatewayUrl(self::ROW, '9876543210', 'Dear parent, school is closed.');

        $this->assertSame(
            'https://sms.example.test/V2/http-api.php?apikey=KEY&senderid=SCHOOL&format=json&number=9876543210&message=Dear+parent%2C+school+is+closed..(Generated%20at%20CMA)',
            $url
        );
    }

    public function test_a_space_anywhere_in_the_saved_parts_is_written_as_percent_20_so_libcurl_accepts_it(): void
    {
        $url = $this->controller()->gatewayUrl(['pram' => 'a=1 2', 'last_var' => ' - School Name'] + self::ROW, '9876543210', 'hi there');

        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringContainsString('a=1%202', $url);
        $this->assertStringContainsString('%20-%20School%20Name', $url);

        // The proof that matters: libcurl takes this address (it fails to CONNECT, error 7 or 28, not error 3).
        $ch = curl_init(str_replace('https://sms.example.test', 'http://127.0.0.1:9', $url));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 2]);
        curl_exec($ch);
        $this->assertNotSame(3, curl_errno($ch), 'libcurl rejected the address as malformed');
        curl_close($ch);
    }

    public function test_a_template_id_is_placed_between_the_saved_parameters_and_the_mobile(): void
    {
        $url = $this->controller()->gatewayUrl(self::ROW, '9876543210', 'x', '&templateid=1707');

        $this->assertStringContainsString('format=json&templateid=1707&number=9876543210', $url);
    }

    public function test_the_modules_own_sender_and_the_assistants_reply_reading_sender_both_use_it(): void
    {
        $source = file_get_contents((new \ReflectionClass(send_sms_parents_controller::class))->getFileName());

        foreach (['sendSMS', 'sendSMSDetailed'] as $method) {
            $reflection = new \ReflectionMethod(send_sms_parents_controller::class, $method);
            $body = implode('', array_slice(explode("\n", $source), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

            $this->assertStringContainsString('$this->gatewayUrl(', $body, "$method builds the address itself instead of through gatewayUrl()");
        }
    }

    public function test_the_template_the_assistant_shows_is_what_the_address_carries(): void
    {
        $signature = SmsGatewayTemplate::describe(self::ROW);
        $this->assertSame('signature', $signature['kind']);
        $this->assertSame('Hi.(Generated at CMA)', SmsGatewayTemplate::finalText('Hi', $signature));

        $parameter = SmsGatewayTemplate::describe(['last_var' => '&templateid=1707&password=hunter2'] + self::ROW);
        $this->assertSame('gateway_parameter', $parameter['kind']);
        $this->assertStringNotContainsString('hunter2', json_encode($parameter));
        $this->assertSame('Hi', SmsGatewayTemplate::finalText('Hi', $parameter));

        $this->assertSame('none', SmsGatewayTemplate::describe(['last_var' => ''] + self::ROW)['kind']);
    }
}
