@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Student Discipline Report</h4>            
            </div>                    
        </div>
        <div class="card">            
            @if(!empty($data['message']))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
            </div>
            @endif
            @php
                $grade_id = $standard_id = $division_id = '';
                    if(isset($data['grade_id'])){
                        $grade_id = $data['grade_id'];
                        $standard_id = $data['standard_id'];
                        $division_id = $data['division_id'];
                    }
            @endphp
            <div class="row">                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('dicipline_report.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}
                        <div class="row">                            
                            
                            {{ App\Helpers\SearchChain('4','single','grade,std,div') }}
                            <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('studentname')}}<i class="mdi mdi-lead-pencil"></i></label>
                        <input type="text" id="stu_name" placeholder="{{App\Helpers\get_string('studentname')}}" name="stu_name" class="form-control" @if(isset($data['stu_name'])) value="{{$data['stu_name']}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('uniqueid')}}<i class="mdi mdi-lead-pencil"></i></label>
                        <input type="text" id="uniqueid" placeholder="{{App\Helpers\get_string('uniqueid')}}" name="uniqueid" class="form-control" @if(isset($data['uniqueid'])) value="{{$data['uniqueid']}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Mobile</label>
                        <input type="text" id="mobile" placeholder="Mobile" name="mobile" class="form-control" @if(isset($data['mobile'])) value="{{$data['mobile']}}" @endif>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{App\Helpers\get_string('grno')}}<i class="mdi mdi-lead-pencil"></i></label>
                        <input type="text" id="grno" placeholder="{{App\Helpers\get_string('grno')}}" name="grno" class="form-control" @if(isset($data['grno'])) value="{{$data['grno']}}" @endif>
                    </div>
                            <div class="col-md-4 form-group mr-0 ml-0">
                                <label>From Date</label>
                                <input type="text" name="from_date" class="form-control mydatepicker" >
                            </div>
                            <div class="col-md-4 form-group ml-0">
                                <label>To Date</label>
                                <input type="text" name="to_date" class="form-control mydatepicker" >
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="submit" name="submit" value="Search" class="btn btn-success" >
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
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable({

    });
});

</script>
@include('includes.footer')
