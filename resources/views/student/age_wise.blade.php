@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<style>
    .report-container { margin:20px 0; }
    .report-header { text-align:center;margin-bottom:20px;padding:15px;background:linear-gradient(45deg,#f8f9fa,#e9ecef);border-radius:5px; }
    .report-table { width:100%;border-collapse:collapse;margin-bottom:20px;box-shadow:0 0 10px rgba(0,0,0,0.1); }
    .report-table th,.report-table td { border:1px solid #dee2e6;padding:12px;text-align:center;vertical-align:middle;font-size:14px; }
    .age-column { font-weight:600;background:#f8f9fa; }
    .total-row { font-weight:600;background:#e9ecef; }
    .grand-total { text-align:right;font-weight:600;margin-top:15px;padding:10px 15px;background:#25bdea;color:white;border-radius:5px; }
    .filter-form { margin-bottom:30px;padding:20px;background:#f8f9fa;border-radius:8px;border:1px solid #dee2e6; }
    .btn-action-group { margin-top:15px; }
    .table-responsive { border-radius:8px;overflow-x:auto; }
</style>

<div id="page-wrapper" class="content-main flex-fill">
    <div class="container-fluid">
        <div class="report-container">
            <form method="GET" action="{{ route('ageWise.index') }}">
                <div class="filter-form">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <div class="row">
                                {{ App\Helpers\SearchChain('4','single','grade',$data['grade_id'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="btn-action-group">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate Report</button>
                                <button type="button" class="btn btn-success export-btn" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export to Excel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            @if(!empty($data['report']) && count($data['report']) > 0)
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="age-column" style="width:120px;background:#25bdea;">Age</th>
                            @foreach($data['classes'] as $class)
                                <th colspan="2" style="background:#25bdea;">
                                    @if($data['medium'] == 'KG')
                                        {{ $class }}
                                    @else
                                        Class {{ $class }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($data['classes'] as $class)
                                <th style="background:#25bdea;">M</th>
                                <th style="background:#25bdea;">F</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['report'] as $age => $byClass)
                            <tr>
                                <td class="age-column">{{ $age }}</td>
                                @foreach($data['classes'] as $className)
                                    <td>{{ $byClass[$className]['M'] ?? 0 }}</td>
                                    <td>{{ $byClass[$className]['F'] ?? 0 }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>Total</strong></td>
                            @foreach($data['classes'] as $className)
                                @php
                                    $sumM = $sumF = 0;
                                    foreach($data['report'] as $ageData) {
                                        $sumM += $ageData[$className]['M'] ?? 0;
                                        $sumF += $ageData[$className]['F'] ?? 0;
                                    }
                                @endphp
                                <td><strong>{{ $sumM }}</strong></td>
                                <td><strong>{{ $sumF }}</strong></td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="grand-total"><i class="fas fa-users"></i> Total Students = {{ $data['grandTotal'] }}</div>
            @else
            <div class="alert alert-info text-center"><i class="fas fa-info-circle"></i> No data found for the selected criteria.</div>
            @endif
        </div>
    </div>
</div>
