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

            @php 
                $sub_institute_id =Session::get('sub_institute_id');
                $oldAdmissionInstitutes = [47,48,49,62,69,72,195,201,202,203,204,233,254];
                $custom_fields = $data['custom_fields'];
            @endphp

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
                                    <th>Admission Standard</th>
                                     <th>Enquiry Remarks</th>
                                     @if(Session::get('sub_institute_id') == '76')
                                     <th>Enquiry Remark 1</th>
                                     <th>Enquiry Remark 2</th>
                                     @endif
                                     @if(isset($data['enquiry_custom_fields']))
                                     @foreach($data['enquiry_custom_fields'] as $k => $v)
                                     <th>{{$v['field_label']}}</th>
                                     @endforeach
                                     @endif
                                     @if(in_array($sub_institute_id,$oldAdmissionInstitutes))
                                         <th>Previous School Name</th>
                                         <th>Previous Standard</th>
                                         @if(session()->get('sub_institute_id')==254)
                                         <th class="text-left">Transport Fees</th>
                                         @endif
                                     @endif

                                       <!-- 2024-12-28  -->
                                       @foreach($data['custom_fields'] as $k => $v)
                                         <th data-toggle="tooltip" title="Enquiry Status">{{$v['field_label']}}</th>
                                     @endforeach
                                     <!-- 2024-12-28  -->

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
                                    <td>{{$data['std_name']}}</td>
                                     <td>{{$data['enquiry_remark']}}</td>
                                     @if(Session::get('sub_institute_id') == '76')
                                     <td>{{ isset($data['enquiry_remark']) ? $data['enquiry_remark'] : '' }}</td>
                                     <td>{{ isset($data['enquiry_remark2']) ? $data['enquiry_remark2'] : '' }}</td>
                                     @endif
                                     @if(isset($data['enquiry_custom_fields']))
                                     @foreach($data['enquiry_custom_fields'] as $k => $v)
                                     <td>
                                     @if($v['field_type'] == 'dropdown' && isset($data['data_fields'][$v['id']]))
                                         @php
                                             $val = $data[$v['field_name']] ?? '';
                                             $text = '';
                                             foreach($data['data_fields'][$v['id']] as $opt) {
                                                 if($opt['display_value'] == $val) {
                                                     $text = $opt['display_text'];
                                                     break;
                                                 }
                                             }
                                             echo $text;
                                         @endphp
                                     @elseif($v['field_type'] == 'checkbox' && isset($data['data_fields'][$v['id']]))
                                         @php
                                             $vals = explode(',', $data[$v['field_name']] ?? '');
                                             $texts = [];
                                             foreach($data['data_fields'][$v['id']] as $opt) {
                                                 if(in_array($opt['display_value'], $vals)) {
                                                     $texts[] = $opt['display_text'];
                                                 }
                                             }
                                             echo implode(', ', $texts);
                                         @endphp
                                     @else
                                         {{ isset($data[$v['field_name']]) ? $data[$v['field_name']] : '' }}
                                     @endif
                                     </td>
                                     @endforeach
                                     @endif
                                     @if(in_array($sub_institute_id,$oldAdmissionInstitutes))
                                         <td>{{$data['previous_school_name']}}</td>
                                         <td>{{$data['previous_standard']}}</td>
                                         @if(session()->get('sub_institute_id')==254)
                                         <td>{{$data['transport_fees']}}</td>
                                         @endif
                                     @endif

                                    <!-- 2024-12-28 -->
                                    @foreach($custom_fields as $k => $v)
                                        @if($v['field_name'] == 'siblings' && !empty($data['siblings']))
                                            @php 
                                                $siblingsData = [];
                                                // Explode the siblings_id from the data array
                                                $siblings_id = explode(',', $data['siblings']);
                                                if (!empty($siblings_id)) {
                                                    foreach ($siblings_id as $sk => $sv) {
                                                        $studentData = App\Helpers\SearchStudent("", "", "", "", "", "", "", "", "", "", $sv);
                                                        $siblingsData[] = $studentData[0] ?? '';
                                                    }
                                                }
                                            @endphp
                                            <td>
                                                @foreach($siblingsData as $skey=>$svalue)
                                                {{$skey+1}}. {{$svalue['first_name'].' '.$svalue['middle_name'].' '.$svalue['last_name']}}<br>
                                                @endforeach
                                            </td>
                                        @else 
                                            <td>{{ isset($data[$v['field_name']]) ? $data[$v['field_name']] : '' }}</td>
                                        @endif 
                                    @endforeach
                                    <!-- 2024-12-28  -->

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
