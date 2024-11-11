{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Request</h4>
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
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @else
                <div class="alert alert-danger alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $sessionData['message'] }}</strong>
                </div>
                @endif
            @endif
            <form action="{{ route('student_request.create') }}" enctype="multipart/form-data">                
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

        @if(isset($data['student_data']))
        @php
        $j = 1;
            if(isset($data['student_data'])){
                $student_data = $data['student_data'];
            }
            if(isset($data['request_type_data']))
            {
                $request_type_data = $data['request_type_data'];
            }
        @endphp
        <div class="card">
            <form method="POST" action="{{route('student_request.store')}}">
                @csrf
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                            <tr>
                                <th><input id="checkall" onchange="checkAll(this,'student_request');" type="checkbox"></th>
                                <th>{{App\Helpers\get_string('grno','request')}}</th>
                                <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                <th>Request Type</th>
                                <th>Document</th>
                                <th>Reason</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($student_data as $key => $value)
                                <tr>
                                    <td> <input type="checkbox" class="student_request" value="{{$value['id']}}" name="student_request[]"> </td>
                                    <td> {{$value['enrollment_no']}} </td>
                                    <td> {{$value['first_name']." ".$value['last_name']}} </td>
                                    <td> 
                                        <select class="form-control" name="CHANGE_REQUEST_IDS[{{$value['id']}}]">
                                            <option value="">Select Request Type</option>
                                                @foreach($request_type_data as $rKey => $rValue)
                                                    <option value="{{$rValue['ID']}}">{{$rValue['REQUEST_TITLE']}}</option>
                                                @endforeach
                                        </select>
                                    </td>
                                    <td> <input type="text" class="form-control" name="PROOF_OF_DOCUMENTS[{{$value['id']}}]"> </td>
                                    <td> <input type="text" class="form-control" name="REASONS[{{$value['id']}}]"> </td>
                                    <td> <input type="text" class="form-control" name="DESCRIPTIONS[{{$value['id']}}]"> </td>
                                    <input type="hidden" class="form-control" value="{{$value['standard_id']}}" name="STANDARD_IDS[{{$value['id']}}]"> 
                                    <input type="hidden" class="form-control" value="{{$value['section_id']}}" name="SECTION_IDS[{{$value['id']}}]"> 
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="row">                        
                        <div class="col-md-12 form-group">
                            <center>
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
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
@endsection