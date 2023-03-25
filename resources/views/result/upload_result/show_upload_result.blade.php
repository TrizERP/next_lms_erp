@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Upload Result</h4>
            </div>
        </div>
        @if ($sessionData = Session::get('data'))
        @if($sessionData['status'] == 1)
        <div class="alert alert-success alert-block">
        @else
        <div class="alert alert-danger alert-block">
        @endif
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
        </div>
        @endif
        @php
            $grade_id = $standard_id = $division_id = $term_id = '';
            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
            if(isset($data['term_id'])){
                $term_id = $data['term_id'];
            }
        @endphp
            <div class="card">
                <form action="{{ route('upload_result.create') }}">
                    @csrf
                    <div class="row">
                        {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                        {{ App\Helpers\TermDD($term_id) }}

                        <div class="col-sm-12 form-group">
                            <center>
                                <input type="submit" name="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
            @if(isset($data['student_data']))
                @php
                    if(isset($data['student_data'])){
                        $student_data = $data['student_data'];
                        $finalData = $data;
                    }
                @endphp

                <div class="card">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('upload_result.store') }}"
                          id="submit_form">
                        @csrf
                        <div class="row">
                            <div class="col-lg-12 col-sm-12 col-xs-12">
                                <div class="table-responsive">
                                    <table id="example" class="table table-striped">
                                        <thead>
                                        <tr>
                                            <th><input id="checkall" onchange="checkAll(this);" type="checkbox"></th>
                                            <th>Student Name</th>
                                            <th>Enrollment/GR No.</th>
                                            <th>Std/Div</th>
                                            <th>Mobile</th>
                                        <th>Term</th>
                                        <th>File</th>
                                        <th>File Link</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        @php
                                        $j=1;
                                        @endphp
                                    @foreach($student_data as $key => $data)
                                    <tr>
                                        <td>
                                            <input id="{{$data['CHECKBOX']}}" value="{{$data['CHECKBOX']}}" name="students[]" type="checkbox" onclick="required_file(this.value);">
                                        </td>
                                        <td>{{$data['student_name']}}</td>
                                        <td>{{$data['enrollment_no']}}</td>
                                        <td>{{$data['standard_name']}} / {{$data['division_name']}}</td>
                                        <td>{{$data['mobile']}}</td>
                                        <td>{{$data['term_name']}}</td>
                                        <td>
                                            <input type="file" id="image[{{$data['CHECKBOX']}}]" name="image[{{$data['CHECKBOX']}}]" class="form-control" onclick="required_checkbox({{$data['CHECKBOX']}});">
                                        </td>
                                        <td>
                                            <a target="blank" href="/storage/upload_result/{{$data['file_name']}}">{{$data['file_name']}}</a>
                                        </td>
                                    </tr>
                                        @php
                                        $j++;
                                        @endphp
                                    @endforeach
                                </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-12 form-group">
                                <center>
                                    <input type="hidden" name="division_id"
                                           @if(isset($finalData['division_id'])) value="{{$finalData['division_id']}}" @endif>
                                    <input type="hidden" name="standard_id"
                                           @if(isset($finalData['standard_id'])) value="{{$finalData['standard_id']}}" @endif>
                                    <input type="hidden" name="grade_id"
                                           @if(isset($finalData['grade_id'])) value="{{$finalData['grade_id']}}" @endif>
                                    <input type="hidden" name="term_id"
                                           @if(isset($finalData['term_id'])) value="{{$finalData['term_id']}}" @endif>
                                    <input type="submit" name="submit" value="Submit" class="btn btn-success">
                                </center>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

@include('includes.footerJs')
    <script>
        $('#grade').attr('required', true);
        $('#standard').attr('required', true);
        $('#term').attr('required', true);

        $('#submit_form').submit(function () {
            var selected_stud = $("input[name='students[]']:checked").length;
            if (selected_stud == 0) {
                alert("Please Select Atleast One Student");
                return false;
            } else {
                return true;
            }
        });

        function required_file(val) {
            document.getElementById('image[' + val + ']').required = true;
        }

        function required_checkbox(val) {
            document.getElementById(val).required = true;
        }

        function checkAll(ele) {
            var checkboxes = $("input[name='checkall']");
            if (ele.checked) {
                for (var i = 0; i < checkboxes.length; i++) {
                    if (checkboxes[i].type == 'checkbox') {
                        checkboxes[i].checked = true;
                    }
                }
            } else {
                for (var i = 0; i < checkboxes.length; i++) {
                    console.log(i)
                    if (checkboxes[i].type == 'checkbox') {
	                 checkboxes[i].checked = false;
	             }
	         }
	     }
	}
</script>
@include('includes.footer')
