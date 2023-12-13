@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Mobile App Menu Rights</h4>
            </div>
        </div>
        <div class="card">
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif

           
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="@if (isset($data))
                      {{ route('mobile_app_menu_rights.update', $data['id']) }}
                      @else
                      {{ route('mobile_app_menu_rights.store') }}
                      @endif" enctype="multipart/form-data" method="post">
                        @if(!isset($data))
                        {{ method_field("POST") }}
                        @else
                        {{ method_field("PUT") }}
                        @endif
                        @csrf

                        <div class="row">                        
                            <div class="col-md-6 form-group">
                                <label>User Profiles</label>
                                <select name="profile_id" onchange="getMobileAppMenuRightsData(this.value);" required id="profile_id" class="form-control">
                                    <option value=""> Select User Profiles </option>
                                    @if(!empty($user_profiles))
                                        @foreach($user_profiles as $key => $value)
                                            <option value="{{ $value['id'] }}"> {{ $value['name'] }} </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-12">
                                <div class="table-responsive" id="groupwiseRightsTable">
                                    <table class="table table-bordered table-striped responsive-utilities">
                                        <thead>
                                            <tr>
                                                <th style="text-align: center;">User Profile Name</th>
                                                <th style="text-align: center;">Main Title</th>
                                                <th style="text-align: center;">Sub Title of Main</th>
                                                <th style="text-align: center;">Screen Name</th>
                                                <th style="text-align: center;"> Rights <input id="checkall" onchange="checkAll(this,'rights');" type="checkbox"></th>
                                            </tr>
                                        </thead>

                                        <tbody id="main-data">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>                                
                                <input type="submit" name="submit" value="Save" class="btn btn-success" >
                            </center>
                        </div>
                    </form>
                </div>
            </div> 
        </div>
    </div>
</div>
<!-- @if(isset($data)) @if($value['id'] == $data['parent_id']) selected @endif  @endif -->
@include('includes.footerJs')
<script>

    function getMobileAppMenuRightsData(x)
    {

        $('input[type="checkbox"]').each(function() {
            this.checked = false;
        });

        $("#main-data").empty(); 
        
        var path = "{{ route('ajax_mobile_app_menu_rights') }}";
            // console.log(path);

        $.ajax({url: path,data:'profile_id='+x, success: function(result){
            var main_data = result[0];
            var rights = result[1];

            if(main_data !=0)
            {
                if(typeof(main_data) != "undefined" && main_data !== null) 
                {
                    $.each(main_data, function (i, item) 
                    {
                        // console.log(item['name']);
                        // #0707e8
                        $('table #main-data').append(`
                            <tr>
                                <td style="text-align: center;font-weigth:bold;">${item['user_profile_name']}</td>
                                <td style="text-align: center;font-weigth:bold;">${item['main_title']}</td>
                                <td style="text-align: center;font-weigth:bold;">${item['sub_title_of_main']}</td>
                                <td style="text-align: center;font-weigth:bold;">${item['screen_name']}</td>
                                <td style="text-align: center;font-weigth:bold;">
                                    <div class="checkbox checkbox-success checkbox-circle">
                                        <input name="rights[${item['screen_name']}][]" id="rights_${item['screen_name']}" value="1" type="checkbox" platform="rights">
                                        <label for="rights_${item['screen_name']}"> Rights </label>
                                        
                                        <input name="main_title[${item['screen_name']}][]" id="rights_${item['main_title']}" value="${item['main_title']}" type="hidden">
                                        <input name="menu_type[${item['screen_name']}][]" id="rights_${item['menu_type']}" value="${item['menu_type']}" type="hidden">
                                        <input name="main_title_color_code[${item['screen_name']}][]" id="rights_${item['main_title_color_code']}" value="${item['main_title_color_code']}" type="hidden">
                                        <input name="main_title_background_image[${item['screen_name']}][]" id="rights_${item['main_title_background_image']}" value="${item['main_title_background_image']}" type="hidden">
                                        <input name="sub_title_of_main[${item['screen_name']}][]" id="rights_${item['sub_title_of_main']}" value="${item['sub_title_of_main']}" type="hidden">
                                        <input name="sub_title_icon[${item['screen_name']}][]" id="rights_${item['sub_title_icon']}" value="${item['main_title_background_image']}" type="hidden">
                                        <input name="sub_title_api[${item['screen_name']}][]" id="rights_${item['sub_title_api']}" value="${item['sub_title_api']}" type="hidden">
                                        <input name="sub_title_api_param[${item['screen_name']}][]" id="rights_${item['sub_title_api_param']}" value="${item['sub_title_api_param']}" type="hidden">
                                        <input name="main_sort_order[${item['screen_name']}][]" id="rights_${item['main_sort_order']}" value="${item['main_sort_order']}" type="hidden">
                                        <input name="sub_title_sort_order[${item['screen_name']}][]" id="rights_${item['sub_title_sort_order']}" value="${item['sub_title_sort_order']}" type="hidden">
                                        <input name="status[${item['screen_name']}][]" id="rights_${item['status']}" value="${item['status']}" type="hidden">
                                    </div>
                                </td>
                                </td>
                            </tr>
                        `);
                    });
                }

                if ("rights" in rights)
                {
                    for (i = 0; i < rights.rights.length; i++) 
                    {
                        var menuView = rights.rights[i];
                        var finalViewId = "rights_"+menuView;
                        console.log(finalViewId);
                        if(document.getElementById(finalViewId))
                        {
                            document.getElementById(finalViewId).checked = true;
                        }
                    }
                }  
            }
            else
            {
                $('table #main-data').append(`<tr><td colspan=5  style="text-align:center">No Rights Given</td></tr>`);
            }
        }});
        // table.draw();
    }
</script>
<script>
    function checkAll(ele,platform) {
         var checkboxes = document.getElementsByTagName('input');
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                // console.log(checkboxes[i].getAttribute('platform'));
                 if (checkboxes[i].type == 'checkbox' && platform == checkboxes[i].getAttribute('platform')) {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox' && platform == checkboxes[i].getAttribute('platform')) {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
@include('includes.footer')
