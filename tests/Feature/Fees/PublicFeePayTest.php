<?php

namespace Tests\Feature\Fees;

use Tests\TestCase;

/**
 * The login-free parent payment API (routes/public_pay.php). These cases only exercise the gates in front of the data,
 * so they read nothing from a real school and can never start a payment.
 */
class PublicFeePayTest extends TestCase
{
    public function test_an_unknown_school_link_is_a_404_with_a_plain_message(): void
    {
        $this->getJson('/api/public-pay/zz-no-such-school-xx')
            ->assertStatus(404)
            ->assertJsonPath('status', 0)
            ->assertJsonPath('message', 'This payment link is not valid. Please ask your school for a new one.');
    }

    public function test_every_action_refuses_an_unknown_school_before_looking_at_the_request(): void
    {
        foreach (['lookup', 'fees', 'pay', 'checkout', 'razorpay/confirm'] as $action) {
            $this->postJson("/api/public-pay/zz-no-such-school-xx/{$action}", ['mobile' => '9876543210', 'student_id' => 1, 'amount' => 1])
                ->assertStatus(404);
        }
    }

    public function test_a_malformed_link_code_never_reaches_the_controller(): void
    {
        $this->getJson('/api/public-pay/'.str_repeat('x', 41))->assertStatus(404);
        $this->getJson('/api/public-pay/' . str_repeat('x', 41))->assertStatus(404);
    }
}
