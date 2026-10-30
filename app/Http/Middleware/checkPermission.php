<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Access\AuthorizationException;
use DB;
use Illuminate\Support\Str;
use App\Models\tourModel;

class checkPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Previously this check only ran for non-API requests, which meant
        // check_view/can_add/can_edit/can_delete were never enforced for the
        // Next.js frontend (every call from it carries type=API). Now that
        // SessionMiddleware hydrates a real, JWT-verified session for type=API
        // requests too, the same permission logic applies uniformly.
        if (session()->get('user_profile_name') != "Super Admin") {
            $current_url = Route::currentRouteName();
            $userProfileId = session()->get('user_profile_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $user_id = session()->get('user_id');

            // Legacy destroy($id) actions delete by id alone; refuse another school's record.
            if (\App\Support\TenantDeleteGuard::crossesTenant($request)) {
                throw new AuthorizationException('This record belongs to another school.');
            }
            // $menu_id = session()->get('right_menu_id');
            $permissions = [];
            
            $currentRouteName = $request->route()->getName();
            // 02-01-2025 check menu_id by url
            $menu_id = Cache::remember('perm_menu_id:'.$currentRouteName, 300, function () use ($currentRouteName) {
                return DB::table('tblmenumaster')->where('status',1)->where('link',$currentRouteName)->value('id');
            });
            // A write route with no menu row is not rights-checked at all. Failing it closed would
            // block every such screen at once, so first make the gap visible: one warning per
            // route per hour, listing exactly which routes need a menu row (or an explicit guard).
            if (empty($menu_id) && ! $request->isMethodSafe()) {
                if (Cache::add('perm_unmapped_logged:'.$currentRouteName, 1, 3600)) {
                    \Illuminate\Support\Facades\Log::channel('daily')->warning('perm: write route has no menu row, rights not checked', [
                        'route' => $currentRouteName,
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'profile' => session()->get('user_profile_name'),
                    ]);
                }

                // Opt-in (config/permissions.php): once the routes are mapped, an unmapped write is refused
                // instead of allowed, except the route names listed as deliberate exceptions.
                if (config('permissions.fail_closed', false)
                    && ! in_array((string) $currentRouteName, (array) config('permissions.unmapped_allow', []), true)) {
                    throw new AuthorizationException('No rights are defined for this action.');
                }
            }
            // echo "<pre>";print_r($currentRouteName);exit;
            // 02-01-2025 end

            if($menu_id!=''){
              
                // Two lookups on every request. Rights are saved from many controllers, so a
                // short TTL (rather than invalidation) bounds how stale a change can look.
                $permissions = Cache::remember(
                    'perm_rights:'.$menu_id.':'.$userProfileId.':'.$user_id.':'.$sub_institute_id,
                    60,
                    function () use ($menu_id, $userProfileId, $user_id, $sub_institute_id) {
                        $individual = DB::table('tblindividual_rights')->where('menu_id', $menu_id)
                            ->where('profile_id', $userProfileId)
                            ->where('user_id', $user_id)
                            ->where('sub_institute_id', $sub_institute_id)
                            ->first();

                        if (!empty($individual)) {
                            return $individual;
                        }

                        // false (not null) so "no rights row" is cached too; Cache::remember
                        // would otherwise re-query on every request for a null result.
                        return DB::table('tblgroupwise_rights')->where('menu_id', $menu_id)
                            ->where('profile_id', $userProfileId)
                            ->where('sub_institute_id', $sub_institute_id)
                            ->first() ?: false;
                    }
                ) ?: null;

                $can_view = $permissions->can_view ?? 0;
                $can_add = $permissions->can_add ?? 0;
                $can_edit = $permissions->can_edit ?? 0;
                $can_delete = $permissions->can_delete ?? 0;

                session()->put('menu_permissions',$permissions);
                // check methods 
                
                // if (empty($permissions)) {
                //     throw new AuthorizationException('You do not have the necessary permissions to access this resource.');
                // }
                // echo "<pre>";print_r($permissions);exit;
                    
                // Legacy search forms POST submit=Search and have no write rights of their own, so a
                // search skips the rights check. A client-chosen value must never unlock a write,
                // so the skip does not apply to write verbs or to write-looking paths.
                $isReadOnlySearch = Str::contains($request->submit, 'Search')
                    && in_array($request->method(), ['GET', 'HEAD', 'POST'], true)
                    && !preg_match('/delete|destroy|update|store|save/i', $request->path());

                if (!$isReadOnlySearch) {
                    // for route not with resource
                    if ((str_contains(request()->path(), 'delete') || str_contains(request()->path(), 'destroy'))  && $can_delete != 1 && !in_array($menu_id,[200])) 
                    {
                        throw new AuthorizationException('You do not have permission to delete this resource.');
                    }
                    // menu_id 31 (/fees/update-fees-breakoff) was removed from this allowlist:
                    // it is a Fees endpoint, and tblgroupwise_rights/tblindividual_rights already
                    // carry real can_edit=0 rows for it that this bypass was silently overriding
                    // (Day-11 Issue 2 — RBAC must not be bypassable on Fees endpoints).
                    elseif ((str_contains(request()->path(), 'update'))  && $can_edit != 1 && !in_array($menu_id,[82,386]))
                    {
                        throw new AuthorizationException('You do not have permission to edit this resource.');
                    }
                    elseif ((str_contains(request()->path(), 'store') || str_contains(request()->path(), 'add') || str_contains(request()->path(), 'save')) && request()->method()=="POST" && $can_add != 1 ) 
                    {
                        throw new AuthorizationException('You do not have permission to add this resource.');
                    } 
                    // for route with resource
                    elseif (request()->method()=="PUT" && $can_edit != 1)
                    {
                        throw new AuthorizationException('You do not have permission to edit this resource.');
                    } 
                    elseif (request()->method()=="POST" && $can_add != 1)
                    {
                        throw new AuthorizationException('You do not have permission to add this resource.');
                    } 
                    elseif (request()->method()=="DELETE" && $can_delete != 1) 
                    {
                        throw new AuthorizationException('You do not have permission to delete this resource.');
                    } 
                    elseif($can_view==0) {
                        throw new AuthorizationException('You do not have permission to view this resource.');
                    }
                }
            }
            
            // 06-01-2025 add for erpTour start
            $erpTourRoute = ["fees_collect.store","fees_title.store","fees_breackoff.store","student_quota.store","map_year.store"];

            $erpTourFeild = ["fees_collect.store"=>"fees_collect","fees_title.store"=>"fees_title","fees_breackoff.store"=>"fees_structure","student_quota.store"=>"student_quota","map_year.store"=>"fees_map"];
            // echo "<pre>";print_r($currentRouteName);exit;
            if(in_array($currentRouteName,$erpTourRoute) && isset($erpTourFeild[$currentRouteName]) && request()->method()=="POST"){

                $checkData = tourModel::where(['user_id' => $user_id, 'sub_institute_id' => $sub_institute_id])->first();

                $addData = [
                    'user_id'=>$user_id,
                    'sub_institute_id'=>$sub_institute_id,
                    $erpTourFeild[$currentRouteName]=>1,
                ];

                if(!empty($checkData)){
                    tourModel::where('id',$checkData->id)->update($addData);
                }else{
                    $addData['user_id'] = $user_id;
                    $addData['sub_institute_id'] = $sub_institute_id;
                    tourModel::insert($addData);
                }

            }
            
            $checkUserTour = tourModel::where(['user_id'=> $user_id, 'sub_institute_id' => $sub_institute_id,
            ])->get()->toArray();

            // Most users have no erptour row: it is only ever written by the fees
            // routes above and at login. Indexing [0] blindly made every request by
            // such a user a 500 -- which is what students hit, since they never go
            // near a fees screen. An unseen tour is every step still to do, so the
            // flags default to 0 and the blades reading them keep working.
            $inTour = $checkUserTour[0] ?? [
                'dashboard'        => 0,
                'school_sidebar'   => 0,
                'student_quota'    => 0,
                'fees_title'       => 0,
                'fees_structure'   => 0,
                'fees_receipt'     => 0,
                'fees_map'         => 0,
                'fees_collect'     => 0,
                'user_id'          => $user_id,
                'sub_institute_id' => $sub_institute_id,
            ];

            $request->session()->put('erpTour', $inTour);
            // 06-01-2025 add for erpTour end 
        }

        return $next($request);
    }
}
