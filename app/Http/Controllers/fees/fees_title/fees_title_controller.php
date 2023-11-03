<?php

namespace App\Http\Controllers\fees\fees_title;

use App\Http\Controllers\Controller;
use App\Models\fees\fees_title\fees_title;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use function App\Helpers\is_mobile;

//use Illuminate\Http\Request;

class fees_title_controller extends Controller
{

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

        $school_data['data'] = $this->getData();
//        $school_data['data'] = array();
        $type = $request->input('type');
        return is_mobile($type, "fees/fees_title/show", $school_data, "view");
    }

    public function getData()
    {
        $data = fees_title::
        select('id', 'display_name', 'sort_order','cumulative_name', 'append_name', 'mandatory', 'syear', 'other_fee_id')
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear')
            ])->OrderBy('display_name','ASC')->get()->toArray();
        $responce_arr = array();
        if (count($data) > 0) {
            foreach ($data as $id => $arr) {
                if ($arr['mandatory'] == '1') {
                    $arr['mandatory'] = 'Yes';
                } else {
                    $arr['mandatory'] = 'No';
                }
                if ($arr['other_fee_id'] == '0') {
                    $arr['other_fee_id'] = 'Regular Fee';
                } else {
                    $arr['other_fee_id'] = 'Other Fee';
                }
                $responce_arr[$id] = $arr;
            }
        }
//        echo "<pre>";
//        print_r($responce_arr);
//        exit;

        return $responce_arr;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
//        $dataStore = array();
        $dataStore['data']['ddTtitle'] = $this->ddTtitle();
        return is_mobile($type, 'fees/fees_title/add', $dataStore, "view");
    }

    public function ddTtitle()
    {
        $std_div_map = DB::table('fees_title_master')
            ->select('fees_title_master.title', 'fees_title_master.id')
            ->pluck('title', 'id');
        $data = fees_title::
        select('fees_title', 'fees_title_id')
            ->where([
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear')
            ])->get()->toArray();

        foreach ($data as $id => $arr) {
            if ($arr["fees_title_id"] == 1) {
                continue;
            } else {
                unset($std_div_map[$arr["fees_title_id"]]);
            }
        }

        return $std_div_map;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function store(Request $request)
    {

        // logic if it was other fee
        if ($request->get('fees_title_id') == 1) {
            $sub_institute_id = session()->get('sub_institute_id');
            $id = DB::select(DB::raw("SELECT ifnull(max(other_fee_id),0) max_id FROM fees_title WHERE sub_institute_id = '$sub_institute_id'"));
            $id = $id[0]->max_id + 1;

            //checking if coloum exist or not
            $columns = Schema::getColumnListing('fees_paid_other');
            if (!in_array($id, $columns)) {
                $type = "decimal";
                $length = "5";
                $fieldName = $id;
                Schema::table('fees_paid_other', function ($table) use ($type, $length, $fieldName) {
                    $table->$type($fieldName, $length);
                });
            }

            $mandatory = $request->get('mandatory');
            $mandatory_val = isset($mandatory) ? $mandatory : 0;

            $exam = new fees_title([
                'fees_title_id' => $request->get('fees_title_id'),
                'fees_title' => $id,
                'display_name' => $request->get('display_name'),
                'sort_order'  => $request->get('sort_order'),
                'cumulative_name' => $request->get('cumulative_name'),
                'append_name' => $request->get('append_name'),
                'mandatory' => $mandatory_val,
                'syear' => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'other_fee_id' => $id,
            ]);
            // dd($exam);
            $exam->save();
        } else {
            $fees_title_id = $request->get('fees_title_id');
            $fees_title = DB::select(DB::raw("
                    SELECT fee_paid_title
                    FROM fees_title_master
                    WHERE id = '$fees_title_id'"));
            $fees_title = $fees_title[0]->fee_paid_title;

            $mandatory = $request->get('mandatory');
            $mandatory_val = isset($mandatory) ? $mandatory : 0;
            $exam = new fees_title([
                'fees_title_id' => $request->get('fees_title_id'),
                'fees_title' => $fees_title,
                'display_name' => $request->get('display_name'),
                'sort_order'  => $request->get('sort_order'),
                'cumulative_name' => $request->get('cumulative_name'),
                'append_name' => $request->get('append_name'),
                'mandatory' => $mandatory_val,
                'syear' => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'other_fee_id' => 0,
            ]);
            // dd($exam);
            $exam->save();
        }
        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return is_mobile($type, "fees_title.index", $res, "redirect");
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
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = fees_title::find($id)->toArray();
        $data['data']['ddTtitle'] = $this->ddTtitle();
        return is_mobile($type, "fees/fees_title/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function update(Request $request, $id)
    {
//RAJESH        fees_title::where(["id" => $id])->delete();
        // logic if it was other fee
        if ($request->get('fees_title_id') == 1) {
            $sub_institute_id = session()->get('sub_institute_id');
            $id = DB::select(DB::raw("SELECT ifnull(max(other_fee_id),0) max_id FROM fees_title WHERE sub_institute_id = '$sub_institute_id'"));
            $id = $id[0]->max_id + 1;

            $mandatory = $request->get('mandatory');
            $mandatory_val = isset($mandatory) ? $mandatory : 0;

            $exam = new fees_title([
                'fees_title_id' => $request->get('fees_title_id'),
                'fees_title' => $id,
                'display_name' => $request->get('display_name'),
                'sort_order'  => $request->get('sort_order'),
                'cumulative_name' => $request->get('cumulative_name'),
                'append_name' => $request->get('append_name'),
                'mandatory' => $mandatory_val,
                'syear' => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'other_fee_id' => $id,
            ]);
            $exam->save();
        } else {
            $fees_title_id = $request->get('fees_title_id');
            $fees_title = DB::select(DB::raw("
                    SELECT fee_paid_title
                    FROM fees_title_master
                    WHERE id = '$fees_title_id'"));
            $fees_title = $fees_title[0]->fee_paid_title;

            $mandatory = $request->get('mandatory');
            $mandatory_val = isset($mandatory) ? $mandatory : 0;

            $exam = new fees_title([
                'fees_title_id' => $request->get('fees_title_id'),
                'fees_title' => $fees_title,
                'display_name' => $request->get('display_name'),
                'sort_order'  => $request->get('sort_order'),
                'cumulative_name' => $request->get('cumulative_name'),
                'append_name' => $request->get('append_name'),
                'mandatory' => $mandatory_val,
                'syear' => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'other_fee_id' => 0,
            ]);
            $exam->save();
        }

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return is_mobile($type, "fees_title.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        fees_title::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return is_mobile($type, "fees_title.index", $res, "redirect");
    }

}
