@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css"
      rel="stylesheet">
<style>
    .toggle.btn.btn-danger {
        width: 200px !important;
    }

    .toggle.btn.btn-warning {
        width: 200px !important;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
    <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Fees Refund Report</h4> </div>
            </div>
        <div class="row bg-title">
            <div class="col-md-3 d-flex">
                <input type="checkbox" id="toggle_cancel_refund" name="toggle_cancel_refund" checked
                       data-toggle="toggle" data-on="Fees Cancel" data-off="Fees Refund" data-onstyle="warning"
                       data-offstyle="danger" onchange="show_fees_cancel_refund();">
            </div>
        </div>
        @php
            $grade_id = $standard_id = $division_id = $enrollment_no = $from_date = $to_date = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
            if(isset($data['enrollment_no']))
            {
                $enrollment_no = $data['enrollment_no'];
            }
            if(isset($data['from_date']))
            {
                $from_date = $data['from_date'];
            }
            if(isset($data['to_date']))
            {
                $to_date = $data['to_date'];
            }
        @endphp
        <div class="card"> <!--  py-0 -->
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
                        <form action="{{ route('show_fees') }}" method="post">
                            {{ method_field("POST") }}
                            @csrf
                            <div class="row">

                                {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                                <div class="col-md-4 form-group">
                                    <label>Enrollment No</label>
                                    <input type="text" id="enrollment_no" name="enrollment_no"
                                           value="{{$enrollment_no}}" class="form-control">
                                </div>

                                <div class="col-md-4 form-group">
                                    <label>From Date</label>
                                    <input type="text" id="from_date" name="from_date" value="{{$from_date}}"
                                           class="form-control mydatepicker" autocomplete="off">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>To Date</label>
                                    <input type="text" id="to_date" name="to_date" value="{{$to_date}}"
                                           class="form-control mydatepicker" autocomplete="off">
                                </div>

                                <div class="col-md-12 form-group">
                                    <center>
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                    </center>
                                </div>

                            </div>
                        </form>
                    </div>
        </div>
        @if(isset($data['fees_data']))
            @php
                if(isset($data['fees_data'])){
                    $fees_data = $data['fees_data'];
                }
            @endphp
            <div class="card">
                <form method="POST" action="cancel_fees">
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12 p-0">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>Sr.No</th>
                                        <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                        <th>{{ App\Helpers\get_string('grno','request')}}</th>
                                        <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                        <th>{{ App\Helpers\get_string('division','request')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $j=1;
                                    @endphp
                                    @if(isset($data['fees_data']))
                                        @foreach($fees_data as $key => $value)
                                            <tr>
                                                <td>{{$j}}</td>
                                                <td>
                                                    <a href="{{ route('fees_refund.edit',$value['student_id'])}}">
                                                        {{$value['student_name']}}
                                                    </a>
                                                </td>
                                                <td>{{$value['enrollment_no']}}</td>
                                                <td>{{$value['standard_name']}}</td>
                                                <td>{{$value['division_name']}}</td>
                                            </tr>
                                            @php
                                                $j++;
                                            @endphp
                                        @endforeach
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-12 form-group mt-4">
                                <center>
                                    <input type="submit" name="submit" value="Cancel" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
    @include('includes.footerJs')
    <script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>
    <script>
        function show_fees_cancel_refund() {
            if ($("#toggle_cancel_refund").prop("checked") == true) {
                var path = "{{ route('fees_cancel.index') }}";
                location.href = path;
            } else {
                var path1 = "{{ route('fees_refund.index') }}";
                location.href = path1;
            }
        }

        $(document).ready(function () {
            $('#example').DataTable({
                "order": [
                    [1, 'asc']
                ],
                "columnDefs": [{
                    "orderable": false,
                    "targets": 0
                }]
            });
        });
    </script>
    @include('includes.footer')
    <style type="text/css">
        @media screen {
            #printSection {
                display: none;
            }
        }
    </style>
