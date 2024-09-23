{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<style>
    .imageTD img{
        width:100px !important;
        height: 100px !important;
    }
</style>
    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Whatsapp Sent Message Reports</h4>
                </div>
            </div>

            <div class="card">
                @if(!empty($data['message']))
                    @if(!empty($data['status_code']) && $data['status_code'] == 1)
                        <div class="alert alert-success alert-block">
                            @else
                                <div class="alert alert-danger alert-block">
                                    @endif
                                    <button type="button" class="close" data-dismiss="alert">×</button>
                                    <strong>{{ $data['message'] }}</strong>
                                </div>
                            @endif
                            @php
                                $grade_id = $standard_id = $division_id = '';
                                    if(isset($data['grade_id'])){
                                        $grade_id = $data['grade_id'];
                                        $standard_id = $data['standard_id'];
                                        $division_id = $data['division_id'];
                                    }
                            @endphp
                            <form action="{{ route('whatsapp_sent_generate_report_details') }}" enctype="multipart/form-data" method="post">
                                {{ method_field("POST") }}
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="row">
                                                {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>From Date</label>
                                        <input type="text" id='date1'  name="from_date" @if(isset($data['from_date'])) value="{{$data['from_date']}}" @endif class="form-control mydatepicker">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>To Date</label>
                                        <input type="text" id='date2' name="to_date" @if(isset($data['to_date'])) value="{{$data['to_date']}}" @endif class="form-control mydatepicker">
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <center>
                                            <input type="submit" name="submit" value="Search Student" class="btn btn-success triz-btn" >
                                        </center>
                                    </div>
                                </div>
                            </form>

                        </div>

                        @if(isset($data['stu_data']))
                            <div class="card">
                <span class="d-inline-block mb-2" tabindex="0" data-toggle="tooltip" title="Only those students will be displayed here whose Fees Structure is added.">
                  <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled>Note</button>
                </span>
                                <form action="{{ route('fees_collect.show_student') }}" enctype="multipart/form-data" method="post">
                                    {{ method_field("POST") }}
                                    @csrf
                                    <div class="table-responsive">
                                        <table id="example" class="table table-box table-bordered">
                                            <thead>
                                            <tr>
                                                <th>Sr No</th>
                                                <th>Gr No</th>
                                                <th>Student Name</th>
                                                <th>Mobile Number</th>
                                                <th>Created By</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Message</th>
                                                <th>Incoming Message</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            
                                            @foreach($data['stu_data'] as $key => $data)
                                                <tr>
                                                    <td>{{$key+1}}</td>
                                                    <td>{{ (isset($data['student'][0]['enrollment_no'])) ? $data['student'][0]['enrollment_no'] : '-'}}</td>
                                                    <td>{{ (isset($data['student'][0])) ? $data['student'][0]['first_name'].' '.$data['student'][0]['last_name'] : '-'}}</td>
                                                    <td>{{ (isset($data['student'][0]['mobile'])) ? $data['student'][0]['mobile'] : '-'}}</td>
                                                    <td>{{$data->created_by_name ?? '-'}}</td>
                                                    <td>{{$data->created_at ?? '-'}}</td>
                                                    <td>{{$data->message_status ?? '-'}}</td>
                                                    <td class="imageTD">{!!$data->message ?? '-'!!}</td>
                                                    <td><a href="/whatsapp-show-reply/{{$data['whatsapp_number']}}" >show reply ({{count($data['messages'])}})</a></td>
                                                </tr>
                                               
                                            @endforeach
                                            </tbody>

                                        </table>
                                    </div>
                                </form>
                            </div>
                        @endif
            </div>
        </div>

        @include('includes.footerJs')

        <script>
            $(document).ready(function () {
                var table = $('#example').DataTable({
                    select: true,
                    lengthMenu: [
                        [100, 500, 1000, -1],
                        ['100', '500', '1000', 'Show All']
                    ],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'pdfHtml5',
                            title: 'Whatsapp Report',
                            orientation: 'landscape',
                            pageSize: 'LEGAL',
                            pageSize: 'A0',
                            exportOptions: {
                                columns: ':visible'
                            },
                        },
                        {extend: 'csv', text: ' CSV', title: 'Whatsapp Report'},
                        {extend: 'excel', text: ' EXCEL', title: 'Whatsapp Report'},
                        {extend: 'print', text: ' PRINT', title: 'Whatsapp Report'},
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
                                .search( this.value )
                                .draw();
                        }
                    } );
                } );
            } );
        </script>


    @include('includes.footer')
@endsection