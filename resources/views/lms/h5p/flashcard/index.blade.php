@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<style>
    /* DataTable Custom Styles */
    .dataTables_wrapper {
        padding: 20px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        margin-top: 20px;
    }

    .dataTables_length select,
    .dataTables_filter input {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 15px;
        margin: 0 5px;
    }

    .dataTables_length select:focus,
    .dataTables_filter input:focus {
        border-color: #005bea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 91, 234, 0.1);
    }

    .dataTables_info {
        padding: 15px 0;
        color: #64748b;
    }

    .dataTables_paginate {
        padding: 15px 0;
    }

    .dataTables_paginate .paginate_button {
        border-radius: 12px;
        margin: 0 3px;
        padding: 8px 15px;
    }

    .dataTables_paginate .paginate_button.current {
        background: linear-gradient(145deg, #1e3c72, #2a5298) !important;
        border: none;
        color: white !important;
    }

    /* Table Styles */
    table.dataTable {
        border-collapse: separate;
        border-spacing: 0 10px;
        width: 100%;
    }

    table.dataTable thead th {
        background: #f8fafd;
        color: #1e3c72;
        font-weight: 600;
        padding: 15px;
        border-bottom: 2px solid #e2e8f0;
    }

    table.dataTable tbody tr {
        background: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        transition: all 0.3s ease;
    }

    table.dataTable tbody tr:hover {
        box-shadow: 0 8px 25px rgba(30, 60, 114, 0.1);
        transform: translateY(-2px);
    }

    table.dataTable tbody td {
        padding: 15px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .btn-edit {
        background: linear-gradient(145deg, #10b981, #059669);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }

    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        color: white;
    }

    .btn-delete {
        background: linear-gradient(145deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-view {
        background: linear-gradient(145deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        color: white;
    }

    /* Search Header Inputs */
    .search-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
    }

    .search-input:focus {
        border-color: #005bea;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 91, 234, 0.1);
    }

    /* Image Preview */
    .scenario-thumb {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* Alert Styles */
    .alert {
        border-radius: 15px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .alert-success {
        background: linear-gradient(145deg, #d1fae5, #a7f3d0);
        color: #065f46;
    }

    .alert-danger {
        background: linear-gradient(145deg, #fee2e2, #fecaca);
        color: #991b1b;
    }

    .btn-dt {
        background: linear-gradient(145deg, #1e3c72, #2a5298);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        margin: 0 2px;
    }

    .btn-dt:hover {
        transform: translateY(-2px);
        color: white;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid mb-5">
        @if($sessionData = Session::get('data'))
        <div class="alert alert-{{ $sessionData['status'] ? 'success' : 'danger' }} alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
        </div>
        @endif

        <!-- Animated Header -->
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                   <div class="header-text">
                        <h1> {{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</h1>
                        <div class="header-badges">
                            <span class="header-badge">
                                <i class="fas fa-book-open"></i>
                                {{ App\Helpers\getTableFieldFromId('sub_std_map','display_name',$data['subject_id'],'subject_id') }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-graduation-cap"></i>
                                {{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="header-stats">
                        <i class="fas fa-cubes"></i>
                        {{ count($data['flashCards']) }} Flash Cards
                    </div>
                    <div class="header-stats">
                        <a href="{{route('h5p_flashacard.create',['chapter_id' => $data['chapter_id'],'standard_id'=>$data['standard_id'],'subject_id'=>$data['subject_id']])}}" class="text-white">
                            <i class="fas fa-plus"></i>
                            Add Cards
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scenarios Table -->
        
    </div>
</div>

@include('includes.lmsfooterJs')

<script>
    $(document).ready(function() {
        var table = $('#scenarioTable').DataTable({
            select: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                ['10', '25', '50', '100', 'Show All']
            ],
            dom: 'Bfrtip',
            buttons: [{
                    extend: 'pdfHtml5',
                    title: 'Scenarios Report',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: [0, 2, 3, 4, 5] // Exclude image and actions columns
                    },
                    customize: function(doc) {
                        doc.content[1].table.widths = ['10%', '25%', '35%', '15%', '15%'];
                    }
                },
                {
                    extend: 'csv',
                    text: ' CSV',
                    title: 'Scenarios Report',
                    exportOptions: {
                        columns: [0, 2, 3, 4, 5]
                    }
                },
                {
                    extend: 'excel',
                    text: ' EXCEL',
                    title: 'Scenarios Report',
                    exportOptions: {
                        columns: [0, 2, 3, 4, 5]
                    }
                },
                {
                    extend: 'print',
                    text: ' PRINT',
                    title: 'Scenarios Report',
                    exportOptions: {
                        columns: [0, 2, 3, 4, 5]
                    }
                },
                'pageLength'
            ],
            order: [
                [0, 'desc']
            ],
            columnDefs: [{
                    orderable: false,
                    targets: [1, 6]
                }, // Disable ordering on image and actions columns
                {
                    searchable: false,
                    targets: [1, 6]
                } // Disable search on image and actions columns
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search scenarios..."
            }
        });

        // Add search inputs to header
        $('#scenarioTable thead tr').clone(true).appendTo('#scenarioTable thead');
        $('#scenarioTable thead tr:eq(1) th').each(function(i) {
            // Skip columns where search is disabled
            if (i === 1 || i === 6) {
                $(this).html('');
                return;
            }

            var title = $(this).text();
            $(this).html('<input type="text" class="search-input" placeholder="Search ' + title + '" />');

            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table
                        .column(i)
                        .search(this.value)
                        .draw();
                }
            });
        });
    });
</script>
@include('includes.footer')
@endsection