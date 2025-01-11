@extends('layout')
@section('container')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">LMS Curriculum</h4>
            </div>
        </div>
        <div class="col-lg-3 col-sm-3 col-xs-3 m-30">
            <a href="{{ route('lms_curriculum.create') }}" class="btn btn-info add-new">
                <i class="fa fa-plus"></i> Add New
            </a>
        </div>
        <div class="card">
            <div class="card-body">
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
            </div>
                <div class="table-responsive">
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <th>Sr No.</th>
                            <th>Standard</th>
                            <th>Subject</th>
                            <th>Board</th>
                            <th>Curriculum Name</th>
                            <th>Progress</th>
                            <th>Curriculum Alignment</th>
                            <th>Holistic Curriculum</th>
                            <!-- <th>Subject Curricula</th> -->
                            <th>Model Integration</th>
                            <th>Objective</th>
                            <th>Chapter</th>
                            <th>Outcome</th>
                            <th>Assessment Tool</th>
                            <th class="text-left">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($data['allData'] as $key=>$value)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$value->standard_name}}</td>
                                <td>{{$value->subject_name}}</td>
                                <td>{{$data['boards'][$value->board_id]}}</td>
                                <td>{{$value->curriculum_name}}</td>
                                <td>
                                    @php 
                                        $totalLesson =$value->total_lesson;
                                        $comleted = $value->completed_status;
                                        $progress = $minusProgress = 0;
                                        $color = 'e0e0e0';
                                        if($totalLesson>0){
                                            $progress= ($comleted*100) / $totalLesson;
                                            $minusProgress = (100-$progress);
                                            $color = '60e6a8';
                                        }
                                    @endphp 
                                   
                                    <svg viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="45" fill="transparent" stroke="#e0e0e0" stroke-width="10px"/>
                                    <circle cx="50" cy="50" r="45" fill="transparent" stroke="#{{$color}}" stroke-width="10px" pathLength="100" stroke-dasharray="{{$progress}} {{$minusProgress}}" stroke-dashoffset="-75"/>
                                    <text x="50%" y="50%" text-anchor="middle" alignment-baseline="middle">{{$progress}}%</text>
                                    </svg>
                                </td>   
                                <td>{{substr($value->curriculum_alignment,0,100)}}.....</td>
                                <td>{{substr($value->holistic_curriculum,0,100)}}.....</td>
                                {{-- <td>
                                    @foreach($value->subject_curricula_name as $k => $v)
                                        {{$k+1}}) {{$v->display_name}}<br>
                                    @endforeach
                                </td>--}}
                                <td>
                                    @php 
                                        $model_integration=[];
                                        if(isset($value->model_integration)){
                                            $model_integration = explode(',',$value->model_integration);
                                        }
                                    @endphp
                                    @foreach($model_integration as $k => $v)
                                    {{isset($data['model_integration'][$v]) ? ($k+1).') '.$data['model_integration'][$v] : '-'}}<br>    
                                    @endforeach
                                </td>
                                <td>{{substr($value->objective,0,100)}}.....</td>
                                <td>{{substr($value->chapter,0,100)}}.....</td>
                                <td>{{substr($value->outcome,0,100)}}.....</td>
                                <td>{{substr($value->assessment_tool,0,100)}}.....</td>
                                <td>
                                    <div class="d-inline">
                                        <a class="btn btn-secondary btn-outline" data-toggle="modal" data-target="#exampleModal_{{$value->id}}">
                                             <span class="mdi mdi-eye-outline"></span>
                                        </a>
                                    </div>
                                    <div class="d-inline">
                                        <a href="{{ route('lms_curriculum.edit',$value->id)}}" class="btn btn-info btn-outline">
                                            <i class="ti-pencil-alt"></i>
                                        </a>
                                    </div>
                                    <form action="{{ route('lms_curriculum.destroy', $value->id)}}" method="post" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirmDelete();" class="btn btn-outline-danger">
                                            <i class="ti-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
    @include('lms.lms_curriculum.model')
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
                        title: 'LMS Curriculum Report',
                        orientation: 'landscape',
                        pageSize: 'LEGAL',
                        pageSize: 'A0',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                    {extend: 'csv', text: ' CSV', title: 'LMS Curriculum Report'},
                    {extend: 'excel', text: ' EXCEL', title: 'LMS Curriculum Report'},
                    {
                        extend: 'print',
                        text: ' PRINT',
                        title: 'LMS Curriculum Report',
                    },
                    'pageLength'
                ],
            });
            //table.buttons().container().appendTo('#example_wrapper .col-md-6:eq(0)');

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