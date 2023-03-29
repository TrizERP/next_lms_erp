<?php

namespace App\Http\Controllers\front_desk\school_detail;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class schooldetailController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }
        $school_data['type_arr'] = $this->getTypeArr($request);

        $school_data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "front_desk/schooldetail/show", $school_data, "view");
    }

    function getData()
    {

        return DB::table("school_detail as c")
            ->where("c.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->get()->toArray();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['type_arr'] = $this->getTypeArr($request);

        $data = json_decode(json_encode($data), false);

        return is_mobile($type, 'front_desk/schooldetail/add', $data, "view");
    }

    public function getTypeArr($request)
    {
        $type_arr = [];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        //START Check for existing records

        $data = DB::table("school_detail")
            ->selectRaw('GROUP_CONCAT(DISTINCT(type)) AS types')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->get()->toArray();

        $existing_type = [];
        if ($data[0]->types != "") {
            $existing_type = explode(",", $data[0]->types);
        }

        $type_arr = [
            "About Us"           => "About Us",
            "School Information" => "School Information",
            "School Timing"      => "School Timing",
            "Achievement"        => "Achievement",
            "Principal Desk"     => "Principal Desk",
            "Rules"              => "Rules",
            "Facility"           => "Facility",
            "Academic Activity"  => "Academic Activity",
            "Reach Us"           => "Reach Us",
        ];

        return array_diff($type_arr, $existing_type);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {

        $values = [
            'title'            => $_REQUEST['message'],
            'type'             => $_REQUEST['type'],
            'sub_institute_id' => session()->get('sub_institute_id'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
        DB::table('school_detail')->insert($values);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "schooldetail.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = DB::table("school_detail")
            ->where("id", "=", $id)
            ->get()->toArray();

        $data = $data[0];

        return is_mobile($type, "front_desk/schooldetail/add", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $finalArr = [
            'title'      => $_REQUEST['message'],
            'updated_at' => now(),
        ];
        DB::table("school_detail")->where(['id' => $id])->update($finalArr);

        $res = [
            "status_code" => 1,
            "message"     => "School Detail Updated Successfully",
        ];

        return is_mobile($type, "schooldetail.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('school_detail')->where(["Id" => $id])->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "schooldetail.index", $res, "redirect");
    }

    public function schoolDetailAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $action = ucfirst($request->input("action"));
        $sub_institute_id = $request->input("sub_institute_id");

        if ($action != "" && $sub_institute_id != "") {
            $data = DB::table("school_detail")
                ->selectRaw('type as title,title as description')
                ->where("sub_institute_id", "=", $sub_institute_id)
                ->where("type", "=", $action)
                ->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

}
