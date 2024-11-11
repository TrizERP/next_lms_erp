{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Registration</h4>
            </div>
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
                                    <th>Admission Docket No</th>
                                    <th>Registration No</th>
                                    {{--  For LancerArmy School --}}
                                    @if (Session::get('sub_institute_id') == '74')
                                    <th>Form No</th>
                                    @endif
                                    <th>Inquiry Date</th>
                                    <th>Follow Up Date</th>
                                    <th>First Name</th>
                                    {{-- For Maheshvari School --}}
                                    @if (Session::get('sub_institute_id') != '198')
                                    <th>Middle Name</th>
                                    @endif
                                    <th>Last Name</th>
                                    @if (Session::get('sub_institute_id') == '74' || Session::get('sub_institute_id') == '181')
                                    <th>Admission Form Charges</th>
                                    <th>Form Fees Receipt</th>
                                    @endif
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Date of Birth</th>
                                    <th>Age</th>
                                    <th>Previous School Name</th>
                                    <th>Previous Standard</th>
                                    <th>Admission Standard</th>
                                    <th>Enquiry Remarks</th>
                                    <th>Transport Fees</th>
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
                                                <a href="{{ route('admission_registration.edit',$data['id'])}}" class="btn btn-outline-success">
                                                    <i class="mdi mdi-grease-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    @else
                                        <td></td>
                                    @endif
                                    <td>{{$j}}</td>
                                    <td>{{$data['enquiry_no']}}</td>
                                    <td>{{$data['admission_docket_no']}}</td>
                                    <td>{{$data['registration_no']}}</td>
                                    {{--  For LancerArmy School --}}
                                    @if (Session::get('sub_institute_id') == '74')
                                    <td>{{$data['form_no']}}</td>
                                    @endif
                                    <td>{{$data['created_on']}}</td>
                                    <td>{{$data['followup_date']}}</td>
                                    <td>{{$data['first_name']}}</td>
                                    {{-- For Maheshvari School --}}
                                    @if (Session::get('sub_institute_id') != '198')
                                    <td>{{$data['middle_name']}}</td>
                                    @endif
                                    <td>{{$data['last_name']}}</td>
                                    @if (Session::get('sub_institute_id') == '74' || Session::get('sub_institute_id') == '181')
                                    <td>{{$data['admission_form_fee']}}</td>
                                    <td>
                                        @if($data['receipt_id'] != '')
                                            <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:add_data({{$data['form_id']}});">{{$data['receipt_id']}}</button>
                                            <input type="hidden" name="fees_html_{{$data['form_id']}}" id="fees_html_{{$data['form_id']}}" value="{{$data['receipt_html']}}">
                                        @else
                                            -
                                        @endif
                                    </td>
                                    @endif
                                    <td>{{$data['mobile']}}</td>
                                    <td>{{$data['email']}}</td>
                                    <td>{{$data['date_of_birth']}}</td>
                                    <td>{{$data['age']}}</td>
                                    <td>{{$data['previous_school_name']}}</td>
                                    <td>{{$data['previous_standard']}}</td>
                                    <td>{{$data['std_name']}}</td>
                                    <td>{{$data['enquiry_remark']}}</td>
                                    <td>{{$data['transport_fees']}}</td>
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

<!--Modal: Add ChapterModal-->
<div id="printThis">
    <div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="max-width: 75%;">
            <!--Content-->
            <div class="modal-content">
                <!--Header-->
                <div class="modal-header">
                    <h5 class="modal-title" id="heading">Re-Print Receipt</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
                <!--Body-->
                <div class="modal-body">
                    <div class="row" style="display: block !important;">
                       <div class="panel-body">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div id="reprint_receipt_html">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Footer-->
                <div class="modal-footer flex-center" style="display: block !important;">
                    <!-- <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button> -->
                    <center>
                        <button id="btnPrint" type="button" class="btn btn-primary">Print</button>
                    </center>
                </div>
            </div>
            <!--/.Content-->
        </div>
    </div>
</div>
<!--Modal: Add ChapterModal-->

@include('includes.footerJs')
<script>
    document.getElementById("btnPrint").onclick = function ()
    {
        PrintDiv("reprint_receipt_html");
    }

    function PrintDiv(divName)
    {
        var divToPrint = document.getElementById(divName);
        var popupWin = window.open('', '_blank', 'width=300,height=300');
        popupWin.document.open();
        popupWin.document.write('<html>');
        popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        popupWin.document.close();
    }

    function add_data(s)
    {
        var fees_content = $('#fees_html_'+s).val();
        $('#reprint_receipt_html').html(fees_content);
        $('#ChapterModal').modal('show');
    }

    $(document).ready(function () {
        $('#example').DataTable();
    });
</script>
@include('includes.footer')
@endsection
