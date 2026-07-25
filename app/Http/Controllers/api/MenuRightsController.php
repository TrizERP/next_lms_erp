<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\tblmenumasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class MenuRightsController extends Controller
{
    /**
     * Get menu rights level wise for a user based on sub_institute_id, user_id, and erp_rights_id.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenuRightsLevelWise(Request $request)
    {
        

        $type = $request->input('type');

        $sub_institute_id = $request->get('sub_institute_id');
        $client_id = $request->get('client_id');
        $is_admin = $request->get('is_admin');
        $user_id = $request->get('user_id');
        $user_profile_id = $request->get('user_profile_id');
        $user_profile_name = $request->get('user_profile_name');

        // Initialize so branches with no sub/child menus don't throw
        // "Undefined variable" when building the response (menu 500 fix).
        $finalSubMenu = [];
        $finalSubChildMenu = [];
        $finalQuickMenu = [];

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
                    ->where('u.status',1) // 23-04-24 by uma
                    ->where('u.id', $user_id)->get()->toArray();
            } else {
//DB::enableQueryLog();
                $rightsQuery = DB::table('tbluser as u')
                    ->leftJoin('tblindividual_rights as i', function ($join) {
                        $join->whereRaw("u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id AND u.user_profile_id=i.profile_id");
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
            if(substr($rightsMenusIds, -1)==","){
                $rightsMenusIds = substr($rightsMenusIds, 0,-1);
            }else{
                $rightsMenusIds = substr($rightsMenusIds, 0);
            }
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

                if ($type == "API" && $type == "JSON") {
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

                   $res['level 1'] = $data;
                   $res['level 2'] = $finalSubMenu;
                   $res['level 3'] = $finalSubSubMenu;

                }

                return response()->json(['status'=>1,'data'=>$res]);
            }
        }

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
                        and id in (" . $rightsMenusIds . ") and status = 1 and (menu_type != 'MASTER' or menu_type IS NULL )")->orderBy('sort_order')->get()->toArray();
                $i = 0;
                foreach ($subChildMenuData as $key => $value) {
                    $finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
                    $i++;
                }
                $res['level 1'] = $data;
                   $res['level 2'] = $finalSubMenu;
                   $res['level 3'] = $finalSubChildMenu;
                
            }
        // echo "<pre>";print_r($rightsMenusIds);exit;


        return response()->json(['status'=>1,'data'=>$res]);
    }

    public function getMasterMenuApi(Request $request)
    {
        // return response()->json(['data'=>$request->all()]);
        $sub_institute_id = $request->get('sub_institute_id');
        $user_id = $request->get('user_id');
        $main_menu_id = $request->menu_id;

        $mainMenus = DB::table('rightside_menumaster as m')
            ->whereRaw("FIND_IN_SET(?, m.sub_institute_id)", [$sub_institute_id])
            ->where('m.parent_menu_id', 0)
            ->where('m.main_menu_id', $main_menu_id)
            ->orderBy('m.sort_order')
            ->get();

    $childMenus = DB::table('tbluser as u')
        ->leftJoin('tblindividual_rights as i', function ($join) {
            $join->on('u.id', '=', 'i.user_id')
                ->on('u.sub_institute_id', '=', 'i.sub_institute_id');
        })
        ->leftJoin('tblgroupwise_rights as g', function ($join) {
            $join->on('u.user_profile_id', '=', 'g.profile_id')
                ->on('u.sub_institute_id', '=', 'g.sub_institute_id');
        })
        ->join('rightside_menumaster as m', function ($join) {
            $join->on('i.menu_id', '=', 'm.tblmenu_master_id')
                ->orOn('g.menu_id', '=', 'm.tblmenu_master_id');
        })
        ->join('tblmenumaster as mm', function ($join) use ($sub_institute_id) {
            $join->on('mm.id', '=', 'm.tblmenu_master_id')
                ->whereRaw("FIND_IN_SET(?, m.sub_institute_id)", [$sub_institute_id]);
        })
        ->select(
            'm.id',
            'm.name',
            'm.icon',
            'm.parent_menu_id',
            'm.tblmenu_master_id',
            'm.main_menu_id',
            'm.sort_order',
            'mm.link'
        )
        ->distinct()
        ->where('u.sub_institute_id', $sub_institute_id)
        ->where('u.status', 1)
        ->where('u.id', $user_id)
        ->where('m.main_menu_id', $main_menu_id)
        ->orderBy('m.sort_order')
        ->get()
        ->groupBy('parent_menu_id');

    $menuData = [];

    foreach ($mainMenus as $mainMenu) {
        if (!isset($childMenus[$mainMenu->id])) {
            continue;
        }

        $children = [];

        foreach ($childMenus[$mainMenu->id] as $child) {
            $children[] = [
                'id' => $child->id,
                'name' => $child->name,
                'tblmenu_master_id' => $child->tblmenu_master_id,
                'route_name' => $child->link,
                //'url' => $this->resolveMenuUrl($child->link),
            ];

            if ($child->name == 'Field Settings') {
                $children[] = [
                    'id' => null,
                    'name' => 'Excel Import/Export',
                    'type' => 'popup',
                    'url' => env('APP_URL') . 'excel_upload/export_xlsx.php?sub_institute_iderp=' . $sub_institute_id,
                ];

                $children[] = [
                    'id' => null,
                    'name' => 'Import Data',
                    'route_name' => 'import.data',
                    'url' => route('import.data'),
                ];

                $children[] = [
                    'id' => null,
                    'name' => 'Workflow',
                    'route_name' => 'workflow.index',
                    'url' => route('workflow.index'),
                ];
            }
        }

        $menuData[] = [
            'id' => $mainMenu->id,
            'name' => $mainMenu->name,
            'icon' => $mainMenu->icon,
            'icon_url' => env('APP_URL') . '/admin_dep/images/side-' . $mainMenu->icon . '.png',
            'active_icon_url' => env('APP_URL') . '/admin_dep/images/side-' . $mainMenu->icon . '-white.png',
            'children' => $children,
        ];
    }

    return response()->json([
        'status' => true,
        'message' => 'Master menu fetched successfully',
        'data' => $menuData,
    ]);
}

    private function resolveMenuUrl($link)
    {
        $link = trim((string) $link);

        if ($link === '' || $link === '#' || $link === 'javascript:void(0);') {
            return '#';
        }

        if (preg_match('/^https?:\/\//i', $link)) {
            return $link;
        }

        $normalizedPath = trim(str_replace('\\', '/', $link), '/');
        $lastSegment = basename($normalizedPath);
        $resourceRoute = str_replace('-', '_', $lastSegment) . '.index';

        $routeCandidates = array_filter(array_unique([
            $link,
            trim($link, '\\/'),
            str_replace(['\\', '/'], '.', trim($link, '\\/')),
            $resourceRoute,
        ]));

        foreach ($routeCandidates as $routeName) {
            if (Route::has($routeName)) {
                return $this->toRelativeMenuUrl(route($routeName));
            }
        }

        return $this->toRelativeMenuUrl(url($normalizedPath));
    }

    private function toRelativeMenuUrl($url)
    {
        if ($url === '#' || $url === 'javascript:void(0);') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if ($path === null || $path === false) {
            return trim((string) $url, '/');
        }

        return trim($path, '/') . ($query ? '?' . $query : '');
    }
}
