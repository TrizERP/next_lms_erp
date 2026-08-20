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

    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>

<div id="page-wrapper" class="content-main flex-fill">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">Daily Summary Report</h4>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 text-sm-end no-print">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print();">
                    <i class="fa-solid fa-print"></i> Print
                </button>
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
                                    <td class="count-cell">{{ $row['count'] }}</td>
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

                <p class="text-muted mt-3 mb-0">
                    <small>
                        All figures are for {{ date('d-m-Y', strtotime($data['report_date'])) }} only and are
                        restricted to this sub-institute. &quot;Total Students&quot; and &quot;Total Staff&quot;
                        are the active strength used as the base for today&#39;s attendance.
                    </small>
                </p>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
