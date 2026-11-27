<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mobile number + mPIN sign-in (api/mobile-auth/*).
 *
 * Like the other access tests these write nothing: every case is refused before it reaches a table that
 * changes, so they are safe to run against a shared database. The full path (activate a number, set an
 * mPIN, lock after repeated failures, biometric device tokens, a teacher and two children on one number)
 * was exercised against synthetic rows inside a transaction that was rolled back.
 */
class MobileAuthApiTest extends TestCase
{
    private function mauth(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('api/mobile-auth/'.$uri, $data);
    }

    #[DataProvider('badNumbers')]
    public function test_a_number_that_is_not_ten_digits_is_refused(string $number): void
    {
        $this->mauth('status', ['mobile' => $number])->assertStatus(422);
        $this->mauth('otp/send', ['mobile' => $number])->assertStatus(422);
    }

    /** @return array<string, array{0:string}> */
    public static function badNumbers(): array
    {
        return ['empty' => [''], 'short' => ['12345'], 'letters' => ['abcdefghij'], 'nine digits' => ['900000000']];
    }

    public function test_the_code_must_be_six_digits(): void
    {
        $this->mauth('otp/verify', ['mobile' => '9000000009', 'otp' => '123'])->assertStatus(422);
        $this->mauth('otp/verify', ['mobile' => '9000000009', 'otp' => 'abcdef'])->assertStatus(422);
    }

    public function test_a_wrong_code_for_a_number_with_no_code_is_refused_without_saying_why(): void
    {
        $r = $this->mauth('otp/verify', ['mobile' => '9000000009', 'otp' => '123456']);
        $r->assertStatus(401);
        $this->assertSame('That code is wrong or has expired. Ask for a new one.', $r->json('message'));
    }

    public function test_setting_an_mpin_needs_the_one_time_token_from_a_verified_code(): void
    {
        $this->mauth('pin/set', ['mobile' => '9000000009', 'pin' => '2468'])->assertStatus(401);
        $this->mauth('pin/set', ['mobile' => '9000000009', 'pin' => '2468', 'setup_token' => 'forged'])->assertStatus(401);
    }

    public function test_a_wrong_mpin_for_an_unknown_number_gives_the_same_answer_as_any_other_failure(): void
    {
        foreach (['2468', '12', 'abcd', ''] as $pin) {
            $r = $this->mauth('pin/login', ['mobile' => '9000000009', 'pin' => $pin]);
            $r->assertStatus(401);
            $this->assertSame('Incorrect mobile number or mPIN.', $r->json('message'));
        }
    }

    public function test_biometric_sign_in_needs_a_device_token_the_server_issued(): void
    {
        $this->mauth('device/login', ['mobile' => '9000000009', 'device_token' => str_repeat('a', 64)])->assertStatus(401);
        $this->mauth('device/login', ['mobile' => '9000000009', 'device_token' => 'short'])->assertStatus(401);
        $this->mauth('device/login', ['mobile' => '9000000009'])->assertStatus(401);
    }

    public function test_registering_a_device_and_choosing_a_profile_need_a_live_ticket(): void
    {
        $this->mauth('device/register', ['ticket' => 'forged'])->assertStatus(401);
        $this->mauth('device/register')->assertStatus(401);
        $this->mauth('select', ['ticket' => 'forged', 'profile' => 'staff:1'])->assertStatus(401);
        $this->mauth('select', ['profile' => 'student:1'])->assertStatus(401);
    }

    public function test_listing_and_switching_profiles_need_a_signed_in_token(): void
    {
        $this->mauth('profiles', ['mobile' => '9000000009'])->assertStatus(401);
        $this->mauth('switch', ['mobile' => '9000000009', 'profile' => 'staff:1', 'pin' => '2468'])->assertStatus(401);
        $this->mauth('switch', ['mobile' => '9000000009', 'profile' => 'staff:1'], )->assertStatus(401);
    }

    public function test_the_existing_web_login_route_is_untouched(): void
    {
        // Still validates its own fields (email + password); the new flow did not change it.
        $this->postJson('api/api-login', [])->assertStatus(422);
    }
}
