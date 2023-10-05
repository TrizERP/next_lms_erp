<?php

namespace App\Http\Controllers\fees\map_year;

use App\Http\Controllers\Controller;
use App\Models\fees\map_year\map_year;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class map_year_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function index(Request $request)
    {
        if (session()->has('data')) {
            // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }
        $school_data['data'] = $this->getData();
//        $school_data['data'] = array();
        $type = $request->input('type');
        return is_mobile($type, "fees/map_year/show", $school_data, "view");
    }

    public function getData()
    {
        $data = map_year::
        where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear' => session()->get('syear'),
        ])->get()->toArray();
        $responce_arr = array();
        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
        foreach ($data as $id => $arr) {
            $data[$id]['from_month'] = $months[$arr['from_month']];
            $data[$id]['to_month'] = $months[$arr['to_month']];
        }
        return $data;
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
        // dd($request);
        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');

        $dataStore['data']['ddMonth'] = $months;
        return is_mobile($type, 'fees/map_year/add', $dataStore, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
//        echo "<pre>";
        //        print_r($_REQUEST);
        //        exit;
        $exam = new map_year([
            'from_month' => $request->get('start_month'),
            'to_month' => $request->get('end_month'),
            'type' => $request->get('fee_type'),
            'syear' => session()->get('syear'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
        $exam->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return is_mobile($type, "map_year.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @param int $id
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = map_year::find($id)->toArray();

        $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
        $data['data']['ddMonth'] = $months;
        return is_mobile($type, "fees/map_year/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        map_year::where(["id" => $id])->delete();
        $exam = new map_year([
            'from_month' => $request->get('start_month'),
            'to_month' => $request->get('end_month'),
            'type' => $request->get('fee_type'),
            'syear' => session()->get('syear'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        ]);
        $exam->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return is_mobile($type, "map_year.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        map_year::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return is_mobile($type, "map_year.index", $res, "redirect");
    }

}
