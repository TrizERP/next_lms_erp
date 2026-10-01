<?php

namespace Tests\Unit;

use App\Services\QuestionGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Facade;

class H5PGenerationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository());
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }
    public function test_catalog_drives_counts_and_unsupported_types_are_skipped(): void
    {
        Schema::shouldReceive('hasTable')->with('question_type_catalog')->andReturn(true);
        Schema::shouldReceive('hasColumn')->with('lms_question_master', 'question_format_code')->andReturn(true);
        $catalog = Mockery::mock();
        $catalog->shouldReceive('where')->with('status', 1)->andReturnSelf();
        $catalog->shouldReceive('orderBy')->with('id')->andReturnSelf();
        $catalog->shouldReceive('get')->andReturn(collect([
            (object) ['id' => 91, 'code' => 'true_false', 'label' => 'True or false'],
            (object) ['id' => 92, 'code' => 'fill_blank', 'label' => 'Blank'],
            (object) ['id' => 93, 'code' => 'proof', 'label' => 'Proof'],
        ]));
        DB::shouldReceive('table')->with('question_type_catalog')->andReturn($catalog);
        $grading = Mockery::mock();
        $grading->shouldReceive('where')->with('status', 1)->andReturnSelf();
        $grading->shouldReceive('get')->andReturn(collect([(object) ['id' => 2, 'question_type' => 'Narrative']]));
        DB::shouldReceive('table')->with('question_type_master')->andReturn($grading);
        DB::shouldReceive('transaction')->twice()->andReturnUsing(fn ($callback) => $callback());

        $service = new class extends QuestionGenerationService {
            private int $sequence = 0;
            protected function loadConceptSlice(int $conceptId, $subInstituteId, ?int $chapterId = null, ?int $subjectId = null, ?int $standardId = null): array
            {
                return ['found' => true, 'concept' => (object) ['id' => 10, 'name' => 'Numbers', 'sub_institute_id' => 7], 'chapter' => null, 'intel' => null];
            }
            protected function buildDedupCorpus(int $conceptId, int $questionTypeId, int $limit = 200): array { return []; }
            protected function callDeepSeek(string $system, string $user, array $opts): array
            {
                $request = json_decode($user, true);
                $i = ++$this->sequence;
                return ['ok' => true, 'content' => json_encode(['rows' => [[
                    'question_title' => "Question {$i}: the number is ___",
                    'answer' => ['model_answer' => $request['catalog_code'] === 'true_false' ? 'False' : 'two'],
                ]]])];
            }
            protected function persist(array $resp, string $type, array $ctx, array $meta): array
            {
                // Catalogue IDs must never leak into the grading-engine FK.
                if ($ctx['question_type_id'] !== 2) { throw new \RuntimeException('Wrong grading ID'); }
                $ids = range($this->sequence - count($resp['rows']) + 1, $this->sequence);
                return ['inserted' => count($resp['rows']), 'ids' => $ids, 'questions' => $resp['rows']];
            }
        };
        $result = $service->generate(['concept_id' => 10, 'sub_institute_id' => 7, 'question_type' => 'all', 'questions_per_type' => 4]);
        $this->assertTrue($result['status']);
        $this->assertSame(8, $result['data']['inserted']);
        $this->assertSame(4, $result['data']['types']['true_false']['inserted']);
        $this->assertArrayHasKey('proof', $result['data']['skipped_types']);
        $this->assertFalse($result['data']['underfilled']);
    }
}
