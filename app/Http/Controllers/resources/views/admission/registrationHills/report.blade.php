@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Admission Confirmation Update Report</h4>
            </div>
        </div>

        <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <form action="{{ route('registration_v1_report.index') }}">
                   @csrf
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Enquiry No</label>
                            <input type="text" class="form-control" name="enq_no" @if(isset($data['enq_no'])) value="{{$data['enq_no']}}" @endif>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Mobile</label>
                            <input type="text" class="form-control" name="mobile" @if(isset($data['mobile'])) value="{{$data['mobile']}}" @endif>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>H.N.</label>
                            <select name="hn" class="hn form-control">
                                <option value="">select</option>
                                @foreach($data['hnArr'] as $key => $hnValue) 
                                    <option value="{{$hnValue}}" @if(isset($data['hn']) && $data['hn']==$hnValue) selected @endif>{{$hnValue}}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-4 form-group">
                            <label>P.INT</label>
                            <select name="pint" class="pint form-control">
                                <option value="">select</option>
                                @foreach($data['pIntArr'] as $key => $pintValue)
                                    <option value="{{$pintValue}}" @if(isset($data['pint']) && $data['pint']==$pintValue) selected @endif>{{$pintValue}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>P.INT Date</label>
                            @php 
                            $pintDate='';
                            if(isset($data['pintdate'])) {
                                $pintDate= date('d-m-Y',strtotime($data['pintdate']));
                            }
                            @endphp
                            <input type="text" class="form-control mydatepicker" name="pintdate"  id="pintdate"  value="{{$pintDate}}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>CONF.</label>
                            <select name="conf" class="conf form-control">
                                <option value="">select</option>
                                @foreach($data['confArr'] as $key => $cvalue)
                                <option value="{{$cvalue}}" @if(isset($data['conf']) && $data['conf']==$cvalue) selected @endif>{{$cvalue}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            @php 
                            $conDate='';
                            if(isset($data['confidate'])) {
                                $conDate= date('d-m-Y',strtotime($data['confidate']));
                            }
                            @endphp
                            <label>CONF. Date</label>
                            <input type="text" class="form-control mydatepicker" name="confidate" id="confidate" value="{{$conDate}}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Paid Fees</label>
                            <select name="fees_paid" class="paid form-control">
                                <option value="">select</option>
                                @foreach($data['yesNo'] as $key => $yesNoValue)
                                    <option value="{{$yesNoValue}}" @if(isset($data['paid_fees']) && $data['paid_fees']==$yesNoValue) selected @endif>{{$yesNoValue}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Transport Fees</label>
                            <select name="transport_fees" class="transport form-control">
                                <option value="">select</option>
                                @foreach($data['yesNo'] as $key => $yesNoValue)
                                    <option value="{{$yesNoValue}}" @if(isset($data['transport_fees']) && $data['transport_fees']==$yesNoValue) selected @endif>{{$yesNoValue}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 form-group">
                            <center>
                                <input type="submit" name="search" value="search" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
        <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Enq.No</th>
                            <th>Name</th>
                            <th>DOB</th>
                            <th>MOB</th>
                            <th>Email</th>
                            <th>Class No</th>
                            <th>SIB</th>
                            <th>H.N.(1,2,3)</th>
                            <th>H.N.Remakrs</th>
                            <th>Activity</th>
                            <th>P.INT.</th>
                            <th>P.INT. Date</th>
                            <th>P.INT. Time</th>
                            <th>P.Attandance</th>
                            <th>P.Remarks</th>
                            <th>CONF.</th>
                            <th>CONF. Date</th>
                            <th>PAID</th>
                            <th class="text-center">Transport Fees</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($data['data'] as $key => $value)
                    <tr>
                        <td>{{$value['enquiry_no']}}</td>
                        <td>{{$value['full_name']}}</td>
                        <td>{{$value['date_of_birth']}}</td>
                        <td>
                            {{$value['mobile']}}
                            <input type="hidden" name="students[{{$value['id']}}][mobile]" class="mobile form-control" disabled="true" value="{{$value['mobile']}}" autocomplete="off">
                        </td>
                        <!--<td>{{$value['mobile2']}}</td>-->
                        <td>{{$value['email']}}
                        <input type="hidden" name="students[{{$value['id']}}][email]" class="email form-control" disabled="true" value="{{$value['email']}}" autocomplete="off">
                        </td>
                        <td>{{$value['std_name']}}</td>
                        @php 
                            $siblingsData = [];
                            $siblings_id = explode(',', $value['siblings']);
                            if (!empty($siblings_id) && isset($value['siblings']) && !empty($value['siblings'])) {
                                foreach ($siblings_id as $sk => $sv) {
                                    $studentData = App\Helpers\SearchStudent("", "", "", "", "", "", "", "", "", "", $sv);
                                    $siblingsData[] = $studentData[0] ?? '';
                                }
                            }
                        @endphp
                        <td>
                            @if(!empty($siblingsData))
                            @foreach($siblingsData as $skey=>$svalue)
                            {{$skey+1}}. {{$svalue['first_name'].' '.$svalue['middle_name'].' '.$svalue['last_name']}}<br>
                            @endforeach
                            @endif
                        </td>
                        <td>{{$value['h_n']}}</td>
                        <td>{{$value['h_n_remarks']}}</td>
                        <td>{{$value['activity']}}</td>
                        <td>{{$value['p_int']}}</td>
                        <td>@if(isset($value['p_int_date'])) {{date('d-m-Y',strtotime($value['p_int_date']))}} @endif</td>
                        <td>@if(isset($value['p_int_time'])) {{ date('h:i a',strtotime($value['p_int_time'])) }} @endif</td>
                        <td>{{$value['p_int_attandance']}}</td>
                        <td>{{$value['p_int_remark']}}</td>
                        <td>{{$value['confi']}}</td>
                        <td>@if(isset($value['confi_date'])) {{date('d-m-Y',strtotime($value['confi_date']) )}} @endif</td>
                        <td>{{$value['paid']}}</td>
                        <td>{{$value['transport_fees']}}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    var table = $('#example').DataTable({
        select: true,
        lengthMenu: [
            [-1,100, 500, 1000],
            ['Show All','100', '500', '1000']
        ],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'pdfHtml5',
                title: 'Admission Registration Update Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                pageSize: 'A0',
                exportOptions: {
                    columns: ':visible'
                },
            },
            {extend: 'csv', text: ' CSV', title: 'Admission Registration Update Report'},
            {extend: 'excel', text: ' EXCEL', title: 'Admission Registration Update Report'},
            {
                extend: 'print', 
                text: ' PRINT', 
                title: 'Admission Registration Report',
                customize: function (win) {
                    $(win.document.body).append(`<div style="text-align: right;margin-top:20px">Printed on: {{date('d-m-Y H:i:s')}}</div>`);
                    $('#all_values').addClass('flex-on-print');
                }
            },
            'pageLength'
        ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function (i) {
        var title = $(this).text();
        $(this).html('<input type="text" placeholder="Search ' + title + '" />');

        $('input', this).on('keyup change', function () {
            if (table.column(i).search() !== this.value) {
                table
                    .column(i)
                    .search(this.value)
                    .draw();
            }
        });
    });
});
</script>
@include('includes.footer')
@endsection