@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Add Student Request</h4> </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
        @endphp
        <div class="row">
            <div class="white-box">
            <div class="panel-body">
				<!-- @TODO: Create a saperate tmplate for messages and include in all tempate -->
                    @if ($sessionData = Session::get('data'))
                    @if($sessionData['status_code'] == 1)
                    <div class="alert alert-success alert-block">
                    @else
                    <div class="alert alert-danger alert-block">
                    @endif
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('student_request.create') }}" enctype="multipart/form-data">
                        
                            @csrf
                        
                        {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        
                        <div class="col-md-12 form-group">
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </div>

                    </form>
                </div>
                <!-- <div class="col-lg-12 col-sm-12 col-xs-12"> -->
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered display">
                            <thead>
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this,'student_request');" type="checkbox"></th>
                                    <th>{{App\Helpers\get_string('grno,'request')}}</th>
                                    <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                    
                                </tr>
                            </thead>
                        </table>
                    </div>
                <!-- </div> -->
            </div>
        </div>
    </div>
</div>
<script>
    function checkAll(ele,name) {
         var checkboxes = document.getElementsByClassName(name);
         if (ele.checked) {
             for (var i = 0; i < checkboxes.length; i++) {
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = true;
                 }
             }
         } else {
             for (var i = 0; i < checkboxes.length; i++) {
                 console.log(i)
                 if (checkboxes[i].type == 'checkbox') {
                     checkboxes[i].checked = false;
                 }
             }
         }
    }
</script>
@include('includes.footerJs')
@include('includes.footer')
