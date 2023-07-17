@php
    $gradeScale = \App\Helpers\getGradeScale();
    $term_1=isset(collect($data['data'])->first()['exam']) ? collect($data['data'])->first()['exam'] : [] ;
    $term_2=isset(collect($data['term_2_data'])->first()['exam']) ? collect($data['term_2_data'])->first()['exam'] : [];
    $term_3=isset(collect($data['term_3_data'])->first()['exam']) ? collect($data['term_3_data'])->first()['exam'] : [];
    $term_4=isset(collect($data['term_4_data'])->first()['exam']) ? collect($data['term_4_data'])->first()['exam'] : [];
@endphp

<table border="1">
    <thead>
    <tr>
        <td style="color:black; font-weight: bold; text-align: center" rowspan="2" colspan="2"></td>
        @if(!empty($data['data']) && !empty(collect($data['data'])->first()['mark']))
        @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count($term_1) ?? 0 + count($term_2) ?? 0 + count($term_3) ?? 0 + count($term_4) ?? 0 + 6 }}">
                {{ $subject }}
            </td>
        @endforeach
    @endif

        <td style="color:black; font-weight: bold; text-align: center" rowspan="2" colspan="3">FINAL RESULT</td>
    </tr>
    <tr>
        @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count($term_1) ?? 0 + 1 }}">{{ collect($data['data'])->first()['term'] ?? [] }}</td>
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count($term_2) ?? 0 + 1 }}">{{ collect($data['term_2_data'])->first()['term']  ?? [] }}</td>
                @if(isset(collect($data['term_3_data'])->first()['term']))
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count($term_3) ?? 0 + 1 }}">{{ collect($data['term_3_data'])->first()['term'] ?? []  }}</td>
                @endif
                @if(isset(collect($data['term_4_data'])->first()['term']))                
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count($term_4) ?? 0 + 1 }}">{{ collect($data['term_4_data'])->first()['term'] ?? []  }}</td>
                @endif
            <td style="color:black; font-weight: bold; text-align: center" colspan="2">MARKS & GRADES</td>
        @endforeach
    </tr>
    <tr>
        <td style="color:black; font-weight: bold;">ROLL NO</td>
        <td style="color:black; font-weight: bold;">STUDENT NAME</td>
        @php
            $mainFinalTotal = 0;
        @endphp
            @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
            @php
                $term1Total = $term2Total = $term3Total = $term4Total = 0;
            @endphp
            @foreach($term_1 as $exam)
                @if($exam['exam'] == 'Marks Obtained')
                    @continue
                @endif
                @php
                    $term1Total += $exam['mark'];
                @endphp
                <td style="color:black; font-weight: bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
            @endforeach
            <td class="fw-bold">Total ({{ $term1Total }})</td>
            <td class="fw-bold">Grade</td>

            @foreach($term_2 as $exam)
                @if($exam['exam'] == 'Marks Obtained')
                    @continue
                @endif
                @php
                    $term2Total += $exam['mark'];
                @endphp
                <td style="color:black; font-weight: bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
            @endforeach
            <td class="fw-bold">
                Total ({{ $term2Total }})
            </td>
            <td class="fw-bold">Grade</td>

            @foreach($term_3 as $exam)
                @if($exam['exam'] == 'Marks Obtained')
                    @continue
                @endif
                @php
                    $term3Total += $exam['mark'];
                @endphp
                <td style="color:black; font-weight: bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
            @endforeach
            <td class="fw-bold">
                Total ({{ $term3Total }})
            </td>
            <td class="fw-bold">Grade</td>

            @foreach($term_4 as $exam)
                @if($exam['exam'] == 'Marks Obtained')
                    @continue
                @endif
                @php
                    $term4Total += $exam['mark'];
                @endphp
                <td style="color:black; font-weight: bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
            @endforeach
            <td class="fw-bold">
                Total ({{ $term4Total }})
            </td>
            <td class="fw-bold">Grade</td>

            <td style="color:black; font-weight: bold">MARKS ({{ $term1Total + $term2Total }})</td>
            <td style="color:black; font-weight: bold">GRADES</td>
            @php
                $mainFinalTotal += $term1Total + $term2Total + $term3Total + $term4Total;
            @endphp
        @endforeach
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
                    <td>{{ $value[$exam['exam']] ?? 00 }}</td>
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
                @if(isset($data['term_3_data'][$studendId]['exam']))                
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
                @endif
                <td class="fw-bold">{{ $term3Total }}</td>
                <td class="fw-bold">{{ \App\Helpers\getGrade($gradeScale, $mainTerm3Total, $term3Total) }}</td>
                @if(isset($data['term_4_data'][$studendId]['exam']))                
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
                @endif
                <td class="fw-bold">{{ $term4Total }}</td>
                <td class="fw-bold">{{ \App\Helpers\getGrade($gradeScale, $mainTerm4Total, $term4Total) }}</td>

                <td style="color:#212529; font-weight: 500">{{ $term1Total + $term2Total + $term3Total + $term4Total }}</td>
                <td style="color:#212529; font-weight: 500">{{ \App\Helpers\getGrade($gradeScale, $mainTerm1Total + $mainTerm2Total + $mainTerm3Total + $mainTerm4Total, $term1Total + $term2Total + $term3Total + $term4Total) }}</td>
                @php
                    $finalTotal += $term1Total + $term2Total + $term3Total + $term4Total;
                @endphp
            @endforeach
            <td>{{ $finalTotal }}</td>
            <td>{{ \App\Helpers\getGrade($gradeScale, $mainFinalTotal, $finalTotal) }}</td>
            <td>{{ number_format(($finalTotal * 100) / $mainFinalTotal, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
