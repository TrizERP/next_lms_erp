@extends('layout')
@section('container')
<style>
    :root {
        --green: #10b981;
        --yellow: #f59e0b;
        --red: #ef4444;
        --gray: #6b7280;
    }

    .mis-header {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        color: white;
        border-radius: 16px;
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    }

    .mis-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
    }

    .mis-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        border-color: #bfdbfe;
    }

    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 9999px;
        display: inline-block;
        margin-right: 6px;
    }

    .status-green { background-color: #10b981; }
    .status-yellow { background-color: #f59e0b; }
    .status-red { background-color: #ef4444; }
    .status-gray { background-color: #6b7280; }

    .status-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.15rem 0.65rem;
        border-radius: 9999px;
        letter-spacing: -.3px;
    }

    .module-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .kpi-banner {
        background: #fff;
        border-radius: 14px;
        padding: 1rem 1.5rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.03);
    }

    .filter-bar {
        background: #f8fafc;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        margin-bottom: 1.25rem;
    }

    .module-title {
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e2937;
        line-height: 1.2;
    }

    .metric-value {
        font-size: 1.65rem;
        font-weight: 700;
        line-height: 1;
        color: #0f172a;
    }

    .mini-list {
        font-size: 0.78rem;
        line-height: 1.35;
        color: #475569;
    }

    .notification-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        box-shadow: 0 0 0 2px #fff;
    }

    .overall-excellent { color: #10b981; background: #ecfdf5; border-color: #a7f3d0; }
    .overall-good { color: #f59e0b; background: #fffbeb; border-color: #fcd34d; }
    .overall-attention { color: #ef4444; background: #fef2f2; border-color: #fecaca; }

    .mis-table {
        font-size: 0.8rem;
    }

    .export-btn {
        transition: all 0.1s ease;
    }

    .export-btn:hover {
        transform: translateY(-1px);
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1rem;
    }

    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
        }
        .mis-header { padding: 1rem; border-radius: 12px; }
    }

    .section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .drill-btn {
        font-size: 0.7rem;
        padding: 0.2rem 0.6rem;
    }

    .modern-shadow {
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.07);
    }
</style>

<!-- Header -->
<div id="page-wrapper">
    <div class="container-fluid"> 
<!--        
<div class="mis-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white bg-opacity-20 rounded-xl p-2">
                <i class="bx bx-bar-chart-alt-2 fs-2"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold">MIS Daily Operational Summary</h4>
                <small class="opacity-90">For Principal, Trustee &amp; Management • Quick Decision Support</small>
            </div>
        </div>
    </div>

    <div class="text-end">
        <div class="d-flex align-items-center gap-2 justify-content-end flex-wrap">
            <div class="bg-white bg-opacity-20 px-3 py-1 rounded-lg text-sm">
                <i class="bx bx-calendar me-1"></i>
                <span id="report-datetime">{{ $reportDate }} • {{ $reportTime }}</span>
            </div>
            <div class="badge bg-white text-dark px-3 py-1 rounded-pill fw-semibold shadow-sm">
                <i class="bx bx-user me-1"></i> {{ $userRole }}
            </div>
            <button onclick="refreshDashboard()" class="btn btn-sm btn-light export-btn d-flex align-items-center gap-1 px-3">
                <i class="bx bx-refresh"></i>
                <span>Refresh</span>
            </button>
        </div>
        <div class="text-white text-opacity-75 mt-1 small">
            {{ $schoolName }} • {{ $branch }}
        </div>
    </div>
</div>
-->
<!-- Overall School Status Banner -->
<div class="kpi-banner mb-4 border-0 modern-shadow">
    <div class="row align-items-center g-3">
        <div class="col-md-5">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <div class="text-muted small fw-medium">OVERALL SCHOOL OPERATIONAL STATUS</div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h2 class="mb-0 fw-bold {{ $overallColor === 'green' ? 'text-success' : ($overallColor === 'yellow' ? 'text-warning' : 'text-danger') }}">
                            {{ $overallStatus }}
                        </h2>
                        <span class="badge fs-6 px-3 py-1 rounded-pill border {{ $overallColor === 'green' ? 'overall-excellent' : ($overallColor === 'yellow' ? 'overall-good' : 'overall-attention') }}">
                            Health Score: {{ $healthScore }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="row text-center g-2">
                <div class="col-3">
                    <div class="small text-muted">Completed</div>
                    <div class="fw-bold text-success fs-3">{{ $greenCount }}</div>
                </div>
                <div class="col-3">
                    <div class="small text-muted">Attention</div>
                    <div class="fw-bold text-warning fs-3">{{ $yellowCount }}</div>
                </div>
                <div class="col-3">
                    <div class="small text-muted">Critical</div>
                    <div class="fw-bold text-danger fs-3">{{ $redCount }}</div>
                </div>
                <div class="col-3">
                    <div class="small text-muted">Nil / No Data</div>
                    <div class="fw-bold text-secondary fs-3">{{ $grayCount }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<!-- <div class="filter-bar mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1 text-muted">Report Date</label>
            <input type="date" id="filter-date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1 text-muted">Branch</label>
            <select id="filter-branch" class="form-select form-select-sm">
                @foreach($availableBranches as $b)
                    <option value="{{ $b }}" {{ $b === $branch ? 'selected' : '' }}>{{ $b }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1 text-muted">Department</label>
            <select id="filter-dept" class="form-select form-select-sm">
                @foreach($availableDepts as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1 text-muted">Standard / Class</label>
            <select id="filter-std" class="form-select form-select-sm">
                @foreach($availableStandards as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
            <button onclick="applyFilters()" class="btn btn-primary btn-sm flex-grow-1 d-flex align-items-center justify-content-center gap-2">
                <i class="bx bx-filter-alt"></i> Apply Filters
            </button>
            <button onclick="resetFilters()" class="btn btn-outline-secondary btn-sm px-3">Reset</button>
        </div>
    </div>
</div>
-->
<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body pb-2">
                <div class="section-title"><i class="bx bx-pie-chart-alt-2 text-primary"></i> Module Status Distribution</div>
                <div id="status-pie-chart" style="min-height: 220px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body pb-2">
                <div class="section-title"><i class="bx bx-line-chart text-primary"></i> Student Attendance Trend (Last 7 Days)</div>
                <div id="attendance-trend-chart" style="min-height: 220px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Module Summary Cards -->
<div class="d-flex align-items-center justify-content-between mb-2 px-1">
    <div class="section-title mb-0">
        <i class="bx bx-grid-alt text-primary"></i> 
        MODULE-WISE SUMMARY <span class="text-muted fw-normal">({{ $totalModules }} modules)</span>
    </div>
    <div class="text-muted small d-none d-md-block">Click "View Details" for drill-down</div>
</div>

<div class="dashboard-grid" id="modules-grid">
    @foreach($modules as $key => $module)
        @php
            $statusClass = $module['status'] === 'green' ? 'success' : ($module['status'] === 'yellow' ? 'warning' : ($module['status'] === 'red' ? 'danger' : 'secondary'));
            $iconColor = $module['status'] === 'green' ? '#10b981' : ($module['status'] === 'yellow' ? '#f59e0b' : ($module['status'] === 'red' ? '#ef4444' : '#64748b'));
            $iconBg = $module['status'] === 'green' ? '#ecfdf5' : ($module['status'] === 'yellow' ? '#fffbeb' : ($module['status'] === 'red' ? '#fef2f2' : '#f1f5f9'));
        @endphp
        <div class="mis-card module-card" data-module="{{ $key }}" data-status="{{ $module['status'] }}">
            <div class="p-3 pb-2 position-relative">
                @if($module['pending_count'] > 0)
                    <div class="notification-badge">{{ $module['pending_count'] }}</div>
                @endif

                <div class="d-flex align-items-start gap-3">
                    <div class="module-icon" style="background: {{ $iconBg }}; color: {{ $iconColor }};">
                        <i class="bx bx-{{ $module['icon'] }}"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="module-title">{{ $module['title'] }}</div>
                            <span class="status-badge bg-{{ $statusClass }} text-white">{{ $module['status_label'] }}</span>
                        </div>

                        <div class="mt-1 d-flex align-items-baseline gap-2">
                            <span class="metric-value">{{ $module['count'] }}</span>
                            @if(is_numeric($module['count']) && $module['count'] > 10)
                                <span class="text-muted small">items</span>
                            @endif
                        </div>
                        <div class="text-muted small mt-0">{{ $module['summary'] }}</div>
                    </div>
                </div>

                @if(count($module['details']) > 0)
                    <div class="mini-list mt-2 pt-2 border-top">
                        @foreach(array_slice($module['details'], 0, 2) as $item)
                            @if(isset($item['name']))
                                <div>• {{ $item['name'] }} <span class="text-muted">({{ $item['time'] ?? '' }})</span></div>
                            @elseif(isset($item['student']))
                                <div>• {{ $item['student'] }}</div>
                            @elseif(isset($item['event']))
                                <div>• {{ $item['event'] }}</div>
                            @elseif(isset($item['title']))
                                <div>• {{ $item['title'] }}</div>
                            @endif
                        @endforeach
                        @if(count($module['details']) > 2)
                            <div class="text-primary small">+{{ count($module['details']) - 2 }} more...</div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="mt-auto border-top px-3 py-2 bg-light d-flex align-items-center justify-content-between" style="background: #f8fafc;">
                @if($module['has_drill'])
                    <button onclick="showDrillDown('{{ $key }}', '{{ $module['title'] }}')" 
                            class="btn btn-sm btn-outline-primary drill-btn px-2 py-1">
                        <i class="bx bx-show me-1"></i> View Details
                    </button>
                @else
                    <span class="text-success small fw-medium"><i class="bx bx-check-circle me-1"></i> No action needed</span>
                @endif

                <div class="d-flex align-items-center gap-1">
                    <span class="status-dot status-{{ $module['status'] }}"></span>
                    <span class="text-muted" style="font-size:0.7rem;">{{ ucfirst($module['status']) }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Export & Quick Actions -->
<div class="d-flex flex-wrap gap-2 mt-4 justify-content-between align-items-center">
    <div>
        <button onclick="exportToPDF()" class="btn btn-danger btn-sm export-btn d-inline-flex align-items-center gap-2 px-3">
            <i class="bx bxs-file-pdf fs-5"></i> <span>PDF</span>
        </button>
        <button onclick="exportToExcel()" class="btn btn-success btn-sm export-btn d-inline-flex align-items-center gap-2 px-3">
            <i class="bx bxs-file-excel fs-5"></i> <span>Excel</span>
        </button>
        <!--<button onclick="printDashboard()" class="btn btn-secondary btn-sm export-btn d-inline-flex align-items-center gap-2 px-3">
            <i class="bx bx-printer fs-5"></i> <span>Print</span>
        </button>-->
    </div>
    <div class="text-muted small">
        Auto-refreshes every 5 minutes • Last generated: <span id="last-generated">{{ $reportTime }}</span>
    </div>
</div>

<!-- Drill-down Modal -->
<div class="modal fade" id="drillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="drillTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="drillBody">
                <!-- Dynamic content injected by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" onclick="simulateAction()" class="btn btn-sm btn-primary">Take Action / Reply</button>
            </div>
        </div>
    </div>
</div>
</div>
</div>
@include('includes.footerJs')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// Tailwind not needed - pure modern Bootstrap + custom

let modulesData = @json($modules);
let statusDist = @json($statusDistribution);
let attendanceData = @json($attendanceTrend);

// Initialize Apex Charts
function initCharts() {
    // Status Distribution Pie/Donut
    const pieOptions = {
        series: statusDist.data,
        chart: { type: 'donut', height: 220 },
        labels: statusDist.labels,
        colors: statusDist.colors,
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: true, formatter: v => v.toFixed(0) + '%' },
        plotOptions: { pie: { donut: { size: '68%' } } },
        responsive: [{ breakpoint: 480, options: { chart: { height: 180 } } }]
    };
    new ApexCharts(document.querySelector("#status-pie-chart"), pieOptions).render();

    // Attendance Trend Line
    const trendOptions = {
        series: [{ name: 'Attendance %', data: attendanceData.data }],
        chart: { type: 'area', height: 220, toolbar: { show: false } },
        xaxis: { categories: attendanceData.labels, labels: { style: { fontSize: '11px' } } },
        colors: ['#3b82f6'],
        stroke: { curve: 'smooth', width: 3 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
        yaxis: { min: 94, max: 100, labels: { formatter: v => v + '%' } },
        tooltip: { y: { formatter: v => v + '%' } },
        grid: { strokeDashArray: 3 }
    };
    new ApexCharts(document.querySelector("#attendance-trend-chart"), trendOptions).render();
}

// Refresh timestamp + simulate minor data change
function refreshDashboard(silent = false) {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    document.getElementById('report-datetime').innerHTML = `${dateStr} • ${timeStr}`;
    document.getElementById('last-generated').innerHTML = timeStr;

    if (!silent) {
        // Simulate slight status change for demo
        const cards = document.querySelectorAll('.module-card');
        cards.forEach((card, idx) => {
            if (Math.random() > 0.85) {
                card.style.transitionDuration = '120ms';
                card.style.opacity = '0.6';
                setTimeout(() => {
                    card.style.opacity = '1';
                    if (idx % 3 === 0) {
                        const badge = card.querySelector('.status-badge');
                        if (badge) badge.classList.add('bg-success', 'text-white');
                    }
                }, 280);
            }
        });
        showToast('Dashboard refreshed with latest data');
    }
}

// Client-side filter (simple demo)
function applyFilters() {
    const dateVal = document.getElementById('filter-date').value;
    const branchVal = document.getElementById('filter-branch').value;
    const deptVal = document.getElementById('filter-dept').value;
    const stdVal = document.getElementById('filter-std').value;

    const cards = document.querySelectorAll('.module-card');
    let visible = 0;

    cards.forEach(card => {
        // In real system this would be server-side. Here we just demo visibility
        const moduleKey = card.dataset.module;
        let show = true;

        // Example filter logic on late_comers or events based on branch
        if (branchVal !== 'Main Campus' && ['late_comers', 'todays_events'].includes(moduleKey)) {
            show = Math.random() > 0.4; // simulate
        }
        if (stdVal !== 'All' && moduleKey === 'student_attendance') show = true; // always show for demo

        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    showToast(`Filters applied — ${visible} modules visible`);
}

// Reset filters
function resetFilters() {
    document.getElementById('filter-date').value = '{{ date('Y-m-d') }}';
    document.getElementById('filter-branch').value = '{{ $branch }}';
    document.getElementById('filter-dept').value = 'All';
    document.getElementById('filter-std').value = 'All';

    document.querySelectorAll('.module-card').forEach(c => c.style.display = '');
    showToast('Filters reset');
}

// Drill down modal
function showDrillDown(moduleKey, title) {
    const modalEl = document.getElementById('drillModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('drillTitle').innerHTML = title + ' — Detailed Report';

    const data = modulesData[moduleKey] || {};
    let html = `<div class="mb-2 text-muted small">Report generated at ${document.getElementById('report-datetime').innerText}</div>`;

    if (data.details && data.details.length > 0) {
        html += '<div class="table-responsive"><table class="table table-sm mis-table mb-0">';
        html += '<thead><tr class="bg-light"><th>#</th><th>Details</th><th>Status / Time</th></tr></thead><tbody>';

        data.details.forEach((item, i) => {
            html += `<tr><td>${i+1}</td><td>`;
            Object.keys(item).forEach(k => {
                html += `<strong>${k}:</strong> ${item[k]} &nbsp;&nbsp;`;
            });
            html += `</td><td><span class="badge bg-${data.status === 'green' ? 'success' : (data.status === 'yellow' ? 'warning' : 'danger')}">${data.status_label}</span></td></tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html += `<div class="alert alert-light py-2 small mb-0">No detailed records available for this module today. <br>Status: <strong>${data.status_label}</strong></div>`;
    }

    if (data.pending_count > 0) {
        html += `<div class="mt-3 p-2 bg-warning bg-opacity-10 border border-warning rounded small">⚠️ ${data.pending_count} items require immediate attention or reply.</div>`;
    }

    document.getElementById('drillBody').innerHTML = html;
    modal.show();

    // Store current module for action button
    window.currentDrillModule = moduleKey;
}

function simulateAction() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('drillModal'));
    modal.hide();
    setTimeout(() => {
        alert('Action simulated!\n\nIn production this would open approval form, send reply, mark as done, or navigate to the full module page.');
        // Optionally update the card status
        refreshDashboard(true);
    }, 180);
}

// Export PDF using jsPDF
async function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    doc.setFontSize(18);
    doc.text('MIS Daily Operational Summary', 40, 40);
    doc.setFontSize(11);
    doc.text(`School: {{ $schoolName }} | Date: ${document.getElementById('report-datetime').innerText} | Role: {{ $userRole }}`, 40, 58);

    doc.setFontSize(10);
    let y = 85;

    // Simple text summary of modules
    Object.keys(modulesData).forEach((key, idx) => {
        const m = modulesData[key];
        doc.text(`${idx + 1}. ${m.title}: ${m.status_label} — ${m.summary}`, 45, y);
        y += 18;
        if (y > 520) { doc.addPage(); y = 50; }
    });

    doc.text('Overall Status: {{ $overallStatus }} (Health: {{ $healthScore }}%)', 40, y + 20);
    doc.save(`MIS_Summary_${new Date().toISOString().slice(0,10)}.pdf`);
    showToast('PDF exported successfully');
}

// Export to Excel using SheetJS
function exportToExcel() {
    const wb = XLSX.utils.book_new();
    const rows = [['Module', 'Status', 'Count / Summary', 'Pending Count']];

    Object.keys(modulesData).forEach(key => {
        const m = modulesData[key];
        rows.push([m.title, m.status_label, m.summary, m.pending_count]);
    });

    const ws = XLSX.utils.aoa_to_sheet(rows);
    XLSX.utils.book_append_sheet(wb, ws, 'MIS Summary');
    XLSX.writeFile(wb, `MIS_Summary_${new Date().toISOString().slice(0,10)}.xlsx`);
    showToast('Excel file downloaded');
}

// Print optimized view
function printDashboard() {
    const originalTitle = document.title;
    document.title = `MIS_Summary_${new Date().toISOString().slice(0,10)}`;
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 800);
}

// Simple toast notification (no external dep)
function showToast(msg) {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#0f172a;color:#fff;padding:10px 18px;border-radius:8px;box-shadow:0 10px 15px -3px rgb(0 0 0 / 0.2);z-index:99999;font-size:0.9rem;';
    toast.innerHTML = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2600);
}

// Auto refresh every 5 minutes
function setupAutoRefresh() {
    setInterval(() => {
        refreshDashboard(true);
    }, 5 * 60 * 1000); // 300000 ms
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && document.activeElement.tagName === 'BODY') {
        e.preventDefault();
        document.getElementById('filter-date').focus();
    }
    if (e.metaKey && e.key === 'r') {
        e.preventDefault();
        refreshDashboard();
    }
});

// Initialize everything
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    setupAutoRefresh();

    // Initial subtle animation on cards
    setTimeout(() => {
        document.querySelectorAll('.mis-card').forEach((card, i) => {
            card.style.transitionDelay = (i * 18) + 'ms';
            card.style.opacity = '0.92';
            setTimeout(() => card.style.opacity = '1', 80);
        });
    }, 120);

    // Show welcome hint once
    if (!localStorage.getItem('mis_dashboard_hint')) {
        setTimeout(() => {
            showToast('Pro tip: Use filters, click View Details, or press / to focus date filter');
            localStorage.setItem('mis_dashboard_hint', '1');
        }, 5200);
    }

    // Make sure charts resize on window resize
    window.addEventListener('resize', () => {
        // Apex will auto handle via responsive config
    });

    console.log('%c[MIS Dashboard] Professional Management Information System ready — {{ $userRole }} view', 'color:#64748b');
});
</script>
@include('includes.footer')
@endsection
