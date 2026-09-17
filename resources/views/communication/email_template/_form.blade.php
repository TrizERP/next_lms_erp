@php
    $events = $data['events'] ?? [];
    $standards = $data['standards'] ?? [];
    $template = $data['data'] ?? [];
    $prefill = $data['prefill'] ?? [];
    $isEdit = !empty($template['id']);

    // Values carried over from "Import & Edit" on a blade-backed list row.
    if (!$isEdit && !empty($prefill['event_key'])) {
        $template['event_key']   = $template['event_key'] ?? $prefill['event_key'];
        $template['status_code'] = $template['status_code'] ?? ($prefill['status_code'] ?? '');
        $template['name']        = $template['name'] ?? ($prefill['name'] ?? '');
        $template['standard_ids'] = $template['standard_ids'] ?? ($prefill['standard_ids'] ?? '');
    }

    $selectedStandards = !empty($template['standard_ids']) ? explode(',', $template['standard_ids']) : [];

    // Only the bits the editor JS needs - the legacy view paths stay server side.
    $eventsForJs = [];
    foreach ($events as $eventKey => $event) {
        $eventsForJs[$eventKey] = [
            'label'           => $event['label'] ?? '',
            'default_subject' => $event['default_subject'] ?? '',
            'status_codes'    => $event['status_codes'] ?? [],
            'placeholders'    => $event['placeholders'] ?? [],
        ];
    }
@endphp

<link href="{{ asset('/plugins/bower_components/summernote/dist/summernote.css') }}" rel="stylesheet">
<style>
    /* Same sidebar offset as the list screen - see show.blade.php. */
    @media (min-width: 768px) {
        #page-wrapper { margin-left: 121px; }
    }
</style>

