@include('includes.headcss')
<link href="../plugins/bower_components/switchery/dist/switchery.min.css" rel="stylesheet" />
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">ERP Status</h4>
            </div>
        </div>
        <div class="card">
            <div id="errorbox" style="display:none;">
                <div class="alert alert-danger alert-block">
                    <strong>Password and confirm password does not match.</strong>
                </div>
            </div>
            <form action="{{ route('erp_status.store') }}" enctype="multipart/form-data" method="post">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Total Student</label>
                            <input type="text" class="form-control counter text-blue" value="{{$data['total_student']}}" readonly="readonly">
                            <!-- <span class="counter text-blue">{{$data['total_student']}}</span> -->
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Total Staff</label>
                            <input type="text" class="form-control counter text-blue" value="{{$data['total_staff']}}" readonly="readonly">
                             <!-- <span class="counter text-blue">{{$data['total_staff']}}</span> -->
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Total Standard</label>
                        <input type="text" class="form-control counter text-blue" value="{{$data['total_standard']}}" readonly="readonly">
                            <!-- <span class="counter text-blue">{{$data['total_standard']}}</span> -->
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Percentage</label>
                        <input type="text" id='percentage' name='percentage' class="form-control"
                        @if(isset($data['percentage'])) value="{{$data['percentage']}}" @endif>
                    </div>
                    <div class="col-md-3 form-group">
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success">
                        </center>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-md-12 form-group">
                    <table id="example" class="table table-striped table-bordered">
                        <tr>
                            <th width="10%">Sr. No.</th>
                            <th>Module Name</th>
                            <th>Data</th>
                            <th>Status</th>
                        </tr>
                        @if(isset($data['erp_status']))
                        @php $i=1; @endphp
                        @foreach($data['erp_status'] as $key =>$val)
                            <tr>
                                <td>{{$i++}}</td>
                                <td>{{$key}}</td>
                                <td>{{$val['DATA']}}</td>
                                <td>
                                    @if($val['STATUS'] == "yes") <font color=green>&#10004;</font>
                                    @else <font color=red>&#x2717;</font>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

@include('includes.footerJs')
@include('includes.footer')
