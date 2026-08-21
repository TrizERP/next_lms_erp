<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\TalentManagement\TalentScreeningResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\talent_screening_results_controller`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input. `created_by` comes from `session()->get('user_id')`
 * (source had a `// TODO: Set to authenticated user ID` left unresolved and
 * always wrote null - resolved here to the actual session actor).
 */
class ScreeningResultController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * POST talent-screening-results
     * Ported from talent_screening_results_controller@store.
     */
    public function store(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $validator = Validator::make($request->all(), [
            'candidate_id' => 'required|exists:talent_job_applications,id',
            'competency_match' => 'required|integer|min:0|max:100',
            'cultural_fit' => 'required|in:High,Medium,Low',
            'predicted_success' => 'required|in:Highly Likely,Likely,Possible,Unlikely',
            'overall_fit_score' => 'required|integer|min:0|max:100',
            'ranking_score' => 'required|integer|min:0|max:100',
            'skill_gaps' => 'nullable|array',
            'strengths' => 'nullable|array',
            'recommendation' => 'required|string',
            'deepseek_analysis' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['id']);
        $data['sub_institute_id'] = $subInstituteId;
        $data['created_by'] = $this->actorId();

        $result = TalentScreeningResult::create($data);

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    private function calculateMatchPercentage($candidate_id)
    {
        $application = DB::table('talent_job_applications')->where('id', $candidate_id)->first();
        if (!$application) {
            return null;
        }

        $jobPosting = DB::table('talent_job_postings')->where('id', $application->job_id)->first();
        if (!$jobPosting) {
            return null;
        }

        $matchScores = [];

        $skillsMatch = $this->calculateSkillsMatch($application->skills, $jobPosting->skills);
        $matchScores['skills'] = $skillsMatch;

        $educationMatch = $this->calculateEducationMatch($application->education, $jobPosting->education);
        $matchScores['education'] = $educationMatch;

        $experienceMatch = $this->calculateExperienceMatch($application->experience, $jobPosting->experience);
        $matchScores['experience'] = $experienceMatch;

        $overallMatch = round(($skillsMatch + $educationMatch + $experienceMatch) / 3, 2);

        return [
            'overall_match' => $overallMatch,
            'skills_match' => $skillsMatch,
            'education_match' => $educationMatch,
            'experience_match' => $experienceMatch,
            'details' => $matchScores,
        ];
    }

    private function calculateSkillsMatch($candidateSkills, $requiredSkills)
    {
        if (empty($candidateSkills) || empty($requiredSkills)) {
            return 0;
        }

        $candidateSkillsArray = array_map('trim', preg_split('/[,;|\n]/', strtolower($candidateSkills)));
        $requiredSkillsArray = array_map('trim', preg_split('/[,;|\n]/', strtolower($requiredSkills)));

        $candidateSkillsArray = array_filter($candidateSkillsArray);
        $requiredSkillsArray = array_filter($requiredSkillsArray);

        if (empty($requiredSkillsArray)) {
            return 0;
        }

        $matchedSkills = 0;
        foreach ($requiredSkillsArray as $requiredSkill) {
            foreach ($candidateSkillsArray as $candidateSkill) {
                if (strpos($candidateSkill, $requiredSkill) !== false || strpos($requiredSkill, $candidateSkill) !== false) {
                    $matchedSkills++;
                    break;
                }
            }
        }

        return round(($matchedSkills / count($requiredSkillsArray)) * 100, 2);
    }

    private function calculateEducationMatch($candidateEducation, $requiredEducation)
    {
        if (empty($candidateEducation) || empty($requiredEducation)) {
            return 0;
        }

        $candidateEdu = strtolower(trim($candidateEducation));
        $requiredEdu = strtolower(trim($requiredEducation));

        if (strpos($candidateEdu, $requiredEdu) !== false || strpos($requiredEdu, $candidateEdu) !== false) {
            return 100;
        }

        return 0;
    }

    private function calculateExperienceMatch($candidateExperience, $requiredExperience)
    {
        if (empty($candidateExperience) || empty($requiredExperience)) {
            return 0;
        }

        $candidateYears = $this->parseExperienceYears($candidateExperience);
        $requiredYears = $this->parseExperienceYears($requiredExperience);

        if ($candidateYears === null || $requiredYears === null) {
            return 0;
        }

        if ($candidateYears >= $requiredYears) {
            return 100;
        }

        if ($candidateYears >= $requiredYears * 0.5) {
            return round(($candidateYears / $requiredYears) * 100, 2);
        }

        return 0;
    }

    private function parseExperienceYears($experienceString)
    {
        if (empty($experienceString)) {
            return null;
        }

        $experience = strtolower(trim($experienceString));

        if (preg_match('/(\d+)-(\d+)\s*years?/', $experience, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)\+?\s*years?/', $experience, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)\s*to\s*(\d+)\s*years?/', $experience, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseCandidateDataForBERT($candidateData)
    {
        if (!$candidateData) {
            return [
                'totalSkillsFound' => 0,
                'yearsExperience' => 0,
                'educationLevel' => '',
                'extractionScore' => 0.0,
            ];
        }

        $skillsCount = 0;
        if (!empty($candidateData->skills)) {
            $skillsArray = array_map('trim', preg_split('/[,;|\n]/', $candidateData->skills));
            $skillsArray = array_filter($skillsArray);
            $skillsCount = count($skillsArray);
        }

        $yearsExperience = $this->parseExperienceYears($candidateData->experience) ?? 0;

        $educationLevel = $candidateData->education ?? '';

        $completenessScore = 0;
        if (!empty($candidateData->skills)) $completenessScore += 0.3;
        if (!empty($candidateData->education)) $completenessScore += 0.3;
        if (!empty($candidateData->experience)) $completenessScore += 0.4;

        $qualityScore = 0;
        if ($skillsCount > 3) $qualityScore += 0.2;
        if (strlen($candidateData->education ?? '') > 10) $qualityScore += 0.1;
        if ($yearsExperience > 0) $qualityScore += 0.1;

        $extractionScore = round(min(1.0, $completenessScore + $qualityScore), 2);

        return [
            'totalSkillsFound' => $skillsCount,
            'yearsExperience' => $yearsExperience,
            'educationLevel' => $educationLevel,
            'extractionScore' => $extractionScore,
        ];
    }

    /**
     * GET talent-screening-results/candidate/{candidateId}
     * Ported from talent_screening_results_controller@show.
     */
    public function show(Request $request, $candidate_id)
    {
        $result = TalentScreeningResult::where('candidate_id', $candidate_id)
            ->where('sub_institute_id', $this->subInstituteId())
            ->first();

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Screening result not found'], 404);
        }

        $deepseek = $result->deepseek_analysis ?? [];

        $skill_match_details = [];
        foreach ($result->strengths ?? [] as $strength) {
            if (preg_match('/^(.+) \((.+)\)$/', $strength, $matches)) {
                $skill = trim($matches[1]);
                $prof = trim($matches[2]);
                $skill_match_details[] = [
                    'competency' => $skill,
                    'matched' => true,
                    'extractedSkill' => $skill,
                    'confidence' => 0.95,
                    'proficiency' => $prof,
                ];
            }
        }

        $matchData = $this->calculateMatchPercentage($candidate_id);

        $candidateData = DB::table('talent_job_applications')->where('id', $candidate_id)->first();

        $bertData = $this->parseCandidateDataForBERT($candidateData);

        $response = [
            'success' => true,
            'candidateId' => $result->candidate_id,
            'scoringPipeline' => [
                'bert_parsed_resume' => $bertData,
                'competency_scoring' => [
                    'overallFitScore' => $result->overall_fit_score,
                    'rankingScore' => $result->ranking_score,
                    'culturalFitIndex' => 78,
                    'matchedCompetencies' => 4,
                    'totalRequired' => 4,
                ],
                'deepseek_validation' => [
                    'competency_match' => $result->competency_match,
                    'cultural_fit' => $result->cultural_fit,
                    'predicted_success' => $result->predicted_success,
                    'summary' => $deepseek['summary'] ?? $result->recommendation,
                    'skill_gaps' => $result->skill_gaps ?? [],
                    'strengths' => $result->strengths ?? [],
                    'recommendation' => $result->recommendation,
                    'reasoning' => $deepseek['reasoning'] ?? '',
                ],
                'match_percentage' => $matchData ? $matchData['overall_match'] : 0,
                'detailed_matches' => $matchData ? [
                    'skills_match' => $matchData['skills_match'],
                    'education_match' => $matchData['education_match'],
                    'experience_match' => $matchData['experience_match'],
                ] : null,
            ],
            'competency_match' => $result->competency_match,
            'cultural_fit' => $result->cultural_fit,
            'predicted_success' => $result->predicted_success,
            'summary' => $deepseek['summary'] ?? $result->recommendation,
            'skill_gaps' => $result->skill_gaps ?? [],
            'strengths' => $result->strengths ?? [],
            'recommendation' => $result->recommendation,
            'ranking_score' => $result->ranking_score,
            'skill_match_details' => $skill_match_details,
        ];

        return response()->json($response);
    }
}
