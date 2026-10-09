<?php

namespace Tests\Unit\QuestionGeneration;

use App\Http\Controllers\api\lms\IntelligenceQuestionGenerationApiController;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\StubRegistry;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * The HTTP edge of format-driven generation: validation, what reaches the
 * service, and the formats listing.
 *
 * NO DATABASE. The service is a recorder, and the session is an in-memory array
 * store standing in for what `api.session` hydrates from the verified JWT.
 */
class QuestionGenerationControllerTest extends TestCase
{
    /** @var array<string, mixed>|null the payload the controller handed the service */
    private ?array $received = null;

    private function controller(array $result = ['status' => true, 'message' => 'ok', 'data' => []]): IntelligenceQuestionGenerationApiController
    {
        $test = $this;
        $service = new class(new StubRegistry(FakeCatalogue::live()), $test, $result) extends TestableGenerationService {
            public function __construct($registry, private $test, private array $result)
            {
                parent::__construct($registry);
            }

            public ?int $scopedTo = null;

            public function forInstitute(int|string|null $subInstituteId): static
            {
                $this->scopedTo = (int) $subInstituteId;

                return $this;
            }

            public function generate(array $input): array
            {
                $this->test->capture($input);

                return $this->result;
            }
        };

        $this->service = $service;

        return new IntelligenceQuestionGenerationApiController($service);
    }

    public ?object $service = null;

    public function capture(array $input): void
    {
        $this->received = $input;
    }

    private function request(array $body, int $tenant = 7, int $user = 55): Request
    {
        $request = Request::create('/api/intelligence/questions/generate', 'POST', $body);
        $session = new Store('test', new \Illuminate\Session\ArraySessionHandler(10));
        $session->put('sub_institute_id', $tenant);
        $session->put('user_id', $user);
        $request->setLaravelSession($session);

        return $request;
    }

    private function base(array $override = []): array
    {
        return $override + [
            'concept_id' => 123, 'subject_id' => 3, 'standard_id' => 8, 'chapter_id' => 11,
            'total_questions' => 5, 'question_format_code' => 'true_false',
        ];
    }

    public function test_a_format_code_request_reaches_the_service(): void
    {
        $response = $this->controller()->generate($this->request($this->base()));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true_false', $this->received['question_format_code']);
        $this->assertSame(5, $this->received['total_questions']);
    }

    public function test_the_legacy_question_type_alone_is_still_accepted(): void
    {
        $response = $this->controller()->generate($this->request($this->base(['question_format_code' => null, 'question_type' => 'mcq'])));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('mcq', $this->received['question_type']);
    }

    public function test_neither_a_format_nor_a_legacy_type_is_a_validation_error(): void
    {
        $response = $this->controller()->generate($this->request($this->base(['question_format_code' => null])));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('question_type', $response->getData(true)['errors']);
        $this->assertNull($this->received);
    }

    public function test_the_client_question_type_id_never_reaches_the_service(): void
    {
        $this->controller()->generate($this->request($this->base(['question_type_id' => 99, 'question_type' => 'narrative'])));

        $this->assertArrayNotHasKey('question_type_id', $this->received);
    }

    public function test_tenant_and_author_come_from_the_session_not_the_body(): void
    {
        $controller = $this->controller();
        $controller->generate($this->request(
            $this->base(['sub_institute_id' => 999, 'created_by' => 888]),
            tenant: 7,
            user: 55
        ));

        $this->assertSame(7, $this->received['sub_institute_id']);
        $this->assertSame(55, $this->received['created_by']);
        $this->assertSame(7, $this->service->scopedTo, 'the provider key is scoped to the verified school');
    }

    public function test_model_and_temperature_cannot_be_chosen_by_the_caller(): void
    {
        $this->controller()->generate($this->request($this->base(['model' => 'expensive-model', 'temperature' => 2])));

        $this->assertArrayNotHasKey('model', $this->received);
        $this->assertArrayNotHasKey('temperature', $this->received);
    }

    public function test_more_than_fifty_questions_is_a_validation_error(): void
    {
        $response = $this->controller()->generate($this->request($this->base(['total_questions' => 51])));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('total_questions', $response->getData(true)['errors']);
    }

