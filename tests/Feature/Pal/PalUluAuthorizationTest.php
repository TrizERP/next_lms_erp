<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 13/16 fix: the entire ULU (Unified
 * Learning Unit) CRUD/moderation surface -- create, update, delete,
 * duplicate, archive, approve -- had no role check at all. None of these
 * routes carry a {learnerId} for pal.auth's ownership scoping to apply to,
 * so any authenticated caller, including a plain student, could approve or
 * delete published ULU content. This directly broke the "AI proposes ->
 * human verifies -> approved" workflow the content model depends on.
 */
class PalUluAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function tokenFor(int $userId, bool $isStudent): string
    {
        return JWT::encode([
            'id' => $userId,
            'sub_institute_id' => 1,
            'is_admin' => $isStudent ? 0 : 1,
            'is_student' => $isStudent,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function uluId(): int
    {
        return (int) DB::table('pal_unified_learning_units')->insertGetId([
            'ulu_id' => 'TEST-ULU-' . random_int(100000, 999999),
            'title' => 'Test ULU ' . random_int(1000, 9999),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A ULU that satisfies ULUService::assertQuality()'s approval gate --
     * every field it requires present, plus all 5 qa_checks passed. Approval
     * genuinely enforces this now (real data, real validation), so a bare
     * title/status stub is only good enough for the role-check-only tests
     * (student blocked / archive / delete), not for proving a teacher CAN
     * approve a well-formed unit.
     */
    private function approvableUluId(): int
    {
        $ulu = \App\Models\PAL\UnifiedLearningUnit::create([
            'ulu_id' => 'TEST-ULU-' . random_int(100000, 999999),
            'title' => 'Approvable Test ULU ' . random_int(1000, 9999),
            'status' => 'draft',
            'grade' => 6,
            'subject' => 'Science',
            'academic_concept' => 'Test Concept',
            'casel_domain' => 'self_awareness',
            'ngss_practice' => 'asking_questions',
            'ncdg_goal' => 'PS1',
            'riasec_signal' => 'I',
            'career_cluster' => 'STEM',
            'real_skill_name' => 'Test Skill',
            'scenario' => [
                'context' => 'A real-world context.',
                'academic_hook' => 'An academic hook.',
                'decision_point' => 'A decision point.',
                'reflection' => 'A reflection prompt.',
            ],
            'branches' => [
                ['label' => 'Path A', 'outcome' => 'Outcome A'],
                ['label' => 'Path B', 'outcome' => 'Outcome B'],
            ],
            'reflections' => [
                'stream' => 'Stream-level reflection.',
                'mountain' => 'Mountain-level reflection.',
                'sky' => 'Sky-level reflection.',
            ],
            'qa_checks' => [
                'academic' => true,
                'sel' => true,
                'india' => true,
                'career' => true,
                'decision' => true,
            ],
        ]);

        return $ulu->id;
    }

    public function test_student_cannot_create_a_ulu(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenFor(1, true)])
            ->postJson('/api/pal/ulu', ['title' => 'Student-authored ULU']);

        $response->assertStatus(403);
    }

    public function test_student_cannot_approve_a_ulu(): void
    {
        $id = $this->uluId();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenFor(1, true)])
            ->postJson("/api/pal/ulu/{$id}/approve");

        $response->assertStatus(403);
    }

    public function test_student_cannot_archive_or_delete_a_ulu(): void
    {
        $token = $this->tokenFor(1, true);

        $archiveId = $this->uluId();
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/pal/ulu/{$archiveId}/archive")
            ->assertStatus(403);

        $deleteId = $this->uluId();
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/pal/ulu/{$deleteId}")
            ->assertStatus(403);
    }

    public function test_teacher_can_approve_a_ulu(): void
    {
        $id = $this->approvableUluId();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenFor(2, false)])
            ->postJson("/api/pal/ulu/{$id}/approve");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
