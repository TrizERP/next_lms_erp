@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">HPC Activity Transfer</h4>
            </div>
        </div>

        <div id="hpc_transfer_alert"></div>

        <div class="card p-3">
            <div class="row">
                <div class="col-md-12 form-group">
                    <label class="d-block">Transfer Type</label>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="transfer_type_standard" name="transfer_type" value="standard"
                               class="custom-control-input" checked>
                        <label class="custom-control-label" for="transfer_type_standard">Standard to Standard</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="transfer_type_sub_institute" name="transfer_type" value="sub_institute"
                               class="custom-control-input">
                        <label class="custom-control-label" for="transfer_type_sub_institute">Sub Institute to Sub Institute</label>
                    </div>
                </div>
            </div>

            {{-- Standard to Standard --}}
            <div id="panel_standard" class="row">
                <div class="col-md-4 form-group">
                    <label>Source Sub Institute</label>
                    <select id="std_source_sub_institute_id" class="form-control">
                        <option value="">Select Sub Institute</option>
                        @foreach ($data['sub_institutes'] as $subInstitute)
                            <option value="{{ $subInstitute->id }}">{{ $subInstitute->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label>Source Standard</label>
                    <select id="std_source_standard_id" class="form-control" disabled>
                        <option value="">Select Sub Institute First</option>
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label>Target Standard</label>
                    <select id="std_target_standard_id" class="form-control" disabled>
                        <option value="">Select Sub Institute First</option>
                    </select>
                </div>
            </div>

            {{-- Sub Institute to Sub Institute --}}
            <div id="panel_sub_institute" class="row" style="display:none;">
                <div class="col-md-6 form-group">
                    <label>Source Sub Institute</label>
                    <select id="si_source_sub_institute_id" class="form-control">
                        <option value="">Select Sub Institute</option>
                        @foreach ($data['sub_institutes'] as $subInstitute)
                            <option value="{{ $subInstitute->id }}">{{ $subInstitute->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Target Sub Institute</label>
                    <select id="si_target_sub_institute_id" class="form-control">
                        <option value="">Select Sub Institute</option>
                        @foreach ($data['sub_institutes'] as $subInstitute)
                            <option value="{{ $subInstitute->id }}">{{ $subInstitute->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-12 form-group">
                <center>
                    <button type="button" id="btn_preview" class="btn btn-info">
                        <i class="ti-eye"></i> Preview Data
                    </button>
                </center>
            </div>
        </div>

        <div id="hpc_loading" class="text-center p-4" style="display:none;">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Please wait...</p>
        </div>

        {{-- Preview --}}
        <div id="preview_section" class="card p-3" style="display:none;">
            <h5 class="mb-3">Preview</h5>
            <div class="row" id="preview_summary"></div>
            <div id="preview_unmatched" class="alert alert-warning" style="display:none;"></div>

            <div class="table-responsive mt-3">
                <table class="table table-bordered table-striped" id="preview_table">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Source Standard</th>
                        <th>Target Standard</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="col-md-12 form-group">
                <center>
                    <button type="button" id="btn_cancel" class="btn btn-secondary">Cancel</button>
                    <button type="button" id="btn_confirm" class="btn btn-success">Confirm Transfer</button>
                </center>
            </div>
        </div>

        {{-- Result --}}
        <div id="result_section" class="card p-3" style="display:none;">
            <h5 class="mb-3">Transfer Result</h5>
            <div class="row" id="result_summary"></div>
            <div class="alert alert-success mt-3" id="result_status_message"></div>
        </div>
    </div>
</div>

{{-- Confirmation modal --}}
<div class="modal fade" id="confirm_transfer_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm HPC Activity Transfer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                Are you sure you want to transfer the previewed HPC activity data? This action will insert new
                records and cannot be undone automatically.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" id="btn_confirm_final" class="btn btn-success">Yes, Transfer</button>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
    var previewStandardsUrl = "{{ route('hpc_activity_transfer.standards') }}";
    var previewUrl = "{{ route('hpc_activity_transfer.preview') }}";
    var transferUrl = "{{ route('hpc_activity_transfer.transfer') }}";

    function hpcShowAlert(type, message) {
        var cls = type === 'success' ? 'alert-success' : 'alert-danger';
        $('#hpc_transfer_alert').html(
            '<div class="alert ' + cls + ' alert-block">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            '<strong>' + message + '</strong></div>'
        );
    }

    function hpcLoadStandards(subInstituteId, targetSelect, placeholder) {
        targetSelect.prop('disabled', true).html('<option value="">Loading...</option>');

        if (!subInstituteId) {
            targetSelect.html('<option value="">' + placeholder + '</option>');
            return;
        }

        $.ajax({
            url: previewStandardsUrl,
            method: 'GET',
            data: {sub_institute_id: subInstituteId},
            success: function (res) {
                var options = '<option value="">Select Standard</option>';
                (res.standards || []).forEach(function (std) {
                    options += '<option value="' + std.id + '">' + std.name + '</option>';
                });
                targetSelect.html(options).prop('disabled', false);
            },
            error: function () {
                targetSelect.html('<option value="">Failed to load standards</option>');
            }
        });
    }

    $('#std_source_sub_institute_id').on('change', function () {
        hpcLoadStandards($(this).val(), $('#std_source_standard_id'), 'Select Sub Institute First');
        hpcLoadStandards($(this).val(), $('#std_target_standard_id'), 'Select Sub Institute First');
    });

    $('input[name="transfer_type"]').on('change', function () {
        var type = $('input[name="transfer_type"]:checked').val();
        $('#panel_standard').toggle(type === 'standard');
        $('#panel_sub_institute').toggle(type === 'sub_institute');
        $('#preview_section, #result_section').hide();
    });

    function hpcBuildPreviewPayload() {
        var type = $('input[name="transfer_type"]:checked').val();

        if (type === 'standard') {
            return {
                transfer_type: 'standard',
                source_sub_institute_id: $('#std_source_sub_institute_id').val(),
                source_standard_id: $('#std_source_standard_id').val(),
                target_standard_id: $('#std_target_standard_id').val()
            };
        }

        return {
            transfer_type: 'sub_institute',
            source_sub_institute_id: $('#si_source_sub_institute_id').val(),
            target_sub_institute_id: $('#si_target_sub_institute_id').val()
        };
    }

    function hpcValidatePayload(payload) {
        if (payload.transfer_type === 'standard') {
            if (!payload.source_sub_institute_id) { return 'Please select the source sub institute.'; }
            if (!payload.source_standard_id) { return 'Please select the source standard.'; }
            if (!payload.target_standard_id) { return 'Please select the target standard.'; }
            if (payload.source_standard_id === payload.target_standard_id) { return 'Source and target standard cannot be the same.'; }
        } else {
            if (!payload.source_sub_institute_id) { return 'Please select the source sub institute.'; }
            if (!payload.target_sub_institute_id) { return 'Please select the target sub institute.'; }
            if (payload.source_sub_institute_id === payload.target_sub_institute_id) { return 'Source and target sub institute cannot be the same.'; }
        }
        return null;
    }

    function hpcRenderSummary(container, groups, skillSets, activityMasters) {
        function card(title, stats) {
            return '<div class="col-md-4">' +
                '<div class="card p-3 text-center mb-2">' +
                '<h6>' + title + '</h6>' +
                '<div>Total: <b>' + stats.total + '</b></div>' +
                '<div class="text-success">New: <b>' + (stats.new !== undefined ? stats.new : stats.inserted) + '</b></div>' +
                '<div class="text-muted">Duplicate/Skipped: <b>' + (stats.duplicate !== undefined ? stats.duplicate : stats.skipped) + '</b></div>' +
                (stats.skillsets_auto_created ? '<div class="text-info">Skill Sets Auto-Created: <b>' + stats.skillsets_auto_created + '</b></div>' : '') +
                '</div></div>';
        }
        container.html(card('Activity Groups', groups) + card('Skill Sets', skillSets) + card('Activity Masters', activityMasters));
    }

    var lastPayload = null;

    $('#btn_preview').on('click', function () {
        var payload = hpcBuildPreviewPayload();
        var error = hpcValidatePayload(payload);

        if (error) {
            hpcShowAlert('error', error);
            return;
        }

        lastPayload = payload;

        $('#hpc_transfer_alert').empty();
        $('#preview_section, #result_section').hide();
        $('#hpc_loading').show();

        $.ajax({
            url: previewUrl,
            method: 'POST',
            data: payload,
            success: function (res) {
                $('#hpc_loading').hide();

                if (!res.data) {
                    hpcShowAlert('error', res.message || 'Failed to generate preview.');
                    return;
                }

                var d = res.data;
                hpcRenderSummary($('#preview_summary'), d.activity_groups, d.skill_sets, d.activity_masters);

                if (d.unmatched_standards && d.unmatched_standards.length) {
                    $('#preview_unmatched').show().text(
                        'No matching standard name found in target sub institute for: ' + d.unmatched_standards.join(', ')
                    );
                } else {
                    $('#preview_unmatched').hide();
                }

                var rows = '';
                (d.records || []).forEach(function (r) {
                    var badge = r.status === 'new' ? 'badge-success' : (r.status === 'duplicate' ? 'badge-secondary' : 'badge-warning');
                    rows += '<tr>' +
                        '<td>' + r.type + '</td>' +
                        '<td>' + r.title + '</td>' +
                        '<td>' + r.source_standard + '</td>' +
                        '<td>' + r.target_standard + '</td>' +
                        '<td><span class="badge ' + badge + '">' + r.status + '</span></td>' +
                        '</tr>';
                });
                $('#preview_table tbody').html(rows || '<tr><td colspan="5" class="text-center">No records found to transfer.</td></tr>');

                $('#preview_section').show();
            },
            error: function (xhr) {
                $('#hpc_loading').hide();
                var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to generate preview.';
                hpcShowAlert('error', message);
            }
        });
    });

    $('#btn_cancel').on('click', function () {
        $('#preview_section').hide();
    });

    $('#btn_confirm').on('click', function () {
        $('#confirm_transfer_modal').modal('show');
    });

    $('#btn_confirm_final').on('click', function () {
        if (!lastPayload) { return; }

        $('#confirm_transfer_modal').modal('hide');
        $('#preview_section, #result_section').hide();
        $('#hpc_loading').show();

        $.ajax({
            url: transferUrl,
            method: 'POST',
            data: lastPayload,
            success: function (res) {
                $('#hpc_loading').hide();

                if (!res.data) {
                    hpcShowAlert('error', res.message || 'Transfer failed.');
                    return;
                }

                var d = res.data;
                hpcRenderSummary($('#result_summary'), d.activity_groups, d.skill_sets, d.activity_masters);
                $('#result_status_message').text(res.message || 'Transfer Completed Successfully.');
                $('#result_section').show();
                hpcShowAlert('success', res.message || 'Transfer Completed Successfully.');
            },
            error: function (xhr) {
                $('#hpc_loading').hide();
                var message = (xhr.responseJSON && xhr.responseJSON.message) || 'Transfer failed.';
                hpcShowAlert('error', message);
            }
        });
    });
</script>
@endsection
