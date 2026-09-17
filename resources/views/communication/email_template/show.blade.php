@extends('layout')
@section('container')
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
                    <div class="table-responsive">
                        <table id="example" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Sr.No.</th>
                                    <th>Source</th>
                                    <th>Event</th>
                                    <th>Template Name</th>
                                    <th>Subject</th>
                                    <th>Applies To ({{ App\Helpers\get_string('standard','request') }})</th>
                                    <th>Status Code</th>
                                    <th>Active</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php $j = 1; @endphp
                            @if(!empty($data['data']))
                                @foreach($data['data'] as $row)
                                <tr>
                                    <td>{{ $j++ }}</td>
                                    <td><span class="label label-success">Editable</span></td>
                                    <td>{{ $row['event_label'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['subject'] }}</td>
                                    <td>{{ $row['standard_ids'] ?: 'All' }}</td>
                                    <td>{{ $row['status_code'] ?: 'Any' }}</td>
                                    <td>
                                        <span class="label {{ $row['status'] ? 'label-success' : 'label-default' }}">
                                            {{ $row['status'] ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-inline">
                                            <a href="{{ route('email_template.edit', $row['id']) }}" class="btn btn-info btn-outline" title="Edit">
                                                <i class="ti-pencil-alt"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('email_template.destroy', $row['id']) }}" method="post" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Delete this email template?');" class="btn btn-info btn-outline-danger" title="Delete">
                                                <i class="ti-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            @endif

                            @if(!empty($data['legacy']))
                                @foreach($data['legacy'] as $row)
                                <tr>
                                    <td>{{ $j++ }}</td>
                                    <td>
                                        <span class="label label-warning" title="{{ $row['file'] }}">Blade File</span>
                                    </td>
                                    <td>{{ $row['event_label'] }}</td>
                                    <td>
                                        {{ basename($row['file'], '.blade.php') }}
                                        <br><small class="text-muted">{{ $row['file'] }}</small>
                                    </td>
                                    <td>{{ $row['default_subject'] }}</td>
                                    <td>{{ $row['standard_ids'] ?: 'All' }}</td>
                                    <td>{{ !empty($row['status_codes']) ? implode(', ', $row['status_codes']) : 'Any' }}</td>
                                    <td>
                                        @if($row['overridden'])
                                            <span class="label label-default" title="An editable template already covers this event">Superseded</span>
                                        @else
                                            <span class="label label-warning">In Use</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a class="btn btn-success btn-outline"
                                           title="Copy this layout into an editable template"
                                           href="{{ route('email_template.create', [
                                                'event_key'    => $row['event_key'],
                                                'standard_ids' => $row['standard_ids'],
                                                'name'         => basename($row['file'], '.blade.php'),
                                                'import'       => 1,
                                           ]) }}">
                                            <i class="ti-import"></i> Import &amp; Edit
                                        </a>
                                        <a class="btn btn-info btn-outline" target="_blank"
                                           title="Preview the current layout"
                                           href="{{ route('email_template.preview_legacy', [
                                                'event_key'    => $row['event_key'],
                                                'standard_ids' => $row['standard_ids'],
                                           ]) }}">
                                            <i class="ti-eye"></i>
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
