{{--
    Fees Intelligence Center - Blade View
    Based on the HTML design from public/fees_intelligence_actions.html
--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">

<style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --primary-light: #a5b4fc;
        --secondary: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --purple: #8b5cf6;
        --pink: #ec4899;
        --teal: #14b8a6;
        --orange: #f97316;
        --dark: #1e293b;
        --gray-50: #f8fafc;
        --gray-100: #f1f5f9;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e1;
        --gray-400: #94a3b8;
        --gray-500: #64748b;
        --white: #ffffff;
        --shadow: 0 1px 3px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    }

    .intelligence-container {
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Header */
    .intelligence-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border-radius: 20px;
        padding: 30px 40px;
        color: white;
        margin-bottom: 30px;
    }

    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .intelligence-header h1 { font-size: 28px; font-weight: 700; }
    .intelligence-header p { opacity: 0.9; font-size: 14px; }

    .header-stats {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .header-stat {
        background: rgba(255,255,255,0.15);
        padding: 12px 20px;
        border-radius: 12px;
        backdrop-filter: blur(10px);
    }

    .header-stat-value { font-size: 20px; font-weight: 700; }
    .header-stat-label { font-size: 11px; opacity: 0.8; text-transform: uppercase; }

    /* Navigation Tabs */
    .nav-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .nav-tab {
        padding: 10px 20px;
        background: var(--white);
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        color: var(--gray-500);
        transition: all 0.2s;
        box-shadow: var(--shadow);
    }

    .nav-tab:hover { background: var(--gray-100); }
    .nav-tab.active {
        background: var(--primary);
        color: white;
    }

    /* Section Titles */
    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-badge {
        background: var(--gray-100);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        color: var(--gray-500);
    }

    /* KPI Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }

    .kpi-card {
        background: var(--white);
        border-radius: 16px;
        padding: 20px;
        box-shadow: var(--shadow);
        border-left: 4px solid var(--primary);
    }

    .kpi-card.success { border-left-color: var(--secondary); }
    .kpi-card.warning { border-left-color: var(--warning); }
    .kpi-card.danger { border-left-color: var(--danger); }

    .kpi-icon { font-size: 32px; margin-bottom: 12px; }
    .kpi-label {
        font-size: 12px;
        color: var(--gray-500);
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .kpi-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .kpi-change {
        font-size: 12px;
        font-weight: 500;
    }

    .kpi-change.positive { color: var(--secondary); }
    .kpi-change.negative { color: var(--danger); }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--white);
        border-radius: 12px;
        padding: 16px;
        text-align: center;
        box-shadow: var(--shadow);
    }

    .stat-card.critical { background: #fee2e2; }
    .stat-card.high { background: #ffedd5; }
    .stat-card.medium { background: #fef3c7; }
    .stat-card.low { background: #dcfce7; }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .stat-card.critical .stat-value { color: #991b1b; }
    .stat-card.high .stat-value { color: #9a3412; }
    .stat-card.medium .stat-value { color: #92400e; }
    .stat-card.low .stat-value { color: #166534; }

    .stat-label {
        font-size: 11px;
        color: var(--gray-500);
        text-transform: uppercase;
    }

    /* Module Grid */
    .module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }

    .module-card {
        background: var(--white);
        border-radius: 16px;
        padding: 20px;
        box-shadow: var(--shadow);
        transition: all 0.2s;
        border-left: 4px solid var(--primary);
        cursor: pointer;
    }

    .module-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .module-card.student { border-left-color: #10b981; }
    .module-card.fees { border-left-color: #6366f1; }
    .module-card.communication { border-left-color: #22c55e; }
    .module-card.transport { border-left-color: #06b6d4; }
    .module-card.attendance { border-left-color: #3b82f6; }

    .module-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 12px;
    }

    .module-card h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .module-card p {
        font-size: 12px;
        color: var(--gray-500);
        margin-bottom: 12px;
    }

    .module-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .module-action-btn {
        padding: 4px 10px;
        background: var(--gray-100);
        border: none;
        border-radius: 6px;
        font-size: 11px;
        color: var(--gray-600);
        cursor: pointer;
        transition: all 0.2s;
    }

    .module-action-btn:hover {
        background: var(--primary);
        color: white;
    }

    /* Action Cards */
    .action-card {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
    }

    .action-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .action-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--dark);
    }

    .action-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .action-badge.critical { background: #fee2e2; color: #991b1b; }
    .action-badge.high { background: #ffedd5; color: #9a3412; }
    .action-badge.medium { background: #fef3c7; color: #92400e; }
    .action-badge.low { background: #dcfce7; color: #166534; }

    .action-description {
        font-size: 14px;
        color: var(--gray-600);
        margin-bottom: 16px;
        line-height: 1.6;
    }

    .action-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .action-meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--gray-500);
    }

    .action-modules {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .linked-module {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        background: var(--gray-100);
        border-radius: 6px;
        font-size: 11px;
        color: var(--gray-600);
    }

    /* Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: var(--primary-dark); }

    .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .btn-secondary:hover { background: var(--gray-200); }

    .btn-success { background: var(--secondary); color: white; }
    .btn-success:hover { background: #059669; }

    .btn-danger { background: var(--danger); color: white; }
    .btn-danger:hover { background: #dc2626; }

    /* Section Content */
    .section-content { display: none; }
    .section-content.active { display: block; }

    /* Intro Box */
    .intro-box {
        padding: 16px;
        border-radius: 12px;
        border-left: 4px solid;
        margin-bottom: 24px;
        font-size: 14px;
    }

    /* Charts */
    .chart-container {
        background: var(--white);
        border-radius: 16px;
        padding: 20px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--dark);
    }

    /* Data Tables */
    .data-table-container {
        background: var(--white);
        border-radius: 16px;
        padding: 20px;
        box-shadow: var(--shadow);
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--gray-200);
    }

    .data-table th {
        background: var(--gray-50);
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        color: var(--gray-500);
    }

    .data-table tbody tr:hover {
        background: var(--gray-50);
    }

    /* Loading State */
    .loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: var(--gray-400);
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid var(--gray-200);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 12px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.show { display: flex; }

    .modal-content {
        background: white;
        border-radius: 16px;
        padding: 24px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--gray-400);
    }

    /* Agent Cards */
    .agent-card {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
        border-left: 4px solid var(--primary);
    }

    .agent-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .agent-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--dark);
    }

    .agent-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--secondary);
    }

    .agent-status::before {
        content: '';
        width: 8px;
        height: 8px;
        background: var(--secondary);
        border-radius: 50%;
    }

    .agent-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
        margin: 16px 0;
    }

    .kpi-mini {
        background: var(--gray-50);
        padding: 12px;
        border-radius: 8px;
        text-align: center;
    }

    .kpi-mini-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
    }

    .kpi-mini-label {
        font-size: 10px;
        color: var(--gray-500);
        text-transform: uppercase;
    }

    /* Agent Actions */
    .agent-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
    }

    /* Workflow Cards */
    .workflow-card {
        background: var(--white);
        border-radius: 16px;
        padding: 20px;
        box-shadow: var(--shadow);
        margin-bottom: 16px;
    }

    .workflow-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .workflow-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--dark);
    }

    .workflow-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
    }

    .workflow-badge.active { background: #dcfce7; color: #166534; }
    .workflow-badge.inactive { background: #fee2e2; color: #991b1b; }

    /* Recommendations */
    .recommendation-card {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
        border-left: 4px solid var(--purple);
    }

    .recommendation-type {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .recommendation-type.strategic { background: #eef2ff; color: #4f46e5; }
    .recommendation-type.operational { background: #dcfce7; color: #166534; }
    .recommendation-type.revenue { background: #fef3c7; color: #92400e; }

    /* Progress */
    .progress-container {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }

    .progress-bar {
        height: 8px;
        background: var(--gray-200);
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: var(--primary);
        border-radius: 4px;
        transition: width 0.3s;
    }

    /* Filter Section */
    .filter-section {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
        background: var(--white);
        padding: 16px;
        border-radius: 12px;
        box-shadow: var(--shadow);
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .filter-label {
        font-size: 11px;
        color: var(--gray-500);
        text-transform: uppercase;
    }

    .filter-select {
        padding: 8px 12px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 13px;
        min-width: 150px;
    }

    /* Data Injection Section */
    .injection-section {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow);
    }

    .input-group {
        margin-bottom: 16px;
    }

    .input-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: var(--dark);
        margin-bottom: 6px;
    }

    .input-field {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: 14px;
    }

    textarea.input-field {
        resize: vertical;
        min-height: 100px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .intelligence-header { padding: 20px; }
        .header-stats { flex-direction: column; }
        .kpi-grid { grid-template-columns: 1fr; }
        .module-grid { grid-template-columns: 1fr; }
        .nav-tabs { overflow-x: auto; }
    }
</style>
<div class="intelligence-container">
    <!-- Header -->
    <header class="intelligence-header">
        <div class="header-top">
            <div>
                <h1>📊 Fees Intelligence Center</h1>
                <p>AI-Powered Fee Management & Analytics</p>
            </div>
            <div class="header-stats">
                <div class="header-stat">
                    <div class="header-stat-value" id="liveTime">{{ now()->format('H:i:s') }}</div>
                    <div class="header-stat-label">Last Updated</div>
                </div>
                <div class="header-stat">
                    <div class="header-stat-value" id="dataPoints">-</div>
                    <div class="header-stat-label">Data Points</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <button class="nav-tab active" onclick="showSection('injection')">📥 Data Injection</button>
        <button class="nav-tab" onclick="showSection('overview')">📊 Overview</button>
        <button class="nav-tab" onclick="showSection('agents')">🤖 AI Agents</button>
        <button class="nav-tab" onclick="showSection('actions')">⚡ Action Items</button>
        <button class="nav-tab" onclick="showSection('workflows')">📋 Cross-Module Workflows</button>
        <button class="nav-tab" onclick="showSection('recommendations')">💡 Recommendations</button>
    </div>

    <!-- Data Injection Section -->
    <div id="injection-section" class="section-content active">
        <div class="intro-box" style="background: #dcfce7; border-left-color: #10b981; color: #166534;">
            <strong>📥 Data Injection for Intelligence Generation</strong><br>
            Select modules, provide data sources, and let the system extract and analyze data to generate actionable intelligence.
        </div>

        <div class="injection-section">
            <!-- Module Selection -->
            <div class="input-group">
                <label class="input-label">🔍 Select Modules for Intelligence</label>
                <div class="module-grid">
                    <label class="module-card fees" style="cursor: pointer;">
                        <input type="checkbox" name="modules" value="fees_collect" checked style="margin-right: 8px;">
                        <span>💰 Fees Collection</span>
                    </label>
                    <label class="module-card fees" style="cursor: pointer;">
                        <input type="checkbox" name="modules" value="fees_cancel" style="margin-right: 8px;">
                        <span>❌ Fees Cancellation</span>
                    </label>
                    <label class="module-card fees" style="cursor: pointer;">
                        <input type="checkbox" name="modules" value="fees_breackoff" checked style="margin-right: 8px;">
                        <span>📋 Fee Breakoff</span>
                    </label>
                    <label class="module-card student" style="cursor: pointer;">
                        <input type="checkbox" name="modules" value="student" checked style="margin-right: 8px;">
                        <span>👥 Student Data</span>
                    </label>
                    <label class="module-card attendance" style="cursor: pointer;">
                        <input type="checkbox" name="modules" value="attendance" style="margin-right: 8px;">
                        <span>📊 Attendance</span>
                    </label>
                    <label class="module-card communication" style="cursor: pointer;">
                        <input type="checkbox" name="modules" value="communication" style="margin-right: 8px;">
                        <span>📱 Communication</span>
                    </label>
                </div>
            </div>

            <!-- Data Source Input -->
            <div class="input-group">
                <label class="input-label">📄 Data Source Input</label>
                <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                    <button class="nav-tab active" id="tabUrl" onclick="showDataInput('url')" style="padding: 8px 16px; font-size: 12px;">🔗 URL</button>
                    <button class="nav-tab" id="tabFile" onclick="showDataInput('file')" style="padding: 8px 16px; font-size: 12px;">📁 Upload File</button>
                </div>

                <div id="inputUrl">
                    <input type="url" id="dataUrl" class="input-field" placeholder="https://example.com/data/source" style="width: 100%;">
                    <p style="font-size: 11px; color: #64748b; margin-top: 8px;">Supported: APIs, JSON endpoints, CSV downloads</p>
                </div>

                <div id="inputFile" style="display: none;">
                    <div style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 32px; text-align: center; cursor: pointer;" onclick="document.getElementById('fileInput').click();">
                        <div style="font-size: 36px; margin-bottom: 12px;">📂</div>
                        <div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Drag & drop files here or click to browse</div>
                        <div style="font-size: 12px; color: #94a3b8;">Supported: PDF, TXT, CSV, XLSX (Max 10MB)</div>
                        <input type="file" id="fileInput" style="display: none;" accept=".pdf,.txt,.csv,.xlsx" onchange="handleFileSelect(this)">
                    </div>
                </div>
            </div>

            <!-- Required Extraction Input -->
            <div class="input-group">
                <label class="input-label">🎯 Required Extraction Input</label>
                <textarea id="requiredExtraction" class="input-field" rows="4" placeholder="E.g., Extract all details of provided data..."></textarea>
            </div>

            <!-- Intelligence Prompt -->
            <div class="input-group">
                <label class="input-label">🧠 Give Prompt Required Intelligence</label>
                <textarea id="intelligencePrompt" class="input-field" rows="5" placeholder="E.g., Generate an analysis template that identifies intelligence and suggests personalized strategies..."></textarea>
            </div>

            <!-- Progress Section -->
            <div id="generationProgress" class="progress-container" style="display: none;">
                <div class="section-title">⚡ Generating Intelligence...</div>
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px;">
                        <span>Processing data...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div id="progressBar" class="progress-fill" style="width: 0%;"></div>
                    </div>
                </div>
                <div id="processingSteps" style="font-size: 12px; color: #64748b;">
                    <div style="padding: 6px 0;"><span style="color: #10b981;">✓</span> Data validated</div>
                    <div style="padding: 6px 0;" id="stepExtract"><span style="color: #94a3b8;">○</span> Extracting data fields</div>
                    <div style="padding: 6px 0;" id="stepAnalyze"><span style="color: #94a3b8;">○</span> Analyzing patterns</div>
                    <div style="padding: 6px 0;" id="stepCorrelate"><span style="color: #94a3b8;">○</span> Cross-module correlation</div>
                    <div style="padding: 6px 0;" id="stepGenerate"><span style="color: #94a3b8;">○</span> Generating insights</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-secondary" onclick="resetInjection()">🔄 Reset</button>
                <button class="btn btn-primary" onclick="loadDataPreview()">👁️ Preview Data</button>
                <button class="btn btn-success" onclick="generateIntelligence()">🚀 Generate Intelligence →</button>
            </div>
        </div>
    </div>

    <!-- Overview Section -->
    <div id="overview-section" class="section-content">
        <!-- KPI Cards -->
        <div class="kpi-grid" id="kpiGrid">
            <div class="kpi-card">
                <div class="kpi-icon">💰</div>
                <div class="kpi-label">Total Revenue MTD</div>
                <div class="kpi-value" id="kpiTotalRevenue">₹0</div>
                <div class="kpi-change positive" id="kpiRevenueChange">Loading...</div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-icon">✅</div>
                <div class="kpi-label">Collection Rate</div>
                <div class="kpi-value" id="kpiCollectionRate">0%</div>
                <div class="kpi-change positive" id="kpiCollectionChange">Loading...</div>
            </div>
            <div class="kpi-card warning">
                <div class="kpi-icon">⏳</div>
                <div class="kpi-label">Outstanding</div>
                <div class="kpi-value" id="kpiOutstanding">₹0</div>
                <div class="kpi-change negative" id="kpiOutstandingChange">Loading...</div>
            </div>
            <div class="kpi-card danger">
                <div class="kpi-icon">⚠️</div>
                <div class="kpi-label">Defaulters</div>
                <div class="kpi-value" id="kpiDefaulters">0</div>
                <div class="kpi-change negative" id="kpiDefaultersChange">Requires attention</div>
            </div>
        </div>

        <!-- Stats Summary -->
        <h2 class="section-title">Action Summary by Priority</h2>
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card critical">
                <div class="stat-value" id="statCritical">0</div>
                <div class="stat-label">Critical Actions</div>
            </div>
            <div class="stat-card high">
                <div class="stat-value" id="statHigh">0</div>
                <div class="stat-label">High Priority</div>
            </div>
            <div class="stat-card medium">
                <div class="stat-value" id="statMedium">0</div>
                <div class="stat-label">Medium Priority</div>
            </div>
            <div class="stat-card low">
                <div class="stat-value" id="statLow">0</div>
                <div class="stat-label">Low/Backlog</div>
            </div>
        </div>

        <!-- Collection Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">📈 Fee Collection Trend</div>
                <div style="display: flex; gap: 8px;">
                    <select class="filter-select" id="periodSelect" onchange="loadCollectionData()">
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                        <option value="daily">Daily</option>
                    </select>
                </div>
            </div>
            <canvas id="collectionChart" height="100"></canvas>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title">Quick Execute Actions</h2>
        <div class="module-grid">
            <div class="module-card fees" onclick="executeAction('view-defaulters')">
                <div class="module-icon" style="background: #eef2ff;">👥</div>
                <h3>View Defaulters List</h3>
                <p>Access comprehensive list of students with outstanding fees</p>
                <div class="module-actions">
                    <button class="module-action-btn">📋 View List</button>
                    <button class="module-action-btn">📊 Analytics</button>
                    <button class="module-action-btn">📧 Notify</button>
                </div>
            </div>

            <div class="module-card communication" onclick="executeAction('send-reminders')">
                <div class="module-icon" style="background: #dcfce7;">📱</div>
                <h3>Send Payment Reminders</h3>
                <p>Automated SMS/Email reminders to parents with pending fees</p>
                <div class="module-actions">
                    <button class="module-action-btn">📱 SMS All</button>
                    <button class="module-action-btn">📧 Email All</button>
                    <button class="module-action-btn">⏰ Schedule</button>
                </div>
            </div>

            <div class="module-card student" onclick="executeAction('export-report')">
                <div class="module-icon" style="background: #fef3c7;">📥</div>
                <h3>Export Collection Report</h3>
                <p>Generate comprehensive fee collection reports for review</p>
                <div class="module-actions">
                    <button class="module-action-btn">📊 PDF</button>
                    <button class="module-action-btn">📗 Excel</button>
                    <button class="module-action-btn">📑 Summary</button>
                </div>
            </div>

            <div class="module-card transport" onclick="executeAction('transport-fee')">
                <div class="module-icon" style="background: #ecfeff;">🚌</div>
                <h3>Transport Fee Review</h3>
                <p>Analyze transport fee collection and outstanding</p>
                <div class="module-actions">
                    <button class="module-action-btn">📍 Routes</button>
                    <button class="module-action-btn">👤 Students</button>
                    <button class="module-action-btn">💵 Pending</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Agents Section -->
    <div id="agents-section" class="section-content">
        <div class="intro-box" style="background: #eef2ff; border-left-color: #6366f1; color: #3730a3;">
            <strong>🤖 Agentic AI for Fees & Finance Module</strong><br>
            Autonomous AI agents built on ScholarClone's Fees Collection, NACH, Online Payment, Fee Circular, and Petty Cash sub-modules. Each agent operates autonomously — monitoring, analyzing, and acting without manual intervention.
        </div>

        <div id="agentsList">
            <!-- Agents will be loaded dynamically -->
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading AI Agents...
            </div>
        </div>
    </div>

    <!-- Action Items Section -->
    <div id="actions-section" class="section-content">
        <h2 class="section-title">Intelligence Action Items</h2>
        <div id="actionItemsList">
            <!-- Action items will be loaded dynamically -->
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading Action Items...
            </div>
        </div>
    </div>

    <!-- Cross-Module Workflows Section -->
    <div id="workflows-section" class="section-content">
        <h2 class="section-title">Cross-Module Workflows</h2>
        <div id="workflowsList">
            <!-- Workflows will be loaded dynamically -->
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading Workflows...
            </div>
        </div>
    </div>

    <!-- Recommendations Section -->
    <div id="recommendations-section" class="section-content">
        <h2 class="section-title">AI-Powered Recommendations</h2>
        <div id="recommendationsList">
            <!-- Recommendations will be loaded dynamically -->
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading Recommendations...
            </div>
        </div>
    </div>
</div>
    </div>
</div>
<!-- Modal -->
<div class="modal" id="actionModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Action Details</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div id="modalContent">
            <!-- Dynamic content -->
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Global state
    let collectionChart = null;
    let currentData = {
        dashboardStats: {},
        collectionData: {},
        recommendations: [],
        actionItems: [],
        workflows: [],
        agents: []
    };

    // Navigation
    function showSection(section) {
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.section-content').forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(section + '-section').classList.add('active');
        
        // Load section-specific data
        switch(section) {
            case 'overview':
                loadDashboardStats();
                loadCollectionData();
                loadActionItems();
                break;
            case 'agents':
                loadAIAgents();
                break;
            case 'actions':
                loadActionItems();
                break;
            case 'workflows':
                loadCrossModuleWorkflows();
                break;
            case 'recommendations':
                loadRecommendations();
                break;
        }
    }

    // Data Input Toggle
    function showDataInput(type) {
        document.getElementById('inputUrl').style.display = type === 'url' ? 'block' : 'none';
        document.getElementById('inputFile').style.display = type === 'file' ? 'block' : 'none';
        document.getElementById('tabUrl').classList.toggle('active', type === 'url');
        document.getElementById('tabFile').classList.toggle('active', type === 'file');
    }

    // File Selection Handler
    function handleFileSelect(input) {
        const file = input.files[0];
        if (file) {
            const preview = document.getElementById('filePreview') || createFilePreview();
            preview.style.display = 'block';
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
        }
    }

    function createFilePreview() {
        const div = document.createElement('div');
        div.id = 'filePreview';
        div.style.cssText = 'margin-top: 12px; padding: 12px; background: #f1f5f9; border-radius: 8px;';
        div.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="fileIcon" style="font-size: 24px;">📄</span>
                <div>
                    <div id="fileName" style="font-size: 13px; font-weight: 600;"></div>
                    <div id="fileSize" style="font-size: 11px; color: #64748b;"></div>
                </div>
                <button onclick="clearFile()" style="margin-left: auto; background: #fee2e2; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">✕</button>
            </div>
        `;
        document.getElementById('inputFile').appendChild(div);
        return div;
    }

    function clearFile() {
        document.getElementById('fileInput').value = '';
        const preview = document.getElementById('filePreview');
        if (preview) preview.style.display = 'none';
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Load Dashboard Stats
    async function loadDashboardStats() {
        try {
            const response = await fetch('/fees/intelligence/dashboard-stats', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const result = await response.json();
            
            if (result.success) {
                currentData.dashboardStats = result.data;
                updateDashboardUI(result.data);
            }
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    function updateDashboardUI(stats) {
        document.getElementById('kpiTotalRevenue').textContent = stats.total_collected_formatted || '₹0';
        document.getElementById('kpiRevenueChange').textContent = `${stats.collected_change >= 0 ? '↑' : '↓'} ${Math.abs(stats.collected_change)}% vs last month`;
        
        document.getElementById('kpiCollectionRate').textContent = `${stats.collection_rate}%`;
        document.getElementById('kpiCollectionChange').textContent = `${stats.collection_rate_change >= 0 ? '↑' : '↓'} ${Math.abs(stats.collection_rate_change)}% improvement`;
        
        document.getElementById('kpiOutstanding').textContent = stats.outstanding_formatted || '₹0';
        document.getElementById('kpiDefaulters').textContent = stats.defaulter_count || 0;
        document.getElementById('dataPoints').textContent = (stats.total_payable || 0).toLocaleString();
    }

    // Load Collection Data
    async function loadCollectionData() {
        try {
            const period = document.getElementById('periodSelect')?.value || 'monthly';
            const response = await fetch(`/fees/intelligence/collection-data?period=${period}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const result = await response.json();
            
            if (result.success) {
                currentData.collectionData = result.data;
                updateCollectionChart(result.data);
            }
        } catch (error) {
            console.error('Error loading collection data:', error);
        }
    }

    function updateCollectionChart(data) {
        const ctx = document.getElementById('collectionChart').getContext('2d');
        
        if (collectionChart) {
            collectionChart.destroy();
        }
        
        collectionChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Fee Collection',
                    data: data.collected || [],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Load AI Agents
    async function loadAIAgents() {
        try {
            const response = await fetch('/fees/intelligence/agents', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const result = await response.json();
            
            if (result.success) {
                currentData.agents = result.data;
                renderAIAgents(result.data);
            }
        } catch (error) {
            console.error('Error loading AI agents:', error);
            document.getElementById('agentsList').innerHTML = '<div class="action-card">Error loading AI agents</div>';
        }
    }

    function renderAIAgents(agents) {
        const container = document.getElementById('agentsList');
        container.innerHTML = agents.map(agent => `
            <div class="agent-card" style="border-left-color: ${agent.priority === 'high' ? '#ef4444' : '#6366f1'};">
                <div class="agent-header">
                    <div class="agent-title">🤖 ${agent.id}: ${agent.name}</div>
                    <div style="display: flex; gap: 8px;">
                        <span class="action-badge ${agent.priority}">${agent.priority.toUpperCase()} Priority</span>
                        <span class="agent-status">${agent.status}</span>
                    </div>
                </div>
                <div class="action-description">${agent.description}</div>
                
                <h4 style="font-size: 13px; margin: 16px 0 12px;">📊 KPIs</h4>
                <div class="agent-kpis">
                    ${Object.entries(agent.kpis).map(([key, value]) => `
                        <div class="kpi-mini">
                            <div class="kpi-mini-value">${value}</div>
                            <div class="kpi-mini-label">${key}</div>
                        </div>
                    `).join('')}
                </div>
                
                <h4 style="font-size: 13px; margin: 16px 0 12px;">🔗 Linked Modules</h4>
                <div class="action-modules">
                    ${agent.modules.map(m => `<div class="linked-module"><span>💰</span> ${m}</div>`).join('')}
                </div>
                
                <div class="agent-actions">
                    ${agent.actions.map(action => `
                        <button class="btn btn-secondary" onclick="executeAgent('${agent.id}', '${action}')">${getActionLabel(action)}</button>
                    `).join('')}
                </div>
            </div>
        `).join('');
    }

    function getActionLabel(action) {
        const labels = {
            'scan': '🔍 Scan',
            'configure': '⚙️ Configure',
            'report': '📊 Report',
            'logs': '📜 Logs',
            'trigger': '🚀 Trigger',
            'preview': '👁️ Preview',
            'forecast': '📈 Forecast',
            'brief': '📄 Brief',
            'history': '📜 History'
        };
        return labels[action] || action;
    }

    // Load Action Items
    async function loadActionItems() {
        try {
            const response = await fetch('/fees/intelligence/action-items', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const result = await response.json();
            
            if (result.success) {
                currentData.actionItems = result.data;
                renderActionItems(result.data);
                updateStatsGrid(result.data);
            }
        } catch (error) {
            console.error('Error loading action items:', error);
            document.getElementById('actionItemsList').innerHTML = '<div class="action-card">Error loading action items</div>';
        }
    }

    function renderActionItems(items) {
        const container = document.getElementById('actionItemsList');
        
        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="action-card" style="text-align: center; padding: 40px;">
                    <div style="font-size: 48px; margin-bottom: 12px;">✨</div>
                    <div style="font-size: 16px; color: var(--gray-500);">No action items at this time</div>
                    <div style="font-size: 13px; color: var(--gray-400);">All caught up! Check back later for new recommendations.</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = items.map(item => `
            <div class="action-card">
                <div class="action-header">
                    <div class="action-title">${getPriorityIcon(item.priority)} ${item.title}</div>
                    <span class="action-badge ${item.priority}">P${getPriorityNumber(item.priority)} - ${item.priority.toUpperCase()}</span>
                </div>
                <div class="action-description">${item.description}</div>
                <div class="action-meta">
                    <div class="action-meta-item">📅 <strong>Deadline:</strong> ${formatDate(item.deadline)}</div>
                    <div class="action-meta-item">👤 <strong>Owner:</strong> ${item.owner}</div>
                    <div class="action-meta-item">⏱️ <strong>Effort:</strong> ${item.effort}</div>
                    <div class="action-meta-item">📊 <strong>Impact:</strong> ${item.impact}</div>
                </div>
                <div class="action-modules">
                    ${item.modules.map(m => `<div class="linked-module"><span>${getModuleIcon(m)}</span> ${formatModuleName(m)}</div>`).join('')}
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="executeActionItem('${item.id}')">🚀 Execute</button>
                    <button class="btn btn-secondary" onclick="showActionDetails('${item.id}')">📋 View Details</button>
                    <button class="btn btn-secondary" onclick="assignAction('${item.id}')">👤 Assign</button>
                </div>
            </div>
        `).join('');
    }

    function updateStatsGrid(items) {
        const counts = { critical: 0, high: 0, medium: 0, low: 0 };
        items.forEach(item => {
            if (counts.hasOwnProperty(item.priority)) {
                counts[item.priority]++;
            }
        });
        
        document.getElementById('statCritical').textContent = counts.critical;
        document.getElementById('statHigh').textContent = counts.high;
        document.getElementById('statMedium').textContent = counts.medium;
        document.getElementById('statLow').textContent = counts.low;
    }

    // Load Cross-Module Workflows
    async function loadCrossModuleWorkflows() {
        try {
            const response = await fetch('/fees/intelligence/workflows', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const result = await response.json();
            
            if (result.success) {
                currentData.workflows = result.data;
                renderWorkflows(result.data);
            }
        } catch (error) {
            console.error('Error loading workflows:', error);
            document.getElementById('workflowsList').innerHTML = '<div class="action-card">Error loading workflows</div>';
        }
    }

    function renderWorkflows(workflows) {
        const container = document.getElementById('workflowsList');
        container.innerHTML = workflows.map(wf => `
            <div class="workflow-card">
                <div class="workflow-header">
                    <div class="workflow-title">${wf.title}</div>
                    <span class="workflow-badge ${wf.status}">${wf.status}</span>
                </div>
                <div class="action-description">${wf.description}</div>
                <div class="action-meta">
                    <div class="action-meta-item">⚡ <strong>Trigger:</strong> ${wf.trigger}</div>
                    <div class="action-meta-item">📊 <strong>Executions:</strong> ${wf.execution_count}</div>
                </div>
                <div class="action-modules">
                    ${wf.modules.map(m => `<div class="linked-module"><span>${getModuleIcon(m)}</span> ${formatModuleName(m)}</div>`).join('')}
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="executeWorkflow('${wf.id}')">▶️ Run Workflow</button>
                    <button class="btn btn-secondary" onclick="viewWorkflowDetails('${wf.id}')">👁️ View</button>
                    <button class="btn btn-secondary" onclick="configureWorkflow('${wf.id}')">⚙️ Configure</button>
                </div>
            </div>
        `).join('');
    }

    // Load Recommendations
    async function loadRecommendations() {
        try {
            const response = await fetch('/fees/intelligence/recommendations', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const result = await response.json();
            
            if (result.success) {
                currentData.recommendations = result.data;
                renderRecommendations(result.data);
            }
        } catch (error) {
            console.error('Error loading recommendations:', error);
            document.getElementById('recommendationsList').innerHTML = '<div class="action-card">Error loading recommendations</div>';
        }
    }

    function renderRecommendations(recommendations) {
        const container = document.getElementById('recommendationsList');
        
        if (!recommendations || recommendations.length === 0) {
            container.innerHTML = `
                <div class="action-card" style="text-align: center; padding: 40px;">
                    <div style="font-size: 48px; margin-bottom: 12px;">💡</div>
                    <div style="font-size: 16px; color: var(--gray-500);">No recommendations available</div>
                    <div style="font-size: 13px; color: var(--gray-400);">Run intelligence generation to get personalized recommendations.</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = recommendations.map(rec => `
            <div class="recommendation-card">
                <span class="recommendation-type ${rec.type}">${rec.type.toUpperCase()}</span>
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">💡 ${rec.title}</h3>
                <div class="action-description">
                    <strong>Insight:</strong> ${rec.insight}
                    <br><br>
                    <strong>Recommendation:</strong>
                    <ul style="margin: 10px 0 10px 20px;">
                        ${rec.actions.map(a => `<li>${a}</li>`).join('')}
                    </ul>
                    <strong>Expected Impact:</strong> ${rec.expected_impact}
                </div>
                <div class="action-modules">
                    ${rec.modules.map(m => `<div class="linked-module"><span>${getModuleIcon(m)}</span> ${formatModuleName(m)}</div>`).join('')}
                </div>
                <div class="action-buttons">
                    <button class="btn btn-success" onclick="implementRecommendation('${rec.id || rec.title}')">🚀 Implement Now</button>
                    <button class="btn btn-secondary" onclick="scheduleRecommendation('${rec.id || rec.title}')">📅 Schedule</button>
                    <button class="btn btn-secondary" onclick="dismissRecommendation('${rec.id || rec.title}')">✕ Dismiss</button>
                </div>
            </div>
        `).join('');
    }

    // Generate Intelligence
    async function generateIntelligence() {
        const progressSection = document.getElementById('generationProgress');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        
        progressSection.style.display = 'block';
        
        const steps = ['stepExtract', 'stepAnalyze', 'stepCorrelate', 'stepGenerate'];
        let progress = 0;
        
        const interval = setInterval(async () => {
            progress += 5;
            progressBar.style.width = progress + '%';
            progressPercent.textContent = progress + '%';
            
            if (progress >= 25) document.getElementById('stepExtract').innerHTML = '<span style="color: #10b981;">✓</span> Extracting data fields';
            if (progress >= 50) document.getElementById('stepAnalyze').innerHTML = '<span style="color: #10b981;">✓</span> Analyzing patterns';
            if (progress >= 75) document.getElementById('stepCorrelate').innerHTML = '<span style="color: #10b981;">✓</span> Cross-module correlation';
            
            if (progress >= 100) {
                document.getElementById('stepGenerate').innerHTML = '<span style="color: #10b981;">✓</span> Generating insights';
                clearInterval(interval);
                
                try {
                    const selectedModules = Array.from(document.querySelectorAll('input[name="modules"]:checked'))
                        .map(cb => cb.value);
                    
                    const response = await fetch('/fees/intelligence/generate', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            extraction_prompt: document.getElementById('requiredExtraction').value,
                            intelligence_prompt: document.getElementById('intelligencePrompt').value,
                            selected_modules: selectedModules
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Refresh all data
                        await Promise.all([
                            loadDashboardStats(),
                            loadRecommendations(),
                            loadActionItems(),
                            loadCrossModuleWorkflows()
                        ]);
                        
                        // Switch to Overview tab
                        showSection('overview');
                        document.querySelectorAll('.nav-tab')[1].classList.add('active');
                        document.querySelectorAll('.nav-tab')[0].classList.remove('active');
                        
                        alert('Intelligence generated successfully!\n\nOverview, Agents, Actions, and Recommendations have been updated with the analyzed data.');
                    }
                } catch (error) {
                    console.error('Error generating intelligence:', error);
                    alert('Error generating intelligence. Please try again.');
                }
                
                setTimeout(() => {
                    progressSection.style.display = 'none';
                    progressBar.style.width = '0%';
                    progressPercent.textContent = '0%';
                    steps.forEach(id => {
                        document.getElementById(id).innerHTML = '<span style="color: #94a3b8;">○</span> ' + document.getElementById(id).textContent.split('✓')[1];
                    });
                }, 2000);
            }
        }, 100);
    }

    // Load Data Preview
    async function loadDataPreview() {
        const selectedModules = Array.from(document.querySelectorAll('input[name="modules"]:checked'))
            .map(cb => cb.value);
        
        if (selectedModules.length === 0) {
            alert('Please select at least one module');
            return;
        }
        
        alert(`Previewing data for modules: ${selectedModules.join(', ')}\n\nThis would show a preview of the data that will be used for intelligence generation.`);
    }

    // Reset Injection
    function resetInjection() {
        if (confirm('Reset all data injection settings?')) {
            document.getElementById('requiredExtraction').value = '';
            document.getElementById('intelligencePrompt').value = '';
            document.getElementById('dataUrl').value = '';
            clearFile();
            document.querySelectorAll('input[name="modules"]').forEach(cb => cb.checked = false);
        }
    }

    // Execute Actions
    function executeAction(action) {
        console.log('Executing action:', action);
        alert('Executing action: ' + action + '\n\nThis will navigate to the relevant module.');
    }

    async function executeAgent(agentId, action) {
        try {
            const response = await fetch('/fees/intelligence/agent-execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ agent_id: agentId, action: action })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(`${agentId} - ${action.toUpperCase()}\n\n${result.data.message || 'Action completed successfully!'}`);
                
                if (result.data.redirect) {
                    window.location.href = result.data.redirect;
                }
            }
        } catch (error) {
            console.error('Error executing agent:', error);
            alert('Error executing agent action. Please try again.');
        }
    }

    function executeWorkflow(workflowId) {
        alert('Starting Workflow: ' + workflowId + '\n\nThis will initiate the cross-module workflow.');
    }

    function viewWorkflowDetails(workflowId) {
        alert('Viewing details for workflow: ' + workflowId);
    }

    function configureWorkflow(workflowId) {
        alert('Configuring workflow: ' + workflowId);
    }

    function executeActionItem(itemId) {
        alert('Executing action item: ' + itemId);
    }

    function showActionDetails(itemId) {
        const item = currentData.actionItems.find(i => i.id === itemId);
        if (item) {
            document.getElementById('modalContent').innerHTML = `
                <h3>${item.title}</h3>
                <p>${item.description}</p>
                <hr style="margin: 16px 0;">
                <p><strong>Priority:</strong> ${item.priority}</p>
                <p><strong>Deadline:</strong> ${formatDate(item.deadline)}</p>
                <p><strong>Owner:</strong> ${item.owner}</p>
                <p><strong>Effort:</strong> ${item.effort}</p>
                <p><strong>Impact:</strong> ${item.impact}</p>
            `;
            document.getElementById('actionModal').classList.add('show');
        }
    }

    function assignAction(itemId) {
        alert('Assigning action: ' + itemId);
    }

    function implementRecommendation(recId) {
        alert('Implementing recommendation: ' + recId);
    }

    function scheduleRecommendation(recId) {
        alert('Scheduling recommendation: ' + recId);
    }

    function dismissRecommendation(recId) {
        if (confirm('Dismiss this recommendation?')) {
            currentData.recommendations = currentData.recommendations.filter(r => (r.id || r.title) !== recId);
            renderRecommendations(currentData.recommendations);
        }
    }

    function closeModal() {
        document.getElementById('actionModal').classList.remove('show');
    }

    // Utility Functions
    function getPriorityIcon(priority) {
        const icons = { critical: '🔴', high: '🟠', medium: '🟡', low: '🟢' };
        return icons[priority] || '⚪';
    }

    function getPriorityNumber(priority) {
        const numbers = { critical: 1, high: 2, medium: 3, low: 4 };
        return numbers[priority] || 5;
    }

    function getModuleIcon(module) {
        const icons = {
            fees: '💰', student: '👥', attendance: '📊',
            communication: '📱', transport: '🚌', it: '💻'
        };
        return icons[module] || '📋';
    }

    function formatModuleName(module) {
        const names = {
            fees: 'Fees Management',
            student: 'Student Management',
            attendance: 'Attendance',
            communication: 'Communication',
            transport: 'Transport',
            it: 'IT/System'
        };
        return names[module] || module;
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Update time every second
        setInterval(() => {
            const timeEl = document.getElementById('liveTime');
            if (timeEl) {
                timeEl.textContent = new Date().toLocaleTimeString();
            }
        }, 1000);
        
        // Close modal on outside click
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        // Load initial data
        loadDashboardStats();
    });
</script>
@include('includes.footer')
@endsection
