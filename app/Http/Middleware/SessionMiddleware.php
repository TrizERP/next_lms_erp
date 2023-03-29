<?php

namespace App\Http\Middleware;
use App\Models\applications;
use Closure;
use Illuminate\Http\Request;

class SessionMiddleware
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
        $type = $request->input("type");

        if($type == "API"){
            // $secret = $request->header('app_secret_key');
            // $data = tblapplicationModel::where(['app_secret_key'=>$secret])->get()->toArray();

            // if(empty($data)){
            //     $res['status_code'] = 0;
            //     $res['message'] = "Authentication Invalid";
            //     return response()->json($res);
            // }
        }else{
            $user_id = $request->session()->get('user_id');
            if(empty($user_id)){
                
                return redirect(route('home'));
            }
        }

        return $next($request);
    }
}
