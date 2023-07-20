<?php

namespace App\Http\Middleware;

use App\Models\tblmenumasterModel;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class MenuMiddleware
{

    protected Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

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

        if ($type == "API") {
            return $next($request);
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $client_id = session()->get('client_id');
        $is_admin = session()->get('is_admin');
        $user_id = $request->session()->get('user_id');
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_profile_name = $request->session()->get('user_profile_name');

        $routeName = Route::currentRouteName();
        $route = explode('.', $routeName);

        $checkMenu = tblmenumasterModel::where(['link' => $routeName])->get()->toArray();
        if (count($checkMenu) == 0) {
            $checkMenu = tblmenumasterModel::whereRaw("link like '%".$route[0]."%' ")->get()->toArray();
        }

        if ($user_profile_name == 'student' || $user_profile_name == 'Student' || $user_profile_name == 'STUDENT') {

            $rightsQuery = DB::table('tblstudent as u')
                ->leftJoin('tblindividual_rights as i', function ($join) {
                    $join->whereRaw("u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id");
                })->leftJoin('tblgroupwise_rights as g', function ($join) {
                    $join->whereRaw("u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id");
                })->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
                    $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(".$sub_institute_id.", m.sub_institute_id)");
                })->selectRaw("GROUP_CONCAT(distinct m.id) AS MID")
                ->whereIn('u.sub_institute_id', explode(',', $sub_institute_id))
                ->where('u.id', $user_id)->get()->toArray();
        } else {

            if ($sub_institute_id == 0 && $is_admin == 1) {
                $rightsQuery = DB::table('tbluser as u')
                    ->leftJoin('tblindividual_rights as i', function ($join) {
                        $join->whereRaw("u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id");
                    })->leftJoin('tblgroupwise_rights as g', function ($join) {
                        $join->whereRaw("u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id");
                    })->join('tblmenumaster as m', function ($join) use ($client_id) {
                        $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(".$client_id.", m.client_id)");
                    })->selectRaw("GROUP_CONCAT(distinct m.id) AS MID")
                    ->whereIn('u.sub_institute_id', explode(',', $sub_institute_id))
                    ->where('u.id', $user_id)->get()->toArray();
            } else {
//DB::enableQueryLog();
                $rightsQuery = DB::table('tbluser as u')
                    ->leftJoin('tblindividual_rights as i', function ($join) {
                        $join->whereRaw("u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id");
                    })->leftJoin('tblgroupwise_rights as g', function ($join) {
                        $join->whereRaw("u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id");
                    })->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
                        $join->whereRaw("(i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(?, m.sub_institute_id)", [$sub_institute_id]);
                    })->selectRaw("GROUP_CONCAT(distinct m.id) AS MID")
                    ->whereIn('u.sub_institute_id', explode(',', $sub_institute_id))
                    ->where(function ($q) use ($user_id) {
                        if (! session()->has('new_sub_institute_id')) {
                            $q->where('u.id', $user_id);
                        }
                    })->get()->toArray();
