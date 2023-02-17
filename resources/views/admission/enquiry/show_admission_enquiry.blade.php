@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Enquiry</h4>
            </div>
        </div>
        <div class="card">
            <form method="POST" action="cancel_fees">
                @if ($sessionData = Session::get('data'))
                    @if ($sessionData['status_code'] == 0)
                        <div class="alert alert-danger alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @else
                        <div class="alert alert-success alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            <strong>{{ $sessionData['message'] }}</strong>
                        </div>
                    @endif
                @endif

                <div class="row">                                 
                    <div class="col-lg-3 col-sm-3 col-xs-3">
                        <a href="{{ route('admission_enquiry.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Enquiry </a>
                        <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="1) Yellow color indicates today's follow up & today's next follow up records. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 2) Pink color indicates close enquiry status records. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 3) Once student is enrolled in System it cannot be edited & deleted. ">
                          <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled="">Note</button>
                        </span>
                    </div>
                    
                    <div class="col-lg-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table id="example" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th data-toggle="tooltip" title="Id">Id</th>
                                        <th data-toggle="tooltip" title="Enquiry No">Enquiry</th>
                                        <th data-toggle="tooltip" title="Inquiry Date">Inquiry</th>
                                        <th data-toggle="tooltip" title="Follow Up Date">Follow</th>
                                        <th data-toggle="tooltip" title="Next Follow Up Date">Next</th>
                                        @if (Session::get('sub_institute_id') == '74')
                                        <th data-toggle="tooltip" title="Form No">Form</th>
                                        @endif
                                        <th data-toggle="tooltip" title="Registration Fees Receipt">Regist</th>
                                        <th data-toggle="tooltip" title="Student Name">Student</th>
                                        @if (Session::get('sub_institute_id') != '198')
                                        <th data-toggle="tooltip" title="Middle Name">Middle</th>
                                        @endif
                                        <th data-toggle="tooltip" title="Surname">Surname</th>
                                        <th data-toggle="tooltip" title="Mobile">Mobile</th>
                                        <th data-toggle="tooltip" title="Email">Email</th>
                                        <th data-toggle="tooltip" title="Date of Birth">DOB</th>
                                        <th data-toggle="tooltip" title="Age">Age</th>
                                        <th data-toggle="tooltip" title="Previous School Name">Previous</th>
                                        <th data-toggle="tooltip" title="Previous Standard">Previous</th>
                                        <th data-toggle="tooltip" title="Admission Standard">Admission</th>
                                        @if(Session::get('sub_institute_id') == '198' ||
                                            Session::get('sub_institute_id') == '201' || 
                                            Session::get('sub_institute_id') == '202' || 
                                            Session::get('sub_institute_id') == '203' || 
                                            Session::get('sub_institute_id') == '204')
                                        <th data-toggle="tooltip" title="Admission Form Charges">Admission</th>
                                        <th data-toggle="tooltip" title="Fees Circular Form No.">Fees</th>
                                        <th data-toggle="tooltip" title="Fees Amount">Fees</th>
                                        <th data-toggle="tooltip" title="Fees Remarks">Fees</th>                                           
                                        @endif
                                        <th data-toggle="tooltip" title="Remarks">Remarks</th>   
                                        <th data-toggle="tooltip" title="Enquiry Status">Enquiry</th>       
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                $j=1;
                                @endphp
                                    @foreach($data['data'] as $key => $data)
                                    @php
                                    if($data['display_enquiry_status'] == 'close')
                                    {
                                        $backgroud_color = $data['enq_color'];
                                    }
                                    else
                                    {
                                        $backgroud_color = $data['current_status_color'];                                        
                                    }
                                    @endphp
                                    <tr style="background-color:{{$backgroud_color}}">
                                        @if($data['total_student_count'] == 0 && $data['enquiry_status'] == 0)
                                            <td>
                                                <div class="d-inline">
                                                    <a href="{{ route('admission_enquiry.edit',$data['id'])}}" class="btn btn-info btn-outline">
                                                        <i class="ti-pencil-alt"></i>
                                                    </a>
                                                </div>
                                                <form action="{{ route('admission_enquiry.destroy', $data['id'])}}" method="post" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                    <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                                </form>
                                            </td>
                                        @else
                                            <td></td>
                                        @endif
                                        
                                        <td>{{$j}}</td>
                                        <td>{{$data['enquiry_no']}}</td>
                                        <td>{{date('d-m-Y', strtotime($data['created_on']))}}</td>
                                        <td>{{date('d-m-Y', strtotime($data['followup_date']))}}</td>
                                        <td>{{$data['next_follow_up_date']}}</td>
                                        @if (Session::get('sub_institute_id') == '74')
                                        <td>{{$data['form_number']}}</td>
                                        @endif
                                        <td>
                                            @if($data['receipt_id'] != '')
                                            <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:add_data({{$data['id']}});">{{$data['receipt_id']}}</button>
                                            <input type="hidden" name="fees_html_{{$data['id']}}" id="fees_html_{{$data['id']}}" value="{{$data['receipt_html']}}">
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{$data['first_name']}}</td>
                                        @if (Session::get('sub_institute_id') != '198')
                                        <td>{{$data['middle_name']}}</td>
                                        @endif
                                        <td>{{$data['last_name']}}</td>
                                        <td>{{$data['mobile']}}</td>
                                        <td>{{$data['email']}}</td>
                                        <td>{{date('d-m-Y', strtotime($data['date_of_birth']))}}</td>
                                        <td>{{$data['age']}}</td>
                                        <td>{{$data['previous_school_name']}}</td>
                                        <td>{{$data['previous_standard']}}</td>
                                        <td>{{$data['std_name']}}</td>
                                        @if (Session::get('sub_institute_id') == '198')
                                        <td>{{$data['admission_fees']}}</td>
                                        @endif
                                        @if(Session::get('sub_institute_id') == '201' || 
                                            Session::get('sub_institute_id') == '202' || 
                                            Session::get('sub_institute_id') == '203' || 
                                            Session::get('sub_institute_id') == '204')
                                        <td>{{$data['fees_circular_form_no']}}</td>                                        
                                        <td>{{$data['fees_amount']}}</td>                                        
                                        <td>{{$data['fees_remark']}}</td>                                        
                                        @endif                                      
                                        <td>{{$data['remarks']}}</td>
                                        <td>{{$data['display_enquiry_status']}}</td>
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
            </form>
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
                            <div class="row">
                               
                                   <div class="panel-body">
                                        <div class="col-lg-12 col-sm-12 col-xs-12">
                                            <div id="reprint_receipt_html">
                                            </div>
                                        </div>
                                    </div>  
                                                           
                            </div>
                        </div>
                        <!--Footer-->
                        <div class="modal-footer flex-center">
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
        document.getElementById("btnPrint").onclick = function () {
            // alert('dddd');
                PrintDiv("reprint_receipt_html");
        }

        function PrintDiv(divName) {
            var divToPrint = document.getElementById(divName);
            var popupWin = window.open('', '_blank', 'width=300,height=300');
            popupWin.document.open();
            popupWin.document.write('<html>');
            popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
            popupWin.document.close();
        }

        function add_data(s)
        {
                var recepit_css = "<style>body {background: rgb(204, 204, 204); } page {background: white; display: block; margin: 0 auto; margin-bottom: 0.5cm; /* box-shadow: 0 0 0.5cm rgba(0, 0, 0, 0.5); */ } page[size='A4'] {width: 21cm; height: 29.7cm; } page[size='A4'][layout='landscape'] {width: 29.7cm; height: 21cm; } page[size='A3'] {width: 29.7cm; height: 42cm; } page[size='A3'][layout='landscape'] {width: 42cm; height: 29.7cm; } page[size='A5'] {width: 14.8cm; height: 21cm; } page[size='A5'][layout='landscape'] {width: 21cm; height: 14.8cm; } @media print {body, page {margin: 0; box-shadow: 0; } } </style> <style> table.fees-receipt {border-collapse: collapse } .fees-receipt {border: 1px solid #888; height: 510px; overflow: hidden } .particulars {border-collapse: collapse } .particulars td {border: 1px solid #888; border-collapse: collapse } .fees-receipt td {font-family: Arial, Helvetica, sans-serif !important; padding: 0 8px; font-size: 13px } .fees-receipt img.logo {width: 100px; height: 90px; margin: 0 } .double-border {border-bottom: 1px double #000; border-width: 5px } .particulars {height: 180px; overflow: hidden; display: block; vertical-align: top } .particulars td {width: 100%; height: 20px; font-size: 12px } .mg-top {top: 10px; position: relative } .mg-top label {border-radius: 3px; font-weight: 700; font-size: 14px; top: 5px; position: relative } .receipt-hd {border: 1px solid #000; padding: 5px 15px; margin-top: 15px } .sc-hd {font-size: 26px; font-weight: 700; font-family: Arial, Helvetica, sans-serif !important } .ma-hd {font-size: 18px; font-weight: 700; font-family: Arial, Helvetica, sans-serif !important } .rg-hd {font-size: 14px; font-weight: 600; font-family: Arial, Helvetica, sans-serif !important } .padding {padding-bottom: 20px !important } .logo-width {width: 165px; text-align: center } </style>"; 
            var fees_content = $('#fees_html_'+s).val();

            // alert(fees_content);
            $('#reprint_receipt_html').html(recepit_css+fees_content);
            $('#ChapterModal').modal('show');
           
        }

$(document).ready(function () {
    $('#example').DataTable();
});
</script>
<script>
    $(document).ready(function(){  
      $('[data-toggle="popover"]').popover({title: "",html: true});
      $('[data-toggle="popover"]').on('click', function (e) {
        $('[data-toggle="popover"]').not(this).popover('hide');
        });
    });
</script>    
@include('includes.footer')
