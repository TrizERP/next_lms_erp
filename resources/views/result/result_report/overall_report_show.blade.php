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
                            <td class="text-center fw-bold" rowspan="2" colspan="2">VI-A</td>
                            @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
                                <td class="text-center"
                                    colspan="{{ count(collect($data['data'])->first()['exam']) + count(collect($data['term_2_data'])->first()['exam'])  }}">{{ $subject }}</td>
                            @endforeach
                            <td class="text-center fw-bold" rowspan="2" colspan="3">FINAL RESULT</td>
                        </tr>
                        <tr>
                            @foreach(collect($data['data'])->first()['mark'] as $subject => $value)
                                <td class="text-center fw-bold"
                                    colspan="{{ count(collect($data['data'])->first()['exam']) - 1 }}">{{ collect($data['data'])->first()['term'] }}</td>
                                <td class="text-center fw-bold"
                                    colspan="{{ count(collect($data['term_2_data'])->first()['exam']) - 1 }}">{{ collect($data['term_2_data'])->first()['term'] }}</td>
                                <td class="text-center fw-bold" colspan="2">MARKS & GRADES</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="fw-bold">ROLL NO</td>
                            <td class="fw-bold">STUDENT NAME</td>
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
                                    <td class="fw-bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
                                @endforeach
                                @foreach(collect($data['term_2_data'])->first()['exam'] as $exam)
                                    @if($exam['exam'] == 'Marks Obtained')
                                        @continue
                                    @endif
                                    @php
                                        $totalMark += $exam['mark'];
                                    @endphp
                                    <td class="fw-bold">{{ $exam['exam'] }} ({{ $exam['mark'] }})</td>
                                @endforeach
                                <td class="fw-bold">MARKS ({{ $totalMark }})</td>
                                <td class="fw-bold">GRADES</td>
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
