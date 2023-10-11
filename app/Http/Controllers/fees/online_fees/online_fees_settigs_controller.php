<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class online_fees_settigs_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return false|Application|Factory|View|RedirectResponse|string
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
        // $school_data['data'] = array();
        $type = $request->input('type');

        return is_mobile($type, "fees/online_fees/show", $school_data, "view");
    }

    public function getData()
    {
        $data = DB::table('fees_online_maping')
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->get();

        $res_arr = array();
        foreach ($data as $key => $value) 
        {
            $res_arr[$key]['syear'] = $value->syear;
            $res_arr[$key]['id'] = $value->id;
            if ($value->bank_name == 'hdfc') {
                $res_arr[$key]['bank_name'] = 'HDFC';
            } elseif ($value->bank_name == 'icici') {
                $res_arr[$key]['bank_name'] = 'ICICI';
            } elseif ($value->bank_name == 'axis') {
                $res_arr[$key]['bank_name'] = 'AXIS';
            } elseif ($value->bank_name == 'aggre_pay') {
                $res_arr[$key]['bank_name'] = 'Aggre Pay';
            } elseif ($value->bank_name == 'razorpay') {
                $res_arr[$key]['bank_name'] = 'Razor pay';
            }
            elseif ($value->bank_name == 'payphi') {
                $res_arr[$key]['bank_name'] = 'Pay Phi';
            }
        }


        return $res_arr;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    // public function showTypes(Request $request)
    // {
    //     $type = $request->input('type');
    //     $dataStore = array();
    //     return \App\Helpers\is_mobile($type, 'fees/online_fees/add', $dataStore, "view");
    //     // return \App\Helpers\is_mobile($type, 'fees/online_fees/show_type', $dataStore, "view");
    // }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore = array();
        return is_mobile($type, 'fees/online_fees/add', $dataStore, "view");
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit;
        DB::table('fees_online_maping')->insert(
            array(
                'syear' => session()->get('syear'),
                'bank_name' => $request->get('map_company'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'fees_type' => $request->get('fees_type'),
                'created_at' => now(),
                'updated_at' => now()
            )
        );

        if ($request->get('map_company') == 'hdfc') {
            DB::table('fees_hdffc')->insert(
                array(
                    'syear' => session()->get('syear'),
                    'merchant_id' => $request->get('merchant_id'),
                    'account_name' => $request->get('account_name'),
                    'access_code' => $request->get('access_code'),
                    'working_code' => $request->get('working_code'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                )
            );
        } elseif ($request->get('map_company') == 'icici') {
            DB::table('fees_icici')->insert(
                array(
                    'syear' => session()->get('syear'),
                    'merchant_id' => $request->get('merchant_id'),
                    'enc_key' => $request->get('enc_key'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                )
            );
        } elseif ($request->get('map_company') == 'axis') {

            DB::table('fees_axis')->insert(
                array(
                    'syear' => session()->get('syear'),
                    'encryption_key' => $request->get('encryption_key'),
                    'checksum_key' => $request->get('checksum_key'),
                    'cid' => $request->get('cid'),
                    // 'merchant_id'=>$request->get('merchant_id'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                )
            );
        } elseif ($request->get('map_company') == 'aggre_pay') {
            DB::table('fees_aggre_pay')->insert(
                array(
                    'syear' => session()->get('syear'),
                    'api_key' => $request->get('api_key'),
                    'salt_key' => $request->get('salt_key'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                )
            );
        } elseif ($request->get('map_company') == 'razorpay') {
            DB::table('fees_razorpay')->insert(
                array(
                    'syear' => session()->get('syear'),
                    'key_id' => $request->get('merchant_id'),
                    'key_secret' => $request->get('enc_key'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                )
            );
        }
        elseif ($request->get('map_company') == 'payphi') {
            DB::table('fees_payphi')->insert(
                array(
                    'syear' => session()->get('syear'),
                    'merchant_id' => $request->get('merchant_id'),
                    'key' => $request->get('key'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                )
            );
        }

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return is_mobile($type, "online_fees.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
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
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('fees_online_maping')
            ->where(["id" => $id])
            ->delete();

        DB::table('fees_hdffc')
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->delete();

        DB::table('fees_icici')
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->delete();

        DB::table('fees_axis')
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->delete();

        DB::table('fees_aggre_pay')
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->delete();

        DB::table('fees_payphi')
            ->where(["sub_institute_id" => session()->get('sub_institute_id')])
            ->delete();

        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return is_mobile($type, "online_fees.index", $res, "redirect");
    }

}

