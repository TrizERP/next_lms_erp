@include('includes.headcss')
<style>
   tfoot input {
   width: 100%;
   padding: 3px;
   box-sizing: border-box;
   }
   tfoot {
   display: table-header-group;
   }
</style>
@include('includes.header')
@include('includes.sideNavigation')
<div id="page-wrapper">
   <div class="container-fluid">
      <div class="row bg-title">
         <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title">Classwise Grade Report</h4>
         </div>
      </div>
        @php 
            $grade=$standard=$division=$term='';
            if(isset($data['grade_id'])){
                $grade = $data['grade_id'];
            }
            if(isset($data['standard_id'])){
                $standard = $data['standard_id'];
            }
            if(isset($data['division_id'])){
                $division = $data['division_id'];
            }
            if(isset($data['term_id'])){
                $term = $data['term_id'];
            }
        @endphp 
        <div class="card">
            <form action="{{route('classwise_grade_report.create')}}" method="GET">
                @csrf
                <div class="row">
                    {{ App\Helpers\TermDD($term,'4') }}
                    {{ App\Helpers\SearchChain('4','single','grade,std,div',$grade,$standard,$division) }}
                    <div class="col-md-4 form-group" id="for_exam_type">
                            <label>Exam Type</label>
                            <select name="exam_type" id="exam_type" class="form-control">
                                <option value="">Select</option>
                              
                            </select>
                        </div>

                    <div class="col-md-12">
                        <center>
                            <input type="submit" value="Search" name="submit" class="btn btn-primary">
                        </center>
                    </div>
                </div>
            </form>
        </div>

    @if(isset($data['studentData']))
      <div class="card">
         <div class="col-lg-12 col-sm-12 col-xs-12">
            <div class="table-responsive">
               <table id="example" class="table table-striped">
                  <thead>
                     <tr>
                        <th>ROLL NO.</th>
                        <th>STUDENT NAME</th>
                        <th>EXAM</th>
                        @foreach($data['examSubject'] as $key=>$value)
                        <th>{{strtoupper($value->subject_name)}}</th>
                        @endforeach
                        <th>TOTAL</th>
                        <th>RANK</th>
                        <th>PERC.</th>
                        <th>ATTED.</th>
                        <th>APPLI.</th>
                        <th>CONDUCT</th>
                        <th class="text-left">REMARKS</th>
                     </tr>
                  </thead>
                  <tbody>
                    @php 
                        $rollNo = $name = [];
                    @endphp
                    @foreach($data['studentData'] as $k => $all_data)
                        @php 
                            $passMarks = 35;
                            $Failed=$rank = 0;
                        @endphp
                        @foreach($all_data['exams'] as $et => $ev)
                        @php 
                            $examTotal = $examObt = $subjectPer = 0;
                            $exam_name = $ev;
                            if(in_array($ev,['Grand Total','Average'])){
                                $exam_name = '<b>'.$exam_name.'<b>';
                            }
                        @endphp
                        <tr>
                            <td>
                            @php 
                                $roll_no = $all_data['roll_no'];
                                if (!in_array($all_data['roll_no'], $rollNo)) {
                                    $rollNo[] = $roll_no; 
                                }else{
                                    $roll_no= '';
                                }
                            @endphp    
                            {{$roll_no}}
                            </td>
                            <td>
                                @php 
                                    $student_name = $all_data['first_name'].' '.$all_data['middle_name'].' '.$all_data['last_name'];
                                    if (!in_array($student_name, $name)) {
                                        $name[] = $student_name; 
                                    }else{
                                        $student_name= '';
                                    }
                                @endphp
                                {{$student_name}}
                            </td>
                            <td>{!! $exam_name !!}</td>
                            @foreach($data['examSubject'] as $key=>$value)
                            <td>
                                @php
                                $obt_mark=0;
                                if(isset($all_data['examData'][$ev][$value->subject_name]->obtained_points)){
                                    $obt_mark = $all_data['examData'][$ev][$value->subject_name]->obtained_points;
                                    if(is_numeric($obt_mark)){
                                        $obt_mark = number_format($obt_mark,2);
                                    }
                                }

                                if(isset($all_data['examData'][$ev][$value->subject_name]->elective_subject) && $all_data['examData'][$ev][$value->subject_name]->elective_subject!='Yes'){
                                    $examObt +=$obt_mark;
                                }

                                if(isset($all_data['examData'][$ev][$value->subject_name]->total_points) && $all_data['examData'][$ev][$value->subject_name]->elective_subject!='Yes'){
                                    $examTotal += $all_data['examData'][$ev][$value->subject_name]->total_points;
                                }

                                if(isset($all_data['examData']['Grand Total'][$value->subject_name]) && $ev=='Grand Total'){
                                    $obt_mark = $all_data['examData'][$ev][$value->subject_name];
                                    $examObt +=$obt_mark;
                                }
                                if(isset($all_data['examData']['Average'][$value->subject_name]) && $ev=='Average'){
                                    $obt_mark = ($all_data['examData']['Average'][$value->subject_name] > 0) ? number_format(($all_data['examData'][$ev][$value->subject_name] * $all_data['examData']['Grand Total'][$value->subject_name]) / 100,2) : 0;
                                    $examObt +=$obt_mark;
                                    $examTotal += $all_data['examData']['Average'][$value->subject_name];
                                }
                               
                                if(isset($all_data['examData'][$ev]['rank'])){
                                    $rank = $all_data['examData'][$ev]['rank'];
                                }

                                if(isset($all_data['examData'][$ev][$value->subject_name]->elective_subject) && $all_data['examData'][$ev][$value->subject_name]->elective_subject=='Yes'){
                                    $obt_mark = '<b>'.$obt_mark.'</b>';
                                }
                                @endphp
                                {!! $obt_mark !!}
                            </td>
                            @endforeach
                            <td>{{$examObt}}</td>
                            <td>{{$rank}}</td>
                            <td>
                                @php
                                    $att = $all_data['attendance'];
                                    $per = ($examTotal != 0) ? (($examObt * 100) / $examTotal) : 0;
                                    $percentage = number_format($per,2);
                                    if($passMarks>$percentage){
                                        $Failed++;
                                    }
                                    $appText = "Good";
                                    $remarksText = "Can do better";
                                    $conduct = "Good";
                                    if (isset($Failed)) {
                                        if ($Failed == 1) {
                                            $appText = "Fair";
                                            $remarksText = 'Work Hard in Failed Subject';
                                        } else if ($Failed == 2) {
                                            $appText = "Satisfaction";
                                            $remarksText = 'Work Hard in Failed Subjects';
                                        } else if ($Failed >= 3) {
                                            $appText = "Not Satisfaction";
                                            $remarksText = 'Work Hard in Failed Subjects';
                                        } else {
                                            $appText = "Good";
                                            $remarksText = 'Work Hard in Failed Subjects';
                                        }
                                    }
                                    if($ev=='Grand Total'){
                                        $appText = $remarksText = $conduct= $percentage = $att= $rank='';
                                    }
                                    if($ev=='Average'){
                                        $rank='';
                                    }
                                @endphp
                                {{$percentage}}
                            </td>
                            <td>{{$att}}</td>
                            <td>{{$appText}}</td>
                            <td>{{$conduct}}</td>
                            <td>{{$remarksText}}</td>
                        </tr>
                        @endforeach
                    @endforeach
                  </tbody>
               </table>
            </div>
         </div>
      </div>
      @endif           
   </div>
