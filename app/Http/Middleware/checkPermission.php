<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Access\AuthorizationException;
use DB;
use Illuminate\Support\Str;

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
        if ($request->get('type') != "API") {
            $current_url = Route::currentRouteName();
            $userProfileId = session()->get('user_profile_id');
            $sub_institute_id = session()->get('sub_institute_id');
            $user_id = session()->get('user_id');
            $menu_id = session()->get('right_menu_id');
            $permissions = [];
            
            // $menu = DB::table('tblmenumaster')->where('link', $current_url)->first();
            // if (!empty($menu)) {
            //     $menu_id = $menu->id;
            // }
            // echo "<pre>";print_r($request->all());exit;
            $currentRouteName = $request->route()->getName();

            if($menu_id!=''){
              
                $individual = DB::table('tblindividual_rights')->where('menu_id', $menu_id)
                    ->where('profile_id', $userProfileId)
                    ->where('user_id', $user_id)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->first();

                $group = DB::table('tblgroupwise_rights')->where('menu_id', $menu_id)
                    ->where('profile_id', $userProfileId)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->first();

                if (!empty($individual)) {
                    $permissions = $individual;
                } else {
                    $permissions = $group;
                }

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
                    
                if (!Str::contains($request->submit, 'Search')) {
                    // for route not with resource
                    if ((str_contains(request()->path(), 'delete') || str_contains(request()->path(), 'destroy'))  && $can_delete != 1 && !in_array($menu_id,[200])) 
                    {
                        throw new AuthorizationException('You do not have permission to delete this resource.');
                    }
                    elseif ((str_contains(request()->path(), 'update'))  && $can_edit != 1 && !in_array($menu_id,[31,82,386]))
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
                    else {
                        
                    }
                }
            }
        }

        return $next($request);
    }
}
