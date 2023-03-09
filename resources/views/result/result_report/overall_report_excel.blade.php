@php
    $gradeScale = \App\Helpers\getGradeScale();
@endphp

<table border="1">
    <thead>
    <tr>
        <td style="color:black; font-weight: bold; text-align: center" rowspan="2" colspan="2">VI-A</td>
        @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count(collect($data['data'])->first()['exam']) + count(collect($data['term_2_data'])->first()['exam'])  }}">{{ $subject }}</td>
        @endforeach
        <td style="color:black; font-weight: bold; text-align: center" rowspan="2" colspan="3">FINAL RESULT</td>
    </tr>
    <tr>
        @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count(collect($data['data'])->first()['exam']) - 1 }}">{{ collect($data['data'])->first()['term'] }}</td>
            <td style="color:black; font-weight: bold; text-align: center"
                colspan="{{ count(collect($data['term_2_data'])->first()['exam']) - 1 }}">{{ collect($data['term_2_data'])->first()['term'] }}</td>
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
                $totalMark = 0;
            @endphp
            @foreach(collect($data['data'])->first()['exam'] as $exam)
                @if($exam['exam'] == 'Marks Obtained')
                    @continue
                @endif
                @php
                    $totalMark += $exam['mark'];
                @endphp
                <td style="color:black; font-weight: bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
            @endforeach
            @foreach(collect($data['term_2_data'])->first()['exam'] as $exam)
                @if($exam['exam'] == 'Marks Obtained')
                    @continue
                @endif
                @php
                    $totalMark += $exam['mark'];
                @endphp
                <td style="color:black; font-weight: bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
            @endforeach
            <td style="color:black; font-weight: bold">MARKS ({{ $totalMark }})</td>
            <td style="color:black; font-weight: bold">GRADES</td>
            @php
                $mainFinalTotal += $totalMark;
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
                    $totalGainMark = 0;
                    $totalMark = 0;
                @endphp

                @foreach($sdata['exam'] as $exam)
                    @if($exam['exam'] == 'Marks Obtained')
                        @continue
                    @endif
                    @php
                        $totalGainMark += (float) ($value[$exam['exam']] ?? 0);
                        $totalMark += $exam['mark'];
                    @endphp
                    <td>{{ $value[$exam['exam']] ?? 00 }}</td>
                @endforeach

                @foreach($data['term_2_data'][$studendId]['exam'] as $exam)
                    @if($exam['exam'] == 'Marks Obtained')
                        @continue
                    @endif
                    @php
                        $totalGainMark += (float) ($data['term_2_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0);
                        $totalMark += $exam['mark'];
                    @endphp
                    <td>{{ $data['term_2_data'][$studendId]['mark'][$subject][$exam['exam']] ?? 0}}</td>
                @endforeach

                <td style="color:#212529; font-weight: 500">{{ $totalGainMark }}</td>
                <td style="color:#212529; font-weight: 500">{{ \App\Helpers\getGrade($gradeScale, $totalMark, $totalGainMark) }}</td>
                @php
                    $finalTotal += $totalGainMark;
                @endphp
            @endforeach
            <td>{{ $finalTotal }}</td>
            <td>{{ \App\Helpers\getGrade($gradeScale, $mainFinalTotal, $finalTotal) }}</td>
            <td>{{ number_format(($finalTotal * 100) / $mainFinalTotal, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
