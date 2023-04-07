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
                    <form action="{{route('ajx_userProfile_Data_Create')}}" method="post">
                        <div class="col-md-6 form-group">
                            <label>User Profiles</label>
                            <select name="profile_id" onchange="getGroupwiseRightsData(this.value);" required
                                    id="profile_id" class="form-control">
                                @if(isset($value['id']))
                                    <option value="{{ $value['id'] }}">{{ $value['name'] }}</option>
                                @else
                                    <option value=""> Select User Profiles</option>
                                @endif
                                @if(!empty($user_profiles))
                                    @foreach($user_profiles as $key => $value)
                                        <option value="{{ $value['id'] }}"> {{ $value['name'] }} </option>
                                    @endforeach
                                @endif
                            </select>
                            <button type="submit" class="btn btn-success">Search</button>
                        </div>

                    </form>

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
                            <div class="col-md-12">
                                <div class="table-responsive" id="groupwiseRightsTable">
                                    <table class="table table-bordered table-striped responsive-utilities">
                                        <thead>
                                        <tr>
                                            <th style="text-align: center;"> Menu Name</th>
                                            <th style="text-align: center;"> Can View <input id="checkall"
                                                                                             onchange="checkAll(this,'view');"
                                                                                             type="checkbox"></th>
                                            <th style="text-align: center;"> Can Add <input id="checkall"
                                                                                            onchange="checkAll(this,'add');"
                                                                                            type="checkbox"></th>
                                            <th style="text-align: center;"> Can Edit <input id="checkall"
                                                                                             onchange="checkAll(this,'edit');"
                                                                                             type="checkbox"></th>
                                            <th style="text-align: center;"> Can Delete <input id="checkall"
                                                                                               onchange="checkAll(this,'delete');"
                                                                                               type="checkbox"></th>
                                        </tr>
                                        </thead>

                                        <tbody id="main-data">
                                        </tbody>
                                        <tbody>
                                        @if(!empty($groupwisemenuMaster))
                                            <?php                                          if (isset($rights["add"])) {
                                                for ($i = 0; $i < count($rights["add"]); $i++) {
                                                    $menuAdd = $rights["add"][$i];
                                                    $res = $menuAdd . array_split("_");
                                                    $finalAddId = "add_" + res[0];
                                                    // console.log(finalAddId);

                                                    //     if(document.getElementById(finalAddId))
                                                    //     {
                                                    //         document.getElementById(finalAddId).checked = true;
                                                    //     }
                                                    // }
                                                }

                                            } ?>
                                            @foreach($groupwisemenuMaster as $key => $value)
                                                <input type="hidden" name="profile_id" value="{{$value['profile_id']}}">
                                                <tr style="background-color: #6cd4f3 !important;">

                                                    <td><b>{{ $value['name'] }}</span></b></td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="view[{{ $value['menu_id'] }}][]"
                                                                   id="view_{{ $value['menu_id'] }}" value="1"
                                                                   type="checkbox" platform="view">
                                                            <label for="view_{{ $value['menu_id'] }}"> View </label>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="add[{{ $value['menu_id'] }}][]"
                                                                   id="add_{{ $value['menu_id'] }}" value="1"
                                                                   platform="add" type="checkbox">
                                                            <label for="add_{{ $value['menu_id'] }}"> Add </label>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="edit[{{ $value['menu_id'] }}][]"
                                                                   id="edit_{{ $value['menu_id'] }}" value="1"
                                                                   platform="edit" type="checkbox">
                                                            <label for="edit_{{ $value['menu_id'] }}"> Edit </label>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="delete[{{ $value['menu_id'] }}][]"
                                                                   id="delete_{{ $value['menu_id'] }}" platform="delete"
                                                                   value="1" type="checkbox">
                                                            <label for="delete_{{ $value['menu_id'] }}"> Delete </label>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @if(!empty($groupwisesubmenuMaster[$value['menu_id']]))
                                                    @foreach($groupwisesubmenuMaster[$value['menu_id']] as $submenuKey => $submenuValue)
                                                        <tr>
                                                            @php
                                                                if($value['menu_type'] == "MASTER")
                                                                {
                                                                    $font_color = "color:#06d81f;";
                                                                }
                                                                else
                                                                {
                                                                    $font_color = "";
                                                                }
                                                                if($value['level'] == "1" && $value['menu_type'] != "MASTER")
                                                                {
                                                                    $level2 = "<font style='color:#0707e8;'><i class='mdi mdi-chevron-double-right fa-lg'></i></font>";
                                                                    $font_weight = "font-weight:bold;color:#0707e8;";
                                                                }
                                                                else
                                                                {
                                                                    $level2 = "";
                                                                    $font_weight = "";
                                                                }
                                                            @endphp
                                                            <td style="text-align: center;{{$font_color}}{{$font_weight}}">{!!$level2!!}{{ $submenuValue['name'] }}</td>
                                                            <td style="text-align: center;">
                                                                <div class="checkbox checkbox-success checkbox-circle">
                                                                    <input name="view[{{ $submenuValue['id'] }}][]"
                                                                           id="view_{{ $submenuValue['id'] }}" value="1"
                                                                           type="checkbox" platform="view">
                                                                    <label for="view_{{ $submenuValue['id'] }}">
                                                                        View </label>
                                                                </div>
                                                            </td>
                                                            <td style="text-align: center;">
                                                                <div class="checkbox checkbox-success checkbox-circle">
                                                                    <input name="add[{{ $submenuValue['id'] }}][]"
                                                                           id="add_{{ $submenuValue['id'] }}"
                                                                           platform="add" value="1" type="checkbox">
                                                                    <label for="add_{{ $submenuValue['id'] }}">
                                                                        Add </label>
                                                                </div>
                                                            </td>
                                                            <td style="text-align: center;">
                                                                <div class="checkbox checkbox-success checkbox-circle">
                                                                    <input name="edit[{{ $submenuValue['id'] }}][]"
                                                                           id="edit_{{ $submenuValue['id'] }}"
                                                                           platform="edit" value="1" type="checkbox">
                                                                    <label for="edit_{{ $submenuValue['id'] }}">
                                                                        Edit </label>
                                                                </div>
                                                            </td>
                                                            <td style="text-align: center;">
                                                                <div class="checkbox checkbox-success checkbox-circle">
                                                                    <input name="delete[{{ $submenuValue['id'] }}][]"
                                                                           id="delete_{{ $submenuValue['id'] }}"
                                                                           platform="delete" value="1" type="checkbox">
                                                                    <label for="delete_{{ $submenuValue['id'] }}">
                                                                        Delete </label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @if(!empty($groupwiseSubsubmenuMaster[$submenuValue['menu_id']]))
                                                            @foreach($groupwiseSubsubmenuMaster[$submenuValue['menu_id']] as $SubsubmenuKey => $SubsubmenuValue)
                                                                <tr>
                                                                    <td style="text-align: center;">{{ $SubsubmenuValue['name'] }}</td>
                                                                    <td style="text-align: center;">
                                                                        <div
                                                                            class="checkbox checkbox-success checkbox-circle">
                                                                            <input
                                                                                name="view[{{ $SubsubmenuValue['id'] }}][]"
                                                                                id="view_{{ $SubsubmenuValue['id'] }}"
                                                                                value="1" type="checkbox"
                                                                                platform="view">
                                                                            <label
                                                                                for="view_{{ $SubsubmenuValue['id'] }}">
                                                                                View </label>
                                                                        </div>
                                                                    </td>
                                                                    <td style="text-align: center;">
                                                                        <div
                                                                            class="checkbox checkbox-success checkbox-circle">
                                                                            <input
                                                                                name="add[{{ $SubsubmenuValue['id'] }}][]"
                                                                                id="add_{{ $SubsubmenuValue['id'] }}"
                                                                                platform="add" value="1"
                                                                                type="checkbox">
                                                                            <label
                                                                                for="add_{{ $SubsubmenuValue['id'] }}">
                                                                                Add </label>
                                                                        </div>
                                                                    </td>
                                                                    <td style="text-align: center;">
                                                                        <div
                                                                            class="checkbox checkbox-success checkbox-circle">
                                                                            <input
                                                                                name="edit[{{ $SubsubmenuValue['id'] }}][]"
                                                                                id="edit_{{ $SubsubmenuValue['id'] }}"
                                                                                platform="edit" value="1"
                                                                                type="checkbox">
                                                                            <label
                                                                                for="edit_{{ $SubsubmenuValue['id'] }}">
                                                                                Edit </label>
                                                                        </div>
                                                                    </td>
                                                                    <td style="text-align: center;">
                                                                        <div
                                                                            class="checkbox checkbox-success checkbox-circle">
                                                                            <input
                                                                                name="delete[{{ $SubsubmenuValue['id'] }}][]"
                                                                                id="delete_{{ $SubsubmenuValue['id'] }}"
                                                                                platform="delete" value="1"
                                                                                type="checkbox">
                                                                            <label
                                                                                for="delete_{{ $SubsubmenuValue['id'] }}">
                                                                                Delete </label>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Save" class="btn btn-success">
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
    function getGroupwiseRightsData(x) {
        $('input[type="checkbox"]').each(function () {
            this.checked = false;
        });

        var path = "{{ route('ajax_groupwiserights') }}";
        // console.log(path);

        $.ajax({
            url: path, data: 'profile_id=' + x, success: function (rights) {
                // console.log(result[0]);
                // console.log(result[1]);
                // console.log(result[2]);
                // console.log(result[3]);

                // var main_data = result[0];
                // var subdata = result[1];
                // var lastdata = result[2];
                // var rights = result[3];
                console.log(rights);

                if ("add" in rights) {
                    for (i = 0; i < rights.add.length; i++) {
                        var menuAdd = rights.add[i];
                        var res = menuAdd.split("_");
                        var finalAddId = "add_" + res[0];
                        console.log(finalAddId);

                        if (document.getElementById(finalAddId)) {
                            document.getElementById(finalAddId).checked = true;
                        }
                    }
                }
                if ("edit" in rights) {
                    for (i = 0; i < rights.edit.length; i++) {
                        var menuEdit = rights.edit[i];
                        var res = menuEdit.split("_");
                        var finalEditId = "edit_" + res[0];
                        if (document.getElementById(finalEditId)) {
                            document.getElementById(finalEditId).checked = true;
                        }
                    }
                }
                if ("delete" in rights) {
                    for (i = 0; i < rights.delete.length; i++) {
                        var menuDelete = rights.delete[i];
                        var res = menuDelete.split("_");
                        var finalDeleteId = "delete_" + res[0];
                        if (document.getElementById(finalDeleteId)) {
                            document.getElementById(finalDeleteId).checked = true;
                        }
                    }
                }
                if ("view" in rights) {
                    for (i = 0; i < rights.view.length; i++) {
                        var menuView = rights.view[i];
                        var res = menuView.split("_");
                        var finalViewId = "view_" + res[0];
                        if (document.getElementById(finalViewId)) {
                            document.getElementById(finalViewId).checked = true;
                        }
                    }
                }
            }
        });
    }
</script>
<script>
    function checkAll(ele, platform) {
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