</div>
@include('includes.footerJs')
<script>
$(document).ready(function() {
    // Event listener for #standard change
    $('#standard').on('change', function() {
        // alert('function called');

        var std_id = $('#standard').val();
        var termID = $('#term').val(); 
        getExam(std_id, termID);
    });

    // Check if standard and term are set
    @if($standard != '' && $term != '')
        var std_id = "{{$standard}}";
        var termID = "{{$term}}"; 
        var selVal = "{{ isset($data['exam_type']) ? $data['exam_type'] : '' }}";
        getExam(std_id, termID, selVal);
    @endif

    // AJAX function to get exam data
    function getExam(std_id, termID, SelVal = '') {
        // alert('function called');
        $.ajax({
            type: "GET",
            url: "{{ route('getCreateExamName') }}",
            data: { stdId: std_id, termID: termID }, 
            success: function(res) {
                if (res) {
                    $("#exam_type").empty();
                    $("#exam_type").append('<option value="">Select</option>');
                    var select = '';
                    $.each(res, function(key, value) {
                        if (value.title == SelVal) {
                            select = "selected";
                        }
                        $("#exam_type").append('<option value="' + value.title + '" ' + select + '>' + value.title + '</option>');
                    });
                } else {
                    $("#exam_type").empty();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error: " + error); // Log any AJAX errors
            }
        });
    }

    // Initialize DataTable
    var table = $('#example').DataTable({
        select: true,
        lengthMenu: [ 
            [100, 500, 1000, -1], 
            ['100', '500', '1000', 'Show All'] 
        ],
        columnDefs: [
            { orderable: false, targets: '_all' } 
        ],
        dom: 'Bfrtip',
        buttons: [
            { 
                extend: 'pdfHtml5',
                title: 'Inward Report',
                orientation: 'landscape',
                pageSize: 'A0',
                exportOptions: { columns: ':visible' },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Classwise Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Classwise Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Classwise Report' }, 
            'pageLength'
        ]
    });

    // Clone table header for search functionality
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