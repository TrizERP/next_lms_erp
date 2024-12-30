{{--
@include('includes.headcss')

@include('includes.header')
@include('includes.sideNavigation')
--}}
@extends('layout')
@section('container')
<style>
    .filter-button
    {
        margin: 0;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Bulk Student Update</h4>
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
                <div class="panel-body">
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
                    <form action="{{ route('show_bulk_student') }}" enctype="multipart/form-data" method="post">
                    @csrf
                    <div class="row">
                            {{ App\Helpers\SearchChain('3','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        <div class="col-md-3 form-group">
                            <label>Order By</label>
                            <select id='order_by' name="order_by" class="form-control">
                                <option>Select Order By Field</option>
                                <option @if($order_by == 'student_name') selected="selected" @endif value="student_name">{{App\Helpers\get_string('studentname','request')}}</option>
                                <option @if($order_by == 'standard_id') selected="selected" @endif value="standard_id">{{App\Helpers\get_string('standard','request')}}</option>
                                <option @if($order_by == 'enrollment_no') selected="selected" @endif value="enrollment_no">{{App\Helpers\get_string('grno','request')}}</option>
                                <option @if($order_by == 'roll_no') selected="selected" @endif value="roll_no">Roll No</option>
                                <option @if($order_by == 'last_name') selected="selected" @endif value="last_name">Last Name</option>
                            </select>
						</div>
                        <div class="col-md-3 col-sm-offset-4 text-center form-group">
                            <input type="submit" name="submit" value="Search" class="btn btn-success triz-btn" >
                            <div class="btn btn-outline-primary btn-sm ml-2 py-2 px-3 cursor-pointer" data-toggle="modal" data-target="#modalCenter"><span class="mdi mdi-tune"></span></div>
                        </div>
                    </div>
                        <!-- Modal -->
                        <div class="modal fade bd-example-modal-lg" id="modalCenter" tabindex="-1" role="dialog" aria-labelledby="modalCenterTitle" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalLongTitle">Choose Checkbox</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
                                                @php
                                                $i = 1;
                                                @endphp
                                                @if(isset($data['data']))
                                                @foreach($data['data'] as $key => $value)
                                                <div class="col-md-4 form-group mt-1">
                                                    <div class="custom-control custom-checkbox">
                                                        @php
                                                        $checked = '';
                                                        if(isset($data['headers'])){
                                                            if(count($data['headers']) > 0){
                                                                $headersChecked = array_keys($data['headers']);
                                                            }
                                                            if(in_array($key,$headersChecked)){
                                                                $checked = 'checked="checked"';
                                                            }
                                                        }
                                                        $id = $key;
                                                        if($key=="division"){
                                                            $id = "division_id";
                                                        }
                                                        if($key=="standard"){
                                                            $id = "standard_id";
                                                        }
                                                        @endphp
                                                        <input id="{{$id}}" {{$checked}} value="{{$key}}" class="custom-control-input" name="dynamicFields[]" type="checkbox">
                                                        <label class="custom-control-label mb-0 pt-1" for="{{$id}}">{{$value['name']}}</label>
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
        @endphp
        <div class="white-box card">
            <h5 class="box-title">Student Fieldwise Report </h3>
            <form method="POST" action="{{route('bulk_update')}}" class="" enctype="multipart/form-data">
            @csrf
                <div class="table-responsive">
                    <table class="table table-striped table-bordered display">
                        <thead>
                            <tr>
                                @foreach($data['headers'] as $hkey => $header)
                                    <th data-toggle="tooltip" title="{{$header['name']}}"> {{$header['name']}} </th>
                                @endforeach
                                <th data-toggle="tooltip" title="Updated On" class="text-left"> Updated On </th>
                            </tr>
                        </thead>
                        <tbody>

                            @foreach($student_data as $key => $value)
                                <tr>
                                    @foreach($data['headers'] as $hkey => $header)
                                        @if($hkey == "student_name")
                                        <td>{{ App\Helpers\sortStudentName($value->$hkey)}}</td>
                                        @else
                                            @if($header['type'] == "textbox")
                                                @if($hkey == "mobile" || $hkey == "mother_mobile")
                                                <td><input type="text" pattern="[1-9]{1}[0-9]{9}" name="values[{{$value->id}}][{{$hkey}}]" value="{{$value->$hkey}}" class="form-control"></td>
                                                @elseif($hkey == "email")
                                                <td>{{$value->$hkey}}</td>
                                                @else
                                                <td><input type="text" name="values[{{$value->id}}][{{$hkey}}]" value="{{$value->$hkey}}"  class="form-control"></td>
                                                @endif
                                            @elseif($header['type'] == "dropdown")
                                            <td>
                                                <!-- @php
                                                    $disable = "";
                                                    if(session()->get('sub_institute_id') == 257)
                                                    {
                                                        $disable = "";
                                                    }
                                                    elseif($hkey == "student_quota" && $value->total_amount > 0)
                                                    {
                                                        $disable = "disabled";
                                                    }
                                                @endphp -->
                                                <select class="form-control" name="values[{{$value->id}}][{{$hkey}}]" value="{{$value->$hkey}}">
                                                    <option value=""> select {{$hkey}} </option>
                                                    @if(isset($data['fieldsData'][$hkey]))
                                                        @foreach($data['fieldsData'][$hkey] as $dkey => $dvalue)
                                                            <option value="{{$dkey}}" @if($dkey == $value->$hkey)  selected="selected" @endif>{{$dvalue}}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </td>
                                            @elseif($header['type'] == "checkbox")
                                            <td>
                                                @if(isset($data['fieldsData'][$hkey]))
                                                    @foreach($data['fieldsData'][$hkey] as $dkey => $dvalue)
                                                        <input type="checkbox" name="values[{{$value->id}}][{{$hkey}}]" @if($dkey == $value->$hkey)  selected="selected" @endif>
                                                    @endforeach
                                                @endif
                                            </td>
                                            @elseif($header['type'] == "date")
                                                <td><input type="text" class="mydatepicker" name="values[{{$value->id}}][{{$hkey}}]" value="{{$value->$hkey}}"></td>
                                            @elseif($header['type'] == "file")
                                                <td><input type="file" name="values[{{$value->id}}][{{$hkey}}]">
                                                    @if($value->$hkey != '')
                                                    <image height="50px" width="50px" src="/storage/student/{{$value->$hkey}}">
                                                    @endif
                                                </td>
                                            @endif
                                        @endif
                                    @endforeach
                                    <td>@if ($value['updated_on'])
                                            {{ date('d-m-Y H:i:s', strtotime($value['updated_on'])) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


                <div class="col-md-12 form-group mt-4">
                    <center>
                        <input type="submit" name="submit" value="Update" class="btn btn-success" onlclick="return validate_data();">
                    </center>
                </div>
            </form>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script>
    var checked = false;
function checkedAll()
{
    if (checked == false) {
        checked = true
    } else {
        checked = false
    }
    for (var i = 0; i < document.getElementsByName('dynamicFields[]').length; i++)
    {
        document.getElementsByName('dynamicFields[]')[i].checked = checked;
    }
}
</script>
    <script>
    $('#grade').attr('required',true);
    $('#standard').attr('required',true);

   $(document).ready(function() {
     var table = $('#example').DataTable( {
         select: true,
         lengthMenu: [
                        [100, 500, 1000, -1],
                        ['100', '500', '1000', 'Show All']
        ]
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
