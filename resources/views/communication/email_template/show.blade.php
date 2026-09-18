@extends('layout')
@section('container')
<style>
    /* .left-sidebar is position:fixed (100px block + 20px padding + 1px border)
       and #content carries no left offset, so page content would start at x=0
       underneath it. Dropped below 768px, where the sidebar stops being fixed. */
    @media (min-width: 768px) {
        #page-wrapper { margin-left: 121px; }
    }

    /* Every column is a percentage: mixing px widths with percentages made the
       table wider than its container and pushed the page under the sidebar. */
    .email-template-wrap { width: 100%; overflow-x: auto; }
    #email_template_table { table-layout: fixed; width: 100%; max-width: 100%; }
    #email_template_table td, #email_template_table th { vertical-align: middle; word-wrap: break-word; overflow-wrap: anywhere; }
    #email_template_table .col-sr      { width: 4%; }
    #email_template_table .col-event   { width: 17%; }
    #email_template_table .col-name    { width: 23%; }
    #email_template_table .col-subject { width: 17%; }
    #email_template_table .col-std     { width: 13%; }
    #email_template_table .col-status  { width: 9%; }
    #email_template_table .col-state   { width: 8%; }
    #email_template_table .col-action  { width: 9%; white-space: nowrap; }
    #email_template_table .tpl-file    { color: #98a6ad; font-size: 11px; display: block; margin-top: 2px; }
    #email_template_table .btn-outline { margin-right: 3px; padding: 4px 8px; }
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                <h4 class="page-title">Email Templates</h4>
            </div>
        </div>
        <div class="card">
            @if ($sessionData = Session::get('data'))
            <div class="alert {{ ($sessionData['status_code'] ?? 1) == 1 ? 'alert-success' : 'alert-danger' }} alert-block">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>{{ $sessionData['message'] }}</strong>
            </div>
            @endif
            <div class="row">
                <div class="col-lg-6 col-sm-6 col-xs-6">
                    <a href="{{ route('email_template.create') }}" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New Template</a>
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="table-responsive email-template-wrap">
                        <table id="email_template_table" class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="col-sr">#</th>
                                    <th class="col-event">Event</th>
                                    <th class="col-name">Template</th>
                                    <th class="col-subject">Subject</th>
                                    <th class="col-std">{{ App\Helpers\get_string('standard','request') }}</th>
                                    <th class="col-status">Status Code</th>
                                    <th class="col-state">State</th>
                                    <th class="col-action">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $j = 1;

                                // Long lists blow the column out; show the first
                                // few names and keep the rest in the tooltip.
                                $shortStandards = function (array $names) {
                                    if (empty($names)) {
                                        return 'All';
                                    }
                                    if (count($names) <= 2) {
                                        return implode(', ', $names);
                                    }

                                    return implode(', ', array_slice($names, 0, 2)) . ' +' . (count($names) - 2) . ' more';
                                };
                            @endphp

                            @if(!empty($data['data']))
                                @foreach($data['data'] as $row)
                                <tr>
                                    <td>{{ $j++ }}</td>
                                    <td>{{ $row['event_label'] }}</td>
                                    <td>
                                        <span class="label label-success">Editable</span>
                                        @if(!empty($row['attach_as_pdf']))
                                            <span class="label label-info" title="The letter is attached as a PDF">PDF</span>
                                        @endif
                                        {{ $row['name'] }}
                                    </td>
                                    <td>{{ $row['subject'] }}</td>
                                    <td title="{{ !empty($row['standard_names']) ? implode(', ', $row['standard_names']) : 'All' }}">
                                        {{ $shortStandards($row['standard_names'] ?? []) }}
                                        @if(!empty($row['attach_as_pdf']) && empty($row['pdf_template_id']))
                                            <small class="tpl-file">PDF letter: per {{ App\Helpers\get_string('standard','request') }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $row['status_code'] ?: 'Any' }}</td>
                                    <td>
                                        <span class="label {{ $row['status'] ? 'label-success' : 'label-default' }}">
                                            {{ $row['status'] ? 'Active' : 'Off' }}
                                        </span>
                                    </td>
                                    <td class="col-action">
                                        <a href="{{ route('email_template.edit', $row['id']) }}" class="btn btn-info btn-outline" title="Edit">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <form action="{{ route('email_template.destroy', $row['id']) }}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Delete this email template?');" class="btn btn-info btn-outline-danger" title="Delete">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            @endif

                            @if(!empty($data['legacy']))
                                @foreach($data['legacy'] as $row)
                                @php $fileName = basename($row['file'], '.blade.php'); @endphp
                                <tr>
                                    <td>{{ $j++ }}</td>
                                    <td>{{ $row['event_label'] }}</td>
                                    <td>
                                        <span class="label label-warning">Blade</span>
                                        {{ $fileName }}
                                        <small class="tpl-file" title="{{ $row['file'] }}">{{ \Illuminate\Support\Str::after($row['file'], 'resources/views/') }}</small>
                                    </td>
                                    <td>{{ $row['default_subject'] }}</td>
                                    <td title="{{ !empty($row['standard_names']) ? implode(', ', $row['standard_names']) : 'All' }}">{{ $shortStandards($row['standard_names'] ?? []) }}</td>
                                    <td>{{ !empty($row['status_codes']) ? implode(', ', $row['status_codes']) : 'Any' }}</td>
                                    <td>
                                        @if($row['overridden'])
                                            <span class="label label-default" title="An editable template already covers this event">Superseded</span>
                                        @else
                                            <span class="label label-warning">In Use</span>
                                        @endif
                                    </td>
                                    <td class="col-action">
                                        <a class="btn btn-success btn-outline"
                                           title="Copy this layout into an editable template"
                                           href="{{ route('email_template.create', [
                                                'event_key'    => $row['event_key'],
                                                'standard_ids' => $row['standard_ids'],
                                                'name'         => $fileName,
                                                'import'       => 1,
                                           ]) }}">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <a class="btn btn-info btn-outline" target="_blank"
                                           title="Preview the current layout"
                                           href="{{ route('email_template.preview_legacy', [
                                                'event_key'    => $row['event_key'],
                                                'standard_ids' => $row['standard_ids'],
                                           ]) }}">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
