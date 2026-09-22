@extends('layout')
@section('container')
{{--
    Image hotspots -- legacy Blade surface.

    The authoring UI and the player for this type live in the Next.js app
    (app/h5p/h5p_image_hotspots/*), which is where every H5P surface is being built
    now. This view exists so the web route is not a 500 for anyone who reaches
    it through the old menu, and so the estate is visible from the Blade admin:
    it lists what is in the chapter and hands off to the app to open it.

    Deliberately not duplicated here: the editor and the player.
--}}
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">Image hotspots</h4>
            <p class="text-muted mb-0" style="font-size: 13px;">
                Learners explore an image by opening hotspots that reveal text, pictures or rich content.
            </p>
        </div>
        <span class="badge bg-secondary">H5P.ImageHotspots</span>
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
                        <th>Hotspots</th>
                        <th>Status</th>
                        <th style="width: 140px;">Package</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($imageHotspotsLists ?? []) as $i => $row)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td>{{ $row['title'] ?? 'Untitled' }}</td>
                            <td class="text-muted">{{ count($row['points'] ?? []) }}</td>
                            <td>
                                <span class="badge {{ ($row['status'] ?? 'draft') === 'published' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ ($row['status'] ?? 'draft') === 'published' ? 'Published' : 'Draft' }}
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('h5p_image_hotspots.export', ['id' => $row['id']]) }}">
                                    Export .h5p
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Nothing authored for this chapter yet. Create it in the LMS app under
                                <strong>H5P content &rarr; Image hotspots</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
