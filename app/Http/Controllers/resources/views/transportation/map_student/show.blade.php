@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Student Mapping</h4>            
            </div>                    
        </div>
        <div class="card">
            <div class="row">
                @if(!empty($data['message']))
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $data['message'] }}</strong>
                    </div>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('map_student.create') }}" enctype="multipart/form-data" method="post">
                        {{ method_field("GET") }}
                        {{csrf_field()}}

                        <div class="col-md-12 form-group">
                            <div class="row">
                                {{ App\Helpers\SearchChain('4','single','grade,std,div') }}                            
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <div class="row">
                        <div class="col-md-4 form-group">
                            <label>{{App\Helpers\get_string('StudentName','request')}}</label>
                            <input type="text" id='name' name="name" value="@if(isset($data['name'])){{ $data['name'] }} @endif" class="form-control" placeholder="Enter {{App\Helpers\get_string('StudentName','request')}}">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>{{App\Helpers\get_string('grno','request')}}</label>
                            <input type="text" id='grno' name="grno" value="@if(isset($data['grno'])){{ $data['grno'] }}@endif" class="form-control" placeholder="Enter {{App\Helpers\get_string('grno','request')}}">
                        </div>

                        <div class="col-md-4 form-group">
                        <label>Area </label>                                        
                        <select name="area" id="from_stop" class="from_stop form-control">
                            <option value="">--Select--</option>
                            @php
                            if(isset($data['area'])){
                            foreach ($data['area'] as $id => $arr) {
                                $selected = "";
                                if ($id == $data['sel_area'])
                                    $selected = "selected=selected";
                                echo "<option $selected value='$id'>$arr</option>";
                            }
                            }
                            @endphp
                        </select>
                        </div>
                        </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                            </center>
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

    // $("#grade").attr("required", "true");
});

</script>
@include('includes.footer')
