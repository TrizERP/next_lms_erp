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
        var currentStatus = @json(old("status_code", $template["status_code"] ?? ""));

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

    function togglePdfOptions() {
        $('.pdf-option').toggle($('#attach_as_pdf').val() === '1');
    }

    $('#attach_as_pdf').on('change', togglePdfOptions);
    togglePdfOptions();

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
            html_content: $('#html_content').val(),
            attach_as_pdf: $('#attach_as_pdf').val(),
            pdf_template_id: $('select[name="pdf_template_id"]').val(),
            pdf_filename: $('input[name="pdf_filename"]').val(),
            status_code: $('#status_code').val(),
            standard_ids: ($('select[name="standard_ids[]"]').val() || []).join(',')
        }, function (res) {
            alert(res.message);
        });
    });

    $('form.email-template-form').on('submit', syncFromEditor);
});
</script>
