@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
use DB;

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Rollover </h4>
            </div>
        </div>
        @php

            $grade_id = $standard_id = $division_id = $to_academic_section = $to_standard = $to_division = $tables = $tblstudent_enrollment_value = $from_institute = '';

            $from_current_syear = Session::get('syear');
            $to_next_syear = $from_current_syear + 1;

            if(isset($_REQUEST['tables']))
            {
                $tables = $_REQUEST['tables'];
                $tblstudent_enrollment_value = $_REQUEST['tblstudent_enrollment_value'];
            }else
            {
                $tables = $data['tables'];
                $tblstudent_enrollment_value = $data['tblstudent_enrollment_value'];

            }

            if(isset($from_institute_name) && $from_institute_name != ''){
                $from_institute  = $from_institute_name;
            }else{
                $from_institute = $data['from_institute_name'];
            }

            if(!isset($to_academic_sections))
            {
                $to_academic_sections = $data['to_academic_sections'];
            }

            if(isset($data['grade'])){
                $grade_id = $data['grade'];
                $standard_id = $data['standard'];
                $division_id = $data['division'];
            }

            if(isset($data['to_academic_section']))
            {
                $to_academic_section = $data['to_academic_section'];
            }
            if(isset($data['to_standard']))
            {
                $to_standard = $data['to_standard'];
            }
            if(isset($data['to_division']))
            {
                $to_division = $data['to_division'];
            }

        @endphp
        <div class="card">
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

                        <form action="{{ route('rollover.create') }}" method="post">
                            @csrf
                            <div class="row" id="form_for_selected_students">
                                <div class="col-md-6">
                                    <div class="col-md-12">
                                        <label>From Institute Name:</label>
                                        <input type="text" id="from_institute_name" value="{{$from_institute}}"
                                               name="from_institute_name" class="form-control" readonly>
                                        <input type="hidden" id="from_sub_institute_id"
                                               value="{{Session::get('sub_institute_id')}}" name="from_sub_institute_id"
                                               class="form-control">
                                        <input type="hidden" id="new_tables" value="{{$tables}}" name="new_tables"
                                               class="form-control">
                                        <input type="hidden" id="tblstudent_enrollment"
                                               value="{{$tblstudent_enrollment_value}}" name="tblstudent_enrollment"
                                               class="form-control">
                                    </div>
                                    <div class="col-md-12">
                                        <label>From Current Year:</label>
                                        <input type="text" id="from_current_syear" value="{{$from_current_syear}}"
                                               name="from_current_syear" class="form-control" readonly>
                                    </div>
                                    {{ App\Helpers\SearchChain('12','single','grade,std,div',$grade_id,$standard_id,$division_id) }}
                                </div>
                                <div class="col-md-6">
                                    <div class="col-md-12">
                                        <label>To Institute Name:</label>
                                        <input type="text" id="to_institute_name" value="{{$from_institute}}" name="to_institute_name" class="form-control" readonly>
                        </div>
                                    <div class="col-md-12">
                                        <label>To Next Year:</label>
                                        <input type="text" id="to_next_syear" value="{{$to_next_syear}}"
                                               name="to_next_syear" class="form-control" readonly>
                                    </div>
                                    <div class="col-md-12">
                                        <label>To Academic Section:</label>
                                        <select name="to_academic_section" id="to_academic_section" class="form-control"
                                                onchange="getToStandard(this.value);">
                                            <option value="">Select</option>
                                            @if(isset($to_academic_sections))
                                                @foreach($to_academic_sections as $k => $v)
                                                    <option value="{{$v['id']}}"
                                                            @if($to_academic_section == $v['id'])
                                                            selected='selected'
                                                        @endif >{{$v['title']}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label>To {{App\Helpers\get_string('standard','request')}}:</label>
                                        <select name="to_standard" id="to_standard" class="form-control"
                                                onchange="getToDivision(this.value);">
                                            <option value="">Select</option>
                                            @if(isset($data['to_standards']))
                                                @foreach($data['to_standards'] as $k1 => $v1)
                                                    <option value="{{$v1['id']}}"
                                                            @if($to_standard == $v1['id'])
                                                            selected='selected'
                                                        @endif>{{$v1['name']}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label>To {{App\Helpers\get_string('division','request')}}:</label>
                                        <select name="to_division" id="to_division" class="form-control">
                                            <option value="">Select</option>
                                            @if(isset($data['to_divisions']))
                                                @foreach($data['to_divisions'] as $k2 => $v2)
                                                    <option value="{{$v2['id']}}"
                                                            @if($to_division == $v2['id'])
                                                            selected='selected'
                                                        @endif>{{$v2['name']}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 form-group mt-3">
                                <center>
                                    <input type="submit" name="search_students" id="search_students"
                                           value="Search Students" class="btn btn-success">
                                </center>
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
            <span class="d-inline-block" tabindex="0" data-toggle="tooltip"
                  title="Green color indicates that student is already exist in next year.">
                <button class="btn btn-danger" style="pointer-events: none;" type="button" disabled="">Note</button>
            </span>
                            <form method="POST" action="{{ route('rollover.store') }}"
                                  id="submit_form_for_selected_students">
                                @csrf
                                <div class="row mt-5">
                                    <div class="col-lg-12 col-sm-12 col-xs-12">
                                        <div class="table-responsive">
                                            <table class="table table-box table-bordered">
                                                <thead>
                                                <tr>
                                                    <th><input id="checkall" name="checkall" onchange="checkAll(this);"
                                                               type="checkbox"></th>
                                                    <th>Sr.No.</th>
                                                    <th>{{App\Helpers\get_string('studentname','request')}}</th>
                                                    <th>{{App\Helpers\get_string('grno','request')}}</th>
                                                    <th>{{App\Helpers\get_string('standard','request')}}</th>
                                        <th>Division</th>
                                        <th>Mobile</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $j=1;
                                    @endphp
                                    @foreach($student_data as $key => $data)

                                    @php
                                        $sub_institute_id = Session::get('sub_institute_id');
                                        $exist_student_data = DB::select("SELECT count(*) as exist_student,se.student_id
                                                                FROM tblstudent_enrollment se
                                                                WHERE se.syear = ".$finalData['to_next_syear']." AND se.sub_institute_id = $sub_institute_id
                                                                AND se.student_id = ".$data['student_id']." ");

                                        $exist_student_data = json_decode(json_encode($exist_student_data),true);

                                        $disabled = $colour = '';
                                        if(!empty($exist_student_data))
                                        {
                                            if($exist_student_data[0]['exist_student'] >= 1)
                                            {
                                                $disabled = 'disabled = "disabled"';
                                                $colour = ' style=background-color:#90EE90; ';
                                            }
                                        }

                                    @endphp

                                    <tr {{$colour}}>
                                        <td>
                                            <input id="students" value="{{$data['student_id']}}" name="students[]"
                                                   type="checkbox" {{$disabled}}>
                                        </td>
                                        <td>{{$j}}</td>
                                        <td>{{$data['first_name']}} {{$data['middle_name']}} {{$data['last_name']}}</td>
                                        <td>{{$data['enrollment_no']}}</td>
                                        <td>{{$data['standard_name']}}</td>
                                        <td>{{$data['division_name']}}</td>
                                        <td>{{$data['mobile']}}</td>
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
                                            <input type="hidden" name="from_institute_name"
                                                   @if(isset($finalData['from_institute_name'])) value="{{$finalData['from_institute_name']}}" @endif>
                                            <input type="hidden" name="from_current_syear"
                                                   @if(isset($finalData['from_current_syear'])) value="{{$finalData['from_current_syear']}}" @endif>
                                            <input type="hidden" name="grade"
                                                   @if(isset($finalData['grade'])) value="{{$finalData['grade']}}" @endif>
                                            <input type="hidden" name="standard"
                                                   @if(isset($finalData['standard'])) value="{{$finalData['standard']}}" @endif>
                                            <input type="hidden" name="division"
                                                   @if(isset($finalData['division'])) value="{{$finalData['division']}}" @endif>
                                            <input type="hidden" name="to_sub_institute_id"
                                                   @if(isset($finalData['from_institute_name'])) value="{{$finalData['from_institute_name']}}" @endif>
                                            <input type="hidden" name="to_next_syear"
                                                   @if(isset($finalData['to_next_syear'])) value="{{$finalData['to_next_syear']}}" @endif>
                                            <input type="hidden" name="to_academic_section"
                                                   @if(isset($finalData['to_academic_section'])) value="{{$finalData['to_academic_section']}}" @endif>
                                            <input type="hidden" name="to_standard"
                                                   @if(isset($finalData['to_standard'])) value="{{$finalData['to_standard']}}" @endif>
                                            <input type="hidden" name="to_division"
                                                   @if(isset($finalData['to_division'])) value="{{$finalData['to_division']}}" @endif>
                                            <input type="hidden" id="new_tables"
                                                   @if(isset($finalData['tables'])) value="{{$finalData['tables']}}"
                                                   @endif name="new_tables">
                                            <input type="hidden" id="tblstudent_enrollment"
                                                   @if(isset($finalData['tblstudent_enrollment_value'])) value="{{$finalData['tblstudent_enrollment_value']}}"
                                                   @endif name="tblstudent_enrollment">
                                            <input type="submit" name="submit" value="Rollover Student"
                                                   class="btn btn-success mt-3">
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
        $('#division').attr('required', true);
        $('#to_academic_section').attr('required', true);
        $('#to_standard').attr('required', true);
        $('#to_division').attr('required', true);

        $('#submit_form_for_selected_students').submit(function () {
            var selected_stud = $("input[name='students[]']:checked").length;
            if (selected_stud == 0) {
                alert("Please Select Atleast One Student");
                return false;
            } else {
                return true;
            }
        });

        $('input[name="tblstudent_enrollment"]:radio').change(function () {
            var selected_radio_value = $(this).attr("value");

            // if(selected_radio_value == 'selected_students')
            // {

            // }

        });

        function getToStandard(to_academic_section) {
            var to_academic_section = $("#to_academic_section").val();
            var path = "{{ route('ajax_toStandards') }}";
            $('#to_standard').find('option').remove().end().append('<option value="">Select</option>').val('');
            $.ajax({
                url: path, data: 'to_academic_section=' + to_academic_section, success: function (result) {
                    for (var i = 0; i < result.length; i++) {
                        $("#to_standard").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
                    }
                }
            });
        }

        function getToDivision(to_standard) {
            var to_standard = $("#to_standard").val();
            var path = "{{ route('ajax_toDivisions') }}";
        $('#to_division').find('option').remove().end().append('<option value="">Select</option>').val('');
        $.ajax({url: path,data:'to_standard='+to_standard, success: function(result){
            for(var i=0;i < result.length;i++){
                $("#to_division").append($("<option></option>").val(result[i]['id']).html(result[i]['name']));
            }
        }
        });
    }

	function checkAll(ele) {
         // var checkboxes = $("input[name='checkall']");
         // alert(checkboxes);
	     var checkboxes_new = document.getElementsByTagName('input');
         // alert(checkboxes);

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
