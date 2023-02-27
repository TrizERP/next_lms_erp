<?php

namespace App\Http\Middleware;

use App\Models\tblmenumasterModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterSetupMenuMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');

        $rightsQuery = DB::table('tbluser as u')
            ->leftJoin('tblindividual_rights as i', function ($join) {
                $join->whereRaw('u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id');
            })->leftJoin('tblgroupwise_rights as g', function ($join) {
                $join->whereRaw('u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id');
            })->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
                $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(".$sub_institute_id.", m.sub_institute_id)");
            })
            ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
            ->where('u.sub_institute_id', $sub_institute_id)
            ->where('u.id', $user_id)->get()->toArray();

        $rightsQuery = array_map(function ($value) {
            return (array) $value;
        }, $rightsQuery);

        $rightsMenusIds = 0;

        if (isset($rightsQuery['0']['MID'])) {
            $rightsMenusIds = $rightsQuery['0']['MID'];
        }

        if ($user_profile_name == 'admin' || $user_profile_name == 'Admin' || $user_profile_name == 'ADMIN') {
            $rightsMenusIds .= ",37,41,42";
        } else {
            if ($request->session()->has('multiSchool') && $request->session()->get('multiSchool') == 1) {
                if ($user_profile_name == 'SCHOOL ADMIN' || $user_profile_name == 'School Admin' || $user_profile_name == 'school admin') {
                    $rightsMenusIds .= ",37,41,42";
                }
            }
        }

        if ($type != "API") {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])
                ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and menu_type = 'MASTER' 
                and id in (".$rightsMenusIds.") and status = 1")->orderBy('sort_order')->get()->toArray();
            $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 2 
                and id in (".$rightsMenusIds.") and status = 1")->orderBy('sort_order')->get()->toArray();

            $i = 0;
            foreach ($subMenuData as $subMenuKey => $subMenuValue) {
                $finalSubMenu[$subMenuValue['parent_menu_id']][$i] = $subMenuValue;
                $i++;
            }

            $subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and id in (".$rightsMenusIds.") 
                AND level = 3 and status = 1")->orderBy('sort_order')->get()->toArray();

            $i = 0;
            foreach ($subChildMenuData as $subChildMenuKey => $subChildMenuValue) {
                $finalSubChildMenu[$subChildMenuValue['parent_menu_id']][$i] = $subChildMenuValue;
                $i++;
            }

            view()->share('menuMaster', $data);
            if (isset($finalSubMenu)) {
                view()->share('submenuMaster', $finalSubMenu);
            }
            if (isset($finalSubChildMenu)) {
                view()->share('subChildmenuMaster', $finalSubChildMenu);
            }
        }

        return $next($request);
    }
}
