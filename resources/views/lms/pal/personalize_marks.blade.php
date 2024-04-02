<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="{{ asset("/admin_dep/css/bootstrap.css") }}" rel="stylesheet">
    <link href="{{ asset("/admin_dep/css/bootstrap-select.css") }}" rel="stylesheet">
    <link href="{{ asset("/admin_dep/css/fontawesome.css") }}" rel="stylesheet">
    <link href="{{ asset("/admin_dep/css/materialdesignicons.min.css") }}" rel="stylesheet">
    
    <link href="{{ asset("/admin_dep/css/style.css") }}" rel="stylesheet">
    <title>Result Personalize Marks</title>
</head>
<style>
    #loading-overlay{
        display:none !important;
    }
</style>
<body>
<div id="page-wrapper" style="padding:70px !important">
	<div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Result Personalize Marks</h4>
            </div>
        </div>

		<!-- form  -->
		<div class="card">
            <div class="panel-body" style="padding:10px">
            @if ($sessionData = Session::get('data'))
            @if (isset($sessionData['status_code']))
                <div class="row m-2">
                    <div class="alert alert-{{ $sessionData['status_code'] == 1 ? 'success' : 'danger' }} alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{!! $sessionData['message'] !!}</strong>
                    </div>
                </div>
            @endif @endif
                <form action="" method="post">
                @csrf 
                <input type="hidden" name="sub_institute_id" value="{{$data['sub_institute_id']}}">
                <input type="hidden" name="syear" value="{{$data['syear']}}">              

                <div class="row cloneThis" id="cloneThis" data-divid='0'>
                    <!-- standard -->
                    <div class="col-md-4 form-group">
                        <label for="">Standard</label>
                        <select name="std_div[]" id="std_div" class="form-control" required>
                        <option> -- Select Standard Division --</option>
                            @foreach($data['getStdDiv'] as $key=>$value)
                                <option data-gradeid="{{$value->grade_id}}" data-stdid="{{$value->std_id}}" data-divid="{{$value->div_id}}">{{$value->standard_name.'-'.$value->division_name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- student select -->                   
                    <div class="col-md-4 form-group">
                        <label for="">Select Student</label>
                        <input type="text"  class="form-control" name="student_name[]" id="student_name" placeholder="Enter Student Name" required>
                    </div>
                     <!-- enrollment -->
                    <div class="col-md-4 form-group">
                        <label for="">Enrollment No</label>
                       <input type="text"  class="form-control" name="enrollment_no[]" id="enrollment_no" placeholder="Enter Enrollment No" required>
                    </div>
                      <!-- student Subject -->                   
                      <div class="col-md-4 form-group">
                        <label for="">Select Subject</label>
                        <input type="text"  class="form-control" name="subject[]" id="subject" placeholder="Enter Subject" required>
                    </div>
                      <!-- Exam -->
                      <div class="col-md-4 form-group">
                        <label for="">Exam</label>
                       <input type="text"  class="form-control" name="exam[]" id="exam" placeholder="Enter Exam Name" required>
                    </div>
                      <!-- total -->
                      <div class="col-md-4 form-group">
                        <label for="">Total Marks</label>
                       <input type="text" pattern="\d+(\.\d{1,2})?" class="form-control total" name="total[]" data-val="0" id="total" placeholder="Enter Total Marks" required autocomplete="off">
                    </div>
                    <!-- obtain -->
                    <div class="col-md-4 form-group">
                        <label for="">Obtained Marks</label>
                       <input type="text" pattern="\d+(\.\d{1,2})?" class="form-control obtain" name="obtain[]" data-val="0" id="obtain" placeholder="Enter Obtained Marks" required onkeyup="checkInput(0)" autocomplete="off">
                    </div>
                      <!-- add button -->
                      <div class="col-md-4 form-group">
                       <input type="button"  class="btn btn-primary mt-4 add_div" data-val="0" name="add_div" id="add_div" value='+' onclick="addNewDiv()">
                       <input type="button"  class="btn btn-primary mt-4 remove_div" data-val="0" name="remove_div" id="remove_div" value='-' onclick="removeNewDiv(0)">
                    </div>
                    <!-- row end  -->
                </div>
                <div class="newDivs">
                </div>
                <!-- row 2 -->
                <div class="row">
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="submit" class="btn btn-success" Value='ADD' id="submitBtn">
                        </center>
                    </div>
                </div>
                <!-- row end  --> 
                </form>
            </div>
        </div>
		<!-- form end -->
        @if(!empty(Session::get('data')))
        @php 
            $all_data =  Session::get('data');
        @endphp
        @if(!empty($all_data['StudentData']))
        <div class="card">
            <div class="panel-body" style="padding:10px">
            <table class="table table-responsive">
                <thead>
                    <tr>
                        <th>Std/Div</th>
                        <th>Student Name</th>
                        <th>Enrollment No</th>
                        <th>Subject</th>
                        <th>Exam</th>
                        <th>Total</th>
                        <th>Obtain</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($all_data['StudentData'] as $key => $value)
                        <tr>
                            <td>{{$value['standard']}}</td>
                            <td>{{$value['student_name']}}</td>
                            <td>{{$value['enrollment_no']}}</td>
                            <td>{{$value['subject']}}</td>
                            <td>{{$value['exam']}}</td>
                            <td>{{$value['total']}}</td>
                            <td>{{$value['obtain']}}</td>                            
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        @endif
        @endif        
        <!-- added data  -->
        <!-- end added data  -->
	</div>
</div>
</body>

<script src="{{ asset("/admin_dep/js/popper.min.js") }}" defer></script>
<script src="{{ asset("/admin_dep/js/custom.js") }}" ></script>
<script src="https://code.jquery.com/jquery-1.10.2.js"></script>
<script src="{{ asset("/admin_dep/js/jquery-ui.js") }}" defer></script>
<script src="{{ asset("/admin_dep/js/bootstrap-select.min.js") }}" defer></script>
<script src="{{ asset("/admin_dep/js/bootstrap.min.js") }}" defer></script>
<script>
function addNewDiv(){

     var getLastCloneThis = $(".cloneThis:last");
    var lastCloneThis = parseInt(getLastCloneThis.attr("data-divid"));

    var newIndex = (lastCloneThis + 1);

    var cloneThis = $('#cloneThis[data-divid="' + lastCloneThis + '"]').clone();
    cloneThis.attr("data-divid", newIndex);

    cloneThis.find("[name^='total']").each(function() {
             $(this).attr({
                "data-val": newIndex
            });
        });

    cloneThis.find("[name^='obtain']").each(function() {
            var currentOnClick = $(this).attr("onkeyup");
            var updatedOnClick = currentOnClick.replace(/\(\d+\)/, "(" + newIndex + ")");
            $(this).attr({
                "onkeyup": updatedOnClick,
                "data-val": newIndex
            });
        });

         cloneThis.find("[name^='remove_div']").each(function() {
            var currentOnClick = $(this).attr("onclick");
            var updatedOnClick = currentOnClick.replace(/\(\d+\)/, "(" + newIndex + ")");
            $(this).attr({
                "onclick": updatedOnClick,
                "data-val": newIndex
            });
        });

        cloneThis.find("[name^='add_div']").each(function() {
        var currentOnClick = $(this).attr("onclick");
        var updatedOnClick = currentOnClick.replace(/\(\d+\)/, "(" + newIndex + ")");
        $(this).attr({
            "onclick": updatedOnClick,
            "data-val": newIndex
        });
    });
        $('#add_div[data-val="' + lastCloneThis + '"]').prop('disabled', true);

    $('.newDivs').append(cloneThis);
}
function removeNewDiv(nameVal){
    var getCloneThis = $(".cloneThis:last");
    var lastCloneThis = parseInt(getCloneThis.attr("data-divid"));
    if(lastCloneThis!==0){
        var elementsToRemove = $('#cloneThis[data-divid="' + nameVal + '"]');
        elementsToRemove.remove();
    }
    $('.add_div:last').prop('disabled', false);        
}

function checkInput(data) {

    var obtain = parseFloat($('.obtain[data-val="' + data + '"]').val()); // Convert to float
    var total = parseFloat($('.total[data-val="' + data + '"]').val()); // Convert to float

    if(obtain > total){
        alert('Obtained can not be greater then total mark');
        $('.obtain[data-val="' + data + '"]').val('0.00');       
    }             

}

</script>

</htm>
