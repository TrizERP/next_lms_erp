@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">


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
                <h4 class="page-title">Hrms In Out Time</h4>
            </div>
        </div>
        <div class="card">
            <!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
            @if ($message = session()->get('data'))
            @if($message['status_code']==1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
            @endif                
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
            @endif
            @if($data['button'] == 'in')
                <form action="{{ route('hrms_in_time.store') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <input type="text" id='' disabled name="" class="form-control"
                                   value="{{$data['date']}}">
                            <input type="hidden" id='indate' name="indate" class="form-control"
                                   value="{{$data['date']}}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Time</label>
                            <input type="text" id='' disabled name="" class="form-control"
                                   value="{{$data['time']}}">
                            <input type="hidden" id='intime' name="intime" class="form-control"
                                   value="{{$data['time']}}">
                        </div>

                        <input type="hidden" name="id" value="{{$data['id']}}">
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" id="Submit" value="In" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            @else
                <form action="{{ route('hrms_out_time.store') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <input type="text" id='' disabled name="" class="form-control"
                                   value="{{$data['date']}}">
                            <input type="hidden" id='outdate' name="outdate" class="form-control"
                                   value="{{$data['date']}}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Time</label>
                            <input type="text" id='' disabled name="" class="form-control"
                                   value="{{$data['time']}}">
                            <input type="hidden" id='outtime' name="outtime" class="form-control"
                                   value="{{$data['time']}}">
                        </div>

                        <input type="hidden" name="id" value="{{$data['id']}}">
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" id="Submit" {{$data['button_disable'] ? 'disabled' : ''}} value="Out" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="../../../admin_dep/js/cbpFWTabs.js"></script>
<script type="text/javascript">
    (function () {
        [].slice.call(document.querySelectorAll('.sttabs')).forEach(function (el) {
            new CBPFWTabs(el);
        });
    })();
</script>
<script src="../../../plugins/bower_components/dropify/dist/js/drsopify.min.js"></script>
@include('includes.footer')
