@extends('layout')
@section('container')
{{--
    Drag and Drop -- legacy Blade surface.

    The authoring UI and the player for this type live in the Next.js app
    (app/h5p/h5p_drag_drop/*), which is where every H5P surface is being built
    now. This view exists so the web route is not a 500 for anyone who reaches
    it through the old menu, and so the estate is visible from the Blade admin:
    it lists what is in the chapter and hands off to the app to open it.

    Deliberately not duplicated here: the canvas editor and the player. Two
    implementations of drag-and-drop scoring would drift, and the second one
    has no users.
--}}
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">Drag and drop</h4>
            <p class="text-muted mb-0" style="font-size: 13px;">
                Learners drag text or images into correct drop zones.
            </p>
        </div>
        <span class="badge bg-secondary">H5P.DragQuestion</span>
    </div>

    @if(session('data'))
        <div class="alert {{ session('data')['status'] ? 'alert-success' : 'alert-danger' }}">
            {{ session('data')['message'] ?? '' }}
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Title</th>
                        <th>Drop zones</th>
                        <th>Draggables</th>
                        <th>Pass mark</th>
                        <th>Status</th>
                        <th class="text-end">Package</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($res['dragDropLists'] ?? []) as $index => $task)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $task->title }}</td>
                            <td>{{ $task->zones->count() }}</td>
                            <td>{{ $task->elements->count() }}</td>
                            <td>{{ $task->pass_percentage }}%</td>
                            <td>
                                <span class="badge {{ $task->status === 'published' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ ucfirst($task->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('h5p_drag_drop.export', ['id' => $task->id]) }}">
                                    Export .h5p
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No drag and drop activities in this chapter yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted mt-3 mb-0" style="font-size: 12px;">
        Create, edit and preview these activities in the LMS app under
        <strong>H5P content &rarr; Drag and drop</strong>.
    </p>
</div>
@endsection
