@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Capture Student Attendance</h4>
            </div>
        </div>
        <div class="card">
            <div class="row">
                <div class="col-md-12 text-right">
                    <a class="btn btn-primary" href="{{route('student_face_attendance.create')}}">Capture Attendance</a>
                </div>
            </div>
            @if ($sessionData = Session::get('data'))
            <div class="alert alert-success alert-block">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif

            <div class="row mb-2">
                <div class="col-md-12">
                    @if(strtoupper(session()->get('user_profile_name'))=="ADMIN")
                    @php 
                        $grade_id = $standard_id = $division_id = '';
                        if(!empty($data['grade_id'])){
                            $grade_id = $data['grade_id'];
                        }
                        if(!empty($data['standard_id'])){
                            $standard_id = $data['standard_id'];
                        }
                        if(!empty($data['division_id'])){
                            $division_id = $data['division_id'];
                        }
                    @endphp
                    <form action="{{route('student_face_attendance.index')}}">
                        <div class="row">
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                            <div class="col-md-12">
                                <center>
                                    <input type="submit" name="submit" class="btn btn-success mb-2" value="Search">
                                </center>
                            </div>
                        </div>
                    </form>
                    @endif
                </div>
                <div class="col-md-12">
                    @if(!empty($data['allCapturedImage']))
                    <div class="card-body table-responsive mt-20 tz-report-table">
                        <table id="example" class="table table-striped" style="width:100% !important">

                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>{{ App\Helpers\get_string('grno','request')}}</th>
                                    <th>{{ App\Helpers\get_string('studentname','request')}}</th>
                                    <th>{{ App\Helpers\get_string('mobile','request')}}</th>
                                    <th>{{ App\Helpers\get_string('standard','request')}}</th>
                                    <th>{{ App\Helpers\get_string('division','request')}}</th>
                                    <th>Image</th>
                                    @if(strtoupper(session()->get('user_profile_name'))=="ADMIN")
                                    <th class="text-left">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['allCapturedImage'] as $key=>$value)
                                <tr>
                                    <td>{{$key+1}}</td>
                                    <td>{{$value->enrollment_no}}</td>
                                    <td>{{$value->full_name}}</td>
                                    <td>{{$value->mobile}}</td>
                                    <td>{{$value->standard_name}}</td>
                                    <td>{{$value->division_name}}</td>
                                    <td><img src="{{asset('public/student_face_image/'.$value->stu_image)}}" alt="" width="50" height="50"></td>
                                    @if(strtoupper(session()->get('user_profile_name'))=="ADMIN")
                                    <td>
                                       <form action="{{ route('student_face_attendance.destroy', $value->id)}}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger"><i class="ti-trash"></i></button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

        </div>

    </div>
</div>

@include('includes.footerJs')
<script>
    $(document).ready(function() {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [{
                    extend: 'pdfHtml5',
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {
                    extend: 'csv',
                    text: ' CSV',
                    title: 'Student Captured Images'
                },
                {
                    extend: 'excel',
                    text: ' EXCEL',
                    title: 'Fees Monthly Report'
                },
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Fees Monthly Report'
                },
                'pageLength'
            ],
        });

        $('#example thead tr').clone(true).appendTo('#example thead');
        $('#example thead tr:eq(1) th').each(function(i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function() {
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