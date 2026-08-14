<?php

namespace App\Services\PAL\ULU;

use App\Models\PAL\UnifiedLearningUnit;
use App\Services\PAL\Framework\FrameworkCatalogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ULUService
{
    public function __construct(
        private readonly FrameworkCatalogService $catalog,
        private readonly ULUGraphService $graph
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = UnifiedLearningUnit::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('ulu_id', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('academic_concept', 'like', "%{$search}%")
                    ->orWhere('career_cluster', 'like', "%{$search}%")
                    ->orWhere('real_skill_name', 'like', "%{$search}%");
            });
        }

        foreach ([
            'grade',
            'subject',
            'academic_concept',
            'casel_domain',
            'ngss_practice',
            'career_cluster',
            'riasec_signal',
            'h5p_type',
            'cultural_context',
            'language',
            'status',
            'difficulty',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'updated_at');
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['title', 'grade', 'subject', 'difficulty', 'duration_minutes', 'status', 'created_at', 'updated_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'updated_at';
        }

        return $query->orderBy($sortBy, $sortDirection)->paginate((int) ($filters['per_page'] ?? 10));
    }

    public function create(array $payload): UnifiedLearningUnit
    {
        $normalized = $this->normalizePayload($payload);
        $this->assertQuality($normalized, false);

        $ulu = DB::transaction(function () use ($normalized) {
            return UnifiedLearningUnit::create($normalized);
        });

        $this->syncGraph($ulu);

        return $ulu->fresh();
    }

    public function update(UnifiedLearningUnit $ulu, array $payload): UnifiedLearningUnit
    {
        $normalized = $this->normalizePayload(array_merge($ulu->toArray(), $payload), $ulu);
        $this->assertQuality($normalized, ($normalized['status'] ?? 'draft') === 'approved');

        DB::transaction(function () use ($ulu, $normalized) {
            $ulu->fill($normalized);
            $ulu->save();
        });

        $this->syncGraph($ulu);

        return $ulu->fresh();
    }

    public function delete(UnifiedLearningUnit $ulu): void
    {
        $ulu->delete();
    }

    public function duplicate(UnifiedLearningUnit $ulu): UnifiedLearningUnit
    {
        $copy = $ulu->replicate();
        $copy->ulu_id = $this->nextUluId($ulu->subject, $ulu->grade, $ulu->academic_concept);
        $copy->title = $ulu->title . ' (Copy)';
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->archived_at = null;
        $copy->graph_sync_status = 'pending';
        $copy->save();

        return $copy->fresh();
    }

    public function archive(UnifiedLearningUnit $ulu): UnifiedLearningUnit
    {
        $ulu->status = 'archived';
        $ulu->archived_at = now();
        $ulu->save();

        return $ulu->fresh();
    }

    public function approve(UnifiedLearningUnit $ulu): UnifiedLearningUnit
    {
        $data = $ulu->toArray();
        $data['status'] = 'approved';
        $this->assertQuality($data, true);

        $ulu->status = 'approved';
        $ulu->published_at = now();
        $ulu->save();

        $this->syncGraph($ulu);

        return $ulu->fresh();
    }

    public function preview(UnifiedLearningUnit $ulu): array
    {
        return [
            'ulu_id' => $ulu->ulu_id,
            'title' => $ulu->title,
            'student_journey' => [
                'context' => data_get($ulu->scenario, 'context'),
                'academic_hook' => data_get($ulu->scenario, 'academic_hook'),
                'decision_point' => data_get($ulu->scenario, 'decision_point'),
                'paths' => $ulu->branches ?? [],
                'reflection' => $ulu->reflections ?? [],
                'career_signal' => [
                    'cluster' => $ulu->career_cluster,
                    'riasec' => $ulu->riasec_signal,
                    'ncdg_goal' => $ulu->ncdg_goal,
                    'domain_exposure' => data_get($ulu->career_layer, 'domain_exposure'),
                ],
                'completion' => [
                    'real_skill' => $ulu->real_skill_name,
                    'pedagogy_tag' => $ulu->pedagogy_tag,
                    'h5p_type' => $ulu->h5p_type,
                ],
            ],
        ];
    }

    public function analytics(UnifiedLearningUnit $ulu): array
    {
        $analytics = (array) ($ulu->analytics ?? []);
        $flags = (array) ($ulu->optimization_flags ?? []);

        return [
            'ulu_id' => $ulu->ulu_id,
            'analytics' => $analytics,
            'optimization_flags' => $flags,
            'recommendations' => $this->optimizationRecommendations($analytics),
        ];
    }

    public function stats(): array
    {
        return [
            'total' => UnifiedLearningUnit::count(),
            'approved' => UnifiedLearningUnit::where('status', 'approved')->count(),
            'draft' => UnifiedLearningUnit::where('status', 'draft')->count(),
            'review' => UnifiedLearningUnit::where('status', 'review')->count(),
            'archived' => UnifiedLearningUnit::where('status', 'archived')->count(),
        ];
    }

    public function seedDefaults(): void
    {
        $examples = [
            [
                'title' => "Ravi's Bank Decision",
                'grade' => 7,
                'subject' => 'Mathematics',
                'academic_concept' => 'MATH_PERCENTAGE',
                'sub_concept' => 'Percentage increase/decrease',
                'status' => 'approved',
                'difficulty' => 3,
                'duration_minutes' => 12,
                'language' => 'en',
                'cultural_context' => 'urban_market',
                'social_mode' => 'individual',
                'pedagogy_tag' => 'scenario_based',
                'h5p_type' => 'branching_scenario',
                'casel_domain' => 'responsible_decision_making',
                'ngss_practice' => 'math_computation',
                'ncdg_goal' => 'CM2',
                'riasec_signal' => 'C',
                'career_cluster' => 'business_finance',
                'real_skill_name' => 'Financial literacy',
                'mastery_gate' => 0.70,
                'academic_core' => [
                    'concept_id' => 'MATH_PERCENTAGE',
                    'bloom_level' => 'apply',
                    'ncert_cg' => 'CG-8',
                    'misconception_tags' => ['percentage_vs_interest'],
                ],
                'sel_layer' => [
                    'casel_indicator' => 'evaluate_consequences_before_deciding',
                    'hpc_lens' => 'Sensitivity',
                    'hpc_level_signal' => 'mountain',
                ],
                'stem_layer' => [
                    'cross_subject' => 'Economics',
                    'skill' => 'numerical_reasoning',
                    'data_interpretation_required' => true,
                ],
                'career_layer' => [
                    'domain_exposure' => 'banking',
                    'gardner_intelligence' => 'logical_mathematical',
                    'career_title' => 'Financial Advisor',
                ],
                'real_skill' => [
                    'nsqf_relevance' => 'BFSI Level 1',
                    'soft_skill_signal' => 'self_regulation',
                    'p21_skill' => 'critical_thinking',
                ],
                'scenario' => [
                    'context' => "Ravi's family wants to open a savings account. Bank A offers 4% interest per year. Bank B offers 3.5% with no minimum balance.",
                    'academic_hook' => 'Which bank gives more interest in one year? Calculate for Ravi.',
                    'decision_point' => "Ravi's family actually keeps withdrawing from savings. Which bank should they choose and why?",
                    'reflection' => "If you were advising Ravi's family, what would you tell them?",
                ],
                'branches' => [
                    ['key' => 'path_a', 'label' => 'Higher interest bank', 'choice' => 'Pick the 4% bank', 'consequence' => 'Penalty wipes out the interest advantage.', 'feedback' => 'You calculated correctly but missed the minimum balance rule.', 'career_signal' => 'Financial Advisor'],
                    ['key' => 'path_b', 'label' => 'Check withdrawal behavior', 'choice' => 'Ask about minimum balance', 'consequence' => 'The family avoids penalties and chooses the right product.', 'feedback' => 'Good thinking. You considered the full real-world context.', 'career_signal' => 'Financial Advisor'],
                ],
                'reflections' => [
                    'stream' => 'What did you choose and why?',
                    'mountain' => 'What would you do differently now?',
                    'sky' => 'What three questions should someone ask before choosing a bank account?',
                ],
                'delivery' => [
                    'language' => 'en',
                    'duration_minutes' => 12,
                    'social_mode' => 'individual',
                ],
                'qa_checks' => ['academic' => true, 'sel' => true, 'india' => true, 'career' => true, 'decision' => true],
                'analytics' => [
                    'total_starts' => 2847,
                    'completion_rate' => 0.84,
                    'avg_time_minutes' => 11.3,
                    'target_time_minutes' => 12,
                    'path_distribution' => ['path_a_wrong_choice' => 0.54, 'path_b_correct' => 0.31, 'path_c_partial' => 0.15],
                    'avg_attempts_to_correct' => 1.8,
                    'mastery_post_ulu' => 0.67,
                    'mastery_target' => 0.70,
                    'sel_reflection_completion' => 0.61,
                    'career_signal_registered' => 0.89,
                    'teacher_overrides' => 3,
                    'confusion_flags' => 12,
                ],
                'optimization_flags' => ['calculation_too_hard' => true, 'reflection_too_abstract' => true, 'path_a_too_common' => true],
                'cross_domain_links' => [
                    ['relation' => 'TEACHES', 'target' => 'MATH_PERCENTAGE'],
                    ['relation' => 'DEVELOPS', 'target' => 'responsible_decision_making'],
                    ['relation' => 'EXERCISES', 'target' => 'math_computation'],
                    ['relation' => 'SIGNALS_CAREER', 'target' => 'business_finance'],
                    ['relation' => 'EVIDENCES', 'target' => 'CM2'],
                ],
            ],
            [
                'title' => 'Kabaddi Force Clash',
                'grade' => 6,
                'subject' => 'Science',
                'academic_concept' => 'SCI_FORCE_MOTION',
                'sub_concept' => "Newton's laws and force diagrams",
                'status' => 'review',
                'difficulty' => 3,
                'duration_minutes' => 14,
                'language' => 'en',
                'cultural_context' => 'sports_ground',
                'social_mode' => 'small_group',
                'pedagogy_tag' => 'concept_sports',
                'h5p_type' => 'interactive_video',
                'casel_domain' => 'social_awareness',
                'ngss_practice' => 'developing_models',
                'ncdg_goal' => 'CM2',
                'riasec_signal' => 'R',
                'career_cluster' => 'sports_science',
                'real_skill_name' => 'Physical literacy',
                'mastery_gate' => 0.70,
                'academic_core' => ['concept_id' => 'SCI_FORCE', 'bloom_level' => 'apply', 'ncert_cg' => 'CG-7'],
                'sel_layer' => ['casel_indicator' => 'understand_team_needs', 'hpc_lens' => 'Sensitivity', 'hpc_level_signal' => 'mountain'],
                'stem_layer' => ['cross_subject' => 'Physical Education', 'skill' => 'force_modeling', 'data_interpretation_required' => false],
                'career_layer' => ['domain_exposure' => 'sports_science', 'gardner_intelligence' => 'bodily_kinesthetic', 'career_title' => 'Sports Scientist'],
                'real_skill' => ['nsqf_relevance' => 'Sports Level 1', 'soft_skill_signal' => 'teamwork', 'p21_skill' => 'collaboration'],
                'scenario' => ['context' => 'A kabaddi raider runs into the defensive line.', 'academic_hook' => 'Why do more defenders stopping a heavier player need more force?', 'decision_point' => 'Should the team spread out or absorb the impact together?', 'reflection' => 'How does teamwork change the science of force?'],
                'branches' => [['key' => 'path_a', 'label' => 'One defender', 'choice' => 'Rely on one strong player', 'consequence' => 'The raider breaks through.', 'feedback' => 'Force is not balanced well enough.', 'career_signal' => 'Sports Scientist'], ['key' => 'path_b', 'label' => 'Coordinated defense', 'choice' => 'Use coordinated defenders', 'consequence' => 'The team distributes force and succeeds.', 'feedback' => 'Your model matches the physics and the tactic.', 'career_signal' => 'Sports Analyst']],
                'reflections' => ['stream' => 'What happened in your chosen path?', 'mountain' => 'Why was that choice stronger or weaker?', 'sky' => 'How would you coach the team before the next raid?'],
                'delivery' => ['language' => 'en', 'duration_minutes' => 14, 'social_mode' => 'small_group'],
                'qa_checks' => ['academic' => true, 'sel' => true, 'india' => true, 'career' => true, 'decision' => true],
                'analytics' => ['total_starts' => 1320, 'completion_rate' => 0.81, 'avg_time_minutes' => 12.5, 'target_time_minutes' => 14, 'path_distribution' => ['path_a_wrong_choice' => 0.43, 'path_b_correct' => 0.45], 'avg_attempts_to_correct' => 1.5, 'mastery_post_ulu' => 0.72, 'mastery_target' => 0.70, 'sel_reflection_completion' => 0.66, 'career_signal_registered' => 0.86, 'teacher_overrides' => 1, 'confusion_flags' => 5],
                'optimization_flags' => ['calculation_too_hard' => false, 'reflection_too_abstract' => false, 'path_a_too_common' => false],
                'cross_domain_links' => [['relation' => 'TEACHES', 'target' => 'SCI_FORCE'], ['relation' => 'DEVELOPS', 'target' => 'social_awareness'], ['relation' => 'EXERCISES', 'target' => 'developing_models'], ['relation' => 'SIGNALS_CAREER', 'target' => 'sports_science']],
            ],
            [
                'title' => 'Village Water Story Lab',
                'grade' => 5,
                'subject' => 'Language & Literacy',
                'academic_concept' => 'LANG_NARRATIVE_WRITING',
                'sub_concept' => 'Narrative structure and descriptive vocabulary',
                'status' => 'approved',
                'difficulty' => 2,
                'duration_minutes' => 15,
                'language' => 'en',
                'cultural_context' => 'rural_village',
                'social_mode' => 'individual',
                'pedagogy_tag' => 'storytelling',
                'h5p_type' => 'documentation_tool',
                'casel_domain' => 'social_awareness',
                'ngss_practice' => 'communication',
                'ncdg_goal' => 'CM2',
                'riasec_signal' => 'S',
                'career_cluster' => 'journalism_social_work',
                'real_skill_name' => 'Communication',
                'mastery_gate' => 0.7,
                'academic_core' => [
                    'concept_id' => 'LANG_NARRATIVE_WRITING',
                    'bloom_level' => 'create',
                    'ncert_cg' => 'CG-4',
                    'misconception_tags' => ['missing_conflict', 'weak_resolution'],
                ],
                'sel_layer' => [
                    'casel_indicator' => 'understand_community_challenges',
                    'hpc_lens' => 'Awareness',
                    'hpc_level_signal' => 'mountain',
                ],
                'stem_layer' => [
                    'cross_subject' => 'Environmental Science',
                    'skill' => 'fact_based_communication',
                    'data_interpretation_required' => false,
                ],
                'career_layer' => [
                    'domain_exposure' => 'journalism',
                    'gardner_intelligence' => 'linguistic',
                    'career_title' => 'Journalist',
                ],
                'real_skill' => [
                    'skill_name' => 'Communication',
                    'nsqf_relevance' => 'Media Level 1',
                    'soft_skill_signal' => 'empathy',
                    'p21_skill' => 'communication',
                ],
                'scenario' => [
                    'context' => "A young girl discovers her village's water source is drying up and must decide how to respond.",
                    'academic_hook' => 'Write a story with character, setting, conflict, and resolution.',
                    'decision_point' => 'Who will she ask for help, and how will she persuade the village to act?',
                    'reflection' => 'How can strong storytelling help communities solve real problems?',
                ],
                'branches' => [
                    ['key' => 'path_a', 'label' => 'Silent worry', 'choice' => 'Keep the problem private', 'consequence' => 'The conflict grows and the story lacks resolution.', 'feedback' => 'Your character notices the issue, but the narrative needs action and community response.', 'career_signal' => 'Journalist'],
                    ['key' => 'path_b', 'label' => 'Build alliances', 'choice' => 'Speak to elders, friends, and the teacher', 'consequence' => 'The character gathers support and the story resolves with action.', 'feedback' => 'This path strengthens both narrative structure and empathy.', 'career_signal' => 'Social Worker'],
                ],
                'reflections' => [
                    'stream' => 'What problem did your character face?',
                    'mountain' => 'Why did your character choose that response?',
                    'sky' => 'How would you rewrite the ending if village leaders disagreed?',
                ],
                'delivery' => [
                    'language' => 'en',
                    'duration_minutes' => 15,
                    'social_mode' => 'individual',
                ],
                'qa_checks' => ['academic' => true, 'sel' => true, 'india' => true, 'career' => true, 'decision' => true],
                'analytics' => [
                    'total_starts' => 1186,
                    'completion_rate' => 0.87,
                    'avg_time_minutes' => 13.1,
                    'target_time_minutes' => 15,
                    'path_distribution' => ['path_a_wrong_choice' => 0.32, 'path_b_correct' => 0.56],
                    'avg_attempts_to_correct' => 1.2,
                    'mastery_post_ulu' => 0.74,
                    'mastery_target' => 0.70,
                    'sel_reflection_completion' => 0.72,
                    'career_signal_registered' => 0.83,
                    'teacher_overrides' => 0,
                    'confusion_flags' => 4,
                ],
                'optimization_flags' => ['calculation_too_hard' => false, 'reflection_too_abstract' => false, 'path_a_too_common' => false],
                'cross_domain_links' => [
                    ['relation' => 'TEACHES', 'target' => 'LANG_NARRATIVE_WRITING'],
                    ['relation' => 'DEVELOPS', 'target' => 'social_awareness'],
                    ['relation' => 'EXERCISES', 'target' => 'communication'],
                    ['relation' => 'SIGNALS_CAREER', 'target' => 'journalism_social_work'],
                ],
            ],
            [
                'title' => 'Meena Public Hearing',
                'grade' => 8,
                'subject' => 'Social Studies',
                'academic_concept' => 'CIVICS_RIGHTS_DEMOCRACY',
                'sub_concept' => 'Fundamental rights, public hearings, and argument from evidence',
                'status' => 'review',
                'difficulty' => 4,
                'duration_minutes' => 16,
                'language' => 'en',
                'cultural_context' => 'village_industry',
                'social_mode' => 'small_group',
                'pedagogy_tag' => 'scenario_based',
                'h5p_type' => 'branching_scenario',
                'casel_domain' => 'responsible_decision_making',
                'ngss_practice' => 'argumentation',
                'ncdg_goal' => 'CM2',
                'riasec_signal' => 'E',
                'career_cluster' => 'law_policy_journalism',
                'real_skill_name' => 'Civic literacy',
                'mastery_gate' => 0.72,
                'academic_core' => [
                    'concept_id' => 'CIVICS_RIGHTS_DEMOCRACY',
                    'bloom_level' => 'evaluate',
                    'ncert_cg' => 'CG-10',
                ],
                'sel_layer' => [
                    'casel_indicator' => 'weigh_competing_needs',
                    'hpc_lens' => 'Sensitivity',
                    'hpc_level_signal' => 'sky',
                ],
                'stem_layer' => [
                    'cross_subject' => 'Environmental Science',
                    'skill' => 'evidence_based_argument',
                    'data_interpretation_required' => true,
                ],
                'career_layer' => [
                    'domain_exposure' => 'policy_and_law',
                    'gardner_intelligence' => 'interpersonal',
                    'career_title' => 'Policy Analyst',
                ],
                'real_skill' => [
                    'skill_name' => 'Civic literacy',
                    'nsqf_relevance' => 'Governance Level 1',
                    'soft_skill_signal' => 'judgment',
                    'p21_skill' => 'critical_thinking',
                ],
                'scenario' => [
                    'context' => 'A factory promises 200 jobs in Meena’s village but may pollute the river the community depends on.',
                    'academic_hook' => 'Which constitutional rights and civic processes apply to this hearing?',
                    'decision_point' => 'What should happen at the public hearing, and what evidence should each stakeholder bring?',
                    'reflection' => 'How do we balance jobs, health, and rights in a democracy?',
                ],
                'branches' => [
                    ['key' => 'path_a', 'label' => 'Jobs only', 'choice' => 'Approve the factory immediately', 'consequence' => 'The village gains jobs but ignores environmental and health rights.', 'feedback' => 'You identified one stakeholder benefit, but missed the full constitutional trade-off.', 'career_signal' => 'Journalist'],
                    ['key' => 'path_b', 'label' => 'Evidence hearing', 'choice' => 'Hold a full public review with environmental safeguards', 'consequence' => 'Students compare rights, jobs, and evidence before deciding.', 'feedback' => 'This path models strong democratic decision-making.', 'career_signal' => 'Policy Analyst'],
                ],
                'reflections' => [
                    'stream' => 'Which side did you support first?',
                    'mountain' => 'What evidence changed or strengthened your view?',
                    'sky' => 'How would you design a fair village decision process?',
                ],
                'delivery' => [
                    'language' => 'en',
                    'duration_minutes' => 16,
                    'social_mode' => 'small_group',
                ],
                'qa_checks' => ['academic' => true, 'sel' => true, 'india' => true, 'career' => true, 'decision' => true],
                'analytics' => [
                    'total_starts' => 902,
                    'completion_rate' => 0.78,
                    'avg_time_minutes' => 14.8,
                    'target_time_minutes' => 16,
                    'path_distribution' => ['path_a_wrong_choice' => 0.41, 'path_b_correct' => 0.39, 'path_c_partial' => 0.20],
                    'avg_attempts_to_correct' => 1.9,
                    'mastery_post_ulu' => 0.69,
                    'mastery_target' => 0.72,
                    'sel_reflection_completion' => 0.64,
                    'career_signal_registered' => 0.81,
                    'teacher_overrides' => 2,
                    'confusion_flags' => 10,
                ],
                'optimization_flags' => ['calculation_too_hard' => false, 'reflection_too_abstract' => false, 'path_a_too_common' => false],
                'cross_domain_links' => [
                    ['relation' => 'TEACHES', 'target' => 'CIVICS_RIGHTS_DEMOCRACY'],
                    ['relation' => 'DEVELOPS', 'target' => 'responsible_decision_making'],
                    ['relation' => 'EXERCISES', 'target' => 'argumentation'],
                    ['relation' => 'SIGNALS_CAREER', 'target' => 'law_policy_journalism'],
                ],
            ],
            [
                'title' => 'Cricket Selection Data Call',
                'grade' => 6,
                'subject' => 'Mathematics',
                'academic_concept' => 'MATH_STATISTICS',
                'sub_concept' => 'Mean, median, mode, graphs, and data interpretation',
                'status' => 'approved',
                'difficulty' => 3,
                'duration_minutes' => 13,
                'language' => 'en',
                'cultural_context' => 'school_cricket',
                'social_mode' => 'pair',
                'pedagogy_tag' => 'data_driven',
                'h5p_type' => 'chart',
                'casel_domain' => 'responsible_decision_making',
                'ngss_practice' => 'data_analysis',
                'ncdg_goal' => 'EDL1',
                'riasec_signal' => 'I',
                'career_cluster' => 'sports_analytics',
                'real_skill_name' => 'Data literacy',
                'mastery_gate' => 0.70,
                'academic_core' => [
                    'concept_id' => 'MATH_STATISTICS',
                    'bloom_level' => 'analyze',
                    'ncert_cg' => 'CG-8',
                ],
                'sel_layer' => [
                    'casel_indicator' => 'use_data_over_favouritism',
                    'hpc_lens' => 'Sensitivity',
                    'hpc_level_signal' => 'mountain',
                ],
                'stem_layer' => [
                    'cross_subject' => 'Sports',
                    'skill' => 'data_analysis',
                    'data_interpretation_required' => true,
                ],
                'career_layer' => [
                    'domain_exposure' => 'sports_analytics',
                    'gardner_intelligence' => 'logical_mathematical',
                    'career_title' => 'Sports Analyst',
                ],
                'real_skill' => [
                    'skill_name' => 'Data literacy',
                    'nsqf_relevance' => 'Analytics Level 1',
                    'soft_skill_signal' => 'fairness',
                    'p21_skill' => 'critical_thinking',
                ],
                'scenario' => [
                    'context' => 'Your school cricket team must choose the opening batter using match score data from the last eight games.',
                    'academic_hook' => 'Build the graph and calculate mean, median, and mode for the players.',
                    'decision_point' => 'Who should open the batting, and how do you explain the choice fairly to teammates?',
                    'reflection' => 'What happens when a selector ignores the data because of favouritism?',
                ],
                'branches' => [
                    ['key' => 'path_a', 'label' => 'Pick the captain’s favorite', 'choice' => 'Ignore the averages and consistency', 'consequence' => 'The decision feels unfair and the team loses trust.', 'feedback' => 'This path shows why data matters in group decisions.', 'career_signal' => 'Journalist'],
                    ['key' => 'path_b', 'label' => 'Use the score data', 'choice' => 'Choose the most consistent batter', 'consequence' => 'The team sees a transparent and evidence-based decision.', 'feedback' => 'Your math and reasoning support the final call.', 'career_signal' => 'Sports Analyst'],
                ],
                'reflections' => [
                    'stream' => 'Which player did you choose?',
                    'mountain' => 'Which statistic mattered most in your decision?',
                    'sky' => 'How would you defend your choice to a disappointed captain?',
                ],
                'delivery' => [
                    'language' => 'en',
                    'duration_minutes' => 13,
                    'social_mode' => 'pair',
                ],
                'qa_checks' => ['academic' => true, 'sel' => true, 'india' => true, 'career' => true, 'decision' => true],
                'analytics' => [
                    'total_starts' => 1765,
                    'completion_rate' => 0.82,
                    'avg_time_minutes' => 12.2,
                    'target_time_minutes' => 13,
                    'path_distribution' => ['path_a_wrong_choice' => 0.37, 'path_b_correct' => 0.49, 'path_c_partial' => 0.14],
                    'avg_attempts_to_correct' => 1.6,
                    'mastery_post_ulu' => 0.73,
                    'mastery_target' => 0.70,
                    'sel_reflection_completion' => 0.68,
                    'career_signal_registered' => 0.87,
                    'teacher_overrides' => 1,
                    'confusion_flags' => 6,
                ],
                'optimization_flags' => ['calculation_too_hard' => false, 'reflection_too_abstract' => false, 'path_a_too_common' => false],
                'cross_domain_links' => [
                    ['relation' => 'TEACHES', 'target' => 'MATH_STATISTICS'],
                    ['relation' => 'DEVELOPS', 'target' => 'responsible_decision_making'],
                    ['relation' => 'EXERCISES', 'target' => 'data_analysis'],
                    ['relation' => 'SIGNALS_CAREER', 'target' => 'sports_analytics'],
                ],
            ],
            [
                'title' => 'Lakshmi and the Mango Tree',
                'grade' => 4,
                'subject' => 'Environmental Science',
                'academic_concept' => 'EVS_PLANTS_ECOSYSTEMS',
                'sub_concept' => 'Photosynthesis, plant parts, and ecosystems',
                'status' => 'draft',
                'difficulty' => 2,
                'duration_minutes' => 11,
                'language' => 'en',
                'cultural_context' => 'school_courtyard',
                'social_mode' => 'individual',
                'pedagogy_tag' => 'observation_based',
                'h5p_type' => 'interactive_video',
                'casel_domain' => 'self_management',
                'ngss_practice' => 'asking_questions',
                'ncdg_goal' => 'CM2',
                'riasec_signal' => 'R',
                'career_cluster' => 'agriculture_science',
                'real_skill_name' => 'Scientific observation',
                'mastery_gate' => 0.68,
                'academic_core' => [
                    'concept_id' => 'EVS_PLANTS_ECOSYSTEMS',
                    'bloom_level' => 'understand',
                    'ncert_cg' => 'CG-6',
                ],
                'sel_layer' => [
                    'casel_indicator' => 'observe_before_acting',
                    'hpc_lens' => 'Awareness',
                    'hpc_level_signal' => 'stream',
                ],
                'stem_layer' => [
                    'cross_subject' => 'Agriculture',
                    'skill' => 'question_formulation',
                    'data_interpretation_required' => false,
                ],
                'career_layer' => [
                    'domain_exposure' => 'horticulture',
                    'gardner_intelligence' => 'naturalistic',
                    'career_title' => 'Horticulturist',
                ],
                'real_skill' => [
                    'skill_name' => 'Scientific observation',
                    'nsqf_relevance' => 'Agriculture Level 1',
                    'soft_skill_signal' => 'care',
                    'p21_skill' => 'curiosity',
                ],
                'scenario' => [
                    'context' => 'Lakshmi notices the mango tree in her school courtyard is not giving fruit this year.',
                    'academic_hook' => 'What does the tree need, and how do plants make their own food?',
                    'decision_point' => 'Should Lakshmi guess the answer or observe the tree carefully before acting?',
                    'reflection' => 'What does caring for a tree teach us about caring for the natural world?',
                ],
                'branches' => [
                    ['key' => 'path_a', 'label' => 'Guess quickly', 'choice' => 'Jump to one answer without observing', 'consequence' => 'Important plant needs are missed.', 'feedback' => 'This path shows why scientists ask questions before solving problems.', 'career_signal' => 'Gardener'],
                    ['key' => 'path_b', 'label' => 'Observe and ask', 'choice' => 'Look at sunlight, water, soil, and leaves first', 'consequence' => 'Lakshmi builds a stronger explanation for the tree’s condition.', 'feedback' => 'Careful observation leads to better science.', 'career_signal' => 'Horticulturist'],
                ],
                'reflections' => [
                    'stream' => 'What did Lakshmi notice first?',
                    'mountain' => 'What else should she check before deciding?',
                    'sky' => 'How would you help the whole school protect the tree?',
                ],
                'delivery' => [
                    'language' => 'en',
                    'duration_minutes' => 11,
                    'social_mode' => 'individual',
                ],
                'qa_checks' => ['academic' => true, 'sel' => true, 'india' => true, 'career' => true, 'decision' => true],
                'analytics' => [
                    'total_starts' => 754,
                    'completion_rate' => 0.79,
                    'avg_time_minutes' => 9.6,
                    'target_time_minutes' => 11,
                    'path_distribution' => ['path_a_wrong_choice' => 0.46, 'path_b_correct' => 0.42],
                    'avg_attempts_to_correct' => 1.7,
                    'mastery_post_ulu' => 0.66,
                    'mastery_target' => 0.68,
                    'sel_reflection_completion' => 0.63,
                    'career_signal_registered' => 0.80,
                    'teacher_overrides' => 1,
                    'confusion_flags' => 8,
                ],
                'optimization_flags' => ['calculation_too_hard' => false, 'reflection_too_abstract' => false, 'path_a_too_common' => false],
                'cross_domain_links' => [
                    ['relation' => 'TEACHES', 'target' => 'EVS_PLANTS_ECOSYSTEMS'],
                    ['relation' => 'DEVELOPS', 'target' => 'self_management'],
                    ['relation' => 'EXERCISES', 'target' => 'asking_questions'],
                    ['relation' => 'SIGNALS_CAREER', 'target' => 'agriculture_science'],
                ],
            ],
        ];

        foreach ($examples as $example) {
            UnifiedLearningUnit::firstOrCreate(
                ['title' => $example['title']],
                $this->normalizePayload($example)
            );
        }
    }

    private function normalizePayload(array $payload, ?UnifiedLearningUnit $existing = null): array
    {
        $pedagogy = $this->catalog->normalizePedagogy((string) ($payload['pedagogy_tag'] ?? data_get($payload, 'delivery.pedagogy_tag') ?? 'scenario_based'));
        $academicCore = (array) ($payload['academic_core'] ?? []);
        $selLayer = (array) ($payload['sel_layer'] ?? []);
        $stemLayer = (array) ($payload['stem_layer'] ?? []);
        $careerLayer = (array) ($payload['career_layer'] ?? []);
        $realSkill = (array) ($payload['real_skill'] ?? []);
        $scenario = (array) ($payload['scenario'] ?? []);
        $delivery = array_merge((array) ($payload['delivery'] ?? []), [
            'pedagogy_tag' => $pedagogy,
            'h5p_type' => $this->catalog->normalizeValue('h5p', $payload['h5p_type'] ?? data_get($payload, 'delivery.h5p_type') ?? 'branching_scenario'),
        ]);

        $subject = (string) ($payload['subject'] ?? data_get($academicCore, 'subject', ''));
        $grade = (int) ($payload['grade'] ?? 0);
        $academicConcept = (string) ($payload['academic_concept'] ?? data_get($academicCore, 'concept_id', ''));

        return [
            'ulu_id' => (string) ($payload['ulu_id'] ?? $existing?->ulu_id ?? $this->nextUluId($subject, $grade, $academicConcept)),
            'title' => (string) ($payload['title'] ?? ''),
            'grade' => $grade,
            'subject' => $subject,
            'academic_concept' => $academicConcept,
            'sub_concept' => (string) ($payload['sub_concept'] ?? data_get($academicCore, 'sub_concept', '')),
            'status' => strtolower((string) ($payload['status'] ?? 'draft')),
            'difficulty' => (int) ($payload['difficulty'] ?? data_get($academicCore, 'difficulty', 1)),
            'duration_minutes' => (int) ($payload['duration_minutes'] ?? data_get($delivery, 'duration_minutes', 10)),
            'language' => (string) ($payload['language'] ?? data_get($delivery, 'language', 'en')),
            'cultural_context' => (string) ($payload['cultural_context'] ?? data_get($delivery, 'cultural_context', 'india_general')),
            'social_mode' => (string) ($payload['social_mode'] ?? data_get($delivery, 'social_mode', 'individual')),
            'pedagogy_tag' => $pedagogy,
            'h5p_type' => (string) ($delivery['h5p_type'] ?? 'branching_scenario'),
            'casel_domain' => (string) $this->catalog->normalizeValue('casel', $payload['casel_domain'] ?? data_get($selLayer, 'casel_domain', '')),
            'ngss_practice' => (string) $this->catalog->normalizeValue('ngss', $payload['ngss_practice'] ?? data_get($stemLayer, 'ngss_practice', '')),
            'ncdg_goal' => (string) $this->catalog->normalizeValue('ncdg', $payload['ncdg_goal'] ?? data_get($careerLayer, 'ncdg_goal', '')),
            'riasec_signal' => (string) $this->catalog->normalizeValue('riasec', $payload['riasec_signal'] ?? data_get($careerLayer, 'riasec_signal', '')),
            'career_cluster' => (string) ($payload['career_cluster'] ?? data_get($careerLayer, 'career_cluster', '')),
            'real_skill_name' => (string) ($payload['real_skill_name'] ?? data_get($realSkill, 'skill_name', '')),
            'mastery_gate' => (float) ($payload['mastery_gate'] ?? data_get($academicCore, 'mastery_gate', 0.70)),
            'academic_core' => $academicCore + [
                'subject' => $subject,
                'concept_id' => $academicConcept,
                'sub_concept' => (string) ($payload['sub_concept'] ?? data_get($academicCore, 'sub_concept', '')),
            ],
            'sel_layer' => $selLayer + ['casel_domain' => $payload['casel_domain'] ?? data_get($selLayer, 'casel_domain')],
            'stem_layer' => $stemLayer + ['ngss_practice' => $payload['ngss_practice'] ?? data_get($stemLayer, 'ngss_practice')],
            'career_layer' => $careerLayer + [
                'ncdg_goal' => $payload['ncdg_goal'] ?? data_get($careerLayer, 'ncdg_goal'),
                'riasec_signal' => $payload['riasec_signal'] ?? data_get($careerLayer, 'riasec_signal'),
                'career_cluster' => $payload['career_cluster'] ?? data_get($careerLayer, 'career_cluster'),
            ],
            'real_skill' => $realSkill + ['skill_name' => $payload['real_skill_name'] ?? data_get($realSkill, 'skill_name')],
            'scenario' => $scenario,
            'branches' => array_values((array) ($payload['branches'] ?? [])),
            'reflections' => (array) ($payload['reflections'] ?? []),
            'delivery' => $delivery,
            'qa_checks' => (array) ($payload['qa_checks'] ?? []),
            'analytics' => (array) ($payload['analytics'] ?? []),
            'optimization_flags' => (array) ($payload['optimization_flags'] ?? []),
            'cross_domain_links' => array_values((array) ($payload['cross_domain_links'] ?? [])),
            'graph_sync_status' => 'pending',
            'published_at' => ($payload['status'] ?? null) === 'approved' ? ($existing?->published_at ?? now()) : $existing?->published_at,
            'archived_at' => ($payload['status'] ?? null) === 'archived' ? ($existing?->archived_at ?? now()) : null,
        ];
    }

    private function assertQuality(array $data, bool $forApproval): void
    {
        $errors = [];

        if (trim((string) $data['title']) === '') {
            $errors['title'][] = 'Title is required.';
        }

        if (count(array_filter([
            data_get($data, 'scenario.context'),
            data_get($data, 'scenario.academic_hook'),
            data_get($data, 'scenario.decision_point'),
            data_get($data, 'scenario.reflection'),
        ], fn ($value) => trim((string) $value) !== '')) < 4) {
            $errors['scenario'][] = 'Context, academic hook, decision point, and reflection are all required.';
        }

        if (count((array) ($data['branches'] ?? [])) < 2) {
            $errors['branches'][] = 'At least Path A and Path B are required.';
        }

        foreach (['stream', 'mountain', 'sky'] as $level) {
            if (trim((string) data_get($data, "reflections.{$level}", '')) === '') {
                $errors['reflections'][] = "Reflection for {$level} is required.";
            }
        }

        foreach (['casel_domain', 'ngss_practice', 'ncdg_goal', 'riasec_signal', 'career_cluster', 'real_skill_name'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        if ($forApproval) {
            $qa = (array) ($data['qa_checks'] ?? []);
            foreach (['academic', 'sel', 'india', 'career', 'decision'] as $check) {
                if (($qa[$check] ?? false) !== true) {
                    $errors['qa_checks'][] = ucfirst($check) . ' quality test must pass before approval.';
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function syncGraph(UnifiedLearningUnit $ulu): void
    {
        $result = $this->graph->sync($ulu);
        $ulu->graph_sync_status = $result['synced'] ? 'synced' : 'failed';
        $ulu->save();
    }

    private function nextUluId(string $subject, int $grade, string $concept): string
    {
        $subjectCode = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($subject)) ?: 'GEN', 0, 4));
        $gradeCode = 'G' . max(1, $grade);
        $conceptCode = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($concept)) ?: 'CONCEPT', 0, 10));
        $prefix = "ULU_{$subjectCode}_{$gradeCode}_{$conceptCode}_";

        $latest = UnifiedLearningUnit::where('ulu_id', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $latest, 3, '0', STR_PAD_LEFT);
    }

    private function optimizationRecommendations(array $analytics): array
    {
        $recommendations = [];

        if ((float) ($analytics['completion_rate'] ?? 1) < 0.75) {
            $recommendations[] = 'Review drop-off points and scenario clarity.';
        }
        if ((float) ($analytics['avg_attempts_to_correct'] ?? 0) > 2.5) {
            $recommendations[] = 'Add hint sequence to the academic core step.';
        }
        if ((float) data_get($analytics, 'path_distribution.path_a_wrong_choice', 0) > 0.65) {
            $recommendations[] = 'Rework Path A so the wrong choice is less obvious and more instructive.';
        }
        if ((float) ($analytics['mastery_post_ulu'] ?? 1) < (float) ($analytics['mastery_target'] ?? 0.70)) {
            $recommendations[] = 'Add linked L2-L3 practice after the ULU.';
        }
        if ((float) ($analytics['sel_reflection_completion'] ?? 1) < 0.50) {
            $recommendations[] = 'Strengthen SEL through scenario logic instead of end-only reflection.';
        }
        if ((float) ($analytics['career_signal_registered'] ?? 1) < 0.80) {
            $recommendations[] = 'Move the career signal earlier or make it more concrete.';
        }

        return $recommendations;
    }
}
