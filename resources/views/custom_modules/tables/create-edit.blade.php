@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<style>
    .email_error {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: #D83D5A;
        font-weight: bold;
    }
    .email_success {
        width: 80%;
        height: 35px;
        font-size: 1.1em;
        color: green;
        font-weight: bold;
    }
</style>
@php
$disabled= $tableExists =  '';
if(isset($data['tableCreated']) && $data['tableCreated'] ==1){
    $disabled = 'disabled';
    $tableExists = 'readonly';
}
@endphp
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Table Name</h4>
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
            <form action="{{ route('custom_module_table.store') }}"  method="post" class="m-4">
                @csrf
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('custom-module.tables') }}" class="btn btn-info add-new">Back </a>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Module Name </label>
                        <input type="text" id='module_name' required name="module_name" class="form-control" value="{{$data['module_name']}}">
                        @error('module_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Module Type </label>
                        <select name="module_type" class="form-control"  >
                            @if($data['module_type'] == "ENTRY")
                            <option value="MASTER">MASTER</option>
                            <option value="ENTRY" selected>ENTRY</option>
                            @else
                                <option value="MASTER" selected>MASTER</option>
                                <option value="ENTRY">ENTRY</option>
                            @endif
                        </select>
                        @error('module_type')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Display under </label>
                        <select name="display_under" class="form-control display_under" id="display_under" required>
                            <option value="">select any one</option>
                            @foreach($data['DisplayUnder'] as $key => $value)
                                @if($data['display_under'] == $value->id)
                                    <option value="{{$value->id}}" selected>{{$value->name}}</option>
                                @else
                                    <option value="{{$value->id}}">{{$value->name}}</option>
                                @endif
                            @endforeach
                            {{-- <option value="Institute" {{$data['display_under'] == 'Institute' ? "selected" : "" }}>Institute</option>
                            <option value="Student" {{$data['display_under'] == 'Student' ? "selected" : "" }}>Student</option>
                            <option value="Teacher" {{$data['display_under'] == 'Teacher' ? "selected" : "" }}>Teacher</option>
                            <option value="LMS" {{$data['display_under'] == 'LMS' ? "selected" : "" }}>LMS</option>
                            <option value="HRMS" {{$data['display_under'] == 'HRMS' ? "selected" : "" }}>HRMS</option>
                            <option value="Library" {{$data['display_under'] == 'Library' ? "selected" : "" }}>Library</option> --}}
                        </select>
                        @error('module_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="level_2">Level 2</label>
                        <select name="level_2" id="level_2" class="form-control"></select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Table Name </label>
                        <input type="text" id='table_name' required name="table_name" class="form-control" value="{{$data['table_name']}}" {{$tableExists}}>
                        @error('table_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Migration </label>
                        <input type="text" id='migration' name="migration" class="form-control" value="{{$data['migration']}}">
                        @error('migration')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Seeder</label>
                        <input type="text" id='seeder' name="seeder" class="form-control" value="{{$data['seeder']}}">
                        @error('seeder')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Model</label>
                        <input type="text" id='model' name="model" class="form-control" value="{{$data['model']}}">
                        @error('model')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>


                    <div class="col-md-4 form-group">
                        <label>Controller</label>
                        <input type="text" id='controller' name="controller" class="form-control" value="{{$data['controller']}}">
                        @error('controller')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Route</label>
                        <input type="text" id='route' name="route" class="form-control" value="{{$data['route']}}">
                        @error('route')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>View</label>
                        <input type="text" id='view' name="view" class="form-control" value="{{$data['view']}}">
                        @error('view')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Storage</label>
                        <input type="text" id='storage' name="storage" class="form-control" value="{{$data['storage']}}">
                        @error('storage')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Validation</label>
                        <input type="text" id='validation' name="validation" class="form-control" value="{{$data['validation']}}">
                        @error('validation')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>


                    <div class="col-md-4 form-group">
                        <label>Access Link</label>
                        <input type="text" id='access_link' name="access_link" class="form-control" value="{{$data['access_link']}}">
                        <span style="color: green">Example: menuName.index</span>
                        @error('access_link')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Helper Function</label>
                        <select name="helper_function" id="helper_function" class="form-control">
                            <option value="">Select Function</option>
                            @forEach($data['helperFunctions'] as $key => $value)
                            <option value="{{$value}}" @if($data['helper_function']==$value) selected @endif>{{$value}}</option>
                            @endforeach
                        </select>

                        @error('access_link')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="syear">Include Syear Wise</label><br>
                        <input type="checkbox" name="syear_wise" id="syear_wise" @if($data['syear_wise'] == 1) checked @endif value="1">
                    </div>
                    <?php
                        $student = false;
                        $staff = false;
                    ?>
                    @if(isset($data['whereColumns']))

                    @foreach($data['whereColumns'] as $where_column)
                        @if($where_column['column_name'] == 'Division')
                         <?php
                                $student = true;
                             ?>
                        @endif
                            @if($where_column['column_name'] == 'staff_mobile')
                               <?php
                                    $staff = true;
                                   ?>
                            @endif
                    @endforeach
                    @endif



                   {{-- <div class="col-md-4 form-group">
                        <label>Include Standard</label>
                        <input type="checkbox" id='standard' name="standard" {{$standard ? 'checked' : ''}} class="form-control" value="1">
                        @error('access_link')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Include Division</label>
                        <input type="checkbox" id='division' name="division" {{$division ? 'checked': ''}} class="form-control" value="1">
                        @error('access_link')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>--}}

                    <div class="col-md-2 form-group">
                        <label>Include Student</label><br>
                        <input type="checkbox" id='student' name="student" {{$student ? 'checked' : ''}}  value="1" {{$disabled}}>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Include Staff</label><br>
                        <input type="checkbox" id='staff' name="staff" {{$staff ? 'checked' : ''}}  value="1" {{$disabled}}>
                    </div>

                    <input type="hidden" name="id" value="{{$data['id']}}">
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" id="Submit" value="Save" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
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
    $(document).ready(function() {
        @if(isset($data['level_2']) && $data['level_2'] != null)
            getLevel2({{$data['display_under']}},{{$data['level_2']}});
        @endif

        $('.display_under').on('change', function() {
            var id = $(this).val();
           getLevel2(id);
        });
    })

    function getLevel2(id,salVal=''){
        $.ajax({
                url: "{{ route('menuLevel2.index') }}",
                type: "GET",
                data: {
                    id: id,
                    _token: '{{ csrf_token() }}'
                },
                success: function(data) {
                    $('#level_2').empty();
                    $('#level_2').append(`<option value=''>Select any one</option>`);
                    data.forEach(function(item) {
                        if(salVal!='' && salVal == item.id){
                            $('#level_2').append(`<option value='${item.id}' selected>${item.name}</option>`);
                        }else{
                            $('#level_2').append(`<option value='${item.id}'>${item.name}</option>`);
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching data:', error);
                }
            });
    }
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
@include('includes.footer')
