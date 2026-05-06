{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Photo Video Gallery</h4>
            </div>
        </div>
        <div class="card">
            <form action="{{ route('photo_video_gallary.store') }}" enctype="multipart/form-data" method="post">
                {{ method_field("POST") }}
                {{csrf_field()}}
                <div class="row">
                    {{ App\Helpers\SearchChain('4','multiple','grade,std,div') }}
                    <div class="col-md-4">
                        <label>Date</label>
                        <input type="text" name="date_" class="form-control mydatepicker" autocomplete="off" required="required">
                    </div>
                    <div class="col-md-4">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" required="required">
                    </div>
                    <div class="col-md-4">
                        <label>Album Title</label>
                        <input type="text" name="album_title" class="form-control" required="required">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Type</label>
                        <select name="type" class="form-control" onchange="changetype(this.value)" required="required">
                            <option value="">Select</option>
                            <option value="Photo">Photo</option>
                            <option value="Video">Video</option>
                        </select>
                    </div>
                    <div id="photo" class="col-md-4 form-group">
                        <label>Browse Files</label>
                        <input type="file" name="attachment[]" id="attachment[]" class="form-control" multiple="multiple" accept="image/*">
                        <span class="text-danger font-weight-bold">Note: Select multiple or single files from here.</span>
                    </div>
                    <div id="video" style="display: none;" class="col-md-4 form-group">
                        <label>Youtube Link</label>
                        <input type="text" name="attachment" id="attachment" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label></label><br>
                        <center>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >
                        </center>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="row">
                @if(isset($data['data']) && !empty($data['data']))
                @foreach($data['data'] as $album => $items)
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center d-flex flex-column">
                            @php
                            $thumb = collect($items)->firstWhere('type', 'Photo');
                            $count = count($items);
                            @endphp
                            @if($thumb)
                                <img src="{{ asset('storage/photo_video_gallary/' . $thumb->file_name) }}" class="card-img-top mb-3" alt="Thumbnail" style="height:150px; object-fit:cover; border-radius:5px;">
                            @else
                                <div class="mb-3" style="height:150px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border-radius:5px;"><i class="fa fa-folder fa-3x text-muted"></i></div>
                            @endif
                            <h5 class="card-title">{{ $album }}</h5>
                            <p class="card-text text-muted">{{ $count }} file{{ $count > 1 ? 's' : '' }}</p>
                            <button class="btn btn-primary mt-auto" onclick="openGallery('{{ $album }}')">View Gallery</button>
                        </div>
                    </div>
                </div>
                @endforeach
                @else
                <div class="col-12 text-center">
                    <p>No albums found.</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Gallery Modal -->
        <div class="modal fade" id="galleryModal" tabindex="-1" role="dialog" aria-labelledby="galleryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Gallery</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row" id="galleryContent">
                            <!-- Gallery items will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal -->
        <div class="modal fade" id="lightboxModal" tabindex="-1" role="dialog" aria-labelledby="lightboxModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body text-center p-0">
                        <img id="lightboxImage" src="" class="img-fluid" alt="Lightbox Image">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#grade').attr('required',true);
    $('#standard').attr('required',true);
    $('#division').attr('required',true);
});

function changetype(values) {
    if (values == 'Photo') {
        $("#video").hide();
        $("#photo").show();
        $("#attachment\\[\\]").attr("required", true);
    } else {
        $("#photo").hide();
        $("#video").show();
        $("#attachment").attr("required", true);
    }
}

function openGallery(album) {
    $('#modalTitle').text(album + ' Gallery');
    var content = '';
    var galleryData = @json($data['data']);
    if (galleryData[album]) {
        galleryData[album].forEach(function(it) {
            content += '<div class="col-md-4 col-sm-6 mb-3">';
            if (it.type == 'Photo') {
                content += '<img src="' + '{{ asset("storage/photo_video_gallary/") }}/' + it.file_name + '" class="img-fluid rounded" onclick="openLightbox(this.src)" style="cursor:pointer; height:200px; object-fit:cover;">';
            } else {
                var vid = it.file_name;
                if (vid && vid.includes('youtube.com') || vid.includes('youtu.be')) {
                    var embed = vid.replace('watch?v=', 'embed/').replace('youtu.be/', 'youtube.com/embed/');
                    content += '<iframe width="100%" height="200" src="' + embed + '" frameborder="0" allowfullscreen class="rounded"></iframe>';
                } else {
                    content += '<video width="100%" height="200" controls class="rounded"><source src="' + vid + '" type="video/mp4"></video>';
                }
            }
            content += '<p class="mt-2 mb-1"><strong>' + it.title + '</strong></p>';
            content += '<p class="text-muted small">' + it.std_name + ' - ' + it.div_name + ' | ' + new Date(it.date_).toLocaleDateString() + ' | ' + it.ai + '</p>';
            content += '<div class="mt-2">';
            content += '<a href="' + '{{ route("photo_video_gallary.edit", ":id") }}'.replace(':id', it.id) + '" class="btn btn-sm btn-info mr-1">Toggle Status</a>';
            content += '<form action="' + '{{ route("photo_video_gallary.destroy", ":id") }}'.replace(':id', it.id) + '" method="post" class="d-inline" onsubmit="return confirmDelete();">';
            content += '{{ csrf_field() }}';
            content += '{{ method_field("DELETE") }}';
            content += '<button type="submit" class="btn btn-sm btn-danger">Delete</button>';
            content += '</form>';
            content += '</div>';
            content += '</div>';
        });
    }
    $('#galleryContent').html(content);
    $('#galleryModal').modal('show');
}

function openLightbox(src) {
    $('#lightboxImage').attr('src', src);
    $('#lightboxModal').modal('show');
}

function confirmDelete() {
    return confirm('Are you sure you want to delete this item?');
}
</script>

@include('includes.footer')
@endsection
