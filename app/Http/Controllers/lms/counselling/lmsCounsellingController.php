<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingCourseModel;
use App\Models\lms\counselling\counsellingOnlineExamModel;
use App\Models\lms\counselling\OnetContentModelReference;
use App\Models\lms\counselling\OnetCareerCluster;
use App\Models\lms\counselling\OnetOccupationData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class lmsCounsellingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        /*echo("<pre>");
        print_r($data);
        echo("</pre>");
        die;*/
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['counselling_course'] = $data['courses'];
        $res['user_data'] = $data['final_user_data'];

        return is_mobile($type, 'lms/counselling/show_lmsCounselling', $res, "view");
    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $data['courses'] = counsellingCourseModel::select("counselling_course.*",
            DB::raw('count(q.`id`) as total_ques'))
            ->leftjoin('counselling_question_master as q', 'q.counselling_course_id', 'counselling_course.id')
            ->where(['counselling_course.sub_institute_id' => $sub_institute_id])
            ->groupby('counselling_course.id')
            ->orderby('counselling_course.sort_order')
            ->get()
            ->toArray();

        $data['final_user_data'] = [];
        $data['user_data'] = counsellingOnlineExamModel::select("counselling_online_exam.*",
            DB::raw('SUM(q.points) as total_points,count(q.id) as total_ques,DATE_FORMAT(created_at,"%Y-%m-%d") AS exam_date'))
            ->leftjoin('counselling_question_master as q', 'q.counselling_course_id',
                'counselling_online_exam.course_id')
            ->where([
                'counselling_online_exam.sub_institute_id' => $sub_institute_id,
                'counselling_online_exam.user_id' => $user_id,
            ])
            ->groupby('counselling_online_exam.id')
            ->get()
            ->toArray();

        foreach ($data['user_data'] as $key => $val) {
            $data['final_user_data'][$val['course_id']][] = $val;
        }

        return $data;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create(Request $request)
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit(Request $request, $id)
    {

    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy(Request $request, $id)
    {

    }

    public function lmsIndustryListing(Request $request)
    {
        $type = $request->input('type');

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/browse/');

            if ($response->successful()) {
                $data = $response->json();
                return view('lms/counselling/industry_listing', compact('data'));
                //return is_mobile($type, 'lms/counselling/demo_career_exam', ['data' => $data], "view");
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }

    public function careersInIndustry(Request $request, $id)
    {
        $type = $request->input('type');
        $allCareers = [];

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $nextPage = 'https://services.onetcenter.org/ws/mnm/browse/' . $id;

            while (!is_null($nextPage)) {
                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . $credentials,
                    'Accept' => 'application/json',
                ])->get($nextPage);

                if ($response->successful()) {
                    $data = $response->json();

                    // Add the careers from this page to the array
                    $allCareers = array_merge($allCareers, $data['career']);

                    // Check if there's a "next" link in the response
                    $nextLink = collect($data['link'])->firstWhere('rel', 'next');
                    $nextPage = $nextLink ? $nextLink['href'] : null;
                } else {
                    $statusCode = $response->status();
                    $errorMessage = $response->body();
                    break; // Exit the loop in case of an error
                }
            }
            return view('lms/counselling/career_in_industry', compact('allCareers'));
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }

    public function careerReport(Request $request, $id)
    {
        $type = $request->input('type');

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/careers/' . $id);

            if ($response->successful()) {
                $data = $response->json();

                return view('lms/counselling/career_report', compact('data', 'id'));
                //return is_mobile($type, 'lms/counselling/demo_career_exam', ['data' => $data], "view");
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }

    public function resources(Request $request, $id, $title)
    {
        $type = $request->input('type');

        try {
            $username = 'trizinnovation';
            $password = '4225aej';

            $credentials = base64_encode($username . ':' . $password);

            $url = 'https://services.onetcenter.org/ws/mnm/careers/' . urlencode($id) . '/' . strtolower($title);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                //    dd($data);
                return view('lms/counselling/career_report_resource', compact('data', 'id', 'title'));
                //return is_mobile($type, 'lms/counselling/demo_career_exam', ['data' => $data], "view");
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
        }
    }
    public function careerExplore()
    {
        // Fetch data from the OnetContentModelReference table
        $elements = OnetContentModelReference::whereNotNull('level')
            ->orderBy('element_id')
            ->get();

        // Fetch data from the onet_job_zone_reference table
        $jobZones = DB::table('onet_job_zone_reference')
        ->select('job_zone as element_id', 'name as element_name')
        ->get();

        // Function to build the nested structure
        function buildTree($elements, $parent_id = '', $level = 1)
        {
            $branch = [];
            foreach ($elements as $element) {
                if (substr($element->element_id, 0, strlen($parent_id)) === $parent_id && $element->level == $level) {
                    $children = buildTree($elements, $element->element_id . '.', $level + 1);
                    $elementData = [
                        'level' => $element->level,
                        'element_id' => $element->element_id,
                        'element_name' => $element->element_name,
                        'element_type' => $element->type,
                    ];
                    if (!empty($children)) {
                        $elementData['children'] = $children;
                    }
                    $branch[] = $elementData;
                }
            }
            return $branch;
        }

        // Build the tree structure starting with the top-level elements (level 1)
        $result = buildTree($elements);

        // Build the required JSON structure for job zones
        $jobZonesJson = [
            'level' => 1,
            'element_id' => '',
            'element_name' => 'Job Zone',
            'element_type' => 'job_zones',
            'children' => []
        ];

        foreach ($jobZones as $jobZone) {
            $jobZonesJson['children'][] = [
                'level' => 2,
                'element_id' => $jobZone->element_id,
                'element_name' => $jobZone->element_name,
                'element_type' => 'job_zones',
            ];
        }

        // Append the job zones structure to the result array
        $result[] = $jobZonesJson;

        // Return the final JSON response
        return response()->json($result);
    }
    public function careerExploreResult(Request $request)
    {
        // Get query parameters
        $abilities = $request->input('abilities');
        $interests = $request->input('interests');
        $knowledge = $request->input('knowledge');
        $basic_skills = $request->input('basic_skills');
        $cross_skills = $request->input('cross_skills');
        $work_styles = $request->input('work_styles');
        $work_values = $request->input('work_values');
        $job_zones = $request->input('job_zones');

        // Build the initial query
        $query = DB::table('onet_occupation_data as od')
            ->select('od.onetsoc_code', 'od.title', 'od.description');

        // Add conditional joins
        if ($abilities) {
            $abilitiesArray = explode(',', $abilities);
            $query->join('onet_abilities as a', function ($join) use ($abilitiesArray) {
                $join->on('a.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('a.scale_id', '=', 'LV')
                    ->where(function ($query) use ($abilitiesArray) {
                        foreach ($abilitiesArray as $ability) {
                            $query->orWhere('a.element_id', 'LIKE', "$ability%");
                        }
                    });
            });
        }

        if ($interests) {
            $interestsArray = explode(',', $interests);
            $query->join('onet_interests as i', function ($join) use ($interestsArray) {
                $join->on('i.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('i.scale_id', '=', 'OI')
                    ->where(function ($query) use ($interestsArray) {
                        foreach ($interestsArray as $interest) {
                            $query->orWhere('i.element_id', 'LIKE', "$interest%");
                        }
                    });
            });
        }

        if ($knowledge) {
            $knowledgeArray = explode(',', $knowledge);
            $query->join('onet_knowledge as k', function ($join) use ($knowledgeArray) {
                $join->on('k.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('k.scale_id', '=', 'LV')
                    ->where(function ($query) use ($knowledgeArray) {
                        foreach ($knowledgeArray as $know) {
                            $query->orWhere('k.element_id', 'LIKE', "$know%");
                        }
                    });
            });
        }

        if ($basic_skills) {
            $basic_skillsArray = explode(',', $basic_skills);
            $query->join('onet_skills as bs', function ($join) use ($basic_skillsArray) {
                $join->on('bs.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('bs.scale_id', '=', 'LV')
                    ->where(function ($query) use ($basic_skillsArray) {
                        foreach ($basic_skillsArray as $basic_skill) {
                            $query->orWhere('bs.element_id', 'LIKE', "$basic_skill%");
                        }
                    });
            });
        }

        if ($cross_skills) {
            $cross_skillsArray = explode(',', $cross_skills);
            $query->join('onet_skills as cs', function ($join) use ($cross_skillsArray) {
                $join->on('cs.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('cs.scale_id', '=', 'LV')
                    ->where(function ($query) use ($cross_skillsArray) {
                        foreach ($cross_skillsArray as $cross_skill) {
                            $query->orWhere('cs.element_id', 'LIKE', "$cross_skill%");
                        }
                    });
            });
        }

        if ($work_styles) {
            $workStylesArray = explode(',', $work_styles);
            $query->join('onet_work_styles as ws', function ($join) use ($workStylesArray) {
                $join->on('ws.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('ws.scale_id', '=', 'IM')
                    ->where(function ($query) use ($workStylesArray) {
                        foreach ($workStylesArray as $workStyle) {
                            $query->orWhere('ws.element_id', 'LIKE', "$workStyle%");
                        }
                    });
            });
        }

        if ($work_values) {
            $workValuesArray = explode(',', $work_values);
            $query->join('onet_work_values as wv', function ($join) use ($workValuesArray) {
                $join->on('wv.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where('wv.scale_id', '=', 'EX')
                    ->where(function ($query) use ($workValuesArray) {
                        foreach ($workValuesArray as $workValue) {
                            $query->orWhere('wv.element_id', 'LIKE', "$workValue%");
                        }
                    });
            });
        }

        if ($job_zones) {
            $jobZonesArray = explode(',', $job_zones);
            $query->join('onet_job_zones as jz', function ($join) use ($jobZonesArray) {
                $join->on('jz.onetsoc_code', '=', 'od.onetsoc_code')
                    ->where(function ($query) use ($jobZonesArray) {
                        foreach ($jobZonesArray as $jobZone) {
                            $query->orWhere('jz.job_zone', 'LIKE', "$jobZone%");
                        }
                    });
            });
        }

        // Group by onetsoc_code and get the results
        $results = $query->groupBy('od.onetsoc_code')->get();

        // Return JSON response
        return response()->json($results);
    }
    public function careerCluster()
    {
        // Fetch data from the database
        $careers = OnetCareerCluster::all();

        // Group by career_cluster and career_pathway
        $groupedCareers = [];
        foreach ($careers as $career) {
            if (!isset($groupedCareers[$career->career_cluster])) {
                $groupedCareers[$career->career_cluster] = [
                    'career_id' => $career->career_id,
                    'career_cluster' => $career->career_cluster,
                    'image' => $career->image,
                    'children' => []
                ];
            }

            $pathwayExists = false;
            foreach ($groupedCareers[$career->career_cluster]['children'] as &$pathway) {
                if ($pathway['career_pathway'] === $career->career_pathway) {
                    $pathway['children'][] = [
                        'onetsoc_code' => $career->onetsoc_code,
                        'title' => $career->title,
                        'description' => $career->description
                    ];
                    $pathwayExists = true;
                    break;
                }
            }

            if (!$pathwayExists) {
                $groupedCareers[$career->career_cluster]['children'][] = [
                    'career_pathway' => $career->career_pathway,
                    'image' => $career->image,
                    'children' => [
                        [
                            
                            'onetsoc_code' => $career->onetsoc_code,
                            'title' => $career->title,
                            'description' => $career->description
                        ]
                    ]
                ];
            }
        }

        // Return the final JSON response
        return response()->json(array_values($groupedCareers));
    }
    public function allOccupation(Request $request)
    {
        // Retrieve the 'title' parameter from the request
        $title = $request->input('title', 'all'); // Default to 'all' if the parameter is not provided

        // Start the query
        $query = OnetOccupationData::query();

        // If 'title' parameter is provided and not 'all', add a where clause
        if ($title !== 'all') {
            $query->where('title', 'LIKE', "%{$title}%");
        }

        // Order the results by 'title'
        $results = $query->orderBy('title')->get();

        // Return the JSON response
        return response()->json($results)
                        ->header('Access-Control-Allow-Origin', '*');
    }
    public function OccupationDetails(Request $request)
    {
        $onetSocCode = $request->input('onetsoc_code');

        $data = [
            [
                "level" => 1,
                "main_id" => 1,
                "main_title" => "Worker Characteristics",
                "main_description" => "Enduring characteristics that may influence both performance and the capacity to acquire knowledge and skills required for effective work performance. Worker characteristics comprise enduring qualities of individuals that may influence how they approach tasks and how they acquire work-relevant knowledges and skills. Traditionally, analyzing abilities has been the most common technique for comparing jobs in terms of these worker characteristics. However, recent research supports the inclusion of other types of worker characteristics. In particular, interests, values, and work styles have received support in the organizational literature. Interests and values reflect preferences for work environments and outcomes. Work style variables represent typical procedural differences in the way work is performed.",
                "children" => [
                    $this->getAbilities($onetSocCode),
                    $this->getInterests($onetSocCode),
                    $this->getWorkvalues($onetSocCode),
                    $this->getWorkstyles($onetSocCode)
                ]
            ],
            [
                "level" => 1,
                "main_id" => 2,
                "main_title" => "Worker Requirements",
                "main_description" => "Worker Requirements...",
                "children" => [
                    $this->getKnowledge($onetSocCode),
                    $this->getSkills($onetSocCode)
                ]
            ],
            [
                "level" => 1,
                "main_id" => 3,
                "main_title" => "Experience Requirements",
                "main_description" => "Experience Requirements...",
                "children" => [

                    ]
            ],
            [
                "level" => 1,
                "main_id" => 4,
                "main_title" => "Occupational Requirements",
                "main_description" => "Occupational Requirements...",
                "children" => [
                    $this->getWorkactivities($onetSocCode),
                    $this->getWorkcontext($onetSocCode)
                ]
            ],
            [
                "level" => 1,
                "main_id" => 5,
                "main_title" => "Work Force Characterstics",
                "main_description" => "Work Force Characterstics...",
                "children" => [
                    
                ]
            ],
            [
                "level" => 1,
                "main_id" => 6,
                "main_title" => "Occupation-Specific Information",
                "main_description" => "Occupation-Specific Information...",
                "children" => [
                    $this->getTasks($onetSocCode),
                    $this->getTechskills($onetSocCode),
                    $this->getToolsused($onetSocCode)
                ]
            ],
            // Add other main categories similarly
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    private function getAbilities($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_abilities a ON omr.element_id = a.element_id AND a.scale_id = 'LV'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Abilities",
            "sub_description" => "Enduring attributes of the individual that influence performance",
            "children" => $children
        ];
    }
    
    private function getInterests($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_interests a ON omr.element_id = a.element_id AND a.scale_id = 'OI'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Interests",
            "sub_description" => "Preferences for work environments. Occupational Interest Profiles (OIPs) are compatible with Holland's (1997) model of personality types and work environments. Six interest categories are used to describe the work environment of occupations: Realistic, Investigative, Artistic, Social, Enterprising, and Conventional. An OIP consists of six numerical scores indicating how descriptive and characteristic each work environment (or interest area) is for an O*NET-SOC occupation. In addition, a high-point profile has been assigned indicating which interests are most characteristic of an O*NET-SOC occupation. A high-point profile consists of one to three interest codes, depending on how many interest categories meet a minimum degree of descriptiveness for the O*NET-SOC occupation.",
            "children" => $children
        ];
    }
    private function getWorkvalues($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_work_values a ON omr.element_id = a.element_id AND a.scale_id = 'EX'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Work Values",
            "sub_description" => "Occupational Reinforcer Patterns (ORPs) indicate which work values and needs are likely to be reinforced or satisfied by a particular O*NET-SOC occupation. The use of work values to describe occupations is based on the Theory of Work Adjustment (TWA) developed during the Work Adjustment Project at the University of Minnesota under Research Grants from the U.S. Department of Health, Education and Welfare (Dawis, R.V., England, G.W., & Lofquist, L.H., 1964; Dawis, R.V., & Lofquist, L.H., 1984). This theory proposes that job satisfaction is directly related to the degree to which a person's values and corresponding needs are satisfied by his or her work environment. The TWA identifies six work values each with a corresponding set of needs.",
            "children" => $children
        ];
    }
    
    private function getWorkstyles($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_work_styles a ON omr.element_id = a.element_id AND a.scale_id = 'IM'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Work Styles",
            "sub_description" => "Personal characteristics that can affect how well someone performs a job.",
            "children" => $children
        ];
    }
    
    private function getKnowledge($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_knowledge a ON omr.element_id = a.element_id AND a.scale_id = 'LV'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Knowledge",
            "sub_description" => "Organized sets of principles and facts applying in general domains",
            "children" => $children
        ];
    }
    private function getSkills($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_skills a ON omr.element_id = a.element_id AND a.scale_id = 'LV'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Skills",
            "sub_description" => "Developed capacities that facilitate learning or the more rapid acquisition of knowledge",
            "children" => $children
        ];
    }
    private function getWorkactivities($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_work_activities a ON omr.element_id = a.element_id AND a.scale_id = 'LV'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Work Activities",
            "sub_description" => "Work activities that are common across a very large number of occupations. They are performed in almost all job families and industries.",
            "children" => $children
        ];
    }
    private function getWorkcontext($onetSocCode) {
        $results = DB::select("
            SELECT omr.element_name, omr.description, ROUND((100 * a.data_value / sr.maximum),0) AS percentage
            FROM onet_content_model_reference omr
            INNER JOIN onet_work_context a ON omr.element_id = a.element_id AND a.scale_id='CX'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = a.scale_id
            WHERE a.onetsoc_code = ?
            GROUP BY a.onetsoc_code,a.element_id
            ORDER BY a.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Work Context",
            "sub_description" => "Physical and social factors that influence the nature of work",
            "children" => $children
        ];
    }
    private function getTasks($onetSocCode) {
        $results = DB::select("
        SELECT CONCAT(LEFT(CONCAT('[', ts.task_type, '] ', ts.task), 30),'...') AS element_name,ts.task as description, ROUND((100 * tr.data_value / sr.maximum),0) AS percentage
            FROM onet_task_statements ts
            INNER JOIN onet_task_ratings tr ON ts.task_id = tr.task_id AND tr.scale_id='IM'
            INNER JOIN onet_scales_reference sr ON sr.scale_id = tr.scale_id
            WHERE tr.onetsoc_code = ?
            ORDER BY tr.data_value DESC
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description,
                "percentage" => $result->percentage
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Tasks",
            "sub_description" => "Occupation-Specific Tasks",
            "children" => $children
        ];
    }
    private function getTechskills($onetSocCode) {
        $results = DB::select("
        SELECT ur.commodity_title AS element_name,GROUP_CONCAT(DISTINCT ts.example ORDER BY ts.example ASC SEPARATOR '; ') AS description, 0 AS percentage 
            FROM onet_technology_skills ts
            INNER JOIN onet_unspsc_reference ur ON ur.commodity_code=ts.commodity_code
            WHERE ts.onetsoc_code = ?
            GROUP BY ts.commodity_code
            ORDER BY ur.commodity_title
        ", [$onetSocCode]);

        
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Technology Skills",
            "sub_description" => "Information technology and software skills essential to the functions of an occupational role.",
            "children" => $children
        ];
    }
    private function getToolsused($onetSocCode) {
        $results = DB::select("
        SELECT ur.commodity_title AS element_name,ts.example AS description, 0 AS percentage 
            FROM onet_tools_used ts
            INNER JOIN onet_unspsc_reference ur ON ur.commodity_code=ts.commodity_code
            WHERE ts.onetsoc_code = ?
            ORDER BY ur.commodity_title
        ", [$onetSocCode]);
    
        $children = [];
        foreach ($results as $result) {
            $children[] = [
                "level" => 3,
                "element_name" => $result->element_name,
                "description" => $result->description
            ];
        }
    
        return [
            "level" => 2,
            "sub_title" => "Tools Used",
            "sub_description" => "Machines, equipment, and tools essential to the performance of an occupational role.",
            "children" => $children
        ];
    }
}