<div class="row">
    <div class="col-md-4 form-group">
        <label>Email Event <span class="text-danger">*</span></label>
        <select name="event_key" id="event_key" class="form-control" {{ $isEdit ? 'disabled' : '' }} required>
            <option value="">-- Select Event --</option>
            @foreach($events as $key => $event)
                <option value="{{ $key }}" {{ ($template['event_key'] ?? '') == $key ? 'selected' : '' }}>{{ $event['label'] }}</option>
            @endforeach
        </select>
        @if($isEdit)
            <input type="hidden" name="event_key" value="{{ $template['event_key'] }}">
        @endif
        <small class="text-muted">Where this layout is used when the system sends mail.</small>
    </div>

    <div class="col-md-4 form-group">
        <label>Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ $template['name'] ?? '' }}" required>
    </div>

    <div class="col-md-4 form-group">
        <label>Status Code</label>
        <select name="status_code" id="status_code" class="form-control">
            <option value="">Any</option>
        </select>
        <small class="text-muted">Sub-state of the event, e.g. C, C/A, I, NO, W/L. Leave as Any to cover all.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-8 form-group">
        <label>Subject <span class="text-danger">*</span></label>
        <input type="text" name="subject" id="subject" class="form-control" value="{{ $template['subject'] ?? '' }}" required>
    </div>

    <div class="col-md-4 form-group">
        <label>Applies To {{ App\Helpers\get_string('standard','request') }}</label>
        <select name="standard_ids[]" class="form-control" multiple size="4">
            @foreach($standards as $standard)
                <option value="{{ $standard->id }}" {{ in_array((string) $standard->id, $selectedStandards) ? 'selected' : '' }}>
                    {{ $standard->name }} {{ $standard->medium ? '(' . $standard->medium . ')' : '' }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Leave empty to use this template for every {{ App\Helpers\get_string('standard','request') }}.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-12 form-group">
        <label>Available Placeholders</label>
        <div id="placeholder_list" class="well" style="padding:10px;min-height:44px;">
            <span class="text-muted">Select an event to see its placeholders.</span>
        </div>
        <small class="text-muted">Click a placeholder to copy it, then paste it into the subject or the body.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-12 form-group">
        <label>Email Body <span class="text-danger">*</span></label>
        <div class="btn-group" style="margin-bottom:8px;">
            <button type="button" class="btn btn-default btn-sm" id="btn_import"><i class="fa fa-download"></i> Import Current Layout</button>
            <button type="button" class="btn btn-default btn-sm" id="btn_toggle_editor"><i class="fa fa-code"></i> Rich Text Editor</button>
            <button type="button" class="btn btn-default btn-sm" id="btn_preview"><i class="fa fa-eye"></i> Preview</button>
            <button type="button" class="btn btn-default btn-sm" id="btn_test"><i class="fa fa-paper-plane"></i> Send Test Mail</button>
        </div>
        <textarea name="html_content" id="html_content" class="form-control" rows="20" style="font-family:monospace;font-size:12px;" required>{{ $template['html_content'] ?? '' }}</textarea>
        <div id="summernote_wrap" style="display:none;">
            <textarea id="summernote"></textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 form-group">
        <label>Remarks</label>
        <input type="text" name="remarks" class="form-control" value="{{ $template['remarks'] ?? '' }}">
    </div>
    <div class="col-md-4 form-group">
        <label>Active</label>
        <select name="status" class="form-control">
            <option value="1" {{ ($template['status'] ?? 1) == 1 ? 'selected' : '' }}>Yes</option>
            <option value="0" {{ ($template['status'] ?? 1) == 0 ? 'selected' : '' }}>No</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <button type="submit" class="btn btn-success">{{ $isEdit ? 'Update' : 'Save' }}</button>
        <a href="{{ route('email_template.index') }}" class="btn btn-default">Cancel</a>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Email Preview</h4>
            </div>
            <div class="modal-body" style="padding:0;">
                <iframe id="preview_frame" style="width:100%;height:520px;border:0;"></iframe>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script src="{{ asset('/plugins/bower_components/summernote/dist/summernote.min.js') }}"></script>
<script>
$(function () {
    var EVENTS = @json($eventsForJs);
    var richMode = false;

    function renderEventMeta() {
        var key = $('#event_key').val();
        var event = EVENTS[key];
        var $list = $('#placeholder_list');
        var $status = $('#status_code');
        var currentStatus = @json($template['status_code'] ?? '');

        $list.empty();
        $status.empty().append('<option value="">Any</option>');

        if (!event) {
            $list.html('<span class="text-muted">Select an event to see its placeholders.</span>');
            return;
        }

        $.each(event.placeholders || {}, function (name, description) {
            var token = '<< ' + name + ' >>';
            $list.append(
                $('<button type="button" class="btn btn-xs btn-info placeholder-chip" style="margin:2px;"></button>')
                    .attr('title', description)
                    .attr('data-token', token)
                    .text(token)
            );
        });

        $.each(event.status_codes || [], function (i, code) {
            $status.append($('<option>').val(code).text(code).prop('selected', code === currentStatus));
        });

        if (!$('#subject').val()) {
            $('#subject').val(event.default_subject || '');
        }
    }

    $(document).on('click', '.placeholder-chip', function () {
        var token = $(this).data('token');
        var $tmp = $('<textarea>').val(token).appendTo('body').select();
        try { document.execCommand('copy'); } catch (e) {}
        $tmp.remove();
        $(this).addClass('btn-success').delay(600).queue(function (next) { $(this).removeClass('btn-success'); next(); });
    });

    $('#event_key').on('change', renderEventMeta);
    renderEventMeta();

    function importLegacyLayout(silent) {
        var standards = $('select[name="standard_ids[]"]').val() || [];

        return $.get('{{ route('email_template.import_legacy') }}', {
            event_key: $('#event_key').val(),
            standard_id: standards.length ? standards[0] : '',
            status_code: $('#status_code').val()
        }, function (res) {
            if (res.status != 1) {
                if (!silent) { alert(res.message); }
                return;
            }
            $('#html_content').val(res.html_content);
            if (richMode) {
                $('#summernote').summernote('code', res.html_content);
            }
            if (!$('#subject').val()) {
                $('#subject').val(res.subject);
            }
        });
    }

    function syncFromEditor() {
        if (richMode) {
            $('#html_content').val($('#summernote').summernote('code'));
        }
    }

    $('#btn_toggle_editor').on('click', function () {
        if (!richMode) {
            $('#summernote_wrap').show();
            $('#html_content').hide();
            $('#summernote').summernote({ height: 420, tabsize: 2 });
            $('#summernote').summernote('code', $('#html_content').val());
            $(this).html('<i class="fa fa-code"></i> HTML Source');
            richMode = true;
        } else {
            $('#html_content').val($('#summernote').summernote('code'));
            $('#summernote').summernote('destroy');
            $('#summernote_wrap').hide();
            $('#html_content').show();
            $(this).html('<i class="fa fa-code"></i> Rich Text Editor');
            richMode = false;
        }
    });

    $('#btn_import').on('click', function () {
        if (!$('#event_key').val()) {
            alert('Select an email event first.');
            return;
        }
        if ($('#html_content').val().trim() && !confirm('Replace the current body with the existing layout?')) {
            return;
        }
        importLegacyLayout(false);
    });

    // Arrived from "Import & Edit" in the list: pull the blade layout straight in.
    @if(!$isEdit && !empty($prefill['import']))
        importLegacyLayout(true);
    @endif

    $('#btn_preview').on('click', function () {
        syncFromEditor();
        $.post('{{ route('email_template.preview') }}', {
            _token: '{{ csrf_token() }}',
            type: 'JSON',
            event_key: $('#event_key').val(),
            subject: $('#subject').val(),
            html_content: $('#html_content').val()
        }, function (res) {
            var frame = document.getElementById('preview_frame');
            frame.srcdoc = res.html;
            $('#previewModal').modal('show');
        });
    });

    $('#btn_test').on('click', function () {
        syncFromEditor();
        var to = prompt('Send a test copy to which email address?');
        if (!to) { return; }
        $.post('{{ route('email_template.send_test') }}', {
            _token: '{{ csrf_token() }}',
            event_key: $('#event_key').val(),
            to: to,
            subject: $('#subject').val(),
            html_content: $('#html_content').val()
        }, function (res) {
            alert(res.message);
        });
    });

    $('form.email-template-form').on('submit', syncFromEditor);
});
</script>
