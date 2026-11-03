<?php

namespace Database\Seeders;

use App\Models\PedagogicalMethodContext;
use App\Services\StudyDeck\TeachingMethodProfile;
use Illuminate\Database\Seeder;

/**
 * Stores the ten teaching-method contexts. Idempotent (updateOrCreate on method_key); never deletes a row.
 *
 * SOURCES, not rewrites: the stages, roles, activity and interaction types and assessment come from
 * config/pedagogical_workflow.php, and the AI instructions are composed from StudyDeck\TeachingMethodProfile (directive,
 * content and interaction rules, phases) plus that config's own ai_instructions. Only the philosophy, principles,
 * strategies and questioning strategies below are new text, because no earlier source held them.
 *
 * Run by hand (the table must exist first, see the migration):
 *   php artisan db:seed --class=PedagogicalMethodContextSeeder
 */
class PedagogicalMethodContextSeeder extends Seeder
{
    public function run(): void
    {
        $config = (array) config('pedagogical_workflow.methods', []);
        $extras = self::extras();
        $profiles = TeachingMethodProfile::all();

        foreach ($extras as $key => $x) {
            $m = $config[$key] ?? null;
            $p = $profiles[$key] ?? null;
            if ($m === null || $p === null) {
                throw new \RuntimeException("Method $key is missing from config/pedagogical_workflow.php or TeachingMethodProfile.");
            }
            PedagogicalMethodContext::updateOrCreate(['method_key' => $key], [
                'method_name' => $m['name'],
                'method_definition' => $m['description'],
                'teaching_philosophy' => $x['philosophy'],
                'core_principles' => $x['principles'],
                'teacher_role' => $m['teacher_role'],
                'student_role' => $m['student_role'],
                'teaching_strategies' => $x['strategies'],
                'workflow_stages' => $m['stages'],
                'classroom_activities' => [
                    'types' => $m['activity_types'],
                    'interaction_types' => $m['interaction_types'],
                    'stage_activities' => array_column($m['stages'], 'activity', 'name'),
                ],
                'questioning_strategies' => $x['questioning'],
                'assessment_strategy' => ['summary' => $m['assessment'], 'learning_objectives' => $m['objectives']],
                'ai_generation_instructions' => self::instructions($m, $p),
                'is_active' => true,
            ]);
        }
    }

    /** @param array<string,mixed> $m config method @param array<string,mixed> $p TeachingMethodProfile entry */
    private static function instructions(array $m, array $p): string
    {
        return implode("\n", [
            "Teaching method: {$m['name']}.",
            'Phases: ' . implode(' -> ', $p['phases']) . '.',
            'Directive: ' . $p['directive'],
            'Slide and story text: ' . $p['content'],
            'Interactions: ' . $p['interaction'],
            'Method rule: ' . $m['ai_instructions'],
            'The chapter text and its concept data are the only source of facts. This method context is fixed: follow it, never redefine it.',
        ]);
    }

