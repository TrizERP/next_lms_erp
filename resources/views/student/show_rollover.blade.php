@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Rollover</h4>
            </div>
        </div>
        @php

        $grade_id = $standard_id = $division_id = $from_institute_name = $to_academic_section = $to_standard = $to_division = '';

            $from_current_syear = Session::get('syear');
            $to_next_syear = $from_current_syear + 1;
        
            if(isset($data['grade'])){
                $grade_id = $data['grade'];
                $standard_id = $data['standard'];
                $division_id = $data['division'];
            }
            if(isset($data['from_institute_name']))
            {
                $from_institute_name = $data['from_institute_name'];
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

            <form action="{{ route('rollover.create') }}" id="submit_rollover_form">  
                <div class="table-responsive">
                    <table class="table table-box table-bordered">
                        <thead>
                            <tr>
                                <th>Module Name</th>
                                <th>Check for Rollover Data</th>
                                <th class="text-left">Rollover Data Status</th>
                            </tr>
                        </thead>
                        <tbody>                         
                        @if(isset($data['table_array']))
                            @foreach($data['table_array'] as $table_key => $table_name)
                            @php
                            
                            $tblstudent_enrollment_data = explode('/',$data['table_array_check']['tblstudent_enrollment']);

                            $checked = $required = $disabled = $radio_disabled = '';
                            if($data['table_array_check'][$table_key] != 0)
                            {
                                $disabled = 'disabled="disabled" ';
                            }
                            if($tblstudent_enrollment_data[2] == 0)
                            {
                                $radio_disabled = 'disabled="disabled" ';
                            }

                            if($table_key == 'academic_year' || $table_key == 'fees_map_years' || $table_key == 'fees_title')
                            {
                                $checked = 'checked=checked';
                                $required = ' required="required" ';
                            }
                            
                            @endphp
                            <tr>
                                <td>{{$table_name}}</td>
                                <td>
                                    @if($table_key != 'tblstudent_enrollment')
                                        <input type="checkbox" id="{{$table_key}}" value="{{$table_key}}" name="tables[]" {{$checked}} {{$required}} {{$disabled}}>
                                    @else                                        
                                        <input type="radio" name="tblstudent_enrollment" id="{{$table_key}}" value="all_students" {{$radio_disabled}}>
                                        <label for="all_students">All Students</label>
                                        <input type="radio" name="tblstudent_enrollment" id="{{$table_key}}" value="selected_students" {{$radio_disabled}}>
                                        <label for="selected_students">Selected Students</label>
                                    @endif
                                </td>
                                @if($table_key != 'tblstudent_enrollment')
                                    @if($data['table_array_check'][$table_key] != 0)
                                        <td style='color:green;font-size: 22px;'>&#10004;</td>
                                    @else
                                        <td style='color:red;'>&#10060;</td>
                                    @endif    
                                @else
                                    @if($tblstudent_enrollment_data[0] == $tblstudent_enrollment_data[1])
                                        <td style='color:green;font-size: 22px;'>&#10004;</td>
                                    @elseif($tblstudent_enrollment_data[1] <= $tblstudent_enrollment_data[0] && $tblstudent_enrollment_data[1] != 0)
                                        <td style='color:blue;font-size: 22px;'>&#10004;</td>
                                    @elseif($tblstudent_enrollment_data[2] != 0 && $tblstudent_enrollment_data[1] == 0)
                                        <td style='color:red;'>&#10060;</td>     
                                    @endif    
                                @endif
                            </tr>
                            @endforeach
                        @endif  
                        </tbody>
                    </table>
                </div>                                
                <div class="col-sm-12 form-group mt-3">
                    <center>                            
                        <input type="submit" name="submit" value="Rollover Data" class="btn btn-success" >
                    </center>
                </div>             
            </form> 
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
    $('#submit_rollover_form').submit(function(){ 
        var selected_tables = $("input[name='tables[]']:checked").length;
        if(selected_tables <= 3)
        {
            alert("Please Select Atleast Table for Rollover.");
            return false;
        }
        else{
            return true;
        }   
    });

    $('input[name="tblstudent_enrollment"]:radio').change(function () {
        var selected_radio_value = $(this).attr("value");
        if(selected_radio_value == 'selected_students')
        {
            var tables_arr = new Array();
            $.each($("input[name='tables[]']:checked"), function() {
              tables_arr.push($(this).val());
            });

            window.location.href = "{{ route('selected_student_view') }}"+"?tables="+tables_arr+"&tblstudent_enrollment_value="+selected_radio_value;
        }
    });

</script>
@include('includes.footer')
