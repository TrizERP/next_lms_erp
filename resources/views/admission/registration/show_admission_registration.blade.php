{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Confirmation</h4> </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-3 col-sm-3 col-xs-3">
                    <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Once student is enrolled in System it cannot be edited & deleted. ">
                      <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled="">Note</button>
                    </span>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Id</th>
                                    <th>Enquiry No</th>
                                    <th>Inquiry Date</th>
                                    <th>Follow Up Date</th>
                                    <th>First Name</th>
                                    @if (Session::get('sub_institute_id') != '198')
                                    <th>Middle Name</th>
                                    @endif
                                    <th>Last Name</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Date of Birth</th>
                                    <th>Age</th>
                                    <th>Previous School Name</th>
                                    <th>Previous Standard</th>
                                    <th>Admission Standard</th>
                                    <th>Enquiry Remarks</th>
                                    @if(session()->get('sub_institute_id')==254)
                                    <th class="text-left">Transport Fees</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @php
                            $j=1;
                            @endphp
                                @foreach($data['data'] as $key => $data)
                                <tr>
                                    @if($data['total_student_count'] == 0)
                                        <td>
                                            <div class="d-inline">
                                                <a href="{{ route('admission_confirmation.edit',$data['id'])}}" class="btn btn-outline-success">
                                                   <i class="mdi mdi-grease-pencil"></i>
                                               </a>
                                            </div>
                                        </td>
                                    @else
                                        <td></td>
                                    @endif
                                    <td>{{$j}}</td>
                                    <td>{{$data['enquiry_no']}}</td>
                                    <td>{{$data['created_on']}}</td>
                                    <td>{{$data['followup_date']}}</td>
                                    <td>{{$data['first_name']}}</td>
                                    @if (Session::get('sub_institute_id') != '198')
                                    <td>{{$data['middle_name']}}</td>
                                    @endif
                                    <td>{{$data['last_name']}}</td>
                                    <td>{{$data['mobile']}}</td>
                                    <td>{{$data['email']}}</td>
                                    <td>{{$data['date_of_birth']}}</td>
                                    <td>{{$data['age']}}</td>
                                    <td>{{$data['previous_school_name']}}</td>
                                    <td>{{$data['previous_standard']}}</td>
                                    <td>{{$data['std_name']}}</td>
                                    <td>{{$data['enquiry_remark']}}</td>
                                    @if(session()->get('sub_institute_id')==254)
                                    <td>{{$data['transport_fees']}}</td>
                                    @endif
                                </tr>
                                @php
                            $j++;
                            @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script src="{{ asset("/plugins/bower_components/datatables/datatables.min.js") }}"></script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
@endsection
