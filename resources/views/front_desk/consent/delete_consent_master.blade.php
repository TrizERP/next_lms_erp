@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Delete Consent Master</h4>
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
            @if ($sessionData = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
            @endif
            <form action="{{ route('delete_consent_master.create') }}">
                @csrf
                <div class="row">
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}

                    <div class="col-md-4 form-group ml-0 mr-0">
                        <label>From Date</label>
                        <input type="text" name="from_date" class="form-control mydatepicker"
                               placeholder="Please select from date." autocomplete="off" required="required"
                               value="@if(isset($data['from_date'])) {{$data['from_date']}} @endif">
                    </div>

                    <div class="col-md-4 form-group ml-0">
                        <label>To Date</label>
                        <input type="text" name="to_date" class="form-control mydatepicker"
                               placeholder="Please select to date." autocomplete="off" required="required"
                               value="@if(isset($data['to_date'])) {{$data['to_date']}} @endif">
                            </div>

                            <div class="col-md-12 form-group">
                                <br>
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                            </div>
                        </div>
                    </form>
            </div>

        @if(isset($data['student_data']))
            @php
                if(isset($data['student_data'])){
                    $student_data = $data['student_data'];
                    $finalData = $data;
                }

            @endphp
            <div class="card">
                <form method="POST" action="{{ route('delete_consent_master.store') }}">
                    @csrf
                    <div class="row">
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                    <th>GR.No.</th>
                                    <th>Student</th>
                                    <th>Academic Year</th>
                                    <th>Standard</th>
                                    <th>Consent Title</th>
                                    <th>Consent Date</th>
                                    <th>Account Status</th>
                                    <th>Amount</th>
                                    <th>Parent Status</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($student_data as $key => $data)

                                    <tr>
                                    <td><input id="{{$data->CHECKBOX}}" value="{{$data->CHECKBOX}}" name="students[]" type="checkbox"></td>
                                    <td>{{$data->enrollment_no}}</td>
                                    <td>{{$data->FULL_NAME}}</td>
                                    <td>{{$data->GRADE_ID}}</td>
                                    <td>{{$data->STANDARD}}</td>
                                    <td>{{$data->title}}</td>
                                    <td>{{$data->consent_date}}</td>
                                    <td>{{$data->account_status}}</td>
                                    <td>{{$data->amount}}</td>
                                    <td>{{$data->status}}</td>
                                    <td>{{$data->created_by}}</td>
                                </tr>
                                    @php
                                    $j++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-12 form-group">
                        <center>
                            <input type="hidden" name="division_id" @if(isset($finalData['division_id'])) value="{{$finalData['division_id']}}" @endif>

                            <input type="hidden" name="standard_id" @if(isset($finalData['standard_id'])) value="{{$finalData['standard_id']}}" @endif>
                            </br>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success">
                        </center>
                    </div>
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
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
