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

    $selectedStandards = old("standard_ids", !empty($template["standard_ids"]) ? explode(",", $template["standard_ids"]) : []);

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
<!-- <style>
    /* Same sidebar offset as the list screen - see show.blade.php. */
    @media (min-width: 768px) {
        #page-wrapper { margin-left: 121px; }
    }
</style> -->

<div class="row">
    <div class="col-md-4 form-group">
        <label>Email Event <span class="text-danger">*</span></label>
        <select name="event_key" id="event_key" class="form-control" {{ $isEdit ? 'disabled' : '' }} required>
            <option value="">-- Select Event --</option>
            @foreach($events as $key => $event)
                <option value="{{ $key }}" {{ (old("event_key", $template["event_key"] ?? "")) == $key ? 'selected' : '' }}>{{ $event['label'] }}</option>
            @endforeach
        </select>
        @if($isEdit)
            <input type="hidden" name="event_key" value="{{ $template['event_key'] }}">
        @endif
        <small class="text-muted">Where this layout is used when the system sends mail.</small>
    </div>

    <div class="col-md-4 form-group">
        <label>Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old("name", $template["name"] ?? "") }}" required>
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
        <input type="text" name="subject" id="subject" class="form-control" value="{{ old("subject", $template["subject"] ?? "") }}" required>
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
        <small class="text-muted">
            Scopes <em>this body text</em> only. Leave empty to use it for every {{ App\Helpers\get_string('standard','request') }}
            &mdash; the attached PDF letter is still picked per {{ App\Helpers\get_string('standard','request') }}.
        </small>
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
        <textarea name="html_content" id="html_content" class="form-control" rows="20" style="font-family:monospace;font-size:12px;" required>{{ old("html_content", $template["html_content"] ?? "") }}</textarea>
        <div id="summernote_wrap" style="display:none;">
            <textarea id="summernote"></textarea>
        </div>
    </div>
</div>

@php
    $letterTemplates = $data['letterTemplates'] ?? [];
    $attachAsPdf = (int) old('attach_as_pdf', $template['attach_as_pdf'] ?? 0);
@endphp
<div class="row">
    <div class="col-md-4 form-group">
        <label>Template Purpose</label>
        <select name="is_letter" id="is_letter" class="form-control">
            <option value="0" {{ (int) old('is_letter', $template['is_letter'] ?? 0) === 0 ? 'selected' : '' }}>Email body</option>
            <option value="1" {{ (int) old('is_letter', $template['is_letter'] ?? 0) === 1 ? 'selected' : '' }}>PDF letter only (never sent as the body)</option>
        </select>
        <small class="text-muted">
            Pick <strong>PDF letter only</strong> for a layout you import purely to attach &mdash; otherwise it competes
            to become the mail body and the attachment is skipped.
        </small>
    </div>

    <div class="col-md-4 form-group body-option">
        <label>Send Letter As PDF Attachment</label>
        <select name="attach_as_pdf" id="attach_as_pdf" class="form-control">
            <option value="0" {{ $attachAsPdf === 0 ? 'selected' : '' }}>No - the body above is the whole mail</option>
            <option value="1" {{ $attachAsPdf === 1 ? 'selected' : '' }}>Yes - attach the letter, body is the covering note</option>
        </select>
        <small class="text-muted">Use this when the body is a short note and the full letter should travel as a PDF.</small>
    </div>

    <div class="col-md-4 form-group pdf-option">
        <label>PDF Letter</label>
        <select name="pdf_template_id" class="form-control">
            <option value="">Per {{ App\Helpers\get_string('standard','request') }} (recommended)</option>
            @foreach($letterTemplates as $letter)
                <option value="{{ $letter['id'] }}" {{ (string) old('pdf_template_id', $template['pdf_template_id'] ?? '') === (string) $letter['id'] ? 'selected' : '' }}>
                    Always use: {{ $letter['name'] }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            Default attaches the letter mapped to the student's own {{ App\Helpers\get_string('standard','request') }},
            so each one keeps its own fee structure. Pick a named template only to force the same letter for everyone.
        </small>
    </div>

    <div class="col-md-4 form-group pdf-option">
        <label>PDF File Name</label>
        <input type="text" name="pdf_filename" class="form-control"
               value="{{ old('pdf_filename', $template['pdf_filename'] ?? 'Admission Confirmation.pdf') }}">
        <small class="text-muted">Placeholders work here too.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-8 form-group">
        <label>Remarks</label>
        <input type="text" name="remarks" class="form-control" value="{{ old("remarks", $template["remarks"] ?? "") }}">
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

