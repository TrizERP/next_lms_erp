<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\mobile_homescreenModel;
use App\Models\teacher_mobile_homescreenModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use function App\Helpers\is_mobile;

class mobileapp_menu_rightsController extends Controller
{
    /**
     * The two tables this screen edits, split by role exactly the way
     * create() and the homescreen APIs already split them: Student rows live
     * in `mobile_homescreen`, Admin/Teacher rows in
     * `teacher_mobile_homescreen`.
     */
    private function tableFor($profile)
    {
        if ($profile == 'Student') {
            return 'mobile_homescreen';
        }

        if ($profile == 'Admin' || $profile == 'Teacher') {
            return 'teacher_mobile_homescreen';
        }

        return null;
    }

    /**
     * Whether this installation has run
     * 2026_09_22_200000_add_render_type_to_mobile_homescreen_tables. Guarded
     * rather than assumed, so the screen keeps saving titles and sort orders
     * on an installation that has not migrated yet -- the same treatment
     * AbstractMenuCategoryApiController gives its own rollout columns.
     */
    private function hasRenderColumns($table)
    {
        return $table && Schema::hasColumn($table, 'render_type');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $Profiles = ['Admin' => 'Admin', 'Teacher' => 'Teacher', 'Student' => 'Student'];
        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['profiles'] = $Profiles;

        return is_mobile($type, "user/add_mobileapp_menu_rights", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input("type");
        $inactive = $request->input('inactive');
        $profile = $request->input('profile');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if ($profile == 'Student') {

            $mobileapp_menu_data = DB::table("mobile_homescreen as mh")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name");
                })
                ->selectRaw('mh.*')
                ->where("mh.sub_institute_id", "=", $sub_institute_id)
                ->where("up.name", "=", $profile)
                ->where(function ($q) use ($inactive) {
                    if (isset($inactive) && $inactive == 'No') {

                    } else {
                        $q->where('mh.status', '=', 'Yes');
                    }
                })
                ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order')
                ->get()->toArray();
        }

