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
            <form action="{{ route('custom_module_table.store') }}"  method="post">
                @csrf
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <a href="{{ route('custom-module.tables') }}" class="btn btn-info add-new"> Back </a>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Module Name </label>
                        <input type="text" id='module_name' required name="module_name" class="form-control" value="{{$customModuleTable['module_name']}}">
                        @error('module_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Module Type </label>
                        <select name="module_type" class="form-control"  >
                            @if($customModuleTable['module_type'] == "ENTRY")
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
                        <select name="display_under" class="form-control" >
                            <option value="Institute" {{$customModuleTable['display_under'] == 'Institute' ? "selected" : "" }}>Institute</option>
                            <option value="Student" {{$customModuleTable['display_under'] == 'Student' ? "selected" : "" }}>Student</option>
                            <option value="Teacher" {{$customModuleTable['display_under'] == 'Teacher' ? "selected" : "" }}>Teacher</option>
                            <option value="LMS" {{$customModuleTable['display_under'] == 'LMS' ? "selected" : "" }}>LMS</option>
                            <option value="HRMS" {{$customModuleTable['display_under'] == 'HRMS' ? "selected" : "" }}>HRMS</option>
                            <option value="Library" {{$customModuleTable['display_under'] == 'Library' ? "selected" : "" }}>Library</option>
                        </select>
                        @error('module_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Table Name </label>
                        <input type="text" id='table_name' required name="table_name" class="form-control" value="{{$customModuleTable['table_name']}}">
                        @error('table_name')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Migration </label>
                        <input type="text" id='migration' name="migration" class="form-control" value="{{$customModuleTable['migration']}}">
                        @error('migration')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Seeder</label>
                        <input type="text" id='seeder' name="seeder" class="form-control" value="{{$customModuleTable['seeder']}}">
                        @error('seeder')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Model</label>
                        <input type="text" id='model' name="model" class="form-control" value="{{$customModuleTable['model']}}">
                        @error('model')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>


                    <div class="col-md-4 form-group">
                        <label>Controller</label>
                        <input type="text" id='controller' name="controller" class="form-control" value="{{$customModuleTable['controller']}}">
                        @error('controller')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Route</label>
                        <input type="text" id='route' name="route" class="form-control" value="{{$customModuleTable['route']}}">
                        @error('route')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>View</label>
                        <input type="text" id='view' name="view" class="form-control" value="{{$customModuleTable['view']}}">
                        @error('view')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Storage</label>
                        <input type="text" id='storage' name="storage" class="form-control" value="{{$customModuleTable['storage']}}">
                        @error('storage')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Validation</label>
                        <input type="text" id='validation' name="validation" class="form-control" value="{{$customModuleTable['validation']}}">
                        @error('validation')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>


                    <div class="col-md-4 form-group">
                        <label>Access Link</label>
                        <input type="text" id='access_link' name="access_link" class="form-control" value="{{$customModuleTable['access_link']}}">
                        @error('access_link')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <input type="hidden" name="id" value="{{$customModuleTable['id']}}">
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
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
@include('includes.footer')
