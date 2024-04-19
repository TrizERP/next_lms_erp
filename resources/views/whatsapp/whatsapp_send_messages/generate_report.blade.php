{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')

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
                                        <input type="text" id='date1'  name="from_date" class="form-control mydatepicker">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>To Date</label>
                                        <input type="text" id='date2' name="to_date" class="form-control mydatepicker">
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
                                                <th>Id</th>
                                                <th>Standard</th>
                                                <th>Division</th>
                                                <th>Student Id</th>
                                                <th>Mobile Number</th>
                                                <th>Created By</th>
                                                <th>Date</th>
                                                <th>Message</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @php
                                                $j=1;
                                            @endphp
                                            @foreach($data['stu_data'] as $key => $data)
                                                <tr>
                                                    <td>{{$j}}</td>
                                                    <td>{{$data->standard_id}}</td>
                                                    <td>{{$data->division_id}}</td>
                                                    <td>{{$data['student_id']}}</td>
                                                    <td>{{$data['student']->mobile}}</td>
                                                    <td>{{$data->created_by_name ?? '-'}}</td>
                                                    <td>{{$data->created_at ?? '-'}}</td>
                                                    <td>{{$data->message ?? '-'}}</td>
                                                </tr>
                                                @php
                                                    $j++;
                                                @endphp
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

        @if (!isset($data['stu_data']))
            @if(isset(Session::get('erpTour')['fees_collect']) && Session::get('erpTour')['fees_collect'] == 0)
                <link rel="stylesheet" href="../../../tooltip/enjoyhint/jquery.enjoyhint.css">

                <script src="../../../tooltip/bower_components/todomvc-common/base.js"></script>
                <!-- <script src="../../../tooltip/bower_components/jquery/jquery.js"></script> -->
                <script src="../../../tooltip/bower_components/underscore/underscore.js"></script>
                <script src="../../../tooltip/bower_components/backbone/backbone.js"></script>
                <script src="../../../tooltip/bower_components/backbone.localStorage/backbone.localStorage.js"></script>
                <script src="../../../tooltip/js/models/todo.js"></script>
                <script src="../../../tooltip/js/collections/todos.js"></script>
                <script src="../../../tooltip/js/views/todo-view.js"></script>
                <script src="../../../tooltip/js/views/app-view.js"></script>
                <script src="../../../tooltip/js/routers/router.js"></script>
                <script src="../../../tooltip/js/app.js"></script>
                <script src="../../../tooltip/enjoyhint/enjoyhint.js"></script>
                <script src="../../../tooltip/enjoyhint/jquery.enjoyhint.js"></script>
                <script src="../../../tooltip/enjoyhint/kinetic.min.js"></script>
                <script>
                    localStorage.clear();
                    var enjoyhint_script_data = [
                        {
                            onBeforeStart: function(){
                                $('#grade').change(function(e){

                                    enjoyhint_instance.trigger('new_todo');

                                });
                            },
                            selector:'#grade',
                            event:'new_todo',
                            event_type:'custom',
                            description:'Select Grade Here.'
                        },
                        {
                            onBeforeStart: function(){
                                $('#standard').change(function(e){

                                    enjoyhint_instance.trigger('new_todo');

                                });
                            },
                            selector:'#standard',
                            event:'new_todo',
                            event_type:'custom',
                            description:'Select Standard Here.'
                        },
                        {
                            selector:'#division',
                            event:'change',
                            description:'Select Division Here.',
                            timeout:100
                        },
                        {
                            selector:'.btn-success',
                            event:'click',
                            description:'Please press to search students.',
                            timeout:100
                        }
                    ];
                    var enjoyhint_instance = null;
                    $(document).ready(function(){
                        enjoyhint_instance = new EnjoyHint({});
                        enjoyhint_instance.setScript(enjoyhint_script_data);
                        enjoyhint_instance.runScript();
                    });
                </script>

                <script type="text/javascript">
                    var url = "http://dev.triz.co.in/tourUpdate?module=fees_collect";
                    var xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            console.log("success");
                        }
                    };
                    xhttp.open("GET", url, true);
                    xhttp.send();
                </script>
            @endif
        @endif

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
                            title: 'Fees Monthly Report',
                            orientation: 'landscape',
                            pageSize: 'LEGAL',
                            pageSize: 'A0',
                            exportOptions: {
                                columns: ':visible'
                            },
                        },
                        {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                        {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                        {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
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

        @if(app('request')->input('implementation') == 1)
            <script type="text/javascript">
                document.body.className = document.body.className.replace("fix-header", "fix-header show-sidebar hide-sidebar");
                document.getElementById('main-header').style.display = 'none';
            </script>
    @endif
    @include('includes.footer')
@endsection
