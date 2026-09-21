@extends('layout')
@section('container')
{{--
    True or false -- legacy Blade surface.

    The authoring UI and the player for this type live in the Next.js app
    (app/h5p/h5p_true_false/*), which is where every H5P surface is being built
    now. This view exists so the web route is not a 500 for anyone who reaches
    it through the old menu, and so the estate is visible from the Blade admin:
    it lists what is in the chapter and hands off to the app to open it.

    Deliberately not duplicated here: the editor and the player.
--}}
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">True or false</h4>
            <p class="text-muted mb-0" style="font-size: 13px;">
                True or false statements with automated scoring and feedback.
            </p>
        </div>
        <span class="badge bg-secondary">H5P.TrueFalse</span>
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
                        <th>Statements</th>
                        <th>Status</th>
                        <th style="width: 140px;">Package</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($trueFalseLists ?? []) as $i => $row)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td>{{ $row['title'] ?? 'Untitled' }}</td>
                            <td class="text-muted">{{ count($row['questions'] ?? []) }}</td>
                            <td>
                                <span class="badge {{ ($row['status'] ?? 'draft') === 'published' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ ($row['status'] ?? 'draft') === 'published' ? 'Published' : 'Draft' }}
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('h5p_true_false.export', ['id' => $row['id']]) }}">
                                    Export .h5p
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Nothing authored for this chapter yet. Create it in the LMS app under
                                <strong>H5P content &rarr; True or false</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
