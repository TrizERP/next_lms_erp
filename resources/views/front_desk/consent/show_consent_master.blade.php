@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Consent Master</h4> </div>
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
                    <form action="{{ route('add_consent_master.create') }}">
                        
                        <div class="row">
                             
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}                                                    
                        
                            <div class="col-md-12 form-group">                        
                                <input type="submit" name="submit" value="Search" class="btn btn-success" >
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        
        @if(isset($data['student_data']))
        @php
            if(isset($data['student_data'])){
                $student_data = $data['student_data'];
                $finalData = $data;
            }
        @endphp    
            <div class="card">
                <form method="POST" action="{{ route('add_consent_master.store') }}">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Date</label>
                            <input type="text" id="date" name="date" class="form-control mydatepicker" required autocomplete="off">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Title</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Accountable Status</label>
                            <select id='accountable_status' name="accountable_status" class="form-control" required>
                            <option>--Select Status--</option>
                            <option value="Accountable">Accountable</option>
                            <option value="Non_Accountable">Non Accountable</option>
                            </select>
                           
                        </div>
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                    <th>Student Name</th>
                                    <th>Enrollment Code</th>
                                    <th>Standard</th>
                                    <th>Division</th>
                                    <th>Mobile</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                @foreach($student_data as $key => $data)
                                <tr>
                                    <td><input id="{{$data['id']}}" value="{{$data['id']}}" name="students[]" type="checkbox"></td>
                                    <td>{{$data['first_name']}}</td>
                                    <td>{{$data['enrollment_no']}}</td>
                                    <td>{{$data['standard_name']}}</td>
                                    <td>{{$data['division_name']}}</td>
                                    <td>{{$data['mobile']}}</td>
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
                            	
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>
                </form>
            </div>
        </div>
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
    $("#grade").attr("required", "true");
    $("#standard").attr("required", "true");
    $("#division").attr("required", "true");
</script>
@include('includes.footer')
