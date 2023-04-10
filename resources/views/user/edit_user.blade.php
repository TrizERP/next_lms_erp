@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')
<style type="text/css">
br {
     display: block;
}
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Edit User</h4>
            </div>
        </div>        
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = Session::get('success'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $message }}</strong>
            </div>
            @endif
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12"> 
                    @php
                    
                    $user_profiles = $data['user_profiles'];
                    $subject_data = $data['subject_data'];
                    $subject_data_selected_arr = $data['subject_data_selected_arr'];
                    $custom_fields = $data['custom_fields'];
                    $data_fields = $data['data_fields'];
                    $data = $data['data'];
                    
                    @endphp                           
                    <form action="{{ route('add_user.update', $data['id']) }}" enctype="multipart/form-data" method="post">
                    {{ method_field("PUT") }}                        
                    @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Name Suffix</label>
                                <select name="name_suffix" id="name_suffix" class="form-control" required>
                                    <option> Select Name Suffix </option>
                                    <option value="Mr." @if(isset($data))@if("Mr." == $data['name_suffix']) selected @endif  @endif> Mr. </option>
                                    <option value="Mrs." @if(isset($data))@if("Mrs." == $data['name_suffix']) selected @endif  @endif> Mrs. </option>
                                    <option value="Miss." @if(isset($data))@if("Miss." == $data['name_suffix']) selected @endif  @endif> Miss. </option>
                                </select>
                            </div>                            
                            <div class="col-md-4 form-group">
                                <label>First Name </label>
                                <input type="text" id='first_name' value="@if(isset($data['first_name'])){{ $data['first_name'] }}@endif" required name="first_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Middle Name</label>
                                <input type="text" value="@if(isset($data['middle_name'])){{ $data['middle_name'] }}@endif" id='middle_name' required name="middle_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Last Name</label>
                                <input type="text" onchange="getUsername();" value="@if(isset($data['last_name'])){{ $data['last_name'] }}@endif" id='last_name' required name="last_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>User Name </label>
                                <input type="text" value="@if(isset($data['user_name'])){{ $data['user_name'] }}@endif" id='user_name' required name="user_name" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Email</label>
                                <span><br><b>{{ $data['email'] }}</b></span>                                
                                <!-- <input type="text" id='email' value="@if(isset($data['email'])){{ $data['email'] }}@endif" required name="email" class="form-control"> -->
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Mobile</label>
                                <input type="text" value="@if(isset($data['mobile'])){{ $data['mobile'] }}@endif" id='mobile' required name="mobile" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Subject</label>
                                <select name="subject_ids[]" id="subject_ids[]" class="form-control" multiple style="height:200px;">
                                    <option value="0"> Select Subject </option>
                                    @if(!empty($subject_data))  
                                    @foreach($subject_data as $key => $val)                                   
                                        <option value="{{ $val['id'] }}" 

                                        @php if( in_array($val['id'],$subject_data_selected_arr) ){ echo "selected"; }@endphp
                                    
                                        > {{ $val['subject_name'] }} </option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Gender</label>
                                <div class="radio-list">
                                    <label class="radio-inline p-0">
                                        <div class="radio radio-success">
                                            <input type="radio" @if(isset($data))@if("M" == $data['gender']) checked @endif  @endif name="gender" id="male" value="M" required>
                                            <label for="male">Male</label>
                                        </div>
                                    </label>
                                    <label class="radio-inline">
                                        <div class="radio radio-success">
                                            <input type="radio" @if(isset($data))@if("F" == $data['gender']) checked @endif  @endif name="gender" id="female" value="F" required>
                                            <label for="female">Female</label>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Address</label>
                                <textarea class="form-control" required name="address">@if(isset($data['address'])){{ $data['address'] }}@endif</textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>City</label>
                                <input type="text" value="@if(isset($data['city'])){{ $data['city'] }}@endif" id='city' required name="city" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>State</label>
                                <input type="text" value="@if(isset($data['state'])){{ $data['state'] }}@endif" id='state' required name="state" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Pincode</label>
                                <input type="number" value="@if(isset($data['pincode'])){{$data['pincode']}}@endif" id='pincode' required name="pincode" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>User Profile</label>
                                <select name="user_profile_id" required id="user_profile_id" class="form-control">
                                    <option value="0"> Select Parent Profile </option>                                    
                                    
                                    @if(!empty($user_profiles))  
                                    @foreach($user_profiles as $key => $value)
                                   
                                        <option value="{{ $value['id'] }}" @if(isset($data)) @if($value['id'] == $data['user_profile_id']) selected @endif  @endif  > {{ $value['name'] }} </option>
                                    @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="total_lecture_div">
                                <label>Total Lectures</label>
                                <input type="number" id='total_lecture' name="total_lecture" class="form-control" value="@if(isset($data['total_lecture'])){{$data['total_lecture']}}@endif">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Join Year</label>
                                <input type="number" value="@if(isset($data['join_year'])){{$data['join_year']}}@endif" id='join_year' required name="join_year" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Password</label>
                                <input type="password" value="@if(isset($data['password'])){{ $data['password'] }}@endif" id='password' required name="password" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Birthdate</label>
                                <div class="input-daterange input-group" id="date-range">
                                <input type="text" required class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data['birthdate'])){{$data['birthdate']}}@endif" name="birthdate" autocomplete="off"><span class="input-group-addon"><i class="icon-calender"></i></span> 
                             </div>
                            </div>
                            <div class="col-md-4 form-group ml-0 mr-0">
                                <label>Inactive Status</label>
                                <select id='status' name="status" class="form-control">
                                <option value="1" @if(isset($data['status'])) @if($data['status'] == 1) selected @endif @endif> No </option>
                                <option value="0" @if(isset($data['status'])) @if($data['status'] == 0) selected @endif @endif> Yes </option>
                                </select>
                            </div>
                            <div class="col-sm-4 ol-md-4 col-xs-12">                         
                                <label for="input-file-now">User Image</label>
                                <input type="file" accept="image/*" name="user_image" @if(isset($data))data-default-file="/storage/user/{{ $data['image'] }}" @else required @endif id="input-file-now" class="dropify" /> 
                            </div>
                            @if(isset($custom_fields))
                            @foreach($custom_fields as $key => $value)
                            <div class="col-md-4 form-group">
                                <label>{{ $value['field_label'] }}</label>
                                @if($value['field_type'] == 'file')
                                <input type="{{ $value['field_type'] }}" accept="image/*" id="input-file-now" data-default-file="@if(isset($data[$value['field_name']])){{'/storage/user/'.$data[$value['field_name']]}}@endif"  required name="{{ $value['field_name'] }}" class="form-control">
                                @elseif($value['field_type'] == 'date')
                                <div class="input-daterange input-group" >
                                <input type="text" class="form-control mydatepicker" placeholder="dd/mm/yyyy" value="@if(isset($data[$value['field_name']])){{$data[$value['field_name']]}}@endif" autocomplete="off" id="{{ $value['field_name'] }}" required name="{{ $value['field_name'] }}" class="form-control"><span class="input-group-addon"><i class="icon-calender"></i></span>
                                </div>
                                @elseif($value['field_type'] == 'checkbox')
                                <div class="checkbox-list">
                                    @if(isset($data_fields[$value['id']]))
                                    @foreach($data_fields[$value['id']] as $keyData => $valueData )
                                        <label class="checkbox-inline">
                                            <div class="checkbox checkbox-success">
                                                <input type="checkbox" @if($valueData['display_value'] == $data[$value['field_name']]) checked @endif name="{{ $value['field_name'] }}[]" value="{{ $valueData['display_value'] }}" id="{{ $valueData['display_value'] }}" required>
                                                <label for="{{ $valueData['display_value'] }}">{{ $valueData['display_text'] }}</label>
                                            </div>
                                        </label>
                                        @endforeach
                                    @endif
                                </div>
                                @elseif($value['field_type'] == 'dropdown')
                                        <select name="{{ $value['field_name'] }}" class="form-control" required id="{{ $value['field_name'] }}">
                                            <option value=""> SELECT {{ strtoupper($value['field_label']) }} </option>
                                            
                                        @if(isset($data_fields[$value['id']]))
                                            @foreach($data_fields[$value['id']] as $keyData => $valueData)
                                            @php
                                                $selected = '';
                                            @endphp
                                            @if(isset($data))
                                                @if($data[$value['field_name']]== $valueData['display_value'])
                                                    @php
                                                        $selected = 'selected';
                                                    @endphp
                                                @endif
                                            @endif
                                            <option value="{{ $valueData['display_value'] }}" {{$selected}}> {{ $valueData['display_text'] }} </option>
                                            @endforeach
                                        @endif
                                        </select>
                                @elseif($value['field_type'] == 'textarea')
                                <textarea id="{{ $value['field_name'] }}" class="form-control" required name="{{ $value['field_name'] }}" placeholder="{{ $value['field_message'] }}">
                                @if(isset($data[$value['field_name']])){{$data[$value['field_name']]}}@endif
                                </textarea>
                                @else
                                <input type="{{ $value['field_type'] }}" id="{{ $value['field_name'] }}" value="@if(isset($data[$value['field_name']])){{$data[$value['field_name']]}}@endif" placeholder="{{ $value['field_message'] }}" required name="{{ $value['field_name'] }}" class="form-control">
                                @endif
                            </div>
                            @endforeach
                            @endif
                            <div class="col-md-12 form-group mt-2">
                                <center>                                    
                                    <input type="submit" name="submit" value="Update" class="btn btn-success" >
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            </div>    
        </div>        
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
    (function() {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function(el) {
            new CBPFWTabs(el);
        });
    })();
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/dropify.min.js"></script>
    <script>
    $(document).ready(function() {
        var val1 = $.trim($("#user_profile_id").find("option:selected").text());            

        if(val1 == 'Teacher' || val1 == 'TEACHER')
        {
            $("#total_lecture_div").css("display","block");
        }
        else
        {
            $("#total_lecture_div").css("display","none");
        }

        // Basic
        $('.dropify').dropify();
        // Translated
        $('.dropify-fr').dropify({
            messages: {
                default: 'Glissez-déposez un fichier ici ou cliquez',
                replace: 'Glissez-déposez un fichier ou cliquez pour remplacer',
                remove: 'Supprimer',
                error: 'Désolé, le fichier trop volumineux'
            }
        });
        // Used events
        var drEvent = $('#input-file-events').dropify();
        drEvent.on('dropify.beforeClear', function(event, element) {
            return confirm("Do you really want to delete \"" + element.file.name + "\" ?");
        });
        drEvent.on('dropify.afterClear', function(event, element) {
            alert('File deleted');
        });
        drEvent.on('dropify.errors', function(event, element) {
            console.log('Has Errors');
        });
        var drDestroy = $('#input-file-to-destroy').dropify();
        drDestroy = drDestroy.data('dropify')
        $('#toggleDropify').on('click', function(e) {
            e.preventDefault();
            if (drDestroy.isDropified()) {
                drDestroy.destroy();
            } else {
                drDestroy.init();
            }
        })
    });
    </script>
    <script>
        $("#user_profile_id").on( "change", function( event ) {
            var val1 = $.trim($("#user_profile_id").find("option:selected").text());            

            if(val1 == 'Teacher' || val1 == 'TEACHER')
            {
                $("#total_lecture_div").css("display","block");
            }
            else
            {
                $("#total_lecture_div").css("display","none");
            }
        });

        function getUsername(){
            var first_name = document.getElementById("first_name").value;
            var last_name = document.getElementById("last_name").value;
            var username = first_name.toLowerCase()+"_"+last_name.toLowerCase();
            document.getElementById("user_name").value = username;
        }
    </script>
@include('includes.footer')
