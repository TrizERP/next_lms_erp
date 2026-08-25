@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style type="text/css">
    #daily_summary_report th,
    #daily_summary_report td {
        vertical-align: middle;
    }

    #daily_summary_report td.module-cell {
        white-space: nowrap;
        font-weight: 600;
    }

    #daily_summary_report td.sub-menu-child {
        padding-left: 32px;
    }

    #daily_summary_report td.count-cell,
    #daily_summary_report td.amount-cell {
        text-align: right;
        white-space: nowrap;
    }

    /* The count itself is the drill-down link into that module's report. */
    #daily_summary_report a.count-link {
        color: #0d6efd;
        font-weight: 600;
        text-decoration: none;
        border-bottom: 1px dashed #0d6efd;
    }

    #daily_summary_report a.count-link:hover {
        text-decoration: none;
        border-bottom-style: solid;
    }

    /* On phones the merged module cell is dropped and the module name is
       repeated above each sub-menu, so nothing needs a horizontal scroll. */
    @media (max-width: 575.98px) {
        #daily_summary_report .module-cell,
        #daily_summary_report .module-head {
            display: none;
        }

        #daily_summary_report td.sub-menu-child {
            padding-left: 24px;
        }

        #daily_summary_report .module-mobile-row td {
            background: #f2f4f7;
            font-weight: 600;
        }
    }

    @media (min-width: 576px) {
        #daily_summary_report .module-mobile-row {
            display: none;
        }
    }

    /* The print footer only exists on paper. */
    .print-footer {
        display: none;
    }

    /*
     * Paper output = the report table only. Everything else on the page
     * (top bar, side navigation, app footer) is hidden, so the browser's own
     * Print / Ctrl+P gives a clean tabular print-out without a Print button
     * on screen.
     */
    @media print {
        @page {
            margin: 12mm 10mm 20mm 10mm;
        }

        body * {
            visibility: hidden !important;
        }

        #print_area,
        #print_area *,
        .print-footer,
        .print-footer * {
            visibility: visible !important;
        }

        /* .content-main reserves ~175px on the left for the side navigation. */
        #page-wrapper,
        .content-main {
            margin: 0 !important;
            padding: 0 !important;
        }

        #print_area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        #print_area .card,
        #print_area .card-body {
            border: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .no-print {
            display: none !important;
        }

        /* The merged module layout is the printed one, never the mobile stack. */
        #daily_summary_report .module-mobile-row {
            display: none !important;
        }

        #daily_summary_report .module-cell,
        #daily_summary_report .module-head {
            display: table-cell !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        #daily_summary_report {
            width: 100% !important;
            font-size: 11px;
        }

        #daily_summary_report th,
        #daily_summary_report td {
            border: 1px solid #000 !important;
            padding: 3px 5px !important;
            color: #000 !important;
            background: transparent !important;
        }

        /* Repeat the column headings on every printed page. */
        #daily_summary_report thead {
            display: table-header-group;
        }

        #daily_summary_report tr {
            page-break-inside: avoid;
        }

        /* Links are meaningless on paper - print them as plain numbers. */
        #daily_summary_report a.count-link {
            color: #000 !important;
            border-bottom: 0 !important;
            text-decoration: none !important;
        }

        #daily_summary_report .badge {
            color: #000 !important;
            background: transparent !important;
            padding: 0 !important;
            font-size: 10px;
        }

        .print-footer {
            display: block !important;
            position: fixed;
            left: 0;
            bottom: 0;
            width: 100%;
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 10px;
            color: #000;
        }
    }
</style>

<div id="page-wrapper" class="content-main flex-fill">
    <div class="container-fluid" id="print_area">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">Daily Summary Report</h4>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 col-sm-12">
                        <strong>Institute :</strong>
                        {{ $data['institute_name'] ?? 'N/A' }}
                        <small class="text-muted">(ID: {{ $data['sub_institute_id'] }})</small>
                    </div>
                    <div class="col-md-6 col-sm-12 text-md-end">
                        <strong>Date :</strong> {{ date('d-m-Y', strtotime($data['report_date'])) }}
                        <span class="badge bg-success">Today</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="daily_summary_report" class="table table-bordered table-striped table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="module-head" style="width:50px;">#</th>
                                <th class="module-head">Module</th>
                                <th>Sub Menu</th>
                                <th class="text-end" style="width:120px;">Count</th>
                                <th class="text-end" style="width:160px;">Amount (&#8377;)</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($data['modules'] as $index => $module)
                            <tr class="module-mobile-row">
                                <td colspan="5">
                                    {{ $index + 1 }}. {{ $module['name'] }}
                                    <span class="badge bg-primary float-end">Total : {{ $module['total'] }}</span>
                                </td>
                            </tr>
                            @foreach($module['rows'] as $rowIndex => $row)
                                <tr>
                                    @if($rowIndex === 0)
                                        <td rowspan="{{ count($module['rows']) }}" class="module-cell">
                                            {{ $index + 1 }}
                                        </td>
                                        <td rowspan="{{ count($module['rows']) }}" class="module-cell module-head">
                                            <i class="fa-solid {{ $module['icon'] }} me-1"></i>
                                            {{ $module['name'] }}
                                            <div>
                                                <span class="badge bg-primary">Total : {{ $module['total'] }}</span>
                                            </div>
                                        </td>
                                    @endif
                                    <td class="{{ $row['child'] ? 'sub-menu-child' : '' }}">
                                        {{ $row['label'] }}
                                    </td>
                                    <td class="count-cell">
                                        @if($row['url'])
                                            <a href="{{ $row['url'] }}" class="count-link"
                                               target="_blank" rel="noopener noreferrer"
                                               title="Open {{ $row['label'] }} report in a new tab">{{ $row['count'] }}</a>
                                        @else
                                            {{ $row['count'] }}
                                        @endif
                                    </td>
                                    <td class="amount-cell">
                                        @if(is_null($row['amount']))
                                            &ndash;
                                        @else
                                            {{ number_format($row['amount'], 2) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-muted mt-3 mb-0 no-print">
                    <small>
                        All figures are for {{ date('d-m-Y', strtotime($data['report_date'])) }} only and are
                        restricted to this sub-institute. &quot;Total Students&quot; and &quot;Total Staff&quot;
                        are the active strength used as the base for today&#39;s attendance.
                    </small>
                </p>
            </div>
        </div>
    </div>

    {{-- Repeated at the bottom of every printed page. --}}
    <div class="print-footer">
        Printed on : <span id="printed_on">{{ $data['printed_on'] }}</span>
        &nbsp;|&nbsp;
        Printed by : {{ $data['printed_by'] ?: 'N/A' }}
    </div>
</div>

<script type="text/javascript">
    // The page may sit open for a while, so stamp the footer with the moment
    // the print is actually fired rather than when the page was rendered.
    (function () {
        function stamp() {
            var el = document.getElementById('printed_on');
            if (!el) {
                return;
            }

            var d = new Date();
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            var hours = d.getHours();
            var meridiem = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;

            el.textContent = pad(d.getDate()) + '-' + pad(d.getMonth() + 1) + '-' + d.getFullYear()
                + ' ' + pad(hours) + ':' + pad(d.getMinutes()) + ' ' + meridiem;
        }

        if (window.matchMedia) {
            window.matchMedia('print').addListener(function (mql) {
                if (mql.matches) {
                    stamp();
                }
            });
        }

        window.addEventListener('beforeprint', stamp);
    })();
</script>

@include('includes.footerJs')
@include('includes.footer')
