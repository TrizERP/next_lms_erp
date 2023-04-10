<?php

namespace App\Http\Middleware;

use App\Models\Accesslog;
use Closure;
use Illuminate\Http\Request;

class LogRouteMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->get('type') != "API") {
//            Log::info($request->fullUrl());

            $sub_institute_id = $request->session()->get('sub_institute_id');
            $user_id = $request->session()->get('user_id');
            $user_profile_id = $request->session()->get('user_profile_id');
            $uri = explode("/", $_SERVER['REQUEST_URI']);

            foreach ($_REQUEST as $key => $val) {
                //Add datepicker fieldsname in array
                $search_field_array = [
                    "from_date", "to_date", "meet_date", "inward_date", "outward_date",
                    "receiptdate", "followup_date", "deduction_date", "cheque_date",
                    "txtbx_date", "date_of_cancel", "date_", "date_of_birth", "date_of_payment",
                    "admission_date", "created_on", "date", "challan_date", "bill_date",
                    "delivery_time", "warranty_start_date", "warranty_end_date",
                    "estimated_received_date", "DATE", "TASK_DATE", "dob", "dates",
                    "medical_close_date", "registration_date", "date_of_application_for_certificate",
                    "date_on_which_pupil_name_was_struck", "date_of_issue_of_certificate",
                    "birthdate", "follow_up_date", "open_date", "close_date", "submission_date",
                ];

                if (in_array($key, $search_field_array) && $val != "") {
                    if (is_array($val)) { //if date is array field --multidimension array
                        $key_array = $request->$key;
                        foreach ($val as $akey => $aval) {
                            if ($aval != "") {
                                $_REQUEST[$key][$akey] = date('Y-m-d', strtotime($aval));
                                $key_array[$akey] = date('Y-m-d', strtotime($aval));
                            }
                        }
                        $request->merge([$key => $key_array]);
                    } else { //if date is single field
                        $_REQUEST[$key] = date('Y-m-d', strtotime($val));
                        $request->merge([$key => date('Y-m-d', strtotime($val))]);
                    }
                }
            }

            $log_data = [
                'url'              => $request->fullUrl(),
                'sub_institute_id' => $sub_institute_id,
                'user_id'          => $user_id,
                'profile_id'       => $user_profile_id,
                'ip_address'       => $_SERVER['REMOTE_ADDR'],
                'module'           => $uri[1] ?? $_SERVER['REQUEST_URI'],
                'action'           => $_SERVER['REQUEST_URI'],
            ];

            $data = Accesslog::insert($log_data);
        }

        return $next($request);
    }
}
