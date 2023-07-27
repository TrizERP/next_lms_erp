@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Certificate</h4>
            </div>
        </div>
        @php
        $grade_id = $standard_id = $division_id = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
        @endphp
        <div class="card">
        @if(!empty($data['message']))
                    @if($data['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                            @else
                                <div class="alert alert-danger alert-block">
                                    @endif
                                    <button type="button" class="close" data-dismiss="alert">×</button>
                                    <strong>{{ $data['message'] }}</strong>
                                </div>
                            @endif
            <form action="{{ route('student_certificate.show_student') }}" enctype="multipart/form-data" method="post">
            {{ method_field("POST") }}
            @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" name="submit" value="Search" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
                </div>
                    @if(isset($data['data']))
                        @php
                            if(isset($data['data'])){
                                $student_data = $data['data'];
                            }
                        @endphp

                        <div class="card">
                            <form method="POST" action="show_student_certificate">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label>Certificate Template</label>
                                        <select class="form-control" name="template" required="required">
                                            <option value="">Select Certificate Template</option>
                                            <option value="Bonafide">Bonafide Certificate</option>
                                            <option value="Character Certificate">Character Certificate</option>
                                            <option value="Transfer Certificate">Transfer Certificate</option>
                                            <option value="Fees Statement">Fees Statement</option>                                            
                                        </select>
                                    </div>
                                    <div class="col-md-4 form-group">
                        <label>Certificate Reason</label>
                        <input type="text" id="certificate_reason" name="certificate_reason" class="form-control">
                    </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                        <th>{{App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{App\Helpers\get_string('standard','request')}}</th>
                                        <th  class="text-left">{{App\Helpers\get_string('division','request')}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                $j=1;
                                @endphp
                                @if(isset($data['data']))
                                    @foreach($data['data'] as $key => $value)
                                    <tr>
                                        <td><input id="{{$value['id']}}" value="{{$value['id']}}" name="students[]" type="checkbox"></td>
                                        <td>{{$value['enrollment_no']}}</td>
                                        <td>{{$value['first_name']}} {{$value['last_name']}}</td>
                                        <td>{{$value['standard_name']}}</td>
                                        <td>{{$value['division_name']}}</td>
                                    </tr>
                                @php
                                $j++;
                                @endphp
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                                    <div class="col-md-12 form-group">
                                        <center>
                                            <input type="hidden" name="grade_id" @if(isset($data['grade_id'])) value="{{$data['grade_id']}}" @endif">
                        	<input type="hidden" name="standard_id" @if(isset($data['standard_id'])) value="{{$data['standard_id']}}" @endif
                                            ">
                                            <input type="submit" name="submit" value="Submit" class="btn btn-success" onclick="check_validation()">
                                        </center>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endif
    </div>
</div>

@include('includes.footerJs')
<script>
	function checkAll(ele) {
	     var checkboxes = document.getElementsByTagName('input');
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
    function check_validation()
{    
    var checked_questions = err = 0;

    $("input[name='students[]']:checked").each(function ()
    {             
        checked_questions = checked_questions + 1;
    });
    if(checked_questions == 0)
    {
        alert("Please Select Atleast one question in paper from search");
        err = 1;
        return false;
    }else{
        return true;
    }
}
</script>

<!-- <script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script> -->
@include('includes.footer')
