<?php 

namespace App\Http\Controllers\skill;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\skill\skill;
use App\Models\skill\matrix;
use App\Models\skill\AssessmentLibrary;
use App\Models\lms\counselling\OnetCareerCluster;
use Illuminate\Support\Facades\Auth;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class SkillMatrixController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $skills = skill::all();
        $completedCount = matrix::where('user_id', $user_id)->count();
        $totalSkills = $skills->count();
        $progress = $totalSkills > 0 ? round(($completedCount / $totalSkills) * 100) : 0;

        return view('skill.matrix.index', compact('skills', 'progress', 'completedCount', 'totalSkills'));
    }

    public function store(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        matrix::updateOrCreate(
            ['user_id' => $user_id, 'skill_id' => $request->skill_id],
            ['skill_level' => $request->skill_level, 'interest_level' => $request->interest_level]
        );

        return response()->json(['success' => true]);
    }

    public function JobRole()
    {
        $skills = DB::table('s_jobrole')
        ->select(
            'id',
'industries',
            'sector',
            'track',
            'jobrole',
            'description',
            DB::raw('FLOOR(RAND() * 9) + 7 AS Skill'),
            DB::raw('FLOOR(RAND() * 9) + 5 AS Knowledge'),
            DB::raw('FLOOR(RAND() * 20) + 8 AS Ability'),
            DB::raw('FLOOR(RAND() * 4) + 9 AS Tasks')
        )
        ->where('status', 'Active')
        ->orderBy('id') // Ordering by ID
        ->get()
        ->map(function ($skill) {
            $skill->SkillData = '<div class="card info-card p-4">
                <span class="badge bg-primary">➤ Technical Skills</span>
                    <ul>
                        <li>➤ Nursing Productivity and Innovation <span class="badge bg-danger">Level 5</span></li>
                        <li>➤ Medication Management in Nursing</li>
                        <li>➤ Patient Care Delivery in Nursing <span class="badge bg-danger">Level 4</span></li>
                        <li>➤ Respiratory Care in Nursing</li>
                    </ul>

                    <span class="badge bg-primary">➤ Functional Skills</span>
                    <ul>
                        <li>➤ Learning Needs Analysis <span class="badge bg-danger">Level 3</span></li>
                        <li>➤ Nursing Research and Statistics</li>
                    </ul>

                    <span class="badge bg-primary">➤ Soft Skills (Behavioral & Interpersonal Skills)</span>
                    <ul>
                        <li>➤ Inter-professional Collaboration</li>
                        <li>➤ Effective Communication in Nursing</li>
                    </ul>

                    <span class="badge bg-primary">➤ Cognitive & Thinking Skills</span>
                    <ul>
                        <li>➤ Sense Making</li>
                        <li>➤ Decision Making</li>
                        <li>➤ Transdisciplinary Thinking</li>
                    </ul>

                    <span class="badge bg-primary">➤ Leadership & Management Skills</span>
                    <ul>
                        <li>➤ Change Management</li>
                        <li>➤ Clinical Teaching and Supervision</li>
                        <li>➤ Emergency Response and Crisis Management</li>
                        <li>➤ Department Financial Management</li>
                        <li>➤ Health Education Programme Development and Implementation</li>
                        <li>➤ Performance Management for Nursing</li>
                        <li>➤ Quality Improvement and Safe Practices</li>
                        <li>➤ Service Quality Management</li>
                        <li>➤ Clinical Services Development</li>
                        <li>➤ Strategy Management</li>
                        <li>➤ Developing People</li>
                    </ul>

                    <span class="badge bg-primary">➤ Compliance & Regulatory Skills</span>
                    <ul>
                        <li>➤ Clinical Governance</li>
                        <li>➤ Infection Prevention and Control in Nursing Practice</li>
                        <li>➤ Workplace Safety and Health</li>
                    </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" style="width: 60%"></div>
                </div>
            </div>';
            return $skill;
        })
        ->map(function ($tasks) {
            $tasks->TasksData = '<div class="card info-card p-4">
                <span class="badge bg-danger">➤ Advance nursing practices</span>
                <ul>
                    <li>➤ Establish funding models for new care models or nursing clinical services</li>
                    <li>➤ Implement new practices and care models or services in accordance to frameworks that include considerations for facilities, resourcing, funding, training, processes, outcomes and regulations</li>
                    <li>➤ Measure outcomes of advanced or specialised nursing practices to assess and improve new interventions</li>
                </ul>
                <span class="badge bg-danger">➤ Oversee nursing clinical care delivery</span>
                <ul>
                    <li>➤ Establish frameworks for hospital-to-community models of care that include funding, manpower, pilots, outcome measurements and implementation</li>
                    <li>➤ Oversee nursing practices and care delivery outcomes</li>
                    <li>➤ Establish frameworks for evidence-based nursing</li>
                    <li>➤ Develop strategies to empower and engage patients and caregivers</li>
                    <li>➤ Promote inter-professional collaboration in care delivery</li>
                </ul>
                <span class="badge bg-danger">➤ Drive nursing quality and patient safety</span>
                <ul>
                    <li>➤ Lead multi-disciplinary work groups to improve patient and staff safety</li>
                    <li>➤ Establish frameworks for critical communications</li>
                    <li>➤ Manage adverse events according to organisational frameworks</li>
                    <li>➤ Establish an open culture to facilitate quality and patient safety development</li>
                    <li>➤ Establish nursing infection prevention and control policies and procedures</li>
                    <li>➤ Lead nursing to achieve local and international accreditations</li>
                    <li>➤ Guide nursing clinical audits</li>
                    <li>➤ Adopt new technology and electronic tools and devices for better quality and patient safety outcomes</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-danger" style="width: 65%"></div>
                </div>
            </div>';
            return $tasks;
        })
        ->map(function ($knowledge) {
            $knowledge->KnowledgeData = '<div class="card info-card p-4">
                    <ul>
                        <li>➤ Innovation evidence, legal and intellectual property rights</li>
                        <li>➤ Clinical information technology analysis frameworks</li>
                        <li>➤ Best practices on the selection and implementation of technology to drive improved quality of care and operational efficiencies</li>
                        <li>➤ Ethical and social issues in nursing informatics and consumer informatics</li>
                        <li>➤ Informatics applications for telehealth, consumer health and community-based care</li>
                        <li>➤ Metrics to measure effectiveness of new technologies in the delivery of patient care</li>
                        <li>➤ Manpower optimisation through adoption of new technologies</li>
                    </ul>
                </div>';
            return $knowledge;
        })
        ->map(function ($ability) {
            $ability->AbilityData = '<div class="card info-card p-4">
                <ul>
                    <li>➤ Promote best practices to adopt new technology towards quality improvement and increase productivity</li>
                    <li>➤ Mitigate challenges in the adoption of new technology</li>
                    <li>➤ Facilitate transition of care in the use of new nursing informatics and medical scientific technology</li>
                    <li>➤ Evaluate data integrity with the adoption of new technologies</li>
                    <li>➤ Develop work plans for the implementation of nursing informatics and medical scientific technology by analysing the practicality, feasibility and risks of new technology adoption</li>
                </ul>
            </div>';
            return $ability;
        });

        return view('skill.jobrole.index', compact('skills'));
    }

    public function JobDescription(Request $request)
    {
        $career = DB::table('s_jobrole')
        ->select('id','sector','track','jobrole','description')
        ->where('id', $request->query('id'))
        ->first();

        // Pass the career data to the view
        return view('skill.jobrole.jobdescription', compact('career'));
    }

    public function AssessmentLibrary()
    {
        $assessments = AssessmentLibrary::all();
        //$assessments = AssessmentLibrary::inRandomOrder()->get();
        return view('skill.assessment.assessment_library', compact('assessments'));
    }
}
