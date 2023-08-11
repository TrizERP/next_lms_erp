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
                <h4 class="page-title">Overall Report</h4>
            </div>
        </div>
        @php
            $gradeScale = \App\Helpers\getGradeScale();
            $dataFirstExam = isset(collect($data['data'])->first()['exam']) ? collect($data['data'])->first()['exam'] : [];
            $t1 = count($dataFirstExam) + 1;

            $term2FirstExam = isset(collect($data['term_2_data'])->first()['exam']) ? collect($data['term_2_data'])->first()['exam'] : [];
            $t2 = count($term2FirstExam) + 1;

            $term3FirstExam = isset(collect($data['term_3_data'])->first()['exam']) ? collect($data['term_3_data'])->first()['exam'] : [];
            $t3 = count($term3FirstExam) + 1;

            $term4FirstExam = isset(collect($data['term_4_data'])->first()['exam']) ? collect($data['term_4_data'])->first()['exam'] : [];
            $t4 = count($term4FirstExam) + 1;

            $sub = $t1 + $t2 + $t3 + $t4;

        @endphp
        <div class="card">
            <div class="col-lg-12 col-sm-12 col-xs-12">
                <div class="table-responsive">
                    <div class="db-buttons">
                        <a style="    background: #25bdea none repeat scroll 0 0;border-radius: 4px;color: #ffffff;margin-right: 3px;
                        padding: 8px 15px;display: inline-block;" class="dt-button buttons-excel buttons-html5"
                           href="{{ url('cbse_1t5_result/download_overall_report') }}">EXCEL</a>
                    </div>
                    <table id="example" class="table table-striped">
                        <thead>
                        <tr>
                            <td class="text-center fw-bold" rowspan="2" colspan="2">
                            {{$data['std_div']}}
                           </td>
                        @if(isset(collect($data['data'])->first()['mark'] ))                            
                            @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
                            <td class="text-center fw-bold" colspan="{{$sub}}">{{ $subject  }}
                            @foreach($data['all_subject'] as $val)
                            @php 
                           $remove = str_replace('#','',$val);
                           $remove_yes =strtoupper(str_replace('Yes','',$remove));
                           @endphp
                           @if($subject==$remove_yes)
                            @if(substr($remove,-3)=="Yes")
                            <span class="text-danger fs-3">*</span>
                            @endif
                            @endif                            
                            @endforeach                            
                            </td>
                            @endforeach
                        @endif
                            <td class="text-center fw-bold" rowspan="2" colspan="3">FINAL RESULT</td>   
                           
                        </tr>
                        @if(isset(collect($data['data'])->first()['mark'] ))
                        <tr>
                            @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
                            @if(isset($data))
                                <td class="text-center fw-bold"
                                    colspan="{{ count(collect($data['data'])->first()['exam']) + 1 }}">{{ collect($data['data'])->first()['term'] }}</td>
                            @endif
                            @if(isset($data['term_2_data']))
                                <td class="text-center fw-bold"
                                    colspan="{{ count(collect($data['term_2_data'])->first()['exam']) + 1 }}">{{ collect($data['term_2_data'])->first()['term'] }}</td>
                            @endif
                            @if(isset($data['term_3_data']) && $data['term_3_data']!=null)
                            
                                <td class="text-center fw-bold"
                                    colspan="{{ count(collect($data['term_3_data'])->first()['exam']) + 1 }}">{{ collect($data['term_3_data'])->first()['term'] }}</td>
                            @endif
                            @if(isset($data['term_4_data']) && $data['term_3_data']!=null)
                            
                                <td class="text-center fw-bold"
                                    colspan="{{ count(collect($data['term_4_data'])->first()['exam']) + 1 }}">{{ collect($data['term_4_data'])->first()['term'] }}</td>
                            @endif
                            
                                <td class="text-center fw-bold" colspan="2">MARKS & GRADES</td>
                            @endforeach
                        </tr>
                        @endif
                        
                        <tr>
                            <td class="fw-bold">ROLL NO</td>
                            <td class="fw-bold">STUDENT NAME</td>
                            @php
                                $mainFinalTotal = 0;
                            @endphp
                        @if(isset(collect($data['data'])->first()['mark'] ))
                            
                            @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
                                @php
                                    $term1Total = $term2Total = $term3Total = $term4Total = 0;
                                @endphp
                                @foreach(collect($data['data'])->first()['exam'] as $exam)
                                    @if($exam['exam'] == 'Marks Obtained')
                                        @continue
                                    @endif
                                    @php
                                        $term1Total += $exam['mark'];
                                    @endphp
                                    
                                    <td class="fw-bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
                                @endforeach
                                <td class="fw-bold">Total ({{ $term1Total }})</td>
                                <td class="fw-bold">Grade</td>

                                @foreach(collect($data['term_2_data'])->first()['exam'] as $exam)
                                    @if($exam['exam'] == 'Marks Obtained')
                                        @continue
                                    @endif
                                    @php
                                        $term2Total += $exam['mark'];
                                    @endphp
                                    <td class="fw-bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
                                @endforeach
                                <td class="fw-bold">
                                    Total ({{ $term2Total }})
                                </td>
                            @if(isset($data['term_3_data']) && $data['term_3_data']!=null)

                                <td class="fw-bold">Grade</td>

                                @foreach(collect($data['term_3_data'])->first()['exam'] as $exam)
                                    @if($exam['exam'] == 'Marks Obtained')
                                        @continue
                                    @endif
                                    @php
                                        $term3Total += $exam['mark'];
                                    @endphp
                                    <td class="fw-bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
                                @endforeach

                                <td class="fw-bold">
                                    Total ({{ $term3Total ?? 0 }})
                                </td>
                                @endif

                            @if(isset($data['term_4_data']) && $data['term_3_data']!=null)

                                <td class="fw-bold">Grade</td>

                                @foreach(collect($data['term_4_data'])->first()['exam'] as $exam)
                                    @if($exam['exam'] == 'Marks Obtained')
                                        @continue
                                    @endif
                                    @php
                                        $term4Total += $exam['mark'];
                                    @endphp
                                    <td class="fw-bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
                                @endforeach

                                <td class="fw-bold">
                                    Total ({{ $term4Total ?? 0 }})
                                </td>
                                @endif

                                <td class="fw-bold">Grade</td>

                                <td class="fw-bold">MARKS ({{ $term1Total + $term2Total + $term3Total + $term4Total }})</td>
                                <td class="fw-bold">GRADES</td>
                                @php
                                    $mainFinalTotal += $term1Total ?? + $term2Total ?? + $term3Total ?? 0 + $term4Total ?? 0;
                                @endphp
                            @endforeach
                            @endif
                            <td>FINAL TOTAL ({{ $mainFinalTotal }})</td>
                            <td>GRADES</td>
                            <td>PERCENTAGE</td>
                        </tr>
                        </thead>
                        <tbody>

                        @foreach($data['data'] as $studendId => $sdata)
                            <tr>
                                <td style="color:#212529; font-weight: 500">{{ $sdata['roll_no'] }}</td>
                                <td style="color:#212529; font-weight: 500">{{ $sdata['name'] }}</td>
                                @php
                                    $finalTotal = 0;
                                @endphp
                                @foreach($sdata['mark'] as $subject => $value)
                                    @php
                                        $term1Total = $term2Total = $term3Total = $term4Total = 0;
                                        $mainTerm1Total = $mainTerm2Total = $mainTerm3Total = $mainTerm4Total = 0;
                                    @endphp

                                    @foreach($sdata['exam'] as $exam)
                                        @if($exam['exam'] == 'Marks Obtained')
                                            @continue
                                        @endif
                                        @php
                                            $mainTerm1Total += $exam['mark'];
                                            $term1Total += (float) ($value[$exam['exam']] ?? 00);
                                        @endphp
                                        <td>{{ $value[$exam['exam']] ?? 0 }}</td>
                                    @endforeach
                                    <td class="fw-bold">{{ $term1Total }}</td>
                                    <td class="fw-bold">{{ \App\Helpers\getGrade($gradeScale, $mainTerm1Total, $term1Total) }}</td>

                                    @foreach($data['term_2_data'][$studendId]['exam'] as $exam)
                                        @if($exam['exam'] == 'Marks Obtained')
                                            @continue
                                        @endif
                                        @php
                                            $mainTerm2Total += $exam['mark'];
                                            $term2Total += (float) ($data['term_2_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0);
                                        @endphp
                                        <td>{{ $data['term_2_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0}}</td>
                                    @endforeach
                                    <td class="fw-bold">{{ $term2Total }}</td>
                                    <td class="fw-bold">{{ \App\Helpers\getGrade($gradeScale, $mainTerm2Total, $term2Total) }}</td>

                                    @if(isset($data['term_3_data']) && $data['term_3_data']!=null)

                                    @foreach($data['term_3_data'][$studendId]['exam'] as $exam)
                                        @if($exam['exam'] == 'Marks Obtained')
                                            @continue
                                        @endif
                                        @php
                                            $mainTerm3Total += $exam['mark'];
                                            $term3Total += (float) ($data['term_3_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0);
                                        @endphp
                                        <td>{{ $data['term_3_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0}}</td>
                                    @endforeach
                                    <td class="fw-bold">{{ $term3Total ?? 0 }}</td>
                                    <td class="fw-bold">{{ \App\Helpers\getGrade($gradeScale, $mainTerm3Total, $term3Total) }}</td>  
                                @endif

                            @if(isset($data['term_4_data']) && $data['term_4_data']!=null)

                                    @foreach($data['term_4_data'][$studendId]['exam'] as $exam)
                                        @if($exam['exam'] == 'Marks Obtained')
                                            @continue
                                        @endif
                                        @php
                                            $mainTerm4Total += $exam['mark'];
                                            $term4Total += (float) ($data['term_4_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0);
                                        @endphp
                                        <td>{{ $data['term_4_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0}}</td>
                                    @endforeach
                                    <td class="fw-bold">{{ $term4Total ?? 0 }}</td>
                                    <td class="fw-bold">{{ \App\Helpers\getGrade($gradeScale, $mainTerm4Total ?? 0, $term4Total ) }}</td>
                                @endif

                                    <td style="color:#212529; font-weight: 500">{{ $term1Total + $term2Total + $term3Total ?? 0 + $term4Total ?? 0 }}</td>
                                    <td style="color:#212529; font-weight: 500">{{ \App\Helpers\getGrade($gradeScale, $mainTerm1Total + $mainTerm2Total + $mainTerm3Total + $mainTerm4Total, $term1Total + $term2Total + $term3Total + $term4Total) }}</td>
                                    @php
                                        $finalTotal += $term1Total + $term2Total + $term3Total + $term4Total;
                                    @endphp
                                @endforeach
                                <td>{{ $finalTotal }}</td>
                                <td>{{ \App\Helpers\getGrade($gradeScale, $mainFinalTotal, $finalTotal) }}</td>
                                <td>@if($mainFinalTotal!=0){{ number_format(($finalTotal * 100) / $mainFinalTotal, 2)  }}@else - @endif </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')

<script>
    $(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All'],
            ],
            dom: 'Bfrtip',
        });
        // $('#example thead tr').clone(true).appendTo( '#example thead' );
        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            });
        });
    });
</script>

@include('includes.footer')