    public function test_fifty_questions_is_allowed(): void
    {
        $response = $this->controller()->generate($this->request($this->base(['total_questions' => 50])));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_a_malformed_format_code_is_rejected_at_the_edge(): void
    {
        foreach (['True/False', 'true false', "true_false'; DROP", str_repeat('a', 49)] as $bad) {
            $response = $this->controller()->generate($this->request($this->base(['question_format_code' => $bad])));

            $this->assertSame(422, $response->getStatusCode(), $bad);
        }
    }

    public function test_the_quota_is_validated_per_row(): void
    {
        $response = $this->controller()->generate($this->request($this->base(['quota' => [
            ['level' => 'Apply', 'count' => 51, 'difficulty' => 'Impossible'],
        ]])));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_quota_difficulty_and_marks_survive_validation(): void
    {
        $this->controller()->generate($this->request($this->base(['quota' => [
            ['level' => 'Apply', 'count' => 3, 'difficulty' => 'Hard', 'points' => 4, 'dok' => 3],
        ]])));

        $this->assertSame(
            [['level' => 'Apply', 'count' => 3, 'difficulty' => 'Hard', 'points' => 4, 'dok' => 3]],
            $this->received['quota']
        );
    }

    public function test_a_tenant_rejection_is_a_403_and_any_other_failure_a_422(): void
    {
        $forbidden = $this->controller(['status' => false, 'message' => 'no', 'code' => 'forbidden', 'data' => []])
            ->generate($this->request($this->base()));
        $this->assertSame(403, $forbidden->getStatusCode());

        $failed = $this->controller(['status' => false, 'message' => 'bad', 'code' => null, 'data' => []])
            ->generate($this->request($this->base()));
        $this->assertSame(422, $failed->getStatusCode());
    }

    public function test_the_formats_endpoint_lists_the_generatable_formats(): void
    {
        $response = $this->controller()->formats();
        $body = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['status']);
        $this->assertSame(50, $body['max_questions']);
        $this->assertContains('mcq', array_column($body['data'], 'code'));
        $this->assertNotContains('narrative', array_column($body['data'], 'code'));
    }

    // ---- several formats ---------------------------------------------------

    public function test_several_format_codes_reach_the_service_without_a_legacy_type(): void
    {
        $response = $this->controller()->generate($this->request($this->base([
            'question_format_code' => null,
            'question_format_codes' => ['mcq', 'fill_blank', 'numerical'],
            'question_type_id' => 99,
        ])));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['mcq', 'fill_blank', 'numerical'], $this->received['question_format_codes']);
        $this->assertArrayNotHasKey('question_type_id', $this->received);
        $this->assertSame(7, $this->received['sub_institute_id'], 'tenant still comes from the session');
    }

    public function test_an_empty_selection_is_a_validation_error(): void
    {
        $response = $this->controller()->generate($this->request($this->base([
            'question_format_code' => null,
            'question_format_codes' => [],
        ])));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull($this->received);
    }

    public function test_duplicate_or_malformed_codes_are_rejected_at_the_edge(): void
    {
        foreach ([['mcq', 'mcq'], ['mcq', 'True/False'], ['mcq', ''], ['mcq', str_repeat('a', 49)], [['mcq']]] as $codes) {
            $response = $this->controller()->generate($this->request($this->base([
                'question_format_code' => null,
                'question_format_codes' => $codes,
            ])));

            $this->assertSame(422, $response->getStatusCode(), json_encode($codes));
        }
    }

    public function test_the_selection_ceiling_is_the_number_of_known_formats_not_a_constant(): void
    {
        $controller = $this->controller();
        $known = count($this->service->formats()->all());

        $tooMany = array_map(fn ($i) => "format_{$i}", range(1, $known + 1));
        $response = $controller->generate($this->request($this->base([
            'question_format_code' => null,
            'question_format_codes' => $tooMany,
        ])));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('question_format_codes', $response->getData(true)['errors']);
    }

    public function test_the_array_does_not_need_the_legacy_question_type(): void
    {
        $response = $this->controller()->generate($this->request($this->base([
            'question_format_code' => null,
            'question_format_codes' => ['true_false'],
        ])));

        $this->assertSame(200, $response->getStatusCode());
    }
}