//dd(DB::getQueryLog($rightsQuery));

            }

        }

        $rightsQuery = array_map(function ($value) {
            return (array) $value;
        }, $rightsQuery);
        $rightsMenusIds = 0;

        if (isset($rightsQuery['0']['MID'])) {
            $rightsMenusIds = $rightsQuery['0']['MID'];
            //$rightsMenusIds = substr($rightsMenusIds, 0,-1);
            $rightsMenusIds = substr($rightsMenusIds, 0);
        }
        // echo "<pre>";print_r($rightsMenusIds);exit;

        if (count($checkMenu) > 0) {
            if ($checkMenu[0]['menu_type'] == 'MASTER') {
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
                    if ($sub_institute_id == 0 && $is_admin == 1) {
                        $data = tblmenumasterModel::where([
                            'parent_menu_id' => "0", 'level' => "1",
                        ])->whereRaw("find_in_set('$client_id',client_id) and status = 1 and id in (".$rightsMenusIds.") and menu_type IS NULL")->orderBy('sort_order')->get()->toArray();

                        $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=',
                            0)->whereRaw("find_in_set('$client_id',client_id) AND level = 2 and id in (".$rightsMenusIds.") and status = 1 and menu_type IS NULL")->orderBy('sort_order')->get()->toArray();

                        $i = 0;
                        foreach ($subMenuData as $key => $value) {
                            $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
                            if ($value['quick_menu'] != '') {
                                $quick_menu_new = "SELECT * FROM tblmenumaster WHERE find_in_set (id,(select quick_menu
                                    from tblmenumaster where id = '" . $value['id'] . "'))";
                                $quick_menu_data = DB::select($quick_menu_new);

                                $quick_menu_data = array_map(function ($value) {
                                    return (array)$value;
                                }, $quick_menu_data);
                                $finalQuickMenu[$value['id']] = $quick_menu_data;
                            }
                            $i++;
                        }
                        $subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                            ->whereRaw("find_in_set('$client_id',client_id) AND level = 3 and id in (" . $rightsMenusIds . ")
                            and status = 1 and menu_type != 'MASTER' or menu_type IS NULL" )->orderBy('sort_order')->get()->toArray();
                        $i = 0;
                        foreach ($subChildMenuData as $key => $value) {
                            $finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
                            $i++;
                        }
                    } else {
                        $data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])
                            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and status = 1
                            and id in (" . $rightsMenusIds . ") and (menu_type!='MASTER' or menu_type IS NULL)")->orderBy('sort_order')->get()->toArray();

                        $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 2
                            and id in (" . $rightsMenusIds . ") and status = 1 and (menu_type!='MASTER' or menu_type IS NULL)")->orderBy('sort_order')->get()->toArray();

                        $i = 0;
                        foreach ($subMenuData as $key => $value) {
                            $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
                            if ($value['quick_menu'] != '') {
                                $quick_menu_new = "SELECT * FROM tblmenumaster WHERE find_in_set (id,(select quick_menu from tblmenumaster where id = '".$value['id']."'))";
                                $quick_menu_data = DB::select($quick_menu_new);

                                $quick_menu_data = array_map(function ($value) {
                                    return (array) $value;
                                }, $quick_menu_data);
                                $finalQuickMenu[$value['id']] = $quick_menu_data;
                            }
                            $i++;
                        }
                        $subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 3
                            and id in (" . $rightsMenusIds . ") and status = 1 and (menu_type != 'MASTER' or menu_type IS NULL) ")->orderBy('sort_order')->get()->toArray();
                        $i = 0;
                        foreach ($subChildMenuData as $key => $value) {
                            $finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
                            $i++;
                        }
                    }
        // echo "<pre>";print_r($finalSubMenu);exit;

                    view()->share('menuMaster', $data);
                    if (isset($finalSubMenu)) {
                        view()->share('submenuMaster', $finalSubMenu);
                    }
                    if (isset($finalQuickMenu)) {
                        view()->share('quickmenuMaster', $finalQuickMenu);
                    }
                    if (isset($finalSubChildMenu)) {
                        view()->share('subChildmenuMaster', $finalSubChildMenu);
                    }
                }

                return $next($request);
            }
        }

        if ($type != "API") {
            if ($sub_institute_id == 0 && $is_admin == 1) {
                $data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])
                    ->whereRaw("find_in_set('$client_id',client_id) and status = 1 and id in (" . $rightsMenusIds . ")
                        ")->orderBy('sort_order')->get()->toArray();

                $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                    ->whereRaw("find_in_set('$client_id',client_id) AND level = 2 and id in (" . $rightsMenusIds . ")
                        and status = 1 and (menu_type != 'MASTER' or menu_type IS NULL)")->orderBy('sort_order')->get()->toArray();

                $i = 0;
                foreach ($subMenuData as $key => $value) {
                    $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
                    if ($value['quick_menu'] != '') {
                        $quick_menu_data = DB::table('tblmenumaster')->whereRaw("find_in_set(id,(select quick_menu from
                            tblmenumaster where id = '" . $value['id'] . "'))")->get()->toArray();

                        $quick_menu_data = array_map(function ($value) {
                            return (array) $value;
                        }, $quick_menu_data);
                        $finalQuickMenu[$value['id']] = $quick_menu_data;
                    }
                    $i++;
                }

                $subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                    ->whereRaw("find_in_set('$client_id',client_id) AND level = 3 and id in (" . $rightsMenusIds . ")
                        and status = 1 and (menu_type != 'MASTER' or menu_type IS NULL)")->orderBy('sort_order')->get()->toArray();
                $i = 0;
                foreach ($subChildMenuData as $key => $value) {
                    $finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
                    $i++;
                }
            } else {
                $data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])
                    ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and status = 1
                        and id in (".$rightsMenusIds.") and (menu_type != 'MASTER' or menu_type IS NULL)")
                    ->orderBy('sort_order')->get()->toArray();
                $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                    ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 2
                        and id in (" . $rightsMenusIds . ") and status = 1 and (menu_type != 'MASTER' or menu_type IS NULL) ")
                    ->orderBy('sort_order')->get()->toArray();

                $i = 0;
                foreach ($subMenuData as $key => $value) {
                    $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
                    if ($value['quick_menu'] != '') {
                        $quick_menu_data = DB::table('tblmenumaster')->whereRaw("find_in_set(id,(select quick_menu from
                            tblmenumaster where id = '" . $value['id'] . "'))")->get()->toArray();

                        $quick_menu_data = array_map(function ($value) {
                            return (array) $value;
                        }, $quick_menu_data);
                        $finalQuickMenu[$value['id']] = $quick_menu_data;
                    }
                    $i++;
                }

                $subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
                    ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 3
                        and id in (" . $rightsMenusIds . ") and status = 1 and menu_type != 'MASTER' or menu_type IS NULL ")->orderBy('sort_order')->get()->toArray();
                $i = 0;
                foreach ($subChildMenuData as $key => $value) {
                    $finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
                    $i++;
                }
            }
        // echo "<pre>";print_r($rightsMenusIds);exit;

            view()->share('menuMaster', $data);
            if (isset($finalSubMenu)) {
                view()->share('submenuMaster', $finalSubMenu);
            }
            if (isset($finalQuickMenu)) {
                view()->share('quickmenuMaster', $finalQuickMenu);
            }
            if (isset($finalSubChildMenu)) {
                view()->share('subChildmenuMaster', $finalSubChildMenu);
            }
        }

        return $next($request);
    }
}
