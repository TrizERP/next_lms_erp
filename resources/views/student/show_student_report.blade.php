{{--@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')--}}

@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student Report <span id="menuId" style="display:none"></span><a href="{{route('norm-clature.create')}}"><i class="mdi mdi-lead-pencil"></i></a></h4>
                </div>
            </div>
        @php
        $grade_id = $standard_id = $division_id = $order_by = '';

            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
            if(isset($data['order_by'])){
                $order_by = $data['order_by'];
            }
        @endphp
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
                            <form action="{{ route('show_student_report') }}" enctype="multipart/form-data"
                                  method="post">
                                @csrf
                                <div class="row">
                                    {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                                    <div class="col-md-3 form-group">
                                        <label>Order By</label>
                                        <select id='order_by' name="order_by" class="form-control">
                                            <option>Select Order By Field</option>
                                            <option @if($order_by == 'student_name') selected="selected"
                                                    @endif value="student_name">{{ App\Helpers\get_string('studentname','request')}}
                                            </option>
                                            <option @if($order_by == 'standard_id') selected="selected"
                                                    @endif value="standard_id">{{ App\Helpers\get_string('standard','request')}}
                                            </option>
                                            <option @if($order_by == 'enrollment_no') selected="selected"
                                                    @endif value="enrollment_no">{{ App\Helpers\get_string('grno','request')}}
                                            </option>
                                            <option @if($order_by == 'roll_no') selected="selected"
                                                    @endif value="roll_no">Roll No
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 col-sm-offset-4 text-center form-group">
                                        <input type="submit" name="submit" value="Search" class="btn btn-success">
                                        <button type="button" class="btn btn-info" data-toggle="modal"
                                                data-target="#exampleModal"><i class="mdi mdi-tune"></i></button>
                                    </div>
                                </div>
                                <!-- Modal -->
                                <div class="modal fade bd-example-modal-lg" id="exampleModal" tabindex="-1"
                                     role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Choose Field</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                        aria-label="Close">
                                                    <span aria-hidden="true">x</span>
                            </button>
                          </div>
                          <div class="modal-body">
                                <div class="slimscrollright">
                                    <div class="rpanel-title"><span><i class="ti-close right-side-toggle"></i></span> </div>
                                    <div class="row">
                                        <div class="col-md-12 form-group mb-2">
                                            <div class="checkbox checkbox-info">
                                                <input id="checkall" onclick="checkedAll();" name="checkall" type="checkbox">
                                                <label for="checkall"> Check All </label>
                                                <input type="hidden" name="page" value="bulk">
                                            </div>
                                        </div>

                                    @if(isset($data['data']))
                                            @php
                                        //$checkedArray = array('enrollment_no','first_name','middle_name','last_name','mobile');
                                        $checkedArray = array();
                                        @endphp
                                        @foreach($data['data'] as $key => $value)
                                        <div class="col-md-4 form-group mt-1">
                                            <div class="custom-control custom-checkbox">
                                                @php
                                                $checked = '';
                                                if(in_array($key,$checkedArray)){
                                                    $checked = 'checked="checked"';
                                                }
                                                if(isset($data['headers'])){
                                                    if(count($data['headers']) > 0){
                                                        $headersChecked = array_keys($data['headers']);
                                                    }
                                                    $checked = '';
                                                    if(in_array($key,$headersChecked)){
                                                        $checked = 'checked="checked"';
                                                    }
                                                }
                                                @endphp
                                                <input id="{{$key}}" {{$checked}} value="{{$key}}" class="custom-control-input" name="dynamicFields[]" type="checkbox">
                                                <label for="{{$key}}" class="custom-control-label"> {{$value}} </label>
                                            </div>
                                        </div>
                                        @endforeach
                                    @endif
                                    </div>
                                </div>
                          </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
            </div>

            @if(isset($data['student_data']))
        @php
            if(isset($data['student_data'])){
                $student_data = $data['student_data'];
            }
    $j =1;
        @endphp
                <div class="card">
                    <div class="table-responsive mt-20 tz-report-table">
                        {!! App\Helpers\get_school_details("$grade_id","$standard_id","$division_id") !!}
                        <table id="example" class="table table-striped">
                            <thead>
                            <tr>
                            <th>Sr no</th>
                                @foreach($data['headers'] as $hkey => $header)
                                <th class="text-left"> {{$header}} </th>
                            @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($student_data as $key => $value)
                                <tr>
                                <td>{{$j++}}</td>
                                    @foreach($data['headers'] as $hkey => $header)

                                        @if($hkey == 'image')
                                            <td><img height="60" width="60" src="../storage/student/{{$value->$hkey}}"/>
                                            </td>
                                        @elseif($hkey == 'admission_date' || $hkey == 'dob' || $hkey == 'date' || $hkey == 'birthdate' || $hkey == 'created_on' || $hkey == 'birthday' || $hkey == 'created_at')
                                            <td> {{$value->$hkey ? date('d-m-Y', strtotime($value->$hkey)) : '-'}} </td>
                                        @elseif($hkey == 'dise_uid')
                                            <td>{{$value->$hkey}}</td>
                                        @else
                                            <td> {{$value->$hkey}} </td>
                                        @endif

                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>
        @endif
    </div>

    @include('includes.footerJs')
</head>
<script>
    var checked = false;
function checkedAll() {
    if (checked == false) {
        checked = true
    } else {
        checked = false
    }
    for (var i = 0; i < document.getElementsByName('dynamicFields[]').length; i++) {
        document.getElementsByName('dynamicFields[]')[i].checked = checked;
    }
}
</script>

<script>
    /* function stripHtmlTags(html) {
        var tmp = document.createElement("div");
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || "";
    } */

    $(document).ready(function () {
        var table = $('#example').DataTable({
            ordering: false,
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Student Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function (doc) {
                        var headerContent = `{!! htmlspecialchars_decode(App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id")) !!}`;

                        var tmp = document.createElement("div");
                        tmp.innerHTML = headerContent;
                        var decodeHeader = tmp.textContent || tmp.innerText;
                        //var header = doc.content[0];
                        //header.text += 'Student Report' + headerContent;

                        doc.content.unshift({
                            text: decodeHeader,
                            alignment: 'center',
                        });
                    }
                },
                {extend: 'csv', text: ' CSV', title: 'Student Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Student Report'},
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Student Report',
                    customize: function (win) {
                        $(win.document.body).prepend(`{!! App\Helpers\get_school_details("$grade_id", "$standard_id", "$division_id") !!}`);
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
                    table.column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });
    });
</script>

<script>
    var menuId = localStorage.getItem('current_id');
    var spans = document.querySelectorAll('span.menuId');
    for (var i = 0; i < spans.length; i++) {
        spans[i].textContent = menuId;
    }
    var url = '{{ route("norm-clature.create") }}?menu_id=' + menuId;
    var links = document.querySelectorAll('a[href="{{ route("norm-clature.create") }}"]');
    for (var j = 0; j < links.length; j++) {
        links[j].href = url;
    }
</script>

@include('includes.footer')
@endsection