    /** @return array<string,array{philosophy:string,principles:array<int,string>,strategies:array<int,string>,questioning:array<int,string>}> */
    public static function extras(): array
    {
        return [
            'explicit_instruction' => [
                'philosophy' => 'Learning is most reliable when the teacher makes the path visible: clear goals, accurate explanation, a worked model, then practice with support that is gradually withdrawn.',
                'principles' => ['State the objective before teaching', 'Explain in small, ordered steps', 'Model the thinking aloud', 'Check understanding before moving on', 'Practise with fading support'],
                'strategies' => ['I do, we do, you do', 'Worked examples with numbered steps', 'Frequent checks for understanding', 'Immediate corrective feedback'],
                'questioning' => ['Recall questions that confirm the idea was taken in', 'Why-this-step questions during the demonstration', 'Short exit questions, one per concept'],
            ],
            'inquiry_based' => [
                'philosophy' => 'Understanding is built, not received: students notice, wonder and test, and the teacher names the concept only after the evidence has pointed to it.',
                'principles' => ['Start from curiosity and observation', 'Work from a driving question', 'Evidence comes before explanation', 'Students explain in their own words first', 'Transfer the idea to a new situation'],
                'strategies' => ['5E sequence: Engage, Ask, Explore, Explain, Elaborate, Evaluate', 'Predict, observe, compare', 'Claim, evidence, reasoning', 'Open investigation with a recording table'],
                'questioning' => ['What do you notice? What puzzles you?', 'What do you think will happen, and why?', 'What does your evidence show?', 'What would you test next?'],
            ],
            'problem_based' => [
                'philosophy' => 'A real problem gives the ideas a reason to exist. Students learn the concepts because they need them to reach a solution they can defend.',
                'principles' => ['Pose the problem before any explanation', 'Students define what is known and unknown', 'Knowledge gaps set the research agenda', 'More than one solution is considered', 'Reflection closes the cycle'],
                'strategies' => ['Known, unknown, goal analysis', 'Learning-issues list', 'Guided research from the chapter', 'Solution comparison against criteria'],
                'questioning' => ['What is the problem and who is affected?', 'What do we still need to find out?', 'Which solution is best, and why?', 'What would you do differently?'],
            ],
            'project_based' => [
                'philosophy' => 'Sustained work toward a meaningful product lets students apply ideas, make decisions and take ownership of what they learn.',
                'principles' => ['An open driving question frames the work', 'Success criteria are known from the start', 'Students plan, build and present', 'Teach only what the project needs', 'Assess the product and the learning'],
                'strategies' => ['Project plan with roles and deadlines', 'Checkpoints during the build', 'Rubric-based assessment', 'Public presentation with questions'],
                'questioning' => ['What are we trying to find out or make?', 'Why did you choose this design?', 'How does your product answer the driving question?'],
            ],
            'experiential' => [
                'philosophy' => 'Experience comes first. Students act, reflect on what happened, form the idea and then test it, so the concept is anchored in something they did.',
                'principles' => ['Act before explaining', 'Record observations carefully', 'Reflect on the experience', 'Form the concept from the result', 'Apply and test in a new situation'],
                'strategies' => ['Kolb cycle: experience, observe, reflect, conceptualise, experiment', 'Safe hands-on tasks with ordinary materials', 'Observation tables', 'Variation tasks'],
                'questioning' => ['What did you do, and what did you see?', 'What surprised you?', 'What idea explains the result?', 'Where do you see this in daily life?'],
            ],
            'collaborative' => [
                'philosophy' => 'Students understand more deeply when they must explain to and learn from each other, with shared goals and individual accountability.',
                'principles' => ['Positive interdependence through roles', 'Individual accountability', 'Structured discussion and agreement', 'Knowledge is shared across groups', 'Peers give feedback'],
                'strategies' => ['Jigsaw sharing', 'Role cards: reader, recorder, reporter', 'Think, pair, share', 'Peer assessment with a feedback form'],
                'questioning' => ['What is your part, and how does it join the others?', 'Do we all agree, and why?', 'Can you explain it to another group?'],
            ],
            'socratic' => [
                'philosophy' => 'Teaching by questioning exposes assumptions and builds reasoning: the teacher asks, probes and withholds the answer until students can reach it themselves.',
                'principles' => ['Open with a question, not a statement', 'Ask for reasons and examples', 'Challenge assumptions with contrast', 'Link statements into an argument', 'Reflect on how thinking changed'],
                'strategies' => ['Question chains', 'Misconception versus correct idea', 'Contrasting cases', 'Concept mapping of the final argument'],
                'questioning' => ['What do you mean by that? How do you know?', 'Can you give an example?', 'What if it were different?', 'Does this follow from that?', 'How has your thinking changed?'],
            ],
            'flipped_classroom' => [
                'philosophy' => 'Content delivery moves out of class so that class time, with the teacher present, goes to application, discussion and help with difficulty.',
                'principles' => ['Pre-class input is short and focused', 'Check the preparation first', 'Spend class time on application', 'Clear confusions through discussion', 'Assess and reflect'],
                'strategies' => ['Pre-class reading or video with notes', 'Quick knowledge check', 'Collaborative problem solving in class', 'Targeted mini-clarifications'],
                'questioning' => ['What did you learn, and what was unclear?', 'What is the main idea?', 'How would you use the idea here?'],
            ],
            'differentiated_mastery' => [
                'philosophy' => 'Students start from different places. Teaching adapts to diagnosed needs, and a student moves on only after showing mastery of the current idea.',
                'principles' => ['Diagnose before teaching', 'Group by need, not by label', 'Scaffold from simple to full', 'Give feedback and a retry', 'Remediate or enrich after the mastery check'],
                'strategies' => ['Diagnostic quiz', 'Tiered tasks', 'Targeted small-group instruction', 'Graded worked examples with model answers'],
                'questioning' => ['What do you already know about each idea?', 'What is the simplest way to say it?', 'Can you do it without help?'],
            ],
            'gamified' => [
                'philosophy' => 'Motivation grows when learning has a goal, visible progress and meaningful choices. Game structure serves the learning and never replaces it.',
                'principles' => ['A clear mission and rules', 'Challenges that rise in difficulty', 'Decisions with consequences', 'Immediate feedback and visible progress', 'Reflect on what was achieved'],
                'strategies' => ['Levels with a progress indicator', 'Decision points with feedback', 'Retry after feedback', 'A final mastery challenge'],
                'questioning' => ['What is your mission?', 'Which choice will you make, and why?', 'What did you learn from the result?'],
            ],
        ];
    }
}
