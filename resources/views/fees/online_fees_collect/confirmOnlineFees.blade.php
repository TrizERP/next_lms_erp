<!-- for ssmission -->
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Confirm Online Fees</h4>
            </div>
        </div>
        <!-- search area start  -->
         <div class="card">
         @if ($sessionData = Session::get('data'))
				@if($sessionData['status'] == "1")
					<div class="alert alert-success alert-block">
				@else
					<div class="alert alert-danger alert-block">
				@endif
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>{{ $sessionData['message'] }}</strong>
				</div>
			@endif
            <form action="{{route('confirm_online_fees.index')}}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="from_date">From Date</label>
                        <input type="text" class="form-control mydatepicker" name="from_date" autocomplete="off" @if(isset($data['from_date'])) value="{{ $data['from_date'] }}" @endif required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="to_date">To Date</label>
                        <input type="text" class="form-control mydatepicker" name="to_date" autocomplete="off" @if(isset($data['to_date'])) value="{{ $data['to_date'] }}" @endif required>
                    </div>
                    <div class="col-md-12">
                        <center>
                            <input type="submit" class="btn btn-success" value="Search" id="searchBtn" name="submit">
                        </center>
                    </div>
                </div>
            </form>
         </div>
         <!-- search area ends  -->

         <!-- studentareat start -->
          @if(isset($data['searchedData']) && !empty($data['searchedData']))
          <div class="card">
            <form action="{{route('confirm_online_fees.store')}}" method="post" onsubmit="checkSubmit();">
            @csrf
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sr No. <input id="ckbCheckAll" type="checkbox"></th>
                            <th>Enrollment No</th>
                            <th>Student Name</th>
                            <th>Mobile</th>
                            <th>Std/Div</th>
                            <th class="text-left">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['searchedData'] as $key => $value)   
                        <tr>
                            <td><input type="checkbox" name="students[{{$value['student_id']}}][]" class="ckbox1" value="{{$value['payment_id']}}"> {{$key+1}}</td>
                            <td>{{$value['enrollment_no']}}</td>
                            <td>{{ App\Helpers\sortStudentName('',$value['first_name'],$value['middle_name'],$value['last_name'])}}</td>
                            <td>{{$value['mobile']}}</td>
                            <td>{{$value['standard_name']}} / {{$value['division_name']}}</td>
                            <td>{{$value['amount']}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <center>
                        <input type="submit" value="Save" class="btn btn-success mt-2" id="saveBtn" name="submit">
                    </center>
                </div>
            </div>
            </form>
          </div>
          @endif
         <!-- studentareat end -->

    </div>
</div>
@include('includes.footerJs')
<script>
      $(function () {
        var $tblChkBox = $("input:checkbox");
        $("#ckbCheckAll").on("click", function () {
            $($tblChkBox).prop('checked', $(this).prop('checked'));
        });
    });

    function checkSubmit() {
        var checked_questions = err = 0;
        $("input[name^='students']:checked").each(function() {
        checked_questions = checked_questions + 1;
        });

        if (checked_questions == 0) {
        alert("Please Select Atleast one Student from search");
        err = 1;
        }

        if (err == 1) {
        event.preventDefault();
        }

        return err;      
    }
</script>
@include('includes.footer')
@endsection