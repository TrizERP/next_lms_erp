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
                <h4 class="page-title">Hrms Job Title</h4>
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
            <form action="{{ route('hrms_job_title.store') }}"  method="post">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Job Title </label>
                        <input type="text" id='title' required name="title" class="form-control" value="{{$hrmsJobTitle['title']}}">
                        @error('title')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Status</label>
                        <select name="status" id="status" class="form-control">
                            @if($hrmsJobTitle['is_active'] == 1)
                                <option value="0"> Disable </option>
                                <option value="1" selected> Enable </option>
                            @else
                                <option value="0" selected> Disable </option>
                                <option value="1"> Enable </option>
                            @endif
                        </select>
                        @error('status')
                        <span style="color: red">{{$message}}</span>
                        @enderror
                    </div>

                    <div class="col-md-12 form-group">
                        <label>Description </label>
                        <input name="description"  class="form-control" value="{{$hrmsJobTitle['description']}}"/>
                    </div>

                    <input type="hidden" name="id" value="{{$hrmsJobTitle['id']}}">
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
