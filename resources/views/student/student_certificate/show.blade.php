{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<style type="text/css">
    #overlay {
        position: fixed; /* Sit on top of the page content */
        display: none; /* Hidden by default */
        width: 100%; /* Full width (cover the whole page) */
        height: 100%; /* Full height (cover the whole page) */
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5); /* Black background with opacity */
        z-index: 2; /* Specify a stack order in case you're using a different order for other elements */
        cursor: pointer; /* Add a pointer on hover */
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Certificate History</h4>
            </div>
        </div>
        @php
        $from_date = $to_date = '';
        if(isset($data['from_date'])){
            $from_date = $data['from_date'];
        }
        if(isset($data['to_date'])){
            $to_date = $data['to_date'];
        }
        @endphp
        <div class="card">
            @if ($sessionData = Session::get('data'))
                @if($sessionData['status_code'] == 1)
                <div class="alert alert-success alert-block">
                @else
                <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <form action="{{ route('student_certificate_report.create') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>From Date</label>
                        <input type="text" name="from_date" class="form-control mydatepicker"
                            placeholder="Please select from date." autocomplete="off" value="{{$from_date}}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>To Date</label>
                        <input type="text" name="to_date" class="form-control mydatepicker"
                            placeholder="Please select to date." autocomplete="off" value="{{$to_date}}">
                    </div>
                    <div class="col-md-4 form-group mt-4">
                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                    </div>
                </div>
            </form>
        </div>
        @if(isset($data['result_report']))
        @php
        $j = 1;
        if(isset($data['result_report'])){
        $result_report = $data['result_report'];
        }
        @endphp
        <div class="card">
            <div class="table-responsive">
               {!! App\Helpers\get_school_details("","","") !!}
                <br><center><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">From Date :{{date('d-m-Y',strtotime($data['from_date']))}} - </span><span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">To Date : {{date('d-m-Y',strtotime($data['to_date'])) }}</span></center><br>
              
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>SR NO</th>
                            <th>{{App\Helpers\get_string('grno','request')}}</th>
                            <th>{{App\Helpers\get_string('studentname','request')}}</th>
                            <th>{{App\Helpers\get_string('standard','request')}}</th>
                            <th>{{App\Helpers\get_string('division','request')}}</th>
                            <th>Certificate No.</th>
                            <th class="text-left">Certificate Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result_report as $key => $value)
                        <tr>
                            <td>{{$j++}}</td>
                            <td>{{$value->enrollment_no}}</td>
                            <td>{{$value->student_name}}</td>
                            <td>{{$value->standard}}</td>
                            <td>{{$value->division}}</td>
                            <td>
                                <button type="button" class="btn btn-info float-right" data-toggle="modal" onclick="javascript:add_data({{$value->id}},{{$value->student_id}},{{$value->certificate_number}});">{{$value->certificate_number}}</button>
                                <input type="hidden" name="certificate_html_{{$value->id}}" id="certificate_html_{{$value->id}}" value="{{$value->certificate_html}}">

                            </td>
                            <td>{{$value->REQUEST}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    </div>
</div>

<!--Modal: Add ChapterModal-->
<div id="printThis">
    <div class="modal fade right modal-scrolling" id="ChapterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
        <div class="modal-dialog modal-side modal-bottom-right modal-notify modal-info" role="document" style="min-width: 75%;">
            <!--Content-->
            <div class="modal-content">
                <!--Header-->
                <div class="modal-header">
                    <h5 class="modal-title" id="heading">Re-Print Certificate</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">x</span>
                    </button>
                </div>
                <!--Body-->
                <div class="modal-body">
                    <div class="row">
                        <div class="panel-body" style="width: 100%;">
                            <div class="col-md-12">
                                <input type="hidden" name="action" id="action" value="certificate_re_receipt">
                                <input type="hidden" name="student_id" id="student_id" value="">
                                <input type="hidden" name="receipt_id_html" id="receipt_id_html" value="">
                                <input type="hidden" name="paper_size" id="paper_size" value="">
                                <div id="reprint_certificate_html">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Footer-->
                <div class="modal-footer" style="display: block !important;">
                    <div id="overlay" style="display:none;"><center><p style="margin-top: 273px;color:red;font-weight: 700;">Please do not refresh the page, while the process is going on.</p><img src="http://dev.triz.co.in/admin_dep/images/loader.gif">
                     </center></div>
                    <center>
                        <button id="ajax_PDF" type="button" class="btn btn-primary">Print Certificate</button>
                    </center>
                </div>
            </div>
            <!--/.Content-->
        </div>
    </div>
</div>
<!--Modal: Add ChapterModal-->

    <script>
        // document.getElementById("btnPrint").onclick = function () {
        //     // alert('dddd');
        //         PrintDiv("reprint_certificate_html");
        // }

        // function PrintDiv(divName) {
        //     var divToPrint = document.getElementById(divName);
        //     var popupWin = window.open('', '_blank', 'width=300,height=300');
        //     popupWin.document.open();
        //     popupWin.document.write('<html>');
        //     popupWin.document.write('<body onload="window.print()">' + divToPrint.innerHTML + '</html>');
        //     popupWin.document.close();
        // }

        function add_data(certificate_id,student_id,receipt_no)
        {

            var certificate_content = $('#certificate_html_'+certificate_id).val();
            $('#reprint_certificate_html').html(certificate_content);
            $('#student_id').val(student_id);
            $('#receipt_id_html').val(certificate_id);
            $('#ChapterModal').modal('show');
        }

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
    $(document).ready(function() {
     var table = $('#example').DataTable( {
         select: true,
         lengthMenu: [
                        [100, 500, 1000, -1],
                        ['100', '500', '1000', 'Show All']
        ],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'pdfHtml5',
                title: 'Student Certificate Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                pageSize: 'A0',
                exportOptions: {
                     columns: ':visible'
                },
            },
            { extend: 'csv', text: ' CSV', title: 'Student Certificate Report' },
            { extend: 'excel', text: ' EXCEL', title: 'Student Certificate Report' },
            {
                extend: 'print',
                text: ' PRINT',
                title: 'Student Certificate Report',
                customize: function (win) {
                    $(win.document.body).prepend(`{!! App\Helpers\get_school_details("", "", "") !!}`);
                    $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                }
            },
            'pageLength'
        ],
        });
        $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each( function (i) {
            var title = $(this).text();
            $(this).html( '<input type="text" placeholder="Search '+title+'" />' );

            $( 'input', this ).on( 'keyup change', function () {
                if ( table.column(i).search() !== this.value ) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
@include('includes.footer')
@endsection
