{{--
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">fees AI</h4>
            </div>
        </div>        
        <div class="card">
            @if (isset($data['status_code']))
                @if($data['status_code'] == 1)
                    <div class="alert <!-- alert -->-success alert-block">
                @else
                    <div class="alert alert-danger alert-block">
                @endif
                <button type="button" class="close" data-dismiss="alert">×</button>
                <strong>{{ $data['message'] }}</strong>
                </div>
            @endif

            @if(!empty($data['standard_data']))
                <div class="table-responsive mt-20 tz-report-table">
                    {!! App\Helpers\get_school_details() !!}
                    <table id="example" class="table">
                        <thead>
                            <tr>
                                <th class="text-center">Standard</th>
                                <th class="text-center" colspan="2">Months</th>
                            </tr>
                        </thead>
                        @foreach($data['standard_data'] as $std_name => $values)
                        <tr style="background:#ddd !important">
                            <td class="text-center" >{{$std_name}}</td>
                            <td class="text-left" colspan="2">{{$values['total_prediction']}}</td>
                        </tr>
                            @foreach($values['all_data'] as $key => $value)
                            @if($std_name == $value['standard_name'])
                            <tr>
                                <td class="border-none"></td>
                                <td>{{$value['month_name']}}</td>
                                <td>{{$value['Prediction']}}</td>     
                            </tr>
                            @endif
                            @endforeach
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
        <!-- card over  -->
    </div>
</div>
@include('includes.footerJs')
<script>
$(document).ready(function () {
        var table = $('#example').DataTable({
            select: true,
            lengthMenu: [
                [100, 500, 1000, -1],
                ['100', '500', '1000', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'pdfHtml5',
                    title: 'Fees Monthly Report',
                    orientation: 'landscape',
                    pageSize: 'LEGAL',
                    pageSize: 'A0',
                    exportOptions: {
                        columns: ':visible'
                    },
                },
                {extend: 'csv', text: ' CSV', title: 'Fees Monthly Report'},
                {extend: 'excel', text: ' EXCEL', title: 'Fees Monthly Report'},
                {extend: 'print', text: ' PRINT', title: 'Fees Monthly Report'},
                'pageLength'
            ],
        });

        $('#example thead tr:eq(1) th').each(function (i) {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function () {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search( this.value )
                        .draw();
                }
            } );
        } );
    } );
</script>
@include('includes.footer')
@endsection
--}}

@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <h4 class="page-title">Smart Reports</h4>
            </div>
        </div>

        <div class="row reports-container">
            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                <a href="https://datastudio.google.com/s/vvsJTtzEy1c" target="_blank" rel="noopener noreferrer" class="card report-card teacher-report" aria-label="Open Teacher Performance Evaluation report in a new tab">
                    <div class="report-icon-wrapper">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </div>
                    <h4 class="report-title">Teacher Performance Evaluation</h4>
                    <p class="report-description">View teacher performance metrics, evaluation summaries, and academic performance insights.</p>
                    <span class="report-link">
                        View Report
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M7 17L17 7"></path>
                            <path d="M8 7H17V16"></path>
                        </svg>
                    </span>
                </a>
            </div>

            <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                <a href="https://datastudio.google.com/s/og1f6rXztWY" target="_blank" rel="noopener noreferrer" class="card report-card daily-report" aria-label="Open Daily Report in a new tab">
                    <div class="report-icon-wrapper">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <h4 class="report-title">Daily Report</h4>
                    <p class="report-description">View daily operational reports, activities, and performance statistics.</p>
                    <span class="report-link">
                        View Report
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M7 17L17 7"></path>
                            <path d="M8 7H17V16"></path>
                        </svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.reports-container {
    margin-top: 20px;
}

.report-card {
    display: block;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    padding: 32px 24px;
    text-align: center;
    margin-bottom: 30px;
    border: 1px solid #eef2f7;
    min-height: 280px;
    color: #2c3e50;
    text-decoration: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    position: relative;
    overflow: hidden;
}

.report-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: #337ab7;
}

.daily-report::before {
    background: #5cb85c;
}

.report-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.14);
    border-color: #d6e4f0;
}

.teacher-report:hover {
    border-color: #337ab7;
}

.daily-report:hover {
    border-color: #5cb85c;
}

.report-icon-wrapper {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    background: #337ab7;
    margin: 0 auto 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 18px rgba(51, 122, 183, 0.25);
    transition: transform 0.25s ease;
}

.daily-report .report-icon-wrapper {
    background: #5cb85c;
    box-shadow: 0 8px 18px rgba(92, 184, 92, 0.25);
}

.report-card:hover .report-icon-wrapper {
    transform: scale(1.06);
}

.report-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 12px;
    color: #2c3e50;
    line-height: 1.4;
}

.report-description {
    font-size: 14px;
    line-height: 1.6;
    color: #6f7c89;
    margin: 0 auto 24px;
    min-height: 48px;
    max-width: 320px;
}

.report-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 9px 24px;
    background: #337ab7;
    color: #fff !important;
    border-radius: 30px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.25s ease, transform 0.25s ease;
}

.report-card:hover .report-link {
    transform: translateY(-1px);
}

.teacher-report .report-link:hover {
    background: #286090;
}

.daily-report .report-link {
    background: #5cb85c;
}

.daily-report .report-link:hover {
    background: #449d44;
}

@media (max-width: 767px) {
    .reports-container {
        margin-top: 15px;
    }

    .report-card {
        padding: 28px 20px;
        min-height: auto;
    }

    .report-icon-wrapper {
        width: 72px;
        height: 72px;
    }

    .report-description {
        min-height: auto;
    }
}
</style>

@include('includes.footerJs')
@include('includes.footer')
@endsection