@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Groupwise Rights</h4>
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
                      {{ route('add_groupwise_rights.update', $data['id']) }}
                      @else
                      {{ route('add_groupwise_rights.store') }}
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
                                <select name="profile_id" onchange="getGroupwiseRightsData(this.value);" required id="profile_id" class="form-control">
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
                                                <th style="text-align: center;"> Can View <input id="checkall" onchange="checkAll(this,'view');" type="checkbox"></th>
                                                <th style="text-align: center;"> Can Add <input id="checkall" onchange="checkAll(this,'add');" type="checkbox"></th>
                                                <th style="text-align: center;"> Can Edit <input id="checkall" onchange="checkAll(this,'edit');" type="checkbox"></th>
                                                <th style="text-align: center;"> Can Delete <input id="checkall" onchange="checkAll(this,'delete');" type="checkbox"></th>
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

    function getGroupwiseRightsData(x){

        $('input[type="checkbox"]').each(function() {
            this.checked = false;
        });

        $("#main-data").empty(); 
        
     var path = "{{ route('ajax_groupwiserights') }}";
            // console.log(path);

        $.ajax({url: path,data:'profile_id='+x, success: function(result){
            // console.log(result);
            // console.log(result[0]);
            // console.log(result[1]);
            // console.log(result[2]);
            // console.log(result[3]);

            var main_data = result[0];
            var subdata = result[1];
            var lastdata = result[2];
            var rights = result[3];

            if(main_data !=0 && subdata != 0 && lastdata != 0 ){

            if(typeof(main_data) != "undefined" && main_data !== null) {
                 $.each(main_data, function (i, item) {
                    // console.log(item['name']);
                    // #0707e8
                     $('table #main-data').append(`
                        <tr style="background:#25bdea;">
                        <td style="text-align: center;font-weigth:bold;">${item['name']}</td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="view[${item['menu_id']}][]" id="view_${item['menu_id']}" value="1" type="checkbox" platform="view">
                                <label for="view_${item['menu_id']}"> View </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="add[${item['menu_id']}][]" id="add_${item['menu_id']}" value="1" platform="add" type="checkbox" >
                                <label for="add_${item['menu_id']}"> Add </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="edit[${item['menu_id']}][]" id="edit_${item['menu_id']}" value="1" platform="edit" type="checkbox" >
                                <label for="edit_${item['menu_id']}"> Edit </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="delete[${item['menu_id']}][]" id="delete_${item['menu_id']}" platform="delete" value="1" type="checkbox" >
                                <label for="delete_${item['menu_id']}"> Delete </label>
                            </div>
                        </td>
                        </tr>
                     `);
                     // console.log(subdata[item['menu_id']]);
                     if(typeof(subdata[item['menu_id']]) != "undefined" && subdata[item['menu_id']] !== null) {

                      $.each(subdata[item['menu_id']], function (si, sitem) {
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
                                <input name="view[${sitem['menu_id']}][]" id="view_${sitem['menu_id']}" value="1" type="checkbox" platform="view">
                                <label for="view_${sitem['menu_id']}"> View </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="add[${sitem['menu_id']}][]" id="add_${sitem['menu_id']}" value="1" platform="add" type="checkbox" >
                                <label for="add_${sitem['menu_id']}"> Add </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="edit[${sitem['menu_id']}][]" id="edit_${sitem['menu_id']}" value="1" platform="edit" type="checkbox" >
                                <label for="edit_${sitem['menu_id']}"> Edit </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="delete[${sitem['menu_id']}][]" id="delete_${sitem['menu_id']}" platform="delete" value="1" type="checkbox" >
                                <label for="delete_${sitem['menu_id']}"> Delete </label>
                            </div>
                        </td>
                        </tr>
                     `);
                if(typeof(lastdata[sitem['menu_id']]) != "undefined" && lastdata[sitem['menu_id']] !== null) {

                      $.each(lastdata[sitem['menu_id']], function (li, litem) {
                    // console.log(sitem['name']);
                        $('table #main-data').append(`
                        <tr>
                        <td style="text-align: center;font-weigth:bold;">${litem['name']}</td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="view[${litem['menu_id']}][]" id="view_${litem['menu_id']}" value="1" type="checkbox" platform="view">
                                <label for="view_${litem['menu_id']}"> View </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="add[${litem['menu_id']}][]" id="add_${litem['menu_id']}" value="1" platform="add" type="checkbox" >
                                <label for="add_${litem['menu_id']}"> Add </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="edit[${litem['menu_id']}][]" id="edit_${litem['menu_id']}" value="1" platform="edit" type="checkbox" >
                                <label for="edit_${litem['menu_id']}"> Edit </label>
                            </div>
                        </td>
                        <td style="text-align: center;font-weigth:bold;">
                            <div class="checkbox checkbox-success checkbox-circle">
                                <input name="delete[${litem['menu_id']}][]" id="delete_${litem['menu_id']}" platform="delete" value="1" type="checkbox" >
                                <label for="delete_${litem['menu_id']}"> Delete </label>
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

            if ("add" in rights)
            {
                for (i = 0; i < rights.add.length; i++) {
                    var menuAdd = rights.add[i];
                    var res = menuAdd.split("_");
                    var finalAddId = "add_"+res[0];
                    // console.log(finalAddId);
                    if(document.getElementById(finalAddId))
                    {
                        document.getElementById(finalAddId).checked = true;
                    }
                }
            }
            if ("edit" in rights)
            {
                for (i = 0; i < rights.edit.length; i++) {
                    var menuEdit = rights.edit[i];
                    var res = menuEdit.split("_");
                    var finalEditId = "edit_"+res[0];
                    if(document.getElementById(finalEditId))
                    {
                        document.getElementById(finalEditId).checked = true;
                    }
                }
            }
            if ("delete" in rights)
            {
                for (i = 0; i < rights.delete.length; i++) {
                    var menuDelete = rights.delete[i];
                    var res = menuDelete.split("_");
                    var finalDeleteId = "delete_"+res[0];
                    if(document.getElementById(finalDeleteId))
                    {
                        document.getElementById(finalDeleteId).checked = true;
                    }
                }
            }
            if ("view" in rights)
            {
                for (i = 0; i < rights.view.length; i++) {
                    var menuView = rights.view[i];
                    var res = menuView.split("_");
                    var finalViewId = "view_"+res[0];
                    if(document.getElementById(finalViewId))
                    {
                        document.getElementById(finalViewId).checked = true;
                    }
                }
            }
        }else{
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