        if ($profile == 'Admin' || $profile == 'Teacher') {
            $mobileapp_menu_data = DB::table("teacher_mobile_homescreen as mh")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name");
                })
                ->selectRaw('mh.*')
                ->where("mh.sub_institute_id", "=", $sub_institute_id)
                ->where("up.name", "=", $profile)
                ->where(function ($q) use ($inactive) {
                    if (isset($inactive) && $inactive == 'No') {

                    } else {
                        $q->where('mh.status', '=', 'Yes');
                    }
                })
                ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order')
                ->get()->toArray();
        }

        $mobileapp_menu_data = json_decode(json_encode($mobileapp_menu_data), true);

        $Profiles = ['Admin' => 'Admin', 'Teacher' => 'Teacher', 'Student' => 'Student'];

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['mobileapp_menu_data'] = $mobileapp_menu_data;
        $res['profiles'] = $Profiles;
        $res['profile'] = $profile;
        $res['inactive'] = $inactive;

        return is_mobile($type, "user/add_mobileapp_menu_rights", $res, "view");
    }

    /**
     * Store a newly created menu item for the selected profile.
     *
     * This was a stub until WebView-backed menus arrived. A native row could
     * always be added the slow way -- insert it as a sub_institute_id = 1
     * template, then grant it on the Mobile App Menu Rights page -- which is
     * tolerable for a screen that needs a Flutter release anyway. A WebView
     * row is the whole point of not needing a release, so it writes straight
     * into the tenant and profile the admin is already looking at.
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $profile = $request->get('profile_hidden');
        $type = $request->input('type');

        $table = $this->tableFor($profile);
        if (empty($table)) {
            $res = [
                'status_code' => 0,
                'message'     => 'Select a user profile before adding a menu item.',
            ];

            return is_mobile($type, 'add_mobileapp_menu_rights.index', $res);
        }

        $validator = Validator::make($request->all(), [
            'main_title'        => 'required|string|max:100',
            'sub_title_of_main' => 'required|string|max:100',
            'screen_name'       => [
                'required',
                'string',
                'max:50',
                // Unique per tenant AND per profile, because screen_name is
                // the key the Mobile App Menu Rights page grants by and the
                // key the app dispatches on.
                Rule::unique($table, 'screen_name')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('user_profile_name', $profile),
            ],
            'render_type' => 'required|in:native,webview,native_dynamic',
            // A WebView row needs a URL; a Native Dynamic row needs the page_key
            // of a page configured under Native Dynamic Pages; a native row is
            // addressed by its screen_name and needs neither.
            'web_url'     => 'required_if:render_type,webview,native_dynamic|nullable|string|max:2000',
            'open_mode'   => 'required|in:in_app,external',
        ], [
            'web_url.required_if' => 'Web URL is required for this render type.',
        ]);

        if ($validator->fails()) {
            $res = [
                'status_code' => 0,
                'message'     => $validator->messages()->first(),
            ];

            return is_mobile($type, 'add_mobileapp_menu_rights.index', $res);
        }

        $render_type = $request->get('render_type');
        if ($render_type == 'webview' && ! $this->hasRenderColumns($table)) {
            $res = [
                'status_code' => 0,
                'message'     => 'WebView menus need the render_type columns. Run the pending migrations and try again.',
            ];

            return is_mobile($type, 'add_mobileapp_menu_rights.index', $res);
        }

        // The listing joins mh.user_profile_id to tbluserprofilemaster, so a
        // row carrying the wrong id would save and then never appear.
        $user_profile = DB::table('tbluserprofilemaster')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('name', $profile)
            ->where('status', '1')
            ->first();

        if (empty($user_profile)) {
            $res = [
                'status_code' => 0,
                'message'     => 'The '.$profile.' profile is not active for this school.',
            ];

            return is_mobile($type, 'add_mobileapp_menu_rights.index', $res);
        }

        $now = date('Y-m-d H:i:s');

        $insert_data = [
            'sub_institute_id'            => $sub_institute_id,
            'user_profile_id'             => $user_profile->id,
            'user_profile_name'           => $profile,
            'main_title'                  => $request->get('main_title'),
            'menu_type'                   => $request->get('menu_type') ?: 'Heading',
            'main_title_color_code'       => $request->get('main_title_color_code'),
            'main_title_background_image' => $request->get('main_title_background_image'),
            'sub_title_of_main'           => $request->get('sub_title_of_main'),
            'sub_title_icon'              => $request->get('sub_title_icon'),
            'main_sort_order'             => $request->get('main_sort_order'),
            'sub_title_sort_order'        => $request->get('sub_title_sort_order'),
            'screen_name'                 => $request->get('screen_name'),
            'status'                      => $request->get('status'),
            'created_on'                  => $now,
            'updated_on'                  => $now,
            'updated_by'                  => session()->get('user_id'),
            'updated_ip_address'          => $_SERVER['REMOTE_ADDR'],
        ];

        if ($this->hasRenderColumns($table)) {
            $insert_data = array_merge($insert_data, $this->renderColumns($request, $render_type));
        }

        DB::table($table)->insert($insert_data);

        $res = [
            'status_code' => 1,
            'message'     => 'Mobile App Menu Added Successfully',
        ];

        return is_mobile($type, 'add_mobileapp_menu_rights.index', $res);
    }

    /**
     * The three WebView columns, read off the form and normalised so a row
     * can never say 'webview' while carrying no URL -- the one combination
     * that would hand the app a blank screen. The homescreen APIs defend
     * against it too; this stops it being stored in the first place.
     */
    private function renderColumns(Request $request, $render_type)
    {
        if ($render_type == 'native_dynamic') {
            // web_url holds the page_key of a row configured under Native
            // Dynamic Pages, not a URL -- open_mode does not apply to it, the
            // app always renders it in place.
            return [
                'render_type' => 'native_dynamic',
                'web_url'     => trim((string) $request->get('web_url')),
                'open_mode'   => 'in_app',
            ];
        }

        if ($render_type != 'webview') {
            return [
                'render_type' => 'native',
                'web_url'     => null,
                'open_mode'   => 'in_app',
            ];
        }

        $open_mode = $request->get('open_mode');

        return [
            'render_type' => 'webview',
            'web_url'     => trim((string) $request->get('web_url')),
            'open_mode'   => $open_mode == 'external' ? 'external' : 'in_app',
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $profile = $request->get('profile_hidden');
        $main_title = $request->get('main_title');
        $main_title_color_code = $request->get('main_title_color_code');
        $main_title_background_image = $request->get('main_title_background_image');
        $main_sort_order = $request->get('main_sort_order');
        $sub_title_of_main = $request->get('sub_title_of_main');
        $sub_title_icon = $request->get('sub_title_icon');
        $sub_title_sort_order = $request->get('sub_title_sort_order');
        $status = $request->get('status');
        $render_type = in_array($request->get('render_type'), ['webview', 'native_dynamic'], true)
            ? $request->get('render_type')
            : 'native';
        $updated_by = session()->get('user_id');
        $updated_on = date('Y-m-d H:i:s');
        $updated_ip = $_SERVER['REMOTE_ADDR'];

        $main_data = [
            'updated_on'         => $updated_on,
            'updated_by'         => $updated_by,
            'updated_ip_address' => $updated_ip,
        ];

        $sub_data = [
            'sub_title_of_main'    => $sub_title_of_main,
            'sub_title_icon'       => $sub_title_icon,
            'sub_title_sort_order' => $sub_title_sort_order,
            'status'               => $status,
            'updated_on'           => $updated_on,
            'updated_by'           => $updated_by,
            'updated_ip_address'   => $updated_ip,
        ];

        // Render type is a property of the individual menu item, so it rides
        // with $sub_data rather than $main_data, which fans out across every
        // row sharing a main_title.
        $table = $this->tableFor($profile);
        if ($this->hasRenderColumns($table)) {
            if (in_array($render_type, ['webview', 'native_dynamic'], true) && trim((string) $request->get('web_url')) === '') {
                $res = [
                    'status_code' => 0,
                    'message'     => 'Web URL is required for this render type.',
                ];

                return is_mobile($request->input('type'), 'add_mobileapp_menu_rights.index', $res);
            }

            $sub_data = array_merge($sub_data, $this->renderColumns($request, $render_type));
        }

        if ($profile == 'Student') {
            $get_old_data = mobile_homescreenModel::where(["id" => $id, "sub_institute_id" => $sub_institute_id])
                ->get()->toArray();
            $get_old_data = $get_old_data[0];
            $old_main_title = $get_old_data['main_title'];
            $old_main_title_color_code = $get_old_data['main_title_color_code'];
            $old_main_title_background_image = $get_old_data['main_title_background_image'];
            $old_main_sort_order = $get_old_data['main_sort_order'];

            if ($main_title != $old_main_title) {
                $main_data['main_title'] = $main_title;
                mobile_homescreenModel::where([
                    "main_title" => $old_main_title, "sub_institute_id" => $sub_institute_id,
                ])
                    ->update($main_data);
            }

            if ($main_title_color_code != $old_main_title_color_code) {
                $main_data['main_title_color_code'] = $main_title_color_code;
                mobile_homescreenModel::where([
                    "main_title_color_code" => $old_main_title_color_code, "main_title" => $main_title,
                    "sub_institute_id"      => $sub_institute_id,
                ])
                    ->update($main_data);
            }

            if ($main_title_background_image != $old_main_title_background_image) {
                $main_data['main_title_background_image'] = $main_title_background_image;
                mobile_homescreenModel::where([
                    "main_title_background_image" => $old_main_title_background_image, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code, "sub_institute_id" => $sub_institute_id,
                ])
                    ->update($main_data);
            }

            if ($main_sort_order != $old_main_sort_order) {
                $main_data['main_sort_order'] = $main_sort_order;
                mobile_homescreenModel::where([
                    "main_sort_order"             => $old_main_sort_order, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code,
                    "main_title_background_image" => $main_title_background_image,
                    "sub_institute_id"            => $sub_institute_id,
                ])
                    ->update($main_data);
            }
            mobile_homescreenModel::where(["id" => $id, "sub_institute_id" => $sub_institute_id])->update($sub_data);
        }

        if ($profile == 'Admin' || $profile == 'Teacher') {
            $get_old_data1 = teacher_mobile_homescreenModel::where([
                "id" => $id, "sub_institute_id" => $sub_institute_id, 'user_profile_name' => $profile,
            ])->get()->toArray();
            $get_old_data1 = $get_old_data1[0];
            $old_main_title1 = $get_old_data1['main_title'];
            $old_main_title_color_code1 = $get_old_data1['main_title_color_code'];
            $old_main_title_background_image1 = $get_old_data1['main_title_background_image'];
            $old_main_sort_order1 = $get_old_data1['main_sort_order'];

            if ($main_title != $old_main_title1) {
                $main_data['main_title'] = $main_title;
                teacher_mobile_homescreenModel::where([
                    "main_title"        => $old_main_title1, "sub_institute_id" => $sub_institute_id,
                    'user_profile_name' => $profile,
                ])
                    ->update($main_data);
            }

            if ($main_title_color_code != $old_main_title_color_code1) {
                $main_data['main_title_color_code'] = $main_title_color_code;
                teacher_mobile_homescreenModel::where([
                    "main_title_color_code" => $old_main_title_color_code1, "main_title" => $main_title,
                    "sub_institute_id"      => $sub_institute_id, 'user_profile_name' => $profile,
                ])
                    ->update($main_data);
            }

            if ($main_title_background_image != $old_main_title_background_image1) {
                $main_data['main_title_background_image'] = $main_title_background_image;
                teacher_mobile_homescreenModel::where([
                    "main_title_background_image" => $old_main_title_background_image1, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code, "sub_institute_id" => $sub_institute_id,
                    'user_profile_name'           => $profile,
                ])
                    ->update($main_data);
            }

            if ($main_sort_order != $old_main_sort_order1) {
                $main_data['main_sort_order'] = $main_sort_order;
                teacher_mobile_homescreenModel::where([
                    "main_sort_order"             => $old_main_sort_order1, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code,
                    "main_title_background_image" => $main_title_background_image,
                    "sub_institute_id"            => $sub_institute_id, 'user_profile_name' => $profile,
                ])
                    ->update($main_data);
            }
            teacher_mobile_homescreenModel::where([
                "id" => $id, "sub_institute_id" => $sub_institute_id,
            ])->update($sub_data);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Mobile App Menu Rights Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_mobileapp_menu_rights.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

}
