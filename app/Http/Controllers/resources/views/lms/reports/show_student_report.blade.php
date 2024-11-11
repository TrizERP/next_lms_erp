{{--@include('includes.lmsheadcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('lmslayout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title align-items-center justify-content-between">
            <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12 mb-4">
                <h1 class="h4 mb-3">Student Report</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{route('course_master.index')}}">LMS</a></li>
                        <li class="breadcrumb-item">Reports</li>
                        <li class="breadcrumb-item active" aria-current="page">Student Report</li>
                    </ol>
                </nav>
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
                <div class="card-body">
                    @if ($sessionData = Session::get('data'))
                    <div class="alert alert-success alert-block">
                        <button type="button" class="close" data-dismiss="alert">×</button>
                        <strong>{{ $sessionData['message'] }}</strong>
                    </div>
                    @endif
                    <form action="{{ route('lmsStudent_report.create') }}">
                        @csrf
                        <div class="row">
                            {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}


                            <div class="col-md-2 form-group mt-4">
                                <br>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @if(isset($data['student_data']))
        @php
        if(isset($data['student_data'])){
        $student_data = $data['student_data'];
        $finalData = $data;
        }
        @endphp
            <div class="card mt-2">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Sr No.</th>
                                        <th>Student Name</th>
                                        <th>Enrollment Code</th>
                                        <!-- <th>Standard</th> -->
                                        <!-- <th>Division</th> -->
                                                <!-- <th>Gender</th> -->
                                                <th>Mobile</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @foreach($student_data as $key => $data)
                                                <tr>
                                                    <td>{{$j}}</td>
                                                    <td>{{$data['first_name']}} {{$data['middle_name']}} {{$data['last_name']}}</td>
                                                    <td>{{$data['enrollment_no']}}</td>
                                                <!-- <td>{{$data['standard_name']}}</td> -->
                                                <!-- <td>{{$data['division_name']}}</td> -->
                                                <!-- <td>{{$data['gender']}}</td> -->
                                                    <td>{{$data['mobile']}}</td>
                                                    <td>
                                                        <a href="{{ route('lmsStudent_report.edit',['lmsStudent_report'=>$data['id']]) }}"
                                                           target="_blank">View</a></td>
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
        @endif
    </div>
</div>
@include('includes.lmsfooterJs')
<script>
    $(document).ready(function () {
        $('#grade').attr("required", true);
        $('#standard').attr("required", true);
        $('#subject').attr("required", true);
    });
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
                    title: 'Other Fees Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Other Fees Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Other Fees Report'},
                {extend: 'print', text: ' PRINT', title: 'Other Fees Report'},
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
