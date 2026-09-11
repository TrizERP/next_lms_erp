<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCurriculumLearningOutcomes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lms:sync-curriculum-learning-outcomes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync exact NCF/NCERT curriculum goals and competencies into document_extractions.md_content and lms_learning_outcomes table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting NCF Curriculum Goals & Competencies Synchronization...');

        // 1. Grade 10 Mathematics (Curriculum ID 101, Standard 43, Subject 3976, Extraction ID 29)
        $this->syncGrade10Math();

        // 2. Grade 9 Hindi (Curriculum ID 92, Standard 42, Subject 3977, Extraction ID 86)
        $this->syncGrade9Hindi();

        // 3. Grade 9 Sanskrit (Curriculum ID 85, Standard 42, Subject 5575, Extraction ID 110)
        $this->syncGrade9Sanskrit();

        // 4. Grade 9 Skill Education (Curriculum ID 83, Standard 42, Subject 5576, Extraction ID 130)
        $this->syncGrade9SkillEd();

        $this->info('Curriculum Learning Outcomes synchronization complete.');

        return 0;
    }

    private function syncGrade10Math()
    {
        $curriculumId = 101;
        $standardId = 43;
        $subjectId = 3976;
        $extractionId = 29;

        $data = [
            [
                'goal' => ['code' => 'CG-1', 'type' => null, 'desc' => 'Understands number systems, real numbers, and the fundamental theorem of arithmetic.'],
                'comps' => [
                    ['code' => 'C-1.1', 'type' => 'competency', 'desc' => 'Applies fundamental theorem of arithmetic to prove irrationality of numbers and analyze decimal expansions.'],
                    ['code' => 'C-1.2', 'type' => 'competency', 'desc' => 'Uses prime factorisation to determine HCF and LCM of integers in practical contexts.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-2', 'type' => null, 'desc' => 'Solves linear and quadratic equations and analyzes polynomial functions.'],
                'comps' => [
                    ['code' => 'C-2.1', 'type' => 'competency', 'desc' => 'Finds zeroes of quadratic polynomials and verifies relationships between zeroes and coefficients.'],
                    ['code' => 'C-2.2', 'type' => 'competency', 'desc' => 'Solves pair of linear equations in two variables algebraically and graphically.'],
                    ['code' => 'C-2.3', 'type' => 'competency', 'desc' => 'Solves quadratic equations using factorisation and quadratic formula.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-3', 'type' => null, 'desc' => 'Explores sequences and progressions to model growth and patterns.'],
                'comps' => [
                    ['code' => 'C-3.1', 'type' => 'competency', 'desc' => 'Identifies arithmetic progressions, derives nth term and sum of first n terms.'],
                    ['code' => 'C-3.2', 'type' => 'competency', 'desc' => 'Applies AP formulae to solve real-life contextualised problems.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-4', 'type' => null, 'desc' => 'Analyzes coordinate geometry to study geometric figures algebraically.'],
                'comps' => [
                    ['code' => 'C-4.1', 'type' => 'competency', 'desc' => 'Calculates distance between two points and uses section formula to find internal division points.'],
                    ['code' => 'C-4.2', 'type' => 'competency', 'desc' => 'Finds area of triangle using coordinate vertices.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-5', 'type' => null, 'desc' => 'Applies geometric theorems on triangles, circles, and constructions.'],
                'comps' => [
                    ['code' => 'C-5.1', 'type' => 'competency', 'desc' => 'Proves basic proportionality theorem and similarity criteria for triangles.'],
                    ['code' => 'C-5.2', 'type' => 'competency', 'desc' => 'Proves tangent theorems for circles and constructs tangents from an external point.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-6', 'type' => null, 'desc' => 'Evaluates trigonometric ratios, identities, and applications to heights and distances.'],
                'comps' => [
                    ['code' => 'C-6.1', 'type' => 'competency', 'desc' => 'Evaluates trigonometric ratios of specific angles (0, 30, 45, 60, 90 degrees).'],
                    ['code' => 'C-6.2', 'type' => 'competency', 'desc' => 'Uses fundamental trigonometric identities to prove algebraic relationships.'],
                    ['code' => 'C-6.3', 'type' => 'competency', 'desc' => 'Applies angles of elevation and depression to calculate heights and distances.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-7', 'type' => null, 'desc' => 'Computes areas related to circles, surface areas, and volumes of solid combinations.'],
                'comps' => [
                    ['code' => 'C-7.1', 'type' => 'competency', 'desc' => 'Calculates areas of sectors and segments of circles in complex plane figures.'],
                    ['code' => 'C-7.2', 'type' => 'competency', 'desc' => 'Determines surface areas and volumes of combinations of solids (cubes, cylinders, cones, spheres).'],
                    ['code' => 'C-7.3', 'type' => 'competency', 'desc' => 'Analyzes frustum of cone and converts shapes from one solid form to another.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-8', 'type' => null, 'desc' => 'Interprets grouped statistical data and calculates theoretical probability.'],
                'comps' => [
                    ['code' => 'C-8.1', 'type' => 'competency', 'desc' => 'Calculates mean, median, and mode of grouped frequency distributions.'],
                    ['code' => 'C-8.2', 'type' => 'competency', 'desc' => 'Evaluates theoretical probability of single and compound events.'],
                ],
            ],
        ];

        $this->syncCurriculumData($curriculumId, $standardId, $subjectId, $extractionId, $data);
    }

    private function syncGrade9Hindi()
    {
        $curriculumId = 92;
        $standardId = 42;
        $subjectId = 3977;
        $extractionId = 86;

        $data = [
            [
                'goal' => ['code' => 'CG-1', 'type' => null, 'desc' => 'श्रवण एवं पठन कौशल द्वारा साहित्य और भाषा के गूढ़ अर्थ का अवबोध करना।'],
                'comps' => [
                    ['code' => 'C-1.1', 'type' => 'competency', 'desc' => 'साहित्यिक रचनाओं (कहानी, निबंध, नाटक) को पढ़कर मुख्य विचार एवं संदेश को ग्रहण करना।'],
                    ['code' => 'C-1.2', 'type' => 'competency', 'desc' => 'भाषा की विविध शैलियों और बिंबों की पहचान कर उनका विश्लेषण करना।'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-2', 'type' => null, 'desc' => 'मौखिक एवं लिखित अभिव्यक्ति को प्रभावी, स्पष्ट और तर्कसंगत बनाना।'],
                'comps' => [
                    ['code' => 'C-2.1', 'type' => 'competency', 'desc' => 'विभिन्न सामाजिक एवं विषयगत मुद्दों पर तर्कपूर्ण एवं सुसंगत विचार व्यक्त करना।'],
                    ['code' => 'C-2.2', 'type' => 'competency', 'desc' => 'रचनात्मक लेखन (पत्र, संवाद, निबंध, संस्मरण) में भाषा के विविध रूपों का सटीक प्रयोग करना।'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-3', 'type' => null, 'desc' => 'व्यावहारिक व्याकरण, पद-परिचय एवं वाक्य-संरचना का बोध एवं अनुप्रयोग।'],
                'comps' => [
                    ['code' => 'C-3.1', 'type' => 'competency', 'desc' => 'विभिन्न श्रव्य और लिखित सामग्री का विश्लेषण और मूल्यांकन करना।'],
                    ['code' => 'C-3.2', 'type' => 'competency', 'desc' => 'रचना में परिवेश का सावधानीपूर्वक मूल्यांकन करके उचित शब्दों का चयन करना।'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-4', 'type' => null, 'desc' => 'भारतीय भाषाई एवं सांस्कृतिक विविधता के प्रति संवेदनशीलता और सम्मान का विकास।'],
                'comps' => [
                    ['code' => 'C-4.1', 'type' => 'competency', 'desc' => 'पाठ को पढ़ते हुए विभिन्न शैलियों की सामग्री के अवलोकन द्वारा भाषा-कौशल विकसित करना।'],
                    ['code' => 'C-4.2', 'type' => 'competency', 'desc' => 'भारतीय भाषाओं की विभिन्न साहित्यिक रचनाओं में निहित सांस्कृतिक मूल्यों का आदर करना।'],
                    ['code' => 'C-4.3', 'type' => 'competency', 'desc' => 'हमारी संस्कृति और पहचान के निर्माण में भाषा की भूमिका को रेखांकित करना।'],
                ],
            ],
        ];

        $this->syncCurriculumData($curriculumId, $standardId, $subjectId, $extractionId, $data);
    }

    private function syncGrade9Sanskrit()
    {
        $curriculumId = 85;
        $standardId = 42;
        $subjectId = 5575;
        $extractionId = 110;

        $data = [
            [
                'goal' => ['code' => 'CG-1', 'type' => null, 'desc' => 'भाषायाः प्रभावपूर्णसम्प्रेषणाय विविधानां मौखिकलेखनाद्यासानां विकासः।'],
                'comps' => [
                    ['code' => 'C-1.1', 'type' => 'competency', 'desc' => 'शुद्धोच्चारणेन संस्कृतश्लोकानां पद्यांशैः सह पाठनं वाचनं च।'],
                    ['code' => 'C-1.2', 'type' => 'competency', 'desc' => 'सरलसंस्कृतभाषया संवादः विचारविनिमयः च।'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-2', 'type' => null, 'desc' => 'संस्कृतभाषया सम्प्रेषणकौशलविकासः परस्परं संस्कृतसम्भाषणेन च।'],
                'comps' => [
                    ['code' => 'C-2.1', 'type' => 'competency', 'desc' => 'गद्यांश-पद्यांशानां भावमवगम्य प्रश्नानां समुचितोत्तरलेखनम्।'],
                    ['code' => 'C-2.2', 'type' => 'competency', 'desc' => 'कारक-विभक्ति-संधि-समासानां व्यावहारिकप्रयोगः।'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-5', 'type' => null, 'desc' => 'श्रवण-भाषण-पठन-लेखनेति चतुर्णाम् भाषिक-कौशलानां विकासः।'],
                'comps' => [
                    ['code' => 'C-5.1', 'type' => 'competency', 'desc' => 'श्रवणकौशलम् - भावाधिग्रहणाय श्राव्यात्मकं भाषायाः प्रथमं कौशलम्।'],
                    ['code' => 'C-5.2', 'type' => 'competency', 'desc' => 'भाषणकौशलम् - भावाभिव्यक्तये श्राव्यात्मकं भाषायाः इदं द्वितीयं कौशलम्।'],
                    ['code' => 'C-5.3', 'type' => 'competency', 'desc' => 'पठनकौशलम् - भावाधिग्रहणाय लिखितात्मकं भाषायाः तृतीयं कौशलम्।'],
                    ['code' => 'C-5.4', 'type' => 'competency', 'desc' => 'लेखनकौशलम् - भावाभिव्यक्तये लिखितात्मकं भाषायाः चतुर्थं कौशलम्।'],
                ],
            ],
        ];

        $this->syncCurriculumData($curriculumId, $standardId, $subjectId, $extractionId, $data);
    }

    private function syncGrade9SkillEd()
    {
        $curriculumId = 83;
        $standardId = 42;
        $subjectId = 5576;
        $extractionId = 130;

        $data = [
            [
                'goal' => ['code' => 'CG-1', 'type' => null, 'desc' => 'Develops in-depth basic skills and allied knowledge of work.'],
                'comps' => [
                    ['code' => 'C-1.1', 'type' => 'competency', 'desc' => 'Perform procedures competently through required tools/equipment.'],
                    ['code' => 'C-1.2', 'type' => 'competency', 'desc' => 'Differentiates between effective and non-effective practices.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-2', 'type' => null, 'desc' => 'Develops essential values while working in a specific vocation.'],
                'comps' => [
                    ['code' => 'C-2.1', 'type' => 'competency', 'desc' => 'Develops workplace values, safety adherence, and attention to precision.'],
                    ['code' => 'C-2.2', 'type' => 'competency', 'desc' => 'Demonstrates professional communication and customer service skills.'],
                ],
            ],
            [
                'goal' => ['code' => 'CG-3', 'type' => null, 'desc' => 'Develops basic skills and allied knowledge to run and contribute to vocational work.'],
                'comps' => [
                    ['code' => 'C-3.1', 'type' => 'competency', 'desc' => 'Applies acquired vocational skills and knowledge in a holistic manner.'],
                    ['code' => 'C-3.2', 'type' => 'competency', 'desc' => 'Solves contextual workplace problems using green and sustainable practices.'],
                ],
            ],
        ];

        $this->syncCurriculumData($curriculumId, $standardId, $subjectId, $extractionId, $data);
    }

    private function syncCurriculumData($curriculumId, $standardId, $subjectId, $extractionId, array $data)
    {
        $mdLines = ["# Curriculum Syllabus and Learning Outcomes\n"];

        foreach ($data as $group) {
            $goal = $group['goal'];

            // Insert or update Goal in lms_learning_outcomes
            $existingGoal = DB::table('lms_learning_outcomes')
                ->where('curriculum_id', $curriculumId)
                ->where('code', $goal['code'])
                ->whereNull('parent_id')
                ->first();

            if ($existingGoal) {
                $goalId = $existingGoal->id;
                DB::table('lms_learning_outcomes')->where('id', $goalId)->update([
                    'extraction_id' => $extractionId,
                    'standard_id' => $standardId,
                    'subject_id' => $subjectId,
                    'description' => $goal['desc'],
                    'updated_at' => now(),
                ]);
            } else {
                $goalId = DB::table('lms_learning_outcomes')->insertGetId([
                    'extraction_id' => $extractionId,
                    'curriculum_id' => $curriculumId,
                    'standard_id' => $standardId,
                    'subject_id' => $subjectId,
                    'chapter_id' => 0,
                    'parent_id' => null,
                    'code' => $goal['code'],
                    'type' => $goal['type'],
                    'description' => $goal['desc'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $mdLines[] = "## {$goal['code']}: {$goal['desc']}";

            foreach ($group['comps'] as $comp) {
                $existingComp = DB::table('lms_learning_outcomes')
                    ->where('curriculum_id', $curriculumId)
                    ->where('code', $comp['code'])
                    ->first();

                if ($existingComp) {
                    DB::table('lms_learning_outcomes')->where('id', $existingComp->id)->update([
                        'extraction_id' => $extractionId,
                        'standard_id' => $standardId,
                        'subject_id' => $subjectId,
                        'parent_id' => $goalId,
                        'type' => $comp['type'],
                        'description' => $comp['desc'],
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('lms_learning_outcomes')->insert([
                        'extraction_id' => $extractionId,
                        'curriculum_id' => $curriculumId,
                        'standard_id' => $standardId,
                        'subject_id' => $subjectId,
                        'chapter_id' => 0,
                        'parent_id' => $goalId,
                        'code' => $comp['code'],
                        'type' => $comp['type'],
                        'description' => $comp['desc'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $mdLines[] = "- **{$comp['code']}**: {$comp['desc']}";
            }

            $mdLines[] = "";
        }

        // Append or update md_content in document_extractions table
        $existingMd = DB::table('document_extractions')->where('id', $extractionId)->value('md_content') ?? '';
        $newMdContent = implode("\n", $mdLines);

        if (!str_contains($existingMd, '## CG-')) {
            $updatedMd = trim($existingMd) . "\n\n" . $newMdContent;
            DB::table('document_extractions')->where('id', $extractionId)->update([
                'md_content' => $updatedMd,
                'updated_at' => now(),
            ]);
        }
    }
}
