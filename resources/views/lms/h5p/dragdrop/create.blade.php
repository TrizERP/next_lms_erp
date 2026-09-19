@extends('layout')
@section('container')
{{-- See the note at the top of index.blade.php: authoring and playback for
     this type live in the Next.js app, not in Blade. --}}
<div class="container-fluid py-5 text-center">
    <h5 class="mb-2">Drag and drop</h5>
    <p class="text-muted mb-0" style="font-size: 13px;">
        Open this activity in the LMS app under <strong>H5P content &rarr; Drag and drop</strong>.
    </p>
</div>
@endsection
