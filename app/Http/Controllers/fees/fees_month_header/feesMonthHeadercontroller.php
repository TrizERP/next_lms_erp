<?php

namespace App\Http\Controllers\fees\fees_month_header;

use App\Http\Controllers\Controller;
use App\Models\fees\fees_breackoff\fees_breackoff;
use App\Models\fees\map_year\map_year;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class feesMonthHeadercontroller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = map_year::where([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear'            => session()->get('syear'),
        ])->get()->toArray();
        
        $start_month = $data[0]['from_month'];
        $end_month = $data[0]['to_month'];

        $months = [
            1  => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $months_arr = [];
        $syear = session()->get('syear');

        if($data[0]['type'] == "yearly_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
        }
        else if($data[0]['type'] == "half_year_fees")
        {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            $sixmonths = ($start_month+6);
            $months_arr[$sixmonths.$syear] = $months[$sixmonths].'/'.$syear;

        }
        else if($data[0]['type'] == "quarterly_fees")
        {
            for ($i = $start_month; $i <= 12; $i++) 
            {
                if ($start_month <= 12) 
                {
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    $start_month = ($start_month+3);
                }
                else
                {
                    $start_month = 1;
                    ++$syear;
                    $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                    break;
                }
            }
        }
        else
        {
            for ($i = 1; $i <= 12; $i++) {
                $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
                if ($start_month == 12) {
                    $start_month = 0;
                    ++$syear;
                }
                ++$start_month;
            }
        }

        $result = array();
        $result = DB::table('fees_month_header')
            ->where('sub_institute_id', session()->get('sub_institute_id'))->get()->toArray();
            
        /* for ($i = 1; $i <= 12; $i++) {
            $months_arr[$start_month.$syear] = $months[$start_month].'/'.$syear;
            if ($start_month == 12) {
                $start_month = 0;
                ++$syear;
            }
            ++$start_month;
        } */

        $res['data']['ddMonth'] = $months_arr;
        $res['month_header'] = $result;
    
        $type = $request->input('type');

        return is_mobile($type, "fees/fees_month_header/fees_month_header", $res, "view");
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
        $month_values = $request->month_value;
        
        foreach ($month_values as $month_id => $header) 
        {
            DB::table('fees_month_header')->updateOrInsert(
                ['month_id' => $month_id],
                [
                    'header' => $header,
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_by' => session()->get('user_profile_id'),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        $res = array(
            "status_code" => 1,
            "message" => "Header Added/Updated Successfully",
        );

        $type = $request->input('type');

        return is_mobile($type, "fees_month_header.index", $res, "redirect");
    }
}
