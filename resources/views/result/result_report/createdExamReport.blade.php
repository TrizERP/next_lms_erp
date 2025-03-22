@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Created Exam Report</h4> 
            </div>
        </div>        
        @if(isset($data['studentData']))
            <div class="card">  
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">                       
                             <thead>
                                <tr>
                                    <th rowspan="2">Roll No</th>
                                    <th rowspan="2">Student Name</th>
                                    <th rowspan="2">Enrollment No</th>
                                    <th rowspan="2">Std/Div</th>
                                    <th rowspan="2">Term</th>
                                    @foreach($data['createdExams'] as $subjectName => $examData)
                                        @php
                                        $colspan =count($examData);
                                            if($data['totalField']=='Yes'){
                                                $colspan = $colspan+1;
                                            }
                                        @endphp
                                        <th colspan="{{$colspan}}">{{ $subjectName }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                @foreach($data['createdExams'] as $subjectName => $examData)

                                    @php $subjectTotal = 0; @endphp
                                    @foreach($examData as $examTitle=>$createExams)
                                        @php $subjectTotal += $createExams->points; @endphp
                                        <th>{{$createExams->title}}<br>({{$createExams->points}})</th>
                                    @endforeach

                                    @if($data['totalField']=='Yes')
                                        <th>Total <br> ({{$subjectTotal}})</th>
                                    @endif
                                @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['studentData'] as $studentId => $studentData)
                                <tr>
                                    <td>{{$studentData['roll_no']}}</td>
                                    <td>{{$studentData['first_name'] ?? '-'}} {{$studentData['middle_name'] ?? '-'}} {{$studentData['last_name'] ?? '-'}}</td>
                                    <td>{{$studentData['enrollment_no']}}</td>
                                    <td>{{$studentData['standard_name']}} / {{$studentData['division_name']}}</td>
                                    <td>{{$data['termData'][$data['term_id']]}}</td>
                                    @foreach($data['createdExams'] as $subjectName => $examData)

                                        @php $marksTotal = 0; @endphp
                                        @foreach($examData as $examTitle=>$createExams)
                                            @php $marksTotal+= (isset($studentData[$subjectName][$createExams->id]['marks']) && !in_array($studentData[$subjectName][$createExams->id]['marks'],['N.A.','AB','EX'])) ? $studentData[$subjectName][$createExams->id]['marks'] : 0; @endphp
                                            <td>{{ isset($studentData[$subjectName][$createExams->id]['marks']) ? $studentData[$subjectName][$createExams->id]['marks'] : '-' }}</td>
                                        @endforeach

                                        @if($data['totalField']=='Yes')
                                        <td>{{$marksTotal}}</td>
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
</div>

@include('includes.footerJs')

<script>
    $(document).ready(function() {
    var table = $('#example').DataTable( {
         select: true,          
         lengthMenu: [ 
                        [100, 500, 1000, -1], 
                        ['100', '500', '1000', 'Show All'] 
        ],
        dom: 'Bfrtip', 
        buttons: [ 
            { 
                extend: 'pdfHtml5',
                title: 'Inward Report',
                orientation: 'landscape',
                pageSize: 'LEGAL',                
                pageSize: 'A0',
                exportOptions: {                   
                     columns: ':visible'                             
                },
            }, 
            { extend: 'csv', text: ' CSV', title: 'Created Exams Report' }, 
            { extend: 'excel', text: ' EXCEL', title: 'Created Exams Report' }, 
            { extend: 'print', text: ' PRINT', title: 'Created Exams Report' }, 
            'pageLength' 
        ], 
        }); 

    //     $('#example thead tr:first-child th').each(function (i) {
    //     if (i < 5) { // Apply search inputs only to the first 4 columns
    //         var title = $(this).text().trim();
    //         if (title !== "") {
    //             $(this).append('<br><input type="text" placeholder="Search ' + title + '" style="width: 100%;"/>');

    //             $('input', this).on('keyup change', function () {
    //                 if (table.column(i).search() !== this.value) {
    //                     table.column(i).search(this.value).draw();
    //                 }
    //             });
    //         }
    //     }
    // });
    
    } );
</script>

@include('includes.footer')
