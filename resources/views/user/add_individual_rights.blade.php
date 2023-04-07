@include('includes.headcss')
<link href="/plugins/bower_components/switchery/dist/switchery.min.css" rel="stylesheet"/>
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Individual Rights</h4>
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
                    {{ route('add_individual_rights.update', $data['id']) }}
                    @else
                    {{ route('add_individual_rights.store') }}
                    @endif" enctype="multipart/form-data" method="post">
                        @if(!isset($data))
                            {{ method_field("POST") }}
                        @else
                            {{ method_field("PUT") }}
                        @endif
                        @csrf
                        <div class="row">
                            <div class="col-md-5 form-group">
                                <label>User Profiles</label>
                                <select name="profile_id"
                                        onchange="getProfileWiseUsersData(this.value);clearCheckBoxs();" required
                                        id="profile_id" class="form-control">
                                    <option value=""> Select User Profiles</option>
                                    @if(!empty($user_profiles))
                                        @foreach($user_profiles as $key => $value)
                                            <option value="{{ $value['id'] }}"> {{ $value['name'] }} </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-5 form-group">
                                <label>Users</label>
                                <select name="user_id" onchange="getIndividualRightsData(this.value);" required
                                        id="user_id" class="form-control">
                                    <option value=""> Select User</option>
                                </select>
                            </div>
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
                                        <tbody>
                                        @if(!empty($individualmenuMaster))
                                            @foreach($individualmenuMaster as $key => $value)
                                                <tr style="background-color: #6cd4f3 !important;">
                                                    <td><b>{{ $value['name'] }}</b></td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="view[{{ $value['id'] }}][]"
                                                                   id="view_{{ $value['id'] }}" value="1"
                                                                   type="checkbox" platform="view">
                                                            <label for="view_{{ $value['id'] }}"> View </label>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="add[{{ $value['id'] }}][]"
                                                                   id="add_{{ $value['id'] }}" value="1" type="checkbox"
                                                                   platform="add">
                                                            <label for="add_{{ $value['id'] }}"> Add </label>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="edit[{{ $value['id'] }}][]"
                                                                   id="edit_{{ $value['id'] }}" value="1"
                                                                   type="checkbox" platform="edit">
                                                            <label for="edit_{{ $value['id'] }}"> Edit </label>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="checkbox checkbox-success checkbox-circle">
                                                            <input name="delete[{{ $value['id'] }}][]"
                                                                   id="delete_{{ $value['id'] }}" value="1"
                                                                   type="checkbox" platform="delete">
                                                            <label for="delete_{{ $value['id'] }}"> Delete </label>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @if(!empty($individualsubmenuMaster[$value['id']]))
                                                    @foreach($individualsubmenuMaster[$value['id']] as $submenuKey => $submenuValue)
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
                                                                           id="add_{{ $submenuValue['id'] }}" value="1"
                                                                           type="checkbox" platform="add">
                                                                    <label for="add_{{ $submenuValue['id'] }}">
                                                                        Add </label>
                                                                </div>
                                                            </td>
                                                            <td style="text-align: center;">
                                                                <div class="checkbox checkbox-success checkbox-circle">
                                                                    <input name="edit[{{ $submenuValue['id'] }}][]"
                                                                           id="edit_{{ $submenuValue['id'] }}" value="1"
                                                                           type="checkbox" platform="edit">
                                                                    <label for="edit_{{ $submenuValue['id'] }}">
                                                                        Edit </label>
                                                                </div>
                                                            </td>
                                                            <td style="text-align: center;">
                                                                <div class="checkbox checkbox-success checkbox-circle">
                                                                    <input name="delete[{{ $submenuValue['id'] }}][]"
                                                                           id="delete_{{ $submenuValue['id'] }}"
                                                                           value="1" type="checkbox" platform="delete">
                                                                    <label for="delete_{{ $submenuValue['id'] }}">
                                                                        Delete </label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @if(!empty($individualSubsubmenuMaster[$submenuValue['id']]))
                                                            @foreach($individualSubsubmenuMaster[$submenuValue['id']] as $SubsubmenuKey => $SubsubmenuValue)
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
                                                                                value="1" type="checkbox"
                                                                                platform="add">
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
                                                                                value="1" type="checkbox"
                                                                                platform="edit">
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
                                                                                value="1" type="checkbox"
                                                                                platform="delete">
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
                            <input type="submit" name="submit" value="Save" class="btn btn-success">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- @if(isset($data)) @if($value['id'] == $data['parent_id']) selected @endif  @endif -->
@include('includes.footerJs')
<script src="/plugins/bower_components/switchery/dist/switchery.min.js"></script>
<script>
    function getProfileWiseUsersData(x) {
        var path = "{{ route('ajax_profileWiseUsers') }}";

        $('#user_id').find('option').remove().end().append('<option value="">Select User</option>').val('');

        $.ajax({
            url: path, data: 'profile_id=' + x, success: function (result) {

                for (var i = 0; i < result.length; i++) {
                    $("#user_id").append($("<option></option>").val(result[i]['id']).html(result[i]['user_name']));
                }
            }
        });
    }

    function clearCheckBoxs() {
        $('input[type="checkbox"]').each(function () {
            this.checked = false;
        });
    }

    function getIndividualRightsData(x) {
        var profile_id = document.getElementById("profile_id").value;
        $('input[type="checkbox"]').each(function () {
            this.checked = false;
        });
        var path = "{{ route('ajax_individualrights') }}";
        $.ajax({
            url: path, data: 'profile_id=' + profile_id + '&user_id=' + x, success: function (result) {
                if ("add" in result) {
                    for (i = 0; i < result.add.length; i++) {
                        var menuAdd = result.add[i];
                        var res = menuAdd.split("_");
                        var finalAddId = "add_" + res[0];
                        if (document.getElementById(finalAddId)) {
                            document.getElementById(finalAddId).checked = true;
                        }
                    }
                }
                if ("edit" in result) {
                    for (i = 0; i < result.edit.length; i++) {
                        var menuEdit = result.edit[i];
                        var res = menuEdit.split("_");
                        var finalEditId = "edit_" + res[0];
                        if (document.getElementById(finalEditId)) {
                            document.getElementById(finalEditId).checked = true;
                        }
                    }
                }
                if ("delete" in result) {
                    for (i = 0; i < result.delete.length; i++) {
                        var menuDelete = result.delete[i];
                        var res = menuDelete.split("_");
                        var finalDeleteId = "delete_" + res[0];
                        if (document.getElementById(finalDeleteId)) {
                            document.getElementById(finalDeleteId).checked = true;
                        }
                    }
                }
                if ("view" in result) {
                    for (i = 0; i < result.view.length; i++) {
                        var menuView = result.view[i];
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
    $(function () {
        // Switchery
        var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
        $('.js-switch').each(function () {
            new Switchery($(this)[0], $(this).data());
        });
    });
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
