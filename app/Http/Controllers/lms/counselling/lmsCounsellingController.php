<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingCourseModel;
use App\Models\lms\counselling\counsellingOnlineExamModel;
use App\Models\lms\counselling\OnetContentModelReference;
use App\Models\lms\counselling\OnetCareerCluster;
use App\Models\lms\counselling\OnetEmployer;
use App\Models\lms\counselling\OnetInstitutes;
use App\Models\lms\counselling\OnetOccupationData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use App\Traits\Constants;

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
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

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
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

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
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

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
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

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

        // Initialize the base structure of the JSON response
        $allOccupationJson = [
            'level' => 1,
            'element_id' => '',
            'element_name' => 'All Occupation',
            'element_type' => 'occupation',
            'children' => [[
                    'level' => 2,
                    'element_id' => 'all',
                    'element_name' => 'All Occupation',
                    'element_type' => 'occupation'
            ]]
        ];
        $result[] = $allOccupationJson;

        // Fetch data from the database
        $industries = DB::table('o_net_data_sub_categories as a')
            ->select('a.id as element_id', 'a.sub_category_name as element_name')
            ->where('a.o_net_data_category_id', 12)
            ->get();

        // Initialize the base structure of the JSON response
        $industriesJson = [
            'level' => 1,
            'element_id' => '',
            'element_name' => 'Industries',
            'element_type' => 'industries',
            'children' => []
        ];

        // Loop through the database result and format it
        foreach ($industries as $industry) {
            $industriesJson['children'][] = [
                'level' => 2,
                'element_id' => $industry->element_id,
                'element_name' => $industry->element_name,
                'element_type' => 'industries'
            ];
        }
        // Append the Industries structure to the result array
        $result[] = $industriesJson;

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
        $industries = $request->input('industries');
        $occupation = $request->input('occupation');

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

        if ($industries) {
            // Explode the industries string into an array
            $industriesArray = explode(',', $industries);

            // Join the 'o_net_data_tables' table on 'od.onetsoc_code' and 'dt.code'
            $query->join('o_net_data_tables as dt', 'dt.code', '=', 'od.onetsoc_code')
                  ->whereIn('dt.o_net_sub_category_id', $industriesArray);
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
    public function getInstituteData1()
    {
        // Fetch the data using an inner join
        $institutes = DB::table('onet_institute_data as oid')
            ->leftJoin('onet_institute_courses as oic', 'oic.institute_id', '=', 'oid.id')
            ->select(
                'oid.id',
                'oid.college_name',
                'oid.description',
                'oid.aicte_id',
                'oid.type',
                'oid.level',
                'oid.address',
                'oid.district',
                'oid.state',
                'oid.image',
                'oid.women',
                'oid.minority',
                'oic.id as course_id',
                'oic.institute_id',
                'oic.aicte_id as course_aicte_id',
                'oic.college_name as course_college_name',
                'oic.description as course_description',
                'oic.programme',
                'oic.university',
                'oic.course_level',
                'oic.course_name',
                'oic.course_type',
                'oic.course_fees',
                'oic.intake',
                'oic.enrollment',
                'oic.placement'
            )
            ->inRandomOrder()
            ->limit(20)
            ->get();

        // Group courses by institute
        $result = [];
        foreach ($institutes as $institute) {
            $instituteId = $institute->id;
            if (!isset($result[$instituteId])) {
                $result[$instituteId] = [
                    'id' => $institute->id,
                    'college_name' => $institute->college_name,
                    'description' => $institute->description,
                    'aicte_id' => $institute->aicte_id,
                    'type' => $institute->type,
                    'level' => $institute->level,
                    'address' => $institute->address,
                    'district' => $institute->district,
                    'state' => $institute->state,
                    'image' => $institute->image,
                    'women' => $institute->women,
                    'minority' => $institute->minority,
                    'course_data' => [],
                ];
            }

            // Check if the course has meaningful data
            $courseData = [
                'id' => $institute->course_id,
                'institute_id' => $institute->institute_id,
                'aicte_id' => $institute->course_aicte_id,
                'college_name' => $institute->course_college_name,
                'description' => $institute->course_description,
                'programme' => $institute->programme,
                'university' => $institute->university,
                'course_level' => $institute->course_level,
                'course_name' => $institute->course_name,
                'course_type' => $institute->course_type,
                'course_fees' => $institute->course_fees,
                'intake' => $institute->intake,
                'enrollment' => $institute->enrollment,
                'placement' => $institute->placement,
            ];

            // Filter out null values and add course data if it has meaningful data
            if (array_filter($courseData)) {
                $result[$instituteId]['course_data'][] = $courseData;
            }
        }

        // Remove 'course_data' key if it's empty
        foreach ($result as &$institute) {
            if (empty($institute['course_data'])) {
                unset($institute['course_data']);
            }
        }

        // Return the result as a JSON response
        return response()->json(array_values($result));
    }
    public function getInstituteData()
    {
        //$institutes = OnetInstitutes::all();
        $institutes = OnetInstitutes::inRandomOrder()->limit(200)->get();
        return response()->json($institutes);
    }

    public function getCourseData()
    {
        // First query to group institutes by course
        $courses = DB::table('onet_institute_courses as oic')
        ->selectRaw('GROUP_CONCAT(DISTINCT oic.institute_id) as institute_id, oic.aicte_id, oic.college_name, oic.description, oic.programme, oic.university, oic.course_level, oic.course_name, oic.course_type, oic.course_fees, oic.intake, oic.enrollment, oic.placement')
        ->groupBy('oic.course_name')
        ->inRandomOrder()
        ->limit(20)
        ->get();

    // Initialize an empty array to store the final result
    $result = [];

    foreach ($courses as $course) {
        // Convert institute_id string to an array
        $instituteIds = explode(',', $course->institute_id);

        // Second query to get institute data for the selected IDs
        $instituteData = DB::table('onet_institute_data as oid')
            ->select('oid.id', 'oid.college_name', 'oid.description', 'oid.aicte_id', 'oid.type', 'oid.level', 'oid.address', 'oid.district', 'oid.state', 'oid.image', 'oid.women', 'oid.minority')
            ->whereIn('oid.id', $instituteIds) // Update as needed to dynamically filter by relevant institute IDs
            ->get();

        // Add course details and associated institute data to the result
        $result[] = [
            'institute_id' => $course->institute_id,
            'description' => $course->description,
            'programme' => $course->programme,
            'course_level' => $course->course_level,
            'course_name' => $course->course_name,
            'course_type' => $course->course_type,
            'course_fees' => $course->course_fees,
            'institute_data' => $instituteData
        ];
    }

    // Return the result as a JSON response
    return response()->json($result);
    }
    public function getEmployerData()
    {
        $employers = OnetEmployer::all();
        return response()->json($employers);
    }
    public function ExploreSector(Request $request)
    {
        $title = $request->input('title');

        // Fetch data from the database using the query builder
        $sectors = DB::table('onet_explore_sector')
            ->where('title', $title)
            ->get();

        // Group data by title
        $response = $sectors->groupBy('title')->map(function ($items) {
            return [
                'title' => $items->first()->title,
                'image' => $items->first()->image,
                'data' => $items->map(function ($item) {
                    return [
                        'key' => $item->key,
                        'value' => $item->value,
                        'html' => $item->html,
                    ];
                })->toArray(),
            ];
        })->values()->first();

        return response()->json($response);
    }
    public function ExpertAdvice(Request $request)
    {
        $title = $request->input('title');

        // Fetch data from the database using the query builder
        $sectors = DB::table('onet_expert_advice')
            ->where('title', $title)
            ->get();

        // Group data by title
        $response = $sectors->groupBy('title')->map(function ($items) {
            return [
                'title' => $items->first()->title,
                'data' => $items->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'description' => $item->description,
                        'image' => $item->image,
                        'education' => $item->education,
                        'city' => $item->city,
                        'state' => $item->state,
                        'contact_no' => $item->contact_no,
                        'teacher' => $item->teacher,
                        'benefits' => $item->benefits,
                        'university_shortlist' => $item->university_shortlist,
                    ];
                })->toArray(),
            ];
        })->values()->first();

        return response()->json($response);
    }
    public function intrestQuestions(Request $request)
    {
        $start = $request->input('start') ?? 1;
        $end = $request->input('end') ?? 60;

        try {
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

            $credentials = base64_encode($username . ':' . $password);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/interestprofiler/questions', [
                'start' => $start,
                'end' => $end,
            ]);

            if ($response->successful()) {
                $data = $response->json(); // Extract the JSON data from the response
                return response()->json($data); // Return the JSON data
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
                return response()->json(['error' => $errorMessage], $statusCode); // Return the error message with status code
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
    public function intrestResults(Request $request)
    {
        $answers = $request->input('answers');

        try {
            // Fetch credentials from app\Traits\Constants.php
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

            // Encode credentials for Basic Auth
            $credentials = base64_encode($username . ':' . $password);

            // Make the API request
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/interestprofiler/results', [
                'answers' => $answers
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                return response()->json($response->json()); // Return the JSON data
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
                return response()->json(['error' => $errorMessage], $statusCode); // Return the error message with status code
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
    public function intrestJobzone()
    {
        try {
            // Fetch credentials from app\Traits\Constants.php
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

            // Encode credentials for Basic Auth
            $credentials = base64_encode($username . ':' . $password);

            // Make the API request
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/interestprofiler/job_zones');

            // Check if the response is successful
            if ($response->successful()) {
                return response()->json($response->json()); // Return the JSON data
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
                return response()->json(['error' => $errorMessage], $statusCode); // Return the error message with status code
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
    public function intrestCareers(Request $request)
    {
        $answers = $request->input('answers');
        $job_zone = $request->input('job_zone');

        try {
            // Fetch credentials from app\Traits\Constants.php
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

            // Encode credentials for Basic Auth
            $credentials = base64_encode($username . ':' . $password);

            // Make the API request
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/interestprofiler/careers', [
                'answers' => $answers,
                'job_zone' => $job_zone
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                return response()->json($response->json()); // Return the JSON data
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
                return response()->json(['error' => $errorMessage], $statusCode); // Return the error message with status code
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
    public function intrestEnterScore(Request $request)
    {
        $Realistic = $request->input('Realistic') ?? 0;
        $Investigative = $request->input('Investigative') ?? 0;
        $Artistic = $request->input('Artistic') ?? 0;
        $Social = $request->input('Social') ?? 0;
        $Enterprising = $request->input('Enterprising') ?? 0;
        $Conventional = $request->input('Conventional') ?? 0;
        $job_zone = $request->input('job_zone') ?? 5;
        $end = $request->input('end') ?? 1000;
        
        try {
            // Fetch credentials from app\Traits\Constants.php
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

            // Encode credentials for Basic Auth
            $credentials = base64_encode($username . ':' . $password);

            // Make the API request
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/interestprofiler/careers', [
                'Realistic' => $Realistic,
                'Investigative' => $Investigative,
                'Artistic' => $Artistic,
                'Social' => $Social,
                'Enterprising' => $Enterprising,
                'Conventional' => $Conventional,
                'job_zone' => $job_zone,
                'end' => $end,
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                return response()->json($response->json()); // Return the JSON data
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
                return response()->json(['error' => $errorMessage], $statusCode); // Return the error message with status code
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
    public function intrestArea(Request $request)
    {
        $area = $request->input('area') ?? 'Realistic';
        $job_zone = $request->input('job_zone') ?? 5;
        $end = $request->input('end') ?? 1000;

        try {
            // Fetch credentials from app\Traits\Constants.php
            $username = Constants::ONET_USERNAME;
            $password = Constants::ONET_PASSWORD;

            // Encode credentials for Basic Auth
            $credentials = base64_encode($username . ':' . $password);

            // Make the API request
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Accept' => 'application/json',
            ])->get('https://services.onetcenter.org/ws/mnm/interestprofiler/careers', [
                'area' => $area,
                'job_zone' => $job_zone,
                'end' => $end,
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                return response()->json($response->json()); // Return the JSON data
            } else {
                $statusCode = $response->status();
                $errorMessage = $response->body();
                return response()->json(['error' => $errorMessage], $statusCode); // Return the error message with status code
            }
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
    public function matchProfile(Request $request)
    {
        try {
            $response = [
            "interest_profile" => [
                [
                    "Realistic" => 25,
                    "Investigative" => 40,
                    "Artistic" => 20,
                    "Social" => 35,
                    "Enterprising" => 24,
                    "Conventional" => 35,
                    "job_zone" => "1",
                ]
            ],
            "exist_student_profile" => [
                [
                    "student_id" => 97382,
                    "name" => "Evaan Rajesh Rafaliya",
                    "data" => [
                        [
                            "standard" => 8,
                            "interests" => [
                                ["type" => "Interests", "element_id" => "1.B.1.a", "name" => "Realistic"],
                                ["type" => "Interests", "element_id" => "1.B.1.b", "name" => "Investigative"],
                                ["type" => "Interests", "element_id" => "1.B.1.c", "name" => "Artistic"],
                            ],
                            "basic_skills" => [
                                ["type" => "Skills", "element_id" => "2.A.1.a", "name" => "Reading Comprehension"],
                                ["type" => "Skills", "element_id" => "2.A.1.b", "name" => "Active Listening"],
                                ["type" => "Skills", "element_id" => "2.A.1.c", "name" => "Writing"],
                            ],
                            "knowledge" => [
                                ["type" => "Knowledge", "element_id" => "2.C.1.a", "name" => "Administration and Management"],
                                ["type" => "Knowledge", "element_id" => "2.C.1.b", "name" => "Administrative"],
                                ["type" => "Knowledge", "element_id" => "2.C.1.c", "name" => "Economics and Accounting"],
                            ],
                            "abilities" => [
                                ["type" => "Abilities", "element_id" => "1.A.1.a.1", "name" => "Oral Comprehension"],
                                ["type" => "Abilities", "element_id" => "1.A.1.a.3", "name" => "Oral Expression"],
                                ["type" => "Abilities", "element_id" => "1.A.1.a.2", "name" => "Written Comprehension"],
                            ]
                        ],
                        [
                            "standard" => 7,
                            "interests" => [
                                ["type" => "Interests", "element_id" => "1.B.1.a", "name" => "Realistic"],
                                ["type" => "Interests", "element_id" => "1.B.1.b", "name" => "Investigative"],
                                ["type" => "Interests", "element_id" => "1.B.1.c", "name" => "Artistic"],
                            ],
                            "basic_skills" => [
                                ["type" => "Skills", "element_id" => "2.A.1.a", "name" => "Reading Comprehension"],
                                ["type" => "Skills", "element_id" => "2.A.1.b", "name" => "Active Listening"],
                                ["type" => "Skills", "element_id" => "2.A.1.c", "name" => "Writing"],
                            ],
                            "knowledge" => [
                                ["type" => "Knowledge", "element_id" => "2.C.1.a", "name" => "Administration and Management"],
                                ["type" => "Knowledge", "element_id" => "2.C.1.b", "name" => "Administrative"],
                                ["type" => "Knowledge", "element_id" => "2.C.1.c", "name" => "Economics and Accounting"],
                            ],
                            "abilities" => [
                                ["type" => "Abilities", "element_id" => "1.A.1.a.1", "name" => "Oral Comprehension"],
                                ["type" => "Abilities", "element_id" => "1.A.1.a.3", "name" => "Oral Expression"],
                                ["type" => "Abilities", "element_id" => "1.A.1.a.2", "name" => "Written Comprehension"],
                            ]
                        ],
                        // Add the same structure for other standards as per the example provided
                    ]
                ]
            ]
        ];
        return response()->json($response);
        } catch (RequestException $exception) {
            $errorMessage = $exception->getMessage();
            return response()->json(['error' => $errorMessage], 500); // Return the exception message with a 500 status code
        }
    }
}
