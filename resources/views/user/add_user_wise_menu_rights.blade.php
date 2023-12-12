@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add User Wise Menu Rights</h4>
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
                        {{ route('user_profile_wise_menu_rights.update', $data['id']) }}
                      @else
                        {{ route('user_profile_wise_menu_rights.store') }}
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
                                <select name="profile_id" onchange="getUserProfilewiseRightsData(this.value);" required id="profile_id" class="form-control">
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
                                                <th style="text-align: center;"> Menu Name </th>
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

@include('includes.footerJs')
<script>
    function getUserProfilewiseRightsData(x)
    {
        $('input[type="checkbox"]').each(function() {
            this.checked = false;
        });

        $("#main-data").empty(); 
        
        var path = "{{ route('ajax_user_profile_wise_rights') }}";
        // console.log(path);

        $.ajax({url: path,data:'profile_id='+x, success: function(result)
        {
            var main_data = result[0];
            var subdata = result[1];
            var lastdata = result[2];
            var rights = result[3];

            if(main_data !=0 && subdata != 0 && lastdata != 0 )
            {
                if(typeof(main_data) != "undefined" && main_data !== null) 
                {
                    $.each(main_data, function (i, item) 
                    {
                        // console.log(item['name']);
                        // #0707e8
                        $('table #main-data').append(`
                            <tr style="background:#25bdea;">
                                <td style="text-align: center;font-weigth:bold;">${item['name']}</td>
                                <td style="text-align: center;font-weigth:bold;">
                                    <div class="checkbox checkbox-success checkbox-circle">
                                        <input name="rights[${item['id']}][]" id="rights_${item['id']}" type="checkbox" platform="rights">
                                    <label for="rights_${item['id']}"> Rights </label>
                                    </div>
                                </td>
                            </tr>
                        `);
                        // console.log(subdata[item['menu_id']]);
                        if(typeof(subdata[item['id']]) != "undefined" && subdata[item['id']] !== null) {

                        $.each(subdata[item['id']], function (si, sitem) 
                        {
                            if(item['menu_type'] == "MASTER")
                            {
                                font_color = "color:#06d81f;";
                            }
                            else
                            {
                                font_color = "";
                            }

                            if(item['level'] == "1" && item['menu_type'] != "MASTER")
                            {
                                level2 = "<font style='color:#0707e8;'><i class='mdi mdi-chevron-double-right fa-lg'></i></font>";
                                font_weight = "font-weight:bold;color:#0707e8;";
                            }
                            else
                            {
                                level2 = "";
                                font_weight = "";
                            }
                            
                            // console.log(sitem['name']);
                            $('table #main-data').append(`
                                <tr>
                                    <td style="text-align: center;${font_color};${font_weight}">${level2}${sitem['name']}</td>
                                    <td style="text-align: center;font-weigth:bold;">
                                        <div class="checkbox checkbox-success checkbox-circle">
                                            <input name="rights[${sitem['id']}][]" id="rights_${sitem['id']}" type="checkbox" platform="rights">
                                            <label for="rights_${sitem['id']}"> Rights </label>
                                        </div>
                                    </td>
                                </tr>
                            `);

                            if(typeof(lastdata[sitem['id']]) != "undefined" && lastdata[sitem['id']] !== null) 
                            {
                                $.each(lastdata[sitem['id']], function (li, litem) 
                                {
                                    $('table #main-data').append(`
                                    <tr>
                                        <td style="text-align: center;font-weigth:bold;">${litem['name']}</td>
                                        <td style="text-align: center;font-weigth:bold;">
                                            <div class="checkbox checkbox-success checkbox-circle">
                                                <input name="rights[${litem['id']}][]" id="rights_${litem['id']}" type="checkbox" platform="rights">
                                                <label for="rights_${litem['id']}"> Rights </label>
                                            </div>
                                        </td>
                                    </tr>
                                    `);
                                });
                            }
                        });
                    }
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
        else{
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
