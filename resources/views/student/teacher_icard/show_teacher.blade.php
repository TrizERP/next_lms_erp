@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Teacher I-Card</h4>
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
                        <form action="{{ route('teacher_icard.show_teacher') }}" enctype="multipart/form-data"
                            method="post">
                            {{ method_field("POST") }}
                            @csrf

                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label>Type</label>
                                    <select name="teacher_type" id="teacher_type" class="form-control">
                                        <option value="">Select</option>
                                        @foreach ($data['teacher_type'] as $item=>$val)
                                        <option value="{{ $item }}">{{ $val }}</option>
                                        @endforeach
                                    </select>
                                    {{-- <input type="text" id="mobile" name="mobile" class="form-control"> --}}
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

            @if(isset($data['data']))
            @php
            if(isset($data['data'])){
            $student_data = $data['data'];
            }
            @endphp
                    <div class="card">
                        <form method="POST" action="show_teacher_icard">
                            @csrf
                            <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Template</label>
                                <select class="form-control" name="template" required="required">
                                    <option value="">Select Template</option>
                                    <option value="teacher_template_3">Template 1</option>
                                    {{-- <option value="template_2">Template 2</option>
                                    <option value="template_3">Template 3</option> --}}
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Card Per Row</label>
                                <input type="text" id="row" name="row" required="required" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Card Per Column</label>
                                <input type="text" required="required" id="column" name="column" class="form-control">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>View Templates</label>
                                <a href="{{ route('view_samples_user') }}" target="blank" class="form-control">View Template</a>
                            </div>

                                <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="example" class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th><input id="checkall" onchange="checkAll(this);" type="checkbox">
                                                </th>
                                                <th>SR No.</th>
                                                <th>Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                            $j=1;
                                            @endphp
                                            @if(isset($data['data']))
                                            <?PHP $i = 1; ?>
                                            @foreach($data['data'] as $key => $value)

                                            <tr>

                                                <td><input id="{{$key}}" value="{{$key}}" name="students[]"
                                                        type="checkbox"></td>
                                                <td>
                                                    {{ $i }}
                                                </td>
                                                {{-- <td>{{$value['enrollment_no']}}</td> --}}
                                                <td>{{$value}}</td>
                                            </tr>
                                            @php
                                            $j++;
                                            @endphp
                                            @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-12 form-group">
                                    <center>
                                        <input type="hidden" name="grade_id" @if(isset($data['grade_id']))
                                            value="{{$data['grade_id']}}" @endif">

                                        <input type="hidden" name="standard_id" @if(isset($data['standard_id']))
                                            value="{{$data['standard_id']}}" @endif">

                                        <input type="submit" name="submit" value="Submit" class="btn btn-success">
                                    </center>
                                </div>
                            </div>
                            </div>
                        </form>

                    </div>
            @endif
        </div>
    </div>

    @include('includes.footerJs')
    <script>
        function checkAll(ele) {
	     var checkboxes = document.getElementsByTagName('input');
	     if (ele.checked) {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = true;
	             }
	         }
	     } else {
	         for (var i = 0; i < checkboxes.length; i++) {
	             if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}
    </script>
    <!-- <script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script> -->
    @include('includes.footer')
