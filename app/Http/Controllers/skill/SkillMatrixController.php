<?php 

namespace App\Http\Controllers\skill;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\skill\skill;
use App\Models\skill\matrix;
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
        $skills = DB::table('onet_career_cluster')
        ->select(
            'career_cluster',
            'onetsoc_code AS JobCode',
            'career_pathway AS CareerPath',
            'title AS JobRole',
            'description',
            DB::raw('FLOOR(RAND() * 9) + 7 AS Skill'),
            DB::raw('FLOOR(RAND() * 9) + 5 AS Knowledge'),
            DB::raw('FLOOR(RAND() * 20) + 8 AS Ability'),
            DB::raw('FLOOR(RAND() * 4) + 9 AS Tasks')
        )
        ->where('career_id', 8)
        ->get()
        ->map(function ($skill) {
            $skill->SkillData = '<div class="card info-card p-4">
                <span class="badge bg-primary">➤ Technical</span>
                <ul>
                    <li>➤ CAD Software Proficiency</li>
                    <li>➤ Biomaterials & Tissue Engineering</li>
                    <li>➤ Medical Device Manufacturing</li>
                    <li>➤ Electronics & Signal Processing</li>
                    <li>➤ Cell Culture Techniques</li>
                </ul>
                <span class="badge bg-primary">➤ Analytical</span>
                <ul>
                    <li>➤ Data Analysis & Interpretation</li>
                    <li>➤ Mathematical Modeling of Biological Systems</li>
                    <li>➤ Critical Thinking & Problem-Solving</li>
                </ul>
                <span class="badge bg-primary">➤ Communication</span>
                <ul>
                    <li>➤ Technical Writing</li>
                    <li>➤ Team Collaboration</li>
                    <li>➤ Presentation Skills</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" style="width: 60%"></div>
                </div>
            </div>';
            return $skill;
        })
        ->map(function ($tasks) {
            $tasks->TasksData = '<div class="card info-card p-4">
                <span class="badge bg-danger">➤ Research & Development</span>
                <ul>
                    <li>➤ Design & prototype medical devices</li>
                    <li>➤ Conduct lab experiments & analyze results</li>
                    <li>➤ Develop testing protocols</li>
                </ul>
                <span class="badge bg-danger">➤ Clinical Translation</span>
                <ul>
                    <li>➤ Collaborate with clinicians</li>
                    <li>➤ Conduct clinical trials</li>
                    <li>➤ Obtain regulatory approvals</li>
                </ul>
                <span class="badge bg-danger">➤ Technical Leadership</span>
                <ul>
                    <li>➤ Lead project teams</li>
                    <li>➤ Manage project timelines & budgets</li>
                    <li>➤ Mentor junior engineers</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-danger" style="width: 65%"></div>
                </div>
            </div>';
            return $tasks;
        })
        ->map(function ($knowledge) {
            $knowledge->KnowledgeData = '<div class="card info-card p-4">
                <span class="badge bg-success">➤ Biological Sciences</span>
                <ul>
                    <li>➤ Cellular Biology & Genetics</li>
                    <li>➤ Microbiology & Pathology</li>
                    <li>➤ Physiology</li>
                </ul>
                <span class="badge bg-success">➤ Engineering Principles</span>
                <ul>
                    <li>➤ Fluid Mechanics & Thermodynamics</li>
                    <li>➤ Mechanics of Materials</li>
                    <li>➤ Signal Processing & Control Systems</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-success" style="width: 80%"></div>
                </div>
            </div>';
            return $knowledge;
        })
        ->map(function ($ability) {
            $ability->AbilityData = '<div class="card info-card p-4">
                <ul>
                    <li>➤ Creativity – Conceptualizing novel designs</li>
                    <li>➤ Adaptability – Keeping up with evolving technologies</li>
                    <li>➤ Attention to Detail – Ensuring design accuracy</li>
                    <li>➤ Teamwork – Collaborating on projects</li>
                </ul>
                <div class="progress mt-2">
                    <div class="progress-bar bg-warning progress-bar-striped" style="width: 50%"></div>
                </div>
            </div>';
            return $ability;
        });

        return view('skill.jobrole.index', compact('skills'));
    }

    public function JobDescription(Request $request)
    {
        // Retrieve the code from the request
        $onetsoc_code = $request->query('code'); // Safer way to get query parameters

        // Fetch career details based on the code
        $career = OnetCareerCluster::where('onetsoc_code', $onetsoc_code)->first();

        // Pass the career data to the view
        return view('skill.jobrole.jobdescription', compact('career'));
    }
}
