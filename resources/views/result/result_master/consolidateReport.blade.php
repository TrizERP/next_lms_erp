@extends('layout')
@section('container')
    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">Consolidate Report</h4>
                </div>
            </div>
            <div class="card">
                <form action="{{ route('consolidate_report.create') }}">
                    @csrf
                    <div class="row">
                        {{ App\Helpers\SearchChain('4', 'single', 'grade,std,div', $data['grade_id'] ?? '', $data['standard_id'] ?? '', $data['division_id'] ?? '') }}
                        <div class="col-md-12">
                            <center>
                                <input type="submit" value="Search" class="btn btn-success">
                            </center>
                        </div>
                    </div>
                </form>
            </div>
            @if (isset($data['examMasterWise']))
                @php
                    // Calculate total columns for each exam
                    $examColspans = [];
                    foreach ($data['examMasterWise'] as $examName => $subjectVal) {
                        $totalCols = 0;
                        foreach ($subjectVal as $exams) {
                            $totalCols += count($exams) + 1; // Add 1 for total column
                        }
                        $examColspans[$examName] = $totalCols;
                    }
                @endphp
                <div class="card">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <button id="exportExcel" class="btn btn-primary float-right">
                                <i class="fa fa-file-excel-o"></i> Export to Excel
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped text-left">
                                    <thead>
                                        <tr>
                                            <th rowspan="3">Roll No</th>
                                            <th rowspan="3">Student Name</th>
                                            @foreach ($data['examMasterWise'] as $examName => $subjectVal)
                                                <th style="text-align:center;" colspan="{{ $examColspans[$examName] }}">
                                                    {{ $examName }}</th>
                                            @endforeach
                                        </tr>
                                        <tr>
                                            @foreach ($data['examMasterWise'] as $examName => $subjectVal)
                                                @foreach ($subjectVal as $subjectName => $exams)
                                                    <th colspan="{{ count($exams) + 1 }}" style="text-align:center;">
                                                        {{ $subjectName }}</th>
                                                @endforeach
                                            @endforeach
                                        </tr>
                                        <tr>
                                            @foreach ($data['examMasterWise'] as $examName => $subjectVal)
                                                @foreach ($subjectVal as $subjectName => $exams)
                                                    @foreach ($exams as $examsKey => $examsVal)
                                                        <th style="text-align:center;">{{ $examsKey }}
                                                            ({{ $examsVal->points }})
                                                        </th>
                                                    @endforeach
                                                    <th style="text-align:center;">Total</th>
                                                @endforeach
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (isset($data['studentMarks']))
                                            @foreach ($data['studentMarks'] as $stuId => $studentVal)
                                                <tr>
                                                    <td>{{ $studentVal['roll_no'] }}</td>
                                                    <td>{{ $studentVal['first_name'] . ' ' . $studentVal['middle_name'] . ' ' . $studentVal['last_name'] }}
                                                    </td>
                                                    @foreach ($data['examMasterWise'] as $examName => $subjectVal)
                                                        @foreach ($subjectVal as $subjectName => $exams)
                                                            @php
                                                                $subjectTotal = 0;
                                                            @endphp
                                                            @foreach ($exams as $examsKey => $examsVal)
                                                                @php
                                                                    $marks =
                                                                        $studentVal[$examName][$subjectName][$examsKey][
                                                                            'ob_marks'
                                                                        ] ?? 0;
                                                                    $subjectTotal +=
                                                                        is_numeric($marks) && !is_null($marks)
                                                                            ? floatval($marks)
                                                                            : 0;
                                                                @endphp
                                                                <td>
                                                                    {{ $marks }}
                                                                </td>
                                                            @endforeach
                                                            <td style="font-weight: bold;">{{ $subjectTotal }}</td>
                                                        @endforeach
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('includes.footerJs')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#grade').on('change', function() {
                $("#standard_id").prop('required', true);
            });
            $('#standard_id').on('change', function() {
                $("#division_id").prop('required', true);
            });

            // Export to Excel functionality
            $('#exportExcel').on('click', function() {
                let table = document.getElementById("example");
                let wb = XLSX.utils.table_to_book(table, {
                    sheet: "Consolidate Report",
                    raw: true,
                    display: true
                });
                XLSX.writeFile(wb, "ConsolidateReport.xlsx");
            });
        });
    </script>
    @include('includes.footer')
@endsection
